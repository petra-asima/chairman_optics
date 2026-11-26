<?php
// Handles the status buttons on views/admin/order_detail.php.
// Which statuses each role may choose is decided in
// OrderController::allowedStatuses(), the same list that draws the buttons.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/OrderController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/orders.php');
}

// Receptionists and Lab Technicians (and the Admin) work on orders
require_staff(['Receptionist', 'Lab Technician']);

$orderId    = (int) ($_POST['order_id'] ?? 0);
$detailPage = 'views/admin/order_detail.php?id=' . $orderId;

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($detailPage);
}

$newStatus = (string) ($_POST['new_status'] ?? '');
$note      = trim($_POST['note'] ?? '');

$controller = new OrderController();
$result = $controller->changeStatus($orderId, $newStatus, $note, (int) $_SESSION['staff_id'], staff_role());

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect($detailPage);
