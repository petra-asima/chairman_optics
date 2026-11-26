<?php
// Add, rename and delete frame and lens brands.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

$controller = new ProductController();
$brands     = $controller->getBrands();

$pageTitle = 'Brands';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<div class="admin-split">

    <!-- Add a brand -->
    <section class="admin-panel">
        <h2>Add a brand</h2>
        <form action="<?= url('actions/add_brand_action.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="brand_name">Brand name <span class="required">*</span></label>
                <input type="text" id="brand_name" name="brand_name" maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-primary btn-small">
                <span class="material-symbols-outlined" aria-hidden="true">add</span> Add Brand
            </button>
        </form>
    </section>

    <!-- All brands -->
    <section class="admin-panel">
        <h2>All brands</h2>

        <?php if (empty($brands)): ?>
            <p class="admin-empty">No brands yet. Add the brands the shop stocks.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="num">Products</th>
                            <th scope="col">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $brand): ?>
                            <tr>
                                <td>
                                    <!-- Rename: change the name and press Save -->
                                    <form action="<?= url('actions/update_brand_action.php') ?>" method="POST" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="brand_id" value="<?= (int) $brand['brand_id'] ?>">
                                        <label for="brand-<?= (int) $brand['brand_id'] ?>" class="visually-hidden">Brand name</label>
                                        <input type="text" id="brand-<?= (int) $brand['brand_id'] ?>" name="brand_name"
                                               value="<?= e($brand['brand_name']) ?>" maxlength="100" required>
                                        <button type="submit" class="btn btn-outline btn-small">Save</button>
                                    </form>
                                </td>
                                <td class="num"><?= (int) $brand['product_count'] ?></td>
                                <td>
                                    <?php if ((int) $brand['product_count'] === 0): ?>
                                        <form action="<?= url('actions/delete_brand_action.php') ?>" method="POST"
                                              data-confirm="Delete the brand <?= e($brand['brand_name']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="brand_id" value="<?= (int) $brand['brand_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <small class="muted">In use</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
