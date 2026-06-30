-- ============================================================
-- HOSTEL MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Stack: PHP + MySQL
-- ============================================================
-- NOTE: This script DROPS and RECREATES the database every time
-- it is imported, so it is always safe to re-import this file
-- without "table already exists" errors.
-- ============================================================

DROP DATABASE IF EXISTS hostel_db;
CREATE DATABASE hostel_db;
USE hostel_db;

-- ============================================================
-- USERS TABLE (Admin, Warden, Student)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','warden','student') NOT NULL DEFAULT 'student',
    phone VARCHAR(15),
    profile_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- STUDENTS TABLE
-- ============================================================
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    dob DATE,
    gender ENUM('male','female','other'),
    address TEXT,
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(15),
    course VARCHAR(100),
    year_of_study INT,
    admission_date DATE,
    photo VARCHAR(255),
    room_id INT,
    status ENUM('active','inactive','alumni') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- ROOMS TABLE
-- ============================================================
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    floor INT NOT NULL,
    room_type ENUM('single','double','triple','dormitory') NOT NULL,
    capacity INT NOT NULL,
    occupied INT DEFAULT 0,
    monthly_rent DECIMAL(10,2) NOT NULL,
    amenities TEXT,
    status ENUM('available','full','maintenance','reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ROOM ALLOTMENTS TABLE
-- ============================================================
CREATE TABLE room_allotments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    allotment_date DATE NOT NULL,
    vacating_date DATE,
    status ENUM('active','vacated') DEFAULT 'active',
    allotted_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (allotted_by) REFERENCES users(id)
);

