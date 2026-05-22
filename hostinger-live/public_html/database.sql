CREATE DATABASE IF NOT EXISTS srm_db;
USE srm_db;

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    parent_id INT NULL,
    default_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_services_parent FOREIGN KEY (parent_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_services_parent (parent_id)
);

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    recorded_by_user_id INT NULL,
    customer_name VARCHAR(120) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','M-Pesa','Card','Bank Transfer','Credit') NOT NULL DEFAULT 'Cash',
    sale_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_service FOREIGN KEY (service_id) REFERENCES services(id),
    INDEX idx_sales_user (recorded_by_user_id)
);

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL DEFAULT 'Shakesbeard CYBER',
    currency_symbol VARCHAR(10) NOT NULL DEFAULT 'KES',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'attendant') NOT NULL DEFAULT 'attendant',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_profiles (
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
);

SET @sales_user_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sales'
      AND CONSTRAINT_NAME = 'fk_sales_user'
);
SET @sales_user_fk_sql := IF(
    @sales_user_fk_exists = 0,
    'ALTER TABLE sales ADD CONSTRAINT fk_sales_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE sales_user_fk_stmt FROM @sales_user_fk_sql;
EXECUTE sales_user_fk_stmt;
DEALLOCATE PREPARE sales_user_fk_stmt;

INSERT INTO settings (business_name, currency_symbol)
SELECT 'Shakesbeard CYBER', 'KES'
WHERE NOT EXISTS (SELECT 1 FROM settings);

INSERT INTO users (username, password_hash, role, is_active)
VALUES
('admin', '$2y$10$epV0MVOA52v2r1tiIAmjjOqPZT62hoA7oq6ADbpVcsvUvWNlBME.K', 'admin', 1),
('attendant', '$2y$10$WIZM1XbNQAzfPJ4rsV/Vuef1J3Hn5Kk1wuxnVCI/Vu93UjDrZVUYi', 'attendant', 1)
ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = VALUES(is_active);

INSERT INTO user_profiles (user_id, full_name, email, phone, hire_date, employment_type, salary_amount, salary_cycle, work_days, off_days)
SELECT u.id, 'System Administrator', 'admin@example.com', '', CURDATE(), 'Full-time', 0.00, 'Monthly', 'Mon-Fri', 'Sat-Sun'
FROM users u
WHERE u.username = 'admin'
  AND NOT EXISTS (SELECT 1 FROM user_profiles p WHERE p.user_id = u.id);

INSERT INTO user_profiles (user_id, full_name, email, phone, hire_date, employment_type, salary_amount, salary_cycle, work_days, off_days)
SELECT u.id, 'Cyber Attendant', 'attendant@example.com', '', CURDATE(), 'Full-time', 0.00, 'Monthly', 'Mon-Sat', 'Sun'
FROM users u
WHERE u.username = 'attendant'
  AND NOT EXISTS (SELECT 1 FROM user_profiles p WHERE p.user_id = u.id);

INSERT INTO services (name, parent_id, default_price)
VALUES
('Printing', NULL, 10.00),
('Scanning', NULL, 50.00),
('Photocopy', NULL, 10.00),
('Browsing', NULL, 100.00),
('Gaming', NULL, 150.00),
('Photography', NULL, 500.00),
('Binding', NULL, 150.00),
('Branding', NULL, 1000.00),
('Lamination', NULL, 100.00),
('E-citizen Services', NULL, 300.00),
('Computer Training', NULL, 5000.00),
('Computer Repairs', NULL, 1000.00)
ON DUPLICATE KEY UPDATE
    parent_id = VALUES(parent_id),
    default_price = VALUES(default_price);
