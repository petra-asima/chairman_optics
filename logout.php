<?php
// Logs the customer out and returns them to the login page.
require_once __DIR__ . '/core/core.php';

// Empty the session, delete its cookie, then end it
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'],
              $params['secure'], $params['httponly']);
}
session_destroy();

// Start a fresh session only to show the message
session_start();
set_flash('success', 'You have been logged out.');
redirect('views/login.php');