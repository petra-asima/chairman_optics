<?php
// Top of every back-office page: sidebar menu and top bar.
// The page must call require_staff() and set $pageTitle before including this.
$pageTitle   = $pageTitle ?? 'Staff Area';
$menu        = require __DIR__ . '/admin_menu.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?> Staff</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= url('images/favicon.png') ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- Sidebar: only shows the sections this role can use -->
    <aside class="admin-sidebar">
        <a href="<?= url('views/admin/dashboard.php') ?>" class="admin-logo">
            <img src="<?= url('images/logo-white.png') ?>" alt="Chairman Optics staff area">
        </a>

        <nav aria-label="Staff menu">
            <ul class="admin-nav">
                <?php foreach ($menu as $item): ?>
                    <?php if (!staff_can($item['roles'])) continue; ?>
                    <?php $isCurrent = basename($item['page']) === $currentPage
                                    || in_array($currentPage, $item['subpages'] ?? [], true); ?>
                    <li>
                        <?php if ($item['ready']): ?>
                            <a href="<?= url($item['page']) ?>" class="<?= $isCurrent ? 'active' : '' ?>"
                               <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                                <span class="material-symbols-outlined" aria-hidden="true"><?= e($item['icon']) ?></span>
                                <?= e($item['label']) ?>
                            </a>
                        <?php else: ?>
                            <span class="admin-nav-soon" title="Coming soon">
                                <span class="material-symbols-outlined" aria-hidden="true"><?= e($item['icon']) ?></span>
                                <?= e($item['label']) ?>
                                <small>Soon</small>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <div class="admin-main">

        <!-- Top bar: page title, who is logged in, and log out -->
        <header class="admin-topbar">
            <h1><?= e($pageTitle) ?></h1>

            <div class="admin-user">
                <span class="admin-user-name"><?= e($_SESSION['staff_name'] ?? '') ?></span>
                <span class="role-badge"><?= e(staff_role()) ?></span>

                <form action="<?= url('actions/staff_logout_action.php') ?>" method="POST" class="admin-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-small">
                        <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                        Log Out
                    </button>
                </form>
            </div>
        </header>

        <main class="admin-content">
            <?php
            // One-time success or error message from the last action
            $flash = get_flash();
            if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
            <?php endif; ?>