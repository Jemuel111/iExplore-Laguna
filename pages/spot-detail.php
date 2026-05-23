<?php
// ============================================================
// IEXPLORE LAGUNA — Spot Detail Page
// pages/spot-detail.php
// Access: pages/spot-detail.php?id=1
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
session_start_safe();

$spot_id = (int) input('id', 'get', 0);

if (!$spot_id) {
    header('Location: ' . APP_URL . '/pages/spots.php');
    exit;
}

// Load spot
$spot = db_fetch_one(
    "SELECT s.*, c.name AS city_name, c.slug AS city_slug,
            c.latitude AS city_lat, c.longitude AS city_lng
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE s.id = ? AND s.is_active = 1",
    [$spot_id]
);

if (!$spot) {
    header('Location: ' . APP_URL . '/pages/spots.php');
    exit;
}

// Load photos
$photos = db_fetch_all(
    "SELECT url, caption, photo_type, sort_order
     FROM spot_photos WHERE spot_id = ? ORDER BY sort_order ASC, id ASC",
    [$spot_id]
);

// Load amenities
$amenities = db_fetch_all(
    "SELECT label, icon FROM spot_amenities WHERE spot_id = ? ORDER BY id ASC",
    [$spot_id]
);

// Load reviews (first page)
$reviews = db_fetch_all(
    "SELECT r.rating, r.title, r.body, r.visited_on, r.created_at,
            u.name AS user_name
     FROM spot_reviews r
     JOIN users u ON r.user_id = u.id
     WHERE r.spot_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC LIMIT 5",
    [$spot_id]
);
$review_stats = db_fetch_one(
    "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating
     FROM spot_reviews WHERE spot_id = ? AND is_approved = 1",
    [$spot_id]
);

// Nearby hotels (same city)
$hotels = db_fetch_all(
    "SELECT h.id, h.name, h.star_rating, h.address, h.phone,
            h.price_min, h.price_max, h.latitude, h.longitude
     FROM hotels h
     WHERE h.city_id = ? AND h.is_active = 1
     ORDER BY h.star_rating DESC LIMIT 4",
    [$spot['city_id']]
);

// Category meta
$cat_emojis = [
    'nature'=>'🌿','heritage'=>'🏛️','waterfall'=>'💧','hotspring'=>'♨️',
    'museum'=>'🏺','religious'=>'⛪','beach_lake'=>'🏞️','adventure'=>'🧗','food'=>'🍜'
];
$cat_labels = [
    'nature'=>'Nature','heritage'=>'Heritage','waterfall'=>'Waterfall',
    'hotspring'=>'Hot Spring','museum'=>'Museum','religious'=>'Religious',
    'beach_lake'=>'Lake / Beach','adventure'=>'Adventure','food'=>'Food'
];
$cat_bgs = [
    'nature'=>'#d8f3dc','heritage'=>'#fef3c7','waterfall'=>'#dbeafe',
    'hotspring'=>'#ffe4e6','museum'=>'#f3e8ff','religious'=>'#fff7ed',
    'beach_lake'=>'#e0f2fe','adventure'=>'#fef9c3','food'=>'#fce7f3'
];

$emoji    = $cat_emojis[$spot['category']] ?? '📍';
$cat_bg   = $cat_bgs[$spot['category']]   ?? '#f1f5f9';
$cat_name = $cat_labels[$spot['category']] ?? $spot['category'];

$page_title  = e($spot['name']);
$active_page = 'spots';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Back breadcrumb ──────────────────────────────────────── -->
<div class="py-2 px-3" style="background:var(--sand);border-bottom:1px solid var(--border)">
  <div class="container d-flex align-items-center gap-2" style="font-size:.85rem">
    <a href="<?= APP_URL ?>" style="color:var(--green-mid);text-decoration:none">Home</a>
    <i class="bi bi-chevron-right text-muted" style="font-size:.7rem"></i>
    <a href="<?= APP_URL ?>/pages/spots.php" style="color:var(--green-mid);text-decoration:none">Tourist Spots</a>
    <i class="bi bi-chevron-right text-muted" style="font-size:.7rem"></i>
    <span style="color:var(--charcoal)"><?= e($spot['name']) ?></span>
  </div>
