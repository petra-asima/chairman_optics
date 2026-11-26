<?php
// Handles the "Update stock" form on the Stock page.
// Every change is saved in co_stock_movements with a reason.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/stock.php');
}

// Only Receptionists (and the Admin) manage stock
require_staff(['Receptionist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/stock.php');
}

$data = [
    'product_id' => $_POST['product_id'] ?? '',
    'reason'     => $_POST['reason'] ?? '',
    'qty'        => trim($_POST['qty'] ?? ''),
    'direction'  => ($_POST['direction'] ?? 'in') === 'out' ? 'out' : 'in',
    'note'       => trim($_POST['note'] ?? ''),
];

$controller = new ProductController();
$result = $controller->adjustStock($data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    set_old($data); // keep the choices so staff do not retype them
} else {
    clear_old();
}

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/stock.php');
