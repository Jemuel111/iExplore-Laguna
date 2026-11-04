<?php
ob_start();
require_once __DIR__ . '/../includes/order_safety.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// IEXPLORE LAGUNA — Super Admin: Order Safety
// pages/admin-order-safety.php
// See tourists with missed pickups, lift restrictions after an appeal,
// and review problems reported from order chats. Super admin only.
// ============================================================
$page_title  = 'Order Safety';
$active_page = 'admin-order-safety';

$u = require_admin_area(true);
ensure_order_safety_schema();
$self = APP_URL . '/pages/admin-order-safety.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) input('action', 'post', '');
    $uid    = (int) input('user_id', 'post', 0);

    if ($action === 'reset_strikes' && $uid) {
        db_execute("UPDATE users SET no_show_count = 0, order_blocked_until = NULL WHERE id = ? AND role = 'tourist'", [$uid]);
        os_notify($uid, 'order_standing', 'Ordering restored', 'The admin cleared your missed-pickup record. You can order normally again.', APP_URL . '/pages/my-orders.php');
        $_SESSION['flash']['success'] = 'Strikes cleared and ordering restored.';
    } elseif ($action === 'unblock' && $uid) {
        db_execute("UPDATE users SET order_blocked_until = NULL WHERE id = ? AND role = 'tourist'", [$uid]);
        os_notify($uid, 'order_standing', 'Ordering restored', 'The admin lifted the ordering pause on your account. Deposits still apply.', APP_URL . '/pages/my-orders.php');
        $_SESSION['flash']['success'] = 'Ordering pause lifted. The strikes stay on record.';
    } elseif ($action === 'resolve_report') {
        db_execute("UPDATE order_reports SET status = 'resolved' WHERE id = ?", [(int) input('report_id', 'post', 0)]);
        $_SESSION['flash']['success'] = 'Report marked as resolved.';
    }
    header('Location: ' . $self); exit;
}

require_once __DIR__ . '/../includes/header.php';

$flagged = db_fetch_all(
    "SELECT id, name, email, phone, no_show_count, order_blocked_until
       FROM users
      WHERE role = 'tourist' AND (no_show_count > 0 OR order_blocked_until > ?)
      ORDER BY no_show_count DESC, name ASC LIMIT 200",
    [os_now()]
);
$reports = db_fetch_all(
    "SELECT r.*, o.order_number, o.status AS order_status, s.name AS shop_name,
            t.name AS tourist_name, rp.name AS reporter_name
       FROM order_reports r
       JOIN orders o ON r.order_id = o.id
       JOIN shops s ON o.shop_id = s.id
       JOIN users t ON o.tourist_id = t.id
       JOIN users rp ON r.reporter_id = rp.id
      ORDER BY (r.status = 'open') DESC, r.id DESC LIMIT 50"
);
$stats = db_fetch_one(
    "SELECT SUM(status = 'no_show') AS no_shows,
            SUM(status = 'picked_up') AS completed,
            SUM(deposit_required = 1) AS with_deposit,
            SUM(status = 'cancelled' AND cancel_reason = 'Deposit was not paid in time') AS expired
       FROM orders"
) ?: [];
?>
<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif"><i class="bi bi-shield-check me-2" style="color:var(--sand-dark)"></i>Order Safety</h1>
    <p class="mb-0 small opacity-75">Missed pickups, restrictions and problems reported from order chats</p>
  </div>
</section>

