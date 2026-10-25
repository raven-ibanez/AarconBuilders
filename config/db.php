<?php
/**
 * Aarcon Builders - Database Connection & Auto-Migration
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_DRIVER === 'sqlite') {
        $dbDir = dirname(SQLITE_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $needsSeed = !file_exists(SQLITE_FILE);

        try {
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Ensure tables and migrations exist
            $hasUsers = false;
            try {
                $pdo->query("SELECT 1 FROM users LIMIT 1");
                $hasUsers = true;
            } catch (Exception $e) {
                $hasUsers = false;
            }

            if ($needsSeed || !$hasUsers) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }

            // Run incremental schema upgrades for existing databases
            ensure_schema_upgrades($pdo);
        } catch (PDOException $e) {
            die("Database Connection Error (SQLite): " . $e->getMessage());
        }
    } else {
        // MySQL connection
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Ensure tables exist in MySQL
            try {
                $pdo->query("SELECT 1 FROM users LIMIT 1");
            } catch (Exception $e) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }
            ensure_schema_upgrades($pdo);
        } catch (PDOException $e) {
            die("Database Connection Error (MySQL): " . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Perform non-destructive migrations to add any missing columns or seed accounts
 */
function ensure_schema_upgrades(PDO $pdo): void {
    static $upgraded = false;
    if ($upgraded) return;
    $upgraded = true;

    // 1. Column additions for checks
    $checkCols = [
        'project_district' => "VARCHAR(100) DEFAULT ''",
        'voucher_number'   => "VARCHAR(100) DEFAULT ''",
        'priority_status'  => "VARCHAR(50) DEFAULT 'Standard'",
        'check_type'       => "VARCHAR(50) DEFAULT 'Dated Check'",
        'is_reviewed'      => "INTEGER DEFAULT 1", // 0 = Draft/Unreviewed in Voucher module; 1 = Reviewed & pushed to Check module
        'custom_fields'    => "TEXT DEFAULT '{}'"
    ];

    // 2. Column additions for cash_requests
    $cashCols = [
        'project_district' => "VARCHAR(100) DEFAULT ''",
        'custom_fields'    => "TEXT DEFAULT '{}'"
    ];

    // 3. Column additions for collections
    $colCols = [
        'contract_id'              => "VARCHAR(100) DEFAULT ''",
        'net_amount'               => "DECIMAL(12, 2) DEFAULT 0",
        'project_district'         => "VARCHAR(100) DEFAULT ''",
        'contract_amount'          => "DECIMAL(12, 2) DEFAULT 0",
        'tax_amount'               => "DECIMAL(12, 2) DEFAULT 0",
        'tax_rate'                 => "DECIMAL(5, 2) DEFAULT 0",
        'percentage_collected'     => "DECIMAL(5, 2) DEFAULT 0",
        'balance_collectible'      => "DECIMAL(12, 2) DEFAULT 0",
        'expected_collection_date' => "DATE DEFAULT NULL",
        'deduction'                => "DECIMAL(12, 2) DEFAULT 0",
        'net_collections'          => "DECIMAL(12, 2) DEFAULT 0",
        'billing_on_process'       => "DECIMAL(12, 2) DEFAULT 0",
        'status'                   => "VARCHAR(50) DEFAULT 'collected'",
        'custom_fields'            => "TEXT DEFAULT '{}'"
    ];

    $tables = [
        'checks'        => $checkCols,
        'cash_requests' => $cashCols,
        'collections'   => $colCols
    ];

    foreach ($tables as $table => $columns) {
        try {
            $existing = [];
            $info = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($info as $col) {
                $existing[$col['name']] = true;
            }

            foreach ($columns as $name => $def) {
                if (!isset($existing[$name])) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$name} {$def}");
                }
            }
        } catch (Exception $ex) {
            // Silently continue for MySQL or if already upgraded
        }
    }

    // 4. Create CMS Tables (system_banks, system_dropdowns, custom_fields)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_banks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bank_name VARCHAR(100) NOT NULL,
            branch VARCHAR(100) NOT NULL,
            code VARCHAR(20) NOT NULL,
            account_number VARCHAR(100) DEFAULT '',
            status VARCHAR(20) DEFAULT 'active',
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS system_dropdowns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category VARCHAR(50) NOT NULL,
            item_key VARCHAR(100) NOT NULL,
            item_label VARCHAR(255) NOT NULL,
            is_default INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_fields (
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
        )");
    } catch (Exception $ex) {
        // Continue
    }

    // 5. Seed default banks if system_banks is empty
    try {
        $bankCount = (int)$pdo->query("SELECT COUNT(*) FROM system_banks")->fetchColumn();
        if ($bankCount === 0) {
            $defaultBanks = [
                ['001', 'BDO Unibank', 'Pasig - Ortigas Central', '001-9988-7711', 1],
                ['002', 'BDO Unibank', 'Rizal - Antipolo Circumferential', '002-8877-6622', 2],
                ['003', 'BPI', 'Pasig - Ortigas Center Emerald', '003-7766-5533', 3],
                ['004', 'BPI', 'Rizal - Taytay Manila East', '004-6655-4444', 4],
                ['005', 'Metrobank', 'Pasig - Shaw Boulevard', '005-5544-3355', 5],
                ['006', 'Metrobank', 'Rizal - Cainta Junction', '006-4433-2266', 6],
                ['007', 'Landbank of the Philippines', 'Pasig - Capitol Commons', '007-3322-1177', 7],
                ['008', 'Philippine National Bank (PNB)', 'Pasig - San Antonio', '008-2211-0088', 8],
                ['009', 'Security Bank', 'Ortigas Center - ADB Ave', '009-1100-9999', 9],
                ['010', 'UnionBank of the Philippines', 'Pasig - Meralco Ave', '010-0099-8800', 10],
                ['011', 'China Bank', 'Pasig - Kapasigan', '011-9988-7700', 11]
            ];
            $bankStmt = $pdo->prepare("INSERT INTO system_banks (code, bank_name, branch, account_number, sort_order, status) VALUES (?, ?, ?, ?, ?, 'active')");
            foreach ($defaultBanks as $b) {
                $bankStmt->execute($b);
            }
        }
    } catch (Exception $ex) {}

    // 6. Seed default dropdowns if system_dropdowns is empty
    try {
        $ddCount = (int)$pdo->query("SELECT COUNT(*) FROM system_dropdowns")->fetchColumn();
        if ($ddCount === 0) {
            $ddStmt = $pdo->prepare("INSERT INTO system_dropdowns (category, item_key, item_label, sort_order, status) VALUES (?, ?, ?, ?, 'active')");
            
            // Project Districts
            $districts = [
                ['DPWH-RIZAL-2nd', 'DPWH - Rizal 2nd District Engineering Office', 1],
                ['DPWH-RIZAL-1st', 'DPWH - Rizal 1st District Engineering Office', 2],
                ['DPWH-NCR-1st', 'DPWH - NCR 1st District Engineering Office', 3],
                ['DPWH-NCR-2nd', 'DPWH - NCR 2nd District Engineering Office', 4],
                ['DPWH-REGION-IVA', 'DPWH - Region IV-A (CALABARZON)', 5],
                ['DPWH-CENTRAL', 'DPWH - Central Office / Major Projects', 6],
                ['LGU-ANTIPOLO', 'LGU - Antipolo City Infrastructure', 7],
                ['LGU-PASIG', 'LGU - Pasig City Infrastructure', 8],
                ['COMMERCIAL', 'Private Commercial Development', 9],
                ['RESIDENTIAL', 'Private Residential Project', 10],
                ['GENERAL', 'General Operations / Site HQ', 11]
            ];
            foreach ($districts as $d) {
                $ddStmt->execute(['project_districts', $d[0], $d[1], $d[2]]);
            }

            // Expense Categories
            $categories = [
                ['Materials', 'Raw Materials & Hardware Supplies', 1],
                ['Subcontractor', 'Subcontractor Progress Billing', 2],
                ['Labor / Payroll', 'Construction Labor & Site Payroll', 3],
                ['Equipment Rental', 'Heavy Equipment & Machinery Rental', 4],
                ['Fuel & Oil', 'Fuel, Lubricants & Hauling', 5],
                ['Permits & Taxes', 'Government Permits, Licenses & Fees', 6],
                ['Utilities & Overhead', 'Site Utilities & Office Overhead', 7],
                ['Miscellaneous', 'Miscellaneous Jobsite Expenses', 8]
            ];
            foreach ($categories as $c) {
                $ddStmt->execute(['expense_categories', $c[0], $c[1], $c[2]]);
            }

            // Payment Methods
            $methods = [
                ['Bank Deposit', 'Bank Deposit / Over-the-counter', 1],
                ['Cash', 'Cash', 2],
                ['Online Transfer', 'Online Bank Transfer (InstaPay / PESONet)', 3],
                ['Incoming Check', 'Incoming Client Check', 4],
                ['GCash / E-Wallet', 'GCash / Digital E-Wallet', 5]
            ];
            foreach ($methods as $m) {
                $ddStmt->execute(['payment_methods', $m[0], $m[1], $m[2]]);
            }

            // Priority Statuses
            $priorities = [
                ['Standard', 'Standard (Regular Release)', 1],
                ['Prioritized', 'Prioritized (Urgent Release)', 2],
                ['Hold', 'Hold (Do Not Hand Out Yet)', 3]
            ];
            foreach ($priorities as $p) {
                $ddStmt->execute(['priority_statuses', $p[0], $p[1], $p[2]]);
            }
        }
    } catch (Exception $ex) {}

    // 7. Seed sample initial custom fields if custom_fields is empty
    try {
        $cfCount = (int)$pdo->query("SELECT COUNT(*) FROM custom_fields")->fetchColumn();
        if ($cfCount === 0) {
            $cfStmt = $pdo->prepare("INSERT INTO custom_fields (module, field_key, field_label, field_type, field_options, placeholder, is_required, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            // Checks sample field: PO Reference Number
            $cfStmt->execute(['checks', 'po_number', 'PO Reference #', 'text', '', 'e.g. PO-2026-081', 0, 1]);
            // Cash requests sample field: Department Cost Center
            $cfStmt->execute(['cash_requests', 'cost_center', 'Cost Center Code', 'text', '', 'e.g. CC-ENG-04', 0, 1]);
            // Collections sample field: Client Tax TIN
            $cfStmt->execute(['collections', 'client_tin', 'Client TIN #', 'text', '', '000-000-000-000', 0, 1]);
        }
    } catch (Exception $ex) {}

    // Ensure outsourced accountant account exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'outsourced'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $pwd = password_hash('accountant123', PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status, avatar_color) VALUES ('outsourced', ?, 'Outsourced Accountant', 'outsourced@aarconbuilders.com', 'outsourced_accountant', 'active', '#7c3aed')");
            $ins->execute([$pwd]);
        }
    } catch (Exception $ex) {
        // Silently continue
    }

    // Ensure checks.check_date allows NULL in SQLite (for checks with unknown maturity date)
    if (DB_DRIVER === 'sqlite') {
        try {
            $info = $pdo->query("PRAGMA table_info(checks)")->fetchAll(PDO::FETCH_ASSOC);
            $checkDateNotNull = false;
            foreach ($info as $col) {
                if ($col['name'] === 'check_date' && (int)$col['notnull'] === 1) {
                    $checkDateNotNull = true;
                    break;
                }
            }

            if ($checkDateNotNull) {
                $pdo->exec("PRAGMA foreign_keys=OFF;");
                $pdo->beginTransaction();
                $pdo->exec("CREATE TABLE checks_dg_tmp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    check_number VARCHAR(50) NOT NULL UNIQUE,
                    bank_name VARCHAR(100) NOT NULL,
                    account_number VARCHAR(100) DEFAULT '',
                    payee VARCHAR(255) NOT NULL,
                    amount DECIMAL(12, 2) NOT NULL,
                    issue_date DATE NOT NULL,
                    check_date DATE DEFAULT NULL,
                    category VARCHAR(100) DEFAULT 'Materials',
                    project_name VARCHAR(255) DEFAULT 'General Operations',
                    project_district VARCHAR(100) DEFAULT '',
                    voucher_number VARCHAR(100) DEFAULT '',
                    priority_status VARCHAR(50) DEFAULT 'Standard',
                    check_type VARCHAR(50) DEFAULT 'Dated Check',
                    status VARCHAR(50) DEFAULT 'pending',
                    funded_at DATETIME DEFAULT NULL,
                    funded_by VARCHAR(100) DEFAULT NULL,
                    remarks TEXT DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_reviewed INTEGER DEFAULT 1
                );");
                $pdo->exec("INSERT INTO checks_dg_tmp (id, check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, voucher_number, priority_status, check_type, status, funded_at, funded_by, remarks, created_at, is_reviewed)
                            SELECT id, check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, voucher_number, priority_status, check_type, status, funded_at, funded_by, remarks, created_at, COALESCE(is_reviewed, 1) FROM checks;");
                $pdo->exec("DROP TABLE checks;");
                $pdo->exec("ALTER TABLE checks_dg_tmp RENAME TO checks;");
                $pdo->commit();
                $pdo->exec("PRAGMA foreign_keys=ON;");
            }
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}


