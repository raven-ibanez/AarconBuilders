<?php if (empty($hidePrintSignatures)): ?>
    <!-- Official Printable Signatures Block (Nested inside <main> to flow immediately beneath document tables) -->
    <div class="print-signatures" style="display: none;">
        <div>
            <div class="sig-space" style="height: 30px;"></div>
            <div class="sig-line">Prepared By</div>
            <div class="sig-sub" style="font-size: 8pt; color: #475569;">Cashier / Accounting</div>
        </div>
        <div>
            <div class="sig-space" style="height: 30px;"></div>
            <div class="sig-line">Checked By</div>
            <div class="sig-sub" style="font-size: 8pt; color: #475569;">Internal Audit</div>
        </div>
        <div>
            <div class="sig-space" style="height: 30px;"></div>
            <div class="sig-line">Approved By</div>
            <div class="sig-sub" style="font-size: 8pt; color: #475569;">General Manager</div>
        </div>
    </div>
<?php endif; ?>
        </main>
    </div> <!-- /.app-main -->
</div> <!-- /.app-layout -->

<?php if (!is_admin()): ?>
    <?php
    $footerDb = get_db();
    $districtsList = get_project_districts();
    $bankBranchesList = get_bank_branches();
    $expenseCategoriesList = get_expense_categories();
    $paymentMethodsList = get_payment_methods();
    $priorityStatusesList = get_priority_statuses();
    $voucherSuggestions = [];
    try {
        $voucherSuggestions = $footerDb->query("SELECT voucher_no, requested_by, amount FROM cash_requests ORDER BY id DESC LIMIT 50")->fetchAll();
    } catch (Exception $e) {
        // Fallback
    }
    ?>

    <!-- Expense Categories Datalist for autocomplete -->
    <datalist id="expenseCategoriesDatalist">
        <?php foreach ($expenseCategoriesList as $cKey => $cLabel): ?>
            <option value="<?= e($cKey) ?>"><?= e($cLabel) ?></option>
        <?php endforeach; ?>
    </datalist>

    <!-- Datalist for Vouchers -->
    <datalist id="voucherDatalist">
        <?php foreach ($voucherSuggestions as $vs): ?>
            <option value="<?= e($vs['voucher_no']) ?>"><?= e($vs['voucher_no']) ?> &bull; <?= e($vs['requested_by']) ?> (<?= format_money($vs['amount']) ?>)</option>
        <?php endforeach; ?>
    </datalist>

    <!-- Modal: Add Check & Voucher (Unified Form: Creating a voucher creates the check) -->
    <div class="modal-overlay" id="modalAddCheck">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalAddCheckTitle">New Check &amp; Disbursement Voucher</h3>
                    <p style="margin: 0.2rem 0 0 0; font-size: 0.78rem; color: var(--text-muted);">Creates a physical check record and its linked Check Disbursement Voucher (CDV)</p>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formAddCheck">
                <input type="hidden" name="action" value="add_check" id="checkActionInput">
                <div class="modal-body">
                    <div class="form-grid">
                        <!-- Voucher Number (Front & Center) -->
                        <div class="form-group">
                            <label class="form-label">Voucher Number (CDV #) <span class="req">*</span></label>
                            <input type="text" name="voucher_number" id="checkVoucherNumber" class="form-control" style="font-family: var(--font-mono); font-weight: 700; color: #dc2626;" value="<?= e(get_next_voucher_number()) ?>" placeholder="e.g. CV-<?= date('Y') ?>-0001" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Check Disbursement Voucher tracking number</small>
                        </div>
                        
                        <!-- Bank Name & Branch Dropdown (Auto-fills first 3 digits of Check Number) -->
                        <div class="form-group">
                            <label class="form-label">Bank Name &amp; Branch <span class="req">*</span></label>
                            <select name="bank_name" id="checkBankBranchSelect" class="form-control" required>
                                <option value="" disabled selected>-- Select Bank &amp; Branch --</option>
                                <?php foreach ($bankBranchesList as $bb): ?>
                                    <option value="<?= e($bb['bank'] . ' (' . $bb['branch'] . ')') ?>" data-code="<?= e($bb['code']) ?>">
                                        <?= e($bb['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other Bank" data-code="">Other Bank (Manual)</option>
                            </select>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Select branch to prefix check number</small>
                        </div>

                        <!-- Check Number (First 3 digits auto-prefixed by selected Bank & Branch) -->
                        <div class="form-group">
                            <label class="form-label">Check Number <span class="req">*</span></label>
                            <div style="display: flex; gap: 0.35rem; align-items: center;">
                                <input type="text" id="bankPrefixDisplay" class="form-control" style="width: 70px; text-align: center; font-family: var(--font-mono); font-weight: 700; background: var(--bg-subtle);" placeholder="000" readonly>
                                <input type="text" name="check_number" id="checkNumberInput" class="form-control" placeholder="e.g. 001-0019281" maxlength="14" style="font-family: var(--font-mono); font-weight: 700;" required>
                            </div>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">First 3 digits identify the bank &amp; branch. Maximum 12 digits total.</small>
                        </div>

                        <!-- Amount -->
                        <div class="form-group">
                            <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>

                        <!-- Payee / Supplier -->
                        <div class="form-group col-span-2">
                            <label class="form-label">Payee / Supplier <span class="req">*</span></label>
                            <input type="text" name="payee" class="form-control" placeholder="Supplier, contractor or vendor name" required>
                        </div>

                        <!-- Issue Date (MANDATORY & REQUIRED) -->
                        <div class="form-group">
                            <label class="form-label">Issue Date <span class="req">*</span></label>
                            <input type="date" name="issue_date" id="checkIssueDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Date voucher &amp; check written/signed</small>
                        </div>

                        <!-- Check Date / Maturity Date (Editable & Optional for Unknown Dates) -->
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="form-label" style="margin-bottom: 0;">Check Date (Post-Date / Maturity)</label>
                                <button type="button" id="btnClearCheckDate" style="background: none; border: none; font-size: 0.72rem; color: var(--text-muted); cursor: pointer; text-decoration: underline;" title="Clear date if unknown yet">
                                    Date Unknown Yet
                                </button>
                            </div>
                            <input type="date" name="check_date" id="checkMaturityDate" class="form-control" style="margin-top: 0.35rem;" value="<?= date('Y-m-d') ?>">
                            <small id="checkMaturityHint" style="font-weight: 600; font-size: 0.72rem; color: var(--color-primary); display: block; margin-top: 2px;">Tag: Dated Check</small>
                            <small style="color: var(--text-muted); font-size: 0.70rem;">Leave blank if maturity date isn't known yet (editable anytime).</small>
                        </div>

                        <!-- Project District Dropdown -->
                        <div class="form-group">
                            <label class="form-label">Project District</label>
                            <select name="project_district" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Priority Status -->
                        <div class="form-group">
                            <label class="form-label">Priority Status</label>
                            <select name="priority_status" class="form-control">
                                <?php foreach ($priorityStatusesList as $pKey => $pLabel): ?>
                                    <option value="<?= e($pKey) ?>" <?= $pKey === 'Standard' ? 'selected' : '' ?>><?= e($pLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" list="expenseCategoriesDatalist" class="form-control" placeholder="Materials, Rental, Subcon" value="Materials">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Project / Site</label>
                            <input type="text" name="project_name" class="form-control" placeholder="Project name or site">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Remarks / Particulars</label>
                            <textarea name="remarks" class="form-control" placeholder="Purpose, invoice # or particulars..."></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Checks & Vouchers -->
                        <?= render_custom_fields_html('checks') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCheck')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitCheck">Save Check &amp; Voucher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Fund Check Workflow Trigger (Cheque Date input required before funding) -->
    <div class="modal-overlay" id="modalFundCheck">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Approve &amp; Fund Check</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formFundCheck">
                <input type="hidden" name="action" value="fund_check">
                <input type="hidden" name="check_id" id="fundCheckId" value="">
                <div class="modal-body">
                    <div style="background: var(--bg-subtle); border-radius: var(--radius-sm); padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <span style="font-weight: 700; font-size: 1.05rem;" id="fundCheckNumberText">Check #---</span>
                            <span class="badge badge-pending" id="fundCheckPayeeText">Payee</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted);">
                            <span>Amount: <strong id="fundCheckAmountText" style="color: var(--text-main); font-family: var(--font-mono);">₱0.00</strong></span>
                            <span>Issue Date: <span id="fundCheckIssueDateText">-</span></span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <!-- Required Cheque Date Input -->
                        <div class="form-group col-span-2">
                            <label class="form-label">Cheque Date (Funding / Maturity Date) <span class="req">*</span></label>
                            <input type="date" name="cheque_date" id="fundChequeDateInput" class="form-control" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">
                                Workflow Requirement: Confirm or update the cheque date before status changes to Funded.
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Priority Status</label>
                            <select name="priority_status" id="fundPriorityStatus" class="form-control">
                                <option value="Standard">Standard</option>
                                <option value="Prioritized">Prioritized</option>
                                <option value="Hold">Hold</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Funded By / Approver</label>
                            <input type="text" name="funded_by" class="form-control" value="<?= e(DEFAULT_APPROVER) ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalFundCheck')">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm &amp; Fund Check</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Check Date (Whenever check date is known or changed) -->
    <div class="modal-overlay" id="modalEditCheckDate">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Edit / Update Check Date</h3>
                    <p style="margin: 0.2rem 0 0 0; font-size: 0.78rem; color: var(--text-muted);">Set or modify the check maturity / dated release date</p>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formEditCheckDate">
                <input type="hidden" name="action" value="update_check_date">
                <input type="hidden" name="check_id" id="editCheckDateId" value="">
                <input type="hidden" id="editCheckIssueDateVal" value="">
                <div class="modal-body">
                    <div style="background: var(--bg-subtle); border-radius: var(--radius-sm); padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <span style="font-weight: 700; font-size: 1.05rem;" id="editCheckNumberText">Check #---</span>
                            <span class="badge badge-pending" id="editCheckPayeeText">Payee</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted);">
                            <span>Amount: <strong id="editCheckAmountText" style="color: var(--text-main); font-family: var(--font-mono);">₱0.00</strong></span>
                            <span>Issue Date: <span id="editCheckIssueDateText">-</span></span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="form-label" style="margin-bottom: 0;">Check Date (Maturity / Post-Date)</label>
                                <button type="button" id="btnEditClearDate" style="background: none; border: none; font-size: 0.72rem; color: var(--text-muted); cursor: pointer; text-decoration: underline;">
                                    Clear (Date Unknown Yet)
                                </button>
                            </div>
                            <input type="date" name="check_date" id="editCheckDateInput" class="form-control" style="margin-top: 0.35rem;">
                            <small id="editCheckDateHint" style="font-weight: 600; font-size: 0.75rem; color: var(--color-primary); display: block; margin-top: 4px;">Tag: Dated Check</small>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">If matching issue date, tags as Dated Check. If future date, tags as Post-Dated Check.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalEditCheckDate')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Check Date</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Cash Request -->
    <div class="modal-overlay" id="modalAddCashRequest">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">New Cash Request / Voucher</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST">
                <input type="hidden" name="action" value="add_cash_request">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Voucher # (Optional)</label>
                            <input type="text" name="voucher_no" class="form-control" placeholder="Leave blank to auto-create">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date <span class="req">*</span></label>
                            <input type="date" name="request_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Requested By <span class="req">*</span></label>
                            <input type="text" name="requested_by" class="form-control" placeholder="Name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department / Project <span class="req">*</span></label>
                            <input type="text" name="department_or_project" class="form-control" placeholder="Site or Office" required>
                        </div>
                        
                        <!-- District Dropdown -->
                        <div class="form-group col-span-2">
                            <label class="form-label">Project District</label>
                            <select name="project_district" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Amount Needed (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Purpose / Description <span class="req">*</span></label>
                            <textarea name="purpose" class="form-control" placeholder="e.g. Office supplies, site fuel, emergency tool repair..." required></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Petty Cash Requests -->
                        <?= render_custom_fields_html('cash_requests') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCashRequest')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Collection with Automatic Financial Computations -->
    <div class="modal-overlay" id="modalAddCollection">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Record Project Collection (Cash In)</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formAddCollection">
                <input type="hidden" name="action" value="add_collection">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Contract ID <span class="req">*</span></label>
                            <input type="text" name="contract_id" id="colContractId" class="form-control" placeholder="e.g. CNT-2026-088" style="font-family: var(--font-mono); font-weight: 700; color: #dc2626;" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Unique contract identifier (Required)</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Receipt / OR # (Optional)</label>
                            <input type="text" name="receipt_no" class="form-control" placeholder="Leave blank to auto-create">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Collection Date <span class="req">*</span></label>
                            <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expected Date of Collection</label>
                            <input type="date" name="expected_collection_date" id="colExpectedDate" class="form-control">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Target clearing / collection date</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Client / Customer <span class="req">*</span></label>
                            <input type="text" name="client_name" class="form-control" placeholder="Client or Agency name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Project / Contract Name <span class="req">*</span></label>
                            <input type="text" name="project_name" class="form-control" placeholder="Project name" required>
                        </div>

                        <!-- District Dropdown -->
                        <div class="form-group">
                            <label class="form-label">Project District</label>
                            <select name="project_district" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Financial Figures (No Tax - Pure Net, Collected & Balance) -->
                        <div class="form-group">
                            <label class="form-label">Contract Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="contract_amount" id="colContractAmount" class="form-control" placeholder="0.00" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Total contract project valuation</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Net Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="net_amount" id="colNetAmount" class="form-control" placeholder="0.00" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Net billing / payable value</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount Collected (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" id="colAmountCollected" class="form-control" placeholder="0.00" required style="font-size: 1.05rem; font-weight: 700; color: #16a34a;">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Actual collection received for this payment (cannot exceed Contract Amount)</small>
                            <div id="colAmountWarning" style="display: none; color: #dc2626; font-size: 0.72rem; font-weight: 600; margin-top: 3px;">Amount Collected cannot exceed Contract Amount.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deduction (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="deduction" id="colDeduction" class="form-control" placeholder="0.00">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Retention, penalties, or charges</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Net Collections (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="net_collections" id="colNetCollections" class="form-control" placeholder="0.00" style="font-size: 1rem; font-weight: 700; color: #dc2626;">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Net realized collection (Amount &minus; Deduction)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Billing on Process (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="billing_on_process" id="colBillingOnProcess" class="form-control" placeholder="0.00">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Optional. Auto-defaults to Amount if In Process</small>
                        </div>

                        <!-- Realtime Computation Summary Callout -->
                        <div class="form-group col-span-2" style="background: rgba(220, 38, 38, 0.04); border: 1px solid rgba(220, 38, 38, 0.15); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.85rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Balance Collectible (Net &minus; Collected):</span>
                                <strong id="calcBalanceDisplay" style="color: var(--color-warning); font-size: 1.05rem; font-family: var(--font-mono);">₱ 0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Collections Received % (Collected / Contract):</span>
                                <strong id="calcPercentDisplay" style="color: var(--color-success); font-weight: 700; font-family: var(--font-mono);">0.00%</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); font-weight: 500;">Remaining Balance % (Balance / Contract):</span>
                                <strong id="calcBalancePercentDisplay" style="color: var(--text-main); font-weight: 600; font-family: var(--font-mono);">0.00%</strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="req">*</span></label>
                            <select name="payment_method" class="form-control" required>
                                <?php foreach ($paymentMethodsList as $mKey => $mLabel): ?>
                                    <option value="<?= e($mKey) ?>"><?= e($mLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Collection Status</label>
                            <select name="status" class="form-control">
                                <option value="collected" selected>Collected (Cashed / Realized)</option>
                                <option value="in process">In Process (Pending Clearing)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reference #</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Deposit ref or check #">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Received By</label>
                            <input type="text" name="received_by" class="form-control" value="Main Cashier">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Notes / Particulars</label>
                            <textarea name="notes" class="form-control" placeholder="Optional particulars..."></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Collections -->
                        <?= render_custom_fields_html('collections') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCollection')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Collection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Collection (Requirement: Edit collection after entering & status editable) -->
    <div class="modal-overlay" id="modalEditCollection">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Edit Project Collection</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalEditCollection')">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formEditCollection">
                <input type="hidden" name="action" value="edit_collection">
                <input type="hidden" name="collection_id" id="editColId" value="">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Contract ID <span class="req">*</span></label>
                            <input type="text" name="contract_id" id="editColContractId" class="form-control" placeholder="e.g. CNT-2026-088" style="font-family: var(--font-mono); font-weight: 700; color: #dc2626;" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Receipt / OR #</label>
                            <input type="text" name="receipt_no" id="editColReceiptNo" class="form-control" placeholder="OR or Receipt #">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Collection Date <span class="req">*</span></label>
                            <input type="date" name="collection_date" id="editColDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expected Date of Collection</label>
                            <input type="date" name="expected_collection_date" id="editColExpectedDate" class="form-control">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Target clearing / collection date</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Client / Customer <span class="req">*</span></label>
                            <input type="text" name="client_name" id="editColClient" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Project / Contract Name <span class="req">*</span></label>
                            <input type="text" name="project_name" id="editColProject" class="form-control" required>
                        </div>

                        <!-- District Dropdown -->
                        <div class="form-group">
                            <label class="form-label">Project District</label>
                            <select name="project_district" id="editColDistrict" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Financial Figures (No Tax - Pure Net, Collected & Balance) -->
                        <div class="form-group">
                            <label class="form-label">Contract Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="contract_amount" id="editColContractAmount" class="form-control" placeholder="0.00" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Total contract project valuation</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Net Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="net_amount" id="editColNetAmount" class="form-control" placeholder="0.00" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Net billing / payable value</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount Collected (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" id="editColAmountCollected" class="form-control" placeholder="0.00" required style="font-size: 1.05rem; font-weight: 700; color: #16a34a;">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Actual collection received for this payment (cannot exceed Contract Amount)</small>
                            <div id="editColAmountWarning" style="display: none; color: #dc2626; font-size: 0.72rem; font-weight: 600; margin-top: 3px;">Amount Collected cannot exceed Contract Amount.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deduction (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="deduction" id="editColDeduction" class="form-control" placeholder="0.00">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Retention, penalties, or charges</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Net Collections (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="net_collections" id="editColNetCollections" class="form-control" placeholder="0.00" style="font-size: 1rem; font-weight: 700; color: #dc2626;">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Net realized collection (Amount &minus; Deduction)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Billing on Process (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="billing_on_process" id="editColBillingOnProcess" class="form-control" placeholder="0.00">
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Amount in billing process</small>
                        </div>

                        <!-- Realtime Computation Summary Callout -->
                        <div class="form-group col-span-2" style="background: rgba(220, 38, 38, 0.04); border: 1px solid rgba(220, 38, 38, 0.15); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.85rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Balance Collectible (Net &minus; Collected):</span>
                                <strong id="editCalcBalanceDisplay" style="color: var(--color-warning); font-size: 1.05rem; font-family: var(--font-mono);">₱ 0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Collections Received % (Collected / Contract):</span>
                                <strong id="editCalcPercentDisplay" style="color: var(--color-success); font-weight: 700; font-family: var(--font-mono);">0.00%</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); font-weight: 500;">Remaining Balance % (Balance / Contract):</span>
                                <strong id="editCalcBalancePercentDisplay" style="color: var(--text-main); font-weight: 600; font-family: var(--font-mono);">0.00%</strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="req">*</span></label>
                            <select name="payment_method" id="editColMethod" class="form-control" required>
                                <?php foreach ($paymentMethodsList as $mKey => $mLabel): ?>
                                    <option value="<?= e($mKey) ?>"><?= e($mLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status editable as requested -->
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700; color: #dc2626;">Collection Status <span class="req">*</span></label>
                            <select name="status" id="editColStatus" class="form-control" style="border: 2px solid #dc2626; font-weight: 600;">
                                <option value="collected">Collected (Cashed / Realized)</option>
                                <option value="in process">In Process (Pending Clearing)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reference #</label>
                            <input type="text" name="reference_no" id="editColRefNo" class="form-control" placeholder="Deposit ref or check #">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Received By</label>
                            <input type="text" name="received_by" id="editColReceivedBy" class="form-control">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Notes / Particulars</label>
                            <textarea name="notes" id="editColNotes" class="form-control" placeholder="Optional particulars..."></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Collections (Edit) -->
                        <?= render_custom_fields_html('collections', [], 'edit_col') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalEditCollection')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Collection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Funded Check (Editable from Disbursement Report & Checks) -->
    <div class="modal-overlay" id="modalEditCheck">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Edit Check Record</h3>
                    <p style="margin: 0.2rem 0 0; font-size: 0.78rem; color: var(--text-muted);">Update check details, maturity date, bank or amounts</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('modalEditCheck')">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formEditCheck">
                <input type="hidden" name="action" value="edit_check">
                <input type="hidden" name="check_id" id="editCheckId" value="">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Check # <span class="req">*</span></label>
                            <input type="text" name="check_number" id="editCheckNumber" class="form-control" maxlength="14" required>
                            <small style="color: var(--text-muted); font-size: 0.72rem;">Maximum 12 digits total.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Voucher #</label>
                            <input type="text" name="voucher_number" id="editCheckVoucher" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bank Name &amp; Branch <span class="req">*</span></label>
                            <input type="text" name="bank_name" id="editCheckBank" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account #</label>
                            <input type="text" name="account_number" id="editCheckAccount" class="form-control">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Payee / Supplier <span class="req">*</span></label>
                            <input type="text" name="payee" id="editCheckPayee" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" id="editCheckAmount" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority Status</label>
                            <select name="priority_status" id="editCheckPriority" class="form-control">
                                <?php foreach ($priorityStatusesList as $pKey => $pLabel): ?>
                                    <option value="<?= e($pKey) ?>"><?= e($pLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Issue Date <span class="req">*</span></label>
                            <input type="date" name="issue_date" id="editCheckIssueDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cheque Date (Maturity)</label>
                            <input type="date" name="check_date" id="editCheckMaturityDate" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Project / Site</label>
                            <input type="text" name="project_name" id="editCheckProject" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Project District</label>
                            <select name="project_district" id="editCheckDistrict" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="editCheckCategory" list="expenseCategoriesDatalist" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCheckStatus" class="form-control">
                                <option value="funded">Funded</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Remarks / Particulars</label>
                            <textarea name="remarks" id="editCheckRemarks" class="form-control"></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Checks & Vouchers (Edit) -->
                        <?= render_custom_fields_html('checks', [], 'edit_chk') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalEditCheck')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Cash Request / Expense (Editable from Disbursement Report & Cash Requests) -->
    <div class="modal-overlay" id="modalEditCashRequest">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Edit Cash Expense Record</h3>
                    <p style="margin: 0.2rem 0 0; font-size: 0.78rem; color: var(--text-muted);">Update voucher, requester, amount, or purpose</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('modalEditCashRequest')">&times;</button>
            </div>
            <form action="api/actions.php" method="POST" id="formEditCashRequest">
                <input type="hidden" name="action" value="edit_cash_request">
                <input type="hidden" name="request_id" id="editCashId" value="">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Voucher #</label>
                            <input type="text" name="voucher_no" id="editCashVoucher" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date <span class="req">*</span></label>
                            <input type="date" name="request_date" id="editCashDate" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Requested By <span class="req">*</span></label>
                            <input type="text" name="requested_by" id="editCashRequester" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department / Project <span class="req">*</span></label>
                            <input type="text" name="department_or_project" id="editCashDept" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Project District</label>
                            <select name="project_district" id="editCashDistrict" class="form-control">
                                <option value="">-- Select Project District --</option>
                                <?php foreach ($districtsList as $distKey => $distName): ?>
                                    <option value="<?= e($distKey) ?>"><?= e($distKey) ?> &ndash; <?= e($distName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" id="editCashAmount" class="form-control" required>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCashStatus" class="form-control">
                                <option value="approved">Approved</option>
                                <option value="disbursed">Disbursed</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Purpose / Description <span class="req">*</span></label>
                            <textarea name="purpose" id="editCashPurpose" class="form-control" required></textarea>
                        </div>

                        <!-- Dynamic Custom Fields for Petty Cash Requests (Edit) -->
                        <?= render_custom_fields_html('cash_requests', [], 'edit_cash') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalEditCashRequest')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <script src="assets/js/app.js?v=<?= file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time() ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (toggleBtn && sidebar && backdrop) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('sidebar-open');
                backdrop.classList.toggle('active');
            });
            backdrop.addEventListener('click', function() {
                sidebar.classList.remove('sidebar-open');
                backdrop.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>
