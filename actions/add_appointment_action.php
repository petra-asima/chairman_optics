<?php
// Handles the front-desk booking form (views/admin/appointment_form.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/appointment_form.php');
}

// Receptionists and Optometrists (and the Admin) manage appointments
require_staff(['Receptionist', 'Optometrist']);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/admin/appointment_form.php');
}

$data = [
    'folder_no'        => trim($_POST['folder_no'] ?? ''),
    'appointment_date' => $_POST['appointment_date'] ?? '',
    'appointment_time' => $_POST['appointment_time'] ?? '',
    'booked_via'       => $_POST['booked_via'] ?? '',
    'optometrist_id'   => $_POST['optometrist_id'] ?? '0',
    'reason'           => trim($_POST['reason'] ?? ''),
];

$controller = new AppointmentController();
$result = $controller->bookAtDesk($data, (int) $_SESSION['staff_id']);

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    set_old($data);
    redirect('views/admin/appointment_form.php?date=' . rawurlencode($data['appointment_date'])
             . '&folder=' . rawurlencode($data['folder_no']));
}

clear_old();
set_flash('success', $result['message']);
redirect('views/admin/appointments.php?date=' . $result['date']);
