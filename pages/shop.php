<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Public Shop Page (Tourist View)
// pages/shop.php?id=SHOP_ID
// Tourists browse products and add to order cart
// ============================================================
$page_title  = 'Shop';
$active_page = 'explore';


$shop_id = (int) input('id', 'get', 0);
if (!$shop_id) { header('Location: ' . APP_URL . '/pages/explore.php'); exit; }

$shop = db_fetch_one(
    "SELECT s.*, c.name AS city_name,
            c.latitude AS city_latitude, c.longitude AS city_longitude
     FROM shops s
     JOIN cities c ON s.city_id = c.id
     WHERE s.id = ? AND s.is_active = 1 AND s.is_verified = 1",
    [$shop_id]
);
if (!$shop) { header('Location: ' . APP_URL . '/pages/explore.php'); exit; }

$products = db_fetch_all(
    "SELECT * FROM shop_products
     WHERE shop_id = ? AND is_available = 1
     ORDER BY category, sort_order, name",
    [$shop_id]
);

// The top-rated tourist spot in this shop's city — used to nudge the
// itinerary builder ("this shop is near X, add it to your trip?")
$nearby_spot = db_fetch_one(
    "SELECT id, name, entrance_fee, category FROM tourist_spots
     WHERE city_id = ? AND is_active = 1
     ORDER BY rating DESC, name ASC
     LIMIT 1",
    [$shop['city_id']]
);

// Group by category
$grouped = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'Other';
    $grouped[$cat][] = $p;
}

// Category icon now comes from the shared shop_category_icon() helper
// in helpers.php instead of a locally duplicated emoji array.
$shopIconClass = shop_category_icon($shop['category']);

// Reviews
$reviews = db_fetch_all(
    "SELECT r.*, u.name AS tourist_name FROM shop_reviews r
     JOIN users u ON r.tourist_id = u.id
     WHERE r.shop_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC LIMIT 5",
    [$shop_id]
);
$avg_rating = count($reviews)
    ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1)
    : null;

