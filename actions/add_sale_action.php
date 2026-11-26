<?php
// Handles the in-store sale form (views/admin/sale_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/OrderController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/sale_form.php');
}

// Sales are made at the front desk (and by the Admin)
require_staff(['Receptionist']);

$folderNo = strtoupper(trim($_POST['folder_no'] ?? ''));
$formPage = 'views/admin/sale_form.php' . ($folderNo !== '' ? '?folder=' . rawurlencode($folderNo) : '');

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($formPage);
}

// Read the sale lines (the form has OrderController::SALE_LINES of them)
$lines = [];
for ($n = 0; $n < OrderController::SALE_LINES; $n++) {
    $lines[] = [
        'product_id'      => $_POST['product_id'][$n] ?? '0',
        'qty'             => trim($_POST['qty'][$n] ?? '1'),
        'lens_type_id'    => $_POST['lens_type_id'][$n] ?? '0',
        'lens_coating_id' => $_POST['lens_coating_id'][$n] ?? '0',
        'prescription_id' => $_POST['prescription_id'][$n] ?? '0',
    ];
}

$data = [
    'folder_no'   => $folderNo,
    'lines'       => $lines,
    'amount_paid' => trim($_POST['amount_paid'] ?? ''),
    'method'      => $_POST['method'] ?? '',
    'reference'   => trim($_POST['reference'] ?? ''),
    'notes'       => trim($_POST['notes'] ?? ''),
];

$controller = new OrderController();
$result = $controller->createInStoreSale($data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/order_detail.php?id=' . (int) $result['order_id']);
