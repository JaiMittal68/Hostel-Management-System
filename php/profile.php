<?php
require_once '../includes/config.php';
$pageTitle = 'My Profile';
$activePage = 'profile';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);

        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $photo = uploadFile($_FILES['photo'], 'students') ?: '';
        }

        if ($photo) {
            $stmt = $db->prepare("UPDATE users SET name=?,phone=?,profile_photo=? WHERE id=?");
            $stmt->bind_param('sssi', $name,$phone,$photo,$_SESSION['user_id']);
            $_SESSION['photo'] = $photo;
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?,phone=? WHERE id=?");
            $stmt->bind_param('ssi', $name,$phone,$_SESSION['user_id']);
        }
        $stmt->execute();
        $_SESSION['name'] = $name;

        flashMessage('success', 'Profile updated successfully.');
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm  = $_POST['confirm_password'];

        $user = $db->query("SELECT password FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

        if (!password_verify($current, $user['password'])) {
            flashMessage('error', 'Current password is incorrect.');
        } elseif ($new_pass !== $confirm) {
            flashMessage('error', 'New passwords do not match.');
        } elseif (strlen($new_pass) < 6) {
            flashMessage('error', 'Password must be at least 6 characters.');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, $_SESSION['user_id']);
            $stmt->execute();
            flashMessage('success', 'Password changed successfully.');
        }
    }

    redirect(SITE_URL . 'php/profile.php');
}

$user = $db->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

// If student, get extra info
$studentInfo = null;
if ($user['role'] === 'student') {
    $studentInfo = $db->query("
        SELECT s.*, r.room_number, r.room_type, r.monthly_rent
        FROM students s
        LEFT JOIN room_allotments ra ON ra.student_id=s.id AND ra.status='active'
        LEFT JOIN rooms r ON r.id=ra.room_id
        WHERE s.user_id={$_SESSION['user_id']}
    ")->fetch_assoc();
}

// Recent activity
$activity = $db->query("
    SELECT * FROM activity_log WHERE user_id={$_SESSION['user_id']}
    ORDER BY created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>My Profile</h2><p>Manage your account settings</p></div>
</div>

<div class="dashboard-grid">
    <!-- Left: Profile Card -->
    <div>
        <!-- Profile Info Card -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-body" style="text-align:center;padding:32px">
                <div style="margin-bottom:16px">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= UPLOAD_URL.$user['profile_photo'] ?>" alt="Profile"
                             style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid var(--primary-light)">
                    <?php else: ?>
                        <div style="width:100px;height:100px;border-radius:50%;background:var(--primary);
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:36px;font-weight:700;color:white;margin:0 auto;
                                    border:4px solid var(--primary-light)">
                            <?= strtoupper(substr($user['name'],0,2)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 style="font-size:20px;font-weight:700"><?= htmlspecialchars($user['name']) ?></h3>
                <p style="color:var(--gray);margin-top:4px"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge badge-<?= $user['role']==='admin'?'danger':($user['role']==='warden'?'warning':'primary') ?>" style="margin-top:8px;font-size:12px">
                    <?= ucfirst($user['role']) ?>
                </span>

                <?php if ($studentInfo): ?>
                <div style="margin-top:20px;padding:16px;background:var(--light-gray);border-radius:8px;text-align:left">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
                        <div><span class="text-muted">Student ID</span><br><strong><?= $studentInfo['student_id'] ?></strong></div>
                        <div><span class="text-muted">Room</span><br><strong><?= $studentInfo['room_number'] ? 'Room '.$studentInfo['room_number'] : 'Not allotted' ?></strong></div>
                        <div><span class="text-muted">Course</span><br><strong><?= htmlspecialchars($studentInfo['course'] ?? '—') ?></strong></div>
                        <div><span class="text-muted">Year</span><br><strong>Year <?= $studentInfo['year_of_study'] ?? '—' ?></strong></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Profile -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><h3><i class="fas fa-user-edit" style="color:var(--primary)"></i> Edit Profile</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <div class="form-hint">JPG, PNG accepted. Max 5MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-lock" style="color:var(--warning)"></i> Change Password</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width:100%;justify-content:center">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Activity Log -->
    <div>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-history" style="color:var(--info)"></i> Recent Activity</h3></div>
            <div class="card-body" style="padding:0">
                <?php if (empty($activity)): ?>
                    <div class="empty-state"><i class="fas fa-history"></i><p>No activity yet</p></div>
                <?php else: ?>
                    <?php foreach ($activity as $a): ?>
                    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-light);
                                    display:flex;align-items:center;justify-content:center;
                                    color:var(--primary);font-size:14px;flex-shrink:0">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($a['action']) ?></div>
                            <div class="text-muted"><?= htmlspecialchars($a['module']) ?> — <?= htmlspecialchars(substr($a['description'],0,60)) ?></div>
                            <div class="text-muted" style="margin-top:2px"><?= formatDateTime($a['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
