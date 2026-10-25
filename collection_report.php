<?php
/**
 * Aarcon Builders - Cashed Collection Report
 * Strictly filters for transactions with status = 'collected' (excluding 'in process')
 * Includes Date Range filters (Start Date & End Date), Print button,
 * and Dynamic Report Column Toggles (Tax, Net Amount, Balance, etc.)
 */

$pageTitle = 'Cashed Collection Report';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Date Range Parameters
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$district  = $_GET['district'] ?? 'all';

// Strict condition: status = 'collected' ONLY (excludes 'in process')
$where = "WHERE status = 'collected'";
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $where .= " AND collection_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

if ($district !== 'all' && !empty($district)) {
    $where .= " AND project_district = ?";
    $params[] = $district;
}

$stmt = $db->prepare("SELECT * FROM collections {$where} ORDER BY collection_date ASC, id ASC");
$stmt->execute($params);
$cashedCollections = $stmt->fetchAll();

// Calculations (No Tax - Pure Net, Collected & Balance)
$totalCashedInflow = 0;
$totalContract = 0;
$totalNet = 0;
$totalBalance = 0;
$totalDeductions = 0;
$totalNetCollections = 0;

foreach ($cashedCollections as $c) {
    $amt = (float)$c['amount'];
    $contract = (float)$c['contract_amount'];
    if ($contract <= 0) $contract = $amt;
    $net = (float)($c['net_amount'] > 0 ? $c['net_amount'] : ($amt > 0 ? $amt : $contract));
    $bal = compute_balance_collectible($net, $amt);
    $deduct = (float)($c['deduction'] ?? 0);
    $netCol = (float)($c['net_collections'] ?? 0);
    if ($netCol <= 0 && $deduct > 0) {
        $netCol = max(0, $amt - $deduct);
    } elseif ($netCol <= 0) {
        $netCol = $amt;
    }

    $totalCashedInflow += $amt;
    $totalContract += $contract;
    $totalNet += $net;
    $totalBalance += $bal;
    $totalDeductions += $deduct;
    $totalNetCollections += $netCol;
}

$districtsList = get_project_districts();
$periodLabel = (!empty($startDate) && !empty($endDate))
    ? date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate))
    : 'All Recorded Time';
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span class="badge badge-collected">Strict Status: 'collected'</span>
            <span style="font-size: 0.85rem; color: var(--text-muted);">&bull; Excludes 'in process' deposits</span>
        </div>
        <h1 class="page-title">Cashed Collection Report</h1>
        <p class="page-subtitle">Statement period: <?= e($periodLabel) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary btn-sm" onclick="window.print();">Print Report (Short Bond Paper)</button>
        <a href="collections.php" class="btn btn-outline btn-sm">Collections Manager</a>
    </div>
</div>

<!-- Filter Bar & Column Toggles Card (Apple Liquid Glass) -->
<div class="no-print" style="margin-bottom: 1.25rem; padding: 1.25rem; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); border-radius: var(--radius-lg, 14px); box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03));">
    <!-- Date Range & District Filters -->
    <form action="collection_report.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 0.85rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-weight: 600; font-size: 0.82rem; color: #475569;">Date Range:</span>
            <input type="date" name="start_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($startDate) ?>" placeholder="Start Date">
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" name="end_date" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?= e($endDate) ?>" placeholder="End Date">
            
            <select name="district" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                <option value="all">All Districts</option>
                <?php foreach ($districtsList as $dCode => $dName): ?>
                    <option value="<?= e($dCode) ?>" <?= $district === $dCode ? 'selected' : '' ?>><?= e($dCode) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Generate Report</button>
            <?php if (!empty($startDate) || !empty($endDate) || $district !== 'all'): ?>
                <a href="collection_report.php" class="btn btn-outline btn-sm" style="color: var(--text-muted);">&times; Reset</a>
            <?php endif; ?>
        </div>

        <div style="font-size: 0.85rem; font-weight: 600; color: #475569;">
            Total Realized Cashed: <strong class="excel-mono" style="color: #059669; font-size: 1.1rem; margin-left: 4px;"><?= format_money($totalCashedInflow) ?></strong>
        </div>
    </form>
</div>

