<?php
require_once '../includes/config.php';
$pageTitle = 'Complaints';
$activePage = 'complaints';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // Students add their own; admin/warden can add for any
        $student_id  = isStudent() ? $_SESSION['student_id'] : (int)$_POST['student_id'];
        $category    = sanitize($_POST['category']);
        $subject     = sanitize($_POST['subject']);
        $description = sanitize($_POST['description']);
        $priority    = sanitize($_POST['priority']);
        $cno         = generateComplaintNumber();

        $stmt = $db->prepare("INSERT INTO complaints (student_id,complaint_number,category,subject,description,priority,status) VALUES (?,?,?,?,?,?,'open')");
        $stmt->bind_param('isssss', $student_id,$cno,$category,$subject,$description,$priority);
        $stmt->execute();

        // Notify admin/wardens
        $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','warden') AND is_active=1")->fetch_all(MYSQLI_ASSOC);
        foreach ($admins as $a) {
            sendNotification($a['id'], 'New Complaint', "A new $priority complaint has been filed: $subject", 'complaint_update', 'complaints.php');
        }

        logActivity($_SESSION['user_id'], 'ADD_COMPLAINT', 'Complaints', $cno);
        flashMessage('success', "Complaint filed. Reference: $cno");
    }

    if ($action === 'update' && isWarden()) {
        $id          = (int)$_POST['id'];
        $status      = sanitize($_POST['status']);
        $assigned_to = (int)$_POST['assigned_to'];
        $resolution  = sanitize($_POST['resolution_note']);
        $resolved_at = in_array($status, ['resolved','closed']) ? 'NOW()' : 'NULL';

        $stmt = $db->prepare("UPDATE complaints SET status=?,assigned_to=?,resolution_note=?,resolved_at=$resolved_at WHERE id=?");
        $stmt->bind_param('sisi', $status,$assigned_to,$resolution,$id);
        $stmt->execute();

        // Notify student
        $c = $db->query("SELECT student_id FROM complaints WHERE id=$id")->fetch_assoc();
        $uid = $db->query("SELECT user_id FROM students WHERE id={$c['student_id']}")->fetch_row()[0] ?? null;
        if ($uid) sendNotification($uid, 'Complaint Update', "Your complaint status changed to: $status", 'complaint_update', 'complaints.php');

        flashMessage('success', 'Complaint updated successfully.');
    }

    redirect(SITE_URL . 'php/complaints.php');
}

// Get student ID for student role
$student_id_filter = '';
if (isStudent()) {
    $sid_res = $db->query("SELECT id FROM students WHERE user_id={$_SESSION['user_id']}")->fetch_row();
    $student_id_filter = $sid_res ? " AND c.student_id={$sid_res[0]}" : " AND 1=0";
}

