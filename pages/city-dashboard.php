<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — City Admin Dashboard
// pages/city-dashboard.php
// One city only: stats, approve/unpublish hotels & shops, and
// shortcuts to manage that city's tourist spots and photos.
// ============================================================
$page_title  = 'City Dashboard';
$active_page = 'city-dashboard';

$u = require_admin_area();

// The super admin has their own dashboard.
if (is_super_admin()) { header('Location: ' . APP_URL . '/pages/admin-dashboard.php'); exit; }

$cityId = (int) admin_scope();   // 0 = no active city assigned

// ── No city assigned (or account deactivated): explain, don't loop ──
if ($cityId <= 0) {
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5 text-center" style="max-width:560px">
      <i class="bi bi-exclamation-triangle fs-1 text-muted d-block mb-3"></i>
      <h4>No city assigned</h4>
      <p class="text-muted">Your account is not linked to an active city yet. Please contact the iExplore Laguna administrator to have a city assigned to you.</p>
      <a href="<?= APP_URL ?>/pages/logout.php" class="btn btn-outline-secondary">Log out</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$city = db_fetch_one("SELECT id, name FROM cities WHERE id = ?", [$cityId]);
if (!$city) { $city = ['id' => $cityId, 'name' => 'Your city']; }
$cityName = (string) $city['name'];

$back = APP_URL . '/pages/city-dashboard.php';

// ── POST: approve / reject / unpublish hotels and shops in THIS city ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) input('action', 'post', '');

    // ---- shops ----
    if (in_array($action, ['approve_shop', 'reject_shop', 'revoke_shop'], true)) {
        $sid = (int) input('shop_id', 'post', 0);
        if (!admin_owns_shop($sid)) {
            $_SESSION['flash']['danger'] = 'You can only manage shops in your own city.';
            header('Location: ' . $back . '#approvals'); exit;
        }
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            $sname = (string) $shop['name'];
            if ($action === 'approve_shop') {
                db_execute("UPDATE shops SET is_verified = 1, is_active = 1 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_approved', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Approved!',
                     "\"{$sname}\" is now live on iExplore Laguna and visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('approve_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['success'] = "\"{$sname}\" approved.";
            } elseif ($action === 'reject_shop') {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_rejected', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Registration Rejected',
                     "\"{$sname}\" was not approved. Please review your details and contact your city administrator.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('reject_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['danger'] = "\"{$sname}\" rejected.";
            } else {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_revoked', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Unpublished',
                     "\"{$sname}\" has been taken offline by an admin and is no longer visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('unpublish_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['danger'] = "\"{$sname}\" unpublished.";
            }
        }
        header('Location: ' . $back . '#approvals'); exit;
    }

    // ---- hotels ----
    if (in_array($action, ['approve_hotel', 'reject_hotel', 'revoke_hotel'], true)) {
        $hid = (int) input('hotel_id', 'post', 0);
        if (!admin_owns_hotel($hid)) {
            $_SESSION['flash']['danger'] = 'You can only manage hotels in your own city.';
            header('Location: ' . $back . '#approvals'); exit;
        }
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            $hname = (string) $hotel['name'];
            if ($action === 'approve_hotel') {
                db_execute("UPDATE hotels SET is_verified = 1, is_active = 1 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_approved', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Approved!',
                         "\"{$hname}\" is now live on iExplore Laguna and visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('approve_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['success'] = "\"{$hname}\" approved.";
            } elseif ($action === 'reject_hotel') {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_rejected', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Registration Rejected',
                         "\"{$hname}\" was not approved. Please review your details and contact your city administrator.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('reject_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['danger'] = "\"{$hname}\" rejected.";
            } else {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_revoked', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Unpublished',
                         "\"{$hname}\" has been taken offline by an admin and is no longer visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('unpublish_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['danger'] = "\"{$hname}\" unpublished.";
            }
        }
        header('Location: ' . $back . '#approvals'); exit;
    }
}

// ── Data for this city only ───────────────────────────────────
ensure_admin_activity_table();

$spotStats = db_fetch_one(
    "SELECT COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active
     FROM tourist_spots WHERE city_id = ?", [$cityId]
) ?: ['total' => 0, 'active' => 0];

$views30 = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM spot_views v JOIN tourist_spots s ON s.id = v.spot_id
     WHERE s.city_id = ? AND v.viewed_at >= (NOW() - INTERVAL 30 DAY)", [$cityId])['n'] ?? 0);

$checkins30 = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM spot_checkins c JOIN tourist_spots s ON s.id = c.spot_id
     WHERE s.city_id = ? AND c.checked_in_at >= (NOW() - INTERVAL 30 DAY)", [$cityId])['n'] ?? 0);

