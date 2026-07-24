<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Admin Dashboard
// pages/admin-dashboard.php
// Approve / reject shop & hotel registrations
// ============================================================
$page_title  = 'Admin Dashboard';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    if ($action === 'approve_shop' || $action === 'reject_shop') {
        $sid  = (int) input('shop_id', 'post', 0);
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            if ($action === 'approve_shop') {
                db_execute("UPDATE shops SET is_verified = 1, is_active = 1 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'shop_approved', ?, ?, ?)",
                    [$shop['owner_id'], '🎉 Shop Approved!',
                     "\"{$shop['name']}\" is now live on iExplore Laguna and visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                $_SESSION['flash']['success'] = "\"{$shop['name']}\" approved.";
            } else {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'shop_rejected', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Registration Rejected',
                     "\"{$shop['name']}\" was not approved. Please review your details and contact support.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                $_SESSION['flash']['danger'] = "\"{$shop['name']}\" rejected.";
            }
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#shops'); exit;
    }

    if ($action === 'approve_hotel' || $action === 'reject_hotel') {
        $hid   = (int) input('hotel_id', 'post', 0);
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            if ($action === 'approve_hotel') {
                db_execute("UPDATE hotels SET is_verified = 1, is_active = 1 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, 'hotel_approved', ?, ?, ?)",
                        [$hotel['owner_id'], '🎉 Hotel Approved!',
                         "\"{$hotel['name']}\" is now live on iExplore Laguna and visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                $_SESSION['flash']['success'] = "\"{$hotel['name']}\" approved.";
            } else {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, 'hotel_rejected', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Registration Rejected',
                         "\"{$hotel['name']}\" was not approved. Please review your details and contact support.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                $_SESSION['flash']['danger'] = "\"{$hotel['name']}\" rejected.";
            }
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#hotels'); exit;
    }

    if ($action === 'revoke_shop') {
        $sid  = (int) input('shop_id', 'post', 0);
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
            db_execute(
                "INSERT INTO notifications (user_id, type, title, message, link)
                 VALUES (?, 'shop_revoked', ?, ?, ?)",
                [$shop['owner_id'], 'Shop Unpublished',
                 "\"{$shop['name']}\" has been taken offline by an admin and is no longer visible to tourists.",
                 APP_URL . '/pages/shop-dashboard.php']
            );
            $_SESSION['flash']['danger'] = "\"{$shop['name']}\" unpublished.";
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#shops'); exit;
    }

    if ($action === 'revoke_hotel') {
        $hid   = (int) input('hotel_id', 'post', 0);
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
            if ($hotel['owner_id']) {
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'hotel_revoked', ?, ?, ?)",
                    [$hotel['owner_id'], 'Hotel Unpublished',
                     "\"{$hotel['name']}\" has been taken offline by an admin and is no longer visible to tourists.",
                     APP_URL . '/pages/hotel-dashboard.php']
                );
            }
            $_SESSION['flash']['danger'] = "\"{$hotel['name']}\" unpublished.";
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#hotels'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$pending_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM shops s
     JOIN users u  ON s.owner_id = u.id
     JOIN cities c ON s.city_id  = c.id
     WHERE s.is_verified = 0
     ORDER BY s.created_at DESC"
);

$pending_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM hotels h
     LEFT JOIN users u ON h.owner_id = u.id
     JOIN cities c      ON h.city_id  = c.id
     WHERE h.is_verified = 0
     ORDER BY h.id DESC"
);

$verified_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM shops s
     JOIN users u  ON s.owner_id = u.id
     JOIN cities c ON s.city_id  = c.id
     WHERE s.is_verified = 1
     ORDER BY s.name"
);

$verified_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM hotels h
     LEFT JOIN users u ON h.owner_id = u.id
     JOIN cities c      ON h.city_id  = c.id
     WHERE h.is_verified = 1
     ORDER BY h.name"
);

$verified_shops_count  = count($verified_shops);
$verified_hotels_count = count($verified_hotels);
$total_users            = db_fetch_one("SELECT COUNT(*) n FROM users")['n'] ?? 0;

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