</div>

<!-- ── Hero Photo Gallery ────────────────────────────────────── -->
<section class="spot-hero-gallery">
<?php
$main_photos = array_values(array_filter($photos, fn($p) => $p['photo_type'] === 'main'));
$gallery     = array_values(array_filter($photos, fn($p) => $p['photo_type'] !== 'main'));
$hero_url    = $main_photos[0]['url'] ?? ($photos[0]['url'] ?? null);
?>

<?php if ($hero_url): ?>
  <div class="gallery-grid <?= count($photos) >= 3 ? 'has-multi' : '' ?>">
    <!-- Main hero image -->
    <div class="gallery-main" onclick="openLightbox(0)" style="cursor:pointer">
      <img src="<?= e($hero_url) ?>" alt="<?= e($spot['name']) ?>" loading="eager">
      <div class="gallery-overlay">
        <i class="bi bi-arrows-fullscreen"></i>
      </div>
    </div>

    <?php if (count($photos) >= 2): ?>
    <!-- Side thumbnails -->
    <div class="gallery-side">
      <?php foreach (array_slice($photos, 1, 4) as $i => $ph): ?>
      <div class="gallery-thumb <?= ($i === 3 && count($photos) > 5) ? 'gallery-thumb-more' : '' ?>"
           onclick="openLightbox(<?= $i + 1 ?>)" style="cursor:pointer">
        <img src="<?= e($ph['url']) ?>" alt="<?= e($ph['caption'] ?? $spot['name']) ?>" loading="lazy">
        <?php if ($i === 3 && count($photos) > 5): ?>
          <div class="gallery-more-overlay">+<?= count($photos) - 5 ?> more</div>
        <?php endif; ?>
        <div class="gallery-overlay"><i class="bi bi-zoom-in"></i></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <!-- No photos: emoji placeholder -->
  <div class="gallery-placeholder" style="background:<?= e($cat_bg) ?>">
    <span style="font-size:6rem"><?= $emoji ?></span>
    <p class="mt-3 text-muted small">No photos yet — be the first to contribute!</p>
  </div>
<?php endif; ?>
</section>

<!-- ── Lightbox ──────────────────────────────────────────────── -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="bi bi-x-lg"></i></button>
  <button class="lightbox-prev" onclick="event.stopPropagation();moveLightbox(-1)"><i class="bi bi-chevron-left"></i></button>
  <div class="lightbox-content" onclick="event.stopPropagation()">
    <img id="lightbox-img" src="" alt="">
    <p id="lightbox-caption" class="lightbox-caption"></p>
  </div>
  <button class="lightbox-next" onclick="event.stopPropagation();moveLightbox(1)"><i class="bi bi-chevron-right"></i></button>
</div>

