<?php
// Model: all SQL for orders lives here.
// Tables used: co_orders, co_orderdetails, co_order_status_history,
// co_payment, co_products (stock) and co_stock_movements.
require_once __DIR__ . '/../core/db_class.php';

class OrderClass extends Database
{
    /**
     * Save a paid online order, all in ONE transaction:
     *   1. the order (invoice number INV-YYYY-00001 from the order_id)
     *   2. one co_orderdetails row per item, with the price fixed at checkout
     *   3. the first status-history row ("Pending")
     *   4. the payment row (status Success, with the Paystack reference)
     *   5. stock taken off each product, with a 'Sale' stock movement
     * If any step fails, nothing is saved.
     *
     * Note: ProductClass::adjustStock() is not used here because it starts
     * its own transaction, and in MySQL starting a second transaction
     * quietly commits the first one. The stock step is written out below.
     *
     * @param array $order    customer_id, order_total, delivery_method, delivery_address, notes
     * @param array $items    each: product_id, product_title, qty, unit_price
     * @param array $payment  reference, amount, method
     * @return array|false    ['order_id', 'invoice_no', 'shortages' => [product titles]]
     */
    public function createOnlineOrder(array $order, array $items, array $payment)
    {
        $this->conn->begin_transaction();
        try {
            // 1. The order. invoice_no must be unique and cannot be empty, so a
            //    temporary value is used until the order_id is known.
            $tempInvoice = 'TMP-' . $payment['reference'];
            $stmt = $this->conn->prepare(
                "INSERT INTO co_orders
                    (customer_id, invoice_no, order_status, order_source, order_total,
                     delivery_method, delivery_address, notes)
                 VALUES (?, ?, 'Pending', 'Online', ?, ?, ?, ?)"
            );
            $stmt->bind_param('isdsss', $order['customer_id'], $tempInvoice, $order['order_total'],
                              $order['delivery_method'], $order['delivery_address'], $order['notes']);
            $stmt->execute();
            $orderId = $stmt->insert_id;
            $stmt->close();

            $invoiceNo = sprintf('INV-%s-%05d', date('Y'), $orderId);
            $stmt = $this->conn->prepare('UPDATE co_orders SET invoice_no = ? WHERE order_id = ?');
            $stmt->bind_param('si', $invoiceNo, $orderId);
            $stmt->execute();
            $stmt->close();

            // 2. The items, with the price copied in so later price changes
            //    never change this order or its invoice
            $stmt = $this->conn->prepare(
                'INSERT INTO co_orderdetails (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $stmt->bind_param('iiid', $orderId, $item['product_id'], $item['qty'], $item['unit_price']);
                $stmt->execute();
            }
            $stmt->close();

            // 3. First line of the order's status timeline
            $stmt = $this->conn->prepare(
                "INSERT INTO co_order_status_history (order_id, status, note)
                 VALUES (?, 'Pending', 'Order placed and paid online')"
            );
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $stmt->close();

            // 4. The payment
            $stmt = $this->conn->prepare(
                "INSERT INTO co_payment (order_id, customer_id, amt, currency, payment_method, pay_reference, pay_status)
                 VALUES (?, ?, ?, 'GHS', ?, ?, 'Success')"
            );
            $stmt->bind_param('iidss', $orderId, $order['customer_id'], $payment['amount'],
                              $payment['method'], $payment['reference']);
            $stmt->execute();
            $stmt->close();

            // 5. Stock. The "AND product_stock >= ?" means the stock can never
            //    go below zero. If another customer bought the last one first,
            //    no row changes, and the product is listed as a shortage so
            //    staff can contact the customer. (The customer has already paid,
            //    so the order is still saved.)
            $shortages = [];
            $takeStock = $this->conn->prepare(
                'UPDATE co_products SET product_stock = product_stock - ?
                 WHERE product_id = ? AND product_stock >= ?'
            );
            $addMovement = $this->conn->prepare(
                "INSERT INTO co_stock_movements (product_id, change_qty, reason, order_id, note)
                 VALUES (?, ?, 'Sale', ?, 'Online order')"
            );
            foreach ($items as $item) {
                $takeStock->bind_param('iii', $item['qty'], $item['product_id'], $item['qty']);
                $takeStock->execute();

                if ($takeStock->affected_rows === 1) {
                    $change = -$item['qty'];
                    $addMovement->bind_param('iii', $item['product_id'], $change, $orderId);
                    $addMovement->execute();
                } else {
                    $shortages[] = $item['product_title'];
                }
            }
            $takeStock->close();
            $addMovement->close();

            // Leave a note on the order for staff if stock ran short
            if (!empty($shortages)) {
                $note = trim(($order['notes'] ?? '') . ' [STOCK SHORT at payment: ' . implode(', ', $shortages) . ']');
                $stmt = $this->conn->prepare('UPDATE co_orders SET notes = ? WHERE order_id = ?');
                $stmt->bind_param('si', $note, $orderId);
                $stmt->execute();
                $stmt->close();
            }

            $this->conn->commit();
            return ['order_id' => $orderId, 'invoice_no' => $invoiceNo, 'shortages' => $shortages];
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('createOnlineOrder failed (ref ' . $payment['reference'] . '): ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Save a sale made at the counter, all in ONE transaction: the order,
     * its items (with lenses and prescription if chosen), the first
     * status-history line, stock taken off, and the first payment if any.
     *
     * Unlike an online order (which is already paid for), an in-store sale
     * is refused if stock is short, because the customer is still at the
     * counter and staff can offer something else.
     *
     * @param array      $order    patient_id, customer_id, order_total, order_status, notes, created_by
     * @param array      $items    product_id, product_title, qty, unit_price, is_service,
     *                             lens_type_id, lens_coating_id, lens_price, prescription_id
     * @param array|null $payment  amount, method, reference (null when nothing is paid now)
     * @return array  ['order_id', 'invoice_no'] or ['error' => 'stock'|'reference'|'database', 'product' => title]
     */
    public function createInStoreOrder(array $order, array $items, ?array $payment): array
    {
        $this->conn->begin_transaction();
        try {
            $tempInvoice = 'TMP-' . bin2hex(random_bytes(8));
            $stmt = $this->conn->prepare(
                "INSERT INTO co_orders
                    (customer_id, patient_id, invoice_no, order_status, order_source, order_total,
                     delivery_method, created_by, notes)
                 VALUES (?, ?, ?, ?, 'In-store', ?, 'Pickup', ?, ?)"
            );
            $stmt->bind_param('iissdis', $order['customer_id'], $order['patient_id'], $tempInvoice,
                              $order['order_status'], $order['order_total'], $order['created_by'], $order['notes']);
            $stmt->execute();
            $orderId = $stmt->insert_id;
            $stmt->close();

            $invoiceNo = sprintf('INV-%s-%05d', date('Y'), $orderId);
            $stmt = $this->conn->prepare('UPDATE co_orders SET invoice_no = ? WHERE order_id = ?');
            $stmt->bind_param('si', $invoiceNo, $orderId);
            $stmt->execute();
            $stmt->close();

            $addItem = $this->conn->prepare(
                'INSERT INTO co_orderdetails
                    (order_id, product_id, qty, unit_price, lens_type_id, lens_coating_id, lens_price, prescription_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $takeStock = $this->conn->prepare(
                'UPDATE co_products SET product_stock = product_stock - ?
                 WHERE product_id = ? AND product_stock >= ?'
            );
            $addMovement = $this->conn->prepare(
                "INSERT INTO co_stock_movements (product_id, change_qty, reason, order_id, staff_id, note)
                 VALUES (?, ?, 'Sale', ?, ?, 'In-store sale')"
            );

            foreach ($items as $item) {
                $addItem->bind_param('iiidiidi', $orderId, $item['product_id'], $item['qty'], $item['unit_price'],
                                     $item['lens_type_id'], $item['lens_coating_id'], $item['lens_price'],
                                     $item['prescription_id']);
                $addItem->execute();

                if ((int) $item['is_service'] === 1) {
                    continue; // services (e.g. an exam fee) have no stock
                }

                $takeStock->bind_param('iii', $item['qty'], $item['product_id'], $item['qty']);
                $takeStock->execute();
                if ($takeStock->affected_rows !== 1) {
                    // Not enough on the shelf: undo the whole sale
                    $this->conn->rollback();
                    return ['error' => 'stock', 'product' => $item['product_title']];
                }
                $change = -$item['qty'];
                $addMovement->bind_param('iiii', $item['product_id'], $change, $orderId, $order['created_by']);
                $addMovement->execute();
            }
            $addItem->close();
            $takeStock->close();
            $addMovement->close();

            $note = $order['order_status'] === 'Delivered' ? 'Sold and handed over in the shop' : 'Ordered in the shop';
            $stmt = $this->conn->prepare(
                'INSERT INTO co_order_status_history (order_id, status, changed_by, note) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('isis', $orderId, $order['order_status'], $order['created_by'], $note);
            $stmt->execute();
            $stmt->close();

            if ($payment !== null) {
                $stmt = $this->conn->prepare(
                    "INSERT INTO co_payment
                        (order_id, customer_id, amt, currency, payment_method, pay_reference, pay_status, received_by)
                     VALUES (?, ?, ?, 'GHS', ?, ?, 'Success', ?)"
                );
                $stmt->bind_param('iidssi', $orderId, $order['customer_id'], $payment['amount'],
                                  $payment['method'], $payment['reference'], $order['created_by']);
                $stmt->execute();
                $stmt->close();
            }

            $this->conn->commit();
            return ['order_id' => $orderId, 'invoice_no' => $invoiceNo];
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            // 1062 = duplicate value in a UNIQUE column (here: the payment reference)
            if ($ex->getCode() === 1062) {
                return ['error' => 'reference'];
            }
            log_error('createInStoreOrder failed: ' . $ex->getMessage());
            return ['error' => 'database'];
        }
    }

    /**
     * Has this Paystack reference already been saved? If so, its order_id.
     * This stops the same payment creating two orders (for example if the
     * customer refreshes the page after paying).
     */
    public function findOrderIdByReference(string $reference): ?int
    {
        $stmt = $this->conn->prepare('SELECT order_id FROM co_payment WHERE pay_reference = ?');
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['order_id'] : null;
    }

    /**
     * One order with its items, only if it belongs to this customer.
     * @return array|false  the order row with an 'items' list
     */
    public function getCustomerOrder(int $orderId, int $customerId)
    {
        $stmt = $this->conn->prepare(
            'SELECT o.*, p.pay_reference, p.payment_method, p.amt AS amount_paid
             FROM co_orders o
             LEFT JOIN co_payment p ON p.order_id = o.order_id AND p.pay_status = \'Success\'
             WHERE o.order_id = ? AND o.customer_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return false;
        }

        $stmt = $this->conn->prepare(
            'SELECT d.qty, d.unit_price, d.lens_price, p.product_title, p.product_image
             FROM co_orderdetails d
             JOIN co_products p ON p.product_id = d.product_id
             WHERE d.order_id = ?
             ORDER BY d.detail_id'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $order;
    }


    // =================================================================
    // STAFF: Orders and Lab
    // =================================================================

    // The buyer's name and phone come from the online account (co_customer)
    // or, for walk-in sales, from the clinic record (co_patients).
    // amount_paid adds up only successful payments, so it is always correct.
    private const STAFF_ORDER_COLUMNS =
        "o.order_id, o.invoice_no, o.order_date, o.order_status, o.order_source,
         o.order_total, o.delivery_method,
         COALESCE(CONCAT(c.customer_fname, ' ', c.customer_lname),
                  CONCAT(pt.patient_fname, ' ', pt.patient_lname), 'Walk-in') AS buyer_name,
         COALESCE(c.customer_contact, pt.patient_contact) AS buyer_contact,
         (SELECT COALESCE(SUM(py.amt), 0) FROM co_payment py
           WHERE py.order_id = o.order_id AND py.pay_status = 'Success') AS amount_paid,
         (SELECT COALESCE(SUM(d.qty), 0) FROM co_orderdetails d
           WHERE d.order_id = o.order_id) AS item_count,
         (SELECT COUNT(*) FROM co_orderdetails d
           WHERE d.order_id = o.order_id AND d.lens_type_id IS NOT NULL) AS lens_lines";

    /**
     * Build the WHERE part of the staff order list from the filters.
     * @param array $f  status ('' = all, 'lab' = Pending/Cutting/Fitting), source, q
     */
    private function staffWhere(array $f, string &$types, array &$params): string
    {
        $sql = ' WHERE 1 = 1';

        if ($f['status'] === 'lab') {
            $sql .= " AND o.order_status IN ('Pending', 'Cutting', 'Fitting')";
        } elseif ($f['status'] !== '') {
            $sql .= ' AND o.order_status = ?';
            $types .= 's';
            $params[] = $f['status'];
        }
        if ($f['source'] !== '') {
            $sql .= ' AND o.order_source = ?';
            $types .= 's';
            $params[] = $f['source'];
        }
        if ($f['q'] !== '') {
            // Search the invoice number, the buyer's name and their phone
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= " AND (o.invoice_no LIKE ?
                          OR CONCAT(c.customer_fname, ' ', c.customer_lname) LIKE ?
                          OR CONCAT(pt.patient_fname, ' ', pt.patient_lname) LIKE ?
                          OR c.customer_contact LIKE ? OR pt.patient_contact LIKE ?)";
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return $sql;
    }

    /** One page of orders for the staff list, newest first. */
    public function getOrders(array $filters, int $limit, int $offset): array
    {
        $types  = '';
        $params = [];
        $where  = $this->staffWhere($filters, $types, $params);

        $stmt = $this->conn->prepare(
            'SELECT ' . self::STAFF_ORDER_COLUMNS . '
             FROM co_orders o
             LEFT JOIN co_customer c  ON c.customer_id = o.customer_id
             LEFT JOIN co_patients pt ON pt.patient_id = o.patient_id'
            . $where .
            ' ORDER BY o.order_date DESC, o.order_id DESC
              LIMIT ? OFFSET ?'
        );
        $types   .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** How many orders match the filters (for the page numbers). */
    public function countOrders(array $filters): int
    {
        $types  = '';
        $params = [];
        $where  = $this->staffWhere($filters, $types, $params);

        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total
             FROM co_orders o
             LEFT JOIN co_customer c  ON c.customer_id = o.customer_id
             LEFT JOIN co_patients pt ON pt.patient_id = o.patient_id' . $where
        );
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * How many orders are in each status, for the tabs above the list.
     * @return array status => count
     */
    public function countByStatus(): array
    {
        $result = $this->conn->query('SELECT order_status, COUNT(*) AS total FROM co_orders GROUP BY order_status');
        $counts = [];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $counts[$row['order_status']] = (int) $row['total'];
        }
        return $counts;
    }

    /**
     * One order with everything staff need: buyer, items (with lens
     * choices), payments and the status timeline.
     * @return array|false
     */
    public function getOrderForStaff(int $orderId)
    {
        $stmt = $this->conn->prepare(
            'SELECT ' . self::STAFF_ORDER_COLUMNS . ',
                    o.delivery_address, o.notes, o.customer_id, o.patient_id,
                    COALESCE(c.customer_email, pt.patient_email) AS buyer_email,
                    pt.folder_no
             FROM co_orders o
             LEFT JOIN co_customer c  ON c.customer_id = o.customer_id
             LEFT JOIN co_patients pt ON pt.patient_id = o.patient_id
             WHERE o.order_id = ?'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return false;
        }

        // Items, with the names of any lens type and coating
        $stmt = $this->conn->prepare(
            'SELECT d.product_id, d.qty, d.unit_price, d.lens_price, d.prescription_id,
                    p.product_title, lt.lens_name AS lens_type, lc.lens_name AS lens_coating
             FROM co_orderdetails d
             JOIN co_products p ON p.product_id = d.product_id
             LEFT JOIN co_lens_options lt ON lt.lens_id = d.lens_type_id
             LEFT JOIN co_lens_options lc ON lc.lens_id = d.lens_coating_id
             WHERE d.order_id = ?
             ORDER BY d.detail_id'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Every payment, including failed ones, so staff can see what happened
        $stmt = $this->conn->prepare(
            'SELECT py.amt, py.payment_method, py.pay_reference, py.pay_status, py.payment_date,
                    s.staff_fname, s.staff_lname
             FROM co_payment py
             LEFT JOIN co_staff s ON s.staff_id = py.received_by
             WHERE py.order_id = ?
             ORDER BY py.payment_date'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order['payments'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // The status timeline, oldest first
        $stmt = $this->conn->prepare(
            'SELECT h.status, h.note, h.changed_at, s.staff_fname, s.staff_lname
             FROM co_order_status_history h
             LEFT JOIN co_staff s ON s.staff_id = h.changed_by
             WHERE h.order_id = ?
             ORDER BY h.changed_at, h.history_id'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order['history'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $order;
    }

    /**
     * Change an order's status and add a line to its timeline, in one
     * transaction. When $restock is true (a cancelled order), the items
     * go back into stock with a 'Return' stock movement.
     * @return int|false  how many items went back into stock, or false if it failed
     */
    public function changeStatus(int $orderId, string $newStatus, int $staffId, ?string $note, bool $restock)
    {
        $returned = 0;
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('UPDATE co_orders SET order_status = ? WHERE order_id = ?');
            $stmt->bind_param('si', $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare(
                'INSERT INTO co_order_status_history (order_id, status, changed_by, note) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('isis', $orderId, $newStatus, $staffId, $note);
            $stmt->execute();
            $stmt->close();

            if ($restock) {
                // Put back only what was really taken out of stock for this
                // order (its 'Sale' movements), so a stock shortage at payment
                // time is never "returned" twice
                $stmt = $this->conn->prepare(
                    "SELECT product_id, -SUM(change_qty) AS qty
                     FROM co_stock_movements
                     WHERE order_id = ? AND reason = 'Sale'
                     GROUP BY product_id"
                );
                $stmt->bind_param('i', $orderId);
                $stmt->execute();
                $taken = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $putBack = $this->conn->prepare(
                    'UPDATE co_products SET product_stock = product_stock + ? WHERE product_id = ?'
                );
                $addMovement = $this->conn->prepare(
                    "INSERT INTO co_stock_movements (product_id, change_qty, reason, order_id, staff_id, note)
                     VALUES (?, ?, 'Return', ?, ?, 'Order cancelled')"
                );
                foreach ($taken as $row) {
                    $qty = (int) $row['qty'];
                    if ($qty <= 0) {
                        continue;
                    }
                    $putBack->bind_param('ii', $qty, $row['product_id']);
                    $putBack->execute();
                    $addMovement->bind_param('iiii', $row['product_id'], $qty, $orderId, $staffId);
                    $addMovement->execute();
                    $returned += $qty;
                }
                $putBack->close();
                $addMovement->close();
            }

            $this->conn->commit();
            return $returned;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('changeStatus failed for order ' . $orderId . ': ' . $ex->getMessage());
            return false;
        }
    }
}
