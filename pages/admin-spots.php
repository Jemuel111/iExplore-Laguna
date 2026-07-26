<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Admin: Manage Tourist Spots
// pages/admin-spots.php
// Create new spots, edit entrance fee/hours/description/etc,
// activate/deactivate, delete.
// ============================================================
$page_title  = 'Manage Spots';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

// Category metadata now comes from the shared spot_categories() helper
// in helpers.php instead of a locally duplicated emoji array.

$edit_id = (int) input('id', 'get', 0);
$errors  = [];

// ── POST: create or update a spot ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'save_spot') {
    $spotId      = (int) input('spot_id', 'post', 0);
    $name        = trim(input('name', 'post', ''));
    $cityId      = (int) input('city_id', 'post', 0);
    $category    = input('category', 'post', 'nature');
    $description = trim(input('description', 'post', ''));
    $entranceFee = (float) input('entrance_fee', 'post', 0);
    $hours       = trim(input('operating_hours', 'post', ''));
    $contact     = trim(input('contact_number', 'post', ''));
    $gmapsUrl    = trim(input('google_maps_url', 'post', ''));
    $websiteUrl  = trim(input('website_url', 'post', ''));
    $tips        = trim(input('tips', 'post', ''));
    $lat         = input('latitude', 'post', '');
    $lng         = input('longitude', 'post', '');
    $isActive    = input('is_active', 'post', '') ? 1 : 0;
    $isClosed    = input('is_closed', 'post', '') ? 1 : 0;
    $closureReason = trim(input('closure_reason', 'post', ''));
    $closedUntil   = trim(input('closed_until', 'post', ''));

    $allowedCats = array_keys(spot_categories());

    if (strlen($name) < 2)                 $errors[] = 'Name is required.';
    if (!$cityId)                          $errors[] = 'Please select a city.';
    if (!in_array($category, $allowedCats)) $errors[] = 'Invalid category.';
    if ($entranceFee < 0)                  $errors[] = 'Entrance fee cannot be negative.';
    if ($isClosed && $closedUntil && $closedUntil < date('Y-m-d')) {
        $errors[] = 'Expected reopening date should be today or later (leave blank if unknown).';
    }

    // Lat/long are NOT NULL in the schema — fall back to the city's
    // coordinates if the admin doesn't know the exact pin location.
    if ($cityId && ($lat === '' || $lng === '')) {
        $city = db_fetch_one("SELECT latitude, longitude FROM cities WHERE id = ?", [$cityId]);
        if ($city) { $lat = $lat === '' ? $city['latitude'] : $lat; $lng = $lng === '' ? $city['longitude'] : $lng; }
    }
    if ($lat === '' || $lng === '') $errors[] = 'Could not determine coordinates — please enter latitude/longitude manually.';

    if (empty($errors)) {
        $slug = slugify($name);

        if ($spotId) {
            // Detect whether the closure state actually changed, so we only
            // stamp closure_updated_at (used to warn tourists whose itinerary
            // predates the announcement) when something real changed —
            // not on every unrelated edit.
            $prev = db_fetch_one(
                "SELECT is_closed, closure_reason, closed_until FROM tourist_spots WHERE id = ?",
                [$spotId]
            );
            $closureChanged = !$prev
                || (int)$prev['is_closed'] !== $isClosed
                || ($prev['closure_reason'] ?? '') !== ($closureReason ?: '')
                || ($prev['closed_until']   ?? '') !== ($closedUntil   ?: '');

            db_execute(
                "UPDATE tourist_spots SET
                    name=?, slug=?, city_id=?, category=?, description=?, entrance_fee=?,
                    operating_hours=?, contact_number=?, google_maps_url=?, website_url=?,
                    tips=?, latitude=?, longitude=?, is_active=?,
                    is_closed=?, closure_reason=?, closed_until=?" .
                    ($closureChanged ? ", closure_updated_at=NOW()" : "") . "
                 WHERE id=?",
                [$name, $slug, $cityId, $category, $description ?: null, $entranceFee,
                 $hours ?: null, $contact ?: null, $gmapsUrl ?: null, $websiteUrl ?: null,
                 $tips ?: null, $lat, $lng, $isActive,
                 $isClosed, ($isClosed && $closureReason) ? $closureReason : null,
                 ($isClosed && $closedUntil) ? $closedUntil : null,
                 $spotId]
            );

            $_SESSION['flash']['success'] = "\"{$name}\" updated.";

            // If this save just closed the spot, tell the admin how many
            // already-saved tourist itineraries include it, so they know
            // the real-world impact.
            if ($closureChanged && $isClosed) {
                $affected = db_fetch_one(
                    "SELECT COUNT(*) AS n FROM itineraries WHERE spot_ids LIKE ?",
                    ['%,' . $spotId . ',%']
                )['n'] ?? 0;
                if ($affected > 0) {
                    $_SESSION['flash']['warning'] = "Heads up: {$affected} saved tourist itiner"
                        . ($affected == 1 ? 'y' : 'ies') . " already include \"{$name}\" — "
                        . "those tourists will see a closure notice on their My Itineraries page.";
                }
            }

            header('Location: ' . APP_URL . '/pages/admin-spots.php?id=' . $spotId); exit;
        } else {
            db_execute(
                "INSERT INTO tourist_spots
                    (city_id, name, slug, description, category, entrance_fee, operating_hours,
                     contact_number, latitude, longitude, google_maps_url, website_url, tips, is_active,
                     is_closed, closure_reason, closed_until, closure_updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$cityId, $name, $slug, $description ?: null, $category, $entranceFee, $hours ?: null,
                 $contact ?: null, $lat, $lng, $gmapsUrl ?: null, $websiteUrl ?: null, $tips ?: null, $isActive,
                 $isClosed, ($isClosed && $closureReason) ? $closureReason : null,
                 ($isClosed && $closedUntil) ? $closedUntil : null,
                 $isClosed ? date('Y-m-d H:i:s') : null]
            );
            $newId = db_last_id();
            $_SESSION['flash']['success'] = "\"{$name}\" created!";
            header('Location: ' . APP_URL . '/pages/admin-spots.php?id=' . $newId); exit;
        }
    }
}

