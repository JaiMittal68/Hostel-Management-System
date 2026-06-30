<?php
require_once '../includes/config.php';
$pageTitle = 'Room Allotments';
$activePage = 'allotments';
requireRole(['admin','warden']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'allot') {
        $student_id    = (int)$_POST['student_id'];
        $room_id       = (int)$_POST['room_id'];
        $allotment_date= sanitize($_POST['allotment_date']);
        $remarks       = sanitize($_POST['remarks']);

        // Check if student already has room
        $existing = $db->query("SELECT id FROM room_allotments WHERE student_id=$student_id AND status='active'")->fetch_row();
        if ($existing) {
            flashMessage('error', 'Student already has an active room allotment. Vacate first.');
        } else {
            // Check room capacity
            $room = $db->query("SELECT * FROM rooms WHERE id=$room_id")->fetch_assoc();
            if ($room['occupied'] >= $room['capacity']) {
                flashMessage('error', 'Room is at full capacity.');
            } else {
                $stmt = $db->prepare("INSERT INTO room_allotments (student_id,room_id,allotment_date,status,allotted_by,remarks) VALUES (?,?,?,'active',?,?)");
                $stmt->bind_param('iisis', $student_id,$room_id,$allotment_date,$_SESSION['user_id'],$remarks);
                $stmt->execute();

                // Update room occupancy
                $db->query("UPDATE rooms SET occupied=occupied+1 WHERE id=$room_id");
                // Update room status if full
                $db->query("UPDATE rooms SET status=IF(occupied>=capacity,'full','available') WHERE id=$room_id");
                // Update student room
                $db->query("UPDATE students SET room_id=$room_id WHERE id=$student_id");

                // Notify student
                $uid = $db->query("SELECT user_id FROM students WHERE id=$student_id")->fetch_row()[0];
                sendNotification($uid, 'Room Allotted', "You have been allotted Room {$room['room_number']}.", 'room_allotment', 'allotments.php');

                logActivity($_SESSION['user_id'], 'ALLOT_ROOM', 'Allotments', "Student $student_id → Room {$room['room_number']}");
                flashMessage('success', "Room {$room['room_number']} allotted successfully.");
            }
        }
    }

    if ($action === 'vacate') {
        $id = (int)$_POST['id'];
        $allotment = $db->query("SELECT * FROM room_allotments WHERE id=$id")->fetch_assoc();
        if ($allotment) {
            $db->query("UPDATE room_allotments SET status='vacated', vacating_date=CURDATE() WHERE id=$id");
            $db->query("UPDATE rooms SET occupied=GREATEST(occupied-1,0) WHERE id={$allotment['room_id']}");
            $db->query("UPDATE rooms SET status=IF(occupied>=capacity,'full','available') WHERE id={$allotment['room_id']}");
            $db->query("UPDATE students SET room_id=NULL WHERE id={$allotment['student_id']}");

            $uid = $db->query("SELECT user_id FROM students WHERE id={$allotment['student_id']}")->fetch_row()[0];
            sendNotification($uid, 'Room Vacated', 'Your room allotment has been ended.', 'room_allotment', '');

            flashMessage('success', 'Room vacated successfully.');
        }
    }

    redirect(SITE_URL . 'php/allotments.php');
}

$status_filter = sanitize($_GET['status'] ?? 'active');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($status_filter) $where .= " AND ra.status='$status_filter'";

