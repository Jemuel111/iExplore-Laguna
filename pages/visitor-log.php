<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/visitors.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Visitor Log
// pages/visitor-log.php
//
// Where visitors come from, for one city, hotel or shop:
//   * app users (check-ins, bookings, orders) counted automatically
//   * walk-in visitors recorded here by staff (counts only, no names)
// City admins, hotel owners and shop owners can record walk-ins for their
// own place. The super admin can view (all cities or one), not record.
// ============================================================
$page_title  = 'Visitor Log';
$active_page = 'visitor-log';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u    = current_user();
$uid  = (int) $u['id'];
$role = (string) ($u['role'] ?? '');
$self = APP_URL . '/pages/visitor-log.php';

// ── Who is this, and what may they see? ───────────────────────
$ctx = ['type' => 'all', 'id' => 0, 'city_id' => 0, 'place' => 'All cities', 'can_record' => false,
        'lede' => 'Visitors across Laguna', 'back' => APP_URL, 'spots' => []];

switch ($role) {
    case 'city_admin':
        $cid = (int) admin_scope();
        if ($cid <= 0) { header('Location: ' . APP_URL . '/pages/city-dashboard.php'); exit; }
        $c = db_fetch_one("SELECT name FROM cities WHERE id = ?", [$cid]);
        $ctx = ['type' => 'city', 'id' => $cid, 'city_id' => $cid, 'place' => (string) ($c['name'] ?? 'Your city'),
                'can_record' => true, 'back' => APP_URL . '/pages/city-dashboard.php',
                'lede' => 'Everyone visiting your city: check-ins, hotel bookings, shop orders and walk-ins',
                'spots' => db_fetch_all("SELECT id, name FROM tourist_spots WHERE city_id = ? ORDER BY name", [$cid])];
        break;

    case 'hotel_owner':
        $h = db_fetch_one("SELECT id, name, city_id FROM hotels WHERE owner_id = ? LIMIT 1", [$uid]);
        if (!$h) { header('Location: ' . APP_URL . '/pages/hotel-dashboard.php'); exit; }
        $ctx = ['type' => 'hotel', 'id' => (int) $h['id'], 'city_id' => (int) $h['city_id'], 'place' => (string) $h['name'],
                'can_record' => true, 'back' => APP_URL . '/pages/hotel-dashboard.php',
                'lede' => 'Your guests: people who booked with you, plus walk-in guests you record here', 'spots' => []];
        break;

    case 'shop_owner':
        $s = db_fetch_one("SELECT id, name, city_id FROM shops WHERE owner_id = ? LIMIT 1", [$uid]);
        if (!$s) { header('Location: ' . APP_URL . '/pages/shop-dashboard.php'); exit; }
        $ctx = ['type' => 'shop', 'id' => (int) $s['id'], 'city_id' => (int) $s['city_id'], 'place' => (string) $s['name'],
                'can_record' => true, 'back' => APP_URL . '/pages/shop-dashboard.php',
                'lede' => 'Your customers: people who ordered from you, plus walk-in customers you record here', 'spots' => []];
        break;

    case 'admin':
        $ctx['back'] = APP_URL . '/pages/admin-dashboard.php';
        $filterCity  = (int) input('city', 'get', 0);
        if ($filterCity > 0) {
            $c = db_fetch_one("SELECT name FROM cities WHERE id = ?", [$filterCity]);
            if ($c) { $ctx['type'] = 'city'; $ctx['id'] = $filterCity; $ctx['place'] = (string) $c['name']; $ctx['lede'] = 'Visitors to ' . $c['name']; }
        }
        break;

    default:
        header('Location: ' . APP_URL); exit;
}
$scope = ['type' => $ctx['type'], 'id' => $ctx['id']];

// SQL that limits walk-in rows to this person's own place.
function vl_scope_sql(array $ctx): array {
    switch ($ctx['type']) {
        case 'city':  return ['w.city_id = ?',  [$ctx['id']]];
        case 'hotel': return ['w.hotel_id = ?', [$ctx['id']]];
        case 'shop':  return ['w.shop_id = ?',  [$ctx['id']]];
        default:      return ['1 = 1', []];
    }
}

