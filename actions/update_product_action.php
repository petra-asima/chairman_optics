<?php
// Handles the "Edit product" form (views/admin/product_form.php?id=N).
// Stock is not changed here. It is changed on the Stock page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/product.php');
}

// Only Receptionists (and the Admin) manage products
require_staff(['Receptionist']);

// An upload bigger than the server allows empties $_POST (see add_product_action.php)
if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    set_flash('error', 'The image is too large to upload. Please use one under 2 MB.');
    redirect('views/admin/product.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/product.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$formPage  = 'views/admin/product_form.php?id=' . $productId;

$data = [
    'product_cat'      => $_POST['product_cat'] ?? '',
    'product_brand'    => $_POST['product_brand'] ?? '',
    'product_title'    => trim($_POST['product_title'] ?? ''),
    'product_price'    => trim($_POST['product_price'] ?? ''),
    'product_desc'     => trim($_POST['product_desc'] ?? ''),
    'product_keywords' => trim($_POST['product_keywords'] ?? ''),
    'product_colour'   => trim($_POST['product_colour'] ?? ''),
    'product_gender'   => $_POST['product_gender'] ?? '',
    'reorder_level'    => trim($_POST['reorder_level'] ?? ''),
    'is_service'       => isset($_POST['is_service']) ? '1' : '0',
    'needs_lenses'     => isset($_POST['needs_lenses']) ? '1' : '0',
    'is_active'        => isset($_POST['is_active']) ? '1' : '0',
];

$controller = new ProductController();
$result = $controller->updateProduct($productId, $data, $_FILES['product_image'] ?? [],
                                     (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/product.php');
