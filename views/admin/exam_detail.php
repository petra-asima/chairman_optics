<?php
// One saved eye exam: visual acuity, the prescription, findings and attachments.
// Exams are medical records, so this page only shows them; they are not edited.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ExamController.php';
require_once __DIR__ . '/../../controllers/PatientController.php';
require_staff(['Optometrist']);

$controller = new ExamController();
$exam       = $controller->getExam((int) ($_GET['id'] ?? 0));

if (!$exam) {
    set_flash('error', 'That exam was not found.');
    redirect('views/admin/exams.php');
}

$rx  = $exam['prescription'];
$age = PatientController::age($exam['date_of_birth']);
$va  = fn(?string $value): string => $value !== null && $value !== '' ? e($value) : '<span class="muted">-</span>';

$pageTitle = 'Eye Exam: ' . $exam['folder_no'];
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/patient_detail.php?id=' . (int) $exam['patient_id']) ?>" class="admin-link-small">&larr; Back to the folder</a></p>

<section class="admin-panel exam-patient">
    <p class="folder-badge"><?= e($exam['folder_no']) ?></p>
    <h2 class="patient-name"><?= e($exam['patient_fname'] . ' ' . $exam['patient_lname']) ?></h2>
    <p class="muted">
        <?= e($exam['patient_gender']) ?><?= $age !== null ? ', ' . $age . ' years' : '' ?>
        &middot; Examined <?= e(date('d M Y, g:i a', strtotime($exam['exam_date']))) ?>
        <?= $exam['optometrist_name'] ? 'by ' . e($exam['optometrist_name']) : '' ?>
    </p>
    <?php if ($exam['chief_complaint']): ?>
        <p><strong>Chief complaint:</strong> <?= e($exam['chief_complaint']) ?></p>
    <?php endif; ?>
</section>

<div class="exam-columns">
    <section class="admin-panel">
        <h2>Visual acuity</h2>
        <table class="exam-grid exam-grid-read">
            <thead><tr><th scope="col"></th><th scope="col">RE</th><th scope="col">LE</th></tr></thead>
            <tbody>
                <tr><th scope="row">Unaided</th><td><?= $va($exam['va_unaided_re']) ?></td><td><?= $va($exam['va_unaided_le']) ?></td></tr>
                <tr><th scope="row">Aided</th><td><?= $va($exam['va_aided_re']) ?></td><td><?= $va($exam['va_aided_le']) ?></td></tr>
                <tr><th scope="row">Pinhole</th><td><?= $va($exam['va_pinhole_re']) ?></td><td><?= $va($exam['va_pinhole_le']) ?></td></tr>
            </tbody>
        </table>
    </section>

    <section class="admin-panel">
        <h2>Prescription</h2>
        <?php if (!$rx): ?>
            <p class="admin-empty">No glasses prescribed at this exam.</p>
        <?php else: ?>
            <table class="exam-grid exam-grid-read">
                <thead><tr><th scope="col"></th><th scope="col">SPH</th><th scope="col">CYL</th><th scope="col">Axis</th><th scope="col">Add</th></tr></thead>
                <tbody>
                    <?php foreach (['re' => 'RE', 'le' => 'LE'] as $eye => $label): ?>
                        <tr>
                            <th scope="row"><?= $label ?></th>
                            <td><?= e(ExamController::formatPower($rx[$eye . '_sphere'], true)) ?></td>
                            <td><?= e(ExamController::formatPower($rx[$eye . '_cylinder'])) ?></td>
                            <td><?= $rx[$eye . '_axis'] !== null ? (int) $rx[$eye . '_axis'] . '&deg;' : '-' ?></td>
                            <td><?= e(ExamController::formatPower($rx[$eye . '_add'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="exam-pd-read">
                <strong>PD:</strong> <?= $rx['pd'] !== null ? e($rx['pd']) . ' mm' : '-' ?>
                &middot; Prescription #<?= (int) $rx['prescription_id'] ?>
            </p>
            <?php if ($rx['notes']): ?><p><?= e($rx['notes']) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<section class="admin-panel">
    <h2>Diagnosis</h2>
    <p><?= $exam['diagnosis'] ? nl2br(e($exam['diagnosis'])) : '<span class="muted">Not recorded</span>' ?></p>
    <?php if ($exam['notes']): ?>
        <h2>Notes and advice</h2>
        <p><?= nl2br(e($exam['notes'])) ?></p>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <h2>Attachments</h2>
    <?php if (empty($exam['attachments'])): ?>
        <p class="admin-empty">No files attached.</p>
    <?php else: ?>
        <ul class="attachment-list">
            <?php foreach ($exam['attachments'] as $file): ?>
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">attach_file</span>
                    <a href="<?= url('actions/download_attachment_action.php?id=' . (int) $file['attachment_id']) ?>" target="_blank" rel="noopener">
                        <?= e($file['file_name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
