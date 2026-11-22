<?php
// Top of the login and register pages.
// These pages use their own simple layout, without the main menu and footer.
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
    <link rel="icon" type="image/png" href="<?= url('images/favicon.png') ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body>

<main class="auth-page">
    <a href="<?= url('index.php') ?>" class="back-link">
    <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
    Back to home
</a>

    <?php
    // One-time success or error message from the last action
    $flash = get_flash();
    if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> auth-alert" role="alert"><?= e($flash['message']) ?></div>
    <?php endif; ?>