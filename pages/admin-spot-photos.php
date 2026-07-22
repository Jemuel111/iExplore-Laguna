<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Admin: Manage Spot Photos
// pages/admin-spot-photos.php
// Upload multiple photos at once for any tourist spot
// ============================================================
$page_title  = 'Manage Spot Photos';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

$spot_id = (int) input('spot_id', 'get', 0);

// ── POST: upload one or more photos ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'upload_photos') {
    $targetSpotId = (int) input('spot_id', 'post', 0);
    $photoType    = input('photo_type', 'post', 'gallery');
    $allowedTypes = ['main', 'gallery', 'food', 'activity'];
    if (!in_array($photoType, $allowedTypes, true)) $photoType = 'gallery';

    if (!$targetSpotId) {
        $_SESSION['flash']['danger'] = 'Please select a spot first.';
        header('Location: ' . APP_URL . '/pages/admin-spot-photos.php'); exit;
    }

    $uploaded = 0;
    $failed   = [];

    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $fileCount = count($_FILES['photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

            // Reshape into the single-file $_FILES structure handle_image_upload() expects
            $_FILES['__single_photo'] = [
                'name'     => $_FILES['photos']['name'][$i],
                'type'     => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error'    => $_FILES['photos']['error'][$i],
                'size'     => $_FILES['photos']['size'][$i],
            ];
            try {
                $url = handle_image_upload('__single_photo', 'spots');
                if ($url) {
                    $sortOrder = db_fetch_one("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM spot_photos WHERE spot_id=?", [$targetSpotId])['n'] ?? 0;
                    db_execute(
                        "INSERT INTO spot_photos (spot_id, url, caption, photo_type, sort_order) VALUES (?,?,?,?,?)",
                        [$targetSpotId, $url, input('caption','post',''), $photoType, $sortOrder]
                    );
                    $uploaded++;
                }
            } catch (RuntimeException $e) {
                $failed[] = $_FILES['photos']['name'][$i] . ': ' . $e->getMessage();
            }
        }
    }

    if ($uploaded > 0) {
        $_SESSION['flash']['success'] = "Uploaded {$uploaded} photo" . ($uploaded!=1?'s':'') . "!";
    }
    if (!empty($failed)) {
        $_SESSION['flash']['danger'] = 'Some files failed: ' . implode('; ', $failed);
    }
    if ($uploaded === 0 && empty($failed)) {
        $_SESSION['flash']['danger'] = 'No files were selected.';
    }
    header('Location: ' . APP_URL . '/pages/admin-spot-photos.php?spot_id=' . $targetSpotId); exit;
}

// ── POST: set a photo as the main hero photo ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'set_main') {
    $photoId = (int) input('photo_id', 'post', 0);
    $sid     = (int) input('spot_id', 'post', 0);
    // Demote any existing main photo for this spot, then promote the chosen one
    db_execute("UPDATE spot_photos SET photo_type='gallery' WHERE spot_id=? AND photo_type='main'", [$sid]);
    db_execute("UPDATE spot_photos SET photo_type='main' WHERE id=? AND spot_id=?", [$photoId, $sid]);
    header('Location: ' . APP_URL . '/pages/admin-spot-photos.php?spot_id=' . $sid); exit;
}

// ── POST: delete a photo ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'delete_photo') {
    $photoId = (int) input('photo_id', 'post', 0);
    $sid     = (int) input('spot_id', 'post', 0);
    db_execute("DELETE FROM spot_photos WHERE id=? AND spot_id=?", [$photoId, $sid]);
    $_SESSION['flash']['success'] = 'Photo removed.';
    header('Location: ' . APP_URL . '/pages/admin-spot-photos.php?spot_id=' . $sid); exit;
}

// ── Data ──────────────────────────────────────────────────────
$spots = db_fetch_all(
    "SELECT s.id, s.name, c.name AS city_name,
            (SELECT COUNT(*) FROM spot_photos p WHERE p.spot_id = s.id) AS photo_count
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     ORDER BY c.name, s.name"
);

