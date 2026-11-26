<?php
// Model: read-only summary queries for the staff dashboard.
// These figures come from several tables (orders, payments,
// appointments, patients, products), so they are kept together here
// instead of being spread across the other model classes.
// Nothing in this class changes data.
//
// Dates are passed in from PHP (which uses the Africa/Accra timezone set
// in core.php) instead of using MySQL's CURDATE(), so "today" is always
// the clinic's today, whatever timezone the database server uses.
require_once __DIR__ . '/../core/db_class.php';

class DashboardClass extends Database
{
    /**
     * Number and value of orders placed between two date-times.
     * Cancelled orders are not counted.
     * @param string $from  e.g. '2026-11-24 00:00:00' (included)
     * @param string $to    e.g. '2026-11-25 00:00:00' (not included)
     * @return array ['order_count' => int, 'sales_total' => float]
     */
    public function getSalesBetween(string $from, string $to): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS order_count, COALESCE(SUM(order_total), 0) AS sales_total
             FROM co_orders
             WHERE order_status <> 'Cancelled'
               AND order_date >= ? AND order_date < ?"
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ['order_count' => (int) $row['order_count'], 'sales_total' => (float) $row['sales_total']];
    }

    /**
     * Money actually received (successful payments) between two date-times.
     * This can differ from sales, because some customers pay a deposit
     * first and the balance later.
     */
    public function getPaymentsBetween(string $from, string $to): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(amt), 0) AS received
             FROM co_payment
             WHERE pay_status = 'Success'
               AND payment_date >= ? AND payment_date < ?"
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $received = (float) $stmt->get_result()->fetch_assoc()['received'];
        $stmt->close();
        return $received;
    }

    /**
     * Appointments on one day (cancelled ones are not counted).
     * @param string $date  e.g. '2026-11-24'
     * @return array ['total' => int, 'completed' => int]
     */
    public function getAppointmentsOn(string $date): array
    {
        // In MySQL a comparison such as (status = 'Completed') gives 1 or 0,
        // so SUM() of it counts the matching rows.
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(appointment_status = 'Completed'), 0) AS completed
             FROM co_appointments
             WHERE appointment_date = ?
               AND appointment_status <> 'Cancelled'"
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ['total' => (int) $row['total'], 'completed' => (int) $row['completed']];
    }

    /** How many new patient folders were opened between two date-times. */
    public function countNewPatientsBetween(string $from, string $to): int
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total FROM co_patients
             WHERE date_registered >= ? AND date_registered < ?'
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * Prescription glasses orders that are still being made, and those
     * ready for collection. An order counts as "glasses" when at least one
     * of its items has a lens type chosen.
     * @return array ['in_lab' => int, 'ready' => int]
     */
    public function getGlassesInProgress(): array
    {
        $result = $this->conn->query(
            "SELECT COALESCE(SUM(o.order_status IN ('Pending', 'Cutting', 'Fitting')), 0) AS in_lab,
                    COALESCE(SUM(o.order_status = 'Ready'), 0) AS ready
             FROM co_orders o
             WHERE EXISTS (
                 SELECT 1 FROM co_orderdetails d
                 WHERE d.order_id = o.order_id AND d.lens_type_id IS NOT NULL
             )"
        );
        $row = $result->fetch_assoc();
        return ['in_lab' => (int) $row['in_lab'], 'ready' => (int) $row['ready']];
    }

    /**
     * The best-selling products since a date, by quantity sold.
     * Services (such as eye exam fees) and cancelled orders are left out.
     * @return array rows with product_id, product_title, qty_sold, revenue
     */
    public function getBestSellers(string $from, int $limit = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.product_id, p.product_title,
                    SUM(d.qty) AS qty_sold,
                    SUM(d.qty * (d.unit_price + d.lens_price)) AS revenue
             FROM co_orderdetails d
             JOIN co_orders o   ON o.order_id = d.order_id
             JOIN co_products p ON p.product_id = d.product_id
             WHERE o.order_status <> 'Cancelled'
               AND o.order_date >= ?
               AND p.is_service = 0
             GROUP BY p.product_id, p.product_title
             ORDER BY qty_sold DESC, revenue DESC
             LIMIT ?"
        );
        $stmt->bind_param('si', $from, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
