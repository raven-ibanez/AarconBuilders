<?php
/**
 * Aarcon Builders - Module 1: Check Management (PDCs, Funding, Holding Area, Monthly Release)
 */

$pageTitle = 'Check Management';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Tab Selection
$activeTab = $_GET['tab'] ?? 'funding';

// Unified Date Filter Logic (Cheque Date vs Issue Date via dropdown)
$dateField = ($_GET['date_field'] ?? 'check_date') === 'issue_date' ? 'issue_date' : 'check_date';
$chkDateFilter = $_GET['cheque_filter'] ?? 'all'; // 'all', 'today', 'week', 'month', 'custom'

$today        = date('Y-m-d');
$endOfWeek    = date('Y-m-d', strtotime('+7 days'));
$startOfMonth = date('Y-m-01');
$endOfMonth   = date('Y-m-t');

$startDate = trim($_GET['chk_start'] ?? $_GET['start_date'] ?? '');
$endDate   = trim($_GET['chk_end'] ?? $_GET['end_date'] ?? '');

// Handle preset shortcut if chosen
if ($chkDateFilter === 'today') {
    $startDate = $today;
    $endDate   = $today;
} elseif ($chkDateFilter === 'week') {
    $startDate = $today;
    $endDate   = $endOfWeek;
} elseif ($chkDateFilter === 'month') {
    $startDate = $startOfMonth;
    $endDate   = $endOfMonth;
}

// Build SQL filter for checks (strictly validated and PDO quoted)
$dateConditions = [];
$isValidStart = !empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate);
$isValidEnd   = !empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate);
if ($isValidStart && $isValidEnd) {
    $dateConditions[] = "{$dateField} BETWEEN " . $db->quote($startDate) . " AND " . $db->quote($endDate);
} elseif ($isValidStart) {
    $dateConditions[] = "{$dateField} >= " . $db->quote($startDate);
} elseif ($isValidEnd) {
    $dateConditions[] = "{$dateField} <= " . $db->quote($endDate);
}

$dateWhereClause = !empty($dateConditions) ? " AND (" . implode(" AND ", $dateConditions) . ")" : "";

// 1. Pending Checks (Awaiting Boss Funding, excluding Hold, strictly reviewed vouchers)
$pendingChecks = $db->query("SELECT * FROM checks WHERE status = 'pending' AND (is_reviewed = 1 OR is_reviewed IS NULL) AND (priority_status IS NULL OR priority_status != 'Hold') {$dateWhereClause} ORDER BY CASE WHEN priority_status = 'Prioritized' THEN 0 ELSE 1 END, check_date ASC, id ASC")->fetchAll();
$pendingTotal = array_sum(array_column($pendingChecks, 'amount'));

// 2. Funded Cheques View (Filtered by selected dateField)
$fundedChecks = $db->query("SELECT * FROM checks WHERE status = 'funded' AND (is_reviewed = 1 OR is_reviewed IS NULL) {$dateWhereClause} ORDER BY check_date DESC, id DESC")->fetchAll();
$fundedTotal = array_sum(array_column($fundedChecks, 'amount'));

// 3. Holding Area View (priority_status = 'Hold', Filtered by selected dateField)
$holdingChecks = $db->query("SELECT * FROM checks WHERE priority_status = 'Hold' AND (is_reviewed = 1 OR is_reviewed IS NULL) {$dateWhereClause} ORDER BY check_date ASC, id DESC")->fetchAll();
$holdingTotal = array_sum(array_column($holdingChecks, 'amount'));

// 4. All checks list (Filtered by selected dateField)
$allChecksWhere = !empty($dateWhereClause) ? "WHERE (is_reviewed = 1 OR is_reviewed IS NULL) {$dateWhereClause}" : "WHERE (is_reviewed = 1 OR is_reviewed IS NULL)";
$allChecks = $db->query("SELECT * FROM checks {$allChecksWhere} ORDER BY id DESC")->fetchAll();
$allChecksCount = count($allChecks);
$allChecksTotal = array_sum(array_column($allChecks, 'amount'));
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Check Management</h1>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="vouchers.php" class="btn btn-outline btn-sm" title="Vouchers are created in the Voucher module and routed here upon review">
            + New Voucher / Check
        </a>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print View</button>
    </div>
</div>

<!-- Key Metrics Dashboard Cards (Summary) -->
<div class="metric-grid no-print" style="margin-bottom: 1.5rem;">
    <!-- Metric 1: Total Checks -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-all\']')?.click();" title="View all checks">
        <div class="metric-label">Total Checks</div>
        <div class="metric-amount" style="color: var(--text-main);">
            <?= $allChecksCount ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($allChecksTotal) ?>)</span>
        </div>
    </div>

    <!-- Metric 2: Awaiting Review & Funding -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-funding\']')?.click();" title="View funding queue">
        <div class="metric-label">Awaiting Funding</div>
        <div class="metric-amount" style="color: var(--color-warning);">
            <?= count($pendingChecks) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($pendingTotal) ?>)</span>
        </div>
    </div>

    <!-- Metric 3: Funded Cheques -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-funded\']')?.click();" title="View funded checks">
        <div class="metric-label">Funded Cheques</div>
        <div class="metric-amount" style="color: var(--color-success);">
            <?= count($fundedChecks) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($fundedTotal) ?>)</span>
        </div>
    </div>

    <!-- Metric 4: Holding Area -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-holding\']')?.click();" title="View holding area">
        <div class="metric-label">Holding Area (PDC)</div>
        <div class="metric-amount" style="color: #6366f1;">
            <?= count($holdingChecks) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($holdingTotal) ?>)</span>
        </div>
    </div>
</div>

