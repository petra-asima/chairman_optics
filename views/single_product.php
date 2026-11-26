<?php
// One product's page in the shop: photo, details, price and stock.
// Opened from the shop as single_product.php?id=N
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/CartController.php';

$controller = new ProductController();
$productId  = (int) ($_GET['id'] ?? 0);
$product    = $productId > 0 ? $controller->getShopProduct($productId) : false;

// A missing, hidden or service product shows a "not found" page
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/layout/header.php';
    ?>
    <div class="container shop-empty shop-not-found">
        <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
        <h1>We couldn't find that product</h1>
        <p>It may have sold out or been removed from the shop.</p>
        <p><a href="<?= url('views/all_products.php') ?>" class="btn btn-primary">Back to the Shop</a></p>
    </div>
    <?php
    require __DIR__ . '/layout/footer.php';
    exit;
}

$related = $controller->getRelatedProducts((int) $product['product_cat'], $productId);
$stock   = (int) $product['product_stock'];

$pageTitle = $product['product_title'];
require __DIR__ . '/layout/header.php';
?>

<div class="container product-page">

    <!-- Breadcrumb: where this page sits in the shop -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('views/all_products.php') ?>">Shop</a></li>
            <li><a href="<?= url('views/all_products.php?cat=' . (int) $product['product_cat']) ?>"><?= e($product['cat_name']) ?></a></li>
            <li aria-current="page"><?= e($product['product_title']) ?></li>
        </ol>
    </nav>

    <div class="product-detail">
        <div class="product-detail-image">
            <?php if ($product['product_image']): ?>
                <img src="<?= url($product['product_image']) ?>" alt="<?= e($product['product_title']) ?>">
            <?php else: ?>
                <span class="material-symbols-outlined product-no-image" aria-hidden="true">eyeglasses</span>
            <?php endif; ?>
        </div>

        <div class="product-detail-info">
            <?php if ($product['brand_name']): ?>
                <p class="product-card-brand">
                    <a href="<?= url('views/all_products.php?brand=' . (int) $product['product_brand']) ?>"><?= e($product['brand_name']) ?></a>
                </p>
            <?php endif; ?>

            <h1><?= e($product['product_title']) ?></h1>
            <p class="product-detail-price"><?= money($product['product_price']) ?></p>

            <!-- Stock status: an icon and words, not colour alone -->
            <?php if ($stock === 0): ?>
                <p class="stock-status stock-out">
                    <span class="material-symbols-outlined" aria-hidden="true">cancel</span> Out of stock
                </p>
            <?php elseif ($stock <= 3): ?>
                <p class="stock-status stock-low">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span> Only <?= $stock ?> left
                </p>
            <?php else: ?>
                <p class="stock-status stock-in">
                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span> In stock
                </p>
            <?php endif; ?>

            <dl class="product-facts">
                <dt>Type</dt>
                <dd><?= e($product['cat_name']) ?></dd>
                <?php if ($product['product_colour']): ?>
                    <dt>Colour</dt>
                    <dd><?= e($product['product_colour']) ?></dd>
                <?php endif; ?>
                <?php if ($product['product_gender']): ?>
                    <dt>Made for</dt>
                    <dd><?= e($product['product_gender']) ?></dd>
                <?php endif; ?>
                <?php if ((int) $product['needs_lenses'] === 1): ?>
                    <dt>Lenses</dt>
                    <dd>Can be fitted with your prescription lenses</dd>
                <?php endif; ?>
            </dl>

            <?php if ($stock > 0): ?>
                <!-- Add to cart. Guests can add too; the cart moves into their account when they log in. -->
                <form action="<?= url('actions/add_to_cart_action.php') ?>" method="POST" class="add-to-cart">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">

                    <label for="qty">Quantity</label>
                    <div class="add-to-cart-row">
                        <input type="number" id="qty" name="qty" value="1" min="1"
                               max="<?= min($stock, CartController::MAX_QTY) ?>" class="qty-input">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span>
                            Add to Cart
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($product['product_desc']): ?>
                <div class="product-description">
                    <h2>About this product</h2>
                    <!-- nl2br keeps the line breaks staff typed; e() runs first so it stays safe -->
                    <p><?= nl2br(e($product['product_desc'])) ?></p>
                </div>
            <?php endif; ?>

            <p class="product-help">
                <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
                Want to try it on? Visit us at <?= e(SITE_ADDRESS) ?>.
            </p>
        </div>
    </div>

    <?php if (!empty($related)): ?>
        <section class="related-products" aria-labelledby="related-heading">
            <h2 id="related-heading">You may also like</h2>
            <?php
            $gridProducts = $related;
            require __DIR__ . '/layout/product_grid.php';
            ?>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