ensure_walkin_table();
$period = visitor_period_bounds((string) input('range', 'get', '30d'));
$level  = (string) input('level', 'get', 'province');
if (!isset(visitor_levels()[$level])) { $level = 'province'; }
$keep = ['range' => $period['key'], 'level' => $level] + ($role === 'admin' && $ctx['type'] === 'city' ? ['city' => $ctx['id']] : []);
$qs   = fn(array $over = []) => '?' . http_build_query(array_merge($keep, $over));

// ── CSV export (finest detail, one row per origin) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && input('export', 'get', '') === 'csv') {
    $rows = visitor_origin_rows($scope, $period['from'], $period['to']);
    $agg = [];
    foreach ($rows as $r) {
        $intl = ($r['tourist_type'] ?? '') === 'international';
        $row  = [$intl ? 'International' : 'Local',
                 $intl ? '' : visitor_title($r['province'] ?? ''),
                 $intl ? '' : visitor_title($r['city'] ?? ''),
                 $intl ? '' : visitor_barangay_name($r['barangay'] ?? ''),
                 $intl ? visitor_title($r['nationality'] ?? '') : 'Filipino'];
        $key = mb_strtolower(implode('|', $row));
        if (!isset($agg[$key])) $agg[$key] = ['cols' => $row, 'app' => 0, 'walkin' => 0];
        $agg[$key][$r['src'] === 'walkin' ? 'walkin' : 'app'] += (int) $r['n'];
    }
    uasort($agg, fn($a, $b) => ($b['app'] + $b['walkin']) <=> ($a['app'] + $a['walkin']));

    $safe = fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;   // spreadsheet formula guard
    while (ob_get_level()) { ob_end_clean(); }
    $fname = 'visitors_' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($ctx['place'])) . '_' . substr($period['from'], 0, 10) . '_to_' . $period['today'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Place', $ctx['place']]);
    fputcsv($out, ['Period', $period['window']]);
    fputcsv($out, []);
    fputcsv($out, ['Visitor type', 'Province', 'City / Municipality', 'Barangay', 'Nationality', 'Visitors with accounts', 'Walk-in visitors', 'Total']);
    foreach ($agg as $a) {
        fputcsv($out, array_merge(array_map($safe, $a['cols']), [$a['app'], $a['walkin'], $a['app'] + $a['walkin']]));
    }
    fclose($out);
    exit;
}

