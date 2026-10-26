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
$activeTab = $_GET['tab'] ?? 'tab-users';

// 1. Fetch Users
$users = $db->query('SELECT * FROM users ORDER BY role ASC, id ASC')->fetchAll();
$totalUsers = count($users);
$activeUsersCount = count(array_filter($users, fn($u) => $u['status'] === 'active'));

// 2. Fetch Audit Logs
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

// 6. Fetch CMS Data: Banks, Dropdowns, Custom Fields
$cmsBanks = $db->query("SELECT * FROM system_banks ORDER BY sort_order ASC, code ASC, id ASC")->fetchAll();
$totalCmsBanks = count($cmsBanks);

$cmsDropdowns = $db->query("SELECT * FROM system_dropdowns ORDER BY category ASC, sort_order ASC, id ASC")->fetchAll();
$totalCmsDropdowns = count($cmsDropdowns);

$cmsFields = $db->query("SELECT * FROM custom_fields ORDER BY module ASC, sort_order ASC, id ASC")->fetchAll();
$totalCmsFields = count($cmsFields);

$activeCmsSub = $_GET['cms_sub'] ?? 'banks';

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
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddUser">
            <span>+ Add New User</span>
        </button>
        <button class="btn btn-outline btn-sm" onclick="switchAdminTab('tab-cms')">
            <span>CMS &amp; Options</span>
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs-container">
    <div class="tabs-nav no-print" style="margin-bottom: 1.5rem;">
        <button class="tab-btn <?= $activeTab === 'tab-users' ? 'active' : '' ?>" data-tab="tab-users">
            <span>User Management</span>
            <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalUsers ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-cms' ? 'active' : '' ?>" data-tab="tab-cms">
            <span>CMS &amp; Customization</span>
            <span class="badge" style="background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); font-weight: 700;"><?= $totalCmsBanks + $totalCmsDropdowns + $totalCmsFields ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-settings' ? 'active' : '' ?>" data-tab="tab-settings">
            <span>System Settings</span>
        </button>
        <button class="tab-btn <?= $activeTab === 'tab-audit' ? 'active' : '' ?>" data-tab="tab-audit">
            <span>Audit Trail</span>
            <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalAuditLogsCount ?></span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB: USER MANAGEMENT -->
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
                                    $avatarColor = !empty($u['avatar_color']) ? $u['avatar_color'] : '#dc2626';
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
                                                        <span class="badge badge-inprocess" style="font-size: 0.7rem; margin-left: 0.25rem;">You</span>
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
    <!-- TAB: CMS & CUSTOMIZATION -->
    <!-- ========================================== -->
    <div class="tab-pane <?= $activeTab === 'tab-cms' ? 'active' : '' ?>" id="tab-cms">
        
        <!-- CMS Sub-Nav Segmented Control & Action Triggers -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div class="tabs-nav no-print" style="margin: 0; background: rgba(0, 0, 0, 0.05); padding: 4px; border-radius: 14px;">
                <button type="button" class="cms-sub-tab <?= $activeCmsSub === 'banks' ? 'active' : '' ?>" data-cms-target="#cmsSubBanks">
                    <span>Banks &amp; Routing Codes</span>
                    <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalCmsBanks ?></span>
                </button>
                <button type="button" class="cms-sub-tab <?= $activeCmsSub === 'dropdowns' ? 'active' : '' ?>" data-cms-target="#cmsSubDropdowns">
                    <span>Dropdown Choices</span>
                    <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalCmsDropdowns ?></span>
                </button>
                <button type="button" class="cms-sub-tab <?= $activeCmsSub === 'fields' ? 'active' : '' ?>" data-cms-target="#cmsSubFields">
                    <span>Custom Fields</span>
                    <span class="badge" style="background: var(--bg-subtle); color: var(--text-main); font-weight: 700;"><?= $totalCmsFields ?></span>
                </button>
            </div>

            <div id="cmsActionButtons">
                <button type="button" class="btn btn-primary btn-sm cms-action-btn" id="btnTriggerAddBank" data-open-modal="modalAddBank" style="<?= $activeCmsSub === 'banks' ? '' : 'display: none;' ?>">
                    <span>+ Add New Bank</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm cms-action-btn" id="btnTriggerAddDropdown" data-open-modal="modalAddDropdown" style="<?= $activeCmsSub === 'dropdowns' ? '' : 'display: none;' ?>">
                    <span>+ Add Dropdown Choice</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm cms-action-btn" id="btnTriggerAddField" data-open-modal="modalAddCustomField" style="<?= $activeCmsSub === 'fields' ? '' : 'display: none;' ?>">
                    <span>+ Add Custom Field</span>
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CMS SUB-PANE 1: BANKS & ROUTING CODES -->
        <!-- ========================================== -->
        <div class="cms-sub-pane <?= $activeCmsSub === 'banks' ? 'active' : '' ?>" id="cmsSubBanks" style="<?= $activeCmsSub === 'banks' ? '' : 'display: none;' ?>">
            <div class="card" style="margin-bottom: 1.25rem; background: linear-gradient(135deg, rgba(0, 113, 227, 0.06) 0%, rgba(255, 255, 255, 0.7) 100%);">
                <div class="card-body" style="padding: 1rem 1.25rem;">
                    <div style="display: flex; gap: 0.85rem; align-items: center;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M14 10v11M12 2L2 7h20L12 2z"/></svg></div>
                        <div style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.5;">
                            <strong style="color: var(--text-main);">Bank Routing &amp; Check Number Automation:</strong>
                            Customize accredited banks, branch offices, and <strong>3-digit starting check routing codes</strong>. When an accountant selects a bank (e.g., <em>BDO Unibank</em>), the system immediately prefixes the check number with that bank's designated starting code (e.g. <code>001-</code>).
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <input type="text" class="search-input" placeholder="Search bank, branch, code..." data-table-search="#banksTable" style="max-width: 320px;">
                    <button class="btn btn-primary btn-sm" data-open-modal="modalAddBank">+ Add New Bank</button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table" id="banksTable">
                            <thead>
                                <tr>
                                    <th style="width: 110px; text-align: center;">Starting Code</th>
                                    <th>Bank Name</th>
                                    <th>Branch Location</th>
                                    <th>Company Account #</th>
                                    <th style="width: 80px; text-align: center;">Order</th>
                                    <th>Status</th>
                                    <th style="text-align: center; width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cmsBanks)): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">No banks configured yet. Click "+ Add New Bank" to create one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cmsBanks as $b): ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <span class="voucher-code-badge" style="font-size: 0.85rem; padding: 3px 8px;">
                                                    <?= e($b['code']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-main);">
                                                    <?= e($b['bank_name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-muted); font-size: 0.85rem;">
                                                    <?= e($b['branch']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-main);">
                                                    <?= !empty($b['account_number']) ? e($b['account_number']) : '&mdash;' ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                                <?= (int)$b['sort_order'] ?>
                                            </td>
                                            <td>
                                                <?php if ($b['status'] === 'active'): ?>
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
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 0.35rem;">
                                                    <button 
                                                        class="btn btn-outline btn-sm btn-edit-bank" 
                                                        data-bank='<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>'
                                                        title="Edit Bank &amp; Starting Code"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form action="api/actions.php" method="POST" style="margin:0;" onsubmit="return confirm('Remove bank <?= e($b['bank_name']) ?> (<?= e($b['branch']) ?>)?');">
                                                        <input type="hidden" name="action" value="delete_bank">
                                                        <input type="hidden" name="bank_id" value="<?= $b['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Bank">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CMS SUB-PANE 2: DROPDOWN CHOICES -->
        <!-- ========================================== -->
        <div class="cms-sub-pane <?= $activeCmsSub === 'dropdowns' ? 'active' : '' ?>" id="cmsSubDropdowns" style="<?= $activeCmsSub === 'dropdowns' ? '' : 'display: none;' ?>">
            <div class="card" style="margin-bottom: 1.25rem; background: linear-gradient(135deg, rgba(52, 199, 89, 0.06) 0%, rgba(255, 255, 255, 0.7) 100%);">
                <div class="card-body" style="padding: 1rem 1.25rem;">
                    <div style="display: flex; gap: 0.85rem; align-items: center;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
                        <div style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.5;">
                            <strong style="color: var(--text-main);">Customizable System Dropdowns:</strong>
                            Manage the selectable options for <strong>Project Districts</strong>, <strong>Expense Categories</strong>, <strong>Payment Methods</strong>, and <strong>Check Priority Statuses</strong>. Any edits or additions instantly propagate to check modals, collection entries, and executive reports.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <input type="text" class="search-input" placeholder="Search dropdown options..." data-table-search="#dropdownsTable" style="max-width: 240px;">
                        <div class="filter-pills" style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-primary filter-dd-cat" data-cat="all">All</button>
                            <button type="button" class="btn btn-sm btn-outline filter-dd-cat" data-cat="project_districts">Districts</button>
                            <button type="button" class="btn btn-sm btn-outline filter-dd-cat" data-cat="expense_categories">Categories</button>
                            <button type="button" class="btn btn-sm btn-outline filter-dd-cat" data-cat="payment_methods">Payment Methods</button>
                            <button type="button" class="btn btn-sm btn-outline filter-dd-cat" data-cat="priority_statuses">Priority</button>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm" data-open-modal="modalAddDropdown">+ Add Dropdown Choice</button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table" id="dropdownsTable">
                            <thead>
                                <tr>
                                    <th style="width: 160px;">Category</th>
                                    <th>Option Key / Code</th>
                                    <th>Display Label</th>
                                    <th style="width: 80px; text-align: center;">Order</th>
                                    <th>Status</th>
                                    <th style="text-align: center; width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cmsDropdowns)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">No dropdown options configured. Click "+ Add Dropdown Choice" to create one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cmsDropdowns as $dd): ?>
                                        <tr data-category="<?= e($dd['category']) ?>">
                                            <td>
                                                <?php
                                                    $catColor = '#dc2626';
                                                    $catBg = 'rgba(220, 38, 38, 0.08)';
                                                    $catName = 'District';
                                                    if ($dd['category'] === 'expense_categories') {
                                                        $catColor = '#af52de';
                                                        $catBg = 'rgba(175, 82, 222, 0.1)';
                                                        $catName = 'Expense Category';
                                                    } elseif ($dd['category'] === 'payment_methods') {
                                                        $catColor = '#34c759';
                                                        $catBg = 'rgba(52, 199, 89, 0.12)';
                                                        $catName = 'Payment Method';
                                                    } elseif ($dd['category'] === 'priority_statuses') {
                                                        $catColor = '#ff9500';
                                                        $catBg = 'rgba(255, 149, 0, 0.12)';
                                                        $catName = 'Priority Status';
                                                    }
                                                ?>
                                                <span class="badge" style="background: <?= $catBg ?>; color: <?= $catColor ?>; font-size: 0.74rem; font-weight: 700;">
                                                    <?= $catName ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-family: var(--font-mono); font-weight: 600; font-size: 0.82rem; color: var(--text-main);">
                                                    <?= e($dd['item_key']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500; color: var(--text-main);">
                                                    <?= e($dd['item_label']) ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                                <?= (int)$dd['sort_order'] ?>
                                            </td>
                                            <td>
                                                <?php if ($dd['status'] === 'active'): ?>
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
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 0.35rem;">
                                                    <button 
                                                        class="btn btn-outline btn-sm btn-edit-dropdown" 
                                                        data-item='<?= htmlspecialchars(json_encode($dd), ENT_QUOTES) ?>'
                                                        title="Edit Option"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form action="api/actions.php" method="POST" style="margin:0;" onsubmit="return confirm('Remove option <?= e($dd['item_label']) ?>?');">
                                                        <input type="hidden" name="action" value="delete_dropdown_item">
                                                        <input type="hidden" name="item_id" value="<?= $dd['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Option">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CMS SUB-PANE 3: CUSTOM FIELDS -->
        <!-- ========================================== -->
        <div class="cms-sub-pane <?= $activeCmsSub === 'fields' ? 'active' : '' ?>" id="cmsSubFields" style="<?= $activeCmsSub === 'fields' ? '' : 'display: none;' ?>">
            <div class="card" style="margin-bottom: 1.25rem; background: linear-gradient(135deg, rgba(175, 82, 222, 0.06) 0%, rgba(255, 255, 255, 0.7) 100%);">
                <div class="card-body" style="padding: 1rem 1.25rem;">
                    <div style="display: flex; gap: 0.85rem; align-items: center;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
                        <div style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.5;">
                            <strong style="color: var(--text-main);">Enterprise Custom Fields Engine:</strong>
                            Add dynamic fields to <strong>Checks &amp; Vouchers</strong>, <strong>Petty Cash Vouchers</strong>, or <strong>Client Collections</strong>. These custom fields appear automatically in input forms, edit modals, voucher printouts, and reporting sheets.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <input type="text" class="search-input" placeholder="Search custom fields..." data-table-search="#fieldsTable" style="max-width: 240px;">
                        <div class="filter-pills" style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-primary filter-cf-mod" data-mod="all">All Modules</button>
                            <button type="button" class="btn btn-sm btn-outline filter-cf-mod" data-mod="checks">Checks &amp; Vouchers</button>
                            <button type="button" class="btn btn-sm btn-outline filter-cf-mod" data-mod="cash_requests">Petty Cash</button>
                            <button type="button" class="btn btn-sm btn-outline filter-cf-mod" data-mod="collections">Collections</button>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm" data-open-modal="modalAddCustomField">+ Add Custom Field</button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table" id="fieldsTable">
                            <thead>
                                <tr>
                                    <th style="width: 150px;">Target Module</th>
                                    <th>Field Label</th>
                                    <th>Field Key (Slug)</th>
                                    <th style="width: 110px;">Input Type</th>
                                    <th>Options / Placeholder</th>
                                    <th style="width: 90px; text-align: center;">Required</th>
                                    <th>Status</th>
                                    <th style="text-align: center; width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cmsFields)): ?>
                                    <tr>
                                        <td colspan="8" class="empty-state">No custom fields defined yet. Click "+ Add Custom Field" to create one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cmsFields as $cf): ?>
                                        <tr data-module="<?= e($cf['module']) ?>">
                                            <td>
                                                <?php
                                                    $modLabel = 'Checks & Vouchers';
                                                    $modBadge = 'badge-pending';
                                                    if ($cf['module'] === 'cash_requests') {
                                                        $modLabel = 'Petty Cash';
                                                        $modBadge = 'badge-inprocess';
                                                    } elseif ($cf['module'] === 'collections') {
                                                        $modLabel = 'Collections';
                                                        $modBadge = 'badge-funded';
                                                    }
                                                ?>
                                                <span class="badge <?= $modBadge ?>" style="font-size: 0.74rem;">
                                                    <?= $modLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-main); font-size: 0.88rem;">
                                                    <?= e($cf['field_label']) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <code style="font-size: 0.78rem; color: #dc2626; background: rgba(220, 38, 38, 0.08); padding: 2px 6px; border-radius: 4px;">
                                                    <?= e($cf['field_key']) ?>
                                                </code>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted);">
                                                    <?= e($cf['field_type']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 200px; word-break: break-word;">
                                                <?= !empty($cf['field_options']) ? 'Choices: ' . e($cf['field_options']) : (!empty($cf['placeholder']) ? 'e.g. ' . e($cf['placeholder']) : '&mdash;') ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($cf['is_required']): ?>
                                                    <span class="badge badge-funded" style="font-size: 0.7rem;">Required</span>
                                                <?php else: ?>
                                                    <span style="font-size: 0.75rem; color: var(--text-muted);">Optional</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cf['status'] === 'active'): ?>
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
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 0.35rem;">
                                                    <button 
                                                        class="btn btn-outline btn-sm btn-edit-custom-field" 
                                                        data-field='<?= htmlspecialchars(json_encode($cf), ENT_QUOTES) ?>'
                                                        title="Edit Custom Field"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form action="api/actions.php" method="POST" style="margin:0;" onsubmit="return confirm('Remove custom field <?= e($cf['field_label']) ?>?');">
                                                        <input type="hidden" name="action" value="delete_custom_field">
                                                        <input type="hidden" name="field_id" value="<?= $cf['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Custom Field">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 4: SYSTEM SETTINGS -->
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
                        Company Profile &amp; Letterhead
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
                        Financial &amp; Approval Defaults
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
                            <option value="accountant">Finance Officer (Financial Operations)</option>
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
                            <option value="accountant">Finance Officer</option>
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

