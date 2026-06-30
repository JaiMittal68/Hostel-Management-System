<?php
require_once 'includes/config.php';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

requireLogin();

$db = getDB();

// Stats
$totalStudents  = $db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetch_row()[0];
$totalRooms     = $db->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0];
$availableRooms = $db->query("SELECT COUNT(*) FROM rooms WHERE status='available'")->fetch_row()[0];
$pendingFees    = $db->query("SELECT COALESCE(SUM(amount-paid_amount),0) FROM fees WHERE status IN ('pending','partial','overdue')")->fetch_row()[0];
$openComplaints = $db->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetch_row()[0];
$collectedFees  = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees WHERE status='paid' AND MONTH(paid_date)=MONTH(NOW())")->fetch_row()[0];

// Recent allotments
$recentAllotments = $db->query("
    SELECT ra.*, s.name AS student_name, s.student_id AS sid, r.room_number
    FROM room_allotments ra
    JOIN students s ON ra.student_id=s.id
    JOIN rooms r ON ra.room_id=r.id
    WHERE ra.status='active'
    ORDER BY ra.created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Recent complaints
$recentComplaints = $db->query("
    SELECT c.*, s.name AS student_name
    FROM complaints c
    JOIN students s ON c.student_id=s.id
    ORDER BY c.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Pending fees
$pendingFeesList = $db->query("
    SELECT f.*, s.name AS student_name, r.room_number
    FROM fees f
    JOIN students s ON f.student_id=s.id
    LEFT JOIN room_allotments ra ON ra.student_id=s.id AND ra.status='active'
    LEFT JOIN rooms r ON r.id=ra.room_id
    WHERE f.status IN ('pending','overdue')
    ORDER BY f.due_date ASC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Room occupancy by type
$occupancy = $db->query("
    SELECT room_type, SUM(capacity) AS total_capacity, SUM(occupied) AS total_occupied
    FROM rooms GROUP BY room_type
")->fetch_all(MYSQLI_ASSOC);

// Notices
$notices = $db->query("
    SELECT n.*, u.name AS posted_by_name
    FROM notices n JOIN users u ON n.posted_by=u.id
    WHERE n.is_active=1 AND (n.expiry_date IS NULL OR n.expiry_date >= CURDATE())
    ORDER BY n.is_urgent DESC, n.created_at DESC LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>! Here's what's happening today.</p>
    </div>
    <span class="text-muted"><?= date('l, d F Y') ?></span>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-door-open"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $availableRooms ?></div>
            <div class="stat-label">Available Rooms</div>
            <div class="stat-sub">of <?= $totalRooms ?> total</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($pendingFees) ?></div>
            <div class="stat-label">Pending Dues</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $openComplaints ?></div>
            <div class="stat-label">Open Complaints</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-check-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($collectedFees) ?></div>
            <div class="stat-label">Fees Collected (This Month)</div>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div>
        <!-- Recent Allotments -->
        <div class="card mb-16">
            <div class="card-header">
                <h3><i class="fas fa-bed" style="color:var(--primary)"></i> Recent Allotments</h3>
                <a href="php/allotments.php" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr>
                        <th>Student</th><th>Room</th><th>Date</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recentAllotments as $a): ?>
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
                            <td><span class="badge badge-primary">Room <?= $a['room_number'] ?></span></td>
                            <td><?= formatDate($a['allotment_date']) ?></td>
                            <td><span class="badge badge-success">Active</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentAllotments)): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-bed"></i><p>No allotments yet</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Complaints -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tools" style="color:var(--warning)"></i> Recent Complaints</h3>
                <a href="php/complaints.php" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Complaint</th><th>Student</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentComplaints as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-600"><?= htmlspecialchars(substr($c['subject'],0,40)) ?>...</div>
                                <div class="text-muted"><?= ucfirst($c['category']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($c['student_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= $c['priority']==='urgent'?'danger':($c['priority']==='high'?'warning':($c['priority']==='medium'?'info':'gray')) ?>">
                                    <?= ucfirst($c['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $c['status']==='resolved'?'success':($c['status']==='open'?'danger':'warning') ?>">
                                    <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentComplaints)): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-smile"></i><p>No complaints!</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Pending Fees -->
        <div class="card mb-16">
            <div class="card-header">
                <h3><i class="fas fa-rupee-sign" style="color:var(--danger)"></i> Overdue Fees</h3>
                <a href="php/fees.php" class="btn btn-sm btn-light">Manage</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php foreach ($pendingFeesList as $f): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div class="flex-between">
                        <span class="fw-600"><?= htmlspecialchars($f['student_name']) ?></span>
                        <span style="color:var(--danger);font-weight:600"><?= formatCurrency($f['amount']-$f['paid_amount']) ?></span>
                    </div>
                    <div class="flex-between mt-4">
                        <span class="text-muted"><?= ucfirst(str_replace('_',' ',$f['fee_type'])) ?></span>
                        <span class="text-muted">Due: <?= formatDate($f['due_date']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pendingFeesList)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle"></i><p>No overdue fees!</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Room Occupancy -->
        <div class="card mb-16">
            <div class="card-header"><h3><i class="fas fa-chart-pie" style="color:var(--info)"></i> Occupancy</h3></div>
            <div class="card-body">
                <?php foreach ($occupancy as $o):
                    $pct = $o['total_capacity'] > 0 ? round(($o['total_occupied']/$o['total_capacity'])*100) : 0;
                    $color = $pct >= 90 ? 'red' : ($pct >= 70 ? 'yellow' : 'green');
                ?>
                <div style="margin-bottom:14px">
                    <div class="flex-between mb-8">
                        <span class="fw-600"><?= ucfirst($o['room_type']) ?></span>
                        <span class="text-muted"><?= $o['total_occupied'] ?>/<?= $o['total_capacity'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notices -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn" style="color:var(--warning)"></i> Notices</h3>
                <a href="php/notices.php" class="btn btn-sm btn-light">All</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php foreach ($notices as $n): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div class="flex-between">
                        <span class="fw-600" style="font-size:13px"><?= htmlspecialchars($n['title']) ?></span>
                        <?php if ($n['is_urgent']): ?>
                            <span class="badge badge-danger">Urgent</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted" style="margin-top:4px"><?= formatDate($n['created_at']) ?> · <?= htmlspecialchars($n['posted_by_name']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($notices)): ?>
                    <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No active notices</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
