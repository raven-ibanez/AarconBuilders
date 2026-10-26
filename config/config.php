<?php
/**
 * Aarcon Builders - Configuration
 * Cash & Check Management System
 */

// Start secure session if not already started (HttpOnly prevents session theft via XSS)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// System Constants
define('APP_NAME', 'Aarcon Builders');
define('APP_TAGLINE', 'Cash, PDC Check & Disbursement Management');
define('CURRENCY_SYMBOL', '₱');
define('DEFAULT_APPROVER', 'Engr. Boss / General Manager');

// Database Configuration
// Default is SQLite (Zero configuration needed, perfectly portable)
// Set DB_DRIVER to 'mysql' to switch to MySQL
define('DB_DRIVER', 'sqlite'); 

// MySQL configuration (used if DB_DRIVER === 'mysql')
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'aarcon_builders');
define('DB_USER', 'root');
define('DB_PASS', '');

// SQLite Database file path
define('SQLITE_FILE', __DIR__ . '/../database/aarcon_builders.db');

/**
 * Get dynamic currency symbol or fallback
 */
function get_currency_symbol() {
    if (function_exists('get_system_setting')) {
        $val = get_system_setting('currency_symbol');
        if (!empty($val)) return $val;
    }
    return CURRENCY_SYMBOL;
}

/**
 * Get dynamic app/company name
 */
function get_app_name() {
    if (function_exists('get_system_setting')) {
        $val = get_system_setting('company_name');
        if (!empty($val)) return $val;
    }
    return APP_NAME;
}

/**
 * Format currency amount
 */
function format_money($amount) {
    return get_currency_symbol() . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date for display
 */
function format_date($dateStr, $format = 'M d, Y') {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return $time ? date($format, $time) : $dateStr;
}

/**
 * Set flash message in session
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize string output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Standard Project Districts for Aarcon Builders (Dynamic CMS with fallback)
 */
function get_project_districts(): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $rows = $db->query("SELECT item_key, item_label FROM system_dropdowns WHERE category = 'project_districts' AND status = 'active' ORDER BY sort_order ASC, item_label ASC")->fetchAll();
            if (!empty($rows)) {
                $districts = [];
                foreach ($rows as $r) {
                    $districts[$r['item_key']] = $r['item_label'];
                }
                return $districts;
            }
        }
    } catch (Exception $e) {}

    return [
        'DPWH-RIZAL-2nd'  => 'DPWH - Rizal 2nd District Engineering Office',
        'DPWH-RIZAL-1st'  => 'DPWH - Rizal 1st District Engineering Office',
        'DPWH-NCR-1st'    => 'DPWH - NCR 1st District Engineering Office',
        'DPWH-NCR-2nd'    => 'DPWH - NCR 2nd District Engineering Office',
        'DPWH-REGION-IVA' => 'DPWH - Region IV-A (CALABARZON)',
        'DPWH-CENTRAL'    => 'DPWH - Central Office / Major Projects',
        'LGU-ANTIPOLO'    => 'LGU - Antipolo City Infrastructure',
        'LGU-PASIG'       => 'LGU - Pasig City Infrastructure',
        'COMMERCIAL'      => 'Private Commercial Development',
        'RESIDENTIAL'     => 'Private Residential Project',
        'GENERAL'         => 'General Operations / Site HQ'
    ];
}

/**
 * Bank Name & Branch with identifying routing codes (Dynamic CMS with fallback)
 */