// Orders this tourist can review — must be picked up, and not already reviewed
$reviewable_order = null;
if (is_logged_in()) {
    $reviewable_order = db_fetch_one(
        "SELECT o.id, o.order_number FROM orders o
         WHERE o.shop_id = ? AND o.tourist_id = ? AND o.status = 'picked_up'
           AND o.id NOT IN (SELECT order_id FROM shop_reviews WHERE tourist_id = ?)
         ORDER BY o.created_at DESC LIMIT 1",
        [$shop_id, current_user()['id'], current_user()['id']]
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Shop hero -->
<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-start gap-4 flex-wrap">
      <div style="width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:2.5rem;flex-shrink:0">
        <?php if (!empty($shop['cover_url'])): ?>
        <img src="<?= e($shop['cover_url']) ?>" alt="<?= e($shop['name']) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
        <i class="bi <?= $shopIconClass ?>"></i>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1">
        <h1 class="mb-1 fs-3" style="font-family:'Playfair Display',serif"><?= e($shop['name']) ?></h1>
        <div class="d-flex flex-wrap gap-3 opacity-85 small">
          <span><i class="bi bi-geo-alt me-1"></i><?= e($shop['city_name']) ?></span>
          <?php if ($shop['address']): ?>
            <span><i class="bi bi-pin-map me-1"></i><?= e($shop['address']) ?></span>
          <?php endif; ?>
          <?php if ($shop['open_time'] && $shop['close_time']): ?>
            <span><i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($shop['open_time'])) ?> – <?= date('g:i A', strtotime($shop['close_time'])) ?></span>
          <?php endif; ?>
          <?php if ($shop['open_days']): ?>
            <span><i class="bi bi-calendar3 me-1"></i><?= e($shop['open_days']) ?></span>
          <?php endif; ?>
          <?php if ($avg_rating): ?>
            <span style="color:var(--sand-dark)"><i class="bi bi-star-fill me-1"></i><?= $avg_rating ?> (<?= count($reviews) ?> reviews)</span>
          <?php endif; ?>
        </div>
        <?php if ($shop['description']): ?>
          <p class="mb-0 mt-2 opacity-80 small"><?= e($shop['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($shop['phone']): ?>
      <a href="tel:<?= e($shop['phone']) ?>" class="btn btn-sm"
         style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:var(--radius-pill)">
        <i class="bi bi-telephone me-1"></i><?= e($shop['phone']) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Products ──────────────────────────────────────────── -->
  <div class="col-lg-8">
    <?php if (empty($products)): ?>
      <div class="text-center py-5">
        <i class="bi bi-box-seam fs-1 text-muted d-block mb-3"></i>
        <h5>No products available yet</h5>
        <p class="text-muted">Check back soon!</p>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $cat => $items): ?>
      <div class="mb-4">
        <h6 class="fw-bold mb-3 pb-2" style="color:var(--green-dark);border-bottom:2px solid var(--green-pale);font-family:'Playfair Display',serif">
          <?= e($cat) ?>
        </h6>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($items as $p): ?>
          <div class="d-flex align-items-center gap-3 p-3"
               style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);transition:border-color .2s">
            <div style="width:52px;height:52px;background:var(--green-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--green-mid);flex-shrink:0">
              <i class="bi bi-bag"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-bold" style="font-size:.95rem"><?= e($p['name']) ?></div>
              <?php if ($p['description']): ?>
              <div class="text-muted small"><?= e(mb_strimwidth($p['description'],0,60,'…')) ?></div>
              <?php endif; ?>
              <?php if ($p['stock'] < 999 && $p['stock'] <= 10): ?>
              <div class="small" style="color:var(--terracotta)"><i class="bi bi-exclamation-triangle-fill me-1"></i>Only <?= $p['stock'] ?> left</div>
              <?php endif; ?>
            </div>
            <div class="text-end flex-shrink-0">
              <div class="fw-bold mb-1" style="color:var(--terracotta);font-size:1.05rem">
                ₱<?= number_format($p['price'],2) ?>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button class="qty-btn" onclick="changeQty(<?= $p['id'] ?>,-1)" style="width:28px;height:28px;border-radius:50%;border:1.5px solid var(--border);background:#fff;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center">−</button>
                <span id="qty-<?= $p['id'] ?>" style="min-width:20px;text-align:center;font-weight:700">0</span>
                <button class="qty-btn" onclick="changeQty(<?= $p['id'] ?>,1,'<?= e(addslashes($p['name'])) ?>',<?= $p['price'] ?>)" style="width:28px;height:28px;border-radius:50%;border:1.5px solid var(--green-mid);background:var(--green-mid);color:#fff;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center">+</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Reviews -->
    <div class="mt-4" id="reviews-section">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0" style="color:var(--green-dark);font-family:'Playfair Display',serif">
          <i class="bi bi-star-fill me-2" style="color:var(--sand-dark)"></i>Customer Reviews
          <?php if ($avg_rating): ?>
            <span class="text-muted small">(<?= $avg_rating ?> avg · <?= count($reviews) ?>)</span>
          <?php endif; ?>
        </h6>
        <?php if ($reviewable_order): ?>
        <button class="btn btn-sm btn-primary-app" id="write-shop-review-btn">
          <i class="bi bi-pencil-square me-1"></i>Write a Review
        </button>
        <?php endif; ?>
      </div>

      <?php if ($reviewable_order): ?>
      <div id="shop-review-form-wrap" class="p-3 mb-3 d-none" style="background:var(--cream);border-radius:var(--radius-sm)">
        <p class="small text-muted mb-2">Reviewing your order <strong><?= e($reviewable_order['order_number']) ?></strong></p>
        <div class="mb-2">
          <?php for ($i=1;$i<=5;$i++): ?>
            <i class="bi bi-star star-pick-shop" data-val="<?= $i ?>" style="font-size:1.4rem;color:#ccc;cursor:pointer"></i>
          <?php endfor; ?>
          <input type="hidden" id="shop-review-rating" value="0">
        </div>
        <textarea class="form-control mb-2" id="shop-review-comment" rows="3" maxlength="500"
                  placeholder="Tell others about your experience with this shop…"></textarea>
        <button class="btn btn-sm btn-primary-app" id="submit-shop-review-btn">Submit Review</button>
        <button class="btn btn-sm btn-outline-secondary" id="cancel-shop-review-btn">Cancel</button>
      </div>
      <?php endif; ?>

      <?php if (empty($reviews)): ?>
      <div class="text-center py-3 text-muted">
        <i class="bi bi-chat-dots fs-3 d-block mb-2 opacity-50"></i>
        <p class="mb-0 small">No reviews yet. Be the first to share your experience!</p>
      </div>
      <?php else: ?>
      <div class="d-flex flex-column gap-2" id="shop-reviews-list">
        <?php foreach ($reviews as $r): ?>
        <div class="p-3" style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <span class="fw-bold small"><?= e($r['tourist_name']) ?></span>
            <span style="color:var(--sand-dark);font-size:.85rem"><?= str_repeat('★',$r['rating']) . str_repeat('☆',5-$r['rating']) ?></span>
          </div>
          <?php if ($r['comment']): ?>
          <p class="mb-0 small text-muted"><?= e($r['comment']) ?></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Location mini-map -->
    <?php $map_lat = $shop['latitude'] ?: $shop['city_latitude']; $map_lng = $shop['longitude'] ?: $shop['city_longitude']; ?>
    <?php if ($map_lat && $map_lng): ?>
    <div class="mt-4">
      <h6 class="fw-bold mb-2 pb-2" style="color:var(--green-dark);border-bottom:2px solid var(--green-pale);font-family:'Playfair Display',serif">
        <i class="bi bi-pin-map-fill me-2" style="color:var(--terracotta)"></i>Location
      </h6>
      <div id="mini-map" style="height:220px;border-radius:10px;overflow:hidden"></div>
      <div class="mt-2 small text-muted">
        <i class="bi bi-geo-alt me-1"></i><?= e($shop['address'] ?: $shop['city_name']) ?>, Laguna
        <?php if (!$shop['latitude']): ?>
          <span class="fst-italic">(approximate — exact address not yet pinned)</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Order summary sidebar ─────────────────────────────── -->
  <div class="col-lg-4">
    <div style="position:sticky;top:80px">
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">
        <div class="p-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
          <h6 class="mb-0" style="font-family:'Playfair Display',serif">
            <i class="bi bi-bag-check me-2" style="color:var(--sand-dark)"></i>Your Order
          </h6>
        </div>

        <div id="order-items-list" class="p-3" style="min-height:80px">
          <p class="text-muted small text-center mb-0 py-2" id="empty-cart-msg">
            <i class="bi bi-bag d-block fs-3 mb-1 opacity-25"></i>
            No items yet — add from the menu!
          </p>
        </div>

        <?php if ($nearby_spot): ?>
        <div class="mx-3 mb-0 p-2 d-flex align-items-center gap-2" style="background:var(--green-pale);border-radius:var(--radius-sm);font-size:.78rem">
          <i class="bi bi-signpost-split-fill" style="color:var(--green-dark)"></i>
          <span style="color:var(--green-dark)">
            <strong><?= e($shop['name']) ?></strong> is near <strong><?= e($nearby_spot['name']) ?></strong> —
            we'll add it to your itinerary when you order!
          </span>
        </div>
        <?php endif; ?>

        <div class="p-3" style="border-top:1px solid var(--border);background:var(--cream)">
          <!-- Pickup date/time -->
          <div class="mb-3">
            <label class="form-label small fw-600">Pickup Date</label>
            <input type="date" class="form-control form-control-sm" id="pickup-date"
                   min="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Pickup Time</label>
            <input type="time" class="form-control form-control-sm" id="pickup-time"
                   <?= $shop['open_time'] ? 'min="'.e($shop['open_time']).'"' : '' ?>
                   <?= $shop['close_time'] ? 'max="'.e($shop['close_time']).'"' : '' ?>>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Special Notes</label>
            <textarea class="form-control form-control-sm" id="order-notes" rows="2"
                      placeholder="e.g. Less sugar, extra pearls…" style="resize:none"></textarea>
          </div>

          <!-- Payment method -->
          <div class="mb-3">
            <label class="form-label small fw-600">Payment Method</label>
            <select class="form-select form-select-sm" id="payment-method">
              <option value="cash_on_pickup">Cash on Pickup</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
            </select>
          </div>

          <div class="d-flex justify-content-between fw-bold mb-3">
            <span>Total</span>
            <span style="color:var(--green-dark)" id="order-total">₱0.00</span>
          </div>

          <button class="btn btn-primary-app w-100" id="place-order-btn"
                  onclick="placeOrder(<?= $shop_id ?>)" disabled>
            <i class="bi bi-bag-check me-2"></i>Place Order
          </button>

          <?php if (!is_logged_in()): ?>
          <p class="text-center small text-muted mt-2 mb-0">
            <a href="login.php?redirect=<?= urlencode(APP_URL.'/pages/shop.php?id='.$shop_id) ?>" class="fw-bold text-green">
              Log in</a> to place an order
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
let orderCart = {};

// Data needed to auto-add this shop (and its nearby spot) to the
// shared "My List" itinerary cart used on explore.php
const SHOP_CART_ITEM  = {
  key: 'shop-<?= $shop_id ?>', type: 'shop', id: <?= $shop_id ?>,
  name: <?= json_encode($shop['name']) ?>, city: <?= json_encode($shop['city_name']) ?>,
  price: 0
};
let NEARBY_SPOT_ITEM = null;
<?php if ($nearby_spot): ?>
NEARBY_SPOT_ITEM = {
  key: 'spot-<?= $nearby_spot['id'] ?>', type: 'spot', id: <?= $nearby_spot['id'] ?>,
  name: <?= json_encode($nearby_spot['name']) ?>, city: <?= json_encode($shop['city_name']) ?>,
  price: <?= (float) $nearby_spot['entrance_fee'] ?>
};
<?php endif; ?>

function addToItineraryCart(item) {
  let cart = [];
  try { cart = JSON.parse(localStorage.getItem('iexplore_cart') || '[]'); } catch (e) { cart = []; }
  if (!cart.some(i => i.key === item.key)) cart.push(item);
  localStorage.setItem('iexplore_cart', JSON.stringify(cart));
}

function changeQty(pid, delta, name, price) {
  if (!orderCart[pid]) orderCart[pid] = { name: name || '', price: price || 0, qty: 0 };
  orderCart[pid].qty = Math.max(0, orderCart[pid].qty + delta);
  if (orderCart[pid].qty === 0) delete orderCart[pid];
  document.getElementById('qty-' + pid).textContent = orderCart[pid]?.qty || 0;
  renderOrderSidebar();
}

function renderOrderSidebar() {
  const list  = document.getElementById('order-items-list');
  const emsg  = document.getElementById('empty-cart-msg');
  const total = Object.values(orderCart).reduce((s, i) => s + i.price * i.qty, 0);
  const btn   = document.getElementById('place-order-btn');

  document.getElementById('order-total').textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  if (Object.keys(orderCart).length === 0) {
    list.innerHTML = '<p class="text-muted small text-center mb-0 py-2"><i class="bi bi-bag d-block fs-3 mb-1 opacity-25"></i>No items yet — add from the menu!</p>';
    btn.disabled = true;
    return;
  }

  btn.disabled = false;
  list.innerHTML = Object.entries(orderCart).map(([pid, item]) => `
    <div class="d-flex justify-content-between align-items-center small mb-2">
      <span>${item.name} × ${item.qty}</span>
      <span class="fw-bold">₱${(item.price * item.qty).toFixed(2)}</span>
    </div>
  `).join('');
}

function placeOrder(shopId) {
  if (!<?= is_logged_in() ? 'true' : 'false' ?>) {
    window.location.href = '<?= APP_URL ?>/pages/login.php?redirect=<?= urlencode(APP_URL.'/pages/shop.php?id='.$shop_id) ?>';
    return;
  }
  if (Object.keys(orderCart).length === 0) return;

  const btn = document.getElementById('place-order-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing…';

  const payload = {
    shop_id:        shopId,
    items:          Object.entries(orderCart).map(([pid, i]) => ({ product_id: parseInt(pid), qty: i.qty })),
    pickup_date:    document.getElementById('pickup-date').value,
    pickup_time:    document.getElementById('pickup-time').value,
    notes:          document.getElementById('order-notes').value,
    payment_method: document.getElementById('payment-method').value,
  };

  fetch('<?= APP_URL ?>/api/orders.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      addToItineraryCart(SHOP_CART_ITEM);
      let spotQS = '';
      if (NEARBY_SPOT_ITEM) { addToItineraryCart(NEARBY_SPOT_ITEM); spotQS = '&spot=' + encodeURIComponent(NEARBY_SPOT_ITEM.name); }
      window.location.href = '<?= APP_URL ?>/pages/my-orders.php?new=' + data.order_number + spotQS;
    } else {
      alert(data.message || 'Failed to place order. Please try again.');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-bag-check me-2"></i>Place Order';
    }
  })
  .catch(() => {
    alert('Connection error. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-bag-check me-2"></i>Place Order';
  });
}

// ── Shop review form ──────────────────────────────────────────
const writeShopReviewBtn  = document.getElementById('write-shop-review-btn');
const shopReviewFormWrap  = document.getElementById('shop-review-form-wrap');
const cancelShopReviewBtn = document.getElementById('cancel-shop-review-btn');

if (writeShopReviewBtn) {
  writeShopReviewBtn.addEventListener('click', () => {
    shopReviewFormWrap.classList.toggle('d-none');
    if (!shopReviewFormWrap.classList.contains('d-none')) {
      shopReviewFormWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
}
if (cancelShopReviewBtn) {
  cancelShopReviewBtn.addEventListener('click', () => shopReviewFormWrap.classList.add('d-none'));
}

let pickedShopRating = 0;
document.querySelectorAll('.star-pick-shop').forEach(star => {
  star.addEventListener('mouseenter', () => {
    const val = +star.dataset.val;
    document.querySelectorAll('.star-pick-shop').forEach((s, i) => {
      s.className = 'bi star-pick-shop ' + (i < val ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < val ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('mouseleave', () => {
    document.querySelectorAll('.star-pick-shop').forEach((s, i) => {
      s.className = 'bi star-pick-shop ' + (i < pickedShopRating ? 'bi-star-fill' : 'bi-star');
      s.style.color = i < pickedShopRating ? 'var(--sand-dark)' : '#ccc';
    });
  });
  star.addEventListener('click', () => {
    pickedShopRating = +star.dataset.val;
    document.getElementById('shop-review-rating').value = pickedShopRating;
  });
});

const submitShopReviewBtn = document.getElementById('submit-shop-review-btn');
if (submitShopReviewBtn) {
  submitShopReviewBtn.addEventListener('click', () => {
    const rating = +document.getElementById('shop-review-rating').value;
    if (!rating) { IExploreApp.toast('Please select a star rating.', 'warning'); return; }

    submitShopReviewBtn.disabled = true;
    submitShopReviewBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('<?= APP_URL ?>/api/shops.php?action=review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
      body: JSON.stringify({
        shop_id:  <?= $shop_id ?>,
        order_id: <?= $reviewable_order['id'] ?? 0 ?>,
        rating:   rating,
        comment:  document.getElementById('shop-review-comment').value.trim(),
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        IExploreApp.toast('Review submitted! Thank you.', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        IExploreApp.toast(data.message || 'Could not submit review.', 'danger');
        submitShopReviewBtn.disabled = false;
        submitShopReviewBtn.innerHTML = 'Submit Review';
      }
    })
    .catch(() => {
      IExploreApp.toast('Connection error. Please try again.', 'danger');
      submitShopReviewBtn.disabled = false;
      submitShopReviewBtn.innerHTML = 'Submit Review';
    });
  });
}

<?php if ($map_lat && $map_lng): ?>
// ── Location mini-map ──────────────────────────────────────────
// Wrapped in DOMContentLoaded: Leaflet's JS loads at the bottom of the
// page (in footer.php), which comes AFTER this script block in document
// order — so we must wait until everything has finished loading.
document.addEventListener('DOMContentLoaded', function() {
  const shopMiniMap = L.map('mini-map', { zoomControl: false, scrollWheelZoom: false })
    .setView([<?= $map_lat ?>, <?= $map_lng ?>], <?= $shop['latitude'] ? 15 : 12 ?>);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 18
  }).addTo(shopMiniMap);
  const shopIcon = L.divIcon({
    className: '',
    html: `<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;
             background:var(--terracotta);border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);
             transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
             <i class="bi <?= $shopIconClass ?>" style="transform:rotate(45deg);font-size:14px;color:#fff"></i></div>`,
    iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32],
  });
  L.marker([<?= $map_lat ?>, <?= $map_lng ?>], { icon: shopIcon })
    .addTo(shopMiniMap)
    .bindPopup(`<strong><?= e(addslashes($shop['name'])) ?></strong>`)
    .openPopup();
  setTimeout(() => shopMiniMap.invalidateSize(), 200);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
