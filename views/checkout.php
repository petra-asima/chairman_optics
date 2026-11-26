<?php
// Checkout: confirm the items, choose pickup or delivery, then pay with Paystack.
// Customers must be logged in. A guest is sent to log in, and their cart
// moves into their account (see login_action.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/CustomerController.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in or create an account to check out. Your cart will be kept.');
    redirect('views/login.php');
}

$cartController  = new CartController();
$orderController = new OrderController();
$cart = $cartController->getCartSummary(cart_owner());

// Nothing to pay for, or something needs fixing first: back to the cart
if (empty($cart['items'])) {
    set_flash('error', 'Your cart is empty.');
    redirect('views/cart.php');
}
if ($cart['has_problems']) {
    set_flash('error', 'Some items in your cart need attention before you can check out.');
    redirect('views/cart.php');
}

$customer = (new CustomerController())->getCustomerById((int) $_SESSION['customer_id']);
$errors   = get_form_errors();
$method   = $_SESSION['old']['delivery_method'] ?? 'Pickup';

$pageTitle = 'Checkout';
require __DIR__ . '/layout/header.php';
?>

<section class="shop-banner">
    <div class="container">
        <h1>Checkout</h1>
        <p>Check your order, choose how to receive it, then pay securely with Paystack.</p>
    </div>
</section>

<div class="container cart-page">

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <?php if (!$orderController->paymentsReady()): ?>
        <div class="alert alert-error" role="alert">
            Online payment is not available right now. Please visit us at <?= e(SITE_ADDRESS) ?> or call <?= e(SITE_PHONE) ?>.
        </div>
    <?php endif; ?>

    <form action="<?= url('actions/process_payment_action.php') ?>" method="POST" class="cart-layout" id="checkout-form" novalidate>
        <?= csrf_field() ?>

        <div>
            <!-- ---------- How to receive the order ---------- -->
            <fieldset class="checkout-box">
                <legend>How would you like to receive your order?</legend>

                <label class="choice-card">
                    <input type="radio" name="delivery_method" value="Pickup" <?= $method === 'Pickup' ? 'checked' : '' ?>>
                    <span>
                        <strong>Pick up at the shop</strong>
                        <small><?= e(SITE_ADDRESS) ?></small>
                    </span>
                </label>

                <label class="choice-card">
                    <input type="radio" name="delivery_method" value="Delivery" <?= $method === 'Delivery' ? 'checked' : '' ?>>
                    <span>
                        <strong>Delivery</strong>
                        <small>[Delivery areas and any delivery charge to be confirmed with the owner]</small>
                    </span>
                </label>
                <?= field_error($errors, 'delivery_method') ?>

                <!-- Shown when Delivery is chosen (js/cart.js). Without JavaScript it is always shown. -->
                <div class="form-group js-address">
                    <label for="delivery_address">Delivery address</label>
                    <textarea id="delivery_address" name="delivery_address" rows="3" maxlength="255"
                              placeholder="Area, street, house number and a landmark"><?= old('delivery_address') ?></textarea>
                    <?= field_error($errors, 'delivery_address') ?>
                </div>

                <div class="form-group">
                    <label for="notes">Notes for the shop (optional)</label>
                    <input type="text" id="notes" name="notes" maxlength="255" value="<?= old('notes') ?>"
                           placeholder="For example, a good time to call you">
                    <?= field_error($errors, 'notes') ?>
                </div>
            </fieldset>

            <!-- ---------- Contact details ---------- -->
            <section class="checkout-box">
                <h2>Your details</h2>
                <dl class="product-facts checkout-details">
                    <dt>Name</dt>
                    <dd><?= e($customer['customer_fname'] . ' ' . $customer['customer_lname']) ?></dd>
                    <dt>Email</dt>
                    <dd><?= e($customer['customer_email']) ?></dd>
                    <dt>Phone</dt>
                    <dd><?= e($customer['customer_contact']) ?></dd>
                </dl>
                <p class="field-hint">We use these details to contact you about this order.</p>
            </section>
        </div>

        <!-- ---------- Order summary and pay button ---------- -->
        <aside class="cart-summary" aria-labelledby="summary-heading">
            <h2 id="summary-heading">Your order</h2>

            <ul class="checkout-lines">
                <?php foreach ($cart['items'] as $item): ?>
                    <li>
                        <span><?= e($item['product_title']) ?> &times; <?= (int) $item['qty'] ?></span>
                        <span><?= money($item['line_total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dl class="cart-totals">
                <dt class="cart-total-label">Total to pay</dt>
                <dd class="cart-total-value"><?= money($cart['subtotal']) ?></dd>
            </dl>

            <button type="submit" class="btn btn-primary btn-block btn-pay" <?= $orderController->paymentsReady() ? '' : 'disabled' ?>>
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                Pay <?= money($cart['subtotal']) ?> with Paystack
            </button>
            <p class="field-hint checkout-secure">
                You will pay on Paystack's secure page by Mobile Money or card. We never see your card details.
            </p>

            <a href="<?= url('views/cart.php') ?>" class="cart-continue">&larr; Back to cart</a>
        </aside>
    </form>
</div>

<script src="<?= url('js/cart.js') ?>"></script>
<?php
clear_old();
require __DIR__ . '/layout/footer.php';
?>
