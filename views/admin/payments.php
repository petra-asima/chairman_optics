<?php
// Payments: money received in a date range (today by default), with a
// total for each payment method. Cash staff count against the Cash total.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/AccountingController.php';
require_staff(['Receptionist', 'Accountant']);

$controller = new AccountingController();
$view       = $controller->getPaymentsView($_GET);
$oneDay     = $view['from'] === $view['to'];

$pageTitle = 'Payments and Invoices';
require __DIR__ . '/../layout/admin_header.php';
?>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-filters">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= e($view['from']) ?>">
        <label for="to">to</label>
        <input type="date" id="to" name="to" value="<?= e($view['to']) ?>">
        <label for="method" class="visually-hidden">Payment method</label>
        <select id="method" name="method">
            <option value="">All methods</option>
            <?php foreach (OrderController::PAYMENT_METHODS as $m): ?>
                <option value="<?= e($m) ?>" <?= $view['method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-small">Show</button>
        <?php if (!$oneDay || $view['from'] !== date('Y-m-d') || $view['method'] !== ''): ?>
            <a href="<?= url('views/admin/payments.php') ?>" class="admin-link-small">Today</a>
        <?php endif; ?>
    </form>

    <?php if (staff_can(['Receptionist'])): ?>
        <a href="<?= url('views/admin/sale_form.php') ?>" class="btn btn-primary btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span> New In-store Sale
        </a>
    <?php endif; ?>
</div>

<!-- Totals for the period, one tile per payment method -->
<div class="stat-tiles">
    <div class="stat-tile">
        <p class="stat-label"><span class="material-symbols-outlined" aria-hidden="true">payments</span> Total received</p>
        <p class="stat-value"><?= money($view['grand']) ?></p>
        <p class="stat-note">
            <?= $oneDay ? e(date('l j F Y', strtotime($view['from']))) : e(date('j M', strtotime($view['from'])) . ' to ' . date('j M Y', strtotime($view['to']))) ?>
        </p>
    </div>
    <?php foreach (OrderController::PAYMENT_METHODS as $m): ?>
        <div class="stat-tile">
            <p class="stat-label"><?= e($m) ?></p>
            <p class="stat-value stat-value-small"><?= money($view['totals'][$m] ?? 0) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="admin-panel">
    <?php if (empty($view['payments'])): ?>
        <p class="admin-empty">No payments received in this period.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Method</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Received by</th>
                        <th scope="col" class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($view['payments'] as $p): ?>
                        <tr>
                            <td><?= e(date($oneDay ? 'g:i a' : 'd M, g:i a', strtotime($p['payment_date']))) ?></td>
                            <td>
                                <?php if (staff_can(['Receptionist', 'Lab Technician'])): ?>
                                    <a href="<?= url('views/admin/order_detail.php?id=' . (int) $p['order_id']) ?>"><?= e($p['invoice_no']) ?></a>
                                <?php else: ?>
                                    <?= e($p['invoice_no']) ?>
                                <?php endif; ?>
                                <br><small class="muted"><?= e($p['order_source']) ?></small>
                            </td>
                            <td><?= e($p['buyer_name']) ?></td>
                            <td><?= e($p['payment_method']) ?></td>
                            <td><small><?= e($p['pay_reference'] ?? '') ?></small></td>
                            <td><?= $p['received_by_name'] ? e($p['received_by_name']) : '<span class="muted">Online (Paystack)</span>' ?></td>
                            <td class="num"><?= money($p['amt']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-count"><?= count($view['payments']) ?> payment(s)</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