-- ============================================================
-- FEES TABLE
-- ============================================================
CREATE TABLE fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type ENUM('room_rent','mess_fee','maintenance','security_deposit','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('cash','online','cheque','upi') DEFAULT 'cash',
    transaction_id VARCHAR(100),
    status ENUM('pending','paid','partial','overdue') DEFAULT 'pending',
    receipt_number VARCHAR(50),
    collected_by INT,
    remarks TEXT,
    month_year VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

-- ============================================================
-- COMPLAINTS TABLE
-- ============================================================
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    complaint_number VARCHAR(20) UNIQUE NOT NULL,
    category ENUM('plumbing','electrical','furniture','cleanliness','security','internet','food','other') NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed','rejected') DEFAULT 'open',
    assigned_to INT,
    resolution_note TEXT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ============================================================
-- NOTICES TABLE
-- ============================================================
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target ENUM('all','students','wardens','specific_room') DEFAULT 'all',
    attachment VARCHAR(255),
    is_urgent TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    posted_by INT NOT NULL,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

-- ============================================================
-- NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('fee_due','complaint_update','notice','room_allotment','general') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- MESS MENU TABLE
-- ============================================================
CREATE TABLE mess_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    meal_type ENUM('breakfast','lunch','snacks','dinner') NOT NULL,
    menu_items TEXT NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- ============================================================
-- VISITOR LOG TABLE
-- ============================================================
CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    visitor_name VARCHAR(100) NOT NULL,
    visitor_phone VARCHAR(15),
    relation VARCHAR(50),
    purpose TEXT,
    id_proof_type VARCHAR(50),
    id_proof_number VARCHAR(50),
    check_in DATETIME NOT NULL,
    check_out DATETIME,
    approved_by INT,
    status ENUM('pending','approved','rejected','checked_out') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ============================================================
-- MAINTENANCE REQUESTS TABLE
-- ============================================================
CREATE TABLE maintenance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    reported_by INT NOT NULL,
    issue_type VARCHAR(100),
    description TEXT NOT NULL,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    assigned_to VARCHAR(100),
    cost DECIMAL(10,2) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- ============================================================
-- GATE PASSES TABLE
-- ============================================================
CREATE TABLE gate_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reason TEXT NOT NULL,
    out_date DATE NOT NULL,
    out_time TIME NOT NULL,
    expected_return DATE NOT NULL,
    actual_return DATETIME,
    approved_by INT,
    status ENUM('pending','approved','rejected','returned') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ============================================================
-- ACTIVITY LOG TABLE (Audit Trail)
-- ============================================================
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Admin user (password: Admin@123)
INSERT INTO users (name, email, password, role) VALUES
('Super Admin', 'admin@hostel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('John Warden', 'warden@hostel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'warden'),
('Amit Kumar', 'student@hostel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- Matching student profile for the demo student login above (user_id = 3)
INSERT INTO students (user_id, student_id, name, email, phone, dob, gender, address, guardian_name, guardian_phone, course, year_of_study, admission_date, status) VALUES
(3, 'STU-2026-0001', 'Amit Kumar', 'student@hostel.com', '9876543210', '2004-05-12', 'male', '123 MG Road, Delhi', 'Rajesh Kumar', '9876500000', 'B.Tech CSE', 2, '2025-07-01', 'active');

-- Sample Rooms (occupied counts start at 0 — real occupancy is tracked
-- automatically via room_allotments whenever a room is actually allotted)
INSERT INTO rooms (room_number, floor, room_type, capacity, occupied, monthly_rent, amenities, status) VALUES
('101', 1, 'single', 1, 0, 5000.00, 'AC, WiFi, Attached Bathroom', 'available'),
('102', 1, 'double', 2, 1, 4000.00, 'Fan, WiFi, Common Bathroom', 'available'),
('103', 1, 'double', 2, 0, 4000.00, 'Fan, WiFi, Common Bathroom', 'available'),
('104', 1, 'triple', 3, 0, 3000.00, 'Fan, Common Bathroom', 'available'),
('201', 2, 'single', 1, 0, 5500.00, 'AC, WiFi, Attached Bathroom', 'available'),
('202', 2, 'double', 2, 0, 4500.00, 'AC, WiFi, Common Bathroom', 'available'),
('203', 2, 'triple', 3, 0, 3500.00, 'Fan, WiFi, Common Bathroom', 'available'),
('204', 2, 'dormitory', 6, 0, 2000.00, 'Fan, Common Bathroom', 'available'),
('301', 3, 'single', 1, 0, 6000.00, 'AC, WiFi, Attached Bathroom, Balcony', 'available'),
('302', 3, 'double', 2, 0, 5000.00, 'AC, WiFi, Attached Bathroom', 'maintenance');

-- Real allotment record backing room 102's occupied count above,
-- so the Allotments page and Room page stay consistent with each other
INSERT INTO room_allotments (student_id, room_id, allotment_date, status, allotted_by, remarks) VALUES
(1, (SELECT id FROM rooms WHERE room_number='102'), '2025-07-05', 'active', 1, 'Initial demo allotment');

UPDATE students SET room_id = (SELECT id FROM rooms WHERE room_number='102') WHERE student_id = 'STU-2026-0001';

-- Sample fee record for the demo student
INSERT INTO fees (student_id, fee_type, amount, due_date, month_year, status) VALUES
(1, 'room_rent', 4000.00, '2026-07-05', '2026-07', 'pending');

-- Sample Mess Menu
INSERT INTO mess_menu (day_of_week, meal_type, menu_items) VALUES
('Monday','breakfast','Idli, Sambar, Chutney, Tea/Coffee'),
('Monday','lunch','Rice, Dal, Sabzi, Chapati, Salad'),
('Monday','snacks','Bread Pakora, Tea'),
('Monday','dinner','Rice, Dal, Paneer Curry, Chapati, Dessert'),
('Tuesday','breakfast','Poha, Jalebi, Tea/Coffee'),
('Tuesday','lunch','Rice, Rajma, Sabzi, Chapati, Salad'),
('Tuesday','snacks','Samosa, Tea'),
('Tuesday','dinner','Rice, Dal Makhani, Sabzi, Chapati'),
('Wednesday','breakfast','Paratha, Curd, Pickle, Tea/Coffee'),
('Wednesday','lunch','Rice, Chana Dal, Aloo Gobi, Chapati'),
('Wednesday','snacks','Maggi, Tea'),
('Wednesday','dinner','Rice, Dal, Mix Veg, Chapati, Sweet'),
('Thursday','breakfast','Upma, Coconut Chutney, Tea/Coffee'),
('Thursday','lunch','Rice, Dal Tadka, Bhindi, Chapati'),
('Thursday','snacks','Vada Pav, Tea'),
('Thursday','dinner','Rice, Dal, Egg Curry / Paneer, Chapati'),
('Friday','breakfast','Puri, Aloo Sabzi, Tea/Coffee'),
('Friday','lunch','Rice, Yellow Dal, Jeera Aloo, Chapati'),
('Friday','snacks','Bread Butter, Tea'),
('Friday','dinner','Special Rice, Dal, Sabzi, Chapati, Ice Cream'),
('Saturday','breakfast','Dosa, Chutney, Sambar, Tea/Coffee'),
('Saturday','lunch','Rice, Mix Dal, Palak Paneer, Chapati'),
('Saturday','snacks','Pakora, Tea'),
('Saturday','dinner','Biryani / Pulao, Raita, Dessert'),
('Sunday','breakfast','Chole Bhature, Tea/Coffee'),
('Sunday','lunch','Special Thali (Rice, Dal, 2 Sabzi, Chapati, Sweet)'),
('Sunday','snacks','Momo / Chaat'),
('Sunday','dinner','Rice, Dal, Special Curry, Chapati, Kheer');
