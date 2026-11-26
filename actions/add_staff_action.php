<?php
// Handles the "Add staff account" form (views/admin/staff_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/StaffController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/staff_form.php');
}

// Only the Admin manages staff accounts
require_staff(['Admin']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/staff_form.php');
}

$data = [
    'staff_fname'   => trim($_POST['staff_fname'] ?? ''),
    'staff_lname'   => trim($_POST['staff_lname'] ?? ''),
    'staff_email'   => strtolower(trim($_POST['staff_email'] ?? '')),
    'staff_contact' => str_replace(' ', '', trim($_POST['staff_contact'] ?? '')),
    'role_id'       => $_POST['role_id'] ?? '',
    'staff_pass'    => $_POST['staff_pass'] ?? '',
    'confirm_pass'  => $_POST['confirm_pass'] ?? '',
];

$controller = new StaffController();
$result = $controller->addStaff($data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    // Send the errors back and keep what was typed (never the passwords)
    $_SESSION['form_errors'] = $result['errors'];
    unset($data['staff_pass'], $data['confirm_pass']);
    set_old($data);
    redirect('views/admin/staff_form.php');
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/staff.php');
