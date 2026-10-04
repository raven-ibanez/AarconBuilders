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
});

// ==========================================
// TABS CONTROLLER
// ==========================================
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');
            const parent = btn.closest('.tabs-container') || document;
            
            parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            parent.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        });
    });
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
// LIVE TABLE SEARCH / FILTER
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
        });
    });
}

// ==========================================
// AJAX 1-CLICK FUND & APPROVE ACTIONS
// ==========================================
function initAjaxButtons() {
    // 1-Click Fund Check Button
    document.querySelectorAll('.btn-ajax-fund').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const checkId = btn.getAttribute('data-check-id');
            const checkNum = btn.getAttribute('data-check-num');
            
            if (!confirm(`Are you sure you want to approve funding for Check #${checkNum}? Money must be deposited into the bank account.`)) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Funding...';

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
                        ajax: '1'
                    })
                });

                const result = await response.json();
                if (result.success) {
                    // Update row in table
                    const row = btn.closest('tr');
                    if (row) {
                        const statusCell = row.querySelector('.status-cell');
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="badge badge-funded">✓ Funded</span>';
                        }
                        const actionCell = row.querySelector('.action-cell');
                        if (actionCell) {
                            actionCell.innerHTML = '<span style="color:var(--accent-emerald); font-size:0.85rem; font-weight:600;">Funded & Routed</span>';
                        }
                        const cb = row.querySelector('.check-select-item');
                        if (cb) {
                            cb.checked = false;
                            cb.disabled = true;
                        }
                    }
                    showToast('success', result.message);
                    // Refresh after short delay so balance counters refresh
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    alert(result.message || 'Action failed.');
                    btn.disabled = false;
                    btn.innerHTML = 'Fund Check';
                }
            } catch (err) {
                console.error(err);
                alert('Connection error occurred.');
                btn.disabled = false;
                btn.innerHTML = 'Fund Check';
            }
        });
    });

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
                    const row = btn.closest('tr');
                    if (row) {
                        const statusCell = row.querySelector('.status-cell');
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="badge badge-approved">✓ Approved</span>';
                        }
                        const actionCell = row.querySelector('.action-cell');
                        if (actionCell) {
                            actionCell.innerHTML = '<span style="color:var(--accent-emerald); font-size:0.85rem; font-weight:600;">Approved for Cash</span>';
                        }
                    }
                    showToast('success', result.message);
                    setTimeout(() => window.location.reload(), 900);
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
