/**
 * YingLume Finance — Invoice & Bill Form JavaScript
 * Handles: dynamic line items, auto-fill via AJAX, real-time totals
 */

'use strict';

(function () {
  // ---- DOM refs ----
  const tbody        = document.getElementById('line-items-body');
  const addRowBtn    = document.getElementById('add-line-btn');
  const subtotalEl   = document.getElementById('display-subtotal');
  const discountEl   = document.getElementById('display-discount');
  const taxEl        = document.getElementById('display-tax');
  const totalEl      = document.getElementById('display-total');
  const form         = document.getElementById('invoice-form');

  if (!tbody) return; // Not on invoice form

  // Items and tax codes pre-loaded from PHP inline JSON
  const ITEMS    = window.INVOICE_ITEMS    || {};
  const TAXCODES = window.INVOICE_TAXCODES || {};

  let rowIndex = tbody.querySelectorAll('tr').length;

  // =============================================
  // ROW TEMPLATE
  // =============================================
  function createRowHtml(idx) {
    const itemOptions = Object.entries(ITEMS).map(function ([id, item]) {
      return `<option value="${id}" data-price="${item.price}" data-desc="${item.name}" data-account="${item.account_id}">${item.name}</option>`;
    }).join('');

    const taxOptions = '<option value="">No Tax</option>' + Object.entries(TAXCODES).map(function ([code, tax]) {
      return `<option value="${code}" data-rate="${tax.rate}">${tax.name} (${tax.rate}%)</option>`;
    }).join('');

    return `
      <tr data-row="${idx}">
        <td style="min-width:200px">
          <select name="lines[${idx}][item_id]" class="form-select form-select-sm item-select" data-row="${idx}">
            <option value="">— Select Item —</option>
            ${itemOptions}
          </select>
          <input type="hidden" name="lines[${idx}][account_id]" class="line-account" value="">
        </td>
        <td style="min-width:160px">
          <input type="text" name="lines[${idx}][description]" class="form-control form-control-sm line-desc" placeholder="Description">
        </td>
        <td style="width:80px">
          <input type="number" name="lines[${idx}][qty]" class="form-control form-control-sm line-qty currency-input" value="1" min="0.001" step="0.001">
        </td>
        <td style="width:110px">
          <input type="number" name="lines[${idx}][unit_price]" class="form-control form-control-sm line-price currency-input" value="0.00" min="0" step="0.01">
        </td>
        <td style="width:80px">
          <input type="number" name="lines[${idx}][discount]" class="form-control form-control-sm line-disc currency-input" value="0" min="0" max="100" step="0.01">
        </td>
        <td style="min-width:140px">
          <select name="lines[${idx}][tax_code]" class="form-select form-select-sm line-tax" data-row="${idx}">
            ${taxOptions}
          </select>
        </td>
        <td class="amount-cell" style="width:110px">
          <input type="hidden" name="lines[${idx}][line_total]" class="line-total-hidden" value="0">
          <span class="line-total-display fw-semibold">0.00</span>
        </td>
        <td class="text-center" style="width:48px">
          <button type="button" class="btn-remove-line" title="Remove line"><i class="bi bi-x-lg"></i></button>
        </td>
      </tr>`;
  }

  // =============================================
  // ADD ROW
  // =============================================
  if (addRowBtn) {
    addRowBtn.addEventListener('click', function () {
      const tr = document.createElement('tbody');
      tr.innerHTML = createRowHtml(rowIndex++);
      tbody.appendChild(tr.firstElementChild);
      recalcTotals();
    });
  }

  // =============================================
  // REMOVE ROW
  // =============================================
  tbody.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remove-line');
    if (!btn) return;
    const row = btn.closest('tr');
    if (tbody.querySelectorAll('tr').length <= 1) {
      showToast('At least one line item is required.', 'warning');
      return;
    }
    row.remove();
    recalcTotals();
  });

  // =============================================
  // ITEM SELECT → AUTO-FILL VIA AJAX
  // =============================================
  tbody.addEventListener('change', function (e) {
    const select = e.target.closest('.item-select');
    if (!select) return;

    const row   = select.closest('tr');
    const id    = select.value;
    if (!id) return;

    // Try local cache first
    if (ITEMS[id]) {
      fillRowFromItem(row, ITEMS[id]);
      recalcTotals();
      return;
    }

    // Fallback: AJAX fetch
    YL.fetchWithCsrf('/invoices/ajax-item/' + id)
      .then(function (r) { return r.json(); })
      .then(function (item) {
        ITEMS[id] = item;
        fillRowFromItem(row, item);
        recalcTotals();
      })
      .catch(function () {
        showToast('Could not load item details.', 'danger');
      });
  });

  function fillRowFromItem(row, item) {
    row.querySelector('.line-desc').value  = item.name || '';
    row.querySelector('.line-price').value = parseFloat(item.price || 0).toFixed(2);
    row.querySelector('.line-account').value = item.account_id || '';

    // Auto-select tax if item has default
    if (item.tax_code) {
      const taxSel = row.querySelector('.line-tax');
      const opt = taxSel.querySelector(`option[value="${item.tax_code}"]`);
      if (opt) taxSel.value = item.tax_code;
    }
  }

  // =============================================
  // REAL-TIME TOTALS ON INPUT
  // =============================================
  tbody.addEventListener('input', function (e) {
    if (e.target.matches('.line-qty, .line-price, .line-disc, .line-tax')) {
      recalcTotals();
    }
  });

  tbody.addEventListener('change', function (e) {
    if (e.target.matches('.line-tax')) {
      recalcTotals();
    }
  });

  function recalcTotals() {
    let subtotal = 0;
    let totalDiscount = 0;
    let totalTax = 0;

    tbody.querySelectorAll('tr').forEach(function (row) {
      const qty   = parseFloat(row.querySelector('.line-qty')?.value)   || 0;
      const price = parseFloat(row.querySelector('.line-price')?.value) || 0;
      const disc  = parseFloat(row.querySelector('.line-disc')?.value)  || 0;
      const taxSel = row.querySelector('.line-tax');
      const taxRate = taxSel ? (parseFloat(taxSel.selectedOptions[0]?.dataset.rate) || 0) : 0;

      const lineSubtotal  = qty * price;
      const lineDiscount  = lineSubtotal * (disc / 100);
      const lineAfterDisc = lineSubtotal - lineDiscount;
      const lineTax       = lineAfterDisc * (taxRate / 100);
      const lineTotal     = lineAfterDisc + lineTax;

      subtotal      += lineSubtotal;
      totalDiscount += lineDiscount;
      totalTax      += lineTax;

      const displayEl = row.querySelector('.line-total-display');
      const hiddenEl  = row.querySelector('.line-total-hidden');
      if (displayEl) displayEl.textContent = YL.formatNumber(lineTotal);
      if (hiddenEl)  hiddenEl.value        = lineTotal.toFixed(4);
    });

    const grandTotal = subtotal - totalDiscount + totalTax;

    if (subtotalEl) subtotalEl.textContent = YL.formatNumber(subtotal);
    if (discountEl) discountEl.textContent = YL.formatNumber(totalDiscount);
    if (taxEl)      taxEl.textContent      = YL.formatNumber(totalTax);
    if (totalEl)    totalEl.textContent    = YL.formatNumber(grandTotal);
  }

  // =============================================
  // FORM SUBMIT VALIDATION
  // =============================================
  if (form) {
    form.addEventListener('submit', function (e) {
      const rows = tbody.querySelectorAll('tr');
      if (rows.length === 0) {
        e.preventDefault();
        showToast('Please add at least one line item.', 'danger');
        return;
      }

      let hasItem = false;
      rows.forEach(function (row) {
        const qty   = parseFloat(row.querySelector('.line-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.line-price')?.value) || 0;
        if (qty > 0 && price > 0) hasItem = true;
      });

      if (!hasItem) {
        e.preventDefault();
        showToast('Each line item must have a quantity and price.', 'danger');
      }
    });
  }

  // =============================================
  // TOAST HELPER
  // =============================================
  function showToast(message, type) {
    type = type || 'info';
    const colorMap = { danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-info', success: 'text-bg-success' };
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center ' + (colorMap[type] || 'text-bg-secondary') + ' border-0 position-fixed bottom-0 end-0 m-3';
    toastEl.setAttribute('role', 'alert');
    toastEl.style.zIndex = 9999;
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body fw-semibold">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    document.body.appendChild(toastEl);
    const t = new bootstrap.Toast(toastEl, { delay: 4000 });
    t.show();
    toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
  }

  // =============================================
  // INIT
  // =============================================
  recalcTotals();

})();
