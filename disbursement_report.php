<?php
/**
 * Aarcon Builders - Module 4: The Final Disbursement & Cash Balance Report
 */

$pageTitle = 'Disbursement & Cash Balance Report';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Period Filter
$period = $_GET['period'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$colWhere = 'WHERE 1=1';
$chkWhere = "WHERE status = 'funded'";
$cashWhere = "WHERE status IN ('approved', 'disbursed')";

$periodLabel = 'All Time';

if ($period === 'today') {
    $today = date('Y-m-d');
    $colWhere .= " AND collection_date = '{$today}'";
    $chkWhere .= " AND check_date = '{$today}'";
    $cashWhere .= " AND request_date = '{$today}'";
    $periodLabel = 'Today (' . date('M d, Y') . ')';
} elseif ($period === 'week') {
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $colWhere .= " AND collection_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $chkWhere .= " AND check_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $cashWhere .= " AND request_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'";
    $periodLabel = 'This Week';
} elseif ($period === 'month') {
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    $colWhere .= " AND collection_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $chkWhere .= " AND check_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $cashWhere .= " AND request_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'";
    $periodLabel = date('F Y');
} elseif ($period === 'custom' && !empty($startDate) && !empty($endDate)) {
    $colWhere .= " AND collection_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $chkWhere .= " AND check_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $cashWhere .= " AND request_date BETWEEN '{$startDate}' AND '{$endDate}'";
    $periodLabel = date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
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

// Net
$totalOutflow = $totalFundedChecks + $totalApprovedCash;
$netCashBalance = $totalInflow - $totalOutflow;
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Disbursement &amp; Cash Balance Report</h1>
        <p class="page-subtitle">Statement period: <?= e($periodLabel) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button class="btn btn-primary btn-sm" onclick="window.print();">Print Report</button>
    </div>
</div>

<!-- Filter Bar -->
<div class="card no-print" style="padding: 0.85rem 1.25rem; margin-bottom: 1.25rem;">
    <form action="disbursement_report.php" method="GET" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
            <a href="disbursement_report.php?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="disbursement_report.php?period=month" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">Month</a>
            <a href="disbursement_report.php?period=week" class="btn btn-sm <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?>">Week</a>
            <a href="disbursement_report.php?period=today" class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
        </div>
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="start_date" class="form-control" style="width: auto; padding: 0.35rem 0.5rem;" value="<?= e($startDate) ?>" required>
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" name="end_date" class="form-control" style="width: auto; padding: 0.35rem 0.5rem;" value="<?= e($endDate) ?>" required>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </div>
    </form>
</div>

<!-- Printable Statement Card -->
<div class="card" style="padding: 1.5rem;">

    <!-- Summary Box -->
    <div class="balance-card-grid" style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-color);">
        <div>
            <div class="balance-title">Net Available Cash Balance</div>
            <div class="balance-value" style="color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>;">
                <?= format_money($netCashBalance) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                <?= $netCashBalance >= 0 ? 'Surplus cash remaining' : 'Deficit: Outflow exceeds collections' ?>
            </div>
        </div>

        <div class="balance-equation">
            <div class="eq-item">
                <span class="eq-label">Total Collections</span>
                <span class="eq-value" style="color: var(--color-success);"><?= format_money($totalInflow) ?></span>
            </div>
            <span class="eq-op">&minus;</span>
            <div class="eq-item">
                <span class="eq-label">Funded Checks</span>
                <span class="eq-value"><?= format_money($totalFundedChecks) ?></span>
            </div>
            <span class="eq-op">&minus;</span>
            <div class="eq-item">
                <span class="eq-label">Cash Expenses</span>
                <span class="eq-value"><?= format_money($totalApprovedCash) ?></span>
            </div>
            <span class="eq-op">=</span>
            <div class="eq-item">
                <span class="eq-label">Net Balance</span>
                <span class="eq-value" style="font-weight: 700;"><?= format_money($netCashBalance) ?></span>
            </div>
        </div>
    </div>

    <!-- 1. Collections Section -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600;">1. Inflow: Collections</h3>
            <strong style="color: var(--color-success); font-family: var(--font-mono);"><?= format_money($totalInflow) ?></strong>
        </div>
        <?php if (empty($collections)): ?>
            <p style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">No collections recorded in this period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>OR / Receipt #</th>
                            <th>Client / Customer</th>
                            <th>Project</th>
                            <th>Method</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collections as $col): ?>
                            <tr>
                                <td><?= format_date($col['collection_date']) ?></td>
                                <td class="mono"><?= e($col['receipt_no']) ?></td>
                                <td><?= e($col['client_name']) ?></td>
                                <td><?= e($col['project_name']) ?></td>
                                <td><?= e($col['payment_method']) ?></td>
                                <td class="amount-col" style="color: var(--color-success);"><?= format_money($col['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. Funded Checks Section -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600;">2. Outflow: Funded Checks</h3>
            <strong style="font-family: var(--font-mono);"><?= format_money($totalFundedChecks) ?></strong>
        </div>
        <?php if (empty($fundedChecks)): ?>
            <p style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">No funded checks recorded in this period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check #</th>
                            <th>Bank</th>
                            <th>Payee</th>
                            <th>Project</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fundedChecks as $chk): ?>
                            <tr>
                                <td><?= format_date($chk['check_date']) ?></td>
                                <td class="mono"><?= e($chk['check_number']) ?></td>
                                <td><?= e($chk['bank_name']) ?></td>
                                <td><?= e($chk['payee']) ?></td>
                                <td><?= e($chk['project_name']) ?></td>
                                <td class="amount-col"><?= format_money($chk['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. Cash Expenses Section -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600;">3. Outflow: Approved Cash Expenses</h3>
            <strong style="font-family: var(--font-mono);"><?= format_money($totalApprovedCash) ?></strong>
        </div>
        <?php if (empty($approvedCash)): ?>
            <p style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">No approved cash expenses in this period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher #</th>
                            <th>Requester</th>
                            <th>Project</th>
                            <th>Purpose</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedCash as $cash): ?>
                            <tr>
                                <td><?= format_date($cash['request_date']) ?></td>
                                <td class="mono"><?= e($cash['voucher_no']) ?></td>
                                <td><?= e($cash['requested_by']) ?></td>
                                <td><?= e($cash['department_or_project']) ?></td>
                                <td><?= e($cash['purpose']) ?></td>
                                <td class="amount-col"><?= format_money($cash['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reconciliation Summary -->
    <div style="background: var(--bg-page); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem 1.25rem;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 6px 0; color: var(--text-muted);">Total Collections (Inflow):</td>
                <td class="mono" style="text-align: right; color: var(--color-success); font-weight: 600;">+ <?= format_money($totalInflow) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 6px 0; color: var(--text-muted);">Less: Funded Checks:</td>
                <td class="mono" style="text-align: right; font-weight: 600;">- <?= format_money($totalFundedChecks) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 6px 0; color: var(--text-muted);">Less: Cash Expenses:</td>
                <td class="mono" style="text-align: right; font-weight: 600;">- <?= format_money($totalApprovedCash) ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0 4px; font-weight: 700; font-size: 1rem;">NET REMAINING BALANCE:</td>
                <td class="mono" style="text-align: right; font-weight: 700; font-size: 1.15rem; padding: 10px 0 4px; color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>;">
                    <?= format_money($netCashBalance) ?>
                </td>
            </tr>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
