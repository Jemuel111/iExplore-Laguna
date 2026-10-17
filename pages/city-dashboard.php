<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — City Admin Dashboard
// pages/city-dashboard.php
// One city only: stats, approve/unpublish hotels & shops, and
// shortcuts to manage that city's tourist spots and photos.
// ============================================================
$page_title  = 'City Dashboard';
$active_page = 'city-dashboard';

$u = require_admin_area();

// The super admin has their own dashboard.
if (is_super_admin()) { header('Location: ' . APP_URL . '/pages/admin-dashboard.php'); exit; }

$cityId = (int) admin_scope();   // 0 = no active city assigned

// ── No city / deactivated account: explain clearly, don't loop ──
if ($cityId <= 0) {
    $acct        = db_fetch_one("SELECT is_active FROM users WHERE id = ?", [(int) $u['id']]);
    $deactivated = $acct && !$acct['is_active'];
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5 text-center" style="max-width:560px">
      <i class="bi bi-<?= $deactivated ? 'lock' : 'exclamation-triangle' ?> fs-1 text-muted d-block mb-3"></i>
      <?php if ($deactivated): ?>
        <h4>This account has been deactivated</h4>
        <p class="text-muted">You can no longer manage your city's spots, hotels or shops. If you think this is a mistake, please contact the iExplore Laguna administrator.</p>
      <?php else: ?>
        <h4>No city assigned</h4>
        <p class="text-muted">Your account is not linked to a city yet. Please contact the iExplore Laguna administrator to have a city assigned to you.</p>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/pages/logout.php" class="btn btn-outline-secondary">Log out</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$city = db_fetch_one("SELECT id, name, description, latitude, longitude FROM cities WHERE id = ?", [$cityId]);
if (!$city) { $city = ['id' => $cityId, 'name' => 'Your city', 'description' => '', 'latitude' => null, 'longitude' => null]; }
$cityName = (string) $city['name'];

$back = APP_URL . '/pages/city-dashboard.php';

// ── POST: approve / reject / unpublish hotels and shops in THIS city ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) input('action', 'post', '');

    // ---- shops ----
    if (in_array($action, ['approve_shop', 'reject_shop', 'revoke_shop'], true)) {
        $sid = (int) input('shop_id', 'post', 0);
        if (!admin_owns_shop($sid)) {
            $_SESSION['flash']['danger'] = 'You can only manage shops in your own city.';
            header('Location: ' . $back . '#approvals'); exit;
        }
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            $sname = (string) $shop['name'];
            if ($action === 'approve_shop') {
                db_execute("UPDATE shops SET is_verified = 1, is_active = 1 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_approved', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Approved!',
                     "\"{$sname}\" is now live on iExplore Laguna and visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('approve_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['success'] = "\"{$sname}\" approved.";
            } elseif ($action === 'reject_shop') {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_rejected', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Registration Rejected',
                     "\"{$sname}\" was not approved. Please review your details and contact your city administrator.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('reject_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['danger'] = "\"{$sname}\" rejected.";
            } else {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'shop_revoked', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Unpublished',
                     "\"{$sname}\" has been taken offline by an admin and is no longer visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                log_admin_activity('unpublish_shop', 'shop', $sid, $cityId, $sname);
                $_SESSION['flash']['danger'] = "\"{$sname}\" unpublished.";
            }
        }
        header('Location: ' . $back . '#approvals'); exit;
    }

    // ---- hotels ----
    if (in_array($action, ['approve_hotel', 'reject_hotel', 'revoke_hotel'], true)) {
        $hid = (int) input('hotel_id', 'post', 0);
        if (!admin_owns_hotel($hid)) {
            $_SESSION['flash']['danger'] = 'You can only manage hotels in your own city.';
            header('Location: ' . $back . '#approvals'); exit;
        }
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            $hname = (string) $hotel['name'];
            if ($action === 'approve_hotel') {
                db_execute("UPDATE hotels SET is_verified = 1, is_active = 1 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_approved', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Approved!',
                         "\"{$hname}\" is now live on iExplore Laguna and visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('approve_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['success'] = "\"{$hname}\" approved.";
            } elseif ($action === 'reject_hotel') {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_rejected', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Registration Rejected',
                         "\"{$hname}\" was not approved. Please review your details and contact your city administrator.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('reject_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['danger'] = "\"{$hname}\" rejected.";
            } else {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'hotel_revoked', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Unpublished',
                         "\"{$hname}\" has been taken offline by an admin and is no longer visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                log_admin_activity('unpublish_hotel', 'hotel', $hid, $cityId, $hname);
                $_SESSION['flash']['danger'] = "\"{$hname}\" unpublished.";
            }
        }
        header('Location: ' . $back . '#approvals'); exit;
    }
}

// ── Data for this city only ───────────────────────────────────
ensure_admin_activity_table();