<!-- ========================================== -->
<!-- CMS MODALS: BANKS -->
<!-- ========================================== -->
<!-- Modal: Add Bank -->
<div class="modal-overlay" id="modalAddBank">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add Bank &amp; Starting Routing Code</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="add_bank">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label">Bank Institution Name <span class="req">*</span></label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. BDO Unibank, BPI, Metrobank" required>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Branch Location <span class="req">*</span></label>
                        <input type="text" name="branch" class="form-control" placeholder="e.g. Pasig - Ortigas Central, Rizal - Antipolo" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Starting Code (Routing Prefix) <span class="req">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. 001, 002, 045" style="font-family: var(--font-mono); font-weight: 700; color: #dc2626;" required>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Prefix automatically inserted into check numbers</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Account Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="e.g. 001-9988-7711" style="font-family: var(--font-mono);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active (Selectable)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAddBank')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Bank</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Bank -->
<div class="modal-overlay" id="modalEditBank">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Bank &amp; Starting Routing Code</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="edit_bank">
            <input type="hidden" name="bank_id" id="editBankId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label">Bank Institution Name <span class="req">*</span></label>
                        <input type="text" name="bank_name" id="editBankName" class="form-control" required>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Branch Location <span class="req">*</span></label>
                        <input type="text" name="branch" id="editBankBranch" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Starting Code (Routing Prefix) <span class="req">*</span></label>
                        <input type="text" name="code" id="editBankCode" class="form-control" style="font-family: var(--font-mono); font-weight: 700; color: #dc2626;" required>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Changing this immediately changes check auto-prefixing</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Account Number</label>
                        <input type="text" name="account_number" id="editBankAccount" class="form-control" style="font-family: var(--font-mono);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" id="editBankSort" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="editBankStatus" class="form-control">
                            <option value="active">Active (Selectable)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalEditBank')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Bank</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- CMS MODALS: DROPDOWN CHOICES -->
