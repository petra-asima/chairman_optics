<?php
// Logs a staff member out. Uses a POST form with a CSRF token, so
// another website cannot log staff out with a hidden link.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/StaffController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    redirect('views/admin/dashboard.php');
}

if (is_staff_logged_in()) {
    $controller = new StaffController();
    $controller->logout((int) $_SESSION['staff_id']);
}

clear_staff_session();
session_regenerate_id(true);

set_flash('success', 'You have been logged out.');
redirect('views/admin/login.php');