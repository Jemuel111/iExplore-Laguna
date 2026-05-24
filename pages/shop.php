<?php
ob_start();
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
    "SELECT s.*, c.name AS city_name FROM shops s
     JOIN cities c ON s.city_id = c.id
     WHERE s.id = ? AND s.is_active = 1",
    [$shop_id]
);
if (!$shop) { header('Location: ' . APP_URL . '/pages/explore.php'); exit; }

$products = db_fetch_all(
    "SELECT * FROM shop_products
     WHERE shop_id = ? AND is_available = 1
     ORDER BY category, sort_order, name",
    [$shop_id]
);

// Group by category
$grouped = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'Other';
    $grouped[$cat][] = $p;
}

$catEmojis = [
    'milktea'=>'🧋','cafe'=>'☕','restaurant'=>'🍜','bakery'=>'🥐',
    'street_food'=>'🍢','souvenir'=>'🛍️','pasalubong'=>'🎁','grocery'=>'🛒','other'=>'🏪'
];
$shopEmoji = $catEmojis[$shop['category']] ?? '🏪';

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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Shop hero -->
<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-start gap-4 flex-wrap">
      <div style="width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;flex-shrink:0">
        <?= $shopEmoji ?>
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
            <div style="width:52px;height:52px;background:var(--green-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0">
              🛍️
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-bold" style="font-size:.95rem"><?= e($p['name']) ?></div>
              <?php if ($p['description']): ?>
              <div class="text-muted small"><?= e(mb_strimwidth($p['description'],0,60,'…')) ?></div>
              <?php endif; ?>
              <?php if ($p['stock'] < 999 && $p['stock'] <= 10): ?>
              <div class="small" style="color:var(--terracotta)">⚠️ Only <?= $p['stock'] ?> left</div>
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
    <?php if ($reviews): ?>
    <div class="mt-4">
      <h6 class="fw-bold mb-3" style="color:var(--green-dark);font-family:'Playfair Display',serif">
        <i class="bi bi-star-fill me-2" style="color:var(--sand-dark)"></i>Customer Reviews
      </h6>
      <div class="d-flex flex-column gap-2">
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
              <option value="cash_on_pickup">💵 Cash on Pickup</option>
              <option value="gcash">📱 GCash</option>
              <option value="maya">📱 Maya</option>
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
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = '<?= APP_URL ?>/pages/my-orders.php?new=' + data.order_number;
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
