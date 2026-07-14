<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
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
            db_execute(
                "INSERT INTO hotel_rooms (hotel_id, owner_id, room_type, description, capacity, bed_type, room_count, price_per_night)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$hid, $u['id'], $rtype, $desc, $cap ?: 2, $bed, $count ?: 1, $price]
            );
            $_SESSION['flash']['success'] = "Room type \"{$rtype}\" added!";
        }
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#rooms'); exit;
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
                    'confirmed'   => ['Booking Confirmed! 🎉', "Your booking #{$booking['booking_number']} has been confirmed by the hotel."],
                    'checked_in'  => ['Checked In ✅', "You've been checked in for booking #{$booking['booking_number']}. Enjoy your stay!"],
                    'checked_out' => ['Checked Out 👋', "Thanks for staying with us! Booking #{$booking['booking_number']} is now complete."],
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
        db_execute(
            "UPDATE hotels SET description=?, address=?, phone=?,
                               star_rating=?, price_min=?, price_max=?
             WHERE id = ?",
            [
                input('description','post',''),
                input('address','post',''),
                input('phone','post',''),
                (int) input('star_rating','post',3),
                (float) input('price_min','post',0) ?: null,
                (float) input('price_max','post',0) ?: null,
                $hid
            ]
        );
        $_SESSION['flash']['success'] = 'Hotel info updated.';
        header('Location: ' . APP_URL . '/pages/hotel-dashboard.php#settings'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$rooms = db_fetch_all(
    "SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY room_type",
    [$hid]
);

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

$statusColors = [
    'pending'     => ['#fff3cd','#856404','⏳'],
    'confirmed'   => ['#d1ecf1','#0c5460','✅'],
    'checked_in'  => ['#d4edda','#155724','🛎️'],
    'checked_out' => ['#e2e3e5','#383d41','✔️'],
    'cancelled'   => ['#f8d7da','#721c24','❌'],
    'no_show'     => ['#f8d7da','#721c24','🚫'],
];

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
<section class="py-3" style="background:linear-gradient(135deg,#534AB7,#7b6ff0);color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem">🏨</div>
        <div>
          <h1 class="mb-0 fs-4" style="font-family:'Playfair Display',serif"><?= e($hotel['name']) ?></h1>
          <p class="mb-0 small opacity-75">
            <i class="bi bi-building me-1"></i>Hotel Dashboard
            <?php if (!$hotel['is_verified']): ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem">⏳ Pending verification</span>
            <?php else: ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem">✅ Verified</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
      <?php if ($pending_count > 0): ?>
        <a href="#bookings" class="btn btn-sm"
           style="background:#fff;color:#534AB7;font-weight:700;border-radius:var(--radius-pill)">
          <i class="bi bi-bell-fill me-1"></i><?= $pending_count ?> New Booking<?= $pending_count > 1 ? 's' : '' ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <?php
    $stats = [
      ['📅', 'Today\'s Bookings', $today_bookings, '#dbeafe','#1e40af'],
      ['⏳', 'Pending Bookings', $pending_count,  '#fef3c7','#92400e'],
      ['🛏️', 'Room Types',       $total_rooms,    '#e6e0fa','#3d3480'],
      ['💰', 'Total Revenue',    '₱'.number_format($total_revenue,2), '#f3e8ff','#6b21a8'],
    ];
    foreach ($stats as [$ico,$lbl,$val,$bg,$fg]): ?>
    <div class="col-6 col-lg-3">
      <div class="p-3 h-100" style="background:<?= $bg ?>;border-radius:var(--radius);border:1.5px solid <?= $fg ?>22">
        <div style="font-size:1.6rem;margin-bottom:.3rem"><?= $ico ?></div>
        <div style="font-size:1.4rem;font-weight:800;color:<?= $fg ?>"><?= $val ?></div>
        <div style="font-size:.78rem;color:<?= $fg ?>;opacity:.8"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Nav tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--border)">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#bookings" style="font-weight:600">
        <i class="bi bi-calendar-check me-1"></i>Bookings
        <?php if ($pending_count): ?>
          <span class="badge rounded-pill ms-1" style="background:#534AB7;font-size:.7rem"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#rooms" style="font-weight:600">
        <i class="bi bi-door-open me-1"></i>Room Types
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
        <div class="d-flex flex-column gap-3">
          <?php foreach ($bookings as $bk):
            [$bg,$fg,$ico] = $statusColors[$bk['status']] ?? ['#f1f5f9','#334155','📋'];
          ?>
          <div class="p-0 rounded overflow-hidden" style="border:1.5px solid var(--border);background:#fff">
            <!-- Booking header -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
                 style="background:var(--cream);border-bottom:1px solid var(--border)">
              <div>
                <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($bk['booking_number']) ?></span>
                <span class="ms-2" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700">
                  <?= $ico ?> <?= ucfirst(str_replace('_',' ',$bk['status'])) ?>
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
                    <i class="bi bi-box-arrow-in-right me-1 text-green"></i>
                    <?= date('M d, Y', strtotime($bk['check_in_date'])) ?>
                  </div>
                  <div class="small">
                    <i class="bi bi-box-arrow-left me-1 text-green"></i>
                    <?= date('M d, Y', strtotime($bk['check_out_date'])) ?>
                  </div>
                  <div class="small text-muted"><?= $bk['nights'] ?> night<?= $bk['nights']!=1?'s':'' ?></div>
                  <?php if ($bk['special_requests']): ?>
                  <div class="small text-muted mt-1 fst-italic">📝 <?= e($bk['special_requests']) ?></div>
                  <?php endif; ?>
                </div>

                <!-- Total + Actions -->
                <div class="col-sm-4 d-flex flex-column align-items-end justify-content-between">
                  <div class="text-end">
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold fs-5" style="color:var(--green-dark)">₱<?= number_format($bk['total_amount'],2) ?></div>
                  </div>

                  <!-- Status update buttons -->
                  <?php if ($bk['status'] === 'pending'): ?>
                  <div class="d-flex gap-2 mt-2">
                    <form method="POST">
                      <input type="hidden" name="action"     value="update_booking">
                      <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                      <input type="hidden" name="status"     value="confirmed">
                      <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill)">
                        <i class="bi bi-check me-1"></i>Accept
                      </button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="action"     value="update_booking">
                      <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                      <input type="hidden" name="status"     value="cancelled">
                      <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
                        Decline
                      </button>
                    </form>
                  </div>
                  <?php elseif ($bk['status'] === 'confirmed'): ?>
                  <form method="POST" class="mt-2">
                    <input type="hidden" name="action"     value="update_booking">
                    <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                    <input type="hidden" name="status"     value="checked_in">
                    <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--green-dark);font-weight:600;border-radius:var(--radius-pill)">
                      <i class="bi bi-door-open me-1"></i>Check In
                    </button>
                  </form>
                  <?php elseif ($bk['status'] === 'checked_in'): ?>
                  <form method="POST" class="mt-2">
                    <input type="hidden" name="action"     value="update_booking">
                    <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                    <input type="hidden" name="status"     value="checked_out">
                    <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill)">
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
      <?php endif; ?>
    </div>

    <!-- ── ROOM TYPES TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="rooms">
      <div class="row g-4">

        <!-- Add room form -->
        <div class="col-lg-4">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--green-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-plus-circle me-2" style="color:#534AB7"></i>Add Room Type
            </h6>
            <form method="POST">
              <input type="hidden" name="action" value="add_room">
              <div class="mb-3">
                <label class="form-label">Room Type Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="room_type" placeholder="e.g. Deluxe Twin" required>
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
            <?php foreach ($rooms as $r): ?>
            <div class="d-flex align-items-center gap-3 p-3"
                 style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                        opacity:<?= $r['is_available'] ? '1' : '.55' ?>">
              <div style="width:44px;height:44px;background:#e6e0fa;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
                🛏️
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-bold" style="font-size:.93rem"><?= e($r['room_type']) ?></div>
                <div class="text-muted small">
                  <?= e($r['bed_type'] ?: '—') ?> · Sleeps <?= $r['capacity'] ?> ·
                  <?= $r['room_count'] ?> room<?= $r['room_count']!=1?'s':'' ?>
                </div>
              </div>
              <div class="fw-bold" style="color:#534AB7;font-size:1rem;white-space:nowrap">
                ₱<?= number_format($r['price_per_night'],2) ?>/night
              </div>
              <div class="d-flex gap-2 flex-shrink-0">
                <!-- Toggle availability -->
                <form method="POST">
                  <input type="hidden" name="action"   value="toggle_room">
                  <input type="hidden" name="room_id"  value="<?= $r['id'] ?>">
                  <button class="btn btn-sm <?= $r['is_available'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                          style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .75rem"
                          title="<?= $r['is_available'] ? 'Hide room type' : 'Show room type' ?>">
                    <?= $r['is_available'] ? '🙈 Hide' : '👁️ Show' ?>
                  </button>
                </form>
                <!-- Delete -->
                <form method="POST" onsubmit="return confirm('Delete this room type?')">
                  <input type="hidden" name="action"  value="delete_room">
                  <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"
                          style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .6rem">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
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
            <h6 class="fw-bold mb-3" style="color:var(--green-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-pencil-square me-2"></i>Edit Hotel Info
            </h6>
            <form method="POST">
              <input type="hidden" name="action" value="update_hotel">
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
