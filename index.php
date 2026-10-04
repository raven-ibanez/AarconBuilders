<?php
/**
 * Aarcon Builders - Dashboard & Cash Balance
 * Module 4: The Final Disbursement & Cash Balance Report
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

// Filter parameters
$period = $_GET['period'] ?? 'all';
$collectionDateWhere = '';
$checkDateWhere = '';
$cashDateWhere = '';

if ($period === 'today') {
    $today = date('Y-m-d');
    $collectionDateWhere = " AND collection_date = '{$today}' ";
    $checkDateWhere = " AND check_date = '{$today}' ";
    $cashDateWhere = " AND request_date = '{$today}' ";
} elseif ($period === 'week') {
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $collectionDateWhere = " AND collection_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}' ";
    $checkDateWhere = " AND check_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}' ";
    $cashDateWhere = " AND request_date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}' ";
} elseif ($period === 'month') {
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    $collectionDateWhere = " AND collection_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}' ";
    $checkDateWhere = " AND check_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}' ";
    $cashDateWhere = " AND request_date BETWEEN '{$startOfMonth}' AND '{$endOfMonth}' ";
}

// 1. Total Collections (Inflow)
$colRes = $db->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM collections WHERE 1=1 {$collectionDateWhere}")->fetch();
$totalCollections = (float) $colRes['total'];
$collectionsCount = (int) $colRes['count'];

// 2. Funded Checks (Outflow)
$chkRes = $db->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM checks WHERE status = 'funded' {$checkDateWhere}")->fetch();
$totalFundedChecks = (float) $chkRes['total'];
$fundedChecksCount = (int) $chkRes['count'];

// 3. Approved Cash Expenses (Outflow)
$cashRes = $db->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM cash_requests WHERE status IN ('approved', 'disbursed') {$cashDateWhere}")->fetch();
$totalApprovedCash = (float) $cashRes['total'];
$approvedCashCount = (int) $cashRes['count'];

// Formula: Net Cash Balance = Total Collections - (Funded Checks + Approved Cash Expenses)
$totalOutflow = $totalFundedChecks + $totalApprovedCash;
$netCashBalance = $totalCollections - $totalOutflow;

// Pending counts
$pendingChecks = $db->query("SELECT * FROM checks WHERE status = 'pending' ORDER BY check_date ASC LIMIT 5")->fetchAll();
$pendingCashRequests = $db->query("SELECT * FROM cash_requests WHERE status = 'pending' ORDER BY request_date DESC LIMIT 5")->fetchAll();
?>

<!-- Page Header
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Financial Dashboard</h1>
        <p class="page-subtitle">Cash balance and daily approvals</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddCheck">+ Check</button>
        <button class="btn btn-outline btn-sm" data-open-modal="modalAddCashRequest">+ Cash Request</button>
        <button class="btn btn-success btn-sm" data-open-modal="modalAddCollection">+ Collection</button>
    </div>
</div>
 -->
<!-- Minimalist Net Balance Hero -->
<div class="balance-card">
    <div class="balance-card-grid">
        <div>
            <div class="balance-title">Net Cash Balance</div>
            <div class="balance-value" style="color: <?= $netCashBalance >= 0 ? '#0f172a' : '#dc2626' ?>;">
                <?= format_money($netCashBalance) ?>
            </div>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                <a href="index.php?period=all"
                    class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : 'btn-outline' ?>">All Time</a>
                <a href="index.php?period=month"
                    class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
                <a href="index.php?period=week"
                    class="btn btn-sm <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
                <a href="index.php?period=today"
                    class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
            </div>
        </div>

        <div class="balance-equation">
            <div class="eq-item">
                <span class="eq-label">Total Collections</span>
                <span class="eq-value"
                    style="color: var(--color-success);"><?= format_money($totalCollections) ?></span>
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
</div>

<!-- 3 Metric Cards -->
<div class="metric-grid">
    <div class="metric-card">
        <div class="metric-label">1. Collections (Inflow)</div>
        <div class="metric-amount" style="color: var(--color-success);"><?= format_money($totalCollections) ?></div>
        <div class="metric-sub"><?= $collectionsCount ?> transaction(s) &bull; <a href="collections.php">View log</a>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">2. Funded Checks (Outflow)</div>
        <div class="metric-amount"><?= format_money($totalFundedChecks) ?></div>
        <div class="metric-sub"><?= $fundedChecksCount ?> check(s) &bull; <a href="checks.php">View checks</a></div>
    </div>

    <div class="metric-card">
        <div class="metric-label">3. Cash Expenses (Outflow)</div>
        <div class="metric-amount"><?= format_money($totalApprovedCash) ?></div>
        <div class="metric-sub"><?= $approvedCashCount ?> voucher(s) &bull; <a href="cash_requests.php">View
                vouchers</a>
        </div>
    </div>
</div>

<!-- Boss Review Queues (Two Columns) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.25rem;">
    <!-- Checks for Funding (Boss Review) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Checks for Funding (Boss Review)</h3>
            <a href="checks.php" class="btn btn-outline btn-sm">All Checks</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($pendingChecks)): ?>
                <div class="empty-state">
                    No checks currently waiting for funding.
                    <p>Click "+ Check" to encode signed checks.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check # &amp; Bank</th>
                                <th>Payee</th>
                                <th style="text-align: right;">Amount</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingChecks as $chk): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= format_date($chk['check_date']) ?></td>
                                    <td>
                                        <span class="mono"><?= e($chk['check_number']) ?></span><br>
                                        <small style="color: var(--text-muted);"><?= e($chk['bank_name']) ?></small>
                                    </td>
                                    <td><?= e($chk['payee']) ?></td>
                                    <td class="amount-col"><?= format_money($chk['amount']) ?></td>
                                    <td class="action-cell" style="text-align: center;">
                                        <button class="btn btn-primary btn-sm btn-ajax-fund" data-check-id="<?= $chk['id'] ?>"
                                            data-check-num="<?= e($chk['check_number']) ?>">
                                            Fund
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cash Requests for Boss Approval -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Cash Requests</h3>
            <a href="cash_requests.php" class="btn btn-outline btn-sm">All Requests</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($pendingCashRequests)): ?>
                <div class="empty-state">
                    No pending cash requests.
                    <p>Click "+ Cash Request" to submit petty cash requests.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Voucher</th>
                                <th>Requester</th>
                                <th>Purpose</th>
                                <th style="text-align: right;">Amount</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingCashRequests as $req): ?>
                                <tr>
                                    <td class="mono" style="font-weight: 600;"><?= e($req['voucher_no']) ?></td>
                                    <td><?= e($req['requested_by']) ?></td>
                                    <td style="max-width: 180px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"
                                        title="<?= e($req['purpose']) ?>">
                                        <?= e($req['purpose']) ?>
                                    </td>
                                    <td class="amount-col"><?= format_money($req['amount']) ?></td>
                                    <td class="action-cell" style="text-align: center;">
                                        <button class="btn btn-success btn-sm btn-ajax-approve-cash"
                                            data-request-id="<?= $req['id'] ?>" data-voucher="<?= e($req['voucher_no']) ?>">
                                            Approve
                                        </button>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>