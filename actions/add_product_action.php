<?php
// Handles the "Add product" form (views/admin/product_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/product_form.php');
}

// Only Receptionists (and the Admin) manage products
require_staff(['Receptionist']);

// If the upload is bigger than the server allows, PHP throws away the
// whole form, so $_POST is empty. Explain that instead of "session expired".
if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    set_flash('error', 'The image is too large to upload. Please use one under 2 MB.');
    redirect('views/admin/product_form.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/product_form.php');
}

// Read the form. Ticked checkboxes send '1'; unticked ones send nothing.
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
    'opening_stock'    => trim($_POST['opening_stock'] ?? ''),
    'is_service'       => isset($_POST['is_service']) ? '1' : '0',
    'needs_lenses'     => isset($_POST['needs_lenses']) ? '1' : '0',
    'is_active'        => isset($_POST['is_active']) ? '1' : '0',
];

$controller = new ProductController();
$result = $controller->addProduct($data, $_FILES['product_image'] ?? [], (int) $_SESSION['staff_id']);

if (!$result['success']) {
    // Send the errors back and keep what was typed
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect('views/admin/product_form.php');
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/product.php');
