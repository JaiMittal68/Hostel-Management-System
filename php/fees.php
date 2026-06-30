<?php
require_once '../includes/config.php';
$pageTitle = 'Fee Management';
$activePage = 'fees';
requireRole(['admin','warden']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_fee') {
        $student_id  = (int)$_POST['student_id'];
        $fee_type    = sanitize($_POST['fee_type']);
        $amount      = (float)$_POST['amount'];
        $due_date    = sanitize($_POST['due_date']);
        $month_year  = sanitize($_POST['month_year']);
        $remarks     = sanitize($_POST['remarks']);

        $stmt = $db->prepare("INSERT INTO fees (student_id,fee_type,amount,due_date,month_year,status,remarks) VALUES (?,?,?,?,?,'pending',?)");
        $stmt->bind_param('isdsss', $student_id,$fee_type,$amount,$due_date,$month_year,$remarks);
        $stmt->execute();

        // Send notification to student
        $user_id_res = $db->query("SELECT user_id FROM students WHERE id=$student_id")->fetch_row();
        if ($user_id_res) {
            sendNotification($user_id_res[0], 'Fee Due', "A new fee of ₹$amount has been added for $fee_type. Due: $due_date", 'fee_due', 'fees.php');
        }

        logActivity($_SESSION['user_id'], 'ADD_FEE', 'Fees', "Fee added for student $student_id");
        flashMessage('success', 'Fee record added successfully.');
    }

    if ($action === 'collect') {
        $fee_id         = (int)$_POST['fee_id'];
        $paid_amount    = (float)$_POST['paid_amount'];
        $payment_method = sanitize($_POST['payment_method']);
        $transaction_id = sanitize($_POST['transaction_id']);
        $receipt        = generateReceiptNumber();

        $fee = $db->query("SELECT * FROM fees WHERE id=$fee_id")->fetch_assoc();
        $total_paid = $fee['paid_amount'] + $paid_amount;
        $new_status = $total_paid >= $fee['amount'] ? 'paid' : 'partial';

        $stmt = $db->prepare("UPDATE fees SET paid_amount=?,paid_date=CURDATE(),payment_method=?,transaction_id=?,receipt_number=?,status=?,collected_by=? WHERE id=?");
        $stmt->bind_param('dssssis', $total_paid,$payment_method,$transaction_id,$receipt,$new_status,$_SESSION['user_id'],$fee_id);
        $stmt->execute();

        logActivity($_SESSION['user_id'], 'COLLECT_FEE', 'Fees', "Fee collected: ₹$paid_amount, Receipt: $receipt");
        flashMessage('success', "Payment recorded. Receipt: $receipt");
    }

    if ($action === 'bulk_generate') {
        $fee_type   = sanitize($_POST['bulk_fee_type']);
        $amount     = (float)$_POST['bulk_amount'];
        $due_date   = sanitize($_POST['bulk_due_date']);
        $month_year = sanitize($_POST['bulk_month_year']);

        $students = $db->query("SELECT id, user_id FROM students WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
        foreach ($students as $s) {
            $stmt = $db->prepare("INSERT INTO fees (student_id,fee_type,amount,due_date,month_year,status) VALUES (?,?,?,?,?,'pending')");
            $stmt->bind_param('isdss', $s['id'],$fee_type,$amount,$due_date,$month_year);
            $stmt->execute();
            sendNotification($s['user_id'], 'Fee Due', "Monthly $fee_type of ₹$amount is due by $due_date", 'fee_due', 'fees.php');
        }
        flashMessage('success', count($students).' fee records generated for all active students.');
    }

    redirect(SITE_URL . 'php/fees.php');
}