// ── POST: record / delete walk-ins ────────────────────────────
$errors = [];
$old = ['visit_date' => $period['today'], 'visitor_count' => 1, 'tourist_type' => 'local', 'province' => 'Laguna',
        'city' => '', 'barangay' => '', 'nationality' => '', 'purpose' => '', 'notes' => '', 'spot_id' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ctx['can_record']) {
    $action = (string) input('action', 'post', '');

    if ($action === 'add_walkin') {
        foreach (['visit_date', 'tourist_type', 'province', 'city', 'barangay', 'nationality', 'purpose', 'notes'] as $k) {
            $old[$k] = trim((string) input($k, 'post', $old[$k]));
        }
        $old['visitor_count'] = (int) input('visitor_count', 'post', 1);
        $old['spot_id']       = (int) input('spot_id', 'post', 0);

        // Date: a real date, not in the future, not older than a year
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $old['visit_date']);
        $todayD = new DateTimeImmutable($period['today']);
        if (!$d || $d->format('Y-m-d') !== $old['visit_date']) {
            $errors[] = 'Please choose a valid date.';
        } elseif ($d > $todayD) {
            $errors[] = 'The visit date cannot be in the future.';
        } elseif ($d < $todayD->modify('-366 day')) {
            $errors[] = 'The visit date is more than a year ago.';
        }
        if ($old['visitor_count'] < 1 || $old['visitor_count'] > 1000) $errors[] = 'Number of visitors must be between 1 and 1,000.';
        if (!in_array($old['tourist_type'], ['local', 'international'], true)) $errors[] = 'Please choose local or international.';
        foreach (['province', 'city', 'barangay', 'nationality'] as $k) {
            if (mb_strlen($old[$k]) > 100) $errors[] = 'Place names must be 100 characters or fewer.';
        }
        if ($old['tourist_type'] === 'local') {
            if ($old['province'] === '') $errors[] = 'Please enter the province.';
            if ($old['city'] === '')     $errors[] = 'Please enter the city or municipality.';
        } elseif ($old['tourist_type'] === 'international' && $old['nationality'] === '') {
            $errors[] = 'Please enter the nationality.';
        }
        if ($old['purpose'] !== '' && !isset(visitor_purposes()[$old['purpose']])) $errors[] = 'Please choose a purpose from the list.';
        if (mb_strlen($old['notes']) > 255) $errors[] = 'Notes must be 255 characters or fewer.';

        // A city admin may tie the entry to one of their own spots; nothing else is trusted from the form.
        $spotId = null;
        if ($ctx['type'] === 'city' && $old['spot_id'] > 0) {
            if (admin_owns_spot($old['spot_id'])) { $spotId = $old['spot_id']; }
            else { $errors[] = 'That spot is not in your city.'; }
        }

        if (!$errors) {
            $local = $old['tourist_type'] === 'local';
            db_execute(
                "INSERT INTO walkin_visits (city_id, spot_id, hotel_id, shop_id, recorded_by, visit_date, visitor_count,
                                            tourist_type, province, city, barangay, nationality, purpose, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$ctx['city_id'], $spotId,
                 $ctx['type'] === 'hotel' ? $ctx['id'] : null,
                 $ctx['type'] === 'shop'  ? $ctx['id'] : null,
                 $uid, $old['visit_date'], $old['visitor_count'], $old['tourist_type'],
                 $local ? visitor_title($old['province']) : null,
                 $local ? visitor_title($old['city']) : null,
                 ($local && $old['barangay'] !== '') ? visitor_barangay_name($old['barangay']) : null,
                 !$local ? visitor_title($old['nationality']) : null,
                 $old['purpose'] !== '' ? $old['purpose'] : null,
                 $old['notes'] !== '' ? $old['notes'] : null]
            );
            if ($role === 'city_admin') {
                log_admin_activity('record_walkin', 'walkin', (int) db_last_id(), $ctx['city_id'], $old['visitor_count'] . ' visitor(s)');
            }
            $_SESSION['flash']['success'] = $old['visitor_count'] . ' visitor' . ($old['visitor_count'] === 1 ? '' : 's') . ' recorded.';
            header('Location: ' . $self . $qs() . '#record'); exit;
        }
    }

    if ($action === 'delete_walkin') {
        $wid = (int) input('walkin_id', 'post', 0);
        [$sc, $sp] = vl_scope_sql($ctx);
        $row = db_fetch_one("SELECT w.id, w.recorded_by FROM walkin_visits w WHERE w.id = ? AND $sc", array_merge([$wid], $sp));
        // Own entries only, except a city admin may remove any entry in their city.
        if ($row && ((int) $row['recorded_by'] === $uid || $role === 'city_admin')) {
            db_execute("DELETE FROM walkin_visits WHERE id = ?", [$wid]);
            if ($role === 'city_admin') { log_admin_activity('delete_walkin', 'walkin', $wid, $ctx['city_id'], ''); }
            $_SESSION['flash']['success'] = 'Entry removed.';
        } else {
            $_SESSION['flash']['danger'] = 'You can only remove entries from your own place.';
        }
        header('Location: ' . $self . $qs() . '#record'); exit;
    }
}

// ── Report data ───────────────────────────────────────────────
$rows    = visitor_origin_rows($scope, $period['from'], $period['to']);
$totals  = visitor_origin_totals($rows);
$summary = visitor_origin_summary($rows, $level);
$maxRow  = $summary ? max(1, $summary[0]['total']) : 1;
$pLocal  = $totals['total'] ? round($totals['local'] / $totals['total'] * 100) : 0;

