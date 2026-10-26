<?php
/**
 * Aarcon Builders - Module 3: Collections (Cash In) & Projected Collections
 * Excel / Spreadsheet 1-line dense tabular layout matching office workflow:
 * PROJECT NAME | CONTRACT ID | CONTRACT AMOUNT | NET AMOUNT | AMOUNT COLLECTED |
 * BALANCE COLLECTIBLE | % | BILLING ON PROCESS | REMARKS | STATUS |
 * EXPECTED DATE OF COLLECTION | DEDUCTIONS | NET COLLECTION | ACTION
 */

$pageTitle = 'Projected Collections';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Filters
$districtFilter = $_GET['district'] ?? 'all';
$statusFilter   = $_GET['status'] ?? 'all';
$searchQuery    = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($districtFilter !== 'all' && !empty($districtFilter)) {
    $where .= " AND project_district = ?";
    $params[] = $districtFilter;
}

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $where .= " AND (project_name LIKE ? OR client_name LIKE ? OR receipt_no LIKE ? OR reference_no LIKE ? OR contract_id LIKE ? OR notes LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Fetch all collections matching query
$stmt = $db->prepare("SELECT * FROM collections {$where} ORDER BY collection_date DESC, id DESC");
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Compute grand totals across all collections
$totalContractAll       = 0;
$totalNetAll            = 0;
$totalCollectedAll      = 0;
$totalBalanceAll        = 0;
$totalBopAll            = 0;
$totalDeductionsAll     = 0;
$totalNetCollectionsAll = 0;

foreach ($collections as &$col) {
    $contractAmt = (float)$col['contract_amount'];
    $netAmt      = (float)($col['net_amount'] > 0 ? $col['net_amount'] : ($col['amount'] > 0 ? $col['amount'] : $contractAmt));
    if ($contractAmt <= 0) $contractAmt = $netAmt;
    $colAmt      = (float)$col['amount'];

    // Balance Collectible = Net Amount - Amount Collected
    $balAmt      = (float)($col['balance_collectible'] > 0 ? $col['balance_collectible'] : compute_balance_collectible($netAmt, $colAmt));

    // Percentage of collections received
    $pctAmt      = (float)($col['percentage_collected'] > 0 ? $col['percentage_collected'] : compute_percentage_collected($contractAmt, $colAmt));

    $deductAmt   = (float)($col['deduction'] ?? 0);

    // Net Collections
    $netColAmt   = (float)($col['net_collections'] ?? 0);
    if ($netColAmt <= 0 && $deductAmt > 0) {
        $netColAmt = max(0, $colAmt - $deductAmt);
    } elseif ($netColAmt <= 0) {
        $netColAmt = $colAmt;
    }

    // Billing on Process (Amount if in process or explicit field)
    $bopAmt      = (float)($col['billing_on_process'] ?? 0);
    if ($bopAmt <= 0 && $col['status'] === 'in process') {
        $bopAmt = $colAmt;
    }

    $col['computed_contract'] = $contractAmt;
    $col['computed_net']      = $netAmt;
    $col['computed_bal']      = $balAmt;
    $col['computed_pct']      = $pctAmt;
    $col['computed_deduct']   = $deductAmt;
    $col['computed_netcol']   = $netColAmt;
    $col['computed_bop']      = $bopAmt;

    $totalContractAll       += $contractAmt;
    $totalNetAll            += $netAmt;
    $totalCollectedAll      += $colAmt;
    $totalBalanceAll        += $balAmt;
    $totalBopAll            += $bopAmt;
    $totalDeductionsAll     += $deductAmt;
    $totalNetCollectionsAll += $netColAmt;
}
unset($col);

$overallPercentage = compute_percentage_collected($totalContractAll, $totalCollectedAll);
$districtsList = get_project_districts();
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Projected Collections</h1>
        <div class="page-subtitle">Excel-style 1-line master view of contracts, billings, and collections as of <?= date('F d, Y') ?></div>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-success btn-sm" data-open-modal="modalAddCollection">+ Record Collection</button>
        <a href="collection_report.php" class="btn btn-primary btn-sm">Cashed Collection Report</a>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print</button>
    </div>
</div>

