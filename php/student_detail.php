<?php
require_once '../includes/config.php';
$pageTitle = 'Student Detail';
$activePage = 'students';
requireRole(['admin','warden']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . 'php/students.php');

$student = $db->query("
    SELECT s.*, r.room_number, r.room_type, r.monthly_rent, r.floor
    FROM students s
    LEFT JOIN room_allotments ra ON ra.student_id=s.id AND ra.status='active'
    LEFT JOIN rooms r ON r.id=ra.room_id
    WHERE s.id=$id
")->fetch_assoc();

if (!$student) {
    flashMessage('error','Student not found.');
    redirect(SITE_URL . 'php/students.php');
}

$fees = $db->query("SELECT * FROM fees WHERE student_id=$id ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$complaints = $db->query("SELECT * FROM complaints WHERE student_id=$id ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$allotments = $db->query("
    SELECT ra.*, r.room_number, r.room_type FROM room_allotments ra
    JOIN rooms r ON ra.room_id=r.id WHERE ra.student_id=$id ORDER BY ra.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
$gatePasses = $db->query("SELECT * FROM gate_passes WHERE student_id=$id ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$totalFees    = $db->query("SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id=$id")->fetch_row()[0];
$paidFees     = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fees WHERE student_id=$id")->fetch_row()[0];
$pendingFees  = $totalFees - $paidFees;

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Student Profile</h2>
        <p><a href="students.php" style="color:var(--primary)"><i class="fas fa-arrow-left"></i> Back to Students</a></p>
    </div>
    <span class="badge badge-<?= $student['status']==='active'?'success':'danger' ?>" style="font-size:14px;padding:8px 16px">
        <?= ucfirst($student['status']) ?>
    </span>
</div>

<div class="dashboard-grid">
    <!-- Left Column -->
    <div>
        <!-- Student Info Card -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-body">
                <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <?php if ($student['photo']): ?>
                            <img src="<?= UPLOAD_URL.$student['photo'] ?>" alt=""
                                 style="width:90px;height:90px;border-radius:12px;object-fit:cover;border:3px solid var(--primary-light)">
                        <?php else: ?>
                            <div style="width:90px;height:90px;border-radius:12px;background:var(--primary);
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:30px;font-weight:700;color:white">
                                <?= strtoupper(substr($student['name'],0,2)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1">
                        <h3 style="font-size:20px;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($student['name']) ?></h3>
                        <p style="color:var(--gray);margin-bottom:8px"><?= htmlspecialchars($student['email']) ?></p>
                        <span class="badge badge-primary" style="font-size:13px"><?= $student['student_id'] ?></span>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px">
                    <?php $fields = [
                        ['Phone', $student['phone'] ?? '—'],
                        ['Gender', ucfirst($student['gender'] ?? '—')],
                        ['Date of Birth', formatDate($student['dob'])],
                        ['Admission Date', formatDate($student['admission_date'])],
                        ['Course', $student['course'] ?? '—'],
                        ['Year of Study', 'Year '.($student['year_of_study'] ?? '—')],
                        ['Guardian', $student['guardian_name'] ?? '—'],
                        ['Guardian Phone', $student['guardian_phone'] ?? '—'],
                    ]; foreach ($fields as [$label,$val]): ?>
                    <div style="padding:10px;background:var(--light-gray);border-radius:8px">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;margin-bottom:2px"><?= $label ?></div>
                        <div style="font-size:13.5px;font-weight:500"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($student['address']): ?>
                <div style="margin-top:12px;padding:10px;background:var(--light-gray);border-radius:8px">
                    <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;margin-bottom:2px">Address</div>
                    <div style="font-size:13px"><?= nl2br(htmlspecialchars($student['address'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Room Info -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><h3><i class="fas fa-bed" style="color:var(--primary)"></i> Room Information</h3></div>
            <div class="card-body">
                <?php if ($student['room_number']): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div style="text-align:center;padding:16px;background:var(--primary-light);border-radius:8px">
                        <div style="font-size:24px;font-weight:700;color:var(--primary)"><?= $student['room_number'] ?></div>
                        <div class="text-muted">Room Number</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:var(--secondary-light);border-radius:8px">
                        <div style="font-size:18px;font-weight:700;color:var(--secondary)"><?= ucfirst($student['room_type']) ?></div>
                        <div class="text-muted">Room Type</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:var(--warning-light);border-radius:8px">
                        <div style="font-size:18px;font-weight:700;color:var(--warning)"><?= formatCurrency($student['monthly_rent']) ?></div>
                        <div class="text-muted">Monthly Rent</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state"><i class="fas fa-door-open"></i><p>No room allotted</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fee Summary -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-rupee-sign" style="color:var(--warning)"></i> Fee Summary</h3></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
                    <div style="text-align:center;padding:12px;background:var(--light-gray);border-radius:8px">
                        <div style="font-size:18px;font-weight:700"><?= formatCurrency($totalFees) ?></div>
                        <div class="text-muted">Total Billed</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--secondary-light);border-radius:8px">
                        <div style="font-size:18px;font-weight:700;color:var(--secondary)"><?= formatCurrency($paidFees) ?></div>
                        <div class="text-muted">Paid</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--danger-light);border-radius:8px">
                        <div style="font-size:18px;font-weight:700;color:var(--danger)"><?= formatCurrency($pendingFees) ?></div>
                        <div class="text-muted">Pending</div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Type</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($fees as $f): ?>
                            <tr>
                                <td><?= ucfirst(str_replace('_',' ',$f['fee_type'])) ?></td>
                                <td><?= formatCurrency($f['amount']) ?></td>
                                <td><?= formatDate($f['due_date']) ?></td>
                                <td><span class="badge badge-<?= $f['status']==='paid'?'success':($f['status']==='overdue'?'danger':'warning') ?>"><?= ucfirst($f['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($fees)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No fee records</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Complaints -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><h3><i class="fas fa-exclamation-circle" style="color:var(--danger)"></i> Complaints</h3></div>
            <div class="card-body" style="padding:0">
                <?php foreach ($complaints as $c): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div class="flex-between">
                        <span class="fw-600" style="font-size:13px"><?= htmlspecialchars(substr($c['subject'],0,45)) ?></span>
                        <span class="badge badge-<?= $c['status']==='resolved'?'success':($c['status']==='open'?'danger':'warning') ?>"><?= ucfirst($c['status']) ?></span>
                    </div>
                    <div class="text-muted"><?= ucfirst($c['category']) ?> · <?= formatDate($c['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($complaints)): ?>
                    <div class="empty-state"><i class="fas fa-smile"></i><p>No complaints</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Allotment History -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><h3><i class="fas fa-history" style="color:var(--info)"></i> Allotment History</h3></div>
            <div class="card-body" style="padding:0">
                <?php foreach ($allotments as $a): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div class="flex-between">
                        <span class="fw-600">Room <?= $a['room_number'] ?> (<?= ucfirst($a['room_type']) ?>)</span>
                        <span class="badge badge-<?= $a['status']==='active'?'success':'gray' ?>"><?= ucfirst($a['status']) ?></span>
                    </div>
                    <div class="text-muted"><?= formatDate($a['allotment_date']) ?> → <?= $a['vacating_date'] ? formatDate($a['vacating_date']) : 'Present' ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($allotments)): ?>
                    <div class="empty-state"><i class="fas fa-bed"></i><p>No allotment history</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gate Passes -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-id-card" style="color:var(--secondary)"></i> Gate Passes</h3></div>
            <div class="card-body" style="padding:0">
                <?php foreach ($gatePasses as $gp): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div class="flex-between">
                        <span style="font-size:13px"><?= htmlspecialchars(substr($gp['reason'],0,40)) ?></span>
                        <span class="badge badge-<?= $gp['status']==='approved'?'success':($gp['status']==='rejected'?'danger':'warning') ?>"><?= ucfirst($gp['status']) ?></span>
                    </div>
                    <div class="text-muted"><?= formatDate($gp['out_date']) ?> → <?= formatDate($gp['expected_return']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($gatePasses)): ?>
                    <div class="empty-state"><i class="fas fa-id-card"></i><p>No gate passes</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
