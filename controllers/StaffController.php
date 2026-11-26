<?php
// Controller: checks staff input, then asks the models to do the work.
require_once __DIR__ . '/../classes/StaffClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';

class StaffController
{
    private $staff;
    private $audit;

    public function __construct()
    {
        $this->staff = new StaffClass();
        $this->audit = new AuditClass();
    }

    /**
     * Check a staff login.
     * @return array  ['success' => true, 'staff' => row]
     *                or ['success' => false, 'message' => text]
     */
    public function login(string $email, string $pass): array
    {
        if ($email === '' || $pass === '') {
            return ['success' => false, 'message' => 'Enter your email and password.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address.'];
        }

        $row = $this->staff->getStaffByEmail($email);

        // Same message whether the email or the password is wrong,
        // so nobody can find out which staff emails exist.
        if (!$row || !password_verify($pass, $row['staff_pass'])) {
            $this->audit->log($row ? (int) $row['staff_id'] : null, 'LOGIN_FAILED',
                              'Failed staff login for ' . $email);
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        // Only checked after the password is right, for the same reason
        if ((int) $row['is_active'] !== 1) {
            $this->audit->log((int) $row['staff_id'], 'LOGIN_BLOCKED', 'Disabled account tried to log in');
            return ['success' => false, 'message' => 'This account has been disabled. Please contact the Admin.'];
        }

        $this->staff->updateLastLogin((int) $row['staff_id']);
        $this->audit->log((int) $row['staff_id'], 'LOGIN', 'Logged in as ' . $row['role_name']);

        unset($row['staff_pass']); // never keep the hash longer than needed
        return ['success' => true, 'staff' => $row];
    }

    /**
     * Record a logout in the audit trail.
     */
    public function logout(int $staffId): void
    {
        $this->audit->log($staffId, 'LOGOUT', 'Logged out');
    }

    // =================================================================
    // Staff accounts (Admin only)
    //
    // Safety rule: an Admin cannot disable their own account or remove
    // their own Admin role. Whoever makes a change is always an active
    // Admin, so this rule alone means there is always at least one
    // Admin left who can log in and manage the system.
    // =================================================================

    public function getAllStaff(): array
    {
        return $this->staff->getAllStaff();
    }

    /** @return array|false */
    public function getStaffById(int $staffId)
    {
        return $this->staff->getStaffById($staffId);
    }

    public function getRoles(): array
    {
        return $this->staff->getAllRoles();
    }

    /**
     * Check the staff details form.
     * @param bool $isNew  true when adding (the password is checked too)
     * @return array  field name => error message (empty when all is fine)
     */
    public function validateStaff(array $data, bool $isNew): array
    {
        $errors = [];
        $namePattern = "/^[A-Za-z][A-Za-z' -]{1,49}$/";

        if (!preg_match($namePattern, $data['staff_fname'])) {
            $errors['staff_fname'] = 'Enter the first name using letters only.';
        }
        if (!preg_match($namePattern, $data['staff_lname'])) {
            $errors['staff_lname'] = 'Enter the surname using letters only.';
        }
        if (!filter_var($data['staff_email'], FILTER_VALIDATE_EMAIL) || strlen($data['staff_email']) > 100) {
            $errors['staff_email'] = 'Enter a valid email address.';
        }
        if (!preg_match('/^(\+233|0)[235][0-9]{8}$/', $data['staff_contact'])) {
            $errors['staff_contact'] = 'Enter a valid Ghana phone number, for example 024 123 4567.';
        }

        // The role must be one of the rows in co_roles
        $roleIds = array_map('intval', array_column($this->staff->getAllRoles(), 'role_id'));
        if (!in_array((int) $data['role_id'], $roleIds, true)) {
            $errors['role_id'] = 'Please choose a role.';
        }

        if ($isNew) {
            $passError = $this->checkPassword($data['staff_pass'], $data['confirm_pass']);
            if ($passError !== '') {
                $errors['staff_pass'] = $passError;
            }
        }

        return $errors;
    }

    /**
     * Create a staff account.
     * @return array ['success' => true, 'message' => ...] or ['success' => false, 'errors' => [...]]
     */
    public function addStaff(array $data, int $adminId): array
    {
        $errors = $this->validateStaff($data, true);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        if ($this->staff->emailExists($data['staff_email'])) {
            return ['success' => false, 'errors' => ['staff_email' => 'Another staff account already uses this email.']];
        }

        // Save phone numbers the same way as customers: 024... becomes +23324...
        $contact = preg_replace('/^0/', '+233', $data['staff_contact']);
        $hash    = password_hash($data['staff_pass'], PASSWORD_DEFAULT);

        $newId = $this->staff->addStaff((int) $data['role_id'], $data['staff_fname'], $data['staff_lname'],
                                        $data['staff_email'], $contact, $hash);
        if ($newId === false) {
            return ['success' => false, 'errors' => ['form' => "We couldn't create the account. Please try again."]];
        }

        $name = $data['staff_fname'] . ' ' . $data['staff_lname'];
        $this->audit->log($adminId, 'CREATE', 'Added staff account for ' . $name . ' (' . $this->roleName((int) $data['role_id']) . ')',
                          'co_staff', $newId);
        return ['success' => true, 'message' => 'Account created for ' . $name . '. Give them their password in person.'];
    }

    /**
     * Update a staff member's details and role (not the password).
     */
    public function updateStaff(int $staffId, array $data, int $adminId): array
    {
        $old = $this->staff->getStaffById($staffId);
        if (!$old) {
            return ['success' => false, 'errors' => ['form' => 'That staff account no longer exists.']];
        }

        $errors = $this->validateStaff($data, false);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        if ($this->staff->emailExists($data['staff_email'], $staffId)) {
            return ['success' => false, 'errors' => ['staff_email' => 'Another staff account already uses this email.']];
        }

        // Safety rule (see the top of this section)
        $newRole = $this->roleName((int) $data['role_id']);
        if ($staffId === $adminId && $newRole !== 'Admin') {
            return ['success' => false,
                    'errors'  => ['role_id' => 'You cannot remove your own Admin role. Ask another Admin to do it.']];
        }

        $contact = preg_replace('/^0/', '+233', $data['staff_contact']);
        $ok = $this->staff->updateStaff($staffId, (int) $data['role_id'], $data['staff_fname'],
                                        $data['staff_lname'], $data['staff_email'], $contact);
        if (!$ok) {
            return ['success' => false, 'errors' => ['form' => "We couldn't save the changes. Please try again."]];
        }

        // Write down what changed, so the audit trail is useful
        $name    = $data['staff_fname'] . ' ' . $data['staff_lname'];
        $details = 'Updated staff account for ' . $name;
        if ($old['role_name'] !== $newRole) {
            $details .= ' (role ' . $old['role_name'] . ' to ' . $newRole . ')';
        }
        $this->audit->log($adminId, 'UPDATE', $details, 'co_staff', $staffId);

        return ['success' => true, 'message' => 'Changes saved for ' . $name . '.'];
    }

    /**
     * Disable (0) or enable (1) an account. Disabled staff cannot log in,
     * and are logged out on their next click (see require_staff() in core.php).
     */
    public function setActive(int $staffId, int $isActive, int $adminId): array
    {
        $old = $this->staff->getStaffById($staffId);
        if (!$old) {
            return ['success' => false, 'message' => 'That staff account no longer exists.'];
        }

        // Safety rule (see the top of this section)
        if ($staffId === $adminId && $isActive === 0) {
            return ['success' => false, 'message' => 'You cannot disable your own account.'];
        }

        if (!$this->staff->setActive($staffId, $isActive)) {
            return ['success' => false, 'message' => "We couldn't change the account. Please try again."];
        }

        $name   = $old['staff_fname'] . ' ' . $old['staff_lname'];
        $action = $isActive === 1 ? 'ENABLE' : 'DISABLE';
        $this->audit->log($adminId, $action, ucfirst(strtolower($action)) . 'd staff account for ' . $name,
                          'co_staff', $staffId);

        return ['success' => true,
                'message' => $isActive === 1 ? $name . ' can log in again.' : $name . "'s account is disabled."];
    }

    /**
     * The Admin sets a new password for a staff member who forgot theirs.
     * The password itself is never written to the audit log.
     */
    public function resetPassword(int $staffId, string $pass, string $confirm, int $adminId): array
    {
        $old = $this->staff->getStaffById($staffId);
        if (!$old) {
            return ['success' => false, 'errors' => ['form' => 'That staff account no longer exists.']];
        }

        $passError = $this->checkPassword($pass, $confirm);
        if ($passError !== '') {
            return ['success' => false, 'errors' => ['new_pass' => $passError]];
        }

        if (!$this->staff->changePassword($staffId, password_hash($pass, PASSWORD_DEFAULT))) {
            return ['success' => false, 'errors' => ['form' => "We couldn't change the password. Please try again."]];
        }

        $name = $old['staff_fname'] . ' ' . $old['staff_lname'];
        $this->audit->log($adminId, 'PASSWORD_RESET', 'Reset the password for ' . $name, 'co_staff', $staffId);
        return ['success' => true, 'message' => 'Password changed for ' . $name . '. Give them the new password in person.'];
    }

    // =================================================================
    // Helpers used only inside this controller
    // =================================================================

    /** Same password rule as customer accounts. @return string  an error message, or '' */
    private function checkPassword(string $pass, string $confirm): string
    {
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            return 'Use at least 8 characters with at least one letter and one number.';
        }
        if ($pass !== $confirm) {
            return 'The passwords do not match.';
        }
        return '';
    }

    /** The name of a role, for messages and the audit trail. */
    private function roleName(int $roleId): string
    {
        foreach ($this->staff->getAllRoles() as $role) {
            if ((int) $role['role_id'] === $roleId) {
                return $role['role_name'];
            }
        }
        return '';
    }
}