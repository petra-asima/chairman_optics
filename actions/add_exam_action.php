<?php
// Handles the eye exam form (views/admin/exam_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ExamController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/exams.php');
}

// Only Optometrists (and the Admin) record eye exams
require_staff(['Optometrist']);

$patientId     = (int) ($_POST['patient_id'] ?? 0);
$appointmentId = (int) ($_POST['appointment_id'] ?? 0);
$formPage      = 'views/admin/exam_form.php?' . ($appointmentId > 0 ? 'appointment_id=' . $appointmentId : 'patient_id=' . $patientId);

// If the files are bigger than the server allows, PHP empties the whole form
if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    set_flash('error', 'The attachments are too large. Each file must be under 5 MB.');
    redirect('views/admin/exams.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($formPage);
}

$data = [
    'patient_id'      => $patientId,
    'appointment_id'  => $appointmentId,
    'chief_complaint' => trim($_POST['chief_complaint'] ?? ''),
    'diagnosis'       => trim($_POST['diagnosis'] ?? ''),
    'notes'           => trim($_POST['notes'] ?? ''),
    'rx_notes'        => trim($_POST['rx_notes'] ?? ''),
    'pd'              => trim($_POST['pd'] ?? ''),
];
foreach (array_keys(ExamController::VA_FIELDS) as $field) {
    $data[$field] = $_POST[$field] ?? '';
}
foreach (['re', 'le'] as $eye) {
    foreach (['sphere', 'cylinder', 'axis', 'add'] as $part) {
        $data[$eye . '_' . $part] = trim($_POST[$eye . '_' . $part] ?? '');
    }
}

$controller = new ExamController();
$files  = ExamController::listUploads($_FILES['attachments'] ?? []);
$result = $controller->saveExam($data, $files, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    // Send the errors back and keep everything typed (files must be chosen again)
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/exam_detail.php?id=' . (int) $result['exam_id']);