[$sc, $sp] = vl_scope_sql($ctx);
$recent = db_fetch_all(
    "SELECT w.*, u.name AS by_name, s.name AS spot_name
     FROM walkin_visits w
     LEFT JOIN users u ON u.id = w.recorded_by
     LEFT JOIN tourist_spots s ON s.id = w.spot_id
     WHERE $sc
     ORDER BY w.visit_date DESC, w.id DESC LIMIT 20", $sp);

$lagunaTowns = array_column(db_fetch_all("SELECT name FROM cities ORDER BY name"), 'name');
$adminCities = $role === 'admin' ? db_fetch_all("SELECT id, name FROM cities ORDER BY name") : [];

require_once __DIR__ . '/../includes/header.php';
visitor_origin_css();
?>
<style>
  .vl-mast{position:relative;color:#fff;background:var(--maroon-dark);overflow:hidden;border-bottom:3px solid var(--sand-dark)}
  .vl-mast::before{content:"";position:absolute;inset:0;pointer-events:none;
    background:repeating-linear-gradient(45deg, rgba(255,255,255,.07) 0 9px, transparent 9px 18px),
               repeating-linear-gradient(-45deg, rgba(0,0,0,.10) 0 9px, transparent 9px 18px);
    -webkit-mask-image:linear-gradient(90deg,transparent 25%,#000 85%);mask-image:linear-gradient(90deg,transparent 25%,#000 85%)}
  .vl-mast .container{position:relative;padding-top:1.75rem;padding-bottom:1.5rem}
  .vl-eyebrow{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:var(--sand-dark);font-weight:600}
  .vl-title{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,4vw,2.6rem);line-height:1.05;margin:.3rem 0 .4rem}
  .vl-lede{font-size:.92rem;color:rgba(255,255,255,.82);margin:0;max-width:62ch}
  .vl-back{font-size:.82rem;color:rgba(255,255,255,.8);text-decoration:none}
  .vl-back:hover{color:#fff;text-decoration:underline}
  .vl-range,.vl-tabs{display:inline-flex;flex-wrap:wrap;background:#fff;border:1px solid var(--border);border-radius:var(--radius-pill);padding:3px;gap:2px}
  .vl-range a,.vl-tabs a{font-size:.8rem;font-weight:600;padding:.3rem .85rem;border-radius:var(--radius-pill);color:var(--text-muted);text-decoration:none}
  .vl-range a:hover,.vl-tabs a:hover{color:var(--maroon-dark)}
  .vl-range a.is-active,.vl-tabs a.is-active{background:var(--maroon-dark);color:#fff}
  .vl-table{width:100%;font-size:.9rem}
  .vl-table th{font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);font-weight:600;padding:.5rem .5rem;border-bottom:1px solid var(--border)}
  .vl-table td{padding:.6rem .5rem;border-bottom:1px solid var(--border);vertical-align:middle}
  .vl-table tr:last-child td{border-bottom:0}
  .vl-num{text-align:right;white-space:nowrap}
  .vl-field-intl{display:none}
  .vl-recent{font-size:.86rem}
  .vl-recent li{padding:.6rem 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem}
  .vl-recent li:last-child{border-bottom:0}
</style>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<header class="vl-mast">
  <div class="container">
    <a class="vl-back" href="<?= e($ctx['back']) ?>">← Back to dashboard</a>
    <div class="vl-eyebrow mt-2">Visitor log</div>
    <h1 class="vl-title"><?= e($ctx['place']) ?></h1>
    <p class="vl-lede"><?= e($ctx['lede']) ?></p>
  </div>
</header>

<div class="container py-4">

  <!-- Period + export -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="vo-muted"><strong style="color:var(--charcoal)"><?= e($period['window']) ?></strong></div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if ($role === 'admin'): ?>
        <form method="GET" class="d-flex gap-2">
          <input type="hidden" name="range" value="<?= e($period['key']) ?>"><input type="hidden" name="level" value="<?= e($level) ?>">
          <select name="city" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="City">
            <option value="0">All cities</option>
            <?php foreach ($adminCities as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $ctx['id'] && $ctx['type'] === 'city' ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <nav class="vl-range" aria-label="Reporting period">
        <?php foreach (visitor_periods() as $k => $p): ?>
          <a href="<?= e($qs(['range' => $k])) ?>" class="<?= $k === $period['key'] ? 'is-active' : '' ?>"><?= e($p['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <a class="btn btn-sm btn-outline-secondary" style="border-radius:var(--radius-pill)" href="<?= e($qs(['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i>Download CSV</a>
    </div>
  </div>

  <!-- Report -->
  <section class="vo-card p-4 mb-4">
    <?php if (!$totals['total']): ?>
      <h2 class="vo-h mb-2">No visitors recorded for <?= e($period['long']) ?></h2>
      <p class="vo-muted mb-0">Tourists with an account are counted automatically when they check in at a spot, book a hotel or order from a shop.<?= $ctx['can_record'] ? ' Use the form below to add walk-in visitors.' : '' ?></p>
    <?php else: ?>
      <div class="row g-4 align-items-center mb-3">
        <div class="col-md-4">
          <div class="vo-label">Visitors · <?= e($period['long']) ?></div>
          <div class="vo-big mt-1"><?= number_format($totals['total']) ?></div>
          <div class="vo-muted"><?= number_format($totals['app']) ?> with accounts · <?= number_format($totals['walkin']) ?> walk-in</div>
        </div>
        <div class="col-md-8">
          <div class="vo-split" role="img" aria-label="<?= $pLocal ?>% local, <?= 100 - $pLocal ?>% international">
            <i class="is-local" style="width:<?= $pLocal ?>%"></i><i class="is-intl" style="width:<?= 100 - $pLocal ?>%"></i>
          </div>
          <div class="vo-muted mt-1"><span class="vo-dot" style="background:var(--maroon-mid)"></span>Local <?= number_format($totals['local']) ?> (<?= $pLocal ?>%)
            &nbsp; <span class="vo-dot" style="background:var(--sand-dark)"></span>International <?= number_format($totals['international']) ?> (<?= 100 - $pLocal ?>%)</div>
        </div>
      </div>

      <nav class="vl-tabs mb-3" aria-label="Group by">
        <?php foreach (visitor_levels() as $k => $lbl): ?>
          <a href="<?= e($qs(['level' => $k])) ?>" class="<?= $k === $level ? 'is-active' : '' ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="table-responsive">
        <table class="vl-table">
          <thead><tr><th><?= e(visitor_levels()[$level]) ?></th><th class="vl-num">Accounts</th><th class="vl-num">Walk-ins</th><th class="vl-num">Total</th><th style="width:30%">Share</th></tr></thead>
          <tbody>
            <?php foreach ($summary as $r): ?>
              <tr>
                <td><?= e($r['label']) ?></td>
                <td class="vl-num"><?= number_format($r['app']) ?></td>
                <td class="vl-num"><?= number_format($r['walkin']) ?></td>
                <td class="vl-num"><strong><?= number_format($r['total']) ?></strong></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="vo-bar flex-grow-1" style="margin-top:0"><i style="width:<?= (int) round($r['total'] / $maxRow * 100) ?>%"></i></div>
                    <span class="vo-muted text-nowrap"><?= rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') ?>%</span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="vo-muted mt-3 mb-0">Accounts are counted once per period. Names and contact details are never shown here. Anyone who left their province or city blank is grouped as “Not specified”.</p>
    <?php endif; ?>
  </section>

  <?php if ($ctx['can_record']): ?>
  <div class="row g-4" id="record">

    <!-- Record walk-ins -->
    <div class="col-lg-5">
      <section class="vo-card p-4">
        <h2 class="vo-h mb-1">Record walk-in visitors</h2>
        <p class="vo-muted">For people who did not use the app. Record a group at a time. Do not enter names.</p>

        <?php if ($errors): ?>
          <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST" action="<?= e($self . $qs()) ?>#record" autocomplete="off"><?= csrf_field() ?>
          <input type="hidden" name="action" value="add_walkin">

          <div class="row g-2 mb-3">
            <div class="col-7">
              <label class="form-label">Date of visit</label>
              <input type="date" class="form-control" name="visit_date" required
                     value="<?= e($old['visit_date']) ?>" max="<?= e($period['today']) ?>"
                     min="<?= e((new DateTimeImmutable($period['today']))->modify('-366 day')->format('Y-m-d')) ?>">
            </div>
            <div class="col-5">
              <label class="form-label">Visitors</label>
              <input type="number" class="form-control" name="visitor_count" min="1" max="1000" required value="<?= (int) $old['visitor_count'] ?>">
            </div>
          </div>

          <?php if ($ctx['type'] === 'city' && $ctx['spots']): ?>
          <div class="mb-3">
            <label class="form-label">Where <span class="text-muted small">(optional)</span></label>
            <select class="form-select" name="spot_id">
              <option value="0">Anywhere in <?= e($ctx['place']) ?></option>
              <?php foreach ($ctx['spots'] as $sp): ?>
                <option value="<?= (int) $sp['id'] ?>" <?= (int) $old['spot_id'] === (int) $sp['id'] ? 'selected' : '' ?>><?= e((string) $sp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label d-block">They are</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="tourist_type" id="vl-local" value="local" <?= $old['tourist_type'] === 'local' ? 'checked' : '' ?>>
              <label class="btn btn-outline-secondary" for="vl-local"><i class="bi bi-house-door me-1"></i>Local (Filipino)</label>
              <input type="radio" class="btn-check" name="tourist_type" id="vl-intl" value="international" <?= $old['tourist_type'] === 'international' ? 'checked' : '' ?>>
              <label class="btn btn-outline-secondary" for="vl-intl"><i class="bi bi-airplane me-1"></i>International</label>
            </div>
          </div>

          <div id="vl-local-fields" class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Province</label>
              <input type="text" class="form-control" name="province" list="dl-prov" maxlength="100" value="<?= e($old['province']) ?>">
            </div>
            <div class="col-6">
              <label class="form-label">City / Municipality</label>
              <input type="text" class="form-control" name="city" list="dl-town" maxlength="100" value="<?= e($old['city']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Barangay <span class="text-muted small">(optional)</span></label>
              <input type="text" class="form-control" name="barangay" maxlength="100" value="<?= e($old['barangay']) ?>">
            </div>
          </div>

          <div id="vl-intl-fields" class="mb-3 vl-field-intl">
            <label class="form-label">Nationality</label>
            <input type="text" class="form-control" name="nationality" list="dl-nat" maxlength="100" value="<?= e($old['nationality']) ?>" placeholder="e.g. American, Korean">
          </div>

          <div class="mb-3">
            <label class="form-label">Purpose <span class="text-muted small">(optional)</span></label>
            <select class="form-select" name="purpose">
              <option value="">—</option>
              <?php foreach (visitor_purposes() as $k => $lbl): ?>
                <option value="<?= e($k) ?>" <?= $old['purpose'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
            <input type="text" class="form-control" name="notes" maxlength="255" value="<?= e($old['notes']) ?>" placeholder="e.g. school field trip">
          </div>

          <button class="btn btn-primary-app w-100"><i class="bi bi-plus-lg me-1"></i>Record visitors</button>
        </form>

        <datalist id="dl-prov"><?php foreach (visitor_province_options() as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
        <datalist id="dl-town"><?php foreach ($lagunaTowns as $t): ?><option value="<?= e((string) $t) ?>"><?php endforeach; ?></datalist>
        <datalist id="dl-nat"><?php foreach (visitor_nationality_options() as $n): ?><option value="<?= e($n) ?>"><?php endforeach; ?></datalist>
      </section>
    </div>

    <!-- Recent entries -->
    <div class="col-lg-7">
      <section class="vo-card p-4">
        <h2 class="vo-h mb-3">Recent entries</h2>
        <?php if (!$recent): ?>
          <p class="vo-muted mb-0">Nothing recorded yet.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0 vl-recent">
            <?php foreach ($recent as $w):
              $intl = $w['tourist_type'] === 'international';
              $origin = $intl ? (visitor_title($w['nationality']) ?: 'International')
                              : implode(', ', array_filter([
                                    $w['barangay'] ? 'Brgy. ' . visitor_barangay_name($w['barangay']) : '',
                                    visitor_title($w['city']), visitor_title($w['province'])]));
              $canDel = ((int) $w['recorded_by'] === $uid) || $role === 'city_admin';
            ?>
              <li>
                <div>
                  <strong><?= (int) $w['visitor_count'] ?></strong> visitor<?= (int) $w['visitor_count'] === 1 ? '' : 's' ?> from <?= e($origin) ?>
                  <div class="vo-muted">
                    <?= e(date('M j, Y', strtotime((string) $w['visit_date']))) ?>
                    <?= $w['spot_name'] ? ' · ' . e((string) $w['spot_name']) : '' ?>
                    <?= $w['purpose'] && isset(visitor_purposes()[$w['purpose']]) ? ' · ' . e(visitor_purposes()[$w['purpose']]) : '' ?>
                    · recorded by <?= e((string) ($w['by_name'] ?? 'unknown')) ?>
                    <?= $w['notes'] ? '<br><em>' . e((string) $w['notes']) . '</em>' : '' ?>
                  </div>
                </div>
                <?php if ($canDel): ?>
                  <form method="POST" action="<?= e($self . $qs()) ?>#record" onsubmit="return confirm('Remove this entry?');"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_walkin">
                    <input type="hidden" name="walkin_id" value="<?= (int) $w['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.75rem;padding:.2rem .7rem">Remove</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <script>
  (function () {
    var local = document.getElementById('vl-local'), intl = document.getElementById('vl-intl');
    var lf = document.getElementById('vl-local-fields'), nf = document.getElementById('vl-intl-fields');
    function sync() { var isIntl = intl.checked; lf.style.display = isIntl ? 'none' : ''; nf.style.display = isIntl ? 'block' : 'none'; }
    local.addEventListener('change', sync); intl.addEventListener('change', sync); sync();
  })();
  </script>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
