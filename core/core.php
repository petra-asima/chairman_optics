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

// Show an amount in Ghana cedis: money(1250.5) → GH₵ 1,250.50
function money($amount): string
{
    return 'GH₵ ' . number_format((float) $amount, 2);
}

// The badge colour for an order status. The status word is always shown
// in the badge too, so the colour is never the only clue.
function order_status_badge(string $status): string
{
    $classes = [
        'Pending'   => 'badge-warning',
        'Cutting'   => 'badge-info',
        'Fitting'   => 'badge-info',
        'Ready'     => 'badge-success',
        'Delivered' => 'badge-muted',
        'Cancelled' => 'badge-danger',
    ];
    return $classes[$status] ?? 'badge-muted';
}

// The badge colour for an appointment status (the word is always shown too)
function appointment_status_badge(string $status): string
{
    $classes = [
        'Booked'    => 'badge-warning',
        'Confirmed' => 'badge-info',
        'Completed' => 'badge-success',
        'Cancelled' => 'badge-danger',
        'No-show'   => 'badge-muted',
    ];
    return $classes[$status] ?? 'badge-muted';
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

// ---------------------------------------------------------------
// Whose cart is it?
// A logged-in customer's cart is linked to their customer_id.
// A guest's cart is linked to a random code kept in a cookie in their
// own browser (saved in the ip_add column of co_cart). The shoppn lab
// used the IP address instead, but many people in Ghana share one IP
// address (mobile networks, school and office Wi-Fi), so strangers
// would see each other's carts.
// ---------------------------------------------------------------

const CART_COOKIE = 'co_cart';
const CART_COOKIE_DAYS = 30;

/**
 * The owner of the current cart.
 * @param bool $create  true = give a guest a new cart code if they have
 *                      none. Only pass true before any HTML is sent,
 *                      because it sets a cookie (actions do this).
 * @return array ['customer_id' => int|null, 'guest_key' => string|null]
 */
function cart_owner(bool $create = false): array
{
    if (is_logged_in()) {
        return ['customer_id' => (int) $_SESSION['customer_id'], 'guest_key' => null];
    }

    // Only accept a code in our own format (32 hex characters)
    $key = $_COOKIE[CART_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
        $key = '';
    }

    if ($key === '' && $create) {
        $key = bin2hex(random_bytes(16));
        setcookie(CART_COOKIE, $key, [
            'expires'  => time() + CART_COOKIE_DAYS * 24 * 60 * 60,
            'path'     => BASE_URL,
            'httponly' => true,       // JavaScript cannot read it
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        $_COOKIE[CART_COOKIE] = $key; // so the rest of this request can use it
    }

    return ['customer_id' => null, 'guest_key' => $key !== '' ? $key : null];
}

/** Remove the guest cart cookie (after the cart moves into an account). */
function forget_guest_cart(): void
{
    setcookie(CART_COOKIE, '', ['expires' => time() - 3600, 'path' => BASE_URL]);
    unset($_COOKIE[CART_COOKIE]);
}

// ---------------------------------------------------------------
// Staff login checks (back office)
// Staff use different session keys from customers, so a customer
// login never gives access to staff pages.
// ---------------------------------------------------------------

// The five roles agreed with the owner. They match the co_roles table.
const STAFF_ROLES = ['Admin', 'Receptionist', 'Optometrist', 'Lab Technician', 'Accountant'];

// Staff are logged out after this many minutes without any activity
const STAFF_TIMEOUT_MINUTES = 30;

function is_staff_logged_in(): bool
{
    return isset($_SESSION['staff_id']);
}

// The logged-in staff member's role, or an empty string
function staff_role(): string
{
    return $_SESSION['staff_role'] ?? '';
}

// Is the logged-in staff member allowed to use something?
// Admin can use everything. An empty list means any staff role.
function staff_can(array $allowedRoles = []): bool
{
    if (!is_staff_logged_in()) {
        return false;
    }
    if (staff_role() === 'Admin' || empty($allowedRoles)) {
        return true;
    }
    return in_array(staff_role(), $allowedRoles, true);
}

// Remove only the staff details from the session
function clear_staff_session(): void
{
    unset($_SESSION['staff_id'], $_SESSION['staff_name'],
          $_SESSION['staff_role'], $_SESSION['staff_last_active']);
}

// Put this at the top of every staff page.
// Example: require_staff(['Receptionist', 'Optometrist']);
function require_staff(array $allowedRoles = []): void
{
    if (!is_staff_logged_in()) {
        set_flash('error', 'Please log in to the staff area.');
        redirect('views/admin/login.php');
    }

    // Log out staff who have been inactive for too long
    $lastActive = $_SESSION['staff_last_active'] ?? time();
    if (time() - $lastActive > STAFF_TIMEOUT_MINUTES * 60) {
        clear_staff_session();
        set_flash('error', 'You were logged out after ' . STAFF_TIMEOUT_MINUTES . ' minutes of inactivity.');
        redirect('views/admin/login.php');
    }
    $_SESSION['staff_last_active'] = time();

    // Check the account in the database on every staff page. Then, when
    // the Admin disables an account or changes a role, it takes effect
    // straight away instead of when the person next logs in.
    require_once __DIR__ . '/../classes/StaffClass.php';
    $staffModel = new StaffClass();
    $account    = $staffModel->getStaffById((int) $_SESSION['staff_id']);

    if (!$account || (int) $account['is_active'] !== 1) {
        clear_staff_session();
        set_flash('error', 'Your account has been disabled. Please contact the Admin.');
        redirect('views/admin/login.php');
    }
    $_SESSION['staff_role'] = $account['role_name'];
    $_SESSION['staff_name'] = $account['staff_fname'] . ' ' . $account['staff_lname'];

    if (!staff_can($allowedRoles)) {
        set_flash('error', 'Your role does not have access to that page.');
        redirect('views/admin/dashboard.php');
    }
}

// Logged-in staff do not need the staff login page
function redirect_if_staff_logged_in(): void
{
    if (is_staff_logged_in()) {
        redirect('views/admin/dashboard.php');
    }
}