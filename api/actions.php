<?php
/**
 * Aarcon Builders - Action Handlers (API / POST)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db = get_db();
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
          || isset($_POST['ajax']);

// If JSON body received, merge into $_POST
$rawJson = file_get_contents('php://input');
if ($rawJson) {
    $jsonData = json_decode($rawJson, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
    }
}

$action = $_POST['action'] ?? '';
$redirectUrl = $_POST['redirect_url'] ?? $_SERVER['HTTP_REFERER'] ?? '../index.php';

function respond($success, $message, $data = [], $redirectUrl = null) {
    global $isAjax;
    if ($success) {
        set_flash('success', $message);
    } else {
        set_flash('danger', $message);
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $data));
        exit;
    } else {
        header('Location: ' . ($redirectUrl ?: '../index.php'));
        exit;
    }
}

try {
    switch ($action) {

        // ==========================================
        // MODULE 1: CHECK ACTIONS
        // ==========================================
        // ==========================================
        // MODULE 1: CHECK & VOUCHER ACTIONS
        // The add new check and add new voucher are literally the same;
        // The voucher module creates the check record with its voucher number.
        // ==========================================
        case 'add_voucher':
        case 'add_check':
            $checkNumber     = trim($_POST['check_number'] ?? '');
            $bankName        = trim($_POST['bank_name'] ?? '');
            $accountNo       = trim($_POST['account_number'] ?? '');
            $payee           = trim($_POST['payee'] ?? '');
            $amount          = floatval($_POST['amount'] ?? 0);
            $issueDate       = trim($_POST['issue_date'] ?? '');
            $checkDate       = trim($_POST['check_date'] ?? '');
            $category        = trim($_POST['category'] ?? 'Materials');
            $projectName     = trim($_POST['project_name'] ?? 'General Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $voucherNumber   = trim($_POST['voucher_number'] ?? '');
            $priorityStatus  = trim($_POST['priority_status'] ?? 'Standard');
            $remarks         = trim($_POST['remarks'] ?? '');

            // Validate priority_status
            if (!in_array($priorityStatus, ['Standard', 'Prioritized', 'Hold'])) {
                $priorityStatus = 'Standard';
            }

            // Enforce Issue Date as REQUIRED data
            if (empty($issueDate)) {
                respond(false, 'Validation Error: Issue Date is required and cannot be empty.', [], $redirectUrl);
            }

            if (empty($checkNumber) || empty($bankName) || empty($payee) || $amount <= 0) {
                respond(false, 'Please fill in all required fields (Check #, Bank, Payee, Amount, and Issue Date).', [], $redirectUrl);
            }

            // Auto-assign voucher number if empty
            if (empty($voucherNumber)) {
                $voucherNumber = get_next_voucher_number();
            }

            // Check if check_number already exists
            $stmt = $db->prepare('SELECT COUNT(*) FROM checks WHERE check_number = ?');
            $stmt->execute([$checkNumber]);
            if ($stmt->fetchColumn() > 0) {
                respond(false, "Check Number '{$checkNumber}' is already registered in the system.", [], $redirectUrl);
            }

            // Tag check automatically as Dated vs Post-Dated Check or Pending Date
            $checkType = get_check_maturity_tag($issueDate, $checkDate);

            // If created via Voucher module (or standard new voucher), set is_reviewed = 0 (Unreviewed / Draft)
            // It will be sent to the Check module only when "Reviewed" is clicked.
            $isReviewed = 0;

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            // If check date is empty, store null or empty string
            $stmt = $db->prepare('INSERT INTO checks (check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, project_district, voucher_number, priority_status, check_type, status, remarks, is_reviewed, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?)');
            $stmt->execute([$checkNumber, $bankName, $accountNo, $payee, $amount, $issueDate, !empty($checkDate) ? $checkDate : null, $category, $projectName, $projectDistrict, $voucherNumber, $priorityStatus, $checkType, $remarks, $isReviewed, $customFieldsJson]);

            $lastId = $db->lastInsertId();
            $msg = "Voucher {$voucherNumber} (Check #{$checkNumber}) for {$payee} saved successfully. Ready for review and printing CDV.";
            respond(true, $msg, ['id' => $lastId, 'voucher_number' => $voucherNumber, 'check_number' => $checkNumber], $redirectUrl);
            break;

        case 'review_voucher':
            $voucherId = intval($_POST['voucher_id'] ?? $_POST['check_id'] ?? 0);
            if ($voucherId <= 0) {
                respond(false, 'Invalid voucher selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('SELECT * FROM checks WHERE id = ?');
            $stmt->execute([$voucherId]);
            $chk = $stmt->fetch();

            if (!$chk) {
                respond(false, 'Voucher / check record not found.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE checks SET is_reviewed = 1 WHERE id = ?');
            $stmt->execute([$voucherId]);

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("REVIEW", "VOUCHERS", ?, ?)');
            $log->execute([$voucherId, "Voucher {$chk['voucher_number']} (Check #{$chk['check_number']}) marked as REVIEWED and routed to Check Management queue"]);

            $vLabel = !empty($chk['voucher_number']) ? $chk['voucher_number'] : "Check #{$chk['check_number']}";
            respond(true, "Voucher {$vLabel} marked as REVIEWED and successfully sent to the Check module!", ['id' => $voucherId], $redirectUrl);
            break;

        case 'fund_check':
            $checkId    = intval($_POST['check_id'] ?? 0);
            $chequeDate = trim($_POST['cheque_date'] ?? $_POST['check_date'] ?? '');
            $fundedBy   = trim($_POST['funded_by'] ?? DEFAULT_APPROVER);
            $priority   = trim($_POST['priority_status'] ?? '');

            if ($checkId <= 0) {
                respond(false, 'Invalid check selected.', [], $redirectUrl);
            }

            // Trigger requirement: Must input / confirm cheque_date before status changes to Funded
            if (empty($chequeDate)) {
                respond(false, 'Cheque Date is mandatory before approving funding for this transaction.', [], $redirectUrl);
            }

            $stmt = $db->prepare('SELECT * FROM checks WHERE id = ?');
            $stmt->execute([$checkId]);
            $check = $stmt->fetch();

            if (!$check) {
                respond(false, 'Check not found.', [], $redirectUrl);
            }

            // Re-evaluate Dated vs Post-Dated tag based on confirmed cheque_date & issue_date
            $checkType = get_check_maturity_tag($check['issue_date'], $chequeDate);
            $newPriority = in_array($priority, ['Standard', 'Prioritized', 'Hold']) ? $priority : ($check['priority_status'] ?? 'Standard');

            $stmt = $db->prepare('UPDATE checks SET status = "funded", check_date = ?, check_type = ?, priority_status = ?, funded_at = datetime("now"), funded_by = ? WHERE id = ?');
            $stmt->execute([$chequeDate, $checkType, $newPriority, $fundedBy, $checkId]);

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("FUND", "CHECKS", ?, ?)');
            $log->execute([$checkId, "Check #{$check['check_number']} ({$check['payee']}) of " . format_money($check['amount']) . " funded on {$chequeDate} by {$fundedBy}"]);

            respond(true, "Check #{$check['check_number']} marked as FUNDED with date " . format_date($chequeDate) . " and routed to Disbursement Report!", ['check_id' => $checkId, 'amount' => $check['amount'], 'check_date' => $chequeDate], $redirectUrl);
            break;

        case 'update_check_date':
            $checkId    = intval($_POST['check_id'] ?? 0);
            $chequeDate = trim($_POST['check_date'] ?? $_POST['cheque_date'] ?? '');

            if ($checkId <= 0) {
                respond(false, 'Invalid check selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('SELECT * FROM checks WHERE id = ?');
            $stmt->execute([$checkId]);
            $check = $stmt->fetch();

            if (!$check) {
                respond(false, 'Check not found.', [], $redirectUrl);
            }

            $newDate = !empty($chequeDate) ? $chequeDate : null;
            $newType = get_check_maturity_tag($check['issue_date'], $newDate);

            $stmt = $db->prepare('UPDATE checks SET check_date = ?, check_type = ? WHERE id = ?');
            $stmt->execute([$newDate, $newType, $checkId]);

            $dateLabel = $newDate ? format_date($newDate) : 'Pending / Unknown Date';
            respond(true, "Check #{$check['check_number']} date updated to {$dateLabel} ({$newType}).", ['check_id' => $checkId, 'check_date' => $newDate, 'check_type' => $newType], $redirectUrl);
            break;

        case 'edit_check':
            $checkId         = intval($_POST['check_id'] ?? 0);
            $checkNumber     = trim($_POST['check_number'] ?? '');
            $bankName        = trim($_POST['bank_name'] ?? '');
            $accountNo       = trim($_POST['account_number'] ?? '');
            $payee           = trim($_POST['payee'] ?? '');
            $amount          = floatval($_POST['amount'] ?? 0);
            $issueDate       = trim($_POST['issue_date'] ?? '');
            $checkDate       = trim($_POST['check_date'] ?? '');
            $category        = trim($_POST['category'] ?? 'Materials');
            $projectName     = trim($_POST['project_name'] ?? 'General Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $voucherNumber   = trim($_POST['voucher_number'] ?? '');
            $priorityStatus  = trim($_POST['priority_status'] ?? 'Standard');
            $status          = trim($_POST['status'] ?? 'funded');
            $remarks         = trim($_POST['remarks'] ?? '');

            if ($checkId <= 0) {
                respond(false, 'Invalid check record selected.', [], $redirectUrl);
            }

            if (empty($issueDate)) {
                respond(false, 'Issue Date is required.', [], $redirectUrl);
            }

            if (empty($checkNumber) || empty($bankName) || empty($payee) || $amount <= 0) {
                respond(false, 'Please fill in check number, bank, payee, and amount.', [], $redirectUrl);
            }

            if (!in_array($priorityStatus, ['Standard', 'Prioritized', 'Hold'])) {
                $priorityStatus = 'Standard';
            }

            // Determine check type
            $checkType = get_check_maturity_tag($issueDate, !empty($checkDate) ? $checkDate : null);

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare('UPDATE checks SET check_number = ?, bank_name = ?, account_number = ?, payee = ?, amount = ?, issue_date = ?, check_date = ?, category = ?, project_name = ?, project_district = ?, voucher_number = ?, priority_status = ?, check_type = ?, status = ?, remarks = ?, custom_fields = ? WHERE id = ?');
            $stmt->execute([
                $checkNumber, $bankName, $accountNo, $payee, $amount, $issueDate,
                !empty($checkDate) ? $checkDate : null, $category, $projectName, $projectDistrict,
                $voucherNumber, $priorityStatus, $checkType, $status, $remarks, $customFieldsJson, $checkId
            ]);

            respond(true, "Check #{$checkNumber} for {$payee} updated successfully.", ['check_id' => $checkId], $redirectUrl);
            break;

        case 'update_check_priority':
            $checkId  = intval($_POST['check_id'] ?? 0);
            $priority = trim($_POST['priority_status'] ?? 'Standard');

            if ($checkId <= 0 || !in_array($priority, ['Standard', 'Prioritized', 'Hold'])) {
                respond(false, 'Invalid priority status.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE checks SET priority_status = ? WHERE id = ?');
            $stmt->execute([$priority, $checkId]);

            respond(true, "Check release priority updated to '{$priority}'.", ['priority_status' => $priority], $redirectUrl);
            break;

        case 'batch_fund_checks':
            $checkIds = $_POST['check_ids'] ?? [];
            if (!is_array($checkIds) && is_string($checkIds)) {
                $checkIds = explode(',', $checkIds);
            }
            $checkIds = array_filter(array_map('intval', $checkIds));

            if (empty($checkIds)) {
                respond(false, 'No checks were selected for funding.', [], $redirectUrl);
            }

            $fundedBy = trim($_POST['funded_by'] ?? DEFAULT_APPROVER);
            $placeholders = implode(',', array_fill(0, count($checkIds), '?'));
            
            $stmt = $db->prepare("UPDATE checks SET status = 'funded', funded_at = datetime('now'), funded_by = ? WHERE id IN ($placeholders) AND status = 'pending'");
            $params = array_merge([$fundedBy], $checkIds);
            $stmt->execute($params);
            $count = $stmt->rowCount();

            respond(true, "Successfully approved and funded {$count} check(s). They are now active in the Disbursement Report.", ['count' => $count], $redirectUrl);
            break;

        case 'cancel_check':
            $checkId = intval($_POST['check_id'] ?? 0);
            if ($checkId <= 0) {
                respond(false, 'Invalid check selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE checks SET status = "cancelled" WHERE id = ?');
            $stmt->execute([$checkId]);
            respond(true, 'Check has been cancelled/voided.', [], $redirectUrl);
            break;

        // ==========================================
        // MODULE 2: CASH REQUEST & VOUCHER ACTIONS
        // ==========================================
        case 'add_cash_request':
            $voucherNo       = trim($_POST['voucher_no'] ?? '');
            $requestedBy     = trim($_POST['requested_by'] ?? '');
            $deptProject     = trim($_POST['department_or_project'] ?? 'Site Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $amount          = floatval($_POST['amount'] ?? 0);
            $purpose         = trim($_POST['purpose'] ?? '');
            $requestDate     = trim($_POST['request_date'] ?? date('Y-m-d'));

            if (empty($voucherNo)) {
                $voucherNo = 'PCV-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            if (empty($requestedBy) || $amount <= 0 || empty($purpose)) {
                respond(false, 'Please fill in requester name, amount, and purpose of cash request.', [], $redirectUrl);
            }

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare('INSERT INTO cash_requests (voucher_no, requested_by, department_or_project, project_district, amount, purpose, request_date, status, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?)');
            $stmt->execute([$voucherNo, $requestedBy, $deptProject, $projectDistrict, $amount, $purpose, $requestDate, $customFieldsJson]);

            respond(true, "Cash Voucher {$voucherNo} for " . format_money($amount) . " submitted for Boss Approval.", ['voucher_no' => $voucherNo], $redirectUrl);
            break;

        case 'approve_cash_request':
            $requestId  = intval($_POST['request_id'] ?? 0);
            $approvedBy = trim($_POST['approved_by'] ?? DEFAULT_APPROVER);

            if ($requestId <= 0) {
                respond(false, 'Invalid cash request selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('SELECT * FROM cash_requests WHERE id = ?');
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();

            if (!$req) {
                respond(false, 'Cash request not found.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE cash_requests SET status = "approved", approved_at = datetime("now"), approved_by = ? WHERE id = ?');
            $stmt->execute([$approvedBy, $requestId]);

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("APPROVE_CASH", "CASH_REQUESTS", ?, ?)');
            $log->execute([$requestId, "Cash voucher {$req['voucher_no']} ({$req['requested_by']}) for " . format_money($req['amount']) . " approved by {$approvedBy}"]);

            respond(true, "Cash Request {$req['voucher_no']} approved for cash release and routed to Cash Expenses.", ['request_id' => $requestId, 'amount' => $req['amount']], $redirectUrl);
            break;

        case 'batch_approve_cash_requests':
            $requestIds = $_POST['request_ids'] ?? [];
            if (!is_array($requestIds) && is_string($requestIds)) {
                $requestIds = explode(',', $requestIds);
            }
            $requestIds = array_filter(array_map('intval', $requestIds));

            if (empty($requestIds)) {
                respond(false, 'No cash requests selected.', [], $redirectUrl);
            }

            $approvedBy = trim($_POST['approved_by'] ?? DEFAULT_APPROVER);
            $placeholders = implode(',', array_fill(0, count($requestIds), '?'));

            $stmt = $db->prepare("UPDATE cash_requests SET status = 'approved', approved_at = datetime('now'), approved_by = ? WHERE id IN ($placeholders) AND status = 'pending'");
            $params = array_merge([$approvedBy], $requestIds);
            $stmt->execute($params);
            $count = $stmt->rowCount();

            respond(true, "Successfully approved {$count} cash request(s). They are now recorded in Cash Expenses.", ['count' => $count], $redirectUrl);
            break;

        case 'reject_cash_request':
            $requestId = intval($_POST['request_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? 'Disapproved by Management');

            if ($requestId <= 0) {
                respond(false, 'Invalid cash request selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE cash_requests SET status = "rejected", rejection_reason = ?, approved_at = datetime("now") WHERE id = ?');
            $stmt->execute([$reason, $requestId]);

            respond(true, 'Cash request marked as rejected.', [], $redirectUrl);
            break;

        case 'edit_cash_request':
            $requestId       = intval($_POST['request_id'] ?? 0);
            $voucherNo       = trim($_POST['voucher_no'] ?? '');
            $requestedBy     = trim($_POST['requested_by'] ?? '');
            $deptProject     = trim($_POST['department_or_project'] ?? 'Site Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $amount          = floatval($_POST['amount'] ?? 0);
            $purpose         = trim($_POST['purpose'] ?? '');
            $requestDate     = trim($_POST['request_date'] ?? date('Y-m-d'));
            $status          = trim($_POST['status'] ?? 'approved');

            if ($requestId <= 0) {
                respond(false, 'Invalid cash expense record selected.', [], $redirectUrl);
            }

            if (empty($requestedBy) || $amount <= 0 || empty($purpose)) {
                respond(false, 'Please fill in requester name, amount, and purpose.', [], $redirectUrl);
            }

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare('UPDATE cash_requests SET voucher_no = ?, requested_by = ?, department_or_project = ?, project_district = ?, amount = ?, purpose = ?, request_date = ?, status = ?, custom_fields = ? WHERE id = ?');
            $stmt->execute([$voucherNo, $requestedBy, $deptProject, $projectDistrict, $amount, $purpose, $requestDate, $status, $customFieldsJson, $requestId]);

            respond(true, "Cash expense {$voucherNo} updated successfully.", ['request_id' => $requestId], $redirectUrl);
            break;

        // ==========================================
        // MODULE 3: COLLECTIONS ACTIONS & FINANCIAL COMPUTATIONS
        // ==========================================
        case 'add_collection':
            $contractId      = trim($_POST['contract_id'] ?? '');
            $receiptNo       = trim($_POST['receipt_no'] ?? '');
            $clientName      = trim($_POST['client_name'] ?? '');
            $projectName     = trim($_POST['project_name'] ?? 'General Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $contractAmount  = floatval($_POST['contract_amount'] ?? 0);
            $netAmount       = floatval($_POST['net_amount'] ?? 0);
            $amount          = floatval($_POST['amount'] ?? 0); // Amount Collected
            $expectedDate    = !empty($_POST['expected_collection_date']) ? trim($_POST['expected_collection_date']) : null;
            $deduction       = floatval($_POST['deduction'] ?? 0);
            $netCollections  = (isset($_POST['net_collections']) && $_POST['net_collections'] !== '') ? floatval($_POST['net_collections']) : ($amount > 0 ? max(0, $amount - $deduction) : 0);
            $status          = trim($_POST['status'] ?? 'collected'); // 'collected' or 'in process'
            $billingOnProcess = (isset($_POST['billing_on_process']) && $_POST['billing_on_process'] !== '') ? floatval($_POST['billing_on_process']) : ($status === 'in process' ? $amount : 0);
            $paymentMethod   = trim($_POST['payment_method'] ?? 'Bank Deposit');
            $collectionDate  = trim($_POST['collection_date'] ?? date('Y-m-d'));
            $referenceNo     = trim($_POST['reference_no'] ?? '');
            $receivedBy      = trim($_POST['received_by'] ?? 'Main Cashier');
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($contractId)) {
                respond(false, 'Contract ID is required.', [], $redirectUrl);
            }

            if (!in_array($status, ['collected', 'in process'])) {
                $status = 'collected';
            }

            if (empty($receiptNo)) {
                $receiptNo = 'OR-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            if (empty($clientName) || $amount <= 0 || empty($paymentMethod)) {
                respond(false, 'Please fill in client name, amount, and payment method.', [], $redirectUrl);
            }

            // Automatic Computations:
            // Net Amount and Contract Amount defaults
            if ($netAmount <= 0) {
                $netAmount = ($contractAmount > 0) ? $contractAmount : $amount;
            }
            if ($contractAmount <= 0) {
                $contractAmount = $netAmount;
            }

            // Balance Collectible = Net Amount - Amount Collected
            $balanceCollectible = compute_balance_collectible($netAmount, $amount);

            // Percentage of collections received
            $percentageCollected = compute_percentage_collected($contractAmount, $amount);

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare('INSERT INTO collections (contract_id, receipt_no, client_name, project_name, project_district, contract_amount, net_amount, percentage_collected, balance_collectible, amount, expected_collection_date, deduction, net_collections, billing_on_process, payment_method, collection_date, reference_no, received_by, notes, status, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$contractId, $receiptNo, $clientName, $projectName, $projectDistrict, $contractAmount, $netAmount, $percentageCollected, $balanceCollectible, $amount, $expectedDate, $deduction, $netCollections, $billingOnProcess, $paymentMethod, $collectionDate, $referenceNo, $receivedBy, $notes, $status, $customFieldsJson]);

            respond(true, "Collection {$receiptNo} [Contract: {$contractId}] from {$clientName} recorded (" . format_money($amount) . ", Status: " . ucfirst($status) . "). Inflow updated!", ['receipt_no' => $receiptNo, 'amount' => $amount], $redirectUrl);
            break;

        case 'edit_collection':
            $collectionId    = intval($_POST['collection_id'] ?? 0);
            $contractId      = trim($_POST['contract_id'] ?? '');
            $receiptNo       = trim($_POST['receipt_no'] ?? '');
            $clientName      = trim($_POST['client_name'] ?? '');
            $projectName     = trim($_POST['project_name'] ?? 'General Operations');
            $projectDistrict = trim($_POST['project_district'] ?? '');
            $contractAmount  = floatval($_POST['contract_amount'] ?? 0);
            $netAmount       = floatval($_POST['net_amount'] ?? 0);
            $amount          = floatval($_POST['amount'] ?? 0); // Amount Collected
            $expectedDate    = !empty($_POST['expected_collection_date']) ? trim($_POST['expected_collection_date']) : null;
            $deduction       = floatval($_POST['deduction'] ?? 0);
            $netCollections  = (isset($_POST['net_collections']) && $_POST['net_collections'] !== '') ? floatval($_POST['net_collections']) : ($amount > 0 ? max(0, $amount - $deduction) : 0);
            $status          = trim($_POST['status'] ?? 'collected'); // 'collected' or 'in process'
            $billingOnProcess = (isset($_POST['billing_on_process']) && $_POST['billing_on_process'] !== '') ? floatval($_POST['billing_on_process']) : ($status === 'in process' ? $amount : 0);
            $paymentMethod   = trim($_POST['payment_method'] ?? 'Bank Deposit');
            $collectionDate  = trim($_POST['collection_date'] ?? date('Y-m-d'));
            $referenceNo     = trim($_POST['reference_no'] ?? '');
            $receivedBy      = trim($_POST['received_by'] ?? 'Main Cashier');
            $notes           = trim($_POST['notes'] ?? '');

            if ($collectionId <= 0) {
                respond(false, 'Invalid collection record selected.', [], $redirectUrl);
            }

            if (empty($contractId)) {
                respond(false, 'Contract ID is required.', [], $redirectUrl);
            }

            if (!in_array($status, ['collected', 'in process'])) {
                $status = 'collected';
            }

            if (empty($clientName) || $amount <= 0 || empty($paymentMethod)) {
                respond(false, 'Please fill in client name, amount, and payment method.', [], $redirectUrl);
            }

            // Automatic Computations:
            if ($netAmount <= 0) {
                $netAmount = ($contractAmount > 0) ? $contractAmount : $amount;
            }
            if ($contractAmount <= 0) {
                $contractAmount = $netAmount;
            }

            // Balance Collectible = Net Amount - Amount Collected
            $balanceCollectible = compute_balance_collectible($netAmount, $amount);

            // Percentage of collections received
            $percentageCollected = compute_percentage_collected($contractAmount, $amount);

            // Process custom fields
            $customFields = [];
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $k => $v) {
                    $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                    if ($cleanK !== '') {
                        $customFields[$cleanK] = trim($v);
                    }
                }
            }
            $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare('UPDATE collections SET contract_id = ?, receipt_no = ?, client_name = ?, project_name = ?, project_district = ?, contract_amount = ?, net_amount = ?, percentage_collected = ?, balance_collectible = ?, amount = ?, expected_collection_date = ?, deduction = ?, net_collections = ?, billing_on_process = ?, payment_method = ?, collection_date = ?, reference_no = ?, received_by = ?, notes = ?, status = ?, custom_fields = ? WHERE id = ?');
            $stmt->execute([$contractId, $receiptNo, $clientName, $projectName, $projectDistrict, $contractAmount, $netAmount, $percentageCollected, $balanceCollectible, $amount, $expectedDate, $deduction, $netCollections, $billingOnProcess, $paymentMethod, $collectionDate, $referenceNo, $receivedBy, $notes, $status, $customFieldsJson, $collectionId]);

            respond(true, "Collection {$receiptNo} [Contract: {$contractId}] updated successfully (Status: " . ucfirst($status) . ", Amount: " . format_money($amount) . ").", ['collection_id' => $collectionId, 'status' => $status], $redirectUrl);
            break;

        case 'update_collection_status':
            $collectionId = intval($_POST['collection_id'] ?? 0);
            $newStatus    = trim($_POST['status'] ?? 'collected');

            if ($collectionId <= 0 || !in_array($newStatus, ['collected', 'in process'])) {
                respond(false, 'Invalid collection status.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE collections SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $collectionId]);

            respond(true, "Collection status updated to '{$newStatus}'.", ['status' => $newStatus], $redirectUrl);
            break;

        case 'delete_collection':
            $collectionId = intval($_POST['collection_id'] ?? 0);
            if ($collectionId <= 0) {
                respond(false, 'Invalid collection record.', [], $redirectUrl);
            }

            $stmt = $db->prepare('DELETE FROM collections WHERE id = ?');
            $stmt->execute([$collectionId]);
            respond(true, 'Collection entry removed.', [], $redirectUrl);
            break;

        // ==========================================
        // AUTHENTICATION ACTIONS
        // ==========================================
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username)) {
                respond(false, 'Please enter your username.', [], '../login.php');
            }

            if (strlen($username) < 3) {
                respond(false, 'Username must be at least 3 characters long.', [], '../login.php');
            }

            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username) && !filter_var($username, FILTER_VALIDATE_EMAIL)) {
                respond(false, 'Username may only contain letters, numbers, dots, dashes, and underscores.', [], '../login.php');
            }

            if (empty($password)) {
                respond(false, 'Please enter your password.', [], '../login.php');
            }

            $authResult = authenticate_user($username, $password);
            if (!$authResult['success']) {
                respond(false, $authResult['message'], [], '../login.php');
            }

            $currentUser = $authResult['user'];
            $defaultDest = ($currentUser['role'] === 'admin') ? '../admin.php' : '../index.php';
            $targetUrl = $_SESSION['redirect_after_login'] ?? $defaultDest;
            unset($_SESSION['redirect_after_login']);

            respond(true, "Welcome back, {$currentUser['full_name']}!", ['user' => $currentUser], $targetUrl);
            break;

        case 'logout':
            logout_user();
            respond(true, 'You have been successfully logged out.', [], '../login.php?logged_out=1');
            break;

        // ==========================================
        // ADMIN: USER MANAGEMENT ACTIONS
        // ==========================================
        case 'add_user':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $username = strtolower(trim($_POST['username'] ?? ''));
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'accountant');
            $status = trim($_POST['status'] ?? 'active');
            $avatarColor = trim($_POST['avatar_color'] ?? '#0f172a');

            if (empty($username) || empty($password) || empty($fullName)) {
                respond(false, 'Username, full name, and password are required.', [], '../admin.php?tab=tab-users');
            }

            if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
                respond(false, 'Username must be 3-30 characters (letters, numbers, underscores, dashes only).', [], '../admin.php?tab=tab-users');
            }

            if (strlen($password) < 4) {
                respond(false, 'Password must be at least 4 characters long.', [], '../admin.php?tab=tab-users');
            }

            // Check if username already exists
            $checkStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)');
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                respond(false, "Username '{$username}' is already in use.", [], '../admin.php?tab=tab-users');
            }

            // Allowed roles: admin, accountant, outsourced_accountant
            $validRoles = ['admin', 'accountant', 'outsourced_accountant'];
            if (!in_array($role, $validRoles)) {
                $role = 'accountant';
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare('INSERT INTO users (username, password, full_name, email, role, status, avatar_color) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$username, $passwordHash, $fullName, $email, $role, $status, $avatarColor]);
            $newUserId = $db->lastInsertId();

            // Audit log
            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("CREATE_USER", "USERS", ?, ?)');
            $log->execute([$newUserId, "Admin '{$adminUser['username']}' created user '{$username}' with role '{$role}'"]);

            respond(true, "User '{$fullName}' (@{$username}) created successfully.", ['user_id' => $newUserId], '../admin.php?tab=tab-users');
            break;

        case 'edit_user':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $userId = intval($_POST['user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = trim($_POST['role'] ?? 'accountant');
            $status = trim($_POST['status'] ?? 'active');
            $password = $_POST['password'] ?? '';
            $avatarColor = trim($_POST['avatar_color'] ?? '#0f172a');

            if (!in_array($role, ['admin', 'accountant', 'outsourced_accountant'])) {
                $role = 'accountant';
            }

            if ($userId <= 0 || empty($fullName)) {
                respond(false, 'Invalid user data provided.', [], '../admin.php?tab=tab-users');
            }

            $currentAdmin = current_user();

            // Prevent self-demotion or self-deactivation
            if ($userId === (int)$currentAdmin['id']) {
                $role = 'admin';
                $status = 'active';
            }

            // Check if user exists
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $existingUser = $stmt->fetch();
            if (!$existingUser) {
                respond(false, 'User not found.', [], '../admin.php?tab=tab-users');
            }

            if (!empty($password)) {
                if (strlen($password) < 4) {
                    respond(false, 'New password must be at least 4 characters long.', [], '../admin.php?tab=tab-users');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, avatar_color = ?, password = ? WHERE id = ?');
                $updateStmt->execute([$fullName, $email, $role, $status, $avatarColor, $passwordHash, $userId]);
            } else {
                $updateStmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, avatar_color = ? WHERE id = ?');
                $updateStmt->execute([$fullName, $email, $role, $status, $avatarColor, $userId]);
            }

            // If admin updated their own profile, sync session
            if ($userId === (int)$currentAdmin['id']) {
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['avatar_color'] = $avatarColor;
            }

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("UPDATE_USER", "USERS", ?, ?)');
            $log->execute([$userId, "Admin '{$currentAdmin['username']}' updated profile for user '{$existingUser['username']}' (Role: {$role}, Status: {$status})"]);

            respond(true, "User '{$existingUser['username']}' updated successfully.", [], '../admin.php?tab=tab-users');
            break;

        case 'delete_user':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $userId = intval($_POST['user_id'] ?? 0);
            $currentAdmin = current_user();

            if ($userId <= 0) {
                respond(false, 'Invalid user ID.', [], '../admin.php?tab=tab-users');
            }

            if ($userId === (int)$currentAdmin['id']) {
                respond(false, 'Security protection: You cannot delete your own administrator account.', [], '../admin.php?tab=tab-users');
            }

            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                respond(false, 'User not found.', [], '../admin.php?tab=tab-users');
            }

            $delStmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $delStmt->execute([$userId]);

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("DELETE_USER", "USERS", ?, ?)');
            $log->execute([$userId, "Admin '{$currentAdmin['username']}' deleted user '{$targetUser['username']}' ({$targetUser['full_name']})"]);

            respond(true, "User '{$targetUser['username']}' was successfully deleted.", [], '../admin.php?tab=tab-users');
            break;

        // ==========================================
        // ADMIN: SYSTEM CONFIGURATION & MAINTENANCE
        // ==========================================
        case 'save_system_settings':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $allowedKeys = [
                'company_name',
                'company_tagline',
                'currency_symbol',
                'default_approver',
                'company_address',
                'company_phone',
                'company_email',
                'fiscal_year',
                'allow_cashier_self_approval'
            ];

            foreach ($allowedKeys as $key) {
                if (isset($_POST[$key])) {
                    $val = trim($_POST[$key]);
                    set_system_setting($key, $val);
                }
            }

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("UPDATE_SETTINGS", "SETTINGS", 0, ?)');
            $log->execute(["Admin '{$adminUser['username']}' updated company and system configuration settings."]);

            respond(true, 'System settings and company configuration updated successfully!', [], '../admin.php?tab=tab-settings');
            break;

        // ==========================================
        // ADMIN: CMS - BANK & ROUTING CODES MANAGEMENT
        // ==========================================
        case 'add_bank':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $bankName   = trim($_POST['bank_name'] ?? '');
            $branch     = trim($_POST['branch'] ?? '');
            $code       = trim($_POST['code'] ?? '');
            $accountNo  = trim($_POST['account_number'] ?? '');
            $sortOrder  = intval($_POST['sort_order'] ?? 0);
            $status     = trim($_POST['status'] ?? 'active');

            if (empty($bankName) || empty($branch) || empty($code)) {
                respond(false, 'Bank Name, Branch, and Starting Code are required.', [], '../admin.php?tab=tab-cms&sub=banks');
            }

            $stmt = $db->prepare('INSERT INTO system_banks (bank_name, branch, code, account_number, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$bankName, $branch, $code, $accountNo, $sortOrder, $status]);
            $bankId = $db->lastInsertId();

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("ADD_BANK", "CMS", ?, ?)');
            $log->execute([$bankId, "Admin '{$adminUser['username']}' added bank '{$bankName} ({$branch})' with starting code '{$code}'"]);

            respond(true, "Bank '{$bankName} ({$branch})' [Code: {$code}] added successfully.", ['bank_id' => $bankId], '../admin.php?tab=tab-cms&sub=banks');
            break;

        case 'edit_bank':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $bankId     = intval($_POST['bank_id'] ?? 0);
            $bankName   = trim($_POST['bank_name'] ?? '');
            $branch     = trim($_POST['branch'] ?? '');
            $code       = trim($_POST['code'] ?? '');
            $accountNo  = trim($_POST['account_number'] ?? '');
            $sortOrder  = intval($_POST['sort_order'] ?? 0);
            $status     = trim($_POST['status'] ?? 'active');

            if ($bankId <= 0 || empty($bankName) || empty($branch) || empty($code)) {
                respond(false, 'Invalid bank data provided. Bank Name, Branch, and Starting Code are required.', [], '../admin.php?tab=tab-cms&sub=banks');
            }

            $stmt = $db->prepare('UPDATE system_banks SET bank_name = ?, branch = ?, code = ?, account_number = ?, sort_order = ?, status = ? WHERE id = ?');
            $stmt->execute([$bankName, $branch, $code, $accountNo, $sortOrder, $status, $bankId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("EDIT_BANK", "CMS", ?, ?)');
            $log->execute([$bankId, "Admin '{$adminUser['username']}' updated bank #{$bankId} '{$bankName} ({$branch})' - Starting code: '{$code}', Status: {$status}"]);

            respond(true, "Bank '{$bankName}' updated successfully (Starting Code: {$code}).", ['bank_id' => $bankId], '../admin.php?tab=tab-cms&sub=banks');
            break;

        case 'delete_bank':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $bankId = intval($_POST['bank_id'] ?? 0);
            if ($bankId <= 0) {
                respond(false, 'Invalid bank ID.', [], '../admin.php?tab=tab-cms&sub=banks');
            }

            $stmt = $db->prepare('SELECT * FROM system_banks WHERE id = ?');
            $stmt->execute([$bankId]);
            $bank = $stmt->fetch();

            if (!$bank) {
                respond(false, 'Bank entry not found.', [], '../admin.php?tab=tab-cms&sub=banks');
            }

            $delStmt = $db->prepare('DELETE FROM system_banks WHERE id = ?');
            $delStmt->execute([$bankId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("DELETE_BANK", "CMS", ?, ?)');
            $log->execute([$bankId, "Admin '{$adminUser['username']}' deleted bank '{$bank['bank_name']} ({$bank['branch']})'"]);

            respond(true, "Bank '{$bank['bank_name']}' removed from system.", [], '../admin.php?tab=tab-cms&sub=banks');
            break;

        // ==========================================
        // ADMIN: CMS - SYSTEM DROPDOWN OPTIONS
        // ==========================================
        case 'add_dropdown_item':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $category  = trim($_POST['category'] ?? '');
            $itemKey   = trim($_POST['item_key'] ?? '');
            $itemLabel = trim($_POST['item_label'] ?? '');
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            $status    = trim($_POST['status'] ?? 'active');

            if (empty($category) || empty($itemLabel)) {
                respond(false, 'Dropdown category and label are required.', [], '../admin.php?tab=tab-cms&sub=dropdowns');
            }

            if (empty($itemKey)) {
                $itemKey = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '-', $itemLabel));
            }

            $stmt = $db->prepare('INSERT INTO system_dropdowns (category, item_key, item_label, sort_order, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$category, $itemKey, $itemLabel, $sortOrder, $status]);
            $itemId = $db->lastInsertId();

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("ADD_DROPDOWN", "CMS", ?, ?)');
            $log->execute([$itemId, "Admin '{$adminUser['username']}' added choice '{$itemLabel}' ({$itemKey}) to '{$category}'"]);

            respond(true, "Option '{$itemLabel}' added to '{$category}'.", ['item_id' => $itemId], '../admin.php?tab=tab-cms&sub=dropdowns');
            break;

        case 'edit_dropdown_item':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $itemId    = intval($_POST['item_id'] ?? 0);
            $category  = trim($_POST['category'] ?? '');
            $itemKey   = trim($_POST['item_key'] ?? '');
            $itemLabel = trim($_POST['item_label'] ?? '');
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            $status    = trim($_POST['status'] ?? 'active');

            if ($itemId <= 0 || empty($itemLabel)) {
                respond(false, 'Invalid dropdown item data.', [], '../admin.php?tab=tab-cms&sub=dropdowns');
            }

            $stmt = $db->prepare('UPDATE system_dropdowns SET category = ?, item_key = ?, item_label = ?, sort_order = ?, status = ? WHERE id = ?');
            $stmt->execute([$category, $itemKey, $itemLabel, $sortOrder, $status, $itemId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("EDIT_DROPDOWN", "CMS", ?, ?)');
            $log->execute([$itemId, "Admin '{$adminUser['username']}' updated dropdown choice #{$itemId} '{$itemLabel}'"]);

            respond(true, "Option '{$itemLabel}' updated successfully.", ['item_id' => $itemId], '../admin.php?tab=tab-cms&sub=dropdowns');
            break;

        case 'delete_dropdown_item':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $itemId = intval($_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                respond(false, 'Invalid dropdown item ID.', [], '../admin.php?tab=tab-cms&sub=dropdowns');
            }

            $stmt = $db->prepare('SELECT * FROM system_dropdowns WHERE id = ?');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                respond(false, 'Dropdown item not found.', [], '../admin.php?tab=tab-cms&sub=dropdowns');
            }

            $delStmt = $db->prepare('DELETE FROM system_dropdowns WHERE id = ?');
            $delStmt->execute([$itemId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("DELETE_DROPDOWN", "CMS", ?, ?)');
            $log->execute([$itemId, "Admin '{$adminUser['username']}' deleted dropdown option '{$item['item_label']}' from '{$item['category']}'"]);

            respond(true, "Option '{$item['item_label']}' deleted.", [], '../admin.php?tab=tab-cms&sub=dropdowns');
            break;

        // ==========================================
        // ADMIN: CMS - CUSTOM FIELDS MANAGEMENT
        // ==========================================
        case 'add_custom_field':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $module      = trim($_POST['module'] ?? 'checks');
            $fieldLabel  = trim($_POST['field_label'] ?? '');
            $fieldKey    = trim($_POST['field_key'] ?? '');
            $fieldType   = trim($_POST['field_type'] ?? 'text');
            $options     = trim($_POST['field_options'] ?? '');
            $placeholder = trim($_POST['placeholder'] ?? '');
            $isRequired  = !empty($_POST['is_required']) ? 1 : 0;
            $sortOrder   = intval($_POST['sort_order'] ?? 0);
            $status      = trim($_POST['status'] ?? 'active');

            if (empty($fieldLabel)) {
                respond(false, 'Field Label is required.', [], '../admin.php?tab=tab-cms&sub=fields');
            }

            if (empty($fieldKey)) {
                $fieldKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $fieldLabel));
                $fieldKey = trim($fieldKey, '_');
            }

            if (!in_array($module, ['checks', 'cash_requests', 'collections'])) {
                $module = 'checks';
            }

            if (!in_array($fieldType, ['text', 'number', 'date', 'select', 'textarea'])) {
                $fieldType = 'text';
            }

            $stmt = $db->prepare('INSERT INTO custom_fields (module, field_key, field_label, field_type, field_options, placeholder, is_required, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$module, $fieldKey, $fieldLabel, $fieldType, $options, $placeholder, $isRequired, $sortOrder, $status]);
            $fieldId = $db->lastInsertId();

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("ADD_CUSTOM_FIELD", "CMS", ?, ?)');
            $log->execute([$fieldId, "Admin '{$adminUser['username']}' created custom field '{$fieldLabel}' ({$fieldKey}) on '{$module}'"]);

            respond(true, "Custom field '{$fieldLabel}' added to {$module}.", ['field_id' => $fieldId], '../admin.php?tab=tab-cms&sub=fields');
            break;

        case 'edit_custom_field':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $fieldId     = intval($_POST['field_id'] ?? 0);
            $module      = trim($_POST['module'] ?? 'checks');
            $fieldLabel  = trim($_POST['field_label'] ?? '');
            $fieldKey    = trim($_POST['field_key'] ?? '');
            $fieldType   = trim($_POST['field_type'] ?? 'text');
            $options     = trim($_POST['field_options'] ?? '');
            $placeholder = trim($_POST['placeholder'] ?? '');
            $isRequired  = !empty($_POST['is_required']) ? 1 : 0;
            $sortOrder   = intval($_POST['sort_order'] ?? 0);
            $status      = trim($_POST['status'] ?? 'active');

            if ($fieldId <= 0 || empty($fieldLabel)) {
                respond(false, 'Invalid custom field data.', [], '../admin.php?tab=tab-cms&sub=fields');
            }

            $stmt = $db->prepare('UPDATE custom_fields SET module = ?, field_key = ?, field_label = ?, field_type = ?, field_options = ?, placeholder = ?, is_required = ?, sort_order = ?, status = ? WHERE id = ?');
            $stmt->execute([$module, $fieldKey, $fieldLabel, $fieldType, $options, $placeholder, $isRequired, $sortOrder, $status, $fieldId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("EDIT_CUSTOM_FIELD", "CMS", ?, ?)');
            $log->execute([$fieldId, "Admin '{$adminUser['username']}' updated custom field #{$fieldId} '{$fieldLabel}'"]);

            respond(true, "Custom field '{$fieldLabel}' updated successfully.", ['field_id' => $fieldId], '../admin.php?tab=tab-cms&sub=fields');
            break;

        case 'delete_custom_field':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            $fieldId = intval($_POST['field_id'] ?? 0);
            if ($fieldId <= 0) {
                respond(false, 'Invalid field ID.', [], '../admin.php?tab=tab-cms&sub=fields');
            }

            $stmt = $db->prepare('SELECT * FROM custom_fields WHERE id = ?');
            $stmt->execute([$fieldId]);
            $field = $stmt->fetch();

            if (!$field) {
                respond(false, 'Custom field not found.', [], '../admin.php?tab=tab-cms&sub=fields');
            }

            $delStmt = $db->prepare('DELETE FROM custom_fields WHERE id = ?');
            $delStmt->execute([$fieldId]);

            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("DELETE_CUSTOM_FIELD", "CMS", ?, ?)');
            $log->execute([$fieldId, "Admin '{$adminUser['username']}' deleted custom field '{$field['field_label']}' from '{$field['module']}'"]);

            respond(true, "Custom field '{$field['field_label']}' deleted.", [], '../admin.php?tab=tab-cms&sub=fields');
            break;

        case 'optimize_database':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            try {
                if (DB_DRIVER === 'sqlite') {
                    $db->exec('VACUUM');
                    $db->exec('ANALYZE');
                } else {
                    $db->exec('OPTIMIZE TABLE checks, cash_requests, collections, users, system_settings, audit_logs');
                }

                $adminUser = current_user();
                $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("OPTIMIZE_DB", "SYSTEM", 0, ?)');
                $log->execute(["Admin '{$adminUser['username']}' executed database optimization / vacuum routine."]);

                respond(true, 'Database maintenance and optimization completed successfully!', [], '../admin.php?tab=tab-settings');
            } catch (Exception $e) {
                respond(false, 'Optimization failed: ' . $e->getMessage(), [], '../admin.php?tab=tab-settings');
            }
            break;

        case 'clear_audit_logs':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            // Keep the most recent 50 logs for safety
            $db->exec('DELETE FROM audit_logs WHERE id NOT IN (SELECT id FROM audit_logs ORDER BY id DESC LIMIT 50)');
            
            $adminUser = current_user();
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("PRUNE_LOGS", "SYSTEM", 0, ?)');
            $log->execute(["Admin '{$adminUser['username']}' pruned older audit logs (kept latest 50)."]);

            respond(true, 'Audit log archived and pruned. Kept the latest 50 records.', [], '../admin.php?tab=tab-audit');
            break;

        case 'reset_system_data':
            if (!is_admin()) {
                respond(false, 'Administrator privileges required.', [], '../admin.php');
            }

            try {
                // Delete all transactions
                $db->exec('DELETE FROM checks');
                $db->exec('DELETE FROM cash_requests');
                $db->exec('DELETE FROM collections');
                $db->exec('DELETE FROM audit_logs');

                // Reset SQLite autoincrement sequence IDs if applicable
                if (DB_DRIVER === 'sqlite') {
                    try {
                        $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('checks', 'cash_requests', 'collections', 'audit_logs')");
                    } catch (Exception $seqEx) {
                        // Ignore if sqlite_sequence does not exist
                    }
                    $db->exec('VACUUM');
                }

                $adminUser = current_user();
                $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("RESET_DATA", "SYSTEM", 0, ?)');
                $log->execute(["Admin '{$adminUser['username']}' reset all financial transaction data (checks, cash vouchers, collections)."]);

                respond(true, 'System data reset successfully! All checks, vouchers, and collections have been cleared to ₱0.00.', [], '../admin.php?tab=tab-settings');
            } catch (Exception $e) {
                respond(false, 'Data reset failed: ' . $e->getMessage(), [], '../admin.php?tab=tab-settings');
            }
            break;

        default:
            respond(false, 'Unknown action requested.', [], $redirectUrl);
            break;
    }
} catch (Exception $e) {
    respond(false, 'Database Error: ' . $e->getMessage(), [], $redirectUrl);
}