<div class="container py-4">
  <div class="row g-3 mb-4">
    <?php foreach ([['Completed pickups', $stats['completed'] ?? 0], ['Missed pickups', $stats['no_shows'] ?? 0], ['Orders that needed a deposit', $stats['with_deposit'] ?? 0], ['Deposit windows that expired', $stats['expired'] ?? 0]] as [$lbl, $val]): ?>
      <div class="col-6 col-md-3"><div class="form-panel text-center py-3"><div class="fs-3 fw-bold" style="color:var(--maroon-dark)"><?= (int) $val ?></div><div class="small text-muted"><?= e($lbl) ?></div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="form-panel mb-4">
    <h6 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Reported problems</h6>
    <p class="small text-muted">Tourists and shop owners can report a problem from the order chat. Read the conversation, then decide.</p>
    <?php if (!$reports): ?>
      <p class="text-muted small mb-0">No reports yet.</p>
    <?php endif; ?>
    <?php foreach ($reports as $r):
      $msgs = db_fetch_all("SELECT sender_id, kind, body, created_at FROM order_messages WHERE order_id = ? ORDER BY id ASC LIMIT 200", [$r['order_id']]);
    ?>
      <div class="border rounded-3 p-3 mb-2" style="background:<?= $r['status'] === 'open' ? '#fff7ed' : '#f8fafc' ?>">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div>
            <strong>Order <?= e($r['order_number']) ?></strong> · <?= e($r['shop_name']) ?> / <?= e($r['tourist_name']) ?>
            <span class="badge text-bg-<?= $r['status'] === 'open' ? 'warning' : 'secondary' ?> ms-1"><?= e($r['status']) ?></span>
            <div class="small text-muted">Reported by <?= e($r['reporter_name']) ?> on <?= date('M j, g:i A', strtotime($r['created_at'])) ?></div>
            <div class="mt-1">“<?= e($r['reason']) ?>”</div>
          </div>
          <?php if ($r['status'] === 'open'): ?>
          <form method="POST" class="m-0"><?= csrf_field() ?>
            <input type="hidden" name="action" value="resolve_report"><input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary">Mark resolved</button>
          </form>
          <?php endif; ?>
        </div>
        <details class="mt-2"><summary class="small">Read the chat (<?= count($msgs) ?> messages)</summary>
          <div class="small mt-2" style="max-height:240px;overflow:auto">
            <?php foreach ($msgs as $m): ?>
              <div class="mb-1"><span class="text-muted"><?= date('M j g:i A', strtotime($m['created_at'])) ?></span>
                <strong><?= $m['kind'] === 'system' ? 'System' : ((int) $m['sender_id'] === (int) $r['reporter_id'] ? e($r['reporter_name']) : 'Other party') ?>:</strong> <?= e($m['body']) ?></div>
            <?php endforeach; ?>
          </div>
        </details>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="form-panel">
    <h6 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Tourists with missed pickups</h6>
    <p class="small text-muted">1 strike is a warning. 2 strikes means every order needs a deposit. 3 strikes pauses ordering for <?= (int) order_safety_config()['block_days'] ?> days. Use these buttons when an appeal is approved.</p>
    <?php if (!$flagged): ?>
      <p class="text-muted small mb-0">No tourists have missed pickups. 🎉</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Tourist</th><th>Contact</th><th class="text-center">Strikes</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($flagged as $f): $blocked = $f['order_blocked_until'] && strtotime($f['order_blocked_until']) > time(); ?>
          <tr>
            <td><?= e($f['name']) ?></td>
            <td class="small"><?= e($f['email']) ?><?= $f['phone'] ? '<br>' . e($f['phone']) : '' ?></td>
            <td class="text-center"><?= (int) $f['no_show_count'] ?></td>
            <td class="small"><?= $blocked ? '<span class="text-danger">Paused until ' . date('M j, Y', strtotime($f['order_blocked_until'])) . '</span>' : ((int) $f['no_show_count'] >= 2 ? 'Deposit on every order' : 'Warning') ?></td>
            <td class="text-end">
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $f['id'] ?>">
                <?php if ($blocked): ?><button class="btn btn-sm btn-outline-secondary" name="action" value="unblock">Lift pause</button><?php endif; ?>
                <button class="btn btn-sm btn-outline-danger" name="action" value="reset_strikes" onclick="return confirm('Clear all strikes for this tourist?')">Clear strikes</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
