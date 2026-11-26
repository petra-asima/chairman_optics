<?php
// Handles the online booking form (views/book_appointment.php).
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/book_appointment.php');
}

// Customers must be logged in, so the clinic can contact them
require_login();

$date     = $_POST['appointment_date'] ?? '';
$formPage = 'views/book_appointment.php?date=' . rawurlencode($date);

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($formPage);
}

$data = [
    'appointment_date' => $date,
    'appointment_time' => $_POST['appointment_time'] ?? '',
    'reason'           => trim($_POST['reason'] ?? ''),
];

$controller = new AppointmentController();
$result = $controller->bookOnline((int) $_SESSION['customer_id'], $data);

if (!$result['success']) {
    set_old(['reason' => $data['reason']]);
    set_flash('error', $result['message']);
    redirect($formPage);
}

clear_old();
set_flash('success', $result['message']);
redirect('views/account/my_appointments.php');
