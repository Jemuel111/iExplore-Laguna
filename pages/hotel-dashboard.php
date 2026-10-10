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
        // Return to the same filter/page the owner was on
        parse_str((string) input('ret', 'post', ''), $ret_raw);
        $ret = [];
        foreach (['bstatus', 'bq', 'bp'] as $k) {
            if (isset($ret_raw[$k]) && is_string($ret_raw[$k]) && $ret_raw[$k] !== '') { $ret[$k] = $ret_raw[$k]; }
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php' . ($ret ? '?' . http_build_query($ret) : '') . '#bookings'); exit;
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

// Bookings list: filtered, searched and paged on the server so it stays fast as bookings pile up.
$BOOKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];
$bk_status = (string) input('bstatus', 'get', '');
if (!in_array($bk_status, $BOOKING_STATUSES, true)) { $bk_status = ''; }
$bk_q    = trim((string) input('bq', 'get', ''));
$bk_page = max(1, (int) input('bp', 'get', 1));
$bk_per  = 10;

$bk_where  = ['b.hotel_id = ?'];
$bk_params = [$hid];
if ($bk_status !== '') { $bk_where[] = 'b.status = ?'; $bk_params[] = $bk_status; }
if ($bk_q !== '') {
    $like = '%' . addcslashes($bk_q, '%_\\') . '%';
    $bk_where[] = "(b.booking_number LIKE ? OR b.guest_name LIKE ? OR u.name LIKE ? OR r.room_type LIKE ?)";
    array_push($bk_params, $like, $like, $like, $like);
}
$bk_where_sql = implode(' AND ', $bk_where);

$bookings_total = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM bookings b
       JOIN users u ON b.tourist_id = u.id
       JOIN hotel_rooms r ON b.room_id = r.id
      WHERE {$bk_where_sql}", $bk_params
)['n'] ?? 0);
$bk_pages = max(1, (int) ceil($bookings_total / $bk_per));
$bk_page  = min($bk_page, $bk_pages);
$bk_off   = ($bk_page - 1) * $bk_per;

$bookings = db_fetch_all(
    "SELECT b.*, u.name AS tourist_name, u.phone AS tourist_phone, r.room_type
     FROM bookings b
     JOIN users u ON b.tourist_id = u.id
     JOIN hotel_rooms r ON b.room_id = r.id
     WHERE {$bk_where_sql}
     ORDER BY b.created_at DESC
     LIMIT {$bk_per} OFFSET {$bk_off}",
    $bk_params
);

// Stats
$today_bookings = db_fetch_one("SELECT COUNT(*) n FROM bookings WHERE hotel_id=? AND DATE(created_at)=CURDATE()", [$hid])['n'] ?? 0;
$pending_count   = db_fetch_one("SELECT COUNT(*) n FROM bookings WHERE hotel_id=? AND status='pending'", [$hid])['n'] ?? 0;

// ── Revenue ledger ───────────────────────────────────────────
// Revenue = completed stays (checked out), dated by the day the guest checked out.
$ts_sub = "(SELECT total_amount, COALESCE(checked_out_at, updated_at) AS ts
              FROM bookings WHERE hotel_id = ? AND status = 'checked_out') t";
$rev = db_fetch_one(
    "SELECT
        COALESCE(SUM(CASE WHEN DATE(ts) = CURDATE() THEN total_amount END), 0)                          AS day_rev,
        COALESCE(SUM(DATE(ts) = CURDATE()), 0)                                                          AS day_cnt,
        COALESCE(SUM(CASE WHEN DATE(ts) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN total_amount END), 0) AS day_prev,
        COALESCE(SUM(CASE WHEN YEARWEEK(ts, 1) = YEARWEEK(CURDATE(), 1) THEN total_amount END), 0)      AS week_rev,
        COALESCE(SUM(YEARWEEK(ts, 1) = YEARWEEK(CURDATE(), 1)), 0)                                      AS week_cnt,
        COALESCE(SUM(CASE WHEN YEARWEEK(ts, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)
                          THEN total_amount END), 0)                                                    AS week_prev,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                          THEN total_amount END), 0)                                                    AS month_rev,
        COALESCE(SUM(DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')), 0)                    AS month_cnt,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')
                          THEN total_amount END), 0)                                                    AS month_prev
     FROM {$ts_sub}",
    [$hid]
) ?: [];
// key => [label, amount, completed stays, label of previous period, previous amount]
$periods = [
    'day'   => ['Today',      $rev['day_rev']   ?? 0, $rev['day_cnt']   ?? 0, 'Yesterday',  $rev['day_prev']   ?? 0],
    'week'  => ['This week',  $rev['week_rev']  ?? 0, $rev['week_cnt']  ?? 0, 'Last week',  $rev['week_prev']  ?? 0],
    'month' => ['This month', $rev['month_rev'] ?? 0, $rev['month_cnt'] ?? 0, 'Last month', $rev['month_prev'] ?? 0],
];

