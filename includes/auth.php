<?php
/**
 * Authentication and Session Management
 * PT Data Lake Indonesia
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Safe output helper
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

// Check if current user is admin
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Require login (redirects to login.php if not logged in)
function require_login($redirectTo = 'login.php') {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman ini.';
        header("Location: " . $redirectTo);
        exit;
    }
}

// Require admin privileges
function require_admin($redirectTo = 'login.php') {
    require_login($redirectTo);
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Akses ditolak. Anda tidak memiliki izin administrator.';
        header("Location: dashboard.php");
        exit;
    }
}

// Log user activity
function log_activity($action, $details = null) {
    try {
        $pdo = get_db_connection();
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently continue if log fails
    }
}

// Set flash message
function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

// Get and clear flash message
function get_flash($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
