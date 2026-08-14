<div class="analytics-page">
<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';

// ============================================================
// iEXPLORE LAGUNA — Admin Analytics
// ============================================================
$page_title  = 'Analytics';
$active_page = '';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

ensure_user_demographic_columns();
ensure_spot_views_table();
ensure_spot_checkins_table();

// ── Date range ────────────────────────────────────────────────
$allowed_periods = [7, 30, 90, 365];
$period = isset($_GET['period']) ? (int) $_GET['period'] : 30;
if (!in_array($period, $allowed_periods, true)) $period = 30;

$period_start = date('Y-m-d 00:00:00', strtotime('-' . ($period - 1) . ' days'));
$today_start  = date('Y-m-d 00:00:00');
$prev_start   = date('Y-m-d 00:00:00', strtotime('-' . ($period * 2 - 1) . ' days'));
$prev_end     = $period_start;

// ── Safe query helper for optional analytics tables/data ─────
function analytics_count(string $sql, array $params = []): int {
    try { return (int) (db_fetch_one($sql, $params)['c'] ?? 0); }
    catch (Throwable $e) { return 0; }
}
function analytics_money(string $sql, array $params = []): float {
    try { return (float) (db_fetch_one($sql, $params)['n'] ?? 0); }
    catch (Throwable $e) { return 0.0; }
}
function analytics_rows(string $sql, array $params = []): array {
    try { return db_fetch_all($sql, $params); }
    catch (Throwable $e) { return []; }
}

// ── Overview KPIs ─────────────────────────────────────────────
$total_tourists = analytics_count("SELECT COUNT(*) c FROM users WHERE role='tourist'");
$total_hotels   = analytics_count("SELECT COUNT(*) c FROM hotels WHERE is_verified=1 AND is_active=1");
$total_shops    = analytics_count("SELECT COUNT(*) c FROM shops WHERE is_verified=1 AND is_active=1");
$total_spots    = analytics_count("SELECT COUNT(*) c FROM tourist_spots WHERE is_active=1");

$period_users = analytics_count(
    "SELECT COUNT(*) c FROM users WHERE role='tourist' AND created_at >= ?", [$period_start]
);
$period_bookings = analytics_count(
    "SELECT COUNT(*) c FROM bookings WHERE created_at >= ? AND status NOT IN ('cancelled','no_show')", [$period_start]
);
$period_orders = analytics_count(
    "SELECT COUNT(*) c FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled')", [$period_start]
);
$period_views = analytics_count(
    "SELECT COUNT(*) c FROM spot_views WHERE viewed_at >= ?", [$period_start]
);
$period_checkins = analytics_count(
    "SELECT COUNT(*) c FROM spot_checkins WHERE checked_in_at >= ?", [$period_start]
);
$period_booking_revenue = analytics_money(
    "SELECT COALESCE(SUM(total_amount),0) n FROM bookings WHERE created_at >= ? AND status NOT IN ('cancelled','no_show')", [$period_start]
);
$period_order_revenue = analytics_money(
    "SELECT COALESCE(SUM(total_amount),0) n FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled')", [$period_start]
);

$prev_users = analytics_count("SELECT COUNT(*) c FROM users WHERE role='tourist' AND created_at >= ? AND created_at < ?", [$prev_start, $period_start]);
$prev_bookings = analytics_count("SELECT COUNT(*) c FROM bookings WHERE created_at >= ? AND created_at < ? AND status NOT IN ('cancelled','no_show')", [$prev_start, $period_start]);
$prev_orders = analytics_count("SELECT COUNT(*) c FROM orders WHERE created_at >= ? AND created_at < ? AND status NOT IN ('cancelled')", [$prev_start, $period_start]);
$prev_revenue = analytics_money("SELECT COALESCE(SUM(total_amount),0) n FROM bookings WHERE created_at >= ? AND created_at < ? AND status NOT IN ('cancelled','no_show')", [$prev_start, $period_start])
    + analytics_money("SELECT COALESCE(SUM(total_amount),0) n FROM orders WHERE created_at >= ? AND created_at < ? AND status NOT IN ('cancelled')", [$prev_start, $period_start]);
