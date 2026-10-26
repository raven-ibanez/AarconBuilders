<?php
/**
 * Aarcon Builders - Official Printable Voucher
 * Exact replica of the physical Aarcon Builders Voucher form
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
if (is_admin()) {
    set_flash('info', 'As an Administrator, you only have access to the Administration module.');
    if (php_sapi_name() !== 'cli') {
        header('Location: admin.php');
        exit;
    }
}

$db = get_db();

$checkId = $_GET['id'] ?? null;
$voucherNo = $_GET['voucher'] ?? null;

$check = null;
if (!empty($checkId)) {
    $stmt = $db->prepare("SELECT * FROM checks WHERE id = ?");
    $stmt->execute([$checkId]);
    $check = $stmt->fetch();
} elseif (!empty($voucherNo)) {
    $stmt = $db->prepare("SELECT * FROM checks WHERE voucher_number = ? LIMIT 1");
    $stmt->execute([$voucherNo]);
    $check = $stmt->fetch();
}

if (!$check) {
    // Fallback: fetch latest check or display sample
    $check = $db->query("SELECT * FROM checks ORDER BY id DESC LIMIT 1")->fetch();
}

// Extract sequential numeric series for the red "No. 18443" voucher number
$displayVoucherNo = $check['voucher_number'] ?? '';
$serialNumber = '';
if (!empty($displayVoucherNo) && preg_match('/(\d+)$/', $displayVoucherNo, $m)) {
    $serialNumber = $m[1];
} else {
    $serialNumber = sprintf('%05d', $check['id'] ?? 1);
}

$issueDateFormatted = !empty($check['issue_date']) ? date('F d, Y', strtotime($check['issue_date'])) : date('F d, Y');
$checkDateFormatted = !empty($check['check_date']) ? date('F d, Y', strtotime($check['check_date'])) : $issueDateFormatted;
$amountFigures = number_format((float)($check['amount'] ?? 0), 2);
$amountWords = number_to_words_pesos($check['amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher <?= e($displayVoucherNo ?: $serialNumber) ?> - Aarcon Builders</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <style>
        /* CSS Reset & Print Geometry */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            overflow-x: hidden !important;
            max-width: 100vw !important;
            width: 100% !important;
        }

        /* Eliminate all horizontal scrollbars */
        ::-webkit-scrollbar:horizontal {
            display: none !important;
            height: 0px !important;
            width: 0px !important;
            background: transparent !important;
        }

        body {
            background: #e2e8f0;
            color: #000000;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            overflow-x: hidden !important;
        }

        /* Top Action Bar (Hidden on Print) */
        .print-toolbar {
            background: #0f172a;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            position: sticky;
            top: 0;
            z-index: 1000;
            max-width: 100vw;
            box-sizing: border-box;
        }
        .print-toolbar-title {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .print-toolbar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-tb {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
            transition: all 0.15s;
        }
        .btn-tb-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-tb-primary:hover {
            background: #1d4ed8;
        }
        .btn-tb-outline {
            background: transparent;
            color: #cbd5e1;
            border-color: #475569;
        }
        .btn-tb-outline:hover {
            background: #1e293b;
            color: #ffffff;
        }

        /* Sheet Container (Screen: Clean Responsive Box, Zero Horizontal Scrollbars) */
        .sheet-wrapper {
            display: flex;
            justify-content: center;
            padding: 20px 10px;
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }
        .sheet {
            background: #ffffff;
            width: 100%;
            max-width: 780px;
            min-height: auto;
            padding: 24px 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden !important;
            box-sizing: border-box;
        }

        /* Letterhead Section */
        .voucher-header {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        .voucher-logo-box {
            position: absolute;
            left: 0;
            top: 2px;
        }
        .voucher-logo-box img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .voucher-company-info {
            text-align: center;
            padding: 0 50px;
            max-width: 100%;
            box-sizing: border-box;
        }
        .voucher-company-name {
            font-family: "Times New Roman", Times, Georgia, serif;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 0.05em;
            color: #000000;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .voucher-company-tag {
            font-family: Arial, "Helvetica Neue", sans-serif;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #000000;
            text-transform: uppercase;
            margin-top: 3px;
            margin-bottom: 3px;
        }
        .voucher-company-address {
            font-size: 10.5px;
            color: #111111;
            line-height: 1.35;
        }

        /* Metadata & Title Grid */
        .voucher-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 8px;
            font-size: 12.5px;
            font-weight: 700;
            width: 100%;
            box-sizing: border-box;
            gap: 12px;
        }
        .meta-field {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            flex: 1;
            min-width: 0;
        }
        .meta-label {
            font-weight: 800;
            white-space: nowrap;
            letter-spacing: 0.02em;
            flex-shrink: 0;
        }
        .meta-line {
            border-bottom: 1px solid #000000;
            flex: 1;
            min-width: 40px;
            padding-bottom: 1px;
            font-weight: 700;
            color: #000000;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .voucher-title-center {
            text-align: center;
            margin: 8px 0 8px 0;
        }
        .voucher-title-text {
            font-family: Arial, "Helvetica Neue", sans-serif;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .voucher-serial-no {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: flex-end;
            gap: 6px;
        }
        .serial-red {
            color: #dc2626;
            font-family: "Courier New", Courier, monospace;
            font-size: 19px;
            font-weight: 900;
            letter-spacing: 0.05em;
        }

        .pay-to-row {
            margin-top: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            display: flex;
            align-items: flex-end;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .pay-to-line {
            border-bottom: 1px solid #000000;
            flex: 1;
            min-width: 0;
            padding-bottom: 2px;
            font-size: 13.5px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Particulars & Amount Table Box */
        .voucher-box {
            border: 1.5px solid #000000;
            display: flex;
            flex-direction: column;
            min-height: 440px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .voucher-box-header {
            display: grid;
            grid-template-columns: 1fr 140px;
            border-bottom: 1.5px solid #000000;
            font-weight: 800;
            font-size: 12.5px;
            letter-spacing: 0.05em;
            text-align: center;
        }
        .voucher-box-header div {
            padding: 6px 10px;
        }
        .voucher-box-header div:first-child {
            border-right: 1.5px solid #000000;
        }

        .voucher-box-body {
            display: grid;
            grid-template-columns: 1fr 140px;
            flex: 1;
            position: relative;
        }
        .col-particulars {
            border-right: 1.5px solid #000000;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }
        .particulars-content {
            font-size: 12.5px;
            line-height: 1.45;
        }
        .particulars-remarks {
            font-weight: 600;
            white-space: pre-wrap;
            margin-bottom: 12px;
            word-break: break-word;
        }
        .particulars-words {
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            color: #1e293b;
            margin-top: 8px;
            border-top: 1px dashed #94a3b8;
            padding-top: 6px;
            word-break: break-word;
        }

        .col-amount {
            padding: 12px 14px;
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
            min-width: 0;
        }
        .amount-top-figure {
            font-family: Arial, "Helvetica Neue", sans-serif;
            font-size: 14.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* Bottom Row inside Box (Account Charge & Total Amount) */
        .voucher-box-footer {
            border-top: 1.5px solid #000000;
            display: grid;
            grid-template-columns: 1fr 140px;
        }
        .footer-account-charge {
            border-right: 1.5px solid #000000;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .footer-account-line {
            display: flex;
            align-items: flex-end;
            gap: 6px;
        }
        .footer-amount-total {
            padding: 6px 12px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            font-size: 15px;
            font-weight: 900;
            white-space: nowrap;
        }

        /* Signatures Block */
        .voucher-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 24px;
            row-gap: 16px;
            margin-top: 8px;
            font-size: 11.5px;
            font-weight: 800;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .sig-entry {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            min-width: 0;
        }
        .sig-label {
            white-space: nowrap;
            font-weight: 800;
            font-size: 11px;
            flex-shrink: 0;
        }
        .sig-underline {
            border-bottom: 1px solid #000000;
            flex: 1;
            height: 18px;
            min-width: 20px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .sheet-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }
            .sheet {
                box-shadow: none !important;
                width: 100% !important;
                height: 100% !important;
                min-height: auto !important;
                padding: 0.35in 0.45in !important;
                page-break-inside: avoid;
            }
            @page {
                size: letter portrait;
                margin: 0.3in 0.4in;
            }
        }
    </style>
</head>
<body>

    <!-- Print Navigation Toolbar -->
    <div class="print-toolbar no-print">
        <div class="print-toolbar-title">
            <img src="img/logo.png" style="width: 22px; height: 22px; filter: brightness(10);" alt="Logo">
            <span>Aarcon Builders &bull; Voucher Document</span>
            <span style="font-weight: 400; opacity: 0.75; font-size: 12px;">(No. <?= e($serialNumber) ?>)</span>
        </div>
        <div class="print-toolbar-actions">
            <button type="button" class="btn-tb btn-tb-primary" onclick="window.print();">
                Print Voucher
            </button>
            <a href="print_check.php?id=<?= $check['id'] ?>" class="btn-tb btn-tb-outline" title="Switch to Check Writer / Bank Cheque Format">
                View / Print Check
            </a>
            <a href="vouchers.php" class="btn-tb btn-tb-outline">
                &larr; Back to Vouchers
            </a>
        </div>
    </div>

    <!-- Official Printable Sheet (8.5" x 11" Short Bond Paper) -->
    <div class="sheet-wrapper">
        <div class="sheet">
            <div>
                <!-- 1. Header with Official Logo & Center Letterhead -->
                <div class="voucher-header">
                    <div class="voucher-logo-box">
                        <img src="img/logo.png" alt="Aarcon Builders Logo">
                    </div>
                    <div class="voucher-company-info">
                        <div class="voucher-company-name">AARCON BUILDERS</div>
                        <div class="voucher-company-tag">GENERAL CONTRACTOR</div>
                        <div class="voucher-company-address">
                            Unit 203, FSS Bldg. I, #20 Sct. Tuazon cor.<br>
                            Sct. Castor Sts., Quezon City<br>
                            Telefax No.: 8441-8225 &bull; Email: aarconbuilders@gmail.com<br>
                            Website: aarconbuilders.com
                        </div>
                    </div>
                </div>

                <!-- 2. Metadata Section: CHECK NO., PROJECT, DATE, VOUCHER NO. -->
                <div class="voucher-meta-row">
                    <div class="meta-field">
                        <span class="meta-label">CHECK NO.:</span>
                        <div class="meta-line"><?= e($check['check_number']) ?></div>
                    </div>
                    <div class="meta-field">
                        <span class="meta-label">DATE:</span>
                        <div class="meta-line" style="min-width: 160px;"><?= e($checkDateFormatted) ?></div>
                    </div>
                </div>

                <div class="voucher-meta-row" style="margin-top: 4px;">
                    <div class="meta-field" style="flex: 1; margin-right: 20px;">
                        <span class="meta-label">PROJECT:</span>
                        <div class="meta-line" style="width: 100%;">
                            <?php 
                                $pName = trim($check['project_name'] ?? '');
                                $pDist = trim($check['project_district'] ?? '');
                                if (!empty($pName) && !empty($pDist)) {
                                    echo e($pName) . ' &bull; <span style="font-size: 11px;">' . e($pDist) . '</span>';
                                } elseif (!empty($pName)) {
                                    echo e($pName);
                                } elseif (!empty($pDist)) {
                                    echo e($pDist);
                                } else {
                                    echo 'General Operations';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="voucher-serial-no">
                        <span>No.</span>
                        <span class="serial-red"><?= e($serialNumber) ?></span>
                    </div>
                </div>

                <!-- Centered Document Title -->
                <div class="voucher-title-center">
                    <span class="voucher-title-text">VOUCHER</span>
                </div>

                <!-- 3. PAY TO Row -->
                <div class="pay-to-row">
                    <span class="meta-label">PAY TO:</span>
                    <div class="pay-to-line"><?= e($check['payee']) ?></div>
                </div>

                <!-- 4. PARTICULARS & AMOUNT BOX -->
                <div class="voucher-box">
                    <div class="voucher-box-header">
                        <div>PARTICULARS</div>
                        <div>AMOUNT</div>
                    </div>
                    
                    <div class="voucher-box-body">
                        <div class="col-particulars">
                            <div class="particulars-content">
                                <div class="particulars-remarks">
                                    <?= nl2br(e($check['remarks'] ?: "Disbursement for {$check['project_name']} operations.")) ?>
                                </div>

                                <div style="font-size: 12px; color: #334155; margin-top: 14px;">
                                    <strong>Bank:</strong> <?= e($check['bank_name']) ?>
                                    <?php if (!empty($check['account_number'])): ?>
                                        &bull; <strong>Acct #:</strong> <?= e($check['account_number']) ?>
                                    <?php endif; ?>
                                    &bull; <strong>Ref:</strong> <?= e($check['voucher_number'] ?: 'CV-' . $serialNumber) ?>
                                </div>
                            </div>

                            <div class="particulars-words">
                                Amount in words: <strong><?= e($amountWords) ?></strong>
                            </div>
                        </div>

                        <div class="col-amount">
                            <div class="amount-top-figure">
                                ₱ <?= e($amountFigures) ?>
                            </div>
                            <div style="font-size: 11px; color: #475569; font-style: italic;">
                                Net Payable
                            </div>
                        </div>
                    </div>

                    <!-- Footer of the Box -->
                    <div class="voucher-box-footer">
                        <div class="footer-account-charge">
                            <div class="footer-account-line">
                                <span>ACCOUNT CHARGE:</span>
                                <span style="font-weight: 700; text-transform: uppercase;"><?= e($check['category'] ?: 'Materials & Operations') ?></span>
                            </div>
                            <div class="footer-account-line" style="margin-top: 2px;">
                                <span>AMOUNT:</span>
                            </div>
                        </div>
                        <div class="footer-amount-total">
                            ₱ <?= e($amountFigures) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. SIGNATURES ROW (PREPARED BY, CHECKED BY, RECEIVED BY, NOTED BY) -->
            <div class="voucher-signatures">
                <div class="sig-entry">
                    <span class="sig-label">PREPARED BY:</span>
                    <div class="sig-underline"></div>
                </div>
                <div class="sig-entry">
                    <span class="sig-label">CHECKED BY:</span>
                    <div class="sig-underline"></div>
                </div>
                <div class="sig-entry">
                    <span class="sig-label">RECEIVED BY:</span>
                    <div class="sig-underline"></div>
                </div>
                <div class="sig-entry">
                    <span class="sig-label">NOTED BY:</span>
                    <div class="sig-underline"></div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
