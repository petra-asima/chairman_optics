<?php
// Controller: the rules for the shopping cart.
//  - only products that are in the shop and in stock can be added
//  - a quantity can never be more than the stock, or more than MAX_QTY
//  - every change is checked against the owner (see cart_owner() in core.php)
require_once __DIR__ . '/../classes/CartClass.php';
require_once __DIR__ . '/../classes/ProductClass.php';

class CartController
{
    // Most of one product a customer can buy online in one order.
    // Bigger orders are best handled in the shop. [Confirm with the owner.]
    public const MAX_QTY = 10;

    private $cart;
    private $product;

    public function __construct()
    {
        $this->cart    = new CartClass();
        $this->product = new ProductClass();
    }

    /** A guest who has never added anything has no cart code yet, so no cart. */
    private function hasCart(array $owner): bool
    {
        return $owner['customer_id'] !== null || $owner['guest_key'] !== null;
    }

    /** The most of a product a customer may have in the cart right now. */
    private function maxQty(int $stock): int
    {
        return max(0, min($stock, self::MAX_QTY));
    }

    /**
     * Add a product to the cart (or add to the quantity already there).
     * @return array ['success' => bool, 'message' => text]
     */
    public function addToCart(array $owner, int $productId, string $qtyText): array
    {
        // getShopProductById only returns products customers may buy
        $product = $this->product->getShopProductById($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Sorry, that product is no longer available.'];
        }

        $stock = (int) $product['product_stock'];
        if ($stock < 1) {
            return ['success' => false, 'message' => 'Sorry, ' . $product['product_title'] . ' is out of stock.'];
        }

        $qty = filter_var($qtyText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_QTY]]);
        if ($qty === false) {
            return ['success' => false, 'message' => 'Please choose a quantity from 1 to ' . self::MAX_QTY . '.'];
        }

        $max      = $this->maxQty($stock);
        $existing = $this->cart->findItem($owner, $productId);

        if ($existing) {
            $wanted = (int) $existing['qty'] + $qty;
            $newQty = min($wanted, $max);

            if ((int) $existing['qty'] >= $max) {
                return ['success' => false,
                        'message' => 'You already have the most we can sell of ' . $product['product_title'] . ' (' . $max . ').'];
            }
            if (!$this->cart->updateQty($owner, (int) $existing['cart_id'], $newQty)) {
                return ['success' => false, 'message' => "We couldn't update your cart. Please try again."];
            }
        } else {
            $newQty = min($qty, $max);
            $wanted = $qty;
            if (!$this->cart->addItem($owner, $productId, $newQty)) {
                return ['success' => false, 'message' => "We couldn't add that to your cart. Please try again."];
            }
        }

        // Tell the customer if they got fewer than they asked for
        if ($newQty < $wanted) {
            return ['success' => true,
                    'message' => 'Only ' . $max . ' of ' . $product['product_title'] . ' can be bought, so your cart now has ' . $newQty . '.'];
        }
        return ['success' => true, 'message' => $product['product_title'] . ' was added to your cart.'];
    }

    /**
     * Change a line's quantity. A quantity of 0 removes the line.
     */
    public function updateQty(array $owner, int $cartId, string $qtyText): array
    {
        $item = $this->hasCart($owner) ? $this->cart->getItem($owner, $cartId) : false;
        if (!$item) {
            return ['success' => false, 'message' => 'That item is no longer in your cart.'];
        }

        $qty = filter_var($qtyText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
        if ($qty === false) {
            return ['success' => false, 'message' => 'Please enter a whole number for the quantity.'];
        }
        if ($qty === 0) {
            return $this->removeItem($owner, $cartId);
        }

        $max = $this->maxQty((int) $item['product_stock']);
        if ($max === 0) {
            return ['success' => false, 'message' => $item['product_title'] . ' is out of stock. Please remove it from your cart.'];
        }

        $newQty = min($qty, $max);
        if (!$this->cart->updateQty($owner, $cartId, $newQty)) {
            return ['success' => false, 'message' => "We couldn't update your cart. Please try again."];
        }

        if ($newQty < $qty) {
            return ['success' => true, 'message' => 'Only ' . $max . ' of ' . $item['product_title'] . ' can be bought, so the quantity is now ' . $newQty . '.'];
        }
        return ['success' => true, 'message' => 'Your cart has been updated.'];
    }

    public function removeItem(array $owner, int $cartId): array
    {
        $item = $this->hasCart($owner) ? $this->cart->getItem($owner, $cartId) : false;
        if (!$item) {
            return ['success' => false, 'message' => 'That item is no longer in your cart.'];
        }
        if (!$this->cart->removeItem($owner, $cartId)) {
            return ['success' => false, 'message' => "We couldn't remove that item. Please try again."];
        }
        return ['success' => true, 'message' => $item['product_title'] . ' was removed from your cart.'];
    }

    /**
     * Everything the cart page needs. Each line gets:
     *   available  - false if the product was hidden or sold out after it was added
     *   max_qty    - the most the customer can choose
     *   line_total - price x quantity (0 when not available)
     *   problem    - a message to show on the line, or ''
     * Lines that are not available are left out of the subtotal.
     * @return array items, subtotal, item_count, has_problems
     */
    public function getCartSummary(array $owner): array
    {
        $items = $this->hasCart($owner) ? $this->cart->getCart($owner) : [];

        $subtotal    = 0.0;
        $itemCount   = 0;
        $hasProblems = false;

        foreach ($items as &$item) {
            $stock = (int) $item['product_stock'];
            $qty   = (int) $item['qty'];

            $item['available']  = (int) $item['is_active'] === 1 && (int) $item['is_service'] === 0 && $stock > 0;
            $item['max_qty']    = $this->maxQty($stock);
            $item['line_total'] = 0.0;
            $item['problem']    = '';

            if (!$item['available']) {
                $item['problem'] = (int) $item['is_active'] !== 1
                    ? 'This product is no longer sold online. Please remove it.'
                    : 'Out of stock. Please remove it.';
                $hasProblems = true;
                continue;
            }
            if ($qty > $item['max_qty']) {
                $item['problem'] = 'Only ' . $item['max_qty'] . ' available. Please lower the quantity.';
                $hasProblems = true;
            }

            $item['line_total'] = (float) $item['product_price'] * $qty;
            $subtotal  += $item['line_total'];
            $itemCount += $qty;
        }
        unset($item); // always unset the reference after a foreach by reference

        return [
            'items'        => $items,
            'subtotal'     => $subtotal,
            'item_count'   => $itemCount,
            'has_problems' => $hasProblems,
        ];
    }

    /** Number of items for the header badge (0 for a guest with no cart). */
    public function countItems(array $owner): int
    {
        return $this->hasCart($owner) ? $this->cart->countItems($owner) : 0;
    }

    /** Move a guest's cart into the customer's account after they log in. */
    public function mergeGuestCart(string $guestKey, int $customerId): int
    {
        return $this->cart->mergeGuestCart($guestKey, $customerId);
    }
}
