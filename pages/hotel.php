<?php
ob_start();
// ============================================================
// iEXPLORE LAGUNA — Public Hotel Page (Tourist View)
// pages/hotel.php?id=HOTEL_ID
// Tourists browse room types and make a reservation
// ============================================================
$page_title  = 'Hotel';
$active_page = 'hotels';
require_once __DIR__ . '/../includes/header.php';

$hotel_id = (int) input('id', 'get', 0);
if (!$hotel_id) { header('Location: ' . APP_URL . '/pages/hotels.php'); exit; }

$hotel = db_fetch_one(
    "SELECT h.*, c.name AS city_name FROM hotels h
     JOIN cities c ON h.city_id = c.id
     WHERE h.id = ? AND h.is_active = 1 AND h.is_verified = 1",
    [$hotel_id]
);
if (!$hotel) { header('Location: ' . APP_URL . '/pages/hotels.php'); exit; }

$rooms = db_fetch_all(
    "SELECT * FROM hotel_rooms
     WHERE hotel_id = ? AND is_available = 1
     ORDER BY price_per_night ASC",
    [$hotel_id]
);

// The top-rated tourist spot in this hotel's city — used to nudge the
// itinerary builder ("this hotel is near X, add it to your trip?")
$nearby_spot = db_fetch_one(
    "SELECT id, name, entrance_fee, category FROM tourist_spots
     WHERE city_id = ? AND is_active = 1
     ORDER BY rating DESC, name ASC
     LIMIT 1",
    [$hotel['city_id']]
);

$amenities = json_decode($hotel['amenities'] ?? '[]', true) ?: [];
?>