<!-- 4 Top Executive Computed KPI Metric Cards -->
<div class="metric-grid no-print" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 1.25rem;">
    <!-- 1. Total Contract Amount -->
    <div class="metric-card">
        <div class="metric-label">1. Total Contract Value</div>
        <div class="metric-amount" style="color: var(--text-main);">
            <?= format_money($totalContractAll) ?>
        </div>
    </div>

    <!-- 2. Total Net Amount -->
    <div class="metric-card">
        <div class="metric-label">2. Total Net Amount</div>
        <div class="metric-amount" style="color: var(--color-primary);">
            <?= format_money($totalNetAll) ?>
        </div>
    </div>

    <!-- 3. Total Amount Collected -->
    <div class="metric-card">
        <div class="metric-label">3. Total Amount Collected</div>
        <div class="metric-amount" style="color: var(--color-success);">
            <?= format_money($totalCollectedAll) ?>
            <span style="font-size: 0.72rem; color: var(--color-success); font-weight: 700; margin-left: 4px;">(<?= number_format($overallPercentage, 1) ?>%)</span>
        </div>
    </div>

    <!-- 4. Balance Collectible (Net - Collected) -->
    <div class="metric-card">
        <div class="metric-label">4. Balance Collectible</div>
        <div class="metric-amount" style="color: var(--color-warning);">
            <?= format_money($totalBalanceAll) ?>
        </div>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card no-print" style="padding: 0.75rem 1.25rem; margin-bottom: 1.25rem;">
    <form action="collections.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted);">District:</span>
            <select name="district" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem;" onchange="this.form.submit();">
                <option value="all">All Districts</option>
                <?php foreach ($districtsList as $dCode => $dTitle): ?>
                    <option value="<?= e($dCode) ?>" <?= $districtFilter === $dCode ? 'selected' : '' ?>><?= e($dCode) ?></option>
                <?php endforeach; ?>
            </select>

            <span style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); margin-left: 0.5rem;">Status:</span>
            <select name="status" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem;" onchange="this.form.submit();">
                <option value="all">All Statuses</option>
                <option value="collected" <?= $statusFilter === 'collected' ? 'selected' : '' ?>>Collected (Realized)</option>
                <option value="in process" <?= $statusFilter === 'in process' ? 'selected' : '' ?>>In Process</option>
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="text" name="search" class="search-input" placeholder="Search project, contract ID, remarks..." value="<?= e($searchQuery) ?>" data-table-search="#collectionExcelTable">
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            <?php if ($districtFilter !== 'all' || $statusFilter !== 'all' || !empty($searchQuery)): ?>
                <a href="collections.php" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- EXCEL SPREADSHEET VIEW: PROJECTED COLLECTION (1 LINE ONLY) -->
<!-- ======================================================== -->
<!-- Print-only Section Header -->
<div class="print-section-title" style="display: none;">
    <span>Projected Collection Registry (<?= count($collections) ?> records)</span>
    <span>Total Realized: <?= format_money(array_sum(array_column($collections, 'amount'))) ?></span>
</div>