// All dates come from the DATABASE clock, in Philippine time, so "today"
// matches the way views and check-ins were recorded.
try { db()->exec("SET time_zone = '+08:00'"); } catch (Throwable $e) { /* keep the server default */ }
$clock   = db_fetch_one("SELECT CURDATE() AS d, NOW() AS n");
$today   = new DateTimeImmutable((string) $clock['d']);
$nowTs   = (string) $clock['n'];
$nowHour = (int) substr($nowTs, 11, 2);

// ── Reporting period (chosen with the buttons above the chart) ──
$ranges = [
    'today' => ['label' => 'Today',     'long' => 'today',               'prev' => 'yesterday so far'],
    '7d'    => ['label' => '7 days',    'long' => 'the last 7 days',     'prev' => 'the 7 days before'],
    '30d'   => ['label' => '30 days',   'long' => 'the last 30 days',    'prev' => 'the 30 days before'],
    '90d'   => ['label' => '3 months',  'long' => 'the last 3 months',   'prev' => 'the 3 months before'],
    '12m'   => ['label' => '12 months', 'long' => 'the last 12 months',  'prev' => 'the 12 months before'],
];
$rangeKey = (string) input('range', 'get', '30d');
if (!isset($ranges[$rangeKey])) { $rangeKey = '30d'; }
$range = $ranges[$rangeKey];

switch ($rangeKey) {
    case 'today': $start = $today;                                              $pStart = $today->modify('-1 day');
                  $pEnd  = (new DateTimeImmutable($nowTs))->modify('-1 day');   break;
    case '7d':    $start = $today->modify('-6 day');                            $pStart = $start->modify('-7 day');   $pEnd = $start; break;
    case '30d':   $start = $today->modify('-29 day');                           $pStart = $start->modify('-30 day');  $pEnd = $start; break;
    case '90d':   $start = $today->modify('-90 day');                           $pStart = $start->modify('-91 day');  $pEnd = $start; break; // 91 days = 13 whole weeks
    default:      $start = $today->modify('first day of this month')->modify('-11 month');
                  $pStart = $start->modify('-12 month');                        $pEnd = $start; break;
}
$S  = $start->format('Y-m-d 00:00:00');
$E  = $today->modify('+1 day')->format('Y-m-d 00:00:00');   // exclusive
$PS = $pStart->format('Y-m-d 00:00:00');
$PE = $pEnd->format('Y-m-d H:i:s');

