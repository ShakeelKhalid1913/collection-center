(function () {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const toggle = document.getElementById('sidebar-toggle');

  function openSidebar() {
    sidebar?.classList.remove('-translate-x-full');
    backdrop?.classList.remove('hidden');
  }

  function closeSidebar() {
    sidebar?.classList.add('-translate-x-full');
    backdrop?.classList.add('hidden');
  }

  toggle?.addEventListener('click', openSidebar);
  backdrop?.addEventListener('click', closeSidebar);

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
      closeSidebar();
    }
  });

  function money(n) {
    return 'Rs. ' + Math.max(0, Math.round(n)).toLocaleString('en-PK');
  }

  function initBilling() {
    const root = document.querySelector('[data-billing]');
    if (!root) return;

    const subtotalEl = root.querySelector('[data-subtotal]');
    const totalEl = root.querySelector('[data-total]');
    const remainingEl = root.querySelector('[data-remaining]');
    const discountEl = root.querySelector('[data-discount]');
    const discountPctEl = root.querySelector('[data-discount-pct]');
    const paidEl = root.querySelector('[data-paid]');
    const amountInput = root.querySelector('[data-amount-input]');
    const checks = document.querySelectorAll('.catalog-check');

    let syncing = false;

    function subtotal() {
      let sum = 0;
      checks.forEach((c) => {
        if (c.checked) sum += Number(c.dataset.price || 0);
      });
      return sum;
    }

    function recalc(source) {
      if (syncing) return;
      syncing = true;

      const sub = subtotal();
      let discount = Number(discountEl?.value || 0);
      let pct = Number(discountPctEl?.value || 0);

      if (source === 'pct' && sub > 0) {
        discount = Math.round((sub * pct) / 100);
        if (discountEl) discountEl.value = String(discount);
      } else if (source === 'discount' && sub > 0) {
        pct = Math.min(100, Math.round((discount / sub) * 100));
        if (discountPctEl) discountPctEl.value = String(pct);
      }

      discount = Math.min(discount, sub);
      const total = Math.max(0, sub - discount);
      const paid = Number(paidEl?.value || 0);
      const remaining = Math.max(0, total - paid);

      if (subtotalEl) subtotalEl.textContent = money(sub);
      if (totalEl) totalEl.textContent = money(total);
      if (amountInput) amountInput.value = String(total);
      if (remainingEl) {
        remainingEl.textContent = money(remaining);
        remainingEl.classList.toggle('text-amber-700', remaining > 0);
        remainingEl.classList.toggle('text-emerald-700', remaining === 0);
      }

      syncing = false;
    }

    checks.forEach((c) => c.addEventListener('change', () => recalc('check')));
    discountEl?.addEventListener('input', () => recalc('discount'));
    discountPctEl?.addEventListener('input', () => recalc('pct'));
    paidEl?.addEventListener('input', () => recalc('paid'));
    recalc('init');
  }

  function initCatalogSearch() {
    const workspace = document.querySelector('[data-catalog-workspace]');
    if (!workspace) return;

    const select = workspace.querySelector('[data-catalog-select]');
    const addBtn = workspace.querySelector('[data-catalog-add]');
    const emptyEl = workspace.querySelector('[data-catalog-empty]');
    const typeButtons = workspace.querySelectorAll('[data-catalog-type]');
    let activeType = typeButtons[0]?.getAttribute('data-catalog-type') || '';

    function refreshSelected() {
      const items = workspace.querySelectorAll('[data-catalog-item]');
      let any = false;
      items.forEach((item) => {
        const check = item.querySelector('.catalog-check');
        const on = !!(check && check.checked);
        item.classList.toggle('hidden', !on);
        item.style.display = on ? '' : 'none';
        if (on) any = true;
      });
      if (emptyEl) emptyEl.style.display = any ? 'none' : '';
    }

    function filterDropdown() {
      if (!select) return;
      Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) {
          opt.hidden = false;
          return;
        }
        const optDept = (opt.getAttribute('data-dept') || '').toLowerCase();
        opt.hidden = !!(activeType && optDept !== activeType);
      });
      const current = select.selectedOptions[0];
      if (current && current.hidden) select.value = '';
    }

    typeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        activeType = btn.getAttribute('data-catalog-type') || '';
        typeButtons.forEach((b) => b.classList.toggle('is-active', b === btn));
        filterDropdown();
      });
    });

    addBtn?.addEventListener('click', () => {
      if (!select || !select.value) return;
      const name = select.value;
      const checkbox = Array.from(workspace.querySelectorAll('.catalog-check')).find(
        (el) => el.value === name
      );
      if (checkbox) {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
      }
      select.value = '';
      refreshSelected();
    });

    select?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addBtn?.click();
      }
    });

    workspace.querySelectorAll('[data-catalog-remove]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const item = btn.closest('[data-catalog-item]');
        const check = item?.querySelector('.catalog-check');
        if (check) {
          check.checked = false;
          check.dispatchEvent(new Event('change', { bubbles: true }));
        }
        refreshSelected();
      });
    });

    filterDropdown();
    refreshSelected();
  }


  function loadHtml2Pdf() {
    return new Promise((resolve, reject) => {
      if (window.html2pdf) {
        resolve(window.html2pdf);
        return;
      }
      const existing = document.querySelector('script[data-html2pdf]');
      if (existing) {
        existing.addEventListener('load', () => resolve(window.html2pdf));
        existing.addEventListener('error', reject);
        return;
      }
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
      s.async = true;
      s.dataset.html2pdf = '1';
      s.onload = () => resolve(window.html2pdf);
      s.onerror = () => reject(new Error('Could not load PDF library'));
      document.head.appendChild(s);
    });
  }

  function initPdfDownload() {
    document.querySelectorAll('[data-download-pdf]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const area = document.querySelector('.print-area');
        const status = btn.parentElement?.querySelector('[data-pdf-status]');
        if (!area) {
          if (status) status.textContent = 'Nothing to export on this page.';
          return;
        }

        const filename = (btn.getAttribute('data-pdf-name') || 'lab-report') + '.pdf';
        btn.disabled = true;
        if (status) status.textContent = 'Preparing PDF…';

        try {
          const html2pdf = await loadHtml2Pdf();
          await html2pdf()
            .set({
              margin: [8, 8, 8, 8],
              filename,
              image: { type: 'jpeg', quality: 0.98 },
              html2canvas: { scale: 2, useCORS: true, logging: false },
              jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
              pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
            })
            .from(area)
            .save();
          if (status) status.textContent = 'Downloaded.';
          setTimeout(() => {
            if (status) status.textContent = '';
          }, 2500);
        } catch (err) {
          console.error(err);
          if (status) status.textContent = 'PDF failed — use Print → Save as PDF.';
          // Fallback
          window.print();
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  function initTestParameters() {
    const list = document.querySelector('[data-param-list]');
    const addBtn = document.querySelector('[data-add-param]');
    if (!list || !addBtn) return;

    addBtn.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'grid gap-2 items-end p-2 bg-slate-50 border border-slate-200 rounded';
      row.style.gridTemplateColumns = '0.9fr 1.1fr 0.5fr 0.7fr 0.7fr auto';
      row.innerHTML = `
        <div>
          <label class="field-label text-xs">Section / Group</label>
          <input type="text" name="param_section[]" class="field text-sm" placeholder="e.g. ERYTHROCYTES">
        </div>
        <div>
          <label class="field-label text-xs">Parameter Name</label>
          <input type="text" name="param_name[]" class="field text-sm" placeholder="e.g. Hemoglobin (HB)" required>
        </div>
        <div>
          <label class="field-label text-xs">Unit</label>
          <input type="text" name="param_unit[]" class="field text-sm" placeholder="g/dl">
        </div>
        <div>
          <label class="field-label text-xs">Normal Value</label>
          <input type="text" name="param_normal[]" class="field text-sm" placeholder="12.0 - 16.5">
        </div>
        <div>
          <label class="field-label text-xs">Reference Range</label>
          <input type="text" name="param_range[]" class="field text-sm" placeholder="12.0 - 16.5">
        </div>
        <div class="flex items-end pb-1">
          <button type="button" class="btn btn-secondary" style="padding:0.4rem 0.6rem;color:#dc2626" data-remove-param title="Remove">&times;</button>
        </div>
      `;
      list.appendChild(row);
      
      row.querySelector('[data-remove-param]').addEventListener('click', () => {
        row.remove();
      });
    });
    
    // Attach to any existing remove buttons (if pre-rendered in edit mode)
    list.querySelectorAll('[data-remove-param]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.target.closest('div.grid').remove();
      });
    });
  }

  initBilling();
  initCatalogSearch();
  initPdfDownload();
  initTestParameters();
})();
