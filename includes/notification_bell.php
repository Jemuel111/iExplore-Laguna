<?php
// ============================================================
// iEXPLORE LAGUNA — Notification Bell Partial
// includes/notification_bell.php
// Included in header.php for any logged-in user. The `notifications`
// table was already being written to all over the app (booking
// cancelled, order ready, hotel approved, etc.) but nothing ever
// displayed them — this is that missing display layer.
// ============================================================
?>
<li class="nav-item dropdown ms-lg-2">
  <a class="nav-link position-relative" href="#" id="notif-bell-toggle" data-bs-toggle="dropdown" title="Notifications">
    <i class="bi bi-bell fs-5" style="color:#fff"></i>
    <span id="notif-badge" class="d-none position-absolute"
          style="top:-2px;right:-6px;background:#c0392b;color:#fff;border-radius:10px;
                 font-size:.65rem;font-weight:700;padding:.1rem .38rem;line-height:1.2">0</span>
  </a>
  <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px;max-height:420px;overflow-y:auto" id="notif-dropdown">
    <div class="d-flex align-items-center justify-content-between p-2 px-3" style="border-bottom:1px solid var(--border)">
      <span class="fw-bold small">Notifications</span>
      <button class="btn btn-link btn-sm p-0 small" id="notif-mark-all" style="text-decoration:none">Mark all read</button>
    </div>
    <div id="notif-list">
      <div class="text-center text-muted small py-4">Loading…</div>
    </div>
  </div>
</li>

<script>
(function() {
  const badge   = document.getElementById('notif-badge');
  const list    = document.getElementById('notif-list');
  const markAll = document.getElementById('notif-mark-all');
  const bell    = document.getElementById('notif-bell-toggle');
  let loaded = false;

  function timeAgo(dateStr) {
    const diff = (Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
  }

  async function loadNotifications() {
    try {
      const res = await fetch('<?= APP_URL ?>/api/notifications.php?action=list').then(r => r.json());
      if (!res.success) return;

      const { notifications, unread_count } = res.data;

      if (unread_count > 0) {
        badge.textContent = unread_count > 9 ? '9+' : unread_count;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }

      if (!notifications.length) {
        list.innerHTML = '<div class="text-center text-muted small py-4"><i class="bi bi-bell-slash d-block mb-1 fs-5"></i>No notifications yet.</div>';
        return;
      }

      list.innerHTML = notifications.map(n => `
        <a href="${n.link ? escapeHtml(n.link) : '#'}" data-id="${n.id}"
           class="notif-item d-block px-3 py-2 text-decoration-none"
           style="border-bottom:1px solid var(--border);background:${n.is_read == 0 ? 'var(--sand)' : '#fff'}">
          <div class="d-flex align-items-start gap-2">
            ${n.is_read == 0 ? '<span style="width:7px;height:7px;background:#c0392b;border-radius:50%;margin-top:.4rem;flex-shrink:0"></span>' : '<span style="width:7px;flex-shrink:0"></span>'}
            <div class="flex-grow-1 min-w-0">
              <div class="small fw-bold" style="color:var(--charcoal)">${escapeHtml(n.title)}</div>
              <div class="small text-muted" style="white-space:normal">${escapeHtml(n.message)}</div>
              <div class="small text-muted mt-1" style="font-size:.72rem">${timeAgo(n.created_at)}</div>
            </div>
          </div>
        </a>
      `).join('');
    } catch (e) {
      list.innerHTML = '<div class="text-center text-muted small py-4">Could not load notifications.</div>';
    }
  }

  bell.addEventListener('click', () => {
    if (!loaded) { loadNotifications(); loaded = true; }
  });

  list.addEventListener('click', (e) => {
    const item = e.target.closest('.notif-item');
    if (!item) return;
    const id = item.dataset.id;
    fetch('<?= APP_URL ?>/api/notifications.php?action=mark_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
      body: JSON.stringify({ id })
    }).catch(() => {});
    // Let the link navigation proceed normally — no preventDefault needed
  });

  markAll.addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch('<?= APP_URL ?>/api/notifications.php?action=mark_all_read', {
      method: 'POST',
      headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
    }).catch(() => {});
    loadNotifications();
  });

  // Load once on page load too, so the badge count shows without needing a click
  loadNotifications();
  loaded = true;
})();
</script>
