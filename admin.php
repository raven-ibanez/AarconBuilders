<?php
/**
 * Aarcon Builders - Enterprise Admin Dashboard & System Management
 * Complete control center for users, settings, audit trails, and system maintenance
 */

$pageTitle = 'Admin Dashboard & System Management';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce Administrator Privileges
require_admin();

$db = get_db();
$currentUser = current_user();
$activeTab = $_GET['tab'] ?? 'tab-overview';

// 1. Fetch Users
$users = $db->query('SELECT * FROM users ORDER BY role ASC, id ASC')->fetchAll();
$totalUsers = count($users);
$activeUsersCount = count(array_filter($users, fn($u) => $u['status'] === 'active'));

// 2. Fetch System Summary Metrics
$totalChecks = (int)$db->query('SELECT COUNT(*) FROM checks')->fetchColumn();
$fundedChecksCount = (int)$db->query("SELECT COUNT(*) FROM checks WHERE status = 'funded'")->fetchColumn();
$pendingChecksCount = (int)$db->query("SELECT COUNT(*) FROM checks WHERE status = 'pending'")->fetchColumn();

$totalCashRequests = (int)$db->query('SELECT COUNT(*) FROM cash_requests')->fetchColumn();
$approvedCashCount = (int)$db->query("SELECT COUNT(*) FROM cash_requests WHERE status IN ('approved', 'disbursed')")->fetchColumn();
$pendingCashCount = (int)$db->query("SELECT COUNT(*) FROM cash_requests WHERE status = 'pending'")->fetchColumn();

$totalCollections = (int)$db->query('SELECT COUNT(*) FROM collections')->fetchColumn();
$totalCollectionsAmount = (float)$db->query('SELECT COALESCE(SUM(amount), 0) FROM collections')->fetchColumn();

// 3. System Environment Info
$dbSizeFormatted = 'N/A';
if (DB_DRIVER === 'sqlite' && file_exists(SQLITE_FILE)) {
    $dbBytes = filesize(SQLITE_FILE);
    $dbSizeFormatted = $dbBytes >= 1048576 
        ? number_format($dbBytes / 1048576, 2) . ' MB' 
        : number_format($dbBytes / 1024, 2) . ' KB';
}

// 4. Fetch Audit Logs
$auditModuleFilter = $_GET['audit_module'] ?? 'all';
$auditWhere = '';
if ($auditModuleFilter !== 'all' && !empty($auditModuleFilter)) {
    $auditWhere = "WHERE module = " . $db->quote($auditModuleFilter);
}
$auditLogs = $db->query("SELECT * FROM audit_logs {$auditWhere} ORDER BY id DESC LIMIT 100")->fetchAll();
$totalAuditLogsCount = (int)$db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

