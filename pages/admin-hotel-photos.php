<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Admin: Manage Hotel Photos
// pages/admin-hotel-photos.php
// Upload multiple photos at once for any hotel
// ============================================================
$page_title  = 'Manage Hotel Photos';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

// Make sure the gallery table exists — this app ships without a bundled
// SQL schema file, so create it on first use if it isn't there yet.
db()->exec(
    "CREATE TABLE IF NOT EXISTS hotel_photos (
        id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hotel_id INT(10) UNSIGNED NOT NULL,
        url VARCHAR(255) NOT NULL,
        caption VARCHAR(200) DEFAULT NULL,
        photo_type ENUM('main','gallery','room','amenity','exterior') DEFAULT 'gallery',
        sort_order TINYINT(4) DEFAULT 0,
        INDEX idx_hotel_photos_hotel (hotel_id),
        CONSTRAINT fk_hotel_photos_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
// Matches the exact column type/charset used by hotels.id and spot_photos in
// this project's schema (int(10) UNSIGNED, utf8mb4_general_ci) — a mismatch
// here is what causes MySQL errno 150 ("foreign key constraint incorrectly
// formed") when creating the table.

$hotel_id = (int) input('hotel_id', 'get', 0);

// ── POST: upload one or more photos ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'upload_photos') {
    $targetHotelId = (int) input('hotel_id', 'post', 0);
    $photoType     = input('photo_type', 'post', 'gallery');
    $allowedTypes  = ['main', 'gallery', 'room', 'amenity', 'exterior'];
    if (!in_array($photoType, $allowedTypes, true)) $photoType = 'gallery';

    if (!$targetHotelId) {
        $_SESSION['flash']['danger'] = 'Please select a hotel first.';
        header('Location: ' . APP_URL . '/pages/admin-hotel-photos.php'); exit;
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
                $url = handle_image_upload('__single_photo', 'hotels');
                if ($url) {
                    $sortOrder = db_fetch_one("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM hotel_photos WHERE hotel_id=?", [$targetHotelId])['n'] ?? 0;
                    db_execute(
                        "INSERT INTO hotel_photos (hotel_id, url, caption, photo_type, sort_order) VALUES (?,?,?,?,?)",
                        [$targetHotelId, $url, input('caption','post',''), $photoType, $sortOrder]
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
    header('Location: ' . APP_URL . '/pages/admin-hotel-photos.php?hotel_id=' . $targetHotelId); exit;
}

// ── POST: set a photo as the main hero photo ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'set_main') {
    $photoId = (int) input('photo_id', 'post', 0);
    $hid     = (int) input('hotel_id', 'post', 0);
    // Demote any existing main photo for this hotel, then promote the chosen one
    db_execute("UPDATE hotel_photos SET photo_type='gallery' WHERE hotel_id=? AND photo_type='main'", [$hid]);
    db_execute("UPDATE hotel_photos SET photo_type='main' WHERE id=? AND hotel_id=?", [$photoId, $hid]);
    header('Location: ' . APP_URL . '/pages/admin-hotel-photos.php?hotel_id=' . $hid); exit;
}

// ── POST: delete a photo ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action','post','') === 'delete_photo') {
    $photoId = (int) input('photo_id', 'post', 0);
    $hid     = (int) input('hotel_id', 'post', 0);
    db_execute("DELETE FROM hotel_photos WHERE id=? AND hotel_id=?", [$photoId, $hid]);
    $_SESSION['flash']['success'] = 'Photo removed.';
    header('Location: ' . APP_URL . '/pages/admin-hotel-photos.php?hotel_id=' . $hid); exit;
}

// ── Data ──────────────────────────────────────────────────────
$hotels = db_fetch_all(
    "SELECT h.id, h.name, c.name AS city_name,
            (SELECT COUNT(*) FROM hotel_photos p WHERE p.hotel_id = h.id) AS photo_count
     FROM hotels h
     JOIN cities c ON h.city_id = c.id
     ORDER BY c.name, h.name"
);

$selected_hotel = null;
$hotel_photos   = [];
if ($hotel_id) {
    $selected_hotel = db_fetch_one(
        "SELECT h.*, c.name AS city_name FROM hotels h JOIN cities c ON h.city_id=c.id WHERE h.id=?",
        [$hotel_id]
    );
    $hotel_photos = db_fetch_all(
        "SELECT * FROM hotel_photos WHERE hotel_id=? ORDER BY photo_type='main' DESC, sort_order",
        [$hotel_id]
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

<section class="py-3" style="background:linear-gradient(135deg,#5c1620,#8e2434);color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-images fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Manage Hotel Photos</h1>
        <p class="mb-0 small opacity-75">Upload multiple photos at once for any hotel</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Hotel picker ────────────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:#8e2434">
        <i class="bi bi-building me-2"></i>Select a Hotel
      </h6>
      <form method="GET">
        <select class="form-select mb-2" name="hotel_id" onchange="this.form.submit()">
          <option value="">Choose a hotel…</option>
          <?php foreach ($hotels as $ht): ?>
          <option value="<?= $ht['id'] ?>" <?= $hotel_id==$ht['id']?'selected':'' ?>>
            <?= e($ht['name']) ?> — <?= e($ht['city_name']) ?> (<?= $ht['photo_count'] ?> photo<?= $ht['photo_count']!=1?'s':'' ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($selected_hotel): ?>
    <div class="form-panel mt-3">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:#8e2434">
        <i class="bi bi-cloud-upload me-2"></i>Upload Photos
      </h6>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_photos">
        <input type="hidden" name="hotel_id" value="<?= $selected_hotel['id'] ?>">
        <div class="mb-3">
          <label class="form-label">Photos (select multiple)</label>
          <input type="file" class="form-control" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
          <div class="form-text">JPG, PNG, or WEBP — max 3MB each.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select class="form-select" name="photo_type">
            <option value="gallery">🖼️ Gallery (general)</option>
            <option value="room">🛏️ Room</option>
            <option value="amenity">🏊 Amenity</option>
            <option value="exterior">🏨 Exterior</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Caption (applies to all in this batch)</label>
          <input type="text" class="form-control" name="caption" placeholder="Optional">
        </div>
        <button type="submit" class="btn w-100" style="background:#8e2434;color:#fff;border-radius:var(--radius-sm)">
          <i class="bi bi-upload me-2"></i>Upload Photos
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Existing photos ─────────────────────────────────────── -->
  <div class="col-lg-8">
    <?php if (!$selected_hotel): ?>
      <div class="text-center py-5">
        <i class="bi bi-images fs-1 text-muted d-block mb-3"></i>
        <h5>Pick a hotel to manage its photos</h5>
      </div>
    <?php else: ?>
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:#8e2434">
        <?= e($selected_hotel['name']) ?> — <?= count($hotel_photos) ?> photo<?= count($hotel_photos)!=1?'s':'' ?>
      </h6>
      <?php if (empty($hotel_photos)): ?>
        <p class="text-muted small">No photos yet — upload some on the left.</p>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($hotel_photos as $ph): ?>
        <div class="col-sm-6 col-lg-4">
          <div style="border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:#fff">
            <div style="position:relative;height:140px">
              <img src="<?= e($ph['url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
              <?php if ($ph['photo_type'] === 'main'): ?>
              <span class="badge" style="position:absolute;top:6px;left:6px;background:var(--sand-dark);color:#8e2434;font-size:.68rem">
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
                  <input type="hidden" name="hotel_id" value="<?= $selected_hotel['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary w-100" style="font-size:.72rem">⭐ Set Main</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this photo?')">
                  <input type="hidden" name="action" value="delete_photo">
                  <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                  <input type="hidden" name="hotel_id" value="<?= $selected_hotel['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/pages/hotel.php?id=<?= $selected_hotel['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm mt-3">
        <i class="bi bi-box-arrow-up-right me-1"></i>View Live Page
      </a>
    <?php endif; ?>
  </div>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
