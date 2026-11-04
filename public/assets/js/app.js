/**
 * YingLume Finance — Main Application JavaScript
 * Vanilla JS, no dependencies beyond Bootstrap 5
 */

'use strict';

// =============================================
// SIDEBAR TOGGLE (Mobile)
// =============================================
(function () {
  const sidebar   = document.getElementById('sidebar');
  const backdrop  = document.getElementById('sidebar-backdrop');
  const toggleBtn = document.getElementById('sidebar-toggle');

  if (!sidebar) return;

  function openSidebar() {
    sidebar.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
    document.body.style.overflow = '';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  // Close sidebar on escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar.classList.contains('show')) {
      closeSidebar();
    }
  });
})();

// =============================================
// ACTIVE SIDEBAR NAV DETECTION
// =============================================
(function () {
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll('#sidebar .nav-link');

  navLinks.forEach(function (link) {
    const href = link.getAttribute('href');
    if (href && currentPath.startsWith(href) && href !== '/') {
      link.classList.add('active');
      // Open parent collapse if exists
      const parentCollapse = link.closest('.collapse');
      if (parentCollapse) {
        parentCollapse.classList.add('show');
        const trigger = document.querySelector('[data-bs-target="#' + parentCollapse.id + '"]');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }
    }
  });
})();

// =============================================
// FLASH MESSAGE AUTO-DISMISS (5 seconds)
// =============================================
(function () {
  const alerts = document.querySelectorAll('.alert-dismissible[data-auto-dismiss]');
  alerts.forEach(function (alert) {
    const delay = parseInt(alert.dataset.autoDismiss) || 5000;
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      if (bsAlert) bsAlert.close();
    }, delay);
  });
})();

// =============================================
// FORM SUBMIT — LOADING STATE
// =============================================
(function () {
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.noLoading) return;

    const submitBtns = form.querySelectorAll('[type="submit"]:not([data-no-loading])');
    submitBtns.forEach(function (btn) {
      btn.disabled = true;
      const originalHtml = btn.innerHTML;
      btn.dataset.originalHtml = originalHtml;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Please wait…';
    });

    // Show overlay if exists
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) overlay.classList.add('active');
  });
})();

// =============================================
// CSRF TOKEN FOR FETCH() CALLS
// =============================================
const csrfToken = (function () {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
})();

function fetchWithCsrf(url, options = {}) {
  options.headers = Object.assign({
    'X-CSRF-Token': csrfToken,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }, options.headers || {});
  return fetch(url, options);
}

// =============================================
// GENERIC AJAX DELETE WITH CONFIRMATION MODAL
// =============================================
(function () {
  // Requires a modal with id="deleteModal" in the layout
  const modal    = document.getElementById('deleteModal');
  const confirmBtn = document.getElementById('deleteConfirmBtn');
  if (!modal || !confirmBtn) return;

  let deleteUrl = '';

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-delete-url]');
    if (!btn) return;
    deleteUrl = btn.dataset.deleteUrl;
    const name = btn.dataset.deleteName || 'this item';
    const msgEl = modal.querySelector('#deleteModalBody');
    if (msgEl) msgEl.textContent = 'Are you sure you want to delete "' + name + '"? This cannot be undone.';
    bootstrap.Modal.getOrCreateInstance(modal).show();
  });

  confirmBtn.addEventListener('click', function () {
    if (!deleteUrl) return;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetchWithCsrf(deleteUrl, { method: 'DELETE' })
      .then(function (res) {
        if (res.ok) {
          window.location.reload();
        } else {
          return res.json().then(function (data) {
            alert(data.message || 'Delete failed.');
          });
        }
      })
      .catch(function () {
        alert('Network error. Please try again.');
      })
      .finally(function () {
        bootstrap.Modal.getOrCreateInstance(modal).hide();
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = 'Delete';
      });
  });
})();

// =============================================
// NUMBER FORMATTING UTILITIES
// =============================================

/**
 * Format a number with commas and 2 decimal places
 * @param {number|string} value
 * @returns {string}
 */
function formatNumber(value) {
  const n = parseFloat(value) || 0;
  return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Parse a formatted number string back to float
 * @param {string} str
 * @returns {number}
 */
function parseNumber(str) {
  if (typeof str === 'number') return str;
  return parseFloat((str || '').replace(/,/g, '')) || 0;
}

// =============================================
// CURRENCY INPUT MASKING
// =============================================
(function () {
  document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('currency-input')) return;
    const input = e.target;
    let val = input.value.replace(/[^0-9.]/g, '');
    // Allow only one decimal point
    const parts = val.split('.');
    if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
    // Limit to 2 decimal places
    if (parts[1] && parts[1].length > 2) {
      val = parts[0] + '.' + parts[1].substring(0, 2);
    }
    input.value = val;
  });
})();

// =============================================
// TOOLTIP INITIALIZATION
// =============================================
(function () {
  const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltipEls.forEach(function (el) {
    bootstrap.Tooltip.getOrCreateInstance(el);
  });
})();

// =============================================
// CONFIRM BEFORE NAVIGATE (UNSAVED CHANGES)
// =============================================
(function () {
  let formDirty = false;
  document.addEventListener('input', function (e) {
    if (e.target.form && !e.target.form.dataset.noConfirm) {
      formDirty = true;
    }
  });
  document.addEventListener('submit', function () { formDirty = false; });
  window.addEventListener('beforeunload', function (e) {
    if (formDirty) {
      e.preventDefault();
      e.returnValue = 'You have unsaved changes. Leave anyway?';
    }
  });
})();

// =============================================
// EXPORTABLE: expose to window
// =============================================
window.YL = {
  formatNumber,
  parseNumber,
  fetchWithCsrf,
  csrfToken,
};
