<?php
/**
 * Aarcon Builders - Module 3: Collections (Cash In)
 */

$pageTitle = 'Collections';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$db = get_db();

$collections = $db->query("SELECT * FROM collections ORDER BY collection_date DESC, id DESC")->fetchAll();
$totalCollections = array_sum(array_column($collections, 'amount'));
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Collections (Cash In)</h1>
        <p class="page-subtitle">Record client payments, progress billings, and cash deposits</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button class="btn btn-success btn-sm" data-open-modal="modalAddCollection">+ Record Collection</button>
        <button class="btn btn-outline btn-sm" onclick="window.print();">Print</button>
    </div>
</div>

<!-- Collections Table Card -->
<div class="card">
    <div class="card-header no-print">
        <input type="text" class="search-input" placeholder="Search client, OR #, project..." data-table-search="#collectionsTable">
        <div style="font-weight: 600; font-family: var(--font-mono); font-size: 0.95rem;">
            Total Inflow: <span style="color: var(--color-success);"><?= format_money($totalCollections) ?></span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($collections)): ?>
            <div class="empty-state">
                No collections recorded yet.
                <p>Click "+ Record Collection" to log incoming payments.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="collectionsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Receipt / OR #</th>
                            <th>Client / Customer</th>
                            <th>Project</th>
                            <th>Method</th>
                            <th>Ref #</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Notes</th>
                            <th style="text-align: center;" class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collections as $col): ?>
                            <tr>
                                <td><?= format_date($col['collection_date']) ?></td>
                                <td class="mono"><?= e($col['receipt_no']) ?></td>
                                <td style="font-weight: 600;"><?= e($col['client_name']) ?></td>
                                <td style="color: var(--text-muted);"><?= e($col['project_name']) ?></td>
                                <td><span class="badge badge-method"><?= e($col['payment_method']) ?></span></td>
                                <td class="mono" style="font-size: 0.82rem; color: var(--text-muted);"><?= e($col['reference_no']) ?: '-' ?></td>
                                <td class="amount-col" style="color: var(--color-success);">
                                    <?= format_money($col['amount']) ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?= e($col['notes']) ?: '-' ?></td>
                                <td style="text-align: center;" class="no-print">
                                    <form action="api/actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete collection entry?');">
                                        <input type="hidden" name="action" value="delete_collection">
                                        <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 6px;">&times;</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
