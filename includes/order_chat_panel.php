<?php
// ============================================================
// IEXPLORE LAGUNA — Order chat panel (shared)
// includes/order_chat_panel.php
//
// A slide-in panel used by pages/my-orders.php (tourist) and
// pages/shop-dashboard.php (owner). Open it with openOrderChat(orderId)
// or by visiting the page with ?chat=ORDER_ID. It shows the order, the
// deposit step (GCash QR, proof upload, confirm), the status buttons and
// the conversation. It only asks the server for news every few seconds
// while it is open, so it does not slow the rest of the site.
// ============================================================
?>
<style>
  .oc-head{padding:.9rem 1rem;border-bottom:1px solid var(--border);background:var(--cream)}
  .oc-badge{display:inline-block;border-radius:999px;padding:.12rem .65rem;font-size:.74rem;font-weight:700}
  .oc-box{margin:.6rem 1rem 0;padding:.75rem;border-radius:12px;background:#fff7ed;border:1px solid #f6d3ad;font-size:.86rem}
  .oc-box.ok{background:#ecfdf3;border-color:#b7ebc9}
  .oc-box.bad{background:#fef2f2;border-color:#f5c2c2}
  .oc-qr{max-width:190px;width:100%;border-radius:10px;border:1px solid var(--border);background:#fff}
  .oc-actions{display:flex;flex-wrap:wrap;gap:.4rem;padding:.6rem 1rem 0}
  .oc-actions button{border:0;border-radius:999px;padding:.38rem .9rem;font-size:.8rem;font-weight:700}
  .oc-actions .primary{background:var(--maroon-mid);color:#fff}
  .oc-actions .sand{background:var(--sand-dark);color:var(--maroon-dark)}
  .oc-actions .quiet{background:#f1f5f9;color:#334155}
  .oc-actions .danger{background:#fee2e2;color:#a61c1c}
  .oc-actions button:disabled{opacity:.5}
  #oc-panel .offcanvas-body{overflow-y:auto}
  .oc-msgs{flex:1 1 240px;min-height:240px;overflow-y:auto;padding:.8rem 1rem;display:flex;flex-direction:column;gap:.45rem;background:#fbf7f1}
  .oc-m{max-width:82%;padding:.45rem .75rem;border-radius:14px;font-size:.88rem;word-wrap:break-word;white-space:pre-wrap}
  .oc-m small{display:block;opacity:.65;font-size:.68rem;margin-top:.1rem}
  .oc-m.me{align-self:flex-end;background:var(--maroon-mid);color:#fff;border-bottom-right-radius:4px}
  .oc-m.them{align-self:flex-start;background:#fff;border:1px solid var(--border);border-bottom-left-radius:4px}
  .oc-m.sys{align-self:center;max-width:94%;background:transparent;color:#6b7280;font-style:italic;text-align:center;font-size:.78rem}
  .oc-chips{display:flex;flex-wrap:wrap;gap:.35rem;padding:.5rem 1rem 0}
  .oc-chips button{border:1px solid var(--border);background:#fff;border-radius:999px;padding:.2rem .7rem;font-size:.76rem}
  .oc-form{display:flex;gap:.5rem;padding:.6rem 1rem .8rem;position:sticky;bottom:0;background:#fff;border-top:1px solid var(--border)}
  .oc-form input{flex:1}
  .oc-confirm{margin:.5rem 1rem 0;padding:.6rem;border-radius:10px;background:#fff;border:1px solid var(--border);font-size:.84rem}
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="oc-panel" aria-labelledby="oc-title" style="width:min(440px,100vw)">
  <div class="offcanvas-header oc-head d-block">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="fw-bold" id="oc-title" style="font-family:'Playfair Display',serif">Order chat</div>
        <div class="small text-muted" id="oc-sub"></div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="mt-1" id="oc-status"></div>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column" style="min-height:0">
    <div id="oc-deposit"></div>
    <div class="oc-actions" id="oc-actions"></div>
    <div id="oc-confirm"></div>
    <div class="oc-msgs" id="oc-msgs" aria-live="polite"></div>
    <div class="oc-chips" id="oc-chips"></div>
    <form class="oc-form" id="oc-form" autocomplete="off">
      <input type="text" class="form-control form-control-sm" id="oc-input" maxlength="500" placeholder="Write a message…" aria-label="Message">
      <button class="btn btn-primary-app btn-sm" type="submit"><i class="bi bi-send"></i></button>
    </form>
    <div class="px-3 pb-3 small text-muted d-flex justify-content-between">
      <span>Payments are made directly between you and the shop.</span>
      <a href="#" id="oc-report" class="text-muted">Report a problem</a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const API  = '<?= APP_URL ?>/api/';
  const $    = id => document.getElementById(id);
  const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const peso = n => '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const panel = $('oc-panel'), oc = new bootstrap.Offcanvas(panel);
  const CHIPS = {
    tourist: ['I am on my way', 'I will be 15 minutes late', 'Is my order ready?'],
    owner:   ['Your order is ready for pickup', 'Please send the deposit', 'Thank you!']
  };

  let orderId = null, lastId = 0, timer = null, tick = null, st = null, fetchedAt = 0, changed = false;
  let sigDeposit = '', sigActions = '';

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({'X-CSRF-Token': window.CSRF_TOKEN}, opts.headers || {});
    return fetch(API + path, opts).then(r => r.json());
  }
  const post = (path, obj) => api(path, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(obj)});

  window.openOrderChat = function (id) {
    orderId = id; lastId = 0; st = null; sigDeposit = sigActions = ''; changed = false;
    $('oc-msgs').innerHTML = ''; $('oc-deposit').innerHTML = ''; $('oc-actions').innerHTML = ''; $('oc-confirm').innerHTML = '';
    document.querySelectorAll('[data-chat-badge="' + id + '"]').forEach(b => b.remove());
    oc.show();
    poll();
  };

  function schedule() {
    clearTimeout(timer);
    if (!panel.classList.contains('show')) return;
    timer = setTimeout(poll, (st && st.closed) ? 15000 : 4000);
  }

  async function poll() {
    if (!orderId || document.hidden) { schedule(); return; }
    let res;
    try { res = await api('order-chat.php?action=list&order_id=' + orderId + '&after=' + lastId); }
    catch (e) { schedule(); return; }
    if (!res.success) { $('oc-msgs').innerHTML = '<div class="oc-m sys">' + esc(res.message || 'Could not load this chat.') + '</div>'; return; }
    fetchedAt = Date.now();
    st = res.data.order;
    renderHeader(); renderDeposit(); renderActions(); renderChips();
    appendMessages(res.data.messages);
    schedule();
  }

  function renderHeader() {
    $('oc-title').textContent = 'Order ' + st.number;
    $('oc-sub').textContent = (st.role === 'owner' ? 'Customer: ' + st.tourist_name : st.role === 'admin' ? st.shop_name + ' / ' + st.tourist_name : st.shop_name)
      + ' · ' + peso(st.total) + (st.pickup ? ' · Pickup ' + st.pickup : '');
    $('oc-status').innerHTML = '<span class="oc-badge" style="background:' + esc(st.status_bg) + ';color:' + esc(st.status_fg) + '">' + esc(st.status_label) + '</span>' +
      (st.pickup_code && st.role === 'tourist' && ['confirmed','preparing','ready'].includes(st.status)
        ? ' <span class="small ms-2">Pickup code <strong style="letter-spacing:.12em">' + esc(st.pickup_code) + '</strong></span>' : '');
    $('oc-form').style.display = st.role === 'admin' ? 'none' : '';
    $('oc-chips').style.display = st.role === 'admin' ? 'none' : '';
    $('oc-report').style.display = st.role === 'admin' ? 'none' : '';
  }

  // ── Deposit box ─────────────────────────────────────────────
  function remainingSec() { return st.deposit.due_ts ? Math.max(0, st.deposit.due_ts - (st.server_ts + (Date.now() - fetchedAt) / 1000)) : 0; }
  function fmtClock(s) { s = Math.floor(s); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }

  function renderDeposit() {
    const d = st.deposit, role = st.role;
    const sig = [st.status, d.status, d.ref, d.has_proof, d.note, role].join('|');
    if (sig === sigDeposit) return;
    sigDeposit = sig;
    let html = '', cls = '';
    const proofLink = '<?= APP_URL ?>/api/order-deposit.php?action=proof&order_id=' + st.id;

    if (d.required && st.status === 'pending') {
      html = role === 'owner'
        ? 'This order needs a <strong>' + peso(d.amount) + '</strong> deposit. When you accept it, the customer gets 30 minutes to pay.'
        : 'This order needs a <strong>' + peso(d.amount) + '</strong> deposit. After the shop accepts, you will pay it here using their GCash QR.';
    } else if (st.status === 'awaiting_deposit' && d.status === 'requested') {
      if (role === 'tourist') {
        html = '<div class="fw-semibold mb-1">Pay the deposit: ' + peso(d.amount) + '</div>' +
          (d.note ? '<div class="text-danger mb-1">The shop said: ' + esc(d.note) + '</div>' : '') +
          '<div class="mb-2">Time left: <strong id="oc-timer">' + fmtClock(remainingSec()) + '</strong>. The order is cancelled automatically if it is not paid in time.</div>' +
          (st.gcash.qr ? '<div class="text-center mb-2"><img class="oc-qr" src="' + esc(st.gcash.qr) + '" alt="Shop GCash QR code">' +
              '<div class="small mt-1"><a href="' + esc(st.gcash.qr) + '" download>Download the QR</a> to scan it from your GCash gallery</div></div>' : '') +
          (st.gcash.name || st.gcash.number ? '<div class="small mb-2">GCash: <strong>' + esc(st.gcash.name || '') + '</strong> ' + esc(st.gcash.number || '') + '</div>' : '') +
          '<form id="oc-proof-form"><div class="mb-2"><label class="small mb-1">Payment screenshot</label><input type="file" name="proof" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm"></div>' +
          '<div class="mb-2"><label class="small mb-1">GCash reference number (optional if you add a screenshot)</label><input type="text" name="ref" maxlength="60" class="form-control form-control-sm"></div>' +
          '<button class="btn btn-primary-app btn-sm w-100" type="submit">I have paid</button></form>';
      } else {
        html = 'Waiting for the customer to pay the <strong>' + peso(d.amount) + '</strong> deposit. Time left: <strong id="oc-timer">' + fmtClock(remainingSec()) + '</strong>.';
      }
    } else if (st.status === 'awaiting_deposit' && d.status === 'proof_submitted') {
      html = role === 'owner'
        ? '<div class="fw-semibold mb-1">Proof of payment received</div>Check your GCash for <strong>' + peso(d.amount) + '</strong>' + (d.ref ? ' (ref ' + esc(d.ref) + ')' : '') + '.' +
          (d.has_proof ? '<div class="mt-2"><a href="' + proofLink + '" target="_blank" rel="noopener"><img src="' + proofLink + '" alt="Payment screenshot" style="max-width:100%;max-height:220px;border-radius:10px;border:1px solid var(--border)"></a></div>' : '')
        : 'Proof sent' + (d.ref ? ' (ref ' + esc(d.ref) + ')' : '') + '. Waiting for the shop to confirm your ' + peso(d.amount) + ' deposit.';
    } else if (d.status === 'paid') {
      cls = 'ok';
      html = 'Deposit of <strong>' + peso(d.amount) + '</strong> paid. ' + (role === 'owner' ? 'Collect the balance of ' : 'Balance to pay at pickup: ') + '<strong>' + peso(d.balance) + '</strong>' + (role === 'owner' ? ' at pickup.' : '.');
    } else if (d.status === 'refund_due') {
      cls = 'bad';
      html = role === 'owner' ? 'A refund of <strong>' + peso(d.amount) + '</strong> is due to the customer. Send it through GCash, then mark it as refunded.' : 'The shop will refund your ' + peso(d.amount) + ' deposit.';
    } else if (d.status === 'refunded') {
      cls = 'ok'; html = 'Deposit of ' + peso(d.amount) + ' was refunded.';
    } else if (d.status === 'forfeited') {
      cls = 'bad'; html = 'The ' + peso(d.amount) + ' deposit was kept by the shop because the order was not picked up.';
    }
    $('oc-deposit').innerHTML = html ? '<div class="oc-box ' + cls + '">' + html + '</div>' : '';
    const f = $('oc-proof-form'); if (f) f.addEventListener('submit', submitProof);
  }

  async function submitProof(e) {
    e.preventDefault();
    const form = e.target, btn = form.querySelector('button');
    btn.disabled = true; btn.textContent = 'Sending…';
    const fd = new FormData(form); fd.append('order_id', st.id);
    try {
      const res = await api('order-deposit.php?action=submit_proof', {method: 'POST', body: fd});
      if (res.success) { changed = true; IExploreApp.toast(res.message || 'Sent.', 'success'); sigDeposit = ''; poll(); return; }
      IExploreApp.toast(res.message || 'Could not send.', 'error');
    } catch (err) { IExploreApp.toast('Connection problem. Please try again.', 'error'); }
    btn.disabled = false; btn.textContent = 'I have paid';
  }

  // ── Status buttons ──────────────────────────────────────────
  function renderActions() {
    const sig = JSON.stringify(st.actions) + st.status;
    if (sig === sigActions) return;
    sigActions = sig;
    const box = $('oc-actions'); box.innerHTML = '';
    st.actions.forEach(a => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = a.style; b.textContent = a.label;
      if (a.disabled) { b.disabled = true; b.title = a.disabled; b.textContent = a.label + ' (' + a.disabled.toLowerCase() + ')'; }
      b.addEventListener('click', () => askThenDo(a));
      box.appendChild(b);
    });
  }

  function askThenDo(a) {
    const c = $('oc-confirm');
    if (!a.confirm) { runDo(a.key, ''); return; }
    const needsReason = a.confirm === 'ask-reason';
    c.innerHTML = '<div class="oc-confirm"><div class="mb-2">' + esc(needsReason ? 'Why is the payment not valid?' : a.confirm) + '</div>' +
      (needsReason ? '<input type="text" class="form-control form-control-sm mb-2" id="oc-reason" maxlength="200" placeholder="e.g. Amount is less than the deposit">' : '') +
      '<div class="d-flex gap-2"><button class="btn btn-sm btn-primary-app" id="oc-yes">Yes</button><button class="btn btn-sm btn-outline-secondary" id="oc-no">Not now</button></div></div>';
    $('oc-no').onclick = () => { c.innerHTML = ''; };
    $('oc-yes').onclick = () => { const r = needsReason ? ($('oc-reason').value || '') : ''; c.innerHTML = ''; runDo(a.key, r); };
  }

  async function runDo(key, reason) {
    try {
      const res = await post('order-chat.php?action=do', {order_id: st.id, do: key, reason: reason});
      IExploreApp.toast(res.message || (res.success ? 'Done.' : 'Could not do that.'), res.success ? 'success' : 'error');
      if (res.success) { changed = true; sigDeposit = sigActions = ''; }
    } catch (e) { IExploreApp.toast('Connection problem. Please try again.', 'error'); }
    poll();
  }

  // ── Messages ────────────────────────────────────────────────
  function appendMessages(list) {
    if (!list.length) return;
    const box = $('oc-msgs'), atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80 || lastId === 0;
    list.forEach(m => {
      lastId = Math.max(lastId, m.id);
      const d = document.createElement('div');
      d.className = 'oc-m ' + (m.kind === 'system' ? 'sys' : m.mine ? 'me' : 'them');
      d.innerHTML = esc(m.body) + (m.kind === 'system' ? '' : '<small>' + esc(m.time) + '</small>');
      box.appendChild(d);
    });
    if (atBottom) box.scrollTop = box.scrollHeight;
  }

  function renderChips() {
    const role = st.role; if (!CHIPS[role] || $('oc-chips').dataset.role === role) return;
    $('oc-chips').dataset.role = role;
    $('oc-chips').innerHTML = CHIPS[role].map(t => '<button type="button">' + esc(t) + '</button>').join('');
    $('oc-chips').querySelectorAll('button').forEach(b => b.addEventListener('click', () => send(b.textContent)));
  }

  async function send(text) {
    text = (text || '').trim(); if (!text || !st) return;
    try {
      const res = await post('order-chat.php?action=send', {order_id: st.id, body: text});
      if (!res.success) { IExploreApp.toast(res.message || 'Could not send.', 'error'); return; }
      $('oc-input').value = '';
      poll();
    } catch (e) { IExploreApp.toast('Connection problem. Please try again.', 'error'); }
  }
  $('oc-form').addEventListener('submit', e => { e.preventDefault(); send($('oc-input').value); });

  $('oc-report').addEventListener('click', e => {
    e.preventDefault();
    const c = $('oc-confirm');
    c.innerHTML = '<div class="oc-confirm"><div class="mb-2">What went wrong? The site admin will read this chat.</div>' +
      '<input type="text" class="form-control form-control-sm mb-2" id="oc-rep" maxlength="300" placeholder="Describe the problem">' +
      '<div class="d-flex gap-2"><button class="btn btn-sm btn-primary-app" id="oc-rep-go">Send report</button><button class="btn btn-sm btn-outline-secondary" id="oc-rep-no">Cancel</button></div></div>';
    $('oc-rep-no').onclick = () => { c.innerHTML = ''; };
    $('oc-rep-go').onclick = async () => {
      const res = await post('order-chat.php?action=report', {order_id: st.id, reason: $('oc-rep').value});
      IExploreApp.toast(res.message || 'Done.', res.success ? 'success' : 'error');
      if (res.success) c.innerHTML = '';
    };
  });

  // countdown text, once a second, only while the panel is open
  tick = setInterval(() => { const t = $('oc-timer'); if (t && st) t.textContent = fmtClock(remainingSec()); }, 1000);

  panel.addEventListener('hidden.bs.offcanvas', () => {
    clearTimeout(timer); orderId = null;
    if (changed) window.location.reload();   // lists on the page show the new status
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden && orderId) poll(); });

  // Open straight from a notification link: ?chat=ORDER_ID
  const want = parseInt(new URLSearchParams(location.search).get('chat'), 10);
  if (want) window.openOrderChat(want);
});
</script>
