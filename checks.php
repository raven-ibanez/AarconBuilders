<?php
/**
 * Aarcon Builders - Module 1: Check Management (PDCs & Funding)
 */

$pageTitle = 'Check Management';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

$activeTab = $_GET['tab'] ?? 'funding';
$fundingFilter = $_GET['filter'] ?? 'all_pending';

$whereClause = "WHERE status = 'pending'";
$today = date('Y-m-d');
$endOfWeek = date('Y-m-d', strtotime('+7 days'));

if ($fundingFilter === 'today') {
    $whereClause .= " AND check_date = '{$today}'";
} elseif ($fundingFilter === 'week') {
    $whereClause .= " AND check_date <= '{$endOfWeek}'";
}

$pendingChecks = $db->query("SELECT * FROM checks {$whereClause} ORDER BY check_date ASC, id ASC")->fetchAll();
$pendingTotal = array_sum(array_column($pendingChecks, 'amount'));

// Monthly Check Data
$allActiveChecks = $db->query("SELECT * FROM checks WHERE status != 'cancelled' ORDER BY check_date ASC")->fetchAll();
$checksByMonth = [];
foreach ($allActiveChecks as $chk) {
    $m = date('F Y', strtotime($chk['check_date']));
    if (!isset($checksByMonth[$m])) {
        $checksByMonth[$m] = ['total' => 0, 'checks' => []];
    }
    $checksByMonth[$m]['total'] += $chk['amount'];
    $checksByMonth[$m]['checks'][] = $chk;
}

// All checks
$allChecks = $db->query("SELECT * FROM checks ORDER BY check_date DESC, id DESC")->fetchAll();
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Check Management (PDCs &amp; Funding)</h1>
        <p class="page-subtitle">Track hand-written checks, boss funding, and monthly maturity schedules</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddCheck">+ New Check</button>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print Report</button>
    </div>
