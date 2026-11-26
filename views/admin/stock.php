<?php
// Stock page: low-stock alerts, a form to record stock changes,
// and the history of recent changes.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ProductController.php';
require_staff(['Receptionist']);

$controller = new ProductController();
$lowStock   = $controller->getLowStock();
$products   = $controller->getStockProducts();
$movements  = $controller->getRecentMovements(50);

// Pre-select a product: from the form that just came back with an
// error, or from a "Restock" link (stock.php?product_id=N)
$old        = $_SESSION['old'] ?? [];
$selectedId = (int) ($old['product_id'] ?? ($_GET['product_id'] ?? 0));
$oldReason  = $old['reason'] ?? 'Restock';

$pageTitle = 'Stock';
require __DIR__ . '/../layout/admin_header.php';
require __DIR__ . '/../layout/product_tabs.php';
?>

<div class="admin-split">

    <!-- ---------- Record a stock change ---------- -->
    <section class="admin-panel">
        <h2>Update stock</h2>

        <?php if (empty($products)): ?>
            <p class="admin-empty">Add products first, then record their stock here.</p>
        <?php else: ?>
            <form action="<?= url('actions/update_stock_action.php') ?>" method="POST" id="stock-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="product_id">Product <span class="required">*</span></label>
                    <select id="product_id" name="product_id" required>
                        <option value="">-- Select product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['product_id'] ?>" <?= $selectedId === (int) $p['product_id'] ? 'selected' : '' ?>>
                                <?= e($p['product_title']) ?> (now <?= (int) $p['product_stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reason">Reason <span class="required">*</span></label>
                        <select id="reason" name="reason" required>
                            <?php foreach (ProductController::MANUAL_STOCK_REASONS as $reason): ?>
                                <option value="<?= e($reason) ?>" <?= $oldReason === $reason ? 'selected' : '' ?>>
                                    <?= e($reason) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="qty">Quantity <span class="required">*</span></label>
                        <input type="number" id="qty" name="qty" min="1" step="1"
                               value="<?= e($old['qty'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Only an adjustment can take stock out (shown by js/admin.js) -->
                <div class="form-group js-direction" <?= $oldReason === 'Adjustment' ? '' : 'hidden' ?>>
                    <label for="direction">Add or remove?</label>
                    <select id="direction" name="direction">
                        <option value="in">Add to stock</option>
                        <option value="out" <?= ($old['direction'] ?? '') === 'out' ? 'selected' : '' ?>>
                            Remove from stock (broken, lost, counted less)
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="note">Note</label>
                    <input type="text" id="note" name="note" maxlength="255"
                           value="<?= e($old['note'] ?? '') ?>" placeholder="e.g. supplier name or reason">
                </div>

                <button type="submit" class="btn btn-primary btn-small">Save Stock Change</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- ---------- Low-stock alerts ---------- -->
    <section class="admin-panel">
        <h2>
            <span class="material-symbols-outlined icon-warning" aria-hidden="true">warning</span>
            Low stock (<?= count($lowStock) ?>)
        </h2>

        <?php if (empty($lowStock)): ?>
            <p class="admin-empty">No products are low on stock.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col" class="num">In stock</th>
                            <th scope="col" class="num">Reorder at</th>
                            <th scope="col"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $item): ?>
                            <tr>
                                <td>
                                    <?= e($item['product_title']) ?>
                                    <br><small class="muted"><?= e($item['cat_name']) ?></small>
                                </td>
                                <td class="num">
                                    <span class="badge <?= (int) $item['product_stock'] === 0 ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= (int) $item['product_stock'] ?>
                                    </span>
                                </td>
                                <td class="num"><?= (int) $item['reorder_level'] ?></td>
                                <td>
                                    <a href="<?= url('views/admin/stock.php?product_id=' . (int) $item['product_id']) ?>"
                                       class="btn btn-outline btn-small">Restock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- ---------- Stock history ---------- -->
<section class="admin-panel">
    <h2>Recent stock changes</h2>

    <?php if (empty($movements)): ?>
        <p class="admin-empty">No stock changes recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Product</th>
                        <th scope="col">Reason</th>
                        <th scope="col" class="num">Change</th>
                        <th scope="col">By</th>
                        <th scope="col">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= e(date('d M Y, g:i a', strtotime($m['created_at']))) ?></td>
                            <td><?= e($m['product_title']) ?></td>
                            <td><?= e($m['reason']) ?></td>
                            <td class="num <?= (int) $m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= (int) $m['change_qty'] > 0 ? '+' : '' ?><?= (int) $m['change_qty'] ?>
                            </td>
                            <td>
                                <?= $m['staff_fname'] ? e($m['staff_fname'] . ' ' . $m['staff_lname']) : '<span class="muted">Online sale</span>' ?>
                            </td>
                            <td><?= e($m['note'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-count">Showing the latest <?= count($movements) ?> change(s).</p>
    <?php endif; ?>
</section>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
