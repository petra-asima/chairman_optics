<?php
// Record a payment on an existing order: the balance on a deposit,
// or money owed by a debtor. Opened from an order page or from Debtors.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/AccountingController.php';
require_staff(['Receptionist', 'Accountant']);

$controller = new AccountingController();
$order      = $controller->getOrder((int) ($_GET['order_id'] ?? 0));

if (!$order) {
    set_flash('error', 'That order was not found.');
    redirect('views/admin/payments.php');
}

$balance = round((float) $order['order_total'] - (float) $order['amount_paid'], 2);
$errors  = get_form_errors();
$old     = $_SESSION['old'] ?? [];

$pageTitle = 'Record Payment';
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/payments.php') ?>" class="admin-link-small">&larr; Back to payments</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="admin-split">
    <section class="admin-panel">
        <h2><?= e($order['invoice_no']) ?></h2>
        <dl class="order-facts">
            <dt>Customer</dt>
            <dd><?= e($order['buyer_name']) ?><?= $order['buyer_contact'] ? '<br><small class="muted">' . e($order['buyer_contact']) . '</small>' : '' ?></dd>
            <dt>Date</dt>
            <dd><?= e(date('d M Y', strtotime($order['order_date']))) ?> (<?= e($order['order_source']) ?>)</dd>
            <dt>Status</dt>
            <dd><span class="badge <?= order_status_badge($order['order_status']) ?>"><?= e($order['order_status']) ?></span></dd>
            <dt>Total</dt>
            <dd><?= money($order['order_total']) ?></dd>
            <dt>Paid so far</dt>
            <dd><?= money($order['amount_paid']) ?></dd>
            <dt>Still owed</dt>
            <dd><strong class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= money($balance) ?></strong></dd>
        </dl>
    </section>

    <section class="admin-panel">
        <h2>New payment</h2>
        <?php if ($order['order_status'] === 'Cancelled'): ?>
            <p class="admin-empty">This order was cancelled, so no payment can be recorded.</p>
        <?php elseif ($balance <= 0): ?>
            <p class="admin-empty">This order is fully paid.</p>
        <?php else: ?>
            <form action="<?= url('actions/add_debtor_payment_action.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">

                <div class="form-group">
                    <label for="amount">Amount received (GH₵) <span class="required">*</span></label>
                    <input type="text" id="amount" name="amount" inputmode="decimal"
                           value="<?= e($old['amount'] ?? number_format($balance, 2, '.', '')) ?>" required>
                    <?= field_error($errors, 'amount') ?>
                </div>
                <div class="form-group">
                    <label for="method">Paid by <span class="required">*</span></label>
                    <select id="method" name="method">
                        <?php foreach (OrderController::PAYMENT_METHODS as $m): ?>
                            <option value="<?= e($m) ?>" <?= ($old['method'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'method') ?>
                </div>
                <div class="form-group">
                    <label for="reference">Mobile Money / bank reference</label>
                    <input type="text" id="reference" name="reference" maxlength="100" value="<?= e($old['reference'] ?? '') ?>" placeholder="Not needed for cash">
                    <?= field_error($errors, 'reference') ?>
                </div>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
