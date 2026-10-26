<?php
/**
 * Aarcon Builders - Master Header Layout
 * Redesigned with Left Sidebar Navigation & Clean Executive Interface
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Enforce authentication on all protected pages
require_login();

$currentPage = basename($_SERVER['PHP_SELF']);

// Enforce strict Separation of Duties:
// Administrator role is solely restricted to the Administration module (admin.php)
if (is_admin() && $currentPage !== 'admin.php') {
    set_flash('info', 'As an Administrator, you only have access to the Administration module.');
    if (php_sapi_name() !== 'cli') {
        header('Location: admin.php');
        exit;
    }
}

$currentUser = current_user();
$db = get_db();

$pendingChecksCount = 0;
$pendingCashCount = 0;

if (!is_admin()) {
    try {
        $pendingChecksCount = (int) $db->query("SELECT COUNT(*) FROM checks WHERE status = 'pending'")->fetchColumn();
        $pendingCashCount = (int) $db->query("SELECT COUNT(*) FROM cash_requests WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        // Fallback
    }
}
$flash = get_flash();
$dynamicAppName = get_app_name();

// User profile data
$userInitials = 'U';
$userColor = '#dc2626';
if ($currentUser) {
    $userInitials = strtoupper(substr($currentUser['full_name'], 0, 1) . (strpos($currentUser['full_name'], ' ') !== false ? substr($currentUser['full_name'], strpos($currentUser['full_name'], ' ') + 1, 1) : ''));
    $userColor = $currentUser['avatar_color'] ?? '#dc2626';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . e($dynamicAppName) : e($dynamicAppName) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">

    <!-- CSS Design System -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
</head>

<body>

<div class="app-layout">

    <!-- ======================================================== -->
    <!-- LEFT SIDEBAR NAVIGATION -->
    <!-- ======================================================== -->
    <aside class="app-sidebar no-print" id="appSidebar">
        <!-- Sidebar Brand Header -->
        <div class="sidebar-brand">
            <a href="<?= is_admin() ? 'admin.php' : 'index.php' ?>" class="sidebar-brand-link">
                <img src="img/logo.png" alt="<?= e($dynamicAppName) ?> Logo" class="sidebar-logo">
                <div class="sidebar-brand-text">
                    <span class="sidebar-title"><?= e($dynamicAppName) ?></span>
                    <span class="sidebar-subtitle">General Contractor &bull; ERP</span>
                </div>
            </a>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <?php if (is_admin()): ?>
                <!-- ADMIN NAVIGATION: Exclusively the Administration module -->
                <div class="sidebar-section">
                    <span class="sidebar-heading">System Management</span>
                    <a href="admin.php" class="sidebar-link <?= $currentPage === 'admin.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        <span>Administration</span>
                    </a>
                </div>

            <?php else: ?>
                <!-- FINANCE OFFICER / STANDARD NAVIGATION -->
                <div class="sidebar-section">
                    <span class="sidebar-heading">Core</span>
                    <a href="index.php" class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="sidebar-section">
                    <span class="sidebar-heading">Disbursements</span>
                    <a href="vouchers.php" class="sidebar-link <?= $currentPage === 'vouchers.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                        <span>Check Vouchers</span>
                    </a>
                    <a href="checks.php" class="sidebar-link <?= $currentPage === 'checks.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line><line x1="6" y1="15" x2="10" y2="15"></line></svg>
                        <span>Checks</span>
                        <?php if ($pendingChecksCount > 0): ?>
                            <span class="sidebar-badge"><?= $pendingChecksCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="cash_requests.php" class="sidebar-link <?= $currentPage === 'cash_requests.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                        <span>Cash Requests</span>
                        <?php if ($pendingCashCount > 0): ?>
                            <span class="sidebar-badge"><?= $pendingCashCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="sidebar-section">
                    <span class="sidebar-heading">Billings &amp; Inflow</span>
                    <a href="collections.php" class="sidebar-link <?= $currentPage === 'collections.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path></svg>
                        <span>Collections</span>
                    </a>
                    <a href="collection_report.php" class="sidebar-link <?= $currentPage === 'collection_report.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                        <span>Cashed Report</span>
                    </a>
                </div>

                <div class="sidebar-section">
                    <span class="sidebar-heading">Reports</span>
                    <a href="disbursement_report.php" class="sidebar-link <?= $currentPage === 'disbursement_report.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        <span>Disbursement Report</span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar User Footer -->
        <?php if ($currentUser): ?>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-avatar" style="background: <?= e($userColor) ?>;">
                        <?= e($userInitials) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name" title="<?= e($currentUser['full_name']) ?>"><?= e($currentUser['full_name']) ?></div>
                        <div class="sidebar-user-role"><?= e(get_user_role_label($currentUser['role'])) ?></div>
                    </div>
                    <a href="logout.php" class="sidebar-logout-btn" title="Sign out of <?= e($currentUser['username']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Mobile Drawer Backdrop -->
    <div class="sidebar-backdrop no-print" id="sidebarBackdrop"></div>

    <!-- App Main Workspace -->
    <div class="app-main">
        <!-- Top App Bar -->
        <header class="app-topbar no-print">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="topbar-breadcrumb">
                    <span class="breadcrumb-app"><?= e($dynamicAppName) ?></span>
                    <span class="breadcrumb-sep">&rsaquo;</span>
                    <span class="breadcrumb-current"><?= isset($pageTitle) ? e($pageTitle) : 'Overview' ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-status">
                    <span class="status-indicator-dot"></span>
                    <span>Live Ledger &bull; <?= date('M d, Y') ?></span>
                </div>
                <?php if ($currentUser): ?>
                    <a href="logout.php" class="btn btn-outline btn-sm topbar-logout" title="Sign out">
                        Logout
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="main-wrapper">

            <!-- Flash Alert -->
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:600;"
                        onclick="document.getElementById('flashAlert').remove();">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Official Printable Company Letterhead -->
            <div class="print-header" style="display: none;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #000000; padding-bottom: 8px; margin-bottom: 8px; width: 100%; box-sizing: border-box;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <img src="img/logo.png" alt="Aarcon Builders Logo" style="width: 44px; height: 44px; object-fit: contain;">
                        <div>
                            <div style="font-family: 'Times New Roman', Times, Georgia, serif; font-size: 18pt; font-weight: 900; letter-spacing: 0.04em; color: #000000; text-transform: uppercase; line-height: 1.1;">
                                AARCON BUILDERS
                            </div>
                            <div style="font-size: 8.5pt; font-weight: 800; letter-spacing: 0.1em; color: #000000; text-transform: uppercase; margin-top: 1px;">
                                GENERAL CONTRACTOR
                            </div>
                            <div style="font-size: 7.5pt; color: #222222; line-height: 1.25; margin-top: 2px;">
                                Unit 203, FSS Bldg. I, #20 Sct. Tuazon cor. Sct. Castor Sts., Quezon City &bull; Telefax: 8441-8225 &bull; Email: aarconbuilders@gmail.com
                            </div>
                        </div>
                    </div>
                    <div style="text-align: right; color: #000000;">
                        <div class="print-report-title" style="font-size: 11.5pt; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; color: #000000;">
                            <?= isset($pageTitle) ? e($pageTitle) : 'Official Financial Report' ?>
                        </div>
                        <div style="font-size: 8pt; font-weight: 700; color: #334155; margin-top: 2px;">
                            Statement Date: <?= date('F d, Y h:i A') ?>
                        </div>
                        <div style="font-size: 7pt; color: #64748b; margin-top: 1px;">
                            Aarcon Financial Management &amp; Disbursement System
                        </div>
                    </div>
                </div>
            </div>