<!-- ========================================== -->
<!-- Modal: Add Dropdown Item -->
<div class="modal-overlay" id="modalAddDropdown">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add System Dropdown Option</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="add_dropdown_item">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label">Target Dropdown Category <span class="req">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="project_districts">Project Districts</option>
                            <option value="expense_categories">Expense / Disbursement Categories</option>
                            <option value="payment_methods">Collection Payment Methods</option>
                            <option value="priority_statuses">Check Priority Statuses</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Display Label <span class="req">*</span></label>
                        <input type="text" name="item_label" class="form-control" placeholder="e.g. DPWH - Cavite 1st District Office" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Option Key / Code</label>
                        <input type="text" name="item_key" class="form-control" placeholder="e.g. DPWH-CAVITE-1st (Auto-generated if blank)" style="font-family: var(--font-mono);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAddDropdown')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Option</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Dropdown Item -->
<div class="modal-overlay" id="modalEditDropdown">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit System Dropdown Option</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="edit_dropdown_item">
            <input type="hidden" name="item_id" id="editItemId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label">Dropdown Category <span class="req">*</span></label>
                        <select name="category" id="editItemCategory" class="form-control" required>
                            <option value="project_districts">Project Districts</option>
                            <option value="expense_categories">Expense / Disbursement Categories</option>
                            <option value="payment_methods">Collection Payment Methods</option>
                            <option value="priority_statuses">Check Priority Statuses</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Display Label <span class="req">*</span></label>
                        <input type="text" name="item_label" id="editItemLabel" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Option Key / Code <span class="req">*</span></label>
                        <input type="text" name="item_key" id="editItemKey" class="form-control" style="font-family: var(--font-mono);" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" id="editItemSort" class="form-control">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Status</label>
                        <select name="status" id="editItemStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalEditDropdown')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Option</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- CMS MODALS: CUSTOM FIELDS -->