</div>

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs-nav no-print">
        <button class="tab-btn <?= $activeTab === 'funding' ? 'active' : '' ?>" data-tab="tab-funding">
            Checks for Funding (Boss Review)
            <?php if (count($pendingChecks) > 0): ?>
                <span class="badge badge-pending"><?= count($pendingChecks) ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab === 'monthly' ? 'active' : '' ?>" data-tab="tab-monthly">
            Monthly Release Report (Printable)
        </button>
        <button class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>" data-tab="tab-all">
            All Checks
        </button>
    </div>

    <!-- TAB 1: Checks for Funding -->
    <div class="tab-pane <?= $activeTab === 'funding' ? 'active' : '' ?>" id="tab-funding">
        <div class="card">
            <div class="card-header no-print">
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Filter:</span>
                    <a href="checks.php?tab=funding&filter=all_pending" class="btn btn-sm <?= $fundingFilter === 'all_pending' ? 'btn-primary' : 'btn-outline' ?>">All Pending</a>
                    <a href="checks.php?tab=funding&filter=week" class="btn btn-sm <?= $fundingFilter === 'week' ? 'btn-primary' : 'btn-outline' ?>">Next 7 Days</a>
                    <a href="checks.php?tab=funding&filter=today" class="btn btn-sm <?= $fundingFilter === 'today' ? 'btn-primary' : 'btn-outline' ?>">Due Today</a>
                </div>
                <div>
                    <input type="text" class="search-input" placeholder="Search payee, check #..." data-table-search="#pendingChecksTable">
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <?php if (empty($pendingChecks)): ?>
                    <div class="empty-state">
                        No pending checks awaiting funding.
                        <p>Click "+ New Check" to record a check into the system.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="pendingChecksTable">
                            <thead>
                                <tr>
                                    <th style="width: 36px; text-align: center;">
                                        <input type="checkbox" id="selectAllChecks" class="custom-checkbox">
                                    </th>
                                    <th>Check Date</th>
                                    <th>Check # &amp; Bank</th>
                                    <th>Payee / Supplier</th>
                                    <th>Project / Site</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingChecks as $chk): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <input type="checkbox" class="custom-checkbox check-select-item" value="<?= $chk['id'] ?>" data-amount="<?= $chk['amount'] ?>">
                                        </td>
                                        <td style="font-weight: 600;"><?= format_date($chk['check_date']) ?></td>
                                        <td>
                                            <span class="mono"><?= e($chk['check_number']) ?></span><br>
                                            <small style="color: var(--text-muted);"><?= e($chk['bank_name']) ?></small>
                                        </td>
                                        <td><?= e($chk['payee']) ?></td>
                                        <td style="color: var(--text-muted);"><?= e($chk['project_name']) ?></td>
                                        <td class="amount-col"><?= format_money($chk['amount']) ?></td>
                                        <td class="status-cell" style="text-align: center;">
                                            <span class="badge badge-pending">Pending</span>
                                        </td>
                                        <td class="action-cell" style="text-align: center;">
                                            <button class="btn btn-primary btn-sm btn-ajax-fund"
                                                    data-check-id="<?= $chk['id'] ?>"
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
    </div>

    <!-- TAB 2: Monthly Release Report -->
    <div class="tab-pane <?= $activeTab === 'monthly' ? 'active' : '' ?>" id="tab-monthly">
        <?php if (empty($checksByMonth)): ?>
            <div class="card">
                <div class="empty-state">
                    No checks recorded yet.
                    <p>Encoded checks will appear here grouped by month.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($checksByMonth as $monthLabel => $group): ?>
                <div class="month-block">
                    <div class="month-block-header">
                        <span class="month-block-title"><?= e($monthLabel) ?></span>
                        <span style="font-weight: 600; font-family: var(--font-mono); font-size: 0.95rem;">
                            Total: <?= format_money($group['total']) ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check #</th>
                                    <th>Bank</th>
                                    <th>Payee / Supplier</th>
                                    <th>Project</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center;">Funding Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['checks'] as $chk): ?>
                                    <tr>
                                        <td><?= format_date($chk['check_date']) ?></td>
                                        <td class="mono"><?= e($chk['check_number']) ?></td>
                                        <td><?= e($chk['bank_name']) ?></td>
                                        <td><?= e($chk['payee']) ?></td>
                                        <td style="color: var(--text-muted);"><?= e($chk['project_name']) ?></td>
                                        <td class="amount-col"><?= format_money($chk['amount']) ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($chk['status'] === 'funded'): ?>
                                                <span class="badge badge-funded">Funded</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- TAB 3: All Checks -->
    <div class="tab-pane <?= $activeTab === 'all' ? 'active' : '' ?>" id="tab-all">
        <div class="card">
            <div class="card-header no-print">
                <div>
                    <input type="text" class="search-input" placeholder="Search all checks..." data-table-search="#allChecksTable">
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($allChecks)): ?>
                    <div class="empty-state">No checks found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="allChecksTable">
                            <thead>
                                <tr>
                                    <th>Check Date</th>
                                    <th>Check #</th>
                                    <th>Bank</th>
                                    <th>Payee</th>
                                    <th>Project</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;" class="no-print">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allChecks as $chk): ?>
                                    <tr>
                                        <td><?= format_date($chk['check_date']) ?></td>
                                        <td class="mono"><?= e($chk['check_number']) ?></td>
                                        <td><?= e($chk['bank_name']) ?></td>
                                        <td><?= e($chk['payee']) ?></td>
                                        <td style="color: var(--text-muted);"><?= e($chk['project_name']) ?></td>
                                        <td class="amount-col"><?= format_money($chk['amount']) ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($chk['status'] === 'funded'): ?>
                                                <span class="badge badge-funded">Funded</span>
                                            <?php elseif ($chk['status'] === 'cancelled'): ?>
                                                <span class="badge badge-cancelled">Cancelled</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;" class="no-print">
                                            <?php if ($chk['status'] === 'pending'): ?>
                                                <form action="api/actions.php" method="POST" style="display:inline;" onsubmit="return confirm('Cancel check?');">
                                                    <input type="hidden" name="action" value="cancel_check">
                                                    <input type="hidden" name="check_id" value="<?= $chk['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Void</button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