<!-- ── Main Content ──────────────────────────────────────────── -->
<section class="py-4">
<div class="container">
<div class="row g-4">

  <!-- ── LEFT: Main Info ──────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Name + Category + Quick Stats -->
    <div class="mb-4">
      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
        <span class="badge-category badge-<?= e($spot['category']) ?>"><?= e($cat_name) ?></span>
        <?php if ($spot['entrance_fee'] == 0): ?>
          <span style="background:#dcfce7;color:#15803d;padding:.2rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700">
            🎉 Free Entry
          </span>
        <?php endif; ?>
      </div>

      <h1 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--green-dark);margin-bottom:.35rem">
        <?= e($spot['name']) ?>
      </h1>

      <div class="d-flex align-items-center gap-3 flex-wrap mb-3" style="font-size:.9rem;color:var(--text-muted)">
        <span><i class="bi bi-geo-alt-fill me-1" style="color:var(--green-mid)"></i><?= e($spot['city_name']) ?>, Laguna</span>
        <span>·</span>
        <!-- Rating -->
        <span class="d-flex align-items-center gap-1">
          <?php
          $rating = round((float)$spot['rating'] * 2) / 2; // nearest 0.5
          for ($i = 1; $i <= 5; $i++) {
            if ($rating >= $i) echo '<i class="bi bi-star-fill" style="color:var(--sand-dark)"></i>';
            elseif ($rating >= $i - 0.5) echo '<i class="bi bi-star-half" style="color:var(--sand-dark)"></i>';
            else echo '<i class="bi bi-star" style="color:#ccc"></i>';
          }
          ?>
          <strong style="color:var(--charcoal)"><?= number_format((float)$spot['rating'], 1) ?></strong>
          <span style="color:var(--text-muted)">(<?= (int)($review_stats['total'] ?? 0) ?> reviews)</span>
        </span>
      </div>

      <!-- Quick info chips -->
      <div class="d-flex flex-wrap gap-2 mb-4">
        <?php if ($spot['entrance_fee'] > 0): ?>
        <div class="spot-chip">
          <i class="bi bi-ticket"></i>
          <span>₱ <?= number_format($spot['entrance_fee'], 2) ?> entrance</span>
        </div>
        <?php endif; ?>
        <?php if ($spot['operating_hours']): ?>
        <div class="spot-chip">
          <i class="bi bi-clock"></i>
          <span><?= e($spot['operating_hours']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($spot['contact_number'])): ?>
        <div class="spot-chip">
          <i class="bi bi-telephone"></i>
          <span><?= e($spot['contact_number']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Description -->
      <p style="font-size:1rem;line-height:1.8;color:var(--charcoal)">
        <?= nl2br(e($spot['description'] ?? 'No description available.')) ?>
      </p>

      <?php if (!empty($spot['tips'])): ?>
      <div class="spot-tips-box">
        <div class="spot-tips-title"><i class="bi bi-lightbulb-fill me-2" style="color:var(--sand-dark)"></i>Visitor Tips</div>
        <p class="mb-0 small"><?= nl2br(e($spot['tips'])) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── What You Can Find Here (Amenities) ──────────────── -->
    <?php if (!empty($amenities)): ?>
    <div class="detail-section">
      <h4 class="detail-section-title">
        <i class="bi bi-grid-3x3-gap-fill me-2" style="color:var(--green-light)"></i>What You Can Find Here
      </h4>
      <div class="amenities-grid">
        <?php foreach ($amenities as $am): ?>
        <div class="amenity-item">
          <i class="bi <?= e($am['icon']) ?>"></i>
          <span><?= e($am['label']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Photo Gallery by Category ───────────────────────── -->
    <?php if (!empty($photos)): ?>
    <div class="detail-section">
      <h4 class="detail-section-title">
        <i class="bi bi-images me-2" style="color:var(--green-light)"></i>Photo Gallery
      </h4>

      <!-- Tab filters -->
      <?php
      $photo_types = array_unique(array_column($photos, 'photo_type'));
      $type_labels = [
        'main'=>'Main','gallery'=>'Gallery','food'=>'Food & Dining',
        'hotel_nearby'=>'Nearby Stays','activity'=>'Activities'
      ];
      ?>
      <div class="d-flex flex-wrap gap-2 mb-3" id="gallery-tabs">
        <button class="gallery-tab active" data-type="all">All</button>
        <?php foreach ($photo_types as $type): ?>
          <?php if ($type !== 'main'): ?>
          <button class="gallery-tab" data-type="<?= e($type) ?>">
            <?= e($type_labels[$type] ?? ucfirst($type)) ?>
          </button>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="gallery-masonry" id="gallery-grid">
        <?php foreach ($photos as $i => $ph): ?>
        <div class="gallery-tile" data-type="<?= e($ph['photo_type']) ?>" onclick="openLightbox(<?= $i ?>)">
          <img src="<?= e($ph['url']) ?>" alt="<?= e($ph['caption'] ?? '') ?>" loading="lazy">
          <?php if ($ph['caption']): ?>
          <div class="gallery-tile-caption"><?= e($ph['caption']) ?></div>
          <?php endif; ?>
          <div class="gallery-overlay"><i class="bi bi-zoom-in"></i></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Reviews ─────────────────────────────────────────── -->
    <div class="detail-section" id="reviews-section">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="detail-section-title mb-0">
          <i class="bi bi-chat-quote-fill me-2" style="color:var(--green-light)"></i>
          Visitor Reviews
          <?php if ($review_stats['total'] > 0): ?>
            <span style="font-size:.85rem;font-weight:400;color:var(--text-muted)">
              (<?= (int)$review_stats['total'] ?>)
            </span>
          <?php endif; ?>
        </h4>
        <?php if (is_logged_in()): ?>
        <button class="btn btn-sm btn-primary-app" id="write-review-btn">
          <i class="bi bi-pencil me-1"></i>Write a Review
        </button>
        <?php else: ?>
        <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-sm btn-outline-app">
          <i class="bi bi-person me-1"></i>Log in to Review
        </a>
        <?php endif; ?>
      </div>

      <!-- Rating summary bar -->
      <?php if ($review_stats['total'] > 0): ?>
      <div class="review-summary mb-4">
        <div class="review-big-rating">
          <span class="review-score"><?= number_format((float)$review_stats['avg_rating'], 1) ?></span>
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
      </div>
      <?php endif; ?>

      <!-- Write review form (hidden by default) -->
      <?php if (is_logged_in()): ?>
      <div id="review-form-wrap" class="review-form-wrap d-none mb-4">
        <h6 class="fw-bold mb-3" style="color:var(--green-dark)">Share Your Experience</h6>

        <!-- Star picker -->
        <div class="mb-3">
          <label class="form-label">Your Rating</label>
          <div class="star-picker" id="star-picker">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star star-pick" data-val="<?= $i ?>"></i>
            <?php endfor; ?>
          </div>
          <input type="hidden" id="review-rating" value="0">
        </div>

        <div class="mb-2">
          <label class="form-label">Title <span class="text-muted">(optional)</span></label>
          <input type="text" class="form-control" id="review-title" maxlength="120"
                 placeholder="e.g. Absolutely beautiful lake!">
        </div>

        <div class="mb-2">
          <label class="form-label">Your Review</label>
          <textarea class="form-control" id="review-body" rows="4" maxlength="1000"
                    placeholder="What did you love? What should visitors know?"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Date Visited <span class="text-muted">(optional)</span></label>
          <input type="date" class="form-control" id="review-visited"
                 max="<?= date('Y-m-d') ?>">
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary-app" id="submit-review-btn">
            <i class="bi bi-send me-1"></i>Submit Review
          </button>
          <button class="btn btn-outline-secondary" id="cancel-review-btn">Cancel</button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Reviews list -->
      <div id="reviews-list">
        <?php if (empty($reviews)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>
            <p class="mb-0">No reviews yet. Be the first to share your experience!</p>
          </div>
        <?php else: ?>
          <?php foreach ($reviews as $rv): ?>
          <div class="review-card">
            <div class="d-flex align-items-start gap-3 mb-2">
              <div class="review-avatar">
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
                  <?php if ($rv['visited_on']): ?>
                    <span>· Visited <?= date('M Y', strtotime($rv['visited_on'])) ?></span>
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
            <button class="btn btn-outline-app btn-sm" id="load-more-reviews"
                    data-page="2" data-spot="<?= $spot_id ?>">
              <i class="bi bi-arrow-down me-1"></i>Load More Reviews
            </button>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- ── RIGHT: Sidebar ───────────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Quick Actions -->
    <div class="sidebar-card mb-3">
      <a href="<?= APP_URL ?>/pages/planner.php?destination=<?= $spot['city_id'] ?>"
         class="btn btn-primary-app w-100 mb-2">
        <i class="bi bi-compass me-2"></i>Plan a Trip Here
      </a>
      <?php if (!empty($spot['google_maps_url'])): ?>
      <a href="<?= e($spot['google_maps_url']) ?>" target="_blank" rel="noopener"
         class="btn btn-outline-app w-100 mb-2">
        <i class="bi bi-map me-2"></i>Open in Google Maps
      </a>
      <?php endif; ?>
      <?php if (!empty($spot['website_url'])): ?>
      <a href="<?= e($spot['website_url']) ?>" target="_blank" rel="noopener"
         class="btn btn-outline-secondary w-100 btn-sm">
        <i class="bi bi-globe me-2"></i>Official Website
      </a>
      <?php endif; ?>
    </div>

    <!-- Location mini-map -->
    <div class="sidebar-card mb-3">
      <h6 class="sidebar-card-title">
        <i class="bi bi-pin-map-fill me-2" style="color:var(--green-light)"></i>Location
      </h6>
      <div id="mini-map" style="height:200px;border-radius:10px;overflow:hidden"></div>
      <div class="mt-2 small text-muted">
        <i class="bi bi-geo-alt me-1"></i><?= e($spot['city_name']) ?>, Laguna
      </div>
    </div>

    <!-- Nearest Hotels -->
    <?php if (!empty($hotels)): ?>
    <div class="sidebar-card mb-3">
      <h6 class="sidebar-card-title">
        <i class="bi bi-building me-2" style="color:var(--green-light)"></i>Where to Stay Nearby
      </h6>

      <?php foreach ($hotels as $hotel): ?>
      <div class="hotel-nearby-card">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div>
            <div class="fw-bold small mb-1"><?= e($hotel['name']) ?></div>
            <?php if ($hotel['address']): ?>
            <div class="small text-muted mb-1">
              <i class="bi bi-geo-alt me-1"></i><?= e($hotel['address']) ?>
            </div>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="bi bi-star<?= $i <= $hotel['star_rating'] ? '-fill' : '' ?>"
                     style="font-size:.65rem;color:<?= $i <= $hotel['star_rating'] ? 'var(--sand-dark)' : '#ddd' ?>"></i>
                <?php endfor; ?>
              </span>
              <?php if ($hotel['price_min'] && $hotel['price_max']): ?>
              <span class="small" style="color:var(--terracotta);font-weight:600">
                ₱<?= number_format($hotel['price_min']) ?>–₱<?= number_format($hotel['price_max']) ?>
              </span>
              <?php endif; ?>
            </div>
            <?php if ($hotel['phone']): ?>
            <div class="small text-muted mt-1">
              <i class="bi bi-telephone me-1"></i><?= e($hotel['phone']) ?>
            </div>
            <?php endif; ?>
          </div>
          <a href="<?= APP_URL ?>/pages/planner.php?destination=<?= $spot['city_id'] ?>"
             class="btn btn-sm btn-outline-app flex-shrink-0" style="font-size:.72rem;padding:.25rem .6rem">
            Plan
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Share -->
    <div class="sidebar-card mb-3">
      <h6 class="sidebar-card-title">
        <i class="bi bi-share me-2" style="color:var(--green-light)"></i>Share This Spot
      </h6>
      <div class="d-flex gap-2 flex-wrap">
        <?php $share_url = APP_URL . '/pages/spot-detail.php?id=' . $spot_id; ?>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($share_url) ?>"
           target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"
           style="color:#1877f2;border-color:#1877f2">
          <i class="bi bi-facebook me-1"></i>Facebook
        </a>
        <button onclick="navigator.clipboard.writeText('<?= $share_url ?>').then(()=>IExploreApp.toast('Link copied!','success'))"
                class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-link-45deg me-1"></i>Copy Link
        </button>
      </div>
    </div>

  </div><!-- /col-lg-4 -->

</div><!-- /row -->
</div><!-- /container -->
</section>

<!-- ── Leaflet for mini-map ──────────────────────────────────── -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const SPOT_ID   = <?= $spot_id ?>;
const SPOT_LAT  = <?= (float) $spot['latitude'] ?>;
const SPOT_LNG  = <?= (float) $spot['longitude'] ?>;
const API_BASE  = '<?= APP_URL ?>/api/';

// ── Photo data for lightbox ─────────────────────────────────
const PHOTOS = <?= json_encode(array_values($photos)) ?>;

// ── Lightbox ────────────────────────────────────────────────
let lbIndex = 0;
function openLightbox(i) {
  if (!PHOTOS.length) return;
  lbIndex = Math.max(0, Math.min(i, PHOTOS.length - 1));
  renderLightbox();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function moveLightbox(dir) {
  lbIndex = (lbIndex + dir + PHOTOS.length) % PHOTOS.length;
  renderLightbox();
}
function renderLightbox() {
  const ph = PHOTOS[lbIndex];
  document.getElementById('lightbox-img').src       = ph.url;
  document.getElementById('lightbox-caption').textContent = ph.caption || '';
}
document.addEventListener('keydown', e => {
  if (!document.getElementById('lightbox').classList.contains('open')) return;
  if (e.key === 'Escape')     closeLightbox();
  if (e.key === 'ArrowLeft')  moveLightbox(-1);
  if (e.key === 'ArrowRight') moveLightbox(1);
});

// ── Gallery tab filter ──────────────────────────────────────
document.querySelectorAll('.gallery-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.gallery-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const type = btn.dataset.type;
    document.querySelectorAll('.gallery-tile').forEach(tile => {
      tile.style.display =
        (type === 'all' || tile.dataset.type === type || (type !== 'main' && tile.dataset.type === type))
        ? '' : 'none';
    });
  });
});

// ── Mini-map ────────────────────────────────────────────────
const miniMap = L.map('mini-map', { zoomControl: false, scrollWheelZoom: false })
  .setView([SPOT_LAT, SPOT_LNG], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap', maxZoom: 18
}).addTo(miniMap);
const spotIcon = L.divIcon({
  className: '',
  html: `<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;
           background:#c77c48;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
           transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
           <span style="transform:rotate(45deg);font-size:12px;color:#fff">★</span></div>`,
  iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32],
});
L.marker([SPOT_LAT, SPOT_LNG], { icon: spotIcon })
  .addTo(miniMap)
  .bindPopup(`<strong><?= e(addslashes($spot['name'])) ?></strong>`)
  .openPopup();