$status_filter = sanitize($_GET['status'] ?? '');
$priority_filter = sanitize($_GET['priority'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1 $student_id_filter";
if ($status_filter)   $where .= " AND c.status='$status_filter'";
if ($priority_filter) $where .= " AND c.priority='$priority_filter'";

$total = $db->query("SELECT COUNT(*) FROM complaints c $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$complaints = $db->query("
    SELECT c.*, s.name AS student_name, s.student_id AS sid, u.name AS assigned_name
    FROM complaints c
    JOIN students s ON c.student_id=s.id
    LEFT JOIN users u ON c.assigned_to=u.id
    $where ORDER BY c.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

$students = $db->query("SELECT id,name,student_id FROM students WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$staff    = $db->query("SELECT id,name FROM users WHERE role IN ('admin','warden') AND is_active=1")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Complaints</h2><p>Track and resolve student complaints</p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> File Complaint</button>
</div>

<!-- Quick Stats -->
<?php
$stats = $db->query("SELECT status, COUNT(*) AS cnt FROM complaints GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$stat_map = array_column($stats, 'cnt', 'status');
?>
<div class="stats-grid" style="margin-bottom:20px">
    <?php $config = [
        'open' => ['red','fa-circle-exclamation','Open'],
        'in_progress' => ['yellow','fa-spinner','In Progress'],
        'resolved' => ['green','fa-check-circle','Resolved'],
        'closed' => ['gray','fa-times-circle','Closed'],
    ]; foreach ($config as $k => [$color, $icon, $label]): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="fas <?= $icon ?>"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stat_map[$k] ?? 0 ?></div>
            <div class="stat-label"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
            <select name="status" class="form-control" style="width:140px">
                <option value="">All Status</option>
                <?php foreach (['open','in_progress','resolved','closed','rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority" class="form-control" style="width:130px">
                <option value="">All Priority</option>
                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                    <option value="<?= $p ?>" <?= $priority_filter===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="complaints.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> record(s)</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Reference</th><th>Student</th><th>Category</th>
                <th>Subject</th><th>Priority</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($complaints as $c): ?>
                <tr>
                    <td><span class="badge badge-gray"><?= $c['complaint_number'] ?></span></td>
                    <td><?= htmlspecialchars($c['student_name']) ?><br><span class="text-muted"><?= $c['sid'] ?></span></td>
                    <td><?= ucfirst($c['category']) ?></td>
                    <td>
                        <span title="<?= htmlspecialchars($c['description']) ?>"><?= htmlspecialchars(substr($c['subject'],0,35)) ?>...</span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $c['priority']==='urgent'?'danger':($c['priority']==='high'?'warning':($c['priority']==='medium'?'info':'gray')) ?>">
                            <?= ucfirst($c['priority']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $c['status']==='resolved'?'success':($c['status']==='open'?'danger':($c['status']==='in_progress'?'warning':'gray')) ?>">
                            <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
                        </span>
                    </td>
                    <td><?= formatDate($c['created_at']) ?></td>
                    <td>
                        <div class="gap-8">
                            <button class="btn btn-sm btn-light btn-icon" title="View Details"
                                onclick="viewComplaint(<?= htmlspecialchars(json_encode($c)) ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if (isWarden() && $c['status'] !== 'closed'): ?>
                            <button class="btn btn-sm btn-primary btn-icon" title="Update"
                                onclick="updateComplaint(<?= htmlspecialchars(json_encode($c)) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($complaints)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-smile"></i><p>No complaints found!</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
                <a href="?status=<?= $status_filter ?>&priority=<?= $priority_filter ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h3>File a Complaint</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
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
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Category <span>*</span></label>
                        <select name="category" class="form-control" required>
                            <?php foreach (['plumbing','electrical','furniture','cleanliness','security','internet','food','other'] as $cat): ?>
                                <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject <span>*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="Brief description" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Detailed Description <span>*</span></label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Describe the issue in detail..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Complaint</button>
            </div>
        </form>
    </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header"><h3>Complaint Details</h3><button class="modal-close" onclick="closeModal('viewModal')">×</button></div>
        <div class="modal-body" id="viewModalBody"></div>
        <div class="modal-footer"><button class="btn btn-light" onclick="closeModal('viewModal')">Close</button></div>
    </div>
</div>

<!-- Update Modal -->
<div class="modal-overlay" id="updateModal">
    <div class="modal">
        <div class="modal-header"><h3>Update Complaint</h3><button class="modal-close" onclick="closeModal('updateModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="update_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="update_status" class="form-control">
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign To</label>
                        <select name="assigned_to" id="update_assigned" class="form-control">
                            <option value="0">Unassigned</option>
                            <?php foreach ($staff as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Resolution Note</label>
                    <textarea name="resolution_note" id="update_resolution" class="form-control" rows="3" placeholder="Action taken or reason for closure..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('updateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewComplaint(c) {
    document.getElementById('viewModalBody').innerHTML = `
        <table style="width:100%;font-size:13.5px">
            <tr><td style="padding:8px;width:40%;font-weight:500;color:var(--gray)">Reference</td><td>${c.complaint_number}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Student</td><td>${c.student_name} (${c.sid})</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Category</td><td>${c.category}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Subject</td><td>${c.subject}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Priority</td><td>${c.priority}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Status</td><td>${c.status}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Description</td><td>${c.description}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Assigned To</td><td>${c.assigned_name || '-'}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Resolution</td><td>${c.resolution_note || '-'}</td></tr>
            <tr><td style="padding:8px;font-weight:500;color:var(--gray)">Filed On</td><td>${c.created_at}</td></tr>
        </table>`;
    openModal('viewModal');
}
function updateComplaint(c) {
    document.getElementById('update_id').value = c.id;
    document.getElementById('update_status').value = c.status;
    document.getElementById('update_assigned').value = c.assigned_to || 0;
    document.getElementById('update_resolution').value = c.resolution_note || '';
    openModal('updateModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
