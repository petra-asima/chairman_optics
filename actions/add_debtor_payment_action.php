<?php
// Records a payment on an existing order: a deposit balance, or money
// owed by a debtor. Used by views/admin/record_payment.php.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AccountingController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/payments.php');
}

// The front desk and the Accountant (and the Admin) receive payments
require_staff(['Receptionist', 'Accountant']);

$orderId  = (int) ($_POST['order_id'] ?? 0);
$formPage = 'views/admin/record_payment.php?order_id=' . $orderId;

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($formPage);
}

$data = [
    'amount'    => trim($_POST['amount'] ?? ''),
    'method'    => $_POST['method'] ?? '',
    'reference' => trim($_POST['reference'] ?? ''),
];

$controller = new AccountingController();
$result = $controller->recordPayment($orderId, $data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/payments.php');
