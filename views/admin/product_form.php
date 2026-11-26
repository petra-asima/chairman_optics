<?php
// One form for adding a product and for editing one.
//   product_form.php        → add a new product
//   product_form.php?id=12  → edit product 12
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

$controller = new ProductController();

// Edit mode: load the product, or go back if it does not exist
$productId = (int) ($_GET['id'] ?? 0);
$product   = false;
if ($productId > 0) {
    $product = $controller->getProductById($productId);
    if (!$product) {
        set_flash('error', 'That product was not found.');
        redirect('views/admin/product.php');
    }
}
$isEdit = $product !== false;

$categories = $controller->getCategories();
$brands     = $controller->getBrands();
$errors     = get_form_errors();

// Which values to show in the boxes:
//  1. what was just typed, if the form came back with errors
//  2. otherwise the saved product (edit mode)
//  3. otherwise sensible starting values (add mode)
if (isset($_SESSION['old'])) {
    $values = $_SESSION['old'];
} elseif ($isEdit) {
    $values = $product;
} else {
    $values = ['is_active' => '1', 'reorder_level' => '5', 'opening_stock' => '0'];
}

// Small helpers for this page only
$val     = fn(string $key): string => e($values[$key] ?? '');
$checked = fn(string $key): string => (string) ($values[$key] ?? '0') === '1' ? 'checked' : '';

$isService = (string) ($values['is_service'] ?? '0') === '1';

$pageTitle = $isEdit ? 'Edit Product' : 'Add Product';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<p><a href="<?= url('views/admin/product.php') ?>" class="admin-link-small">&larr; Back to all products</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<form action="<?= url($isEdit ? 'actions/update_product_action.php' : 'actions/add_product_action.php') ?>"
      method="POST" enctype="multipart/form-data" id="product-form" class="admin-form" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
    <?php endif; ?>

    <p class="field-hint">Fields marked <span class="required">*</span> are required.</p>

    <!-- ---------- Product details ---------- -->
    <fieldset class="admin-panel">
        <legend>Product details</legend>

        <div class="form-group">
            <label for="product_title">Product name <span class="required">*</span></label>
            <input type="text" id="product_title" name="product_title" value="<?= $val('product_title') ?>"
                   maxlength="200" placeholder="[e.g. brand, model and colour]" required>
            <?= field_error($errors, 'product_title') ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="product_cat">Category <span class="required">*</span></label>
                <select id="product_cat" name="product_cat" required>
                    <option value="">-- Select category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['cat_id'] ?>"
                            <?= (int) ($values['product_cat'] ?? 0) === (int) $cat['cat_id'] ? 'selected' : '' ?>>
                            <?= e($cat['cat_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'product_cat') ?>
            </div>

            <div class="form-group">
                <label for="product_brand">Brand</label>
                <select id="product_brand" name="product_brand">
                    <option value="0">-- No brand --</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= (int) $brand['brand_id'] ?>"
                            <?= (int) ($values['product_brand'] ?? 0) === (int) $brand['brand_id'] ? 'selected' : '' ?>>
                            <?= e($brand['brand_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">Brand missing? <a href="<?= url('views/admin/brand.php') ?>">Add it on the Brands tab</a> first.</p>
                <?= field_error($errors, 'product_brand') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="product_price">Price (GH₵) <span class="required">*</span></label>
                <input type="text" id="product_price" name="product_price" value="<?= $val('product_price') ?>"
                       inputmode="decimal" placeholder="0.00" required>
                <?= field_error($errors, 'product_price') ?>
            </div>

            <div class="form-group">
                <label for="product_colour">Colour</label>
                <input type="text" id="product_colour" name="product_colour" value="<?= $val('product_colour') ?>"
                       maxlength="50" placeholder="e.g. Black">
                <p class="field-hint">Used by the colour filter in the shop.</p>
                <?= field_error($errors, 'product_colour') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="product_gender">Made for</label>
                <select id="product_gender" name="product_gender">
                    <option value="">-- Not set --</option>
                    <?php foreach (ProductController::PRODUCT_GENDERS as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= ($values['product_gender'] ?? '') === $gender ? 'selected' : '' ?>>
                            <?= e($gender) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'product_gender') ?>
            </div>

            <div class="form-group">
                <label for="product_keywords">Search keywords</label>
                <input type="text" id="product_keywords" name="product_keywords" value="<?= $val('product_keywords') ?>"
                       maxlength="255" placeholder="e.g. round, metal, light">
                <p class="field-hint">Separate words with commas. Customers can find the product with these.</p>
                <?= field_error($errors, 'product_keywords') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="product_desc">Description</label>
            <textarea id="product_desc" name="product_desc" rows="4" maxlength="2000"><?= $val('product_desc') ?></textarea>
            <?= field_error($errors, 'product_desc') ?>
        </div>

        <div class="check-list">
            <label class="check-label">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ?>>
                Show in the shop
            </label>
            <label class="check-label">
                <input type="checkbox" name="is_service" id="is_service" value="1" <?= $checked('is_service') ?>>
                This is a service (for example an eye exam fee), so it has no stock
            </label>
            <label class="check-label js-stock-fields" <?= $isService ? 'hidden' : '' ?>>
                <input type="checkbox" name="needs_lenses" value="1" <?= $checked('needs_lenses') ?>>
                This frame can take prescription lenses
            </label>
        </div>
    </fieldset>

    <!-- ---------- Stock (hidden for services) ---------- -->
    <fieldset class="admin-panel js-stock-fields" <?= $isService ? 'hidden' : '' ?>>
        <legend>Stock</legend>

        <div class="form-row">
            <?php if ($isEdit): ?>
                <div class="form-group">
                    <span class="label-text">Current stock</span>
                    <p class="stock-now"><?= (int) $product['product_stock'] ?></p>
                    <p class="field-hint">
                        To change stock, use the <a href="<?= url('views/admin/stock.php?product_id=' . (int) $product['product_id']) ?>">Stock tab</a>,
                        so every change is recorded with a reason.
                    </p>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="opening_stock">Opening stock</label>
                    <input type="number" id="opening_stock" name="opening_stock" value="<?= $val('opening_stock') ?>"
                           min="0" step="1">
                    <p class="field-hint">How many you have now. Saved in the stock history as a restock.</p>
                    <?= field_error($errors, 'opening_stock') ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="reorder_level">Reorder level</label>
                <input type="number" id="reorder_level" name="reorder_level" value="<?= $val('reorder_level') ?>"
                       min="0" step="1">
                <p class="field-hint">A low-stock alert shows when stock falls to this number or below.</p>
                <?= field_error($errors, 'reorder_level') ?>
            </div>
        </div>
    </fieldset>

    <!-- ---------- Image ---------- -->
    <fieldset class="admin-panel">
        <legend>Product image</legend>

        <div class="image-field">
            <?php $currentImage = $isEdit ? $product['product_image'] : null; ?>
            <img id="image-preview" class="image-preview"
                 src="<?= $currentImage ? url($currentImage) : '' ?>"
                 alt="Product image preview" <?= $currentImage ? '' : 'hidden' ?>>

            <div class="form-group">
                <label for="product_image"><?= $currentImage ? 'Replace image' : 'Choose image' ?></label>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp">
                <p class="field-hint">JPG, PNG or WEBP, up to 2 MB. A square photo on a white background looks best.</p>
                <?= field_error($errors, 'product_image') ?>
            </div>
        </div>
    </fieldset>

    <div class="admin-form-buttons">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
        <a href="<?= url('views/admin/product.php') ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
