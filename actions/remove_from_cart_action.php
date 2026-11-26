<?php
// Handles the "Remove" button on each line of the cart page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CartController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/cart.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/cart.php');
}

$cartId = (int) ($_POST['cart_id'] ?? 0);

$controller = new CartController();
$result = $controller->removeItem(cart_owner(), $cartId);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/cart.php');
