<?php
// Lens types and coatings, with their prices.
// These are offered to customers when they order prescription glasses.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

$controller  = new ProductController();
$lensOptions = $controller->getLensOptions();

$pageTitle = 'Lens Options';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<p class="admin-intro">
    The price of a pair of prescription glasses is the frame price plus the lens type plus any coating.
    Untick <strong>Offered</strong> to stop offering an option. Options are never deleted, because past orders use them.
</p>

<section class="admin-panel">
    <h2>Lens types and coatings</h2>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Group</th>
                    <th scope="col">Price (GH₵)</th>
                    <th scope="col">Short description</th>
                    <th scope="col">Offered</th>
                    <th scope="col"><span class="visually-hidden">Save</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lensOptions as $lens): ?>
                    <?php $formId = 'lens-' . (int) $lens['lens_id']; ?>
                    <tr>
                        <!-- Each row is its own form. The inputs use form="..." to join
                             the form in the last cell, because a <form> cannot wrap a table row. -->
                        <td>
                            <label for="<?= $formId ?>-name" class="visually-hidden">Name</label>
                            <input type="text" id="<?= $formId ?>-name" name="lens_name" form="<?= $formId ?>"
                                   value="<?= e($lens['lens_name']) ?>" maxlength="100" required>
                        </td>
                        <td>
                            <label for="<?= $formId ?>-group" class="visually-hidden">Group</label>
                            <select id="<?= $formId ?>-group" name="option_group" form="<?= $formId ?>">
                                <?php foreach (ProductController::LENS_GROUPS as $group): ?>
                                    <option value="<?= e($group) ?>" <?= $lens['option_group'] === $group ? 'selected' : '' ?>>
                                        <?= e($group) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <label for="<?= $formId ?>-price" class="visually-hidden">Price</label>
                            <input type="text" id="<?= $formId ?>-price" name="lens_price" form="<?= $formId ?>"
                                   value="<?= e($lens['lens_price']) ?>" inputmode="decimal" class="input-short" required>
                        </td>
                        <td>
                            <label for="<?= $formId ?>-desc" class="visually-hidden">Description</label>
                            <input type="text" id="<?= $formId ?>-desc" name="lens_desc" form="<?= $formId ?>"
                                   value="<?= e($lens['lens_desc'] ?? '') ?>" maxlength="255">
                        </td>
                        <td>
                            <label class="check-label">
                                <input type="checkbox" name="is_active" value="1" form="<?= $formId ?>"
                                       <?= (int) $lens['is_active'] === 1 ? 'checked' : '' ?>>
                                <span class="visually-hidden">Offered</span>
                            </label>
                        </td>
                        <td>
                            <form action="<?= url('actions/update_lens_action.php') ?>" method="POST" id="<?= $formId ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="lens_id" value="<?= (int) $lens['lens_id'] ?>">
                                <button type="submit" class="btn btn-outline btn-small">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <h2>Add a lens option</h2>
    <form action="<?= url('actions/add_lens_action.php') ?>" method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="lens_name">Name <span class="required">*</span></label>
                <input type="text" id="lens_name" name="lens_name" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="option_group">Group <span class="required">*</span></label>
                <select id="option_group" name="option_group" required>
                    <?php foreach (ProductController::LENS_GROUPS as $group): ?>
                        <option value="<?= e($group) ?>"><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="lens_price">Price (GH₵) <span class="required">*</span></label>
                <input type="text" id="lens_price" name="lens_price" inputmode="decimal" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label for="lens_desc">Short description</label>
                <input type="text" id="lens_desc" name="lens_desc" maxlength="255">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">add</span> Add Lens Option
        </button>
    </form>
</section>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
