<?php
// Model: money in and out of the business.
// This part: payments received (co_payment).
// Expenses, debtors and profit-and-loss reports are added in later modules.
require_once __DIR__ . '/../core/db_class.php';

class AccountingClass extends Database
{
    /**
     * Record a payment against an order (for example the balance on a
     * pair of glasses). The order row is locked and the balance worked out
     * again inside the transaction, so two staff recording the last payment
     * at the same moment cannot take more than is owed.
     * @return int|string  the new pay_id, or 'too_much', 'reference' (already used), 'error'
     */
    public function addPayment(int $orderId, float $amount, string $method, ?string $reference, int $staffId)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                "SELECT o.order_total, o.customer_id,
                        (SELECT COALESCE(SUM(p.amt), 0) FROM co_payment p
                          WHERE p.order_id = o.order_id AND p.pay_status = 'Success') AS paid
                 FROM co_orders o
                 WHERE o.order_id = ? AND o.order_status <> 'Cancelled'
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Compare in pesewas (whole numbers) so 0.1 + 0.2 style rounding never matters
            $balance = $order ? (int) round(($order['order_total'] - $order['paid']) * 100) : 0;
            if (!$order || (int) round($amount * 100) > $balance) {
                $this->conn->rollback();
                return 'too_much';
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO co_payment
                    (order_id, customer_id, amt, currency, payment_method, pay_reference, pay_status, received_by)
                 VALUES (?, ?, ?, 'GHS', ?, ?, 'Success', ?)"
            );
            $stmt->bind_param('iidssi', $orderId, $order['customer_id'], $amount, $method, $reference, $staffId);
            $stmt->execute();
            $payId = $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();
            return $payId;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            if ($ex->getCode() === 1062) {
                return 'reference'; // the same Mobile Money / bank reference was used before
            }
            log_error('addPayment failed: ' . $ex->getMessage());
            return 'error';
        }
    }

    /**
     * Successful payments between two dates (inclusive), newest first,
     * optionally for one payment method.
     */
    public function getPayments(string $fromDate, string $toDate, string $method): array
    {
        $sql = "SELECT p.pay_id, p.amt, p.payment_method, p.pay_reference, p.payment_date,
                       o.order_id, o.invoice_no, o.order_source,
                       COALESCE(CONCAT(c.customer_fname, ' ', c.customer_lname),
                                CONCAT(pt.patient_fname, ' ', pt.patient_lname), 'Walk-in') AS buyer_name,
                       CONCAT(s.staff_fname, ' ', s.staff_lname) AS received_by_name
                FROM co_payment p
                JOIN co_orders o         ON o.order_id = p.order_id
                LEFT JOIN co_customer c  ON c.customer_id = o.customer_id
                LEFT JOIN co_patients pt ON pt.patient_id = o.patient_id
                LEFT JOIN co_staff s     ON s.staff_id = p.received_by
                WHERE p.pay_status = 'Success'
                  AND p.payment_date >= ? AND p.payment_date < DATE_ADD(?, INTERVAL 1 DAY)";
        $types  = 'ss';
        $params = [$fromDate, $toDate];

        if ($method !== '') {
            $sql .= ' AND p.payment_method = ?';
            $types .= 's';
            $params[] = $method;
        }
        $sql .= ' ORDER BY p.payment_date DESC, p.pay_id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Money received between two dates, added up per payment method.
     * @return array method => total
     */
    public function totalsByMethod(string $fromDate, string $toDate): array
    {
        $stmt = $this->conn->prepare(
            "SELECT payment_method, SUM(amt) AS total
             FROM co_payment
             WHERE pay_status = 'Success'
               AND payment_date >= ? AND payment_date < DATE_ADD(?, INTERVAL 1 DAY)
             GROUP BY payment_method"
        );
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $totals = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $totals[$row['payment_method']] = (float) $row['total'];
        }
        $stmt->close();
        return $totals;
    }
}
