<?php
// Shared top of every customer page: <head>, site header and menu.
// Set $pageTitle before including this file.
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="icon" type="image/png" href="<?= url('images/favicon.png') ?>">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="header-bar">
            <a href="<?= url('index.php') ?>" class="logo">
                <img src="<?= url('images/logo.png') ?>" alt="Chairman Optics and Lens Centre – home" class="logo-img">
            </a>

            <button type="button" class="menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="main-nav">
                <span class="material-symbols-outlined" aria-hidden="true">menu</span>
            </button>

            <nav class="main-nav" id="main-nav" aria-label="Main menu">
                <ul class="nav-list">
                    <li><a href="<?= url('index.php') ?>" class="nav-link">Home</a></li>

                    <li class="dropdown">
                        <button type="button" class="nav-link dropdown-toggle" aria-expanded="false">
                            Services <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a href="<?= url('views/services.php#eye-exams') ?>">Eye Examinations</a></li>
                            <li><a href="<?= url('views/services.php#glasses') ?>">Prescription Glasses</a></li>
                            <li><a href="<?= url('views/services.php#lenses') ?>">Lenses and Coatings</a></li>
                            <li><a href="<?= url('views/services.php#children') ?>">Children's Eye Care</a></li>
                            <li><a href="<?= url('views/services.php#repairs') ?>">Frame Repairs</a></li>
                        </ul>
                    </li>

                    <li class="dropdown">
                        <button type="button" class="nav-link dropdown-toggle" aria-expanded="false">
                            About Us <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a href="<?= url('views/about.php') ?>">Our Story</a></li>
                            <li><a href="<?= url('views/about.php#team') ?>">Our Team</a></li>
                        </ul>
                    </li>

                    <li class="dropdown">
                        <button type="button" class="nav-link dropdown-toggle" aria-expanded="false">
                            Pages <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a href="<?= url('views/book_appointment.php') ?>">Book an Appointment</a></li>
                            <li><a href="<?= url('views/prescription_order.php') ?>">Order with Prescription</a></li>
                            <li><a href="<?= url('views/track_order.php') ?>">Track Your Order</a></li>
                            <li><a href="<?= url('index.php#faq') ?>">FAQ</a></li>
                            <li><a href="<?= url('index.php#testimonials') ?>">Testimonials</a></li>
                        </ul>
                    </li>

                    <li class="dropdown">
                        <button type="button" class="nav-link dropdown-toggle" aria-expanded="false">
                            Shop <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a href="<?= url('views/all_products.php') ?>">All Products</a></li>
                            <li><a href="<?= url('views/all_products.php?type=frames') ?>">Frames</a></li>
                            <li><a href="<?= url('views/all_products.php?type=lenses') ?>">Lenses</a></li>
                            <li><a href="<?= url('views/all_products.php?type=accessories') ?>">Accessories</a></li>
                            <li><a href="<?= url('views/cart.php') ?>">My Cart</a></li>
                        </ul>
                    </li>

                    <li><a href="<?= url('views/blog.php') ?>" class="nav-link">Blog</a></li>
                    <li><a href="<?= url('index.php#contact') ?>" class="nav-link">Contact</a></li>
                </ul>

                <div class="header-actions">
                    <?php if (is_logged_in()): ?>
                        <div class="dropdown">
                            <button type="button" class="nav-link dropdown-toggle account-link" aria-expanded="false">
                                <span class="material-symbols-outlined" aria-hidden="true">account_circle</span>
                                <?= e($_SESSION['customer_name']) ?>
                                <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li><a href="<?= url('views/account/my_account.php') ?>">My Account</a></li>
                                <li><a href="<?= url('views/account/order_history.php') ?>">My Orders</a></li>
                                <li><a href="<?= url('views/account/my_appointments.php') ?>">My Appointments</a></li>
                                <li><a href="<?= url('logout.php') ?>">Log Out</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= url('views/login.php') ?>" class="nav-link account-link">
                            <span class="material-symbols-outlined" aria-hidden="true">person</span>
                            Log In
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('views/book_appointment.php') ?>" class="btn btn-primary">Book an Eye Exam</a>
                </div>
            </nav>
        </div>
    </div>
</header>

<main>
<?php
// One-time success or error message from the last action
$flash = get_flash();
if ($flash): ?>
    <div class="container">
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
    </div>
<?php endif; ?>