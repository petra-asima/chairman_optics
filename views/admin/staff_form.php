<?php
// Admin only. One form for adding a staff account and for editing one.
//   staff_form.php       → add a new account (with a first password)
//   staff_form.php?id=3  → edit account 3, plus a "Reset password" box
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/StaffController.php';
require_staff(['Admin']);

$controller = new StaffController();

// Edit mode: load the account, or go back if it does not exist
$staffId = (int) ($_GET['id'] ?? 0);
$member  = false;
if ($staffId > 0) {
    $member = $controller->getStaffById($staffId);
    if (!$member) {
        set_flash('error', 'That staff account was not found.');
        redirect('views/admin/staff.php');
    }
}
$isEdit = $member !== false;
$isMe   = $isEdit && (int) $member['staff_id'] === (int) $_SESSION['staff_id'];

$roles  = $controller->getRoles();
$errors = get_form_errors();

// Show what was just typed (after an error), else the saved account
$values = $_SESSION['old'] ?? ($isEdit ? $member : []);
$val    = fn(string $key): string => e($values[$key] ?? '');

$pageTitle = $isEdit ? 'Edit Staff Account' : 'Add Staff Account';
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/staff.php') ?>" class="admin-link-small">&larr; Back to staff accounts</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="admin-form">

    <!-- ---------- Details ---------- -->
    <form action="<?= url($isEdit ? 'actions/update_staff_action.php' : 'actions/add_staff_action.php') ?>"
          method="POST" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="staff_id" value="<?= (int) $member['staff_id'] ?>">
        <?php endif; ?>

        <fieldset class="admin-panel">
            <legend><?= $isEdit ? 'Staff details' : 'New staff member' ?></legend>
            <p class="field-hint">Fields marked <span class="required">*</span> are required.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="staff_fname">First name <span class="required">*</span></label>
                    <input type="text" id="staff_fname" name="staff_fname" value="<?= $val('staff_fname') ?>"
                           maxlength="50" autocomplete="off" required>
                    <?= field_error($errors, 'staff_fname') ?>
                </div>
                <div class="form-group">
                    <label for="staff_lname">Surname <span class="required">*</span></label>
                    <input type="text" id="staff_lname" name="staff_lname" value="<?= $val('staff_lname') ?>"
                           maxlength="50" autocomplete="off" required>
                    <?= field_error($errors, 'staff_lname') ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="staff_email">Email (used to log in) <span class="required">*</span></label>
                    <input type="email" id="staff_email" name="staff_email" value="<?= $val('staff_email') ?>"
                           maxlength="100" autocomplete="off" required>
                    <?= field_error($errors, 'staff_email') ?>
                </div>
                <div class="form-group">
                    <label for="staff_contact">Phone number <span class="required">*</span></label>
                    <input type="tel" id="staff_contact" name="staff_contact" value="<?= $val('staff_contact') ?>"
                           maxlength="20" placeholder="024 123 4567" required>
                    <?= field_error($errors, 'staff_contact') ?>
                </div>
            </div>

            <div class="form-group">
                <label for="role_id">Role <span class="required">*</span></label>
                <select id="role_id" name="role_id" required>
                    <option value="">-- Select role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['role_id'] ?>"
                            <?= (int) ($values['role_id'] ?? 0) === (int) $role['role_id'] ? 'selected' : '' ?>>
                            <?= e($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isMe): ?>
                    <p class="field-hint">This is your own account. You cannot remove your own Admin role.</p>
                <?php endif; ?>
                <?= field_error($errors, 'role_id') ?>
            </div>

            <?php if (!$isEdit): ?>
                <!-- First password (only when adding) -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_pass">Password <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" id="staff_pass" name="staff_pass" autocomplete="new-password" required>
                            <button type="button" class="toggle-password" data-target="staff_pass" aria-label="Show password">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            </button>
                        </div>
                        <p class="field-hint">At least 8 characters, with a letter and a number.</p>
                        <?= field_error($errors, 'staff_pass') ?>
                    </div>
                    <div class="form-group">
                        <label for="confirm_pass">Confirm password <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" id="confirm_pass" name="confirm_pass" autocomplete="new-password" required>
                            <button type="button" class="toggle-password" data-target="confirm_pass" aria-label="Show password">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </fieldset>

        <div class="admin-form-buttons">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Account' ?></button>
            <a href="<?= url('views/admin/staff.php') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <!-- ---------- Reset password (a separate form) ---------- -->
        <form action="<?= url('actions/reset_staff_pass_action.php') ?>" method="POST" class="admin-form-gap" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="staff_id" value="<?= (int) $member['staff_id'] ?>">

            <fieldset class="admin-panel">
                <legend>Reset password</legend>
                <p class="field-hint">
                    Use this when <?= e($member['staff_fname']) ?> forgets their password.
                    Tell them the new password in person, never by SMS or email.
                </p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_pass">New password <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" id="new_pass" name="new_pass" autocomplete="new-password" required>
                            <button type="button" class="toggle-password" data-target="new_pass" aria-label="Show password">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            </button>
                        </div>
                        <p class="field-hint">At least 8 characters, with a letter and a number.</p>
                        <?= field_error($errors, 'new_pass') ?>
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_pass">Confirm new password <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" id="confirm_new_pass" name="confirm_new_pass" autocomplete="new-password" required>
                            <button type="button" class="toggle-password" data-target="confirm_new_pass" aria-label="Show password">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-outline btn-small">
                    <span class="material-symbols-outlined" aria-hidden="true">lock_reset</span> Set New Password
                </button>
            </fieldset>
        </form>
    <?php endif; ?>
</div>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
