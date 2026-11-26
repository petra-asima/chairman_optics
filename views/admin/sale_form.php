<?php
// In-store sale at the counter.
// Step 1 (optional): enter the patient's folder number, so their
// prescriptions can be used for lenses. Leave it empty for a walk-in.
// Step 2: choose the products, then record what the customer pays now.
// The total shown while typing is only a preview; the server works out
// the real total from the database.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
require_once __DIR__ . '/../../controllers/ExamController.php';
require_staff(['Receptionist']);

$controller = new OrderController();
$errors     = get_form_errors();
$old        = $_SESSION['old'] ?? [];
$folder     = strtoupper(trim($old['folder_no'] ?? ($_GET['folder'] ?? '')));
$form       = $controller->getSaleFormData($folder);
$patient    = $form['patient'];

// The value a sale line had before the form came back with errors
$lineValue = fn(int $n, string $key, string $default = ''): string => (string) ($old['lines'][$n][$key] ?? $default);

// A short label for a prescription, e.g. "12 Nov 2026: RE -1.25 / -0.50 x 180, LE Plano / -0.75 x 90"
$rxLabel = function (array $rx): string {
    $eye = function (string $e) use ($rx): string {
        $text = ExamController::formatPower($rx[$e . '_sphere'], true);
        if ($rx[$e . '_cylinder'] !== null && (float) $rx[$e . '_cylinder'] != 0.0) {
            $text .= ' / ' . ExamController::formatPower($rx[$e . '_cylinder']) . ' x ' . (int) $rx[$e . '_axis'];
        }
        return $text;
    };
    return date('j M Y', strtotime($rx['created_at'])) . ': RE ' . $eye('re') . ', LE ' . $eye('le');
};

$pageTitle = 'New In-store Sale';
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/payments.php') ?>" class="admin-link-small">&larr; Back to payments</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<!-- Step 1: who is buying -->
<form method="GET" action="" class="admin-panel sale-who">
    <h2>1. Customer</h2>
    <div class="sale-who-row">
        <div class="form-group">
            <label for="folder">Patient folder number</label>
            <input type="text" id="folder" name="folder" value="<?= e($folder) ?>" placeholder="Leave empty for a walk-in" maxlength="20">
        </div>
        <button type="submit" class="btn btn-outline btn-small">Find</button>
    </div>
    <?php if ($patient): ?>
        <p class="sale-patient">
            <span class="badge badge-success">Found</span>
            <strong><?= e($patient['patient_fname'] . ' ' . $patient['patient_lname']) ?></strong> (<?= e($patient['folder_no']) ?>)
            &middot; <?= count($form['prescriptions']) ?> prescription(s) on file
        </p>
    <?php elseif ($folder !== ''): ?>
        <p class="field-error">No patient has folder <?= e($folder) ?>. Check the number, or leave it empty for a walk-in.</p>
    <?php else: ?>
        <p class="field-hint">Walk-in sale. To sell prescription lenses, find the patient first.</p>
    <?php endif; ?>
    <?= field_error($errors, 'folder_no') ?>
</form>

