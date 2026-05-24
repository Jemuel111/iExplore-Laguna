<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Tourist: My Orders Page
// pages/my-orders.php
// ============================================================
$page_title  = 'My Orders';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();

$new_order = input('new', 'get', '');

$orders = db_fetch_all(
    "SELECT o.*, s.name AS shop_name, s.id AS shop_id,
            c.name AS city_name
     FROM orders o
     JOIN shops  s ON o.shop_id  = s.id
     JOIN cities c ON s.city_id  = c.id
     WHERE o.tourist_id = ?
     ORDER BY o.created_at DESC",
    [$u['id']]
);

$statusColors = [
    'pending'   => ['#fff3cd','#856404','⏳','Pending'],
    'confirmed' => ['#d1ecf1','#0c5460','✅','Confirmed'],
    'preparing' => ['#d4edda','#155724','🍳','Preparing'],
    'ready'     => ['#d4edda','#155724','📦','Ready for Pickup!'],
    'picked_up' => ['#e2e3e5','#383d41','✔️','Picked Up'],
    'cancelled' => ['#f8d7da','#721c24','❌','Cancelled'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-bag-check-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">My Orders</h1>
        <p class="mb-0 small opacity-75"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> total</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <?php if ($new_order): ?>
  <div class="alert alert-success d-flex align-items-start gap-3 mb-4" style="border-radius:var(--radius)">
    <i class="bi bi-check-circle-fill fs-4 flex-shrink-0"></i>
    <div>
      <div class="fw-bold mb-1">Order Placed Successfully! 🎉</div>
      <div>Your order <strong><?= e($new_order) ?></strong> has been sent to the shop.
        You'll be notified when it's confirmed and ready for pickup.</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>
    <div class="text-center py-5">
      <i class="bi bi-bag fs-1 text-muted d-block mb-3"></i>
      <h5>No orders yet</h5>
      <p class="text-muted">Browse shops and order food in advance!</p>
      <a href="explore.php" class="btn btn-primary-app">Explore Shops</a>
    </div>
  <?php else: ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($orders as $ord):
        [$bg,$fg,$ico,$label] = $statusColors[$ord['status']] ?? ['#f1f5f9','#334155','📋','Unknown'];
        $items = db_fetch_all("SELECT * FROM order_items WHERE order_id = ?", [$ord['id']]);
      ?>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden">

        <!-- Order header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
             style="background:var(--cream);border-bottom:1px solid var(--border)">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($ord['order_number']) ?></span>
            <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.22rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <?= $ico ?> <?= $label ?>
            </span>
          </div>
          <span class="text-muted small"><?= date('M d, Y g:i A', strtotime($ord['created_at'])) ?></span>
        </div>

        <div class="p-3">
          <div class="row g-3 align-items-start">
            <!-- Shop + items -->
            <div class="col-sm-5">
              <div class="small text-muted fw-600 mb-1">Shop</div>
              <div class="fw-bold"><?= e($ord['shop_name']) ?></div>
              <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($ord['city_name']) ?></div>

              <div class="mt-2">
                <div class="small text-muted fw-600 mb-1">Items</div>
                <?php foreach ($items as $item): ?>
                <div class="small d-flex justify-content-between">
                  <span><?= e($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                  <span>₱<?= number_format($item['subtotal'],2) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Pickup info -->
            <div class="col-sm-4">
              <?php if ($ord['pickup_date']): ?>
              <div class="small text-muted fw-600 mb-1">Pickup Schedule</div>
              <div class="small">
                <i class="bi bi-calendar3 me-1 text-green"></i>
                <?= date('D, M d Y', strtotime($ord['pickup_date'])) ?>
              </div>
              <?php if ($ord['pickup_time']): ?>
              <div class="small">
                <i class="bi bi-clock me-1 text-green"></i>
                <?= date('g:i A', strtotime($ord['pickup_time'])) ?>
              </div>
              <?php endif; ?>
              <?php endif; ?>

              <?php if ($ord['special_notes']): ?>
              <div class="mt-2 small text-muted fst-italic">📝 <?= e($ord['special_notes']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Pickup code + total -->
            <div class="col-sm-3 text-sm-end">
              <div class="small text-muted fw-600 mb-1">Total</div>
              <div class="fw-bold fs-5" style="color:var(--green-dark)">₱<?= number_format($ord['total_amount'],2) ?></div>

              <?php if ($ord['pickup_code'] && in_array($ord['status'],['confirmed','preparing','ready'])): ?>
              <div class="mt-2">
                <div class="small text-muted fw-600 mb-1">Pickup Code</div>
                <div style="background:var(--green-pale);color:var(--green-dark);padding:.4rem 1rem;border-radius:10px;font-family:monospace;font-size:1.3rem;font-weight:800;letter-spacing:.15em;display:inline-block">
                  <?= e($ord['pickup_code']) ?>
                </div>
                <div class="small text-muted mt-1">Show this to the shop</div>
              </div>
              <?php endif; ?>

              <?php if ($ord['status'] === 'ready'): ?>
              <div class="mt-2">
                <span class="badge p-2" style="background:var(--green-mid);font-size:.82rem">
                  🎉 Ready! Go pick up your order
                </span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Action footer -->
        <?php if ($ord['status'] === 'pending'): ?>
        <div class="px-3 pb-3">
          <form method="POST" action="<?= APP_URL ?>/api/cancel-order.php"
                onsubmit="return confirm('Cancel this order?')">
            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
              <i class="bi bi-x-circle me-1"></i>Cancel Order
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
