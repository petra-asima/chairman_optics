<?php
// Handles the "Add lens option" form on the Lens Options page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/lens_options.php');
}

// Only Receptionists (and the Admin) manage prices
require_staff(['Receptionist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/lens_options.php');
}

$data = [
    'lens_name'    => trim($_POST['lens_name'] ?? ''),
    'option_group' => $_POST['option_group'] ?? '',
    'lens_price'   => trim($_POST['lens_price'] ?? ''),
    'lens_desc'    => trim($_POST['lens_desc'] ?? ''),
];

$controller = new ProductController();
$result = $controller->addLensOption($data, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/lens_options.php');
