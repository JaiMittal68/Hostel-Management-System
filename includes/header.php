<?php
requireLogin();
$unread = getUnreadNotifications($_SESSION['user_id']);
$unreadCount = count($unread);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-building"></i>
            <span>HMS</span>
        </div>
        <p class="logo-sub">Hostel Management</p>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">Main</span>
            <a href="<?= SITE_URL ?>dashboard.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
        </div>

        <?php if (isAdmin() || isWarden()): ?>
        <div class="nav-section">
            <span class="nav-section-label">Management</span>
            <a href="<?= SITE_URL ?>php/students.php" class="nav-item <?= ($activePage ?? '') === 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i><span>Students</span>
            </a>
            <a href="<?= SITE_URL ?>php/rooms.php" class="nav-item <?= ($activePage ?? '') === 'rooms' ? 'active' : '' ?>">
                <i class="fas fa-door-open"></i><span>Rooms</span>
            </a>
            <a href="<?= SITE_URL ?>php/allotments.php" class="nav-item <?= ($activePage ?? '') === 'allotments' ? 'active' : '' ?>">
                <i class="fas fa-bed"></i><span>Room Allotments</span>
            </a>
            <a href="<?= SITE_URL ?>php/fees.php" class="nav-item <?= ($activePage ?? '') === 'fees' ? 'active' : '' ?>">
                <i class="fas fa-rupee-sign"></i><span>Fee Management</span>
            </a>
            <a href="<?= SITE_URL ?>php/visitors.php" class="nav-item <?= ($activePage ?? '') === 'visitors' ? 'active' : '' ?>">
                <i class="fas fa-user-friends"></i><span>Visitors</span>
            </a>
            <a href="<?= SITE_URL ?>php/gate_pass.php" class="nav-item <?= ($activePage ?? '') === 'gate_pass' ? 'active' : '' ?>">
                <i class="fas fa-id-card"></i><span>Gate Pass</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <span class="nav-section-label">Services</span>
            <a href="<?= SITE_URL ?>php/complaints.php" class="nav-item <?= ($activePage ?? '') === 'complaints' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-circle"></i><span>Complaints</span>
            </a>
            <a href="<?= SITE_URL ?>php/notices.php" class="nav-item <?= ($activePage ?? '') === 'notices' ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i><span>Notices</span>
            </a>
            <a href="<?= SITE_URL ?>php/mess_menu.php" class="nav-item <?= ($activePage ?? '') === 'mess' ? 'active' : '' ?>">
                <i class="fas fa-utensils"></i><span>Mess Menu</span>
            </a>
        </div>

        <?php if (isAdmin()): ?>
        <div class="nav-section">
            <span class="nav-section-label">Reports</span>
            <a href="<?= SITE_URL ?>php/reports.php" class="nav-item <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i><span>Reports</span>
            </a>
            <a href="<?= SITE_URL ?>php/users.php" class="nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i><span>User Management</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <span class="nav-section-label">Account</span>
            <a href="<?= SITE_URL ?>php/profile.php" class="nav-item <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i><span>My Profile</span>
            </a>
            <a href="<?= SITE_URL ?>logout.php" class="nav-item nav-logout">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <!-- Top Navbar -->
    <header class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <div class="topbar-right">
            <!-- Notifications -->
            <div class="notif-wrapper">
                <button class="notif-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <h4>Notifications</h4>
                        <?php if ($unreadCount > 0): ?>
                            <a href="<?= SITE_URL ?>php/mark_read.php" class="mark-all-read">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <div class="notif-list">
                        <?php if (empty($unread)): ?>
                            <div class="notif-empty"><i class="fas fa-check-circle"></i><p>All caught up!</p></div>
                        <?php else: ?>
                            <?php foreach ($unread as $n): ?>
                                <a href="<?= SITE_URL ?>php/mark_read.php?id=<?= $n['id'] ?>&redirect=<?= urlencode($n['link']) ?>" class="notif-item">
                                    <div class="notif-icon notif-<?= $n['type'] ?>">
                                        <i class="fas <?= $n['type'] === 'fee_due' ? 'fa-rupee-sign' : ($n['type'] === 'complaint_update' ? 'fa-tools' : 'fa-info') ?>"></i>
                                    </div>
                                    <div class="notif-body">
                                        <p class="notif-title"><?= htmlspecialchars($n['title']) ?></p>
                                        <p class="notif-msg"><?= htmlspecialchars(substr($n['message'], 0, 60)) ?>...</p>
                                        <span class="notif-time"><?= formatDateTime($n['created_at']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="user-menu">
                <div class="user-avatar">
                    <?php if (!empty($_SESSION['photo'])): ?>
                        <img src="<?= UPLOAD_URL . $_SESSION['photo'] ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initials"><?= strtoupper(substr($_SESSION['name'], 0, 2)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Message -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> fade-in">
        <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <!-- Page Content Starts -->
    <main class="page-content">
