<?php
// A grid of product cards, used by the shop page and "You may also like".
// Set $gridProducts (an array of product rows) before including this file.
$gridProducts = $gridProducts ?? [];
?>
<ul class="product-grid">
    <?php foreach ($gridProducts as $item): ?>
        <?php
        $link  = url('views/single_product.php?id=' . (int) $item['product_id']);
        $stock = (int) $item['product_stock'];
        ?>
        <li class="product-card">
            <a href="<?= $link ?>" class="product-card-link">
                <div class="product-card-image">
                    <?php if ($item['product_image']): ?>
                        <img src="<?= url($item['product_image']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="material-symbols-outlined product-no-image" aria-hidden="true">eyeglasses</span>
                    <?php endif; ?>

                    <?php if ($stock === 0): ?>
                        <span class="product-flag product-flag-out">Out of stock</span>
                    <?php elseif ($stock <= 3): ?>
                        <span class="product-flag">Only <?= $stock ?> left</span>
                    <?php endif; ?>
                </div>

                <div class="product-card-body">
                    <p class="product-card-brand"><?= e($item['brand_name'] ?? $item['cat_name']) ?></p>
                    <h3 class="product-card-title"><?= e($item['product_title']) ?></h3>
                    <?php if ($item['product_colour']): ?>
                        <p class="product-card-colour"><?= e($item['product_colour']) ?></p>
                    <?php endif; ?>
                    <p class="product-card-price"><?= money($item['product_price']) ?></p>
                    <?php if ((int) $item['needs_lenses'] === 1): ?>
                        <p class="product-card-note">
                            <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            Can take prescription lenses
                        </p>
                    <?php endif; ?>
                </div>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
