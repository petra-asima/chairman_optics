<?php
// A customer's own appointments, with a Cancel button for upcoming ones.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/AppointmentController.php';
require_login();

$controller   = new AppointmentController();
$appointments = $controller->getCustomerAppointments((int) $_SESSION['customer_id']);

$pageTitle = 'My Appointments';
require __DIR__ . '/../layout/header.php';
?>

<section class="shop-banner">
    <div class="container">
        <h1>My Appointments</h1>
        <p>Your eye exam bookings with <?= e(SITE_NAME) ?>.</p>
    </div>
</section>

<div class="container booking-page">
    <p><a href="<?= url('views/book_appointment.php') ?>" class="btn btn-primary">Book an Eye Exam</a></p>

    <?php if (empty($appointments)): ?>
        <div class="shop-empty">
            <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
            <h2>No appointments yet</h2>
            <p>Book an eye exam online in less than a minute.</p>
        </div>
    <?php else: ?>
        <ul class="my-appointments">
            <?php foreach ($appointments as $a): ?>
                <li class="booking-box my-appointment">
                    <div class="my-appointment-date">
                        <span class="day"><?= e(date('j', strtotime($a['appointment_date']))) ?></span>
                        <span class="month"><?= e(date('M Y', strtotime($a['appointment_date']))) ?></span>
                    </div>
                    <div class="my-appointment-info">
                        <p class="my-appointment-when">
                            <?= e(date('l', strtotime($a['appointment_date']))) ?> at
                            <strong><?= e(date('g:i a', strtotime($a['appointment_time']))) ?></strong>
                            <span class="badge <?= appointment_status_badge($a['appointment_status']) ?>"><?= e($a['appointment_status']) ?></span>
                        </p>
                        <?php if ($a['reason']): ?>
                            <p class="my-appointment-reason"><?= e($a['reason']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (AppointmentController::customerCanCancel($a)): ?>
                        <form action="<?= url('actions/cancel_appointment_action.php') ?>" method="POST"
                              onsubmit="return confirm('Cancel this appointment?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="appointment_id" value="<?= (int) $a['appointment_id'] ?>">
                            <button type="submit" class="cart-remove">
                                <span class="material-symbols-outlined" aria-hidden="true">event_busy</span> Cancel
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
