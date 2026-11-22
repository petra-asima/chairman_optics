<?php
// Included at the top of every page and action.
// Sets up errors, the session, settings and shared helper functions.

// Errors: show them on localhost, hide them on the live server, always log them
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') === 0;

error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error/error.log');

// Session with safer cookie settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');

    // Only mark the cookie as secure when the page is really on HTTPS.
    // The school server uses plain HTTP, so a secure-only cookie would
    // never be sent back and every page would start a new session.
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

date_default_timezone_set('Africa/Accra');

define('BASE_URL', $isLocal ? '/chairmanoptics/' : '/~petra.asima/e_com_final_project/');

// Business details used across the site
define('SITE_NAME', 'Chairman Optics');
define('SITE_PHONE', '[Phone number]');
define('SITE_EMAIL', '[Email address]');
define('SITE_ADDRESS', 'Meduma-Abass Junction, off Maa Se Road, Kumasi');

// Options for the gender dropdown on the registration form
const GENDERS = ['Female', 'Male', 'Prefer not to say'];

require_once __DIR__ . '/db_class.php';

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

// Full link from the project root: url('views/login.php') → /chairmanoptics/views/login.php
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

// Send the browser to another page, then stop this script
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Make text safe to print inside HTML
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Write a timestamped line to error/error.log
function log_error(string $message): void
{
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, __DIR__ . '/../error/error.log');
}

// ---------------------------------------------------------------
// Messages shown once on the next page (success or error)
// ---------------------------------------------------------------

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Returns the message and removes it so it only shows once
function get_flash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ---------------------------------------------------------------
// Keep what the user typed when a form is sent back with errors
// ---------------------------------------------------------------

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function old(string $field): string
{
    return e($_SESSION['old'][$field] ?? '');
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

// Field errors sent back by an action, read once by the form page
function get_form_errors(): array
{
    $errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    return $errors;
}

// Prints the error message under one form field, if it has one
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

// ---------------------------------------------------------------
// CSRF protection: every form sends a hidden token that must match
// ---------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Prints the hidden input inside a form
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

// ---------------------------------------------------------------
// Customer login checks
// ---------------------------------------------------------------

function is_logged_in(): bool
{
    return isset($_SESSION['customer_id']);
}

// Send visitors who are not logged in to the login page
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('views/login.php');
    }
}

// Logged-in customers do not need the login or register pages
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect('index.php');
    }
}