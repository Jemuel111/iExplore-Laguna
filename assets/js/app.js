/* ============================================================
   LAKBAY LAGUNA — Core App JavaScript
   assets/js/app.js
   ============================================================ */

'use strict';

// ── Global app namespace ────────────────────────────────────
const IExploreApp = (() => {

  /* ── API base URL ──────────────────────────────────────── */
  const BASE_URL = document.currentScript?.dataset?.base
    || window.location.origin + '/iexplore-laguna';

  /* ── Fetch wrapper ─────────────────────────────────────── */
  async function apiFetch(endpoint, options = {}) {
    try {
      const res = await fetch(`${BASE_URL}/api/${endpoint}`, {
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.error('[IExploreApp] API error:', err);
      return { success: false, message: err.message };
    }
  }

  /* ── DOM helper ────────────────────────────────────────── */
  function el(selector, ctx = document) {
    return ctx.querySelector(selector);
  }
  function els(selector, ctx = document) {
    return [...ctx.querySelectorAll(selector)];
  }

  /* ── Show/hide loading spinner ─────────────────────────── */
  function setLoading(element, loading = true, originalText = '') {
    if (!element) return;
    if (loading) {
      element.dataset.origText = element.innerHTML;
      element.innerHTML = '<span class="spinner-app" style="width:1rem;height:1rem;border-width:2px"></span>';
      element.disabled = true;
    } else {
      element.innerHTML = element.dataset.origText || originalText;
      element.disabled = false;
    }
  }

  /* ── Toast notification ────────────────────────────────── */
  function toast(message, type = 'success', duration = 3500) {
    const wrapper = document.getElementById('toast-wrapper')
      || (() => {
        const d = document.createElement('div');
        d.id = 'toast-wrapper';
        d.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(d);
        return d;
      })();

    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill',
                    warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const colors = { success: '#2d6a4f', error: '#dc2626', warning: '#d97706', info: '#1d4ed8' };

    const t = document.createElement('div');
    t.style.cssText = `
      background:#fff;border:1px solid #e5e0d8;border-left:4px solid ${colors[type]||colors.info};
      border-radius:8px;padding:.75rem 1.1rem;box-shadow:0 4px 20px rgba(0,0,0,.12);
      display:flex;align-items:center;gap:.65rem;min-width:240px;max-width:360px;
      font-family:'DM Sans',sans-serif;font-size:.9rem;animation:slideIn .25s ease;
    `;
    t.innerHTML = `<i class="bi ${icons[type]||icons.info}" style="color:${colors[type]};font-size:1rem;flex-shrink:0"></i>
                   <span>${message}</span>`;

    if (!document.getElementById('toast-anim')) {
      const s = document.createElement('style');
      s.id = 'toast-anim';
      s.textContent = '@keyframes slideIn{from{transform:translateX(110%);opacity:0}to{transform:none;opacity:1}}';
      document.head.appendChild(s);
    }

    wrapper.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s';
                       setTimeout(() => t.remove(), 320); }, duration);
  }

  /* ── Format peso ───────────────────────────────────────── */
  function peso(amount) {
    return '₱\u202f' + Number(amount).toLocaleString('en-PH', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  /* ── Format duration (minutes → "X hr Y min") ─────────── */
  function duration(minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m} min`;
    if (m === 0) return `${h} hr`;
    return `${h} hr ${m} min`;
  }

  /* ── Render star rating ────────────────────────────────── */
  function stars(rating) {
    const full  = Math.floor(rating);
    const half  = rating - full >= 0.5 ? 1 : 0;
    const empty = 5 - full - half;
    return '<span class="star-rating">'
      + '<i class="bi bi-star-fill"></i>'.repeat(full)
      + (half ? '<i class="bi bi-star-half"></i>' : '')
      + '<i class="bi bi-star"></i>'.repeat(empty)
      + ` <small class="text-muted ms-1">${Number(rating).toFixed(1)}</small>`
      + '</span>';
  }

  /* ── Category badge HTML ───────────────────────────────── */
  const catLabels = {
    nature:'Nature', heritage:'Heritage', waterfall:'Waterfall',
    hotspring:'Hot Spring', museum:'Museum', religious:'Religious',
    beach_lake:'Lake/Beach', adventure:'Adventure', food:'Food'
  };
  function badge(category) {
    const label = catLabels[category] || category;
    return `<span class="badge-category badge-${category}">${label}</span>`;
  }

  /* ── Category icon ─────────────────────────────────────── */
  const catIcons = {
    nature:'bi-tree-fill', heritage:'bi-bank', waterfall:'bi-water',
    hotspring:'bi-thermometer-sun', museum:'bi-building', religious:'bi-arrow-up-circle',
    beach_lake:'bi-tsunami', adventure:'bi-person-walking', food:'bi-cup-hot'
  };
  function categoryIcon(category) {
    return catIcons[category] || 'bi-geo-alt-fill';
  }

  /* ── Public API ────────────────────────────────────────── */
  return { apiFetch, el, els, setLoading, toast, peso, duration, stars, badge, categoryIcon };

})();

/* ── Navbar scroll effect ──────────────────────────────────── */
(function () {
  const nav = document.getElementById('main-nav');
  if (!nav) return;
  const onScroll = () => {
    nav.style.boxShadow = window.scrollY > 40
      ? '0 4px 24px rgba(0,0,0,.28)'
      : '0 2px 12px rgba(0,0,0,.20)';
  };
  window.addEventListener('scroll', onScroll, { passive: true });
})();
