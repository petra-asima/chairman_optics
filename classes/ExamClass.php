<?php
// Model: all SQL for eye exams lives here.
// Tables used: co_eye_exams, co_prescriptions (the prescription written
// from an exam), co_exam_attachments, and co_appointments (marked
// Completed when an exam is saved from an appointment).
//
// The prescription from an exam is saved here, not in PrescriptionClass,
// so the exam and its prescription are saved in the same transaction.
// PrescriptionClass is for prescriptions customers upload themselves.
require_once __DIR__ . '/../core/db_class.php';

class ExamClass extends Database
{
    /**
     * Save an exam, its prescription (if any) and its attachments, and
     * complete the appointment it came from, all in ONE transaction.
     * @param array      $exam          patient_id, appointment_id, optometrist_id, chief_complaint,
     *                                  va_* (six fields), diagnosis, notes
     * @param array|null $prescription  re_/le_ sphere, cylinder, axis, add, pd, notes, customer_id
     * @param array      $attachments   each: file_name (original), file_path (saved name)
     * @return int|false  the new exam_id, or false if nothing was saved
     */
    public function saveExam(array $exam, ?array $prescription, array $attachments)
    {
        $this->conn->begin_transaction();
        try {
            // 1. The exam
            $stmt = $this->conn->prepare(
                'INSERT INTO co_eye_exams
                    (patient_id, appointment_id, optometrist_id, chief_complaint,
                     va_unaided_re, va_unaided_le, va_aided_re, va_aided_le, va_pinhole_re, va_pinhole_le,
                     diagnosis, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iiisssssssss',
                $exam['patient_id'], $exam['appointment_id'], $exam['optometrist_id'], $exam['chief_complaint'],
                $exam['va_unaided_re'], $exam['va_unaided_le'], $exam['va_aided_re'], $exam['va_aided_le'],
                $exam['va_pinhole_re'], $exam['va_pinhole_le'], $exam['diagnosis'], $exam['notes']
            );
            $stmt->execute();
            $examId = $stmt->insert_id;
            $stmt->close();

            // 2. The prescription. It is written by the optometrist, so it is
            //    verified from the start (is_verified = 1).
            if ($prescription !== null) {
                $stmt = $this->conn->prepare(
                    "INSERT INTO co_prescriptions
                        (source, patient_id, customer_id, exam_id,
                         re_sphere, re_cylinder, re_axis, re_add,
                         le_sphere, le_cylinder, le_axis, le_add,
                         pd, notes, is_verified)
                     VALUES ('Exam', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
                );
                // Types: patient, customer, exam (iii), then for each eye
                // sphere, cylinder, axis, add (ddid ddid), then pd, notes (ds)
                $stmt->bind_param(
                    'iii' . 'ddid' . 'ddid' . 'ds',
                    $exam['patient_id'], $prescription['customer_id'], $examId,
                    $prescription['re_sphere'], $prescription['re_cylinder'], $prescription['re_axis'], $prescription['re_add'],
                    $prescription['le_sphere'], $prescription['le_cylinder'], $prescription['le_axis'], $prescription['le_add'],
                    $prescription['pd'], $prescription['notes']
                );
                $stmt->execute();
                $stmt->close();
            }

            // 3. Attachments (the files themselves are already in uploads/exam_attachments/)
            if (!empty($attachments)) {
                $stmt = $this->conn->prepare(
                    'INSERT INTO co_exam_attachments (exam_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)'
                );
                foreach ($attachments as $file) {
                    $stmt->bind_param('issi', $examId, $file['file_name'], $file['file_path'], $exam['optometrist_id']);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // 4. The appointment this exam came from is now Completed
            if ($exam['appointment_id'] !== null) {
                $stmt = $this->conn->prepare(
                    "UPDATE co_appointments
                     SET appointment_status = 'Completed', optometrist_id = ?, patient_id = ?
                     WHERE appointment_id = ? AND appointment_status IN ('Booked', 'Confirmed')"
                );
                $stmt->bind_param('iii', $exam['optometrist_id'], $exam['patient_id'], $exam['appointment_id']);
                $stmt->execute();
                $stmt->close();
            }

            $this->conn->commit();
            return $examId;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('saveExam failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * One exam with the patient, the optometrist, its prescription and attachments.
     * @return array|false
     */
    public function getExam(int $examId)
    {
        $stmt = $this->conn->prepare(
            "SELECT e.*, p.folder_no, p.patient_fname, p.patient_lname, p.patient_gender, p.date_of_birth,
                    CONCAT(s.staff_fname, ' ', s.staff_lname) AS optometrist_name
             FROM co_eye_exams e
             JOIN co_patients p    ON p.patient_id = e.patient_id
             LEFT JOIN co_staff s  ON s.staff_id = e.optometrist_id
             WHERE e.exam_id = ?"
        );
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exam) {
            return false;
        }

        $stmt = $this->conn->prepare('SELECT * FROM co_prescriptions WHERE exam_id = ? LIMIT 1');
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $exam['prescription'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        $stmt = $this->conn->prepare(
            'SELECT attachment_id, file_name, uploaded_at FROM co_exam_attachments WHERE exam_id = ? ORDER BY attachment_id'
        );
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $exam['attachments'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $exam;
    }

    /** One attachment, for downloading. @return array|false */
    public function getAttachment(int $attachmentId)
    {
        $stmt = $this->conn->prepare('SELECT * FROM co_exam_attachments WHERE attachment_id = ?');
        $stmt->bind_param('i', $attachmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Recent exams, newest first, optionally searched by folder number or name.
     * @return array
     */
    public function getExams(string $q, int $limit, int $offset): array
    {
        $sql = "SELECT e.exam_id, e.exam_date, e.chief_complaint, e.diagnosis,
                       p.patient_id, p.folder_no, p.patient_fname, p.patient_lname,
                       CONCAT(s.staff_fname, ' ', s.staff_lname) AS optometrist_name,
                       (SELECT COUNT(*) FROM co_prescriptions pr WHERE pr.exam_id = e.exam_id) AS has_prescription
                FROM co_eye_exams e
                JOIN co_patients p   ON p.patient_id = e.patient_id
                LEFT JOIN co_staff s ON s.staff_id = e.optometrist_id";
        $types  = '';
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sql .= " WHERE p.folder_no LIKE ? OR CONCAT(p.patient_fname, ' ', p.patient_lname) LIKE ?";
            $types .= 'ss';
            array_push($params, $like, $like);
        }
        $sql .= ' ORDER BY e.exam_date DESC, e.exam_id DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        array_push($params, $limit, $offset);

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** How many exams match a search (for the page numbers). */
    public function countExams(string $q): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM co_eye_exams e JOIN co_patients p ON p.patient_id = e.patient_id';
        if ($q !== '') {
            $sql .= " WHERE p.folder_no LIKE ? OR CONCAT(p.patient_fname, ' ', p.patient_lname) LIKE ?";
        }
        $stmt = $this->conn->prepare($sql);
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $stmt->bind_param('ss', $like, $like);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }
}
