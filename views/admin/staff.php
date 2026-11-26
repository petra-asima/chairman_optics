<?php
// Admin only: every staff account, with buttons to edit, disable and enable.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/StaffController.php';
require_staff(['Admin']);

$controller = new StaffController();
$staffList  = $controller->getAllStaff();
$myId       = (int) $_SESSION['staff_id'];

$pageTitle = 'Staff Accounts';
require __DIR__ . '/../layout/admin_header.php';
?>

<div class="admin-toolbar">
    <p class="admin-intro">
        Each staff member logs in with their own email and password. Accounts are never deleted,
        because orders and records show who did what. Disable an account when someone leaves.
    </p>
    <a href="<?= url('views/admin/staff_form.php') ?>" class="btn btn-primary btn-small">
        <span class="material-symbols-outlined" aria-hidden="true">person_add</span> Add Staff
    </a>
</div>

<div class="admin-panel">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Last login</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $s): ?>
                    <?php
                    $isMe     = (int) $s['staff_id'] === $myId;
                    $isActive = (int) $s['is_active'] === 1;
                    $name     = $s['staff_fname'] . ' ' . $s['staff_lname'];
                    ?>
                    <tr class="<?= $isActive ? '' : 'row-disabled' ?>">
                        <td>
                            <strong><?= e($name) ?></strong>
                            <?php if ($isMe): ?><span class="badge badge-muted">You</span><?php endif; ?>
                        </td>
                        <td><?= e($s['staff_email']) ?></td>
                        <td><?= e($s['staff_contact']) ?></td>
                        <td><span class="role-badge"><?= e($s['role_name']) ?></span></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $s['last_login']
                                ? e(date('d M Y, g:i a', strtotime($s['last_login'])))
                                : '<span class="muted">Never</span>' ?>
                        </td>
                        <td class="admin-actions">
                            <a href="<?= url('views/admin/staff_form.php?id=' . (int) $s['staff_id']) ?>"
                               class="btn btn-outline btn-small">Edit</a>

                            <?php if (!$isMe): /* Admins cannot disable themselves */ ?>
                                <form action="<?= url('actions/toggle_staff_action.php') ?>" method="POST"
                                      <?= $isActive ? 'data-confirm="Disable ' . e($name) . '? They will not be able to log in."' : '' ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="staff_id" value="<?= (int) $s['staff_id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" class="btn btn-danger btn-small">Disable</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline btn-small">Enable</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="admin-count"><?= count($staffList) ?> account(s)</p>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