// Filters
$search     = sanitize($_GET['search'] ?? '');
$fee_status = sanitize($_GET['fee_status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($fee_status) $where .= " AND f.status='$fee_status'";
if ($search)     $where .= " AND (s.name LIKE '%$search%' OR s.student_id LIKE '%$search%')";

$total   = $db->query("SELECT COUNT(*) FROM fees f JOIN students s ON f.student_id=s.id $where")->fetch_row()[0];
$pag     = paginate($total, RECORDS_PER_PAGE, $page);

$fees = $db->query("
    SELECT f.*, s.name AS student_name, s.student_id AS sid
    FROM fees f JOIN students s ON f.student_id=s.id
    $where ORDER BY f.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

$students = $db->query("SELECT id,name,student_id FROM students WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Summary
$summary = $db->query("
    SELECT
        SUM(amount) AS total_billed,
        SUM(paid_amount) AS total_collected,
        SUM(CASE WHEN status='pending' OR status='overdue' THEN amount-paid_amount ELSE 0 END) AS total_pending,
        COUNT(CASE WHEN status='overdue' THEN 1 END) AS overdue_count
    FROM fees
")->fetch_assoc();

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Fee Management</h2><p>Track and collect student fees</p></div>
    <div class="gap-8">
        <button class="btn btn-light" onclick="openModal('bulkModal')"><i class="fas fa-layer-group"></i> Bulk Generate</button>
        <button class="btn btn-primary" onclick="openModal('addFeeModal')"><i class="fas fa-plus"></i> Add Fee</button>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($summary['total_billed'] ?? 0) ?></div>
            <div class="stat-label">Total Billed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($summary['total_collected'] ?? 0) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatCurrency($summary['total_pending'] ?? 0) ?></div>
            <div class="stat-label">Total Pending</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $summary['overdue_count'] ?? 0 ?></div>
            <div class="stat-label">Overdue Records</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;flex:1">
            <div class="search-box" style="flex:1">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search student..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="fee_status" class="form-control" style="width:150px">
                <option value="">All Status</option>
                <option value="pending" <?= $fee_status==='pending'?'selected':'' ?>>Pending</option>
                <option value="paid" <?= $fee_status==='paid'?'selected':'' ?>>Paid</option>
                <option value="partial" <?= $fee_status==='partial'?'selected':'' ?>>Partial</option>
                <option value="overdue" <?= $fee_status==='overdue'?'selected':'' ?>>Overdue</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="fees.php" class="btn btn-light">Reset</a>
        </form>
        <a href="reports.php?type=fees" class="btn btn-light"><i class="fas fa-download"></i> Export</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Student</th><th>Fee Type</th><th>Amount</th><th>Paid</th>
                <th>Due Date</th><th>Month</th><th>Status</th><th>Receipt</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($fees as $f): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($f['student_name']) ?></div>
                        <div class="text-muted"><?= $f['sid'] ?></div>
                    </td>
                    <td><?= ucfirst(str_replace('_',' ',$f['fee_type'])) ?></td>
                    <td class="fw-600"><?= formatCurrency($f['amount']) ?></td>
                    <td><?= formatCurrency($f['paid_amount']) ?></td>
                    <td><?= formatDate($f['due_date']) ?></td>
                    <td><?= $f['month_year'] ?: '-' ?></td>
                    <td>
                        <span class="badge badge-<?= $f['status']==='paid'?'success':($f['status']==='partial'?'warning':($f['status']==='overdue'?'danger':'info')) ?>">
                            <?= ucfirst($f['status']) ?>
                        </span>
                    </td>
                    <td><?= $f['receipt_number'] ?: '-' ?></td>
                    <td>
                        <?php if ($f['status'] !== 'paid'): ?>
                        <button class="btn btn-sm btn-success"
                            onclick="collectFee(<?= $f['id'] ?>, '<?= htmlspecialchars($f['student_name']) ?>', <?= $f['amount']-$f['paid_amount'] ?>)">
                            <i class="fas fa-hand-holding-usd"></i> Collect
                        </button>
                        <?php else: ?>
                        <span class="text-muted">Paid</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fees)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-file-invoice"></i><p>No fee records found</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
                <a href="?search=<?= urlencode($search) ?>&fee_status=<?= $fee_status ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Fee Modal -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Fee Record</h3><button class="modal-close" onclick="closeModal('addFeeModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_fee">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student <span>*</span></label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['student_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Fee Type <span>*</span></label>
                        <select name="fee_type" class="form-control" required>
                            <option value="room_rent">Room Rent</option>
                            <option value="mess_fee">Mess Fee</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="security_deposit">Security Deposit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (₹) <span>*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date <span>*</span></label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Month/Year</label>
                        <input type="month" name="month_year" class="form-control" value="<?= date('Y-m') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Fee</button>
            </div>
        </form>
    </div>
</div>

<!-- Collect Payment Modal -->
<div class="modal-overlay" id="collectModal">
    <div class="modal">
        <div class="modal-header"><h3>Collect Payment</h3><button class="modal-close" onclick="closeModal('collectModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="collect">
            <input type="hidden" name="fee_id" id="collect_fee_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student</label>
                    <input type="text" id="collect_student" class="form-control" readonly style="background:var(--light-gray)">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Balance Due</label>
                        <input type="text" id="collect_balance" class="form-control" readonly style="background:var(--light-gray);color:var(--danger);font-weight:600">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount to Collect <span>*</span></label>
                        <input type="number" name="paid_amount" id="collect_amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="online">Online Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transaction ID</label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="UTR/Cheque number">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('collectModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Generate Modal -->
<div class="modal-overlay" id="bulkModal">
    <div class="modal">
        <div class="modal-header"><h3>Bulk Fee Generation</h3><button class="modal-close" onclick="closeModal('bulkModal')">×</button></div>
        <form method="POST" onsubmit="return confirm('Generate fees for ALL active students?')">
            <input type="hidden" name="action" value="bulk_generate">
            <div class="modal-body">
                <div class="alert alert-info"><i class="fas fa-info-circle"></i> This will create fee records for all active students.</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Fee Type</label>
                        <select name="bulk_fee_type" class="form-control">
                            <option value="room_rent">Room Rent</option>
                            <option value="mess_fee">Mess Fee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" name="bulk_amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="bulk_due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Month</label>
                        <input type="month" name="bulk_month_year" class="form-control" value="<?= date('Y-m') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('bulkModal')">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-layer-group"></i> Generate for All</button>
            </div>
        </form>
    </div>
</div>

<script>
function collectFee(id, student, balance) {
    document.getElementById('collect_fee_id').value = id;
    document.getElementById('collect_student').value = student;
    document.getElementById('collect_balance').value = '₹' + balance.toFixed(2);
    document.getElementById('collect_amount').value = balance.toFixed(2);
    openModal('collectModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
