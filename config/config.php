<?php
/**
 * Aarcon Builders - Configuration
 * Cash & Check Management System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// System Constants
define('APP_NAME', 'Aarcon Builders');
define('APP_TAGLINE', 'Cash, PDC Check & Disbursement Management');
define('CURRENCY_SYMBOL', '₱');
define('DEFAULT_APPROVER', 'Engr. Boss / General Manager');

// Database Configuration
// Default is SQLite (Zero configuration needed, perfectly portable)
// Set DB_DRIVER to 'mysql' to switch to MySQL
define('DB_DRIVER', 'sqlite'); 

// MySQL configuration (used if DB_DRIVER === 'mysql')
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'aarcon_builders');
define('DB_USER', 'root');
define('DB_PASS', '');

// SQLite Database file path
define('SQLITE_FILE', __DIR__ . '/../database/aarcon_builders.db');

/**
 * Get dynamic currency symbol or fallback
 */
function get_currency_symbol() {
    if (function_exists('get_system_setting')) {
        $val = get_system_setting('currency_symbol');
        if (!empty($val)) return $val;
    }
    return CURRENCY_SYMBOL;
}

/**
 * Get dynamic app/company name
 */
function get_app_name() {
    if (function_exists('get_system_setting')) {
        $val = get_system_setting('company_name');
        if (!empty($val)) return $val;
    }
    return APP_NAME;
}

/**
 * Format currency amount
 */
function format_money($amount) {
    return get_currency_symbol() . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date for display
 */
function format_date($dateStr, $format = 'M d, Y') {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return $time ? date($format, $time) : $dateStr;
}

/**
 * Set flash message in session
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize string output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Load authentication and authorization helpers
require_once __DIR__ . '/../includes/auth.php';