setTimeout(() => miniMap.invalidateSize(), 200);

// ── Review form toggle ──────────────────────────────────────
const writeBtn  = document.getElementById('write-review-btn');
const cancelBtn = document.getElementById('cancel-review-btn');
const formWrap  = document.getElementById('review-form-wrap');

if (writeBtn) {
  writeBtn.addEventListener('click', () => {
    formWrap.classList.toggle('d-none');
    if (!formWrap.classList.contains('d-none')) {
      formWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
}
if (cancelBtn) {
  cancelBtn.addEventListener('click', () => formWrap.classList.add('d-none'));
}

// ── Star picker ─────────────────────────────────────────────
let pickedRating = 0;
document.querySelectorAll('.star-pick').forEach(star => {
  star.addEventListener('mouseenter', () => {
    const val = +star.dataset.val;
    document.querySelectorAll('.star-pick').forEach((s, i) => {
      s.className = 'bi star-pick ' + (i < val ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < val ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('mouseleave', () => {
    document.querySelectorAll('.star-pick').forEach((s, i) => {
      s.className = 'bi star-pick ' + (i < pickedRating ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < pickedRating ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('click', () => {
    pickedRating = +star.dataset.val;
    document.getElementById('review-rating').value = pickedRating;
  });
});

// ── Submit review ───────────────────────────────────────────
const submitBtn = document.getElementById('submit-review-btn');
if (submitBtn) {
  submitBtn.addEventListener('click', async () => {
    const rating = +document.getElementById('review-rating').value;
    if (!rating) { IExploreApp.toast('Please select a star rating.', 'warning'); return; }

    IExploreApp.setLoading(submitBtn, true);
    const res = await fetch(API_BASE + 'spots.php?action=review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        spot_id:    SPOT_ID,
        rating,
        title:      document.getElementById('review-title').value.trim(),
        body:       document.getElementById('review-body').value.trim(),
        visited_on: document.getElementById('review-visited').value,
      })
    }).then(r => r.json());
    IExploreApp.setLoading(submitBtn, false);

    if (res.success) {
      IExploreApp.toast('Review submitted! Thank you.', 'success');
      formWrap.classList.add('d-none');
      setTimeout(() => location.reload(), 1200);
    } else {
      IExploreApp.toast(res.message || 'Could not submit review.', 'error');
    }
  });
}

// ── Load more reviews ───────────────────────────────────────
const loadMoreBtn = document.getElementById('load-more-reviews');
if (loadMoreBtn) {
  loadMoreBtn.addEventListener('click', async () => {
    const page = +loadMoreBtn.dataset.page;
    IExploreApp.setLoading(loadMoreBtn, true);
    const res = await fetch(API_BASE + `spots.php?action=reviews&id=${SPOT_ID}&page=${page}`).then(r => r.json());
    IExploreApp.setLoading(loadMoreBtn, false);

    if (res.success) {
      const list = document.getElementById('reviews-list');
      res.data.reviews.forEach(rv => {
        const stars = Array.from({length:5}, (_,i) =>
          `<i class="bi ${i < rv.rating ? 'bi-star-fill' : 'bi-star'}"
              style="color:${i < rv.rating ? 'var(--sand-dark)' : '#ddd'}"></i>`
        ).join('');
        const div = document.createElement('div');
        div.className = 'review-card';
        div.innerHTML = `
          <div class="d-flex align-items-start gap-3 mb-2">
            <div class="review-avatar">${rv.user_name.charAt(0).toUpperCase()}</div>
            <div class="flex-grow-1">
              <div class="fw-bold small">${rv.user_name}</div>
              <div class="d-flex align-items-center gap-2" style="font-size:.8rem;color:var(--text-muted)">
                <span>${stars}</span>
                ${rv.visited_on ? `<span>· Visited ${new Date(rv.visited_on).toLocaleDateString('en-US',{month:'short',year:'numeric'})}</span>` : ''}
              </div>
            </div>
          </div>
          ${rv.title ? `<div class="fw-bold small mb-1">${rv.title}</div>` : ''}
          ${rv.body  ? `<p class="mb-0 small" style="color:var(--charcoal);line-height:1.6">${rv.body}</p>` : ''}
        `;
        list.insertBefore(div, loadMoreBtn.parentElement);
      });

      loadMoreBtn.dataset.page = page + 1;
      if (!res.data.has_more) loadMoreBtn.parentElement.remove();
    }
  });
}
</script>

<!-- ── Spot Detail Page CSS (scoped) ────────────────────────── -->
<style>
/* ── Hero gallery ─────────────────────────────────── */
.spot-hero-gallery { background: #000; }
.gallery-grid { display: grid; grid-template-columns: 1fr; height: 420px; }
.gallery-grid.has-multi { grid-template-columns: 60% 1fr; gap: 3px; }
.gallery-main, .gallery-thumb { position: relative; overflow: hidden; }
.gallery-main img, .gallery-thumb img { width:100%;height:100%;object-fit:cover;transition:transform .3s; }
.gallery-main:hover img, .gallery-thumb:hover img { transform:scale(1.04); }
.gallery-side { display: grid; grid-template-rows: repeat(2, 1fr); gap: 3px; }
.gallery-overlay {
  position:absolute;inset:0;background:rgba(0,0,0,0);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.5rem;transition:background .25s;
}
.gallery-main:hover .gallery-overlay,
.gallery-thumb:hover .gallery-overlay { background:rgba(0,0,0,.28); }
.gallery-placeholder { display:flex;flex-direction:column;align-items:center;justify-content:center;height:240px; }
.gallery-more-overlay {
  position:absolute;inset:0;background:rgba(0,0,0,.55);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.2rem;font-weight:700;
}

/* ── Lightbox ─────────────────────────────────────── */
.lightbox {
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);
  z-index:9999;align-items:center;justify-content:center;
}
.lightbox.open { display:flex; }
.lightbox-content { max-width:90vw;max-height:85vh;text-align:center; }
.lightbox-content img { max-width:100%;max-height:80vh;border-radius:6px;object-fit:contain; }
.lightbox-caption { color:rgba(255,255,255,.7);font-size:.85rem;margin-top:.5rem; }
.lightbox-close, .lightbox-prev, .lightbox-next {
  position:fixed;background:rgba(255,255,255,.15);border:none;color:#fff;
  border-radius:50%;width:44px;height:44px;display:flex;align-items:center;
  justify-content:center;font-size:1.1rem;cursor:pointer;transition:background .2s;
}
.lightbox-close:hover,.lightbox-prev:hover,.lightbox-next:hover { background:rgba(255,255,255,.3); }
.lightbox-close { top:1rem;right:1rem; }
.lightbox-prev  { left:1rem;top:50%;transform:translateY(-50%); }
.lightbox-next  { right:1rem;top:50%;transform:translateY(-50%); }

/* ── Info chips ───────────────────────────────────── */
.spot-chip {
  display:inline-flex;align-items:center;gap:.4rem;
  background:var(--sand);border:1px solid var(--border);
  border-radius:20px;padding:.3rem .85rem;font-size:.82rem;color:var(--charcoal);
}

/* ── Tips box ─────────────────────────────────────── */
.spot-tips-box {
  background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
  padding:1rem 1.25rem;margin-top:1rem;
}
.spot-tips-title { font-weight:700;margin-bottom:.4rem;font-size:.9rem;color:#92400e; }

/* ── Section titles ───────────────────────────────── */
.detail-section { border-top:1px solid var(--border);padding-top:1.75rem;margin-top:1.75rem; }
.detail-section-title {
  font-family:'Playfair Display',serif;font-size:1.15rem;
  color:var(--green-dark);margin-bottom:1rem;
}

/* ── Amenities ────────────────────────────────────── */
.amenities-grid {
  display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;
}
.amenity-item {
  display:flex;align-items:center;gap:.6rem;
  background:var(--green-pale);border-radius:10px;padding:.6rem .85rem;
  font-size:.84rem;font-weight:500;color:var(--green-dark);
}
.amenity-item i { font-size:1rem;color:var(--green-mid);flex-shrink:0; }

/* ── Gallery masonry ──────────────────────────────── */
.gallery-masonry {
  display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;
}
.gallery-tile {
  position:relative;border-radius:8px;overflow:hidden;
  aspect-ratio:1/1;cursor:pointer;
}
.gallery-tile img { width:100%;height:100%;object-fit:cover;transition:transform .3s; }
.gallery-tile:hover img { transform:scale(1.06); }
.gallery-tile-caption {
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(transparent,rgba(0,0,0,.65));
  color:#fff;font-size:.72rem;padding:.5rem .6rem;
}
.gallery-tab {
  background:#fff;border:1.5px solid var(--border);border-radius:20px;
  padding:.25rem .85rem;font-size:.78rem;cursor:pointer;transition:all .2s;
  color:var(--charcoal);font-family:'DM Sans',sans-serif;
}
.gallery-tab.active, .gallery-tab:hover {
  background:var(--green-mid);color:#fff;border-color:var(--green-mid);
}

/* ── Review cards ─────────────────────────────────── */
.review-summary {
  display:flex;align-items:center;gap:1.25rem;
  background:var(--green-pale);border-radius:12px;padding:1rem 1.25rem;
}
.review-big-rating { display:flex;align-items:center;gap:.85rem; }
.review-score {
  font-size:2.5rem;font-weight:700;color:var(--green-dark);
  font-family:'Playfair Display',serif;line-height:1;
}
.review-card {
  border:1px solid var(--border);border-radius:12px;
  padding:1rem 1.25rem;margin-bottom:.75rem;background:#fff;
  transition:box-shadow .2s;
}
.review-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.08); }
.review-avatar {
  width:38px;height:38px;border-radius:50%;background:var(--green-mid);
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:.95rem;flex-shrink:0;
}
.review-form-wrap {
  background:var(--sand);border:1px solid var(--border);
  border-radius:12px;padding:1.25rem;
}
.star-picker { display:flex;gap:.35rem;font-size:1.6rem;cursor:pointer;margin-bottom:.25rem; }
.star-pick { color:#ccc;transition:color .15s; }

/* ── Sidebar ──────────────────────────────────────── */
.sidebar-card {
  background:#fff;border:1px solid var(--border);border-radius:var(--radius);
  padding:1.25rem;box-shadow:var(--shadow-sm);
}
.sidebar-card-title {
  font-family:'Playfair Display',serif;font-size:1rem;
  color:var(--green-dark);margin-bottom:1rem;font-weight:700;
}
.hotel-nearby-card {
  border-bottom:1px solid var(--border);padding:.85rem 0;
}
.hotel-nearby-card:last-child { border-bottom:none;padding-bottom:0; }

/* ── Responsive ───────────────────────────────────── */
@media (max-width: 768px) {
  .gallery-grid { height: 260px; }
  .gallery-grid.has-multi { grid-template-columns: 65% 1fr; }
  .gallery-masonry { grid-template-columns: repeat(2,1fr); }
  .amenities-grid { grid-template-columns: repeat(2,1fr); }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
