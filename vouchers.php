<?php
/**
 * Aarcon Builders - Voucher Dashboard & Management
 * Official landing page for Check Disbursement Vouchers (CDVs) & Check Issuance
 * In Aarcon Builders: Adding a voucher creates a physical check (and vice-versa).
 */

$pageTitle = 'Voucher Dashboard (CDVs)';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Filter parameters
$reviewFilter   = $_GET['review_status'] ?? 'all'; // 'all', 'reviewed', 'pending'
$statusFilter   = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$startDate      = $_GET['start_date'] ?? '';
$endDate        = $_GET['end_date'] ?? '';
$dateField      = $_GET['date_field'] ?? 'issue_date'; // 'issue_date' or 'check_date'
$searchQuery    = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($reviewFilter === 'reviewed') {
    $where .= " AND (is_reviewed = 1 OR is_reviewed IS NULL)";
} elseif ($reviewFilter === 'pending') {
    $where .= " AND is_reviewed = 0";
}

if ($statusFilter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter !== 'all') {
    $where .= " AND priority_status = ?";
    $params[] = $priorityFilter;
}

if (!empty($startDate) && !empty($endDate)) {
    $field = ($dateField === 'check_date') ? 'check_date' : 'issue_date';
    $where .= " AND {$field} BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

if (!empty($searchQuery)) {
    $where .= " AND (voucher_number LIKE ? OR check_number LIKE ? OR payee LIKE ? OR bank_name LIKE ? OR project_name LIKE ? OR project_district LIKE ? OR remarks LIKE ?)";
    $likeQ = "%{$searchQuery}%";
    $params = array_merge($params, array_fill(0, 7, $likeQ));
}

// Fetch metrics for Check Vouchers
$totalVouchers         = (int)$db->query("SELECT COUNT(*) FROM checks")->fetchColumn();
$totalVoucherAmount    = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM checks")->fetchColumn();

$reviewedVouchers      = (int)$db->query("SELECT COUNT(*) FROM checks WHERE (is_reviewed = 1 OR is_reviewed IS NULL)")->fetchColumn();
$pendingReviewVouchers = (int)$db->query("SELECT COUNT(*) FROM checks WHERE is_reviewed = 0")->fetchColumn();

$pendingFundingVouchers = (int)$db->query("SELECT COUNT(*) FROM checks WHERE status = 'pending' AND (priority_status IS NULL OR priority_status != 'Hold')")->fetchColumn();
$fundedVouchers        = (int)$db->query("SELECT COUNT(*) FROM checks WHERE status = 'funded'")->fetchColumn();
$holdVouchers          = (int)$db->query("SELECT COUNT(*) FROM checks WHERE priority_status = 'Hold'")->fetchColumn();

// Fetch check vouchers list
$stmt = $db->prepare("SELECT * FROM checks {$where} ORDER BY issue_date DESC, id DESC");
$stmt->execute($params);
$vouchers = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Check Disbursement Vouchers (CDVs)</h1>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <!-- Opening modalAddCheck creates the Check & Voucher together -->
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddCheck" data-modal-title="New Check Disbursement Voucher (CDV)" data-submit-label="Save Check &amp; Voucher" data-action="add_voucher">
            + Create New Voucher
        </button>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print Registry</button>
    </div>
</div>

<!-- Key Metrics Dashboard Cards -->
<div class="metric-grid no-print" style="margin-bottom: 1.5rem;">
    <!-- Metric 1: Total Vouchers -->
    <div class="metric-card">
        <div class="metric-label">Total Check Vouchers</div>
        <div class="metric-amount" style="color: var(--text-main);">
            <?= $totalVouchers ?> <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-muted);">(<?= format_money($totalVoucherAmount) ?>)</span>
        </div>
    </div>

    <!-- Metric 2: Pending Review -->
    <div class="metric-card">
        <div class="metric-label">Pending to Review</div>
        <div class="metric-amount" style="color: var(--color-warning);">
            <?= $pendingReviewVouchers ?>
        </div>
    </div>

    <!-- Metric 3: Reviewed -->
    <div class="metric-card">
        <div class="metric-label">Reviewed Vouchers</div>
        <div class="metric-amount" style="color: var(--color-success);">
            <?= $reviewedVouchers ?>
        </div>
    </div>

    <!-- Metric 4: Funded Checks -->
    <div class="metric-card">
        <div class="metric-label">Funded &amp; Approved</div>
        <div class="metric-amount" style="color: var(--color-primary);">
            <?= $fundedVouchers ?>
        </div>
    </div>