// Every spot in this city, with what the table and the to-do list need.
$spots = db_fetch_all(
    "SELECT s.id, s.name, s.category, s.description, s.operating_hours,
            s.latitude, s.longitude, s.is_active, s.is_closed, s.closed_until, s.image_url,
            (SELECT url FROM spot_photos WHERE spot_id = s.id AND photo_type = 'main'
              ORDER BY sort_order, id LIMIT 1) AS main_photo_url,
            (SELECT COUNT(*) FROM spot_photos WHERE spot_id = s.id) AS photo_count,
            (SELECT COUNT(*) FROM spot_views v
              WHERE v.spot_id = s.id AND v.viewed_at >= ? AND v.viewed_at < ?) AS views_range
     FROM tourist_spots s
     WHERE s.city_id = ?
     ORDER BY views_range DESC, s.name", [$S, $E, $cityId]);

$spotTotal  = count($spots);
$spotActive = count(array_filter($spots, fn($x) => (int) $x['is_active'] === 1));
$maxViews   = max(1, ...array_map(fn($x) => (int) $x['views_range'], $spots ?: [['views_range' => 0]]));

// Views over the chosen period, grouped into buckets that suit its length:
//   today = hours · 7 / 30 days = days · 3 months = weeks · 12 months = months
$series = [];   // each: ['label' => tooltip text, 'n' => views]
$viewsFrom = "FROM spot_views v JOIN tourist_spots s ON s.id = v.spot_id
              WHERE s.city_id = ? AND v.viewed_at >= ? AND v.viewed_at < ?";
if ($rangeKey === 'today') {
    $by = [];
    foreach (db_fetch_all("SELECT HOUR(v.viewed_at) AS b, COUNT(*) AS n $viewsFrom GROUP BY HOUR(v.viewed_at)", [$cityId, $S, $E]) as $r) { $by[(int) $r['b']] = (int) $r['n']; }
    for ($h = 0; $h <= $nowHour; $h++) {
        $series[] = ['label' => (($h % 12) ?: 12) . ($h < 12 ? ' AM' : ' PM'), 'n' => $by[$h] ?? 0];
    }
} elseif ($rangeKey === '12m') {
    $by = [];
    foreach (db_fetch_all("SELECT DATE_FORMAT(v.viewed_at, '%Y-%m') AS b, COUNT(*) AS n $viewsFrom GROUP BY DATE_FORMAT(v.viewed_at, '%Y-%m')", [$cityId, $S, $E]) as $r) { $by[(string) $r['b']] = (int) $r['n']; }
    for ($i = 0; $i < 12; $i++) {
        $m = $start->modify("+{$i} month");
        $series[] = ['label' => $m->format('F Y'), 'n' => $by[$m->format('Y-m')] ?? 0];
    }
} else {
    $by = [];
    foreach (db_fetch_all("SELECT DATE(v.viewed_at) AS b, COUNT(*) AS n $viewsFrom GROUP BY DATE(v.viewed_at)", [$cityId, $S, $E]) as $r) { $by[(string) $r['b']] = (int) $r['n']; }
    $dayCount = (int) $start->diff($today)->days + 1;
    if ($rangeKey === '90d') {
        for ($w = 0; $w < 13; $w++) {
            $sum = 0;
            for ($d = 0; $d < 7; $d++) { $sum += $by[$start->modify('+' . ($w * 7 + $d) . ' day')->format('Y-m-d')] ?? 0; }
            $series[] = ['label' => 'Week of ' . $start->modify('+' . ($w * 7) . ' day')->format('M j'), 'n' => $sum];
        }
    } else {
        for ($d = 0; $d < $dayCount; $d++) {
            $day = $start->modify("+{$d} day");
            $series[] = ['label' => $day->format('D, M j'), 'n' => $by[$day->format('Y-m-d')] ?? 0];
        }
    }
}
$periodViews = array_sum(array_column($series, 'n'));
$prevViews   = (int) (db_fetch_one("SELECT COUNT(*) AS n $viewsFrom", [$cityId, $PS, $PE])['n'] ?? 0);

$checkinsN = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM spot_checkins c JOIN tourist_spots s ON s.id = c.spot_id
     WHERE s.city_id = ? AND c.checked_in_at >= ? AND c.checked_in_at < ?", [$cityId, $S, $E])['n'] ?? 0);

$reviewStats = db_fetch_one(
    "SELECT COUNT(*) AS n, AVG(r.rating) AS avg_rating
     FROM spot_reviews r JOIN tourist_spots s ON s.id = r.spot_id
     WHERE s.city_id = ? AND r.created_at >= ? AND r.created_at < ?", [$cityId, $S, $E]) ?: ['n' => 0, 'avg_rating' => null];

$bookingsN = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM bookings b JOIN hotels h ON h.id = b.hotel_id
     WHERE h.city_id = ? AND b.created_at >= ? AND b.created_at < ?", [$cityId, $S, $E])['n'] ?? 0);

$ordersN = (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM orders o JOIN shops s ON s.id = o.shop_id
     WHERE s.city_id = ? AND o.created_at >= ? AND o.created_at < ?", [$cityId, $S, $E])['n'] ?? 0);

$pending_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
     FROM shops s JOIN users u ON s.owner_id = u.id
     WHERE s.city_id = ? AND s.is_verified = 0
     ORDER BY s.created_at DESC", [$cityId]);

$pending_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
     FROM hotels h LEFT JOIN users u ON h.owner_id = u.id
     WHERE h.city_id = ? AND h.is_verified = 0
     ORDER BY h.id DESC", [$cityId]);

$verified_shops = db_fetch_all(
    "SELECT s.id, s.name, s.category, u.name AS owner_name
     FROM shops s JOIN users u ON s.owner_id = u.id
     WHERE s.city_id = ? AND s.is_verified = 1
     ORDER BY s.name", [$cityId]);

$verified_hotels = db_fetch_all(
    "SELECT h.id, h.name, h.star_rating, u.name AS owner_name
     FROM hotels h LEFT JOIN users u ON h.owner_id = u.id
     WHERE h.city_id = ? AND h.is_verified = 1
     ORDER BY h.name", [$cityId]);

$activity = db_fetch_all(
    "SELECT l.action, l.entity, l.details, l.created_at, u.name AS user_name
     FROM admin_activity_log l LEFT JOIN users u ON u.id = l.user_id
     WHERE l.city_id = ?
     ORDER BY l.id DESC LIMIT 12", [$cityId]);

$pendingTotal = count($pending_shops) + count($pending_hotels);

// ── To-do list, built from the city's real data ───────────────
$hasCenter = $city['latitude'] !== null && $city['longitude'] !== null;
$todo = [];
if ($pendingTotal) {
    $todo[] = ['urgent' => true, 'href' => '#approvals', 'cta' => 'Review',
               'text' => '<strong>' . $pendingTotal . '</strong> hotel or shop registration' . ($pendingTotal === 1 ? ' is' : 's are') . ' waiting for your approval'];
}
foreach ($spots as $sp) {
    $nm    = '<strong>' . e((string) $sp['name']) . '</strong>';
    $edit  = APP_URL . '/pages/admin-spots.php?id=' . (int) $sp['id'];
    $photo = APP_URL . '/pages/admin-spot-photos.php?spot_id=' . (int) $sp['id'];

    if ((int) $sp['photo_count'] === 0 && empty($sp['image_url'])) {
        $todo[] = ['urgent' => false, 'href' => $photo, 'cta' => 'Add photos', 'text' => "$nm has no photo yet"];
    }
    if ($hasCenter
        && abs((float) $sp['latitude']  - (float) $city['latitude'])  < 0.0003
        && abs((float) $sp['longitude'] - (float) $city['longitude']) < 0.0003) {
        $todo[] = ['urgent' => false, 'href' => $edit, 'cta' => 'Fix the pin',
                   'text' => "$nm is still pinned at the center of " . e($cityName) . " — trip routes will start from the wrong place"];
    }
    if (trim((string) $sp['description']) === '') {
        $todo[] = ['urgent' => false, 'href' => $edit, 'cta' => 'Write it', 'text' => "$nm has no description"];
    } elseif (trim((string) $sp['operating_hours']) === '') {
        $todo[] = ['urgent' => false, 'href' => $edit, 'cta' => 'Add hours', 'text' => "$nm has no opening hours"];
    }
    if ((int) $sp['is_closed'] === 1 && !empty($sp['closed_until']) && $sp['closed_until'] < $today->format('Y-m-d')) {
        $todo[] = ['urgent' => false, 'href' => $edit, 'cta' => 'Update',
                   'text' => "$nm is marked closed, but its reopening date has passed"];
    }
}
$todoShown = array_slice($todo, 0, 6);
$todoMore  = count($todo) - count($todoShown);

// ── Bar chart geometry (viewBox 300 × 70) ─────────────────────
$barN   = max(1, count($series));
$barMax = max(1, max(array_column($series, 'n') ?: [0]));
$gap    = $barN <= 14 ? 6.0 : 2.0;
$barW   = ($barN > 1) ? (300 - $gap * ($barN - 1)) / $barN : 300.0;
if ($barW > 30) { $barW = 30.0; $gap = $barN > 1 ? (300 - $barW * $barN) / ($barN - 1) : 0.0; }
$bars = [];
foreach ($series as $i => $pt) {
    $h = $pt['n'] > 0 ? max(3.0, ($pt['n'] / $barMax) * 58) : 0.0;
    $bars[] = ['x' => round($i * ($barW + $gap), 2), 'y' => round(66 - $h, 2), 'h' => round($h, 2),
               'w' => round($barW, 2), 'label' => $pt['label'], 'n' => $pt['n']];
}
$axisLeft  = $series ? $series[0]['label'] : '';
$axisRight = $rangeKey === 'today' ? 'Now' : ($rangeKey === '12m' ? 'This month' : ($rangeKey === '90d' ? 'This week' : 'Today'));

$delta = $periodViews - $prevViews;
if ($prevViews === 0 && $periodViews === 0) $deltaText = 'No views in this period or the one before';
elseif ($prevViews === 0)                   $deltaText = 'No views ' . ($rangeKey === 'today' ? 'yesterday so far' : 'in ' . $range['prev']);
elseif ($delta === 0)                       $deltaText = 'Same as ' . $range['prev'];
elseif ($delta > 0)                         $deltaText = '↑ ' . number_format($delta) . ' more than ' . $range['prev'];
else                                        $deltaText = '↓ ' . number_format(abs($delta)) . ' fewer than ' . $range['prev'];

// Human-readable window for the label above the chart.
if     ($rangeKey === 'today') $windowText = $today->format('l, F j');
elseif ($rangeKey === '12m')   $windowText = $start->format('M Y') . ' – ' . $today->format('M Y');
else                           $windowText = $start->format('M j') . ' – ' . $today->format('M j, Y');

$blurb = trim((string) ($city['description'] ?? ''));
if ($blurb !== '' && mb_strlen($blurb) > 170) { $blurb = rtrim(mb_substr($blurb, 0, 167)) . '…'; }

$categoryLabel = fn(string $c) => ucwords(str_replace('_', ' ', $c));

require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .cd-mast{position:relative;color:#fff;background:var(--maroon-dark);overflow:hidden;border-bottom:3px solid var(--sand-dark)}
  /* a quiet woven-mat (banig) texture that fades in from the right */
  .cd-mast::before{content:"";position:absolute;inset:0;pointer-events:none;
    background:
      repeating-linear-gradient(45deg, rgba(255,255,255,.07) 0 9px, transparent 9px 18px),
      repeating-linear-gradient(-45deg, rgba(0,0,0,.10) 0 9px, transparent 9px 18px);
    -webkit-mask-image:linear-gradient(90deg,transparent 25%,#000 85%);
            mask-image:linear-gradient(90deg,transparent 25%,#000 85%)}
  .cd-mast .container{position:relative;padding-top:2rem;padding-bottom:1.75rem}
  .cd-eyebrow{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:var(--sand-dark);font-weight:600}
  .cd-city{font-family:'Playfair Display',serif;font-size:clamp(2.1rem,4.5vw,3.2rem);line-height:1.05;margin:.35rem 0 .6rem}
  .cd-blurb{max-width:58ch;font-size:.95rem;line-height:1.55;color:rgba(255,255,255,.82);margin:0}
  .cd-date{font-size:.8rem;color:rgba(255,255,255,.7)}
  .cd-btn{display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;padding:.5rem 1.05rem;border-radius:var(--radius-pill);text-decoration:none;border:1.5px solid rgba(255,255,255,.55);color:#fff;transition:var(--transition)}
  .cd-btn:hover{background:rgba(255,255,255,.14);color:#fff}
  .cd-btn.is-primary{background:var(--sand-dark);border-color:var(--sand-dark);color:var(--maroon-dark)}
  .cd-btn.is-primary:hover{background:#f0d27f;color:var(--maroon-dark)}

  .cd-label{font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);font-weight:600}
  .cd-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)}
  .cd-h{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--maroon-dark);margin:0}

  .cd-range{display:inline-flex;flex-wrap:wrap;background:#fff;border:1px solid var(--border);border-radius:var(--radius-pill);padding:3px;gap:2px}
  .cd-range a{font-size:.8rem;font-weight:600;padding:.3rem .85rem;border-radius:var(--radius-pill);color:var(--text-muted);text-decoration:none;transition:var(--transition)}
  .cd-range a:hover{color:var(--maroon-dark)}
  .cd-range a.is-active{background:var(--maroon-dark);color:#fff}
  .cd-pulse{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr);gap:1.25rem}
  .cd-big{font-family:'Playfair Display',serif;font-size:3.1rem;line-height:1;color:var(--maroon-dark)}
  .cd-delta{font-size:.82rem;color:var(--text-muted)}
  .cd-spark{width:100%;height:70px;display:block;margin-top:.9rem}
  .cd-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}
  .cd-stat{padding:1.15rem 1.25rem;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}
  .cd-stat:nth-child(3n){border-right:0}
  .cd-stat:nth-last-child(-n+3){border-bottom:0}
  .cd-stat b{display:block;font-family:'Playfair Display',serif;font-size:1.7rem;line-height:1.1;color:var(--charcoal);font-weight:600}
  .cd-stat span{font-size:.78rem;color:var(--text-muted)}

  .cd-todo{border-left:4px solid var(--sand-dark)}
  .cd-todo.has-urgent{border-left-color:var(--maroon-mid)}
  .cd-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--border);font-size:.9rem}
  .cd-row:last-child{border-bottom:0}
  .cd-row a.cd-cta{flex-shrink:0;font-size:.8rem;font-weight:600;color:var(--maroon-mid);text-decoration:none;white-space:nowrap}
  .cd-row a.cd-cta:hover{color:var(--maroon-dark);text-decoration:underline}
  .cd-dot{width:8px;height:8px;border-radius:50%;background:var(--sand-dark);flex-shrink:0;margin-right:.7rem}
  .cd-dot.is-urgent{background:var(--maroon-mid)}

  .cd-spots-head,.cd-spot{display:grid;grid-template-columns:minmax(0,2.6fr) 1fr 1.3fr .6fr .8fr auto;gap:.9rem;align-items:center}
  .cd-spots-head{padding:.6rem 1rem;border-bottom:1px solid var(--border);background:var(--cream)}
  .cd-spot{padding:.65rem 1rem;border-bottom:1px solid var(--border);font-size:.88rem}
  .cd-spot:last-child{border-bottom:0}
  .cd-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;background:var(--maroon-pale);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:var(--maroon-mid)}
  .cd-spot a.cd-name{font-weight:600;color:var(--charcoal);text-decoration:none}
  .cd-spot a.cd-name:hover{color:var(--maroon-mid)}
  .cd-bar{height:5px;border-radius:3px;background:var(--maroon-pale);margin-top:4px;overflow:hidden}
  .cd-bar i{display:block;height:100%;background:var(--maroon-mid);border-radius:3px}
  .cd-pill{display:inline-block;font-size:.7rem;font-weight:600;padding:.18rem .6rem;border-radius:var(--radius-pill)}
  .cd-pill.live{background:#e3f3e8;color:#1f6b3a}
  .cd-pill.hidden{background:#eceae6;color:#6b6b6b}
  .cd-pill.closed{background:#fdf0d5;color:#8a5a00}
  .cd-mini{font-size:.78rem;font-weight:600;color:var(--maroon-mid);text-decoration:none;margin-left:.6rem}
  .cd-mini:hover{text-decoration:underline;color:var(--maroon-dark)}

  details.cd-fold{border:1px solid var(--border);border-radius:var(--radius-sm);background:#fff;margin-bottom:.75rem}
  details.cd-fold>summary{cursor:pointer;list-style:none;padding:.8rem 1rem;font-weight:600;font-size:.92rem;display:flex;justify-content:space-between;align-items:center}
  details.cd-fold>summary::-webkit-details-marker{display:none}
  details.cd-fold>summary::after{content:"+";font-size:1.2rem;color:var(--maroon-mid)}
  details.cd-fold[open]>summary::after{content:"–"}
  details.cd-fold .cd-fold-body{padding:0 1rem .5rem}

  .cd-time{position:relative;margin:0;padding:0 0 0 1.1rem;list-style:none}
  .cd-time::before{content:"";position:absolute;left:3px;top:6px;bottom:6px;width:1px;background:var(--border)}
  .cd-time li{position:relative;padding-bottom:.9rem;font-size:.84rem}
  .cd-time li::before{content:"";position:absolute;left:-1.1rem;top:.38rem;width:7px;height:7px;border-radius:50%;background:var(--maroon-mid)}
  .cd-time small{display:block;color:var(--text-muted)}

  @media (max-width:991px){.cd-pulse{grid-template-columns:1fr}}
  @media (max-width:767px){
    .cd-spots-head{display:none}
    .cd-spot{grid-template-columns:minmax(0,1fr) auto}
    .cd-hide-sm{display:none!important}
    .cd-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    .cd-stat:nth-child(3n){border-right:1px solid var(--border)}
    .cd-stat:nth-child(2n){border-right:0}
    .cd-stat:nth-last-child(-n+3){border-bottom:1px solid var(--border)}
    .cd-stat:nth-last-child(-n+2){border-bottom:0}
  }
</style>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- ── Masthead ─────────────────────────────────────────────── -->
<header class="cd-mast">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <div class="cd-eyebrow">City dashboard · Laguna</div>
        <h1 class="cd-city"><?= e($cityName) ?></h1>
        <?php if ($blurb !== ''): ?><p class="cd-blurb"><?= e($blurb) ?></p><?php endif; ?>
      </div>
      <div class="text-lg-end">
        <div class="cd-date mb-2"><?= e(date('l, F j')) ?></div>
        <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
          <a href="<?= APP_URL ?>/pages/admin-spots.php" class="cd-btn is-primary"><i class="bi bi-plus-lg"></i>Add a spot</a>
          <a href="<?= APP_URL ?>/pages/admin-spot-photos.php" class="cd-btn"><i class="bi bi-images"></i>Photos</a>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="container py-4">

  <!-- ── Pulse ──────────────────────────────────────────────── -->
  <div id="pulse" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <div class="cd-delta"><strong style="color:var(--charcoal)"><?= e($windowText) ?></strong></div>
    <nav class="cd-range" aria-label="Reporting period">
      <?php foreach ($ranges as $k => $r): ?>
        <a href="?range=<?= e($k) ?>#pulse" class="<?= $k === $rangeKey ? 'is-active' : '' ?>" <?= $k === $rangeKey ? 'aria-current="true"' : '' ?>><?= e($r['label']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <section class="cd-pulse mb-4" aria-label="Activity in <?= e($range['long']) ?>">
    <div class="cd-card p-4">
      <div class="cd-label">Spot views · <?= e($range['long']) ?></div>
      <div class="d-flex align-items-baseline gap-3 mt-2 flex-wrap">
        <div class="cd-big"><?= number_format($periodViews) ?></div>
        <div class="cd-delta"><?= e($deltaText) ?></div>
      </div>
      <svg class="cd-spark" viewBox="0 0 300 70" preserveAspectRatio="none" role="img"
           aria-label="Spot views in <?= e($cityName) ?> over <?= e($range['long']) ?>">
        <line x1="0" y1="66.5" x2="300" y2="66.5" stroke="var(--border)" stroke-width="1" vector-effect="non-scaling-stroke"/>
        <?php foreach ($bars as $i => $b): if ($b['h'] <= 0) { continue; } ?>
          <rect x="<?= $b['x'] ?>" y="<?= $b['y'] ?>" width="<?= $b['w'] ?>" height="<?= $b['h'] ?>" rx="1.5"
                fill="<?= $i === count($bars) - 1 ? 'var(--maroon-dark)' : 'var(--maroon-mid)' ?>">
            <title><?= e($b['label']) ?> — <?= (int) $b['n'] ?> view<?= (int) $b['n'] === 1 ? '' : 's' ?></title>
          </rect>
        <?php endforeach; ?>
      </svg>
      <div class="d-flex justify-content-between cd-delta mt-1"><span><?= e($axisLeft) ?></span><span><?= e($axisRight) ?></span></div>
      <?php if ($periodViews === 0): ?>
        <div class="cd-delta mt-2" style="font-style:italic">No spot views recorded for this period yet.</div>
      <?php endif; ?>
    </div>

    <div class="cd-card cd-stats">
      <div class="cd-stat"><b><?= $spotActive ?><span style="font-size:1rem;color:var(--text-muted);font-weight:400"> / <?= $spotTotal ?></span></b><span>Spots live</span></div>
      <div class="cd-stat"><b><?= number_format($checkinsN) ?></b><span>Check-ins</span></div>
      <div class="cd-stat"><b><?= (int) $reviewStats['n'] ?><?php if ($reviewStats['avg_rating']): ?><span style="font-size:1rem;color:var(--terracotta);font-weight:400"> · <?= number_format((float) $reviewStats['avg_rating'], 1) ?>★</span><?php endif; ?></b><span>New reviews</span></div>
      <div class="cd-stat"><b><?= count($verified_hotels) ?></b><span>Hotels published</span></div>
      <div class="cd-stat"><b><?= $bookingsN ?></b><span>Hotel bookings</span></div>
      <div class="cd-stat"><b><?= $ordersN ?></b><span>Shop orders</span></div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-lg-8">

      <!-- ── To do ───────────────────────────────────────────── -->
      <section class="cd-card cd-todo p-4 mb-4 <?= $pendingTotal ? 'has-urgent' : '' ?>">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <h2 class="cd-h">To do</h2>
          <?php if ($todo): ?><span class="cd-label"><?= count($todo) ?> item<?= count($todo) === 1 ? '' : 's' ?></span><?php endif; ?>
        </div>
        <?php if (!$todoShown): ?>
          <p class="mb-0 mt-2 text-muted" style="font-size:.9rem">Nothing needs attention right now. Spots have photos, descriptions and a real pin, and nothing is waiting for approval.</p>
        <?php else: ?>
          <div>
            <?php foreach ($todoShown as $t): ?>
              <div class="cd-row">
                <div class="d-flex align-items-center"><span class="cd-dot <?= $t['urgent'] ? 'is-urgent' : '' ?>"></span><span><?= $t['text'] ?></span></div>
                <a class="cd-cta" href="<?= e((string) $t['href']) ?>"><?= e((string) $t['cta']) ?> →</a>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($todoMore > 0): ?>
            <div class="cd-delta mt-2">…and <?= $todoMore ?> more once these are done.</div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <!-- ── Your spots ──────────────────────────────────────── -->
      <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="cd-h">Your spots</h2>
          <a href="<?= APP_URL ?>/pages/admin-spots.php" class="cd-mini ms-0">Manage all →</a>
        </div>
        <?php if (!$spots): ?>
          <div class="cd-card p-4 text-center">
            <p class="mb-2">No spots in <?= e($cityName) ?> yet.</p>
            <a href="<?= APP_URL ?>/pages/admin-spots.php" class="cd-btn is-primary" style="border-color:var(--sand-dark)"><i class="bi bi-plus-lg"></i>Add the first one</a>
          </div>
        <?php else: ?>
          <div class="cd-card" style="overflow:hidden">
            <div class="cd-spots-head cd-label">
              <span>Spot</span><span class="cd-hide-sm">Type</span><span class="cd-hide-sm">Views · <?= e($range['label']) ?></span><span class="cd-hide-sm">Photos</span><span>Status</span><span></span>
            </div>
            <?php foreach (array_slice($spots, 0, 15) as $sp):
              $img    = (string) ($sp['main_photo_url'] ?: $sp['image_url']);
              $status = !(int) $sp['is_active'] ? ['hidden', 'Hidden'] : ((int) $sp['is_closed'] ? ['closed', 'Closed'] : ['live', 'Live']);
              $w      = (int) round(((int) $sp['views_range'] / $maxViews) * 100);
            ?>
            <div class="cd-spot">
              <div class="d-flex align-items-center gap-3 min-w-0">
                <?php if ($img !== ''): ?>
                  <img class="cd-thumb" src="<?= e($img) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="cd-thumb"><i class="bi bi-image"></i></span>
                <?php endif; ?>
                <a class="cd-name text-truncate" href="<?= APP_URL ?>/pages/admin-spots.php?id=<?= (int) $sp['id'] ?>"><?= e((string) $sp['name']) ?></a>
              </div>
              <span class="cd-hide-sm text-muted"><?= e($categoryLabel((string) $sp['category'])) ?></span>
              <div class="cd-hide-sm">
                <?= number_format((int) $sp['views_range']) ?>
                <div class="cd-bar"><i style="width:<?= $w ?>%"></i></div>
              </div>
              <span class="cd-hide-sm"><?= (int) $sp['photo_count'] ?></span>
              <span><span class="cd-pill <?= $status[0] ?>"><?= $status[1] ?></span></span>
              <span class="text-nowrap">
                <a class="cd-mini" href="<?= APP_URL ?>/pages/admin-spots.php?id=<?= (int) $sp['id'] ?>">Edit</a>
                <a class="cd-mini" href="<?= APP_URL ?>/pages/admin-spot-photos.php?spot_id=<?= (int) $sp['id'] ?>">Photos</a>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($spotTotal > 15): ?>
            <div class="cd-delta mt-2"><?= $spotTotal - 15 ?> more spots — <a href="<?= APP_URL ?>/pages/admin-spots.php">see the full list</a>.</div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <!-- ── Approvals ───────────────────────────────────────── -->
      <section id="approvals" class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <h2 class="cd-h">Hotels &amp; shops</h2>
          <?php if ($pendingTotal): ?><span class="badge rounded-pill" style="background:var(--maroon-mid)"><?= $pendingTotal ?> waiting</span><?php endif; ?>
        </div>

        <?php if (!$pendingTotal): ?>
          <div class="cd-card p-3 mb-3 text-muted" style="font-size:.9rem">No registrations are waiting for approval.</div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3 mb-3">

            <?php foreach ($pending_hotels as $h): ?>
            <div class="cd-card p-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-8">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge" style="background:var(--maroon-dark);font-size:.68rem">Hotel</span>
                    <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e((string) $h['name']) ?></span>
                  </div>
                  <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e((string) ($h['address'] ?? '')) ?></div>
                  <?php if (!empty($h['description'])): ?><div class="small text-muted mb-1"><?= e((string) $h['description']) ?></div><?php endif; ?>
                  <div class="small mt-2">
                    <i class="bi bi-person-circle me-1"></i><strong><?= e((string) ($h['owner_name'] ?? 'No owner')) ?></strong>
                    <?php if (!empty($h['owner_email'])): ?>&nbsp;·&nbsp;<?= e((string) $h['owner_email']) ?><?php endif; ?>
                    <?php if (!empty($h['owner_phone'])): ?>&nbsp;·&nbsp;<?= e((string) $h['owner_phone']) ?><?php endif; ?>
                  </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve_hotel">
                    <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
                    <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem"><i class="bi bi-check-lg me-1"></i>Approve</button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Reject this hotel? The owner will need to re-submit their registration.');"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject_hotel">
                    <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem"><i class="bi bi-x-lg me-1"></i>Reject</button>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($pending_shops as $s): ?>
            <div class="cd-card p-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-8">
                  <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge" style="background:var(--terracotta);font-size:.68rem">Shop</span>
                    <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e((string) $s['name']) ?></span>
                    <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.68rem"><?= e(ucfirst((string) $s['category'])) ?></span>
                  </div>
                  <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e((string) ($s['address'] ?? '')) ?></div>
                  <?php if (!empty($s['description'])): ?><div class="small text-muted mb-1"><?= e((string) $s['description']) ?></div><?php endif; ?>
                  <div class="small mt-2">
                    <i class="bi bi-person-circle me-1"></i><strong><?= e((string) $s['owner_name']) ?></strong>
                    &nbsp;·&nbsp;<?= e((string) $s['owner_email']) ?>
                    <?php if (!empty($s['owner_phone'])): ?>&nbsp;·&nbsp;<?= e((string) $s['owner_phone']) ?><?php endif; ?>
                  </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve_shop">
                    <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem"><i class="bi bi-check-lg me-1"></i>Approve</button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Reject this shop? The owner will need to re-submit their registration.');"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject_shop">
                    <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem"><i class="bi bi-x-lg me-1"></i>Reject</button>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>

          </div>
        <?php endif; ?>

        <details class="cd-fold">
          <summary>Published hotels <span class="cd-label ms-auto me-3"><?= count($verified_hotels) ?></span></summary>
          <div class="cd-fold-body">
            <?php if (!$verified_hotels): ?>
              <p class="text-muted small my-2">No published hotels in <?= e($cityName) ?> yet.</p>
            <?php else: foreach ($verified_hotels as $h): ?>
              <div class="cd-row">
                <div>
                  <div class="fw-semibold"><?= e((string) $h['name']) ?></div>
                  <div class="small text-muted">
                    <?php if (!empty($h['star_rating'])): ?><?= str_repeat('★', (int) $h['star_rating']) ?> · <?php endif; ?>
                    <?= e((string) ($h['owner_name'] ?? 'No owner')) ?>
                  </div>
                </div>
                <form method="POST" onsubmit="return confirm('Unpublish this hotel? It will be hidden from tourists until re-approved.');"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="revoke_hotel">
                  <input type="hidden" name="hotel_id" value="<?= (int) $h['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.76rem;padding:.25rem .8rem"><i class="bi bi-eye-slash me-1"></i>Unpublish</button>
                </form>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </details>

        <details class="cd-fold">
          <summary>Published shops <span class="cd-label ms-auto me-3"><?= count($verified_shops) ?></span></summary>
          <div class="cd-fold-body">
            <?php if (!$verified_shops): ?>
              <p class="text-muted small my-2">No published shops in <?= e($cityName) ?> yet.</p>
            <?php else: foreach ($verified_shops as $s): ?>
              <div class="cd-row">
                <div>
                  <div class="fw-semibold"><?= e((string) $s['name']) ?> <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.66rem"><?= e(ucfirst((string) $s['category'])) ?></span></div>
                  <div class="small text-muted"><?= e((string) $s['owner_name']) ?></div>
                </div>
                <form method="POST" onsubmit="return confirm('Unpublish this shop? It will be hidden from tourists until re-approved.');"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="revoke_shop">
                  <input type="hidden" name="shop_id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.76rem;padding:.25rem .8rem"><i class="bi bi-eye-slash me-1"></i>Unpublish</button>
                </form>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </details>
      </section>

    </div>

    <!-- ── Activity ──────────────────────────────────────────── -->
    <div class="col-lg-4">
      <section class="cd-card p-4">
        <h2 class="cd-h mb-3">Recent changes</h2>
        <?php if (!$activity): ?>
          <p class="text-muted small mb-0">Nothing yet. Everything you add or approve is listed here, with the date and who did it.</p>
        <?php else: ?>
          <ul class="cd-time">
            <?php foreach ($activity as $a): ?>
              <li>
                <strong><?= e(ucfirst(strtolower(str_replace('_', ' ', (string) $a['action'])))) ?></strong><?= $a['details'] ? ' — ' . e((string) $a['details']) : '' ?>
                <small><?= e((string) ($a['user_name'] ?? 'Unknown')) ?> · <?= e(date('M j, g:i A', strtotime((string) $a['created_at']))) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
