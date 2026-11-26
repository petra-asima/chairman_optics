<?php
// Book an eye exam online.
// Step 1: choose a day (the page reloads to show that day's free times).
// Step 2: choose a time, add a reason, and book.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

$controller = new AppointmentController();

// The chosen day (from the address bar), or the next day the clinic is open
$date = $_GET['date'] ?? '';
if (!AppointmentController::isValidDate($date)) {
    $date = date('Y-m-d');
    while (!AppointmentController::isOpenDay($date)) {
        $date = date('Y-m-d', strtotime($date . ' +1 day'));
    }
}
$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+' . AppointmentController::BOOK_AHEAD_DAYS . ' days'));

$inRange   = $date >= $minDate && $date <= $maxDate;
$freeSlots = $inRange ? $controller->getFreeSlots($date, true) : [];

$pageTitle = 'Book an Eye Exam';
require __DIR__ . '/layout/header.php';
?>

<section class="shop-banner">
    <div class="container">
        <h1>Book an Eye Exam</h1>
        <p>Choose a day and time that suits you. Our optometrist will see you at <?= e(SITE_ADDRESS) ?>.</p>
    </div>
</section>

<div class="container booking-page">

    <?php if (!is_logged_in()): ?>
        <!-- Guests must log in, so the clinic has their name and phone number -->
        <div class="booking-box booking-login">
            <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
            <h2>Please log in to book</h2>
            <p>We use your account details to confirm your appointment and contact you if anything changes.</p>
            <p>
                <a href="<?= url('views/login.php') ?>" class="btn btn-primary">Log In</a>
                <a href="<?= url('views/register.php') ?>" class="btn btn-light">Create an Account</a>
            </p>
        </div>
    <?php else: ?>
        <div class="booking-box">

            <!-- Step 1: choose a day -->
            <form method="GET" action="<?= url('views/book_appointment.php') ?>" class="booking-step">
                <h2><span class="step-number">1</span> Choose a day</h2>
                <label for="date" class="visually-hidden">Date</label>
                <input type="date" id="date" name="date" value="<?= e($date) ?>"
                       min="<?= $minDate ?>" max="<?= $maxDate ?>" class="js-auto-submit booking-date">
                <noscript><button type="submit" class="btn btn-light">Show times</button></noscript>
                <p class="field-hint">
                    Open <?= e(AppointmentController::OPEN_TIME) ?> to <?= e(AppointmentController::CLOSE_TIME) ?>,
                    Monday to Saturday. [Opening hours to be confirmed with the owner]
                </p>
            </form>

            <!-- Step 2: choose a time and book -->
            <form action="<?= url('actions/book_appointment_action.php') ?>" method="POST" class="booking-step">
                <?= csrf_field() ?>
                <input type="hidden" name="appointment_date" value="<?= e($date) ?>">

                <h2><span class="step-number">2</span> Choose a time on <?= e(date('l j F', strtotime($date))) ?></h2>

                <?php if (!$inRange): ?>
                    <p class="booking-none">Please choose a date within the next <?= AppointmentController::BOOK_AHEAD_DAYS ?> days.</p>
                <?php elseif (!AppointmentController::isOpenDay($date)): ?>
                    <p class="booking-none">The clinic is closed on this day. Please choose another date.</p>
                <?php elseif (empty($freeSlots)): ?>
                    <p class="booking-none">No times are free on this day. Please choose another date.</p>
                <?php else: ?>
                    <fieldset class="slot-grid">
                        <legend class="visually-hidden">Available times</legend>
                        <?php foreach ($freeSlots as $slot): ?>
                            <label class="slot">
                                <input type="radio" name="appointment_time" value="<?= e($slot) ?>" required>
                                <span><?= e(date('g:i a', strtotime($slot))) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="form-group">
                        <label for="reason">Reason for your visit (optional)</label>
                        <input type="text" id="reason" name="reason" maxlength="255" value="<?= old('reason') ?>"
                               placeholder="e.g. blurred vision, new glasses, routine check">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Book Appointment
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <p class="booking-mine">
            <a href="<?= url('views/account/my_appointments.php') ?>">See or cancel my appointments &rarr;</a>
        </p>
    <?php endif; ?>
</div>

<script src="<?= url('js/filters.js') ?>"></script>
<?php
clear_old();
require __DIR__ . '/layout/footer.php';
?>
