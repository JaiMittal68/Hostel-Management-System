<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    redirect(SITE_URL . 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['name']          = $user['name'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['photo']         = $user['profile_photo'];
            $_SESSION['last_activity'] = time();

            logActivity($user['id'], 'LOGIN', 'Auth', 'User logged in from ' . $_SERVER['REMOTE_ADDR']);
            redirect(SITE_URL . 'dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --secondary: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --dark2: #1e293b;
            --gray: #94a3b8;
            --border: #e2e8f0;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: var(--dark);
            overflow: hidden;
        }

        /* ── LEFT PANEL ── */
        .left {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 56px;
            overflow: hidden;
        }

        /* animated gradient background */
        .left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #1e3a5f 100%);
            z-index: 0;
        }

        /* decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.25;
            z-index: 0;
        }
        .blob-1 { width: 380px; height: 380px; background: #6366f1; top: -80px; left: -80px; }
        .blob-2 { width: 300px; height: 300px; background: #10b981; bottom: -60px; right: -40px; }
        .blob-3 { width: 200px; height: 200px; background: #f59e0b; top: 50%; left: 55%; }

        /* floating cards decoration */
        .float-card {
            position: absolute;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            padding: 16px 20px;
            z-index: 1;
            animation: floatUp 6s ease-in-out infinite;
        }
        .float-card-1 { top: 12%; right: 8%; animation-delay: 0s; }
        .float-card-2 { bottom: 18%; left: 6%; animation-delay: 2s; }
        .float-card-3 { top: 55%; right: 4%; animation-delay: 4s; }

        @keyframes floatUp {
            0%,100% { transform: translateY(0px); }
            50%      { transform: translateY(-12px); }
        }

        .float-card .fc-icon { font-size: 22px; margin-bottom: 6px; }
        .float-card .fc-val  { font-size: 22px; font-weight: 700; color: #fff; }
        .float-card .fc-lbl  { font-size: 11px; color: rgba(255,255,255,0.6); }

        /* grid dots background */
        .grid-dots {
            position: absolute;
            inset: 0;
            z-index: 0;
            background-image: radial-gradient(rgba(255,255,255,0.07) 1px, transparent 1px);
            background-size: 36px 36px;
        }

        .left-content {
            position: relative;
            z-index: 2;
            color: #fff;
            max-width: 480px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            padding: 8px 18px;
            margin-bottom: 32px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
        }
        .brand-badge i { color: #a5b4fc; }

        .left-content h1 {
            font-size: 46px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .left-content h1 span { color: #a5b4fc; }

        .left-content p {
            font-size: 16px;
            color: rgba(255,255,255,0.65);
            line-height: 1.75;
            margin-bottom: 40px;
        }

        .feature-list { display: flex; flex-direction: column; gap: 14px; }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 14px;
            color: rgba(255,255,255,0.85);
        }
        .feature-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .fi-purple { background: rgba(99,102,241,0.3); color: #a5b4fc; }
        .fi-green  { background: rgba(16,185,129,0.3); color: #6ee7b7; }
        .fi-yellow { background: rgba(245,158,11,0.3); color: #fcd34d; }
        .fi-red    { background: rgba(239,68,68,0.3);  color: #fca5a5; }
        .fi-blue   { background: rgba(59,130,246,0.3); color: #93c5fd; }

        .stats-row {
            display: flex;
            gap: 20px;
            margin-top: 44px;
            padding-top: 32px;
            border-top: 1px solid rgba(255,255,255,0.12);
        }
        .stat-item { text-align: center; }
        .stat-item .num { font-size: 26px; font-weight: 800; color: #fff; }
        .stat-item .lbl { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px; }

        /* ── RIGHT PANEL ── */
        .right {
            width: 500px;
            flex-shrink: 0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 52px;
            position: relative;
            overflow-y: auto;
        }

        .right::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, #4f46e5, #10b981, #f59e0b);
        }

        .form-box { width: 100%; }

        .form-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }
        .form-logo .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
        }
        .form-logo .logo-text h2 { font-size: 18px; font-weight: 700; color: #0f172a; }
        .form-logo .logo-text p  { font-size: 12px; color: #94a3b8; }

        .form-heading { margin-bottom: 6px; font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
        .form-sub { font-size: 14px; color: #64748b; margin-bottom: 32px; }

        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-label span { color: var(--danger); }

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: #4f46e5;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79,70,229,0.08);
        }
        .form-input::placeholder { color: #cbd5e1; }

        .pwd-toggle {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 15px;
            transition: color 0.2s;
        }
        .pwd-toggle:hover { color: #4f46e5; }

        .error-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13.5px;
            color: #dc2626;
            font-weight: 500;
        }

        .timeout-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13.5px;
            color: #d97706;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(79,70,229,0.35);
            margin-top: 4px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #3730a3, #4f46e5);
            box-shadow: 0 6px 20px rgba(79,70,229,0.45);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: #cbd5e1;
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .demo-cards { display: flex; flex-direction: column; gap: 8px; }
        .demo-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .demo-card:hover {
            border-color: #4f46e5;
            background: #f0f0ff;
            transform: translateX(3px);
        }
        .demo-role {
            width: 34px; height: 34px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .role-admin   { background: #fee2e2; color: #dc2626; }
        .role-warden  { background: #fef3c7; color: #d97706; }
        .role-student { background: #dbeafe; color: #2563eb; }

        .demo-info { flex: 1; }
        .demo-info strong { font-size: 13px; color: #0f172a; display: block; }
        .demo-info span   { font-size: 11px; color: #94a3b8; }

        .demo-arrow { color: #cbd5e1; font-size: 12px; }
        .demo-card:hover .demo-arrow { color: #4f46e5; }

        .form-footer {
            margin-top: 28px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .left { display: none; }
            .right { width: 100%; padding: 40px 28px; }
        }
    </style>
</head>
<body>

<!-- ═══ LEFT PANEL ═══ -->
<div class="left">
    <div class="grid-dots"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- Floating decoration cards -->
    <div class="float-card float-card-1">
        <div class="fc-icon">🏠</div>
        <div class="fc-val">248</div>
        <div class="fc-lbl">Rooms Managed</div>
    </div>
    <div class="float-card float-card-2">
        <div class="fc-icon">✅</div>
        <div class="fc-val">98%</div>
        <div class="fc-lbl">Fee Collection</div>
    </div>
    <div class="float-card float-card-3">
        <div class="fc-icon">⚡</div>
        <div class="fc-val">12</div>
        <div class="fc-lbl">Complaints Resolved</div>
    </div>

    <div class="left-content">
        <div class="brand-badge">
            <i class="fas fa-building"></i>
            Hostel Management System
        </div>

        <h1>Manage your<br><span>Hostel Smarter</span><br>& Faster</h1>

        <p>A complete digital platform for hostel administrators, wardens and students — all in one unified portal.</p>

        <div class="feature-list">
            <div class="feature-item">
                <div class="feature-icon fi-purple"><i class="fas fa-bed"></i></div>
                <span>Room Allotment & Occupancy Tracking</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon fi-green"><i class="fas fa-rupee-sign"></i></div>
                <span>Fee Collection, Receipts & Overdue Alerts</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon fi-yellow"><i class="fas fa-tools"></i></div>
                <span>Complaint Management & Resolution</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon fi-red"><i class="fas fa-bullhorn"></i></div>
                <span>Notice Board & Real-time Notifications</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon fi-blue"><i class="fas fa-chart-bar"></i></div>
                <span>Analytics, Reports & Audit Trail</span>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-item"><div class="num">13+</div><div class="lbl">DB Tables</div></div>
            <div class="stat-item"><div class="num">3</div><div class="lbl">User Roles</div></div>
            <div class="stat-item"><div class="num">10+</div><div class="lbl">Modules</div></div>
            <div class="stat-item"><div class="num">100%</div><div class="lbl">PHP + MySQL</div></div>
        </div>
    </div>
</div>

<!-- ═══ RIGHT PANEL ═══ -->
<div class="right">
    <div class="form-box">

        <div class="form-logo">
            <div class="logo-icon"><i class="fas fa-building"></i></div>
            <div class="logo-text">
                <h2>HMS Portal</h2>
                <p>Hostel Management System</p>
            </div>
        </div>

        <h2 class="form-heading">Welcome back 👋</h2>
        <p class="form-sub">Sign in to access your hostel dashboard</p>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="timeout-box"><i class="fas fa-clock"></i> Session expired. Please sign in again.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label class="form-label">Email Address <span>*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" id="emailInput" class="form-input"
                           placeholder="Enter your email address"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password <span>*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="pwdInput" class="form-input"
                           placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="pwd-toggle" onclick="togglePwd()">
                        <i class="fas fa-eye" id="pwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In to Dashboard
            </button>
        </form>

        <div class="divider">Quick Login — Demo Accounts</div>

        <div class="demo-cards">
            <div class="demo-card" onclick="fillDemo('admin@hostel.com','password')">
                <div class="demo-role role-admin"><i class="fas fa-user-shield"></i></div>
                <div class="demo-info">
                    <strong>Super Admin</strong>
                    <span>admin@hostel.com &nbsp;·&nbsp; Full Access</span>
                </div>
                <i class="fas fa-arrow-right demo-arrow"></i>
            </div>
            <div class="demo-card" onclick="fillDemo('warden@hostel.com','password')">
                <div class="demo-role role-warden"><i class="fas fa-user-tie"></i></div>
                <div class="demo-info">
                    <strong>Warden</strong>
                    <span>warden@hostel.com &nbsp;·&nbsp; Management Access</span>
                </div>
                <i class="fas fa-arrow-right demo-arrow"></i>
            </div>
            <div class="demo-card" onclick="fillDemo('student@hostel.com','password')">
                <div class="demo-role role-student"><i class="fas fa-user-graduate"></i></div>
                <div class="demo-info">
                    <strong>Student</strong>
                    <span>student@hostel.com &nbsp;·&nbsp; Student Portal</span>
                </div>
                <i class="fas fa-arrow-right demo-arrow"></i>
            </div>
        </div>

        <div class="form-footer">
            © <?= date('Y') ?> Hostel Management System &nbsp;·&nbsp; All rights reserved
        </div>
    </div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('pwdInput');
    const icon  = document.getElementById('pwdIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function fillDemo(email, password) {
    document.getElementById('emailInput').value = email;
    document.getElementById('pwdInput').value   = password;
    document.getElementById('pwdInput').type    = 'text';
    document.getElementById('pwdIcon').className = 'fas fa-eye-slash';
    // Auto-submit after short delay
    setTimeout(() => document.getElementById('loginForm').submit(), 300);
}
</script>
</body>
</html>
