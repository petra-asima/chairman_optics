<?php
// Staff login page for the back office
require_once __DIR__ . '/../../core/core.php';
redirect_if_staff_logged_in();

$pageTitle = 'Staff Log In';
require __DIR__ . '/../layout/auth_header.php';
?>

<div class="auth-card auth-card-small">
    <div class="auth-banner">
        <a href="<?= url('index.php') ?>">
            <img src="<?= url('images/logo.png') ?>" alt="Chairman Optics and Lens Centre - home" class="auth-banner-logo">
        </a>
        <h1>Staff Log In</h1>
    </div>

    <div class="auth-content">
        <p class="auth-intro">For Chairman Optics staff only. Customers can <a href="<?= url('views/login.php') ?>">log in here</a>.</p>

        <form action="<?= url('actions/staff_login_action.php') ?>" method="POST" id="staff-login-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="staff_email">Staff Email</label>
                <input type="email" id="staff_email" name="staff_email"
                       value="<?= old('staff_email') ?>" placeholder="you@chairmanoptics.com"
                       autocomplete="username" required>
            </div>

            <div class="form-group">
                <label for="staff_pass">Password</label>
                <div class="password-wrap">
                    <input type="password" id="staff_pass" name="staff_pass"
                           placeholder="Your password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password" data-target="staff_pass" aria-label="Show password">
                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-dark btn-block">Log In</button>
        </form>
    </div>
</div>

<?php
clear_old();
require __DIR__ . '/../layout/auth_footer.php';
?>