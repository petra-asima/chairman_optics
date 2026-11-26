<?php
// Handles the "Save" button on each appointment in the staff day view:
// status, optometrist, patient folder and notes.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/appointments.php');
}

// Receptionists and Optometrists (and the Admin) manage appointments
require_staff(['Receptionist', 'Optometrist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/appointments.php');
}

$data = [
    'appointment_status' => $_POST['appointment_status'] ?? '',
    'optometrist_id'     => $_POST['optometrist_id'] ?? '0',
    'folder_no'          => $_POST['folder_no'] ?? '',
    'notes'              => trim($_POST['notes'] ?? ''),
];

$controller = new AppointmentController();
$result = $controller->updateByStaff((int) ($_POST['appointment_id'] ?? 0), $data, (int) $_SESSION['staff_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/admin/appointments.php?date=' . $result['date']);
