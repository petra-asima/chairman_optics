<?php
// Appointments day view for the front desk and optometrists.
// One card per appointment, each with its own small Save form
// (status, optometrist, patient folder, notes).
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/AppointmentController.php';
require_staff(['Receptionist', 'Optometrist']);

$controller = new AppointmentController();

$date = $_GET['date'] ?? date('Y-m-d');
if (!AppointmentController::isValidDate($date)) {
    $date = date('Y-m-d');
}
$view         = $controller->getDayView($date);
$optometrists = $controller->getOptometrists();

$prevDay = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDay = date('Y-m-d', strtotime($date . ' +1 day'));
$dayUrl  = fn(string $d): string => url('views/admin/appointments.php?date=' . $d);

$pageTitle = 'Appointments';
require __DIR__ . '/../layout/admin_header.php';
?>

<div class="admin-toolbar">
    <div class="day-nav">
        <a href="<?= $dayUrl($prevDay) ?>" class="btn btn-outline btn-small" aria-label="Previous day">
            <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </a>
        <form method="GET" action="" class="day-pick">
            <label for="date" class="visually-hidden">Choose a day</label>
            <input type="date" id="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
        </form>
        <a href="<?= $dayUrl($nextDay) ?>" class="btn btn-outline btn-small" aria-label="Next day">
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </a>
        <?php if ($date !== date('Y-m-d')): ?>
            <a href="<?= $dayUrl(date('Y-m-d')) ?>" class="admin-link-small">Today</a>
        <?php endif; ?>
    </div>

    <a href="<?= url('views/admin/appointment_form.php?date=' . $date) ?>" class="btn btn-primary btn-small">
        <span class="material-symbols-outlined" aria-hidden="true">add</span> Book Appointment
    </a>
</div>

<!-- Seven days around the chosen day, with how many are booked on each -->
<nav class="week-strip" aria-label="This week">
    <?php for ($i = 0; $i < 7; $i++): ?>
        <?php
        $d       = date('Y-m-d', strtotime($view['from'] . " +$i days"));
        $count   = $view['week'][$d] ?? 0;
        $closed  = !AppointmentController::isOpenDay($d);
        ?>
        <a href="<?= $dayUrl($d) ?>" class="week-day <?= $d === $date ? 'active' : '' ?> <?= $closed ? 'closed' : '' ?>"
           <?= $d === $date ? 'aria-current="date"' : '' ?>>
            <span class="week-name"><?= e(date('D', strtotime($d))) ?></span>
            <span class="week-num"><?= e(date('j', strtotime($d))) ?></span>
            <span class="week-count"><?= $closed ? 'Closed' : $count . ' booked' ?></span>
        </a>
    <?php endfor; ?>
</nav>

<h2 class="dashboard-heading"><?= e(date('l j F Y', strtotime($date))) ?></h2>

<?php if (!AppointmentController::isOpenDay($date)): ?>
    <p class="admin-empty">The clinic is closed on this day.</p>
<?php endif; ?>

<?php if (empty($view['appointments'])): ?>
    <div class="admin-panel"><p class="admin-empty">No appointments on this day.</p></div>
<?php else: ?>
    <ul class="appt-list">
        <?php foreach ($view['appointments'] as $a): ?>
            <?php
            $next     = AppointmentController::nextStatuses($a['appointment_status']);
            $isFinal  = empty($next);
            $formId   = 'appt-' . (int) $a['appointment_id'];
            ?>
            <li class="admin-panel appt-card <?= $isFinal ? 'appt-final' : '' ?>">
                <div class="appt-time">
                    <strong><?= e(date('g:i a', strtotime($a['appointment_time']))) ?></strong>
                    <span class="badge <?= appointment_status_badge($a['appointment_status']) ?>"><?= e($a['appointment_status']) ?></span>
                </div>

                <div class="appt-who">
                    <p class="appt-name">
                        <?php if ($a['patient_id']): ?>
                            <a href="<?= url('views/admin/patient_detail.php?id=' . (int) $a['patient_id']) ?>"><?= e($a['person_name']) ?></a>
                            <span class="muted"><?= e($a['folder_no']) ?></span>
                        <?php else: ?>
                            <?= e($a['person_name'] ?? 'Unknown') ?>
                            <span class="badge badge-warning">No folder yet</span>
                        <?php endif; ?>
                    </p>
                    <p class="appt-meta">
                        <?php if ($a['person_contact']): ?>
                            <a href="tel:<?= e($a['person_contact']) ?>"><?= e($a['person_contact']) ?></a> &middot;
                        <?php endif; ?>
                        <?= e($a['booked_via']) ?>
                        <?= $a['reason'] ? ' &middot; ' . e($a['reason']) : '' ?>
                    </p>
                    <?php if ($a['notes']): ?>
                        <p class="appt-meta"><em><?= e($a['notes']) ?></em></p>
                    <?php endif; ?>
                </div>

                <?php if ($isFinal): ?>
                    <p class="appt-meta">
                        <?= $a['optometrist_name'] ? 'Seen by ' . e($a['optometrist_name']) : '' ?>
                    </p>
                <?php else: ?>
                    <form action="<?= url('actions/update_appointment_action.php') ?>" method="POST" class="appt-form" id="<?= $formId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int) $a['appointment_id'] ?>">

                        <label>
                            <span class="appt-label">Status</span>
                            <select name="appointment_status">
                                <option value="<?= e($a['appointment_status']) ?>"><?= e($a['appointment_status']) ?></option>
                                <?php foreach ($next as $status): ?>
                                    <option value="<?= e($status) ?>"><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span class="appt-label">Optometrist</span>
                            <select name="optometrist_id">
                                <option value="0">Not assigned</option>
                                <?php foreach ($optometrists as $o): ?>
                                    <option value="<?= (int) $o['staff_id'] ?>" <?= (int) $a['optometrist_id'] === (int) $o['staff_id'] ? 'selected' : '' ?>>
                                        <?= e($o['staff_fname'] . ' ' . $o['staff_lname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span class="appt-label">Folder no.</span>
                            <input type="text" name="folder_no" value="<?= e($a['folder_no'] ?? '') ?>" placeholder="e.g. CO-<?= date('Y') ?>-…" maxlength="20">
                        </label>

                        <label class="appt-notes">
                            <span class="appt-label">Notes</span>
                            <input type="text" name="notes" value="<?= e($a['notes'] ?? '') ?>" maxlength="255">
                        </label>

                        <button type="submit" class="btn btn-primary btn-small">Save</button>

                        <?php if ($a['patient_id'] && staff_can(['Optometrist'])): ?>
                            <a href="<?= url('views/admin/exam_form.php?appointment_id=' . (int) $a['appointment_id']) ?>" class="btn btn-outline btn-small">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span> Start Exam
                            </a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
