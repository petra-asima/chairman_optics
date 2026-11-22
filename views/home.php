<?php
// Home page (temporary). The full homepage design is built in a later step.
$pageTitle = 'Home';
require __DIR__ . '/layout/header.php';
?>

<section class="page-hero">
    <div class="page-hero-content">
        <img src="<?= url('images/logo-white.png') ?>" alt="" class="page-hero-logo">
        <h1>Expert Eye Care and Quality Glasses in Kumasi</h1>
        <p>The full homepage is coming in the next step.</p>
        <?php if (!is_logged_in()): ?>
            <p><a href="<?= url('views/register.php') ?>" class="btn btn-light">Create an Account</a></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>