<?php
// One order for staff: items, payments, the status timeline, and the
// buttons to move it along (Pending > Cutting > Fitting > Ready > Delivered).
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
require_staff(['Receptionist', 'Lab Technician']);

$controller = new OrderController();
$order      = $controller->getOrderForStaff((int) ($_GET['id'] ?? 0));

if (!$order) {
    set_flash('error', 'That order was not found.');
    redirect('views/admin/orders.php');
}

$allowed   = $controller->allowedStatuses($order, staff_role());
$forward   = array_values(array_diff($allowed, ['Cancelled']));
$canCancel = in_array('Cancelled', $allowed, true);
$balance   = (float) $order['order_total'] - (float) $order['amount_paid'];

$pageTitle = 'Order ' . $order['invoice_no'];
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/orders.php') ?>" class="admin-link-small">&larr; Back to orders</a></p>

<div class="order-layout">

    <div>
        <!-- ---------- Items ---------- -->
        <section class="admin-panel">
            <h2>Items</h2>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col" class="num">Qty</th>
                            <th scope="col" class="num">Price each</th>
                            <th scope="col" class="num">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                            <?php $each = (float) $item['unit_price'] + (float) $item['lens_price']; ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['product_title']) ?></strong>
                                    <?php if ($item['lens_type']): ?>
                                        <br><small>Lenses: <?= e($item['lens_type']) ?><?= $item['lens_coating'] ? ' + ' . e($item['lens_coating']) : '' ?>
                                        (<?= money($item['lens_price']) ?>)</small>
                                    <?php endif; ?>
                                    <?php if ($item['prescription_id']): ?>
                                        <br><small class="muted">Prescription #<?= (int) $item['prescription_id'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= (int) $item['qty'] ?></td>
                                <td class="num"><?= money($each) ?></td>
                                <td class="num"><?= money($each * (int) $item['qty']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3" class="num">Order total</th>
                            <td class="num"><strong><?= money($order['order_total']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($order['notes']): ?>
                <p class="order-notes">
                    <span class="material-symbols-outlined" aria-hidden="true">sticky_note_2</span>
                    <?= e($order['notes']) ?>
                </p>
            <?php endif; ?>
        </section>

        <!-- ---------- Payments ---------- -->
        <section class="admin-panel">
            <h2>Payments</h2>
            <?php if (empty($order['payments'])): ?>
                <p class="admin-empty">No payments recorded.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Method</th>
                                <th scope="col">Reference</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['payments'] as $pay): ?>
                                <tr>
                                    <td><?= e(date('d M Y, g:i a', strtotime($pay['payment_date']))) ?></td>
                                    <td>
                                        <?= e($pay['payment_method']) ?>
                                        <?php if ($pay['staff_fname']): ?><br><small class="muted">Received by <?= e($pay['staff_fname'] . ' ' . $pay['staff_lname']) ?></small><?php endif; ?>
                                    </td>
                                    <td><small><?= e($pay['pay_reference'] ?? '-') ?></small></td>
                                    <td>
                                        <span class="badge <?= $pay['pay_status'] === 'Success' ? 'badge-success' : ($pay['pay_status'] === 'Failed' ? 'badge-danger' : 'badge-warning') ?>">
                                            <?= e($pay['pay_status']) ?>
                                        </span>
                                    </td>
                                    <td class="num"><?= money($pay['amt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- ---------- Status timeline ---------- -->
        <section class="admin-panel">
            <h2>Timeline</h2>
            <ol class="timeline">
                <?php foreach ($order['history'] as $step): ?>
                    <li>
                        <span class="badge <?= order_status_badge($step['status']) ?>"><?= e($step['status']) ?></span>
                        <span class="timeline-when"><?= e(date('d M Y, g:i a', strtotime($step['changed_at']))) ?></span>
                        <span class="timeline-who">
                            <?= $step['staff_fname'] ? 'by ' . e($step['staff_fname'] . ' ' . $step['staff_lname']) : 'by online checkout' ?>
                        </span>
                        <?php if ($step['note']): ?>
                            <p class="timeline-note"><?= e($step['note']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <aside>
        <!-- ---------- Summary ---------- -->
        <section class="admin-panel">
            <p class="order-status-now">
                <span class="badge <?= order_status_badge($order['order_status']) ?>"><?= e($order['order_status']) ?></span>
            </p>
            <dl class="order-facts">
                <dt>Customer</dt>
                <dd>
                    <?= e($order['buyer_name']) ?>
                    <?php if ($order['folder_no']): ?><br><small class="muted">Folder <?= e($order['folder_no']) ?></small><?php endif; ?>
                </dd>
                <?php if ($order['buyer_contact']): ?>
                    <dt>Phone</dt>
                    <dd><a href="tel:<?= e($order['buyer_contact']) ?>"><?= e($order['buyer_contact']) ?></a></dd>
                <?php endif; ?>
                <?php if ($order['buyer_email']): ?>
                    <dt>Email</dt>
                    <dd><?= e($order['buyer_email']) ?></dd>
                <?php endif; ?>
                <dt>Placed</dt>
                <dd><?= e(date('d M Y, g:i a', strtotime($order['order_date']))) ?> (<?= e($order['order_source']) ?>)</dd>
                <dt><?= $order['delivery_method'] === 'Delivery' ? 'Deliver to' : 'Pickup' ?></dt>
                <dd><?= $order['delivery_method'] === 'Delivery' ? e($order['delivery_address']) : 'At the shop' ?></dd>
                <dt>Total</dt>
                <dd><?= money($order['order_total']) ?></dd>
                <dt>Paid</dt>
                <dd><?= money($order['amount_paid']) ?></dd>
                <dt>Balance</dt>
                <dd>
                    <?php if ($balance > 0 && $order['order_status'] !== 'Cancelled'): ?>
                        <strong class="text-danger"><?= money($balance) ?> owed</strong>
                    <?php else: ?>
                        <span class="text-success">Fully paid</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <!-- ---------- Move the order along ---------- -->
        <section class="admin-panel">
            <h2>Update status</h2>

            <?php if (empty($allowed)): ?>
                <p class="admin-empty">
                    <?php if ($order['order_status'] === 'Delivered' || $order['order_status'] === 'Cancelled'): ?>
                        This order is finished (<?= e($order['order_status']) ?>).
                    <?php else: ?>
                        Nothing for the lab to do. The front desk will hand this order over.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <?php if (!empty($forward)): ?>
                    <form action="<?= url('actions/update_order_status_action.php') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">

                        <div class="form-group">
                            <label for="note">Note (optional)</label>
                            <input type="text" id="note" name="note" maxlength="255" placeholder="e.g. lenses cut, frame adjusted">
                        </div>

                        <?php if ($balance > 0 && in_array('Delivered', $forward, true)): ?>
                            <p class="status-warning">
                                <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                                <?= money($balance) ?> is still owed. Collect it before handing over, or it will show as a debt.
                            </p>
                        <?php endif; ?>

                        <p class="label-text">Move to</p>
                        <div class="status-buttons">
                            <?php foreach ($forward as $status): ?>
                                <button type="submit" name="new_status" value="<?= e($status) ?>" class="btn btn-primary btn-small">
                                    <?= e($status) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                    <form action="<?= url('actions/update_order_status_action.php') ?>" method="POST" class="cancel-order"
                          data-confirm="Cancel order <?= e($order['invoice_no']) ?>? The items will go back into stock.<?= (float) $order['amount_paid'] > 0 ? ' The customer paid ' . e(money($order['amount_paid'])) . ', which you must refund by hand.' : '' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                        <input type="hidden" name="new_status" value="Cancelled">
                        <label for="cancel-note" class="visually-hidden">Reason for cancelling</label>
                        <input type="text" id="cancel-note" name="note" maxlength="255" placeholder="Reason for cancelling">
                        <button type="submit" class="btn btn-danger btn-small">Cancel Order</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