<!-- Tabs Container -->
<div class="tabs-container">
    <div class="tabs-nav no-print">
        <button class="tab-btn <?= $activeTab === 'funding' ? 'active' : '' ?>" data-tab="tab-funding">
            <span>Funding Queue (Boss Review)</span>
            <?php if (count($pendingChecks) > 0): ?>
                <span class="badge badge-pending"><?= count($pendingChecks) ?></span>
            <?php endif; ?>
        </button>

        <button class="tab-btn <?= $activeTab === 'funded' ? 'active' : '' ?>" data-tab="tab-funded">
            <span>Funded Cheques</span>
            <?php if (count($fundedChecks) > 0): ?>
                <span class="badge badge-funded"><?= count($fundedChecks) ?></span>
            <?php endif; ?>
        </button>

        <button class="tab-btn <?= $activeTab === 'holding' ? 'active' : '' ?>" data-tab="tab-holding">
            <span>Holding Area</span>
            <?php if (count($holdingChecks) > 0): ?>
                <span class="badge badge-hold"><?= count($holdingChecks) ?></span>
            <?php endif; ?>
        </button>

        <button class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>" data-tab="tab-all">
            <span>All Checks (<?= $allChecksCount ?>)</span>
        </button>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 1: Checks for Funding (Boss Review) -->
    <!-- ======================================================== -->
    <div class="tab-pane <?= $activeTab === 'funding' ? 'active' : '' ?>" id="tab-funding">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>Cheque Funding Queue - Awaiting Review (<?= count($pendingChecks) ?> cheques)</span>
            <span>Pending Total: <?= format_money($pendingTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <!-- Excel Header Bar -->
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    CHEQUE FUNDING QUEUE (AWAITING REVIEW)
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <div class="excel-sheet-date">
                        PENDING: <strong style="color: #0f172a;"><?= format_money($pendingTotal) ?></strong> &bull; <?= count($pendingChecks) ?> CHEQUE(S)
                    </div>
                    <span class="badge badge-prioritized">Prioritized First</span>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="no-print" style="padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <form action="checks.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                    <input type="hidden" name="tab" value="funding">
                    <select name="date_field" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; font-weight: 600; cursor: pointer;" onchange="this.form.submit()">
                        <option value="check_date" <?= $dateField === 'check_date' ? 'selected' : '' ?>>Cheque Date:</option>
                        <option value="issue_date" <?= $dateField === 'issue_date' ? 'selected' : '' ?>>Issue Date:</option>
                    </select>
                    <a href="checks.php?tab=funding&date_field=<?= urlencode($dateField) ?>&cheque_filter=all" class="btn btn-sm <?= $chkDateFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
                    <a href="checks.php?tab=funding&date_field=<?= urlencode($dateField) ?>&cheque_filter=today" class="btn btn-sm <?= $chkDateFilter === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
                    <a href="checks.php?tab=funding&date_field=<?= urlencode($dateField) ?>&cheque_filter=week" class="btn btn-sm <?= $chkDateFilter === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
                    <a href="checks.php?tab=funding&date_field=<?= urlencode($dateField) ?>&cheque_filter=month" class="btn btn-sm <?= $chkDateFilter === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
                    
                    <span style="color: var(--text-muted);">&bull;</span>
                    <input type="hidden" name="cheque_filter" value="custom">
                    <input type="date" name="chk_start" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($startDate) ?>">
                    <span>&rarr;</span>
                    <input type="date" name="chk_end" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($endDate) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if (!empty($startDate) || !empty($endDate) || $chkDateFilter !== 'all'): ?>
                        <a href="checks.php?tab=funding&date_field=<?= urlencode($dateField) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
                    <?php endif; ?>
                </form>

                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <input type="text" class="search-input" placeholder="Search payee, check #, voucher..." data-table-search="#pendingChecksTable">

                    <!-- Subtle Column Picker Dropdown -->
                    <div class="col-picker-dropdown">
                        <button type="button" class="btn-col-picker" title="Toggle Columns">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="12" y1="3" x2="12" y2="21"></line>
                            </svg>
                            <span>Columns</span>
                            <svg class="picker-caret" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="col-picker-menu">
                            <div class="col-picker-header">
                                <span class="col-picker-title">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Columns
                                </span>
                                <button type="button" class="col-picker-reset-btn">Reset</button>
                            </div>
                            <div class="col-picker-body">
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-priority" checked>
                                    <span>Priority</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-issuedate" checked>
                                    <span>Issue Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-chequedate" checked>
                                    <span>Cheque Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-check" checked>
                                    <span>Check # &amp; Bank</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-payee" checked>
                                    <span>Payee / Supplier</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-project" checked>
                                    <span>Project &amp; District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <?= render_custom_field_column_pickers('checks') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if (empty($pendingChecks)): ?>
                    <div class="empty-state">
                        No pending checks awaiting funding.
                        <p>Click "+ New Check" to encode hand-written checks into the system.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="pendingChecksTable">
                            <thead>
                                <tr>
                                    <th style="width: 32px; text-align: center;" class="no-print">
                                        <input type="checkbox" id="selectAllChecks" class="custom-checkbox">
                                    </th>
                                    <th class="col-toggle-chk-priority" style="min-width: 105px;">PRIORITY</th>
                                    <th class="col-toggle-chk-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                                    <th class="col-toggle-chk-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                                    <th class="col-toggle-chk-check" style="min-width: 140px;">CHECK # &amp; BANK</th>
                                    <th class="col-toggle-chk-payee" style="min-width: 180px;">PAYEE / SUPPLIER</th>
                                    <th class="col-toggle-chk-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                                    <th class="col-toggle-chk-voucher" style="min-width: 110px;">VOUCHER #</th>
                                    <th class="col-toggle-chk-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <?= render_custom_field_table_headers('checks') ?>
                                    <th style="min-width: 135px; text-align: center;" class="no-print">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingChecks as $chk): ?>
                                    <?php 
                                        $typeTag = get_check_maturity_tag($chk['issue_date'], $chk['check_date']);
                                        $priority = $chk['priority_status'] ?: 'Standard';
                                    ?>
                                    <tr style="<?= $priority === 'Prioritized' ? 'background: #fffbeb;' : '' ?>">
                                        <td style="text-align: center;" class="no-print">
                                            <input type="checkbox" class="custom-checkbox check-select-item" value="<?= $chk['id'] ?>" data-amount="<?= $chk['amount'] ?>">
                                        </td>
                                        <td class="col-toggle-chk-priority">
                                            <div class="no-print">
                                                <select class="check-priority-select form-control" data-check-id="<?= $chk['id'] ?>" style="padding: 2px 4px; font-size: 0.75rem; height: auto;">
                                                    <option value="Prioritized" <?= $priority === 'Prioritized' ? 'selected' : '' ?>>Prioritized</option>
                                                    <option value="Standard" <?= $priority === 'Standard' ? 'selected' : '' ?>>Standard</option>
                                                    <option value="Hold" <?= $priority === 'Hold' ? 'selected' : '' ?>>Hold</option>
                                                </select>
                                            </div>
                                            <div class="print-only" style="display: none; font-weight: 700;">
                                                <?= e($priority) ?>
                                            </div>
                                        </td>
                                        <td class="col-toggle-chk-issuedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['issue_date']) ?></td>
                                        <td class="col-toggle-chk-chequedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono">
                                            <?= !empty($chk['check_date']) ? format_date($chk['check_date']) : '<span style="color: var(--text-muted); font-style: italic;">Pending Date</span>' ?>
                                        </td>
                                        <td class="col-toggle-chk-check">
                                            <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($chk['check_number']) ?></span>
                                            <small style="color: var(--text-muted); font-size: 0.72rem; margin-left: 4px;">(<?= e($chk['bank_name']) ?>)</small>
                                        </td>
                                        <td class="col-toggle-chk-payee" style="font-weight: 600; color: #0f172a;"><?= e($chk['payee']) ?></td>
                                        <td class="col-toggle-chk-project">
                                            <span style="font-weight: 500;"><?= e($chk['project_name']) ?></span>
                                            <?php if (!empty($chk['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($chk['project_district']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-voucher">
                                            <?php if (!empty($chk['voucher_number'])): ?>
                                                <a href="vouchers.php?search=<?= urlencode($chk['voucher_number']) ?>" class="voucher-code-badge" title="Linked Voucher">
                                                    <?= e($chk['voucher_number']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-amount excel-num" style="color: #0f172a; font-weight: 700;"><?= format_money($chk['amount']) ?></td>
                                        <?= render_custom_field_table_cells('checks', $chk['custom_fields'] ?? '') ?>
                                        <td class="action-cell no-print" style="text-align: center;">
                                            <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center;">
                                                <button type="button" class="btn btn-outline btn-sm btn-print-check-menu"
                                                        style="padding: 1px 6px; font-size: 0.72rem;"
                                                        title="Print Official Voucher or Bank Cheque"
                                                        data-check-id="<?= $chk['id'] ?>"
                                                        data-check-num="<?= e($chk['check_number']) ?>"
                                                        data-voucher-num="<?= e($chk['voucher_number'] ?? '') ?>">
                                                    Print
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                        style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                        title="Edit Full Check Details"
                                                        data-check='<?= htmlspecialchars(json_encode($chk), ENT_QUOTES, 'UTF-8') ?>'>
                                                    Edit
                                                </button>
                                                <!-- Fund Button triggers Cheque Date confirmation modal -->
                                                <button type="button" class="btn btn-primary btn-sm btn-ajax-fund"
                                                        style="padding: 1px 7px; font-size: 0.72rem;"
                                                        data-check-id="<?= $chk['id'] ?>"
                                                        data-check-num="<?= e($chk['check_number']) ?>"
                                                        data-payee="<?= e($chk['payee']) ?>"
                                                        data-amount="<?= $chk['amount'] ?>"
                                                        data-issue-date="<?= e($chk['issue_date']) ?>"
                                                        data-check-date="<?= e($chk['check_date']) ?>"
                                                        data-priority="<?= e($priority) ?>">
                                                    Fund
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="no-print"></th>
                                    <th class="col-toggle-chk-priority"></th>
                                    <th class="col-toggle-chk-issuedate"></th>
                                    <th class="col-toggle-chk-chequedate"></th>
                                    <th class="col-toggle-chk-check"></th>
                                    <th class="col-toggle-chk-payee"></th>
                                    <th class="col-toggle-chk-project"></th>
                                    <th class="col-toggle-chk-voucher" style="text-align: right; text-transform: uppercase;">Total:</th>
                                    <th class="col-toggle-chk-amount excel-num" style="color: #dc2626; font-weight: 800;"><?= format_money($pendingTotal) ?></th>
                                    <?= render_custom_field_table_footers('checks') ?>
                                    <th class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Spreadsheet Status Bar -->
                    <div class="excel-sheet-footer no-print">
                        <span><span class="excel-status-dot"></span> Sheet Ready &bull; <?= count($pendingChecks) ?> cheque(s) pending</span>
                        <span>Pending Funding Total: <strong style="color: #dc2626; font-size: 0.85rem;"><?= format_money($pendingTotal) ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 2: Funded Cheques View (Filtered by cheque_date) -->
    <!-- ======================================================== -->
    <div class="tab-pane <?= $activeTab === 'funded' ? 'active' : '' ?>" id="tab-funded">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>Funded Cheques Registry (<?= count($fundedChecks) ?> cheques)</span>
            <span>Total Funded: <?= format_money($fundedTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge"><?= count($fundedChecks) ?> CHEQUES</span>
                    <span>Funded Cheques Registry</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Total Funded: <strong class="excel-mono" style="color: #059669; font-size: 0.88rem;"><?= format_money($fundedTotal) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search funded checks..." data-table-search="#fundedChecksTable">
                    </div>

                    <!-- Subtle Column Picker Dropdown -->
                    <div class="col-picker-dropdown">
                        <button type="button" class="btn-col-picker" title="Toggle Columns">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="12" y1="3" x2="12" y2="21"></line>
                            </svg>
                            <span>Columns</span>
                            <svg class="picker-caret" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="col-picker-menu">
                            <div class="col-picker-header">
                                <span class="col-picker-title">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Columns
                                </span>
                                <button type="button" class="col-picker-reset-btn">Reset</button>
                            </div>
                            <div class="col-picker-body">
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-chequedate" checked>
                                    <span>Cheque Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-issuedate" checked>
                                    <span>Issue Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-priority" checked>
                                    <span>Priority</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-check" checked>
                                    <span>Check # &amp; Bank</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-payee" checked>
                                    <span>Payee / Supplier</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-project" checked>
                                    <span>Project &amp; District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-funded" checked>
                                    <span>Funded At &amp; By</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-status" checked>
                                    <span>Status</span>
                                </label>
                                <?= render_custom_field_column_pickers('checks') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="no-print" style="padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <form action="checks.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                    <input type="hidden" name="tab" value="funded">
                    <select name="date_field" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; font-weight: 600; cursor: pointer;" onchange="this.form.submit()">
                        <option value="check_date" <?= $dateField === 'check_date' ? 'selected' : '' ?>>Cheque Date:</option>
                        <option value="issue_date" <?= $dateField === 'issue_date' ? 'selected' : '' ?>>Issue Date:</option>
                    </select>
                    <a href="checks.php?tab=funded&date_field=<?= urlencode($dateField) ?>&cheque_filter=all" class="btn btn-sm <?= $chkDateFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
                    <a href="checks.php?tab=funded&date_field=<?= urlencode($dateField) ?>&cheque_filter=today" class="btn btn-sm <?= $chkDateFilter === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
                    <a href="checks.php?tab=funded&date_field=<?= urlencode($dateField) ?>&cheque_filter=week" class="btn btn-sm <?= $chkDateFilter === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
                    <a href="checks.php?tab=funded&date_field=<?= urlencode($dateField) ?>&cheque_filter=month" class="btn btn-sm <?= $chkDateFilter === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
                    
                    <span style="color: var(--text-muted);">&bull;</span>
                    <input type="hidden" name="cheque_filter" value="custom">
                    <input type="date" name="chk_start" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($startDate) ?>">
                    <span>&rarr;</span>
                    <input type="date" name="chk_end" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($endDate) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if (!empty($startDate) || !empty($endDate) || $chkDateFilter !== 'all'): ?>
                        <a href="checks.php?tab=funded&date_field=<?= urlencode($dateField) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div>
                <?php if (empty($fundedChecks)): ?>
                    <div class="empty-state">
                        No funded cheques found for this date period.
                        <p>Approved checks will appear here with funding dates and bank release tracking.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="fundedChecksTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-chk-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                                    <th class="col-toggle-chk-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                                    <th class="col-toggle-chk-priority" style="min-width: 95px;">PRIORITY</th>
                                    <th class="col-toggle-chk-check" style="min-width: 140px;">CHECK # &amp; BANK</th>
                                    <th class="col-toggle-chk-payee" style="min-width: 180px;">PAYEE / SUPPLIER</th>
                                    <th class="col-toggle-chk-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                                    <th class="col-toggle-chk-voucher" style="min-width: 110px;">VOUCHER #</th>
                                    <th class="col-toggle-chk-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <th class="col-toggle-chk-funded" style="min-width: 140px;">FUNDED AT &amp; BY</th>
                                    <th class="col-toggle-chk-status" style="min-width: 90px; text-align: center;">STATUS</th>
                                    <?= render_custom_field_table_headers('checks') ?>
                                    <th style="min-width: 125px; text-align: center;" class="no-print">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fundedChecks as $chk): ?>
                                    <?php 
                                        $typeTag = get_check_maturity_tag($chk['issue_date'], $chk['check_date']);
                                        $priority = $chk['priority_status'] ?: 'Standard';
                                    ?>
                                    <tr>
                                        <td class="col-toggle-chk-chequedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['check_date']) ?></td>
                                        <td class="col-toggle-chk-issuedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['issue_date']) ?></td>
                                        <td class="col-toggle-chk-priority">
                                            <?php if ($priority === 'Prioritized'): ?>
                                                <span class="badge badge-prioritized">Prioritized</span>
                                            <?php elseif ($priority === 'Hold'): ?>
                                                <span class="badge badge-hold">Hold</span>
                                            <?php else: ?>
                                                <span class="badge badge-standard">Standard</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-check">
                                            <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($chk['check_number']) ?></span>
                                            <small style="color: var(--text-muted); font-size: 0.72rem; margin-left: 4px;">(<?= e($chk['bank_name']) ?>)</small>
                                        </td>
                                        <td class="col-toggle-chk-payee" style="font-weight: 600; color: #0f172a;"><?= e($chk['payee']) ?></td>
                                        <td class="col-toggle-chk-project">
                                            <span style="font-weight: 500;"><?= e($chk['project_name']) ?></span>
                                            <?php if (!empty($chk['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($chk['project_district']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-voucher">
                                            <?php if (!empty($chk['voucher_number'])): ?>
                                                <a href="vouchers.php?search=<?= urlencode($chk['voucher_number']) ?>" class="badge badge-approved excel-mono" title="Linked Voucher">
                                                    <?= e($chk['voucher_number']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-amount excel-num" style="color: #0f172a; font-weight: 700;">
                                            <?= format_money($chk['amount']) ?>
                                        </td>
                                        <td class="col-toggle-chk-funded" style="font-size: 0.78rem;">
                                            <span class="excel-mono"><?= format_date($chk['funded_at'], 'M d, Y h:i A') ?></span>
                                            <span style="color: var(--text-muted); margin-left: 4px;">(<?= e($chk['funded_by'] ?: 'Manager') ?>)</span>
                                        </td>
                                        <td class="col-toggle-chk-status" style="text-align: center;">
                                            <span class="badge badge-funded">Funded</span>
                                        </td>
                                        <?= render_custom_field_table_cells('checks', $chk['custom_fields'] ?? '') ?>
                                        <td class="action-cell no-print" style="text-align: center;">
                                            <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center;">
                                                <button type="button" class="btn btn-outline btn-sm btn-print-check-menu"
                                                        style="padding: 1px 6px; font-size: 0.72rem;"
                                                        title="Print Official Voucher or Bank Cheque"
                                                        data-check-id="<?= $chk['id'] ?>"
                                                        data-check-num="<?= e($chk['check_number']) ?>"
                                                        data-voucher-num="<?= e($chk['voucher_number'] ?? '') ?>">
                                                    Print
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                        style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                        title="Edit Full Check Details"
                                                        data-check='<?= htmlspecialchars(json_encode($chk), ENT_QUOTES, 'UTF-8') ?>'>
                                                    Edit
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="col-toggle-chk-chequedate"></th>
                                    <th class="col-toggle-chk-issuedate"></th>
                                    <th class="col-toggle-chk-priority"></th>
                                    <th class="col-toggle-chk-check"></th>
                                    <th class="col-toggle-chk-payee"></th>
                                    <th class="col-toggle-chk-project"></th>
                                    <th class="col-toggle-chk-voucher" style="text-align: right; text-transform: uppercase;">Total:</th>
                                    <th class="col-toggle-chk-amount excel-num" style="color: #059669;"><?= format_money($fundedTotal) ?></th>
                                    <th class="col-toggle-chk-funded"></th>
                                    <th class="col-toggle-chk-status"></th>
                                    <?= render_custom_field_table_footers('checks') ?>
                                    <th class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 3: Holding Area (priority_status = 'Hold', Filtered by cheque_date) -->
    <!-- ======================================================== -->
    <div class="tab-pane <?= $activeTab === 'holding' ? 'active' : '' ?>" id="tab-holding">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>Holding Area - Cheques Withheld From Release (<?= count($holdingChecks) ?> cheques)</span>
            <span>Withheld Amount: <?= format_money($holdingTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c; border-color: rgba(239, 68, 68, 0.3);"><?= count($holdingChecks) ?> ON HOLD</span>
                    <span>Holding Area &mdash; Cheques Withheld From Release</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Held Cash Flow: <strong class="excel-mono" style="color: #dc2626; font-size: 0.88rem;"><?= format_money($holdingTotal) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search holding checks..." data-table-search="#holdingChecksTable">
                    </div>

                    <!-- Subtle Column Picker Dropdown -->
                    <div class="col-picker-dropdown">
                        <button type="button" class="btn-col-picker" title="Toggle Columns">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="12" y1="3" x2="12" y2="21"></line>
                            </svg>
                            <span>Columns</span>
                            <svg class="picker-caret" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="col-picker-menu">
                            <div class="col-picker-header">
                                <span class="col-picker-title">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Columns
                                </span>
                                <button type="button" class="col-picker-reset-btn">Reset</button>
                            </div>
                            <div class="col-picker-body">
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-status" checked>
                                    <span>Status</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-chequedate" checked>
                                    <span>Cheque Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-issuedate" checked>
                                    <span>Issue Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-check" checked>
                                    <span>Check # &amp; Bank</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-payee" checked>
                                    <span>Payee / Supplier</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-project" checked>
                                    <span>Project &amp; District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <?= render_custom_field_column_pickers('checks') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="no-print" style="padding: 0.6rem 1.25rem; background: rgba(254, 242, 242, 0.7); backdrop-filter: blur(8px); border-bottom: 1px solid rgba(254, 205, 211, 0.8); font-size: 0.78rem; color: #991b1b; display: flex; align-items: center; gap: 0.5rem;">
                <span><strong>Holding Area:</strong> These signed checks are intentionally withheld from release to control company cash flow. Releasers must NOT hand out these checks until management unholds or prioritizes them.</span>
            </div>

            <!-- Filter Controls Bar -->
            <div class="no-print" style="padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <form action="checks.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                    <input type="hidden" name="tab" value="holding">
                    <select name="date_field" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; font-weight: 600; cursor: pointer;" onchange="this.form.submit()">
                        <option value="check_date" <?= $dateField === 'check_date' ? 'selected' : '' ?>>Cheque Date:</option>
                        <option value="issue_date" <?= $dateField === 'issue_date' ? 'selected' : '' ?>>Issue Date:</option>
                    </select>
                    <a href="checks.php?tab=holding&date_field=<?= urlencode($dateField) ?>&cheque_filter=all" class="btn btn-sm <?= $chkDateFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
                    <a href="checks.php?tab=holding&date_field=<?= urlencode($dateField) ?>&cheque_filter=today" class="btn btn-sm <?= $chkDateFilter === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
                    <a href="checks.php?tab=holding&date_field=<?= urlencode($dateField) ?>&cheque_filter=week" class="btn btn-sm <?= $chkDateFilter === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
                    <a href="checks.php?tab=holding&date_field=<?= urlencode($dateField) ?>&cheque_filter=month" class="btn btn-sm <?= $chkDateFilter === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
                    
                    <span style="color: var(--text-muted);">&bull;</span>
                    <input type="hidden" name="cheque_filter" value="custom">
                    <input type="date" name="chk_start" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($startDate) ?>">
                    <span>&rarr;</span>
                    <input type="date" name="chk_end" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($endDate) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if (!empty($startDate) || !empty($endDate) || $chkDateFilter !== 'all'): ?>
                        <a href="checks.php?tab=holding&date_field=<?= urlencode($dateField) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div>
                <?php if (empty($holdingChecks)): ?>
                    <div class="empty-state">
                        No checks currently in the holding area.
                        <p>Any check set to "Hold" status will be isolated here for cash flow protection.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="holdingChecksTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-chk-status" style="min-width: 95px; text-align: center;">STATUS</th>
                                    <th class="col-toggle-chk-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                                    <th class="col-toggle-chk-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                                    <th class="col-toggle-chk-check" style="min-width: 140px;">CHECK # &amp; BANK</th>
                                    <th class="col-toggle-chk-payee" style="min-width: 180px;">PAYEE / SUPPLIER</th>
                                    <th class="col-toggle-chk-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                                    <th class="col-toggle-chk-voucher" style="min-width: 110px;">VOUCHER #</th>
                                    <th class="col-toggle-chk-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <?= render_custom_field_table_headers('checks') ?>
                                    <th style="min-width: 180px; text-align: center;" class="no-print">RELEASE DECISION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holdingChecks as $chk): ?>
                                    <?php $typeTag = get_check_maturity_tag($chk['issue_date'], $chk['check_date']); ?>
                                    <tr style="background: rgba(254, 242, 242, 0.5);">
                                        <td class="col-toggle-chk-status" style="text-align: center;">
                                            <span class="badge badge-hold">ON HOLD</span>
                                        </td>
                                        <td class="col-toggle-chk-chequedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['check_date']) ?></td>
                                        <td class="col-toggle-chk-issuedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['issue_date']) ?></td>
                                        <td class="col-toggle-chk-check">
                                            <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($chk['check_number']) ?></span>
                                            <small style="color: var(--text-muted); font-size: 0.72rem; margin-left: 4px;">(<?= e($chk['bank_name']) ?>)</small>
                                        </td>
                                        <td class="col-toggle-chk-payee" style="font-weight: 600; color: #0f172a;"><?= e($chk['payee']) ?></td>
                                        <td class="col-toggle-chk-project">
                                            <span style="font-weight: 500;"><?= e($chk['project_name']) ?></span>
                                            <?php if (!empty($chk['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($chk['project_district']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-voucher">
                                            <?php if (!empty($chk['voucher_number'])): ?>
                                                <a href="vouchers.php?search=<?= urlencode($chk['voucher_number']) ?>" class="badge badge-approved excel-mono" title="Linked Voucher">
                                                    <?= e($chk['voucher_number']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-amount excel-num" style="color: #dc2626; font-weight: 700;"><?= format_money($chk['amount']) ?></td>
                                        <?= render_custom_field_table_cells('checks', $chk['custom_fields'] ?? '') ?>
                                        <td class="action-cell no-print" style="text-align: center;">
                                            <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                                                <button type="button" class="btn btn-outline btn-sm btn-print-check-menu"
                                                        style="padding: 1px 6px; font-size: 0.72rem;"
                                                        title="Print Official Voucher or Bank Cheque"
                                                        data-check-id="<?= $chk['id'] ?>"
                                                        data-check-num="<?= e($chk['check_number']) ?>"
                                                        data-voucher-num="<?= e($chk['voucher_number'] ?? '') ?>">
                                                    Print
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                        style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                        title="Edit Full Check Details"
                                                        data-check='<?= htmlspecialchars(json_encode($chk), ENT_QUOTES, 'UTF-8') ?>'>
                                                    Edit
                                                </button>
                                                <form action="api/actions.php" method="POST" style="margin:0; display:inline;">
                                                    <input type="hidden" name="action" value="update_check_priority">
                                                    <input type="hidden" name="check_id" value="<?= $chk['id'] ?>">
                                                    <input type="hidden" name="priority_status" value="Prioritized">
                                                    <button type="submit" class="btn btn-sm btn-primary" style="padding: 1px 6px; font-size: 0.72rem;" title="Move to Prioritized Release">Prioritize</button>
                                                </form>
                                                <form action="api/actions.php" method="POST" style="margin:0; display:inline;">
                                                    <input type="hidden" name="action" value="update_check_priority">
                                                    <input type="hidden" name="check_id" value="<?= $chk['id'] ?>">
                                                    <input type="hidden" name="priority_status" value="Standard">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="padding: 1px 6px; font-size: 0.72rem;" title="Return to Standard Release">Unhold</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="col-toggle-chk-status"></th>
                                    <th class="col-toggle-chk-chequedate"></th>
                                    <th class="col-toggle-chk-issuedate"></th>
                                    <th class="col-toggle-chk-check"></th>
                                    <th class="col-toggle-chk-payee"></th>
                                    <th class="col-toggle-chk-project"></th>
                                    <th class="col-toggle-chk-voucher" style="text-align: right; text-transform: uppercase;">Total:</th>
                                    <th class="col-toggle-chk-amount excel-num" style="color: #dc2626;"><?= format_money($holdingTotal) ?></th>
                                    <?= render_custom_field_table_footers('checks') ?>
                                    <th class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 4: All Checks -->
    <!-- ======================================================== -->
    <div class="tab-pane <?= $activeTab === 'all' ? 'active' : '' ?>" id="tab-all">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>All Cheques Master Registry (<?= $allChecksCount ?> cheques)</span>
            <span>Total Registered: <?= format_money(array_sum(array_column($allChecks, 'amount'))) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge"><?= $allChecksCount ?> TOTAL</span>
                    <span>All Cheques Master Registry</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Total Registered: <strong class="excel-mono" style="color: #0f172a; font-size: 0.88rem;"><?= format_money(array_sum(array_column($allChecks, 'amount'))) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search all checks..." data-table-search="#allChecksTable">
                    </div>

                    <!-- Subtle Column Picker Dropdown -->
                    <div class="col-picker-dropdown">
                        <button type="button" class="btn-col-picker" title="Toggle Columns">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="12" y1="3" x2="12" y2="21"></line>
                            </svg>
                            <span>Columns</span>
                            <svg class="picker-caret" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="col-picker-menu">
                            <div class="col-picker-header">
                                <span class="col-picker-title">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Columns
                                </span>
                                <button type="button" class="col-picker-reset-btn">Reset</button>
                            </div>
                            <div class="col-picker-body">
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-issuedate" checked>
                                    <span>Issue Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-chequedate" checked>
                                    <span>Cheque Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-priority" checked>
                                    <span>Priority</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-check" checked>
                                    <span>Check # &amp; Bank</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-payee" checked>
                                    <span>Payee / Supplier</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-project" checked>
                                    <span>Project &amp; District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="chk-status" checked>
                                    <span>Status</span>
                                </label>
                                <?= render_custom_field_column_pickers('checks') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="no-print" style="padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <form action="checks.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                    <input type="hidden" name="tab" value="all">
                    <select name="date_field" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; font-weight: 600; cursor: pointer;" onchange="this.form.submit()">
                        <option value="check_date" <?= $dateField === 'check_date' ? 'selected' : '' ?>>Cheque Date:</option>
                        <option value="issue_date" <?= $dateField === 'issue_date' ? 'selected' : '' ?>>Issue Date:</option>
                    </select>
                    <a href="checks.php?tab=all&date_field=<?= urlencode($dateField) ?>&cheque_filter=all" class="btn btn-sm <?= $chkDateFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
                    <a href="checks.php?tab=all&date_field=<?= urlencode($dateField) ?>&cheque_filter=today" class="btn btn-sm <?= $chkDateFilter === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
                    <a href="checks.php?tab=all&date_field=<?= urlencode($dateField) ?>&cheque_filter=week" class="btn btn-sm <?= $chkDateFilter === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
                    <a href="checks.php?tab=all&date_field=<?= urlencode($dateField) ?>&cheque_filter=month" class="btn btn-sm <?= $chkDateFilter === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
                    
                    <span style="color: var(--text-muted);">&bull;</span>
                    <input type="hidden" name="cheque_filter" value="custom">
                    <input type="date" name="chk_start" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($startDate) ?>">
                    <span>&rarr;</span>
                    <input type="date" name="chk_end" class="form-control" style="width: auto; padding: 0.25rem 0.45rem; font-size: 0.8rem;" value="<?= e($endDate) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if (!empty($startDate) || !empty($endDate) || $chkDateFilter !== 'all'): ?>
                        <a href="checks.php?tab=all&date_field=<?= urlencode($dateField) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div>
                <?php if (empty($allChecks)): ?>
                    <div class="empty-state">No checks recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="allChecksTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-chk-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                                    <th class="col-toggle-chk-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                                    <th class="col-toggle-chk-priority" style="min-width: 95px;">PRIORITY</th>
                                    <th class="col-toggle-chk-check" style="min-width: 140px;">CHECK # &amp; BANK</th>
                                    <th class="col-toggle-chk-payee" style="min-width: 180px;">PAYEE</th>
                                    <th class="col-toggle-chk-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                                    <th class="col-toggle-chk-voucher" style="min-width: 110px;">VOUCHER #</th>
                                    <th class="col-toggle-chk-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <th class="col-toggle-chk-status" style="min-width: 90px; text-align: center;">STATUS</th>
                                    <?= render_custom_field_table_headers('checks') ?>
                                    <th style="min-width: 135px; text-align: center;" class="no-print">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allChecks as $chk): ?>
                                    <?php 
                                        $typeTag = get_check_maturity_tag($chk['issue_date'], $chk['check_date']);
                                        $priority = $chk['priority_status'] ?: 'Standard';
                                    ?>
                                    <tr>
                                        <td class="col-toggle-chk-issuedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['issue_date']) ?></td>
                                        <td class="col-toggle-chk-chequedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono"><?= format_date($chk['check_date']) ?></td>
                                        <td class="col-toggle-chk-priority">
                                            <?php if ($priority === 'Prioritized'): ?>
                                                <span class="badge badge-prioritized">Prioritized</span>
                                            <?php elseif ($priority === 'Hold'): ?>
                                                <span class="badge badge-hold">Hold</span>
                                            <?php else: ?>
                                                <span class="badge badge-standard">Standard</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-check">
                                            <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($chk['check_number']) ?></span>
                                            <small style="color: var(--text-muted); font-size: 0.72rem; margin-left: 4px;">(<?= e($chk['bank_name']) ?>)</small>
                                        </td>
                                        <td class="col-toggle-chk-payee" style="font-weight: 600; color: #0f172a;"><?= e($chk['payee']) ?></td>
                                        <td class="col-toggle-chk-project">
                                            <span style="font-weight: 500;"><?= e($chk['project_name']) ?></span>
                                            <?php if (!empty($chk['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($chk['project_district']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-voucher">
                                            <?php if (!empty($chk['voucher_number'])): ?>
                                                <a href="vouchers.php?search=<?= urlencode($chk['voucher_number']) ?>" class="badge badge-approved excel-mono" title="Linked Voucher">
                                                    <?= e($chk['voucher_number']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-chk-amount excel-num" style="color: #0f172a; font-weight: 700;"><?= format_money($chk['amount']) ?></td>
                                        <td class="col-toggle-chk-status" style="text-align: center;">
                                            <?php if ($chk['status'] === 'funded'): ?>
                                                <span class="badge badge-funded">Funded</span>
                                            <?php elseif ($chk['status'] === 'cancelled'): ?>
                                                <span class="badge badge-cancelled">Cancelled</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <?= render_custom_field_table_cells('checks', $chk['custom_fields'] ?? '') ?>
                                        <td class="action-cell no-print" style="text-align: center;">
                                            <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                                                <button type="button" class="btn btn-outline btn-sm btn-print-check-menu"
                                                        style="padding: 1px 6px; font-size: 0.72rem;"
                                                        title="Print Official Voucher or Bank Cheque"
                                                        data-check-id="<?= $chk['id'] ?>"
                                                        data-check-num="<?= e($chk['check_number']) ?>"
                                                        data-voucher-num="<?= e($chk['voucher_number'] ?? '') ?>">
                                                    Print
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                        style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                        title="Edit Full Check Details"
                                                        data-check='<?= htmlspecialchars(json_encode($chk), ENT_QUOTES, 'UTF-8') ?>'>
                                                    Edit
                                                </button>
                                                <?php if ($chk['status'] === 'pending'): ?>
                                                    <form action="api/actions.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Void/Cancel check?');">
                                                        <input type="hidden" name="action" value="cancel_check">
                                                        <input type="hidden" name="check_id" value="<?= $chk['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 1px 6px; font-size: 0.72rem;">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="col-toggle-chk-issuedate"></th>
                                    <th class="col-toggle-chk-chequedate"></th>
                                    <th class="col-toggle-chk-priority"></th>
                                    <th class="col-toggle-chk-check"></th>
                                    <th class="col-toggle-chk-payee"></th>
                                    <th class="col-toggle-chk-project"></th>
                                    <th class="col-toggle-chk-voucher" style="text-align: right; text-transform: uppercase;">Total:</th>
                                    <th class="col-toggle-chk-amount excel-num" style="color: #0f172a;"><?= format_money(array_sum(array_column($allChecks, 'amount'))) ?></th>
                                    <th class="col-toggle-chk-status"></th>
                                    <?= render_custom_field_table_footers('checks') ?>
                                    <th class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Floating Batch Action Bar -->
<div class="batch-bar" id="batchBar">
    <div>
        <span id="batchCount" style="font-weight: 700;">0</span> check(s) selected &bull;
        Total: <strong id="batchTotal" style="color: #4ade80;">₱ 0.00</strong>
    </div>
    <form action="api/actions.php" method="POST" onsubmit="return confirm('Fund all selected checks?');">
        <input type="hidden" name="action" value="batch_fund_checks">
        <input type="hidden" name="check_ids" id="batchCheckIds" value="">
        <button type="submit" class="btn btn-success btn-sm">Fund Selected</button>
    </form>
</div>

<!-- Modal: Choose Print Format for Check -->
<div class="modal-overlay" id="modalChooseCheckPrint">
    <div class="modal-dialog" style="max-width: 460px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Print Document</h3>
                <p style="margin: 2px 0 0; font-size: 0.8rem; color: var(--text-muted);">
                    Choose print format for Check #<strong id="printModalCheckNum" style="color: var(--text-main);">-</strong>
                </p>
            </div>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem;">
            <!-- Option 1: Official Voucher -->
            <a id="linkModalPrintVoucher" href="#" target="_blank" class="card" style="padding: 1rem; text-decoration: none; display: flex; align-items: center; gap: 1rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); transition: all 0.2s; background: var(--bg-surface);">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Official Aarcon Builders Voucher</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                        Exact replica of company pre-printed voucher form with particulars, account charge, and 4-slot signatures.
                    </div>
                </div>
            </a>

            <!-- Option 2: Bank Cheque (Check Writer) -->
            <a id="linkModalPrintCheque" href="#" target="_blank" class="card" style="padding: 1rem; text-decoration: none; display: flex; align-items: center; gap: 1rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); transition: all 0.2s; background: var(--bg-surface);">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(220, 38, 38, 0.08); color: var(--aarcon-red); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Bank Cheque (Check Writer)</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                        8" x 3" check leaf layout with payee protection asterisks, words, crossed check, and direct printer alignment.
                    </div>
                </div>
            </a>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalChooseCheckPrint')">Close</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-print-check-menu').forEach(btn => {
            btn.addEventListener('click', () => {
                const checkId = btn.getAttribute('data-check-id');
                const checkNum = btn.getAttribute('data-check-num');
                document.getElementById('printModalCheckNum').textContent = checkNum;

                document.getElementById('linkModalPrintVoucher').href = 'print_voucher.php?id=' + encodeURIComponent(checkId);
                document.getElementById('linkModalPrintCheque').href = 'print_check.php?id=' + encodeURIComponent(checkId);

                openModal('modalChooseCheckPrint');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
