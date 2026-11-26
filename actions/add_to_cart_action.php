<?php
// Handles the "Add to Cart" form on a product page.
// Guests can add to a cart too. They get a cart code cookie the first time.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CartController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/all_products.php');
}

$productId   = (int) ($_POST['product_id'] ?? 0);
$productPage = 'views/single_product.php?id=' . $productId;

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($productPage);
}

// true = give a guest a cart code if they do not have one yet
$owner = cart_owner(true);

$controller = new CartController();
$result = $controller->addToCart($owner, $productId, (string) ($_POST['qty'] ?? '1'));

set_flash($result['success'] ? 'success' : 'error', $result['message']);

// After adding, show the cart. If it failed, stay on the product page.
redirect($result['success'] ? 'views/cart.php' : $productPage);
