<?php
// One patient's folder: their details and a timeline of every visit,
// exam, prescription and order, newest first.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/PatientController.php';
require_staff(['Receptionist', 'Optometrist']);

$controller = new PatientController();
$patient    = $controller->getPatient((int) ($_GET['id'] ?? 0));

if (!$patient) {
    set_flash('error', 'That patient was not found.');
    redirect('views/admin/patients.php');
}

$timeline = $controller->getTimeline($patient);
$age      = PatientController::age($patient['date_of_birth']);
$name     = $patient['patient_fname'] . ' ' . $patient['patient_lname'];

// Only link to orders for roles that can open the Orders page
$canOpenOrders = staff_can(['Receptionist', 'Lab Technician']);
// Clinical details are for Optometrists (and the Admin) only
$canOpenExams  = staff_can(['Optometrist']);

// An icon for each kind of event in the timeline
$eventIcons = [
    'Appointment'  => 'calendar_month',
    'Eye exam'     => 'visibility',
    'Prescription' => 'description',
    'Order'        => 'receipt_long',
];

$pageTitle = $patient['folder_no'];
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/patients.php') ?>" class="admin-link-small">&larr; Back to patients</a></p>

<div class="order-layout">

    <!-- ---------- Visit timeline ---------- -->
    <section class="admin-panel">
        <h2>Visit timeline</h2>

        <?php if (empty($timeline)): ?>
            <p class="admin-empty">Nothing recorded yet. Appointments, eye exams, prescriptions and orders will appear here.</p>
        <?php else: ?>
            <ol class="timeline patient-timeline">
                <?php foreach ($timeline as $event): ?>
                    <li>
                        <p class="timeline-head">
                            <span class="material-symbols-outlined" aria-hidden="true"><?= e($eventIcons[$event['event_type']] ?? 'event') ?></span>
                            <strong><?= e($event['event_type']) ?></strong>
                            <span class="timeline-when"><?= e(date('d M Y, g:i a', strtotime($event['event_date']))) ?></span>
                        </p>
                        <p class="timeline-title">
                            <?php if ($event['event_type'] === 'Order' && $canOpenOrders): ?>
                                <a href="<?= url('views/admin/order_detail.php?id=' . (int) $event['record_id']) ?>"><?= e($event['title']) ?></a>
                            <?php elseif ($event['event_type'] === 'Eye exam' && $canOpenExams): ?>
                                <a href="<?= url('views/admin/exam_detail.php?id=' . (int) $event['record_id']) ?>"><?= e($event['title']) ?></a>
                            <?php else: ?>
                                <?= e($event['title']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($event['detail'] !== '' || $event['amount'] !== null): ?>
                            <p class="timeline-note">
                                <?= $event['amount'] !== null ? money($event['amount']) . ' &middot; ' : '' ?><?= e($event['detail']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($event['staff_name']): ?>
                            <p class="timeline-who"><?= e($event['staff_name']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <!-- ---------- Patient details ---------- -->
    <aside>
        <section class="admin-panel">
            <p class="folder-badge"><?= e($patient['folder_no']) ?></p>
            <h2 class="patient-name"><?= e($name) ?></h2>

            <dl class="order-facts">
                <dt>Gender</dt>
                <dd><?= e($patient['patient_gender']) ?></dd>
                <dt>Age</dt>
                <dd>
                    <?php if ($age !== null): ?>
                        <?= $age ?> (born <?= e(date('d M Y', strtotime($patient['date_of_birth']))) ?>)
                    <?php else: ?>
                        <span class="muted">Not recorded</span>
                    <?php endif; ?>
                </dd>
                <dt>Phone</dt>
                <dd><a href="tel:<?= e($patient['patient_contact']) ?>"><?= e($patient['patient_contact']) ?></a></dd>
                <?php if ($patient['patient_email']): ?>
                    <dt>Email</dt>
                    <dd><?= e($patient['patient_email']) ?></dd>
                <?php endif; ?>
                <?php if ($patient['patient_address']): ?>
                    <dt>Address</dt>
                    <dd><?= e($patient['patient_address']) ?></dd>
                <?php endif; ?>
                <?php if ($patient['occupation']): ?>
                    <dt>Occupation</dt>
                    <dd><?= e($patient['occupation']) ?></dd>
                <?php endif; ?>
                <dt>Online</dt>
                <dd>
                    <?php if ($patient['customer_id']): ?>
                        <span class="badge badge-info">Linked</span> <?= e($patient['account_email']) ?>
                    <?php else: ?>
                        <span class="muted">No online account</span>
                    <?php endif; ?>
                </dd>
                <dt>Registered</dt>
                <dd>
                    <?= e(date('d M Y', strtotime($patient['date_registered']))) ?>
                    <?php if ($patient['staff_fname']): ?>
                        <br><small class="muted">by <?= e($patient['staff_fname'] . ' ' . $patient['staff_lname']) ?></small>
                    <?php endif; ?>
                </dd>
            </dl>

            <p class="patient-actions">
                <a href="<?= url('views/admin/patient_form.php?id=' . (int) $patient['patient_id']) ?>" class="btn btn-outline btn-small">
                    <span class="material-symbols-outlined" aria-hidden="true">edit</span> Edit Details
                </a>
                <a href="<?= url('views/admin/appointment_form.php?folder=' . rawurlencode($patient['folder_no'])) ?>" class="btn btn-primary btn-small">
                    <span class="material-symbols-outlined" aria-hidden="true">calendar_add_on</span> Book Appointment
                </a>
                <?php if (staff_can(['Optometrist'])): ?>
                    <a href="<?= url('views/admin/exam_form.php?patient_id=' . (int) $patient['patient_id']) ?>" class="btn btn-primary btn-small">
                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span> Start Eye Exam
                    </a>
                <?php endif; ?>
            </p>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
