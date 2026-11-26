<?php
// Back-office home page. Every staff role can open it.
// It shows today's figures for the sections this role works in,
// then a card for each section the role can open.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/DashboardController.php';
require_staff();

$controller = new DashboardController();
$figures    = $controller->getDashboard();

$pageTitle = 'Dashboard';
$menu      = require __DIR__ . '/../layout/admin_menu.php';

// Link a figure to its section only once that section's page is built.
// A section counts as built when its menu item is ready. $href lets a
// tile go to another page in that section (e.g. stock.php in Products).
$readyPages = [];
foreach ($menu as $item) {
    if ($item['ready']) {
        $readyPages[] = $item['page'];
    }
}
$linkIfReady = function (string $menuPage, string $text, string $href = '') use ($readyPages): string {
    if (!in_array($menuPage, $readyPages, true)) {
        return '';
    }
    $href = $href !== '' ? $href : $menuPage;
    return '<a href="' . url($href) . '" class="stat-link">' . e($text) . ' &rarr;</a>';
};

require __DIR__ . '/../layout/admin_header.php';
?>

<section class="admin-welcome">
    <h2>Welcome, <?= e($_SESSION['staff_name']) ?></h2>
    <p>
        You are logged in as <strong><?= e(staff_role()) ?></strong>.
        Today is <?= e(date('l, j F Y')) ?>.
    </p>
</section>

<!-- ---------- Today's figures (only the ones this role uses) ---------- -->
<div class="stat-tiles">

    <?php if ($figures['sales'] !== null): ?>
        <div class="stat-tile">
            <p class="stat-label">
                <span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span> Sales today
            </p>
            <p class="stat-value"><?= money($figures['sales']['sales_total']) ?></p>
            <p class="stat-note">
                <?= $figures['sales']['order_count'] ?> order(s) &middot;
                <?= money($figures['sales']['received']) ?> received
            </p>
            <?= $linkIfReady('views/admin/payments.php', 'Payments') ?>
        </div>
    <?php endif; ?>

    <?php if ($figures['patients'] !== null): ?>
        <div class="stat-tile">
            <p class="stat-label">
                <span class="material-symbols-outlined" aria-hidden="true">groups</span> Patients today
            </p>
            <p class="stat-value"><?= $figures['patients']['total'] ?></p>
            <p class="stat-note">
                <?= $figures['patients']['completed'] ?> seen &middot;
                <?= $figures['patients']['new_patients'] ?> new folder(s)
            </p>
            <?= $linkIfReady('views/admin/appointments.php', 'Appointments') ?>
        </div>
    <?php endif; ?>

    <?php if ($figures['glasses'] !== null): ?>
        <div class="stat-tile">
            <p class="stat-label">
                <span class="material-symbols-outlined" aria-hidden="true">eyeglasses</span> Glasses in the lab
            </p>
            <p class="stat-value"><?= $figures['glasses']['in_lab'] ?></p>
            <p class="stat-note">
                Pending, cutting or fitting &middot; <?= $figures['glasses']['ready'] ?> ready for collection
            </p>
            <?= $linkIfReady('views/admin/orders.php', 'Orders and Lab') ?>
        </div>
    <?php endif; ?>

    <?php if ($figures['low_stock'] !== null): ?>
        <?php $lowCount = count($figures['low_stock']); ?>
        <div class="stat-tile">
            <p class="stat-label">
                <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span> Low stock
            </p>
            <p class="stat-value"><?= $lowCount ?></p>
            <?php if ($lowCount > 0): ?>
                <!-- A status is shown with an icon and words, never colour alone -->
                <p class="stat-note stat-warning">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    Product(s) need restocking
                </p>
            <?php else: ?>
                <p class="stat-note stat-good">
                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                    Everything is above its reorder level
                </p>
            <?php endif; ?>
            <?= $linkIfReady('views/admin/product.php', 'Stock', 'views/admin/stock.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($figures['best_sellers'] !== null || $figures['low_stock'] !== null): ?>
    <div class="dashboard-panels">

        <?php if ($figures['best_sellers'] !== null): ?>
            <!-- ---------- Best sellers: ranked bars, largest first ---------- -->
            <section class="admin-panel">
                <h2>Best sellers <small class="muted">last <?= DashboardController::BEST_SELLER_DAYS ?> days</small></h2>

                <?php if (empty($figures['best_sellers'])): ?>
                    <p class="admin-empty">No products sold in the last <?= DashboardController::BEST_SELLER_DAYS ?> days yet.</p>
                <?php else: ?>
                    <?php
                    // Each bar's length is its quantity compared with the top seller
                    $topQty = (int) $figures['best_sellers'][0]['qty_sold'];
                    ?>
                    <ol class="rank-list">
                        <?php foreach ($figures['best_sellers'] as $item): ?>
                            <?php
                            $qty   = (int) $item['qty_sold'];
                            $width = $topQty > 0 ? round($qty / $topQty * 100) : 0;
                            $tip   = $qty . ' sold, ' . money($item['revenue']);
                            ?>
                            <li class="rank-row" title="<?= e($item['product_title'] . ': ' . $tip) ?>">
                                <span class="rank-name"><?= e($item['product_title']) ?></span>
                                <span class="rank-track" aria-hidden="true">
                                    <span class="rank-bar" style="width: <?= $width ?>%"></span>
                                </span>
                                <span class="rank-figure"><?= $qty ?> sold</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($figures['low_stock'] !== null): ?>
            <!-- ---------- The lowest five products ---------- -->
            <section class="admin-panel">
                <h2>Restock soon</h2>

                <?php if (empty($figures['low_stock'])): ?>
                    <p class="admin-empty">No products are low on stock.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col" class="num">In stock</th>
                                <th scope="col" class="num">Reorder at</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($figures['low_stock'], 0, 5) as $item): ?>
                                <tr>
                                    <td><?= e($item['product_title']) ?></td>
                                    <td class="num">
                                        <?php if ((int) $item['product_stock'] === 0): ?>
                                            <span class="badge badge-danger">Out of stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><?= (int) $item['product_stock'] ?> left</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?= (int) $item['reorder_level'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($figures['low_stock']) > 5): ?>
                        <p class="admin-count">
                            and <?= count($figures['low_stock']) - 5 ?> more.
                            <a href="<?= url('views/admin/stock.php') ?>">See all</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ---------- One card for each section this role can use ---------- -->
<h2 class="dashboard-heading">Your sections</h2>
<div class="admin-cards">
    <?php foreach ($menu as $item): ?>
        <?php if ($item['page'] === 'views/admin/dashboard.php' || !staff_can($item['roles'])) continue; ?>
        <div class="admin-card <?= $item['ready'] ? '' : 'admin-card-soon' ?>">
            <span class="material-symbols-outlined" aria-hidden="true"><?= e($item['icon']) ?></span>
            <h3><?= e($item['label']) ?></h3>
            <?php if ($item['ready']): ?>
                <a href="<?= url($item['page']) ?>" class="btn btn-primary btn-small">Open</a>
            <?php else: ?>
                <p class="admin-card-note">Coming soon</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