<!-- Step 2: what is being bought, and the payment -->
<form action="<?= url('actions/add_sale_action.php') ?>" method="POST" id="sale-form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="folder_no" value="<?= $patient ? e($patient['folder_no']) : '' ?>">

    <section class="admin-panel">
        <h2>2. Items</h2>
        <div class="table-wrap">
            <table class="admin-table sale-lines">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Qty</th>
                        <th scope="col">Lens type</th>
                        <th scope="col">Coating</th>
                        <th scope="col">Prescription</th>
                        <th scope="col" class="num">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($n = 0; $n < OrderController::SALE_LINES; $n++): ?>
                        <tr class="js-sale-line">
                            <td>
                                <label for="product-<?= $n ?>" class="visually-hidden">Product, line <?= $n + 1 ?></label>
                                <select id="product-<?= $n ?>" name="product_id[]" class="js-product">
                                    <option value="0">-- None --</option>
                                    <?php $group = null; ?>
                                    <?php foreach ($form['products'] as $p): ?>
                                        <?php if ($p['cat_name'] !== $group): ?>
                                            <?php if ($group !== null): ?></optgroup><?php endif; ?>
                                            <optgroup label="<?= e($p['cat_name']) ?>">
                                            <?php $group = $p['cat_name']; ?>
                                        <?php endif; ?>
                                        <option value="<?= (int) $p['product_id'] ?>"
                                                data-price="<?= e($p['product_price']) ?>"
                                                data-lenses="<?= (int) $p['needs_lenses'] ?>"
                                                <?= (int) $p['is_service'] === 0 && (int) $p['product_stock'] < 1 ? 'disabled' : '' ?>
                                                <?= $lineValue($n, 'product_id') === (string) $p['product_id'] ? 'selected' : '' ?>>
                                            <?= e($p['product_title']) ?> - <?= money($p['product_price']) ?>
                                            <?= (int) $p['is_service'] === 1 ? '' : '(' . (int) $p['product_stock'] . ' in stock)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($group !== null): ?></optgroup><?php endif; ?>
                                </select>
                                <?= field_error($errors, 'line' . $n) ?>
                            </td>
                            <td>
                                <label for="qty-<?= $n ?>" class="visually-hidden">Quantity, line <?= $n + 1 ?></label>
                                <input type="number" id="qty-<?= $n ?>" name="qty[]" value="<?= e($lineValue($n, 'qty', '1')) ?>"
                                       min="1" max="<?= OrderController::SALE_MAX_QTY ?>" class="qty-input js-qty-sale">
                            </td>
                            <td>
                                <label for="lens-<?= $n ?>" class="visually-hidden">Lens type, line <?= $n + 1 ?></label>
                                <select id="lens-<?= $n ?>" name="lens_type_id[]" class="js-lens">
                                    <option value="0" data-price="0">No lenses</option>
                                    <?php foreach ($form['lens_types'] as $l): ?>
                                        <option value="<?= (int) $l['lens_id'] ?>" data-price="<?= e($l['lens_price']) ?>"
                                            <?= $lineValue($n, 'lens_type_id') === (string) $l['lens_id'] ? 'selected' : '' ?>>
                                            <?= e($l['lens_name']) ?> (+<?= money($l['lens_price']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <label for="coat-<?= $n ?>" class="visually-hidden">Coating, line <?= $n + 1 ?></label>
                                <select id="coat-<?= $n ?>" name="lens_coating_id[]" class="js-lens">
                                    <option value="0" data-price="0">None</option>
                                    <?php foreach ($form['coatings'] as $l): ?>
                                        <option value="<?= (int) $l['lens_id'] ?>" data-price="<?= e($l['lens_price']) ?>"
                                            <?= $lineValue($n, 'lens_coating_id') === (string) $l['lens_id'] ? 'selected' : '' ?>>
                                            <?= e($l['lens_name']) ?> (+<?= money($l['lens_price']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <label for="rx-<?= $n ?>" class="visually-hidden">Prescription, line <?= $n + 1 ?></label>
                                <select id="rx-<?= $n ?>" name="prescription_id[]" <?= empty($form['prescriptions']) ? 'disabled' : '' ?>>
                                    <option value="0"><?= empty($form['prescriptions']) ? 'None on file' : '-- Choose --' ?></option>
                                    <?php foreach ($form['prescriptions'] as $rx): ?>
                                        <option value="<?= (int) $rx['prescription_id'] ?>"
                                            <?= $lineValue($n, 'prescription_id') === (string) $rx['prescription_id'] ? 'selected' : '' ?>>
                                            <?= e($rxLabel($rx)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="num js-line-total">-</td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="5" class="num">Total (preview)</th>
                        <td class="num"><strong class="js-sale-total">GH₵ 0.00</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="field-hint">Lenses can only be added to frames that take prescription lenses. Glasses with lenses go to the lab; other items are handed over now.</p>
    </section>

    <section class="admin-panel">
        <h2>3. Payment now</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="amount_paid">Amount paid now (GH₵)</label>
                <input type="text" id="amount_paid" name="amount_paid" inputmode="decimal"
                       value="<?= e($old['amount_paid'] ?? '') ?>" placeholder="Full amount, a deposit, or empty">
                <p class="field-hint">Anything not paid now shows as owed on the order and in Debtors.</p>
                <?= field_error($errors, 'amount_paid') ?>
            </div>
            <div class="form-group">
                <label for="method">Paid by</label>
                <select id="method" name="method">
                    <?php foreach (OrderController::PAYMENT_METHODS as $m): ?>
                        <option value="<?= e($m) ?>" <?= ($old['method'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'method') ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="reference">Mobile Money / bank reference</label>
                <input type="text" id="reference" name="reference" maxlength="100" value="<?= e($old['reference'] ?? '') ?>"
                       placeholder="Not needed for cash">
                <?= field_error($errors, 'reference') ?>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <input type="text" id="notes" name="notes" maxlength="255" value="<?= e($old['notes'] ?? '') ?>">
            </div>
        </div>
    </section>

    <div class="admin-form-buttons">
        <button type="submit" class="btn btn-primary">Save Sale</button>
        <a href="<?= url('views/admin/payments.php') ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
