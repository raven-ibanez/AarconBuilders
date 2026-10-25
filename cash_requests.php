<?php
/**
 * Aarcon Builders - Module 2: Cash Request Interface & Petty Cash
 */

$pageTitle = 'Cash Requests';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

$activeTab = $_GET['tab'] ?? 'pending';
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

// Build date range condition
$dateCondition = '';
$dateParams = [];

if (!empty($startDate) && !empty($endDate)) {
    $dateCondition = " AND request_date BETWEEN ? AND ?";
    $dateParams = [$startDate, $endDate];
}

// 1. Pending Requests Query
$pendingStmt = $db->prepare("SELECT * FROM cash_requests WHERE status = 'pending' {$dateCondition} ORDER BY request_date DESC, id DESC");
$pendingStmt->execute($dateParams);
$pendingRequests = $pendingStmt->fetchAll();
$pendingTotal = array_sum(array_column($pendingRequests, 'amount'));

// 2. Approved Requests Query
$approvedStmt = $db->prepare("SELECT * FROM cash_requests WHERE status IN ('approved', 'disbursed') {$dateCondition} ORDER BY approved_at DESC, id DESC");
$approvedStmt->execute($dateParams);
$approvedRequests = $approvedStmt->fetchAll();
$approvedTotal = array_sum(array_column($approvedRequests, 'amount'));

// 3. All Requests Query
$allStmt = $db->prepare("SELECT * FROM cash_requests WHERE 1=1 {$dateCondition} ORDER BY id DESC");
$allStmt->execute($dateParams);
$allRequests = $allStmt->fetchAll();
$allTotal = array_sum(array_column($allRequests, 'amount'));
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Physical Cash Request Interface</h1>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddCashRequest">+ New Cash Request</button>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print View</button>
    </div>
</div>

<!-- Key Metrics Dashboard Cards (Summary) -->
<div class="metric-grid no-print" style="margin-bottom: 1.5rem;">
    <!-- Metric 1: Total Requests -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-all\']')?.click();" title="View all cash requests">
        <div class="metric-label">Total Cash Requests</div>
        <div class="metric-amount" style="color: var(--text-main);">
            <?= count($allRequests) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($allTotal) ?>)</span>
        </div>
    </div>

    <!-- Metric 2: Pending Approvals -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-pending\']')?.click();" title="View pending requests">
        <div class="metric-label">Pending Approval</div>
        <div class="metric-amount" style="color: var(--color-warning);">
            <?= count($pendingRequests) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($pendingTotal) ?>)</span>
        </div>
    </div>

    <!-- Metric 3: Approved & Disbursed -->
    <div class="metric-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=\'tab-approved\']')?.click();" title="View approved requests">
        <div class="metric-label">Approved &amp; Disbursed</div>
        <div class="metric-amount" style="color: var(--color-success);">
            <?= count($approvedRequests) ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($approvedTotal) ?>)</span>
        </div>
    </div>
</div>

<!-- Date Range Filter Bar (Required) -->
<div class="no-print" style="padding: 0.75rem 1.25rem; margin-bottom: 1.25rem; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); border-radius: var(--radius-lg, 14px); box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03));">
    <form action="cash_requests.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin: 0;">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-weight: 600; font-size: 0.82rem; color: #475569;">Date Range Filter:</span>
            <input type="date" name="start_date" id="cashStartDate" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($startDate) ?>">
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" name="end_date" id="cashEndDate" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($endDate) ?>">
            <button type="submit" class="btn btn-outline btn-sm">Filter Range</button>
            <?php if (!empty($startDate) || !empty($endDate)): ?>
                <a href="cash_requests.php?tab=<?= e($activeTab) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Clear</a>
            <?php endif; ?>
        </div>

        <div class="excel-search-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" placeholder="Global search requests..." data-table-search="#pendingCashTable, #approvedCashTable, #cashRequestsTable">
        </div>
    </form>
