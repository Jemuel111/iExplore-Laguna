<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Admin: Manage Trip Packages
// pages/admin-packages.php
// Step 1: package info + hotel/room   Step 2: assign spots per day
// ============================================================
$page_title  = 'Manage Packages';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

$step       = input('step', 'get', '');
$edit_id    = (int) input('id', 'get', 0);
$errors     = [];

// ── POST: create package (step 1) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'create_package') {
    $title       = trim(input('title', 'post', ''));
    $description = trim(input('description', 'post', ''));
    $scope       = input('scope', 'post', 'single_city') === 'multi_city' ? 'multi_city' : 'single_city';
    $city_id     = (int) input('city_id', 'post', 0);
    $days        = max(1, (int) input('days', 'post', 2));
    $price       = (float) input('estimated_price', 'post', 0);
    $hotel_id    = (int) input('hotel_id', 'post', 0);
    $room_id     = (int) input('room_id', 'post', 0);
    $emoji       = trim(input('cover_emoji', 'post', ''));

    if (strlen($title) < 3)              $errors[] = 'Title is required.';
    if ($scope === 'single_city' && !$city_id) $errors[] = 'Select a city for a single-city package.';
    if ($price <= 0)                     $errors[] = 'Estimated price is required.';
    if (!$hotel_id || !$room_id)         $errors[] = 'Select a hotel and room type.';

    if (empty($errors)) {
        db_execute(
            "INSERT INTO packages (title, description, scope, city_id, days, estimated_price, hotel_id, room_id, cover_emoji, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,1)",
            [$title, $description, $scope, $scope==='single_city' ? $city_id : null, $days, $price, $hotel_id, $room_id, $emoji]
        );
        $new_id = db_last_id();
        header('Location: ' . APP_URL . '/pages/admin-packages.php?step=2&id=' . $new_id); exit;
    }
}

// ── POST: save spot assignments (step 2) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'save_spots') {
    $pkg_id = (int) input('package_id', 'post', 0);
    $spot_ids = $_POST['spot_id'] ?? [];
    $day_nums = $_POST['day_number'] ?? [];

    db_execute("DELETE FROM package_spots WHERE package_id = ?", [$pkg_id]);
    $order = 0;
    foreach ($spot_ids as $sid) {
        $sid = (int) $sid;
        $day = max(1, (int) ($day_nums[$sid] ?? 1));
        db_execute(
            "INSERT INTO package_spots (package_id, spot_id, day_number, sort_order) VALUES (?,?,?,?)",
            [$pkg_id, $sid, $day, $order++]
        );
    }
    $_SESSION['flash']['success'] = 'Package itinerary saved!';
    header('Location: ' . APP_URL . '/pages/admin-packages.php'); exit;
}

// ── POST: toggle / delete ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'toggle_package') {
    $pid = (int) input('package_id', 'post', 0);
    db_execute("UPDATE packages SET is_active = NOT is_active WHERE id = ?", [$pid]);
    header('Location: ' . APP_URL . '/pages/admin-packages.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'delete_package') {
    $pid = (int) input('package_id', 'post', 0);
    db_execute("DELETE FROM packages WHERE id = ?", [$pid]);
    $_SESSION['flash']['danger'] = 'Package deleted.';
    header('Location: ' . APP_URL . '/pages/admin-packages.php'); exit;
}

// ── Data for forms ──────────────────────────────────────────────
$cities = db_fetch_all("SELECT id, name FROM cities ORDER BY name");
$hotels = db_fetch_all(
    "SELECT h.id, h.name, h.city_id, c.name AS city_name FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE h.is_active = 1 AND h.is_verified = 1
     ORDER BY c.name, h.name"
);
$rooms = db_fetch_all(
    "SELECT id, hotel_id, room_type, price_per_night FROM hotel_rooms WHERE is_available = 1 ORDER BY hotel_id, price_per_night"
);

