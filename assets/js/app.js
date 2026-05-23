/* ============================================================
   iEXPLORE LAGUNA — Core App JavaScript (Polished v2)
   ============================================================ */

   'use strict';

   const IExploreApp = (() => {
   
     /* ── Fetch wrapper ─────────────────────────────────────── */
     async function apiFetch(endpoint, options = {}) {
       try {
         const res = await fetch(`${window.APP_URL || ''}/api/${endpoint}`, {
           headers: { 'Content-Type': 'application/json', ...options.headers },
           ...options,
         });
         if (!res.ok) throw new Error(`HTTP ${res.status}`);
         return await res.json();
       } catch (err) {
         console.error('[iExplore] API error:', err);
         return { success: false, message: err.message };
       }
     }
   
     /* ── DOM helpers ───────────────────────────────────────── */
     const el  = (s, c = document) => c.querySelector(s);
     const els = (s, c = document) => [...c.querySelectorAll(s)];
   
     /* ── Loading state ─────────────────────────────────────── */
     function setLoading(element, loading = true) {
       if (!element) return;
       if (loading) {
         element.dataset.origHtml = element.innerHTML;
         element.innerHTML = '<span class="spinner-app" style="width:1rem;height:1rem;border-width:2px;vertical-align:middle"></span>';
         element.disabled = true;
       } else {
         element.innerHTML = element.dataset.origHtml || '';
         element.disabled = false;
       }
     }
   
     /* ── Toast ─────────────────────────────────────────────── */
     function toast(message, type = 'success', duration = 3500) {
       let wrapper = document.getElementById('toast-wrapper');
       if (!wrapper) {
         wrapper = document.createElement('div');
         wrapper.id = 'toast-wrapper';
         document.body.appendChild(wrapper);
       }
   
       const icons  = { success:'bi-check-circle-fill', error:'bi-x-circle-fill',
                        warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
       const colors = { success:'#2d6a4f', error:'#dc2626', warning:'#d97706', info:'#1d4ed8' };
   
       const t = document.createElement('div');
       t.className = 'toast-item';
       t.style.borderLeftColor = colors[type] || colors.info;
       t.style.borderLeft = `4px solid ${colors[type] || colors.info}`;
       t.innerHTML = `<i class="bi ${icons[type]||icons.info}" style="color:${colors[type]};font-size:1rem;flex-shrink:0"></i>
                      <span style="font-family:'DM Sans',sans-serif">${message}</span>`;
       wrapper.appendChild(t);
   
       setTimeout(() => {
         t.style.transition = 'opacity .3s, transform .3s';
         t.style.opacity = '0';
         t.style.transform = 'translateX(20px)';
         setTimeout(() => t.remove(), 320);
       }, duration);
     }
   
     /* ── Formatters ────────────────────────────────────────── */
     function peso(amount) {
       return '₱\u202f' + Number(amount || 0).toLocaleString('en-PH',
         { minimumFractionDigits: 2, maximumFractionDigits: 2 });
     }
   
     function duration(minutes) {
       const h = Math.floor(minutes / 60), m = minutes % 60;
       if (h === 0) return `${m} min`;
       if (m === 0) return `${h} hr`;
       return `${h} hr ${m} min`;
     }
   
     function stars(rating) {
       const full = Math.floor(rating);
       const half = rating - full >= 0.5 ? 1 : 0;
       const empty = 5 - full - half;
       return `<span class="star-rating">${'<i class="bi bi-star-fill"></i>'.repeat(full)}${half ? '<i class="bi bi-star-half"></i>' : ''}${'<i class="bi bi-star"></i>'.repeat(empty)}<small class="text-muted ms-1">${Number(rating).toFixed(1)}</small></span>`;
     }
   
     const catLabels = {
       nature:'Nature', heritage:'Heritage', waterfall:'Waterfall',
       hotspring:'Hot Spring', museum:'Museum', religious:'Religious',
       beach_lake:'Lake/Beach', adventure:'Adventure', food:'Food'
     };
     const catEmojis = {
       nature:'🌿', heritage:'🏛️', waterfall:'💧', hotspring:'♨️',
       museum:'🏺', religious:'⛪', beach_lake:'🏞️', adventure:'🧗', food:'🍜'
     };
     const catIcons = {
       nature:'bi-tree-fill', heritage:'bi-bank', waterfall:'bi-water',
       hotspring:'bi-thermometer-sun', museum:'bi-building', religious:'bi-arrow-up-circle',
       beach_lake:'bi-tsunami', adventure:'bi-person-walking', food:'bi-cup-hot'
     };
   
     function badge(category) {
       return `<span class="badge-category badge-${category}">${catLabels[category] || category}</span>`;
     }
     function emoji(category) { return catEmojis[category] || '📍'; }
     function categoryIcon(category) { return catIcons[category] || 'bi-geo-alt-fill'; }
   
     return { apiFetch, el, els, setLoading, toast, peso, duration, stars, badge, emoji, categoryIcon };
   })();
   
   /* ── Navbar: shrink on scroll + active underline ───────────── */
   (function () {
     const nav = document.getElementById('main-nav');
     if (!nav) return;
     const onScroll = () => {
       nav.classList.toggle('scrolled', window.scrollY > 50);
     };
     window.addEventListener('scroll', onScroll, { passive: true });
   })();
   
   /* ── Scroll-to-top button ──────────────────────────────────── */
   (function () {
     const btn = document.getElementById('scroll-top');
     if (!btn) return;
     window.addEventListener('scroll', () => {
       btn.classList.toggle('visible', window.scrollY > 300);
     }, { passive: true });
     btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
   })();
   
   /* ── Fade-up on scroll (IntersectionObserver) ──────────────── */
   (function () {
     if (!window.IntersectionObserver) return;
     const targets = document.querySelectorAll('.reveal');
     if (!targets.length) return;
     const obs = new IntersectionObserver((entries) => {
       entries.forEach(e => {
         if (e.isIntersecting) {
           e.target.classList.add('fade-up');
           obs.unobserve(e.target);
         }
       });
     }, { threshold: 0.12 });
     targets.forEach(t => obs.observe(t));
   })();
   