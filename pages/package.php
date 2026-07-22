<?php
ob_start();
// ============================================================
// iEXPLORE LAGUNA — Package Detail & Booking
// pages/package.php?id=PACKAGE_ID
// ============================================================
$page_title  = 'Package';
$active_page = 'packages';
require_once __DIR__ . '/../includes/header.php';

$package_id = (int) input('id', 'get', 0);
if (!$package_id) { header('Location: ' . APP_URL . '/pages/packages.php'); exit; }

$pkg = db_fetch_one(
    "SELECT p.*, c.name AS city_name,
            h.name AS hotel_name, h.address AS hotel_address, h.star_rating, h.phone AS hotel_phone,
            h.latitude AS hotel_latitude, h.longitude AS hotel_longitude,
            r.room_type, r.price_per_night, r.capacity, r.room_count
     FROM packages p
     LEFT JOIN cities c       ON p.city_id = c.id
     LEFT JOIN hotels h       ON p.hotel_id = h.id
     LEFT JOIN hotel_rooms r  ON p.room_id  = r.id
     WHERE p.id = ? AND p.is_active = 1",
    [$package_id]
);
if (!$pkg) { header('Location: ' . APP_URL . '/pages/packages.php'); exit; }

$spot_rows = db_fetch_all(
    "SELECT ps.day_number, s.id, s.name, s.category, s.entrance_fee, s.description,
            s.latitude, s.longitude, c.name AS city_name
     FROM package_spots ps
     JOIN tourist_spots s ON ps.spot_id = s.id
     JOIN cities c ON s.city_id = c.id
     WHERE ps.package_id = ?
     ORDER BY ps.day_number, ps.sort_order",
    [$package_id]
);

$byDay = [];
foreach ($spot_rows as $sp) { $byDay[$sp['day_number']][] = $sp; }
ksort($byDay);
?>

