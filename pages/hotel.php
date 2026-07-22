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

// Photo gallery (table is created lazily by the admin hotel-photos page;
// guard so this page still works before that table has ever been created)
$hotel_photos = [];
try {
    $hotel_photos = db_fetch_all(
        "SELECT url, caption, photo_type, sort_order
         FROM hotel_photos WHERE hotel_id = ? ORDER BY photo_type='main' DESC, sort_order ASC, id ASC",
        [$hotel_id]
    );
} catch (Throwable $e) {
    $hotel_photos = [];
}
$hero_hotel_photo = $hotel_photos[0] ?? null;

// Reviews
ensure_hotel_reviews_table();
$review_stats = db_fetch_one(
    "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating
     FROM hotel_reviews WHERE hotel_id = ? AND is_approved = 1",
    [$hotel_id]
);
$reviews = db_fetch_all(
    "SELECT r.id, r.rating, r.title, r.body, r.stayed_on, r.created_at,
            u.name AS user_name
     FROM hotel_reviews r
     JOIN users u ON r.user_id = u.id
     WHERE r.hotel_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC
     LIMIT 5",
    [$hotel_id]
);
?>

<!-- Hotel hero -->
<section class="py-4" style="background:linear-gradient(135deg,#5c1620,#8e2434);color:#fff">
  <div class="container">
    <div class="d-flex align-items-start gap-4 flex-wrap">
      <div style="width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:2.5rem;flex-shrink:0">
        <?php if (!empty($hotel['cover_url'])): ?>
        <img src="<?= e($hotel['cover_url']) ?>" alt="<?= e($hotel['name']) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
        🏨
        <?php endif; ?>
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

<!-- ── Photo Gallery ─────────────────────────────────────────── -->
<?php if (!empty($hotel_photos)): ?>
<section class="py-4">
<div class="container">
  <div class="hotel-cover-photo" onclick="openHotelLightbox(0)">
    <div class="hotel-cover-bg" style="background-image:url('<?= e($hero_hotel_photo['url']) ?>')"></div>
    <img src="<?= e($hero_hotel_photo['url']) ?>" alt="<?= e($hotel['name']) ?>" loading="eager">
    <div class="hotel-gallery-overlay"><i class="bi bi-arrows-fullscreen"></i></div>
  </div>

  <?php if (count($hotel_photos) > 1): ?>
  <div class="hotel-gallery-grid mt-3">
    <?php foreach ($hotel_photos as $i => $ph): ?>
      <?php if ($i === 0) continue; // already shown as the cover photo ?>
    <div class="hotel-gallery-tile" onclick="openHotelLightbox(<?= $i ?>)">
      <img src="<?= e($ph['url']) ?>" alt="<?= e($ph['caption'] ?? '') ?>" loading="lazy">
      <?php if ($ph['caption']): ?>
      <div class="hotel-gallery-tile-caption"><?= e($ph['caption']) ?></div>
      <?php endif; ?>
      <div class="hotel-gallery-overlay"><i class="bi bi-zoom-in"></i></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</section>

<!-- ── Lightbox ──────────────────────────────────────────────── -->
<div id="hotel-lightbox" class="hotel-lightbox" onclick="closeHotelLightbox()">
  <button class="hotel-lightbox-close" onclick="closeHotelLightbox()"><i class="bi bi-x-lg"></i></button>
  <button class="hotel-lightbox-prev" onclick="event.stopPropagation();moveHotelLightbox(-1)"><i class="bi bi-chevron-left"></i></button>
  <div class="hotel-lightbox-content" onclick="event.stopPropagation()">
    <img id="hotel-lightbox-img" src="" alt="">
    <p id="hotel-lightbox-caption" class="hotel-lightbox-caption"></p>
  </div>
  <button class="hotel-lightbox-next" onclick="event.stopPropagation();moveHotelLightbox(1)"><i class="bi bi-chevron-right"></i></button>
