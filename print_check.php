<?php
/**
 * Aarcon Builders - Professional Bank Cheque Printing & Check Writer
 * Supports Direct Bank Cheque Leaf Printing (8" x 3") and Combined Check + CDV Voucher (8.5" x 11")
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

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
    $check = $db->query("SELECT * FROM checks ORDER BY id DESC LIMIT 1")->fetch();
}

$displayVoucherNo = $check['voucher_number'] ?? '';
$serialNumber = '';
if (!empty($displayVoucherNo) && preg_match('/(\d+)$/', $displayVoucherNo, $m)) {
    $serialNumber = $m[1];
} else {
    $serialNumber = sprintf('%05d', $check['id'] ?? 1);
}

// Target maturity date for check
$checkDateToPrint = !empty($check['check_date']) ? $check['check_date'] : ($check['issue_date'] ?? date('Y-m-d'));
$dateFormatted = date('m/d/Y', strtotime($checkDateToPrint));
$dateWordsFormatted = date('F d, Y', strtotime($checkDateToPrint));

$amountVal = (float)($check['amount'] ?? 0);
$amountFigures = number_format($amountVal, 2);
$amountWords = number_to_words_pesos($amountVal);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque #<?= e($check['check_number']) ?> - Aarcon Builders Check Writer</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <style>
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
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 13px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            overflow-x: hidden !important;
        }

        /* Top Calibration & Action Toolbar */
        .check-toolbar {
            background: #0f172a;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar-label {
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tb-select, .tb-input {
            background: #1e293b;
            color: #ffffff;
            border: 1px solid #475569;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
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

        /* Page Layout Container */
        .preview-wrapper {
            display: flex;
            justify-content: center;
            padding: 20px 10px;
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        /* Bank Cheque Container */
        /* Standard Philippine Bank Cheque on Screen */
        .check-sheet {
            background: #ffffff;
            width: 100%;
            max-width: 780px;
            height: 290px;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            position: relative;
            padding: 25px 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid #cbd5e1;
        }

        /* Realistic Bank Watermark / Background Graphics (Toggleable) */
        .check-sheet.with-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 50%, #f1f5f9 100%);
            border: 1.5px solid #64748b;
        }
        .check-sheet.with-bg::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(#94a3b8 0.65px, transparent 0.65px);
            background-size: 8px 8px;
            opacity: 0.25;
            pointer-events: none;
        }

        /* Mode: Combined Voucher & Check on Short Bond Paper (8.5" x 11") */
        .combined-sheet {
            display: none;
            width: 8.5in;
            min-height: 11in;
            background: #ffffff;
            padding: 0.4in 0.5in;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .combined-active .check-sheet {
            display: none;
        }
        .combined-active .combined-sheet {
            display: block;
        }

        /* Cheque Top Section: Bank Details, Crossed Check, Date */
        .cheque-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
        }
        .bank-info-box {
            max-width: 300px;
        }
        .bank-name-label {
            font-size: 13px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .bank-sub-info {
            font-size: 10px;
            color: #475569;
            margin-top: 1px;
        }

        /* Crossed Check Indicator: Two parallel lines with "ACCOUNT PAYEE ONLY" */
        .crossed-check-box {
            position: absolute;
            left: 210px;
            top: -5px;
            border-left: 2px solid #0f172a;
            border-right: 2px solid #0f172a;
            padding: 4px 10px;
            transform: rotate(-15deg);
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #0f172a;
            text-align: center;
            display: none;
        }
        .crossed-active .crossed-check-box {
            display: block;
        }

        /* Date Box (Top Right) */
        .cheque-date-box {
            text-align: right;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .cheque-date-label {
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            letter-spacing: 0.05em;
        }
        .cheque-date-val {
            font-family: Arial, "Helvetica Neue", sans-serif;
            font-size: 14.5px;
            font-weight: 800;
            letter-spacing: 0.12em;
            border-bottom: 1.5px solid #0f172a;
            min-width: 130px;
            text-align: center;
            padding-bottom: 1px;
            color: #000000;
        }

        /* Cheque Middle Section: Payee, Amount in Figures, Amount in Words */
        .cheque-middle {
            margin-top: 6px;
            position: relative;
            z-index: 2;
        }
        .payee-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 8px;
        }
        .payee-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.03em;
            white-space: nowrap;
            color: #1e293b;
        }
        .payee-value {
            flex: 1;
            border-bottom: 1.5px solid #0f172a;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.05em;
            padding-bottom: 2px;
            color: #000000;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .amount-figures-box {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            margin-left: 12px;
        }
        .peso-sign {
            font-size: 15px;
            font-weight: 800;
        }
        .amount-figures-val {
            border: 1.5px solid #0f172a;
            background: #ffffff;
            padding: 3px 10px;
            font-family: Arial, "Helvetica Neue", sans-serif;
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 0.05em;
            min-width: 140px;
            text-align: right;
            border-radius: 3px;
        }

        /* Amount in Words Row */
        .amount-words-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        .words-label {
            font-size: 11px;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: 0.03em;
        }
        .words-value {
            flex: 1;
            border-bottom: 1.5px solid #0f172a;
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            padding-bottom: 2px;
            color: #000000;
            letter-spacing: 0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Cheque Bottom: Account info, Signatures */
        .cheque-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 2;
            margin-top: 4px;
        }
        .cheque-issuer-memo {
            font-size: 10px;
            color: #334155;
            line-height: 1.3;
        }
        .company-issuer-name {
            font-weight: 800;
            font-size: 11px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .micr-line {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            letter-spacing: 0.15em;
            color: #1e293b;
            font-weight: 700;
            margin-top: 3px;
        }

        .signatures-container {
            display: flex;
            gap: 20px;
            text-align: center;
        }
        .sign-slot {
            width: 140px;
        }
        .sign-underline {
            border-bottom: 1.5px solid #0f172a;
            height: 24px;
            margin-bottom: 3px;
        }
        .sign-title {
            font-size: 9px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Perforation Line for Combined Mode */
        .perforation-divider {
            border-top: 1.5px dashed #94a3b8;
            margin: 20px 0;
            position: relative;
            text-align: center;
        }
        .perforation-divider span {
            background: #ffffff;
            padding: 0 10px;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            position: relative;
            top: -7px;
        }

        /* Combined Mode Voucher Section */
        .combined-voucher-box {
            border: 1.5px solid #000000;
            padding: 15px;
            font-size: 12px;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .check-toolbar {
                display: none !important;
            }
            .preview-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Single Cheque Print Mode */
            body:not(.combined-active) .check-sheet {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0.25in 0.35in !important;
                page-break-inside: avoid;
            }
            body:not(.combined-active) .check-sheet.hide-bg-print {
                background: none !important;
                border: none !important;
            }
            body:not(.combined-active) .check-sheet.hide-bg-print::before {
                display: none !important;
            }
            @page {
                size: 8.0in 3.0in landscape;
                margin: 0.1in;
            }

            /* Combined Check + Voucher Print Mode */
            body.combined-active {
                @page {
                    size: letter portrait;
                    margin: 0.4in;
                }
            }
            body.combined-active .combined-sheet {
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="crossed-active">

    <!-- Top Calibration & Action Toolbar -->
    <div class="check-toolbar no-print">
        <div class="toolbar-group">
            <img src="img/logo.png" style="width: 22px; height: 22px; filter: brightness(10);" alt="Logo">
            <span style="font-weight: 700; font-size: 14px;">Aarcon Check Writer</span>
            <span style="font-size: 12px; opacity: 0.75;">(Check #<?= e($check['check_number']) ?>)</span>
        </div>

        <div class="toolbar-group">
            <!-- Mode Switcher -->
            <label class="toolbar-label">Format:</label>
            <select id="selectPrintMode" class="tb-select" onchange="switchPrintMode(this.value)">
                <option value="cheque_only">Cheque Leaf Only (8" x 3")</option>
                <option value="combined">Check + CDV Voucher (8.5" x 11")</option>
            </select>

            <!-- Crossed Check Toggle -->
            <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                <input type="checkbox" id="chkCrossed" checked onchange="toggleCrossed(this.checked)">
                Crossed Check (//)
            </label>

            <!-- Asterisks Protection Toggle -->
            <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                <input type="checkbox" id="chkAsterisks" checked onchange="toggleAsterisks(this.checked)">
                Asterisks Protection
            </label>

            <!-- Hide Artwork for Real Check Leaf Feed -->
            <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; cursor: pointer;" title="Check this if feeding real bank cheque leaves so only text is printed">
                <input type="checkbox" id="chkHideBg" onchange="toggleHideBg(this.checked)">
                Real Cheque Feed (Text Only)
            </label>
        </div>

        <div class="toolbar-group">
            <button type="button" class="btn-tb btn-tb-primary" onclick="window.print();">
                Print Cheque
            </button>
            <a href="print_voucher.php?id=<?= $check['id'] ?>" class="btn-tb btn-tb-outline" title="Switch to Full Aarcon Voucher Format">
                Official Voucher
            </a>
            <a href="checks.php" class="btn-tb btn-tb-outline">
                &larr; Back to Checks
            </a>
        </div>
    </div>

    <!-- Check Display Container -->
    <div class="preview-wrapper">

        <!-- 1. STANDARD CHEQUE LEAF (8" x 3") -->
        <div class="check-sheet with-bg" id="chequeLeaf">
            <!-- Crossed Check Indicator -->
            <div class="crossed-check-box" id="crossedBadge">
                ACCOUNT PAYEE ONLY
            </div>

            <!-- Top Row: Bank Details & Date -->
            <div class="cheque-top">
                <div class="bank-info-box">
                    <div class="bank-name-label"><?= e($check['bank_name']) ?></div>
                    <div class="bank-sub-info">
                        <?php if (!empty($check['project_district'])): ?>
                            <?= e($check['project_district']) ?> Branch &bull;
                        <?php endif; ?>
                        Acct #: <?= e($check['account_number'] ?: '001-234-5678') ?>
                    </div>
                </div>

                <div class="cheque-date-box">
                    <span class="cheque-date-label">DATE:</span>
                    <div class="cheque-date-val"><?= e($dateFormatted) ?></div>
                </div>
            </div>

            <!-- Middle Row: Payee, Amount in Figures, Amount in Words -->
            <div class="cheque-middle">
                <div class="payee-row">
                    <span class="payee-label">PAY TO THE ORDER OF:</span>
                    <div class="payee-value" id="valPayee">** <?= e($check['payee']) ?> **</div>
                    <div class="amount-figures-box">
                        <span class="peso-sign">₱</span>
                        <div class="amount-figures-val" id="valAmountFigures">** <?= e($amountFigures) ?> **</div>
                    </div>
                </div>

                <div class="amount-words-row">
                    <span class="words-label">PESOS:</span>
                    <div class="words-value" id="valAmountWords">** <?= e($amountWords) ?> **</div>
                </div>
            </div>

            <!-- Bottom Row: Issuer info, MICR/Check #, Signatures -->
            <div class="cheque-bottom">
                <div class="cheque-issuer-memo">
                    <div class="company-issuer-name">AARCON BUILDERS</div>
                    <div style="font-size: 10px; color: #475569;">
                        Check #: <?= e($check['check_number']) ?> &bull; Ref: <?= e($check['voucher_number'] ?: 'CV-' . $serialNumber) ?>
                    </div>
                    <div class="micr-line">
                        |: <?= e($check['check_number']) ?> :| 001002345|: <?= e($check['account_number'] ?: '123456789') ?>
                    </div>
                </div>

                <div class="signatures-container">
                    <div class="sign-slot">
                        <div class="sign-underline"></div>
                        <div class="sign-title">Authorized Signatory</div>
                    </div>
                    <div class="sign-slot">
                        <div class="sign-underline"></div>
                        <div class="sign-title">Authorized Signatory</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. COMBINED SHEET: CHECK ON TOP + CDV VOUCHER ON BOTTOM (8.5" x 11") -->
        <div class="combined-sheet" id="combinedSheet">
            <!-- Top Half: Check Leaf -->
            <div style="border: 1px solid #94a3b8; border-radius: 4px; padding: 18px 24px; background: #fafafa; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <strong style="font-size: 14px; color: #1e3a8a; text-transform: uppercase;"><?= e($check['bank_name']) ?></strong>
                        <div style="font-size: 11px; color: #64748b;">Acct: <?= e($check['account_number'] ?: '001-234-5678') ?> &bull; Check #: <?= e($check['check_number']) ?></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; font-weight: 700;">DATE:</span>
                        <strong style="font-size: 14px; border-bottom: 1px solid #000; padding: 0 15px 2px 15px; margin-left: 6px;"><?= e($dateFormatted) ?></strong>
                    </div>
                </div>

                <div style="margin-bottom: 10px; display: flex; align-items: flex-end;">
                    <span style="font-size: 11px; font-weight: 800; margin-right: 8px;">PAY TO THE ORDER OF:</span>
                    <span style="flex: 1; border-bottom: 1.5px solid #000; font-size: 15px; font-weight: 800; padding-bottom: 2px;">
                        ** <?= e($check['payee']) ?> **
                    </span>
                    <span style="font-size: 16px; font-weight: 900; margin-left: 15px; border: 1.5px solid #000; padding: 2px 10px; background: #fff;">
                        ₱ ** <?= e($amountFigures) ?> **
                    </span>
                </div>

                <div style="margin-bottom: 16px; display: flex; align-items: flex-end;">
                    <span style="font-size: 11px; font-weight: 800; margin-right: 8px;">PESOS:</span>
                    <span style="flex: 1; border-bottom: 1.5px solid #000; font-size: 13px; font-weight: 800; padding-bottom: 2px;">
                        ** <?= e($amountWords) ?> **
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 15px;">
                    <div>
                        <strong style="font-size: 11px;">AARCON BUILDERS</strong>
                        <div style="font-size: 10px; color: #475569;">Project: <?= e($check['project_name']) ?></div>
                    </div>
                    <div style="display: flex; gap: 24px; text-align: center;">
                        <div style="width: 140px;">
                            <div style="border-bottom: 1px solid #000; height: 20px;"></div>
                            <span style="font-size: 9px; color: #475569;">Authorized Signature</span>
                        </div>
                        <div style="width: 140px;">
                            <div style="border-bottom: 1px solid #000; height: 20px;"></div>
                            <span style="font-size: 9px; color: #475569;">Authorized Signature</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Perforation Divider -->
            <div class="perforation-divider">
                <span>&bull; CUT OR DETACH HERE &bull; OFFICIAL AARCON BUILDERS VOUCHER STUB &bull;</span>
            </div>

            <!-- Bottom Half: Official Voucher Stub -->
            <div style="border: 1.5px solid #000; padding: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #000; padding-bottom: 10px; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <img src="img/logo.png" style="width: 48px; height: 48px;" alt="Logo">
                        <div>
                            <div style="font-family: 'Times New Roman', serif; font-size: 18px; font-weight: 900;">AARCON BUILDERS</div>
                            <div style="font-size: 10px; font-weight: 800; letter-spacing: 0.1em;">GENERAL CONTRACTOR</div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 14px; font-weight: 900; text-decoration: underline;">CHECK DISBURSEMENT VOUCHER</div>
                        <div style="font-size: 13px; font-weight: 700; color: #dc2626;">No. <?= e($serialNumber) ?></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; margin-bottom: 12px;">
                    <div><strong>Payee:</strong> <?= e($check['payee']) ?></div>
                    <div><strong>Date:</strong> <?= e($dateWordsFormatted) ?></div>
                    <div><strong>Check #:</strong> <?= e($check['check_number']) ?> (<?= e($check['bank_name']) ?>)</div>
                    <div><strong>Project:</strong> <?= e($check['project_name']) ?></div>
                </div>

                <div style="border: 1px solid #000; margin-bottom: 14px;">
                    <div style="background: #f1f5f9; border-bottom: 1px solid #000; padding: 4px 8px; font-weight: 800; font-size: 11px; display: flex; justify-content: space-between;">
                        <span>PARTICULARS</span>
                        <span>AMOUNT</span>
                    </div>
                    <div style="padding: 10px; display: flex; justify-content: space-between; min-height: 70px;">
                        <div>
                            <?= nl2br(e($check['remarks'] ?: "Disbursement for {$check['project_name']} operations.")) ?>
                            <div style="font-size: 11px; color: #64748b; margin-top: 6px;">Category: <?= e($check['category']) ?></div>
                        </div>
                        <strong style="font-size: 15px;">₱ <?= e($amountFigures) ?></strong>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; text-align: center; font-size: 11px; margin-top: 15px;">
                    <div>
                        <div style="border-top: 1px solid #000; padding-top: 3px; font-weight: 700;">Prepared By</div>
                        <div style="font-size: 9px; color: #64748b;">Accounting</div>
                    </div>
                    <div>
                        <div style="border-top: 1px solid #000; padding-top: 3px; font-weight: 700;">Checked By</div>
                        <div style="font-size: 9px; color: #64748b;">Internal Audit</div>
                    </div>
                    <div>
                        <div style="border-top: 1px solid #000; padding-top: 3px; font-weight: 700;">Approved By</div>
                        <div style="font-size: 9px; color: #64748b;">Management</div>
                    </div>
                    <div>
                        <div style="border-top: 1px solid #000; padding-top: 3px; font-weight: 700;">Received By</div>
                        <div style="font-size: 9px; color: #64748b;">Signature / Date</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        const rawPayee = <?= json_encode($check['payee'] ?? '') ?>;
        const rawFigures = <?= json_encode($amountFigures) ?>;
        const rawWords = <?= json_encode($amountWords) ?>;

        function toggleCrossed(enabled) {
            document.body.classList.toggle('crossed-active', enabled);
        }

        function toggleAsterisks(enabled) {
            const prefix = enabled ? '** ' : '';
            const suffix = enabled ? ' **' : '';
            document.getElementById('valPayee').textContent = prefix + rawPayee + suffix;
            document.getElementById('valAmountFigures').textContent = prefix + rawFigures + suffix;
            document.getElementById('valAmountWords').textContent = prefix + rawWords + suffix;
        }

        function toggleHideBg(enabled) {
            const leaf = document.getElementById('chequeLeaf');
            if (enabled) {
                leaf.classList.remove('with-bg');
                leaf.classList.add('hide-bg-print');
            } else {
                leaf.classList.add('with-bg');
                leaf.classList.remove('hide-bg-print');
            }
        }

        function switchPrintMode(mode) {
            if (mode === 'combined') {
                document.body.classList.add('combined-active');
            } else {
                document.body.classList.remove('combined-active');
            }
        }
    </script>
</body>
</html>