</div>

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs-nav no-print">
        <button class="tab-btn <?= $activeTab === 'pending' ? 'active' : '' ?>" data-tab="tab-pending">
            <span>Pending Approvals</span>
            <?php if (count($pendingRequests) > 0): ?>
                <span class="badge badge-pending"><?= count($pendingRequests) ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab === 'approved' ? 'active' : '' ?>" data-tab="tab-approved">
            <span>Approved Cash Log</span>
            <?php if (count($approvedRequests) > 0): ?>
                <span class="badge badge-funded"><?= count($approvedRequests) ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>" data-tab="tab-all">
            <span>All Requests (<?= count($allRequests) ?>)</span>
        </button>
    </div>

    <!-- TAB 1: Pending Requests -->
    <div class="tab-pane <?= $activeTab === 'pending' ? 'active' : '' ?>" id="tab-pending">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>Pending Operational Cash Requests (<?= count($pendingRequests) ?> requests)</span>
            <span>Pending Total: <?= format_money($pendingTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge" style="background: rgba(245, 158, 11, 0.15); color: #b45309; border-color: rgba(245, 158, 11, 0.3);"><?= count($pendingRequests) ?> PENDING</span>
                    <span>Pending Operational Cash Requests</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Pending Total: <strong class="excel-mono" style="color: #d97706; font-size: 0.88rem;"><?= format_money($pendingTotal) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search pending cash..." data-table-search="#pendingCashTable">
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
                                    <input type="checkbox" data-report-toggle="cash-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-project" checked>
                                    <span>Project</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-district" checked>
                                    <span>District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-requestedby" checked>
                                    <span>Requested By</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-desc" checked>
                                    <span>Description</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-date" checked>
                                    <span>Request Date</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if (empty($pendingRequests)): ?>
                    <div class="empty-state">
                        No pending cash requests in this range.
                        <p>Click "+ New Cash Request" to submit an operational petty cash need.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="pendingCashTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-cash-voucher" style="min-width: 140px;">CASH REQUEST (VOUCHER #)</th>
                                    <th class="col-toggle-cash-project" style="min-width: 170px;">PROJECT</th>
                                    <th class="col-toggle-cash-district" style="min-width: 100px;">DISTRICT</th>
                                    <th class="col-toggle-cash-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <th class="col-toggle-cash-requestedby" style="min-width: 150px;">REQUESTED BY</th>
                                    <th class="col-toggle-cash-desc" style="min-width: 220px;">DESCRIPTION</th>
                                    <th class="col-toggle-cash-date" style="min-width: 110px; text-align: center;">REQUEST DATE</th>
                                    <th style="min-width: 120px; text-align: center;" class="no-print">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td class="col-toggle-cash-voucher">
                                            <span class="voucher-code-badge"><?= e($req['voucher_no']) ?></span>
                                        </td>
                                        <td class="col-toggle-cash-project" style="font-weight: 600; color: #0f172a;"><?= e($req['department_or_project']) ?></td>
                                        <td class="col-toggle-cash-district">
                                            <?php if (!empty($req['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem;"><?= e($req['project_district']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #0f172a; font-weight: 700;" data-row-amount="<?= $req['amount'] ?>">
                                            <?= format_money($req['amount']) ?>
                                        </td>
                                        <td class="col-toggle-cash-requestedby"><?= e($req['requested_by']) ?></td>
                                        <td class="col-toggle-cash-desc" style="max-width: 260px;" title="<?= e($req['purpose']) ?>">
                                            <?= e($req['purpose']) ?>
                                        </td>
                                        <td class="col-toggle-cash-date excel-mono" style="text-align: center; font-size: 0.78rem;">
                                            <?= format_date($req['request_date']) ?>
                                        </td>
                                        <td class="action-cell no-print" style="text-align: center;">
                                            <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center;">
                                                <button class="btn btn-success btn-sm btn-ajax-approve-cash"
                                                        style="padding: 1px 7px; font-size: 0.72rem;"
                                                        data-request-id="<?= $req['id'] ?>"
                                                        data-voucher="<?= e($req['voucher_no']) ?>"
                                                        title="Approve Cash Release">
                                                    ✓ Approve
                                                </button>
                                                <form action="api/actions.php" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Disapprove / Delete request?');">
                                                    <input type="hidden" name="action" value="reject_cash_request">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 1px 7px; font-size: 0.72rem;" title="Reject">&times;</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right; text-transform: uppercase;">Total Filtered Amount:</th>
                                    <th class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #d97706;"><?= format_money($pendingTotal) ?></th>
                                    <th colspan="4" class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 2: Approved Cash Log -->
    <div class="tab-pane <?= $activeTab === 'approved' ? 'active' : '' ?>" id="tab-approved">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>Approved Petty Cash Expenses Journal (<?= count($approvedRequests) ?> requests)</span>
            <span>Approved Total: <?= format_money($approvedTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge" style="background: rgba(16, 185, 129, 0.15); color: #065f46; border-color: rgba(16, 185, 129, 0.3);"><?= count($approvedRequests) ?> APPROVED</span>
                    <span>Approved Petty Cash Expenses Journal</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Approved Total: <strong class="excel-mono" style="color: #059669; font-size: 0.88rem;"><?= format_money($approvedTotal) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search approved cash..." data-table-search="#approvedCashTable">
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
                                    <input type="checkbox" data-report-toggle="cash-voucher" checked>
                                    <span>Voucher #</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-project" checked>
                                    <span>Project</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-district" checked>
                                    <span>District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-requestedby" checked>
                                    <span>Requested By</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-desc" checked>
                                    <span>Description</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-apprdate" checked>
                                    <span>Approved At &amp; By</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-status" checked>
                                    <span>Status</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if (empty($approvedRequests)): ?>
                    <div class="empty-state">No approved cash expenses found in this range.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="approvedCashTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-cash-voucher" style="min-width: 140px;">CASH REQUEST (VOUCHER #)</th>
                                    <th class="col-toggle-cash-project" style="min-width: 170px;">PROJECT</th>
                                    <th class="col-toggle-cash-district" style="min-width: 100px;">DISTRICT</th>
                                    <th class="col-toggle-cash-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <th class="col-toggle-cash-requestedby" style="min-width: 150px;">REQUESTED BY</th>
                                    <th class="col-toggle-cash-desc" style="min-width: 220px;">DESCRIPTION</th>
                                    <th class="col-toggle-cash-apprdate" style="min-width: 140px;">APPROVED AT &amp; BY</th>
                                    <th class="col-toggle-cash-status" style="min-width: 90px; text-align: center;">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedRequests as $req): ?>
                                    <tr>
                                        <td class="col-toggle-cash-voucher">
                                            <span class="voucher-code-badge"><?= e($req['voucher_no']) ?></span>
                                        </td>
                                        <td class="col-toggle-cash-project" style="font-weight: 600; color: #0f172a;"><?= e($req['department_or_project']) ?></td>
                                        <td class="col-toggle-cash-district">
                                            <?php if (!empty($req['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem;"><?= e($req['project_district']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #0f172a; font-weight: 700;" data-row-amount="<?= $req['amount'] ?>">
                                            <?= format_money($req['amount']) ?>
                                        </td>
                                        <td class="col-toggle-cash-requestedby"><?= e($req['requested_by']) ?></td>
                                        <td class="col-toggle-cash-desc" style="max-width: 260px;" title="<?= e($req['purpose']) ?>">
                                            <?= e($req['purpose']) ?>
                                        </td>
                                        <td class="col-toggle-cash-apprdate" style="font-size: 0.78rem;">
                                            <span class="excel-mono"><?= format_date($req['approved_at'], 'M d, Y h:i A') ?></span>
                                            <span style="color: var(--text-muted); margin-left: 4px;">(<?= e($req['approved_by'] ?: 'Manager') ?>)</span>
                                        </td>
                                        <td class="col-toggle-cash-status" style="text-align: center;">
                                            <span class="badge badge-funded">✓ Approved</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right; text-transform: uppercase;">Total Approved Cash:</th>
                                    <th class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #059669;"><?= format_money($approvedTotal) ?></th>
                                    <th colspan="4" class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 3: All Requests -->
    <div class="tab-pane <?= $activeTab === 'all' ? 'active' : '' ?>" id="tab-all">
        <!-- Print-only Section Header -->
        <div class="print-section-title" style="display: none;">
            <span>All Cash Requests Master Log (<?= count($allRequests) ?> requests)</span>
            <span>Total: <?= format_money($allTotal) ?></span>
        </div>
        <div class="excel-sheet-card">
            <div class="excel-sheet-header no-print">
                <div class="excel-sheet-title">
                    <span class="excel-sheet-badge"><?= count($allRequests) ?> TOTAL</span>
                    <span>All Cash Requests Master Log</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                    <div style="font-size: 0.78rem; font-weight: 600; color: #475569;">
                        Total Requests: <strong class="excel-mono" style="color: #0f172a; font-size: 0.88rem;"><?= format_money($allTotal) ?></strong>
                    </div>
                    <div class="excel-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search all cash..." data-table-search="#cashRequestsTable">
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
                                    <input type="checkbox" data-report-toggle="cash-voucher" checked>
                                    <span>Cash Request</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-project" checked>
                                    <span>Project</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-district" checked>
                                    <span>District</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-amount" checked>
                                    <span>Amount</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-requestedby" checked>
                                    <span>Requested By</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-desc" checked>
                                    <span>Description</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-logdate" checked>
                                    <span>Log Date</span>
                                </label>
                                <label class="col-picker-item">
                                    <input type="checkbox" data-report-toggle="cash-status" checked>
                                    <span>Status</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if (empty($allRequests)): ?>
                    <div class="empty-state">No requests found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="excel-table" id="cashRequestsTable">
                            <thead>
                                <tr>
                                    <th class="col-toggle-cash-voucher" style="min-width: 140px;">CASH REQUEST</th>
                                    <th class="col-toggle-cash-project" style="min-width: 170px;">PROJECT</th>
                                    <th class="col-toggle-cash-district" style="min-width: 100px;">DISTRICT</th>
                                    <th class="col-toggle-cash-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                                    <th class="col-toggle-cash-requestedby" style="min-width: 150px;">REQUESTED BY</th>
                                    <th class="col-toggle-cash-desc" style="min-width: 220px;">DESCRIPTION</th>
                                    <th class="col-toggle-cash-logdate" style="min-width: 110px; text-align: center;">LOG DATE</th>
                                    <th class="col-toggle-cash-status" style="min-width: 90px; text-align: center;">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRequests as $req): ?>
                                    <tr>
                                        <td class="col-toggle-cash-voucher">
                                            <span class="voucher-code-badge"><?= e($req['voucher_no']) ?></span>
                                        </td>
                                        <td class="col-toggle-cash-project" style="font-weight: 600; color: #0f172a;"><?= e($req['department_or_project']) ?></td>
                                        <td class="col-toggle-cash-district">
                                            <?php if (!empty($req['project_district'])): ?>
                                                <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem;"><?= e($req['project_district']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #0f172a; font-weight: 700;" data-row-amount="<?= $req['amount'] ?>">
                                            <?= format_money($req['amount']) ?>
                                        </td>
                                        <td class="col-toggle-cash-requestedby"><?= e($req['requested_by']) ?></td>
                                        <td class="col-toggle-cash-desc" style="max-width: 260px;" title="<?= e($req['purpose']) ?>">
                                            <?= e($req['purpose']) ?>
                                        </td>
                                        <td class="col-toggle-cash-logdate" style="text-align: center; font-size: 0.78rem;" class="excel-mono">
                                            <?php if ($req['status'] === 'approved'): ?>
                                                <span style="color: #059669; font-weight: 600;"><?= format_date($req['approved_at'], 'M d, Y') ?></span>
                                            <?php elseif ($req['status'] === 'rejected'): ?>
                                                <span style="color: #dc2626; font-weight: 600;"><?= format_date($req['approved_at'], 'M d, Y') ?></span>
                                            <?php else: ?>
                                                <span><?= format_date($req['request_date'], 'M d, Y') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-toggle-cash-status" style="text-align: center;">
                                            <?php if ($req['status'] === 'approved'): ?>
                                                <span class="badge badge-funded">Approved</span>
                                            <?php elseif ($req['status'] === 'rejected'): ?>
                                                <span class="badge badge-cancelled">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right; text-transform: uppercase;">Total Amount:</th>
                                    <th class="cash-filtered-total excel-num amount-col col-toggle-cash-amount" style="color: #0f172a;"><?= format_money($allTotal) ?></th>
                                    <th colspan="4" class="no-print"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
