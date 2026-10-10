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

// Product categories shown in the dashboard (add form, filter, grouping).
$PRODUCT_CATEGORIES = ['Main Course', 'Side Dishes', 'Snacks', 'Drinks', 'Desserts', 'Others'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    // ── Add product ──────────────────────────────────────────
    if ($action === 'add_product') {
        $pname  = trim(input('pname',  'post', ''));
        $price  = (float) input('price',  'post', 0);
        $cat    = trim(input('pcat',   'post', ''));
        if (!in_array($cat, $PRODUCT_CATEGORIES, true)) { $cat = 'Others'; }
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

    // ── Change a product's category ───────────────────────────
    if ($action === 'set_product_category') {
        $pid = (int) input('product_id', 'post', 0);
        $cat = trim(input('pcat', 'post', ''));
        if (in_array($cat, $PRODUCT_CATEGORIES, true)) {
            db_execute("UPDATE shop_products SET category = ? WHERE id = ? AND shop_id = ?", [$cat, $pid, $sid]);
            $_SESSION['flash']['success'] = 'Category updated.';
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
// ── Revenue ledger ───────────────────────────────────────────
// Revenue = completed (picked-up) orders only, dated by the day they were picked up.
$ts_sub = "(SELECT total_amount, COALESCE(picked_up_at, updated_at) AS ts
              FROM orders WHERE shop_id = ? AND status = 'picked_up') t";
$rev = db_fetch_one(
    "SELECT
        COALESCE(SUM(CASE WHEN DATE(ts) = CURDATE() THEN total_amount END), 0)                          AS day_rev,
        COALESCE(SUM(DATE(ts) = CURDATE()), 0)                                                          AS day_cnt,
        COALESCE(SUM(CASE WHEN DATE(ts) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN total_amount END), 0) AS day_prev,
        COALESCE(SUM(CASE WHEN YEARWEEK(ts, 1) = YEARWEEK(CURDATE(), 1) THEN total_amount END), 0)      AS week_rev,
        COALESCE(SUM(YEARWEEK(ts, 1) = YEARWEEK(CURDATE(), 1)), 0)                                      AS week_cnt,
        COALESCE(SUM(CASE WHEN YEARWEEK(ts, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)
                          THEN total_amount END), 0)                                                    AS week_prev,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                          THEN total_amount END), 0)                                                    AS month_rev,
        COALESCE(SUM(DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')), 0)                    AS month_cnt,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(ts, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')
                          THEN total_amount END), 0)                                                    AS month_prev
     FROM {$ts_sub}",
    [$sid]
) ?: [];
// key => [label, amount, completed orders, label of previous period, previous amount]
$periods = [
    'day'   => ['Today',      $rev['day_rev']   ?? 0, $rev['day_cnt']   ?? 0, 'Yesterday',  $rev['day_prev']   ?? 0],
    'week'  => ['This week',  $rev['week_rev']  ?? 0, $rev['week_cnt']  ?? 0, 'Last week',  $rev['week_prev']  ?? 0],
    'month' => ['This month', $rev['month_rev'] ?? 0, $rev['month_cnt'] ?? 0, 'Last month', $rev['month_prev'] ?? 0],
];

// Daily revenue for the last 14 days (the bars under the headline figure).
$db_today  = db_fetch_one("SELECT CURDATE() AS d")['d'] ?? date('Y-m-d');
$daily_raw = db_fetch_all(
    "SELECT DATE(ts) AS d, SUM(total_amount) AS amt FROM {$ts_sub}
     WHERE DATE(ts) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(ts)",
    [$sid]
);
$daily_map = array_column($daily_raw, 'amt', 'd');
$daily = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("{$db_today} -{$i} day"));
    $daily[] = ['d' => $d, 'amt' => (float) ($daily_map[$d] ?? 0)];
}
$daily_max = max(array_column($daily, 'amt'));

