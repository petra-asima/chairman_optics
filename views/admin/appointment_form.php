<?php
// Front-desk booking for walk-in and phone patients.
// Opened from the day view, or from a patient's folder (?folder=CO-2026-0001).
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/AppointmentController.php';
require_staff(['Receptionist', 'Optometrist']);

$controller = new AppointmentController();
$errors     = get_form_errors();
$old        = $_SESSION['old'] ?? [];

$date = $_GET['date'] ?? date('Y-m-d');
if (!AppointmentController::isValidDate($date) || $date < date('Y-m-d')) {
    $date = date('Y-m-d');
}
$folder    = $old['folder_no'] ?? ($_GET['folder'] ?? '');
$freeSlots = $controller->getFreeSlots($date, false);

$pageTitle = 'Book Appointment';
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/appointments.php?date=' . $date) ?>" class="admin-link-small">&larr; Back to appointments</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="admin-form">

    <!-- Choosing the day reloads the page to show that day's free times -->
    <form method="GET" action="" class="admin-panel">
        <h2>1. Day</h2>
        <input type="hidden" name="folder" value="<?= e($folder) ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="pick-date">Date</label>
                <input type="date" id="pick-date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                <noscript><button type="submit" class="btn btn-outline btn-small">Show times</button></noscript>
            </div>
        </div>
    </form>

    <form action="<?= url('actions/add_appointment_action.php') ?>" method="POST" class="admin-panel" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="appointment_date" value="<?= e($date) ?>">

        <h2>2. Patient and time</h2>
        <?= field_error($errors, 'appointment_date') ?>

        <div class="form-row">
            <div class="form-group">
                <label for="folder_no">Folder number <span class="required">*</span></label>
                <input type="text" id="folder_no" name="folder_no" value="<?= e($folder) ?>" placeholder="CO-2026-0001" maxlength="20" required>
                <p class="field-hint">New patient? <a href="<?= url('views/admin/patient_form.php') ?>">Open a folder first</a>.</p>
                <?= field_error($errors, 'folder_no') ?>
            </div>
            <div class="form-group">
                <label for="appointment_time">Time on <?= e(date('D j M', strtotime($date))) ?> <span class="required">*</span></label>
                <?php if (!AppointmentController::isOpenDay($date)): ?>
                    <p class="admin-empty">The clinic is closed on this day.</p>
                <?php elseif (empty($freeSlots)): ?>
                    <p class="admin-empty">No free times left on this day.</p>
                <?php else: ?>
                    <select id="appointment_time" name="appointment_time" required>
                        <?php foreach ($freeSlots as $slot): ?>
                            <option value="<?= e($slot) ?>" <?= ($old['appointment_time'] ?? '') === $slot ? 'selected' : '' ?>>
                                <?= e(date('g:i a', strtotime($slot))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?= field_error($errors, 'appointment_time') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="booked_via">Booked by</label>
                <select id="booked_via" name="booked_via">
                    <?php foreach (AppointmentController::DESK_VIAS as $via): ?>
                        <option value="<?= e($via) ?>" <?= ($old['booked_via'] ?? '') === $via ? 'selected' : '' ?>><?= e($via) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'booked_via') ?>
            </div>
            <div class="form-group">
                <label for="optometrist_id">Optometrist</label>
                <select id="optometrist_id" name="optometrist_id">
                    <option value="0">Not assigned yet</option>
                    <?php foreach ($controller->getOptometrists() as $o): ?>
                        <option value="<?= (int) $o['staff_id'] ?>" <?= (int) ($old['optometrist_id'] ?? 0) === (int) $o['staff_id'] ? 'selected' : '' ?>>
                            <?= e($o['staff_fname'] . ' ' . $o['staff_lname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'optometrist_id') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="reason">Reason for the visit</label>
            <input type="text" id="reason" name="reason" value="<?= e($old['reason'] ?? '') ?>" maxlength="255">
            <?= field_error($errors, 'reason') ?>
        </div>

        <button type="submit" class="btn btn-primary" <?= empty($freeSlots) ? 'disabled' : '' ?>>Book Appointment</button>
    </form>
</div>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
