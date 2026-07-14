<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Tourist: My Bookings Page
// pages/my-bookings.php
// ============================================================
$page_title  = 'My Bookings';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();

$new_booking = input('new', 'get', '');

$bookings = db_fetch_all(
    "SELECT b.*, h.name AS hotel_name, h.id AS hotel_id,
            c.name AS city_name, r.room_type
     FROM bookings b
     JOIN hotels h      ON b.hotel_id = h.id
     JOIN cities c       ON h.city_id  = c.id
     JOIN hotel_rooms r ON b.room_id  = r.id
     WHERE b.tourist_id = ?
     ORDER BY b.created_at DESC",
    [$u['id']]
);

$statusColors = [
    'pending'     => ['#fff3cd','#856404','⏳','Pending'],
    'confirmed'   => ['#d1ecf1','#0c5460','✅','Confirmed'],
    'checked_in'  => ['#d4edda','#155724','🛎️','Checked In'],
    'checked_out' => ['#e2e3e5','#383d41','✔️','Checked Out'],
    'cancelled'   => ['#f8d7da','#721c24','❌','Cancelled'],
    'no_show'     => ['#f8d7da','#721c24','🚫','No Show'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,#5c1620,#8e2434);color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-calendar-check-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">My Bookings</h1>
        <p class="mb-0 small opacity-75"><?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?> total</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <?php if ($new_booking): ?>
  <div class="alert alert-success d-flex align-items-start gap-3 mb-4" style="border-radius:var(--radius)">
    <i class="bi bi-check-circle-fill fs-4 flex-shrink-0"></i>
    <div>
      <div class="fw-bold mb-1">Reservation Placed Successfully! 🎉</div>
      <div>Your booking <strong><?= e($new_booking) ?></strong> has been sent to the hotel.
        You'll be notified once it's confirmed.</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="text-center py-5">
      <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
      <h5>No bookings yet</h5>
      <p class="text-muted">Browse hotels and reserve a room for your trip!</p>
      <a href="hotels.php" class="btn btn-primary-app">Browse Hotels</a>
    </div>
  <?php else: ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($bookings as $bk):
        [$bg,$fg,$ico,$label] = $statusColors[$bk['status']] ?? ['#f1f5f9','#334155','📋','Unknown'];
      ?>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">

        <!-- Booking header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
             style="background:var(--cream);border-bottom:1px solid var(--border)">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($bk['booking_number']) ?></span>
            <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.22rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <?= $ico ?> <?= $label ?>
            </span>
          </div>
          <span class="text-muted small"><?= date('M d, Y g:i A', strtotime($bk['created_at'])) ?></span>
        </div>

        <div class="p-3">
          <div class="row g-3 align-items-start">
            <!-- Hotel + room -->
            <div class="col-sm-5">
              <div class="small text-muted fw-600 mb-1">Hotel</div>
              <div class="fw-bold"><?= e($bk['hotel_name']) ?></div>
              <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($bk['city_name']) ?></div>
              <div class="small text-muted mt-1"><i class="bi bi-door-open me-1"></i><?= e($bk['room_type']) ?> · <?= $bk['guests_count'] ?> guest<?= $bk['guests_count']!=1?'s':'' ?></div>
            </div>

            <!-- Stay dates -->
            <div class="col-sm-4">
              <div class="small text-muted fw-600 mb-1">Stay</div>
              <div class="small">
                <i class="bi bi-box-arrow-in-right me-1 text-green"></i>
                <?= date('D, M d Y', strtotime($bk['check_in_date'])) ?>
              </div>
              <div class="small">
                <i class="bi bi-box-arrow-left me-1 text-green"></i>
                <?= date('D, M d Y', strtotime($bk['check_out_date'])) ?>
              </div>
              <div class="small text-muted"><?= $bk['nights'] ?> night<?= $bk['nights']!=1?'s':'' ?></div>

              <?php if ($bk['special_requests']): ?>
              <div class="mt-2 small text-muted fst-italic">📝 <?= e($bk['special_requests']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Total -->
            <div class="col-sm-3 text-sm-end">
              <div class="small text-muted fw-600 mb-1">Total</div>
              <div class="fw-bold fs-5" style="color:var(--green-dark)">₱<?= number_format($bk['total_amount'],2) ?></div>

              <?php if ($bk['status'] === 'checked_in'): ?>
              <div class="mt-2">
                <span class="badge p-2" style="background:var(--green-mid);font-size:.82rem">
                  🛎️ Enjoy your stay!
                </span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Action footer -->
        <?php if (in_array($bk['status'], ['pending','confirmed'])): ?>
        <div class="px-3 pb-3">
          <form method="POST" action="<?= APP_URL ?>/api/cancel-booking.php"
                onsubmit="return confirm('Cancel this reservation?')">
            <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
              <i class="bi bi-x-circle me-1"></i>Cancel Reservation
            </button>
          </form>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
