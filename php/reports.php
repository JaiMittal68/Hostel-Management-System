<?php
require_once '../includes/config.php';
$pageTitle = 'Reports & Analytics';
$activePage = 'reports';
requireRole(['admin']);

$db = getDB();

// Aggregate data for charts
$monthlyFees = $db->query("
    SELECT DATE_FORMAT(paid_date,'%b %Y') AS month,
           SUM(paid_amount) AS collected
    FROM fees WHERE paid_date IS NOT NULL
    AND paid_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(paid_date,'%Y-%m')
    ORDER BY paid_date
")->fetch_all(MYSQLI_ASSOC);

$complaintsByCategory = $db->query("
    SELECT category, COUNT(*) AS cnt FROM complaints GROUP BY category ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);

$complaintsByStatus = $db->query("
    SELECT status, COUNT(*) AS cnt FROM complaints GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$roomOccupancy = $db->query("
    SELECT room_type, SUM(capacity) AS cap, SUM(occupied) AS occ FROM rooms GROUP BY room_type
")->fetch_all(MYSQLI_ASSOC);

$feeCollection = $db->query("
    SELECT status, COUNT(*) AS cnt, SUM(amount) AS total FROM fees GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// Summary
$summaryStats = [
    'students' => $db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetch_row()[0],
    'rooms'    => $db->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0],
    'total_fees_collected' => $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees WHERE status='paid'")->fetch_row()[0],
    'pending_fees' => $db->query("SELECT COALESCE(SUM(amount-paid_amount),0) FROM fees WHERE status IN ('pending','partial','overdue')")->fetch_row()[0],
    'open_complaints' => $db->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetch_row()[0],
    'resolved_complaints' => $db->query("SELECT COUNT(*) FROM complaints WHERE status='resolved'")->fetch_row()[0],
];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Reports & Analytics</h2><p>Visual insights into hostel operations</p></div>
    <div class="gap-8">
        <a href="?export=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="?export=csv&type=fees" class="btn btn-success"><i class="fas fa-file-csv"></i> Export Fees CSV</a>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $summaryStats['students'] ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($summaryStats['total_fees_collected']) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($summaryStats['pending_fees']) ?></div>
            <div class="stat-label">Pending Fees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-tools"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $summaryStats['open_complaints'] ?></div>
            <div class="stat-label">Open Complaints</div>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="dashboard-grid" style="margin-bottom:24px">
    <!-- Monthly Fee Collection Chart -->
    <div class="card">
        <div class="card-header"><h3>Monthly Fee Collection (Last 6 Months)</h3></div>
        <div class="card-body">
            <canvas id="feeChart" height="120"></canvas>
        </div>
    </div>

    <!-- Room Occupancy Chart -->
    <div class="card">
        <div class="card-header"><h3>Room Occupancy</h3></div>
        <div class="card-body">
            <canvas id="occupancyChart" height="120"></canvas>
        </div>
    </div>
</div>

<div class="dashboard-grid-2" style="margin-bottom:24px">
    <!-- Complaints by Category -->
    <div class="card">
        <div class="card-header"><h3>Complaints by Category</h3></div>
        <div class="card-body">
            <canvas id="complaintCatChart" height="180"></canvas>
        </div>
    </div>

    <!-- Complaints by Status -->
    <div class="card">
        <div class="card-header"><h3>Complaints by Status</h3></div>
        <div class="card-body">
            <canvas id="complaintStatusChart" height="180"></canvas>
        </div>
    </div>
</div>

<!-- Fee Collection Breakdown Table -->
<div class="card mb-16">
    <div class="card-header"><h3>Fee Collection Breakdown</h3></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Status</th><th>No. of Records</th><th>Total Amount</th><th>% of Total</th></tr></thead>
            <tbody>
            <?php
            $grandTotal = array_sum(array_column($feeCollection, 'total'));
            foreach ($feeCollection as $f):
                $pct = $grandTotal > 0 ? round(($f['total']/$grandTotal)*100, 1) : 0;
            ?>
                <tr>
                    <td><span class="badge badge-<?= $f['status']==='paid'?'success':($f['status']==='partial'?'warning':($f['status']==='overdue'?'danger':'info')) ?>"><?= ucfirst($f['status']) ?></span></td>
                    <td><?= $f['cnt'] ?></td>
                    <td class="fw-600"><?= formatCurrency($f['total']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <?= $pct ?>%
                            <div class="progress-bar" style="flex:1;width:80px">
                                <div class="progress-fill <?= $f['status']==='paid'?'green':($f['status']==='overdue'?'red':'yellow') ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Room Occupancy Table -->
<div class="card">
    <div class="card-header"><h3>Room-wise Occupancy Report</h3></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Room Type</th><th>Capacity</th><th>Occupied</th><th>Available</th><th>Occupancy %</th></tr></thead>
            <tbody>
            <?php foreach ($roomOccupancy as $r):
                $pct = $r['cap'] > 0 ? round(($r['occ']/$r['cap'])*100) : 0;
                $available = $r['cap'] - $r['occ'];
            ?>
                <tr>
                    <td class="fw-600"><?= ucfirst($r['room_type']) ?></td>
                    <td><?= $r['cap'] ?></td>
                    <td><?= $r['occ'] ?></td>
                    <td><span class="badge badge-<?= $available>0?'success':'danger' ?>"><?= $available ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span><?= $pct ?>%</span>
                            <div class="progress-bar" style="flex:1;max-width:100px">
                                <div class="progress-fill <?= $pct>=90?'red':($pct>=70?'yellow':'green') ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Color palette
const colors = ['#4f46e5','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'];

// Fee Collection Chart
const feeData = <?= json_encode($monthlyFees) ?>;
new Chart(document.getElementById('feeChart'), {
    type: 'bar',
    data: {
        labels: feeData.map(d => d.month),
        datasets: [{
            label: 'Amount Collected (₹)',
            data: feeData.map(d => d.collected),
            backgroundColor: 'rgba(79,70,229,0.7)',
            borderColor: '#4f46e5',
            borderWidth: 1, borderRadius: 6
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Occupancy Chart
const occData = <?= json_encode($roomOccupancy) ?>;
new Chart(document.getElementById('occupancyChart'), {
    type: 'doughnut',
    data: {
        labels: occData.map(d => d.room_type.charAt(0).toUpperCase()+d.room_type.slice(1)),
        datasets: [{
            data: occData.map(d => d.occ),
            backgroundColor: colors, borderWidth: 2
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Complaints by Category
const catData = <?= json_encode($complaintsByCategory) ?>;
new Chart(document.getElementById('complaintCatChart'), {
    type: 'bar',
    data: {
        labels: catData.map(d => d.category.charAt(0).toUpperCase()+d.category.slice(1)),
        datasets: [{
            label: 'Count',
            data: catData.map(d => d.cnt),
            backgroundColor: colors, borderRadius: 5
        }]
    },
    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
});

// Complaints by Status
const statData = <?= json_encode($complaintsByStatus) ?>;
new Chart(document.getElementById('complaintStatusChart'), {
    type: 'pie',
    data: {
        labels: statData.map(d => d.status.charAt(0).toUpperCase()+d.status.slice(1).replace('_',' ')),
        datasets: [{ data: statData.map(d => d.cnt), backgroundColor: colors, borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
