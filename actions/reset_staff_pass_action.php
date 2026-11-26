<?php
// Handles the "Reset password" form on views/admin/staff_form.php?id=N.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/StaffController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/staff.php');
}

// Only the Admin resets staff passwords
require_staff(['Admin']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/staff.php');
}

$staffId = (int) ($_POST['staff_id'] ?? 0);
$pass    = $_POST['new_pass'] ?? '';
$confirm = $_POST['confirm_new_pass'] ?? '';

$controller = new StaffController();
$result = $controller->resetPassword($staffId, $pass, $confirm, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    redirect('views/admin/staff_form.php?id=' . $staffId);
}

set_flash('success', $result['message']);
redirect('views/admin/staff.php');