<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-start gap-3 flex-wrap">
      <div style="width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;flex-shrink:0">
        <?= $pkg['cover_emoji'] ?>
      </div>
      <div class="flex-grow-1">
        <div class="mb-1">
          <span class="badge" style="background:rgba(255,255,255,.18)">
            <?= $pkg['scope']==='single_city' ? '📍 '.e($pkg['city_name']) : '🗺️ Multi-City' ?>
          </span>
          <span class="badge" style="background:rgba(255,255,255,.18)"><?= (int)$pkg['days'] ?> days</span>
        </div>
        <h1 class="mb-1 fs-3" style="font-family:'Playfair Display',serif"><?= e($pkg['title']) ?></h1>
        <?php if ($pkg['description']): ?>
        <p class="mb-0 opacity-85 small"><?= e($pkg['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Day-by-day itinerary ─────────────────────────────── -->
  <div class="col-lg-7">
    <h6 class="fw-bold mb-3 pb-2" style="color:var(--green-dark);border-bottom:2px solid var(--green-pale);font-family:'Playfair Display',serif">
      What's Included
    </h6>

    <!-- Route map -->
    <?php if ($pkg['hotel_latitude'] || !empty(array_filter($spot_rows, fn($s) => $s['latitude']))): ?>
    <div id="package-map" style="height:280px;border-radius:var(--radius-sm);overflow:hidden;margin-bottom:1rem"></div>
    <?php endif; ?>

    <!-- Hotel -->
    <div class="p-3 mb-3 d-flex align-items-center gap-3" style="background:#f7dde1;border-radius:var(--radius-sm)">
      <div style="width:48px;height:48px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0">🏨</div>
      <div class="flex-grow-1">
        <div class="fw-bold" style="font-size:.92rem"><?= e($pkg['hotel_name'] ?? 'Hotel') ?>
          <span style="color:var(--sand-dark);font-size:.75rem"><?= str_repeat('★',(int)$pkg['star_rating']) ?></span>
        </div>
        <div class="small text-muted"><?= e($pkg['room_type'] ?? '') ?> · Sleeps <?= (int)$pkg['capacity'] ?> · <?= (int)$pkg['days']-1 ?> night<?= ($pkg['days']-1)!=1?'s':'' ?></div>
      </div>
    </div>

    <?php if (empty($byDay)): ?>
      <p class="text-muted small">Itinerary details coming soon.</p>
    <?php else: ?>
      <?php foreach ($byDay as $dayNum => $spots): ?>
      <div class="mb-3">
        <div class="fw-bold mb-2" style="font-family:'Playfair Display',serif;color:var(--green-dark)">Day <?= $dayNum ?></div>
        <?php foreach ($spots as $sp): ?>
        <div class="d-flex align-items-center gap-3 p-2 mb-1" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <div style="width:36px;height:36px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0">📍</div>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-bold" style="font-size:.86rem"><?= e($sp['name']) ?></div>
            <div class="small text-muted"><?= e($sp['city_name']) ?></div>
          </div>
          <div class="small text-muted flex-shrink-0"><?= $sp['entrance_fee']>0 ? '₱'.number_format($sp['entrance_fee'],0) : 'Free' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ── Booking sidebar ─────────────────────────────────────── -->
  <div class="col-lg-5">
    <div style="position:sticky;top:80px">
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">
        <div class="p-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
          <div class="d-flex align-items-baseline justify-content-between">
            <h6 class="mb-0" style="font-family:'Playfair Display',serif">Book This Package</h6>
            <span class="fw-bold fs-4">₱<?= number_format($pkg['estimated_price'],0) ?></span>
          </div>
          <div class="small opacity-75">estimated total for this trip</div>
        </div>

        <div class="p-3">
          <div class="mb-3">
            <label class="form-label small fw-600">Start Date (Check-in)</label>
            <input type="date" class="form-control form-control-sm" id="start-date" min="<?= date('Y-m-d') ?>" onchange="updateDates()">
          </div>
          <div class="mb-3 p-2 small" style="background:var(--cream);border-radius:var(--radius-sm)" id="date-summary">
            Select a start date to see your full itinerary dates.
          </div>

          <div class="mb-3">
            <label class="form-label small fw-600">Guests</label>
            <input type="number" class="form-control form-control-sm" id="guests-count" min="1" value="2" max="<?= (int)$pkg['capacity'] ?: 4 ?>">
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
            <label class="form-label small fw-600">Payment Method</label>
            <select class="form-select form-select-sm" id="payment-method">
              <option value="cash_on_checkin">💵 Cash on Check-in</option>
              <option value="gcash">📱 GCash</option>
              <option value="maya">📱 Maya</option>
            </select>
          </div>

          <button class="btn w-100" id="book-pkg-btn"
                  style="background:var(--green-mid);color:#fff;border-radius:var(--radius-sm)"
                  onclick="bookPackage(<?= $package_id ?>)" disabled>
            <i class="bi bi-box-seam-fill me-2"></i>Book This Package
          </button>

          <?php if (!is_logged_in()): ?>
          <p class="text-center small text-muted mt-2 mb-0">
            <a href="login.php?redirect=<?= urlencode(APP_URL.'/pages/package.php?id='.$package_id) ?>" class="fw-bold text-green">Log in</a> to book
          </p>
          <?php else: ?>
          <p class="text-center small text-muted mt-2 mb-0">
            Your hotel &amp; itinerary will be booked and saved automatically.
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
const PKG_DAYS = <?= (int) $pkg['days'] ?>;

function updateDates() {
  const startEl = document.getElementById('start-date');
  const btn = document.getElementById('book-pkg-btn');
  const summary = document.getElementById('date-summary');
  if (!startEl.value) { btn.disabled = true; return; }

  const start = new Date(startEl.value);
  const end = new Date(start);
  end.setDate(end.getDate() + (PKG_DAYS - 1));

  const fmt = d => d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
  summary.innerHTML = `<i class="bi bi-calendar-check me-1"></i><strong>${fmt(start)}</strong> → <strong>${fmt(end)}</strong> (${PKG_DAYS} day${PKG_DAYS!==1?'s':''})`;
  btn.disabled = false;
}

function bookPackage(packageId) {
  if (!<?= is_logged_in() ? 'true' : 'false' ?>) {
    window.location.href = '<?= APP_URL ?>/pages/login.php?redirect=<?= urlencode(APP_URL.'/pages/package.php?id='.$package_id) ?>';
    return;
  }
  const startDate = document.getElementById('start-date').value;
  if (!startDate) { IExploreApp.toast('Please select a start date.', 'warning'); return; }

  const guestName = document.getElementById('guest-name').value.trim();
  if (!guestName) { IExploreApp.toast('Please enter the guest name.', 'warning'); return; }

  const btn = document.getElementById('book-pkg-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Booking…';

  fetch('<?= APP_URL ?>/api/packages.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      package_id:    packageId,
      start_date:    startDate,
      guests:        document.getElementById('guests-count').value,
      guest_name:    guestName,
      guest_phone:   document.getElementById('guest-phone').value,
      payment_method: document.getElementById('payment-method').value,
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = '<?= APP_URL ?>/pages/my-bookings.php?new=' + data.booking_number + '&package=1';
    } else {
      IExploreApp.toast(data.message || 'Failed to book package.', 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-box-seam-fill me-2"></i>Book This Package';
    }
  })
  .catch(() => {
    IExploreApp.toast('Connection error. Please try again.', 'danger');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-box-seam-fill me-2"></i>Book This Package';
  });
}

<?php
// Build an ordered list of map points: hotel first, then each spot in
// day/sort order — mirrors how a traveler would actually move through the trip.
$mapPoints = [];
if ($pkg['hotel_latitude'] && $pkg['hotel_longitude']) {
    $mapPoints[] = [
        'lat' => (float) $pkg['hotel_latitude'], 'lng' => (float) $pkg['hotel_longitude'],
        'label' => $pkg['hotel_name'], 'type' => 'hotel', 'day' => 0,
    ];
}
foreach ($spot_rows as $sp) {
    if ($sp['latitude'] && $sp['longitude']) {
        $mapPoints[] = [
            'lat' => (float) $sp['latitude'], 'lng' => (float) $sp['longitude'],
            'label' => $sp['name'], 'type' => 'spot', 'day' => (int) $sp['day_number'],
        ];
    }
}
?>
<?php if (!empty($mapPoints)): ?>
// ── Package route map ────────────────────────────────────────
// Wrapped in DOMContentLoaded: Leaflet's JS loads at the bottom of the
// page (in footer.php), which comes AFTER this script block in document
// order — so we must wait until everything has finished loading.
document.addEventListener('DOMContentLoaded', function() {
  const pkgPoints = <?= json_encode($mapPoints) ?>;
  const pkgMap = L.map('package-map', { zoomControl: true, scrollWheelZoom: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 18
  }).addTo(pkgMap);

  const pkgLatLngs = [];
  pkgPoints.forEach((pt, i) => {
    pkgLatLngs.push([pt.lat, pt.lng]);
    const isHotel = pt.type === 'hotel';
    const bg = isHotel ? '#8e2434' : 'var(--green-mid)';
    const label = isHotel ? '🏨' : String(pt.day);
    const icon = L.divIcon({
      className: '',
      html: `<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;
               background:${bg};border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
               transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
               <span style="transform:rotate(45deg);font-size:${isHotel?'13px':'12px'};color:#fff;font-weight:700">${label}</span></div>`,
      iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -30],
    });
    L.marker([pt.lat, pt.lng], { icon })
      .addTo(pkgMap)
      .bindPopup(`<strong>${pt.label}</strong>${isHotel ? ' (hotel)' : ' — Day ' + pt.day}`);
  });

  if (pkgLatLngs.length > 1) {
    L.polyline(pkgLatLngs, { color: 'var(--green-mid)', weight: 3, opacity: 0.6, dashArray: '6,8' }).addTo(pkgMap);
  }
  pkgMap.fitBounds(pkgLatLngs, { padding: [30, 30] });
  setTimeout(() => pkgMap.invalidateSize(), 200);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
