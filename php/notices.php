<?php
require_once '../includes/config.php';
$pageTitle = 'Notice Board';
$activePage = 'notices';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isWarden()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = sanitize($_POST['title']);
        $content     = sanitize($_POST['content']);
        $target      = sanitize($_POST['target']);
        $is_urgent   = isset($_POST['is_urgent']) ? 1 : 0;
        $expiry_date = sanitize($_POST['expiry_date']);

        $attachment = '';
        if (!empty($_FILES['attachment']['name'])) {
            $attachment = uploadFile($_FILES['attachment'], 'notices') ?: '';
        }

        $stmt = $db->prepare("INSERT INTO notices (title,content,target,is_urgent,expiry_date,attachment,posted_by,is_active) VALUES (?,?,?,?,?,?,?,1)");
        $stmt->bind_param('sssissi', $title,$content,$target,$is_urgent,$expiry_date,$attachment,$_SESSION['user_id']);
        $stmt->execute();

        // Notify all students
        $students = $db->query("SELECT user_id FROM students WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
        foreach ($students as $s) {
            sendNotification($s['user_id'], 'New Notice: '.$title, substr($content,0,100), 'notice', 'notices.php');
        }

        logActivity($_SESSION['user_id'], 'POST_NOTICE', 'Notices', $title);
        flashMessage('success', 'Notice posted successfully.');
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE notices SET is_active = NOT is_active WHERE id=$id");
        flashMessage('success', 'Notice status updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM notices WHERE id=$id");
        flashMessage('success', 'Notice deleted.');
    }

    redirect(SITE_URL . 'php/notices.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$show_inactive = isset($_GET['show_inactive']);

$where = $show_inactive ? "WHERE 1=1" : "WHERE n.is_active=1";

$total = $db->query("SELECT COUNT(*) FROM notices n $where")->fetch_row()[0];
$pag   = paginate($total, 12, $page);

$notices = $db->query("
    SELECT n.*, u.name AS posted_by_name
    FROM notices n JOIN users u ON n.posted_by=u.id
    $where ORDER BY n.is_urgent DESC, n.created_at DESC
    LIMIT 12 OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Notice Board</h2><p>View and manage hostel notices</p></div>
    <div class="gap-8">
        <a href="?<?= $show_inactive ? '' : 'show_inactive=1' ?>" class="btn btn-light">
            <i class="fas fa-eye<?= $show_inactive ? '-slash' : '' ?>"></i> <?= $show_inactive ? 'Active Only' : 'Show All' ?>
        </a>
        <?php if (isWarden()): ?>
        <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Post Notice</button>
        <?php endif; ?>
    </div>
</div>

<!-- Notice Cards Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
    <?php foreach ($notices as $n): ?>
    <div class="card" style="border-left:4px solid <?= $n['is_urgent'] ? 'var(--danger)' : 'var(--primary)' ?>">
        <div class="card-body">
            <div class="flex-between mb-16" style="align-items:flex-start">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                        <?php if ($n['is_urgent']): ?>
                            <span class="badge badge-danger"><i class="fas fa-exclamation"></i> URGENT</span>
                        <?php endif; ?>
                        <?php if (!$n['is_active']): ?>
                            <span class="badge badge-gray">Inactive</span>
                        <?php endif; ?>
                        <span class="badge badge-primary"><?= ucfirst($n['target']) ?></span>
                    </div>
                    <h3 style="font-size:15px;font-weight:600"><?= htmlspecialchars($n['title']) ?></h3>
                </div>
                <?php if (isWarden()): ?>
                <div class="gap-8">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $n['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light btn-icon" title="<?= $n['is_active']?'Deactivate':'Activate' ?>">
                            <i class="fas <?= $n['is_active']?'fa-eye-slash':'fa-eye' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this notice?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $n['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <p style="font-size:13.5px;color:var(--gray);line-height:1.6;margin-bottom:12px">
                <?= nl2br(htmlspecialchars(substr($n['content'], 0, 200))) ?><?= strlen($n['content'])>200?'...':'' ?>
            </p>

            <?php if ($n['attachment']): ?>
            <a href="<?= UPLOAD_URL.$n['attachment'] ?>" target="_blank" class="btn btn-sm btn-light" style="margin-bottom:10px">
                <i class="fas fa-paperclip"></i> Attachment
            </a>
            <?php endif; ?>

            <div style="border-top:1px solid var(--border);padding-top:10px;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <span style="font-size:12px;color:var(--gray)"><i class="fas fa-user"></i> <?= htmlspecialchars($n['posted_by_name']) ?></span>
                </div>
                <div style="text-align:right">
                    <div class="text-muted"><?= formatDate($n['created_at']) ?></div>
                    <?php if ($n['expiry_date']): ?>
                        <div class="text-muted" style="color:<?= strtotime($n['expiry_date']) < time() ? 'var(--danger)':'' ?>">
                            Expires: <?= formatDate($n['expiry_date']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notices)): ?>
    <div style="grid-column:1/-1">
        <div class="card">
            <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No notices at the moment</p></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($pag['totalPages'] > 1): ?>
<div style="margin-top:20px">
    <div class="pagination">
        <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?><?= $show_inactive?'&show_inactive=1':'' ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<!-- Post Notice Modal -->
<?php if (isWarden()): ?>
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3><i class="fas fa-bullhorn"></i> Post New Notice</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title <span>*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Notice title" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Target Audience</label>
                        <select name="target" class="form-control">
                            <option value="all">All</option>
                            <option value="students">Students Only</option>
                            <option value="wardens">Wardens Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Content <span>*</span></label>
                    <textarea name="content" class="form-control" rows="6" placeholder="Notice content..." required></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Attachment (PDF/Image)</label>
                        <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;padding-top:24px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                            <input type="checkbox" name="is_urgent" style="width:16px;height:16px">
                            <span style="font-weight:500">Mark as Urgent</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Notice</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
