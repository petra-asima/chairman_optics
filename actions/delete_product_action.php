<?php
// Handles the "Delete" button on the product list.
// Products that are on past orders are not deleted (the controller explains why).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/product.php');
}

// Only Receptionists (and the Admin) manage products
require_staff(['Receptionist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/product.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);

$controller = new ProductController();
$result = $controller->deleteProduct($productId, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/product.php');
