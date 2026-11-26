<?php
// Handles the "Pay with Paystack" button on the checkout page.
// Checks the cart and delivery details, saves a snapshot of the order
// in the session, then sends the customer to Paystack's payment page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/OrderController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/checkout.php');
}

// Only logged-in customers can pay (Paystack needs their email, and the
// order must belong to an account)
require_login();

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/checkout.php');
}

$data = [
    'delivery_method'  => $_POST['delivery_method'] ?? '',
    'delivery_address' => trim($_POST['delivery_address'] ?? ''),
    'notes'            => trim($_POST['notes'] ?? ''),
];

$customer = [
    'customer_id'    => (int) $_SESSION['customer_id'],
    'customer_email' => $_SESSION['customer_email'],
];

// The full address Paystack sends the customer back to after paying
$scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$callbackUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('actions/payment_success.php');

$controller = new OrderController();
$result = $controller->startCheckout($customer, $data, $callbackUrl);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect('views/checkout.php');
}

// Remember exactly what is being paid for, to create the order afterwards
$_SESSION['checkout'] = $result['checkout'];
clear_old();

// Paystack's page is on another website, so redirect() (which adds
// BASE_URL) is not used here
header('Location: ' . $result['payment_url']);
exit;