<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-shield-check fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Admin Dashboard</h1>
        <p class="mb-0 small opacity-75">Review and approve shop &amp; hotel registrations</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <?php
    $stats = [
      ['⏳', 'Pending Shops',  count($pending_shops),  '#fef3c7','#92400e'],
      ['⏳', 'Pending Hotels', count($pending_hotels), '#fef3c7','#92400e'],
      ['🏪', 'Verified Shops', $verified_shops_count,  '#d4edda','#155724'],
      ['🏨', 'Verified Hotels',$verified_hotels_count, '#d4edda','#155724'],
      ['👥', 'Total Users',    $total_users,            '#dbeafe','#1e40af'],
    ];
    foreach ($stats as [$ico,$lbl,$val,$bg,$fg]): ?>
    <div class="col-6 col-lg">
      <div class="p-3 h-100" style="background:<?= $bg ?>;border-radius:var(--radius);border:1.5px solid <?= $fg ?>22">
        <div style="font-size:1.4rem;margin-bottom:.2rem"><?= $ico ?></div>
        <div style="font-size:1.3rem;font-weight:800;color:<?= $fg ?>"><?= $val ?></div>
        <div style="font-size:.75rem;color:<?= $fg ?>;opacity:.8"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--border)">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#shops" style="font-weight:600">
        <i class="bi bi-shop me-1"></i>Shops
        <?php if (count($pending_shops)): ?>
          <span class="badge rounded-pill ms-1" style="background:#c0392b;font-size:.7rem"><?= count($pending_shops) ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#hotels" style="font-weight:600">
        <i class="bi bi-building me-1"></i>Hotels
        <?php if (count($pending_hotels)): ?>
          <span class="badge rounded-pill ms-1" style="background:#c0392b;font-size:.7rem"><?= count($pending_hotels) ?></span>
        <?php endif; ?>
      </a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── PENDING SHOPS ── -->
    <div class="tab-pane fade show active" id="shops">
      <?php if (empty($pending_shops)): ?>
        <div class="text-center py-5">
          <i class="bi bi-check2-circle fs-1 text-muted d-block mb-3"></i>
          <h5>All caught up!</h5>
          <p class="text-muted">No pending shop registrations.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($pending_shops as $s): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e($s['name']) ?></span>
                  <span class="badge" style="background:var(--green-pale);color:var(--green-dark);font-size:.72rem"><?= e(ucfirst($s['category'])) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($s['city_name']) ?><?= $s['address'] ? ' — '.e($s['address']) : '' ?></div>
                <?php if ($s['description']): ?>
                  <div class="small text-muted mb-1"><?= e($s['description']) ?></div>
                <?php endif; ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e($s['owner_name']) ?></strong>
                  &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e($s['owner_email']) ?>
                  <?php if ($s['owner_phone']): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e($s['owner_phone']) ?><?php endif; ?>
                </div>
                <div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i>Registered <?= date('M d, Y', strtotime($s['created_at'])) ?></div>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action"  value="approve_shop">
                  <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this shop registration?')"><?= csrf_field() ?>
                  <input type="hidden" name="action"  value="reject_shop">
                  <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
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

      <!-- ── VERIFIED SHOPS ── -->
      <div class="d-flex align-items-center gap-2 mt-5 mb-3 pb-2" style="border-bottom:2px solid var(--green-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--green-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          Verified Shops (<?= count($verified_shops) ?>)
        </h6>
      </div>
      <?php if (empty($verified_shops)): ?>
        <p class="text-muted small">No verified shops yet.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($verified_shops as $s): ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3"
               style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold" style="font-size:.92rem"><?= e($s['name']) ?></span>
                <span class="badge" style="background:var(--green-pale);color:var(--green-dark);font-size:.68rem"><?= e(ucfirst($s['category'])) ?></span>
              </div>
              <div class="small text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($s['city_name']) ?>
                &nbsp;·&nbsp;<i class="bi bi-person-circle me-1"></i><?= e($s['owner_name']) ?>
              </div>
            </div>
            <form method="POST" onsubmit="return confirm('Unpublish this shop? It will be hidden from tourists until re-approved.')" class="flex-shrink-0"><?= csrf_field() ?>
              <input type="hidden" name="action"  value="revoke_shop">
              <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── PENDING HOTELS ── -->
    <div class="tab-pane fade" id="hotels">
      <?php if (empty($pending_hotels)): ?>
        <div class="text-center py-5">
          <i class="bi bi-check2-circle fs-1 text-muted d-block mb-3"></i>
          <h5>All caught up!</h5>
          <p class="text-muted">No pending hotel registrations.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($pending_hotels as $h): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e($h['name']) ?></span>
                  <span style="color:var(--sand-dark);font-size:.8rem"><?= str_repeat('★',(int)$h['star_rating']) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($h['city_name']) ?><?= $h['address'] ? ' — '.e($h['address']) : '' ?></div>
                <?php if ($h['description']): ?>
                  <div class="small text-muted mb-1"><?= e($h['description']) ?></div>
                <?php endif; ?>
                <?php if ($h['owner_name']): ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e($h['owner_name']) ?></strong>
                  &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e($h['owner_email']) ?>
                  <?php if ($h['owner_phone']): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e($h['owner_phone']) ?><?php endif; ?>
                </div>
                <?php else: ?>
                <div class="small text-muted mt-2 fst-italic">No owner account linked (legacy listing)</div>
                <?php endif; ?>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action"   value="approve_hotel">
                  <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this hotel registration?')"><?= csrf_field() ?>
                  <input type="hidden" name="action"   value="reject_hotel">
                  <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
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

      <!-- ── VERIFIED HOTELS ── -->
      <div class="d-flex align-items-center gap-2 mt-5 mb-3 pb-2" style="border-bottom:2px solid var(--green-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--green-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          Verified Hotels (<?= count($verified_hotels) ?>)
        </h6>
      </div>
      <?php if (empty($verified_hotels)): ?>
        <p class="text-muted small">No verified hotels yet.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($verified_hotels as $h): ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3"
               style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold" style="font-size:.92rem"><?= e($h['name']) ?></span>
                <span style="color:var(--sand-dark);font-size:.75rem"><?= str_repeat('★',(int)$h['star_rating']) ?></span>
              </div>
              <div class="small text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($h['city_name']) ?>
                <?php if ($h['owner_name']): ?>
                  &nbsp;·&nbsp;<i class="bi bi-person-circle me-1"></i><?= e($h['owner_name']) ?>
                <?php else: ?>
                  &nbsp;·&nbsp;<span class="fst-italic">Legacy listing (no owner account)</span>
                <?php endif; ?>
              </div>
            </div>
            <form method="POST" onsubmit="return confirm('Unpublish this hotel? It will be hidden from tourists until re-approved.')" class="flex-shrink-0"><?= csrf_field() ?>
              <input type="hidden" name="action"   value="revoke_hotel">
              <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash) {
    const tab = document.querySelector(`[href="${hash}"]`);
    if (tab) new bootstrap.Tab(tab).show();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
