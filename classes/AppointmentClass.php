<?php
// Model: all SQL for the co_appointments table lives here.
// An online booking has a customer_id. A front-desk booking has a
// patient_id. Reception links an online booking to a patient folder
// when the person arrives.
require_once __DIR__ . '/../core/db_class.php';

class AppointmentClass extends Database
{
    // Bookings that hold a place in a time slot. Cancelled and No-show
    // appointments free the slot again.
    private const ACTIVE_STATUSES = "('Booked', 'Confirmed', 'Completed')";

    // Columns used by the day view and the customer's list. The person's
    // name comes from the patient folder if linked, else from the online account.
    private const LIST_COLUMNS =
        "a.appointment_id, a.customer_id, a.patient_id, a.optometrist_id,
         a.appointment_date, a.appointment_time, a.reason, a.booked_via,
         a.appointment_status, a.notes, a.created_at,
         pt.folder_no,
         COALESCE(CONCAT(pt.patient_fname, ' ', pt.patient_lname),
                  CONCAT(c.customer_fname, ' ', c.customer_lname)) AS person_name,
         COALESCE(pt.patient_contact, c.customer_contact) AS person_contact,
         CONCAT(s.staff_fname, ' ', s.staff_lname) AS optometrist_name";

    private const LIST_JOINS =
        ' FROM co_appointments a
          LEFT JOIN co_patients pt ON pt.patient_id = a.patient_id
          LEFT JOIN co_customer c  ON c.customer_id = a.customer_id
          LEFT JOIN co_staff s     ON s.staff_id = a.optometrist_id';

    /**
     * How many places are taken at each time on one day.
     * @return array 'HH:MM' => number of bookings
     */
    public function getTakenTimes(string $date): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DATE_FORMAT(appointment_time, '%H:%i') AS slot, COUNT(*) AS taken
             FROM co_appointments
             WHERE appointment_date = ? AND appointment_status IN " . self::ACTIVE_STATUSES . '
             GROUP BY appointment_time'
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $taken = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $taken[$row['slot']] = (int) $row['taken'];
        }
        $stmt->close();
        return $taken;
    }

    /**
     * Save a booking, but only if the time slot still has room.
     * The SELECT ... FOR UPDATE locks that slot until the transaction
     * ends, so two people booking the last place at the same moment
     * cannot both get it: the second one waits, then sees it is full.
     * @param array $a  customer_id, patient_id, optometrist_id, appointment_date,
     *                  appointment_time, reason, booked_via, appointment_status
     * @return int|string|false  the new appointment_id, 'full', or false on error
     */
    public function bookAppointment(array $a, int $perSlot)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT COUNT(*) AS taken FROM co_appointments
                 WHERE appointment_date = ? AND appointment_time = ?
                   AND appointment_status IN ' . self::ACTIVE_STATUSES . '
                 FOR UPDATE'
            );
            $stmt->bind_param('ss', $a['appointment_date'], $a['appointment_time']);
            $stmt->execute();
            $taken = (int) $stmt->get_result()->fetch_assoc()['taken'];
            $stmt->close();

            if ($taken >= $perSlot) {
                $this->conn->rollback();
                return 'full';
            }

            $stmt = $this->conn->prepare(
                'INSERT INTO co_appointments
                    (customer_id, patient_id, optometrist_id, appointment_date, appointment_time,
                     reason, booked_via, appointment_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iiisssss',
                $a['customer_id'], $a['patient_id'], $a['optometrist_id'], $a['appointment_date'],
                $a['appointment_time'], $a['reason'], $a['booked_via'], $a['appointment_status']
            );
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('bookAppointment failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Every appointment on one day, in time order. */
    public function getDay(string $date): array
    {
        $stmt = $this->conn->prepare(
            'SELECT ' . self::LIST_COLUMNS . self::LIST_JOINS . '
             WHERE a.appointment_date = ?
             ORDER BY a.appointment_time, a.appointment_id'
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Number of active bookings on each day between two dates, for the
     * week strip above the day view.
     * @return array 'YYYY-MM-DD' => count
     */
    public function countByDay(string $from, string $to): array
    {
        $stmt = $this->conn->prepare(
            'SELECT appointment_date, COUNT(*) AS total FROM co_appointments
             WHERE appointment_date BETWEEN ? AND ?
               AND appointment_status IN ' . self::ACTIVE_STATUSES . '
             GROUP BY appointment_date'
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $counts = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $counts[$row['appointment_date']] = (int) $row['total'];
        }
        $stmt->close();
        return $counts;
    }

    /** One appointment. @return array|false */
    public function getById(int $appointmentId)
    {
        $stmt = $this->conn->prepare('SELECT ' . self::LIST_COLUMNS . self::LIST_JOINS . ' WHERE a.appointment_id = ?');
        $stmt->bind_param('i', $appointmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** A customer's own appointments, newest first. */
    public function getCustomerAppointments(int $customerId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT ' . self::LIST_COLUMNS . self::LIST_JOINS . '
             WHERE a.customer_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC'
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** How many upcoming Booked or Confirmed appointments a customer has. */
    public function countUpcomingForCustomer(int $customerId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM co_appointments
             WHERE customer_id = ? AND appointment_status IN ('Booked', 'Confirmed')
               AND TIMESTAMP(appointment_date, appointment_time) >= NOW()"
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /** Save the front desk's changes: status, optometrist, patient folder, notes. */
    public function updateAppointment(int $appointmentId, string $status, ?int $optometristId,
                                      ?int $patientId, ?string $notes): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'UPDATE co_appointments
                 SET appointment_status = ?, optometrist_id = ?, patient_id = ?, notes = ?
                 WHERE appointment_id = ?'
            );
            $stmt->bind_param('siisi', $status, $optometristId, $patientId, $notes, $appointmentId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateAppointment failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * A customer cancels their own booking. The WHERE checks the owner and
     * that it is still Booked or Confirmed, so nobody can cancel someone
     * else's appointment by changing the id.
     */
    public function cancelByCustomer(int $appointmentId, int $customerId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE co_appointments SET appointment_status = 'Cancelled'
             WHERE appointment_id = ? AND customer_id = ?
               AND appointment_status IN ('Booked', 'Confirmed')"
        );
        $stmt->bind_param('ii', $appointmentId, $customerId);
        $stmt->execute();
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    }
}
