<?php
// The shopping cart: what the customer has chosen, with quantities and total.
// Works for guests and logged-in customers (see cart_owner() in core.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/CartController.php';

$controller = new CartController();
$cart       = $controller->getCartSummary(cart_owner());

$pageTitle = 'My Cart';
require __DIR__ . '/layout/header.php';
?>

<section class="shop-banner">
    <div class="container">
        <h1>My Cart</h1>
        <p>
            <?php if ($cart['item_count'] > 0): ?>
                <?= $cart['item_count'] ?> item<?= $cart['item_count'] === 1 ? '' : 's' ?> ready for checkout.
            <?php else: ?>
                Your cart is empty.
            <?php endif; ?>
        </p>
    </div>
</section>

<div class="container cart-page">

    <?php if (empty($cart['items'])): ?>
        <div class="shop-empty">
            <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
            <h2>Your cart is empty</h2>
            <p>Browse our frames, sunglasses and eye care products.</p>
            <p><a href="<?= url('views/all_products.php') ?>" class="btn btn-primary">Go to the Shop</a></p>
        </div>
    <?php else: ?>
        <div class="cart-layout">

            <!-- ---------- The items ---------- -->
            <ul class="cart-items" aria-label="Items in your cart">
                <?php foreach ($cart['items'] as $item): ?>
                    <?php $link = url('views/single_product.php?id=' . (int) $item['product_id']); ?>
                    <li class="cart-item <?= $item['available'] ? '' : 'cart-item-unavailable' ?>">
                        <a href="<?= $link ?>" class="cart-item-image" tabindex="-1" aria-hidden="true">
                            <?php if ($item['product_image']): ?>
                                <img src="<?= url($item['product_image']) ?>" alt="">
                            <?php else: ?>
                                <span class="material-symbols-outlined product-no-image">eyeglasses</span>
                            <?php endif; ?>
                        </a>

                        <div class="cart-item-info">
                            <?php if ($item['brand_name']): ?>
                                <p class="product-card-brand"><?= e($item['brand_name']) ?></p>
                            <?php endif; ?>
                            <h2 class="cart-item-title"><a href="<?= $link ?>"><?= e($item['product_title']) ?></a></h2>
                            <p class="cart-item-meta">
                                <?= money($item['product_price']) ?> each
                                <?= $item['product_colour'] ? '&middot; ' . e($item['product_colour']) : '' ?>
                            </p>

                            <?php if ($item['problem'] !== ''): ?>
                                <p class="cart-item-problem">
                                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                                    <?= e($item['problem']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="cart-item-actions">
                            <?php if ($item['available']): ?>
                                <!-- Change the quantity (js/cart.js saves it as soon as it changes) -->
                                <form action="<?= url('actions/update_qty_action.php') ?>" method="POST" class="cart-qty-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                    <label for="qty-<?= (int) $item['cart_id'] ?>" class="visually-hidden">
                                        Quantity of <?= e($item['product_title']) ?>
                                    </label>
                                    <input type="number" id="qty-<?= (int) $item['cart_id'] ?>" name="qty"
                                           value="<?= (int) $item['qty'] ?>" min="1" max="<?= (int) $item['max_qty'] ?>"
                                           class="qty-input js-qty">
                                    <button type="submit" class="btn btn-light btn-update">Update</button>
                                </form>
                            <?php endif; ?>

                            <p class="cart-line-total"><?= money($item['line_total']) ?></p>

                            <form action="<?= url('actions/remove_from_cart_action.php') ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                <button type="submit" class="cart-remove">
                                    <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                                    Remove<span class="visually-hidden"> <?= e($item['product_title']) ?></span>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- ---------- Summary ---------- -->
            <aside class="cart-summary" aria-labelledby="summary-heading">
                <h2 id="summary-heading">Order summary</h2>

                <dl class="cart-totals">
                    <dt>Items (<?= $cart['item_count'] ?>)</dt>
                    <dd><?= money($cart['subtotal']) ?></dd>
                    <dt>Delivery</dt>
                    <dd>Chosen at checkout</dd>
                    <dt class="cart-total-label">Subtotal</dt>
                    <dd class="cart-total-value"><?= money($cart['subtotal']) ?></dd>
                </dl>

                <?php if ($cart['has_problems']): ?>
                    <p class="cart-summary-note">
                        <span class="material-symbols-outlined" aria-hidden="true">info</span>
                        Please fix the items marked above before checking out.
                    </p>
                <?php endif; ?>

                <?php if ($cart['has_problems']): ?>
                    <button type="button" class="btn btn-primary btn-block" disabled>Checkout</button>
                <?php else: ?>
                    <a href="<?= url('views/checkout.php') ?>" class="btn btn-primary btn-block">
                        <?= is_logged_in() ? 'Checkout' : 'Log In to Checkout' ?>
                    </a>
                <?php endif; ?>

                <a href="<?= url('views/all_products.php') ?>" class="cart-continue">&larr; Continue shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script src="<?= url('js/cart.js') ?>"></script>
<?php require __DIR__ . '/layout/footer.php'; ?>