function get_bank_branches(): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $rows = $db->query("SELECT * FROM system_banks WHERE status = 'active' ORDER BY sort_order ASC, code ASC, id ASC")->fetchAll();
            if (!empty($rows)) {
                $result = [];
                foreach ($rows as $r) {
                    $result[] = [
                        'id'             => (int)$r['id'],
                        'code'           => $r['code'],
                        'bank'           => $r['bank_name'],
                        'branch'         => $r['branch'],
                        'account_number' => $r['account_number'] ?? '',
                        'label'          => sprintf('%s - %s (%s)', $r['bank_name'], $r['branch'], $r['code'])
                    ];
                }
                return $result;
            }
        }
    } catch (Exception $e) {}

    return [
        ['code' => '001', 'bank' => 'BDO Unibank', 'branch' => 'Pasig - Ortigas Central', 'label' => 'BDO - Ortigas Central (001)'],
        ['code' => '002', 'bank' => 'BDO Unibank', 'branch' => 'Rizal - Antipolo Circumferential', 'label' => 'BDO - Antipolo Circumferential (002)'],
        ['code' => '003', 'bank' => 'BPI', 'branch' => 'Pasig - Ortigas Center Emerald', 'label' => 'BPI - Emerald Ortigas (003)'],
        ['code' => '004', 'bank' => 'BPI', 'branch' => 'Rizal - Taytay Manila East', 'label' => 'BPI - Taytay Manila East (004)'],
        ['code' => '005', 'bank' => 'Metrobank', 'branch' => 'Pasig - Shaw Boulevard', 'label' => 'Metrobank - Shaw Blvd (005)'],
        ['code' => '006', 'bank' => 'Metrobank', 'branch' => 'Rizal - Cainta Junction', 'label' => 'Metrobank - Cainta Junction (006)'],
        ['code' => '007', 'bank' => 'Landbank of the Philippines', 'branch' => 'Pasig - Capitol Commons', 'label' => 'Landbank - Pasig Capitol (007)'],
        ['code' => '008', 'bank' => 'Philippine National Bank (PNB)', 'branch' => 'Pasig - San Antonio', 'label' => 'PNB - San Antonio Pasig (008)'],
        ['code' => '009', 'bank' => 'Security Bank', 'branch' => 'Ortigas Center - ADB Ave', 'label' => 'Security Bank - ADB Ave (009)'],
        ['code' => '010', 'bank' => 'UnionBank of the Philippines', 'branch' => 'Pasig - Meralco Ave', 'label' => 'UnionBank - Meralco Ave (010)'],
        ['code' => '011', 'bank' => 'China Bank', 'branch' => 'Pasig - Kapasigan', 'label' => 'China Bank - Kapasigan (011)']
    ];
}

/**
 * Standard Expense / Disbursement Categories (Dynamic CMS with fallback)
 */
function get_expense_categories(): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $rows = $db->query("SELECT item_key, item_label FROM system_dropdowns WHERE category = 'expense_categories' AND status = 'active' ORDER BY sort_order ASC, item_label ASC")->fetchAll();
            if (!empty($rows)) {
                $cats = [];
                foreach ($rows as $r) {
                    $cats[$r['item_key']] = $r['item_label'];
                }
                return $cats;
            }
        }
    } catch (Exception $e) {}

    return [
        'Materials'             => 'Raw Materials & Hardware Supplies',
        'Subcontractor'         => 'Subcontractor Progress Billing',
        'Labor / Payroll'       => 'Construction Labor & Site Payroll',
        'Equipment Rental'      => 'Heavy Equipment & Machinery Rental',
        'Fuel & Oil'            => 'Fuel, Lubricants & Hauling',
        'Permits & Taxes'       => 'Government Permits, Licenses & Fees',
        'Utilities & Overhead'  => 'Site Utilities & Office Overhead',
        'Miscellaneous'         => 'Miscellaneous Jobsite Expenses'
    ];
}

/**
 * Standard Payment Methods for Collections (Dynamic CMS with fallback)
 */
function get_payment_methods(): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $rows = $db->query("SELECT item_key, item_label FROM system_dropdowns WHERE category = 'payment_methods' AND status = 'active' ORDER BY sort_order ASC, item_label ASC")->fetchAll();
            if (!empty($rows)) {
                $methods = [];
                foreach ($rows as $r) {
                    $methods[$r['item_key']] = $r['item_label'];
                }
                return $methods;
            }
        }
    } catch (Exception $e) {}

    return [
        'Bank Deposit'    => 'Bank Deposit / Over-the-counter',
        'Cash'            => 'Cash',
        'Online Transfer' => 'Online Bank Transfer (InstaPay / PESONet)',
        'Incoming Check'  => 'Incoming Client Check',
        'GCash / E-Wallet'=> 'GCash / Digital E-Wallet'
    ];
}

/**
 * Standard Priority Statuses for Checks (Dynamic CMS with fallback)
 */