$current_revenue = $period_booking_revenue + $period_order_revenue;

function pct_change(float $current, float $previous): ?float {
    if ($previous == 0.0) return $current > 0 ? 100.0 : null;
    return (($current - $previous) / $previous) * 100;
}

$growth_users = pct_change($period_users, $prev_users);
$growth_bookings = pct_change($period_bookings, $prev_bookings);
$growth_orders = pct_change($period_orders, $prev_orders);
$growth_revenue = pct_change($current_revenue, $prev_revenue);

// ── Time series ───────────────────────────────────────────────
$daily_users = analytics_rows(
    "SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE role='tourist' AND created_at >= ? GROUP BY DATE(created_at) ORDER BY d",
    [$period_start]
);
$daily_bookings = analytics_rows(
    "SELECT DATE(created_at) d, COUNT(*) c FROM bookings WHERE created_at >= ? AND status NOT IN ('cancelled','no_show') GROUP BY DATE(created_at) ORDER BY d",
    [$period_start]
);
$daily_orders = analytics_rows(
    "SELECT DATE(created_at) d, COUNT(*) c FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled') GROUP BY DATE(created_at) ORDER BY d",
    [$period_start]
);
$daily_views = analytics_rows(
    "SELECT DATE(viewed_at) d, COUNT(*) c FROM spot_views WHERE viewed_at >= ? GROUP BY DATE(viewed_at) ORDER BY d",
    [$period_start]
);
$daily_checkins = analytics_rows(
    "SELECT DATE(checked_in_at) d, COUNT(*) c FROM spot_checkins WHERE checked_in_at >= ? GROUP BY DATE(checked_in_at) ORDER BY d",
    [$period_start]
);

$series = [];
for ($i = $period - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $series[$d] = ['label' => date('M j', strtotime($d)), 'users'=>0, 'bookings'=>0, 'orders'=>0, 'views'=>0, 'checkins'=>0];
}
foreach ($daily_users as $r) if (isset($series[$r['d']])) $series[$r['d']]['users'] = (int)$r['c'];
foreach ($daily_bookings as $r) if (isset($series[$r['d']])) $series[$r['d']]['bookings'] = (int)$r['c'];
foreach ($daily_orders as $r) if (isset($series[$r['d']])) $series[$r['d']]['orders'] = (int)$r['c'];
foreach ($daily_views as $r) if (isset($series[$r['d']])) $series[$r['d']]['views'] = (int)$r['c'];
foreach ($daily_checkins as $r) if (isset($series[$r['d']])) $series[$r['d']]['checkins'] = (int)$r['c'];

// ── Demographics ──────────────────────────────────────────────
$tourist_type_split = analytics_rows("SELECT tourist_type, COUNT(*) c FROM users WHERE role='tourist' AND tourist_type IS NOT NULL GROUP BY tourist_type");
$local_count = 0; $intl_count = 0;
foreach ($tourist_type_split as $row) {
    if ($row['tourist_type'] === 'local') $local_count = (int)$row['c'];
    if ($row['tourist_type'] === 'international') $intl_count = (int)$row['c'];
}
$gender_split = analytics_rows("SELECT gender, COUNT(*) c FROM users WHERE role='tourist' AND gender IS NOT NULL GROUP BY gender");
$age_buckets = analytics_rows("SELECT CASE
    WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) < 18 THEN 'Under 18'
    WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 18 AND 24 THEN '18–24'
    WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 25 AND 34 THEN '25–34'
    WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 35 AND 44 THEN '35–44'
    WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 45 AND 54 THEN '45–54'
    ELSE '55+' END bucket, COUNT(*) c
    FROM users WHERE role='tourist' AND birthdate IS NOT NULL GROUP BY bucket");
