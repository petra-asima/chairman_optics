<?php
// Model: all SQL for the co_cart table lives here.
// Column names are kept from the shoppn lab: p_id (product), c_id
// (customer) and ip_add. ip_add now holds a guest's random cart code
// instead of their IP address (see cart_owner() in core.php).
//
// Every method takes an $owner array from cart_owner():
//   ['customer_id' => 5,    'guest_key' => null]    a logged-in customer
//   ['customer_id' => null, 'guest_key' => 'a1b2…'] a guest
// The owner is always part of the WHERE, so nobody can change or
// remove a row in someone else's cart, even by changing a cart_id.
require_once __DIR__ . '/../core/db_class.php';

class CartClass extends Database
{
    /**
     * The WHERE condition that limits a query to one owner's cart.
     * @return array [sql, types, params]
     */
    private function ownerWhere(array $owner): array
    {
        if ($owner['customer_id'] !== null) {
            return ['ct.c_id = ?', 'i', [$owner['customer_id']]];
        }
        return ['ct.c_id IS NULL AND ct.ip_add = ?', 's', [$owner['guest_key']]];
    }

    /**
     * Every item in the cart, with the product's current details.
     * The price is read live from co_products, so it is always up to date.
     * @return array
     */
    public function getCart(array $owner): array
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare(
            'SELECT ct.cart_id, ct.qty, ct.added_at,
                    p.product_id, p.product_title, p.product_price, p.product_image,
                    p.product_colour, p.product_stock, p.is_active, p.is_service,
                    b.brand_name
             FROM co_cart ct
             JOIN co_products p ON p.product_id = ct.p_id
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand
             WHERE ' . $where . '
             ORDER BY ct.added_at, ct.cart_id'
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Total number of items (adds up the quantities), for the header badge. */
    public function countItems(array $owner): int
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(ct.qty), 0) AS total FROM co_cart ct WHERE ' . $where);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * The cart row for one product, if it is already in the cart.
     * Only plain items (no lenses) are matched, so a frame bought with
     * prescription lenses later is kept as its own line.
     * @return array|false
     */
    public function findItem(array $owner, int $productId)
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare(
            'SELECT ct.cart_id, ct.qty FROM co_cart ct
             WHERE ' . $where . ' AND ct.p_id = ? AND ct.lens_type_id IS NULL'
        );
        $types   .= 'i';
        $params[] = $productId;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** One row of this owner's cart. @return array|false */
    public function getItem(array $owner, int $cartId)
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare(
            'SELECT ct.cart_id, ct.qty, p.product_id, p.product_title, p.product_stock
             FROM co_cart ct
             JOIN co_products p ON p.product_id = ct.p_id
             WHERE ' . $where . ' AND ct.cart_id = ?'
        );
        $types   .= 'i';
        $params[] = $cartId;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** Put a new line in the cart. @return bool */
    public function addItem(array $owner, int $productId, int $qty): bool
    {
        try {
            $stmt = $this->conn->prepare('INSERT INTO co_cart (p_id, ip_add, c_id, qty) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isii', $productId, $owner['guest_key'], $owner['customer_id'], $qty);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('addItem failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Change the quantity of a line in this owner's cart. */
    public function updateQty(array $owner, int $cartId, int $qty): bool
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        // "ct" is the table's short name, so the shared WHERE works here too
        $stmt = $this->conn->prepare('UPDATE co_cart ct SET ct.qty = ? WHERE ' . $where . ' AND ct.cart_id = ?');

        // The values must be in the same order as the ? marks: qty, owner, cart_id
        $allTypes  = 'i' . $types . 'i';
        $allValues = array_merge([$qty], $params, [$cartId]);
        $stmt->bind_param($allTypes, ...$allValues);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Remove a line from this owner's cart. */
    public function removeItem(array $owner, int $cartId): bool
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare('DELETE ct FROM co_cart ct WHERE ' . $where . ' AND ct.cart_id = ?');
        $types   .= 'i';
        $params[] = $cartId;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Empty the whole cart (used after a successful order in Module 4c). */
    public function clearCart(array $owner): bool
    {
        [$where, $types, $params] = $this->ownerWhere($owner);

        $stmt = $this->conn->prepare('DELETE ct FROM co_cart ct WHERE ' . $where);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * When a guest logs in, move their cart into their account.
     * If the account's cart already has the same product, the quantities
     * are added together on one line instead of making two lines.
     * Done as one transaction, so the cart is never left half-moved.
     * @return int  how many guest lines were moved or merged
     */
    public function mergeGuestCart(string $guestKey, int $customerId): int
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT cart_id, p_id, qty FROM co_cart
                 WHERE c_id IS NULL AND ip_add = ? AND lens_type_id IS NULL'
            );
            $stmt->bind_param('s', $guestKey);
            $stmt->execute();
            $guestRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($guestRows as $row) {
                $existing = $this->findItem(['customer_id' => $customerId, 'guest_key' => null], (int) $row['p_id']);

                if ($existing) {
                    // Same product already in the account: add the quantities
                    $stmt = $this->conn->prepare('UPDATE co_cart SET qty = qty + ? WHERE cart_id = ?');
                    $stmt->bind_param('ii', $row['qty'], $existing['cart_id']);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $this->conn->prepare('DELETE FROM co_cart WHERE cart_id = ?');
                    $stmt->bind_param('i', $row['cart_id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // New to the account: hand the line over to the customer
                    $stmt = $this->conn->prepare('UPDATE co_cart SET c_id = ?, ip_add = NULL WHERE cart_id = ?');
                    $stmt->bind_param('ii', $customerId, $row['cart_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $this->conn->commit();
            return count($guestRows);
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('mergeGuestCart failed: ' . $ex->getMessage());
            return 0;
        }
    }
}