$selected_spot = null;
$spot_photos   = [];
if ($spot_id) {
    $selected_spot = db_fetch_one(
        "SELECT s.*, c.name AS city_name FROM tourist_spots s JOIN cities c ON s.city_id=c.id WHERE s.id=?",
        [$spot_id]
    );
    $spot_photos = db_fetch_all(
        "SELECT * FROM spot_photos WHERE spot_id=? ORDER BY photo_type='main' DESC, sort_order",
        [$spot_id]
    );
}

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
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-images fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Manage Spot Photos</h1>
        <p class="mb-0 small opacity-75">Upload multiple photos at once for any tourist spot</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Spot picker ─────────────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-geo-alt me-2"></i>Select a Spot
      </h6>
      <form method="GET">
        <select class="form-select mb-2" name="spot_id" onchange="this.form.submit()">
          <option value="">Choose a spot…</option>
          <?php foreach ($spots as $sp): ?>
          <option value="<?= $sp['id'] ?>" <?= $spot_id==$sp['id']?'selected':'' ?>>
            <?= e($sp['name']) ?> — <?= e($sp['city_name']) ?> (<?= $sp['photo_count'] ?> photo<?= $sp['photo_count']!=1?'s':'' ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($selected_spot): ?>
    <div class="form-panel mt-3">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <i class="bi bi-cloud-upload me-2"></i>Upload Photos
      </h6>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_photos">
        <input type="hidden" name="spot_id" value="<?= $selected_spot['id'] ?>">
        <div class="mb-3">
          <label class="form-label">Photos (select multiple)</label>
          <input type="file" class="form-control" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
          <div class="form-text">JPG, PNG, or WEBP — max 3MB each.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select class="form-select" name="photo_type">
            <option value="gallery">🖼️ Gallery (general)</option>
            <option value="food">🍜 Food &amp; Dining</option>
            <option value="activity">🏃 Activity</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Caption (applies to all in this batch)</label>
          <input type="text" class="form-control" name="caption" placeholder="Optional">
        </div>
        <button type="submit" class="btn btn-primary-app w-100">
          <i class="bi bi-upload me-2"></i>Upload Photos
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Existing photos ─────────────────────────────────────── -->
  <div class="col-lg-8">
    <?php if (!$selected_spot): ?>
      <div class="text-center py-5">
        <i class="bi bi-images fs-1 text-muted d-block mb-3"></i>
        <h5>Pick a spot to manage its photos</h5>
      </div>
    <?php else: ?>
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        <?= e($selected_spot['name']) ?> — <?= count($spot_photos) ?> photo<?= count($spot_photos)!=1?'s':'' ?>
      </h6>
      <?php if (empty($spot_photos)): ?>
        <p class="text-muted small">No photos yet — upload some on the left.</p>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($spot_photos as $ph): ?>
        <div class="col-sm-6 col-lg-4">
          <div style="border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:#fff">
            <div style="position:relative;height:140px">
              <img src="<?= e($ph['url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
              <?php if ($ph['photo_type'] === 'main'): ?>
              <span class="badge" style="position:absolute;top:6px;left:6px;background:var(--sand-dark);color:var(--green-dark);font-size:.68rem">
                ⭐ Main Photo
              </span>
              <?php endif; ?>
            </div>
            <div class="p-2">
              <div class="small text-muted mb-2 text-capitalize"><?= e($ph['photo_type']) ?></div>
              <div class="d-flex gap-1">
                <?php if ($ph['photo_type'] !== 'main'): ?>
                <form method="POST" class="flex-grow-1">
                  <input type="hidden" name="action" value="set_main">
                  <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                  <input type="hidden" name="spot_id" value="<?= $selected_spot['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary w-100" style="font-size:.72rem">⭐ Set Main</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this photo?')">
                  <input type="hidden" name="action" value="delete_photo">
                  <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                  <input type="hidden" name="spot_id" value="<?= $selected_spot['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/pages/spot-detail.php?id=<?= $selected_spot['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm mt-3">
        <i class="bi bi-box-arrow-up-right me-1"></i>View Live Page
      </a>
    <?php endif; ?>
  </div>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
