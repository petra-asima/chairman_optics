<?php
// One form for opening a new patient folder and for editing one.
//   patient_form.php       → new folder (the folder number is given on saving)
//   patient_form.php?id=7  → edit patient 7
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/PatientController.php';
require_staff(['Receptionist', 'Optometrist']);

$controller = new PatientController();

$patientId = (int) ($_GET['id'] ?? 0);
$patient   = false;
if ($patientId > 0) {
    $patient = $controller->getPatient($patientId);
    if (!$patient) {
        set_flash('error', 'That patient was not found.');
        redirect('views/admin/patients.php');
    }
}
$isEdit = $patient !== false;
$errors = get_form_errors();

// Show what was just typed (after an error), else the saved patient
$values = $_SESSION['old'] ?? ($isEdit ? $patient : []);
$val    = fn(string $key): string => e($values[$key] ?? '');

$pageTitle = $isEdit ? 'Edit ' . $patient['folder_no'] : 'New Patient';
require __DIR__ . '/../layout/admin_header.php';
?>

<p>
    <a href="<?= url($isEdit ? 'views/admin/patient_detail.php?id=' . $patientId : 'views/admin/patients.php') ?>" class="admin-link-small">
        &larr; <?= $isEdit ? 'Back to the folder' : 'Back to patients' ?>
    </a>
</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<form action="<?= url($isEdit ? 'actions/update_patient_action.php' : 'actions/add_patient_action.php') ?>"
      method="POST" class="admin-form" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
    <?php endif; ?>

    <fieldset class="admin-panel">
        <legend><?= $isEdit ? 'Patient details' : 'New patient folder' ?></legend>
        <p class="field-hint">
            Fields marked <span class="required">*</span> are required.
            <?php if (!$isEdit): ?>The folder number is given automatically when you save.<?php endif; ?>
        </p>

        <div class="form-row">
            <div class="form-group">
                <label for="patient_fname">First name <span class="required">*</span></label>
                <input type="text" id="patient_fname" name="patient_fname" value="<?= $val('patient_fname') ?>" maxlength="50" autocomplete="off" required>
                <?= field_error($errors, 'patient_fname') ?>
            </div>
            <div class="form-group">
                <label for="patient_lname">Surname <span class="required">*</span></label>
                <input type="text" id="patient_lname" name="patient_lname" value="<?= $val('patient_lname') ?>" maxlength="50" autocomplete="off" required>
                <?= field_error($errors, 'patient_lname') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="patient_gender">Gender <span class="required">*</span></label>
                <select id="patient_gender" name="patient_gender" required>
                    <option value="">-- Select --</option>
                    <?php foreach (GENDERS as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= ($values['patient_gender'] ?? '') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'patient_gender') ?>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?= $val('date_of_birth') ?>" max="<?= date('Y-m-d') ?>">
                <?= field_error($errors, 'date_of_birth') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="patient_contact">Phone number <span class="required">*</span></label>
                <input type="tel" id="patient_contact" name="patient_contact" value="<?= $val('patient_contact') ?>" maxlength="20" placeholder="024 123 4567" required>
                <?= field_error($errors, 'patient_contact') ?>
            </div>
            <div class="form-group">
                <label for="patient_email">Email</label>
                <input type="email" id="patient_email" name="patient_email" value="<?= $val('patient_email') ?>" maxlength="100">
                <p class="field-hint">If it matches an online account, the folder is linked to it.</p>
                <?= field_error($errors, 'patient_email') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="patient_address">Address</label>
                <input type="text" id="patient_address" name="patient_address" value="<?= $val('patient_address') ?>" maxlength="255" placeholder="Area and landmark">
                <?= field_error($errors, 'patient_address') ?>
            </div>
            <div class="form-group">
                <label for="occupation">Occupation</label>
                <input type="text" id="occupation" name="occupation" value="<?= $val('occupation') ?>" maxlength="100">
                <p class="field-hint">Helps the optometrist, e.g. screen work or driving.</p>
                <?= field_error($errors, 'occupation') ?>
            </div>
        </div>
    </fieldset>

    <div class="admin-form-buttons">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Open Folder' ?></button>
        <a href="<?= url($isEdit ? 'views/admin/patient_detail.php?id=' . $patientId : 'views/admin/patients.php') ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php
clear_old();
require __DIR__ . '/../layout/admin_footer.php';
?>
