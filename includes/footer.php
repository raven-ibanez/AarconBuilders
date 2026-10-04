    </main>

    <!-- Print Signatures (Printed output only) -->
    <div class="print-signatures" style="display: none;">
        <div>
            <div class="sig-line">Prepared By: Cashier / Accounting</div>
        </div>
        <div>
            <div class="sig-line">Checked By: Internal Audit</div>
        </div>
        <div>
            <div class="sig-line">Approved By: General Manager</div>
        </div>
    </div>

    <!-- Modal: Add Check -->
    <div class="modal-overlay" id="modalAddCheck">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">New Physical Check (PDC)</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST">
                <input type="hidden" name="action" value="add_check">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Check Number <span class="req">*</span></label>
                            <input type="text" name="check_number" class="form-control" placeholder="e.g. 0019281" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bank <span class="req">*</span></label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. BDO, BPI, Metrobank" required>
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Payee / Supplier <span class="req">*</span></label>
                            <input type="text" name="payee" class="form-control" placeholder="Supplier or vendor name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Check Date (Post-Date) <span class="req">*</span></label>
                            <input type="date" name="check_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="Materials, Rental, Subcon" value="Materials">
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Project / Site</label>
                            <input type="text" name="project_name" class="form-control" placeholder="Project name or site">
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCheck')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Check</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Cash Request -->
    <div class="modal-overlay" id="modalAddCashRequest">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">New Cash Request</h3>
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
                        <div class="form-group col-span-2">
                            <label class="form-label">Amount Needed (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Purpose <span class="req">*</span></label>
                            <textarea name="purpose" class="form-control" placeholder="e.g. Office supplies, fuel, emergency tool repair..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCashRequest')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Collection -->
    <div class="modal-overlay" id="modalAddCollection">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Record Collection (Cash In)</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form action="api/actions.php" method="POST">
                <input type="hidden" name="action" value="add_collection">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Receipt / OR # (Optional)</label>
                            <input type="text" name="receipt_no" class="form-control" placeholder="Leave blank to auto-create">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date <span class="req">*</span></label>
                            <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Client / Customer <span class="req">*</span></label>
                            <input type="text" name="client_name" class="form-control" placeholder="Client name" required>
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Project / Contract</label>
                            <input type="text" name="project_name" class="form-control" placeholder="Project name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="req">*</span></label>
                            <select name="payment_method" class="form-control" required>
                                <option value="Bank Deposit">Bank Deposit</option>
                                <option value="Cash">Cash</option>
                                <option value="Online Transfer">Online Transfer</option>
                                <option value="Incoming Check">Incoming Check</option>
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
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" placeholder="Optional particulars..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCollection')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Collection</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
