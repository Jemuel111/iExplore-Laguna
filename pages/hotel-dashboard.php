<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Hotel Owner Dashboard
// pages/hotel-dashboard.php
// Manage room types, view & update bookings
// ============================================================
$page_title  = 'Hotel Dashboard';
$active_page = '';


// All redirects BEFORE any output
if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'hotel_owner') { header('Location: ' . APP_URL); exit; }

// Get this owner's hotel
$hotel = db_fetch_one("SELECT * FROM hotels WHERE owner_id = ?", [$u['id']]);
if (!$hotel) { header('Location: ' . APP_URL . '/pages/register-hotel.php?step=2'); exit; }

$hid = $hotel['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    // ── Add room type ────────────────────────────────────────
    if ($action === 'add_room') {
        $rtype = trim(input('room_type', 'post', ''));
        $price = (float) input('price', 'post', 0);
        $cap   = (int) input('capacity', 'post', 2);
        $bed   = trim(input('bed_type', 'post', ''));
        $count = (int) input('room_count', 'post', 1);
        $desc  = trim(input('description', 'post', ''));
        if ($rtype && $price > 0) {
            try {
                $photoUrl = handle_image_upload('photo', 'rooms');
            } catch (RuntimeException $e) {
                $_SESSION['flash']['error'] = $e->getMessage();
                header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
            }
            db_execute(
                "INSERT INTO hotel_rooms (hotel_id, owner_id, room_type, description, image_url, capacity, bed_type, room_count, price_per_night)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$hid, $u['id'], $rtype, $desc, $photoUrl, $cap ?: 2, $bed, $count ?: 1, $price]
            );
            $newRoomId = db_last_id();
            if ($photoUrl && $newRoomId) {
                db_execute("INSERT INTO room_photos (room_id, url, sort_order) VALUES (?,?,0)", [$newRoomId, $photoUrl]);
            }
            $_SESSION['flash']['success'] = "Room type \"{$rtype}\" added!";
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Room gallery: upload one or more photos ─────────────────
    if ($action === 'upload_room_photos') {
        $rid = (int) input('room_id', 'post', 0);
        $owns = db_fetch_one("SELECT id FROM hotel_rooms WHERE id=? AND hotel_id=?", [$rid, $hid]);
        if (!$owns) { header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit; }

        $uploaded = 0;
        $failed   = [];
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $fileCount = count($_FILES['photos']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $_FILES['__single_photo'] = [
                    'name' => $_FILES['photos']['name'][$i], 'type' => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i],
                    'size' => $_FILES['photos']['size'][$i],
                ];
                try {
                    $url = handle_image_upload('__single_photo', 'rooms');
                    if ($url) {
                        $sortOrder = db_fetch_one("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM room_photos WHERE room_id=?", [$rid])['n'] ?? 0;
                        db_execute("INSERT INTO room_photos (room_id, url, sort_order) VALUES (?,?,?)", [$rid, $url, $sortOrder]);
                        $hasCover = db_fetch_one("SELECT image_url FROM hotel_rooms WHERE id=?", [$rid]);
                        if (empty($hasCover['image_url'])) {
                            db_execute("UPDATE hotel_rooms SET image_url=? WHERE id=?", [$url, $rid]);
                        }
                        $uploaded++;
                    }
                } catch (RuntimeException $e) {
                    $failed[] = $_FILES['photos']['name'][$i] . ': ' . $e->getMessage();
                }
            }
        }
        if ($uploaded > 0) $_SESSION['flash']['success'] = "Uploaded {$uploaded} photo" . ($uploaded!=1?'s':'') . "!";
        if (!empty($failed)) $_SESSION['flash']['error'] = 'Some files failed: ' . implode('; ', $failed);
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Room gallery: set a photo as the cover shown on cards ────
    if ($action === 'set_main_room_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        $photo = db_fetch_one(
            "SELECT rp.url FROM room_photos rp JOIN hotel_rooms r ON rp.room_id=r.id
             WHERE rp.id=? AND r.hotel_id=?", [$photoId, $hid]
        );
        if ($photo) {
            $rid = (int) input('room_id', 'post', 0);
            db_execute("UPDATE hotel_rooms SET image_url=? WHERE id=? AND hotel_id=?", [$photo['url'], $rid, $hid]);
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Room gallery: delete a photo ─────────────────────────────
    if ($action === 'delete_room_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        $photo = db_fetch_one(
            "SELECT rp.url, rp.room_id FROM room_photos rp JOIN hotel_rooms r ON rp.room_id=r.id
             WHERE rp.id=? AND r.hotel_id=?", [$photoId, $hid]
        );
        if ($photo) {
            db_execute("DELETE FROM room_photos WHERE id=?", [$photoId]);
            $room = db_fetch_one("SELECT image_url FROM hotel_rooms WHERE id=?", [$photo['room_id']]);
            if ($room && $room['image_url'] === $photo['url']) {
                $next = db_fetch_one("SELECT url FROM room_photos WHERE room_id=? ORDER BY sort_order LIMIT 1", [$photo['room_id']]);
                db_execute("UPDATE hotel_rooms SET image_url=? WHERE id=?", [$next['url'] ?? null, $photo['room_id']]);
            }
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Hotel gallery: upload one or more photos ────────────────
    // Always scoped to this owner's own $hid — never a submitted hotel_id,
    // so an owner can't modify another hotel's gallery by tampering with
    // the form.
    if ($action === 'upload_hotel_photos') {
        ensure_hotel_photos_table();
        $uploaded = 0;
        $failed   = [];
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $fileCount = count($_FILES['photos']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
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
                        $sortOrder = db_fetch_one("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM hotel_photos WHERE hotel_id=?", [$hid])['n'] ?? 0;
                        db_execute(
                            "INSERT INTO hotel_photos (hotel_id, url, caption, photo_type, sort_order) VALUES (?,?,?,?,?)",
                            [$hid, $url, input('caption','post',''), 'gallery', $sortOrder]
                        );
                        $uploaded++;
                    }
                } catch (RuntimeException $e) {
                    $failed[] = $_FILES['photos']['name'][$i] . ': ' . $e->getMessage();
                }
            }
        }
        if ($uploaded > 0) $_SESSION['flash']['success'] = "Uploaded {$uploaded} photo" . ($uploaded!=1?'s':'') . "!";
        if (!empty($failed)) $_SESSION['flash']['error'] = 'Some files failed: ' . implode('; ', $failed);
        if ($uploaded === 0 && empty($failed)) $_SESSION['flash']['error'] = 'No files were selected.';
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#photos'); exit;
    }

    // ── Hotel gallery: set a photo as the main hero photo ───────
    if ($action === 'set_main_hotel_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        db_execute("UPDATE hotel_photos SET photo_type='gallery' WHERE hotel_id=? AND photo_type='main'", [$hid]);
        db_execute("UPDATE hotel_photos SET photo_type='main' WHERE id=? AND hotel_id=?", [$photoId, $hid]);
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#photos'); exit;
    }

    // ── Hotel gallery: delete a photo ────────────────────────────
    if ($action === 'delete_hotel_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        db_execute("DELETE FROM hotel_photos WHERE id=? AND hotel_id=?", [$photoId, $hid]);
        $_SESSION['flash']['success'] = 'Photo removed.';
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#photos'); exit;
    }

    // ── Toggle room availability ──────────────────────────────
    if ($action === 'toggle_room') {
        $rid = (int) input('room_id', 'post', 0);
        db_execute(
            "UPDATE hotel_rooms SET is_available = NOT is_available
             WHERE id = ? AND hotel_id = ?",
            [$rid, $hid]
        );
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Delete room type ────────────────────────────────────────
    if ($action === 'delete_room') {
        $rid = (int) input('room_id', 'post', 0);
        db_execute("DELETE FROM hotel_rooms WHERE id = ? AND hotel_id = ?", [$rid, $hid]);
        $_SESSION['flash']['success'] = 'Room type removed.';
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
    }

    // ── Update booking status ───────────────────────────────────
    if ($action === 'update_booking') {
        $bid    = (int) input('booking_id', 'post', 0);
        $status = input('status', 'post', '');
        $allowed = ['confirmed','checked_in','checked_out','cancelled'];
        if (in_array($status, $allowed)) {
            if ($status === 'confirmed') {
                $ts_col = ', confirmed_at = NOW()';
            } elseif ($status === 'checked_in') {
                $ts_col = ', checked_in_at = NOW()';
            } elseif ($status === 'checked_out') {
                $ts_col = ', checked_out_at = NOW()';
            } elseif ($status === 'cancelled') {
                $ts_col = ', cancelled_at = NOW()';
            } else {
                $ts_col = '';
            }
            db_execute(
                "UPDATE bookings SET status = ? {$ts_col}
                 WHERE id = ? AND hotel_id = ?",
                [$status, $bid, $hid]
            );
            // Notify tourist
            $booking = db_fetch_one("SELECT * FROM bookings WHERE id = ?", [$bid]);
            if ($booking) {
                $msgs = [
                    'confirmed'   => ['Booking Confirmed!', "Your booking #{$booking['booking_number']} has been confirmed by the hotel."],
                    'checked_in'  => ['Checked In', "You've been checked in for booking #{$booking['booking_number']}. Enjoy your stay!"],
                    'checked_out' => ['Checked Out', "Thanks for staying with us! Booking #{$booking['booking_number']} is now complete."],
                    'cancelled'   => ['Booking Cancelled', "Your booking #{$booking['booking_number']} was cancelled by the hotel."],
                ];
                if (isset($msgs[$status])) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, ?, ?, ?, ?)",
                        [$booking['tourist_id'], 'booking_'.$status,
                         $msgs[$status][0], $msgs[$status][1],
                         APP_URL . '/pages/my-bookings.php']
                    );
                }
            }
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#bookings'); exit;
    }

    // ── Update hotel info ──────────────────────────────────────
    if ($action === 'update_hotel') {
        $coverUrl = null;
        try {
            $coverUrl = handle_image_upload('cover_photo', 'hotels');
        } catch (RuntimeException $e) {
            $_SESSION['flash']['danger'] = $e->getMessage();
            header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#settings'); exit;
        }

        $sql    = "UPDATE hotels SET description=?, address=?, phone=?, star_rating=?, price_min=?, price_max=?";
        $params = [
            input('description','post',''),
            input('address','post',''),
            input('phone','post',''),
            (int) input('star_rating','post',3),
            (float) input('price_min','post',0) ?: null,
            (float) input('price_max','post',0) ?: null,
        ];
        if ($coverUrl) {
            $sql .= ", cover_url=?";
            $params[] = $coverUrl;
        }
        $sql .= " WHERE id = ?";
        $params[] = $hid;

        db_execute($sql, $params);
        $_SESSION['flash']['success'] = 'Hotel info updated.';
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#settings'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$rooms = db_fetch_all(
    "SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY room_type",
    [$hid]
);