</div>

<!-- Vouchers Filter Bar -->
<div class="card no-print" style="padding: 0.85rem 1.25rem; margin-bottom: 1.25rem;">
    <form action="vouchers.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Review Status Filter Buttons: All, Reviewed, and Pending to Review -->
        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
            <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Vouchers:</span>
            <a href="vouchers.php?review_status=all" class="btn btn-sm <?= $reviewFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All (<?= $totalVouchers ?>)</a>
            <a href="vouchers.php?review_status=reviewed" class="btn btn-sm <?= $reviewFilter === 'reviewed' ? 'btn-primary' : 'btn-outline' ?>">Reviewed (<?= $reviewedVouchers ?>)</a>
            <a href="vouchers.php?review_status=pending" class="btn btn-sm <?= $reviewFilter === 'pending' ? 'btn-primary' : 'btn-outline' ?>" style="<?= $reviewFilter === 'pending' ? 'background: #f59e0b; border-color: #f59e0b; color: #fff;' : 'color: #b45309; border-color: #fde68a;' ?>">Pending Review (<?= $pendingReviewVouchers ?>)</a>
        </div>

        <!-- Date Range & Search -->
        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
            <select name="date_field" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem;">
                <option value="issue_date" <?= $dateField === 'issue_date' ? 'selected' : '' ?>>Issue Date</option>
                <option value="check_date" <?= $dateField === 'check_date' ? 'selected' : '' ?>>Cheque Date</option>
            </select>
            <input type="date" name="start_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem;" value="<?= e($startDate) ?>" placeholder="Start Date">
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" name="end_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem;" value="<?= e($endDate) ?>" placeholder="End Date">
            
            <input type="text" name="search" class="form-control" style="width: 170px; padding: 0.25rem 0.5rem; font-size: 0.82rem;" placeholder="Search voucher/check..." value="<?= e($searchQuery) ?>">
            
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            <?php if (!empty($startDate) || !empty($endDate) || !empty($searchQuery) || $reviewFilter !== 'all'): ?>
                <a href="vouchers.php" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Vouchers Excel-Style Liquid Glass Spreadsheet View -->
