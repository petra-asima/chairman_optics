<?php
// Model: writes to the co_audit_log table (the audit trail).
// Rows are only ever added, never edited or deleted.
require_once __DIR__ . '/../core/db_class.php';

class AuditClass extends Database
{
    /**
     * Record one action.
     * @param int|null $staffId   who did it (NULL if unknown, for example a failed login)
     * @param string   $action    short code such as LOGIN, LOGOUT, CREATE, UPDATE, DELETE
     * @param string   $details   a short description in plain words
     */
    public function log(?int $staffId, string $action, string $details = '',
                        ?string $tableName = null, ?int $recordId = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO co_audit_log (staff_id, action, table_name, record_id, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ississ', $staffId, $action, $tableName, $recordId, $details, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (mysqli_sql_exception $ex) {
            // A failed audit write should never stop the page, but it is logged
            log_error('Audit log failed: ' . $ex->getMessage());
        }
    }
}