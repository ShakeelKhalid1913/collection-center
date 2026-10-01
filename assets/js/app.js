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

    const searchInput = workspace.querySelector('[data-catalog-search]');
    const hitsBox = workspace.querySelector('[data-catalog-hits]');
    const hitsHint = workspace.querySelector('[data-catalog-hits-hint]');
    const emptyEl = workspace.querySelector('[data-catalog-empty]');
    const typeButtons = workspace.querySelectorAll('[data-catalog-type]');
    const hits = Array.from(workspace.querySelectorAll('[data-catalog-hit]'));
    let activeType = '';

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

    function addTestByName(name) {
      if (!name) return;
      const checkbox = Array.from(workspace.querySelectorAll('.catalog-check')).find(
        (el) => el.value === name
      );
      if (checkbox) {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
      }
      refreshSelected();
      if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
      }
      applyFilters();
    }

    function applyFilters() {
      const q = (searchInput?.value || '').trim().toLowerCase();
      let shown = 0;
      const maxShow = q ? 40 : 25;

      hits.forEach((hit) => {
        const dept = (hit.getAttribute('data-dept') || '').toLowerCase();
        const hay = (hit.getAttribute('data-search') || '').toLowerCase();
        const matchType = !activeType || dept === activeType;
        const matchQ = !q || hay.includes(q);
        const ok = matchType && matchQ && shown < maxShow;
        if (matchType && matchQ && shown < maxShow) {
          shown += 1;
        }
        hit.hidden = !ok;
        hit.classList.toggle('hidden', !ok);
      });

      if (hitsBox) {
        hitsBox.classList.toggle('is-empty', shown === 0);
      }
      if (hitsHint) {
        if (shown === 0) {
          hitsHint.textContent = q
            ? 'No tests match “' + searchInput.value.trim() + '”. Try another name or code.'
            : 'No tests in this type.';
        } else if (q) {
          hitsHint.textContent = shown + ' match' + (shown === 1 ? '' : 'es') + ' — click to add.';
        } else {
          hitsHint.textContent = activeType
            ? 'Showing tests in this type. Search above to find any test by name or code.'
            : 'Type to search all tests, or pick a type on the left. Click a result to add it.';
        }
      }
    }

    typeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        activeType = btn.getAttribute('data-catalog-type') || '';
        typeButtons.forEach((b) => b.classList.toggle('is-active', b === btn));
        applyFilters();
      });
    });

    searchInput?.addEventListener('input', applyFilters);
    searchInput?.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const first = hits.find((h) => !h.hidden);
      if (first) addTestByName(first.getAttribute('data-name') || '');
    });

    hits.forEach((hit) => {
      hit.addEventListener('click', () => {
        addTestByName(hit.getAttribute('data-name') || '');
      });
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

    applyFilters();
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
        const area = document.querySelector('[data-print-stack]') || document.querySelector('.print-area');
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
              margin: [2, 0, 2, 0],
              filename,
              image: { type: 'jpeg', quality: 0.98 },
              html2canvas: { scale: 2, useCORS: true, logging: false },
              jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
              pagebreak: { mode: ['css', 'legacy'] },
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
          window.print();
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  function computeFlagJs(value, range) {
    const v = String(value || '').trim();
    const r = String(range || '').trim();
    if (!v || !r || r === '—' || Number.isNaN(Number(v))) return '';
    const num = Number(v);
    let m = r.match(/^([0-9]+(?:\.[0-9]+)?)\s*[\-–]\s*([0-9]+(?:\.[0-9]+)?)$/);
    if (m) {
      const low = Number(m[1]);
      const high = Number(m[2]);
      if (num < low) return 'L';
      if (num > high) return 'H';
      return '';
    }
    m = r.match(/^<\s*([0-9]+(?:\.[0-9]+)?)$/);
    if (m) return num >= Number(m[1]) ? 'H' : '';
    m = r.match(/^>\s*([0-9]+(?:\.[0-9]+)?)$/);
    if (m) return num <= Number(m[1]) ? 'L' : '';
    return '';
  }

  function flagDisplayHtml(flag, value) {
    const f = String(flag || '').toLowerCase();
    const v = String(value || '').trim();
    if (f === 'l') return '<span class="font-bold" style="color:#2563eb" title="Low">↓ Low</span>';
    if (f === 'h') return '<span class="font-bold" style="color:#dc2626" title="High">↑ High</span>';
    if (f === 'critical') return '<span class="font-bold" style="color:#dc2626" title="Critical">! Critical</span>';
    if (v !== '' && !Number.isNaN(Number(v))) {
      return '<span class="font-bold" style="color:#16a34a" title="Normal">✓ Normal</span>';
    }
    return '—';
  }

  function initResultEntry() {
    const form = document.querySelector('[data-results-entry]');
    if (!form) return;

    form.querySelectorAll('[data-result-input]').forEach((input) => {
      const refreshFlag = () => {
        const row = input.closest('[data-result-row]');
        if (!row) return;
        const range = input.getAttribute('data-ref-range') || row.querySelector('input[name*="[range]"]')?.value || '';
        const flag = input.tagName === 'SELECT' ? '' : computeFlagJs(input.value, range);
        const hidden = row.querySelector('[data-auto-flag]');
        const display = row.querySelector('[data-flag-display]');
        if (hidden) hidden.value = flag;
        if (display) display.innerHTML = flagDisplayHtml(flag, input.value);
        input.classList.toggle('text-blue-700', flag === 'L');
        input.classList.toggle('border-blue-400', flag === 'L');
        input.classList.toggle('text-red-600', flag === 'H' || flag === 'critical');
        input.classList.toggle('border-red-400', flag === 'H' || flag === 'critical');
        input.classList.toggle('text-green-700', flag === '' && input.value.trim() !== '' && !Number.isNaN(Number(input.value)));
        input.classList.toggle('border-green-400', flag === '' && input.value.trim() !== '' && !Number.isNaN(Number(input.value)));
      };

      input.addEventListener('input', refreshFlag);
      input.addEventListener('change', refreshFlag);

      input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const inputs = Array.from(form.querySelectorAll('[data-result-input]'));
        const idx = inputs.indexOf(input);
        if (idx >= 0 && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
          if (typeof inputs[idx + 1].select === 'function') inputs[idx + 1].select();
        }
      });
    });

    initPageGrouping(form);
  }

  function initPageGrouping(form) {
    const board = form.querySelector('[data-page-board]');
    const addBtn = form.querySelector('[data-add-page]');
    if (!board) return;

    let dragChip = null;

    function bindChip(chip) {
      chip.addEventListener('dragstart', () => {
        dragChip = chip;
        chip.classList.add('opacity-60');
      });
      chip.addEventListener('dragend', () => {
        chip.classList.remove('opacity-60');
        dragChip = null;
      });
    }

    function bindPage(pageEl) {
      const list = pageEl.querySelector('[data-page-list]');
      if (!list) return;
      pageEl.addEventListener('dragover', (e) => {
        e.preventDefault();
        pageEl.classList.add('ring-2', 'ring-teal-400');
      });
      pageEl.addEventListener('dragleave', () => {
        pageEl.classList.remove('ring-2', 'ring-teal-400');
      });
      pageEl.addEventListener('drop', (e) => {
        e.preventDefault();
        pageEl.classList.remove('ring-2', 'ring-teal-400');
        if (!dragChip) return;
        const emptyHint = list.querySelector('.italic');
        if (emptyHint) emptyHint.remove();
        list.appendChild(dragChip);
        const pageNo = pageEl.getAttribute('data-page') || '1';
        const hidden = dragChip.querySelector('[data-page-map]');
        if (hidden) hidden.value = pageNo;
      });
    }

    board.querySelectorAll('.page-group-chip').forEach(bindChip);
    board.querySelectorAll('.page-group-page').forEach(bindPage);

    addBtn?.addEventListener('click', () => {
      const pages = board.querySelectorAll('.page-group-page');
      const next = pages.length + 1;
      const el = document.createElement('div');
      el.className = 'page-group-page border border-dashed border-slate-300 rounded-lg p-2 bg-slate-50 space-y-1.5';
      el.setAttribute('data-page', String(next));
      el.innerHTML = `
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Page ${next}</div>
        <div class="page-group-list space-y-1.5 min-h-[2rem]" data-page-list="${next}">
          <div class="text-[11px] text-slate-400 italic px-1 py-2">Drop tests here</div>
        </div>`;
      board.appendChild(el);
      bindPage(el);
    });
  }

  function initPrintToggles() {
    const stack = document.querySelector('[data-print-stack]');
    const toolbar = document.querySelector('[data-print-toolbar]');
    if (!stack || !toolbar) return;

    const headerCb = toolbar.querySelector('[data-print-toggle="hide-header"]');
    const qrCb = toolbar.querySelector('[data-print-toggle="hide-qr"]');
    const allCb = toolbar.querySelector('[data-print-toggle="hide-all"]');

    function apply() {
      const hideAll = !!allCb?.checked;
      stack.classList.toggle('lab-print--hide-all', hideAll);
      stack.classList.toggle('lab-print--hide-header', hideAll || !!headerCb?.checked);
      stack.classList.toggle('lab-print--hide-qr', hideAll || !!qrCb?.checked);
      if (hideAll) {
        if (headerCb) headerCb.checked = true;
        if (qrCb) qrCb.checked = true;
      }
    }

    [headerCb, qrCb, allCb].forEach((cb) => cb?.addEventListener('change', apply));
    apply();
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
    
    list.querySelectorAll('[data-remove-param]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.target.closest('div.grid').remove();
      });
    });
  }

  function initHeaderAlign() {
    const root = document.querySelector('[data-header-align]');
    if (!root) return;

    const input = root.querySelector('[data-header-align-input]');
    const label = root.querySelector('[data-header-align-label]');
    const thumb = root.querySelector('[data-header-align-thumb]');
    const track = root.querySelector('[data-header-align-track]');
    const zones = Array.from(root.querySelectorAll('[data-align]')).filter((el) => el.matches('.header-align__zone'));

    function setAlign(pos) {
      const next = pos === 'center' || pos === 'right' ? pos : 'left';
      if (input) input.value = next;
      if (label) label.textContent = next.charAt(0).toUpperCase() + next.slice(1);
      if (thumb) thumb.setAttribute('data-align', next);
      zones.forEach((z) => z.classList.toggle('is-active', z.getAttribute('data-align') === next));
    }

    zones.forEach((z) => {
      z.addEventListener('click', () => setAlign(z.getAttribute('data-align') || 'left'));
    });

    function posFromClientX(clientX) {
      if (!track) return 'left';
      const rect = track.getBoundingClientRect();
      const ratio = (clientX - rect.left) / Math.max(1, rect.width);
      if (ratio < 0.33) return 'left';
      if (ratio < 0.66) return 'center';
      return 'right';
    }

    let dragging = false;

    thumb?.addEventListener('pointerdown', (e) => {
      dragging = true;
      thumb.setPointerCapture?.(e.pointerId);
      e.preventDefault();
    });

    thumb?.addEventListener('pointermove', (e) => {
      if (!dragging) return;
      setAlign(posFromClientX(e.clientX));
    });

    const endDrag = () => {
      dragging = false;
    };
    thumb?.addEventListener('pointerup', endDrag);
    thumb?.addEventListener('pointercancel', endDrag);

    // HTML5 drag fallback
    thumb?.addEventListener('dragstart', (e) => {
      e.dataTransfer?.setData('text/plain', 'header-align');
    });
    track?.addEventListener('dragover', (e) => {
      e.preventDefault();
      setAlign(posFromClientX(e.clientX));
    });
    track?.addEventListener('drop', (e) => {
      e.preventDefault();
      setAlign(posFromClientX(e.clientX));
    });
  }

  initBilling();
  initCatalogSearch();
  initPdfDownload();
  initTestParameters();
  initResultEntry();
  initPrintToggles();
  initHeaderAlign();
})();