$total = $db->query("SELECT COUNT(*) FROM room_allotments ra $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$allotments = $db->query("
    SELECT ra.*, s.name AS student_name, s.student_id AS sid,
           r.room_number, r.room_type, r.floor,
           u.name AS allotted_by_name
    FROM room_allotments ra
    JOIN students s ON ra.student_id=s.id
    JOIN rooms r ON ra.room_id=r.id
    LEFT JOIN users u ON ra.allotted_by=u.id
    $where ORDER BY ra.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

// Available students (no active allotment)
$availStudents = $db->query("
    SELECT s.id, s.name, s.student_id AS sid FROM students s
    WHERE s.status='active'
    AND s.id NOT IN (SELECT student_id FROM room_allotments WHERE status='active')
    ORDER BY s.name
")->fetch_all(MYSQLI_ASSOC);

// Available rooms (not full)
$availRooms = $db->query("
    SELECT id, room_number, floor, room_type, capacity, occupied, monthly_rent
    FROM rooms WHERE status='available' AND occupied < capacity
    ORDER BY floor, room_number
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Room Allotments</h2><p>Assign and manage student room allotments</p></div>
    <button class="btn btn-primary" onclick="openModal('allotModal')"><i class="fas fa-plus"></i> New Allotment</button>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px">
            <select name="status" class="form-control" style="width:160px">
                <option value="active" <?= $status_filter==='active'?'selected':'' ?>>Active Allotments</option>
                <option value="vacated" <?= $status_filter==='vacated'?'selected':'' ?>>Vacated</option>
                <option value="" <?= $status_filter===''?'selected':'' ?>>All</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="allotments.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Student</th><th>Room</th><th>Type</th><th>Allotment Date</th>
                <th>Vacating Date</th><th>Allotted By</th><th>Remarks</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($allotments as $a): ?>
                <tr>
                    <td>
                        <div class="table-avatar">
                            <div class="avatar-sm"><?= strtoupper(substr($a['student_name'],0,2)) ?></div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($a['student_name']) ?></div>
                                <div class="text-muted"><?= $a['sid'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary" style="font-size:14px">Room <?= $a['room_number'] ?></span><br><span class="text-muted">Floor <?= $a['floor'] ?></span></td>
                    <td><?= ucfirst($a['room_type']) ?></td>
                    <td><?= formatDate($a['allotment_date']) ?></td>
                    <td><?= $a['vacating_date'] ? formatDate($a['vacating_date']) : '—' ?></td>
                    <td><?= htmlspecialchars($a['allotted_by_name'] ?? '—') ?></td>
                    <td style="max-width:120px;font-size:12px"><?= htmlspecialchars($a['remarks'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $a['status']==='active'?'success':'gray' ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'active'): ?>
                        <form method="POST" onsubmit="return confirm('Vacate this room?')">
                            <input type="hidden" name="action" value="vacate">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-sign-out-alt"></i> Vacate</button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($allotments)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-bed"></i><p>No allotments found</p></div></td></tr>
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

<!-- Allot Room Modal -->
<div class="modal-overlay" id="allotModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-bed"></i> New Room Allotment</h3><button class="modal-close" onclick="closeModal('allotModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="allot">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student <span>*</span></label>
                    <select name="student_id" class="form-control" required>
                        <option value="">— Select Student (without room) —</option>
                        <?php foreach ($availStudents as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['sid'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($availStudents)): ?>
                        <div class="form-hint" style="color:var(--warning)">All active students already have rooms.</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Room <span>*</span></label>
                    <select name="room_id" id="room_select" class="form-control" required onchange="showRoomInfo(this)">
                        <option value="">— Select Available Room —</option>
                        <?php foreach ($availRooms as $r): ?>
                            <option value="<?= $r['id'] ?>"
                                data-type="<?= $r['room_type'] ?>"
                                data-cap="<?= $r['capacity'] ?>"
                                data-occ="<?= $r['occupied'] ?>"
                                data-rent="<?= $r['monthly_rent'] ?>">
                                Room <?= $r['room_number'] ?> | Floor <?= $r['floor'] ?> | <?= ucfirst($r['room_type']) ?> | <?= $r['occupied'] ?>/<?= $r['capacity'] ?> occupied | ₹<?= number_format($r['monthly_rent']) ?>/mo
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="roomInfoBox" style="display:none;margin-top:10px;padding:12px;background:var(--primary-light);border-radius:8px;font-size:13px"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Allotment Date <span>*</span></label>
                        <input type="date" name="allotment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('allotModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-bed"></i> Allot Room</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRoomInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const box = document.getElementById('roomInfoBox');
    if (sel.value) {
        box.style.display = 'block';
        box.innerHTML = `<strong>Room Info:</strong> ${opt.dataset.type} | Capacity: ${opt.dataset.cap} | Occupied: ${opt.dataset.occ} | Available: ${opt.dataset.cap - opt.dataset.occ} | Rent: ₹${parseFloat(opt.dataset.rent).toLocaleString('en-IN')}/month`;
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
