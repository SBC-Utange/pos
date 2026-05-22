<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

date_default_timezone_set($config['timezone']);

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

if ($mysqli->connect_errno) {
    http_response_code(500);
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

// Runtime migration: ensure hierarchical services support exists.
$columnCheck = $mysqli->query("SHOW COLUMNS FROM services LIKE 'parent_id'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE services ADD COLUMN parent_id INT NULL AFTER name');
    $mysqli->query('ALTER TABLE services ADD INDEX idx_services_parent (parent_id)');
}

$constraintCheckSql = "SELECT 1
                       FROM information_schema.TABLE_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA = DATABASE()
                         AND TABLE_NAME = 'services'
                         AND CONSTRAINT_NAME = 'fk_services_parent'
                       LIMIT 1";
$constraintCheck = $mysqli->query($constraintCheckSql);
if ($constraintCheck && $constraintCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE services ADD CONSTRAINT fk_services_parent FOREIGN KEY (parent_id) REFERENCES services(id) ON DELETE SET NULL');
}

// Runtime migration: ensure discounts support exists for sales.
$discountColumnCheck = $mysqli->query("SHOW COLUMNS FROM sales LIKE 'discount_amount'");
if ($discountColumnCheck && $discountColumnCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER unit_price');
}

$recordedByColumnCheck = $mysqli->query("SHOW COLUMNS FROM sales LIKE 'recorded_by_user_id'");
if ($recordedByColumnCheck && $recordedByColumnCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE sales ADD COLUMN recorded_by_user_id INT NULL AFTER service_id');
}

$salesUserIndexCheck = $mysqli->query("SHOW INDEX FROM sales WHERE Key_name = 'idx_sales_user'");
if ($salesUserIndexCheck && $salesUserIndexCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE sales ADD INDEX idx_sales_user (recorded_by_user_id)');
}

// Runtime migration: authentication and staff profile tables.
$mysqli->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'attendant') NOT NULL DEFAULT 'attendant',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$mysqli->query("CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    next_of_kin VARCHAR(150) NULL,
    next_of_kin_phone VARCHAR(40) NULL,
    hire_date DATE NULL,
    employment_type ENUM('Full-time', 'Part-time', 'Contract') NOT NULL DEFAULT 'Full-time',
    salary_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    salary_cycle ENUM('Daily', 'Weekly', 'Monthly') NOT NULL DEFAULT 'Monthly',
    overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shift_start TIME NULL,
    shift_end TIME NULL,
    work_days VARCHAR(120) NULL,
    off_days VARCHAR(120) NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$adminHash = '$2y$10$epV0MVOA52v2r1tiIAmjjOqPZT62hoA7oq6ADbpVcsvUvWNlBME.K';
$attendantHash = '$2y$10$WIZM1XbNQAzfPJ4rsV/Vuef1J3Hn5Kk1wuxnVCI/Vu93UjDrZVUYi';

$stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, role, is_active)
                          VALUES (?, ?, ?, 1)
                          ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = VALUES(is_active)");
$adminUsername = 'admin';
$adminRole = 'admin';
$stmt->bind_param('sss', $adminUsername, $adminHash, $adminRole);
$stmt->execute();
$stmt->close();

$stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, role, is_active)
                          VALUES (?, ?, ?, 1)
                          ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = VALUES(is_active)");
$attendantUsername = 'attendant';
$attendantRole = 'attendant';
$stmt->bind_param('sss', $attendantUsername, $attendantHash, $attendantRole);
$stmt->execute();
$stmt->close();

$stmt = $mysqli->prepare('SELECT id, username FROM users WHERE username IN (?, ?)');
$stmt->bind_param('ss', $adminUsername, $attendantUsername);
$stmt->execute();
$result = $stmt->get_result();
$userMap = [];
while ($row = $result->fetch_assoc()) {
    $userMap[$row['username']] = (int) $row['id'];
}
$stmt->close();

if (isset($userMap['admin'])) {
    $adminUserId = $userMap['admin'];
    $stmt = $mysqli->prepare("INSERT INTO user_profiles (user_id, full_name, email, phone, hire_date, employment_type, salary_amount, salary_cycle, work_days, off_days)
                              VALUES (?, 'System Administrator', 'admin@example.com', '', CURDATE(), 'Full-time', 0.00, 'Monthly', 'Mon-Fri', 'Sat-Sun')
                              ON DUPLICATE KEY UPDATE full_name = full_name");
    $stmt->bind_param('i', $adminUserId);
    $stmt->execute();
    $stmt->close();
}

if (isset($userMap['attendant'])) {
    $attendantUserId = $userMap['attendant'];
    $stmt = $mysqli->prepare("INSERT INTO user_profiles (user_id, full_name, email, phone, hire_date, employment_type, salary_amount, salary_cycle, work_days, off_days)
                              VALUES (?, 'Cyber Attendant', 'attendant@example.com', '', CURDATE(), 'Full-time', 0.00, 'Monthly', 'Mon-Sat', 'Sun')
                              ON DUPLICATE KEY UPDATE full_name = full_name");
    $stmt->bind_param('i', $attendantUserId);
    $stmt->execute();
    $stmt->close();
}

$salesUserConstraintCheckSql = "SELECT 1
                                FROM information_schema.TABLE_CONSTRAINTS
                                WHERE CONSTRAINT_SCHEMA = DATABASE()
                                  AND TABLE_NAME = 'sales'
                                  AND CONSTRAINT_NAME = 'fk_sales_user'
                                LIMIT 1";
$salesUserConstraintCheck = $mysqli->query($salesUserConstraintCheckSql);
if ($salesUserConstraintCheck && $salesUserConstraintCheck->num_rows === 0) {
    $mysqli->query('ALTER TABLE sales ADD CONSTRAINT fk_sales_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL');
}
