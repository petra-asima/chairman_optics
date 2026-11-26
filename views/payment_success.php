<?php
// Shown after a successful payment: the order number and what was bought.
// A customer can only see their own orders.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/OrderController.php';

require_login();

$controller = new OrderController();
$order = $controller->getCustomerOrder((int) ($_GET['order'] ?? 0), (int) $_SESSION['customer_id']);

if (!$order) {
    set_flash('error', 'That order was not found.');
    redirect('index.php');
}

$pageTitle = 'Order ' . $order['invoice_no'];
require __DIR__ . '/layout/header.php';
?>

<div class="container order-done">
    <div class="order-done-box">
        <span class="material-symbols-outlined order-done-icon" aria-hidden="true">check_circle</span>
        <h1>Thank you for your order</h1>
        <p>Your order number is <strong><?= e($order['invoice_no']) ?></strong>. Please keep it for your records.</p>

        <dl class="product-facts">
            <dt>Date</dt>
            <dd><?= e(date('d M Y, g:i a', strtotime($order['order_date']))) ?></dd>
            <dt>Status</dt>
            <dd><?= e($order['order_status']) ?></dd>
            <dt>Paid</dt>
            <dd><?= money($order['amount_paid']) ?> by <?= e($order['payment_method']) ?></dd>
            <dt>Payment reference</dt>
            <dd><?= e($order['pay_reference']) ?></dd>
            <dt><?= $order['delivery_method'] === 'Delivery' ? 'Deliver to' : 'Collect from' ?></dt>
            <dd><?= e($order['delivery_method'] === 'Delivery' ? $order['delivery_address'] : SITE_ADDRESS) ?></dd>
        </dl>

        <h2>Items</h2>
        <ul class="checkout-lines">
            <?php foreach ($order['items'] as $item): ?>
                <li>
                    <span><?= e($item['product_title']) ?> &times; <?= (int) $item['qty'] ?></span>
                    <span><?= money(($item['unit_price'] + $item['lens_price']) * $item['qty']) ?></span>
                </li>
            <?php endforeach; ?>
            <li class="checkout-lines-total">
                <span>Total</span>
                <span><?= money($order['order_total']) ?></span>
            </li>
        </ul>

        <p><a href="<?= url('views/all_products.php') ?>" class="btn btn-primary">Continue Shopping</a></p>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