<!-- ========================================== -->
<!-- Modal: Add Custom Field -->
<div class="modal-overlay" id="modalAddCustomField">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Dynamic Custom Field</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="add_custom_field">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Target Module <span class="req">*</span></label>
                        <select name="module" class="form-control" required>
                            <option value="checks">Checks &amp; Vouchers</option>
                            <option value="cash_requests">Petty Cash Vouchers</option>
                            <option value="collections">Client Collections</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Input Field Type <span class="req">*</span></label>
                        <select name="field_type" class="form-control" required onchange="toggleFieldOptionsVisibility(this, 'add')">
                            <option value="text">Text Input</option>
                            <option value="number">Numeric (Decimals / Integers)</option>
                            <option value="date">Date Picker</option>
                            <option value="select">Dropdown Select</option>
                            <option value="textarea">Multi-line Textarea</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Field Label (Displayed on Form) <span class="req">*</span></label>
                        <input type="text" name="field_label" class="form-control" placeholder="e.g. Purchase Order (PO) #, Supplier TIN" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Field Slug / Key (Optional)</label>
                        <input type="text" name="field_key" class="form-control" placeholder="e.g. po_number (auto-slugged if empty)" style="font-family: var(--font-mono);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Placeholder Hint</label>
                        <input type="text" name="placeholder" class="form-control" placeholder="e.g. PO-2026-XXXX">
                    </div>
                    <div class="form-group col-span-2" id="addFieldOptionsGroup" style="display: none;">
                        <label class="form-label">Dropdown Choices (Comma-separated)</label>
                        <input type="text" name="field_options" class="form-control" placeholder="Option 1, Option 2, Option 3">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Enter choices separated by commas</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Requirement</label>
                        <select name="is_required" class="form-control">
                            <option value="0">Optional</option>
                            <option value="1">Required (Mandatory)</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active (Visible in forms)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCustomField')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Field</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Custom Field -->