// Orders that need the owner: oldest pending first, plus a count of what is in progress.
$queue = db_fetch_all(
    "SELECT o.id, o.pickup_code, o.total_amount, u.name AS tourist_name
       FROM orders o JOIN users u ON o.tourist_id = u.id
      WHERE o.shop_id = ? AND o.status = 'pending'
      ORDER BY o.created_at ASC LIMIT 4",
    [$sid]
);
foreach ($queue as &$q_) {
    $its = db_fetch_all("SELECT product_name, quantity FROM order_items WHERE order_id = ?", [$q_['id']]);
    $q_['summary'] = implode(', ', array_map(fn($i) => $i['quantity'] . ' × ' . $i['product_name'], $its));
}
unset($q_);
$inprog = array_column(db_fetch_all(
    "SELECT status, COUNT(*) AS n FROM orders
      WHERE shop_id = ? AND status IN ('confirmed','preparing','ready') GROUP BY status", [$sid]
), 'n', 'status');

$chip_labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing',
                'ready' => 'Ready', 'picked_up' => 'Picked up', 'cancelled' => 'Cancelled'];
$status_counts = array_count_values(array_column($orders, 'status'));

/** One-button form that moves an order to a new status. */
function sd_order_btn(int $oid, string $status, string $label, string $cls = 'sd-btn'): string {
    return '<form method="POST" class="m-0">' . csrf_field()
         . '<input type="hidden" name="action" value="update_order">'
         . '<input type="hidden" name="order_id" value="' . $oid . '">'
         . '<input type="hidden" name="status" value="' . e($status) . '">'
         . '<button class="' . e($cls) . '">' . e($label) . '</button></form>';
}

// Group products by category: preset categories first (in order), then any
// older free-text categories, then anything uncategorised.
$product_groups = [];
foreach ($PRODUCT_CATEGORIES as $c) { $product_groups[$c] = []; }
$legacy = [];
foreach ($products as $p) {
    $c = trim((string) ($p['category'] ?? ''));
    $match = null;
    foreach ($PRODUCT_CATEGORIES as $pc) { if (strcasecmp($pc, $c) === 0) { $match = $pc; break; } }
    if ($match !== null)  { $product_groups[$match][] = $p; }
    elseif ($c !== '')    { $legacy[$c][] = $p; }
    else                  { $legacy['Uncategorized'][] = $p; }
}
ksort($legacy, SORT_NATURAL | SORT_FLAG_CASE);
if (isset($legacy['Uncategorized'])) { $u_ = $legacy['Uncategorized']; unset($legacy['Uncategorized']); $legacy['Uncategorized'] = $u_; }
foreach ($legacy as $c => $list) { $product_groups[$c] = $list; }
$product_groups = array_filter($product_groups, fn($l) => !empty($l));
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

<style>
/* Shop dashboard — uses the site's tokens (app.css), scoped to .sd */
.sd .sd-head{padding:.5rem 0 1.75rem}
.sd .sd-title{font-size:clamp(1.9rem,4.2vw,2.6rem);margin:0;color:var(--charcoal)}
.sd .sd-sub{margin:.3rem 0 0;color:var(--text-muted);font-size:.92rem}
.sd .sd-sub i{color:var(--maroon-mid)}
.sd .sd-top{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:2.25rem;margin-bottom:2.75rem;align-items:start}
@media (max-width:991.98px){.sd .sd-top{grid-template-columns:1fr;gap:2rem}}

/* Revenue ledger */
.sd .sd-ledger{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.4rem 1.75rem 1.5rem}
.sd .sd-periods{display:flex;gap:1.5rem;border-bottom:1px solid var(--border);margin-bottom:1.4rem}
.sd .sd-periods button{background:none;border:0;border-bottom:2px solid transparent;margin-bottom:-1px;padding:.2rem 0 .65rem;
  font:inherit;font-weight:600;color:var(--text-muted);cursor:pointer}
.sd .sd-periods button:hover{color:var(--maroon-dark)}
.sd .sd-periods button[aria-selected="true"]{color:var(--maroon-dark);border-bottom-color:var(--maroon-mid)}
.sd .sd-figure{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:clamp(2.5rem,6vw,3.5rem);
  line-height:1.05;color:var(--charcoal);font-variant-numeric:lining-nums}