$editing_package = null;
$editing_spots   = [];
if ($step === '2' && $edit_id) {
    $editing_package = db_fetch_one(
        "SELECT p.*, c.name AS city_name FROM packages p LEFT JOIN cities c ON p.city_id = c.id WHERE p.id = ?",
        [$edit_id]
    );
    $assigned = db_fetch_all("SELECT spot_id, day_number FROM package_spots WHERE package_id = ?", [$edit_id]);
    foreach ($assigned as $a) { $editing_spots[$a['spot_id']] = $a['day_number']; }
}

// Spot pool for step 2 — all spots if multi-city, only that city's spots if single-city
if ($editing_package) {
    if ($editing_package['scope'] === 'single_city' && $editing_package['city_id']) {
        $spot_pool = db_fetch_all(
            "SELECT s.*, c.name AS city_name FROM tourist_spots s JOIN cities c ON s.city_id=c.id
             WHERE s.city_id = ? AND s.is_active = 1 ORDER BY s.name",
            [$editing_package['city_id']]
        );
    } else {
        $spot_pool = db_fetch_all(
            "SELECT s.*, c.name AS city_name FROM tourist_spots s JOIN cities c ON s.city_id=c.id
             WHERE s.is_active = 1 ORDER BY c.name, s.name"
        );
    }
}

