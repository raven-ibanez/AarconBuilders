<?php
/**
 * Aarcon Builders - Automated Comprehensive Verification Suite
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$db = get_db();
$passed = 0;
$failed = 0;

function assert_test($condition, $name) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $name\n";
        $passed++;
    } else {
        echo " [FAIL] $name\n";
        $failed++;
    }
}

echo "=======================================================\n";
echo "   AARCON BUILDERS VERIFICATION TEST SUITE\n";
echo "=======================================================\n\n";

// --- 1. Database Schema & Columns Verification ---
echo "--- 1. Testing Database & Schema Updates ---\n";
$checkCols = array_column($db->query("PRAGMA table_info(checks)")->fetchAll(), 'name');
assert_test(in_array('issue_date', $checkCols), "checks table has issue_date column");
assert_test(in_array('project_district', $checkCols), "checks table has project_district column");
assert_test(in_array('voucher_number', $checkCols), "checks table has voucher_number column");
assert_test(in_array('priority_status', $checkCols), "checks table has priority_status column");
assert_test(in_array('check_type', $checkCols), "checks table has check_type column");

$cashCols = array_column($db->query("PRAGMA table_info(cash_requests)")->fetchAll(), 'name');
assert_test(in_array('project_district', $cashCols), "cash_requests table has project_district column");

$colCols = array_column($db->query("PRAGMA table_info(collections)")->fetchAll(), 'name');
assert_test(in_array('contract_id', $colCols), "collections table has contract_id column");
assert_test(in_array('net_amount', $colCols), "collections table has net_amount column");
assert_test(in_array('project_district', $colCols), "collections table has project_district column");
assert_test(in_array('contract_amount', $colCols), "collections table has contract_amount column");
assert_test(in_array('percentage_collected', $colCols), "collections table has percentage_collected column");
assert_test(in_array('balance_collectible', $colCols), "collections table has balance_collectible column");
assert_test(in_array('expected_collection_date', $colCols), "collections table has expected_collection_date column");
assert_test(in_array('deduction', $colCols), "collections table has deduction column");
assert_test(in_array('net_collections', $colCols), "collections table has net_collections column");
assert_test(in_array('billing_on_process', $colCols), "collections table has billing_on_process column");
assert_test(in_array('status', $colCols), "collections table has status column");

// --- 2. Bank Check & Routing Code Logic ---
echo "\n--- 2. Testing Bank Check Account Number & District Logic ---\n";
$districts = get_project_districts();
assert_test(isset($districts['DPWH-RIZAL-2nd']), "Project district DPWH-RIZAL-2nd exists in district dictionary");

$branches = get_bank_branches();
assert_test(!empty($branches) && isset($branches[0]['code']) && strlen($branches[0]['code']) === 3, "Bank branches have 3-digit identifying codes (e.g. {$branches[0]['code']})");

// --- 3. Dated vs. Post-Dated Check Categorization ---
echo "\n--- 3. Testing Dated vs. Post-Dated Check Categorization ---\n";
$datedTag = get_check_maturity_tag('2026-10-20', '2026-10-20');
assert_test($datedTag === 'Dated Check', "Same date (issue == check_date) categorized as 'Dated Check'");

$pdcTag = get_check_maturity_tag('2026-10-20', '2026-11-20');
assert_test($pdcTag === 'Post-Dated Check', "Future maturity (check_date > issue) categorized as 'Post-Dated Check'");

$pendingDateTag = get_check_maturity_tag('2026-10-20', null);
assert_test($pendingDateTag === 'Pending Date', "Unknown / empty maturity date categorized as 'Pending Date'");

// --- 4. Financial Computations (Pure Net, Amount Collected & Balance = Net - Collected) ---
echo "\n--- 4. Testing Financial Computations ---\n";
// Contract: 1,000,000, Net: 900,000, Collected: 600,000
$contract = 1000000.0;
$net = 900000.0;
$collected = 600000.0;

$percent = compute_percentage_collected($contract, $collected);
assert_test($percent === 60.0, "Percentage Collected = ($collected / $contract) * 100 = $percent%");

// Balance Collectible = Net Amount - Amount Collected
$balance = compute_balance_collectible($net, $collected);
assert_test($balance === 300000.0, "Balance Collectible = Net ($net) - Collected ($collected) = $balance");

$balancePercent = compute_percentage_balance($contract, $balance);
assert_test($balancePercent === 30.0, "Percentage Balance = ($balance / $contract) * 100 = $balancePercent%");

// --- 5. User Roles & RBAC ---
echo "\n--- 5. Testing Outsourced Accountant User & RBAC ---\n";
$outsourcedUser = $db->query("SELECT * FROM users WHERE role = 'outsourced_accountant' LIMIT 1")->fetch();
assert_test(!empty($outsourcedUser), "Outsourced Accountant account exists in database (User: {$outsourcedUser['username']})");

$_SESSION['user'] = $outsourcedUser;
assert_test(is_outsourced_accountant(), "is_outsourced_accountant() returns true for outsourced role");
assert_test(is_accountant(), "is_accountant() permits outsourced accountant for general financial access");
assert_test(!is_admin(), "is_admin() strictly prevents admin access for outsourced accountant");
assert_test(get_user_role_label('outsourced_accountant') === 'Outsourced Accountant', "Role label returns 'Outsourced Accountant'");

// --- 6. Workflow Validation: Adding Check & Funding with Cheque Date ---
echo "\n--- 6. Testing Cheque Processing & Funding Workflow Logic ---\n";
// Create test check with priority = 'Prioritized', district = 'DPWH-RIZAL-2nd', voucher = 'PCV-TEST-001'
$testCheckNum = 'TEST-' . time();
$insStmt = $db->prepare("INSERT INTO checks (check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, voucher_number, priority_status, check_type, status) VALUES (?, 'BDO Unibank (Ortigas)', '001-99887766', 'Test Supplier Ltd', 50000.00, '2026-10-20', '2026-11-20', 'Materials', 'Pasig Bridge Rehab', 'DPWH-RIZAL-2nd', 'PCV-TEST-001', 'Prioritized', 'Post-Dated Check', 'pending')");
$insStmt->execute([$testCheckNum]);
$testCheckId = (int)$db->lastInsertId();

$savedCheck = $db->query("SELECT * FROM checks WHERE id = {$testCheckId}")->fetch();
assert_test($savedCheck['priority_status'] === 'Prioritized', "Check saved with Priority Status = 'Prioritized'");
assert_test($savedCheck['project_district'] === 'DPWH-RIZAL-2nd', "Check saved with District = 'DPWH-RIZAL-2nd'");
assert_test($savedCheck['voucher_number'] === 'PCV-TEST-001', "Check linked to voucher 'PCV-TEST-001'");
assert_test($savedCheck['check_type'] === 'Post-Dated Check', "Check tagged as Post-Dated Check");

// Test Funding Workflow: Must provide cheque_date
$fundChequeDate = '2026-11-25';
$fundStmt = $db->prepare("UPDATE checks SET status = 'funded', check_date = ?, funded_at = datetime('now'), funded_by = 'Engr. Boss' WHERE id = ?");
$fundStmt->execute([$fundChequeDate, $testCheckId]);

$fundedCheck = $db->query("SELECT * FROM checks WHERE id = {$testCheckId}")->fetch();
assert_test($fundedCheck['status'] === 'funded', "Check status updated to 'funded'");
assert_test($fundedCheck['check_date'] === $fundChequeDate, "Cheque date successfully confirmed/updated to $fundChequeDate upon funding");

// Clean up test check
$db->exec("DELETE FROM checks WHERE id = {$testCheckId}");

// --- 7. Cash Requests & Collections DB Insertion Verification ---
echo "\n--- 7. Testing Cash Requests & Collections Tables ---\n";
$testVoucher = 'PCV-UNIT-' . rand(1000, 9999);
$crStmt = $db->prepare("INSERT INTO cash_requests (voucher_no, requested_by, department_or_project, project_district, amount, purpose, request_date, status) VALUES (?, 'Site Engineer', 'Cainta Bypass', 'DPWH-RIZAL-2nd', 15000.00, 'Site fuel and gravel transport', '2026-10-20', 'pending')");
$crStmt->execute([$testVoucher]);
$crId = (int)$db->lastInsertId();

$savedCr = $db->query("SELECT * FROM cash_requests WHERE id = {$crId}")->fetch();
assert_test($savedCr['project_district'] === 'DPWH-RIZAL-2nd', "Cash Request saved with District = 'DPWH-RIZAL-2nd'");
$db->exec("DELETE FROM cash_requests WHERE id = {$crId}");

$testOR = 'OR-UNIT-' . rand(1000, 9999);
$testContractId = 'CNT-TEST-' . rand(100, 999);
$colStmt = $db->prepare("INSERT INTO collections (contract_id, receipt_no, client_name, project_name, project_district, contract_amount, net_amount, percentage_collected, balance_collectible, amount, expected_collection_date, deduction, net_collections, billing_on_process, payment_method, collection_date, status) VALUES (?, ?, 'DPWH Regional Office', 'Marikina Flood Gate', 'DPWH-NCR-2nd', 500000.00, 480000.00, 50.0, 230000.00, 250000.00, '2026-10-30', 5000.00, 245000.00, 12000.00, 'Bank Deposit', '2026-10-20', 'collected')");
$colStmt->execute([$testContractId, $testOR]);
$colId = (int)$db->lastInsertId();

$savedCol = $db->query("SELECT * FROM collections WHERE id = {$colId}")->fetch();
assert_test($savedCol['contract_id'] === $testContractId, "Collection saved with non-optional Contract ID = '{$testContractId}'");
assert_test($savedCol['status'] === 'collected', "Collection saved with status = 'collected'");
assert_test($savedCol['project_district'] === 'DPWH-NCR-2nd', "Collection saved with District = 'DPWH-NCR-2nd'");
assert_test((float)$savedCol['balance_collectible'] === 230000.00, "Collection saved with computed Balance Collectible (Net - Collected) = 230,000.00");
assert_test($savedCol['expected_collection_date'] === '2026-10-30', "Collection saved with Expected Date = '2026-10-30'");
assert_test((float)$savedCol['deduction'] === 5000.00, "Collection saved with Deduction = 5,000.00");
assert_test((float)$savedCol['net_collections'] === 245000.00, "Collection saved with Net Collections = 245,000.00");
assert_test((float)$savedCol['billing_on_process'] === 12000.00, "Collection saved with Billing on Process = 12,000.00");
$db->exec("DELETE FROM collections WHERE id = {$colId}");

// --- 8. Unified Check & Voucher Creation (Voucher Module = Create New Check) ---
echo "\n--- 8. Testing Unified Check & Voucher Module Logic ---\n";
$nextVoucher = get_next_voucher_number();
assert_test(strpos($nextVoucher, 'CV-' . date('Y') . '-') === 0, "get_next_voucher_number() generates valid format: $nextVoucher");

$words = number_to_words_pesos(150000.50);
assert_test(strpos($words, 'ONE HUNDRED FIFTY THOUSAND PESOS') !== false && strpos($words, '50/100') !== false, "number_to_words_pesos(150000.50) correctly spells out: $words");

// Simulate creating a voucher with initial is_reviewed = 0
$testVoucherCheckNum = 'VCH-CHK-' . rand(1000, 9999);
$autoVoucher = get_next_voucher_number();
$insVoucherStmt = $db->prepare("INSERT INTO checks (check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, voucher_number, priority_status, check_type, status, is_reviewed) VALUES (?, 'BPI (Emerald Ortigas)', '003-11223344', 'Concrete Works Corp', 75000.00, '2026-10-20', '2026-10-20', 'Materials', 'Ortigas Tower', 'DPWH-NCR-1st', ?, 'Standard', 'Dated Check', 'pending', 0)");
$insVoucherStmt->execute([$testVoucherCheckNum, $autoVoucher]);
$voucherCheckId = (int)$db->lastInsertId();

$savedVoucherCheck = $db->query("SELECT * FROM checks WHERE id = {$voucherCheckId}")->fetch();
assert_test(!empty($savedVoucherCheck['voucher_number']), "Check created via Voucher module has voucher_number: {$savedVoucherCheck['voucher_number']}");
assert_test($savedVoucherCheck['check_number'] === $testVoucherCheckNum, "Check created via Voucher module matches check_number: {$testVoucherCheckNum}");
assert_test((int)$savedVoucherCheck['is_reviewed'] === 0, "New voucher created with is_reviewed = 0 (Pending Review)");

// Verify unreviewed voucher is NOT visible in checks pending funding queue
$visibleInChecks = (int)$db->query("SELECT COUNT(*) FROM checks WHERE id = {$voucherCheckId} AND (is_reviewed = 1 OR is_reviewed IS NULL)")->fetchColumn();
assert_test($visibleInChecks === 0, "Unreviewed voucher is hidden from Check module funding queue");

// Simulate marking voucher as reviewed (review_voucher action)
$db->exec("UPDATE checks SET is_reviewed = 1 WHERE id = {$voucherCheckId}");
$visibleAfterReview = (int)$db->query("SELECT COUNT(*) FROM checks WHERE id = {$voucherCheckId} AND (is_reviewed = 1 OR is_reviewed IS NULL)")->fetchColumn();
assert_test($visibleAfterReview === 1, "After clicking 'Reviewed', voucher is thrown to the Check module queue");
$db->exec("DELETE FROM checks WHERE id = {$voucherCheckId}");

// --- 9. Testing Collection Editing & Status Modification ---
echo "\n--- 9. Testing Collection Editing & Status Modification ---\n";
$testOR2 = 'OR-EDIT-' . rand(1000, 9999);
$testContractId2 = 'CNT-INIT-' . rand(100, 999);
$insColStmt = $db->prepare("INSERT INTO collections (contract_id, receipt_no, client_name, project_name, project_district, contract_amount, net_amount, percentage_collected, balance_collectible, amount, payment_method, collection_date, status) VALUES (?, ?, 'Original Client', 'Original Project', 'DPWH-RIZAL-1st', 100000.00, 90000.00, 50.0, 40000.00, 50000.00, 'Cash', '2026-10-20', 'in process')");
$insColStmt->execute([$testContractId2, $testOR2]);
$editColId = (int)$db->lastInsertId();

$origCol = $db->query("SELECT * FROM collections WHERE id = {$editColId}")->fetch();
assert_test($origCol['status'] === 'in process', "Initial collection status is 'in process'");
assert_test($origCol['contract_id'] === $testContractId2, "Initial collection has contract_id = '{$testContractId2}'");

// Simulate edit_collection action: updating fields with contract_id, net_amount, and changing status to 'collected'
$newContractId = 'CNT-UPDATED-888';
$newContract   = 200000.00;
$newNet        = 180000.00;
$newAmount     = 150000.00;
$newPct        = compute_percentage_collected($newContract, $newAmount);
$newBal        = compute_balance_collectible($newNet, $newAmount); // Net (180k) - Collected (150k) = 30k

$updateColStmt = $db->prepare('UPDATE collections SET contract_id = ?, receipt_no = ?, client_name = ?, project_name = ?, project_district = ?, contract_amount = ?, net_amount = ?, percentage_collected = ?, balance_collectible = ?, amount = ?, expected_collection_date = ?, deduction = ?, net_collections = ?, billing_on_process = ?, payment_method = ?, collection_date = ?, reference_no = ?, received_by = ?, notes = ?, status = ? WHERE id = ?');
$updateColStmt->execute([
    $newContractId, 'OR-EDITED-9999', 'Updated Client Corp', 'Updated Bridge Project', 'DPWH-RIZAL-2nd',
    $newContract, $newNet, $newPct, $newBal, $newAmount,
    '2026-11-15', 7500.00, 142500.00, 25000.00,
    'Bank Deposit', '2026-10-21', 'DEP-REF-12345', 'Auditor Jane', 'Edited particulars', 'collected',
    $editColId
]);

$updatedCol = $db->query("SELECT * FROM collections WHERE id = {$editColId}")->fetch();
assert_test($updatedCol['contract_id'] === $newContractId, "Contract ID updated to '{$newContractId}'");
assert_test($updatedCol['receipt_no'] === 'OR-EDITED-9999', "Receipt number updated to 'OR-EDITED-9999'");
assert_test($updatedCol['status'] === 'collected', "Collection status successfully edited to 'collected'");
assert_test($updatedCol['project_district'] === 'DPWH-RIZAL-2nd', "Project district updated to 'DPWH-RIZAL-2nd'");
assert_test((float)$updatedCol['contract_amount'] === 200000.00, "Contract amount updated to 200,000.00");
assert_test((float)$updatedCol['net_amount'] === 180000.00, "Net amount updated to 180,000.00");
assert_test((float)$updatedCol['amount'] === 150000.00, "Amount collected updated to 150,000.00");
assert_test((float)$updatedCol['balance_collectible'] === 30000.00, "Balance collectible recomputed (Net 180k - Collected 150k = 30k)");
assert_test((float)$updatedCol['percentage_collected'] === 75.00, "Percentage collected recomputed to 75.00%");
assert_test($updatedCol['expected_collection_date'] === '2026-11-15', "Expected date updated to '2026-11-15'");
assert_test((float)$updatedCol['deduction'] === 7500.00, "Deduction updated to 7,500.00");
assert_test((float)$updatedCol['net_collections'] === 142500.00, "Net collections updated to 142,500.00");
assert_test((float)$updatedCol['billing_on_process'] === 25000.00, "Billing on process updated to 25,000.00");

// Clean up
$db->exec("DELETE FROM collections WHERE id = {$editColId}");

// --- 10. Testing Disbursement Report Modifiability & Category Filters ---
echo "\n--- 10. Testing Disbursement Report Editing & Category Filters ---\n";
// Test Check Editing
$testCheckForReport = 'CHK-REP-' . rand(1000, 9999);
$insChkStmt = $db->prepare("INSERT INTO checks (check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, priority_status, status) VALUES (?, 'BDO (Ortigas)', '001-998877', 'Original Vendor', 25000.00, '2026-10-20', '2026-10-25', 'Materials', 'Test Tower', 'DPWH-NCR-1st', 'Standard', 'funded')");
$insChkStmt->execute([$testCheckForReport]);
$repCheckId = (int)$db->lastInsertId();

$editChkStmt = $db->prepare("UPDATE checks SET payee = 'Updated Vendor Corp', amount = 30000.00, priority_status = 'Prioritized' WHERE id = ?");
$editChkStmt->execute([$repCheckId]);
$updatedChk = $db->query("SELECT * FROM checks WHERE id = {$repCheckId}")->fetch();
assert_test($updatedChk['payee'] === 'Updated Vendor Corp', "Disbursement check payee updated to 'Updated Vendor Corp'");
assert_test((float)$updatedChk['amount'] === 30000.00, "Disbursement check amount updated to 30,000.00");
assert_test($updatedChk['priority_status'] === 'Prioritized', "Disbursement check priority updated to 'Prioritized'");
$db->exec("DELETE FROM checks WHERE id = {$repCheckId}");

// Test Cash Expense Editing
$db->exec("DELETE FROM cash_requests WHERE voucher_no LIKE 'PCV-REP-%'");
$testCashForReport = 'PCV-REP-' . uniqid();
$insCashStmt = $db->prepare("INSERT INTO cash_requests (voucher_no, requested_by, department_or_project, project_district, amount, purpose, request_date, status) VALUES (?, 'Worker Dan', 'Site Electrical', 'DPWH-RIZAL-1st', 5000.00, 'Wires and conduits', '2026-10-20', 'approved')");
$insCashStmt->execute([$testCashForReport]);
$repCashId = (int)$db->lastInsertId();

$editCashStmt = $db->prepare("UPDATE cash_requests SET requested_by = 'Lead Engr Dan', amount = 6500.00, purpose = 'Heavy duty industrial cables' WHERE id = ?");
$editCashStmt->execute([$repCashId]);
$updatedCash = $db->query("SELECT * FROM cash_requests WHERE id = {$repCashId}")->fetch();
assert_test($updatedCash['requested_by'] === 'Lead Engr Dan', "Cash expense requester updated to 'Lead Engr Dan'");
assert_test((float)$updatedCash['amount'] === 6500.00, "Cash expense amount updated to 6,500.00");
assert_test($updatedCash['purpose'] === 'Heavy duty industrial cables', "Cash expense purpose updated successfully");
$db->exec("DELETE FROM cash_requests WHERE id = {$repCashId}");
// --- 11. Testing Issue Date & Check Date Filtering Logic ---
echo "\n--- 11. Testing Issue Date & Check Date Filters ---\n";
$filterCheck1 = 'CHK-FIL1-' . rand(1000, 9999);
$filterCheck2 = 'CHK-FIL2-' . rand(1000, 9999);

// Check 1: Issue 2026-05-01, Maturity 2026-06-15
$db->prepare("INSERT INTO checks (check_number, bank_name, payee, amount, issue_date, check_date, status) VALUES (?, 'BDO', 'Vendor A', 10000, '2026-05-01', '2026-06-15', 'pending')")->execute([$filterCheck1]);
$c1Id = (int)$db->lastInsertId();

// Check 2: Issue 2026-07-01, Maturity 2026-08-20
$db->prepare("INSERT INTO checks (check_number, bank_name, payee, amount, issue_date, check_date, status) VALUES (?, 'BPI', 'Vendor B', 20000, '2026-07-01', '2026-08-20', 'pending')")->execute([$filterCheck2]);
$c2Id = (int)$db->lastInsertId();

// Test filtering by Issue Date (2026-05)
$resIssue = $db->query("SELECT id FROM checks WHERE issue_date BETWEEN '2026-05-01' AND '2026-05-31' AND id IN ({$c1Id}, {$c2Id})")->fetchAll();
assert_test(count($resIssue) === 1 && (int)$resIssue[0]['id'] === $c1Id, "Issue date filter correctly matches only Check 1");

// Test filtering by Check Date / Maturity Date (2026-08)
$resMaturity = $db->query("SELECT id FROM checks WHERE check_date BETWEEN '2026-08-01' AND '2026-08-31' AND id IN ({$c1Id}, {$c2Id})")->fetchAll();
assert_test(count($resMaturity) === 1 && (int)$resMaturity[0]['id'] === $c2Id, "Check date (maturity) filter correctly matches only Check 2");

// Test filtering by both simultaneously
$resBoth = $db->query("SELECT id FROM checks WHERE issue_date = '2026-05-01' AND check_date = '2026-06-15' AND id IN ({$c1Id}, {$c2Id})")->fetchAll();
assert_test(count($resBoth) === 1 && (int)$resBoth[0]['id'] === $c1Id, "Simultaneous Issue Date and Check Date filter query works correctly");

$db->exec("DELETE FROM checks WHERE id IN ({$c1Id}, {$c2Id})");

// --- 12. Testing Admin CMS: Bank Routing Codes, Dropdowns & Custom Fields ---
echo "\n--- 12. Testing Admin CMS (Banks, Dropdowns & Custom Fields) ---\n";

// A. Test Bank Starting Code Customization (User Request: "if the bank is bdo the starting code could change")
$db->exec("UPDATE system_banks SET code = '099' WHERE bank_name LIKE '%BDO%' AND code = '001'");
$updatedBranches = get_bank_branches();
$bdoBranch = null;
foreach ($updatedBranches as $ub) {
    if (strpos($ub['bank'], 'BDO') !== false && $ub['code'] === '099') {
        $bdoBranch = $ub;
        break;
    }
}
assert_test($bdoBranch !== null && $bdoBranch['code'] === '099', "Admin CMS can change BDO starting code from '001' to '099'");

// Revert BDO code back to 001 for consistency
$db->exec("UPDATE system_banks SET code = '001' WHERE bank_name LIKE '%BDO%' AND code = '099'");
$revertedBranches = get_bank_branches();
$bdoReverted = false;
foreach ($revertedBranches as $rb) {
    if (strpos($rb['bank'], 'BDO') !== false && $rb['code'] === '001') {
        $bdoReverted = true;
        break;
    }
}
assert_test($bdoReverted === true, "BDO starting code successfully restored to '001'");

// B. Test Dropdown Choices Customization
$testDistrictKey = 'DPWH-CAVITE-TEST';
$db->exec("INSERT INTO system_dropdowns (category, item_key, item_label, sort_order, status) VALUES ('project_districts', '{$testDistrictKey}', 'DPWH - Cavite Test District', 99, 'active')");
$districtsAfterCms = get_project_districts();
assert_test(isset($districtsAfterCms[$testDistrictKey]), "Admin CMS can add new dropdown choice ({$testDistrictKey}) which immediately reflects in get_project_districts()");
$db->exec("DELETE FROM system_dropdowns WHERE item_key = '{$testDistrictKey}'");

// C. Test Dynamic Custom Fields Engine
$testFieldKey = 'test_po_ref';
$db->exec("INSERT INTO custom_fields (module, field_key, field_label, field_type, field_options, placeholder, is_required, sort_order, status) VALUES ('checks', '{$testFieldKey}', 'Test Purchase Order', 'text', '', 'PO-TEST', 1, 99, 'active')");
$checkFields = get_custom_fields_for_module('checks');
$foundTestField = false;
foreach ($checkFields as $cf) {
    if ($cf['field_key'] === $testFieldKey) {
        $foundTestField = true;
        break;
    }
}
assert_test($foundTestField === true, "Admin CMS can define dynamic custom field ({$testFieldKey}) on Checks module");

$renderedHtml = render_custom_fields_html('checks', [$testFieldKey => 'PO-99999']);
assert_test(strpos($renderedHtml, 'Test Purchase Order') !== false && strpos($renderedHtml, 'PO-99999') !== false, "render_custom_fields_html dynamically outputs custom field input with value");
$db->exec("DELETE FROM custom_fields WHERE field_key = '{$testFieldKey}'");

echo "\n=======================================================\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "=======================================================\n";