.sd .sd-peso{color:var(--maroon-mid);margin-right:.15em}
.sd .sd-note{margin:.55rem 0 0;color:var(--text-muted);font-size:.93rem}
.sd .sd-cap{margin:1.6rem 0 .5rem;font-size:.82rem;color:var(--text-muted)}
.sd .sd-bars{display:flex;gap:5px;height:104px;border-bottom:1px solid var(--border)}
.sd .sd-bar-col{flex:1;display:flex;flex-direction:column;min-width:0}
.sd .sd-bar-wrap{flex:1;display:flex;align-items:flex-end;justify-content:center;min-height:0}
.sd .sd-bar{width:100%;max-width:24px;min-height:2px;background:var(--sand-dark);opacity:.6;border-radius:3px 3px 0 0}
.sd .sd-bar-col.is-today .sd-bar{background:var(--maroon-mid);opacity:1}
.sd .sd-days{display:flex;gap:5px;margin-top:.3rem}
.sd .sd-days span{flex:1;text-align:center;font-size:.72rem;color:var(--text-muted)}
.sd .sd-days span.is-today{color:var(--maroon-dark);font-weight:600}
.sd .sd-empty-bars{margin:0;padding:2.2rem 0 1rem;color:var(--text-muted);font-size:.9rem;border-bottom:1px solid var(--border)}