// Daily revenue for the last 7 days (the bars under the headline figure).
$db_today  = db_fetch_one("SELECT CURDATE() AS d")['d'] ?? date('Y-m-d');
$daily_raw = db_fetch_all(
    "SELECT DATE(ts) AS d, SUM(total_amount) AS amt FROM {$ts_sub}
     WHERE DATE(ts) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(ts)",
    [$hid]
);
$daily_map = array_column($daily_raw, 'amt', 'd');
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("{$db_today} -{$i} day"));
    $daily[] = ['d' => $d, 'amt' => (float) ($daily_map[$d] ?? 0)];
}
$daily_max = max(array_column($daily, 'amt'));

// Bookings that need the owner: oldest pending first.
$queue = db_fetch_all(
    "SELECT b.id, b.guest_name, b.check_in_date, b.check_out_date, b.nights, b.guests_count, b.total_amount, r.room_type
       FROM bookings b JOIN hotel_rooms r ON b.room_id = r.id
      WHERE b.hotel_id = ? AND b.status = 'pending'
      ORDER BY b.created_at ASC LIMIT 4",
    [$hid]
);
$flow = db_fetch_one(
    "SELECT COALESCE(SUM(status = 'confirmed' AND check_in_date = CURDATE()), 0) AS arriving,
            COALESCE(SUM(status = 'checked_in'), 0)                              AS staying,
            COALESCE(SUM(status = 'confirmed' AND check_in_date > CURDATE()), 0) AS upcoming
       FROM bookings WHERE hotel_id = ?", [$hid]
) ?: [];

$chip_labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in',
                'checked_out' => 'Checked out', 'cancelled' => 'Cancelled'];
// Chip counts cover all of the hotel's bookings, whatever filter is active.
$status_counts = array_column(db_fetch_all(
    "SELECT status, COUNT(*) AS n FROM bookings WHERE hotel_id = ? GROUP BY status", [$hid]
), 'n', 'status');
$bookings_all = array_sum($status_counts);

/** One-button form that moves a booking to a new status. */
function sd_booking_btn(int $bid, string $status, string $label, string $cls = 'sd-btn'): string {
    return '<form method="POST" class="m-0">' . csrf_field()
         . '<input type="hidden" name="action" value="update_booking">'
         . '<input type="hidden" name="booking_id" value="' . $bid . '">'
         . '<input type="hidden" name="status" value="' . e($status) . '">'
         . '<input type="hidden" name="ret" value="' . e((string) ($_SERVER['QUERY_STRING'] ?? '')) . '">'
         . '<button class="' . e($cls) . '">' . e($label) . '</button></form>';
}

/** Link to the bookings list with some filters changed (always lands on the Bookings tab). */
function sd_bookings_url(array $over = []): string {
    global $bk_status, $bk_q, $bk_page;
    $q = array_merge(['bstatus' => $bk_status, 'bq' => $bk_q, 'bp' => $bk_page], $over);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return APP_URL . '/pages/hotel-dashboard.php' . ($q ? '?' . http_build_query($q) : '') . '#bookings';
}
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

<style>
/* Hotel dashboard — uses the site's tokens (app.css), scoped to .sd */
.sd .sd-head{padding:.5rem 0 1.75rem}
.sd .sd-title{font-size:clamp(1.9rem,4.2vw,2.6rem);margin:0;color:var(--charcoal)}
.sd .sd-sub{margin:.3rem 0 0;color:var(--text-muted);font-size:.92rem}
.sd .sd-sub i{color:var(--maroon-mid)}
.sd .sd-top{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:2.25rem;margin-bottom:2.75rem;align-items:start}
@media (max-width:991.98px){.sd .sd-top{grid-template-columns:1fr;gap:2rem}}

/* Revenue ledger */
.sd .sd-ledger{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.4rem 1.75rem 1.5rem}
.sd .sd-periods{display:flex;gap:1.5rem;border-bottom:1px solid var(--border);margin-bottom:1.4rem}
.sd .sd-periods button{background:none;border:0;border-bottom:2px solid transparent;margin-bottom:-1px;padding:.2rem 0 .65rem;
  font:inherit;font-weight:600;color:var(--text-muted);cursor:pointer}