ensure_hotel_photos_table();
$hotel_photos = db_fetch_all(
    "SELECT * FROM hotel_photos WHERE hotel_id = ? ORDER BY photo_type='main' DESC, sort_order",
    [$hid]
);

// Per-room photo galleries, grouped by room_id for easy lookup in the cards below.
$room_photos_raw = db_fetch_all(
    "SELECT rp.* FROM room_photos rp JOIN hotel_rooms r ON rp.room_id=r.id
     WHERE r.hotel_id = ? ORDER BY rp.sort_order", [$hid]
);
$room_photos = [];
foreach ($room_photos_raw as $rp) { $room_photos[$rp['room_id']][] = $rp; }

$bookings = db_fetch_all(
    "SELECT b.*, u.name AS tourist_name, u.phone AS tourist_phone, r.room_type
     FROM bookings b
     JOIN users u ON b.tourist_id = u.id
     JOIN hotel_rooms r ON b.room_id = r.id
     WHERE b.hotel_id = ?
     ORDER BY b.created_at DESC
     LIMIT 50",
    [$hid]
);

// Stats
$today_bookings = db_fetch_one("SELECT COUNT(*) n FROM bookings WHERE hotel_id=? AND DATE(created_at)=CURDATE()", [$hid])['n'] ?? 0;
$pending_count   = db_fetch_one("SELECT COUNT(*) n FROM bookings WHERE hotel_id=? AND status='pending'", [$hid])['n'] ?? 0;
$total_revenue   = db_fetch_one("SELECT COALESCE(SUM(total_amount),0) n FROM bookings WHERE hotel_id=? AND status='checked_out'", [$hid])['n'] ?? 0;
$total_rooms     = count($rooms);

