<?php
// Customer registration page
require_once __DIR__ . '/../core/core.php';
redirect_if_logged_in();

$errors    = get_form_errors();
$pageTitle = 'Create an Account';
require __DIR__ . '/layout/auth_header.php';
?>

<div class="auth-card">
    <div class="auth-banner">
        <a href="<?= url('index.php') ?>">
            <img src="<?= url('images/logo.png') ?>" alt="Chairman Optics and Lens Centre - home" class="auth-banner-logo">
        </a>
        <h1>Create Your Account</h1>
    </div>

    <div class="auth-content">
        <p class="auth-intro">Please fill in the fields below to create your account. Fields marked <span class="required">*</span> are required.</p>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form action="<?= url('actions/register_action.php') ?>" method="POST" id="register-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="customer_fname">First Name <span class="required">*</span></label>
                    <input type="text" id="customer_fname" name="customer_fname"
                           value="<?= old('customer_fname') ?>" placeholder="Your first name"
                           maxlength="50" autocomplete="given-name" required>
                    <?= field_error($errors, 'fname') ?>
                </div>

                <div class="form-group">
                    <label for="customer_lname">Last Name <span class="required">*</span></label>
                    <input type="text" id="customer_lname" name="customer_lname"
                           value="<?= old('customer_lname') ?>" placeholder="Your surname"
                           maxlength="50" autocomplete="family-name" required>
                    <?= field_error($errors, 'lname') ?>
                </div>
            </div>

            <div class="form-group">
                <label for="customer_gender">Gender <span class="required">*</span></label>
                <select id="customer_gender" name="customer_gender" required>
                    <option value="">-- Select Gender --</option>
                    <?php foreach (GENDERS as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= old('customer_gender') === e($gender) ? 'selected' : '' ?>>
                            <?= e($gender) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'gender') ?>
            </div>

            <div class="form-group">
                <label for="customer_email">Email <span class="required">*</span></label>
                <input type="email" id="customer_email" name="customer_email"
                       value="<?= old('customer_email') ?>" placeholder="you@example.com"
                       maxlength="100" autocomplete="email" required>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="form-group">
                <label for="customer_contact">Phone Number <span class="required">*</span></label>
                <input type="tel" id="customer_contact" name="customer_contact"
                       value="<?= old('customer_contact') ?>" placeholder="024 123 4567"
                       maxlength="20" autocomplete="tel" required>
                <p class="field-hint">We send SMS updates about your orders and appointments to this number.</p>
                <?= field_error($errors, 'contact') ?>
            </div>

            <div class="form-group">
                <label for="customer_pass">Password <span class="required">*</span></label>
                <div class="password-wrap">
                    <input type="password" id="customer_pass" name="customer_pass"
                           placeholder="Your password" autocomplete="new-password" required>
                    <button type="button" class="toggle-password" data-target="customer_pass" aria-label="Show password">
                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                    </button>
                </div>
                <p class="field-hint">At least 8 characters, with a letter and a number.</p>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="form-group">
                <label for="confirm_pass">Confirm Password <span class="required">*</span></label>
                <div class="password-wrap">
                    <input type="password" id="confirm_pass" name="confirm_pass"
                           placeholder="Type your password again" autocomplete="new-password" required>
                    <button type="button" class="toggle-password" data-target="confirm_pass" aria-label="Show password">
                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                    </button>
                </div>
                <?= field_error($errors, 'confirm') ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="<?= url('views/login.php') ?>">Log in</a></p>
    </div>
</div>

<?php
clear_old();
require __DIR__ . '/layout/auth_footer.php';
?>