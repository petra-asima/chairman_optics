<?php
// Add, rename and delete product categories (frames, sunglasses ...).
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

$controller = new ProductController();
$categories = $controller->getCategories();

$pageTitle = 'Categories';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<div class="admin-split">

    <!-- Add a category -->
    <section class="admin-panel">
        <h2>Add a category</h2>
        <form action="<?= url('actions/add_category_action.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="cat_name">Category name <span class="required">*</span></label>
                <input type="text" id="cat_name" name="cat_name" maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-primary btn-small">
                <span class="material-symbols-outlined" aria-hidden="true">add</span> Add Category
            </button>
        </form>
    </section>

    <!-- All categories -->
    <section class="admin-panel">
        <h2>All categories</h2>

        <?php if (empty($categories)): ?>
            <p class="admin-empty">No categories yet.</p>
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
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <!-- Rename: change the name and press Save -->
                                    <form action="<?= url('actions/update_category_action.php') ?>" method="POST" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="cat_id" value="<?= (int) $cat['cat_id'] ?>">
                                        <label for="cat-<?= (int) $cat['cat_id'] ?>" class="visually-hidden">Category name</label>
                                        <input type="text" id="cat-<?= (int) $cat['cat_id'] ?>" name="cat_name"
                                               value="<?= e($cat['cat_name']) ?>" maxlength="100" required>
                                        <button type="submit" class="btn btn-outline btn-small">Save</button>
                                    </form>
                                </td>
                                <td class="num"><?= (int) $cat['product_count'] ?></td>
                                <td>
                                    <?php if ((int) $cat['product_count'] === 0): ?>
                                        <form action="<?= url('actions/delete_category_action.php') ?>" method="POST"
                                              data-confirm="Delete the category <?= e($cat['cat_name']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="cat_id" value="<?= (int) $cat['cat_id'] ?>">
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
