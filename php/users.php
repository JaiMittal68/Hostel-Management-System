<?php
require_once '../includes/config.php';
$pageTitle = 'User Management';
$activePage = 'users';
requireRole(['admin']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = sanitize($_POST['name']);
        $email    = sanitize($_POST['email']);
        $role     = sanitize($_POST['role']);
        $phone    = sanitize($_POST['phone']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Check email unique
        $exists = $db->query("SELECT id FROM users WHERE email='$email'")->fetch_row();
        if ($exists) {
            flashMessage('error', 'Email already exists.');
        } else {
            $stmt = $db->prepare("INSERT INTO users (name,email,password,role,phone,is_active) VALUES (?,?,?,?,?,1)");
            $stmt->bind_param('sssss', $name,$email,$password,$role,$phone);
            $stmt->execute();
            logActivity($_SESSION['user_id'], 'ADD_USER', 'Users', "Added user: $email ($role)");
            flashMessage('success', "User '$name' created successfully.");
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        if ($id != $_SESSION['user_id']) {
            $db->query("UPDATE users SET is_active = NOT is_active WHERE id=$id");
            flashMessage('success', 'User status updated.');
        }
    }

    if ($action === 'reset_password') {
        $id          = (int)$_POST['id'];
        $new_password= password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $new_password, $id);
        $stmt->execute();
        flashMessage('success', 'Password reset successfully.');
    }

    redirect(SITE_URL . 'php/users.php');
}

$role_filter = sanitize($_GET['role'] ?? '');
$page        = max(1,(int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($role_filter) $where .= " AND role='$role_filter'";

$total = $db->query("SELECT COUNT(*) FROM users $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$users = $db->query("
    SELECT u.*, 
        (SELECT COUNT(*) FROM activity_log al WHERE al.user_id=u.id) AS activity_count
    FROM users u $where
    ORDER BY u.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>User Management</h2><p>Manage system users and access control</p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add User</button>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px">
            <select name="role" class="form-control" style="width:150px">
                <option value="">All Roles</option>
                <option value="admin"   <?= $role_filter==='admin'?'selected':'' ?>>Admin</option>
                <option value="warden"  <?= $role_filter==='warden'?'selected':'' ?>>Warden</option>
                <option value="student" <?= $role_filter==='student'?'selected':'' ?>>Student</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="users.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> users</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>User</th><th>Role</th><th>Phone</th>
                <th>Activity Count</th><th>Created</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="table-avatar">
                            <div class="avatar-sm"><?= strtoupper(substr($u['name'],0,2)) ?></div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-muted"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $u['role']==='admin'?'danger':($u['role']==='warden'?'warning':'info') ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td><?= $u['phone'] ?: '—' ?></td>
                    <td><?= $u['activity_count'] ?></td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td>
                        <span class="badge badge-<?= $u['is_active']?'success':'danger' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="gap-8">
                            <button class="btn btn-sm btn-light" onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">
                                <i class="fas fa-key"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-<?= $u['is_active']?'warning':'success' ?>" title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                                    <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1;$i<=$pag['totalPages'];$i++): ?>
                <a href="?role=<?= $role_filter ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-user-plus"></i> Add New User</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span>*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span>*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role <span>*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="warden">Warden</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" maxlength="15">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Password <span>*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-key"></i> Reset Password</h3><button class="modal-close" onclick="closeModal('resetModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="reset_user_id">
            <div class="modal-body">
                <p id="reset_user_name" style="margin-bottom:16px;font-weight:600;color:var(--primary)"></p>
                <div class="form-group">
                    <label class="form-label">New Password <span>*</span></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('resetModal')">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = 'Resetting password for: ' + name;
    openModal('resetModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
