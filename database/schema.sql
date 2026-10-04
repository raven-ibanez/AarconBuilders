-- Aarcon Builders Database Schema
-- Compatible with SQLite and MySQL

CREATE TABLE IF NOT EXISTS checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    check_number VARCHAR(50) NOT NULL UNIQUE,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) DEFAULT '',
    payee VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    issue_date DATE NOT NULL,
    check_date DATE NOT NULL, -- Post-date / Maturity date
    category VARCHAR(100) DEFAULT 'Materials',
    project_name VARCHAR(255) DEFAULT 'General Operations',
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'funded', 'cleared', 'cancelled'
    funded_at DATETIME DEFAULT NULL,
    funded_by VARCHAR(100) DEFAULT NULL,
    remarks TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cash_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    voucher_no VARCHAR(50) NOT NULL UNIQUE,
    requested_by VARCHAR(100) NOT NULL,
    department_or_project VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    purpose TEXT NOT NULL,
    request_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'disbursed'
    approved_at DATETIME DEFAULT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    rejection_reason TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_no VARCHAR(100) NOT NULL UNIQUE,
    client_name VARCHAR(255) NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL, -- 'Cash', 'Bank Deposit', 'Incoming Check', 'Online Transfer'
    collection_date DATE NOT NULL,
    reference_no VARCHAR(100) DEFAULT '',
    received_by VARCHAR(100) DEFAULT 'Main Cashier',
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action_type VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT '',
    role VARCHAR(50) DEFAULT 'accountant', -- 'admin', 'accountant'
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive'
    avatar_color VARCHAR(20) DEFAULT '#0f172a',
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed 2 System Accounts: Admin and Accountant (Pass: admin123, accountant123)
INSERT OR IGNORE INTO users (id, username, password, full_name, email, role, status, avatar_color) VALUES 
(1, 'admin', '$2y$10$JSIM4rLgYaeY0Mqif1TNBOKgSMqy.idlNulcaazm2mOxDGG77ZSOK', 'System Administrator', 'admin@aarconbuilders.com', 'admin', 'active', '#0f172a'),
(2, 'accountant', '$2y$10$KW3MLuKL8LXor87O10Byu.rhstmyshcSvrs/etsnulwyjVaj44The', 'Lead Accountant', 'accountant@aarconbuilders.com', 'accountant', 'active', '#0f172a');

-- Seed System Settings
INSERT OR IGNORE INTO system_settings (setting_key, setting_value) VALUES
('company_name', 'Aarcon Builders'),
('company_tagline', 'Cash, PDC Check & Disbursement Management'),
('currency_symbol', '₱'),
('default_approver', 'Engr. Boss / General Manager'),
('company_address', '123 Construction Blvd, Ortigas Center, Pasig City'),
('company_phone', '+63 (02) 8876-5432'),
('company_email', 'finance@aarconbuilders.com'),
('fiscal_year', '2026'),
('allow_cashier_self_approval', '0');
