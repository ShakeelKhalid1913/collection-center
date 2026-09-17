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
    const input = document.querySelector('[data-catalog-search]');
    if (!input) return;
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      document.querySelectorAll('[data-catalog-item]').forEach((item) => {
        const name = item.getAttribute('data-name') || '';
        item.style.display = !q || name.includes(q) ? '' : 'none';
      });
    });
  }

  initBilling();
  initCatalogSearch();
})();
