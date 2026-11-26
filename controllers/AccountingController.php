<?php
// Controller: payments received (this module), and later expenses,
// debtors and profit-and-loss reports.
require_once __DIR__ . '/../classes/AccountingClass.php';
require_once __DIR__ . '/../classes/OrderClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';
require_once __DIR__ . '/OrderController.php';

class AccountingController
{
    private $accounting;
    private $order;
    private $audit;

    public function __construct()
    {
        $this->accounting = new AccountingClass();
        $this->order      = new OrderClass();
        $this->audit      = new AuditClass();
    }

    /** Is this a real date written as YYYY-MM-DD? */
    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /**
     * The payments page: payments in a date range, with totals per method.
     * The range defaults to today.
     */
    public function getPaymentsView(array $get): array
    {
        $from   = (string) ($get['from'] ?? '');
        $to     = (string) ($get['to'] ?? '');
        $from   = $this->isValidDate($from) ? $from : date('Y-m-d');
        $to     = $this->isValidDate($to) ? $to : $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $method = (string) ($get['method'] ?? '');
        if (!in_array($method, OrderController::PAYMENT_METHODS, true)) {
            $method = '';
        }

        $totals = $this->accounting->totalsByMethod($from, $to);
        return [
            'from'     => $from,
            'to'       => $to,
            'method'   => $method,
            'payments' => $this->accounting->getPayments($from, $to, $method),
            'totals'   => $totals,
            'grand'    => array_sum($totals),
        ];
    }

    /** The order a payment is being recorded for. @return array|false */
    public function getOrder(int $orderId)
    {
        return $this->order->getOrderForStaff($orderId);
    }

    /**
     * Record a payment on an existing order (a deposit, the balance, or a debt).
     * @param array $data  amount, method, reference
     */
    public function recordPayment(int $orderId, array $data, int $staffId): array
    {
        $order = $this->order->getOrderForStaff($orderId);
        if (!$order || $order['order_status'] === 'Cancelled') {
            return ['success' => false, 'errors' => ['form' => 'Payments cannot be recorded for that order.']];
        }

        $balance = round((float) $order['order_total'] - (float) $order['amount_paid'], 2);
        $errors  = [];

        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $data['amount']) || (float) $data['amount'] <= 0) {
            $errors['amount'] = 'Enter the amount received, for example 150 or 150.50.';
        } elseif (round((float) $data['amount'], 2) > $balance) {
            $errors['amount'] = 'That is more than the ' . money($balance) . ' still owed.';
        }
        if (!in_array($data['method'], OrderController::PAYMENT_METHODS, true)) {
            $errors['method'] = 'Choose how the customer paid.';
        }
        if (strlen($data['reference']) > 100) {
            $errors['reference'] = 'The reference can be up to 100 characters.';
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $amount    = round((float) $data['amount'], 2);
        $reference = $data['method'] !== 'Cash' && $data['reference'] !== '' ? $data['reference'] : null;
        $result    = $this->accounting->addPayment($orderId, $amount, $data['method'], $reference, $staffId);

        if ($result === 'too_much') {
            return ['success' => false, 'errors' => ['amount' => 'The balance has just changed. Please check the amount again.']];
        }
        if ($result === 'reference') {
            return ['success' => false, 'errors' => ['reference' => 'That reference has already been recorded. Check it is not a duplicate payment.']];
        }
        if ($result === 'error') {
            return ['success' => false, 'errors' => ['form' => "We couldn't save the payment. Please try again."]];
        }

        $this->audit->log($staffId, 'PAYMENT',
            money($amount) . ' ' . $data['method'] . ' for ' . $order['invoice_no'], 'co_payment', (int) $result);

        $left = $balance - $amount;
        return ['success' => true,
                'message' => money($amount) . ' received for ' . $order['invoice_no'] . '. '
                           . ($left > 0 ? money($left) . ' is still owed.' : 'The order is now fully paid.')];
    }
}