<div class="excel-sheet-card">
    <!-- Excel Header Bar -->
    <div class="excel-sheet-header no-print">
        <div class="excel-sheet-title">
            PROJECTED COLLECTION
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <div class="excel-sheet-date">
                AS OF <?= strtoupper(date('m/d/Y')) ?> &bull; <?= count($collections) ?> RECORD(S)
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
                            <input type="checkbox" data-report-toggle="proj-contractid" checked>
                            <span>Contract ID</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-contract" checked>
                            <span>Contract Amount</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-net" checked>
                            <span>Net Amount</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-collected" checked>
                            <span>Amount Collected</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-balance" checked>
                            <span>Balance Collectible</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-pct" checked>
                            <span>Percentage %</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-billing" checked>
                            <span>Billing on Process</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-remarks" checked>
                            <span>Remarks</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-status" checked>
                            <span>Status</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-expdate" checked>
                            <span>Expected Date</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-deductions" checked>
                            <span>Deductions</span>
                        </label>
                        <label class="col-picker-item">
                            <input type="checkbox" data-report-toggle="proj-netcol" checked>
                            <span>Net Collection</span>
                        </label>
                        <?= render_custom_field_column_pickers('collections') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Excel Master Table (1 Line Only) -->
    <div class="table-responsive">
        <table class="excel-table" id="collectionExcelTable">
            <thead>
                <tr>
                    <th style="min-width: 170px;">PROJECT NAME</th>
                    <th class="col-toggle-proj-contractid" style="min-width: 105px;">CONTRACT ID</th>
                    <th class="col-toggle-proj-contract" style="min-width: 120px; text-align: right;">CONTRACT AMOUNT</th>
                    <th class="col-toggle-proj-net" style="min-width: 110px; text-align: right;">NET AMOUNT</th>
                    <th class="col-toggle-proj-collected" style="min-width: 120px; text-align: right;">AMOUNT COLLECTED</th>
                    <th class="col-toggle-proj-balance" style="min-width: 120px; text-align: right;">BALANCE COLLECTIBLE</th>
                    <th class="col-toggle-proj-pct" style="min-width: 65px; text-align: center;">%</th>
                    <th class="col-toggle-proj-billing" style="min-width: 120px; text-align: right;">BILLING ON PROCESS</th>
                    <th class="col-toggle-proj-remarks" style="min-width: 130px;">REMARKS</th>
                    <th class="col-toggle-proj-status" style="min-width: 95px; text-align: center;">STATUS</th>
                    <th class="col-toggle-proj-expdate" style="min-width: 110px; text-align: center;">Expected Date of Collection</th>
                    <th class="col-toggle-proj-deductions" style="min-width: 100px; text-align: right;">DEDUCTIONS</th>
                    <th class="col-toggle-proj-netcol" style="min-width: 115px; text-align: right;">NET COLLECTION</th>
                    <?= render_custom_field_table_headers('collections') ?>
                    <th style="min-width: 135px; text-align: center;" class="no-print">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr>
                        <td colspan="<?= 14 + count(get_custom_fields_for_module('collections')) ?>" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No collection records found. Click "+ Record Collection" to add a new project entry.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($collections as $col): ?>
                        <tr>
                            <!-- 1. PROJECT NAME -->
                            <td style="font-weight: 700; color: #0f172a;">
                                <div><?= e($col['project_name']) ?></div>
                                <?php if (!empty($col['project_district'])): ?>
                                    <span style="font-size: 0.68rem; font-weight: 600; color: #64748b;"><?= e($col['project_district']) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- 2. CONTRACT ID -->
                            <td class="col-toggle-proj-contractid">
                                <?php if (!empty($col['contract_id'])): ?>
                                    <span class="voucher-code-badge"><?= e($col['contract_id']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>

                            <!-- 3. CONTRACT AMOUNT -->
                            <td class="col-toggle-proj-contract excel-num" style="color: #0f172a;">
                                <?= format_money($col['computed_contract']) ?>
                            </td>

                            <!-- 4. NET AMOUNT -->
                            <td class="col-toggle-proj-net excel-num" style="color: #0f172a; font-weight: 600;">
                                <?= format_money($col['computed_net']) ?>
                            </td>

                            <!-- 5. AMOUNT COLLECTED -->
                            <td class="col-toggle-proj-collected excel-num" style="color: #16a34a; font-weight: 700;">
                                <?= format_money($col['amount']) ?>
                            </td>

                            <!-- 6. BALANCE COLLECTIBLE -->
                            <td class="col-toggle-proj-balance excel-num" style="color: #d97706; font-weight: 700;">
                                <?= format_money($col['computed_bal']) ?>
                            </td>

                            <!-- 7. % -->
                            <td class="col-toggle-proj-pct excel-mono" style="color: <?= $col['computed_pct'] >= 100 ? '#16a34a' : '#dc2626' ?>; font-weight: 700; text-align: center;">
                                <?= number_format($col['computed_pct'], 1) ?>%
                            </td>

                            <!-- 8. BILLING ON PROCESS -->
                            <td class="col-toggle-proj-billing excel-num" style="color: #64748b;">
                                <?= $col['computed_bop'] > 0 ? format_money($col['computed_bop']) : '&mdash;' ?>
                            </td>

                            <!-- 9. REMARKS -->
                            <td class="col-toggle-proj-remarks" style="max-width: 170px; overflow: hidden; text-overflow: ellipsis;" title="<?= e($col['notes'] ?: $col['receipt_no']) ?>">
                                <?= e($col['notes']) ?: (!empty($col['receipt_no']) ? 'OR: ' . e($col['receipt_no']) : '&mdash;') ?>
                            </td>

                            <!-- 10. STATUS -->
                            <td class="col-toggle-proj-status" style="text-align: center;">
                                <?php if ($col['status'] === 'collected'): ?>
                                    <span class="badge badge-collected" style="font-size: 0.68rem; padding: 2px 7px;">Collected</span>
                                <?php else: ?>
                                    <span class="badge badge-inprocess" style="font-size: 0.68rem; padding: 2px 7px;">In Process</span>
                                <?php endif; ?>
                            </td>

                            <!-- 11. Expected Date of Collection -->
                            <td class="col-toggle-proj-expdate" style="text-align: center; font-size: 0.78rem;">
                                <?= !empty($col['expected_collection_date']) ? format_date($col['expected_collection_date']) : '&mdash;' ?>
                            </td>

                            <!-- 12. DEDUCTIONS -->
                            <td class="col-toggle-proj-deductions excel-num" style="color: #64748b;">
                                <?= $col['computed_deduct'] > 0 ? format_money($col['computed_deduct']) : '&mdash;' ?>
                            </td>

                            <!-- 13. NET COLLECTION -->
                            <td class="col-toggle-proj-netcol excel-num" style="color: #dc2626; font-weight: 700;">
                                <?= format_money($col['computed_netcol']) ?>
                            </td>

                            <?= render_custom_field_table_cells('collections', $col['custom_fields'] ?? '') ?>

                            <!-- 14. ACTION -->
                            <td style="text-align: center;" class="no-print">
                                <div style="display: flex; gap: 0.25rem; justify-content: center; align-items: center;">
                                    <button type="button" class="btn btn-outline btn-sm btn-edit-collection" 
                                            style="padding: 1px 6px; font-size: 0.72rem; color: #dc2626; border-color: #fca5a5;" 
                                            title="Edit Collection"
                                            data-collection='<?= htmlspecialchars(json_encode($col), ENT_QUOTES, 'UTF-8') ?>'>
                                        Edit
                                    </button>
                                    <form action="api/actions.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="update_collection_status">
                                        <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $col['status'] === 'collected' ? 'in process' : 'collected' ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="padding: 1px 6px; font-size: 0.72rem;" title="Toggle Collected / In Process">
                                            <?= $col['status'] === 'collected' ? 'Mark Pending' : 'Mark Collected' ?>
                                        </button>
                                    </form>
                                    <form action="api/actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this collection entry?');">
                                        <input type="hidden" name="action" value="delete_collection">
                                        <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 1px 5px; font-size: 0.72rem;" title="Delete">&times;</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align: right; text-transform: uppercase;">Total Projected Collections:</th>
                    <th class="col-toggle-proj-contract excel-num" style="color: #0f172a;"><?= format_money($totalContractAll) ?></th>
                    <th class="col-toggle-proj-net excel-num" style="color: #0f172a;"><?= format_money($totalNetAll) ?></th>
                    <th class="col-toggle-proj-collected excel-num" style="color: #16a34a;"><?= format_money($totalCollectedAll) ?></th>
                    <th class="col-toggle-proj-balance excel-num" style="color: #d97706;"><?= format_money($totalBalanceAll) ?></th>
                    <th class="col-toggle-proj-pct excel-mono" style="color: #0f172a; text-align: center;"><?= number_format($overallPercentage, 1) ?>%</th>
                    <th class="col-toggle-proj-billing excel-num" style="color: #64748b;"><?= format_money($totalBopAll) ?></th>
                    <th class="col-toggle-proj-remarks"></th>
                    <th class="col-toggle-proj-status"></th>
                    <th class="col-toggle-proj-expdate"></th>
                    <th class="col-toggle-proj-deductions excel-num" style="color: #64748b;"><?= format_money($totalDeductionsAll) ?></th>
                    <th class="col-toggle-proj-netcol excel-num" style="color: #dc2626; font-weight: 800;"><?= format_money($totalNetCollectionsAll) ?></th>
                    <?= render_custom_field_table_footers('collections') ?>
                    <th class="no-print"></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Spreadsheet Status Bar -->
    <div class="excel-sheet-footer no-print">
        <span><span class="excel-status-dot"></span> Sheet Ready &bull; <?= count($collections) ?> collection record(s)</span>
        <span>Total Net Collections: <strong style="color: #dc2626; font-size: 0.85rem;"><?= format_money($totalNetCollectionsAll) ?></strong></span>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