</div>
<?php endif; ?>

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

    <!-- Location mini-map -->
    <?php if ($hotel['latitude'] && $hotel['longitude']): ?>
    <div class="mt-4">
      <h6 class="fw-bold mb-2 pb-2" style="color:var(--green-dark);border-bottom:2px solid var(--green-pale);font-family:'Playfair Display',serif">
        <i class="bi bi-pin-map-fill me-2" style="color:#8e2434"></i>Location
      </h6>
      <div id="mini-map" style="height:220px;border-radius:10px;overflow:hidden"></div>
      <div class="mt-2 small text-muted">
        <i class="bi bi-geo-alt me-1"></i><?= e($hotel['address'] ?: $hotel['city_name']) ?>, Laguna
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Reviews ─────────────────────────────────────────── -->
    <div class="mt-4" id="reviews-section">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2"
           style="border-bottom:2px solid #f7dde1">
        <h6 class="fw-bold mb-0" style="color:#8e2434;font-family:'Playfair Display',serif;font-size:1rem">
          <i class="bi bi-chat-quote-fill me-2"></i>Guest Reviews
          <?php if ($review_stats['total'] > 0): ?>
            <span style="font-size:.85rem;font-weight:400;color:var(--text-muted)">
              (<?= (int)$review_stats['total'] ?>)
            </span>
          <?php endif; ?>
        </h6>
        <?php if (is_logged_in()): ?>
        <button class="btn btn-sm" id="write-hotel-review-btn" style="background:#8e2434;color:#fff;border-radius:var(--radius-pill)">
          <i class="bi bi-pencil me-1"></i>Write a Review
        </button>
        <?php else: ?>
        <a href="<?= APP_URL ?>/pages/login.php?redirect=<?= urlencode(APP_URL.'/pages/hotel.php?id='.$hotel_id) ?>"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-person me-1"></i>Log in to Review
        </a>
        <?php endif; ?>
      </div>

      <!-- Rating summary -->
      <?php if ($review_stats['total'] > 0): ?>
      <div class="d-flex align-items-center gap-3 mb-4">
        <span style="font-size:2rem;font-weight:700;color:#8e2434;font-family:'Playfair Display',serif">
          <?= number_format((float)$review_stats['avg_rating'], 1) ?>
        </span>
        <div class="d-flex flex-column">
          <div class="d-flex gap-1 mb-1">
            <?php
            $avg = round((float)$review_stats['avg_rating'] * 2) / 2;
            for ($i = 1; $i <= 5; $i++) {
              if ($avg >= $i) echo '<i class="bi bi-star-fill" style="color:var(--sand-dark);font-size:1rem"></i>';
              elseif ($avg >= $i - 0.5) echo '<i class="bi bi-star-half" style="color:var(--sand-dark);font-size:1rem"></i>';
              else echo '<i class="bi bi-star" style="color:#ccc;font-size:1rem"></i>';
            }
            ?>
          </div>
          <span class="small text-muted"><?= (int)$review_stats['total'] ?> review<?= $review_stats['total'] != 1 ? 's' : '' ?></span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Write review form (hidden by default) -->
      <?php if (is_logged_in()): ?>
      <div id="hotel-review-form-wrap" class="hotel-review-form-wrap d-none mb-4">
        <h6 class="fw-bold mb-3" style="color:#8e2434">Share Your Stay</h6>

        <div class="mb-3">
          <label class="form-label">Your Rating</label>
          <div class="hotel-star-picker" id="hotel-star-picker">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star hotel-star-pick" data-val="<?= $i ?>"></i>
            <?php endfor; ?>
          </div>
          <input type="hidden" id="hotel-review-rating" value="0">
        </div>

        <div class="mb-2">
          <label class="form-label">Title <span class="text-muted">(optional)</span></label>
          <input type="text" class="form-control" id="hotel-review-title" maxlength="120"
                 placeholder="e.g. Comfortable stay, great service!">
        </div>

        <div class="mb-2">
          <label class="form-label">Your Review</label>
          <textarea class="form-control" id="hotel-review-body" rows="4" maxlength="1000"
                    placeholder="How was the room, staff, and overall stay?"></textarea>
          <div class="form-text"><i class="bi bi-shield-check me-1"></i>Inappropriate language is automatically censored.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Date Stayed <span class="text-muted">(optional)</span></label>
          <input type="date" class="form-control" id="hotel-review-stayed" max="<?= date('Y-m-d') ?>">
        </div>

        <div class="d-flex gap-2">
          <button class="btn" id="submit-hotel-review-btn" style="background:#8e2434;color:#fff;border-radius:var(--radius-sm)">
            <i class="bi bi-send me-1"></i>Submit Review
          </button>
          <button class="btn btn-outline-secondary" id="cancel-hotel-review-btn">Cancel</button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Reviews list -->
      <div id="hotel-reviews-list">
        <?php if (empty($reviews)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>
            <p class="mb-0">No reviews yet. Be the first to share your stay!</p>
          </div>
        <?php else: ?>
          <?php foreach ($reviews as $rv): ?>
          <div class="hotel-review-card">
            <div class="d-flex align-items-start gap-3 mb-2">
              <div class="hotel-review-avatar">
                <?= strtoupper(substr($rv['user_name'], 0, 1)) ?>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold small"><?= e($rv['user_name']) ?></div>
                <div class="d-flex align-items-center gap-2" style="font-size:.8rem;color:var(--text-muted)">
                  <span>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="bi <?= $i <= $rv['rating'] ? 'bi-star-fill' : 'bi-star' ?>"
                         style="color:<?= $i <= $rv['rating'] ? 'var(--sand-dark)' : '#ddd' ?>"></i>
                    <?php endfor; ?>
                  </span>
                  <?php if ($rv['stayed_on']): ?>
                    <span>· Stayed <?= date('M Y', strtotime($rv['stayed_on'])) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div style="font-size:.75rem;color:var(--text-muted)">
                <?= date('M j, Y', strtotime($rv['created_at'])) ?>
              </div>
            </div>
            <?php if ($rv['title']): ?>
              <div class="fw-bold small mb-1"><?= e($rv['title']) ?></div>
            <?php endif; ?>
            <?php if ($rv['body']): ?>
              <p class="mb-0 small" style="color:var(--charcoal);line-height:1.6"><?= nl2br(e($rv['body'])) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <?php if ($review_stats['total'] > 5): ?>
          <div class="text-center mt-3">
            <button class="btn btn-outline-secondary btn-sm" id="load-more-hotel-reviews"
                    data-page="2" data-hotel="<?= $hotel_id ?>">
              <i class="bi bi-arrow-down me-1"></i>Load More Reviews
            </button>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
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

/* ── Hotel reviews ────────────────────────────────────────── */
.hotel-review-card {
  border:1px solid var(--border);border-radius:12px;
  padding:1rem 1.25rem;margin-bottom:.75rem;background:#fff;
  transition:box-shadow .2s;
}
.hotel-review-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.08); }
.hotel-review-avatar {
  width:38px;height:38px;border-radius:50%;background:#8e2434;
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:.95rem;flex-shrink:0;
}
.hotel-review-form-wrap {
  background:#faf3f3;border:1px solid var(--border);
  border-radius:12px;padding:1.25rem;
}
.hotel-star-picker { display:flex;gap:.35rem;font-size:1.6rem;cursor:pointer;margin-bottom:.25rem; }
.hotel-star-pick { color:#ccc;transition:color .15s; }

/* ── Hotel photo gallery ──────────────────────────────────── */
.hotel-cover-photo {
  position: relative; width: 100%; height: 380px;
  border-radius: var(--radius, 16px); overflow: hidden; cursor: pointer; background: #000;
}
.hotel-cover-bg {
  position: absolute; inset: 0;
  background-size: cover; background-position: center;
  filter: blur(30px) brightness(.65); transform: scale(1.15);
}
.hotel-cover-photo img {
  position: relative; z-index: 1;
  width: 100%; height: 100%; object-fit: contain;
  transition: transform .3s;
}
.hotel-cover-photo:hover img { transform: scale(1.03); }
.hotel-gallery-overlay {
  position:absolute;inset:0;background:rgba(0,0,0,0);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.5rem;transition:background .25s;
}
.hotel-cover-photo:hover .hotel-gallery-overlay,
.hotel-gallery-tile:hover .hotel-gallery-overlay { background:rgba(0,0,0,.28); }
.hotel-gallery-grid {
  display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;
}
.hotel-gallery-tile {
  position:relative;border-radius:8px;overflow:hidden;
  aspect-ratio:1/1;cursor:pointer;
}
.hotel-gallery-tile img { width:100%;height:100%;object-fit:cover;transition:transform .3s; }
.hotel-gallery-tile:hover img { transform:scale(1.06); }
.hotel-gallery-tile-caption {
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(transparent,rgba(0,0,0,.65));
  color:#fff;font-size:.7rem;padding:.4rem .5rem;
}

/* ── Lightbox ─────────────────────────────────────────────── */
.hotel-lightbox {
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);
  z-index:9999;align-items:center;justify-content:center;
}
.hotel-lightbox.open { display:flex; }
.hotel-lightbox-content { max-width:90vw;max-height:85vh;text-align:center; }
.hotel-lightbox-content img { max-width:100%;max-height:80vh;border-radius:6px;object-fit:contain; }
.hotel-lightbox-caption { color:rgba(255,255,255,.7);font-size:.85rem;margin-top:.5rem; }
.hotel-lightbox-close, .hotel-lightbox-prev, .hotel-lightbox-next {
  position:fixed;background:rgba(255,255,255,.15);border:none;color:#fff;
  border-radius:50%;width:44px;height:44px;display:flex;align-items:center;
  justify-content:center;font-size:1.1rem;cursor:pointer;transition:background .2s;
}
.hotel-lightbox-close:hover,.hotel-lightbox-prev:hover,.hotel-lightbox-next:hover { background:rgba(255,255,255,.3); }
.hotel-lightbox-close { top:1rem;right:1rem; }
.hotel-lightbox-prev  { left:1rem;top:50%;transform:translateY(-50%); }
.hotel-lightbox-next  { right:1rem;top:50%;transform:translateY(-50%); }

