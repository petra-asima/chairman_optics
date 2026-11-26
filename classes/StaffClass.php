<?php
// Model: all SQL for the co_staff table lives here.
// Each staff member has a role_id that points to a row in co_roles.
require_once __DIR__ . '/../core/db_class.php';

class StaffClass extends Database
{
    /**
     * Is this email already used by a staff account?
     * $ignoreId lets a staff member keep their own email when their
     * details are edited (0 = check every account).
     * @return bool
     */
    public function emailExists(string $email, int $ignoreId = 0): bool
    {
        $stmt = $this->conn->prepare('SELECT staff_id FROM co_staff WHERE staff_email = ? AND staff_id <> ?');
        $stmt->bind_param('si', $email, $ignoreId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * One staff row by email, with the name of their role.
     * Includes the password hash, so use it for login only.
     * @return array|false
     */
    public function getStaffByEmail(string $email)
    {
        $stmt = $this->conn->prepare(
            'SELECT s.*, r.role_name
             FROM co_staff s
             JOIN co_roles r ON r.role_id = s.role_id
             WHERE s.staff_email = ?'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * One staff member's details by id, with their role name.
     * The password hash is not selected.
     * @return array|false
     */
    public function getStaffById(int $staffId)
    {
        $stmt = $this->conn->prepare(
            'SELECT s.staff_id, s.role_id, r.role_name, s.staff_fname, s.staff_lname,
                    s.staff_email, s.staff_contact, s.is_active, s.last_login, s.date_created
             FROM co_staff s
             JOIN co_roles r ON r.role_id = s.role_id
             WHERE s.staff_id = ?'
        );
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Every staff member with their role name, for the Admin's staff accounts page.
     * @return array
     */
    public function getAllStaff(): array
    {
        $result = $this->conn->query(
            'SELECT s.staff_id, s.role_id, r.role_name, s.staff_fname, s.staff_lname,
                    s.staff_email, s.staff_contact, s.is_active, s.last_login, s.date_created
             FROM co_staff s
             JOIN co_roles r ON r.role_id = s.role_id
             ORDER BY s.staff_fname, s.staff_lname'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** Record the date and time of a successful login. */
    public function updateLastLogin(int $staffId): bool
    {
        $stmt = $this->conn->prepare('UPDATE co_staff SET last_login = NOW() WHERE staff_id = ?');
        $stmt->bind_param('i', $staffId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Add a staff account. $hash must already be made with password_hash().
     * @return int|false  the new staff_id, or false if the insert failed
     */
    public function addStaff(int $roleId, string $fname, string $lname,
                             string $email, string $contact, string $hash)
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO co_staff
                    (role_id, staff_fname, staff_lname, staff_email, staff_contact, staff_pass)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssss', $roleId, $fname, $lname, $email, $contact, $hash);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addStaff failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Update a staff member's details and role. */
    public function updateStaff(int $staffId, int $roleId, string $fname, string $lname,
                                string $email, string $contact): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'UPDATE co_staff
                 SET role_id = ?, staff_fname = ?, staff_lname = ?, staff_email = ?, staff_contact = ?
                 WHERE staff_id = ?'
            );
            $stmt->bind_param('issssi', $roleId, $fname, $lname, $email, $contact, $staffId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateStaff failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Switch an account on (1) or off (0). Disabled staff cannot log in. */
    public function setActive(int $staffId, int $isActive): bool
    {
        $stmt = $this->conn->prepare('UPDATE co_staff SET is_active = ? WHERE staff_id = ?');
        $stmt->bind_param('ii', $isActive, $staffId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Save a new password. $hash must already be made with password_hash(). */
    public function changePassword(int $staffId, string $hash): bool
    {
        $stmt = $this->conn->prepare('UPDATE co_staff SET staff_pass = ? WHERE staff_id = ?');
        $stmt->bind_param('si', $hash, $staffId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Active staff with one role, e.g. every Optometrist who can be
     * given an appointment. The password hash is not selected.
     * @return array
     */
    public function getActiveStaffByRole(string $roleName): array
    {
        $stmt = $this->conn->prepare(
            'SELECT s.staff_id, s.staff_fname, s.staff_lname
             FROM co_staff s
             JOIN co_roles r ON r.role_id = s.role_id
             WHERE r.role_name = ? AND s.is_active = 1
             ORDER BY s.staff_fname, s.staff_lname'
        );
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Every role, for the role dropdown on the staff form.
     * @return array
     */
    public function getAllRoles(): array
    {
        $result = $this->conn->query('SELECT role_id, role_name FROM co_roles ORDER BY role_id');
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}