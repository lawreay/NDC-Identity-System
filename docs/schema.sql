CREATE DATABASE IF NOT EXISTS nbs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nbs_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Administrator', 'Finance Officer') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(50) NOT NULL,
    photo_path VARCHAR(255) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    district VARCHAR(100) NULL,
    traditional_authority VARCHAR(100) NULL,
    village VARCHAR(100) NULL,
    phone_number VARCHAR(20) NULL,
    qualification VARCHAR(100) NULL,
    program VARCHAR(150) NULL,
    class_level VARCHAR(80) NULL,
    billing_category ENUM('Formal', 'Informal') NOT NULL DEFAULT 'Formal',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY students_student_number_unique (student_number),
    KEY students_phone_number_index (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(40) NOT NULL,
    student_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    status ENUM('Draft', 'Issued', 'Cancelled') NOT NULL DEFAULT 'Issued',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY invoices_invoice_number_unique (invoice_number),
    KEY invoices_student_id_index (student_id),
    KEY invoices_created_by_index (created_by),
    CONSTRAINT invoices_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT invoices_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY invoice_items_invoice_id_index (invoice_id),
    CONSTRAINT invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    percentage_default DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY sponsors_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY settings_setting_key_unique (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY password_resets_token_hash_unique (token_hash),
    KEY password_resets_user_id_index (user_id),
    KEY password_resets_expires_at_index (expires_at),
    CONSTRAINT password_resets_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(80) NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY role_permissions_unique (role_name, permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_id_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    qr_code_path VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_card (student_id),
    KEY student_id_cards_expiry_date_index (expiry_date),
    CONSTRAINT student_id_cards_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY expense_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_to VARCHAR(190) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    payment_method VARCHAR(80) NULL,
    reference_number VARCHAR(120) NULL,
    notes TEXT NULL,
    is_reversed TINYINT(1) NOT NULL DEFAULT 0,
    reversal_reason TEXT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY expenses_expense_date_index (expense_date),
    KEY expenses_category_id_index (category_id),
    KEY expenses_recorded_by_index (recorded_by),
    CONSTRAINT expenses_category_id_foreign FOREIGN KEY (category_id) REFERENCES expense_categories (id),
    CONSTRAINT expenses_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    position VARCHAR(120) NULL,
    start_date DATE NULL,
    monthly_pay_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY employees_full_name_index (full_name),
    KEY employees_email_index (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payment_month CHAR(7) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(80) NULL,
    reference_number VARCHAR(120) NULL,
    notes TEXT NULL,
    is_reversed TINYINT(1) NOT NULL DEFAULT 0,
    reversal_reason TEXT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY employee_payments_employee_id_index (employee_id),
    KEY employee_payments_payment_month_index (payment_month),
    KEY employee_payments_payment_date_index (payment_date),
    KEY employee_payments_recorded_by_index (recorded_by),
    CONSTRAINT employee_payments_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES employees (id),
    CONSTRAINT employee_payments_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(190) NOT NULL,
    recipient_type VARCHAR(80) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    related_type VARCHAR(80) NULL,
    related_id INT NULL,
    status ENUM('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    KEY email_notifications_status_index (status),
    KEY email_notifications_related_index (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS balance_override_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    new_balance DECIMAL(12,2) NOT NULL,
    reason TEXT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    CONSTRAINT balance_override_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS balance_override_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_balance DECIMAL(12,2) NOT NULL,
    new_balance DECIMAL(12,2) NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT balance_override_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('organization_name', 'Ntcheu Development Center'),
('organization_address', 'Ntcheu, Malawi'),
('organization_email', 'finance@ndc.local'),
('organization_phone', ''),
('organization_website', ''),
('organization_logo_path', 'C:/wamp64/www/NDC Financial Management System/public/assets/img/logo.svg'),
('invoice_prefix', 'NDC'),
('billing_formal_amount', '120000'),
('billing_informal_amount', '45000'),
('qualification_formal_mapping', 'MSCE'),
('qualification_informal_mapping', 'JCE'),
('academic_programs', 'Business Studies
ICT/Digital Skills'),
('principal_signature_name', 'Principal'),
('principal_signature_path', ''),
('smtp_enabled', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', 'finance@ndc.local'),
('smtp_from_name', 'Ntcheu Development Center'),
('maintenance_mode', '0'),
('maintenance_message', 'We are updating the billing system. Please check back soon.'),
('accounts_notification_emails', ''),
('notify_admins_finance', '1'),
('notify_employee_payments', '1'),
('notify_new_expenses', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO expense_categories (name) VALUES
('Salaries'),
('Allowances'),
('Fuel'),
('Stationery'),
('Utilities'),
('Training Materials'),
('Maintenance'),
('Other')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO role_permissions (role_name, permission_key) VALUES
('Administrator', 'dashboard.view'),
('Administrator', 'students.view'),
('Administrator', 'students.create'),
('Administrator', 'students.update'),
('Administrator', 'student_ids.manage'),
('Administrator', 'invoices.view'),
('Administrator', 'invoices.create'),
('Administrator', 'invoices.edit'),
('Administrator', 'finance.view'),
('Administrator', 'finance.manage'),
('Administrator', 'reports.view'),
('Administrator', 'settings.manage'),
('Administrator', 'users.manage'),
('Administrator', 'sponsors.view'),
('Finance Officer', 'dashboard.view'),
('Finance Officer', 'students.view'),
('Finance Officer', 'student_ids.manage'),
('Finance Officer', 'invoices.view'),
('Finance Officer', 'invoices.create'),
('Finance Officer', 'finance.view'),
('Finance Officer', 'finance.manage'),
('Finance Officer', 'reports.view')
ON DUPLICATE KEY UPDATE permission_key = VALUES(permission_key);
