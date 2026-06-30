<?php
require_once '../includes/config.php';
$pageTitle = 'Room Management';
$activePage = 'rooms';
requireRole(['admin','warden']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $room_number = sanitize($_POST['room_number']);
        $floor       = (int)$_POST['floor'];
        $room_type   = sanitize($_POST['room_type']);
        $capacity    = (int)$_POST['capacity'];
        $monthly_rent= (float)$_POST['monthly_rent'];
        $amenities   = sanitize($_POST['amenities']);
        $status      = sanitize($_POST['status']);

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO rooms (room_number,floor,room_type,capacity,monthly_rent,amenities,status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('siisdss', $room_number,$floor,$room_type,$capacity,$monthly_rent,$amenities,$status);
            $stmt->execute();
            flashMessage('success', "Room $room_number added successfully.");
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE rooms SET room_number=?,floor=?,room_type=?,capacity=?,monthly_rent=?,amenities=?,status=? WHERE id=?");
            $stmt->bind_param('siisdssi', $room_number,$floor,$room_type,$capacity,$monthly_rent,$amenities,$status,$id);
            $stmt->execute();
            flashMessage('success', "Room updated successfully.");
        }
        logActivity($_SESSION['user_id'], strtoupper($action).'_ROOM', 'Rooms', "Room: $room_number");
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $occupied = $db->query("SELECT occupied FROM rooms WHERE id=$id")->fetch_row()[0];
        if ($occupied > 0) {
            flashMessage('error', 'Cannot delete a room with active occupants.');
        } else {
            $db->query("DELETE FROM rooms WHERE id=$id");
            flashMessage('success', 'Room deleted.');
        }
    }

    redirect(SITE_URL . 'php/rooms.php');
}

