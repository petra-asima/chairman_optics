<?php
// Handles the staff login form.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/StaffController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/login.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/login.php');
}

$email = trim($_POST['staff_email'] ?? '');
$pass  = $_POST['staff_pass'] ?? '';

$controller = new StaffController();
$result = $controller->login($email, $pass);

if (!$result['success']) {
    set_old(['staff_email' => $email]);
    set_flash('error', $result['message']);
    redirect('views/admin/login.php');
}

$staff = $result['staff'];

// New session id after login, to protect against session fixation
session_regenerate_id(true);

$_SESSION['staff_id']          = (int) $staff['staff_id'];
$_SESSION['staff_name']        = $staff['staff_fname'] . ' ' . $staff['staff_lname'];
$_SESSION['staff_role']        = $staff['role_name'];
$_SESSION['staff_last_active'] = time();

clear_old();
set_flash('success', 'Welcome, ' . $staff['staff_fname'] . '. You are logged in as ' . $staff['role_name'] . '.');
redirect('views/admin/dashboard.php');