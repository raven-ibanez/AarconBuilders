<?php
/**
 * Aarcon Builders - Module 4: The Final Disbursement & Cash Balance Report
 * Fully editable from the report (Record/Edit Collections, Edit Checks, Edit Cash Expenses)
 * With individual per-category filters (Status, District, Payment Method, Priority, etc.)
 */

$pageTitle = 'Disbursement & Cash Balance Report';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$districtsList = get_project_districts();

// Global Period Filter
$period    = $_GET['period'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

// Category-Specific Filters
$colStatus   = $_GET['col_status'] ?? 'all';   // 'all', 'collected', 'in process'
$colDistrict = $_GET['col_district'] ?? 'all';
$colMethod   = $_GET['col_method'] ?? 'all';

$chkPriority = $_GET['chk_priority'] ?? 'all'; // 'all', 'Standard', 'Prioritized', 'Hold'
$chkDistrict = $_GET['chk_district'] ?? 'all';

$cashDistrict = $_GET['cash_district'] ?? 'all';
$cashStatus   = $_GET['cash_status'] ?? 'all'; // 'all', 'approved', 'disbursed', 'pending'

$colWhere  = 'WHERE 1=1';
$chkWhere  = "WHERE status = 'funded'";
$cashWhere = "WHERE status IN ('approved', 'disbursed')";

$periodLabel = 'All Time';

if ($period === 'today') {
    $today = date('Y-m-d');
    $colWhere  .= " AND collection_date = '{$today}'";
    $chkWhere  .= " AND check_date = '{$today}'";
    $cashWhere .= " AND request_date = '{$today}'";
    $periodLabel = 'Today (' . date('M d, Y') . ')';
} elseif ($period === 'week') {
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek   = date('Y-m-d', strtotime('sunday this week'));
    $colWhere  .= " AND collection_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $chkWhere  .= " AND check_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $cashWhere .= " AND request_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $periodLabel = 'This Week';
} elseif ($period === 'month') {
    $startOfMonth = date('Y-m-01');
    $endOfMonth   = date('Y-m-t');
    $colWhere  .= " AND collection_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $chkWhere  .= " AND check_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $cashWhere .= " AND request_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $periodLabel = date('F Y');
} elseif ($period === 'custom' && !empty($startDate) && !empty($endDate)) {
    $colWhere  .= " AND collection_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $chkWhere  .= " AND check_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $cashWhere .= " AND request_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $periodLabel = date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
}

// Apply Category 1: Collections Filters
if ($colStatus !== 'all' && !empty($colStatus)) {
    $colWhere .= " AND status = " . $db->quote($colStatus);
}
if ($colDistrict !== 'all' && !empty($colDistrict)) {
    $colWhere .= " AND project_district = " . $db->quote($colDistrict);
}
if ($colMethod !== 'all' && !empty($colMethod)) {
    $colWhere .= " AND payment_method = " . $db->quote($colMethod);
}

// Apply Category 2: Funded Checks Filters
if ($chkPriority !== 'all' && !empty($chkPriority)) {
    $chkWhere .= " AND priority_status = " . $db->quote($chkPriority);
}
if ($chkDistrict !== 'all' && !empty($chkDistrict)) {
    $chkWhere .= " AND project_district = " . $db->quote($chkDistrict);
}

// Apply Category 3: Cash Expenses Filters
if ($cashDistrict !== 'all' && !empty($cashDistrict)) {
    $cashWhere .= " AND project_district = " . $db->quote($cashDistrict);
}
if ($cashStatus !== 'all' && !empty($cashStatus)) {
    $cashWhere .= " AND status = " . $db->quote($cashStatus);
}

// 1. Collections (Inflow)
$collections = $db->query("SELECT * FROM collections {$colWhere} ORDER BY collection_date ASC")->fetchAll();
$totalInflow = array_sum(array_column($collections, 'amount'));

// 2. Funded Checks (Outflow)
$fundedChecks = $db->query("SELECT * FROM checks {$chkWhere} ORDER BY check_date ASC")->fetchAll();
$totalFundedChecks = array_sum(array_column($fundedChecks, 'amount'));

// 3. Approved Cash Expenses (Outflow)
$approvedCash = $db->query("SELECT * FROM cash_requests {$cashWhere} ORDER BY request_date ASC")->fetchAll();
$totalApprovedCash = array_sum(array_column($approvedCash, 'amount'));

// Net Cash Balance
$totalOutflow = $totalFundedChecks + $totalApprovedCash;
$netCashBalance = $totalInflow - $totalOutflow;
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Disbursement &amp; Cash Balance Report</h1>
        <p class="page-subtitle">Statement period: <?= e($periodLabel) ?> &bull; Real-time editable ledger</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn btn-success btn-sm" data-open-modal="modalAddCollection">
            + Input Collection
        </button>
        <button type="button" class="btn btn-outline btn-sm" data-open-modal="modalAddCheck">
            + New Check / Voucher
        </button>
        <button type="button" class="btn btn-outline btn-sm" data-open-modal="modalAddCashRequest">
            + Cash Request
        </button>
        <button class="btn btn-primary btn-sm" onclick="window.print();">Print Report</button>
    </div>
</div>

<!-- Global Period Filter Bar -->
<div class="no-print" style="padding: 0.75rem 1.25rem; margin-bottom: 1.25rem; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); border-radius: var(--radius-lg, 14px); box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03));">
    <form action="disbursement_report.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin: 0;">
        <!-- Retain category filters across period clicks -->
        <input type="hidden" name="col_status" value="<?= e($colStatus) ?>">
        <input type="hidden" name="col_district" value="<?= e($colDistrict) ?>">
        <input type="hidden" name="col_method" value="<?= e($colMethod) ?>">
        <input type="hidden" name="chk_priority" value="<?= e($chkPriority) ?>">
        <input type="hidden" name="chk_district" value="<?= e($chkDistrict) ?>">
        <input type="hidden" name="cash_district" value="<?= e($cashDistrict) ?>">
        <input type="hidden" name="cash_status" value="<?= e($cashStatus) ?>">

        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
            <span style="font-weight: 600; font-size: 0.82rem; color: #475569; margin-right: 0.25rem;">Statement Period:</span>
            <a href="disbursement_report.php?period=all&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&chk_priority=<?= urlencode($chkPriority) ?>&chk_district=<?= urlencode($chkDistrict) ?>" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="disbursement_report.php?period=month&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&chk_priority=<?= urlencode($chkPriority) ?>&chk_district=<?= urlencode($chkDistrict) ?>" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">Month</a>
            <a href="disbursement_report.php?period=week&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&chk_priority=<?= urlencode($chkPriority) ?>&chk_district=<?= urlencode($chkDistrict) ?>" class="btn btn-sm <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?>">Week</a>
            <a href="disbursement_report.php?period=today&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&chk_priority=<?= urlencode($chkPriority) ?>&chk_district=<?= urlencode($chkDistrict) ?>" class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
        </div>
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="start_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($startDate) ?>" required>
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" name="end_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($endDate) ?>" required>
            <button type="submit" class="btn btn-outline btn-sm">Filter Dates</button>
            <?php if ($period !== 'all' || $colStatus !== 'all' || $colDistrict !== 'all' || $chkPriority !== 'all' || $chkDistrict !== 'all'): ?>
                <a href="disbursement_report.php" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset All</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Statement Wrapper -->
<div>

    <!-- Dedicated Corporate Executive Balance Summary for Print -->
    <div class="print-summary-box" style="display: none;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; border: 1.5px solid #000000;">
            <thead>
                <tr style="background: #f1f5f9; text-align: center; font-size: 8pt; font-weight: 800;">
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">1. TOTAL COLLECTIONS (INFLOW)</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">2. LESS: FUNDED CHEQUES (OUTFLOW)</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">3. LESS: CASH EXPENSES (OUTFLOW)</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; background: #e2e8f0; text-transform: uppercase;">NET AVAILABLE CASH BALANCE</th>
                </tr>
            </thead>
            <tbody>
                <tr style="text-align: center; font-size: 10.5pt; font-weight: 900;">
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #047857; font-family: Arial, sans-serif;">
                        <?= format_money($totalInflow) ?>
                        <div style="font-size: 7pt; font-weight: 600; color: #64748b; margin-top: 2px;">
                            <?= count($collections) ?> collection receipt(s)
                        </div>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #b91c1c; font-family: Arial, sans-serif;">
                        <?= format_money($totalFundedChecks) ?>
                        <div style="font-size: 7pt; font-weight: 600; color: #64748b; margin-top: 2px;">
                            <?= count($fundedChecks) ?> funded check(s)
                        </div>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #b91c1c; font-family: Arial, sans-serif;">
                        <?= format_money($totalApprovedCash) ?>
                        <div style="font-size: 7pt; font-weight: 600; color: #64748b; margin-top: 2px;">
                            <?= count($approvedCash) ?> expense voucher(s)
                        </div>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: <?= $netCashBalance >= 0 ? '#000000' : '#dc2626' ?>; background: #f8fafc; font-family: Arial, sans-serif;">
                        <?= format_money($netCashBalance) ?>
                        <div style="font-size: 7pt; font-weight: 700; color: <?= $netCashBalance >= 0 ? '#047857' : '#dc2626' ?>; margin-top: 2px;">
                            <?= $netCashBalance >= 0 ? 'Net Surplus Remaining' : 'Cash Deficit' ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Screen Summary Box (Apple Liquid Glass) -->
    <div class="balance-card-grid no-print" style="margin-bottom: 1.5rem; padding: 1.25rem 1.5rem; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); border-radius: var(--radius-lg, 14px); box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03));">
        <div>
            <div class="balance-title" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Net Available Cash Balance</div>
            <div class="balance-value excel-mono" style="color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>; font-size: 1.85rem; font-weight: 800; letter-spacing: -0.02em;">
                <?= format_money($netCashBalance) ?>
            </div>
            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                <?= $netCashBalance >= 0 ? 'Surplus cash remaining' : 'Deficit: Outflow exceeds collections' ?>
            </div>
        </div>

        <div class="balance-equation">
            <div class="eq-item">
                <span class="eq-label">Total Collections</span>
                <span class="eq-value excel-mono" style="color: var(--color-success); font-weight: 700;"><?= format_money($totalInflow) ?></span>
            </div>
            <span class="eq-op">&minus;</span>
            <div class="eq-item">
                <span class="eq-label">Funded Checks</span>
                <span class="eq-value excel-mono" style="font-weight: 700;"><?= format_money($totalFundedChecks) ?></span>
            </div>
            <span class="eq-op">&minus;</span>
            <div class="eq-item">
                <span class="eq-label">Cash Expenses</span>
                <span class="eq-value excel-mono" style="font-weight: 700;"><?= format_money($totalApprovedCash) ?></span>
            </div>
            <span class="eq-op">=</span>
            <div class="eq-item">
                <span class="eq-label">Net Balance</span>
                <span class="eq-value excel-mono" style="font-weight: 800; color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>;"><?= format_money($netCashBalance) ?></span>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 1. COLLECTIONS SECTION (INFLOW) -->
    <!-- With dedicated filter bar and editable rows -->
    <!-- ======================================================== -->
    <div class="excel-sheet-card" style="margin-bottom: 2rem;">
        
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>1. Inflow: Collections (<?= count($collections) ?> receipts)</span>
            <span>Subtotal: <?= format_money($totalInflow) ?></span>
        </div>

        <!-- Excel Sheet Header -->
        <div class="excel-sheet-header no-print">
            <div class="excel-sheet-title">
                <span class="excel-sheet-badge" style="background: rgba(16, 185, 129, 0.15); color: #065f46; border-color: rgba(16, 185, 129, 0.3);"><?= count($collections) ?> INFLOW</span>
                <span>1. Inflow: Collections Journal</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                    Subtotal: <strong class="excel-mono" style="color: #059669; font-size: 0.92rem;"><?= format_money($totalInflow) ?></strong>
                </div>
                <div class="excel-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search collections..." data-table-search="#disbCollectionsTable">
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
                                <input type="checkbox" data-report-toggle="disb-col-date" checked>
                                <span>Date</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-receipt" checked>
                                <span>OR / Receipt #</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-client" checked>
                                <span>Client / Customer</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-project" checked>
                                <span>Project &amp; District</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-method" checked>
                                <span>Method</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-status" checked>
                                <span>Status</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-col-amount" checked>
                                <span>Amount</span>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-success btn-sm no-print" data-open-modal="modalAddCollection" style="padding: 2px 8px; font-size: 0.75rem;">
                    + Input Collection
                </button>
            </div>
        </div>

        <!-- Dedicated Category Filter: Collections -->
        <div class="no-print" style="padding: 0.65rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <form action="disbursement_report.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                <!-- Preserve other filters -->
                <input type="hidden" name="period" value="<?= e($period) ?>">
                <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
                <input type="hidden" name="chk_priority" value="<?= e($chkPriority) ?>">
                <input type="hidden" name="chk_district" value="<?= e($chkDistrict) ?>">
                <input type="hidden" name="cash_district" value="<?= e($cashDistrict) ?>">
                <input type="hidden" name="cash_status" value="<?= e($cashStatus) ?>">

                <span style="font-weight: 600; font-size: 0.78rem; color: #475569;">Filter:</span>
                
                <select name="col_status" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all" <?= $colStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="collected" <?= $colStatus === 'collected' ? 'selected' : '' ?>>Collected (Realized)</option>
                    <option value="in process" <?= $colStatus === 'in process' ? 'selected' : '' ?>>In Process (Pending)</option>
                </select>

                <select name="col_district" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Districts</option>
                    <?php foreach ($districtsList as $dCode => $dName): ?>
                        <option value="<?= e($dCode) ?>" <?= $colDistrict === $dCode ? 'selected' : '' ?>><?= e($dCode) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="col_method" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Payment Methods</option>
                    <option value="Bank Deposit" <?= $colMethod === 'Bank Deposit' ? 'selected' : '' ?>>Bank Deposit</option>
                    <option value="Cash" <?= $colMethod === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Online Transfer" <?= $colMethod === 'Online Transfer' ? 'selected' : '' ?>>Online Transfer</option>
                    <option value="Incoming Check" <?= $colMethod === 'Incoming Check' ? 'selected' : '' ?>>Incoming Check</option>
                </select>

                <?php if ($colStatus !== 'all' || $colDistrict !== 'all' || $colMethod !== 'all'): ?>
                    <a href="disbursement_report.php?period=<?= urlencode($period) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&col_status=all&col_district=all&col_method=all&chk_priority=<?= urlencode($chkPriority) ?>&chk_district=<?= urlencode($chkDistrict) ?>" style="color: var(--color-danger); text-decoration: none; font-size: 0.75rem;">
                        &times; Reset Filter
                    </a>
                <?php endif; ?>
            </form>
            <div style="color: var(--text-muted); font-size: 0.75rem;">
                Showing <strong><?= count($collections) ?></strong> collection receipt(s)
            </div>
        </div>

        <div>
            <?php if (empty($collections)): ?>
                <div class="empty-state">No collections recorded matching current filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="excel-table" id="disbCollectionsTable">
                        <thead>
                            <tr>
                                <th class="col-toggle-disb-col-date" style="min-width: 95px; text-align: center;">DATE</th>
                                <th class="col-toggle-disb-col-receipt" style="min-width: 140px;">OR / RECEIPT #</th>
                                <th class="col-toggle-disb-col-client" style="min-width: 180px;">CLIENT / CUSTOMER</th>
                                <th class="col-toggle-disb-col-project" style="min-width: 170px;">PROJECT &amp; DISTRICT</th>
                                <th class="col-toggle-disb-col-method" style="min-width: 120px;">METHOD</th>
                                <th class="col-toggle-disb-col-status" style="min-width: 95px; text-align: center;">STATUS</th>
                                <th class="col-toggle-disb-col-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                <th style="min-width: 80px; text-align: center;" class="no-print">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $col): ?>
                                <tr>
                                    <td class="col-toggle-disb-col-date excel-mono" style="text-align: center; font-size: 0.78rem;"><?= format_date($col['collection_date']) ?></td>
                                    <td class="col-toggle-disb-col-receipt">
                                        <span class="excel-mono" style="font-weight: 700; color: #0f172a;"><?= e($col['receipt_no']) ?></span>
                                    </td>
                                    <td class="col-toggle-disb-col-client" style="font-weight: 600; color: #0f172a;"><?= e($col['client_name']) ?></td>
                                    <td class="col-toggle-disb-col-project">
                                        <span><?= e($col['project_name']) ?></span>
                                        <?php if (!empty($col['project_district'])): ?>
                                            <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($col['project_district']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-col-method"><span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.72rem;"><?= e($col['payment_method']) ?></span></td>
                                    <td class="col-toggle-disb-col-status" style="text-align: center;">
                                        <?php if ($col['status'] === 'collected'): ?>
                                            <span class="badge badge-collected" style="font-size: 0.7rem;">Collected</span>
                                        <?php else: ?>
                                            <span class="badge badge-inprocess" style="font-size: 0.7rem;">In Process</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-col-amount excel-num" style="color: #059669; font-weight: 700;"><?= format_money($col['amount']) ?></td>
                                    <td class="action-cell no-print" style="text-align: center;">
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-collection"
                                                style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                title="Edit Collection Record"
                                                data-collection='<?= htmlspecialchars(json_encode($col), ENT_QUOTES, 'UTF-8') ?>'>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" style="text-align: right; text-transform: uppercase;">Total Collections (Inflow):</th>
                                <th class="col-toggle-disb-col-amount excel-num" style="color: #059669;"><?= format_money($totalInflow) ?></th>
                                <th class="no-print"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 2. FUNDED CHECKS SECTION (OUTFLOW) -->
    <!-- With dedicated filter bar and editable rows -->
    <!-- ======================================================== -->
    <div class="excel-sheet-card" style="margin-bottom: 2rem;">
        
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>2. Outflow: Funded Cheques (<?= count($fundedChecks) ?> checks)</span>
            <span>Subtotal: <?= format_money($totalFundedChecks) ?></span>
        </div>

        <!-- Excel Sheet Header -->
        <div class="excel-sheet-header no-print">
            <div class="excel-sheet-title">
                <span class="excel-sheet-badge" style="background: rgba(37, 99, 235, 0.15); color: #1e40af; border-color: rgba(37, 99, 235, 0.3);"><?= count($fundedChecks) ?> CHEQUES</span>
                <span>2. Outflow: Funded Checks Ledger</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                    Subtotal: <strong class="excel-mono" style="color: #b91c1c; font-size: 0.92rem;"><?= format_money($totalFundedChecks) ?></strong>
                </div>
                <div class="excel-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search checks..." data-table-search="#disbChecksTable">
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
                                <input type="checkbox" data-report-toggle="disb-chk-issuedate" checked>
                                <span>Issue Date</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-chequedate" checked>
                                <span>Cheque Date</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-check" checked>
                                <span>Check # / Voucher</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-bank" checked>
                                <span>Bank &amp; Branch</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-payee" checked>
                                <span>Payee</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-project" checked>
                                <span>Project &amp; District</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-chk-amount" checked>
                                <span>Amount</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dedicated Category Filter: Funded Checks -->
        <div class="no-print" style="padding: 0.65rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <form action="disbursement_report.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="period" value="<?= e($period) ?>">
                <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
                <input type="hidden" name="col_status" value="<?= e($colStatus) ?>">
                <input type="hidden" name="col_district" value="<?= e($colDistrict) ?>">
                <input type="hidden" name="col_method" value="<?= e($colMethod) ?>">
                <input type="hidden" name="cash_district" value="<?= e($cashDistrict) ?>">
                <input type="hidden" name="cash_status" value="<?= e($cashStatus) ?>">

                <span style="font-weight: 600; font-size: 0.78rem; color: #475569;">Filter:</span>

                <select name="chk_priority" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Priorities</option>
                    <option value="Standard" <?= $chkPriority === 'Standard' ? 'selected' : '' ?>>Standard</option>
                    <option value="Prioritized" <?= $chkPriority === 'Prioritized' ? 'selected' : '' ?>>Prioritized</option>
                    <option value="Hold" <?= $chkPriority === 'Hold' ? 'selected' : '' ?>>Hold</option>
                </select>

                <select name="chk_district" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Districts</option>
                    <?php foreach ($districtsList as $dCode => $dName): ?>
                        <option value="<?= e($dCode) ?>" <?= $chkDistrict === $dCode ? 'selected' : '' ?>><?= e($dCode) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($chkPriority !== 'all' || $chkDistrict !== 'all'): ?>
                    <a href="disbursement_report.php?period=<?= urlencode($period) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&chk_priority=all&chk_district=all" style="color: var(--color-danger); text-decoration: none; font-size: 0.75rem;">
                        &times; Reset Filter
                    </a>
                <?php endif; ?>
            </form>
            <div style="color: var(--text-muted); font-size: 0.75rem;">
                Showing <strong><?= count($fundedChecks) ?></strong> funded check(s)
            </div>
        </div>

        <div>
            <?php if (empty($fundedChecks)): ?>
                <div class="empty-state">No funded checks recorded in this period matching filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="excel-table" id="disbChecksTable">
                        <thead>
                            <tr>
                                <th class="col-toggle-disb-chk-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                                <th class="col-toggle-disb-chk-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                                <th class="col-toggle-disb-chk-check" style="min-width: 140px;">CHECK # / VOUCHER</th>
                                <th class="col-toggle-disb-chk-bank" style="min-width: 130px;">BANK &amp; BRANCH</th>
                                <th class="col-toggle-disb-chk-payee" style="min-width: 180px;">PAYEE</th>
                                <th class="col-toggle-disb-chk-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                                <th class="col-toggle-disb-chk-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                <th style="min-width: 80px; text-align: center;" class="no-print">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fundedChecks as $chk): ?>
                                <tr>
                                    <td class="col-toggle-disb-chk-issuedate excel-mono" style="text-align: center; font-size: 0.78rem;"><?= format_date($chk['issue_date']) ?></td>
                                    <td class="col-toggle-disb-chk-chequedate excel-mono" style="text-align: center; font-size: 0.78rem;"><?= format_date($chk['check_date']) ?></td>
                                    <td class="col-toggle-disb-chk-check">
                                        <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($chk['check_number']) ?></span>
                                        <?php if (!empty($chk['voucher_number'])): ?>
                                            <span class="badge badge-approved excel-mono" style="margin-left: 4px; font-size: 0.68rem;"><?= e($chk['voucher_number']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-chk-bank"><?= e($chk['bank_name']) ?></td>
                                    <td class="col-toggle-disb-chk-payee" style="font-weight: 600; color: #0f172a;">
                                        <?= e($chk['payee']) ?>
                                        <?php if ($chk['priority_status'] === 'Prioritized'): ?>
                                            <span class="badge badge-prioritized" style="font-size: 0.68rem; margin-left: 2px;">Prioritized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-chk-project">
                                        <span><?= e($chk['project_name']) ?></span>
                                        <?php if (!empty($chk['project_district'])): ?>
                                            <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($chk['project_district']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-chk-amount excel-num" style="font-weight: 700; color: #b91c1c;"><?= format_money($chk['amount']) ?></td>
                                    <td class="action-cell no-print" style="text-align: center;">
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                title="Edit Check Record"
                                                data-check='<?= htmlspecialchars(json_encode($chk), ENT_QUOTES, 'UTF-8') ?>'>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" style="text-align: right; text-transform: uppercase;">Total Funded Checks (Outflow):</th>
                                <th class="col-toggle-disb-chk-amount excel-num" style="color: #b91c1c;"><?= format_money($totalFundedChecks) ?></th>
                                <th class="no-print"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 3. CASH EXPENSES SECTION (OUTFLOW) -->
    <!-- With dedicated filter bar and editable rows -->
    <!-- ======================================================== -->
    <div class="excel-sheet-card" style="margin-bottom: 2rem;">
        
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>3. Outflow: Approved Cash Expenses (<?= count($approvedCash) ?> expenses)</span>
            <span>Subtotal: <?= format_money($totalApprovedCash) ?></span>
        </div>

        <!-- Excel Sheet Header -->
        <div class="excel-sheet-header no-print">
            <div class="excel-sheet-title">
                <span class="excel-sheet-badge" style="background: rgba(245, 158, 11, 0.15); color: #b45309; border-color: rgba(245, 158, 11, 0.3);"><?= count($approvedCash) ?> EXPENSES</span>
                <span>3. Outflow: Approved Cash Expenses</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                    Subtotal: <strong class="excel-mono" style="color: #b91c1c; font-size: 0.92rem;"><?= format_money($totalApprovedCash) ?></strong>
                </div>
                <div class="excel-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search cash..." data-table-search="#disbCashTable">
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
                                <input type="checkbox" data-report-toggle="disb-cash-date" checked>
                                <span>Date</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-cash-voucher" checked>
                                <span>Voucher #</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-cash-requester" checked>
                                <span>Requester</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-cash-project" checked>
                                <span>Project &amp; District</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-cash-purpose" checked>
                                <span>Purpose</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="disb-cash-amount" checked>
                                <span>Amount</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dedicated Category Filter: Cash Expenses -->
        <div class="no-print" style="padding: 0.65rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <form action="disbursement_report.php" method="GET" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="period" value="<?= e($period) ?>">
                <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
                <input type="hidden" name="col_status" value="<?= e($colStatus) ?>">
                <input type="hidden" name="col_district" value="<?= e($colDistrict) ?>">
                <input type="hidden" name="col_method" value="<?= e($colMethod) ?>">
                <input type="hidden" name="chk_priority" value="<?= e($chkPriority) ?>">
                <input type="hidden" name="chk_district" value="<?= e($chkDistrict) ?>">

                <span style="font-weight: 600; font-size: 0.78rem; color: #475569;">Filter:</span>

                <select name="cash_status" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Outflows (Approved &amp; Disbursed)</option>
                    <option value="approved" <?= $cashStatus === 'approved' ? 'selected' : '' ?>>Approved Only</option>
                    <option value="disbursed" <?= $cashStatus === 'disbursed' ? 'selected' : '' ?>>Disbursed Only</option>
                </select>

                <select name="cash_district" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.78rem; width: auto;" onchange="this.form.submit();">
                    <option value="all">All Districts</option>
                    <?php foreach ($districtsList as $dCode => $dName): ?>
                        <option value="<?= e($dCode) ?>" <?= $cashDistrict === $dCode ? 'selected' : '' ?>><?= e($dCode) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($cashStatus !== 'all' || $cashDistrict !== 'all'): ?>
                    <a href="disbursement_report.php?period=<?= urlencode($period) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&col_status=<?= urlencode($colStatus) ?>&col_district=<?= urlencode($colDistrict) ?>&cash_status=all&cash_district=all" style="color: var(--color-danger); text-decoration: none; font-size: 0.75rem;">
                        &times; Reset Filter
                    </a>
                <?php endif; ?>
            </form>
            <div style="color: var(--text-muted); font-size: 0.75rem;">
                Showing <strong><?= count($approvedCash) ?></strong> cash expense(s)
            </div>
        </div>

        <div>
            <?php if (empty($approvedCash)): ?>
                <div class="empty-state">No approved cash expenses in this period matching filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="excel-table" id="disbCashTable">
                        <thead>
                            <tr>
                                <th class="col-toggle-disb-cash-date" style="min-width: 95px; text-align: center;">DATE</th>
                                <th class="col-toggle-disb-cash-voucher" style="min-width: 140px;">VOUCHER #</th>
                                <th class="col-toggle-disb-cash-requester" style="min-width: 160px;">REQUESTER</th>
                                <th class="col-toggle-disb-cash-project" style="min-width: 170px;">PROJECT &amp; DISTRICT</th>
                                <th class="col-toggle-disb-cash-purpose" style="min-width: 220px;">PURPOSE</th>
                                <th class="col-toggle-disb-cash-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                <th style="min-width: 80px; text-align: center;" class="no-print">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvedCash as $cash): ?>
                                <tr>
                                    <td class="col-toggle-disb-cash-date excel-mono" style="text-align: center; font-size: 0.78rem;"><?= format_date($cash['request_date']) ?></td>
                                    <td class="col-toggle-disb-cash-voucher">
                                        <span class="voucher-code-badge"><?= e($cash['voucher_no']) ?></span>
                                    </td>
                                    <td class="col-toggle-disb-cash-requester"><?= e($cash['requested_by']) ?></td>
                                    <td class="col-toggle-disb-cash-project">
                                        <span style="font-weight: 600; color: #0f172a;"><?= e($cash['department_or_project']) ?></span>
                                        <?php if (!empty($cash['project_district'])): ?>
                                            <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($cash['project_district']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-disb-cash-purpose" style="max-width: 260px;" title="<?= e($cash['purpose']) ?>"><?= e($cash['purpose']) ?></td>
                                    <td class="col-toggle-disb-cash-amount excel-num" style="font-weight: 700; color: #b91c1c;"><?= format_money($cash['amount']) ?></td>
                                    <td class="action-cell no-print" style="text-align: center;">
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-cash-request"
                                                style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                title="Edit Cash Expense Record"
                                                data-cash='<?= htmlspecialchars(json_encode($cash), ENT_QUOTES, 'UTF-8') ?>'>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" style="text-align: right; text-transform: uppercase;">Total Cash Expenses (Outflow):</th>
                                <th class="col-toggle-disb-cash-amount excel-num" style="color: #b91c1c;"><?= format_money($totalApprovedCash) ?></th>
                                <th class="no-print"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Final Reconciliation Summary (Apple Liquid Glass) -->
    <div class="reconciliation-box" style="position: relative; z-index: 1; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); border-radius: var(--radius-lg, 14px); padding: 1.25rem 1.5rem; box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03));">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
            <tr style="border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                <td style="padding: 8px 0; color: #64748b; font-weight: 500;">Total Collections (Inflow):</td>
                <td class="excel-mono" style="text-align: right; color: var(--color-success); font-weight: 700;">+ <?= format_money($totalInflow) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                <td style="padding: 8px 0; color: #64748b; font-weight: 500;">Less: Funded Checks (Outflow):</td>
                <td class="excel-mono" style="text-align: right; font-weight: 700; color: #b91c1c;">- <?= format_money($totalFundedChecks) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                <td style="padding: 8px 0; color: #64748b; font-weight: 500;">Less: Cash Expenses (Outflow):</td>
                <td class="excel-mono" style="text-align: right; font-weight: 700; color: #b91c1c;">- <?= format_money($totalApprovedCash) ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 0 4px; font-weight: 800; font-size: 0.95rem; color: #0f172a;">NET REMAINING CASH BALANCE:</td>
                <td class="excel-mono" style="text-align: right; font-weight: 800; font-size: 1.25rem; padding: 12px 0 4px; color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>;">
                    <?= format_money($netCashBalance) ?>
                </td>
            </tr>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
