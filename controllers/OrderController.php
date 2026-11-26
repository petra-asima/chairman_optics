<?php
// Controller: checkout and online payment with Paystack (test mode).
//
// The steps:
//  1. startCheckout()   - check the cart and the delivery details, work out
//                         the total ON THE SERVER, and ask Paystack for a
//                         payment page. The action saves a snapshot of the
//                         order in the session and sends the customer there.
//  2. completePayment() - when Paystack sends the customer back, ask
//                         Paystack directly whether they paid the right
//                         amount, then create the order.
// The browser is never trusted with the amount: it is worked out here,
// and checked again against what Paystack says was paid.
require_once __DIR__ . '/../core/paystack.php';
require_once __DIR__ . '/../classes/OrderClass.php';
require_once __DIR__ . '/../classes/CartClass.php';
require_once __DIR__ . '/CartController.php';

class OrderController
{
    // How an online order reaches the customer (must match the ENUM in co_orders)
    public const DELIVERY_METHODS = ['Pickup', 'Delivery'];

    // Paystack's payment channels, matched to the methods in co_payment
    private const PAYSTACK_CHANNELS = [
        'mobile_money'  => 'Mobile Money',
        'card'          => 'Card',
        'bank'          => 'Bank Transfer',
        'bank_transfer' => 'Bank Transfer',
    ];

    private $order;
    private $cart;
    private $cartController;

    public function __construct()
    {
        $this->order          = new OrderClass();
        $this->cart           = new CartClass();
        $this->cartController = new CartController();
    }

    /** Can customers pay online right now? (Is a Paystack test key set up?) */
    public function paymentsReady(): bool
    {
        return paystack_ready();
    }

    /**
     * Check the checkout form.
     * @return array field name => error message (empty when all is fine)
     */
    public function validateCheckout(array $data): array
    {
        $errors = [];

        if (!in_array($data['delivery_method'], self::DELIVERY_METHODS, true)) {
            $errors['delivery_method'] = 'Please choose pickup or delivery.';
        }
        if ($data['delivery_method'] === 'Delivery'
            && (strlen($data['delivery_address']) < 5 || strlen($data['delivery_address']) > 255)) {
            $errors['delivery_address'] = 'Please enter the delivery address (area, street and a landmark).';
        }
        if (strlen($data['notes']) > 255) {
            $errors['notes'] = 'Notes can be up to 255 characters.';
        }
        return $errors;
    }