// ── POST: toggle active/inactive ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'toggle_spot') {
    $sid = (int) input('spot_id', 'post', 0);
    db_execute("UPDATE tourist_spots SET is_active = NOT is_active WHERE id = ?", [$sid]);
    header('Location: ' . APP_URL . '/pages/admin-spots.php'); exit;
}

// ── POST: delete a spot ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'delete_spot') {
    $sid = (int) input('spot_id', 'post', 0);
    db_execute("DELETE FROM spot_photos WHERE spot_id = ?", [$sid]);
    db_execute("DELETE FROM spot_reviews WHERE spot_id = ?", [$sid]);
    db_execute("DELETE FROM spot_amenities WHERE spot_id = ?", [$sid]);
    db_execute("DELETE FROM tourist_spots WHERE id = ?", [$sid]);
    $_SESSION['flash']['danger'] = 'Spot deleted.';
    header('Location: ' . APP_URL . '/pages/admin-spots.php'); exit;
}

// ── Data ──────────────────────────────────────────────────────
$cities = db_fetch_all("SELECT id, name, latitude, longitude FROM cities ORDER BY name");

$editing_spot = null;
if ($edit_id) {
    $editing_spot = db_fetch_one("SELECT * FROM tourist_spots WHERE id = ?", [$edit_id]);
}

