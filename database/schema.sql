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
    check_date DATE DEFAULT NULL, -- Post-date / Maturity date (NULL when date unknown yet)
    category VARCHAR(100) DEFAULT 'Materials',
    project_name VARCHAR(255) DEFAULT 'General Operations',
    project_district VARCHAR(100) DEFAULT '',
    voucher_number VARCHAR(100) DEFAULT '',
    priority_status VARCHAR(50) DEFAULT 'Standard', -- 'Hold', 'Prioritized', 'Standard'
    check_type VARCHAR(50) DEFAULT 'Dated Check', -- 'Dated Check', 'Post-Dated Check', 'Pending Date'
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'funded', 'cleared', 'cancelled'
    funded_at DATETIME DEFAULT NULL,
    funded_by VARCHAR(100) DEFAULT NULL,
    remarks TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    custom_fields TEXT DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS cash_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    voucher_no VARCHAR(50) NOT NULL UNIQUE,
    requested_by VARCHAR(100) NOT NULL,
    department_or_project VARCHAR(255) NOT NULL,
    project_district VARCHAR(100) DEFAULT '',
    amount DECIMAL(12, 2) NOT NULL,
    purpose TEXT NOT NULL,
    request_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'disbursed'
    approved_at DATETIME DEFAULT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    rejection_reason TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    custom_fields TEXT DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id VARCHAR(100) NOT NULL DEFAULT '',
    receipt_no VARCHAR(100) NOT NULL UNIQUE,
    client_name VARCHAR(255) NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_district VARCHAR(100) DEFAULT '',
    contract_amount DECIMAL(12, 2) DEFAULT 0,
    net_amount DECIMAL(12, 2) DEFAULT 0,
    tax_amount DECIMAL(12, 2) DEFAULT 0,
    tax_rate DECIMAL(5, 2) DEFAULT 0,
    percentage_collected DECIMAL(5, 2) DEFAULT 0,
    balance_collectible DECIMAL(12, 2) DEFAULT 0,
    amount DECIMAL(12, 2) NOT NULL,
    expected_collection_date DATE DEFAULT NULL,
    deduction DECIMAL(12, 2) DEFAULT 0,
    net_collections DECIMAL(12, 2) DEFAULT 0,
    billing_on_process DECIMAL(12, 2) DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL, -- 'Cash', 'Bank Deposit', 'Incoming Check', 'Online Transfer'
    collection_date DATE NOT NULL,
    reference_no VARCHAR(100) DEFAULT '',
    received_by VARCHAR(100) DEFAULT 'Main Cashier',
    notes TEXT DEFAULT '',
    status VARCHAR(50) DEFAULT 'collected', -- 'collected', 'in process'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    custom_fields TEXT DEFAULT '{}'
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

-- Seed System Accounts: Admin, Accountant (Pass: admin123, accountant123)
INSERT OR IGNORE INTO users (id, username, password, full_name, email, role, status, avatar_color) VALUES 
(1, 'admin', '$2y$10$JSIM4rLgYaeY0Mqif1TNBOKgSMqy.idlNulcaazm2mOxDGG77ZSOK', 'System Administrator', 'admin@aarconbuilders.com', 'admin', 'active', '#0f172a'),
(2, 'accountant', '$2y$10$KW3MLuKL8LXor87O10Byu.rhstmyshcSvrs/etsnulwyjVaj44The', 'Finance Officer', 'accountant@aarconbuilders.com', 'accountant', 'active', '#0f172a');

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

-- CMS: System Banks & Check Routing Codes
CREATE TABLE IF NOT EXISTS system_banks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_name VARCHAR(100) NOT NULL,
    branch VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    account_number VARCHAR(100) DEFAULT '',
    status VARCHAR(20) DEFAULT 'active',
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- CMS: System Dropdown Choices (Project Districts, Categories, Payment Methods, Priority Statuses)
CREATE TABLE IF NOT EXISTS system_dropdowns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category VARCHAR(50) NOT NULL,
    item_key VARCHAR(100) NOT NULL,
    item_label VARCHAR(255) NOT NULL,
    is_default INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- CMS: Dynamic Custom Fields per Module
CREATE TABLE IF NOT EXISTS custom_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    module VARCHAR(50) NOT NULL,
    field_key VARCHAR(50) NOT NULL,
    field_label VARCHAR(100) NOT NULL,
    field_type VARCHAR(30) DEFAULT 'text',
    field_options TEXT DEFAULT '',
    placeholder VARCHAR(150) DEFAULT '',
    is_required INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

