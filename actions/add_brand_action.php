<?php
// Handles the "Add brand" form on the Brand page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/brand.php');
}

// Only Receptionists (and the Admin) manage products
require_staff(['Receptionist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/brand.php');
}

$name = trim($_POST['brand_name'] ?? '');

$controller = new ProductController();
$result = $controller->addBrand($name, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/brand.php');
