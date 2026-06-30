<?php
require_once '../includes/config.php';
$pageTitle = 'Visitor Management';
$activePage = 'visitors';
requireRole(['admin','warden']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $student_id      = (int)$_POST['student_id'];
        $visitor_name    = sanitize($_POST['visitor_name']);
        $visitor_phone   = sanitize($_POST['visitor_phone']);
        $relation        = sanitize($_POST['relation']);
        $purpose         = sanitize($_POST['purpose']);
        $id_proof_type   = sanitize($_POST['id_proof_type']);
        $id_proof_number = sanitize($_POST['id_proof_number']);
        $check_in        = sanitize($_POST['check_in']);

        $stmt = $db->prepare("INSERT INTO visitors (student_id,visitor_name,visitor_phone,relation,purpose,id_proof_type,id_proof_number,check_in,status,approved_by) VALUES (?,?,?,?,?,?,?,?,'approved',?)");
        $stmt->bind_param('isssssssi', $student_id,$visitor_name,$visitor_phone,$relation,$purpose,$id_proof_type,$id_proof_number,$check_in,$_SESSION['user_id']);
        $stmt->execute();

        logActivity($_SESSION['user_id'], 'ADD_VISITOR', 'Visitors', "Visitor: $visitor_name for student $student_id");
        flashMessage('success', "Visitor '$visitor_name' logged in successfully.");
    }

    if ($action === 'checkout') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE visitors SET check_out=NOW(), status='checked_out' WHERE id=$id");
        flashMessage('success', 'Visitor checked out successfully.');
    }

    redirect(SITE_URL . 'php/visitors.php');
}

$date_filter = sanitize($_GET['date'] ?? date('Y-m-d'));
$page        = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE DATE(v.check_in)='$date_filter'";
$total = $db->query("SELECT COUNT(*) FROM visitors v $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$visitors = $db->query("
    SELECT v.*, s.name AS student_name, s.student_id AS sid, u.name AS approved_by_name
    FROM visitors v
    JOIN students s ON v.student_id=s.id
    LEFT JOIN users u ON v.approved_by=u.id
    $where ORDER BY v.check_in DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

$students = $db->query("SELECT id,name,student_id FROM students WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Today's stats
$todayTotal    = $db->query("SELECT COUNT(*) FROM visitors WHERE DATE(check_in)=CURDATE()")->fetch_row()[0];
$todayInside   = $db->query("SELECT COUNT(*) FROM visitors WHERE DATE(check_in)=CURDATE() AND status='approved'")->fetch_row()[0];
$todayCheckout = $db->query("SELECT COUNT(*) FROM visitors WHERE DATE(check_in)=CURDATE() AND status='checked_out'")->fetch_row()[0];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Visitor Management</h2><p>Log and track hostel visitors</p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Log Visitor</button>
</div>

<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-body"><div class="stat-value"><?= $todayTotal ?></div><div class="stat-label">Today's Total</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-sign-in-alt"></i></div><div class="stat-body"><div class="stat-value"><?= $todayInside ?></div><div class="stat-label">Currently Inside</div></div></div>
    <div class="stat-card"><div class="stat-icon gray"><i class="fas fa-sign-out-alt"></i></div><div class="stat-body"><div class="stat-value"><?= $todayCheckout ?></div><div class="stat-label">Checked Out</div></div></div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex:1">
            <div class="form-group" style="margin:0">
                <input type="date" name="date" class="form-control" value="<?= $date_filter ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="visitors.php" class="btn btn-light">Today</a>
        </form>
        <span class="text-muted"><?= $total ?> records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Visitor Name</th><th>Phone</th><th>Visiting Student</th>
                <th>Relation</th><th>Purpose</th><th>ID Proof</th>
                <th>Check In</th><th>Check Out</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($visitors as $v): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($v['visitor_name']) ?></td>
                    <td><?= $v['visitor_phone'] ?></td>
                    <td><?= htmlspecialchars($v['student_name']) ?><br><span class="text-muted"><?= $v['sid'] ?></span></td>
                    <td><?= ucfirst($v['relation']) ?></td>
                    <td style="max-width:120px;font-size:12px"><?= htmlspecialchars(substr($v['purpose'],0,50)) ?></td>
                    <td style="font-size:12px"><?= $v['id_proof_type'] ?><br><?= $v['id_proof_number'] ?></td>
                    <td><?= formatDateTime($v['check_in']) ?></td>
                    <td><?= $v['check_out'] ? formatDateTime($v['check_out']) : '—' ?></td>
                    <td>
                        <span class="badge badge-<?= $v['status']==='checked_out'?'gray':'success' ?>">
                            <?= $v['status']==='checked_out' ? 'Checked Out' : 'Inside' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($v['status'] === 'approved'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-sign-out-alt"></i> Check Out</button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($visitors)): ?>
                <tr><td colspan="10"><div class="empty-state"><i class="fas fa-user-friends"></i><p>No visitors for this date</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1;$i<=$pag['totalPages'];$i++): ?>
                <a href="?date=<?= $date_filter ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Visitor Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3><i class="fas fa-user-plus"></i> Log New Visitor</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Visiting Student <span>*</span></label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['student_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Visitor Name <span>*</span></label>
                        <input type="text" name="visitor_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="visitor_phone" class="form-control" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relation</label>
                        <select name="relation" class="form-control">
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="guardian">Guardian</option>
                            <option value="friend">Friend</option>
                            <option value="relative">Relative</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Check-In Time <span>*</span></label>
                        <input type="datetime-local" name="check_in" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ID Proof Type</label>
                        <select name="id_proof_type" class="form-control">
                            <option value="Aadhar">Aadhar Card</option>
                            <option value="PAN">PAN Card</option>
                            <option value="Voter ID">Voter ID</option>
                            <option value="Passport">Passport</option>
                            <option value="Driving License">Driving License</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ID Proof Number</label>
                        <input type="text" name="id_proof_number" class="form-control" placeholder="Document number">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Purpose of Visit</label>
                        <textarea name="purpose" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Log Visitor</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
