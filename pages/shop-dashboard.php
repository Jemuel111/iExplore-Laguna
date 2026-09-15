<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
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
            try {
                $photoUrl = handle_image_upload('photo', 'products');
            } catch (RuntimeException $e) {
                $_SESSION['flash']['error'] = $e->getMessage();
                header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
            }
            db_execute(
                "INSERT INTO shop_products (shop_id, name, description, image_url, price, category, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$sid, $pname, $pdesc, $photoUrl, $price, $cat, $stock]
            );
            $newId = db_last_id();
            if ($photoUrl && $newId) {
                db_execute("INSERT INTO product_photos (product_id, url, sort_order) VALUES (?,?,0)", [$newId, $photoUrl]);
            }
            $_SESSION['flash']['success'] = "Product \"{$pname}\" added!";
        }
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Update just a product's photo (existing product) ──────
    // ── Product gallery: upload one or more photos ──────────────
    // Scoped to $sid (this owner's own shop) — the product itself is
    // also re-checked against shop_id to prevent an owner from adding
    // photos to another shop's product by tampering with the form.
    if ($action === 'upload_product_photos') {
        $pid = (int) input('product_id', 'post', 0);
        $owns = db_fetch_one("SELECT id FROM shop_products WHERE id=? AND shop_id=?", [$pid, $sid]);
        if (!$owns) { header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit; }

        $uploaded = 0;
        $failed   = [];
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $fileCount = count($_FILES['photos']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $_FILES['__single_photo'] = [
                    'name' => $_FILES['photos']['name'][$i], 'type' => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i],
                    'size' => $_FILES['photos']['size'][$i],
                ];
                try {
                    $url = handle_image_upload('__single_photo', 'products');
                    if ($url) {
                        $sortOrder = db_fetch_one("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM product_photos WHERE product_id=?", [$pid])['n'] ?? 0;
                        db_execute("INSERT INTO product_photos (product_id, url, sort_order) VALUES (?,?,?)", [$pid, $url, $sortOrder]);
                        // First photo ever uploaded for this product also becomes its list-view cover.
                        $hasCover = db_fetch_one("SELECT image_url FROM shop_products WHERE id=?", [$pid]);
                        if (empty($hasCover['image_url'])) {
                            db_execute("UPDATE shop_products SET image_url=? WHERE id=?", [$url, $pid]);
                        }
                        $uploaded++;
                    }
                } catch (RuntimeException $e) {
                    $failed[] = $_FILES['photos']['name'][$i] . ': ' . $e->getMessage();
                }
            }
        }
        if ($uploaded > 0) $_SESSION['flash']['success'] = "Uploaded {$uploaded} photo" . ($uploaded!=1?'s':'') . "!";
        if (!empty($failed)) $_SESSION['flash']['error'] = 'Some files failed: ' . implode('; ', $failed);
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Product gallery: set a photo as the cover shown on cards ──
    if ($action === 'set_main_product_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        $photo = db_fetch_one(
            "SELECT pp.url FROM product_photos pp JOIN shop_products p ON pp.product_id=p.id
             WHERE pp.id=? AND p.shop_id=?", [$photoId, $sid]
        );
        if ($photo) {
            $pid = (int) input('product_id', 'post', 0);
            db_execute("UPDATE shop_products SET image_url=? WHERE id=? AND shop_id=?", [$photo['url'], $pid, $sid]);
        }
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#products'); exit;
    }

    // ── Product gallery: delete a photo ──────────────────────────
    if ($action === 'delete_product_photo') {
        $photoId = (int) input('photo_id', 'post', 0);
        $photo = db_fetch_one(
            "SELECT pp.url, pp.product_id FROM product_photos pp JOIN shop_products p ON pp.product_id=p.id
             WHERE pp.id=? AND p.shop_id=?", [$photoId, $sid]
        );
        if ($photo) {
            db_execute("DELETE FROM product_photos WHERE id=?", [$photoId]);
            // If the deleted photo was the cover, promote another remaining
            // gallery photo (if any) so the card never points at a dead URL.
            $product = db_fetch_one("SELECT image_url FROM shop_products WHERE id=?", [$photo['product_id']]);
            if ($product && $product['image_url'] === $photo['url']) {
                $next = db_fetch_one("SELECT url FROM product_photos WHERE product_id=? ORDER BY sort_order LIMIT 1", [$photo['product_id']]);
                db_execute("UPDATE shop_products SET image_url=? WHERE id=?", [$next['url'] ?? null, $photo['product_id']]);
            }
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
        $allowed = ['confirmed','preparing','ready','picked_up','cancelled'];
        if (in_array($status, $allowed)) {
            if ($status === 'confirmed') {
                $ts_col = ', confirmed_at = NOW()';
            } elseif ($status === 'ready') {
                $ts_col = ', ready_at = NOW()';
            } elseif ($status === 'picked_up') {
                $ts_col = ', picked_up_at = NOW()';
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
                    'confirmed' => ['Order Confirmed!', "Your order #{$order['order_number']} has been confirmed by the shop."],
                    'preparing' => ['Order Being Prepared', "Your order #{$order['order_number']} is now being prepared!"],
                    'ready'     => ['Ready for Pickup!', "Your order #{$order['order_number']} is ready. Show your pickup code: {$order['pickup_code']}"],
                    'picked_up' => ['Order Completed', "Your order #{$order['order_number']} has been picked up. Thanks for ordering!"],
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
        $coverUrl = null;
        try {
            $coverUrl = handle_image_upload('cover_photo', 'shops');
        } catch (RuntimeException $e) {
            $_SESSION['flash']['danger'] = $e->getMessage();
            header('Location: ' . APP_URL . '/pages/shop-dashboard.php#settings'); exit;
        }

        $sql    = "UPDATE shops SET description=?, address=?, phone=?, email=?, open_time=?, close_time=?, open_days=?";
        $params = [
            input('description','post',''),
            input('address','post',''),
            input('phone','post',''),
            input('shop_email','post',''),
            input('open_time','post',null) ?: null,
            input('close_time','post',null) ?: null,
            input('open_days','post',''),
        ];
        if ($coverUrl) {
            $sql .= ", cover_url=?";
            $params[] = $coverUrl;
        }
        $sql .= " WHERE id = ?";
        $params[] = $sid;

        db_execute($sql, $params);
        $_SESSION['flash']['success'] = 'Shop info updated.';
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php#settings'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$products = db_fetch_all(
    "SELECT * FROM shop_products WHERE shop_id = ? ORDER BY sort_order, name",
    [$sid]
);

// Per-product photo galleries, grouped by product_id for easy lookup in the cards below.
$product_photos_raw = db_fetch_all(
    "SELECT pp.* FROM product_photos pp JOIN shop_products p ON pp.product_id=p.id
     WHERE p.shop_id = ? ORDER BY pp.sort_order", [$sid]
);
$product_photos = [];
foreach ($product_photos_raw as $pp) { $product_photos[$pp['product_id']][] = $pp; }

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

// Order status colors/icons/labels now come from the shared
// order_status_meta() helper in helpers.php.

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
<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem"><i class="bi bi-shop"></i></div>
        <div>
          <h1 class="mb-0 fs-4" style="font-family:'Playfair Display',serif"><?= e($shop['name']) ?></h1>
          <p class="mb-0 small opacity-75">
            <i class="bi bi-geo-alt me-1"></i>Shop Dashboard
            <?php if (!$shop['is_verified']): ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem"><i class="bi bi-hourglass-split me-1"></i>Pending verification</span>
            <?php else: ?>
              <span class="badge ms-2" style="background:rgba(255,255,255,.2);font-size:.7rem"><i class="bi bi-check-circle-fill me-1"></i>Verified</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
      <?php if ($pending_count > 0): ?>
        <a href="#orders" class="btn btn-sm"
           style="background:#fff;color:var(--maroon-mid);font-weight:700;border-radius:var(--radius-pill)">
          <i class="bi bi-bell-fill me-1"></i><?= $pending_count ?> New Order<?= $pending_count > 1 ? 's' : '' ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats row -->
  <div class="stat-strip mb-4" style="border-radius:var(--radius)">
    <div class="row g-0 text-center">
    <?php
    $stats = [
      ['bi-box-seam',        "Today's Orders",   $today_orders],
      ['bi-hourglass-split', 'Pending Orders',   $pending_count],
      ['bi-bag',             'Products Listed',  $total_products],
      ['bi-cash-stack',      'Total Revenue',    '₱'.number_format($total_revenue,2)],
    ];
    foreach ($stats as [$ico,$lbl,$val]): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-item">
        <div class="stat-item-icon"><i class="bi <?= $ico ?>"></i></div>
        <div class="stat-num" style="font-size:1.5rem"><?= $val ?></div>
        <div class="stat-lbl"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>


  <!-- Nav tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--border)">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#orders" style="font-weight:600">
        <i class="bi bi-receipt me-1"></i>Orders
        <?php if ($pending_count): ?>
          <span class="badge rounded-pill ms-1" style="background:var(--maroon-mid);font-size:.7rem"><?= $pending_count ?></span>
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
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div class="input-group" style="max-width:340px">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="orders-search" class="form-control"
                   placeholder="Search by order #, customer, item, or status…">
          </div>
          <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">
            <span id="orders-visible-count"><?= count($orders) ?></span> / <?= count($orders) ?> shown
          </span>
        </div>
        <div class="d-flex flex-column gap-3" id="orders-list-body">
          <?php foreach ($orders as $ord):
            $st = order_status_meta($ord['status']);
            $bg = $st['bg']; $fg = $st['fg'];
            // Get items
            $items = db_fetch_all(
                "SELECT * FROM order_items WHERE order_id = ?",
                [$ord['id']]
            );
            $itemNames = implode(' ', array_column($items, 'product_name'));
            $orderSearchBlob = strtolower($ord['order_number'].' '.$ord['tourist_name'].' '.$ord['status'].' '.$itemNames);
          ?>
          <div class="p-0 rounded overflow-hidden order-row" data-search="<?= e($orderSearchBlob) ?>"
               style="border:1.5px solid var(--border);background:#fff">
            <!-- Order header -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3"
                 style="background:var(--cream);border-bottom:1px solid var(--border)">
              <div>
                <span class="fw-bold" style="font-family:'Playfair Display',serif"><?= e($ord['order_number']) ?></span>
                <span class="ms-2" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700">
                  <i class="bi <?= $st['icon'] ?> me-1"></i><?= ucfirst($ord['status']) ?>
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
                    <span style="background:var(--maroon-pale);color:var(--maroon-dark);padding:.25rem .75rem;border-radius:8px;font-family:monospace;font-weight:700;font-size:.95rem;letter-spacing:.1em">
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
                  <div class="small text-muted mt-1 fst-italic"><i class="bi bi-chat-left-text me-1"></i><?= e($ord['special_notes']) ?></div>
                  <?php endif; ?>
                </div>

                <!-- Total + Actions -->
                <div class="col-sm-4 d-flex flex-column align-items-end justify-content-between">
                  <div class="text-end">
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold fs-5" style="color:var(--maroon-dark)">₱<?= number_format($ord['total_amount'],2) ?></div>
                  </div>

                  <!-- Status update buttons -->
                  <?php if ($ord['status'] === 'pending'): ?>
                  <div class="d-flex gap-2 mt-2">
                    <form method="POST"><?= csrf_field() ?>
                      <input type="hidden" name="action"   value="update_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <input type="hidden" name="status"   value="confirmed">
                      <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill)">
                        <i class="bi bi-check me-1"></i>Accept
                      </button>
                    </form>
                    <form method="POST"><?= csrf_field() ?>
                      <input type="hidden" name="action"   value="update_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <input type="hidden" name="status"   value="cancelled">
                      <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill)">
                        Decline
                      </button>
                    </form>
                  </div>
                  <?php elseif ($ord['status'] === 'confirmed'): ?>
                  <form method="POST" class="mt-2"><?= csrf_field() ?>
                    <input type="hidden" name="action"   value="update_order">
                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                    <input type="hidden" name="status"   value="preparing">
                    <button class="btn btn-sm" style="background:var(--sand-dark);color:var(--maroon-dark);font-weight:600;border-radius:var(--radius-pill)">
                      <i class="bi bi-fire me-1"></i>Start Preparing
                    </button>
                  </form>
                  <?php elseif ($ord['status'] === 'preparing'): ?>
                  <form method="POST" class="mt-2"><?= csrf_field() ?>
                    <input type="hidden" name="action"   value="update_order">
                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                    <input type="hidden" name="status"   value="ready">
                    <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill)">
                      <i class="bi bi-box-seam me-1"></i>Mark Ready
                    </button>
                  </form>
                  <?php elseif ($ord['status'] === 'ready'): ?>
                  <form method="POST" class="mt-2"><?= csrf_field() ?>
                    <input type="hidden" name="action"   value="update_order">
                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                    <input type="hidden" name="status"   value="picked_up">
                    <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill)">
                      <i class="bi bi-check2-circle me-1"></i>Mark Picked Up
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
          <button type="button" id="orders-show-more" class="btn btn-outline-secondary btn-sm">
            Show more <i class="bi bi-chevron-down ms-1"></i>
          </button>
          <div id="orders-no-results" class="text-muted small py-3 d-none">
            <i class="bi bi-search me-1"></i>No orders match "<span id="orders-no-results-query"></span>".
          </div>
        </div>
        <script>
        (function () {
          const PAGE_SIZE = 10;
          const rows = Array.from(document.querySelectorAll('#orders-list-body .order-row'));
          const searchInput = document.getElementById('orders-search');
          const showMoreBtn = document.getElementById('orders-show-more');
          const visibleCountEl = document.getElementById('orders-visible-count');
          const noResultsEl = document.getElementById('orders-no-results');
          const noResultsQueryEl = document.getElementById('orders-no-results-query');
          let shownCount = PAGE_SIZE;

          function renderPaged() {
            rows.forEach((row, i) => { row.style.display = i < shownCount ? '' : 'none'; });
            visibleCountEl.textContent = Math.min(shownCount, rows.length);
            showMoreBtn.classList.toggle('d-none', shownCount >= rows.length);
            noResultsEl.classList.add('d-none');
          }
          function renderSearch(query) {
            let matches = 0;
            rows.forEach(row => {
              const isMatch = row.dataset.search.includes(query);
              row.style.display = isMatch ? '' : 'none';
              if (isMatch) matches++;
            });
            visibleCountEl.textContent = matches;
            showMoreBtn.classList.add('d-none');
            noResultsEl.classList.toggle('d-none', matches > 0);
            noResultsQueryEl.textContent = query;
          }
          showMoreBtn.addEventListener('click', () => {
            shownCount = Math.min(shownCount + PAGE_SIZE, rows.length);
            renderPaged();
          });
          searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            if (query) renderSearch(query); else { shownCount = PAGE_SIZE; renderPaged(); }
          });
          renderPaged();
        })();
        </script>
      <?php endif; ?>
    </div>

    <!-- ── PRODUCTS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="products">
      <div class="row g-4">

        <!-- Add product form -->
        <div class="col-lg-4">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--maroon-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-plus-circle me-2" style="color:var(--maroon-mid)"></i>Add New Product
            </h6>
            <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
              <input type="hidden" name="action" value="add_product">
              <div class="mb-3">
                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="pname" placeholder="e.g. Brown Sugar Milk Tea" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Photo</label>
                <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WEBP. Max 3MB. Optional, but products with photos get picked more often.</div>
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
            <?php foreach ($products as $p): $pPhotos = $product_photos[$p['id']] ?? []; ?>
            <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;
                        opacity:<?= $p['is_available'] ? '1' : '.55' ?>">
              <div class="d-flex align-items-center gap-3 p-3">
                <?php if (!empty($p['image_url'])): ?>
                  <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>"
                       style="width:44px;height:44px;object-fit:cover;border-radius:10px;flex-shrink:0">
                <?php else: ?>
                  <div style="width:44px;height:44px;background:var(--maroon-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--maroon-mid);flex-shrink:0">
                    <i class="bi bi-bag"></i>
                  </div>
                <?php endif; ?>
                <div class="flex-grow-1 min-w-0">
                  <div class="fw-bold" style="font-size:.93rem"><?= e($p['name']) ?></div>
                  <div class="text-muted small">
                    <?= $p['category'] ? e($p['category']).' · ' : '' ?>
                    Stock: <?= $p['stock'] >= 999 ? '∞' : $p['stock'] ?>
                  </div>
                </div>
                <div class="fw-bold" style="color:var(--maroon-mid);font-size:1rem;white-space:nowrap">
                  ₱<?= number_format($p['price'],2) ?>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                  <!-- Manage photos toggle -->
                  <button type="button" class="btn btn-sm btn-outline-secondary gallery-toggle-btn"
                          style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .7rem"
                          data-target="#product-gallery-<?= $p['id'] ?>">
                    <i class="bi bi-images me-1"></i>Photos<?= count($pPhotos) ? ' ('.count($pPhotos).')' : '' ?>
                  </button>
                  <!-- Toggle availability -->
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action"     value="toggle_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-sm <?= $p['is_available'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .75rem"
                            title="<?= $p['is_available'] ? 'Hide product' : 'Show product' ?>">
                      <i class="bi <?= $p['is_available'] ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= $p['is_available'] ? 'Hide' : 'Show' ?>
                    </button>
                  </form>
                  <!-- Delete -->
                  <form method="POST" onsubmit="return confirm('Delete this product?')"><?= csrf_field() ?>
                    <input type="hidden" name="action"     value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .6rem">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Expandable photo gallery manager -->
              <div id="product-gallery-<?= $p['id'] ?>" class="d-none gallery-panel" style="background:var(--sand);padding:1rem;border-top:1px solid var(--border)">
                <?php if ($pPhotos): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <?php foreach ($pPhotos as $ph): ?>
                    <div style="position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:1.5px solid var(--border)">
                      <img src="<?= e($ph['url']) ?>" style="width:100%;height:100%;object-fit:cover">
                      <?php if ($ph['url'] === $p['image_url']): ?>
                        <span class="badge" style="position:absolute;top:2px;left:2px;background:var(--maroon-mid);font-size:.55rem">Cover</span>
                      <?php endif; ?>
                      <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);padding:.15rem;display:flex;gap:.25rem;justify-content:center">
                        <?php if ($ph['url'] !== $p['image_url']): ?>
                          <form method="POST" class="m-0"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_main_product_photo">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                            <button class="btn btn-sm btn-light" style="font-size:.6rem;padding:.05rem .35rem" title="Set as cover"><i class="bi bi-star"></i></button>
                          </form>
                        <?php endif; ?>
                        <form method="POST" class="m-0" onsubmit="return confirm('Remove this photo?')"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_product_photo">
                          <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                          <button class="btn btn-sm btn-outline-light" style="font-size:.6rem;padding:.05rem .35rem" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                  <p class="small text-muted mb-3">No photos yet — add some so tourists can see what this product actually looks like.</p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="upload_product_photos">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm" style="max-width:280px">
                  <button type="submit" class="btn btn-sm" style="background:var(--maroon-mid);color:#fff">Upload</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <script>
    document.querySelectorAll('.gallery-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelector(btn.dataset.target).classList.toggle('d-none');
      });
    });
    </script>

    <!-- ── SETTINGS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="settings">
      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="form-panel">
            <h6 class="fw-bold mb-3" style="color:var(--maroon-dark);font-family:'Playfair Display',serif">
              <i class="bi bi-pencil-square me-2"></i>Edit Shop Info
            </h6>
            <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
              <input type="hidden" name="action" value="update_shop">

              <div class="mb-3">
                <label class="form-label">Cover Photo</label>
                <?php if (!empty($shop['cover_url'])): ?>
                <img src="<?= e($shop['cover_url']) ?>" alt="Current cover"
                     class="d-block mb-2" style="width:100%;max-height:180px;object-fit:cover;border-radius:var(--radius-sm)">
                <?php endif; ?>
                <input type="file" class="form-control" name="cover_photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WEBP — max 3MB. Leave empty to keep the current photo.</div>
              </div>

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