.sd .sd-periods button:hover{color:var(--maroon-dark)}
.sd .sd-periods button[aria-selected="true"]{color:var(--maroon-dark);border-bottom-color:var(--maroon-mid)}
.sd .sd-figure{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:clamp(2.5rem,6vw,3.5rem);
  line-height:1.05;color:var(--charcoal);font-variant-numeric:lining-nums}
.sd .sd-peso{color:var(--maroon-mid);margin-right:.15em}
.sd .sd-note{margin:.55rem 0 0;color:var(--text-muted);font-size:.93rem}
.sd .sd-cap{margin:1.6rem 0 .5rem;font-size:.82rem;color:var(--text-muted)}
.sd .sd-bars{display:flex;gap:10px;height:104px;border-bottom:1px solid var(--border)}
.sd .sd-bar-col{flex:1;display:flex;flex-direction:column;min-width:0}
.sd .sd-bar-wrap{flex:1;display:flex;align-items:flex-end;justify-content:center;min-height:0}
.sd .sd-bar{width:100%;max-width:44px;min-height:2px;background:var(--sand-dark);opacity:.6;border-radius:3px 3px 0 0}
.sd .sd-bar-col.is-today .sd-bar{background:var(--maroon-mid);opacity:1}
.sd .sd-days{display:flex;gap:10px;margin-top:.4rem}
.sd .sd-days span{flex:1;text-align:center;font-size:.8rem;line-height:1.3;color:var(--text-muted)}
.sd .sd-days span em{display:block;font-style:normal;font-size:.68rem;opacity:.8}
.sd .sd-days span.is-today{color:var(--maroon-dark);font-weight:600}
.sd .sd-empty-bars{margin:0;padding:2.2rem 0 1rem;color:var(--text-muted);font-size:.9rem;border-bottom:1px solid var(--border)}

