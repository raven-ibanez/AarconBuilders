<?php
/**
 * Aarcon Builders - Authentication & Session Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get the currently logged-in user data
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if the logged-in user is an administrator
 */
function is_admin(): bool {
    $user = current_user();
    return $user !== null && isset($user['role']) && $user['role'] === 'admin';
}

/**
 * Check if the logged-in user is an accountant
 */
function is_accountant(): bool {
    $user = current_user();
    return $user !== null && isset($user['role']) && $user['role'] === 'accountant';
}

/**
 * Check if the logged-in user is specifically an outsourced accountant (deprecated/removed role)
 */
function is_outsourced_accountant(): bool {
    return false;
}

/**
 * Get human-readable role label
 */
function get_user_role_label(?string $role = null): string {
    if ($role === null) {
        $user = current_user();
        $role = $user['role'] ?? 'accountant';
    }
    switch ($role) {
        case 'admin':
            return 'Administrator';
        case 'accountant':
        default:
            return 'Lead Accountant';
    }
}


/**
 * Enforce authentication on protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $_SESSION['redirect_after_login'] = $currentUri;
        header('Location: login.php');
        exit;
    }
}

/**
 * Enforce admin privileges on restricted pages
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access restricted. You need Administrator privileges to access that section.');
        header('Location: index.php');
        exit;
    }
}

/**
 * Authenticate a user by username/email and password
 */
function authenticate_user(string $usernameOrEmail, string $password): array {
    $db = get_db();
    
    $stmt = $db->prepare('SELECT * FROM users WHERE (LOWER(username) = LOWER(:login) OR LOWER(email) = LOWER(:login)) LIMIT 1');
    $stmt->execute([':login' => trim($usernameOrEmail)]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'No account found with that username or email.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'This account has been deactivated. Please contact an administrator.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Incorrect password. Please try again.'];
    }

    // Update last login timestamp
    try {
        $updateStmt = $db->prepare('UPDATE users SET last_login = datetime("now") WHERE id = ?');
        $updateStmt->execute([$user['id']]);
    } catch (Exception $e) {
        // Non-fatal if driver differs
    }

    // Set user session data
    $_SESSION['user'] = [
        'id'           => (int)$user['id'],
        'username'     => $user['username'],
        'full_name'    => $user['full_name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'avatar_color' => $user['avatar_color'] ?? '#dc2626'
    ];

    // Log to audit log
    try {
        $logStmt = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("LOGIN", "AUTH", ?, ?)');
        $logStmt->execute([$user['id'], "User '{$user['username']}' ({$user['full_name']}) logged in successfully."]);
    } catch (Exception $e) {
        // Silently continue
    }

    return ['success' => true, 'user' => $_SESSION['user']];
}

/**
 * Log out current user
 */
function logout_user(): void {
    if (is_logged_in()) {
        $user = current_user();
        try {
            $db = get_db();
            $logStmt = $db->prepare('INSERT INTO audit_logs (action_type, module, record_id, description) VALUES ("LOGOUT", "AUTH", ?, ?)');
            $logStmt->execute([$user['id'] ?? 0, "User '{$user['username']}' logged out."]);
        } catch (Exception $e) {
            // Silently continue
        }
    }

    unset($_SESSION['user']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Get setting value with cache
 */
function get_system_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $db = get_db();
            $rows = $db->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
            foreach ($rows as $r) {
                $settingsCache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            // Fallback
        }
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Update or insert a system setting
 */
function set_system_setting(string $key, string $value): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, datetime("now")) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime("now")');
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        // Fallback for MySQL or older SQLite syntax
        try {
            $stmt = $db->prepare('REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)');
            return $stmt->execute([$key, $value]);
        } catch (Exception $e2) {
            return false;
        }
    }
}