// Booking status colors/icons/labels now come from the shared
// booking_status_meta() helper in helpers.php.

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- Page header -->
<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem"><i class="bi bi-building"></i></div>
        <div>
          <h1 class="mb-0 fs-4" style="font-family:'Playfair Display',serif"><?= e($hotel['name']) ?></h1>
          <p class="mb-0 small opacity-75">
            <i class="bi bi-building me-1"></i>Hotel Dashboard
            <?php if (!$hotel['is_verified']): ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem"><i class="bi bi-hourglass-split me-1"></i>Pending verification</span>
            <?php else: ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem"><i class="bi bi-check-circle-fill me-1"></i>Verified</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
      <?php if ($pending_count > 0): ?>
        <a href="#bookings" class="btn btn-sm"
           style="background:#fff;color:var(--maroon-dark);font-weight:700;border-radius:var(--radius-pill)">
          <i class="bi bi-bell-fill me-1"></i><?= $pending_count ?> New Booking<?= $pending_count > 1 ? 's' : '' ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats row -->
  <div class="stat-strip mb-4" style="border-radius:var(--radius)">
    <div class="row g-0 text-center">
    <?php
    $stats = [
      ['bi-calendar-check',  "Today's Bookings", $today_bookings],
      ['bi-hourglass-split', 'Pending Bookings', $pending_count],
      ['bi-door-closed',     'Room Types',       $total_rooms],
      ['bi-cash-stack',      'Total Revenue',    '₱'.number_format($total_revenue,2)],
    ];
    foreach ($stats as [$ico,$lbl,$val]): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-item">
        <div class="stat-item-icon"><i class="bi <?= $ico ?>"></i></div>
        <div class="stat-num" style="font-size:1.5rem"><?= $val ?></div>
        <div class="stat-lbl"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>


  <!-- Nav tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--border)">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#bookings" style="font-weight:600">
        <i class="bi bi-calendar-check me-1"></i>Bookings
        <?php if ($pending_count): ?>
          <span class="badge rounded-pill ms-1" style="background:var(--maroon-dark);font-size:.7rem"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#rooms" style="font-weight:600">
        <i class="bi bi-door-open me-1"></i>Room Types
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#photos" style="font-weight:600">
        <i class="bi bi-images me-1"></i>Photos
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#settings" style="font-weight:600">
        <i class="bi bi-gear me-1"></i>Hotel Settings
      </a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── BOOKINGS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="bookings">
      <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
          <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
          <h5>No bookings yet</h5>
          <p class="text-muted">Bookings will appear here once tourists reserve a room.</p>
        </div>
      <?php else: ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div class="input-group" style="max-width:340px">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="bookings-search" class="form-control"
                   placeholder="Search by booking #, customer, room type, or status…">
          </div>
          <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">
            <span id="bookings-visible-count"><?= count($bookings) ?></span> / <?= count($bookings) ?> shown
          </span>
        </div>
        <div class="d-flex flex-column gap-3" id="bookings-list-body">
          <?php foreach ($bookings as $bk):
            $st = booking_status_meta($bk['status']);
            $bg = $st['bg']; $fg = $st['fg'];
            $bookingSearchBlob = strtolower($bk['booking_number'].' '.$bk['tourist_name'].' '.$bk['room_type'].' '.$bk['status']);
          ?>
          <div class="p-0 rounded overflow-hidden booking-row" data-search="<?= e($bookingSearchBlob) ?>"
               style="border:1.5px solid var(--border);background:#fff">
            <!-- Booking header -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
                 style="background:var(--cream);border-bottom:1px solid var(--border)">
              <div>
                <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($bk['booking_number']) ?></span>
                <span class="ms-2" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700">
                  <i class="bi <?= $st['icon'] ?> me-1"></i><?= ucfirst(str_replace('_',' ',$bk['status'])) ?>
                </span>
              </div>
              <div class="text-muted small">
                <i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($bk['created_at'])) ?>
              </div>
            </div>

            <div class="p-3">
              <div class="row g-3">
                <!-- Guest info -->
                <div class="col-sm-4">
                  <div class="small text-muted mb-1 fw-600">Guest</div>
                  <div class="fw-bold"><?= e($bk['guest_name']) ?></div>
                  <?php if ($bk['guest_phone']): ?>
                  <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($bk['guest_phone']) ?></div>
                  <?php endif; ?>
                  <div class="small text-muted mt-1"><?= e($bk['room_type']) ?> · <?= $bk['guests_count'] ?> guest<?= $bk['guests_count']!=1?'s':'' ?></div>
                </div>

                <!-- Dates -->
                <div class="col-sm-4">
                  <div class="small text-muted mb-1 fw-600">Stay</div>
                  <div class="small">
                    <i class="bi bi-box-arrow-in-right me-1 text-maroon"></i>
                    <?= date('M d, Y', strtotime($bk['check_in_date'])) ?>
                  </div>
                  <div class="small">
                    <i class="bi bi-box-arrow-left me-1 text-maroon"></i>
                    <?= date('M d, Y', strtotime($bk['check_out_date'])) ?>
                  </div>
                  <div class="small text-muted"><?= $bk['nights'] ?> night<?= $bk['nights']!=1?'s':'' ?></div>
                  <?php if ($bk['special_requests']): ?>
                  <div class="small text-muted mt-1 fst-italic"><i class="bi bi-chat-left-text me-1"></i><?= e($bk['special_requests']) ?></div>
                  <?php endif; ?>
                </div>

                <!-- Total + Actions -->
                <div class="col-sm-4 d-flex flex-column align-items-end justify-content-between">
                  <div class="text-end">
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold fs-5" style="color:var(--maroon-dark)">₱<?= number_format($bk['total_amount'],2) ?></div>
                  </div>

                  <!-- Status update buttons -->
                  <?php if ($bk['status'] === 'pending'): ?>
                  <div class="d-flex gap-2 mt-2">
                    <form method="POST"><?= csrf_field() ?>
                      <input type="hidden" name="action"     value="update_booking">
                      <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                      <input type="hidden" name="status"     value="confirmed">
                      <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill)">
                        <i class="bi bi-check me-1"></i>Accept
                      </button>
                    </form>
                    <form method="POST"><?= csrf_field() ?>
                      <input type="hidden" name="action"     value="update_booking">
                      <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                      <input type="hidden" name="status"     value="cancelled">
                      <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
                        Decline
                      </button>
                    </form>
                  </div>
                  <?php elseif ($bk['status'] === 'confirmed'): ?>
                  <form method="POST" class="mt-2"><?= csrf_field() ?>
                    <input type="hidden" name="action"     value="update_booking">
                    <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                    <input type="hidden" name="status"     value="checked_in">
                    <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--maroon-dark);font-weight:600;border-radius:var(--radius-pill)">
                      <i class="bi bi-door-open me-1"></i>Check In
                    </button>
                  </form>
                  <?php elseif ($bk['status'] === 'checked_in'): ?>
                  <form method="POST" class="mt-2"><?= csrf_field() ?>
                    <input type="hidden" name="action"     value="update_booking">
                    <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                    <input type="hidden" name="status"     value="checked_out">
                    <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill)">
                      <i class="bi bi-box-arrow-left me-1"></i>Check Out
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
          <button type="button" id="bookings-show-more" class="btn btn-outline-secondary btn-sm">
            Show more <i class="bi bi-chevron-down ms-1"></i>
          </button>
          <div id="bookings-no-results" class="text-muted small py-3 d-none">
            <i class="bi bi-search me-1"></i>No bookings match "<span id="bookings-no-results-query"></span>".
          </div>
        </div>
        <script>
        (function () {
          const PAGE_SIZE = 10;
          const rows = Array.from(document.querySelectorAll('#bookings-list-body .booking-row'));
          const searchInput = document.getElementById('bookings-search');
          const showMoreBtn = document.getElementById('bookings-show-more');
          const visibleCountEl = document.getElementById('bookings-visible-count');
          const noResultsEl = document.getElementById('bookings-no-results');
          const noResultsQueryEl = document.getElementById('bookings-no-results-query');
          let shownCount = PAGE_SIZE;

          function renderPaged() {
            rows.forEach((row, i) => { row.style.display = i < shownCount ? '' : 'none'; });
            visibleCountEl.textContent = Math.min(shownCount, rows.length);
            showMoreBtn.classList.toggle('d-none', shownCount >= rows.length);
            noResultsEl.classList.add('d-none');
          }
          function renderSearch(query) {
            let matches = 0;
            rows.forEach(row => {
              const isMatch = row.dataset.search.includes(query);
              row.style.display = isMatch ? '' : 'none';
              if (isMatch) matches++;
            });
            visibleCountEl.textContent = matches;
            showMoreBtn.classList.add('d-none');
            noResultsEl.classList.toggle('d-none', matches > 0);
            noResultsQueryEl.textContent = query;
          }
          showMoreBtn.addEventListener('click', () => {
            shownCount = Math.min(shownCount + PAGE_SIZE, rows.length);
            renderPaged();
          });
          searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            if (query) renderSearch(query); else { shownCount = PAGE_SIZE; renderPaged(); }
          });
          renderPaged();
        })();
        </script>
      <?php endif; ?>
    </div>

    <!-- ── ROOM TYPES TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="rooms">
      <div class="row g-4">

        <!-- Add room form -->
        <div class="col-lg-4">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--maroon-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-plus-circle me-2" style="color:var(--maroon-dark)"></i>Add Room Type
            </h6>
            <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
              <input type="hidden" name="action" value="add_room">
              <div class="mb-3">
                <label class="form-label">Room Type Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="room_type" placeholder="e.g. Deluxe Twin" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Photo</label>
                <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WEBP. Max 3MB. Optional, but rooms with photos get booked more.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Price per Night (₱) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="price" min="1" step="50" placeholder="0.00" required>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label">Capacity (guests)</label>
                  <input type="number" class="form-control" name="capacity" value="2" min="1">
                </div>
                <div class="col-6">
                  <label class="form-label">Bed Type</label>
                  <input type="text" class="form-control" name="bed_type" placeholder="e.g. Queen">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" style="resize:none"
                          placeholder="Short description…"></textarea>
              </div>
              <div class="mb-4">
                <label class="form-label"># of Rooms of this Type</label>
                <input type="number" class="form-control" name="room_count" value="1" min="1">
              </div>
              <button type="submit" class="btn btn-primary-app w-100">
                <i class="bi bi-plus-lg me-2"></i>Add Room Type
              </button>
            </form>
          </div>
        </div>

        <!-- Rooms list -->
        <div class="col-lg-8">
          <?php if (empty($rooms)): ?>
          <div class="text-center py-5">
            <i class="bi bi-door-closed fs-1 text-muted d-block mb-3"></i>
            <h5>No room types yet</h5>
            <p class="text-muted">Add your first room type using the form.</p>
          </div>
          <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($rooms as $r): $rPhotos = $room_photos[$r['id']] ?? []; ?>
            <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;
                        opacity:<?= $r['is_available'] ? '1' : '.55' ?>">
              <div class="d-flex align-items-center gap-3 p-3">
                <?php if (!empty($r['image_url'])): ?>
                  <img src="<?= e($r['image_url']) ?>" alt="<?= e($r['room_type']) ?>"
                       style="width:44px;height:44px;object-fit:cover;border-radius:10px;flex-shrink:0">
                <?php else: ?>
                  <div style="width:44px;height:44px;background:var(--maroon-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--maroon-dark);flex-shrink:0">
                    <i class="bi bi-door-closed"></i>
                  </div>
                <?php endif; ?>
                <div class="flex-grow-1 min-w-0">
                  <div class="fw-bold" style="font-size:.93rem"><?= e($r['room_type']) ?></div>
                  <div class="text-muted small">
                    <?= e($r['bed_type'] ?: '—') ?> · Sleeps <?= $r['capacity'] ?> ·
                    <?= $r['room_count'] ?> room<?= $r['room_count']!=1?'s':'' ?>
                  </div>
                </div>
                <div class="fw-bold" style="color:var(--maroon-dark);font-size:1rem;white-space:nowrap">
                  ₱<?= number_format($r['price_per_night'],2) ?>/night
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                  <!-- Manage photos toggle -->
                  <button type="button" class="btn btn-sm btn-outline-secondary gallery-toggle-btn"
                          style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .7rem"
                          data-target="#room-gallery-<?= $r['id'] ?>">
                    <i class="bi bi-images me-1"></i>Photos<?= count($rPhotos) ? ' ('.count($rPhotos).')' : '' ?>
                  </button>
                  <!-- Toggle availability -->
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action"   value="toggle_room">
                    <input type="hidden" name="room_id"  value="<?= $r['id'] ?>">
                    <button class="btn btn-sm <?= $r['is_available'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .75rem"
                            title="<?= $r['is_available'] ? 'Hide room type' : 'Show room type' ?>">
                      <i class="bi <?= $r['is_available'] ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= $r['is_available'] ? 'Hide' : 'Show' ?>
                    </button>
                  </form>
                  <!-- Delete -->
                  <form method="POST" onsubmit="return confirm('Delete this room type?')"><?= csrf_field() ?>
                    <input type="hidden" name="action"  value="delete_room">
                    <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .6rem">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Expandable photo gallery manager -->
              <div id="room-gallery-<?= $r['id'] ?>" class="d-none gallery-panel" style="background:#fdf3f0;padding:1rem;border-top:1px solid var(--border)">
                <?php if ($rPhotos): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <?php foreach ($rPhotos as $ph): ?>
                    <div style="position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:1.5px solid var(--border)">
                      <img src="<?= e($ph['url']) ?>" style="width:100%;height:100%;object-fit:cover">
                      <?php if ($ph['url'] === $r['image_url']): ?>
                        <span class="badge" style="position:absolute;top:2px;left:2px;background:var(--maroon-dark);font-size:.55rem">Cover</span>
                      <?php endif; ?>
                      <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);padding:.15rem;display:flex;gap:.25rem;justify-content:center">
                        <?php if ($ph['url'] !== $r['image_url']): ?>
                          <form method="POST" class="m-0"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_main_room_photo">
                            <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                            <button class="btn btn-sm btn-light" style="font-size:.6rem;padding:.05rem .35rem" title="Set as cover"><i class="bi bi-star"></i></button>
                          </form>
                        <?php endif; ?>
                        <form method="POST" class="m-0" onsubmit="return confirm('Remove this photo?')"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_room_photo">
                          <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                          <button class="btn btn-sm btn-outline-light" style="font-size:.6rem;padding:.05rem .35rem" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                  <p class="small text-muted mb-3">No photos yet — rooms with photos get booked far more often.</p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="upload_room_photos">
                  <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                  <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm" style="max-width:280px">
                  <button type="submit" class="btn btn-sm" style="background:var(--maroon-dark);color:#fff">Upload</button>
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
    document.querySelectorAll('.gallery-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelector(btn.dataset.target).classList.toggle('d-none');
      });
    });
    </script>

    <!-- ── PHOTOS TAB ────────────────────────────────────────── -->
    <div class="tab-pane fade" id="photos">
      <div class="row justify-content-center">
        <div class="col-lg-9">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
              <h5 class="fw-bold mb-3">Upload Photos</h5>
              <p class="text-muted small mb-3">
                These show up in your hotel's gallery on the public listing — the first
                impression a tourist gets before booking. You can select multiple files at once.
              </p>
              <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_hotel_photos">
                <div class="mb-3">
                  <input type="file" class="form-control" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                  <div class="form-text">JPG, PNG, or WEBP. Max 3MB each.</div>
                </div>
                <button type="submit" class="btn" style="background:var(--maroon-dark);color:#fff">
                  <i class="bi bi-upload me-1"></i>Upload
                </button>
              </form>
            </div>
          </div>

          <?php if (empty($hotel_photos)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-images fs-1 d-block mb-2"></i>
              <div class="fw-semibold">No photos yet</div>
              <div class="small">Upload your first hotel photo above — listings with photos get booked far more often.</div>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($hotel_photos as $ph): ?>
                <div class="col-6 col-md-4">
                  <div style="position:relative;border-radius:var(--radius-sm);overflow:hidden;border:1.5px solid var(--border)">
                    <img src="<?= e($ph['url']) ?>" alt="" style="width:100%;height:150px;object-fit:cover;display:block">
                    <?php if ($ph['photo_type'] === 'main'): ?>
                      <span class="badge" style="position:absolute;top:8px;left:8px;background:var(--maroon-dark);color:#fff">
                        <i class="bi bi-star-fill me-1"></i>Main photo
                      </span>
                    <?php endif; ?>
                    <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);padding:.4rem;display:flex;gap:.4rem;justify-content:center">
                      <?php if ($ph['photo_type'] !== 'main'): ?>
                        <form method="POST" class="m-0"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="set_main_hotel_photo">
                          <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                          <button class="btn btn-sm btn-light" style="font-size:.7rem;padding:.15rem .5rem" title="Set as main photo">
                            <i class="bi bi-star"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" class="m-0" onsubmit="return confirm('Remove this photo?')"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_hotel_photo">
                        <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                        <button class="btn btn-sm btn-outline-light" style="font-size:.7rem;padding:.15rem .5rem" title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── SETTINGS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="settings">
      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--maroon-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-pencil-square me-2"></i>Edit Hotel Info
            </h6>
            <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
              <input type="hidden" name="action" value="update_hotel">

              <div class="mb-3">
                <label class="form-label">Cover Photo</label>
                <?php if (!empty($hotel['cover_url'])): ?>
                <img src="<?= e($hotel['cover_url']) ?>" alt="Current cover"
                     class="d-block mb-2" style="width:100%;max-height:180px;object-fit:cover;border-radius:var(--radius-sm)">
                <?php endif; ?>
                <input type="file" class="form-control" name="cover_photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WEBP — max 3MB. Leave empty to keep the current photo.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" style="resize:none"><?= e($hotel['description'] ?? '') ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address" value="<?= e($hotel['address'] ?? '') ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone" value="<?= e($hotel['phone'] ?? '') ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Star Rating</label>
                  <select class="form-select" name="star_rating">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                      <option value="<?= $s ?>" <?= $hotel['star_rating']==$s?'selected':'' ?>><?= str_repeat('★',$s).str_repeat('☆',5-$s) ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Lowest Nightly Rate (₱)</label>
                  <input type="number" class="form-control" name="price_min" min="0" step="50" value="<?= e($hotel['price_min'] ?? '') ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Highest Nightly Rate (₱)</label>
                  <input type="number" class="form-control" name="price_max" min="0" step="50" value="<?= e($hotel['price_max'] ?? '') ?>">
                </div>
              </div>
              <button type="submit" class="btn btn-primary-app w-100">
                <i class="bi bi-floppy me-2"></i>Save Changes
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<script>
// Activate tab from URL hash
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash) {
    const tab = document.querySelector(`[href="${hash}"]`);
    if (tab) new bootstrap.Tab(tab).show();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
