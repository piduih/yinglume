/**
 * YingLume Finance — Journal Entry Form JavaScript
 * Handles: dynamic line rows, real-time debit/credit totals, balance validation
 */

'use strict';

(function () {
  const tbody        = document.getElementById('journal-lines-body');
  const addRowBtn    = document.getElementById('add-journal-line');
  const debitTotalEl = document.getElementById('journal-debit-total');
  const creditTotalEl= document.getElementById('journal-credit-total');
  const diffEl       = document.getElementById('journal-diff');
  const balanceIndicator = document.getElementById('balance-indicator');
  const form         = document.getElementById('journal-form');
  const postBtn      = document.getElementById('btn-post');

  if (!tbody) return;

  // Accounts pre-loaded from PHP inline JSON: { id: { code, name } }
  const ACCOUNTS = window.JOURNAL_ACCOUNTS || {};
  const accountList = Object.values(ACCOUNTS);

  let rowIndex = tbody.querySelectorAll('tr').length;

  // =============================================
  // ROW TEMPLATE
  // =============================================
  function createRowHtml(idx) {
    const listId = 'account-list-' + idx;
    return `
      <tr data-row="${idx}">
        <td style="min-width:240px">
          <input type="text"
                 name="lines[${idx}][account_name]"
                 class="form-control form-control-sm account-search"
                 placeholder="Search account…"
                 list="${listId}"
                 autocomplete="off"
                 data-row="${idx}">
          <datalist id="${listId}">
            ${accountList.map(a => `<option value="${a.code} — ${a.name}" data-id="${a.id}"></option>`).join('')}
          </datalist>
          <input type="hidden" name="lines[${idx}][account_id]" class="account-id-hidden" value="">
        </td>
        <td style="min-width:160px">
          <input type="text" name="lines[${idx}][description]" class="form-control form-control-sm" placeholder="Description">
        </td>
        <td style="width:130px">
          <input type="number" name="lines[${idx}][debit]"
                 class="form-control form-control-sm line-debit currency-input"
                 value="" min="0" step="0.01" placeholder="0.00">
        </td>
        <td style="width:130px">
          <input type="number" name="lines[${idx}][credit]"
                 class="form-control form-control-sm line-credit currency-input"
                 value="" min="0" step="0.01" placeholder="0.00">
        </td>
        <td class="text-center" style="width:48px">
          <button type="button" class="btn-remove-line" title="Remove"><i class="bi bi-x-lg"></i></button>
        </td>
      </tr>`;
  }

  // =============================================
  // ACCOUNT SEARCH → RESOLVE ID
  // =============================================
  function buildAccountMap() {
    const map = {};
    accountList.forEach(function (a) {
      map[a.code + ' — ' + a.name] = a.id;
    });
    return map;
  }
  const accountMap = buildAccountMap();

  tbody.addEventListener('input', function (e) {
    const input = e.target.closest('.account-search');
    if (!input) return;

    const val = input.value.trim();
    const row = input.closest('tr');
    const hiddenId = row.querySelector('.account-id-hidden');

    if (accountMap[val] && hiddenId) {
      hiddenId.value = accountMap[val];
    } else if (hiddenId) {
      hiddenId.value = '';
    }
  });

  // =============================================
  // MUTUAL EXCLUSION: DEBIT clears CREDIT and vice-versa
  // =============================================
  tbody.addEventListener('focus', function (e) {
    const input = e.target;
    if (input.classList.contains('line-debit')) {
      const row = input.closest('tr');
      const creditInput = row.querySelector('.line-credit');
      input.addEventListener('input', function onDebitInput() {
        if (parseFloat(input.value) > 0) creditInput.value = '';
        recalcTotals();
      }, { once: false });
    } else if (input.classList.contains('line-credit')) {
      const row = input.closest('tr');
      const debitInput = row.querySelector('.line-debit');
      input.addEventListener('input', function onCreditInput() {
        if (parseFloat(input.value) > 0) debitInput.value = '';
        recalcTotals();
      }, { once: false });
    }
  }, true);

  // =============================================
  // ADD ROW
  // =============================================
  if (addRowBtn) {
    addRowBtn.addEventListener('click', function () {
      const tmp = document.createElement('tbody');
      tmp.innerHTML = createRowHtml(rowIndex++);
      tbody.appendChild(tmp.firstElementChild);
      recalcTotals();
    });
  }

  // =============================================
  // REMOVE ROW
  // =============================================
  tbody.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remove-line');
    if (!btn) return;
    if (tbody.querySelectorAll('tr').length <= 2) {
      alert('A journal entry needs at least 2 lines.');
      return;
    }
    btn.closest('tr').remove();
    recalcTotals();
  });

  // =============================================
  // REAL-TIME TOTALS
  // =============================================
  tbody.addEventListener('input', function (e) {
    if (e.target.classList.contains('line-debit') || e.target.classList.contains('line-credit')) {
      recalcTotals();
    }
  });

  function recalcTotals() {
    let totalDebit  = 0;
    let totalCredit = 0;

    tbody.querySelectorAll('tr').forEach(function (row) {
      totalDebit  += parseFloat(row.querySelector('.line-debit')?.value)  || 0;
      totalCredit += parseFloat(row.querySelector('.line-credit')?.value) || 0;
    });

    const diff = Math.abs(totalDebit - totalCredit);
    const balanced = diff < 0.001;

    if (debitTotalEl)  debitTotalEl.textContent  = YL.formatNumber(totalDebit);
    if (creditTotalEl) creditTotalEl.textContent = YL.formatNumber(totalCredit);
    if (diffEl) {
      diffEl.textContent = balanced ? '—' : YL.formatNumber(diff);
      diffEl.className = 'amount-cell fw-bold ' + (balanced ? 'text-success' : 'text-danger');
    }

    if (balanceIndicator) {
      if (balanced && (totalDebit > 0 || totalCredit > 0)) {
        balanceIndicator.className = 'balance-indicator balanced';
        balanceIndicator.innerHTML = '<i class="bi bi-check-circle-fill"></i> Balanced';
      } else if (!balanced) {
        balanceIndicator.className = 'balance-indicator unbalanced';
        balanceIndicator.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Out of balance by ${YL.formatNumber(diff)}`;
      } else {
        balanceIndicator.className = 'balance-indicator';
        balanceIndicator.innerHTML = '<i class="bi bi-dash-circle"></i> Enter amounts';
      }
    }

    // Enable/disable Post button
    if (postBtn) {
      postBtn.disabled = !balanced || totalDebit === 0;
    }
  }

  // =============================================
  // FORM SUBMIT VALIDATION
  // =============================================
  if (form) {
    form.addEventListener('submit', function (e) {
      const rows = tbody.querySelectorAll('tr');

      if (rows.length < 2) {
        e.preventDefault();
        alert('A journal entry must have at least 2 lines.');
        return;
      }

      let totalDebit  = 0;
      let totalCredit = 0;
      let accountMissing = false;

      rows.forEach(function (row) {
        totalDebit  += parseFloat(row.querySelector('.line-debit')?.value)  || 0;
        totalCredit += parseFloat(row.querySelector('.line-credit')?.value) || 0;
        const accId = row.querySelector('.account-id-hidden')?.value;
        if (!accId) accountMissing = true;
      });

      const diff = Math.abs(totalDebit - totalCredit);
      if (diff >= 0.001) {
        e.preventDefault();
        alert('Journal entry is not balanced. Debit and Credit totals must be equal.');
        return;
      }

      if (totalDebit === 0) {
        e.preventDefault();
        alert('Journal entry has zero amounts. Please enter debit and credit amounts.');
        return;
      }

      if (accountMissing) {
        e.preventDefault();
        alert('Some lines are missing account selections. Please select a valid account for each line.');
        return;
      }
    });
  }

  // =============================================
  // INIT
  // =============================================
  recalcTotals();

})();
