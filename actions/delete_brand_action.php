<?php
// Handles the "Delete" button on each row of the Brand page.
// Delete uses a POST form with a CSRF token, never a plain link.
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

$id = (int) ($_POST['brand_id'] ?? 0);

$controller = new ProductController();
$result = $controller->deleteBrand($id, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/brand.php');
