<?php
require_once '../includes/config.php';
$pageTitle = 'Students';
$activePage = 'students';
requireRole(['admin','warden']);

$db = getDB();

// Handle Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name          = sanitize($_POST['name']);
        $email         = sanitize($_POST['email']);
        $phone         = sanitize($_POST['phone']);
        $dob           = sanitize($_POST['dob']);
        $gender        = sanitize($_POST['gender']);
        $address       = sanitize($_POST['address']);
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_phone= sanitize($_POST['guardian_phone']);
        $course        = sanitize($_POST['course']);
        $year          = (int)$_POST['year_of_study'];
        $adm_date      = sanitize($_POST['admission_date']);

        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $photo = uploadFile($_FILES['photo'], 'students') ?: '';
        }

        if ($action === 'add') {
            // Create user account
            $password = password_hash('Student@123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'student')");
            $stmt->bind_param('sss', $name, $email, $password);
            $stmt->execute();
            $user_id = $db->insert_id;

            $sid = 'STU-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("INSERT INTO students (user_id,student_id,name,email,phone,dob,gender,address,guardian_name,guardian_phone,course,year_of_study,admission_date,photo,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')");
            $stmt->bind_param('isssssssssssss', $user_id, $sid, $name, $email, $phone, $dob, $gender, $address, $guardian_name, $guardian_phone, $course, $year, $adm_date, $photo);
            $stmt->execute();

            logActivity($_SESSION['user_id'], 'ADD_STUDENT', 'Students', "Added student: $name");
            flashMessage('success', "Student '$name' added successfully. Login: $email / Student@123");
        } else {
            $id = (int)$_POST['id'];
            if ($photo) {
                $stmt = $db->prepare("UPDATE students SET name=?,email=?,phone=?,dob=?,gender=?,address=?,guardian_name=?,guardian_phone=?,course=?,year_of_study=?,admission_date=?,photo=? WHERE id=?");
                $stmt->bind_param('ssssssssssssi', $name,$email,$phone,$dob,$gender,$address,$guardian_name,$guardian_phone,$course,$year,$adm_date,$photo,$id);
            } else {
                $stmt = $db->prepare("UPDATE students SET name=?,email=?,phone=?,dob=?,gender=?,address=?,guardian_name=?,guardian_phone=?,course=?,year_of_study=?,admission_date=? WHERE id=?");
                $stmt->bind_param('sssssssssssi', $name,$email,$phone,$dob,$gender,$address,$guardian_name,$guardian_phone,$course,$year,$adm_date,$id);
            }
            $stmt->execute();
            logActivity($_SESSION['user_id'], 'EDIT_STUDENT', 'Students', "Updated student ID: $id");
            flashMessage('success', "Student updated successfully.");
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE students SET status='inactive' WHERE id=$id");
        flashMessage('success', 'Student deactivated successfully.');
    }

    redirect(SITE_URL . 'php/students.php');
}

// Fetch students with filters
$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? 'active');
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($status) $where .= " AND s.status='$status'";
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.student_id LIKE '%$search%' OR s.email LIKE '%$search%' OR s.course LIKE '%$search%')";

$total   = $db->query("SELECT COUNT(*) FROM students s $where")->fetch_row()[0];
$pag     = paginate($total, RECORDS_PER_PAGE, $page);

$students = $db->query("
    SELECT s.*, r.room_number
    FROM students s
    LEFT JOIN room_allotments ra ON ra.student_id=s.id AND ra.status='active'
    LEFT JOIN rooms r ON r.id=ra.room_id
    $where ORDER BY s.created_at DESC
    LIMIT ".RECORDS_PER_PAGE." OFFSET {$pag['offset']}
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Students</h2><p>Manage hostel student records</p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Student
    </button>
</div>

<div class="card">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;flex:1">
            <div class="search-box" style="flex:1;min-width:200px">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, ID, email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="status" class="form-control" style="width:140px">
                <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="alumni" <?= $status==='alumni'?'selected':'' ?>>Alumni</option>
                <option value="" <?= $status===''?'selected':'' ?>>All</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="students.php" class="btn btn-light">Reset</a>
        </form>
        <span class="text-muted"><?= $total ?> record(s)</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr>
                <th>Student</th><th>Student ID</th><th>Course</th>
                <th>Room</th><th>Phone</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <div class="table-avatar">
                            <?php if ($s['photo']): ?>
                                <img src="<?= UPLOAD_URL.$s['photo'] ?>" alt="">
                            <?php else: ?>
                                <div class="avatar-sm"><?= strtoupper(substr($s['name'],0,2)) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="text-muted"><?= htmlspecialchars($s['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary"><?= $s['student_id'] ?></span></td>
                    <td><?= htmlspecialchars($s['course']) ?><br><span class="text-muted">Year <?= $s['year_of_study'] ?></span></td>
                    <td><?= $s['room_number'] ? '<span class="badge badge-info">Room '.$s['room_number'].'</span>' : '<span class="text-muted">Not allotted</span>' ?></td>
                    <td><?= $s['phone'] ?></td>
                    <td><span class="badge badge-<?= $s['status']==='active'?'success':($s['status']==='alumni'?'info':'danger') ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <div class="gap-8">
                            <button class="btn btn-sm btn-light btn-icon" title="Edit"
                                onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="student_detail.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-light btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Deactivate this student?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Deactivate"><i class="fas fa-user-slash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pag['totalPages'] > 1): ?>
    <div style="padding:16px">
        <div class="pagination">
            <?php for ($i=1; $i<=$pag['totalPages']; $i++): ?>
                <a href="?search=<?= urlencode($search) ?>&status=<?= $status ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span>*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span>*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" class="form-control" placeholder="e.g. B.Tech CSE">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year of Study</label>
                        <select name="year_of_study" class="form-control">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guardian Name</label>
                        <input type="text" name="guardian_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guardian Phone</label>
                        <input type="text" name="guardian_phone" class="form-control">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Student</h3>
            <button class="modal-close" onclick="closeModal('editModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span>*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span>*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="edit_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="edit_gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admission Date</label>
                        <input type="date" name="admission_date" id="edit_admission_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" id="edit_course" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year of Study</label>
                        <select name="year_of_study" id="edit_year" class="form-control">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guardian Name</label>
                        <input type="text" name="guardian_name" id="edit_guardian_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guardian Phone</label>
                        <input type="text" name="guardian_phone" id="edit_guardian_phone" class="form-control">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Photo (optional)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Student</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStudent(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_email').value = s.email;
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_dob').value = s.dob || '';
    document.getElementById('edit_gender').value = s.gender || '';
    document.getElementById('edit_admission_date').value = s.admission_date || '';
    document.getElementById('edit_course').value = s.course || '';
    document.getElementById('edit_year').value = s.year_of_study || '1';
    document.getElementById('edit_guardian_name').value = s.guardian_name || '';
    document.getElementById('edit_guardian_phone').value = s.guardian_phone || '';
    document.getElementById('edit_address').value = s.address || '';
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
