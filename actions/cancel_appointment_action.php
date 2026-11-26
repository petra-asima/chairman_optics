<?php
// Handles the "Cancel" button on the customer's My Appointments page.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

// This page only accepts the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/account/my_appointments.php');
}

require_login();

if (!csrf_valid()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('views/account/my_appointments.php');
}

$controller = new AppointmentController();
$result = $controller->cancelByCustomer((int) ($_POST['appointment_id'] ?? 0), (int) $_SESSION['customer_id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect('views/account/my_appointments.php');
