<?php
// Customer login page
require_once __DIR__ . '/../core/core.php';
redirect_if_logged_in();

$pageTitle = 'Log In';
require __DIR__ . '/layout/auth_header.php';
?>

<div class="auth-card auth-card-small">
    <div class="auth-banner">
        <a href="<?= url('index.php') ?>">
            <img src="<?= url('images/logo.png') ?>" alt="Chairman Optics and Lens Centre - home" class="auth-banner-logo">
        </a>
        <h1>Log In</h1>
    </div>

    <div class="auth-content">
        <p class="auth-switch auth-switch-top">Don't have an account? <a href="<?= url('views/register.php') ?>">Sign up</a></p>

        <form action="<?= url('actions/login_action.php') ?>" method="POST" id="login-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="customer_email">Email</label>
                <input type="email" id="customer_email" name="customer_email"
                       value="<?= old('customer_email') ?>" placeholder="you@example.com"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="customer_pass">Password</label>
                <div class="password-wrap">
                    <input type="password" id="customer_pass" name="customer_pass"
                           placeholder="Your password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password" data-target="customer_pass" aria-label="Show password">
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
require __DIR__ . '/layout/auth_footer.php';
?>