$floor_filter  = sanitize($_GET['floor'] ?? '');
$type_filter   = sanitize($_GET['type'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($floor_filter)  $where .= " AND floor='$floor_filter'";
if ($type_filter)   $where .= " AND room_type='$type_filter'";
if ($status_filter) $where .= " AND status='$status_filter'";

$total = $db->query("SELECT COUNT(*) FROM rooms $where")->fetch_row()[0];
$pag   = paginate($total, RECORDS_PER_PAGE, $page);

$rooms = $db->query("
    SELECT r.*, 
        GROUP_CONCAT(s.name SEPARATOR ', ') AS occupant_names
    FROM rooms r
    LEFT JOIN room_allotments ra ON ra.room_id=r.id AND ra.status='active'
    LEFT JOIN students s ON s.id=ra.student_id
    $where GROUP BY r.id
    ORDER BY r.floor, r.room_number
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

$floors = $db->query("SELECT DISTINCT floor FROM rooms ORDER BY floor")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Room Management</h2><p>Manage hostel rooms and availability</p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Room</button>
</div>

<!-- Quick Stats -->
<?php
$rStats = $db->query("SELECT status, COUNT(*) AS cnt FROM rooms GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$rMap   = array_column($rStats, 'cnt', 'status');
?>
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-door-open"></i></div><div class="stat-body"><div class="stat-value"><?= $rMap['available'] ?? 0 ?></div><div class="stat-label">Available</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-door-closed"></i></div><div class="stat-body"><div class="stat-value"><?= $rMap['full'] ?? 0 ?></div><div class="stat-label">Full</div></div></div>
    <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-tools"></i></div><div class="stat-body"><div class="stat-value"><?= $rMap['maintenance'] ?? 0 ?></div><div class="stat-label">Maintenance</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-building"></i></div><div class="stat-body"><div class="stat-value"><?= array_sum(array_column($rStats,'cnt')) ?></div><div class="stat-label">Total Rooms</div></div></div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
            <select name="floor" class="form-control" style="width:120px">
                <option value="">All Floors</option>
                <?php foreach ($floors as $f): ?>
                    <option value="<?= $f['floor'] ?>" <?= $floor_filter==$f['floor']?'selected':'' ?>>Floor <?= $f['floor'] ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control" style="width:140px">
                <option value="">All Types</option>
                <?php foreach (['single','double','triple','dormitory'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type_filter===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:140px">
                <option value="">All Status</option>
                <?php foreach (['available','full','maintenance','reserved'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="rooms.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> rooms</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Room No.</th><th>Floor</th><th>Type</th><th>Capacity</th>
                <th>Occupancy</th><th>Rent/Month</th><th>Amenities</th>
                <th>Current Occupants</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rooms as $r):
                $pct = $r['capacity'] > 0 ? round(($r['occupied']/$r['capacity'])*100) : 0;
            ?>
                <tr>
                    <td><span class="fw-600" style="font-size:15px">Room <?= $r['room_number'] ?></span></td>
                    <td>Floor <?= $r['floor'] ?></td>
                    <td><span class="badge badge-primary"><?= ucfirst($r['room_type']) ?></span></td>
                    <td><?= $r['capacity'] ?> beds</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;min-width:100px">
                            <span><?= $r['occupied'] ?>/<?= $r['capacity'] ?></span>
                            <div class="progress-bar" style="flex:1">
                                <div class="progress-fill <?= $pct>=100?'red':($pct>=70?'yellow':'green') ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="fw-600"><?= formatCurrency($r['monthly_rent']) ?></td>
                    <td style="max-width:150px;font-size:12px;color:var(--gray)"><?= htmlspecialchars($r['amenities'] ?? '') ?></td>
                    <td style="font-size:12px;max-width:150px"><?= $r['occupant_names'] ? htmlspecialchars($r['occupant_names']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status']==='available'?'success':($r['status']==='full'?'danger':($r['status']==='maintenance'?'warning':'info')) ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="gap-8">
                            <button class="btn btn-sm btn-light btn-icon" title="Edit" onclick="editRoom(<?= htmlspecialchars(json_encode($r)) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this room?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rooms)): ?>
                <tr><td colspan="10"><div class="empty-state"><i class="fas fa-door-open"></i><p>No rooms found</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
                <a href="?floor=<?= $floor_filter ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Room Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Add New Room</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Room Number <span>*</span></label>
                        <input type="text" name="room_number" class="form-control" placeholder="e.g. 101" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Floor <span>*</span></label>
                        <input type="number" name="floor" class="form-control" min="0" max="20" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Type <span>*</span></label>
                        <select name="room_type" class="form-control" required>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="triple">Triple</option>
                            <option value="dormitory">Dormitory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacity <span>*</span></label>
                        <input type="number" name="capacity" class="form-control" min="1" max="20" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Rent (₹) <span>*</span></label>
                        <input type="number" name="monthly_rent" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Amenities</label>
                        <input type="text" name="amenities" class="form-control" placeholder="e.g. AC, WiFi, Attached Bathroom">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Room</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-edit"></i> Edit Room</h3><button class="modal-close" onclick="closeModal('editModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Room Number <span>*</span></label>
                        <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Floor <span>*</span></label>
                        <input type="number" name="floor" id="edit_floor" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Type</label>
                        <select name="room_type" id="edit_room_type" class="form-control">
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="triple">Triple</option>
                            <option value="dormitory">Dormitory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Rent (₹)</label>
                        <input type="number" name="monthly_rent" id="edit_monthly_rent" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="available">Available</option>
                            <option value="full">Full</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Amenities</label>
                        <input type="text" name="amenities" id="edit_amenities" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Room</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRoom(r) {
    document.getElementById('edit_id').value = r.id;
    document.getElementById('edit_room_number').value = r.room_number;
    document.getElementById('edit_floor').value = r.floor;
    document.getElementById('edit_room_type').value = r.room_type;
    document.getElementById('edit_capacity').value = r.capacity;
    document.getElementById('edit_monthly_rent').value = r.monthly_rent;
    document.getElementById('edit_status').value = r.status;
    document.getElementById('edit_amenities').value = r.amenities || '';
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
