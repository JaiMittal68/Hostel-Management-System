<?php
// logout.php
require_once 'includes/config.php';
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'LOGOUT', 'Auth', 'User logged out');
}
session_unset();
session_destroy();
redirect(SITE_URL . 'login.php');
