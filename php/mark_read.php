<?php
require_once '../includes/config.php';
requireLogin();

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirect = $_GET['redirect'] ?? '';

if ($id) {
    $db->query("UPDATE notifications SET is_read=1 WHERE id=$id AND user_id={$_SESSION['user_id']}");
} else {
    // Mark all read
    $db->query("UPDATE notifications SET is_read=1 WHERE user_id={$_SESSION['user_id']}");
}

$dest = $redirect ? SITE_URL . ltrim($redirect, '/') : SITE_URL . 'dashboard.php';
redirect($dest);
