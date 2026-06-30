<?php
require_once '../includes/config.php';
$pageTitle = 'Gate Pass';
$activePage = 'gate_pass';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply') {
        $student_id = isStudent()
            ? $db->query("SELECT id FROM students WHERE user_id={$_SESSION['user_id']}")->fetch_row()[0]
            : (int)$_POST['student_id'];
        $reason          = sanitize($_POST['reason']);
        $out_date        = sanitize($_POST['out_date']);
        $out_time        = sanitize($_POST['out_time']);
        $expected_return = sanitize($_POST['expected_return']);

        $stmt = $db->prepare("INSERT INTO gate_passes (student_id,reason,out_date,out_time,expected_return,status) VALUES (?,?,?,?,?,'pending')");
        $stmt->bind_param('issss', $student_id,$reason,$out_date,$out_time,$expected_return);
        $stmt->execute();

        $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','warden') AND is_active=1")->fetch_all(MYSQLI_ASSOC);
        foreach ($admins as $a) {
            sendNotification($a['id'], 'Gate Pass Request', "New gate pass request submitted.", 'general', 'gate_pass.php');
        }

        logActivity($_SESSION['user_id'], 'GATE_PASS_APPLY', 'GatePass', "Student $student_id applied");
        flashMessage('success', 'Gate pass application submitted.');
    }

    if ($action === 'approve' && isWarden()) {
        $id     = (int)$_POST['id'];
        $status = sanitize($_POST['status']);
        $stmt   = $db->prepare("UPDATE gate_passes SET status=?,approved_by=? WHERE id=?");
        $stmt->bind_param('sii', $status,$_SESSION['user_id'],$id);
        $stmt->execute();

        $gp  = $db->query("SELECT student_id FROM gate_passes WHERE id=$id")->fetch_assoc();
        $uid = $db->query("SELECT user_id FROM students WHERE id={$gp['student_id']}")->fetch_row()[0] ?? null;
        if ($uid) sendNotification($uid, 'Gate Pass '.ucfirst($status), "Your gate pass has been $status.", 'general', 'gate_pass.php');

        flashMessage('success', "Gate pass $status.");
    }

    if ($action === 'return') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE gate_passes SET actual_return=NOW(), status='returned' WHERE id=$id");
        flashMessage('success', 'Return recorded.');
    }

    redirect(SITE_URL . 'php/gate_pass.php');
}

$student_id_filter = '';
if (isStudent()) {
    $sid = $db->query("SELECT id FROM students WHERE user_id={$_SESSION['user_id']}")->fetch_row();
    $student_id_filter = $sid ? " AND gp.student_id={$sid[0]}" : " AND 1=0";
}

$status_filter = sanitize($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));

$where = "WHERE 1=1 $student_id_filter";
if ($status_filter) $where .= " AND gp.status='$status_filter'";

$total = $db->query("SELECT COUNT(*) FROM gate_passes gp $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$passes = $db->query("
    SELECT gp.*, s.name AS student_name, s.student_id AS sid,
           u.name AS approved_by_name
    FROM gate_passes gp
    JOIN students s ON gp.student_id=s.id
    LEFT JOIN users u ON gp.approved_by=u.id
    $where ORDER BY gp.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

$students = $db->query("SELECT id,name,student_id FROM students WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Gate Pass</h2><p>Manage student gate passes and outings</p></div>
    <button class="btn btn-primary" onclick="openModal('applyModal')"><i class="fas fa-plus"></i>
        <?= isStudent() ? 'Apply for Gate Pass' : 'New Gate Pass' ?>
    </button>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px">
            <select name="status" class="form-control" style="width:150px">
                <option value="">All Status</option>
                <?php foreach (['pending','approved','rejected','returned'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="gate_pass.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Student</th><th>Reason</th><th>Out Date & Time</th>
                <th>Expected Return</th><th>Actual Return</th>
                <th>Status</th><th>Approved By</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($passes as $gp): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($gp['student_name']) ?></div>
                        <div class="text-muted"><?= $gp['sid'] ?></div>
                    </td>
                    <td style="max-width:150px"><?= htmlspecialchars(substr($gp['reason'],0,60)) ?></td>
                    <td><?= formatDate($gp['out_date']) ?><br><span class="text-muted"><?= $gp['out_time'] ?></span></td>
                    <td><?= formatDate($gp['expected_return']) ?></td>
                    <td><?= $gp['actual_return'] ? formatDateTime($gp['actual_return']) : '—' ?></td>
                    <td>
                        <span class="badge badge-<?= $gp['status']==='approved'?'success':($gp['status']==='rejected'?'danger':($gp['status']==='returned'?'info':'warning')) ?>">
                            <?= ucfirst($gp['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($gp['approved_by_name'] ?? '—') ?></td>
                    <td>
                        <div class="gap-8">
                            <?php if ($gp['status'] === 'pending' && isWarden()): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $gp['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $gp['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                            </form>
                            <?php elseif ($gp['status'] === 'approved' && isWarden()): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="id" value="<?= $gp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-sign-in-alt"></i> Mark Return</button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($passes)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-id-card"></i><p>No gate passes found</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
                <a href="?status=<?= $status_filter ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Apply Gate Pass Modal -->
<div class="modal-overlay" id="applyModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-id-card"></i> Apply for Gate Pass</h3><button class="modal-close" onclick="closeModal('applyModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="apply">
            <div class="modal-body">
                <?php if (isWarden()): ?>
                <div class="form-group">
                    <label class="form-label">Student <span>*</span></label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['student_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Reason for Going Out <span>*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Purpose of outing..."></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Out Date <span>*</span></label>
                        <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Out Time <span>*</span></label>
                        <input type="time" name="out_time" class="form-control" required>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Expected Return Date <span>*</span></label>
                        <input type="date" name="expected_return" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('applyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
