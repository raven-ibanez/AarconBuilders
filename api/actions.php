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
        case 'add_check':
            $checkNumber = trim($_POST['check_number'] ?? '');
            $bankName    = trim($_POST['bank_name'] ?? '');
            $accountNo   = trim($_POST['account_number'] ?? '');
            $payee       = trim($_POST['payee'] ?? '');
            $amount      = floatval($_POST['amount'] ?? 0);
            $issueDate   = trim($_POST['issue_date'] ?? date('Y-m-d'));
            $checkDate   = trim($_POST['check_date'] ?? '');
            $category    = trim($_POST['category'] ?? 'Materials');
            $projectName = trim($_POST['project_name'] ?? 'General Operations');
            $remarks     = trim($_POST['remarks'] ?? '');

            if (empty($checkNumber) || empty($bankName) || empty($payee) || $amount <= 0 || empty($checkDate)) {
                respond(false, 'Please fill in all required fields (Check #, Bank, Payee, Amount, and Post-Date).', [], $redirectUrl);
            }

            // Check if check_number already exists
            $stmt = $db->prepare('SELECT COUNT(*) FROM checks WHERE check_number = ?');
            $stmt->execute([$checkNumber]);
            if ($stmt->fetchColumn() > 0) {
                respond(false, "Check Number '{$checkNumber}' is already registered in the system.", [], $redirectUrl);
            }

            $stmt = $db->prepare('INSERT INTO checks (check_number, bank_name, account_number, payee, amount, issue_date, check_date, category, project_name, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)');
            $stmt->execute([$checkNumber, $bankName, $accountNo, $payee, $amount, $issueDate, $checkDate, $category, $projectName, $remarks]);

            respond(true, "Check #{$checkNumber} for {$payee} encoded successfully (Amount: " . format_money($amount) . ").", ['id' => $db->lastInsertId()], $redirectUrl);
            break;

        case 'fund_check':
            $checkId = intval($_POST['check_id'] ?? 0);
            $fundedBy = trim($_POST['funded_by'] ?? DEFAULT_APPROVER);

            if ($checkId <= 0) {
                respond(false, 'Invalid check selected.', [], $redirectUrl);
            }

            $stmt = $db->prepare('SELECT * FROM checks WHERE id = ?');
            $stmt->execute([$checkId]);
            $check = $stmt->fetch();

            if (!$check) {
                respond(false, 'Check not found.', [], $redirectUrl);
            }

            $stmt = $db->prepare('UPDATE checks SET status = "funded", funded_at = datetime("now"), funded_by = ? WHERE id = ?');
            $stmt->execute([$fundedBy, $checkId]);

            // Audit log
            $log = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("FUND", "CHECKS", ?, ?)');
            $log->execute([$checkId, "Check #{$check['check_number']} ({$check['payee']}) of " . format_money($check['amount']) . " approved for funding by {$fundedBy}"]);

            respond(true, "Check #{$check['check_number']} marked as FUNDED and routed to Disbursement Report!", ['check_id' => $checkId, 'amount' => $check['amount']], $redirectUrl);
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
        // MODULE 2: CASH REQUEST ACTIONS
        // ==========================================
        case 'add_cash_request':
            $voucherNo   = trim($_POST['voucher_no'] ?? '');
            $requestedBy = trim($_POST['requested_by'] ?? '');
            $deptProject = trim($_POST['department_or_project'] ?? 'Site Operations');
            $amount      = floatval($_POST['amount'] ?? 0);
            $purpose     = trim($_POST['purpose'] ?? '');
            $requestDate = trim($_POST['request_date'] ?? date('Y-m-d'));

            if (empty($voucherNo)) {
                $voucherNo = 'PCV-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            if (empty($requestedBy) || $amount <= 0 || empty($purpose)) {
                respond(false, 'Please fill in requester name, amount, and purpose of cash request.', [], $redirectUrl);
            }

            $stmt = $db->prepare('INSERT INTO cash_requests (voucher_no, requested_by, department_or_project, amount, purpose, request_date, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');
            $stmt->execute([$voucherNo, $requestedBy, $deptProject, $amount, $purpose, $requestDate]);

            respond(true, "Cash Request {$voucherNo} for " . format_money($amount) . " submitted for Boss Approval.", ['voucher_no' => $voucherNo], $redirectUrl);
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

            $stmt = $db->prepare('UPDATE cash_requests SET status = "rejected", rejection_reason = ? WHERE id = ?');
            $stmt->execute([$reason, $requestId]);

            respond(true, 'Cash request marked as rejected.', [], $redirectUrl);
            break;

        // ==========================================
        // MODULE 3: COLLECTIONS ACTIONS
        // ==========================================
        case 'add_collection':
            $receiptNo       = trim($_POST['receipt_no'] ?? '');
            $clientName      = trim($_POST['client_name'] ?? '');
            $projectName     = trim($_POST['project_name'] ?? 'General Operations');
            $amount          = floatval($_POST['amount'] ?? 0);
            $paymentMethod   = trim($_POST['payment_method'] ?? 'Bank Deposit');
            $collectionDate  = trim($_POST['collection_date'] ?? date('Y-m-d'));
            $referenceNo     = trim($_POST['reference_no'] ?? '');
            $receivedBy      = trim($_POST['received_by'] ?? 'Main Cashier');
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($receiptNo)) {
                $receiptNo = 'OR-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            if (empty($clientName) || $amount <= 0 || empty($paymentMethod)) {
                respond(false, 'Please fill in client name, amount, and payment method.', [], $redirectUrl);
            }

            $stmt = $db->prepare('INSERT INTO collections (receipt_no, client_name, project_name, amount, payment_method, collection_date, reference_no, received_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$receiptNo, $clientName, $projectName, $amount, $paymentMethod, $collectionDate, $referenceNo, $receivedBy, $notes]);

            respond(true, "Collection {$receiptNo} from {$clientName} recorded (" . format_money($amount) . "). Total Inflow updated!", ['receipt_no' => $receiptNo, 'amount' => $amount], $redirectUrl);
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

            // Allowed roles: only admin and accountant
            $validRoles = ['admin', 'accountant'];
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

            if (!in_array($role, ['admin', 'accountant'])) {
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

                respond(true, 'Database maintenance and optimization completed successfully!', [], '../admin.php?tab=tab-system');
            } catch (Exception $e) {
                respond(false, 'Optimization failed: ' . $e->getMessage(), [], '../admin.php?tab=tab-system');
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

                respond(true, 'System data reset successfully! All checks, vouchers, and collections have been cleared to ₱0.00.', [], '../admin.php?tab=tab-system');
            } catch (Exception $e) {
                respond(false, 'Data reset failed: ' . $e->getMessage(), [], '../admin.php?tab=tab-system');
            }
            break;

        default:
            respond(false, 'Unknown action requested.', [], $redirectUrl);
            break;
    }
} catch (Exception $e) {
    respond(false, 'Database Error: ' . $e->getMessage(), [], $redirectUrl);
}