<div class="excel-sheet-card">
    <!-- Print-only Section Header -->
    <div class="print-section-title" style="display: none;">
        <span>Check Disbursement Vouchers Registry (<?= count($vouchers) ?> vouchers)</span>
        <span>Total: <?= format_money($totalVoucherAmount) ?></span>
    </div>

    <!-- Excel Header Bar -->
    <div class="excel-sheet-header no-print">
        <div class="excel-sheet-title">
            <span class="excel-sheet-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span> CHECK DISBURSEMENT VOUCHERS (CDVS) REGISTRY
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <div class="excel-sheet-date">
                AS OF <?= strtoupper(date('m/d/Y')) ?> &bull; <?= count($vouchers) ?> RECORD(S)
            </div>
            <input type="text" class="search-input no-print" placeholder="Search visible vouchers..." data-table-search="#vouchersTable">

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
                            <input type="checkbox" data-report-toggle="vch-check" checked>
                            <span>Check # &amp; Bank</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-issuedate" checked>
                            <span>Issue Date</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-chequedate" checked>
                            <span>Cheque Date</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-payee" checked>
                            <span>Payee / Supplier</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-project" checked>
                            <span>Project &amp; District</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-category" checked>
                            <span>Category</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-priority" checked>
                            <span>Priority</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-amount" checked>
                            <span>Amount</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="vch-status" checked>
                            <span>Status</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <?php if (empty($vouchers)): ?>
            <div class="empty-state">
                No check vouchers found matching your filters.
                <p>Click "+ Create New Voucher" to issue a new check with its disbursement voucher.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="excel-table" id="vouchersTable">
                    <thead>
                        <tr>
                            <th style="min-width: 120px;">VOUCHER #</th>
                            <th class="col-toggle-vch-check" style="min-width: 130px;">CHECK # &amp; BANK</th>
                            <th class="col-toggle-vch-issuedate" style="min-width: 95px; text-align: center;">ISSUE DATE</th>
                            <th class="col-toggle-vch-chequedate" style="min-width: 95px; text-align: center;">CHEQUE DATE</th>
                            <th class="col-toggle-vch-payee" style="min-width: 180px;">PAYEE / SUPPLIER</th>
                            <th class="col-toggle-vch-project" style="min-width: 160px;">PROJECT &amp; DISTRICT</th>
                            <th class="col-toggle-vch-category" style="min-width: 90px; text-align: center;">CATEGORY</th>
                            <th class="col-toggle-vch-priority" style="min-width: 85px; text-align: center;">PRIORITY</th>
                            <th class="col-toggle-vch-amount" style="min-width: 120px; text-align: right;">AMOUNT</th>
                            <th class="col-toggle-vch-status" style="min-width: 100px; text-align: center;">STATUS</th>
                            <th style="min-width: 140px; text-align: center;" class="no-print">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                            <?php 
                                $vNo = !empty($v['voucher_number']) ? $v['voucher_number'] : sprintf('CV-%s-%04d', date('Y', strtotime($v['issue_date'] ?: 'now')), $v['id']);
                                $checkType = !empty($v['check_type']) ? $v['check_type'] : get_check_maturity_tag($v['issue_date'], $v['check_date']);
                                $priority = $v['priority_status'] ?? 'Standard';
                                $words = number_to_words_pesos($v['amount']);
                                
                                $voucherPayload = [
                                    'id'               => $v['id'],
                                    'voucher_number'   => $vNo,
                                    'check_number'     => $v['check_number'],
                                    'bank_name'        => $v['bank_name'],
                                    'payee'            => $v['payee'],
                                    'amount'           => (float)$v['amount'],
                                    'amount_words'     => $words,
                                    'issue_date'       => $v['issue_date'],
                                    'check_date'       => $v['check_date'],
                                    'check_type'       => $checkType,
                                    'project_name'     => $v['project_name'],
                                    'project_district' => $v['project_district'] ?? '',
                                    'category'         => $v['category'] ?? 'General',
                                    'remarks'          => $v['remarks'] ?? '',
                                    'status'           => $v['status'],
                                    'priority_status'  => $priority,
                                    'funded_by'        => $v['funded_by'] ?? DEFAULT_APPROVER
                                ];
                            ?>
                            <tr>
                                <!-- 1. Voucher # -->
                                <td class="col-toggle-vch-code">
                                    <span class="voucher-code-badge" title="Voucher Number">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                        <?= e($vNo) ?>
                                    </span>
                                </td>

                                <!-- 2. Check # & Bank -->
                                <td class="col-toggle-vch-check">
                                    <span class="excel-mono" style="font-weight: 700; color: #1e293b;"><?= e($v['check_number']) ?></span>
                                    <small style="color: #64748b; font-size: 0.72rem; margin-left: 4px;">(<?= e($v['bank_name']) ?>)</small>
                                </td>

                                <!-- 3. Issue Date -->
                                <td class="col-toggle-vch-issuedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono">
                                    <?= format_date($v['issue_date']) ?>
                                </td>

                                <!-- 4. Cheque Date -->
                                <td class="col-toggle-vch-chequedate" style="text-align: center; font-size: 0.78rem;" class="excel-mono">
                                    <?= !empty($v['check_date']) ? format_date($v['check_date']) : '<span style="color: #94a3b8; font-style: italic;">Pending</span>' ?>
                                </td>

                                <!-- 5. Payee -->
                                <td class="col-toggle-vch-payee" style="font-weight: 600; color: #0f172a;">
                                    <?= e($v['payee']) ?>
                                </td>

                                <!-- 6. Project & District -->
                                <td class="col-toggle-vch-project">
                                    <span><?= e($v['project_name']) ?></span>
                                    <?php if (!empty($v['project_district'])): ?>
                                        <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem; margin-left: 4px;"><?= e($v['project_district']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- 7. Category -->
                                <td class="col-toggle-vch-category" style="text-align: center;">
                                    <span class="category-badge-excel">
                                        <?= e($v['category'] ?? 'General') ?>
                                    </span>
                                </td>

                                <!-- 8. Priority -->
                                <td class="col-toggle-vch-priority" style="text-align: center;">
                                    <?php if ($priority === 'Prioritized'): ?>
                                        <span class="badge badge-prioritized" style="font-size: 0.68rem; padding: 2px 6px;">Prioritized</span>
                                    <?php elseif ($priority === 'Hold'): ?>
                                        <span class="badge badge-hold" style="font-size: 0.68rem; padding: 2px 6px;">Hold</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.68rem; padding: 2px 6px;">Standard</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 9. Amount -->
                                <td class="col-toggle-vch-amount excel-num" style="color: #0f172a; font-weight: 700;">
                                    <?= format_money($v['amount']) ?>
                                </td>

                                <!-- 10. Status -->
                                <td class="col-toggle-vch-status" style="text-align: center;">
                                    <?php if ((int)($v['is_reviewed'] ?? 1) === 1): ?>
                                        <span class="badge badge-funded" style="font-size: 0.68rem; padding: 2px 6px;" title="Reviewed and pushed to Check funding module">
                                            In Checks
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-inprocess" style="font-size: 0.68rem; padding: 2px 6px;" title="Pending review">
                                            To Review
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 11. Actions -->
                                <td style="text-align: center;" class="no-print">
                                    <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center;">
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-check"
                                                style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;"
                                                data-check='<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>'
                                                title="Edit Full Voucher Details">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm btn-print-check-voucher" 
                                                style="padding: 1px 6px; font-size: 0.72rem;"
                                                data-voucher='<?= json_encode($voucherPayload, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                                title="Print Official Aarcon Voucher or Bank Cheque">
                                            Print
                                        </button>
                                        <?php if ((int)($v['is_reviewed'] ?? 1) === 0): ?>
                                            <form action="api/actions.php" method="POST" class="form-review-voucher" style="margin: 0; display: inline;">
                                                <input type="hidden" name="action" value="review_voucher">
                                                <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm btn-review-voucher"
                                                        style="padding: 1px 6px; font-size: 0.72rem; background: #059669; border-color: #059669;"
                                                        title="Mark Reviewed: Throws this voucher to the Check module">
                                                    Review
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th class="col-toggle-vch-check"></th>
                            <th class="col-toggle-vch-issuedate"></th>
                            <th class="col-toggle-vch-chequedate"></th>
                            <th class="col-toggle-vch-payee"></th>
                            <th class="col-toggle-vch-project"></th>
                            <th class="col-toggle-vch-category"></th>
                            <th class="col-toggle-vch-priority" style="text-align: right; text-transform: uppercase; font-size: 0.76rem; color: #475569;">TOTAL:</th>
                            <th class="col-toggle-vch-amount excel-num" style="color: #dc2626; font-weight: 800;"><?= format_money($totalVoucherAmount) ?></th>
                            <th class="col-toggle-vch-status"></th>
                            <th class="no-print"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Spreadsheet Status Bar -->
            <div class="excel-sheet-footer no-print">
                <span><span class="excel-status-dot"></span> Sheet Ready &bull; <?= count($vouchers) ?> voucher record(s)</span>
                <span>Total Net Disbursed: <strong style="color: #dc2626; font-size: 0.85rem;"><?= format_money($totalVoucherAmount) ?></strong></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: OFFICIAL AARCON BUILDERS PRINTABLE VOUCHER -->
