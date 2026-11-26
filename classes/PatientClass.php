<?php
// Model: all SQL for the co_patients table lives here, plus the
// patient's visit timeline (appointments, eye exams, prescriptions and
// orders, read from their own tables).
// Patients are never deleted: they are medical records, and exams,
// prescriptions and orders point to them.
require_once __DIR__ . '/../core/db_class.php';

class PatientClass extends Database
{
    /**
     * Build the WHERE part of a patient search.
     * Searches the folder number, the full name and the phone number.
     * @param array $phoneForms  extra spellings of a phone number to search
     *                           (e.g. 0241234567 is saved as +233241234567)
     */
    private function searchWhere(string $q, array $phoneForms, string &$types, array &$params): string
    {
        if ($q === '') {
            return '';
        }

        $like = '%' . addcslashes($q, '%_\\') . '%';
        $sql  = " WHERE (p.folder_no LIKE ?
                   OR CONCAT(p.patient_fname, ' ', p.patient_lname) LIKE ?
                   OR CONCAT(p.patient_lname, ' ', p.patient_fname) LIKE ?
                   OR p.patient_contact LIKE ?";
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);

        foreach ($phoneForms as $phone) {
            $sql .= ' OR p.patient_contact LIKE ?';
            $types .= 's';
            $params[] = '%' . addcslashes($phone, '%_\\') . '%';
        }
        return $sql . ')';
    }

    /** One page of patients, most recently registered first. */
    public function searchPatients(string $q, array $phoneForms, int $limit, int $offset): array
    {
        $types  = '';
        $params = [];
        $where  = $this->searchWhere($q, $phoneForms, $types, $params);

        $stmt = $this->conn->prepare(
            'SELECT p.patient_id, p.folder_no, p.patient_fname, p.patient_lname, p.patient_gender,
                    p.date_of_birth, p.patient_contact, p.date_registered, p.customer_id,
                    (SELECT MAX(a.appointment_date) FROM co_appointments a
                      WHERE a.patient_id = p.patient_id AND a.appointment_status = \'Completed\') AS last_visit
             FROM co_patients p' . $where . '
             ORDER BY p.date_registered DESC, p.patient_id DESC
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

    /** How many patients match a search (for the page numbers). */
    public function countPatients(string $q, array $phoneForms): int
    {
        $types  = '';
        $params = [];
        $where  = $this->searchWhere($q, $phoneForms, $types, $params);

        $stmt = $this->conn->prepare('SELECT COUNT(*) AS total FROM co_patients p' . $where);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * One patient, with the name of the staff member who registered them
     * and their online account email if the folder is linked to one.
     * @return array|false
     */
    public function getPatientById(int $patientId)
    {
        $stmt = $this->conn->prepare(
            'SELECT p.*, s.staff_fname, s.staff_lname, c.customer_email AS account_email
             FROM co_patients p
             LEFT JOIN co_staff s    ON s.staff_id = p.registered_by
             LEFT JOIN co_customer c ON c.customer_id = p.customer_id
             WHERE p.patient_id = ?'
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** A patient by folder number (e.g. CO-2026-0001). @return array|false */
    public function getPatientByFolder(string $folderNo)
    {
        $stmt = $this->conn->prepare(
            'SELECT patient_id, folder_no, patient_fname, patient_lname, customer_id
             FROM co_patients WHERE folder_no = ?'
        );
        $stmt->bind_param('s', $folderNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** The patient folder linked to an online account, if any. @return array|false */
    public function getPatientByCustomerId(int $customerId)
    {
        $stmt = $this->conn->prepare(
            'SELECT patient_id, folder_no FROM co_patients WHERE customer_id = ? ORDER BY patient_id LIMIT 1'
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * An existing patient with the same name and phone number, if any,
     * to stop the same person getting two folders.
     * @return array|false  patient_id and folder_no
     */
    public function findDuplicate(string $fname, string $lname, string $contact, int $ignoreId = 0)
    {
        $stmt = $this->conn->prepare(
            'SELECT patient_id, folder_no FROM co_patients
             WHERE patient_fname = ? AND patient_lname = ? AND patient_contact = ? AND patient_id <> ?'
        );
        $stmt->bind_param('sssi', $fname, $lname, $contact, $ignoreId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** The online account (co_customer) with this email, if there is one. */
    public function findCustomerIdByEmail(string $email): ?int
    {
        $stmt = $this->conn->prepare('SELECT customer_id FROM co_customer WHERE customer_email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['customer_id'] : null;
    }

    /**
     * Open a new patient folder.
     * The folder number comes from the patient_id (CO-2026-0001), so two
     * receptionists saving at the same moment can never get the same number.
     * folder_no cannot be empty, so a temporary value is used until the
     * patient_id is known. Both steps are in one transaction.
     * @param array $p  keys match the co_patients column names
     * @return array|false  ['patient_id' => N, 'folder_no' => 'CO-2026-0001']
     */
    public function addPatient(array $p)
    {
        $this->conn->begin_transaction();
        try {
            $tempFolder = 'TMP-' . bin2hex(random_bytes(6));
            $stmt = $this->conn->prepare(
                'INSERT INTO co_patients
                    (folder_no, customer_id, patient_fname, patient_lname, patient_gender, date_of_birth,
                     patient_contact, patient_email, patient_address, occupation, registered_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sissssssssi',
                $tempFolder, $p['customer_id'], $p['patient_fname'], $p['patient_lname'], $p['patient_gender'],
                $p['date_of_birth'], $p['patient_contact'], $p['patient_email'], $p['patient_address'],
                $p['occupation'], $p['registered_by']
            );
            $stmt->execute();
            $patientId = $stmt->insert_id;
            $stmt->close();

            $folderNo = sprintf('CO-%s-%04d', date('Y'), $patientId);
            $stmt = $this->conn->prepare('UPDATE co_patients SET folder_no = ? WHERE patient_id = ?');
            $stmt->bind_param('si', $folderNo, $patientId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['patient_id' => $patientId, 'folder_no' => $folderNo];
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('addPatient failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Update a patient's details. The folder number never changes. */
    public function updatePatient(int $patientId, array $p): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'UPDATE co_patients
                 SET customer_id = ?, patient_fname = ?, patient_lname = ?, patient_gender = ?,
                     date_of_birth = ?, patient_contact = ?, patient_email = ?, patient_address = ?,
                     occupation = ?
                 WHERE patient_id = ?'
            );
            $stmt->bind_param(
                'issssssssi',
                $p['customer_id'], $p['patient_fname'], $p['patient_lname'], $p['patient_gender'],
                $p['date_of_birth'], $p['patient_contact'], $p['patient_email'], $p['patient_address'],
                $p['occupation'], $patientId
            );
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updatePatient failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Everything that has happened for this patient, newest first.
     * Four tables are combined with UNION ALL into one list. Each part
     * returns the same columns: when, what kind of event, a short title,
     * more detail, the staff member, an amount (orders only) and the id
     * of the record. Money is formatted later in PHP with money(), not
     * here, so the SQL only returns plain numbers.
     * Orders placed online by the patient's linked account are included.
     * @return array
     */
    public function getTimeline(int $patientId, ?int $customerId): array
    {
        // 0 never matches a real customer, so "no linked account" finds nothing
        $customerId = $customerId ?? 0;

        $stmt = $this->conn->prepare(
            "SELECT * FROM (
                SELECT TIMESTAMP(a.appointment_date, a.appointment_time) AS event_date,
                       'Appointment' AS event_type,
                       a.appointment_status AS title,
                       COALESCE(a.reason, '') AS detail,
                       CONCAT(s.staff_fname, ' ', s.staff_lname) AS staff_name,
                       NULL AS amount,
                       a.appointment_id AS record_id
                FROM co_appointments a
                LEFT JOIN co_staff s ON s.staff_id = a.optometrist_id
                WHERE a.patient_id = ?

                UNION ALL

                SELECT e.exam_date, 'Eye exam',
                       COALESCE(NULLIF(e.chief_complaint, ''), 'Eye examination'),
                       COALESCE(e.diagnosis, ''),
                       CONCAT(s.staff_fname, ' ', s.staff_lname),
                       NULL,
                       e.exam_id
                FROM co_eye_exams e
                LEFT JOIN co_staff s ON s.staff_id = e.optometrist_id
                WHERE e.patient_id = ?

                UNION ALL

                SELECT pr.created_at, 'Prescription',
                       IF(pr.source = 'Exam', 'From eye exam', 'Uploaded by customer'),
                       COALESCE(pr.notes, ''),
                       NULL,
                       NULL,
                       pr.prescription_id
                FROM co_prescriptions pr
                WHERE pr.patient_id = ?

                UNION ALL

                SELECT o.order_date, 'Order',
                       CONCAT(o.invoice_no, ' - ', o.order_status),
                       o.order_source,
                       CONCAT(s.staff_fname, ' ', s.staff_lname),
                       o.order_total,
                       o.order_id
                FROM co_orders o
                LEFT JOIN co_staff s ON s.staff_id = o.created_by
                WHERE o.patient_id = ? OR o.customer_id = ?
             ) AS events
             ORDER BY event_date DESC"
        );
        $stmt->bind_param('iiiii', $patientId, $patientId, $patientId, $patientId, $customerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
