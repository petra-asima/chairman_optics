<?php
// Handles the "Edit staff details" form (views/admin/staff_form.php?id=N).
// The password is changed separately, by reset_staff_pass_action.php.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/StaffController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/staff.php');
}

// Only the Admin manages staff accounts
require_staff(['Admin']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/staff.php');
}

$staffId  = (int) ($_POST['staff_id'] ?? 0);
$formPage = 'views/admin/staff_form.php?id=' . $staffId;

$data = [
    'staff_fname'   => trim($_POST['staff_fname'] ?? ''),
    'staff_lname'   => trim($_POST['staff_lname'] ?? ''),
    'staff_email'   => strtolower(trim($_POST['staff_email'] ?? '')),
    'staff_contact' => str_replace(' ', '', trim($_POST['staff_contact'] ?? '')),
    'role_id'       => $_POST['role_id'] ?? '',
];

$controller = new StaffController();
$result = $controller->updateStaff($staffId, $data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/staff.php');
