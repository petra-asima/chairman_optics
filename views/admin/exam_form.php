<?php
// The eye exam form.
//   exam_form.php?appointment_id=N  → exam for a booked appointment (it becomes Completed)
//   exam_form.php?patient_id=N      → exam started from a patient folder
// RE = right eye, LE = left eye (the abbreviations optometrists use).
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ExamController.php';
require_once __DIR__ . '/../../controllers/PatientController.php';
require_staff(['Optometrist']);

$controller = new ExamController();
$context    = $controller->getStartContext($_GET);

if (isset($context['error'])) {
    set_flash('error', $context['error']);
    redirect('views/admin/exams.php');
}

$patient     = $context['patient'];
$appointment = $context['appointment'];
$errors      = get_form_errors();
$old         = $_SESSION['old'] ?? [];
$val         = fn(string $key): string => e($old[$key] ?? '');
$age         = PatientController::age($patient['date_of_birth']);

$pageTitle = 'Eye Exam: ' . $patient['folder_no'];
require __DIR__ . '/../layout/admin_header.php';
?>

<p><a href="<?= url('views/admin/patient_detail.php?id=' . (int) $patient['patient_id']) ?>" class="admin-link-small">&larr; Back to the folder</a></p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php elseif (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">Please check the fields marked below. Attachments must be chosen again.</div>
<?php endif; ?>

<form action="<?= url('actions/add_exam_action.php') ?>" method="POST" enctype="multipart/form-data"
      class="exam-form" novalidate data-confirm="Save this exam? An eye exam cannot be changed after it is saved.">
    <?= csrf_field() ?>
    <input type="hidden" name="patient_id" value="<?= (int) $patient['patient_id'] ?>">
    <input type="hidden" name="appointment_id" value="<?= $appointment ? (int) $appointment['appointment_id'] : 0 ?>">

    <!-- ---------- Who ---------- -->
    <section class="admin-panel exam-patient">
        <p class="folder-badge"><?= e($patient['folder_no']) ?></p>
        <h2 class="patient-name"><?= e($patient['patient_fname'] . ' ' . $patient['patient_lname']) ?></h2>
        <p class="muted">
            <?= e($patient['patient_gender']) ?><?= $age !== null ? ', ' . $age . ' years' : '' ?>
            <?= $patient['occupation'] ? ' &middot; ' . e($patient['occupation']) : '' ?>
            <?php if ($appointment): ?>
                &middot; Appointment <?= e(date('D j M, g:i a', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))) ?>
            <?php endif; ?>
        </p>
    </section>

    <!-- ---------- History ---------- -->
    <fieldset class="admin-panel">
        <legend>1. Chief complaint</legend>
        <div class="form-group">
            <label for="chief_complaint" class="visually-hidden">Chief complaint</label>
            <input type="text" id="chief_complaint" name="chief_complaint" maxlength="255"
                   value="<?= $val('chief_complaint') ?: e($appointment['reason'] ?? '') ?>"
                   placeholder="e.g. blurred distance vision for 3 months">
            <?= field_error($errors, 'chief_complaint') ?>
        </div>
    </fieldset>

    <!-- ---------- Visual acuity ---------- -->
    <fieldset class="admin-panel">
        <legend>2. Visual acuity</legend>
        <div class="table-wrap">
            <table class="exam-grid">
                <thead>
                    <tr><th scope="col"></th><th scope="col">Right eye (RE)</th><th scope="col">Left eye (LE)</th></tr>
                </thead>
                <tbody>
                    <?php foreach (['unaided' => 'Unaided', 'aided' => 'Aided (with glasses)', 'pinhole' => 'Pinhole'] as $kind => $label): ?>
                        <tr>
                            <th scope="row"><?= e($label) ?></th>
                            <?php foreach (['re', 'le'] as $eye): ?>
                                <?php $field = 'va_' . $kind . '_' . $eye; ?>
                                <td>
                                    <label for="<?= $field ?>" class="visually-hidden"><?= e(ExamController::VA_FIELDS[$field]) ?></label>
                                    <select id="<?= $field ?>" name="<?= $field ?>">
                                        <option value="">-</option>
                                        <?php foreach (ExamController::VA_VALUES as $va): ?>
                                            <option value="<?= e($va) ?>" <?= ($old[$field] ?? '') === $va ? 'selected' : '' ?>><?= e($va) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($errors, $field) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint">CF = counts fingers, HM = hand movements, PL = perceives light, NPL = no perception of light.</p>
    </fieldset>

    <!-- ---------- Refraction ---------- -->
    <fieldset class="admin-panel">
        <legend>3. Refraction (becomes the prescription)</legend>
        <div class="table-wrap">
            <table class="exam-grid">
                <thead>
                    <tr>
                        <th scope="col"></th>
                        <th scope="col">Sphere (SPH)</th>
                        <th scope="col">Cylinder (CYL)</th>
                        <th scope="col">Axis</th>
                        <th scope="col">Add</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (['re' => 'Right eye (RE)', 'le' => 'Left eye (LE)'] as $eye => $label): ?>
                        <tr>
                            <th scope="row"><?= e($label) ?></th>
                            <?php // No example values in the boxes: on a clinical form they could be mistaken for real readings ?>
                            <?php foreach (['sphere' => ['-30', '30', '0.25'], 'cylinder' => ['-10', '10', '0.25'],
                                            'axis' => ['1', '180', '1'], 'add' => ['0.25', '4', '0.25']] as $part => [$min, $max, $step]): ?>
                                <?php $field = $eye . '_' . $part; ?>
                                <td>
                                    <label for="<?= $field ?>" class="visually-hidden"><?= e($label . ' ' . $part) ?></label>
                                    <input type="number" id="<?= $field ?>" name="<?= $field ?>" value="<?= $val($field) ?>"
                                           min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>"
                                           class="<?= isset($errors[$field]) ? 'is-invalid' : '' ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php foreach (['re_sphere', 're_cylinder', 're_axis', 're_add', 'le_sphere', 'le_cylinder', 'le_axis', 'le_add'] as $field): ?>
            <?= field_error($errors, $field) ?>
        <?php endforeach; ?>

        <div class="form-row exam-pd">
            <div class="form-group">
                <label for="pd">PD (pupillary distance, mm)</label>
                <input type="number" id="pd" name="pd" value="<?= $val('pd') ?>" min="40" max="80" step="0.5">
                <?= field_error($errors, 'pd') ?>
            </div>
            <div class="form-group">
                <label for="rx_notes">Prescription notes</label>
                <input type="text" id="rx_notes" name="rx_notes" value="<?= $val('rx_notes') ?>" maxlength="255"
                       placeholder="e.g. distance glasses, wear full time">
                <?= field_error($errors, 'rx_notes') ?>
            </div>
        </div>
        <p class="field-hint">
            Powers in steps of 0.25, for example SPH -1.25, CYL -0.50, Axis 180, Add +2.00.
            Use 0 for a Plano sphere. Leave the refraction empty if no glasses are needed.
        </p>
    </fieldset>

    <!-- ---------- Findings ---------- -->
    <fieldset class="admin-panel">
        <legend>4. Diagnosis and notes</legend>
        <div class="form-group">
            <label for="diagnosis">Diagnosis</label>
            <textarea id="diagnosis" name="diagnosis" rows="3" maxlength="2000"><?= $val('diagnosis') ?></textarea>
            <?= field_error($errors, 'diagnosis') ?>
        </div>
        <div class="form-group">
            <label for="notes">Notes and advice</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000"><?= $val('notes') ?></textarea>
        </div>
    </fieldset>

    <!-- ---------- Attachments ---------- -->
    <fieldset class="admin-panel">
        <legend>5. Attachments (optional)</legend>
        <div class="form-group">
            <label for="attachments">Scans, referral letters or reports</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept="application/pdf,image/jpeg,image/png">
            <p class="field-hint">Up to <?= ExamController::MAX_FILES ?> files: PDF, JPG or PNG, each under 5 MB. Only optometrists can open them.</p>
            <?= field_error($errors, 'attachments') ?>
        </div>
    </fieldset>

    <div class="admin-form-buttons">
        <button type="submit" class="btn btn-primary">Save Exam</button>
        <a href="<?= url('views/admin/patient_detail.php?id=' . (int) $patient['patient_id']) ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
