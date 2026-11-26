<?php
// Model: reading prescriptions (co_prescriptions).
// Prescriptions from eye exams are written by ExamClass, in the same
// transaction as the exam. Prescriptions uploaded by customers will be
// added here in the prescription-ordering module.
require_once __DIR__ . '/../core/db_class.php';

class PrescriptionClass extends Database
{
    /**
     * A patient's prescriptions, newest first, for choosing lenses at the counter.
     * @return array
     */
    public function getPatientPrescriptions(int $patientId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT prescription_id, source, re_sphere, re_cylinder, re_axis, re_add,
                    le_sphere, le_cylinder, le_axis, le_add, pd, created_at, is_verified
             FROM co_prescriptions
             WHERE patient_id = ?
             ORDER BY created_at DESC, prescription_id DESC'
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
