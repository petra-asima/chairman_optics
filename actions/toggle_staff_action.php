<?php
// Handles the "Disable" and "Enable" buttons on the staff list.
// Staff accounts are never deleted, because orders, exams and the
// audit trail point to them. Disabling stops the person logging in.
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
$isActive = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;

$controller = new StaffController();
$result = $controller->setActive($staffId, $isActive, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/staff.php');