<div class="modal-overlay" id="modalEditCustomField">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Dynamic Custom Field</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form action="api/actions.php" method="POST">
            <input type="hidden" name="action" value="edit_custom_field">
            <input type="hidden" name="field_id" id="editFieldId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Target Module <span class="req">*</span></label>
                        <select name="module" id="editFieldModule" class="form-control" required>
                            <option value="checks">Checks &amp; Vouchers</option>
                            <option value="cash_requests">Petty Cash Vouchers</option>
                            <option value="collections">Client Collections</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Input Field Type <span class="req">*</span></label>
                        <select name="field_type" id="editFieldType" class="form-control" required onchange="toggleFieldOptionsVisibility(this, 'edit')">
                            <option value="text">Text Input</option>
                            <option value="number">Numeric (Decimals / Integers)</option>
                            <option value="date">Date Picker</option>
                            <option value="select">Dropdown Select</option>
                            <option value="textarea">Multi-line Textarea</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Field Label <span class="req">*</span></label>
                        <input type="text" name="field_label" id="editFieldLabel" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Field Slug / Key <span class="req">*</span></label>
                        <input type="text" name="field_key" id="editFieldKey" class="form-control" style="font-family: var(--font-mono);" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Placeholder Hint</label>
                        <input type="text" name="placeholder" id="editFieldPlaceholder" class="form-control">
                    </div>
                    <div class="form-group col-span-2" id="editFieldOptionsGroup">
                        <label class="form-label">Dropdown Choices (Comma-separated)</label>
                        <input type="text" name="field_options" id="editFieldOptions" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" id="editFieldSort" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Requirement</label>
                        <select name="is_required" id="editFieldRequired" class="form-control">
                            <option value="0">Optional</option>
                            <option value="1">Required (Mandatory)</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Status</label>
                        <select name="status" id="editFieldStatus" class="form-control">
                            <option value="active">Active (Visible in forms)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalEditCustomField')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Field</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab switching helper
    function switchAdminTab(tabId) {
        const tabBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
        if (tabBtn) {
            tabBtn.click();
        }
    }

    // Toggle dropdown choices input visibility
    function toggleFieldOptionsVisibility(selectEl, mode) {
        const targetId = (mode === 'add') ? 'addFieldOptionsGroup' : 'editFieldOptionsGroup';
        const group = document.getElementById(targetId);
        if (group) {
            group.style.display = (selectEl.value === 'select') ? 'block' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Edit User modal populate logic
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
                    document.getElementById('editAvatarColor').value = user.avatar_color || '#dc2626';
                }

                openModal('modalEditUser');
            });
        });

        // 2. CMS Sub-Tab switching logic
        const cmsSubTabs = document.querySelectorAll('.cms-sub-tab');
        const cmsSubPanes = document.querySelectorAll('.cms-sub-pane');
        const btnAddBank = document.getElementById('btnTriggerAddBank');
        const btnAddDropdown = document.getElementById('btnTriggerAddDropdown');
        const btnAddField = document.getElementById('btnTriggerAddField');

        cmsSubTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                cmsSubTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                const targetSelector = tab.getAttribute('data-cms-target');
                cmsSubPanes.forEach(pane => {
                    pane.style.display = 'none';
                    pane.classList.remove('active');
                });

                const activePane = document.querySelector(targetSelector);
                if (activePane) {
                    activePane.style.display = 'block';
                    activePane.classList.add('active');
                }

                // Switch corresponding action button
                if (btnAddBank && btnAddDropdown && btnAddField) {
                    btnAddBank.style.display = (targetSelector === '#cmsSubBanks') ? 'inline-flex' : 'none';
                    btnAddDropdown.style.display = (targetSelector === '#cmsSubDropdowns') ? 'inline-flex' : 'none';
                    btnAddField.style.display = (targetSelector === '#cmsSubFields') ? 'inline-flex' : 'none';
                }

                // Persist CMS sub-tab to URL without page reload
                try {
                    const subKey = (targetSelector === '#cmsSubBanks') ? 'banks' : ((targetSelector === '#cmsSubDropdowns') ? 'dropdowns' : 'fields');
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'tab-cms');
                    url.searchParams.set('cms_sub', subKey);
                    window.history.replaceState({}, '', url.toString());
                } catch (err) {}
            });
        });

        // 3. Edit Bank modal populate logic
        document.querySelectorAll('.btn-edit-bank').forEach(btn => {
            btn.addEventListener('click', () => {
                const bankJson = btn.getAttribute('data-bank');
                if (!bankJson) return;
                const b = JSON.parse(bankJson);

                document.getElementById('editBankId').value = b.id;
                document.getElementById('editBankName').value = b.bank_name;
                document.getElementById('editBankBranch').value = b.branch;
                document.getElementById('editBankCode').value = b.code;
                document.getElementById('editBankAccount').value = b.account_number || '';
                document.getElementById('editBankSort').value = b.sort_order || 0;
                document.getElementById('editBankStatus').value = b.status || 'active';

                openModal('modalEditBank');
            });
        });

        // 4. Edit Dropdown Item modal populate logic
        document.querySelectorAll('.btn-edit-dropdown').forEach(btn => {
            btn.addEventListener('click', () => {
                const itemJson = btn.getAttribute('data-item');
                if (!itemJson) return;
                const it = JSON.parse(itemJson);

                document.getElementById('editItemId').value = it.id;
                document.getElementById('editItemCategory').value = it.category;
                document.getElementById('editItemKey').value = it.item_key;
                document.getElementById('editItemLabel').value = it.item_label;
                document.getElementById('editItemSort').value = it.sort_order || 0;
                document.getElementById('editItemStatus').value = it.status || 'active';

                openModal('modalEditDropdown');
            });
        });

        // 5. Edit Custom Field modal populate logic
        document.querySelectorAll('.btn-edit-custom-field').forEach(btn => {
            btn.addEventListener('click', () => {
                const fieldJson = btn.getAttribute('data-field');
                if (!fieldJson) return;
                const f = JSON.parse(fieldJson);

                document.getElementById('editFieldId').value = f.id;
                document.getElementById('editFieldModule').value = f.module;
                document.getElementById('editFieldLabel').value = f.field_label;
                document.getElementById('editFieldKey').value = f.field_key;
                document.getElementById('editFieldType').value = f.field_type;
                document.getElementById('editFieldOptions').value = f.field_options || '';
                document.getElementById('editFieldPlaceholder').value = f.placeholder || '';
                document.getElementById('editFieldRequired').value = f.is_required ? '1' : '0';
                document.getElementById('editFieldSort').value = f.sort_order || 0;
                document.getElementById('editFieldStatus').value = f.status || 'active';

                const optGroup = document.getElementById('editFieldOptionsGroup');
                if (optGroup) {
                    optGroup.style.display = (f.field_type === 'select') ? 'block' : 'none';
                }

                openModal('modalEditCustomField');
            });
        });

        // 6. Dropdown Category client-side filter pills
        document.querySelectorAll('.filter-dd-cat').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.filter-dd-cat').forEach(p => {
                    p.classList.remove('btn-primary');
                    p.classList.add('btn-outline');
                });
                pill.classList.remove('btn-outline');
                pill.classList.add('btn-primary');

                const targetCat = pill.getAttribute('data-cat');
                document.querySelectorAll('#dropdownsTable tbody tr').forEach(row => {
                    if (targetCat === 'all') {
                        row.style.display = '';
                    } else {
                        const rowCat = row.getAttribute('data-category');
                        row.style.display = (rowCat === targetCat) ? '' : 'none';
                    }
                });
            });
        });

        // 7. Custom Field Module client-side filter pills
        document.querySelectorAll('.filter-cf-mod').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.filter-cf-mod').forEach(p => {
                    p.classList.remove('btn-primary');
                    p.classList.add('btn-outline');
                });
                pill.classList.remove('btn-outline');
                pill.classList.add('btn-primary');

                const targetMod = pill.getAttribute('data-mod');
                document.querySelectorAll('#fieldsTable tbody tr').forEach(row => {
                    if (targetMod === 'all') {
                        row.style.display = '';
                    } else {
                        const rowMod = row.getAttribute('data-module');
                        row.style.display = (rowMod === targetMod) ? '' : 'none';
                    }
                });
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