// ── List of existing packages ────────────────────────────────
$packages = db_fetch_all(
    "SELECT p.*, c.name AS city_name, h.name AS hotel_name,
            (SELECT COUNT(*) FROM package_spots ps WHERE ps.package_id = p.id) AS spot_count
     FROM packages p
     LEFT JOIN cities c ON p.city_id = c.id
     LEFT JOIN hotels h ON p.hotel_id = h.id
     ORDER BY p.created_at DESC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-box-seam-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Manage Trip Packages</h1>
        <p class="mb-0 small opacity-75">Create ready-made packages tourists can book in one click</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Create form (step 1) or Spot assignment (step 2) ─────── -->
  <div class="col-lg-5">
    <?php if ($step === '2' && $editing_package): ?>
      <!-- STEP 2: Assign spots -->
      <div class="form-panel">
        <h6 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-geo-alt me-2"></i>Step 2 — Assign Spots
        </h6>
        <p class="text-muted small mb-3">
          "<?= e($editing_package['title']) ?>" · <?= $editing_package['days'] ?> day<?= $editing_package['days']!=1?'s':'' ?>
        </p>
        <form method="POST"><?= csrf_field() ?>
          <input type="hidden" name="action" value="save_spots">
          <input type="hidden" name="package_id" value="<?= $edit_id ?>">
          <div style="max-height:520px;overflow-y:auto" class="mb-3">
            <?php foreach ($spot_pool as $sp): $checked = isset($editing_spots[$sp['id']]); ?>
            <div class="d-flex align-items-center gap-2 p-2 mb-1" style="border:1px solid var(--border);border-radius:var(--radius-sm)">
              <input type="checkbox" class="form-check-input" name="spot_id[]" value="<?= $sp['id'] ?>"
                     id="sp<?= $sp['id'] ?>" <?= $checked?'checked':'' ?>>
              <label for="sp<?= $sp['id'] ?>" class="flex-grow-1 mb-0" style="font-size:.86rem;cursor:pointer">
                <strong><?= e($sp['name']) ?></strong>
                <span class="text-muted"> · <?= e($sp['city_name']) ?></span>
              </label>
              <select name="day_number[<?= $sp['id'] ?>]" class="form-select form-select-sm" style="width:90px">
                <?php for ($d=1; $d<=$editing_package['days']; $d++): ?>
                <option value="<?= $d ?>" <?= ($checked && $editing_spots[$sp['id']]==$d) ? 'selected' : '' ?>>Day <?= $d ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary-app w-100">
            <i class="bi bi-check-lg me-2"></i>Save Package Itinerary
          </button>
        </form>
      </div>
    <?php else: ?>
      <!-- STEP 1: Package info -->
      <div class="form-panel">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <i class="bi bi-plus-circle me-2"></i>New Package
        </h6>
        <?php if ($errors): ?>
        <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST"><?= csrf_field() ?>
          <input type="hidden" name="action" value="create_package">
          <div class="mb-3">
            <label class="form-label">Package Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="title" placeholder="e.g. San Pablo Lakes & Hot Springs Getaway" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="2" style="resize:none" placeholder="Short teaser shown on the package card"></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Scope</label>
              <select class="form-select" name="scope" id="scope-select" onchange="toggleCityField()">
                <option value="single_city">Single City</option>
                <option value="multi_city">Multi-City</option>
              </select>
            </div>
            <div class="col-6" id="city-field">
              <label class="form-label">City</label>
              <select class="form-select" name="city_id">
                <option value="">Select…</option>
                <?php foreach ($cities as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Days</label>
              <input type="number" class="form-control" name="days" value="2" min="1" max="14">
            </div>
            <div class="col-6">
              <label class="form-label">Est. Price (₱)</label>
              <input type="number" class="form-control" name="estimated_price" min="1" step="50" placeholder="3000" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Included Hotel <span class="text-danger">*</span></label>
            <select class="form-select" name="hotel_id" id="hotel-select" onchange="filterRooms()" required>
              <option value="">Select hotel…</option>
              <?php foreach ($hotels as $h): ?>
              <option value="<?= $h['id'] ?>" data-hotel="<?= $h['id'] ?>"><?= e($h['name']) ?> — <?= e($h['city_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label">Room Type <span class="text-danger">*</span></label>
            <select class="form-select" name="room_id" id="room-select" required>
              <option value="">Select hotel first…</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary-app w-100">
            <i class="bi bi-arrow-right-circle me-2"></i>Continue to Assign Spots
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Existing packages list ─────────────────────────────── -->
  <div class="col-lg-7">
    <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
      All Packages (<?= count($packages) ?>)
    </h6>
    <?php if (empty($packages)): ?>
      <p class="text-muted small">No packages yet — create one on the left.</p>
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($packages as $p): ?>
      <div class="d-flex align-items-center gap-3 p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);opacity:<?= $p['is_active']?'1':'.55' ?>">
        <div style="width:44px;height:44px;background:var(--green-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
          <?= $p['cover_emoji'] ?>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-bold" style="font-size:.9rem"><?= e($p['title']) ?></div>
          <div class="text-muted small">
            <?php if ($p['scope']==='single_city'): ?><i class="bi bi-geo-alt"></i> <?= e($p['city_name']) ?><?php else: ?><i class="bi bi-map"></i> Multi-City<?php endif; ?>
            · <?= $p['days'] ?>d · <?= $p['spot_count'] ?> spots · <?= e($p['hotel_name'] ?? '—') ?>
          </div>
        </div>
        <div class="fw-bold flex-shrink-0" style="color:var(--green-dark)">₱<?= number_format($p['estimated_price'],0) ?></div>
        <div class="d-flex gap-1 flex-shrink-0">
          <a href="?step=2&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;padding:.25rem .6rem" title="Edit spots">
            <i class="bi bi-pencil"></i>
          </a>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_package"><input type="hidden" name="package_id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm <?= $p['is_active']?'btn-outline-secondary':'btn-outline-success' ?>" style="font-size:.72rem;padding:.25rem .6rem">
              <i class="bi <?= $p['is_active']?'bi-eye-slash':'bi-eye' ?>"></i>
            </button>
          </form>
          <form method="POST" onsubmit="return confirm('Delete this package permanently?')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_package"><input type="hidden" name="package_id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem;padding:.25rem .6rem"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>

<script>
const ROOMS = <?= json_encode($rooms) ?>;

function toggleCityField() {
  const scope = document.getElementById('scope-select').value;
  document.getElementById('city-field').style.display = scope === 'multi_city' ? 'none' : '';
}

function filterRooms() {
  const hotelId = document.getElementById('hotel-select').value;
  const roomSelect = document.getElementById('room-select');
  const rooms = ROOMS.filter(r => String(r.hotel_id) === String(hotelId));
  roomSelect.innerHTML = rooms.length
    ? rooms.map(r => `<option value="${r.id}">${r.room_type} — ₱${Number(r.price_per_night).toLocaleString()}/night</option>`).join('')
    : '<option value="">No rooms for this hotel</option>';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
