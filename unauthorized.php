<?php
require_once 'includes/config.php';
$pageTitle = 'Unauthorized';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized — HMS</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc">
    <div style="text-align:center;max-width:400px;padding:40px">
        <div style="font-size:80px;margin-bottom:16px">🚫</div>
        <h1 style="font-size:28px;font-weight:700;color:#1e293b;margin-bottom:8px">Access Denied</h1>
        <p style="color:#64748b;margin-bottom:24px">You do not have permission to access this page.</p>
        <a href="<?= SITE_URL ?>dashboard.php" class="btn btn-primary" style="display:inline-flex;gap:8px;align-items:center">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>
