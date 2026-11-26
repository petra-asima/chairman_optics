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
    // STAFF: in-store sales (at the counter)
    // =================================================================

    // Lines on the sale form, and the most of one product per line
    public const SALE_LINES   = 5;
    public const SALE_MAX_QTY = 20;

    // How customers pay in the shop (must match the ENUM in co_payment)
    public const PAYMENT_METHODS = ['Cash', 'Mobile Money', 'Card', 'Bank Transfer'];

    /**
     * Everything the sale form needs: products, lens options, and the
     * patient and their prescriptions if a folder number was given.
     */
    public function getSaleFormData(string $folderNo): array
    {
        require_once __DIR__ . '/../classes/ProductClass.php';
        require_once __DIR__ . '/../classes/PatientClass.php';
        require_once __DIR__ . '/../classes/PrescriptionClass.php';

        $products = (new ProductClass())->getSaleProducts();
        $lenses   = array_filter((new ProductClass())->getAllLensOptions(), fn($l) => (int) $l['is_active'] === 1);

        $patient = false;
        $prescriptions = [];
        if ($folderNo !== '') {
            $patient = (new PatientClass())->getPatientByFolder(strtoupper($folderNo));
            if ($patient) {
                $prescriptions = (new PrescriptionClass())->getPatientPrescriptions((int) $patient['patient_id']);
            }
        }

        return [
            'products'      => $products,
            'lens_types'    => array_values(array_filter($lenses, fn($l) => $l['option_group'] === 'Lens type')),
            'coatings'      => array_values(array_filter($lenses, fn($l) => $l['option_group'] === 'Coating')),
            'patient'       => $patient,
            'prescriptions' => $prescriptions,
        ];
    }

    /**
     * Make a sale at the counter.
     * @param array $data  folder_no, lines (product_id, qty, lens_type_id, lens_coating_id,
     *                     prescription_id), amount_paid, method, reference, notes
     * @return array ['success' => true, 'order_id' => N, 'message' => ...] or ['success' => false, 'errors' => [...]]
     */
    public function createInStoreSale(array $data, int $staffId): array
    {
        $form   = $this->getSaleFormData($data['folder_no']);
        $errors = [];

        if ($data['folder_no'] !== '' && !$form['patient']) {
            $errors['folder_no'] = 'No patient has that folder number. Leave it empty for a walk-in sale.';
        }

        // Look-up tables by id, so each line can be checked against the real records
        $products      = array_column($form['products'], null, 'product_id');
        $lensTypes     = array_column($form['lens_types'], null, 'lens_id');
        $coatings      = array_column($form['coatings'], null, 'lens_id');
        $prescriptions = array_column($form['prescriptions'], null, 'prescription_id');

        $items     = [];
        $total     = 0.0;
        $hasLenses = false;

        foreach ($data['lines'] as $n => $line) {
            $productId = (int) $line['product_id'];
            if ($productId === 0) {
                continue; // an empty line
            }
            $label = 'Line ' . ($n + 1);

            if (!isset($products[$productId])) {
                $errors['line' . $n] = $label . ': choose a product from the list.';
                continue;
            }
            $product = $products[$productId];

            $qty = filter_var($line['qty'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::SALE_MAX_QTY]]);
            if ($qty === false) {
                $errors['line' . $n] = $label . ': quantity must be 1 to ' . self::SALE_MAX_QTY . '.';
                continue;
            }
            if ((int) $product['is_service'] === 0 && $qty > (int) $product['product_stock']) {
                $errors['line' . $n] = $label . ': only ' . (int) $product['product_stock'] . ' of ' . $product['product_title'] . ' in stock.';
                continue;
            }

            // Lenses: only for frames that can take them, only with a prescription
            $lensTypeId = (int) $line['lens_type_id'];
            $coatingId  = (int) $line['lens_coating_id'];
            $rxId       = (int) $line['prescription_id'];
            $lensPrice  = 0.0;

            if ($lensTypeId > 0 || $coatingId > 0 || $rxId > 0) {
                if ((int) $product['needs_lenses'] !== 1) {
                    $errors['line' . $n] = $label . ': ' . $product['product_title'] . ' cannot take prescription lenses.';
                    continue;
                }
                if (!isset($lensTypes[$lensTypeId])) {
                    $errors['line' . $n] = $label . ': choose a lens type.';
                    continue;
                }
                if ($coatingId > 0 && !isset($coatings[$coatingId])) {
                    $errors['line' . $n] = $label . ': choose a coating from the list.';
                    continue;
                }
                if (!isset($prescriptions[$rxId])) {
                    $errors['line' . $n] = $label . ': choose one of this patient\'s prescriptions (enter their folder number first).';
                    continue;
                }
                $lensPrice = (float) $lensTypes[$lensTypeId]['lens_price']
                           + ($coatingId > 0 ? (float) $coatings[$coatingId]['lens_price'] : 0.0);
                $hasLenses = true;
            }

            $items[] = [
                'product_id'      => $productId,
                'product_title'   => $product['product_title'],
                'qty'             => $qty,
                'unit_price'      => (float) $product['product_price'],
                'is_service'      => (int) $product['is_service'],
                'lens_type_id'    => $lensTypeId > 0 ? $lensTypeId : null,
                'lens_coating_id' => $coatingId > 0 ? $coatingId : null,
                'lens_price'      => $lensPrice,
                'prescription_id' => $rxId > 0 ? $rxId : null,
            ];
            $total += ((float) $product['product_price'] + $lensPrice) * $qty;
        }

        if (empty($items) && empty($errors)) {
            $errors['form'] = 'Add at least one product to the sale.';
        }

        // Payment now: all, part or nothing; never more than the total
        $total = round($total, 2);
        $paid  = 0.0;
        if ($data['amount_paid'] !== '') {
            if (!preg_match(self::PRICE_PATTERN_SALE, $data['amount_paid'])) {
                $errors['amount_paid'] = 'Enter the amount paid, for example 150 or 150.50.';
            } else {
                $paid = round((float) $data['amount_paid'], 2);
                if ($paid > $total) {
                    $errors['amount_paid'] = 'The amount paid cannot be more than the total (' . money($total) . ').';
                }
            }
        }
        if ($paid > 0 && !in_array($data['method'], self::PAYMENT_METHODS, true)) {
            $errors['method'] = 'Choose how the customer paid.';
        }
        if (strlen($data['reference']) > 100 || strlen($data['notes']) > 255) {
            $errors['reference'] = 'The reference can be up to 100 characters and notes up to 255.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $patient = $form['patient'];
        $saved = $this->order->createInStoreOrder(
            [
                'patient_id'   => $patient ? (int) $patient['patient_id'] : null,
                'customer_id'  => $patient && $patient['customer_id'] !== null ? (int) $patient['customer_id'] : null,
                'order_total'  => $total,
                // Glasses go to the lab; everything else is handed over now
                'order_status' => $hasLenses ? 'Pending' : 'Delivered',
                'notes'        => $data['notes'] !== '' ? $data['notes'] : null,
                'created_by'   => $staffId,
            ],
            $items,
            $paid > 0 ? [
                'amount'    => $paid,
                'method'    => $data['method'],
                // A reference only makes sense for non-cash payments
                'reference' => $data['method'] !== 'Cash' && $data['reference'] !== '' ? $data['reference'] : null,
            ] : null
        );

        if (isset($saved['error'])) {
            $messages = [
                'stock'     => 'Someone has just sold the last of ' . ($saved['product'] ?? 'an item') . '. Please check the stock.',
                'reference' => 'That payment reference has already been recorded. Check it is not a duplicate payment.',
                'database'  => "We couldn't save the sale. Please try again.",
            ];
            return ['success' => false, 'errors' => ['form' => $messages[$saved['error']]]];
        }

        require_once __DIR__ . '/../classes/AuditClass.php';
        (new AuditClass())->log($staffId, 'CREATE',
            'In-store sale ' . $saved['invoice_no'] . ' for ' . money($total) . ', paid ' . money($paid),
            'co_orders', $saved['order_id']);

        $balance = $total - $paid;
        return ['success' => true, 'order_id' => $saved['order_id'],
                'message' => 'Sale ' . $saved['invoice_no'] . ' saved: ' . money($total) . ' total'
                           . ($balance > 0 ? ', ' . money($balance) . ' still owed.' : ', fully paid.')
                           . ($hasLenses ? ' The glasses are now in the lab queue.' : '')];
    }

    // An amount of money such as 150 or 150.50
    private const PRICE_PATTERN_SALE = '/^\d{1,8}(\.\d{1,2})?$/';

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