@media (max-width: 768px) {
  .hotel-cover-photo { height: 220px; }
  .hotel-gallery-grid { grid-template-columns: repeat(3,1fr); }
}
</style>

<script>
// ── Hotel photo lightbox ────────────────────────────────────────
const HOTEL_PHOTOS = <?= json_encode(array_values($hotel_photos)) ?>;
let hlbIndex = 0;
function openHotelLightbox(i) {
  if (!HOTEL_PHOTOS.length) return;
  hlbIndex = Math.max(0, Math.min(i, HOTEL_PHOTOS.length - 1));
  renderHotelLightbox();
  document.getElementById('hotel-lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeHotelLightbox() {
  document.getElementById('hotel-lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function moveHotelLightbox(dir) {
  hlbIndex = (hlbIndex + dir + HOTEL_PHOTOS.length) % HOTEL_PHOTOS.length;
  renderHotelLightbox();
}
function renderHotelLightbox() {
  const ph = HOTEL_PHOTOS[hlbIndex];
  document.getElementById('hotel-lightbox-img').src = ph.url;
  document.getElementById('hotel-lightbox-caption').textContent = ph.caption || '';
}
document.addEventListener('keydown', e => {
  const lb = document.getElementById('hotel-lightbox');
  if (!lb || !lb.classList.contains('open')) return;
  if (e.key === 'Escape')     closeHotelLightbox();
  if (e.key === 'ArrowLeft')  moveHotelLightbox(-1);
  if (e.key === 'ArrowRight') moveHotelLightbox(1);
});

let selectedRoom = null;

// ── Hotel review form toggle ─────────────────────────────────
const hotelWriteBtn  = document.getElementById('write-hotel-review-btn');
const hotelCancelBtn = document.getElementById('cancel-hotel-review-btn');
const hotelFormWrap  = document.getElementById('hotel-review-form-wrap');

if (hotelWriteBtn) {
  hotelWriteBtn.addEventListener('click', () => {
    hotelFormWrap.classList.toggle('d-none');
    if (!hotelFormWrap.classList.contains('d-none')) {
      hotelFormWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
}
if (hotelCancelBtn) {
  hotelCancelBtn.addEventListener('click', () => hotelFormWrap.classList.add('d-none'));
}

// ── Hotel star picker ────────────────────────────────────────
let hotelPickedRating = 0;
document.querySelectorAll('.hotel-star-pick').forEach(star => {
  star.addEventListener('mouseenter', () => {
    const val = +star.dataset.val;
    document.querySelectorAll('.hotel-star-pick').forEach((s, i) => {
      s.className = 'bi hotel-star-pick ' + (i < val ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < val ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('mouseleave', () => {
    document.querySelectorAll('.hotel-star-pick').forEach((s, i) => {
      s.className = 'bi hotel-star-pick ' + (i < hotelPickedRating ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < hotelPickedRating ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('click', () => {
    hotelPickedRating = +star.dataset.val;
    document.getElementById('hotel-review-rating').value = hotelPickedRating;
  });
});

// ── Submit hotel review ──────────────────────────────────────
const submitHotelReviewBtn = document.getElementById('submit-hotel-review-btn');
if (submitHotelReviewBtn) {
  submitHotelReviewBtn.addEventListener('click', async () => {
    const rating = +document.getElementById('hotel-review-rating').value;
    if (!rating) { IExploreApp.toast('Please select a star rating.', 'warning'); return; }

    submitHotelReviewBtn.disabled = true;
    const res = await fetch('<?= APP_URL ?>/api/hotel-reviews.php?action=review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        hotel_id:   <?= $hotel_id ?>,
        rating,
        title:      document.getElementById('hotel-review-title').value.trim(),
        body:       document.getElementById('hotel-review-body').value.trim(),
        stayed_on:  document.getElementById('hotel-review-stayed').value,
      })
    }).then(r => r.json()).catch(() => null);
    submitHotelReviewBtn.disabled = false;

    if (res && res.success) {
      IExploreApp.toast(res.message || 'Review submitted! Thank you.', 'success');
      hotelFormWrap.classList.add('d-none');
      setTimeout(() => location.reload(), 1200);
    } else {
      IExploreApp.toast((res && res.message) || 'Could not submit review.', 'danger');
    }
  });
}

// ── Load more hotel reviews ───────────────────────────────────
const loadMoreHotelBtn = document.getElementById('load-more-hotel-reviews');
if (loadMoreHotelBtn) {
  loadMoreHotelBtn.addEventListener('click', async () => {
    const page = +loadMoreHotelBtn.dataset.page;
    const hid  = +loadMoreHotelBtn.dataset.hotel;
    loadMoreHotelBtn.disabled = true;
    const res = await fetch(`<?= APP_URL ?>/api/hotel-reviews.php?action=reviews&id=${hid}&page=${page}`).then(r => r.json());
    loadMoreHotelBtn.disabled = false;

    if (res.success) {
      const list = document.getElementById('hotel-reviews-list');
      res.data.reviews.forEach(rv => {
        const stars = Array.from({length:5}, (_,i) =>
          `<i class="bi ${i < rv.rating ? 'bi-star-fill' : 'bi-star'}"
              style="color:${i < rv.rating ? 'var(--sand-dark)' : '#ddd'}"></i>`
        ).join('');
        const div = document.createElement('div');
        div.className = 'hotel-review-card';
        div.innerHTML = `
          <div class="d-flex align-items-start gap-3 mb-2">
            <div class="hotel-review-avatar">${rv.user_name.charAt(0).toUpperCase()}</div>
            <div class="flex-grow-1">
              <div class="fw-bold small">${rv.user_name}</div>
              <div class="d-flex align-items-center gap-2" style="font-size:.8rem;color:var(--text-muted)">
                <span>${stars}</span>
                ${rv.stayed_on ? `<span>· Stayed ${new Date(rv.stayed_on).toLocaleDateString('en-US',{month:'short',year:'numeric'})}</span>` : ''}
              </div>
            </div>
          </div>
          ${rv.title ? `<div class="fw-bold small mb-1">${rv.title}</div>` : ''}
          ${rv.body  ? `<p class="mb-0 small" style="color:var(--charcoal);line-height:1.6">${rv.body}</p>` : ''}
        `;
        list.insertBefore(div, loadMoreHotelBtn.parentElement);
      });

      loadMoreHotelBtn.dataset.page = page + 1;
      if (!res.data.has_more) loadMoreHotelBtn.parentElement.remove();
    }
  });
}

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

<?php if ($hotel['latitude'] && $hotel['longitude']): ?>
// ── Location mini-map ──────────────────────────────────────────
// Wrapped in DOMContentLoaded: Leaflet's JS loads at the bottom of the
// page (in footer.php), which comes AFTER this script block in document
// order — so we must wait until everything has finished loading.
document.addEventListener('DOMContentLoaded', function() {
  const hotelMiniMap = L.map('mini-map', { zoomControl: false, scrollWheelZoom: false })
    .setView([<?= $hotel['latitude'] ?>, <?= $hotel['longitude'] ?>], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 18
  }).addTo(hotelMiniMap);
  const hotelIcon = L.divIcon({
    className: '',
    html: `<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;
             background:#8e2434;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
             transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
             <span style="transform:rotate(45deg);font-size:14px">🏨</span></div>`,
    iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32],
  });
  L.marker([<?= $hotel['latitude'] ?>, <?= $hotel['longitude'] ?>], { icon: hotelIcon })
    .addTo(hotelMiniMap)
    .bindPopup(`<strong><?= e(addslashes($hotel['name'])) ?></strong>`)
    .openPopup();
  setTimeout(() => hotelMiniMap.invalidateSize(), 200);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