/* Waiting on you */
.sd .sd-h{font-size:1.3rem;margin:0 0 .15rem;color:var(--charcoal)}
.sd .sd-q-lede{margin:0 0 .5rem;color:var(--text-muted);font-size:.9rem}
.sd .sd-q-item{padding:.95rem 0;border-top:1px solid var(--border)}
.sd .sd-q-row{display:flex;justify-content:space-between;align-items:baseline;gap:1rem}
.sd .sd-q-code{font-weight:600;letter-spacing:.08em;color:var(--maroon-dark);font-variant-numeric:tabular-nums}
.sd .sd-q-name{font-weight:600;margin-left:.6rem}
.sd .sd-q-total{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:1.1rem}
.sd .sd-q-items{margin:.15rem 0 .65rem;color:var(--text-muted);font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sd .sd-q-actions{display:flex;gap:.5rem}
.sd .sd-q-foot{padding-top:.85rem;border-top:1px solid var(--border);color:var(--text-muted);font-size:.88rem}
.sd .sd-q-foot p{margin:0 0 .25rem}
.sd .sd-q-empty{padding:1.1rem 0;border-top:1px solid var(--border);color:var(--text-muted);font-size:.93rem}

/* Buttons */
.sd .sd-btn,.sd .sd-btn-sand,.sd .sd-btn-quiet{border:1px solid transparent;border-radius:50px;padding:.38rem 1.05rem;font-size:.85rem;font-weight:600;line-height:1.4;cursor:pointer;transition:background var(--transition),color var(--transition)}
.sd .sd-btn{background:var(--maroon-mid);color:#fff}
.sd .sd-btn:hover{background:var(--maroon-dark)}
.sd .sd-btn-sand{background:var(--sand-dark);color:var(--charcoal)}
.sd .sd-btn-sand:hover{background:#dcb558}
.sd .sd-btn-quiet{background:transparent;color:var(--text-muted);border-color:var(--border)}
.sd .sd-btn-quiet:hover{color:var(--maroon-dark);border-color:var(--maroon-mid)}
.sd button:focus-visible,.sd .nav-link:focus-visible,.sd input:focus-visible{outline:2px solid var(--maroon-dark);outline-offset:2px}

/* Tabs */
.sd .nav-tabs{border-bottom:1px solid var(--border);gap:1.9rem;margin-bottom:1.75rem}
.sd .nav-tabs .nav-link{border:0;border-bottom:2px solid transparent;border-radius:0;background:none;margin-bottom:-1px;padding:.55rem 0;font-weight:600;color:var(--text-muted)}
.sd .nav-tabs .nav-link:hover{color:var(--maroon-dark);border-bottom-color:transparent}
.sd .nav-tabs .nav-link.active{color:var(--maroon-dark);background:none;border-bottom-color:var(--maroon-mid)}
.sd .sd-tabn{margin-left:.35rem;font-weight:500;color:var(--text-muted)}
.sd .sd-tabn.is-alert{background:var(--maroon-mid);color:#fff;border-radius:50px;padding:.05rem .5rem;font-size:.75rem;font-weight:600}

/* Order filters */
.sd .sd-filters{display:flex;flex-wrap:wrap;gap:1rem 1.5rem;align-items:center;justify-content:space-between;margin-bottom:.4rem}
.sd .sd-chips{display:flex;flex-wrap:wrap;gap:.4rem}
.sd .sd-chip{background:#fff;border:1px solid var(--border);border-radius:50px;padding:.25rem .85rem;font-size:.83rem;font-weight:600;color:var(--charcoal);cursor:pointer}
.sd a.sd-chip{text-decoration:none}
.sd .sd-chip:hover{border-color:var(--maroon-mid);color:var(--charcoal)}
.sd .sd-chip[aria-current="true"]{background:var(--maroon-pale);border-color:var(--maroon-mid);color:var(--maroon-dark)}
.sd .sd-chip span{font-weight:500;color:var(--text-muted);margin-left:.25rem}
.sd .sd-search{position:relative;min-width:240px}
.sd .sd-search i{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--text-muted)}
.sd .sd-search input{width:100%;padding:.45rem .9rem .45rem 2.2rem;border:1px solid var(--border);border-radius:50px;background:#fff;font-size:.9rem}
.sd .sd-countline{margin:.7rem 0 1rem;color:var(--text-muted);font-size:.85rem}
.sd .sd-pager{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:.35rem;margin:1.6rem 0 .5rem}
.sd .sd-pager a,.sd .sd-pager span{min-width:2.3rem;padding:.35rem .8rem;text-align:center;border:1px solid var(--border);border-radius:50px;background:#fff;color:var(--charcoal);font-size:.88rem;font-weight:600;text-decoration:none}
.sd .sd-pager a:hover{border-color:var(--maroon-mid);color:var(--maroon-dark)}
.sd .sd-pager a[aria-current="page"]{background:var(--maroon-mid);border-color:var(--maroon-mid);color:#fff}
.sd .sd-pager .is-off{color:#b9b2a7;background:transparent}
.sd .sd-pager .gap{border:0;background:none;min-width:0;padding:.35rem .2rem;color:var(--text-muted)}

/* Pickup slips */
.sd .slip{display:flex;background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:.9rem}
.sd .slip[hidden]{display:none}
.sd .slip-stub{position:relative;flex:0 0 138px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.2rem;
  padding:1rem .75rem;background:var(--sand);border-right:2px dashed #d8cfc2}
.sd .slip-stub::before,.sd .slip-stub::after{content:"";position:absolute;right:-9px;width:16px;height:16px;border-radius:50%;
  background:var(--cream);border:1px solid var(--border)}
.sd .slip-stub::before{top:-9px}
.sd .slip-stub::after{bottom:-9px}
.sd .slip-code{font-weight:700;font-size:1.2rem;letter-spacing:.09em;color:var(--maroon-dark);font-variant-numeric:tabular-nums}
.sd .slip-stub small{font-size:.72rem;color:var(--text-muted)}
.sd .slip[data-status="pending"] .slip-stub{background:var(--maroon-mid);border-right-color:rgba(255,255,255,.55)}
.sd .slip[data-status="pending"] .slip-code,.sd .slip[data-status="pending"] .slip-stub small{color:#fff}
.sd .slip[data-status="checked_in"] .slip-stub{background:var(--sand-dark)}
.sd .slip[data-status="checked_in"] .slip-code{color:var(--charcoal)}
.sd .slip[data-status="checked_out"],.sd .slip[data-status="cancelled"]{opacity:.72}
.sd .slip[data-status="cancelled"] .slip-code{text-decoration:line-through}
.sd .slip-main{flex:1;min-width:0;padding:1rem 1.3rem 1.05rem}
.sd .slip-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}
.sd .slip-name{font-weight:600;font-size:1.02rem}
.sd .slip-meta{font-size:.84rem;color:var(--text-muted);line-height:1.5}
.sd .slip-status{font-size:.84rem;font-weight:600;white-space:nowrap}
.sd .slip-items{list-style:none;margin:.8rem 0 0;padding:0}
.sd .slip-items li{display:flex;align-items:baseline;gap:.5rem;font-size:.92rem;line-height:1.7}
.sd .slip-items .lead{flex:1;border-bottom:1px dotted #c9bfb0;min-width:1rem;transform:translateY(-.2em)}
.sd .slip-items .amt{font-variant-numeric:tabular-nums}
.sd .slip-note{margin:.45rem 0 0;font-size:.86rem;color:var(--text-muted);font-style:italic}
.sd .slip-foot{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin-top:.95rem}
.sd .slip-total{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:1.5rem;line-height:1.1}
.sd .slip-when{display:block;font-family:'DM Sans',system-ui,sans-serif;font-weight:400;font-size:.78rem;color:var(--text-muted);margin-top:.2rem}
.sd .slip-actions{display:flex;gap:.5rem;flex-wrap:wrap}
@media (max-width:575.98px){
  .sd .slip{flex-direction:column}
  .sd .slip-stub{flex:none;flex-direction:row;gap:.6rem;border-right:0;border-bottom:2px dashed #d8cfc2;padding:.7rem 1rem}
  .sd .slip[data-status="pending"] .slip-stub{border-bottom-color:rgba(255,255,255,.55)}
  .sd .slip-stub::before,.sd .slip-stub::after{right:auto;top:auto;bottom:-9px}
  .sd .slip-stub::before{left:-9px}
  .sd .slip-stub::after{right:-9px}
  .sd .sd-search{min-width:100%}
}

.sd .slip-stub small{line-height:1.3}
.sd .slip-stub .slip-code{font-size:1.25rem}
</style>

<div class="container py-4 sd">

  <header class="sd-head">
    <h1 class="sd-title"><?= e($hotel['name']) ?></h1>
    <p class="sd-sub">
      <?php if ($hotel['is_verified']): ?>
        <i class="bi bi-patch-check-fill me-1"></i>Verified hotel. Tourists can find you and book rooms.
      <?php else: ?>
        <i class="bi bi-hourglass-split me-1"></i>Waiting for admin verification. Tourists can't see your hotel yet.
      <?php endif; ?>
    </p>
  </header>

  <div class="sd-top">

    <!-- Revenue ledger -->
    <section class="sd-ledger" aria-label="Revenue">
      <div class="sd-periods" role="tablist">
        <?php $first = true; foreach ($periods as $key => $per): ?>
          <button type="button" role="tab" data-period="<?= $key ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= $per[0] ?></button>
        <?php $first = false; endforeach; ?>
      </div>

      <?php $first = true; foreach ($periods as $key => [$pLabel, $pAmt, $pCnt, $prevLabel, $prevAmt]): ?>
      <div class="sd-period" data-period="<?= $key ?>" <?= $first ? '' : 'hidden' ?>>
        <div class="sd-figure"><span class="sd-peso">₱</span><?= number_format((float) $pAmt, 2) ?></div>
        <p class="sd-note">
          <?= (int) $pCnt ?> <?= (int) $pCnt === 1 ? 'stay' : 'stays' ?> checked out.
          <?= $prevLabel ?>: ₱<?= number_format((float) $prevAmt, 2) ?>.
        </p>
      </div>
      <?php $first = false; endforeach; ?>

      <p class="sd-cap">Daily revenue, last 7 days</p>
      <?php if ($daily_max > 0): ?>
        <div class="sd-bars">
          <?php foreach ($daily as $day): $isToday = $day['d'] === $db_today; ?>
            <div class="sd-bar-col <?= $isToday ? 'is-today' : '' ?>"
                 title="<?= e(date('D, M j', strtotime($day['d']))) ?>: ₱<?= number_format($day['amt'], 2) ?>">
              <div class="sd-bar-wrap"><div class="sd-bar" style="height:<?= max(2, round($day['amt'] / $daily_max * 100)) ?>%"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="sd-days" aria-hidden="true">
          <?php foreach ($daily as $day): ?>
            <span class="<?= $day['d'] === $db_today ? 'is-today' : '' ?>"><?= $day['d'] === $db_today ? 'Today' : date('D', strtotime($day['d'])) ?><em><?= date('M j', strtotime($day['d'])) ?></em></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="sd-empty-bars">No guests checked out in the last 7 days. Revenue shows up here once you check a guest out.</p>
      <?php endif; ?>
    </section>

    <!-- Waiting on you -->
    <section aria-label="Bookings waiting for you">
      <h2 class="sd-h">Waiting on you</h2>
      <?php if ($queue): ?>
        <p class="sd-q-lede"><?= (int) $pending_count ?> new <?= (int) $pending_count === 1 ? 'booking needs' : 'bookings need' ?> an answer.</p>
        <?php foreach ($queue as $q): ?>
          <div class="sd-q-item">
            <div class="sd-q-row">
              <div><span class="sd-q-name" style="margin-left:0"><?= e($q['guest_name']) ?></span></div>
              <span class="sd-q-total">₱<?= number_format((float) $q['total_amount'], 2) ?></span>
            </div>
            <div class="sd-q-items">
              <?= e($q['room_type']) ?>, <?= date('M j', strtotime($q['check_in_date'])) ?> to <?= date('M j', strtotime($q['check_out_date'])) ?>
              (<?= (int) $q['nights'] ?> <?= (int) $q['nights'] === 1 ? 'night' : 'nights' ?>, <?= (int) $q['guests_count'] ?> <?= (int) $q['guests_count'] === 1 ? 'guest' : 'guests' ?>)
            </div>
            <div class="sd-q-actions">
              <?= sd_booking_btn((int) $q['id'], 'confirmed', 'Accept') ?>
              <?= sd_booking_btn((int) $q['id'], 'cancelled', 'Decline', 'sd-btn-quiet') ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($pending_count > count($queue)): ?>
          <div class="sd-q-foot"><p><?= (int) $pending_count - count($queue) ?> more in the Bookings tab.</p></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="sd-q-empty">Nothing waiting. New booking requests appear here as soon as tourists send them.</div>
      <?php endif; ?>

      <?php
        $prog = [];
        if (!empty($flow['arriving'])) $prog[] = $flow['arriving'] . ' arriving today';
        if (!empty($flow['staying']))  $prog[] = $flow['staying'] . ' staying now';
        if (!empty($flow['upcoming'])) $prog[] = $flow['upcoming'] . ' confirmed for later';
      ?>
      <div class="sd-q-foot">
        <?php if ($prog): ?><p>Guests: <?= implode(', ', $prog) ?>.</p><?php endif; ?>
        <p><?= (int) $today_bookings ?> <?= (int) $today_bookings === 1 ? 'booking' : 'bookings' ?> made today.</p>
      </div>
    </section>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#bookings">Bookings<?php if ($pending_count): ?><span class="sd-tabn is-alert"><?= (int) $pending_count ?></span><?php endif; ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#rooms">Room types<span class="sd-tabn"><?= (int) $total_rooms ?></span></a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#photos">Photos</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#settings">Hotel settings</a>
    </li>
  </ul>

  <script>
  document.querySelectorAll('.sd-periods button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.sd-periods button').forEach(b => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
      document.querySelectorAll('.sd-period').forEach(p => { p.hidden = p.dataset.period !== btn.dataset.period; });
    });
  });
  </script>

  <div class="tab-content">

    <!-- ── BOOKINGS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="bookings">
      <?php if ($bookings_all === 0): ?>
        <div class="py-5 text-center">
          <h5 class="mb-1">No bookings yet</h5>
          <p class="text-muted mb-0">When a tourist reserves a room, the booking appears here with its stay dates.</p>
        </div>
      <?php else: ?>
        <div class="sd-filters">
          <div class="sd-chips">
            <a class="sd-chip" href="<?= e(sd_bookings_url(['bstatus' => '', 'bp' => 1])) ?>" <?= $bk_status === '' ? 'aria-current="true"' : '' ?>>All<span><?= (int) $bookings_all ?></span></a>
            <?php foreach ($chip_labels as $stKey => $stLabel): if (empty($status_counts[$stKey])) continue; ?>
              <a class="sd-chip" href="<?= e(sd_bookings_url(['bstatus' => $stKey, 'bp' => 1])) ?>" <?= $bk_status === $stKey ? 'aria-current="true"' : '' ?>><?= $stLabel ?><span><?= (int) $status_counts[$stKey] ?></span></a>
            <?php endforeach; ?>
          </div>
          <form class="sd-search" method="get" action="<?= APP_URL ?>/pages/hotel-dashboard.php#bookings">
            <i class="bi bi-search"></i>
            <input type="search" name="bq" value="<?= e($bk_q) ?>" placeholder="Find a booking, guest or room" aria-label="Search bookings">
            <?php if ($bk_status !== ''): ?><input type="hidden" name="bstatus" value="<?= e($bk_status) ?>"><?php endif; ?>
          </form>
        </div>

        <p class="sd-countline">
          <?php if ($bookings_total === 0): ?>
            No bookings match<?= $bk_q !== '' ? ' "' . e($bk_q) . '"' : '' ?>.
            <a href="<?= e(sd_bookings_url(['bstatus' => '', 'bq' => '', 'bp' => 1])) ?>">Clear filters</a>
          <?php else: ?>
            Showing <?= $bk_off + 1 ?> to <?= $bk_off + count($bookings) ?> of <?= $bookings_total ?> <?= $bookings_total === 1 ? 'booking' : 'bookings' ?>
            <?php if ($bk_q !== ''): ?>matching "<?= e($bk_q) ?>". <a href="<?= e(sd_bookings_url(['bq' => '', 'bp' => 1])) ?>">Clear search</a><?php endif; ?>
          <?php endif; ?>
        </p>

        <div id="bookings-list-body">
          <?php foreach ($bookings as $bk):
            $st  = booking_status_meta($bk['status']);
            $bid = (int) $bk['id'];
            $n   = (int) $bk['nights'];
          ?>
          <article class="slip booking-row" data-status="<?= e($bk['status']) ?>">
            <div class="slip-stub">
              <span class="slip-code"><?= date('M j', strtotime($bk['check_in_date'])) ?></span>
              <small>to <?= date('M j', strtotime($bk['check_out_date'])) ?></small>
              <small><?= $n ?> <?= $n === 1 ? 'night' : 'nights' ?></small>
            </div>
            <div class="slip-main">
              <div class="slip-top">
                <div>
                  <div class="slip-name"><?= e($bk['guest_name']) ?></div>
                  <div class="slip-meta">
                    <?php if ($bk['guest_phone']): ?><?= e($bk['guest_phone']) ?><br><?php endif; ?>
                    Check-in <?= date('D, M j, Y', strtotime($bk['check_in_date'])) ?><br>
                    Check-out <?= date('D, M j, Y', strtotime($bk['check_out_date'])) ?>
                  </div>
                </div>
                <span class="slip-status" style="color:<?= $st['fg'] ?>"><i class="bi <?= $st['icon'] ?> me-1"></i><?= e($chip_labels[$bk['status']] ?? ucfirst($bk['status'])) ?></span>
              </div>

              <ul class="slip-items">
                <li>
                  <span><?= e($bk['room_type']) ?>, <?= (int) $bk['guests_count'] ?> <?= (int) $bk['guests_count'] === 1 ? 'guest' : 'guests' ?></span>
                  <span class="lead"></span>
                  <span class="amt">₱<?= number_format((float) $bk['price_per_night'], 2) ?> a night</span>
                </li>
              </ul>
              <?php if ($bk['special_requests']): ?>
                <p class="slip-note">Request from guest: <?= e($bk['special_requests']) ?></p>
              <?php endif; ?>

              <div class="slip-foot">
                <div class="slip-total">₱<?= number_format((float) $bk['total_amount'], 2) ?>
                  <span class="slip-when">Booking <?= e($bk['booking_number']) ?>, made <?= date('M j, g:i A', strtotime($bk['created_at'])) ?></span>
                </div>
                <div class="slip-actions">
                  <?php if ($bk['status'] === 'pending'): ?>
                    <?= sd_booking_btn($bid, 'confirmed', 'Accept') ?>
                    <?= sd_booking_btn($bid, 'cancelled', 'Decline', 'sd-btn-quiet') ?>
                  <?php elseif ($bk['status'] === 'confirmed'): ?>
                    <?= sd_booking_btn($bid, 'checked_in', 'Check in', 'sd-btn-sand') ?>
                  <?php elseif ($bk['status'] === 'checked_in'): ?>
                    <?= sd_booking_btn($bid, 'checked_out', 'Check out') ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <?php if ($bk_pages > 1):
          $nums = array_unique(array_merge([1, $bk_pages], range(max(1, $bk_page - 1), min($bk_pages, $bk_page + 1))));
          sort($nums);
        ?>
        <nav class="sd-pager" aria-label="Bookings pages">
          <?php if ($bk_page > 1): ?>
            <a href="<?= e(sd_bookings_url(['bp' => $bk_page - 1])) ?>">Previous</a>
          <?php else: ?><span class="is-off">Previous</span><?php endif; ?>
          <?php $prev = 0; foreach ($nums as $pn):
            if ($pn - $prev > 1): ?><span class="gap">…</span><?php endif; ?>
            <a href="<?= e(sd_bookings_url(['bp' => $pn])) ?>" <?= $pn === $bk_page ? 'aria-current="page"' : '' ?>><?= $pn ?></a>
          <?php $prev = $pn; endforeach; ?>
          <?php if ($bk_page < $bk_pages): ?>
            <a href="<?= e(sd_bookings_url(['bp' => $bk_page + 1])) ?>">Next</a>
          <?php else: ?><span class="is-off">Next</span><?php endif; ?>
        </nav>
        <?php endif; ?>
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
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action"  value="delete_room">
                    <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                    <button type="button" class="btn btn-sm btn-outline-danger js-confirm-action"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .6rem"
                            data-icon="bi-trash3" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
                            data-title="Delete this room type?" data-name="<?= e($r['room_type']) ?>"
                            data-body="This can't be undone."
                            data-confirm-label="Yes, delete" data-confirm-class="btn-danger">
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
                        <form method="POST" class="m-0"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_room_photo">
                          <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                          <button type="button" class="btn btn-sm btn-outline-light js-confirm-action" style="font-size:.6rem;padding:.05rem .35rem" title="Delete"
                            data-icon="bi-trash3" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
                            data-title="Remove this photo?" data-name="<?= e($r['room_type']) ?>"
                            data-body="This photo can't be recovered once removed."
                            data-confirm-label="Yes, remove" data-confirm-class="btn-danger"><i class="bi bi-trash"></i></button>
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
                      <form method="POST" class="m-0"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_hotel_photo">
                        <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                        <button type="button" class="btn btn-sm btn-outline-light js-confirm-action" style="font-size:.7rem;padding:.15rem .5rem" title="Delete"
                          data-icon="bi-trash3" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
                          data-title="Remove this photo?" data-name="Hotel photo"
                          data-body="This photo can't be recovered once removed."
                          data-confirm-label="Yes, remove" data-confirm-class="btn-danger">
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

<!-- Generic confirmation modal, replacing this page's plain browser confirm() popups. -->
<div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-labelledby="genericConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--radius);overflow:hidden;border:none">
      <div class="modal-body text-center p-4 pb-3">
        <div id="genericConfirmIcon" style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 1rem">
          <i class="bi" id="genericConfirmIconInner"></i>
        </div>
        <h5 class="fw-bold mb-3" id="genericConfirmTitle" style="font-family:'Playfair Display',serif"></h5>
        <div class="text-start p-3 mb-3" style="background:var(--sand);border:1px solid var(--border);border-radius:var(--radius-sm)">
          <div class="fw-semibold" id="genericConfirmName"></div>
          <div class="small text-muted mt-1" id="genericConfirmMeta"></div>
        </div>
        <p class="text-muted small mb-0" id="genericConfirmBody"></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn px-4" id="genericConfirmBtn"></button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ── Generic confirmation modal wiring ─────────────────────────
  // A trigger button supplies its own nearby <form> (found via closest())
  // plus data-* attributes describing what to show; confirming just
  // submits that form, so each action's existing hidden fields (action,
  // *_id, CSRF token) are untouched. Delegated on the document, since
  // these buttons can live inside dynamically-shown panels.
  const genericModalEl = document.getElementById('genericConfirmModal');
  if (!genericModalEl) return;
  document.body.appendChild(genericModalEl); // avoid backdrop z-index issues if nested in a positioned/faded container
  const genericModal = new bootstrap.Modal(genericModalEl);
  const iconWrap    = document.getElementById('genericConfirmIcon');
  const iconInner   = document.getElementById('genericConfirmIconInner');
  const titleEl     = document.getElementById('genericConfirmTitle');
  const nameEl      = document.getElementById('genericConfirmName');
  const metaEl      = document.getElementById('genericConfirmMeta');
  const bodyEl      = document.getElementById('genericConfirmBody');
  const confirmBtn  = document.getElementById('genericConfirmBtn');
  let pendingForm = null;
  let defaultConfirmHtml = '';

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-confirm-action');
    if (!btn) return;
    pendingForm = btn.closest('form');
    if (!pendingForm) return;

    iconWrap.style.background = btn.dataset.iconBg || '#fee2e2';
    iconInner.className = 'bi ' + (btn.dataset.icon || 'bi-question-circle');
    iconInner.style.color = btn.dataset.iconColor || '#a61c1c';
    titleEl.textContent = btn.dataset.title || 'Are you sure?';
    nameEl.textContent = btn.dataset.name || '';
    metaEl.textContent = btn.dataset.meta || '';
    bodyEl.textContent = btn.dataset.body || '';

    confirmBtn.className = 'btn px-4 ' + (btn.dataset.confirmClass || 'btn-danger');
    defaultConfirmHtml = '<i class="bi bi-check-lg me-1"></i>' + (btn.dataset.confirmLabel || 'Confirm');
    confirmBtn.innerHTML = defaultConfirmHtml;
    confirmBtn.disabled = false;

    genericModal.show();
  });

  confirmBtn.addEventListener('click', () => {
    if (!pendingForm) return;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = 'Working…';
    pendingForm.submit();
  });

  genericModalEl.addEventListener('hidden.bs.modal', () => {
    confirmBtn.disabled = false;
    if (defaultConfirmHtml) confirmBtn.innerHTML = defaultConfirmHtml;
    pendingForm = null;
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