/* Waiting on you */
.sd .sd-h{font-size:1.3rem;margin:0 0 .15rem;color:var(--charcoal)}
.sd .sd-q-lede{margin:0 0 .5rem;color:var(--text-muted);font-size:.9rem}
.sd .sd-q-item{padding:.95rem 0;border-top:1px solid var(--border)}
.sd .sd-q-row{display:flex;justify-content:space-between;align-items:baseline;gap:1rem}
.sd .sd-q-code{font-weight:600;letter-spacing:.08em;color:var(--maroon-dark);font-variant-numeric:tabular-nums}
.sd .sd-q-name{font-weight:600;margin-left:.6rem}
.sd .sd-q-total{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:1.1rem}
.sd .sd-q-items{margin:.15rem 0 .65rem;color:var(--text-muted);font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sd .sd-q-actions{display:flex;gap:.5rem}
.sd .sd-q-foot{padding-top:.85rem;border-top:1px solid var(--border);color:var(--text-muted);font-size:.88rem}
.sd .sd-q-foot p{margin:0 0 .25rem}
.sd .sd-q-empty{padding:1.1rem 0;border-top:1px solid var(--border);color:var(--text-muted);font-size:.93rem}

/* Buttons */
.sd .sd-btn,.sd .sd-btn-sand,.sd .sd-btn-quiet{border:1px solid transparent;border-radius:50px;padding:.38rem 1.05rem;font-size:.85rem;font-weight:600;line-height:1.4;cursor:pointer;transition:background var(--transition),color var(--transition)}
.sd .sd-btn{background:var(--maroon-mid);color:#fff}
.sd .sd-btn:hover{background:var(--maroon-dark)}
.sd .sd-btn-sand{background:var(--sand-dark);color:var(--charcoal)}
.sd .sd-btn-sand:hover{background:#dcb558}
.sd .sd-btn-quiet{background:transparent;color:var(--text-muted);border-color:var(--border)}
.sd .sd-btn-quiet:hover{color:var(--maroon-dark);border-color:var(--maroon-mid)}
.sd button:focus-visible,.sd .nav-link:focus-visible,.sd input:focus-visible{outline:2px solid var(--maroon-dark);outline-offset:2px}

/* Tabs */
.sd .nav-tabs{border-bottom:1px solid var(--border);gap:1.9rem;margin-bottom:1.75rem}
.sd .nav-tabs .nav-link{border:0;border-bottom:2px solid transparent;border-radius:0;background:none;margin-bottom:-1px;padding:.55rem 0;font-weight:600;color:var(--text-muted)}
.sd .nav-tabs .nav-link:hover{color:var(--maroon-dark);border-bottom-color:transparent}
.sd .nav-tabs .nav-link.active{color:var(--maroon-dark);background:none;border-bottom-color:var(--maroon-mid)}
.sd .sd-tabn{margin-left:.35rem;font-weight:500;color:var(--text-muted)}
.sd .sd-tabn.is-alert{background:var(--maroon-mid);color:#fff;border-radius:50px;padding:.05rem .5rem;font-size:.75rem;font-weight:600}

/* Order filters */
.sd .sd-filters{display:flex;flex-wrap:wrap;gap:1rem 1.5rem;align-items:center;justify-content:space-between;margin-bottom:.4rem}
.sd .sd-chips{display:flex;flex-wrap:wrap;gap:.4rem}
.sd .sd-chip{background:#fff;border:1px solid var(--border);border-radius:50px;padding:.25rem .85rem;font-size:.83rem;font-weight:600;color:var(--charcoal);cursor:pointer}
.sd .sd-chip:hover{border-color:var(--maroon-mid)}
.sd .sd-chip[aria-pressed="true"]{background:var(--maroon-pale);border-color:var(--maroon-mid);color:var(--maroon-dark)}
.sd .sd-chip span{font-weight:500;color:var(--text-muted);margin-left:.25rem}
.sd .sd-search{position:relative;min-width:240px}
.sd .sd-search i{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--text-muted)}
.sd .sd-search input{width:100%;padding:.45rem .9rem .45rem 2.2rem;border:1px solid var(--border);border-radius:50px;background:#fff;font-size:.9rem}
.sd .sd-countline{margin:.7rem 0 1rem;color:var(--text-muted);font-size:.85rem}

/* Pickup slips */
.sd .slip{display:flex;background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:.9rem}
.sd .slip[hidden]{display:none}
.sd .slip-stub{position:relative;flex:0 0 138px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.2rem;
  padding:1rem .75rem;background:var(--sand);border-right:2px dashed #d8cfc2}
.sd .slip-stub::before,.sd .slip-stub::after{content:"";position:absolute;right:-9px;width:16px;height:16px;border-radius:50%;
  background:var(--cream);border:1px solid var(--border)}
.sd .slip-stub::before{top:-9px}
.sd .slip-stub::after{bottom:-9px}
.sd .slip-code{font-weight:700;font-size:1.2rem;letter-spacing:.09em;color:var(--maroon-dark);font-variant-numeric:tabular-nums}
.sd .slip-stub small{font-size:.72rem;color:var(--text-muted)}
.sd .slip[data-status="pending"] .slip-stub{background:var(--maroon-mid);border-right-color:rgba(255,255,255,.55)}
.sd .slip[data-status="pending"] .slip-code,.sd .slip[data-status="pending"] .slip-stub small{color:#fff}
.sd .slip[data-status="ready"] .slip-stub{background:var(--sand-dark)}
.sd .slip[data-status="ready"] .slip-code{color:var(--charcoal)}
.sd .slip[data-status="picked_up"],.sd .slip[data-status="cancelled"]{opacity:.72}
.sd .slip[data-status="cancelled"] .slip-code{text-decoration:line-through}
.sd .slip-main{flex:1;min-width:0;padding:1rem 1.3rem 1.05rem}
.sd .slip-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}
.sd .slip-name{font-weight:600;font-size:1.02rem}
.sd .slip-meta{font-size:.84rem;color:var(--text-muted);line-height:1.5}
.sd .slip-status{font-size:.84rem;font-weight:600;white-space:nowrap}
.sd .slip-items{list-style:none;margin:.8rem 0 0;padding:0}
.sd .slip-items li{display:flex;align-items:baseline;gap:.5rem;font-size:.92rem;line-height:1.7}
.sd .slip-items .lead{flex:1;border-bottom:1px dotted #c9bfb0;min-width:1rem;transform:translateY(-.2em)}
.sd .slip-items .amt{font-variant-numeric:tabular-nums}
.sd .slip-note{margin:.45rem 0 0;font-size:.86rem;color:var(--text-muted);font-style:italic}
.sd .slip-foot{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin-top:.95rem}
.sd .slip-total{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:1.5rem;line-height:1.1}
.sd .slip-when{display:block;font-family:'DM Sans',system-ui,sans-serif;font-weight:400;font-size:.78rem;color:var(--text-muted);margin-top:.2rem}
.sd .slip-actions{display:flex;gap:.5rem;flex-wrap:wrap}
@media (max-width:575.98px){
  .sd .slip{flex-direction:column}
  .sd .slip-stub{flex:none;flex-direction:row;gap:.6rem;border-right:0;border-bottom:2px dashed #d8cfc2;padding:.7rem 1rem}
  .sd .slip[data-status="pending"] .slip-stub{border-bottom-color:rgba(255,255,255,.55)}
  .sd .slip-stub::before,.sd .slip-stub::after{right:auto;top:auto;bottom:-9px}
  .sd .slip-stub::before{left:-9px}
  .sd .slip-stub::after{right:-9px}
  .sd .sd-search{min-width:100%}
}

/* Products */
.sd .sd-plist{background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.sd .sd-prow{border-bottom:1px solid var(--border)}
.sd .sd-prow:last-child{border-bottom:0}
.sd .cat-pill{background:#fff;border:1px solid var(--border);color:var(--charcoal);font-weight:600}
.sd .cat-pill:hover{border-color:var(--maroon-mid)}
.sd .cat-pill.active{background:var(--maroon-pale);border-color:var(--maroon-mid);color:var(--maroon-dark)}
.sd .sd-cat-h{font-size:1.15rem;margin:0 0 .6rem;color:var(--charcoal)}
.sd .sd-cat-h span{font-family:'DM Sans',system-ui,sans-serif;font-size:.85rem;font-weight:400;color:var(--text-muted);margin-left:.4rem}
</style>

<div class="container py-4 sd">

  <header class="sd-head">
    <h1 class="sd-title"><?= e($shop['name']) ?></h1>
    <p class="sd-sub">
      <?php if ($shop['is_verified']): ?>
        <i class="bi bi-patch-check-fill me-1"></i>Verified shop. Tourists can find you and place orders.
      <?php else: ?>
        <i class="bi bi-hourglass-split me-1"></i>Waiting for admin verification. Tourists can't see your shop yet.
      <?php endif; ?>
    </p>
  </header>

  <div class="sd-top">

    <!-- Revenue ledger -->
    <section class="sd-ledger" aria-label="Revenue">
      <div class="sd-periods" role="tablist">
        <?php $first = true; foreach ($periods as $key => $per): ?>
          <button type="button" role="tab" data-period="<?= $key ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= $per[0] ?></button>
        <?php $first = false; endforeach; ?>
      </div>

      <?php $first = true; foreach ($periods as $key => [$pLabel, $pAmt, $pCnt, $prevLabel, $prevAmt]): ?>
      <div class="sd-period" data-period="<?= $key ?>" <?= $first ? '' : 'hidden' ?>>
        <div class="sd-figure"><span class="sd-peso">₱</span><?= number_format((float) $pAmt, 2) ?></div>
        <p class="sd-note">
          <?= (int) $pCnt ?> <?= (int) $pCnt === 1 ? 'order' : 'orders' ?> picked up.
          <?= $prevLabel ?>: ₱<?= number_format((float) $prevAmt, 2) ?>.
        </p>
      </div>
      <?php $first = false; endforeach; ?>

      <p class="sd-cap">Daily revenue, last 14 days</p>
      <?php if ($daily_max > 0): ?>
        <div class="sd-bars">
          <?php foreach ($daily as $day): $isToday = $day['d'] === $db_today; ?>
            <div class="sd-bar-col <?= $isToday ? 'is-today' : '' ?>"
                 title="<?= e(date('D, M j', strtotime($day['d']))) ?>: ₱<?= number_format($day['amt'], 2) ?>">
              <div class="sd-bar-wrap"><div class="sd-bar" style="height:<?= max(2, round($day['amt'] / $daily_max * 100)) ?>%"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="sd-days" aria-hidden="true">
          <?php foreach ($daily as $day): ?>
            <span class="<?= $day['d'] === $db_today ? 'is-today' : '' ?>"><?= substr(date('D', strtotime($day['d'])), 0, 1) ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="sd-empty-bars">Nothing picked up in the last 14 days. Revenue shows up here once you mark an order as picked up.</p>
      <?php endif; ?>
    </section>

    <!-- Waiting on you -->
    <section aria-label="Orders waiting for you">
      <h2 class="sd-h">Waiting on you</h2>
      <?php if ($queue): ?>
        <p class="sd-q-lede"><?= (int) $pending_count ?> new <?= (int) $pending_count === 1 ? 'order needs' : 'orders need' ?> an answer.</p>
        <?php foreach ($queue as $q): ?>
          <div class="sd-q-item">
            <div class="sd-q-row">
              <div><span class="sd-q-code"><?= e($q['pickup_code'] ?: '—') ?></span><span class="sd-q-name"><?= e($q['tourist_name']) ?></span></div>
              <span class="sd-q-total">₱<?= number_format((float) $q['total_amount'], 2) ?></span>
            </div>
            <div class="sd-q-items"><?= e($q['summary']) ?></div>
            <div class="sd-q-actions">
              <?= sd_order_btn((int) $q['id'], 'confirmed', 'Accept') ?>
              <?= sd_order_btn((int) $q['id'], 'cancelled', 'Decline', 'sd-btn-quiet') ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($pending_count > count($queue)): ?>
          <div class="sd-q-foot"><p><?= (int) $pending_count - count($queue) ?> more in the Orders tab.</p></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="sd-q-empty">Nothing waiting. New orders from tourists appear here as soon as they're placed.</div>
      <?php endif; ?>

      <?php
        $prog = [];
        if (!empty($inprog['confirmed'])) $prog[] = $inprog['confirmed'] . ' accepted';
        if (!empty($inprog['preparing'])) $prog[] = $inprog['preparing'] . ' being prepared';
        if (!empty($inprog['ready']))     $prog[] = $inprog['ready'] . ' ready for pickup';
      ?>
      <div class="sd-q-foot">
        <?php if ($prog): ?><p>In progress: <?= implode(', ', $prog) ?>.</p><?php endif; ?>
        <p><?= (int) $today_orders ?> <?= (int) $today_orders === 1 ? 'order' : 'orders' ?> placed today.</p>
      </div>
    </section>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#orders">Orders<?php if ($pending_count): ?><span class="sd-tabn is-alert"><?= (int) $pending_count ?></span><?php endif; ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#products">Products<span class="sd-tabn"><?= (int) $total_products ?></span></a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#settings">Shop settings</a>
    </li>
  </ul>

  <script>
  document.querySelectorAll('.sd-periods button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.sd-periods button').forEach(b => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
      document.querySelectorAll('.sd-period').forEach(p => { p.hidden = p.dataset.period !== btn.dataset.period; });
    });
  });
  </script>

  <div class="tab-content">

    <!-- ── ORDERS TAB ──────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="orders">
      <?php if (empty($orders)): ?>
        <div class="py-5 text-center">
          <h5 class="mb-1">No orders yet</h5>
          <p class="text-muted mb-0">When a tourist orders from your shop, the order slip appears here with its pickup code.</p>
        </div>
      <?php else: ?>
        <div class="sd-filters">
          <div class="sd-chips" id="orders-chips">
            <button type="button" class="sd-chip" data-status="all" aria-pressed="true">All<span><?= count($orders) ?></span></button>
            <?php foreach ($chip_labels as $stKey => $stLabel): if (empty($status_counts[$stKey])) continue; ?>
              <button type="button" class="sd-chip" data-status="<?= $stKey ?>" aria-pressed="false"><?= $stLabel ?><span><?= $status_counts[$stKey] ?></span></button>
            <?php endforeach; ?>
          </div>
          <label class="sd-search">
            <i class="bi bi-search"></i>
            <input type="search" id="orders-search" placeholder="Find an order, customer or item" aria-label="Search orders">
          </label>
        </div>
        <p class="sd-countline" id="orders-count"></p>

        <div id="orders-list-body">
          <?php foreach ($orders as $ord):
            $st    = order_status_meta($ord['status']);
            $items = db_fetch_all("SELECT * FROM order_items WHERE order_id = ?", [$ord['id']]);
            $itemNames = implode(' ', array_column($items, 'product_name'));
            $blob  = strtolower($ord['order_number'].' '.$ord['tourist_name'].' '.$ord['pickup_code'].' '.$ord['status'].' '.$itemNames);
            $oid   = (int) $ord['id'];
          ?>
          <article class="slip order-row" data-status="<?= e($ord['status']) ?>" data-search="<?= e($blob) ?>">
            <div class="slip-stub">
              <span class="slip-code"><?= e($ord['pickup_code'] ?: '—') ?></span>
              <small>pickup code</small>
            </div>
            <div class="slip-main">
              <div class="slip-top">
                <div>
                  <div class="slip-name"><?= e($ord['tourist_name']) ?></div>
                  <div class="slip-meta">
                    <?php if ($ord['tourist_phone']): ?><?= e($ord['tourist_phone']) ?><br><?php endif; ?>
                    <?php if ($ord['pickup_date']): ?>
                      Pickup <?= date('D, M j', strtotime($ord['pickup_date'])) ?><?= $ord['pickup_time'] ? ' at '.date('g:i A', strtotime($ord['pickup_time'])) : '' ?>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="slip-status" style="color:<?= $st['fg'] ?>"><i class="bi <?= $st['icon'] ?> me-1"></i><?= e($chip_labels[$ord['status']] ?? ucfirst($ord['status'])) ?></span>
              </div>

              <ul class="slip-items">
                <?php foreach ($items as $item): ?>
                  <li><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><span class="lead"></span><span class="amt">₱<?= number_format($item['subtotal'], 2) ?></span></li>
                <?php endforeach; ?>
              </ul>
              <?php if ($ord['special_notes']): ?>
                <p class="slip-note">Note from customer: <?= e($ord['special_notes']) ?></p>
              <?php endif; ?>

              <div class="slip-foot">
                <div class="slip-total">₱<?= number_format($ord['total_amount'], 2) ?>
                  <span class="slip-when">Order <?= e($ord['order_number']) ?>, placed <?= date('M j, g:i A', strtotime($ord['created_at'])) ?></span>
                </div>
                <div class="slip-actions">
                  <?php if ($ord['status'] === 'pending'): ?>
                    <?= sd_order_btn($oid, 'confirmed', 'Accept') ?>
                    <?= sd_order_btn($oid, 'cancelled', 'Decline', 'sd-btn-quiet') ?>
                  <?php elseif ($ord['status'] === 'confirmed'): ?>
                    <?= sd_order_btn($oid, 'preparing', 'Start preparing', 'sd-btn-sand') ?>
                  <?php elseif ($ord['status'] === 'preparing'): ?>
                    <?= sd_order_btn($oid, 'ready', 'Mark ready') ?>
                  <?php elseif ($ord['status'] === 'ready'): ?>
                    <?= sd_order_btn($oid, 'picked_up', 'Mark picked up') ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <div class="text-center mt-3">
          <button type="button" id="orders-show-more" class="sd-btn-quiet">Show more orders</button>
          <p id="orders-no-results" class="text-muted py-3 mb-0" hidden>No orders match. Try a different status or search.</p>
        </div>

        <script>
        (function () {
          const PAGE = 10;
          const rows   = Array.from(document.querySelectorAll('#orders-list-body .order-row'));
          const chips  = Array.from(document.querySelectorAll('#orders-chips .sd-chip'));
          const search = document.getElementById('orders-search');
          const more   = document.getElementById('orders-show-more');
          const none   = document.getElementById('orders-no-results');
          const count  = document.getElementById('orders-count');
          let status = 'all', query = '', shown = PAGE;

          function apply() {
            const matches = rows.filter(r =>
              (status === 'all' || r.dataset.status === status) &&
              (!query || r.dataset.search.includes(query)));
            const filtering = status !== 'all' || query !== '';
            const limit = filtering ? matches.length : Math.min(shown, matches.length);
            rows.forEach(r => { r.hidden = true; });
            matches.slice(0, limit).forEach(r => { r.hidden = false; });
            more.hidden = filtering || shown >= matches.length;
            none.hidden = matches.length > 0;
            count.textContent = 'Showing ' + limit + ' of ' + matches.length +
              (!filtering && rows.length >= 50 ? ' (your latest 50 orders)' : '');
          }
          chips.forEach(chip => chip.addEventListener('click', () => {
            status = chip.dataset.status;
            chips.forEach(c => c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'));
            apply();
          }));
          search.addEventListener('input', () => { query = search.value.trim().toLowerCase(); apply(); });
          more.addEventListener('click', () => { shown += PAGE; apply(); });
          apply();
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
              Add a product
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
                <select class="form-select" name="pcat">
                  <?php foreach ($PRODUCT_CATEGORIES as $pc): ?>
                    <option value="<?= e($pc) ?>"><?= e($pc) ?></option>
                  <?php endforeach; ?>
                </select>
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
                Add product
              </button>
            </form>
          </div>
        </div>

        <!-- Products list -->
        <div class="col-lg-8">
          <?php if (empty($products)): ?>
          <div class="text-center py-5">
            <h5 class="mb-1">Your shop has no products yet</h5>
            <p class="text-muted mb-0">Add your first product with the form. It shows up for tourists right away.</p>
          </div>
          <?php else: ?>
          <!-- Category filter -->
          <div class="d-flex flex-wrap gap-2 mb-3" id="category-filter">
            <button type="button" class="btn btn-sm cat-pill active" data-cat="__all"
                    style="border-radius:var(--radius-pill);font-size:.78rem">All (<?= count($products) ?>)</button>
            <?php foreach ($product_groups as $cName => $cList): ?>
              <button type="button" class="btn btn-sm cat-pill" data-cat="<?= e($cName) ?>"
                      style="border-radius:var(--radius-pill);font-size:.78rem"><?= e($cName) ?> (<?= count($cList) ?>)</button>
            <?php endforeach; ?>
          </div>

          <div>
          <?php foreach ($product_groups as $cName => $cList): ?>
          <div class="product-cat-group mb-4" data-cat="<?= e($cName) ?>">
            <h3 class="sd-cat-h"><?= e($cName) ?><span><?= count($cList) ?></span></h3>
          <div class="sd-plist">
            <?php foreach ($cList as $p): $pPhotos = $product_photos[$p['id']] ?? []; ?>
            <div class="sd-prow" style="opacity:<?= $p['is_available'] ? '1' : '.55' ?>">
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
                  <div class="text-muted small d-flex align-items-center flex-wrap gap-2">
                    <form method="POST" class="m-0"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_product_category">
                      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                      <select name="pcat" class="form-select form-select-sm" style="width:auto;font-size:.75rem;padding:.1rem 1.6rem .1rem .5rem"
                              onchange="this.form.submit()" title="Change category">
                        <?php if (!in_array($cName, $PRODUCT_CATEGORIES, true)): ?>
                          <option selected disabled><?= e($cName) ?></option>
                        <?php endif; ?>
                        <?php foreach ($PRODUCT_CATEGORIES as $pc): ?>
                          <option value="<?= e($pc) ?>" <?= $pc === $cName ? 'selected' : '' ?>><?= e($pc) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <span>Stock: <?= $p['stock'] >= 999 ? '∞' : $p['stock'] ?></span>
                  </div>
                </div>
                <div style="font-family:'Playfair Display',serif;font-weight:700;color:var(--charcoal);font-size:1.05rem;white-space:nowrap">
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
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action"     value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="button" class="btn btn-sm btn-outline-danger js-confirm-action"
                            style="border-radius:var(--radius-pill);font-size:.75rem;padding:.28rem .6rem"
                            data-icon="bi-trash3" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
                            data-title="Delete this product?" data-name="<?= e($p['name']) ?>"
                            data-body="This can't be undone."
                            data-confirm-label="Yes, delete" data-confirm-class="btn-danger">
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
                        <form method="POST" class="m-0"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_product_photo">
                          <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                          <button type="button" class="btn btn-sm btn-outline-light js-confirm-action" style="font-size:.6rem;padding:.05rem .35rem" title="Delete"
                            data-icon="bi-trash3" data-icon-bg="#fee2e2" data-icon-color="#a61c1c"
                            data-title="Remove this photo?" data-name="<?= e($p['name']) ?>"
                            data-body="This photo can't be recovered once removed."
                            data-confirm-label="Yes, remove" data-confirm-class="btn-danger"><i class="bi bi-trash"></i></button>
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
          </div>
          <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <script>
    document.querySelectorAll('.cat-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        document.querySelectorAll('.cat-pill').forEach(p => p.classList.toggle('active', p === pill));
        const cat = pill.dataset.cat;
        document.querySelectorAll('.product-cat-group').forEach(g => {
          g.classList.toggle('d-none', cat !== '__all' && g.dataset.cat !== cat);
        });
      });
    });
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
