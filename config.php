<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'payroll_db');

// Attempt to connect to MySQL database
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Start session if not already started with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Global functions
/**
 * Sanitize user input to prevent XSS
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Log system activity
 */
function logActivity($pdo, $user_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silently fail or log to a file
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    
    // Force password change on first login (Exempt Admin)
    if (isset($_SESSION['first_login']) && $_SESSION['first_login'] && $_SESSION['role'] !== 'admin' && basename($_SERVER['PHP_SELF']) !== 'force_change_password.php' && basename($_SERVER['PHP_SELF']) !== 'logout.php') {
        redirect('force_change_password.php');
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isHR() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

/**
 * Check if the user has permission to access a page
 */
function authorize($allowed_roles) {
    checkLogin();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['error'] = "Access denied. You do not have permission to view this page.";
        redirect('dashboard.php');
    }
}
?>
