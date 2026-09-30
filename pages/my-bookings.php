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
$new_spot    = input('spot', 'get', '');
$is_package  = input('package', 'get', '');

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

// Booking status colors/icons/labels now come from the shared
// booking_status_meta() helper in helpers.php.

require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
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
      <div class="fw-bold mb-1"><?= $is_package ? 'Package Booked Successfully!' : 'Reservation Placed Successfully!' ?></div>
      <div>Your booking <strong><?= e($new_booking) ?></strong> has been sent to the hotel.
        You'll be notified once it's confirmed.</div>
      <?php if ($is_package): ?>
      <div class="mt-1">Your full itinerary was saved automatically —
        <a href="itineraries.php" class="fw-bold" style="color:inherit;text-decoration:underline">view it in My Itineraries →</a></div>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($new_spot): ?>
  <div class="alert d-flex align-items-start gap-3 mb-4"
       style="border-radius:var(--radius);background:var(--maroon-pale);border:1px solid var(--maroon-light);color:var(--maroon-dark)">
    <i class="bi bi-signpost-split-fill fs-4 flex-shrink-0"></i>
    <div>
      <div class="fw-bold mb-1">Added to your itinerary!</div>
      <div>This hotel is near <strong><?= e($new_spot) ?></strong> — we added it to your trip list.</div>
      <a href="explore.php" class="fw-bold" style="color:var(--maroon-dark)">Open My Itinerary →</a>
    </div>
  </div>
  <?php endif; ?>
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
        $st = booking_status_meta($bk['status']); $bg = $st['bg']; $fg = $st['fg']; $ico = $st['icon']; $label = $st['label'];
      ?>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">

        <!-- Booking header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
             style="background:var(--cream);border-bottom:1px solid var(--border)">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($bk['booking_number']) ?></span>
            <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.22rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <i class="bi <?= $ico ?> me-1"></i><?= $label ?>
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
                <i class="bi bi-box-arrow-in-right me-1 text-maroon"></i>
                <?= date('D, M d Y', strtotime($bk['check_in_date'])) ?>
              </div>
              <div class="small">
                <i class="bi bi-box-arrow-left me-1 text-maroon"></i>
                <?= date('D, M d Y', strtotime($bk['check_out_date'])) ?>
              </div>
              <div class="small text-muted"><?= $bk['nights'] ?> night<?= $bk['nights']!=1?'s':'' ?></div>

              <?php if ($bk['special_requests']): ?>
              <div class="mt-2 small text-muted fst-italic"><i class="bi bi-chat-left-text me-1"></i><?= e($bk['special_requests']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Total -->
            <div class="col-sm-3 text-sm-end">
              <div class="small text-muted fw-600 mb-1">Total</div>
              <div class="fw-bold fs-5" style="color:var(--maroon-dark)">₱<?= number_format($bk['total_amount'],2) ?></div>

              <?php if ($bk['status'] === 'checked_in'): ?>
              <div class="mt-2">
                <span class="badge p-2" style="background:var(--maroon-mid);font-size:.82rem">
                  <i class="bi bi-door-open-fill me-1"></i>Enjoy your stay!
                </span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Action footer -->
        <?php if (in_array($bk['status'], ['pending','confirmed'])): ?>
        <div class="px-3 pb-3">
          <form method="POST" action="<?= APP_URL ?>/api/cancel-booking.php">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
            <button type="button" class="btn btn-sm btn-outline-danger js-confirm-action" style="border-radius:var(--radius-pill)"
              data-icon="bi-x-circle" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
              data-title="Cancel this reservation?" data-name="<?= e($bk['hotel_name']) ?>"
              data-meta="<?= e(date('D, M d Y', strtotime($bk['check_in_date']))) ?> \u2013 <?= e(date('D, M d Y', strtotime($bk['check_out_date']))) ?>"
              data-body="Your room won't be held for these dates anymore. This can't be undone."
              data-confirm-label="Yes, cancel" data-confirm-class="btn-danger">
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
