<?php
// Paystack sends the customer back here after the payment page:
//   actions/payment_success.php?trxref=CO2026...&reference=CO2026...
//
// This is a GET request from Paystack, not one of our forms, so there is
// no CSRF token. That is safe because nothing in the address is trusted:
// the reference is only used to ask Paystack directly what was paid, and
// the payment must belong to the logged-in customer.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/OrderController.php';

require_login();

// Paystack references we create look like CO20261125143005A1B2C3
$reference = $_GET['reference'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{6,100}$/', $reference)) {
    set_flash('error', 'That payment link is not valid.');
    redirect('views/cart.php');
}

$customer = [
    'customer_id'    => (int) $_SESSION['customer_id'],
    'customer_email' => $_SESSION['customer_email'],
];

$controller = new OrderController();
$result = $controller->completePayment($reference, $customer, $_SESSION['checkout'] ?? null);

if (!$result['success']) {
    set_flash('error', $result['message']);
    redirect('views/payment_failed.php');
}

// The order is saved, so the snapshot is no longer needed
unset($_SESSION['checkout']);

set_flash('success', $result['message']);
redirect('views/payment_success.php?order=' . (int) $result['order_id']);
