<?php
// Orders and Lab: every order, online and in the shop, with tabs by status.
// Receptionists see everything; a Lab Technician starts on "In the lab".
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
require_staff(['Receptionist', 'Lab Technician']);

$controller = new OrderController();
$filters    = $controller->cleanStaffFilters($_GET, staff_role());
$result     = $controller->getStaffOrderPage($filters);
$counts     = $controller->countByStatus();

// The address of this page with some filters changed (empty ones left out)
$listUrl = function (array $changes = []) use ($filters): string {
    $query = array_merge($filters, $changes);
    if (!isset($changes['page'])) {
        $query['page'] = 1;
    }
    // status is always kept, even when empty ("All"), so a Lab Technician's
    // default tab ("In the lab") does not take over on the next page
    $query = array_filter($query, function ($value, $key) {
        if ($key === 'status') {
            return true;
        }
        return $key === 'page' ? $value > 1 : $value !== '';
    }, ARRAY_FILTER_USE_BOTH);
    return url('views/admin/orders.php') . '?' . http_build_query($query);
};

// The tabs: All, In the lab, then one per status
$labCount = ($counts['Pending'] ?? 0) + ($counts['Cutting'] ?? 0) + ($counts['Fitting'] ?? 0);
$tabs = ['' => ['All', array_sum($counts)], 'lab' => ['In the lab', $labCount]];
foreach (OrderController::ORDER_STATUSES as $status) {
    $tabs[$status] = [$status, $counts[$status] ?? 0];
}

$pageTitle = 'Orders and Lab';
require __DIR__ . '/../layout/admin_header.php';
?>

<nav class="admin-tabs" aria-label="Order status">
    <?php foreach ($tabs as $value => [$label, $count]): ?>
        <a href="<?= e($listUrl(['status' => $value])) ?>"
           class="<?= $filters['status'] === $value ? 'active' : '' ?>"
           <?= $filters['status'] === $value ? 'aria-current="page"' : '' ?>>
            <?= e($label) ?> <span class="tab-count"><?= (int) $count ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-filters">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">

        <label for="q" class="visually-hidden">Search orders</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Invoice, name or phone">

        <label for="source" class="visually-hidden">Where the order was placed</label>
        <select id="source" name="source">
            <option value="">Online and in-store</option>
            <?php foreach (OrderController::ORDER_SOURCES as $source): ?>
                <option value="<?= e($source) ?>" <?= $filters['source'] === $source ? 'selected' : '' ?>><?= e($source) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-outline btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">search</span> Search
        </button>
        <?php if ($filters['q'] !== '' || $filters['source'] !== ''): ?>
            <a href="<?= e($listUrl(['q' => '', 'source' => ''])) ?>" class="admin-link-small">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-panel">
    <?php if (empty($result['orders'])): ?>
        <p class="admin-empty">No orders here.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Date</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Items</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="num">Balance</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['orders'] as $o): ?>
                        <?php
                        $balance = (float) $o['order_total'] - (float) $o['amount_paid'];
                        $detail  = url('views/admin/order_detail.php?id=' . (int) $o['order_id']);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $detail ?>"><strong><?= e($o['invoice_no']) ?></strong></a>
                                <br><small class="muted"><?= e($o['order_source']) ?> &middot; <?= e($o['delivery_method']) ?></small>
                            </td>
                            <td><?= e(date('d M Y', strtotime($o['order_date']))) ?><br><small class="muted"><?= e(date('g:i a', strtotime($o['order_date']))) ?></small></td>
                            <td>
                                <?= e($o['buyer_name']) ?>
                                <?php if ($o['buyer_contact']): ?><br><small class="muted"><?= e($o['buyer_contact']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?= (int) $o['item_count'] ?>
                                <?php if ((int) $o['lens_lines'] > 0): ?>
                                    <span class="badge badge-info">Glasses</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= money($o['order_total']) ?></td>
                            <td class="num">
                                <?php if ($balance > 0 && $o['order_status'] !== 'Cancelled'): ?>
                                    <span class="text-danger"><?= money($balance) ?></span>
                                <?php else: ?>
                                    <span class="muted">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= order_status_badge($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
                            <td><a href="<?= $detail ?>" class="btn btn-outline btn-small">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="admin-count"><?= $result['total'] ?> order(s)</p>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Pages">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <?php if ($i === $result['page']): ?>
                        <span class="current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e($listUrl(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