function get_priority_statuses(): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $rows = $db->query("SELECT item_key, item_label FROM system_dropdowns WHERE category = 'priority_statuses' AND status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
            if (!empty($rows)) {
                $priorities = [];
                foreach ($rows as $r) {
                    $priorities[$r['item_key']] = $r['item_label'];
                }
                return $priorities;
            }
        }
    } catch (Exception $e) {}

    return [
        'Standard'    => 'Standard (Regular Release)',
        'Prioritized' => 'Prioritized (Urgent Release)',
        'Hold'        => 'Hold (Do Not Hand Out Yet)'
    ];
}

/**
 * Fetch Custom Fields configured for a given module (checks, cash_requests, collections)
 */
function get_custom_fields_for_module(string $module): array {
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM custom_fields WHERE module = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$module]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
    return [];
}

/**
 * Render dynamic custom field HTML input elements for forms
 */
function render_custom_fields_html(string $module, $values = [], string $prefix = ''): string {
    if (is_string($values)) {
        $values = json_decode($values, true) ?: [];
    }
    $fields = get_custom_fields_for_module($module);
    if (empty($fields)) {
        return '';
    }

    $html = '<div class="custom-fields-box col-span-2" style="background: rgba(255, 255, 255, 0.55); border: 1px dashed rgba(0, 113, 227, 0.35); border-radius: 14px; padding: 0.85rem 1rem; margin: 0.65rem 0;">';
    $html .= '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.65rem;">';
    $html .= '<span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--apple-blue); display: flex; align-items: center; gap: 0.35rem;">Custom Fields</span>';
    $html .= '<small style="font-size: 0.7rem; color: var(--text-muted);">Configured via Admin CMS</small>';
    $html .= '</div>';
    $html .= '<div class="form-grid" style="gap: 0.75rem;">';

    foreach ($fields as $f) {
        $key = htmlspecialchars($f['field_key'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($f['field_label'], ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES, 'UTF-8');
        $reqAttr = $f['is_required'] ? 'required' : '';
        $reqStar = $f['is_required'] ? ' <span class="req">*</span>' : '';
        $val = htmlspecialchars($values[$f['field_key']] ?? '', ENT_QUOTES, 'UTF-8');
        $inputName = "custom_fields[{$key}]";
        $id = ($prefix ? $prefix . '_' : '') . "cf_{$module}_{$key}";

        $html .= '<div class="form-group">';
        $html .= "<label class=\"form-label\" for=\"{$id}\">{$label}{$reqStar}</label>";

        if ($f['field_type'] === 'select') {
            $options = array_filter(array_map('trim', explode(',', $f['field_options'] ?? '')));
            $html .= "<select name=\"{$inputName}\" id=\"{$id}\" data-cf-key=\"{$key}\" class=\"form-control\" {$reqAttr}>";
            $html .= '<option value="">-- Select --</option>';
            foreach ($options as $opt) {
                $sel = ($opt === ($values[$f['field_key']] ?? '')) ? 'selected' : '';
                $optEsc = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
                $html .= "<option value=\"{$optEsc}\" {$sel}>{$optEsc}</option>";
            }
            $html .= '</select>';
        } elseif ($f['field_type'] === 'number') {
            $html .= "<input type=\"number\" step=\"any\" name=\"{$inputName}\" id=\"{$id}\" data-cf-key=\"{$key}\" class=\"form-control\" value=\"{$val}\" placeholder=\"{$placeholder}\" {$reqAttr}>";
        } elseif ($f['field_type'] === 'date') {
            $html .= "<input type=\"date\" name=\"{$inputName}\" id=\"{$id}\" data-cf-key=\"{$key}\" class=\"form-control\" value=\"{$val}\" {$reqAttr}>";
        } elseif ($f['field_type'] === 'textarea') {
            $html .= "<textarea name=\"{$inputName}\" id=\"{$id}\" data-cf-key=\"{$key}\" class=\"form-control\" rows=\"2\" placeholder=\"{$placeholder}\" {$reqAttr}>{$val}</textarea>";
        } else {
            $html .= "<input type=\"text\" name=\"{$inputName}\" id=\"{$id}\" data-cf-key=\"{$key}\" class=\"form-control\" value=\"{$val}\" placeholder=\"{$placeholder}\" {$reqAttr}>";
        }

        $html .= '</div>';
    }

    $html .= '</div></div>';
    return $html;
}

/**
 * Render visual pill badges for custom field values in tables or detail cards
 */
function render_custom_fields_badges($customFieldsData): string {
    if (empty($customFieldsData)) return '';
    if (is_string($customFieldsData)) {
        $customFieldsData = json_decode($customFieldsData, true) ?: [];
    }
    if (empty($customFieldsData) || !is_array($customFieldsData)) return '';

    $html = '<div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.35rem;">';
    foreach ($customFieldsData as $k => $v) {
        if ($v === '' || $v === null) continue;
        $label = ucwords(str_replace(['_', '-'], ' ', $k));
        $valEsc = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $html .= "<span class=\"badge\" style=\"background: rgba(0, 113, 227, 0.08); color: var(--apple-blue); font-size: 0.72rem; padding: 2px 7px; border: 1px solid rgba(0, 113, 227, 0.18); border-radius: 6px;\"><strong>{$label}:</strong> {$valEsc}</span>";
    }
    $html .= '</div>';
    return $html;
}

/**
 * Render dynamic custom field column picker checkboxes for module data tables
 */
function render_custom_field_column_pickers(string $module): string {
    $fields = get_custom_fields_for_module($module);
    if (empty($fields)) return '';
    $html = '';
    foreach ($fields as $f) {
        $key = htmlspecialchars($f['field_key'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($f['field_label'], ENT_QUOTES, 'UTF-8');
        $html .= '<label class="col-picker-item">';
        $html .= '<input type="checkbox" data-report-toggle="cf-' . $key . '" checked>';
        $html .= '<span>' . $label . '</span>';
        $html .= '</label>';
    }
    return $html;
}

/**
 * Render dynamic custom field table headers (th) for module data tables
 */
function render_custom_field_table_headers(string $module, string $extraStyles = ''): string {
    $fields = get_custom_fields_for_module($module);
    if (empty($fields)) return '';
    $html = '';
    foreach ($fields as $f) {
        $key = htmlspecialchars($f['field_key'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(strtoupper($f['field_label']), ENT_QUOTES, 'UTF-8');
        $html .= '<th class="col-toggle-cf-' . $key . '" style="min-width: 110px; ' . $extraStyles . '">' . $label . '</th>';
    }
    return $html;
}

/**
 * Render dynamic custom field table data cells (td) for module data tables
 */
function render_custom_field_table_cells(string $module, $customFieldsRaw, string $extraStyles = ''): string {
    $fields = get_custom_fields_for_module($module);
    if (empty($fields)) return '';
    if (is_string($customFieldsRaw)) {
        $values = json_decode($customFieldsRaw, true) ?: [];
    } elseif (is_array($customFieldsRaw)) {
        $values = $customFieldsRaw;
    } else {
        $values = [];
    }
    $html = '';
    foreach ($fields as $f) {
        $key = $f['field_key'];
        $keyEsc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $val = isset($values[$key]) && $values[$key] !== '' && $values[$key] !== null ? (string)$values[$key] : '-';
        $valEsc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        $html .= '<td class="col-toggle-cf-' . $keyEsc . '" style="' . $extraStyles . '">' . $valEsc . '</td>';
    }
    return $html;
}

/**
 * Render dynamic custom field table footer cells (th) for module data tables
 */
function render_custom_field_table_footers(string $module): string {
    $fields = get_custom_fields_for_module($module);
    if (empty($fields)) return '';
    $html = '';
    foreach ($fields as $f) {
        $key = htmlspecialchars($f['field_key'], ENT_QUOTES, 'UTF-8');
        $html .= '<th class="col-toggle-cf-' . $key . '"></th>';
    }
    return $html;
}

/**
 * Financial Computations
 * Balance Collectible = Net Amount - Amount Collected
 */
function compute_balance_collectible(float $netAmount, float $amountCollected): float {
    return max(0, $netAmount - $amountCollected);
}

/**
 * Percentage of Collection Received = (Amount Collected / Contract Amount) * 100 (Capped at 100%)
 */
function compute_percentage_collected(float $contractAmount, float $amountCollected): float {
    if ($contractAmount <= 0) return 0.0;
    $pct = ($amountCollected / $contractAmount) * 100.0;
    return min(100.0, max(0.0, $pct));
}

/**
 * Percentage of Remaining Balance = (Balance Collectible / Contract Amount) * 100
 */
function compute_percentage_balance(float $contractAmount, float $balanceCollectible): float {
    if ($contractAmount <= 0) return 0.0;
    return ($balanceCollectible / $contractAmount) * 100.0;
}

/**
 * Net Amount Helper
 */
function compute_net_amount(float $contractAmount, float $taxAmount = 0.0): float {
    return max(0, $contractAmount - $taxAmount);
}

/**
 * Amount Collected = Contract Amount * (Percentage Collected / 100)
 */
function compute_amount_collected(float $contractAmount, float $percentage): float {
    return $contractAmount * ($percentage / 100.0);
}

/**
 * Categorize Check as Dated Check or Post-Dated Check based on dates:
 * If cheque_date is empty/unknown -> Tag as 'Undated / Pending Date'
 * If cheque_date == issue_date    -> Tag as 'Dated Check'
 * If cheque_date > issue_date     -> Tag as 'Post-Dated Check'
 */
function get_check_maturity_tag(?string $issueDate, ?string $checkDate): string {
    if (empty($checkDate)) {
        return 'Pending Date';
    }
    if (empty($issueDate)) {
        return 'Dated Check';
    }
    $issueTs = strtotime($issueDate);
    $checkTs = strtotime($checkDate);

    if ($checkTs > $issueTs) {
        return 'Post-Dated Check';
    }
    return 'Dated Check';
}


/**
 * Get next formatted check voucher number (e.g. CV-2026-0001)
 */
function get_next_voucher_number(): string {
    $year = date('Y');
    try {
        if (function_exists('get_db')) {
            $db = get_db();
            $maxId = (int)$db->query("SELECT MAX(id) FROM checks")->fetchColumn();
            $count = (int)$db->query("SELECT COUNT(*) FROM checks WHERE voucher_number LIKE 'CV-{$year}-%'")->fetchColumn();
            $seq = max($count + 1, $maxId + 1);
            return sprintf('CV-%s-%04d', $year, $seq);
        }
    } catch (Exception $e) {
        // Fallback
    }
    return sprintf('CV-%s-0001', $year);
}

/**
 * Convert monetary amount to words in Philippine Pesos for official check voucher printing
 */
function number_to_words_pesos($number): string {
    $num = (float)$number;
    $pesos = (int)floor($num);
    $cents = (int)round(($num - $pesos) * 100);

    $ones = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    ];
    $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    ];

    $convertGroup = function($n) use ($ones, $tens, &$convertGroup) {
        $res = '';
        if ($n >= 100) {
            $res .= $ones[(int)floor($n / 100)] . ' Hundred ';
            $n %= 100;
        }
        if ($n >= 20) {
            $res .= $tens[(int)floor($n / 10)] . ' ';
            $n %= 10;
        }
        if ($n > 0) {
            $res .= $ones[$n] . ' ';
        }
        return trim($res);
    };

    if ($pesos == 0) {
        $words = 'Zero Pesos';
    } else {
        $groups = [
            1000000000 => 'Billion',
            1000000 => 'Million',
            1000 => 'Thousand',
            1 => ''
        ];
        $parts = [];
        foreach ($groups as $val => $unit) {
            if ($pesos >= $val) {
                $count = (int)floor($pesos / $val);
                $pesos %= $val;
                $grpStr = $convertGroup($count);
                if (!empty($grpStr)) {
                    $parts[] = $grpStr . ($unit ? ' ' . $unit : '');
                }
            }
        }
        $words = implode(' ', $parts) . ' Pesos';
    }

    if ($cents > 0) {
        $words .= sprintf(' & %02d/100', $cents);
    } else {
        $words .= ' Only';
    }

    return strtoupper(trim($words));
}

// Load authentication and authorization helpers
require_once __DIR__ . '/../includes/auth.php';


