<?php
// Shown when a payment was not completed or could not be confirmed.
// The reason is in the flash message set by actions/payment_success.php.
require_once __DIR__ . '/../core/core.php';

$pageTitle = 'Payment not completed';
require __DIR__ . '/layout/header.php';
?>

<div class="container order-done">
    <div class="order-done-box">
        <span class="material-symbols-outlined order-done-icon order-failed-icon" aria-hidden="true">error</span>
        <h1>Your payment was not completed</h1>
        <p>Your cart has been kept, so you can try again.</p>
        <p>
            <a href="<?= url('views/checkout.php') ?>" class="btn btn-primary">Try Again</a>
            <a href="<?= url('views/cart.php') ?>" class="btn btn-light">Back to Cart</a>
        </p>
        <p class="field-hint">Need help? Call us on <?= e(SITE_PHONE) ?> or visit us at <?= e(SITE_ADDRESS) ?>.</p>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
