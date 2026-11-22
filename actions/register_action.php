<?php
// Handles the registration form.
// Reads $_POST, calls the controller, then redirects.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CustomerController.php';

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/register.php');
}

// Reject forms that did not come from our own page
if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/register.php');
}

$data = [
    'fname'    => trim($_POST['customer_fname'] ?? ''),
    'lname'    => trim($_POST['customer_lname'] ?? ''),
    'gender'   => $_POST['customer_gender'] ?? '',
    'email'    => strtolower(trim($_POST['customer_email'] ?? '')),
    'contact'  => str_replace(' ', '', trim($_POST['customer_contact'] ?? '')),
    'password' => $_POST['customer_pass'] ?? '',
    'confirm'  => $_POST['confirm_pass'] ?? '',
];

$ctrl   = new CustomerController();
$result = $ctrl->register($data);

if (!$result['success']) {
    // Send the errors back, and keep what was typed (never the passwords)
    $_SESSION['form_errors'] = $result['errors'];
    set_old([
        'customer_fname'   => $data['fname'],
        'customer_lname'   => $data['lname'],
        'customer_gender'  => $data['gender'],
        'customer_email'   => $data['email'],
        'customer_contact' => $data['contact'],
    ]);
    redirect('views/register.php');
}

clear_old();
set_flash('success', 'Your account has been created. Please log in.');
redirect('views/login.php');