<?php
// Handles the "New patient" form (views/admin/patient_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/PatientController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/patient_form.php');
}

// Receptionists and Optometrists (and the Admin) work with patients
require_staff(['Receptionist', 'Optometrist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/patient_form.php');
}

$data = [
    'patient_fname'   => trim($_POST['patient_fname'] ?? ''),
    'patient_lname'   => trim($_POST['patient_lname'] ?? ''),
    'patient_gender'  => $_POST['patient_gender'] ?? '',
    'date_of_birth'   => trim($_POST['date_of_birth'] ?? ''),
    'patient_contact' => str_replace(' ', '', trim($_POST['patient_contact'] ?? '')),
    'patient_email'   => trim($_POST['patient_email'] ?? ''),
    'patient_address' => trim($_POST['patient_address'] ?? ''),
    'occupation'      => trim($_POST['occupation'] ?? ''),
];

$controller = new PatientController();
$result = $controller->addPatient($data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect('views/admin/patient_form.php');
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/patient_detail.php?id=' . (int) $result['patient_id']);
