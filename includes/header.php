<?php
/**
 * Aarcon Builders - Master Header Layout
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Enforce authentication on all protected pages
require_login();

$currentUser = current_user();
$db = get_db();

$pendingChecksCount = 0;
$pendingCashCount = 0;

try {
    $pendingChecksCount = (int) $db->query("SELECT COUNT(*) FROM checks WHERE status = 'pending'")->fetchColumn();
    $pendingCashCount = (int) $db->query("SELECT COUNT(*) FROM cash_requests WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    // Fallback
}

$currentPage = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
$dynamicAppName = get_app_name();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . e($dynamicAppName) : e($dynamicAppName) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- Top Navigation -->
    <nav class="navbar" id="mainNavbar">
        <div class="nav-container">
            <a href="<?= is_admin() ? 'admin.php' : 'index.php' ?>" class="brand">
                <img src="img/logo.png" alt="<?= e($dynamicAppName) ?> Logo" class="brand-logo-nav">
                <span class="brand-title"><?= e($dynamicAppName) ?></span>
            </a>

            <ul class="nav-links">
                <?php if (is_admin()): ?>
                    <li>
                        <a href="admin.php" class="nav-link <?= $currentPage === 'admin.php' ? 'active' : '' ?>">
                            System Administration
                        </a>
                    </li>
                    <li>
                        <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                            Financial Overview
                        </a>
                    </li>
                    <li>
                        <a href="disbursement_report.php"
                            class="nav-link <?= $currentPage === 'disbursement_report.php' ? 'active' : '' ?>">
                            Disbursement Report
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Accountant Operational Modules -->
                    <li>
                        <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="checks.php" class="nav-link <?= $currentPage === 'checks.php' ? 'active' : '' ?>">
                            Checks
                            <?php if ($pendingChecksCount > 0): ?>
                                <span class="nav-counter"><?= $pendingChecksCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="cash_requests.php"
                            class="nav-link <?= $currentPage === 'cash_requests.php' ? 'active' : '' ?>">
                            Cash Requests
                            <?php if ($pendingCashCount > 0): ?>
                                <span class="nav-counter"><?= $pendingCashCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="collections.php"
                            class="nav-link <?= $currentPage === 'collections.php' ? 'active' : '' ?>">
                            Collections
                        </a>
                    </li>
                    <li>
                        <a href="disbursement_report.php"
                            class="nav-link <?= $currentPage === 'disbursement_report.php' ? 'active' : '' ?>">
                            Disbursement Report
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <!--
            <div class="no-print" style="display: flex; align-items: center; gap: 0.75rem;">
                <button class="btn btn-outline btn-sm" onclick="window.print();" title="Print this view">Print</button>
-->
            <?php if ($currentUser): ?>
                <?php
                $userInitials = strtoupper(substr($currentUser['full_name'], 0, 1) . (strpos($currentUser['full_name'], ' ') !== false ? substr($currentUser['full_name'], strpos($currentUser['full_name'], ' ') + 1, 1) : ''));
                $userColor = $currentUser['avatar_color'] ?? '#2563eb';
                ?>
                <div class="user-nav-profile"
                    style="display: flex; align-items: center; gap: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--border-color);">
                    <div
                        style="width: 32px; height: 32px; border-radius: 50%; background: <?= e($userColor) ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <?= e($userInitials) ?>
                    </div>
                    <div class="user-nav-details" style="display: flex; flex-direction: column; line-height: 1.2;">
                        <span style="font-weight: 600; font-size: 0.82rem; color: var(--text-main);">
                            <?= e($currentUser['full_name']) ?>
                        </span>
                        <span style="font-size: 0.7rem; color: var(--text-muted); text-transform: capitalize;">
                            <?= e($currentUser['role']) ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn btn-outline btn-sm"
                        style="padding: 0.25rem 0.55rem; font-size: 0.75rem; margin-left: 0.25rem;"
                        title="Sign out of <?= e($currentUser['username']) ?>">
                        Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-wrapper">

        <!-- Flash Alert -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
                <span><?= e($flash['message']) ?></span>
                <button type="button" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:600;"
                    onclick="document.getElementById('flashAlert').remove();">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Printable Header -->
        <div class="print-header" style="display: none;">
            <div
                style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                <img src="img/logo.png" alt="Logo" style="width: 36px; height: 36px; object-fit: contain;">
                <div class="print-title"><?= e($dynamicAppName) ?></div>
            </div>
            <div class="print-sub"><?= isset($pageTitle) ? e($pageTitle) : 'Disbursement & Cash Report' ?> &bull;
                Printed: <?= date('M d, Y h:i A') ?></div>
        </div>