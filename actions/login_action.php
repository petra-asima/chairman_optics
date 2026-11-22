<?php
// Handles the login form.
// Reads $_POST, calls the controller, sets the session, redirects.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CustomerController.php';

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/login.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/login.php');
}

$email = strtolower(trim($_POST['customer_email'] ?? ''));
$pass  = $_POST['customer_pass'] ?? '';

// Server-side validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
    set_flash('error', 'Enter a valid email address and your password.');
    set_old(['customer_email' => $email]);
    redirect('views/login.php');
}

$ctrl   = new CustomerController();
$result = $ctrl->login($email, $pass);

if (isset($result['error'])) {
    set_flash('error', $result['error']);
    set_old(['customer_email' => $email]);
    redirect('views/login.php');
}

// New session ID at login stops session fixation attacks
session_regenerate_id(true);
clear_old();

$_SESSION['customer_id']    = (int) $result['customer_id'];
$_SESSION['customer_name']  = $result['customer_fname'];
$_SESSION['customer_email'] = $result['customer_email'];

set_flash('success', 'Welcome back, ' . $result['customer_fname'] . '.');
redirect('index.php');