$reviewStats = db_fetch_one(
    "SELECT COUNT(*) AS n, AVG(r.rating) AS avg_rating
     FROM spot_reviews r JOIN tourist_spots s ON s.id = r.spot_id
     WHERE s.city_id = ?", [$cityId]) ?: ['n' => 0, 'avg_rating' => null];

$bookings30 = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM bookings b JOIN hotels h ON h.id = b.hotel_id
     WHERE h.city_id = ? AND b.created_at >= (NOW() - INTERVAL 30 DAY)", [$cityId])['n'] ?? 0);

$orders30 = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM orders o JOIN shops s ON s.id = o.shop_id
     WHERE s.city_id = ? AND o.created_at >= (NOW() - INTERVAL 30 DAY)", [$cityId])['n'] ?? 0);

$topSpots = db_fetch_all(
    "SELECT s.id, s.name, COUNT(v.id) AS views
     FROM tourist_spots s
     LEFT JOIN spot_views v ON v.spot_id = s.id AND v.viewed_at >= (NOW() - INTERVAL 30 DAY)
     WHERE s.city_id = ?
     GROUP BY s.id, s.name
     ORDER BY views DESC, s.name
     LIMIT 5", [$cityId]);

$pending_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
     FROM shops s JOIN users u ON s.owner_id = u.id
     WHERE s.city_id = ? AND s.is_verified = 0
     ORDER BY s.created_at DESC", [$cityId]);

$pending_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
     FROM hotels h LEFT JOIN users u ON h.owner_id = u.id
     WHERE h.city_id = ? AND h.is_verified = 0
     ORDER BY h.id DESC", [$cityId]);

$verified_shops = db_fetch_all(
    "SELECT s.id, s.name, s.category, u.name AS owner_name
     FROM shops s JOIN users u ON s.owner_id = u.id
     WHERE s.city_id = ? AND s.is_verified = 1
     ORDER BY s.name", [$cityId]);

$verified_hotels = db_fetch_all(
    "SELECT h.id, h.name, h.star_rating, u.name AS owner_name
     FROM hotels h LEFT JOIN users u ON h.owner_id = u.id
     WHERE h.city_id = ? AND h.is_verified = 1
     ORDER BY h.name", [$cityId]);

$activity = db_fetch_all(
    "SELECT l.action, l.entity, l.details, l.created_at, u.name AS user_name
     FROM admin_activity_log l LEFT JOIN users u ON u.id = l.user_id
     WHERE l.city_id = ?
     ORDER BY l.id DESC LIMIT 10", [$cityId]);

$pendingTotal = count($pending_shops) + count($pending_hotels);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-buildings fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif"><?= e($cityName) ?> — City Dashboard</h1>
        <p class="mb-0 small opacity-75">Manage tourist spots, hotels and shops in <?= e($cityName) ?></p>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/pages/admin-spots.php" class="btn btn-sm btn-light"><i class="bi bi-plus-circle me-1"></i>Add / Manage Spots</a>
      <a href="<?= APP_URL ?>/pages/admin-spot-photos.php" class="btn btn-sm btn-outline-light"><i class="bi bi-images me-1"></i>Spot Photos</a>
      <a href="<?= APP_URL ?>/pages/admin-hotel-photos.php" class="btn btn-sm btn-outline-light"><i class="bi bi-building me-1"></i>Hotel Photos</a>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats -->
  <div class="stat-strip mb-4" style="border-radius:var(--radius)">
    <div class="row g-0 text-center">
      <?php
      $stats = [
        ['bi-geo-alt-fill',    'Active Spots',        (int) $spotStats['active'] . ' / ' . (int) $spotStats['total']],
        ['bi-eye',             'Spot Views (30 days)', $views30],
        ['bi-pin-map',         'Check-ins (30 days)',  $checkins30],
        ['bi-star-fill',       'Spot Reviews',        (int) $reviewStats['n'] . ($reviewStats['avg_rating'] ? ' · ' . number_format((float) $reviewStats['avg_rating'], 1) . '★' : '')],
        ['bi-calendar-check',  'Hotel Bookings (30 days)', $bookings30],
        ['bi-bag-check',       'Shop Orders (30 days)',    $orders30],
      ];
      foreach ($stats as [$ico, $lbl, $val]): ?>
      <div class="col-6 col-lg-2">
        <div class="stat-item">
          <div class="stat-item-icon"><i class="bi <?= $ico ?>"></i></div>
          <div class="stat-num" style="font-size:1.3rem"><?= e((string) $val) ?></div>
          <div class="stat-lbl"><?= e($lbl) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="row g-4">

    <!-- ── Approvals ─────────────────────────────────────────── -->
    <div class="col-lg-8" id="approvals">

      <div class="d-flex align-items-center gap-2 mb-3 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-hourglass-split" style="color:var(--maroon-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Waiting for your approval</h6>
        <?php if ($pendingTotal): ?>
          <span class="badge rounded-pill" style="background:#c0392b"><?= $pendingTotal ?></span>
        <?php endif; ?>
      </div>

      <?php if (!$pendingTotal): ?>
        <div class="text-center py-4 mb-4">
          <i class="bi bi-check2-circle fs-1 text-muted d-block mb-2"></i>
          <div class="fw-semibold">All caught up!</div>
          <div class="text-muted small">No pending hotel or shop registrations in <?= e($cityName) ?>.</div>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3 mb-4">

          <?php foreach ($pending_hotels as $h): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge" style="background:var(--maroon-dark);font-size:.7rem">Hotel</span>
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e((string) $h['name']) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e((string) ($h['address'] ?? '')) ?></div>
                <?php if (!empty($h['description'])): ?>
                  <div class="small text-muted mb-1"><?= e((string) $h['description']) ?></div>
                <?php endif; ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e((string) ($h['owner_name'] ?? 'No owner')) ?></strong>
                  <?php if (!empty($h['owner_email'])): ?>&nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e((string) $h['owner_email']) ?><?php endif; ?>
                  <?php if (!empty($h['owner_phone'])): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e((string) $h['owner_phone']) ?><?php endif; ?>
                </div>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve_hotel">
                  <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this hotel? The owner will need to re-submit their registration.');"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="reject_hotel">
                  <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-x-lg me-1"></i>Reject
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <?php foreach ($pending_shops as $s): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge" style="background:var(--terracotta);font-size:.7rem">Shop</span>
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e((string) $s['name']) ?></span>
                  <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.7rem"><?= e(ucfirst((string) $s['category'])) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e((string) ($s['address'] ?? '')) ?></div>
                <?php if (!empty($s['description'])): ?>
                  <div class="small text-muted mb-1"><?= e((string) $s['description']) ?></div>
                <?php endif; ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e((string) $s['owner_name']) ?></strong>
                  &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e((string) $s['owner_email']) ?>
                  <?php if (!empty($s['owner_phone'])): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e((string) $s['owner_phone']) ?><?php endif; ?>
                </div>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve_shop">
                  <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this shop? The owner will need to re-submit their registration.');"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="reject_shop">
                  <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-x-lg me-1"></i>Reject
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

        </div>
      <?php endif; ?>

      <!-- Published hotels -->
      <div class="d-flex align-items-center gap-2 mt-4 mb-3 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--maroon-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Published hotels (<?= count($verified_hotels) ?>)</h6>
      </div>
      <?php if (!$verified_hotels): ?>
        <p class="text-muted small">No published hotels in <?= e($cityName) ?> yet.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-2 mb-4">
          <?php foreach ($verified_hotels as $h): ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="fw-bold" style="font-size:.92rem"><?= e((string) $h['name']) ?></div>
              <div class="small text-muted">
                <?php if (!empty($h['star_rating'])): ?><?= str_repeat('★', (int) $h['star_rating']) ?> &nbsp;·&nbsp;<?php endif; ?>
                <i class="bi bi-person-circle me-1"></i><?= e((string) ($h['owner_name'] ?? 'No owner')) ?>
              </div>
            </div>
            <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Unpublish this hotel? It will be hidden from tourists until re-approved.');"><?= csrf_field() ?>
              <input type="hidden" name="action" value="revoke_hotel">
              <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Published shops -->
      <div class="d-flex align-items-center gap-2 mt-4 mb-3 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--maroon-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">Published shops (<?= count($verified_shops) ?>)</h6>
      </div>
      <?php if (!$verified_shops): ?>
        <p class="text-muted small">No published shops in <?= e($cityName) ?> yet.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($verified_shops as $s): ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold" style="font-size:.92rem"><?= e((string) $s['name']) ?></span>
                <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.68rem"><?= e(ucfirst((string) $s['category'])) ?></span>
              </div>
              <div class="small text-muted"><i class="bi bi-person-circle me-1"></i><?= e((string) $s['owner_name']) ?></div>
            </div>
            <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Unpublish this shop? It will be hidden from tourists until re-approved.');"><?= csrf_field() ?>
              <input type="hidden" name="action" value="revoke_shop">
              <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

    <!-- ── Side column ───────────────────────────────────────── -->
    <div class="col-lg-4">

      <div class="form-panel mb-4">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-graph-up me-2"></i>Top spots (last 30 days)
        </h6>
        <?php if (!$topSpots): ?>
          <p class="text-muted small mb-2">No spots yet. <a href="<?= APP_URL ?>/pages/admin-spots.php">Add your first spot</a>.</p>
        <?php else: ?>
          <ol class="ps-3 mb-0 small">
            <?php foreach ($topSpots as $sp): ?>
              <li class="mb-1">
                <a href="<?= APP_URL ?>/pages/admin-spots.php?id=<?= (int) $sp['id'] ?>" class="text-decoration-none"><?= e((string) $sp['name']) ?></a>
                <span class="text-muted">· <?= (int) $sp['views'] ?> view<?= (int) $sp['views'] === 1 ? '' : 's' ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>

      <div class="form-panel">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          <i class="bi bi-clock-history me-2"></i>Recent activity
        </h6>
        <?php if (!$activity): ?>
          <p class="text-muted small mb-0">Nothing yet. Your changes will be listed here.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0 small">
            <?php foreach ($activity as $a): ?>
              <li class="mb-2 pb-2" style="border-bottom:1px solid var(--border)">
                <div><strong><?= e(ucwords(str_replace('_', ' ', (string) $a['action']))) ?></strong><?= $a['details'] ? ' — ' . e((string) $a['details']) : '' ?></div>
                <div class="text-muted"><?= e((string) ($a['user_name'] ?? 'Unknown')) ?> · <?= e(date('M d, g:i A', strtotime((string) $a['created_at']))) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
