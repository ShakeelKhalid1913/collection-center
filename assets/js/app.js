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
              margin: [2, 6, 2, 6],
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

      const rangeInput = input.closest('[data-result-row]')?.querySelector('input[name*="[range]"]');
      if (rangeInput) {
        rangeInput.addEventListener('input', refreshFlag);
      }

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
    const footerCb = toolbar.querySelector('[data-print-toggle="hide-footer"]');
    const allCb = toolbar.querySelector('[data-print-toggle="hide-all"]');

    // Restore saved toggle states
    try {
      if (headerCb && localStorage.getItem('print_hide_header') === '1') headerCb.checked = true;
      if (qrCb && localStorage.getItem('print_hide_qr') === '1') qrCb.checked = true;
      if (footerCb && localStorage.getItem('print_hide_footer') === '1') footerCb.checked = true;
      if (allCb && localStorage.getItem('print_hide_all') === '1') allCb.checked = true;
    } catch (_) {}

    function apply() {
      const hideAll = !!allCb?.checked;
      stack.classList.toggle('lab-print--hide-all', hideAll);
      stack.classList.toggle('lab-print--hide-header', hideAll || !!headerCb?.checked);
      stack.classList.toggle('lab-print--hide-qr', hideAll || !!qrCb?.checked);
      stack.classList.toggle('lab-print--hide-footer', hideAll || !!footerCb?.checked);
      if (hideAll) {
        if (headerCb) headerCb.checked = true;
        if (qrCb) qrCb.checked = true;
        if (footerCb) footerCb.checked = true;
      }
      try {
        localStorage.setItem('print_hide_header', headerCb?.checked ? '1' : '0');
        localStorage.setItem('print_hide_qr', qrCb?.checked ? '1' : '0');
        localStorage.setItem('print_hide_footer', footerCb?.checked ? '1' : '0');
        localStorage.setItem('print_hide_all', allCb?.checked ? '1' : '0');
      } catch (_) {}
    }

    [headerCb, qrCb, footerCb, allCb].forEach((cb) => cb?.addEventListener('change', apply));
    apply();
  }

  function initTestParameters() {
    const list = document.querySelector('[data-param-list]');
    const addBtn = document.querySelector('[data-add-param]');
    if (!list || !addBtn) return;

    addBtn.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2';
      row.setAttribute('data-param-row', '');
      row.innerHTML = `
        <div class="grid gap-2 items-end" style="grid-template-columns: 0.9fr 1.1fr 0.5fr 0.7fr 0.7fr auto;">
          <div>
            <label class="field-label text-xs">Section / Group</label>
            <input type="text" name="param_section[]" class="field text-sm" placeholder="e.g. ERYTHROCYTES">
          </div>
          <div>
            <label class="field-label text-xs">Parameter Name <span class="text-red-500">*</span></label>
            <input type="text" name="param_name[]" class="field text-sm font-semibold" placeholder="e.g. Hemoglobin (HB)" required>
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
            <button type="button" class="btn btn-secondary text-sm font-bold text-red-600 hover:bg-red-50 hover:border-red-300" style="padding:0.4rem 0.65rem;" data-remove-param title="Remove Parameter">&times;</button>
          </div>
        </div>
        <div class="grid gap-3 pt-2 border-t border-slate-200/80 sm:grid-cols-2 items-start">
          <div>
            <details class="mt-1.5 text-xs text-slate-600 criteria-table-wrap" data-criteria-builder>
              <summary class="cursor-pointer font-semibold text-slate-600 hover:text-teal-700 inline-flex items-center gap-1.5 py-0.5 select-none">
                <i class="fa-solid fa-table-list text-teal-600 text-[11px]"></i>
                <span>Reference Criteria Table (<span data-criteria-count>0</span>)</span>
              </summary>
              <div class="mt-1.5 p-2.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Criteria &amp; Specific Ranges</span>
                  <button type="button" class="btn btn-secondary text-[11px] py-1 px-2.5 text-teal-700 bg-teal-50 border-teal-300 hover:bg-teal-100 font-semibold" data-add-criteria-row>
                    <i class="fa-solid fa-plus mr-1"></i> Add Row
                  </button>
                </div>
                <table class="w-full text-xs criteria-table-editor border border-slate-200 rounded overflow-hidden bg-white">
                  <thead class="bg-slate-100/90 text-[10px] font-bold text-slate-600 uppercase">
                    <tr>
                      <th class="px-2 py-1 text-left w-7/12">Criteria / Age / Condition</th>
                      <th class="px-2 py-1 text-left w-4/12">Reference Range</th>
                      <th class="px-1 py-1 text-center w-8"></th>
                    </tr>
                  </thead>
                  <tbody data-criteria-tbody></tbody>
                </table>
                <textarea name="param_sub_table[]" class="hidden criteria-serialized-textarea"></textarea>
              </div>
            </details>
          </div>
          <div>
            <label class="field-label text-xs text-slate-600">Default Result Note / Remark <span class="text-slate-400 font-normal">(shown below result)</span></label>
            <textarea name="param_result_note[]" rows="2" class="field text-xs w-full py-1 px-2" placeholder="e.g. Serum index: Normal (optional)"></textarea>
          </div>
        </div>
      `;
      list.appendChild(row);

      row.querySelector('[data-remove-param]')?.addEventListener('click', () => {
        row.remove();
      });

      initCriteriaTableBuilders();
    });

    list.querySelectorAll('[data-remove-param]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        const item = e.target.closest('[data-param-row]') || e.target.closest('div.grid');
        if (item) item.remove();
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

  function initHeaderLayoutBuilder() {
    const canvas = document.getElementById('header-builder-canvas');
    if (!canvas) return;

    const logoItem = document.getElementById('drag-item-logo');
    const qrItem = document.getElementById('drag-item-qr');
    const jsonInput = document.getElementById('header_layout_json');
    const posInput = document.getElementById('header_image_position');
    const hSlider = document.getElementById('builder-h-slider');
    const hVal = document.getElementById('builder-h-val');
    const coordsStatus = document.getElementById('builder-coords-status');
    const presetBtns = document.querySelectorAll('[data-builder-preset]');
    const fileInput = document.getElementById('header_image');

    if (!logoItem || !qrItem) return;

    function syncState() {
      const cW = canvas.clientWidth || 760;
      const cH = parseInt(canvas.style.height, 10) || 90;

      const logoX = Math.round(parseFloat(logoItem.style.left) || 0);
      const logoY = Math.round(parseFloat(logoItem.style.top) || 0);
      const logoW = Math.round(parseFloat(logoItem.style.width) || 240);
      const logoH = Math.round(parseFloat(logoItem.style.height) || 70);

      const qrX = Math.round(parseFloat(qrItem.style.left) || 0);
      const qrY = Math.round(parseFloat(qrItem.style.top) || 0);
      const qrSize = Math.round(parseFloat(qrItem.style.width) || 60);

      const logoXPct = Number(((logoX / cW) * 100).toFixed(2));
      const logoWPct = Number(((logoW / cW) * 100).toFixed(2));
      const qrXPct = Number(((qrX / cW) * 100).toFixed(2));

      const data = {
        canvas_h: cH,
        logo: {
          x: logoX,
          y: logoY,
          w: logoW,
          h: logoH,
          x_pct: logoXPct,
          w_pct: logoWPct
        },
        qr: {
          x: qrX,
          y: qrY,
          size: qrSize,
          x_pct: qrXPct
        }
      };

      if (jsonInput) {
        jsonInput.value = JSON.stringify(data);
      }

      if (posInput) {
        if (logoX < cW * 0.3) {
          posInput.value = 'left';
        } else if (logoX > cW * 0.55) {
          posInput.value = 'right';
        } else {
          posInput.value = 'center';
        }
      }

      if (coordsStatus) {
        coordsStatus.textContent = `Logo: (${logoX}, ${logoY}) ${logoW}×${logoH} | QR: (${qrX}, ${qrY}) ${qrSize}×${qrSize}`;
      }
    }

    function setupDragAndResize(item, isSquare) {
      const handle = item.querySelector('.builder-resize-handle');
      let mode = null; // 'drag' | 'resize' | null
      let startX = 0;
      let startY = 0;
      let startL = 0;
      let startT = 0;
      let startW = 0;
      let startH = 0;

      item.addEventListener('pointerdown', (e) => {
        if (e.target === handle || handle?.contains(e.target)) {
          mode = 'resize';
          startX = e.clientX;
          startY = e.clientY;
          startW = item.offsetWidth;
          startH = item.offsetHeight;
          startL = item.offsetLeft;
          startT = item.offsetTop;
          handle?.setPointerCapture?.(e.pointerId);
        } else {
          mode = 'drag';
          startX = e.clientX;
          startY = e.clientY;
          startL = item.offsetLeft;
          startT = item.offsetTop;
          item?.setPointerCapture?.(e.pointerId);
        }
        item.classList.add('is-active');
        e.preventDefault();
      });

      const onPointerMove = (e) => {
        if (!mode) return;
        const cW = canvas.clientWidth || 760;
        const cH = canvas.clientHeight || 90;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        if (mode === 'drag') {
          const maxL = Math.max(0, cW - item.offsetWidth);
          const maxT = Math.max(0, cH - item.offsetHeight);
          const nextL = Math.max(0, Math.min(maxL, startL + dx));
          const nextT = Math.max(0, Math.min(maxT, startT + dy));
          item.style.left = nextL + 'px';
          item.style.top = nextT + 'px';
          syncState();
        } else if (mode === 'resize') {
          if (isSquare) {
            const maxDimension = Math.min(cW - startL, cH - startT);
            const rawSize = Math.max(startW + dx, startH + dy);
            const newSize = Math.max(35, Math.min(maxDimension, rawSize));
            item.style.width = newSize + 'px';
            item.style.height = newSize + 'px';
          } else {
            const maxW = Math.max(60, cW - startL);
            const maxH = Math.max(25, cH - startT);
            const newW = Math.max(60, Math.min(maxW, startW + dx));
            const newH = Math.max(25, Math.min(maxH, startH + dy));
            item.style.width = newW + 'px';
            item.style.height = newH + 'px';
          }
          syncState();
        }
      };

      const endAction = (e) => {
        if (!mode) return;
        try {
          if (mode === 'resize' && handle?.hasPointerCapture?.(e.pointerId)) {
            handle.releasePointerCapture(e.pointerId);
          } else if (mode === 'drag' && item?.hasPointerCapture?.(e.pointerId)) {
            item.releasePointerCapture(e.pointerId);
          }
        } catch (_) {}
        mode = null;
        item.classList.remove('is-active');
        syncState();
      };

      item.addEventListener('pointermove', onPointerMove);
      item.addEventListener('pointerup', endAction);
      item.addEventListener('pointercancel', endAction);
    }

    setupDragAndResize(logoItem, false);
    setupDragAndResize(qrItem, true);

    if (hSlider) {
      const onHeightChange = () => {
        const h = parseInt(hSlider.value, 10) || 90;
        canvas.style.height = h + 'px';
        if (hVal) hVal.textContent = h + 'px';

        const logoH = logoItem.offsetHeight;
        if (logoItem.offsetTop + logoH > h) {
          logoItem.style.top = Math.max(0, h - logoH) + 'px';
        }
        const qrH = qrItem.offsetHeight;
        if (qrItem.offsetTop + qrH > h) {
          qrItem.style.top = Math.max(0, h - qrH) + 'px';
        }
        syncState();
      };
      hSlider.addEventListener('input', onHeightChange);
      hSlider.addEventListener('change', onHeightChange);
    }

    presetBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const preset = btn.getAttribute('data-builder-preset');
        const cW = canvas.clientWidth || 760;

        if (preset === 'left') {
          logoItem.style.left = '15px';
          logoItem.style.top = '10px';
          logoItem.style.width = '240px';
          logoItem.style.height = '70px';

          const qrSize = qrItem.offsetWidth || 60;
          qrItem.style.left = Math.max(0, cW - qrSize - 15) + 'px';
          qrItem.style.top = '10px';
        } else if (preset === 'center') {
          const logoW = 240;
          logoItem.style.left = Math.max(0, Math.round((cW - logoW) / 2)) + 'px';
          logoItem.style.top = '10px';
          logoItem.style.width = logoW + 'px';
          logoItem.style.height = '70px';

          const qrSize = qrItem.offsetWidth || 60;
          qrItem.style.left = Math.max(0, cW - qrSize - 15) + 'px';
          qrItem.style.top = '10px';
        } else if (preset === 'right') {
          const logoW = 240;
          logoItem.style.left = Math.max(0, cW - logoW - 15) + 'px';
          logoItem.style.top = '10px';
          logoItem.style.width = logoW + 'px';
          logoItem.style.height = '70px';

          qrItem.style.left = '15px';
          qrItem.style.top = '10px';
        } else if (preset === 'default') {
          if (hSlider) {
            hSlider.value = '90';
            canvas.style.height = '90px';
            if (hVal) hVal.textContent = '90px';
          }
          logoItem.style.left = '15px';
          logoItem.style.top = '10px';
          logoItem.style.width = '240px';
          logoItem.style.height = '70px';

          qrItem.style.left = Math.max(0, cW - 75) + 'px';
          qrItem.style.top = '10px';
          qrItem.style.width = '60px';
          qrItem.style.height = '60px';
        }

        syncState();
      });
    });

    if (fileInput) {
      fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (file && file.type.startsWith('image/')) {
          const url = URL.createObjectURL(file);
          const previewImg = document.getElementById('builder-logo-preview-img');
          const previewText = document.getElementById('builder-logo-preview-text');
          if (previewImg) {
            previewImg.src = url;
          } else if (previewText) {
            previewText.outerHTML = `<img id="builder-logo-preview-img" src="${url}" alt="Header Logo" draggable="false" style="max-height:100%;max-width:100%;object-fit:contain;pointer-events:none;">`;
          }
        }
      });
    }

    // Initial sync
    syncState();
  }

  function initFooterLayoutBuilder() {
    const canvas = document.getElementById('footer-builder-canvas');
    if (!canvas) return;

    const imgItem = document.getElementById('drag-item-footer-img');
    const jsonInput = document.getElementById('footer_layout_json');
    const hSlider = document.getElementById('footer-builder-h-slider');
    const hVal = document.getElementById('footer-builder-h-val');
    const coordsStatus = document.getElementById('footer-builder-coords-status');
    const presetBtns = document.querySelectorAll('[data-footer-preset]');
    const fileInput = document.getElementById('footer_image');

    if (!imgItem) return;

    function syncState() {
      const cW = canvas.clientWidth || 760;
      const cH = parseInt(canvas.style.height, 10) || 80;

      const imgX = Math.round(parseFloat(imgItem.style.left) || 0);
      const imgY = Math.round(parseFloat(imgItem.style.top) || 0);
      const imgW = Math.round(parseFloat(imgItem.style.width) || 740);
      const imgH = Math.round(parseFloat(imgItem.style.height) || 60);

      const imgXPct = Number(((imgX / cW) * 100).toFixed(2));
      const imgWPct = Number(((imgW / cW) * 100).toFixed(2));

      const data = {
        canvas_h: cH,
        image: {
          x: imgX,
          y: imgY,
          w: imgW,
          h: imgH,
          x_pct: imgXPct,
          w_pct: imgWPct
        }
      };

      if (jsonInput) {
        jsonInput.value = JSON.stringify(data);
      }

      if (coordsStatus) {
        coordsStatus.textContent = `Footer Banner: (${imgX}, ${imgY}) ${imgW}×${imgH}`;
      }
    }

    function setupDragAndResize(item) {
      const handle = item.querySelector('.builder-resize-handle');
      let mode = null;
      let startX = 0, startY = 0, startL = 0, startT = 0, startW = 0, startH = 0;

      item.addEventListener('pointerdown', (e) => {
        if (e.target === handle || handle?.contains(e.target)) {
          mode = 'resize';
          startX = e.clientX;
          startY = e.clientY;
          startW = item.offsetWidth;
          startH = item.offsetHeight;
          startL = item.offsetLeft;
          startT = item.offsetTop;
          handle?.setPointerCapture?.(e.pointerId);
        } else {
          mode = 'drag';
          startX = e.clientX;
          startY = e.clientY;
          startL = item.offsetLeft;
          startT = item.offsetTop;
          item?.setPointerCapture?.(e.pointerId);
        }
        item.classList.add('is-active');
        e.preventDefault();
      });

      const onPointerMove = (e) => {
        if (!mode) return;
        const cW = canvas.clientWidth || 760;
        const cH = canvas.clientHeight || 80;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        if (mode === 'drag') {
          const maxL = Math.max(0, cW - item.offsetWidth);
          const maxT = Math.max(0, cH - item.offsetHeight);
          const nextL = Math.max(0, Math.min(maxL, startL + dx));
          const nextT = Math.max(0, Math.min(maxT, startT + dy));
          item.style.left = nextL + 'px';
          item.style.top = nextT + 'px';
          syncState();
        } else if (mode === 'resize') {
          const maxW = Math.max(60, cW - startL);
          const maxH = Math.max(20, cH - startT);
          const newW = Math.max(60, Math.min(maxW, startW + dx));
          const newH = Math.max(20, Math.min(maxH, startH + dy));
          item.style.width = newW + 'px';
          item.style.height = newH + 'px';
          syncState();
        }
      };

      const endAction = (e) => {
        if (!mode) return;
        try {
          if (mode === 'resize' && handle?.hasPointerCapture?.(e.pointerId)) {
            handle.releasePointerCapture(e.pointerId);
          } else if (mode === 'drag' && item?.hasPointerCapture?.(e.pointerId)) {
            item.releasePointerCapture(e.pointerId);
          }
        } catch (_) {}
        mode = null;
        item.classList.remove('is-active');
        syncState();
      };

      item.addEventListener('pointermove', onPointerMove);
      item.addEventListener('pointerup', endAction);
      item.addEventListener('pointercancel', endAction);
    }

    setupDragAndResize(imgItem);

    if (hSlider) {
      const onHeightChange = () => {
        const h = parseInt(hSlider.value, 10) || 80;
        canvas.style.height = h + 'px';
        if (hVal) hVal.textContent = h + 'px';

        const imgH = imgItem.offsetHeight;
        if (imgItem.offsetTop + imgH > h) {
          imgItem.style.top = Math.max(0, h - imgH) + 'px';
        }
        syncState();
      };
      hSlider.addEventListener('input', onHeightChange);
      hSlider.addEventListener('change', onHeightChange);
    }

    presetBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const preset = btn.getAttribute('data-footer-preset');
        const cW = canvas.clientWidth || 760;

        if (preset === 'full') {
          imgItem.style.left = '5px';
          imgItem.style.top = '5px';
          imgItem.style.width = Math.max(60, cW - 10) + 'px';
          imgItem.style.height = (parseInt(canvas.style.height, 10) - 10) + 'px';
        } else if (preset === 'center') {
          const w = Math.round(cW * 0.7);
          imgItem.style.left = Math.round((cW - w) / 2) + 'px';
          imgItem.style.top = '5px';
          imgItem.style.width = w + 'px';
          imgItem.style.height = (parseInt(canvas.style.height, 10) - 10) + 'px';
        } else if (preset === 'left') {
          const w = Math.round(cW * 0.5);
          imgItem.style.left = '10px';
          imgItem.style.top = '5px';
          imgItem.style.width = w + 'px';
          imgItem.style.height = (parseInt(canvas.style.height, 10) - 10) + 'px';
        } else if (preset === 'default') {
          if (hSlider) {
            hSlider.value = '80';
            canvas.style.height = '80px';
            if (hVal) hVal.textContent = '80px';
          }
          imgItem.style.left = '10px';
          imgItem.style.top = '10px';
          imgItem.style.width = Math.max(60, cW - 20) + 'px';
          imgItem.style.height = '60px';
        }

        syncState();
      });
    });

    if (fileInput) {
      fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (file && file.type.startsWith('image/')) {
          const url = URL.createObjectURL(file);
          const previewImg = document.getElementById('builder-footer-preview-img');
          const previewText = document.getElementById('builder-footer-preview-text');
          if (previewImg) {
            previewImg.src = url;
          } else if (previewText) {
            previewText.outerHTML = `<img id="builder-footer-preview-img" src="${url}" alt="Footer Banner" draggable="false" style="max-height:100%;max-width:100%;object-fit:contain;pointer-events:none;">`;
          }
        }
      });
    }

    syncState();
  }

  function initCriteriaTableBuilders() {
    function bindBuilder(wrap) {
      if (wrap.dataset.criteriaBound) return;
      wrap.dataset.criteriaBound = '1';

      const tbody = wrap.querySelector('[data-criteria-tbody]');
      const textarea = wrap.querySelector('.criteria-serialized-textarea');
      const addBtn = wrap.querySelector('[data-add-criteria-row]');
      const countBadge = wrap.querySelector('[data-criteria-count]');

      function sync() {
        if (!tbody || !textarea) return;
        const rows = tbody.querySelectorAll('tr');
        const lines = [];
        rows.forEach(tr => {
          const nameInput = tr.querySelector('.criteria-name-input');
          const rangeInput = tr.querySelector('.criteria-range-input');
          const name = nameInput ? nameInput.value.trim() : '';
          const range = rangeInput ? rangeInput.value.trim() : '';
          if (name !== '' || range !== '') {
            lines.push(name + ': ' + range);
          }
        });
        textarea.value = lines.join('\n');
        if (countBadge) {
          countBadge.textContent = lines.length;
        }
      }

      function attachRowEvents(tr) {
        tr.querySelectorAll('.criteria-name-input, .criteria-range-input').forEach(inp => {
          inp.addEventListener('input', sync);
          inp.addEventListener('change', sync);
        });
        tr.querySelectorAll('[data-remove-criteria-row]').forEach(btn => {
          btn.addEventListener('click', () => {
            tr.remove();
            sync();
          });
        });
      }

      if (tbody) {
        tbody.querySelectorAll('tr').forEach(attachRowEvents);
      }

      if (addBtn && tbody) {
        addBtn.addEventListener('click', (e) => {
          e.preventDefault();
          const tr = document.createElement('tr');
          tr.className = 'border-b border-slate-100 hover:bg-slate-50/80 transition-colors';
          tr.innerHTML = `
            <td class="p-1"><input type="text" class="field text-xs py-1 px-2 w-full criteria-name-input" placeholder="e.g. Adult Male / 0-2 yrs"></td>
            <td class="p-1"><input type="text" class="field text-xs py-1 px-2 w-full criteria-range-input font-mono" placeholder="e.g. 15.2 - 23.5"></td>
            <td class="p-1 text-center"><button type="button" class="text-red-500 hover:text-red-700 font-bold p-1 leading-none text-base border-0 bg-transparent cursor-pointer" data-remove-criteria-row title="Delete row">&times;</button></td>
          `;
          tbody.appendChild(tr);
          attachRowEvents(tr);
          const firstInp = tr.querySelector('.criteria-name-input');
          if (firstInp) firstInp.focus();
          sync();
        });
      }
    }

    document.querySelectorAll('[data-criteria-builder]').forEach(bindBuilder);
  }

  initBilling();
  initCatalogSearch();
  initPdfDownload();
  initTestParameters();
  initCriteriaTableBuilders();
  initResultEntry();
  initPrintToggles();
  initHeaderAlign();
  initHeaderLayoutBuilder();
  initFooterLayoutBuilder();
})();