<!-- Hotel hero -->
<section class="py-4" style="background:linear-gradient(135deg,#5c1620,#8e2434);color:#fff">
  <div class="container">
    <div class="d-flex align-items-start gap-4 flex-wrap">
      <div style="width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;flex-shrink:0">
        🏨
      </div>
      <div class="flex-grow-1">
        <div class="mb-1" style="color:var(--sand-dark)">
          <?= str_repeat('★', (int)$hotel['star_rating']) . str_repeat('☆', 5 - (int)$hotel['star_rating']) ?>
        </div>
        <h1 class="mb-1 fs-3" style="font-family:'Playfair Display',serif"><?= e($hotel['name']) ?></h1>
        <div class="d-flex flex-wrap gap-3 opacity-85 small">
          <span><i class="bi bi-geo-alt me-1"></i><?= e($hotel['city_name']) ?></span>
          <?php if ($hotel['address']): ?>
            <span><i class="bi bi-pin-map me-1"></i><?= e($hotel['address']) ?></span>
          <?php endif; ?>
          <?php if ($hotel['price_min'] && $hotel['price_max']): ?>
            <span><i class="bi bi-cash me-1"></i>₱<?= number_format($hotel['price_min'],0) ?>–₱<?= number_format($hotel['price_max'],0) ?>/night</span>
          <?php endif; ?>
        </div>
        <?php if ($hotel['description']): ?>
          <p class="mb-0 mt-2 opacity-80 small"><?= e($hotel['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($hotel['phone']): ?>
      <a href="tel:<?= e($hotel['phone']) ?>" class="btn btn-sm"
         style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:var(--radius-pill)">
        <i class="bi bi-telephone me-1"></i><?= e($hotel['phone']) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Room types ────────────────────────────────────────── -->
  <div class="col-lg-8">
    <?php if (empty($rooms)): ?>
      <div class="text-center py-5">
        <i class="bi bi-door-closed fs-1 text-muted d-block mb-3"></i>
        <h5>No room types available yet</h5>
        <p class="text-muted">Check back soon!</p>
      </div>
    <?php else: ?>
      <h6 class="fw-bold mb-3 pb-2" style="color:var(--green-dark);border-bottom:2px solid var(--green-pale);font-family:'Playfair Display',serif">
        Room Types
      </h6>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($rooms as $r): ?>
        <div class="p-3 room-option" data-room-id="<?= $r['id'] ?>"
             data-room-name="<?= e($r['room_type']) ?>" data-room-price="<?= $r['price_per_night'] ?>"
             style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:border-color .2s">
          <div class="d-flex align-items-center gap-3">
            <div style="width:52px;height:52px;background:#f7dde1;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0">
              🛏️
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-bold" style="font-size:.95rem"><?= e($r['room_type']) ?></div>
              <div class="text-muted small">
                <?= e($r['bed_type'] ?: 'Standard bed') ?> · Sleeps <?= $r['capacity'] ?>
              </div>
              <?php if ($r['description']): ?>
              <div class="text-muted small"><?= e(mb_strimwidth($r['description'],0,80,'…')) ?></div>
              <?php endif; ?>
            </div>
            <div class="text-end flex-shrink-0">
              <div class="fw-bold" style="color:#8e2434;font-size:1.05rem">
                ₱<?= number_format($r['price_per_night'],2) ?>
              </div>
              <div class="small text-muted">per night</div>
              <div class="form-check mt-1">
                <input class="form-check-input room-radio" type="radio" name="room_choice" value="<?= $r['id'] ?>">
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Booking sidebar ─────────────────────────────────────── -->
  <div class="col-lg-4">
    <div style="position:sticky;top:80px">
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">
        <div class="p-3" style="background:linear-gradient(135deg,#5c1620,#8e2434);color:#fff">
          <h6 class="mb-0" style="font-family:'Playfair Display',serif">
            <i class="bi bi-calendar-check me-2" style="color:var(--sand-dark)"></i>Reserve a Room
          </h6>
        </div>

        <div class="p-3">
          <p class="text-muted small mb-3" id="selected-room-msg">Select a room type from the list.</p>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-600">Check-in</label>
              <input type="date" class="form-control form-control-sm" id="checkin-date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-600">Check-out</label>
              <input type="date" class="form-control form-control-sm" id="checkout-date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-600">Guests</label>
            <input type="number" class="form-control form-control-sm" id="guests-count" min="1" value="1">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-600">Guest Name</label>
            <input type="text" class="form-control form-control-sm" id="guest-name"
                   value="<?= is_logged_in() ? e(current_user()['name']) : '' ?>" placeholder="Full name">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Guest Phone</label>
            <input type="text" class="form-control form-control-sm" id="guest-phone" placeholder="09XXXXXXXXX">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Special Requests</label>
            <textarea class="form-control form-control-sm" id="booking-notes" rows="2"
                      placeholder="e.g. Early check-in, extra pillow…" style="resize:none"></textarea>
          </div>

          <?php if ($nearby_spot): ?>
          <div class="mb-3 p-2 d-flex align-items-center gap-2" style="background:#f7dde1;border-radius:var(--radius-sm);font-size:.78rem">
            <i class="bi bi-signpost-split-fill" style="color:#8e2434"></i>
            <span style="color:#8e2434">
              📍 <strong><?= e($hotel['name']) ?></strong> is near <strong><?= e($nearby_spot['name']) ?></strong> —
              we'll add it to your itinerary when you reserve!
            </span>
          </div>
          <?php endif; ?>

          <!-- Payment method -->
          <div class="mb-3">
            <label class="form-label small fw-600">Payment Method</label>
            <select class="form-select form-select-sm" id="payment-method">
              <option value="cash_on_checkin">💵 Cash on Check-in</option>
              <option value="gcash">📱 GCash</option>
              <option value="maya">📱 Maya</option>
            </select>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-1">
            <span id="nights-label">— nights</span>
          </div>
          <div class="d-flex justify-content-between fw-bold mb-3">
            <span>Total</span>
            <span style="color:var(--green-dark)" id="booking-total">₱0.00</span>
          </div>

          <button class="btn w-100" id="book-btn" style="background:#8e2434;color:#fff;border-radius:var(--radius-sm)"
                  onclick="submitBooking(<?= $hotel_id ?>)" disabled>
            <i class="bi bi-calendar-check me-2"></i>Reserve Now
          </button>

          <?php if (!is_logged_in()): ?>
          <p class="text-center small text-muted mt-2 mb-0">
            <a href="login.php?redirect=<?= urlencode(APP_URL.'/pages/hotel.php?id='.$hotel_id) ?>" class="fw-bold text-green">
              Log in</a> to reserve
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<style>
.room-option.selected { border-color:#8e2434 !important; background:#f7dde1 !important; }
</style>

<script>
let selectedRoom = null;

// Data needed to auto-add this hotel (and its nearby spot) to the
// shared "My List" itinerary cart used on explore.php
const HOTEL_CART_ITEM = {
  key: 'hotel-<?= $hotel_id ?>', type: 'hotel', id: <?= $hotel_id ?>,
  name: <?= json_encode($hotel['name']) ?>, city: <?= json_encode($hotel['city_name']) ?>,
  price: <?= (float) ($hotel['price_min'] ?: 0) ?>
};
let NEARBY_SPOT_ITEM = null;
<?php if ($nearby_spot): ?>
NEARBY_SPOT_ITEM = {
  key: 'spot-<?= $nearby_spot['id'] ?>', type: 'spot', id: <?= $nearby_spot['id'] ?>,
  name: <?= json_encode($nearby_spot['name']) ?>, city: <?= json_encode($hotel['city_name']) ?>,
  price: <?= (float) $nearby_spot['entrance_fee'] ?>
};
<?php endif; ?>

function addToItineraryCart(item) {
  let cart = [];
  try { cart = JSON.parse(localStorage.getItem('iexplore_cart') || '[]'); } catch (e) { cart = []; }
  if (!cart.some(i => i.key === item.key)) cart.push(item);
  localStorage.setItem('iexplore_cart', JSON.stringify(cart));
}

document.querySelectorAll('.room-option').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.room-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('.room-radio').checked = true;
    selectedRoom = {
      id:    el.dataset.roomId,
      name:  el.dataset.roomName,
      price: parseFloat(el.dataset.roomPrice),
    };
    document.getElementById('selected-room-msg').textContent = 'Selected: ' + selectedRoom.name;
    recalcTotal();
  });
});

