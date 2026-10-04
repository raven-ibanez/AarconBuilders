<?php
/**
 * Aarcon Builders - Module 2: Physical Cash Requests (Petty Cash)
 */

$pageTitle = 'Cash Requests';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

$activeTab = $_GET['tab'] ?? 'pending';

$pendingRequests = $db->query("SELECT * FROM cash_requests WHERE status = 'pending' ORDER BY request_date DESC, id DESC")->fetchAll();
$approvedRequests = $db->query("SELECT * FROM cash_requests WHERE status IN ('approved', 'disbursed') ORDER BY approved_at DESC, id DESC")->fetchAll();
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Physical Cash Requests</h1>
        <p class="page-subtitle">Petty cash and site operational cash requests</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button class="btn btn-primary btn-sm" data-open-modal="modalAddCashRequest">+ New Cash Request</button>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print</button>
    </div>
</div>

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs-nav no-print">
        <button class="tab-btn <?= $activeTab === 'pending' ? 'active' : '' ?>" data-tab="tab-pending">
            Boss Approval Queue
            <?php if (count($pendingRequests) > 0): ?>
                <span class="badge badge-pending"><?= count($pendingRequests) ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab === 'approved' ? 'active' : '' ?>" data-tab="tab-approved">
            Approved Cash Log
        </button>
    </div>

    <!-- TAB 1: Pending Requests -->
    <div class="tab-pane <?= $activeTab === 'pending' ? 'active' : '' ?>" id="tab-pending">
        <div class="card">
            <div class="card-header no-print">
                <input type="text" class="search-input" placeholder="Search requests..." data-table-search="#pendingCashTable">
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($pendingRequests)): ?>
                    <div class="empty-state">
                        No pending cash requests.
                        <p>Click "+ New Cash Request" to submit an urgent petty cash need.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="pendingCashTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher #</th>
                                    <th>Requested By</th>
                                    <th>Project / Dept</th>
                                    <th>Purpose</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td><?= format_date($req['request_date']) ?></td>
                                        <td class="mono"><?= e($req['voucher_no']) ?></td>
                                        <td><?= e($req['requested_by']) ?></td>
                                        <td><?= e($req['department_or_project']) ?></td>
                                        <td><?= e($req['purpose']) ?></td>
                                        <td class="amount-col"><?= format_money($req['amount']) ?></td>
                                        <td class="action-cell" style="text-align: center;">
                                            <div style="display: flex; gap: 0.35rem; justify-content: center;">
                                                <button class="btn btn-success btn-sm btn-ajax-approve-cash"
                                                        data-request-id="<?= $req['id'] ?>"
                                                        data-voucher="<?= e($req['voucher_no']) ?>">
                                                    Approve
                                                </button>
                                                <form action="api/actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Disapprove request?');">
                                                    <input type="hidden" name="action" value="reject_cash_request">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </div>
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

    <!-- TAB 2: Approved Cash Log -->
    <div class="tab-pane <?= $activeTab === 'approved' ? 'active' : '' ?>" id="tab-approved">
        <div class="card">
            <div class="card-header no-print">
                <input type="text" class="search-input" placeholder="Search approved vouchers..." data-table-search="#approvedCashTable">
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($approvedRequests)): ?>
                    <div class="empty-state">No approved cash expenses yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" id="approvedCashTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher #</th>
                                    <th>Requested By</th>
                                    <th>Project</th>
                                    <th>Purpose</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th>Approved By</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedRequests as $req): ?>
                                    <tr>
                                        <td><?= format_date($req['request_date']) ?></td>
                                        <td class="mono"><?= e($req['voucher_no']) ?></td>
                                        <td><?= e($req['requested_by']) ?></td>
                                        <td><?= e($req['department_or_project']) ?></td>
                                        <td><?= e($req['purpose']) ?></td>
                                        <td class="amount-col"><?= format_money($req['amount']) ?></td>
                                        <td><?= e($req['approved_by']) ?></td>
                                        <td style="text-align: center;">
                                            <span class="badge badge-approved">Approved</span>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
