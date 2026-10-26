/**
 * Aarcon Builders - Interactive UI Engine
 * Cash & Check Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initModals();
    initCheckSelection();
    initTableSearch();
    initAjaxButtons();
    initBankBranchLogic();
    initCheckMaturityHint();
    initCollectionCalculations();
    initFundCheckModal();
    initEditCheckDateModal();
    initEditCollectionModal();
    initEditCheckModal();
    initEditCashRequestModal();
    initCashTableFilteredTotal();
    initReportColumnToggles();
});

// ==========================================
// TABS CONTROLLER
// ==========================================
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn[data-tab]');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const targetId = btn.getAttribute('data-tab');
            if (!targetId) return;

            const nav = btn.closest('.tabs-nav') || btn.parentElement;
            if (nav) {
                nav.querySelectorAll('.tab-btn[data-tab]').forEach(b => b.classList.remove('active'));
            }

            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                const parent = targetPane.parentElement || document;
                parent.querySelectorAll(':scope > .tab-pane, .tab-pane').forEach(p => {
                    if (p.parentElement === targetPane.parentElement) {
                        p.classList.remove('active');
                    }
                });
                targetPane.classList.add('active');
            }

            btn.classList.add('active');

            // Persist active tab so page reloads do not reset user view
            try {
                const pageName = window.location.pathname.split('/').pop() || 'index.php';
                localStorage.setItem(`aarcon_active_tab_${pageName}`, targetId);

                const url = new URL(window.location.href);
                const tabParam = (pageName === 'admin.php') ? targetId : targetId.replace('tab-', '');
                url.searchParams.set('tab', tabParam);
                window.history.replaceState({}, '', url.toString());
            } catch (err) {}
        });
    });

    // Auto-restore tab from localStorage if not explicitly in URL
    try {
        const pageName = window.location.pathname.split('/').pop() || 'index.php';
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('tab')) {
            const savedTabId = localStorage.getItem(`aarcon_active_tab_${pageName}`);
            if (savedTabId) {
                const savedBtn = document.querySelector(`.tab-btn[data-tab="${savedTabId}"]`);
                if (savedBtn && !savedBtn.classList.contains('active')) {
                    savedBtn.click();
                }
            }
        }
    } catch (err) {}
}

// ==========================================
// MODAL CONTROLLER
// ==========================================
function initModals() {
    // Open Modal buttons
    document.querySelectorAll('[data-open-modal]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = trigger.getAttribute('data-open-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                if (trigger.hasAttribute('data-modal-title')) {
                    const titleEl = modal.querySelector('.modal-title');
                    if (titleEl) titleEl.textContent = trigger.getAttribute('data-modal-title');
                }
                if (trigger.hasAttribute('data-submit-label')) {
                    const submitBtn = modal.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.textContent = trigger.getAttribute('data-submit-label');
                }
                if (trigger.hasAttribute('data-action')) {
                    const actInput = modal.querySelector('input[name="action"]');
                    if (actInput) actInput.value = trigger.getAttribute('data-action');
                }
            }
            openModal(modalId);
        });
    });

    // Close Modal buttons & overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay.id);
            }
        });

        const closeBtn = overlay.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeModal(overlay.id));
        }
    });

    // ESC key closes active modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 100);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ==========================================
// CHECK SELECTION & BATCH FUNDING BAR
// ==========================================
function initCheckSelection() {
    const selectAllCheck = document.getElementById('selectAllChecks');
    const checkCheckboxes = document.querySelectorAll('.check-select-item');
    const batchBar = document.getElementById('batchBar');
    const batchCountEl = document.getElementById('batchCount');
    const batchTotalEl = document.getElementById('batchTotal');
    const batchIdsInput = document.getElementById('batchCheckIds');

    if (!batchBar || checkCheckboxes.length === 0) return;

    function updateBatchBar() {
        let count = 0;
        let total = 0;
        const selectedIds = [];

        checkCheckboxes.forEach(cb => {
            if (cb.checked) {
                count++;
                total += parseFloat(cb.getAttribute('data-amount') || 0);
                selectedIds.push(cb.value);
            }
        });

        if (count > 0) {
            batchBar.classList.add('visible');
            if (batchCountEl) batchCountEl.textContent = count;
            if (batchTotalEl) batchTotalEl.textContent = '₱ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (batchIdsInput) batchIdsInput.value = selectedIds.join(',');
        } else {
            batchBar.classList.remove('visible');
            if (batchIdsInput) batchIdsInput.value = '';
        }
    }

    if (selectAllCheck) {
        selectAllCheck.addEventListener('change', () => {
            checkCheckboxes.forEach(cb => {
                cb.checked = selectAllCheck.checked;
            });
            updateBatchBar();
        });
    }

    checkCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            updateBatchBar();
            if (selectAllCheck) {
                selectAllCheck.checked = Array.from(checkCheckboxes).every(c => c.checked);
            }
        });
    });
}

// ==========================================
// LIVE TABLE SEARCH & FILTERING
// ==========================================
function initTableSearch() {
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const targetTableSelector = input.getAttribute('data-table-search');
        const table = document.querySelector(targetTableSelector);
        if (!table) return;

        input.addEventListener('input', () => {
            const query = input.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr:not(.no-filter)');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Recalculate cash table totals if applicable
            if (table.id === 'pendingCashTable' || table.id === 'approvedCashTable' || table.id === 'cashRequestsTable') {
                updateCashTableTotals(table);
            }
        });
    });
}

// ==========================================
// BANK BRANCH LOGIC & 3-DIGIT PREFIX FOR CHECK NUMBER
// ==========================================
function initBankBranchLogic() {
    const bankSelect = document.getElementById('checkBankBranchSelect');
    const prefixDisplay = document.getElementById('bankPrefixDisplay');
    const checkInput = document.getElementById('checkNumberInput') || document.getElementById('checkAccountNumber');

    if (!bankSelect || !checkInput) return;

    bankSelect.addEventListener('change', () => {
        const selectedOpt = bankSelect.options[bankSelect.selectedIndex];
        const code = selectedOpt.getAttribute('data-code') || '';

        if (prefixDisplay) {
            prefixDisplay.value = code || '---';
        }

        if (code) {
            let currentVal = checkInput.value.trim();
            // If already starts with a 3-digit prefix like '001-' or '001 '
            if (/^\d{3}[-\s]/.test(currentVal)) {
                checkInput.value = code + '-' + currentVal.replace(/^\d{3}[-\s]/, '');
            } else if (currentVal === '') {
                checkInput.value = code + '-';
            } else {
                checkInput.value = code + '-' + currentVal;
            }
        }
        checkInput.focus();
    });
}

// ==========================================
// CHECK MATURITY HINT (Dated vs Post-Dated vs Pending Date)
// ==========================================
function initCheckMaturityHint() {
    const issueDateInput = document.getElementById('checkIssueDate');
    const maturityDateInput = document.getElementById('checkMaturityDate');
    const hintEl = document.getElementById('checkMaturityHint');
    const btnClearDate = document.getElementById('btnClearCheckDate');

    if (!issueDateInput || !maturityDateInput || !hintEl) return;

    function updateMaturityHint() {
        const issueVal = issueDateInput.value;
        const maturityVal = maturityDateInput.value;

        if (!maturityVal) {
            hintEl.textContent = 'Tag: Pending Date (Date Unknown Yet)';
            hintEl.style.color = '#d97706'; // warning amber
            return;
        }

        if (issueVal && maturityVal > issueVal) {
            hintEl.textContent = 'Tag: Post-Dated Check (PDC)';
            hintEl.style.color = '#7c3aed'; // purple
        } else {
            hintEl.textContent = 'Tag: Dated Check';
            hintEl.style.color = '#16a34a'; // green
        }
    }

    if (btnClearDate) {
        btnClearDate.addEventListener('click', () => {
            maturityDateInput.value = '';
            updateMaturityHint();
        });
    }

    issueDateInput.addEventListener('change', updateMaturityHint);
    maturityDateInput.addEventListener('change', updateMaturityHint);
    maturityDateInput.addEventListener('input', updateMaturityHint);
    updateMaturityHint();
}


// ==========================================
// FINANCIAL COMPUTATIONS (COLLECTIONS MODAL)
// ==========================================
function initCollectionCalculations() {
    const contractInput = document.getElementById('colContractAmount');
    const netInput      = document.getElementById('colNetAmount');
    const amountInput   = document.getElementById('colAmountCollected');
    const deductionInput = document.getElementById('colDeduction');
    const netColInput   = document.getElementById('colNetCollections');

    const balanceDisplay        = document.getElementById('calcBalanceDisplay');
    const percentDisplay        = document.getElementById('calcPercentDisplay');
    const balancePercentDisplay = document.getElementById('calcBalancePercentDisplay');

    if (!contractInput || !amountInput) return;

    function formatPHP(val) {
        return '₱ ' + (val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculate() {
        const contract = parseFloat(contractInput.value) || 0;
        const net = parseFloat(netInput ? netInput.value : 0) || 0;
        const amountCollected = parseFloat(amountInput.value) || 0;
        const deduction = parseFloat(deductionInput ? deductionInput.value : 0) || 0;

        // Auto-compute net collections if netColInput exists and active element is amount or deduction
        if (netColInput && (document.activeElement === amountInput || document.activeElement === deductionInput || !netColInput.value)) {
            const netCol = Math.max(0, amountCollected - deduction);
            netColInput.value = (amountCollected > 0 || deduction > 0) ? netCol.toFixed(2) : '';
        }

        // Balance Collectible = Net Amount - Amount Collected
        const balance = Math.max(0, net - amountCollected);

        // Percentage of collections received against contract amount
        let percentReceived = 0;
        if (contract > 0) {
            percentReceived = (amountCollected / contract) * 100;
        } else if (net > 0) {
            percentReceived = (amountCollected / net) * 100;
        } else if (amountCollected > 0) {
            percentReceived = 100;
        }

        // Percentage of remaining balance against contract amount
        let percentBalance = 0;
        if (contract > 0) {
            percentBalance = (balance / contract) * 100;
        } else if (net > 0) {
            percentBalance = (balance / net) * 100;
        }

        if (balanceDisplay) balanceDisplay.textContent = formatPHP(balance);
        if (percentDisplay) percentDisplay.textContent = percentReceived.toFixed(2) + '%';
        if (balancePercentDisplay) balancePercentDisplay.textContent = percentBalance.toFixed(2) + '%';
    }

    [contractInput, netInput, amountInput, deductionInput, netColInput].forEach(inp => {
        if (inp) {
            inp.addEventListener('input', recalculate);
            inp.addEventListener('change', recalculate);
        }
    });

    recalculate();
}

// ==========================================
// FUND CHECK MODAL WORKFLOW TRIGGER
// ==========================================
function initFundCheckModal() {
    const fundModal = document.getElementById('modalFundCheck');
    const fundCheckId = document.getElementById('fundCheckId');
    const fundCheckNumberText = document.getElementById('fundCheckNumberText');
    const fundCheckPayeeText = document.getElementById('fundCheckPayeeText');
    const fundCheckAmountText = document.getElementById('fundCheckAmountText');
    const fundCheckIssueDateText = document.getElementById('fundCheckIssueDateText');
    const fundChequeDateInput = document.getElementById('fundChequeDateInput');
    const fundPriorityStatus = document.getElementById('fundPriorityStatus');
    const formFundCheck = document.getElementById('formFundCheck');

    // Trigger modal when clicking .btn-ajax-fund
    document.querySelectorAll('.btn-ajax-fund').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const checkId = btn.getAttribute('data-check-id');
            const checkNum = btn.getAttribute('data-check-num') || '';
            const payee = btn.getAttribute('data-payee') || '';
            const amount = btn.getAttribute('data-amount') || '0';
            const issueDate = btn.getAttribute('data-issue-date') || '';
            const checkDate = btn.getAttribute('data-check-date') || '';
            const priority = btn.getAttribute('data-priority') || 'Standard';

            if (fundModal && fundCheckId && fundChequeDateInput) {
                fundCheckId.value = checkId;
                if (fundCheckNumberText) fundCheckNumberText.textContent = `Check #${checkNum}`;
                if (fundCheckPayeeText) fundCheckPayeeText.textContent = payee;
                if (fundCheckAmountText) fundCheckAmountText.textContent = '₱ ' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
                if (fundCheckIssueDateText) fundCheckIssueDateText.textContent = issueDate || '-';
                
                // Default cheque date to current check date or today
                fundChequeDateInput.value = checkDate || new Date().toISOString().split('T')[0];
                if (fundPriorityStatus) fundPriorityStatus.value = priority;

                openModal('modalFundCheck');
            } else {
                // Fallback prompt for cheque date if modal not on page
                const promptDate = prompt(`Confirm Cheque Date for funding Check #${checkNum}:`, checkDate || new Date().toISOString().split('T')[0]);
                if (promptDate) {
                    executeFundAjax(checkId, promptDate, btn);
                }
            }
        });
    });

    if (formFundCheck) {
        formFundCheck.addEventListener('submit', async (e) => {
            e.preventDefault();
            const checkId = fundCheckId.value;
            const chequeDate = fundChequeDateInput.value;
            const submitBtn = formFundCheck.querySelector('button[type="submit"]');

            if (!chequeDate) {
                alert('Please input the Cheque Date before proceeding with funding.');
                fundChequeDateInput.focus();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Funding...';
            }

            const formData = new FormData(formFundCheck);
            formData.append('ajax', '1');

            try {
                const response = await fetch('api/actions.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams(formData)
                });
                const result = await response.json();

                if (result.success) {
                    closeModal('modalFundCheck');
                    showToast('success', result.message);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    alert(result.message || 'Funding failed.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Confirm &amp; Fund Check';
                    }
                }
            } catch (err) {
                console.error(err);
                alert('Connection error occurred.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Confirm &amp; Fund Check';
                }
            }
        });
    }
}

// ==========================================
// EDIT CHECK DATE MODAL CONTROLLER
// ==========================================
function initEditCheckDateModal() {
    const modal = document.getElementById('modalEditCheckDate');
    const form = document.getElementById('formEditCheckDate');
    const checkIdInput = document.getElementById('editCheckDateId');
    const issueDateVal = document.getElementById('editCheckIssueDateVal');
    const dateInput = document.getElementById('editCheckDateInput');
    const hintEl = document.getElementById('editCheckDateHint');
    const btnClear = document.getElementById('btnEditClearDate');

    if (!modal || !form || !dateInput) return;

    function updateModalDateHint() {
        const issueVal = issueDateVal.value;
        const curDate = dateInput.value;

        if (!curDate) {
            hintEl.textContent = 'Tag: Pending Date (Date Unknown Yet)';
            hintEl.style.color = '#d97706';
            return;
        }

        if (issueVal && curDate > issueVal) {
            hintEl.textContent = 'Tag: Post-Dated Check (PDC)';
            hintEl.style.color = '#7c3aed';
        } else {
            hintEl.textContent = 'Tag: Dated Check';
            hintEl.style.color = '#16a34a';
        }
    }

    if (btnClear) {
        btnClear.addEventListener('click', () => {
            dateInput.value = '';
            updateModalDateHint();
        });
    }

    dateInput.addEventListener('change', updateModalDateHint);
    dateInput.addEventListener('input', updateModalDateHint);

    // Bind triggers to open edit modal
    document.querySelectorAll('.btn-edit-check-date').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const checkId = btn.getAttribute('data-check-id');
            const checkNum = btn.getAttribute('data-check-num') || '---';
            const payee = btn.getAttribute('data-payee') || '-';
            const amount = parseFloat(btn.getAttribute('data-amount') || 0);
            const issueDate = btn.getAttribute('data-issue-date') || '';
            const checkDate = btn.getAttribute('data-check-date') || '';

            checkIdInput.value = checkId;
            issueDateVal.value = issueDate;
            dateInput.value = checkDate;

            document.getElementById('editCheckNumberText').textContent = 'Check #' + checkNum;
            document.getElementById('editCheckPayeeText').textContent = payee;
            document.getElementById('editCheckAmountText').textContent = '₱ ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('editCheckIssueDateText').textContent = issueDate || '-';

            updateModalDateHint();
            openModal('modalEditCheckDate');
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Saving...';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const response = await fetch('api/actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(formData)
            });
            const result = await response.json();

            if (result.success) {
                closeModal('modalEditCheckDate');
                showToast('success', result.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(result.message || 'Failed to update check date.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save Check Date';
                }
            }
        } catch (err) {
            console.error(err);
            alert('Connection error occurred.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Check Date';
            }
        }
    });
}

// Helper to populate dynamic custom fields inside modal dialogs
function populateModalCustomFields(modalEl, customFieldsData) {
    if (!modalEl) return;
    let data = {};
    if (typeof customFieldsData === 'string') {
        try { data = JSON.parse(customFieldsData); } catch (e) { data = {}; }
    } else if (typeof customFieldsData === 'object' && customFieldsData !== null) {
        data = customFieldsData;
    }
    modalEl.querySelectorAll('[data-cf-key]').forEach(input => {
        const key = input.getAttribute('data-cf-key');
        if (data && data[key] !== undefined) {
            input.value = data[key];
        } else {
            input.value = '';
        }
    });
}

// ==========================================
// EDIT COLLECTION MODAL CONTROLLER
// (Requirement: Edit collection after entering & status editable)
// ==========================================
function initEditCollectionModal() {
    const modal = document.getElementById('modalEditCollection');
    const form = document.getElementById('formEditCollection');
    if (!modal || !form) return;

    const idInput           = document.getElementById('editColId');
    const contractIdInput   = document.getElementById('editColContractId');
    const receiptInput      = document.getElementById('editColReceiptNo');
    const dateInput         = document.getElementById('editColDate');
    const expectedDateInput = document.getElementById('editColExpectedDate');
    const clientInput       = document.getElementById('editColClient');
    const projectInput      = document.getElementById('editColProject');
    const districtSelect    = document.getElementById('editColDistrict');
    const contractInput     = document.getElementById('editColContractAmount');
    const netInput          = document.getElementById('editColNetAmount');
    const amountInput       = document.getElementById('editColAmountCollected');
    const deductionInput    = document.getElementById('editColDeduction');
    const netColInput       = document.getElementById('editColNetCollections');
    const billingOnProcessInput = document.getElementById('editColBillingOnProcess');
    const methodSelect      = document.getElementById('editColMethod');
    const statusSelect      = document.getElementById('editColStatus');
    const refInput          = document.getElementById('editColRefNo');
    const receivedInput     = document.getElementById('editColReceivedBy');
    const notesInput        = document.getElementById('editColNotes');

    const balanceDisplay        = document.getElementById('editCalcBalanceDisplay');
    const percentDisplay        = document.getElementById('editCalcPercentDisplay');
    const balancePercentDisplay = document.getElementById('editCalcBalancePercentDisplay');

    function formatPHP(val) {
        return '₱ ' + (val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateEdit() {
        const contract = parseFloat(contractInput ? contractInput.value : 0) || 0;
        const net = parseFloat(netInput ? netInput.value : 0) || 0;
        const amountCollected = parseFloat(amountInput ? amountInput.value : 0) || 0;
        const deduction = parseFloat(deductionInput ? deductionInput.value : 0) || 0;

        // Auto-compute net collections if netColInput exists and active element is amount or deduction
        if (netColInput && (document.activeElement === amountInput || document.activeElement === deductionInput || !netColInput.value)) {
            const netCol = Math.max(0, amountCollected - deduction);
            netColInput.value = (amountCollected > 0 || deduction > 0) ? netCol.toFixed(2) : '';
        }

        // Balance Collectible = Net Amount - Amount Collected
        const balance = Math.max(0, net - amountCollected);

        // Percentage of collections received against contract amount
        let percentReceived = 0;
        if (contract > 0) {
            percentReceived = (amountCollected / contract) * 100;
        } else if (net > 0) {
            percentReceived = (amountCollected / net) * 100;
        } else if (amountCollected > 0) {
            percentReceived = 100;
        }

        // Percentage of remaining balance against contract amount
        let percentBalance = 0;
        if (contract > 0) {
            percentBalance = (balance / contract) * 100;
        } else if (net > 0) {
            percentBalance = (balance / net) * 100;
        }

        if (balanceDisplay) balanceDisplay.textContent = formatPHP(balance);
        if (percentDisplay) percentDisplay.textContent = percentReceived.toFixed(2) + '%';
        if (balancePercentDisplay) balancePercentDisplay.textContent = percentBalance.toFixed(2) + '%';
    }

    [contractInput, netInput, amountInput, deductionInput, netColInput].forEach(inp => {
        if (inp) {
            inp.addEventListener('input', recalculateEdit);
            inp.addEventListener('change', recalculateEdit);
        }
    });

    // Populate modal when clicking .btn-edit-collection
    document.querySelectorAll('.btn-edit-collection').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const dataStr = btn.getAttribute('data-collection');
            if (!dataStr) return;

            try {
                const item = JSON.parse(dataStr);
                idInput.value = item.id || '';
                if (contractIdInput) contractIdInput.value = item.contract_id || '';
                receiptInput.value = item.receipt_no || '';
                dateInput.value = item.collection_date || '';
                if (expectedDateInput) expectedDateInput.value = item.expected_collection_date || '';
                clientInput.value = item.client_name || '';
                projectInput.value = item.project_name || '';
                districtSelect.value = item.project_district || '';
                contractInput.value = item.contract_amount || '';
                if (netInput) netInput.value = (item.net_amount !== null && item.net_amount !== undefined && item.net_amount !== '') ? item.net_amount : (item.amount || '');
                amountInput.value = item.amount || '';
                if (deductionInput) deductionInput.value = (item.deduction !== null && item.deduction !== undefined && item.deduction !== '') ? item.deduction : '';
                if (netColInput) netColInput.value = (item.net_collections !== null && item.net_collections !== undefined && item.net_collections !== '') ? item.net_collections : '';
                if (billingOnProcessInput) billingOnProcessInput.value = (item.billing_on_process !== null && item.billing_on_process !== undefined && item.billing_on_process !== '') ? item.billing_on_process : '';
                methodSelect.value = item.payment_method || 'Bank Deposit';
                statusSelect.value = item.status || 'collected';
                refInput.value = item.reference_no || '';
                receivedInput.value = item.received_by || '';
                notesInput.value = item.notes || '';

                recalculateEdit();
                populateModalCustomFields(modal, item.custom_fields);
                openModal('modalEditCollection');
            } catch (err) {
                console.error('Failed to parse collection data:', err);
                alert('Error opening collection editor.');
            }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Updating...';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const response = await fetch('api/actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(formData)
            });
            const result = await response.json();

            if (result.success) {
                closeModal('modalEditCollection');
                showToast('success', result.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(result.message || 'Failed to update collection.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Update Collection';
                }
            }
        } catch (err) {
            console.error(err);
            alert('Connection error occurred while updating collection.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Update Collection';
            }
        }
    });
}

// ==========================================
// EDIT CHECK MODAL CONTROLLER
// (Disbursement Report & Check Management)
// ==========================================
function initEditCheckModal() {
    const modal = document.getElementById('modalEditCheck');
    const form  = document.getElementById('formEditCheck');
    if (!modal || !form) return;

    const idInput        = document.getElementById('editCheckId');
    const numberInput    = document.getElementById('editCheckNumber');
    const voucherInput   = document.getElementById('editCheckVoucher');
    const bankInput      = document.getElementById('editCheckBank');
    const accountInput   = document.getElementById('editCheckAccount');
    const payeeInput     = document.getElementById('editCheckPayee');
    const amountInput    = document.getElementById('editCheckAmount');
    const prioritySelect = document.getElementById('editCheckPriority');
    const issueDateInput = document.getElementById('editCheckIssueDate');
    const checkDateInput = document.getElementById('editCheckMaturityDate');
    const projectInput   = document.getElementById('editCheckProject');
    const districtSelect = document.getElementById('editCheckDistrict');
    const categoryInput  = document.getElementById('editCheckCategory');
    const statusSelect   = document.getElementById('editCheckStatus');
    const remarksInput   = document.getElementById('editCheckRemarks');

    document.querySelectorAll('.btn-edit-check').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const dataStr = btn.getAttribute('data-check');
            if (!dataStr) return;

            try {
                const item = JSON.parse(dataStr);
                idInput.value = item.id || '';
                numberInput.value = item.check_number || '';
                voucherInput.value = item.voucher_number || '';
                bankInput.value = item.bank_name || '';
                accountInput.value = item.account_number || '';
                payeeInput.value = item.payee || '';
                amountInput.value = item.amount || '';
                prioritySelect.value = item.priority_status || 'Standard';
                issueDateInput.value = item.issue_date || '';
                checkDateInput.value = item.check_date || '';
                projectInput.value = item.project_name || '';
                districtSelect.value = item.project_district || '';
                categoryInput.value = item.category || 'Materials';
                statusSelect.value = item.status || 'funded';
                remarksInput.value = item.remarks || '';

                if (typeof populateModalCustomFields === 'function') {
                    populateModalCustomFields(modal, item.custom_fields);
                }
                openModal('modalEditCheck');
            } catch (err) {
                console.error('Failed to parse check data:', err);
                alert('Error opening check editor.');
            }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Saving...';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const response = await fetch('api/actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(formData)
            });
            const result = await response.json();

            if (result.success) {
                closeModal('modalEditCheck');
                showToast('success', result.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(result.message || 'Failed to update check.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save Changes';
                }
            }
        } catch (err) {
            console.error(err);
            alert('Connection error occurred while updating check.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Changes';
            }
        }
    });
}

// ==========================================
// EDIT CASH REQUEST / EXPENSE MODAL CONTROLLER
// (Disbursement Report & Cash Requests)
// ==========================================
function initEditCashRequestModal() {
    const modal = document.getElementById('modalEditCashRequest');
    const form  = document.getElementById('formEditCashRequest');
    if (!modal || !form) return;

    const idInput        = document.getElementById('editCashId');
    const voucherInput   = document.getElementById('editCashVoucher');
    const dateInput      = document.getElementById('editCashDate');
    const reqInput       = document.getElementById('editCashRequester');
    const deptInput      = document.getElementById('editCashDept');
    const districtSelect = document.getElementById('editCashDistrict');
    const amountInput    = document.getElementById('editCashAmount');
    const statusSelect   = document.getElementById('editCashStatus');
    const purposeInput   = document.getElementById('editCashPurpose');

    document.querySelectorAll('.btn-edit-cash-request').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const dataStr = btn.getAttribute('data-cash');
            if (!dataStr) return;

            try {
                const item = JSON.parse(dataStr);
                idInput.value = item.id || '';
                voucherInput.value = item.voucher_no || '';
                dateInput.value = item.request_date || '';
                reqInput.value = item.requested_by || '';
                deptInput.value = item.department_or_project || '';
                districtSelect.value = item.project_district || '';
                amountInput.value = item.amount || '';
                statusSelect.value = item.status || 'approved';
                purposeInput.value = item.purpose || '';

                if (typeof populateModalCustomFields === 'function') {
                    populateModalCustomFields(modal, item.custom_fields);
                }
                openModal('modalEditCashRequest');
            } catch (err) {
                console.error('Failed to parse cash request data:', err);
                alert('Error opening cash expense editor.');
            }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Saving...';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const response = await fetch('api/actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(formData)
            });
            const result = await response.json();

            if (result.success) {
                closeModal('modalEditCashRequest');
                showToast('success', result.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(result.message || 'Failed to update cash expense.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save Changes';
                }
            }
        } catch (err) {
            console.error(err);
            alert('Connection error occurred while updating cash expense.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Changes';
            }
        }
    });
}



async function executeFundAjax(checkId, chequeDate, btn) {
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = 'Funding...';
    }

    try {
        const response = await fetch('api/actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'fund_check',
                check_id: checkId,
                cheque_date: chequeDate,
                ajax: '1'
            })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', result.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            alert(result.message || 'Action failed.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Fund';
            }
        }
    } catch (err) {
        console.error(err);
        alert('Network error.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Fund';
        }
    }
}

// ==========================================
// 1-CLICK ACTIONS (APPROVE CASH, ETC)
// ==========================================
function initAjaxButtons() {
    // 1-Click Approve Cash Request
    document.querySelectorAll('.btn-ajax-approve-cash').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const reqId = btn.getAttribute('data-request-id');
            const voucher = btn.getAttribute('data-voucher');

            if (!confirm(`Approve cash release for Voucher ${voucher}?`)) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Approving...';

            try {
                const response = await fetch('api/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'approve_cash_request',
                        request_id: reqId,
                        ajax: '1'
                    })
                });

                const result = await response.json();
                if (result.success) {
                    showToast('success', result.message);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    alert(result.message || 'Approval failed.');
                    btn.disabled = false;
                    btn.innerHTML = 'Approve & Fund';
                }
            } catch (err) {
                console.error(err);
                alert('Connection error occurred.');
                btn.disabled = false;
                btn.innerHTML = 'Approve & Fund';
            }
        });
    });

    // Check Priority Quick Selector
    document.querySelectorAll('.check-priority-select').forEach(select => {
        select.addEventListener('change', async () => {
            const checkId = select.getAttribute('data-check-id');
            const newPriority = select.value;

            try {
                const response = await fetch('api/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'update_check_priority',
                        check_id: checkId,
                        priority_status: newPriority,
                        ajax: '1'
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showToast('success', result.message);
                } else {
                    alert(result.message || 'Failed to update priority.');
                }
            } catch (err) {
                console.error(err);
            }
        });
    });

    // Quick AJAX Review Voucher Button
    document.querySelectorAll('.form-review-voucher').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('.btn-review-voucher');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'Reviewing...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            try {
                const response = await fetch('api/actions.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams(formData)
                });
                const result = await response.json();
                if (result.success) {
                    showToast('success', result.message);
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    alert(result.message || 'Failed to review voucher.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Review';
                    }
                }
            } catch (err) {
                console.error(err);
                alert('Connection error occurred.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Review';
                }
            }
        });
    });
}

// ==========================================
// CASH REQUEST TABLE DYNAMIC FOOTER ROW
// ==========================================
function initCashTableFilteredTotal() {
    const tables = document.querySelectorAll('#cashRequestsTable, #pendingCashTable, #approvedCashTable');
    tables.forEach(table => {
        updateCashTableTotals(table);
    });
}

function updateCashTableTotals(table) {
    if (!table) return;
    const footerTotalEl = table.querySelector('.cash-filtered-total') || document.getElementById('cashFilteredTotal');
    if (!footerTotalEl) return;

    let total = 0;
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none' && !row.classList.contains('empty-row')) {
            const amountCell = row.querySelector('.amount-col, [data-row-amount]');
            if (amountCell) {
                const amountVal = parseFloat(amountCell.getAttribute('data-row-amount') || amountCell.textContent.replace(/[^\d.-]/g, '')) || 0;
                total += amountVal;
            }
        }
    });

    footerTotalEl.textContent = '₱ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ==========================================
// DYNAMIC REPORT COLUMN TOGGLES (Persistent across reloads)
// ==========================================
function initReportColumnToggles() {
    function getTableForPicker(dropdown) {
        // Find associated table by data-table-search or inside the nearest card/pane container
        const container = dropdown.closest('.excel-sheet-card, .tab-pane, .card') || dropdown.parentElement;
        if (container) {
            const searchInput = container.querySelector('[data-table-search]');
            if (searchInput) {
                const tableSel = searchInput.getAttribute('data-table-search');
                if (tableSel) {
                    const tbl = document.querySelector(tableSel);
                    if (tbl) return tbl;
                }
            }
            const tbl = container.querySelector('table.excel-table') || container.querySelector('table');
            if (tbl) return tbl;
        }
        return document.querySelector('table.excel-table');
    }

    function getStorageKey(dropdown, table) {
        const tableId = table && table.id ? table.id : 'default';
        const pageKey = window.location.pathname.split('/').pop() || 'page';
        return `aarcon_cols_${pageKey}_${tableId}`;
    }

    function closeAllPickers() {
        document.querySelectorAll('.col-picker-dropdown.active').forEach(d => {
            d.classList.remove('active');
            const parentCard = d.closest('.excel-sheet-card');
            if (parentCard) {
                parentCard.classList.remove('card-dropdown-active');
                parentCard.style.zIndex = '';
            }
        });
    }

    // 1. Column Pickers initialization & state restoration
    const pickerDropdowns = document.querySelectorAll('.col-picker-dropdown');
    pickerDropdowns.forEach(dropdown => {
        const table = getTableForPicker(dropdown);
        const storageKey = getStorageKey(dropdown, table);

        let savedPrefs = {};
        try {
            const raw = localStorage.getItem(storageKey);
            if (raw) {
                savedPrefs = JSON.parse(raw);
            }
        } catch (e) {
            savedPrefs = {};
        }

        const checkboxes = dropdown.querySelectorAll('[data-report-toggle]');
        checkboxes.forEach(cb => {
            const colKey = cb.getAttribute('data-report-toggle');
            if (!colKey) return;

            // Restore state from localStorage if user previously changed it
            if (savedPrefs[colKey] !== undefined) {
                cb.checked = Boolean(savedPrefs[colKey]);
            }

            function applyToggle() {
                const isVisible = cb.checked;
                // Scope hiding to the specific table, or document fallback
                const targetCols = (table || document).querySelectorAll(`.col-toggle-${colKey}`);
                targetCols.forEach(el => {
                    if (isVisible) {
                        el.style.display = '';
                        el.classList.remove('col-hidden');
                    } else {
                        el.style.display = 'none';
                        el.classList.add('col-hidden');
                    }
                });

                // Persist current state of all checkboxes in this picker to localStorage
                savedPrefs[colKey] = isVisible;
                try {
                    localStorage.setItem(storageKey, JSON.stringify(savedPrefs));
                } catch (e) {}
            }

            cb.addEventListener('change', applyToggle);

            // Immediately apply visibility on page load according to restored or default state
            const isVisible = cb.checked;
            const targetCols = (table || document).querySelectorAll(`.col-toggle-${colKey}`);
            targetCols.forEach(el => {
                if (isVisible) {
                    el.style.display = '';
                    el.classList.remove('col-hidden');
                } else {
                    el.style.display = 'none';
                    el.classList.add('col-hidden');
                }
            });
        });
    });

    // 2. Col Picker dropdown trigger buttons
    const pickerBtns = document.querySelectorAll('.btn-col-picker');
    pickerBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dropdown = btn.closest('.col-picker-dropdown');
            if (!dropdown) return;
            const wasActive = dropdown.classList.contains('active');
            
            // Close any currently active picker
            closeAllPickers();

            if (!wasActive) {
                dropdown.classList.add('active');
                const parentCard = dropdown.closest('.excel-sheet-card');
                if (parentCard) {
                    parentCard.classList.add('card-dropdown-active');
                    parentCard.style.zIndex = '9999';
                }
            }
        });
    });

    // 3. Reset All Buttons in pickers (restores default visibility and clears stored preference)
    const resetBtns = document.querySelectorAll('.col-picker-reset-btn');
    resetBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dropdown = btn.closest('.col-picker-dropdown');
            if (!dropdown) return;
            const table = getTableForPicker(dropdown);
            const storageKey = getStorageKey(dropdown, table);

            try {
                localStorage.removeItem(storageKey);
            } catch (e) {}

            const checkboxes = dropdown.querySelectorAll('[data-report-toggle]');
            checkboxes.forEach(cb => {
                cb.checked = true;
                const colKey = cb.getAttribute('data-report-toggle');
                const targetCols = (table || document).querySelectorAll(`.col-toggle-${colKey}`);
                targetCols.forEach(el => {
                    el.style.display = '';
                    el.classList.remove('col-hidden');
                });
            });
        });
    });

    // 4. Prevent click inside col-picker-menu from closing dropdown
    document.querySelectorAll('.col-picker-menu').forEach(menu => {
        menu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    });

    // 5. Global click outside listener & Escape key
    if (!window._colPickerGlobalBound) {
        window._colPickerGlobalBound = true;
        document.addEventListener('click', closeAllPickers);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllPickers();
            }
        });
    }
}


// ==========================================
// TOAST NOTIFICATIONS
// ==========================================
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '3000';
    toast.style.boxShadow = 'var(--shadow-lg)';
    toast.style.maxWidth = '380px';
    toast.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}