<!-- ========================================== -->
<style>
    #modalPrintCheckVoucher,
    #modalPrintCheckVoucher .modal-dialog,
    #modalPrintCheckVoucher .modal-body,
    #modalPrintCheckVoucher #checkVoucherPrintArea {
        overflow-x: hidden !important;
        max-width: 100%;
        box-sizing: border-box;
    }
    #modalPrintCheckVoucher ::-webkit-scrollbar:horizontal {
        display: none !important;
        height: 0px !important;
        width: 0px !important;
    }
</style>
<div class="modal-overlay" id="modalPrintCheckVoucher">
    <div class="modal-dialog" style="max-width: 740px; width: 95vw; overflow-x: hidden !important; box-sizing: border-box;">
        <div class="modal-header no-print">
            <h3 class="modal-title">Official Check Disbursement Voucher</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1rem; background: #f8fafc; overflow-x: hidden !important; box-sizing: border-box;">
            <!-- Official Voucher Print Box (Exact replica of company paper) -->
            <div id="checkVoucherPrintArea" style="border: 1.5px solid #000; padding: 1.25rem 1.25rem; background: #fff; color: #000; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; font-size: 12px; line-height: 1.35; box-shadow: 0 2px 10px rgba(0,0,0,0.06); width: 100%; max-width: 100%; box-sizing: border-box; overflow-x: hidden !important;">
                
                <!-- 1. Header with Logo & Center Letterhead -->
                <div style="display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 14px; width: 100%;">
                    <div style="position: absolute; left: 0; top: 0;">
                        <img src="img/logo.png" alt="Aarcon Builders Logo" style="width: 56px; height: 56px; object-fit: contain;">
                    </div>
                    <div style="text-align: center; padding: 0 50px; max-width: 100%; box-sizing: border-box;">
                        <div style="font-family: 'Times New Roman', Times, Georgia, serif; font-size: 22px; font-weight: 900; letter-spacing: 0.05em; color: #000; text-transform: uppercase; line-height: 1.1;">
                            AARCON BUILDERS
                        </div>
                        <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #000; text-transform: uppercase; margin-top: 2px; margin-bottom: 2px;">
                            GENERAL CONTRACTOR
                        </div>
                        <div style="font-size: 10px; color: #111; line-height: 1.3;">
                            Unit 203, FSS Bldg. I, #20 Sct. Tuazon cor.<br>
                            Sct. Castor Sts., Quezon City<br>
                            Telefax No.: 8441-8225 &bull; Email: aarconbuilders@gmail.com<br>
                            Website: aarconbuilders.com
                        </div>
                    </div>
                </div>

                <!-- 2. Metadata Section: CHECK NO., PROJECT, DATE, No. [serial] -->
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 6px; font-size: 12px; font-weight: 700; gap: 12px; width: 100%; box-sizing: border-box;">
                    <div style="display: flex; align-items: flex-end; gap: 6px; flex: 1; min-width: 0;">
                        <span style="font-weight: 800; white-space: nowrap; flex-shrink: 0;">CHECK NO.:</span>
                        <div id="cdvCheckNo" style="border-bottom: 1px solid #000; flex: 1; min-width: 40px; padding-bottom: 1px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 6px; flex: 1; min-width: 0;">
                        <span style="font-weight: 800; white-space: nowrap; flex-shrink: 0;">DATE:</span>
                        <div id="cdvCheckDate" style="border-bottom: 1px solid #000; flex: 1; min-width: 40px; padding-bottom: 1px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 6px; font-size: 12px; font-weight: 700; gap: 12px; width: 100%; box-sizing: border-box;">
                    <div style="display: flex; align-items: flex-end; gap: 6px; flex: 1; min-width: 0;">
                        <span style="font-weight: 800; white-space: nowrap; flex-shrink: 0;">PROJECT:</span>
                        <div id="cdvProject" style="border-bottom: 1px solid #000; flex: 1; min-width: 40px; padding-bottom: 1px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 4px; white-space: nowrap; flex-shrink: 0;">
                        <span style="font-weight: 800;">No.</span>
                        <span id="cdvVoucherSerial" style="color: #dc2626; font-family: 'Courier New', Courier, monospace; font-size: 16px; font-weight: 900; letter-spacing: 0.05em;">-</span>
                    </div>
                </div>

                <!-- Centered Document Title -->
                <div style="text-align: center; margin: 6px 0 6px 0;">
                    <span style="font-size: 15px; font-weight: 900; letter-spacing: 0.12em; text-decoration: underline; text-underline-offset: 3px;">VOUCHER</span>
                </div>

                <!-- 3. PAY TO Row -->
                <div style="display: flex; align-items: flex-end; gap: 6px; margin-bottom: 10px; font-size: 12px; font-weight: 700; width: 100%; box-sizing: border-box;">
                    <span style="font-weight: 800; white-space: nowrap; flex-shrink: 0;">PAY TO:</span>
                    <div id="cdvPayee" style="border-bottom: 1px solid #000; flex: 1; min-width: 0; padding-bottom: 1px; font-size: 13px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</div>
                </div>

                <!-- 4. PARTICULARS & AMOUNT BOX -->
                <div style="border: 1.5px solid #000; min-height: 180px; display: flex; flex-direction: column; margin-bottom: 14px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;">
                    <div style="display: grid; grid-template-columns: 1fr 140px; border-bottom: 1.5px solid #000; font-weight: 800; font-size: 11px; letter-spacing: 0.05em; text-align: center;">
                        <div style="padding: 5px 8px; border-right: 1.5px solid #000;">PARTICULARS</div>
                        <div style="padding: 5px 8px;">AMOUNT</div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 140px; flex: 1;">
                        <div style="border-right: 1.5px solid #000; padding: 10px 12px; display: flex; flex-direction: column; justify-content: space-between; min-width: 0;">
                            <div>
                                <div id="cdvParticulars" style="font-weight: 600; line-height: 1.45; white-space: pre-wrap; font-size: 12px; word-break: break-word;">-</div>
                                <div style="font-size: 10.5px; color: #475569; margin-top: 8px;">
                                    <strong>Bank:</strong> <span id="cdvBank">-</span> &bull; 
                                    <strong>Voucher Ref:</strong> <span id="cdvVoucherRef">-</span>
                                </div>
                            </div>
                            <div style="border-top: 1px dashed #cbd5e1; padding-top: 5px; margin-top: 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; word-break: break-word;">
                                Amount in Words: <span id="cdvAmountWords" style="color: #dc2626;">-</span>
                            </div>
                        </div>

                        <div style="padding: 10px 12px; text-align: right; display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; min-width: 0;">
                            <div id="cdvAmountFigures" style="font-size: 14px; font-weight: 900; white-space: nowrap;">₱ 0.00</div>
                            <div style="font-size: 9.5px; color: #64748b; font-style: italic;">Net Payable</div>
                        </div>
                    </div>

                    <div style="border-top: 1.5px solid #000; display: grid; grid-template-columns: 1fr 140px;">
                        <div style="border-right: 1.5px solid #000; padding: 5px 10px; font-size: 11px; font-weight: 800; min-width: 0;">
                            <div>ACCOUNT CHARGE: <span id="cdvCategory" style="text-transform: uppercase; margin-left: 4px;">-</span></div>
                            <div style="margin-top: 2px;">AMOUNT:</div>
                        </div>
                        <div style="padding: 5px 10px; display: flex; align-items: center; justify-content: flex-end; font-size: 14px; font-weight: 900; white-space: nowrap;" id="cdvBottomAmount">
                            ₱ 0.00
                        </div>
                    </div>
                </div>

                <!-- 5. SIGNATURES ROW -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; column-gap: 20px; row-gap: 12px; font-size: 11px; font-weight: 800; width: 100%; max-width: 100%; box-sizing: border-box;">
                    <div style="display: flex; align-items: flex-end; gap: 4px; min-width: 0;">
                        <span style="white-space: nowrap; font-size: 10.5px; flex-shrink: 0;">PREPARED BY:</span>
                        <div style="border-bottom: 1px solid #000; flex: 1; height: 16px; min-width: 20px;"></div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 4px; min-width: 0;">
                        <span style="white-space: nowrap; font-size: 10.5px; flex-shrink: 0;">CHECKED BY:</span>
                        <div style="border-bottom: 1px solid #000; flex: 1; height: 16px; min-width: 20px;"></div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 4px; min-width: 0;">
                        <span style="white-space: nowrap; font-size: 10.5px; flex-shrink: 0;">RECEIVED BY:</span>
                        <div style="border-bottom: 1px solid #000; flex: 1; height: 16px; min-width: 20px;"></div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 4px; min-width: 0;">
                        <span style="white-space: nowrap; font-size: 10.5px; flex-shrink: 0;">NOTED BY:</span>
                        <div style="border-bottom: 1px solid #000; flex: 1; height: 16px; min-width: 20px;"></div>
                    </div>
                </div>

            </div>
        </div>
        <div class="modal-footer no-print" style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalPrintCheckVoucher')">Close</button>
            <div style="display: flex; gap: 8px;">
                <a id="btnLinkPrintCheck" href="#" target="_blank" class="btn btn-outline" style="color: #dc2626; border-color: #fca5a5;">
                    Print Bank Cheque
                </a>
                <a id="btnLinkPrintVoucher" href="#" target="_blank" class="btn btn-primary">
                    Print Official Voucher
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    let activeVoucherCheckId = null;

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-print-check-voucher').forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.getAttribute('data-voucher') || '{}');
                activeVoucherCheckId = data.id;

                const vNo = data.voucher_number || '-';
                document.getElementById('cdvVoucherRef').textContent = vNo;
                
                // Extract serial for red "No."
                const serialMatch = vNo.match(/(\d+)$/);
                document.getElementById('cdvVoucherSerial').textContent = serialMatch ? serialMatch[1] : (data.id ? String(data.id).padStart(5, '0') : '00001');

                document.getElementById('cdvCheckNo').textContent = data.check_number || '-';
                document.getElementById('cdvBank').textContent = data.bank_name || '-';
                document.getElementById('cdvCheckDate').textContent = data.check_date || data.issue_date || '-';
                document.getElementById('cdvPayee').textContent = data.payee || '-';
                
                let projText = data.project_name || '-';
                if (data.project_district) projText += ' (' + data.project_district + ')';
                document.getElementById('cdvProject').textContent = projText;

                document.getElementById('cdvParticulars').textContent = data.remarks || ('Check disbursement for ' + (data.project_name || 'project operations') + '.');
                document.getElementById('cdvCategory').textContent = data.category || 'Materials & Operations';

                const formattedMoney = '₱ ' + parseFloat(data.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                document.getElementById('cdvAmountFigures').textContent = formattedMoney;
                document.getElementById('cdvBottomAmount').textContent = formattedMoney;
                document.getElementById('cdvAmountWords').textContent = data.amount_words || '';

                // Set hrefs for direct standalone printing
                document.getElementById('btnLinkPrintVoucher').href = 'print_voucher.php?id=' + encodeURIComponent(data.id);
                document.getElementById('btnLinkPrintCheck').href = 'print_check.php?id=' + encodeURIComponent(data.id);

                openModal('modalPrintCheckVoucher');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