function nightsBetween() {
  const ci = document.getElementById('checkin-date').value;
  const co = document.getElementById('checkout-date').value;
  if (!ci || !co) return 0;
  const d1 = new Date(ci), d2 = new Date(co);
  const diff = Math.round((d2 - d1) / 86400000);
  return diff > 0 ? diff : 0;
}

function recalcTotal() {
  const nights = nightsBetween();
  const btn = document.getElementById('book-btn');
  const label = document.getElementById('nights-label');
  const totalEl = document.getElementById('booking-total');

  label.textContent = nights > 0 ? (nights + ' night' + (nights !== 1 ? 's' : '')) : '— nights';

  if (selectedRoom && nights > 0) {
    const total = selectedRoom.price * nights;
    totalEl.textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    btn.disabled = false;
  } else {
    totalEl.textContent = '₱0.00';
    btn.disabled = true;
  }
}

document.getElementById('checkin-date').addEventListener('change', function() {
  const ci = this.value;
  if (ci) {
    const next = new Date(ci);
    next.setDate(next.getDate() + 1);
    document.getElementById('checkout-date').min = next.toISOString().slice(0,10);
  }
  recalcTotal();
});
document.getElementById('checkout-date').addEventListener('change', recalcTotal);

function submitBooking(hotelId) {
  if (!<?= is_logged_in() ? 'true' : 'false' ?>) {
    window.location.href = '<?= APP_URL ?>/pages/login.php?redirect=<?= urlencode(APP_URL.'/pages/hotel.php?id='.$hotel_id) ?>';
    return;
  }
  if (!selectedRoom) { IExploreApp.toast('Please select a room type.', 'warning'); return; }
  const nights = nightsBetween();
  if (nights < 1) { IExploreApp.toast('Please select valid check-in and check-out dates.', 'warning'); return; }

  const guestName = document.getElementById('guest-name').value.trim();
  if (!guestName) { IExploreApp.toast('Please enter the guest name.', 'warning'); return; }

  const btn = document.getElementById('book-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Reserving…';

  const payload = {
    hotel_id:         hotelId,
    room_id:          parseInt(selectedRoom.id),
    check_in:         document.getElementById('checkin-date').value,
    check_out:        document.getElementById('checkout-date').value,
    guests:            document.getElementById('guests-count').value,
    guest_name:        guestName,
    guest_phone:       document.getElementById('guest-phone').value,
    special_requests:  document.getElementById('booking-notes').value,
    payment_method:    document.getElementById('payment-method').value,
  };

  fetch('<?= APP_URL ?>/api/bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      addToItineraryCart(HOTEL_CART_ITEM);
      let spotQS = '';
      if (NEARBY_SPOT_ITEM) { addToItineraryCart(NEARBY_SPOT_ITEM); spotQS = '&spot=' + encodeURIComponent(NEARBY_SPOT_ITEM.name); }
      window.location.href = '<?= APP_URL ?>/pages/my-bookings.php?new=' + data.booking_number + spotQS;
    } else {
      IExploreApp.toast(data.message || 'Failed to reserve. Please try again.', 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Reserve Now';
    }
  })
  .catch(() => {
    IExploreApp.toast('Connection error. Please try again.', 'danger');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Reserve Now';
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
