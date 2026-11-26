<?php
// Tabs across the top of every Products and Stock page.
// $currentPage is set in admin_header.php.
$productTabs = [
    'product.php'      => ['label' => 'Products',     'icon' => 'inventory_2'],
    'brand.php'        => ['label' => 'Brands',       'icon' => 'sell'],
    'category.php'     => ['label' => 'Categories',   'icon' => 'category'],
    'stock.php'        => ['label' => 'Stock',        'icon' => 'warehouse'],
    'lens_options.php' => ['label' => 'Lens Options', 'icon' => 'eyeglasses'],
];

// The add/edit form belongs under the Products tab
$activeTab = $currentPage === 'product_form.php' ? 'product.php' : $currentPage;
?>
<nav class="admin-tabs" aria-label="Products and stock sections">
    <?php foreach ($productTabs as $file => $tab): ?>
        <a href="<?= url('views/admin/' . $file) ?>"
           class="<?= $activeTab === $file ? 'active' : '' ?>"
           <?= $activeTab === $file ? 'aria-current="page"' : '' ?>>
            <span class="material-symbols-outlined" aria-hidden="true"><?= e($tab['icon']) ?></span>
            <?= e($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
