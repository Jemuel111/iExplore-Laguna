<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Shop Owner Dashboard
// pages/shop-dashboard.php
// Manage products, view & update orders
// ============================================================
$page_title  = 'Shop Dashboard';
$active_page = '';


// All redirects BEFORE any output
if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'shop_owner') { header('Location: ' . APP_URL); exit; }

// Get this owner's shop
$shop = db_fetch_one("SELECT * FROM shops WHERE owner_id = ?", [$u['id']]);
if (!$shop) { header('Location: ' . APP_URL . '/pages/register-shop.php?step=2'); exit; }

$sid = $shop['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    // ── Add product ──────────────────────────────────────────
    if ($action === 'add_product') {
        $pname  = trim(input('pname',  'post', ''));
        $price  = (float) input('price',  'post', 0);
        $cat    = trim(input('pcat',   'post', ''));
        $pdesc  = trim(input('pdesc',  'post', ''));
        $stock  = (int) input('stock',  'post', 999);
        if ($pname && $price > 0) {
            db_execute(
                "INSERT INTO shop_products (shop_id, name, description, price, category, stock)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$sid, $pname, $pdesc, $price, $cat, $stock]
            );
            $_SESSION['flash']['success'] = "Product \"{$pname}\" added!";
        }
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Toggle product availability ───────────────────────────
    if ($action === 'toggle_product') {
        $pid = (int) input('product_id', 'post', 0);
        db_execute(
            "UPDATE shop_products SET is_available = NOT is_available
             WHERE id = ? AND shop_id = ?",
            [$pid, $sid]
        );
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Delete product ────────────────────────────────────────
    if ($action === 'delete_product') {
        $pid = (int) input('product_id', 'post', 0);
        db_execute("DELETE FROM shop_products WHERE id = ? AND shop_id = ?", [$pid, $sid]);
        $_SESSION['flash']['success'] = 'Product removed.';
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Update order status ───────────────────────────────────
    if ($action === 'update_order') {
        $oid    = (int) input('order_id', 'post', 0);
        $status = input('status', 'post', '');
        $allowed = ['confirmed','preparing','ready','cancelled'];
        if (in_array($status, $allowed)) {
            if ($status === 'confirmed') {
                $ts_col = ', confirmed_at = NOW()';
            } elseif ($status === 'ready') {
                $ts_col = ', ready_at = NOW()';
            } elseif ($status === 'cancelled') {
                $ts_col = ', cancelled_at = NOW()';
            } else {
                $ts_col = '';
            }
            db_execute(
                "UPDATE orders SET status = ? {$ts_col}
                 WHERE id = ? AND shop_id = ?",
                [$status, $oid, $sid]
            );
            // Notify tourist
            $order = db_fetch_one("SELECT * FROM orders WHERE id = ?", [$oid]);
            if ($order) {
                $msgs = [
                    'confirmed' => ['Order Confirmed! 🎉', "Your order #{$order['order_number']} has been confirmed by the shop."],
                    'preparing' => ['Order Being Prepared 🍳', "Your order #{$order['order_number']} is now being prepared!"],
                    'ready'     => ['Ready for Pickup! ✅', "Your order #{$order['order_number']} is ready. Show your pickup code: {$order['pickup_code']}"],
                    'cancelled' => ['Order Cancelled', "Your order #{$order['order_number']} was cancelled by the shop."],
                ];
                if (isset($msgs[$status])) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, ?, ?, ?, ?)",
                        [$order['tourist_id'], 'order_'.$status,
                         $msgs[$status][0], $msgs[$status][1],
                         APP_URL . '/pages/my-orders.php']
                    );
                }
            }
        }
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#orders'); exit;
    }

    // ── Update shop info ──────────────────────────────────────
    if ($action === 'update_shop') {
        db_execute(
            "UPDATE shops SET description=?, address=?, phone=?, email=?,
                              open_time=?, close_time=?, open_days=?
             WHERE id = ?",
            [
                input('description','post',''),
                input('address','post',''),
                input('phone','post',''),
                input('shop_email','post',''),
                input('open_time','post',null) ?: null,
                input('close_time','post',null) ?: null,
                input('open_days','post',''),
                $sid
            ]
        );
        $_SESSION['flash']['success'] = 'Shop info updated.';
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#settings'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$products = db_fetch_all(
    "SELECT * FROM shop_products WHERE shop_id = ? ORDER BY sort_order, name",
    [$sid]
);

$orders = db_fetch_all(
    "SELECT o.*, u.name AS tourist_name, u.phone AS tourist_phone
     FROM orders o
     JOIN users u ON o.tourist_id = u.id
     WHERE o.shop_id = ?
     ORDER BY o.created_at DESC
     LIMIT 50",
    [$sid]
);

// Stats
$today_orders  = db_fetch_one("SELECT COUNT(*) n FROM orders WHERE shop_id=? AND DATE(created_at)=CURDATE()", [$sid])['n'] ?? 0;
$pending_count = db_fetch_one("SELECT COUNT(*) n FROM orders WHERE shop_id=? AND status='pending'", [$sid])['n'] ?? 0;
$total_revenue = db_fetch_one("SELECT COALESCE(SUM(total_amount),0) n FROM orders WHERE shop_id=? AND status='picked_up'", [$sid])['n'] ?? 0;
$total_products = count($products);

$statusColors = [
    'pending'   => ['#fff3cd','#856404','⏳'],
    'confirmed' => ['#d1ecf1','#0c5460','✅'],
    'preparing' => ['#d4edda','#155724','🍳'],
    'ready'     => ['#d4edda','#155724','📦'],
    'picked_up' => ['#e2e3e5','#383d41','✔️'],
    'cancelled' => ['#f8d7da','#721c24','❌'],
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
<section class="py-3" style="background:linear-gradient(135deg,var(--terracotta),var(--sand-dark));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem">🏪</div>
        <div>
          <h1 class="mb-0 fs-4" style="font-family:'Playfair Display',serif"><?= e($shop['name']) ?></h1>
          <p class="mb-0 small opacity-75">
            <i class="bi bi-geo-alt me-1"></i>Shop Dashboard
            <?php if (!$shop['is_verified']): ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem">⏳ Pending verification</span>
            <?php else: ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem">✅ Verified</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
      <?php if ($pending_count > 0): ?>
        <a href="#orders" class="btn btn-sm"
           style="background:#fff;color:var(--terracotta);font-weight:700;border-radius:var(--radius-pill)">
          <i class="bi bi-bell-fill me-1"></i><?= $pending_count ?> New Order<?= $pending_count > 1 ? 's' : '' ?>
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
      ['📦', 'Today\'s Orders',  $today_orders,   '#dbeafe','#1e40af'],
      ['⏳', 'Pending Orders',   $pending_count,  '#fef3c7','#92400e'],
      ['🛍️', 'Products Listed', $total_products, '#d8f3dc','#1a3a2a'],
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
      <a class="nav-link active" data-bs-toggle="tab" href="#orders" style="font-weight:600">
        <i class="bi bi-receipt me-1"></i>Orders
        <?php if ($pending_count): ?>
          <span class="badge rounded-pill ms-1" style="background:var(--terracotta);font-size:.7rem"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#products" style="font-weight:600">
        <i class="bi bi-grid me-1"></i>Products
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#settings" style="font-weight:600">
        <i class="bi bi-gear me-1"></i>Shop Settings
      </a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── ORDERS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="orders">
      <?php if (empty($orders)): ?>
        <div class="text-center py-5">
          <i class="bi bi-receipt fs-1 text-muted d-block mb-3"></i>
          <h5>No orders yet</h5>
          <p class="text-muted">Orders will appear here once tourists place them.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($orders as $ord):
            [$bg,$fg,$ico] = $statusColors[$ord['status']] ?? ['#f1f5f9','#334155','📋'];
            // Get items
            $items = db_fetch_all(
                "SELECT * FROM order_items WHERE order_id = ?",
                [$ord['id']]
            );
          ?>
          <div class="p-0 rounded overflow-hidden" style="border:1.5px solid var(--border);background:#fff">
            <!-- Order header -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
                 style="background:var(--cream);border-bottom:1px solid var(--border)">
              <div>
                <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($ord['order_number']) ?></span>
                <span class="ms-2" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700">
                  <?= $ico ?> <?= ucfirst($ord['status']) ?>
                </span>
              </div>
              <div class="text-muted small">
                <i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($ord['created_at'])) ?>
              </div>
            </div>

            <div class="p-3">
              <div class="row g-3">
                <!-- Tourist info -->
                <div class="col-sm-4">
                  <div class="small text-muted mb-1 fw-600">Customer</div>
                  <div class="fw-bold"><?= e($ord['tourist_name']) ?></div>
                  <?php if ($ord['tourist_phone']): ?>
                  <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($ord['tourist_phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($ord['pickup_date']): ?>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-calendar me-1"></i>Pickup: <?= date('M d', strtotime($ord['pickup_date'])) ?>
                    <?= $ord['pickup_time'] ? ' at '.date('g:i A', strtotime($ord['pickup_time'])) : '' ?>
                  </div>
                  <?php endif; ?>
                  <?php if ($ord['pickup_code']): ?>
                  <div class="mt-1">
                    <span style="background:var(--green-pale);color:var(--green-dark);padding:.25rem .75rem;border-radius:8px;font-family:monospace;font-weight:700;font-size:.95rem;letter-spacing:.1em">
                      <?= e($ord['pickup_code']) ?>
                    </span>
                  </div>
                  <?php endif; ?>
                </div>

                <!-- Items -->
                <div class="col-sm-4">
                  <div class="small text-muted mb-1 fw-600">Items</div>
                  <?php foreach ($items as $item): ?>
                  <div class="small d-flex justify-content-between">
                    <span><?= e($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                    <span class="fw-600">₱<?= number_format($item['subtotal'], 2) ?></span>
                  </div>
                  <?php endforeach; ?>
                  <?php if ($ord['special_notes']): ?>
                  <div class="small text-muted mt-1 fst-italic">📝 <?= e($ord['special_notes']) ?></div>
                  <?php endif; ?>
                </div>

                <!-- Total + Actions -->
                <div class="col-sm-4 d-flex flex-column align-items-end justify-content-between">
                  <div class="text-end">
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold fs-5" style="color:var(--green-dark)">₱<?= number_format($ord['total_amount'],2) ?></div>
                  </div>

                  <!-- Status update buttons -->
                  <?php if ($ord['status'] === 'pending'): ?>
                  <div class="d-flex gap-2 mt-2">
                    <form method="POST">
                      <input type="hidden" name="action"   value="update_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <input type="hidden" name="status"   value="confirmed">
                      <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill)">
                        <i class="bi bi-check me-1"></i>Accept
                      </button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="action"   value="update_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <input type="hidden" name="status"   value="cancelled">
                      <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
                        Decline
                      </button>
                    </form>
                  </div>
                  <?php elseif ($ord['status'] === 'confirmed'): ?>
                  <form method="POST" class="mt-2">
                    <input type="hidden" name="action"   value="update_order">
                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                    <input type="hidden" name="status"   value="preparing">
                    <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--green-dark);font-weight:600;border-radius:var(--radius-pill)">
                      <i class="bi bi-fire me-1"></i>Start Preparing
                    </button>
                  </form>
                  <?php elseif ($ord['status'] === 'preparing'): ?>
                  <form method="POST" class="mt-2">
                    <input type="hidden" name="action"   value="update_order">
                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                    <input type="hidden" name="status"   value="ready">
                    <button class="btn btn-sm" style="background:var(--green-mid);color:#fff;border-radius:var(--radius-pill)">
                      <i class="bi bi-box-seam me-1"></i>Mark Ready
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

    <!-- ── PRODUCTS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="products">
      <div class="row g-4">

        <!-- Add product form -->
        <div class="col-lg-4">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--green-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-plus-circle me-2" style="color:var(--terracotta)"></i>Add New Product
            </h6>
            <form method="POST">
              <input type="hidden" name="action" value="add_product">
              <div class="mb-3">
                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="pname" placeholder="e.g. Brown Sugar Milk Tea" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="price" min="1" step="0.50" placeholder="0.00" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Category</label>
                <input type="text" class="form-control" name="pcat" placeholder="e.g. Drinks, Snacks, Meals">
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="pdesc" rows="2" style="resize:none"
                          placeholder="Short description…"></textarea>
              </div>
              <div class="mb-4">
                <label class="form-label">Stock</label>
                <input type="number" class="form-control" name="stock" value="999" min="1">
                <div class="form-text">Use 999 for unlimited stock.</div>
              </div>
              <button type="submit" class="btn btn-primary-app w-100">
                <i class="bi bi-plus-lg me-2"></i>Add Product
              </button>
            </form>
          </div>
        </div>

        <!-- Products list -->
        <div class="col-lg-8">
          <?php if (empty($products)): ?>
          <div class="text-center py-5">
            <i class="bi bi-box-seam fs-1 text-muted d-block mb-3"></i>
            <h5>No products yet</h5>
            <p class="text-muted">Add your first product using the form.</p>
          </div>
          <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($products as $p): ?>
            <div class="d-flex align-items-center gap-3 p-3"
                 style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                        opacity:<?= $p['is_available'] ? '1' : '.55' ?>">
              <div style="width:44px;height:44px;background:var(--green-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
                🛍️
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-bold" style="font-size:.93rem"><?= e($p['name']) ?></div>
                <div class="text-muted small">
                  <?= $p['category'] ? e($p['category']).' · ' : '' ?>
                  Stock: <?= $p['stock'] >= 999 ? '∞' : $p['stock'] ?>
                </div>
              </div>
              <div class="fw-bold" style="color:var(--terracotta);font-size:1rem;white-space:nowrap">
                ₱<?= number_format($p['price'],2) ?>
              </div>
              <div class="d-flex gap-2 flex-shrink-0">
                <!-- Toggle availability -->
                <form method="POST">
                  <input type="hidden" name="action"     value="toggle_product">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <button class="btn btn-sm <?= $p['is_available'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                          style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .75rem"
                          title="<?= $p['is_available'] ? 'Hide product' : 'Show product' ?>">
                    <?= $p['is_available'] ? '🙈 Hide' : '👁️ Show' ?>
                  </button>
                </form>
                <!-- Delete -->
                <form method="POST" onsubmit="return confirm('Delete this product?')">
                  <input type="hidden" name="action"     value="delete_product">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
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
              <i class="bi bi-pencil-square me-2"></i>Edit Shop Info
            </h6>
            <form method="POST">
              <input type="hidden" name="action" value="update_shop">
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" style="resize:none"><?= e($shop['description']) ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address" value="<?= e($shop['address']) ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone" value="<?= e($shop['phone']) ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="shop_email" value="<?= e($shop['email']) ?>">
                </div>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-sm-4">
                  <label class="form-label">Opening Time</label>
                  <input type="time" class="form-control" name="open_time" value="<?= e($shop['open_time']) ?>">
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Closing Time</label>
                  <input type="time" class="form-control" name="close_time" value="<?= e($shop['close_time']) ?>">
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Open Days</label>
                  <input type="text" class="form-control" name="open_days" value="<?= e($shop['open_days']) ?>">
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

// Fix match() PHP 7 note — the match in this file is PHP only,
// but update_order uses string concatenation fallback already.
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