<!-- Statement Wrapper -->
<div>
    
    <!-- Dedicated Corporate Executive Summary for Print (No Tax Columns) -->
    <div class="print-summary-box" style="display: none;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1.5px solid #000000;">
            <thead>
                <tr style="background: #f1f5f9; text-align: center; font-size: 8pt; font-weight: 800;">
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">1. TOTAL REALIZED INFLOW</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">2. GROSS CONTRACT VALUE</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; text-transform: uppercase;">3. TOTAL NET AMOUNT</th>
                    <th style="padding: 6px; border: 1px solid #334155; width: 25%; background: #e2e8f0; text-transform: uppercase;">4. REMAINING BALANCE</th>
                </tr>
            </thead>
            <tbody>
                <tr style="text-align: center; font-size: 10.5pt; font-weight: 900;">
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #047857; font-family: Arial, sans-serif;">
                        <?= format_money($totalCashedInflow) ?>
                        <div style="font-size: 7pt; font-weight: 600; color: #64748b; margin-top: 2px;">
                            <?= count($cashedCollections) ?> realized receipt(s)
                        </div>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #0f172a; font-family: Arial, sans-serif;">
                        <?= format_money($totalContract) ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #1e40af; font-family: Arial, sans-serif;">
                        <?= format_money($totalNet) ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #cbd5e1; color: #b45309; background: #fffbeb; font-family: Arial, sans-serif;">
                        <?= format_money($totalBalance) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Screen Summary Equation Bar (Apple Liquid Glass) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg, rgba(255, 255, 255, 0.75)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-radius: var(--radius-lg, 14px); padding: 1rem 1.35rem; margin-bottom: 1.25rem; border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.5)); box-shadow: var(--glass-shadow, 0 4px 20px rgba(0,0,0,0.03)); flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Total Realized Cash Inflow</div>
            <div class="excel-mono" style="font-size: 1.75rem; font-weight: 800; color: #059669; letter-spacing: -0.02em; margin-top: 0.15rem;">
                <?= format_money($totalCashedInflow) ?>
            </div>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.15rem;">
                Strictly includes <strong>status = 'collected'</strong> transactions only (excludes <?= (int)$db->query("SELECT COUNT(*) FROM collections WHERE status = 'in process'")->fetchColumn() ?> in-process items)
            </div>
        </div>

        <div style="display: flex; gap: 1.5rem; text-align: right; font-size: 0.82rem; flex-wrap: wrap;">
            <div class="col-toggle-contract">
                <span style="display: block; font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Gross Contracts</span>
                <strong class="excel-mono" style="font-size: 0.95rem; color: #0f172a;"><?= format_money($totalContract) ?></strong>
            </div>
            <div class="col-toggle-net">
                <span style="display: block; font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Net Amount</span>
                <strong class="excel-mono" style="color: #0f172a; font-size: 0.95rem;"><?= format_money($totalNet) ?></strong>
            </div>
            <div class="col-toggle-balance">
                <span style="display: block; font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Remaining Balance</span>
                <strong class="excel-mono" style="color: #d97706; font-size: 0.95rem;"><?= format_money($totalBalance) ?></strong>
            </div>
        </div>
    </div>

    <!-- Print-only Section Header -->
    <div class="print-section-title" style="display: none;">
        <span>Cashed Collections Journal - Realized Inflow (<?= count($cashedCollections) ?> records)</span>
        <span>Total Realized Inflow: <?= format_money($totalCashedInflow) ?></span>
    </div>

    <!-- Cashed Collections Excel Sheet Card -->
    <div class="excel-sheet-card">
        <div class="excel-sheet-header no-print">
            <div class="excel-sheet-title">
                <span class="excel-sheet-badge" style="background: rgba(16, 185, 129, 0.15); color: #065f46; border-color: rgba(16, 185, 129, 0.3);"><?= count($cashedCollections) ?> REALIZED</span>
                <span>Cashed Collections Spreadsheet Ledger</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <div class="excel-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search realized collections..." data-table-search="#cashedCollectionsTable">
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
                                <input type="checkbox" data-report-toggle="district" checked>
                                <span>Project District</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="contractid" checked>
                                <span>Contract ID</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="client" checked>
                                <span>Client / Agency</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="contract" checked>
                                <span>Contract Amount</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="net" checked>
                                <span>Net Amount</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="deduction" checked>
                                <span>Deduction</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="netcollections" checked>
                                <span>Net Collections</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="balance" checked>
                                <span>Balance Collectible</span>
                            </label>
                            <label class="col-picker-item">
                                <input type="checkbox" data-report-toggle="method" checked>
                                <span>Method &amp; Ref #</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <?php if (empty($cashedCollections)): ?>
                <div class="empty-state">
                    No cashed collections found for the specified period.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="excel-table" id="cashedCollectionsTable">
                        <thead>
                            <tr>
                                <th style="min-width: 95px; text-align: center;">DATE</th>
                                <th class="col-toggle-contractid" style="min-width: 120px;">CONTRACT ID</th>
                                <th style="min-width: 120px;">OR / RECEIPT #</th>
                                <th class="col-toggle-client" style="min-width: 160px;">CLIENT / AGENCY</th>
                                <th style="min-width: 170px;">PROJECT NAME</th>
                                <th class="col-toggle-district" style="min-width: 90px;">DISTRICT</th>
                                <th class="col-toggle-contract" style="min-width: 110px; text-align: right;">CONTRACT AMT</th>
                                <th class="col-toggle-net" style="min-width: 110px; text-align: right;">NET AMOUNT</th>
                                <th style="min-width: 120px; text-align: right;">AMOUNT COLLECTED</th>
                                <th class="col-toggle-deduction" style="min-width: 100px; text-align: right;">DEDUCTION</th>
                                <th class="col-toggle-netcollections" style="min-width: 115px; text-align: right;">NET COLLECTIONS</th>
                                <th class="col-toggle-balance" style="min-width: 110px; text-align: right;">BALANCE</th>
                                <th class="col-toggle-method" style="min-width: 120px;">METHOD &amp; REF</th>
                                <th style="min-width: 90px; text-align: center;">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashedCollections as $c): ?>
                                <?php 
                                    $cContract = (float)$c['contract_amount'];
                                    if ($cContract <= 0) $cContract = (float)$c['amount'];
                                    $cNet = (float)($c['net_amount'] > 0 ? $c['net_amount'] : ($c['amount'] > 0 ? $c['amount'] : $cContract));
                                    $cBal = compute_balance_collectible($cNet, (float)$c['amount']);
                                    $cDeduct = (float)($c['deduction'] ?? 0);
                                    $cNetCol = (float)($c['net_collections'] ?? 0);
                                    if ($cNetCol <= 0 && $cDeduct > 0) {
                                        $cNetCol = max(0, (float)$c['amount'] - $cDeduct);
                                    } elseif ($cNetCol <= 0) {
                                        $cNetCol = (float)$c['amount'];
                                    }
                                ?>
                                <tr>
                                    <td style="text-align: center; font-size: 0.78rem;" class="excel-mono">
                                        <div><?= format_date($c['collection_date']) ?></div>
                                        <?php if (!empty($c['expected_collection_date'])): ?>
                                            <div style="font-size: 0.68rem; color: var(--text-muted);" title="Expected Date of Collection">
                                                Exp: <?= format_date($c['expected_collection_date']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-toggle-contractid">
                                        <span class="voucher-code-badge"><?= e($c['contract_id'] ?? '') ?: '&mdash;' ?></span>
                                    </td>
                                    <td>
                                        <span class="excel-mono" style="font-weight: 700; color: #0f172a;"><?= e($c['receipt_no']) ?></span>
                                    </td>
                                    <td class="col-toggle-client" style="font-weight: 500; color: #0f172a;"><?= e($c['client_name']) ?></td>
                                    <td style="font-weight: 600; color: #0f172a;"><?= e($c['project_name']) ?></td>
                                    <td class="col-toggle-district">
                                        <span class="badge" style="background: rgba(0, 0, 0, 0.04); font-size: 0.68rem;"><?= e($c['project_district']) ?: 'General' ?></span>
                                    </td>
                                    <td class="col-toggle-contract excel-num"><?= format_money($cContract) ?></td>
                                    <td class="col-toggle-net excel-num" style="color: #0f172a; font-weight: 600;"><?= format_money($cNet) ?></td>
                                    <td class="excel-num" style="color: #059669; font-weight: 700;"><?= format_money($c['amount']) ?></td>
                                    <td class="col-toggle-deduction excel-num" style="color: #64748b;">
                                        <?= $cDeduct > 0 ? format_money($cDeduct) : '&mdash;' ?>
                                    </td>
                                    <td class="col-toggle-netcollections excel-num" style="color: #dc2626; font-weight: 700;">
                                        <?= format_money($cNetCol) ?>
                                    </td>
                                    <td class="col-toggle-balance excel-num" style="color: #d97706; font-weight: 600;"><?= format_money($cBal) ?></td>
                                    <td class="col-toggle-method">
                                        <span style="font-size: 0.78rem;"><?= e($c['payment_method']) ?></span>
                                        <?php if (!empty($c['reference_no'])): ?>
                                            <span class="excel-mono" style="color: var(--text-muted); font-size: 0.7rem; margin-left: 4px;">(#<?= e($c['reference_no']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-collected" style="font-size: 0.7rem;">Collected</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th style="text-align: center;">TOTALS:</th>
                                <th class="col-toggle-contractid"></th>
                                <th colspan="2" class="col-toggle-client" style="text-align: left; text-transform: uppercase;"><?= count($cashedCollections) ?> collections</th>
                                <th></th>
                                <th class="col-toggle-district"></th>
                                <th class="col-toggle-contract excel-num"><?= format_money($totalContract) ?></th>
                                <th class="col-toggle-net excel-num" style="color: #0f172a;"><?= format_money($totalNet) ?></th>
                                <th class="excel-num" style="color: #059669;"><?= format_money($totalCashedInflow) ?></th>
                                <th class="col-toggle-deduction excel-num" style="color: #64748b;"><?= format_money($totalDeductions) ?></th>
                                <th class="col-toggle-netcollections excel-num" style="color: #dc2626; font-weight: 800;"><?= format_money($totalNetCollections) ?></th>
                                <th class="col-toggle-balance excel-num" style="color: #d97706;"><?= format_money($totalBalance) ?></th>
                                <th class="col-toggle-method"></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
