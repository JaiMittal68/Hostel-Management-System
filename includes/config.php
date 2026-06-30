<?php
// ============================================================
// DEBUG MODE — set to false in production
// While true, PHP/MySQL errors are printed directly on screen
// (via our own handlers below) instead of a blank "HTTP ERROR 500"
// page. This works even if Apache/php.ini has display_errors
// locked off, because we manually echo the error ourselves
// rather than relying on PHP's built-in display mechanism.
// ============================================================
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Catch uncaught exceptions (e.g. mysqli_sql_exception) and print clearly
    set_exception_handler(function ($e) {
        http_response_code(500);
        echo '<div style="font-family:monospace;background:#fff3f3;border:2px solid #ef4444;
                    padding:20px;margin:20px;border-radius:8px;color:#7f1d1d">';
        echo '<h2 style="margin-bottom:10px">⚠️ Uncaught Exception</h2>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (line ' . $e->getLine() . ')</p>';
        echo '<pre style="white-space:pre-wrap;font-size:12px;margin-top:10px">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    });

    // Catch fatal errors (parse-safe runtime fatals) that PHP would otherwise
    // show as a blank page if display_errors is locked off at the server level
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            http_response_code(500);
            echo '<div style="font-family:monospace;background:#fff3f3;border:2px solid #ef4444;
                        padding:20px;margin:20px;border-radius:8px;color:#7f1d1d">';
            echo '<h2 style="margin-bottom:10px">⚠️ Fatal Error</h2>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($error['message']) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($error['file']) . ' (line ' . $error['line'] . ')</p>';
            echo '</div>';
        }
    });
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Make MySQLi throw catchable exceptions instead of silently failing
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hostel_db');

define('SITE_NAME', 'Hostel Management System');
define('SITE_URL', 'http://localhost/hostel_management/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . 'uploads/');

// Session timeout (in seconds)
define('SESSION_TIMEOUT', 3600);

// Pagination
define('RECORDS_PER_PAGE', 10);

// ============================================================
// DATABASE CONNECTION (MySQLi)
// ============================================================
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function sanitize($data) {
    $db = getDB();
    return $db->real_escape_string(htmlspecialchars(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . 'login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(SITE_URL . 'unauthorized.php');
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function isAdmin() { return hasRole('admin'); }
function isWarden() { return hasRole('warden') || hasRole('admin'); }
function isStudent() { return hasRole('student'); }

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateReceiptNumber() {
    return 'RCT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generateComplaintNumber() {
    return 'CMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function logActivity($user_id, $action, $module, $description = '') {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, module, description, ip_address) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $user_id, $action, $module, $description, $ip);
    $stmt->execute();
}

function sendNotification($user_id, $title, $message, $type = 'general', $link = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $user_id, $title, $message, $type, $link);
    $stmt->execute();
}

function getUnreadNotifications($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function formatDate($date) {
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function formatDateTime($dt) {
    return $dt ? date('d M Y, h:i A', strtotime($dt)) : '-';
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function uploadFile($file, $folder = 'students') {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false; // 5MB limit
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_DIR . $folder . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $folder . '/' . $filename;
    }
    return false;
}

function paginate($total, $perPage, $currentPage) {
    $totalPages = ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    return ['totalPages' => $totalPages, 'offset' => $offset, 'currentPage' => $currentPage];
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout check
if (isLoggedIn()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect(SITE_URL . 'login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}