$search = trim(input('q', 'get', ''));
$spots_where  = ['1=1'];
$spots_params = [];
if ($search !== '') {
    $spots_where[]  = 's.name LIKE ?';
    $spots_params[] = '%' . $search . '%';
}
$spots_list = db_fetch_all(
    "SELECT s.id, s.name, s.entrance_fee, s.rating, s.is_active, s.category,
            s.is_closed, s.closure_reason, s.closed_until, c.name AS city_name
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE " . implode(' AND ', $spots_where) . "
     ORDER BY c.name, s.name",
    $spots_params
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
      <i class="bi bi-geo-alt-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Manage Tourist Spots</h1>
        <p class="mb-0 small opacity-75">Edit entrance fees, hours, descriptions — or add a brand-new spot</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Create / Edit form ─────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="form-panel">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          <?= $editing_spot ? '<i class="bi bi-pencil-square me-2"></i>Edit Spot' : '<i class="bi bi-plus-circle me-2"></i>New Spot' ?>
        </h6>
        <?php if ($editing_spot): ?>
        <a href="admin-spots.php" class="btn btn-sm btn-outline-secondary">+ New Instead</a>
        <?php endif; ?>
      </div>

      <?php if ($errors): ?>
      <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="POST"><?= csrf_field() ?>
        <input type="hidden" name="action" value="save_spot">
        <input type="hidden" name="spot_id" value="<?= $editing_spot['id'] ?? '' ?>">

        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="name" required
                 value="<?= e($editing_spot['name'] ?? '') ?>" placeholder="e.g. Pagsanjan Falls">
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">City <span class="text-danger">*</span></label>
            <select class="form-select" name="city_id" required>
              <option value="">Select…</option>
              <?php foreach ($cities as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($editing_spot['city_id'] ?? null)==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select class="form-select" name="category" required>
              <?php foreach (spot_categories() as $key => $meta): ?>
              <option value="<?= $key ?>" <?= ($editing_spot['category'] ?? '')===$key?'selected':'' ?>><?= $meta['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Entrance Fee (₱)</label>
            <input type="number" class="form-control" name="entrance_fee" min="0" step="0.01"
                   value="<?= e($editing_spot['entrance_fee'] ?? '0') ?>">
            <div class="form-text">0 = free entry</div>
          </div>
          <div class="col-6">
            <label class="form-label">Operating Hours</label>
            <input type="text" class="form-control" name="operating_hours" placeholder="e.g. 7:00 AM - 5:00 PM"
                   value="<?= e($editing_spot['operating_hours'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" style="resize:none"
                    placeholder="What makes this spot worth visiting?"><?= e($editing_spot['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Visitor Tips</label>
          <textarea class="form-control" name="tips" rows="2" style="resize:none"
                    placeholder="e.g. Wear comfortable shoes, bring cash…"><?= e($editing_spot['tips'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Contact Number</label>
          <input type="text" class="form-control" name="contact_number"
                 value="<?= e($editing_spot['contact_number'] ?? '') ?>" placeholder="Optional">
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Google Maps URL</label>
            <input type="text" class="form-control" name="google_maps_url"
                   value="<?= e($editing_spot['google_maps_url'] ?? '') ?>" placeholder="Optional">
          </div>
          <div class="col-6">
            <label class="form-label">Website URL</label>
            <input type="text" class="form-control" name="website_url"
                   value="<?= e($editing_spot['website_url'] ?? '') ?>" placeholder="Optional">
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Latitude</label>
            <input type="text" class="form-control" name="latitude"
                   value="<?= e($editing_spot['latitude'] ?? '') ?>" placeholder="Auto-fills from city if blank">
          </div>
          <div class="col-6">
            <label class="form-label">Longitude</label>
            <input type="text" class="form-control" name="longitude"
                   value="<?= e($editing_spot['longitude'] ?? '') ?>" placeholder="Auto-fills from city if blank">
          </div>
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_check"
                 <?= ($editing_spot['is_active'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active_check">Visible to tourists</label>
        </div>

        <div class="mb-4 p-3" style="background:var(--sand);border-radius:var(--radius-sm);border:1px solid var(--sand-dark)">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_closed" value="1" id="is_closed_check"
                   onchange="document.getElementById('closure-fields').classList.toggle('d-none', !this.checked)"
                   <?= !empty($editing_spot['is_closed']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="is_closed_check">
              <i class="bi bi-cone-striped me-1"></i>Temporarily closed
            </label>
            <div class="form-text mb-0">
              Use this for renovations, weather, maintenance, etc. — the spot stays
              on the site, but tourists are warned and it's kept out of new itineraries
              until it reopens. This is different from "Visible to tourists" above,
              which is for permanently retiring a spot.
            </div>
          </div>

          <div id="closure-fields" class="<?= empty($editing_spot['is_closed']) ? 'd-none' : '' ?>">
            <div class="mb-2">
              <label class="form-label small">Reason (shown to tourists)</label>
              <input type="text" class="form-control form-control-sm" name="closure_reason"
                     value="<?= e($editing_spot['closure_reason'] ?? '') ?>"
                     placeholder="e.g. Boat dock renovation">
            </div>
            <div>
              <label class="form-label small">Expected reopening date (optional)</label>
              <input type="date" class="form-control form-control-sm" name="closed_until"
                     value="<?= e($editing_spot['closed_until'] ?? '') ?>">
              <div class="form-text">Leave blank if unknown — spot will stay flagged closed until you uncheck the box above.</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary-app w-100">
          <i class="bi bi-check-lg me-2"></i><?= $editing_spot ? 'Save Changes' : 'Create Spot' ?>
        </button>
      </form>

      <?php if ($editing_spot): ?>
      <div class="d-flex gap-2 mt-2">
        <a href="admin-spot-photos.php?spot_id=<?= $editing_spot['id'] ?>" class="btn btn-outline-secondary btn-sm flex-grow-1">
          <i class="bi bi-images me-1"></i>Manage Photos
        </a>
        <a href="<?= APP_URL ?>/pages/spot-detail.php?id=<?= $editing_spot['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm flex-grow-1">
          <i class="bi bi-box-arrow-up-right me-1"></i>View Live
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Spot list ────────────────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
      <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        All Spots (<?= count($spots_list) ?>)
      </h6>
      <form method="GET" class="d-flex gap-1">
        <input type="text" class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Search…" style="width:180px;max-width:100%">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
      </form>
    </div>

    <?php if (empty($spots_list)): ?>
      <p class="text-muted small">No spots found.</p>
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($spots_list as $sp): ?>
      <div class="d-flex align-items-center gap-3 p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);opacity:<?= $sp['is_active']?'1':'.55' ?>">
        <div class="flex-grow-1 min-w-0">
          <div class="fw-bold d-flex align-items-center gap-2" style="font-size:.9rem">
            <?= e($sp['name']) ?>
            <?php if (!empty($sp['is_closed'])): ?>
              <span class="badge rounded-pill" style="background:#fee2e2;color:#a61c1c;font-size:.65rem;font-weight:700"
                    title="<?= e($sp['closure_reason'] ?: 'Temporarily closed') ?>">
                <i class="bi bi-cone-striped me-1"></i>Closed<?= $sp['closed_until'] ? ' until '.date('M j', strtotime($sp['closed_until'])) : '' ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="text-muted small">
            <?= spot_category_label($sp['category']) ?> · <?= e($sp['city_name']) ?> · <i class="bi bi-star-fill" style="color:var(--sand-dark)"></i> <?= number_format($sp['rating'],1) ?>
          </div>
        </div>
        <div class="fw-bold flex-shrink-0" style="color:var(--green-dark);white-space:nowrap">
          <?= $sp['entrance_fee'] > 0 ? '₱'.number_format($sp['entrance_fee'],0) : 'Free' ?>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
          <a href="?id=<?= $sp['id'] ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;padding:.25rem .6rem" title="Edit">
            <i class="bi bi-pencil"></i>
          </a>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_spot"><input type="hidden" name="spot_id" value="<?= $sp['id'] ?>">
            <button class="btn btn-sm <?= $sp['is_active']?'btn-outline-secondary':'btn-outline-success' ?>" style="font-size:.72rem;padding:.25rem .6rem" title="<?= $sp['is_active']?'Hide':'Show' ?>">
              <i class="bi <?= $sp['is_active']?'bi-eye-slash':'bi-eye' ?>"></i>
            </button>
          </form>
          <form method="POST" onsubmit="return confirm('Delete this spot permanently? This also removes its photos and reviews.')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_spot"><input type="hidden" name="spot_id" value="<?= $sp['id'] ?>">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