$age_order = ['Under 18','18–24','25–34','35–44','45–54','55+'];
$age_map = array_column($age_buckets, 'c', 'bucket');
$age_ordered = []; foreach ($age_order as $b) $age_ordered[$b] = (int)($age_map[$b] ?? 0);
$top_provinces = analytics_rows("SELECT province, COUNT(*) c FROM users WHERE role='tourist' AND tourist_type='local' AND province IS NOT NULL AND province!='' GROUP BY province ORDER BY c DESC LIMIT 8");
$top_nationalities = analytics_rows("SELECT nationality, COUNT(*) c FROM users WHERE role='tourist' AND tourist_type='international' AND nationality IS NOT NULL AND nationality!='' GROUP BY nationality ORDER BY c DESC LIMIT 8");

// ── Spot analytics ────────────────────────────────────────────
$spot_stats = analytics_rows("SELECT s.id,s.name,c.name city_name,
    (SELECT COUNT(*) FROM spot_views v WHERE v.spot_id=s.id) total_views,
    (SELECT COUNT(*) FROM spot_checkins k WHERE k.spot_id=s.id) total_checkins,
    (SELECT MIN(k2.checked_in_at) FROM spot_checkins k2 WHERE k2.spot_id=s.id) first_checkin
    FROM tourist_spots s JOIN cities c ON s.city_id=c.id WHERE s.is_active=1
    ORDER BY total_checkins DESC,total_views DESC");
foreach ($spot_stats as &$row) {
    $monthsActive = 1;
    if ($row['first_checkin']) $monthsActive = max(1,(int)((strtotime('now')-strtotime($row['first_checkin']))/(30*86400))+1);
    $row['avg_per_month'] = $row['total_checkins'] > 0 ? round($row['total_checkins']/$monthsActive,1) : 0;
    $row['conversion'] = $row['total_views'] > 0 ? round($row['total_checkins']/$row['total_views']*100,1) : 0;
}
unset($row);
usort($spot_stats, fn($a,$b) => $b['total_checkins'] <=> $a['total_checkins']);
$top_spots = array_slice($spot_stats, 0, 8);
$site_total_views = array_sum(array_column($spot_stats,'total_views'));
$site_total_checkins = array_sum(array_column($spot_stats,'total_checkins'));

// ── Booking / order status ────────────────────────────────────
$booking_status = analytics_rows("SELECT status, COUNT(*) c FROM bookings GROUP BY status ORDER BY c DESC");
$order_status = analytics_rows("SELECT status, COUNT(*) c FROM orders GROUP BY status ORDER BY c DESC");
$top_hotels = analytics_rows("SELECT h.name, COUNT(*) c, COALESCE(SUM(b.total_amount),0) revenue
    FROM bookings b JOIN hotels h ON b.hotel_id=h.id
    WHERE b.created_at >= ? AND b.status NOT IN ('cancelled','no_show')
    GROUP BY h.id,h.name ORDER BY c DESC LIMIT 8", [$period_start]);
$top_shops = analytics_rows("SELECT s.name, COUNT(*) c, COALESCE(SUM(o.total_amount),0) revenue
    FROM orders o JOIN shops s ON o.shop_id=s.id
    WHERE o.created_at >= ? AND o.status NOT IN ('cancelled')
    GROUP BY s.id,s.name ORDER BY c DESC LIMIT 8", [$period_start]);

$review_count = analytics_count("SELECT COUNT(*) c FROM hotel_reviews WHERE created_at >= ?", [$period_start]);
$avg_hotel_rating = analytics_money("SELECT COALESCE(AVG(rating),0) n FROM hotel_reviews WHERE created_at >= ?", [$period_start]);
$spot_review_count = analytics_count("SELECT COUNT(*) c FROM spot_reviews WHERE created_at >= ?", [$period_start]);
$avg_spot_rating = analytics_money("SELECT COALESCE(AVG(rating),0) n FROM spot_reviews WHERE created_at >= ?", [$period_start]);

$chart_labels = array_values(array_column($series,'label'));
$chart_users = array_values(array_column($series,'users'));
$chart_bookings = array_values(array_column($series,'bookings'));
$chart_orders = array_values(array_column($series,'orders'));
$chart_views = array_values(array_column($series,'views'));
$chart_checkins = array_values(array_column($series,'checkins'));

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.analytics-card{background:var(--surface,#fff);border:1px solid var(--border,#e8e8e8);border-radius:18px;box-shadow:0 5px 18px rgba(0,0,0,.05);height:auto}
.analytics-card .card-body{padding:22px}
.analytics-kpi{position:relative;overflow:hidden}
.analytics-kpi:after{content:'';position:absolute;width:90px;height:90px;border-radius:50%;right:-28px;top:-28px;background:var(--green-pale,#eef5ee);opacity:.8}
.analytics-kpi-icon{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:var(--green-pale,#eef5ee);color:var(--green-dark,#285943);font-size:1.2rem}
.analytics-kpi .value{font-size:1.75rem;font-weight:800;line-height:1.1}
.analytics-kpi .label{font-size:.8rem;color:var(--text-muted,#77808c);font-weight:600}
.analytics-growth{font-size:.75rem;font-weight:700}
.analytics-growth.up{color:#198754}.analytics-growth.down{color:#c0392b}.analytics-growth.flat{color:#7b8794}
.chart-wrap{position:relative;height:310px}
.mini-bar{height:9px;background:#edf0f2;border-radius:99px;overflow:hidden}.mini-bar>span{display:block;height:100%;border-radius:99px;background:var(--green-mid,#5c8d6a)}
.rank-row{padding:10px 0;border-bottom:1px solid var(--border,#eee)}.rank-row:last-child{border-bottom:0}
.rank-num{width:28px;height:28px;border-radius:9px;background:var(--green-pale,#eef5ee);color:var(--green-dark,#285943);display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800}
.period-btn.active{background:var(--green-dark,#285943);border-color:var(--green-dark,#285943);color:#fff}
.status-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:6px;background:#adb5bd}
.analytics-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted,#77808c);white-space:nowrap}
@media(max-width:767px){.chart-wrap{height:260px}.analytics-kpi .value{font-size:1.45rem}}

/* Compact full-width analytics sections */
.analytics-spot-performance,
.analytics-top-shops {
  height: auto !important;
  min-height: 0 !important;
  align-self: flex-start;
}
.analytics-spot-performance .card-body,
.analytics-top-shops .card-body {
  height: auto !important;
  min-height: 0 !important;
}
.analytics-spot-performance .table-responsive,
.analytics-top-shops .table-responsive {
  height: auto !important;
  min-height: 0 !important;
}
.analytics-spot-performance table,
.analytics-top-shops table {
  margin-bottom: 0 !important;
}
.analytics-spot-performance th,
.analytics-top-shops th {
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}
.analytics-spot-performance td,
.analytics-top-shops td {
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}

</style>

<section class="py-3" style="background:linear-gradient(135deg,var(--theme-dark,#5c1620),var(--theme-primary,#a61c1c));color:#fff">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-bar-chart-line-fill fs-2" style="color:var(--sand-dark,#e9c46a)"></i>
        <div><h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Analytics</h1><p class="mb-0 small opacity-75">Understand tourists, bookings, orders and destination activity</p></div>
      </div>
      <div class="btn-group btn-group-sm" role="group" aria-label="Analytics period">
        <?php foreach ([7=>'7 Days',30=>'30 Days',90=>'90 Days',365=>'1 Year'] as $days=>$label): ?>
          <a class="btn btn-outline-light period-btn <?= $period===$days?'active':'' ?>" href="?period=<?= $days ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><h5 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--green-dark)">Performance Overview</h5><div class="text-muted small">Showing the last <?= $period ?> days · compared with the previous <?= $period ?> days</div></div>
  </div>

  <div class="row g-3 mb-4">
    <?php
    $kpis = [
      ['bi-person-plus-fill','New Tourists',$period_users,$growth_users,'users'],
      ['bi-calendar-check-fill','Valid Bookings',$period_bookings,$growth_bookings,'bookings'],
      ['bi-bag-check-fill','Valid Orders',$period_orders,$growth_orders,'orders'],
      ['bi-cash-stack','Activity Revenue','₱'.number_format($current_revenue,2),$growth_revenue,'revenue'],
      ['bi-eye-fill','Spot Views',$period_views,null,'views'],
      ['bi-geo-alt-fill','Verified Check-ins',$period_checkins,null,'checkins'],
    ];
    foreach ($kpis as [$icon,$label,$value,$growth,$key]): ?>
      <div class="col-6 col-lg-2"><div class="analytics-card analytics-kpi"><div class="card-body">
        <div class="analytics-kpi-icon mb-3"><i class="bi <?= $icon ?>"></i></div>
        <div class="value"><?= is_string($value) ? $value : number_format($value) ?></div>
        <div class="label mt-1"><?= $label ?></div>
        <?php if ($growth !== null): $cls=$growth>0?'up':($growth<0?'down':'flat'); $arrow=$growth>0?'↑':($growth<0?'↓':'→'); ?>
          <div class="analytics-growth <?= $cls ?> mt-2"><?= $arrow ?> <?= number_format(abs($growth),1) ?>% vs previous period</div>
        <?php endif; ?>
      </div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="analytics-card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1">Activity Trend</h6><div class="text-muted small">Tourist registrations, bookings and orders per day</div></div></div>
      <div class="chart-wrap"><canvas id="activityChart"></canvas></div>
    </div></div></div>
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Destination Interest</h6><div class="text-muted small mb-3">Views versus verified visits</div>
      <div class="chart-wrap" style="height:260px"><canvas id="visitChart"></canvas></div>
      <div class="small text-muted mt-2"><strong>Conversion:</strong> <?= $site_total_views>0 ? number_format($site_total_checkins/$site_total_views*100,1) : '0.0' ?>% of all recorded spot views resulted in a verified check-in.</div>
    </div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Top Tourist Spots</h6><div class="text-muted small mb-3">Ranked by verified check-ins</div>
      <?php if (!$top_spots): ?><p class="text-muted small mb-0">No spot data yet.</p><?php else: $max=max(1,(int)$top_spots[0]['total_checkins']); foreach($top_spots as $i=>$s): ?>
        <div class="rank-row"><div class="d-flex align-items-center gap-2"><span class="rank-num"><?= $i+1 ?></span><div class="flex-grow-1"><div class="d-flex justify-content-between small"><strong><?= e($s['name']) ?></strong><span><?= number_format($s['total_checkins']) ?> visits</span></div><div class="mini-bar mt-2"><span style="width:<?= min(100,round($s['total_checkins']/$max*100)) ?>%"></span></div></div></div></div>
      <?php endforeach; endif; ?>
    </div></div></div>

    <div class="col-lg-6"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Top Hotels</h6><div class="text-muted small mb-3">Most bookings in the selected period</div>
      <?php if (!$top_hotels): ?><p class="text-muted small mb-0">No booking data yet.</p><?php else: $max=max(1,(int)$top_hotels[0]['c']); foreach($top_hotels as $i=>$h): ?>
        <div class="rank-row"><div class="d-flex align-items-center gap-2"><span class="rank-num"><?= $i+1 ?></span><div class="flex-grow-1"><div class="d-flex justify-content-between small"><strong><?= e($h['name']) ?></strong><span><?= number_format($h['c']) ?> bookings</span></div><div class="mini-bar mt-2"><span style="width:<?= min(100,round($h['c']/$max*100)) ?>%"></span></div><div class="small text-muted mt-1">₱<?= number_format((float)$h['revenue'],2) ?> booking value</div></div></div></div>
      <?php endforeach; endif; ?>
    </div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Booking Status</h6><div class="text-muted small mb-3">All recorded bookings</div>
      <?php if (!$booking_status): ?><p class="text-muted small">No data yet.</p><?php else: $bt=array_sum(array_column($booking_status,'c')); foreach($booking_status as $r): $pct=$bt?round($r['c']/$bt*100):0; ?>
        <div class="d-flex justify-content-between small mb-2"><span><span class="status-dot"></span><?= e(ucwords(str_replace('_',' ',$r['status']))) ?></span><strong><?= number_format($r['c']) ?> (<?= $pct ?>%)</strong></div>
      <?php endforeach; endif; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Order Status</h6><div class="text-muted small mb-3">All recorded orders</div>
      <?php if (!$order_status): ?><p class="text-muted small">No data yet.</p><?php else: $ot=array_sum(array_column($order_status,'c')); foreach($order_status as $r): $pct=$ot?round($r['c']/$ot*100):0; ?>
        <div class="d-flex justify-content-between small mb-2"><span><span class="status-dot"></span><?= e(ucwords(str_replace('_',' ',$r['status']))) ?></span><strong><?= number_format($r['c']) ?> (<?= $pct ?>%)</strong></div>
      <?php endforeach; endif; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body">
      <h6 class="fw-bold mb-1">Reviews & Ratings</h6><div class="text-muted small mb-3">Feedback received in this period</div>
      <div class="d-flex justify-content-between py-2 border-bottom"><span>Hotel reviews</span><strong><?= number_format($review_count) ?></strong></div>
      <div class="d-flex justify-content-between py-2 border-bottom"><span>Hotel average</span><strong><?= number_format($avg_hotel_rating,1) ?> ★</strong></div>
      <div class="d-flex justify-content-between py-2 border-bottom"><span>Spot reviews</span><strong><?= number_format($spot_review_count) ?></strong></div>
      <div class="d-flex justify-content-between py-2"><span>Spot average</span><strong><?= number_format($avg_spot_rating,1) ?> ★</strong></div>
    </div></div></div>
  </div>

  <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)"><i class="bi bi-people-fill me-2"></i>Tourist Demographics</h5>
  <div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body"><h6 class="fw-bold mb-3">Tourist Type</h6>
      <?php $ttotal=max(1,$local_count+$intl_count); foreach([['Local',$local_count,'var(--green-mid,#5c8d6a)'],['International',$intl_count,'var(--sand-dark,#d9a441)']] as [$lab,$cnt,$color]): $pct=round($cnt/$ttotal*100); ?>
        <div class="d-flex justify-content-between small mb-1"><span><?= $lab ?></span><strong><?= number_format($cnt) ?> (<?= $pct ?>%)</strong></div><div class="mini-bar mb-3"><span style="width:<?= $pct ?>%;background:<?= $color ?>"></span></div>
      <?php endforeach; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body"><h6 class="fw-bold mb-3">Age Range</h6>
      <?php $ageTotal=max(1,array_sum($age_ordered)); foreach($age_ordered as $bucket=>$count): $pct=round($count/$ageTotal*100); ?><div class="d-flex justify-content-between small mb-1"><span><?= $bucket ?></span><strong><?= number_format($count) ?></strong></div><div class="mini-bar mb-2"><span style="width:<?= $pct ?>%;background:var(--sand-dark,#d9a441)"></span></div><?php endforeach; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="analytics-card"><div class="card-body"><h6 class="fw-bold mb-3">Gender</h6>
      <?php if (!$gender_split): ?><p class="text-muted small">No demographic data yet.</p><?php else: $gt=max(1,array_sum(array_column($gender_split,'c'))); $gl=['male'=>'Male','female'=>'Female','prefer_not_to_say'=>'Prefer not to say']; foreach($gender_split as $g): $pct=round($g['c']/$gt*100); ?><div class="d-flex justify-content-between small mb-1"><span><?= e($gl[$g['gender']]??ucfirst($g['gender'])) ?></span><strong><?= number_format($g['c']) ?> (<?= $pct ?>%)</strong></div><div class="mini-bar mb-2"><span style="width:<?= $pct ?>%"></span></div><?php endforeach; endif; ?>
    </div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="analytics-card"><div class="card-body"><h6 class="fw-bold mb-1">Top Local Origins</h6><div class="text-muted small mb-3">Registered local tourists by province</div>
      <?php if (!$top_provinces): ?><p class="text-muted small">No data yet.</p><?php else: foreach($top_provinces as $p): ?><span class="badge me-1 mb-1" style="background:var(--green-pale);color:var(--green-dark);font-weight:600"><?= e($p['province']) ?> (<?= $p['c'] ?>)</span><?php endforeach; endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="analytics-card"><div class="card-body"><h6 class="fw-bold mb-1">Top International Origins</h6><div class="text-muted small mb-3">Registered international tourists by nationality</div>
      <?php if (!$top_nationalities): ?><p class="text-muted small">No data yet.</p><?php else: foreach($top_nationalities as $n): ?><span class="badge me-1 mb-1" style="background:#f3e2d3;color:var(--terracotta);font-weight:600"><?= e($n['nationality']) ?> (<?= $n['c'] ?>)</span><?php endforeach; endif; ?>
    </div></div></div>
  </div>

  <div class="analytics-card analytics-spot-performance mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1">Spot Performance</h6><div class="text-muted small">Interest views, verified visits and visit conversion</div></div><span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark)"><?= number_format($site_total_views) ?> total views</span></div>
    <div class="table-responsive"><table class="table analytics-table align-middle mb-0 analytics-compact-table"><thead><tr><th>Spot</th><th>City</th><th class="text-end">Views</th><th class="text-end">Visits</th><th class="text-end">Conversion</th><th class="text-end">Avg / Month</th></tr></thead><tbody>
      <?php foreach($spot_stats as $s): ?><tr><td class="fw-bold"><?= e($s['name']) ?></td><td class="text-muted"><?= e($s['city_name']) ?></td><td class="text-end"><?= number_format($s['total_views']) ?></td><td class="text-end fw-bold" style="color:var(--green-dark)"><?= number_format($s['total_checkins']) ?></td><td class="text-end"><?= number_format($s['conversion'],1) ?>%</td><td class="text-end"><?= number_format($s['avg_per_month'],1) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></div>

  <div class="analytics-card analytics-top-shops"><div class="card-body"><h6 class="fw-bold mb-1">Top Shops</h6><div class="text-muted small mb-3">Most orders in the selected period</div>
    <?php if (!$top_shops): ?><p class="text-muted small mb-0">No order data yet.</p><?php else: ?><div class="table-responsive"><table class="table analytics-table align-middle mb-0 analytics-compact-table"><thead><tr><th>Shop</th><th class="text-end">Orders</th><th class="text-end">Order Value</th></tr></thead><tbody><?php foreach($top_shops as $s): ?><tr><td class="fw-bold"><?= e($s['name']) ?></td><td class="text-end"><?= number_format($s['c']) ?></td><td class="text-end">₱<?= number_format((float)$s['revenue'],2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
  const labels = <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>;
  const users = <?= json_encode($chart_users) ?>;
  const bookings = <?= json_encode($chart_bookings) ?>;
  const orders = <?= json_encode($chart_orders) ?>;
  const views = <?= json_encode($chart_views) ?>;
  const checkins = <?= json_encode($chart_checkins) ?>;
  const css = getComputedStyle(document.documentElement);
  const green = css.getPropertyValue('--green-mid').trim() || '#5c8d6a';
  const dark = css.getPropertyValue('--green-dark').trim() || '#285943';
  const accent = css.getPropertyValue('--sand-dark').trim() || '#d9a441';
  const primary = css.getPropertyValue('--theme-primary').trim() || '#a61c1c';
  const muted = '#9aa3ad';
  const ctx = document.getElementById('activityChart');
  if (ctx && window.Chart) new Chart(ctx, {type:'line',data:{labels,datasets:[
    {label:'New tourists',data:users,borderColor:green,backgroundColor:green+'22',fill:true,tension:.35,borderWidth:2,pointRadius:0},
    {label:'Bookings',data:bookings,borderColor:primary,backgroundColor:primary+'12',fill:false,tension:.35,borderWidth:2,pointRadius:0},
    {label:'Orders',data:orders,borderColor:accent,backgroundColor:accent+'12',fill:false,tension:.35,borderWidth:2,pointRadius:0}
  ]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:10}},y:{beginAtZero:true,ticks:{precision:0}}}}});
  const vc = document.getElementById('visitChart');
  if (vc && window.Chart) new Chart(vc,{type:'doughnut',data:{labels:['Interest views','Verified check-ins'],datasets:[{data:[<?= (int)$site_total_views ?>,<?= (int)$site_total_checkins ?>],backgroundColor:[muted,green],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8}}}}});
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</div>