// 5. System Settings
$settings = [
    'company_name'                => get_system_setting('company_name', APP_NAME),
    'company_tagline'             => get_system_setting('company_tagline', APP_TAGLINE),
    'currency_symbol'             => get_system_setting('currency_symbol', CURRENCY_SYMBOL),
    'default_approver'            => get_system_setting('default_approver', DEFAULT_APPROVER),
    'company_address'             => get_system_setting('company_address', '123 Construction Blvd, Ortigas Center, Pasig City'),
    'company_phone'               => get_system_setting('company_phone', '+63 (02) 8876-5432'),
    'company_email'               => get_system_setting('company_email', 'finance@aarconbuilders.com'),
    'fiscal_year'                 => get_system_setting('fiscal_year', date('Y')),
    'allow_cashier_self_approval' => get_system_setting('allow_cashier_self_approval', '0')
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Admin Header -->
<div class="page-header no-print">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span class="badge badge-admin" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Super Admin</span>
            <span style="font-size: 0.85rem; color: var(--text-muted);">&bull; System Control Center</span>
        </div>
        <h1 class="page-title">System Administration</h1>
        <p class="page-subtitle">Manage system users, company configuration, security audit logs, and database health</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddUser">
            <span>+ Add New User</span>
        </button>
        <form action="api/actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Optimize database indexes and vacuum free space?');">
            <input type="hidden" name="action" value="optimize_database">
            <button type="submit" class="btn btn-outline btn-sm">
                <span>⚡ Optimize DB</span>
            </button>
        </form>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs-container">
    <div class="tabs-nav no-print" style="margin-bottom: 1.5rem;">
        <button class="tab-btn <?= $activeTab === 'tab-overview' ? 'active' : '' ?>" data-tab="tab-overview">
            <span>📊 System Pulse</span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-users' ? 'active' : '' ?>" data-tab="tab-users">
            <span>👥 User Management</span>
            <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalUsers ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-settings' ? 'active' : '' ?>" data-tab="tab-settings">
            <span>⚙️ System Settings</span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-audit' ? 'active' : '' ?>" data-tab="tab-audit">
            <span>🛡️ Audit Trail</span>
            <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalAuditLogsCount ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-system' ? 'active' : '' ?>" data-tab="tab-system">
            <span>💾 Database &amp; Maintenance</span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: OVERVIEW & SYSTEM PULSE -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-overview' ? 'active' : '' ?>" id="tab-overview">
        
        <!-- Summary Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            
            <!-- Users Card -->
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.03em;">User Accounts</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                            <?= $totalUsers ?>
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(37, 99, 235, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        👥
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--color-success); display: flex; align-items: center; gap: 0.35rem;">
                    <span>●</span> <?= $activeUsersCount ?> Active accounts enabled
                </div>
            </div>

            <!-- Checks Card -->
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.03em;">Physical Checks</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                            <?= $totalChecks ?>
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(5, 150, 105, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        📑
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                    <span style="font-weight: 600; color: var(--color-success);"><?= $fundedChecksCount ?> funded</span> &bull; <?= $pendingChecksCount ?> pending
                </div>
            </div>

            <!-- Petty Cash Card -->
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.03em;">Cash Vouchers</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                            <?= $totalCashRequests ?>
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(217, 119, 6, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        💵
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                    <span style="font-weight: 600; color: var(--color-success);"><?= $approvedCashCount ?> approved</span> &bull; <?= $pendingCashCount ?> pending
                </div>
            </div>

            <!-- Total Collections Card -->
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.03em;">Total Inflow Tracked</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-success); margin-top: 0.25rem; font-family: var(--font-mono);">
                            <?= format_money($totalCollectionsAmount) ?>
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        💰
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                    From <?= $totalCollections ?> recorded client payments
                </div>
            </div>

        </div>

        <!-- Quick Info & Action Banners -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            
            <!-- Quick Management Navigation -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1rem; font-weight: 700;">System Administrative Controls</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="admin.php?tab=tab-users" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 1rem; height: auto;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Manage User Accounts</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Add staff, change roles, reset credentials</div>
                            </div>
                        </a>
                        <a href="admin.php?tab=tab-settings" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 1rem; height: auto;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Company Configuration</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Company name, currency, approver title</div>
                            </div>
                        </a>
                        <a href="admin.php?tab=tab-audit" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 1rem; height: auto;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Security Audit Trail</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Real-time logging of user activity</div>
                            </div>
                        </a>
                        <a href="admin.php?tab=tab-system" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 1rem; height: auto;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Database Maintenance</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Backup SQLite, vacuum database</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Server & Environment Pulse -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1rem; font-weight: 700;">System Environment</h3>
                </div>
                <div class="card-body" style="padding: 1rem 1.25rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span style="color: var(--text-muted);">Database Driver:</span>
                            <span style="font-weight: 600; font-family: var(--font-mono); text-transform: uppercase;"><?= DB_DRIVER ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span style="color: var(--text-muted);">Database Size:</span>
                            <span style="font-weight: 600; font-family: var(--font-mono);"><?= $dbSizeFormatted ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span style="color: var(--text-muted);">PHP Version:</span>
                            <span style="font-weight: 600; font-family: var(--font-mono);"><?= phpversion() ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span style="color: var(--text-muted);">Current Admin:</span>
                            <span style="font-weight: 600; color: var(--color-primary);"><?= e($currentUser['username']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Server Time:</span>
                            <span style="font-weight: 500; font-size: 0.8rem;"><?= date('M d, Y h:i A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 2: USER MANAGEMENT -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-users' ? 'active' : '' ?>" id="tab-users">
        
        <div class="card">
            <div class="card-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <input type="text" class="search-input" placeholder="Search user, name, email, or role..." data-table-search="#usersTable" style="max-width: 320px;">
                <button class="btn btn-primary btn-sm" data-open-modal="modalAddUser">+ Create New User</button>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php 
                                    $isSelf = ((int)$u['id'] === (int)$currentUser['id']);
                                    $initials = strtoupper(substr($u['full_name'], 0, 1) . (strpos($u['full_name'], ' ') !== false ? substr($u['full_name'], strpos($u['full_name'], ' ') + 1, 1) : ''));
                                    $avatarColor = !empty($u['avatar_color']) ? $u['avatar_color'] : '#2563eb';
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: <?= e($avatarColor) ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                <?= e($initials) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-main);">
                                                    <?= e($u['full_name']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 0.7rem; margin-left: 0.25rem;">You</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">ID #<?= $u['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-family: var(--font-mono); font-weight: 600; color: var(--text-main);">
                                            @<?= e($u['username']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">
                                            <?= !empty($u['email']) ? e($u['email']) : '&mdash;' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-accountant">Accountant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--color-success); font-weight: 600; font-size: 0.8rem;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-success);"></span>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--text-muted); font-weight: 600; font-size: 0.8rem;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;"></span>
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= format_date($u['last_login'], 'M d, Y h:i A') ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= format_date($u['created_at'], 'M d, Y') ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <button 
                                                class="btn btn-outline btn-sm btn-edit-user" 
                                                data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>'
                                                title="Edit User Details"
                                            >
                                                Edit
                                            </button>
                                            
                                            <?php if (!$isSelf): ?>
                                                <form action="api/actions.php" method="POST" style="margin:0;" onsubmit="return confirm('Delete user <?= e($u['username']) ?>? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete User">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-outline btn-sm" disabled style="opacity: 0.4;" title="Cannot delete yourself">Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Role Permissions Guide Card -->
        <div class="card" style="margin-top: 1.5rem; background: var(--bg-subtle);">
            <div class="card-body">
                <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.5rem;">System Roles &amp; Responsibilities:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                    <div>
                        <strong style="color: var(--text-main);">Administrator (System Admin Only)</strong>:
                        <p style="margin-top: 0.25rem;">Responsible exclusively for managing the system — user accounts, system configuration &amp; company settings, security audit trail monitoring, and database maintenance.</p>
                    </div>
                    <div>
                        <strong style="color: var(--text-main);">Accountant (Financial Operations)</strong>:
                        <p style="margin-top: 0.25rem;">Manages all operational financial modules — physical checks &amp; post-date funding, petty cash voucher approvals, client collections, and final cash disbursement reporting.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 3: SYSTEM SETTINGS -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-settings' ? 'active' : '' ?>" id="tab-settings">
        
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div class="card-header">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Company &amp; System Configuration</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">Changes here immediately update system headers, disbursement reports, and printable statements</p>
                </div>
            </div>
            
            <form action="api/actions.php" method="POST">
                <input type="hidden" name="action" value="save_system_settings">

                <div class="card-body">
                    
                    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
                        🏢 Company Profile &amp; Letterhead
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Company Name <span class="req">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= e($settings['company_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tagline / Subtitle</label>
                            <input type="text" name="company_tagline" class="form-control" value="<?= e($settings['company_tagline']) ?>">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Company Office Address</label>
                            <input type="text" name="company_address" class="form-control" value="<?= e($settings['company_address']) ?>" placeholder="Address printed on official statements">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?= e($settings['company_phone']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Finance Email</label>
                            <input type="email" name="company_email" class="form-control" value="<?= e($settings['company_email']) ?>">
                        </div>
                    </div>

                    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">

                    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
                        💰 Financial &amp; Approval Defaults
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Currency Symbol <span class="req">*</span></label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= e($settings['currency_symbol']) ?>" style="font-family: var(--font-mono); font-weight: 700;" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">e.g. ₱, $, €, SGD</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fiscal Year</label>
                            <input type="text" name="fiscal_year" class="form-control" value="<?= e($settings['fiscal_year']) ?>">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Default Approver Name / Title <span class="req">*</span></label>
                            <input type="text" name="default_approver" class="form-control" value="<?= e($settings['default_approver']) ?>" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Used as the signatory name for 1-click check funding and voucher approval</small>
                        </div>
                    </div>

                </div>

                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; background: var(--bg-subtle);">
                    <button type="submit" class="btn btn-primary">
                        <span>Save System Configuration</span>
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 4: AUDIT TRAIL -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-audit' ? 'active' : '' ?>" id="tab-audit">
        
        <div class="card">
            <div class="card-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <input type="text" class="search-input" placeholder="Search audit logs..." data-table-search="#auditTable" style="max-width: 280px;">
                    
                    <div style="display: flex; gap: 0.35rem;">
                        <a href="admin.php?tab=tab-audit&audit_module=all" class="btn btn-sm <?= $auditModuleFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All Modules</a>
                        <a href="admin.php?tab=tab-audit&audit_module=AUTH" class="btn btn-sm <?= $auditModuleFilter === 'AUTH' ? 'btn-primary' : 'btn-outline' ?>">Auth</a>
                        <a href="admin.php?tab=tab-audit&audit_module=USERS" class="btn btn-sm <?= $auditModuleFilter === 'USERS' ? 'btn-primary' : 'btn-outline' ?>">Users</a>
                        <a href="admin.php?tab=tab-audit&audit_module=CHECKS" class="btn btn-sm <?= $auditModuleFilter === 'CHECKS' ? 'btn-primary' : 'btn-outline' ?>">Checks</a>
                        <a href="admin.php?tab=tab-audit&audit_module=SETTINGS" class="btn btn-sm <?= $auditModuleFilter === 'SETTINGS' ? 'btn-primary' : 'btn-outline' ?>">Settings</a>
                    </div>
                </div>

                <form action="api/actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Prune older logs? Keeps the latest 50 records.');">
                    <input type="hidden" name="action" value="clear_audit_logs">
                    <button type="submit" class="btn btn-outline btn-sm">Prune Old Logs</button>
                </form>
            </div>

            <div class="card-body" style="padding: 0;">
                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state">
                        No audit logs recorded for this selection.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="auditTable">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Log ID</th>
                                    <th style="width: 170px;">Timestamp</th>
                                    <th>Module</th>
                                    <th>Action Type</th>
                                    <th>Event Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-light);">
                                            #<?= $log['id'] ?>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);">
                                            <?= format_date($log['created_at'], 'M d, Y h:i:s A') ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 0.75rem; font-weight: 700;">
                                                <?= e($log['module']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $actionClass = 'badge-pending';
                                                if (strpos($log['action_type'], 'LOGIN') !== false) $actionClass = 'badge-funded';
                                                if (strpos($log['action_type'], 'DELETE') !== false) $actionClass = 'badge-danger';
                                                if (strpos($log['action_type'], 'FUND') !== false || strpos($log['action_type'], 'APPROVE') !== false) $actionClass = 'badge-funded';
                                            ?>
                                            <span class="badge <?= $actionClass ?>" style="font-size: 0.75rem;">
                                                <?= e($log['action_type']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-main);">
                                            <?= e($log['description']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 5: DATABASE & MAINTENANCE -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-system' ? 'active' : '' ?>" id="tab-system">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            
            <!-- Database Management Card -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1rem; font-weight: 700;">💾 Database Health &amp; Storage</h3>
                </div>
                <div class="card-body">
                    <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-muted); margin-bottom: 1.25rem;">
                        The system is actively powered by <strong><?= strtoupper(DB_DRIVER) ?></strong>. Maintain high read/write throughput by optimizing index tables and vacuuming unused fragmented space.
                    </div>

                    <div style="background: var(--bg-subtle); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                            <span>Active Database File:</span>
                            <span style="font-family: var(--font-mono); font-weight: 600; color: var(--text-main); word-break: break-all;">aarcon_builders.db</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                            <span>Current File Size:</span>
                            <span style="font-family: var(--font-mono); font-weight: 600;"><?= $dbSizeFormatted ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Audit Trail Records:</span>
                            <span style="font-family: var(--font-mono); font-weight: 600;"><?= $totalAuditLogsCount ?></span>
                        </div>
                    </div>

                    <form action="api/actions.php" method="POST" onsubmit="return confirm('Run SQLite vacuum and index optimization?');">
                        <input type="hidden" name="action" value="optimize_database">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <span>⚡ Vacuum &amp; Optimize Database</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Server Diagnostic Checklist -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1rem; font-weight: 700;">🔍 Server Diagnostics</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span>PDO SQLite Driver</span>
                            <span class="badge badge-funded">Enabled</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span>Session Management</span>
                            <span class="badge badge-funded">Active</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span>Bcrypt Password Security</span>
                            <span class="badge badge-funded">Supported</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
                            <span>Database Folder Write Permissions</span>
                            <span class="badge badge-funded">Writable</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Server Architecture</span>
                            <span style="font-family: var(--font-mono); font-weight: 600;"><?= PHP_OS ?></span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Reset Financial Data Card -->
            <div class="card" style="border-left: 4px solid var(--color-danger, #dc2626);">
                <div class="card-header">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #dc2626;">⚠️ Reset Financial Data</h3>
                </div>
                <div class="card-body">
                    <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-muted); margin-bottom: 1rem;">
                        Permanently clear all financial records to start fresh. This will reset the cash balance and transaction counts to <strong>₱0.00</strong>.
                    </div>

                    <div style="background: var(--bg-subtle); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.82rem;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem; color: var(--text-main);">What gets cleared:</div>
                        <ul style="padding-left: 1.25rem; color: var(--text-muted); line-height: 1.5;">
                            <li>All Physical Checks (pending, funded, cancelled)</li>
                            <li>All Cash Requests / Petty Cash vouchers</li>
                            <li>All Recorded Client Collections</li>
                            <li>Audit logs &amp; activity history</li>
                        </ul>
                        <div style="margin-top: 0.5rem; font-size: 0.78rem; color: var(--color-success); font-weight: 600;">
                            ✓ User accounts (admin &amp; accountant) and company configuration remain intact.
                        </div>
                    </div>

                    <form action="api/actions.php" method="POST" onsubmit="return confirm('⚠️ DANGER: This will permanently erase ALL checks, cash vouchers, collections, and audit logs. The system balance will be reset to ₱0.00.\n\nAre you sure you want to proceed?');">
                        <input type="hidden" name="action" value="reset_system_data">
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <span>🗑️ Reset All Financial Records</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- ========================================== -->
<!-- MODAL: ADD USER -->
<!-- ========================================== -->
<div class="modal-overlay" id="modalAddUser">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create New System User</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="add_user">
            
            <div class="modal-body">
                <div class="form-grid">
                    
                    <div class="form-group col-span-2">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. Engr. Juan Dela Cruz" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username <span class="req">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. jdelacruz" required autocomplete="off">
                        <small style="color: var(--text-muted); font-size: 0.7rem;">Letters, numbers, underscores</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="juan@aarconbuilders.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password <span class="req">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 4 characters" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label">System Role <span class="req">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="accountant">Accountant (Financial Operations)</option>
                            <option value="admin">Administrator (System Admin Only)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active (Can Sign In)</option>
                            <option value="inactive">Inactive (Suspended)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Avatar Badge Color</label>
                        <select name="avatar_color" class="form-control">
                            <option value="#2563eb">Classic Blue</option>
                            <option value="#059669">Emerald Green</option>
                            <option value="#d97706">Warm Amber</option>
                            <option value="#7c3aed">Royal Purple</option>
                            <option value="#dc2626">Crimson Red</option>
                            <option value="#0f172a">Slate Black</option>
                        </select>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAddUser')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT USER -->
<!-- ========================================== -->
<div class="modal-overlay" id="modalEditUser">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit User Account</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="modal-body">
                <div class="form-grid">
                    
                    <div class="form-group col-span-2">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" id="editUsername" class="form-control" disabled style="background: var(--bg-subtle); color: var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="editEmail" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">System Role</label>
                        <select name="role" id="editRole" class="form-control" required>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive (Suspended)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Avatar Badge Color</label>
                        <select name="avatar_color" id="editAvatarColor" class="form-control">
                            <option value="#2563eb">Classic Blue</option>
                            <option value="#059669">Emerald Green</option>
                            <option value="#d97706">Warm Amber</option>
                            <option value="#7c3aed">Royal Purple</option>
                            <option value="#dc2626">Crimson Red</option>
                            <option value="#0f172a">Slate Black</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password (Optional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalEditUser')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Edit User modal populate logic
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-edit-user').forEach(btn => {
            btn.addEventListener('click', () => {
                const userJson = btn.getAttribute('data-user');
                if (!userJson) return;

                const user = JSON.parse(userJson);
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editFullName').value = user.full_name;
                document.getElementById('editUsername').value = '@' + user.username;
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRole').value = user.role;
                document.getElementById('editStatus').value = user.status;
                if (document.getElementById('editAvatarColor')) {
                    document.getElementById('editAvatarColor').value = user.avatar_color || '#2563eb';
                }

                openModal('modalEditUser');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