    /**
     * Step 1: check everything, then ask Paystack for a payment page.
     * @param array $customer  customer_id and customer_email
     * @return array success: ['success' => true, 'payment_url' => ..., 'checkout' => snapshot]
     *               failure: ['success' => false, 'errors' => [...]]
     */
    public function startCheckout(array $customer, array $data, string $callbackUrl): array
    {
        if (!paystack_ready()) {
            return ['success' => false, 'errors' => ['form' => 'Online payment is not available right now. Please try again later.']];
        }

        $errors = $this->validateCheckout($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $owner   = ['customer_id' => (int) $customer['customer_id'], 'guest_key' => null];
        $summary = $this->cartController->getCartSummary($owner);

        if (empty($summary['items'])) {
            return ['success' => false, 'errors' => ['form' => 'Your cart is empty.']];
        }
        if ($summary['has_problems']) {
            return ['success' => false, 'errors' => ['form' => 'Some items in your cart need attention. Please check your cart.']];
        }

        // A snapshot of exactly what is being bought, at today's prices
        $items = [];
        foreach ($summary['items'] as $item) {
            $items[] = [
                'product_id'    => (int) $item['product_id'],
                'product_title' => $item['product_title'],
                'qty'           => (int) $item['qty'],
                'unit_price'    => (float) $item['product_price'],
            ];
        }

        // Our own unique reference, e.g. CO20261125143005A1B2C3
        $reference = 'CO' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
        $total     = round($summary['subtotal'], 2);

        $reply = paystack_initialize($customer['customer_email'], $this->toPesewas($total), $reference, $callbackUrl);
        if (!$reply['ok'] || empty($reply['data']['authorization_url'])) {
            return ['success' => false, 'errors' => ['form' => "We couldn't start the payment. Please try again in a moment."]];
        }

        return [
            'success'     => true,
            'payment_url' => $reply['data']['authorization_url'],
            'checkout'    => [
                'reference'        => $reference,
                'customer_id'      => (int) $customer['customer_id'],
                'order_total'      => $total,
                'items'            => $items,
                'delivery_method'  => $data['delivery_method'],
                'delivery_address' => $data['delivery_method'] === 'Delivery' ? $data['delivery_address'] : null,
                'notes'            => $data['notes'] !== '' ? $data['notes'] : null,
            ],
        ];
    }

    /**
     * Step 2: the customer is back from Paystack. Check the payment with
     * Paystack, then save the order.
     * @param array      $customer  customer_id and customer_email
     * @param array|null $checkout  the snapshot saved in step 1 (null if the session was lost)
     * @return array ['success' => true, 'order_id' => N, 'message' => ...]
     *               or ['success' => false, 'message' => ...]
     */
    public function completePayment(string $reference, array $customer, ?array $checkout): array
    {
        // Already saved? (e.g. the page was refreshed) Then just show that order.
        $existing = $this->order->findOrderIdByReference($reference);
        if ($existing !== null) {
            return ['success' => true, 'order_id' => $existing, 'message' => 'Your order has been placed.'];
        }

        $reply = paystack_verify($reference);
        if (!$reply['ok']) {
            return ['success' => false, 'message' => "We couldn't confirm your payment with Paystack. If money was taken, please contact us with reference " . $reference . '.'];
        }

        return $this->saveVerifiedOrder($reference, $reply['data'], $customer, $checkout);
    }

    /**
     * Check Paystack's answer and save the order.
     * Kept separate from completePayment() so it can be tested with a
     * sample Paystack answer, without making a real payment.
     * @param array $paystack  the 'data' part of Paystack's verify reply
     */
    public function saveVerifiedOrder(string $reference, array $paystack, array $customer, ?array $checkout): array
    {
        $customerId = (int) $customer['customer_id'];

        if (($paystack['status'] ?? '') !== 'success') {
            return ['success' => false, 'message' => 'The payment was not completed, so no order was placed. You have not been charged.'];
        }

        // The payment must be in cedis, for this reference, from this customer
        $paidEmail = strtolower($paystack['customer']['email'] ?? '');
        if (($paystack['currency'] ?? '') !== 'GHS'
            || ($paystack['reference'] ?? '') !== $reference
            || $paidEmail !== strtolower($customer['customer_email'])) {
            log_error('Payment ' . $reference . ' does not match customer ' . $customerId);
            return ['success' => false, 'message' => 'This payment does not match your account. Please contact us with reference ' . $reference . '.'];
        }

        // Use the snapshot from step 1. If the session was lost, rebuild
        // the order from the cart, which is still saved in the database.
        if ($checkout === null || $checkout['reference'] !== $reference || (int) $checkout['customer_id'] !== $customerId) {
            $checkout = $this->rebuildCheckout($customerId);
            if ($checkout === null) {
                log_error('Payment ' . $reference . ' succeeded but the order could not be rebuilt.');
                return ['success' => false, 'message' => 'Your payment was received, but we could not find your order details. Please contact us with reference ' . $reference . '.'];
            }
        }

        // The amount paid must be exactly the order total
        $paidPesewas = (int) ($paystack['amount'] ?? 0);
        if ($paidPesewas !== $this->toPesewas($checkout['order_total'])) {
            log_error('Payment ' . $reference . ' amount ' . $paidPesewas . ' does not match order total ' . $checkout['order_total']);
            return ['success' => false, 'message' => 'Your payment was received, but the amount did not match your order. Please contact us with reference ' . $reference . '.'];
        }

        $method = self::PAYSTACK_CHANNELS[$paystack['channel'] ?? ''] ?? 'Card';

        $saved = $this->order->createOnlineOrder(
            [
                'customer_id'      => $customerId,
                'order_total'      => $checkout['order_total'],
                'delivery_method'  => $checkout['delivery_method'],
                'delivery_address' => $checkout['delivery_address'],
                'notes'            => $checkout['notes'],
            ],
            $checkout['items'],
            ['reference' => $reference, 'amount' => $paidPesewas / 100, 'method' => $method]
        );

        if ($saved === false) {
            return ['success' => false, 'message' => 'Your payment was received, but saving the order failed. Please contact us with reference ' . $reference . '.'];
        }

        // The order is saved, so the cart can be emptied
        $this->cart->clearCart(['customer_id' => $customerId, 'guest_key' => null]);

        if (!empty($saved['shortages'])) {
            log_error('Order ' . $saved['invoice_no'] . ' paid with stock short: ' . implode(', ', $saved['shortages']));
        }

        return ['success' => true, 'order_id' => $saved['order_id'],
                'message' => 'Thank you! Your payment was successful and your order ' . $saved['invoice_no'] . ' has been placed.'];
    }

    /** One of this customer's orders, with its items. @return array|false */
    public function getCustomerOrder(int $orderId, int $customerId)
    {
        return $this->order->getCustomerOrder($orderId, $customerId);
    }

    // =================================================================
    // STAFF: Orders and Lab
    // =================================================================

    // The normal journey of an order. Steps may be skipped (a pair of
    // sunglasses needs no cutting or fitting), but never gone back on.
    public const STATUS_FLOW = ['Pending', 'Cutting', 'Fitting', 'Ready', 'Delivered'];

    // Every status, for the tabs and badges (must match the ENUM in co_orders)
    public const ORDER_STATUSES = ['Pending', 'Cutting', 'Fitting', 'Ready', 'Delivered', 'Cancelled'];

    // The only statuses a Lab Technician may set. Handing over (Delivered)
    // and cancelling are done at the front desk. [Confirm with the owner.]
    public const LAB_STATUSES = ['Cutting', 'Fitting', 'Ready'];

    public const ORDER_SOURCES = ['Online', 'In-store'];

    // Orders on each page of the staff list
    public const STAFF_PER_PAGE = 25;

    /**
     * Read and clean the order list filters from the address bar.
     * A Lab Technician starts on the "In the lab" tab.
     */
    public function cleanStaffFilters(array $get, string $role): array
    {
        $default = $role === 'Lab Technician' ? 'lab' : '';
        $status  = (string) ($get['status'] ?? $default);
        if ($status !== '' && $status !== 'lab' && !in_array($status, self::ORDER_STATUSES, true)) {
            $status = '';
        }

        $source = (string) ($get['source'] ?? '');
        if (!in_array($source, self::ORDER_SOURCES, true)) {
            $source = '';
        }

        return [
            'status' => $status,
            'source' => $source,
            'q'      => mb_substr(trim((string) ($get['q'] ?? '')), 0, 100),
            'page'   => max(1, (int) ($get['page'] ?? 1)),
        ];
    }

    /** One page of orders and the page numbers. */
    public function getStaffOrderPage(array $filters): array
    {
        $total = $this->order->countOrders($filters);
        $pages = max(1, (int) ceil($total / self::STAFF_PER_PAGE));
        $page  = min($filters['page'], $pages);

        $orders = $this->order->getOrders($filters, self::STAFF_PER_PAGE, ($page - 1) * self::STAFF_PER_PAGE);
        return ['orders' => $orders, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function countByStatus(): array
    {
        return $this->order->countByStatus();
    }

    /** @return array|false */
    public function getOrderForStaff(int $orderId)
    {
        return $this->order->getOrderForStaff($orderId);
    }

    /**
     * The statuses this role may move this order to next.
     * The same list is used to show the buttons AND to check the action,
     * so a role can never do something its buttons do not offer.
     */
    public function allowedStatuses(array $order, string $role): array
    {
        $current = $order['order_status'];

        // A delivered or cancelled order is finished
        if ($current === 'Delivered' || $current === 'Cancelled') {
            return [];
        }

        // Every step after the current one (steps may be skipped)
        $position = array_search($current, self::STATUS_FLOW, true);
        $next     = array_slice(self::STATUS_FLOW, $position + 1);

        if ($role === 'Lab Technician') {
            return array_values(array_intersect($next, self::LAB_STATUSES));
        }

        // Receptionist and Admin may also cancel
        $next[] = 'Cancelled';
        return $next;
    }

    /**
     * Move an order to a new status.
     * @return array ['success' => bool, 'message' => text]
     */
    public function changeStatus(int $orderId, string $newStatus, string $note, int $staffId, string $role): array
    {
        $order = $this->order->getOrderForStaff($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'That order was not found.'];
        }

        if (!in_array($newStatus, $this->allowedStatuses($order, $role), true)) {
            return ['success' => false,
                    'message' => 'Order ' . $order['invoice_no'] . ' cannot be moved from ' . $order['order_status'] . ' to ' . $newStatus . ' by your role.'];
        }
        if (strlen($note) > 255) {
            return ['success' => false, 'message' => 'The note can be up to 255 characters.'];
        }

        // A cancelled order's items go back into stock
        $restock = $newStatus === 'Cancelled';
        $returned = $this->order->changeStatus($orderId, $newStatus, $staffId, $note !== '' ? $note : null, $restock);
        if ($returned === false) {
            return ['success' => false, 'message' => "We couldn't change the status. Please try again."];
        }

        require_once __DIR__ . '/../classes/AuditClass.php';
        (new AuditClass())->log($staffId, 'ORDER_STATUS',
            $order['invoice_no'] . ': ' . $order['order_status'] . ' to ' . $newStatus . ($note !== '' ? ' (' . $note . ')' : ''),
            'co_orders', $orderId);

        // Extra reminders for the member of staff
        $message = $order['invoice_no'] . ' is now ' . $newStatus . '.';
        $balance = (float) $order['order_total'] - (float) $order['amount_paid'];

        if ($newStatus === 'Delivered' && $balance > 0) {
            $message .= ' Note: ' . money($balance) . ' is still owed on this order.';
        }
        if ($newStatus === 'Cancelled') {
            $message .= $returned > 0
                ? ' ' . $returned . ' item(s) are back in stock.'
                : ' No stock had been taken for this order, so stock is unchanged.';
            if ((float) $order['amount_paid'] > 0) {
                $message .= ' The customer paid ' . money($order['amount_paid'])
                          . '. Refund it by hand (Paystack dashboard or cash).';
            }
        }

        // [Module: Notifications] An SMS to the customer when the order is Ready goes here.

        return ['success' => true, 'message' => $message];
    }

    // =================================================================
    // Helpers used only inside this controller
    // =================================================================

    /** Cedis to pesewas, as a whole number (GH₵ 250.50 → 25050). */
    private function toPesewas(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Rebuild the order from the customer's saved cart, for when the
     * session was lost between paying and coming back. Delivery defaults
     * to Pickup, and a note asks staff to confirm with the customer.
     */
    private function rebuildCheckout(int $customerId): ?array
    {
        $summary = $this->cartController->getCartSummary(['customer_id' => $customerId, 'guest_key' => null]);
        if (empty($summary['items']) || $summary['has_problems']) {
            return null;
        }

        $items = [];
        foreach ($summary['items'] as $item) {
            $items[] = [
                'product_id'    => (int) $item['product_id'],
                'product_title' => $item['product_title'],
                'qty'           => (int) $item['qty'],
                'unit_price'    => (float) $item['product_price'],
            ];
        }

        return [
            'reference'        => '',
            'customer_id'      => $customerId,
            'order_total'      => round($summary['subtotal'], 2),
            'items'            => $items,
            'delivery_method'  => 'Pickup',
            'delivery_address' => null,
            'notes'            => '[Delivery choice was lost after payment. Please confirm pickup or delivery with the customer.]',
        ];
    }
}
