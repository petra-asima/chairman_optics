<?php
// Staff list of every product, with search, filters and low-stock badges.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

// Filters come from the search form (GET, so the page can be bookmarked)
$search  = trim($_GET['q'] ?? '');
$catId   = (int) ($_GET['cat'] ?? 0);
$lowOnly = isset($_GET['low']);

$controller = new ProductController();
$products   = $controller->getProducts($search, $catId, $lowOnly);
$categories = $controller->getCategories();
$lowCount   = count($controller->getLowStock());

$pageTitle = 'Products and Stock';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<?php if ($lowCount > 0): ?>
    <div class="alert alert-warning">
        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
        <?= $lowCount ?> product(s) are at or below their reorder level.
        <a href="<?= url('views/admin/stock.php') ?>">See low stock</a>
    </div>
<?php endif; ?>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-filters">
        <label for="q" class="visually-hidden">Search products</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Search by name or keyword">

        <label for="cat" class="visually-hidden">Category</label>
        <select id="cat" name="cat">
            <option value="0">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['cat_id'] ?>" <?= $catId === (int) $cat['cat_id'] ? 'selected' : '' ?>>
                    <?= e($cat['cat_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="check-label">
            <input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>> Low stock only
        </label>

        <button type="submit" class="btn btn-outline btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">search</span> Filter
        </button>
        <?php if ($search !== '' || $catId > 0 || $lowOnly): ?>
            <a href="<?= url('views/admin/product.php') ?>" class="admin-link-small">Clear</a>
        <?php endif; ?>
    </form>

    <a href="<?= url('views/admin/product_form.php') ?>" class="btn btn-primary btn-small">
        <span class="material-symbols-outlined" aria-hidden="true">add</span> Add Product
    </a>
</div>

<div class="admin-panel">
    <?php if (empty($products)): ?>
        <p class="admin-empty">
            No products found.
            <?php if ($search === '' && $catId === 0 && !$lowOnly): ?>
                <a href="<?= url('views/admin/product_form.php') ?>">Add the first product</a>.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Image</th>
                        <th scope="col">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col">Brand</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col" class="num">Stock</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $isService = (int) $p['is_service'] === 1;
                        $isLow     = !$isService && (int) $p['product_stock'] <= (int) $p['reorder_level'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($p['product_image']): ?>
                                    <img src="<?= url($p['product_image']) ?>" alt="" class="admin-thumb">
                                <?php else: ?>
                                    <span class="admin-thumb admin-thumb-empty material-symbols-outlined" aria-hidden="true">image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($p['product_title']) ?></strong>
                                <?php if ($p['product_colour']): ?>
                                    <br><small class="muted"><?= e($p['product_colour']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['cat_name']) ?></td>
                            <td><?= e($p['brand_name'] ?? '-') ?></td>
                            <td class="num"><?= money($p['product_price']) ?></td>
                            <td class="num">
                                <?php if ($isService): ?>
                                    <span class="muted">Service</span>
                                <?php else: ?>
                                    <?= (int) $p['product_stock'] ?>
                                    <?php if ($isLow): ?>
                                        <span class="badge badge-danger">Low</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $p['is_active'] === 1): ?>
                                    <span class="badge badge-success">In shop</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-actions">
                                <a href="<?= url('views/admin/product_form.php?id=' . (int) $p['product_id']) ?>"
                                   class="btn btn-outline btn-small">Edit</a>
                                <form action="<?= url('actions/delete_product_action.php') ?>" method="POST"
                                      data-confirm="Delete <?= e($p['product_title']) ?>? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-count"><?= count($products) ?> product(s)</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
