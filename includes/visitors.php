<?php
// ============================================================
// iEXPLORE LAGUNA — Visitor origins
// includes/visitors.php
//
// Where visitors come from (province, city/municipality, barangay,
// nationality), combining two sources:
//   1. App users   — tourists with an account who checked in at a spot,
//                    booked a hotel, or ordered from a shop. Counted once
//                    each per period, using the origin on their account.
//   2. Walk-ins    — visitors recorded by staff in the visitor log
//                    (table walkin_visits). Counts only, never names.
//
// Totals and percentages only. No names or emails are exposed here.
// ============================================================

require_once __DIR__ . '/helpers.php';

/** Walk-in log table. Created on first use, like admin_activity_log. */
function ensure_walkin_table(): void {
    static $done = false;
    if ($done) return;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS walkin_visits (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            city_id INT UNSIGNED NOT NULL,
            spot_id INT UNSIGNED NULL,
            hotel_id INT UNSIGNED NULL,
            shop_id INT UNSIGNED NULL,
            recorded_by INT UNSIGNED NOT NULL,
            visit_date DATE NOT NULL,
            visitor_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            tourist_type VARCHAR(20) NOT NULL,
            province VARCHAR(100) NULL,
            city VARCHAR(100) NULL,
            barangay VARCHAR(100) NULL,
            nationality VARCHAR(100) NULL,
            purpose VARCHAR(30) NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_city_date (city_id, visit_date),
            KEY idx_hotel_date (hotel_id, visit_date),
            KEY idx_shop_date (shop_id, visit_date),
            KEY idx_recorded_by (recorded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $done = true;
}

/** Normalise free text so "SANTA rosa " and "Santa Rosa" group together. */
function visitor_title(?string $s): string {
    $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
    return $s === '' ? '' : mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
}

/** "Brgy. Pulo", "barangay Pulo" and "Pulo" are the same barangay. */
function visitor_barangay_name(?string $s): string {
    $s = trim((string) $s);
    $s = preg_replace('/^(brgy\.?|barangay|bgy\.?)\s+/iu', '', $s);
    return visitor_title($s);
}

// ── Reporting periods ────────────────────────────────────────
function visitor_periods(): array {
    return [
        'today' => ['label' => 'Today',     'long' => 'today'],
        '7d'    => ['label' => '7 days',    'long' => 'the last 7 days'],
        '30d'   => ['label' => '30 days',   'long' => 'the last 30 days'],
        '90d'   => ['label' => '3 months',  'long' => 'the last 3 months'],
        '12m'   => ['label' => '12 months', 'long' => 'the last 12 months'],
    ];
}

/**
 * From/to (to is exclusive) for a period key, using the DATABASE clock in
 * Philippine time so "today" matches how check-ins and orders were stored.
 */
function visitor_period_bounds(string $key): array {
    $periods = visitor_periods();
    if (!isset($periods[$key])) $key = '30d';
    try { db()->exec("SET time_zone = '+08:00'"); } catch (Throwable $e) { /* keep server default */ }
    $clock = db_fetch_one("SELECT CURDATE() AS d");
    $today = new DateTimeImmutable((string) $clock['d']);

    switch ($key) {
        case 'today': $start = $today; break;
        case '7d':    $start = $today->modify('-6 day');  break;
        case '30d':   $start = $today->modify('-29 day'); break;
        case '90d':   $start = $today->modify('-90 day'); break;
        default:      $start = $today->modify('first day of this month')->modify('-11 month'); break;
    }
    if     ($key === 'today') $window = $today->format('l, F j');
    elseif ($key === '12m')   $window = $start->format('M Y') . ' – ' . $today->format('M Y');
    else                      $window = $start->format('M j') . ' – ' . $today->format('M j, Y');

    return [
        'key'    => $key,
        'from'   => $start->format('Y-m-d 00:00:00'),
        'to'     => $today->modify('+1 day')->format('Y-m-d 00:00:00'),
        'label'  => $periods[$key]['label'],
        'long'   => $periods[$key]['long'],
        'window' => $window,
        'today'  => $today->format('Y-m-d'),
    ];
}

// ── The data ─────────────────────────────────────────────────
/**
 * Raw origin rows for a scope and period.
 *
 * $scope = ['type' => 'city'|'hotel'|'shop'|'all', 'id' => int]
 * $from inclusive, $to exclusive ('Y-m-d H:i:s').
 *
 * Returns rows of: src ('app'|'walkin'), tourist_type, province, city,
 * barangay, nationality, n (people).
 */
function visitor_origin_rows(array $scope, string $from, string $to): array {
    ensure_user_demographic_columns();
    ensure_walkin_table();

    $type = (string) ($scope['type'] ?? 'all');
    $id   = (int) ($scope['id'] ?? 0);
    if (in_array($type, ['city', 'hotel', 'shop'], true) && $id <= 0) return [];

    // ---- App users: distinct tourists, from the three kinds of real activity ----
    $parts = []; $params = [];
    if ($type === 'city' || $type === 'all') {
        $parts[]  = "SELECT c.user_id AS uid FROM spot_checkins c
                     JOIN tourist_spots s ON s.id = c.spot_id
                     WHERE c.checked_in_at >= ? AND c.checked_in_at < ?" . ($type === 'city' ? " AND s.city_id = ?" : "");
        array_push($params, $from, $to); if ($type === 'city') $params[] = $id;
    }
    if ($type === 'city' || $type === 'all' || $type === 'hotel') {
        $cond = $type === 'city' ? " AND h.city_id = ?" : ($type === 'hotel' ? " AND b.hotel_id = ?" : "");
        $parts[]  = "SELECT b.tourist_id AS uid FROM bookings b
                     JOIN hotels h ON h.id = b.hotel_id
                     WHERE b.status NOT IN ('cancelled','no_show')
                       AND b.created_at >= ? AND b.created_at < ?" . $cond;
        array_push($params, $from, $to); if ($type !== 'all') $params[] = $id;
    }
    if ($type === 'city' || $type === 'all' || $type === 'shop') {
        $cond = $type === 'city' ? " AND s.city_id = ?" : ($type === 'shop' ? " AND o.shop_id = ?" : "");
        $parts[]  = "SELECT o.tourist_id AS uid FROM orders o
                     JOIN shops s ON s.id = o.shop_id
                     WHERE o.status <> 'cancelled'
                       AND o.created_at >= ? AND o.created_at < ?" . $cond;
        array_push($params, $from, $to); if ($type !== 'all') $params[] = $id;
    }

    $rows = [];
    if ($parts) {
        $sql = "SELECT u.tourist_type, u.province, u.city, u.barangay, u.nationality, COUNT(*) AS n
                FROM (" . implode(' UNION ', $parts) . ") v
                JOIN users u ON u.id = v.uid
                WHERE u.role = 'tourist'
                GROUP BY u.tourist_type, u.province, u.city, u.barangay, u.nationality";
        foreach (db_fetch_all($sql, $params) as $r) { $r['src'] = 'app'; $rows[] = $r; }
    }

    // ---- Walk-ins recorded by staff ----
    $w = "visit_date >= ? AND visit_date < ?";
    $wp = [substr($from, 0, 10), substr($to, 0, 10)];
    if     ($type === 'city')  { $w .= " AND city_id = ?";  $wp[] = $id; }
    elseif ($type === 'hotel') { $w .= " AND hotel_id = ?"; $wp[] = $id; }
    elseif ($type === 'shop')  { $w .= " AND shop_id = ?";  $wp[] = $id; }
    foreach (db_fetch_all(
        "SELECT tourist_type, province, city, barangay, nationality, SUM(visitor_count) AS n
         FROM walkin_visits WHERE $w
         GROUP BY tourist_type, province, city, barangay, nationality", $wp) as $r) {
        $r['src'] = 'walkin'; $rows[] = $r;
    }
    return $rows;
}

/** The label a row belongs to at a given level. */
function visitor_origin_label(array $r, string $level): string {
    $intl = (($r['tourist_type'] ?? '') === 'international');
    $prov = visitor_title($r['province'] ?? '');
    $city = visitor_title($r['city'] ?? '');
    $brgy = visitor_barangay_name($r['barangay'] ?? '');
    $nat  = visitor_title($r['nationality'] ?? '');

    switch ($level) {
        case 'country':
            return $intl ? ($nat !== '' ? $nat : 'Nationality not given') : 'Philippines';
        case 'city':
            if ($intl) return 'International';
            if ($city !== '') return $prov !== '' ? "$city, $prov" : $city;
            return $prov !== '' ? "City not given, $prov" : 'Not specified';
        case 'barangay':
            if ($intl) return 'International';
            if ($brgy === '') return 'Barangay not given';
            $tail = array_filter([$city, $prov], fn($x) => $x !== '');
            return 'Brgy. ' . $brgy . ($tail ? ', ' . implode(', ', $tail) : '');
        default: // province
            if ($intl) return 'International';
            return $prov !== '' ? $prov : 'Not specified';
    }
}

/**
 * Roll the raw rows up to one level.
 * Returns [ ['label'=>, 'app'=>, 'walkin'=>, 'total'=>, 'pct'=>], ... ] biggest first.
 */
function visitor_origin_summary(array $rows, string $level): array {
    $agg = []; $grand = 0;
    foreach ($rows as $r) {
        $label = visitor_origin_label($r, $level);
        $key   = mb_strtolower($label);
        $n     = (int) $r['n'];
        if (!isset($agg[$key])) $agg[$key] = ['label' => $label, 'app' => 0, 'walkin' => 0];
        $agg[$key][$r['src'] === 'walkin' ? 'walkin' : 'app'] += $n;
        $grand += $n;
    }
    foreach ($agg as &$a) {
        $a['total'] = $a['app'] + $a['walkin'];
        $a['pct']   = $grand > 0 ? round($a['total'] / $grand * 100, 1) : 0;
    }
    unset($a);
    uasort($agg, fn($x, $y) => [$y['total'], $x['label']] <=> [$x['total'], $y['label']]);
    return array_values($agg);
}

/** Totals: people overall, local vs international, app vs walk-in. */
function visitor_origin_totals(array $rows): array {
    $t = ['total' => 0, 'local' => 0, 'international' => 0, 'app' => 0, 'walkin' => 0];
    foreach ($rows as $r) {
        $n = (int) $r['n'];
        $t['total'] += $n;
        $t[(($r['tourist_type'] ?? '') === 'international') ? 'international' : 'local'] += $n;
        $t[$r['src'] === 'walkin' ? 'walkin' : 'app'] += $n;
    }
    return $t;
}

// ── Form helpers ─────────────────────────────────────────────
function visitor_levels(): array {
    return ['province' => 'Province', 'city' => 'City / Municipality', 'barangay' => 'Barangay', 'country' => 'Nationality'];
}

function visitor_purposes(): array {
    return ['leisure' => 'Leisure / sightseeing', 'event' => 'Event or festival', 'business' => 'Business',
            'education' => 'School / educational', 'religious' => 'Religious / pilgrimage', 'other' => 'Other'];
}

function visitor_province_options(): array {
    return ['Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao','Aurora','Basilan','Bataan',
        'Batanes','Batangas','Benguet','Biliran','Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur',
        'Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro','Davao del Norte','Davao del Sur',
        'Davao Occidental','Davao Oriental','Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte','Ilocos Sur',
        'Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte','Lanao del Sur','Leyte','Maguindanao del Norte',
        'Maguindanao del Sur','Marinduque','Masbate','Metro Manila','Misamis Occidental','Misamis Oriental','Mountain Province',
        'Negros Occidental','Negros Oriental','Northern Samar','Nueva Ecija','Nueva Vizcaya','Occidental Mindoro',
        'Oriental Mindoro','Palawan','Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar','Sarangani',
        'Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat','Sulu','Surigao del Norte','Surigao del Sur',
        'Tarlac','Tawi-Tawi','Zambales','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'];
}

function visitor_nationality_options(): array {
    return ['American','Australian','British','Canadian','Chinese','Taiwanese','Hong Konger','Japanese','Korean','Singaporean',
        'Malaysian','Indonesian','Thai','Vietnamese','Indian','German','French','Spanish','Italian','Dutch','Swiss','Swedish',
        'Norwegian','Danish','Russian','Emirati','Saudi','Israeli','Brazilian','Mexican','New Zealander','Irish'];
}

// ── Compact panel for dashboards ─────────────────────────────
function visitor_origin_css(): void {
    static $printed = false;
    if ($printed) return;
    $printed = true; ?>
<style>
  .vo-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)}
  .vo-label{font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);font-weight:600}
  .vo-h{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--maroon-dark);margin:0}
  .vo-big{font-family:'Playfair Display',serif;font-size:2.4rem;line-height:1;color:var(--maroon-dark)}
  .vo-muted{font-size:.8rem;color:var(--text-muted)}
  .vo-split{display:flex;height:8px;border-radius:4px;overflow:hidden;background:var(--maroon-pale)}
  .vo-split i{display:block;height:100%}
  .vo-split .is-local{background:var(--maroon-mid)}
  .vo-split .is-intl{background:var(--sand-dark)}
  .vo-row{padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.88rem}
  .vo-row:last-child{border-bottom:0}
  .vo-bar{height:5px;border-radius:3px;background:var(--maroon-pale);margin-top:5px;overflow:hidden}
  .vo-bar i{display:block;height:100%;background:var(--maroon-mid);border-radius:3px}
  .vo-link{font-size:.8rem;font-weight:600;color:var(--maroon-mid);text-decoration:none}
  .vo-link:hover{color:var(--maroon-dark);text-decoration:underline}
  .vo-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:.35rem}
</style>
<?php }

/**
 * Compact "where visitors come from" card.
 * $opts: title, level (province|city|barangay|country), top (rows to show),
 *        log_url (full report link), can_record (bool, wording only)
 */
function render_visitor_origin_panel(array $scope, string $from, string $to, string $periodLong, array $opts = []): void {
    visitor_origin_css();
    $level  = $opts['level'] ?? 'province';
    $top    = (int) ($opts['top'] ?? 5);
    $title  = $opts['title'] ?? 'Where visitors come from';
    $logUrl = $opts['log_url'] ?? (APP_URL . '/pages/visitor-log.php');
    $levels = visitor_levels();

    $rows    = visitor_origin_rows($scope, $from, $to);
    $totals  = visitor_origin_totals($rows);
    $summary = visitor_origin_summary($rows, $level);
    $shown   = array_slice($summary, 0, $top);
    $max     = $shown ? max(1, $shown[0]['total']) : 1;
    $pLocal  = $totals['total'] ? round($totals['local'] / $totals['total'] * 100) : 0;
    $pIntl   = $totals['total'] ? 100 - $pLocal : 0;
    ?>
    <section class="vo-card p-4">
      <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-2">
        <div>
          <h2 class="vo-h"><?= e($title) ?></h2>
          <div class="vo-muted"><?= e(ucfirst($periodLong)) ?> · by <?= e(strtolower($levels[$level] ?? 'province')) ?></div>
        </div>
        <a class="vo-link" href="<?= e($logUrl) ?>">Full report<?= !empty($opts['can_record']) ? ' &amp; walk-in log' : '' ?> →</a>
      </div>

      <?php if (!$totals['total']): ?>
        <p class="vo-muted mb-0 mt-3">No visitor origins for this period yet. Tourists with an account are counted automatically when they check in at a spot, book a hotel or order from a shop<?= !empty($opts['can_record']) ? ', and you can add walk-in visitors in the visitor log' : '' ?>.</p>
      <?php else: ?>
        <div class="d-flex align-items-baseline gap-3 mt-3">
          <div class="vo-big"><?= number_format($totals['total']) ?></div>
          <div class="vo-muted">visitors<?= $totals['walkin'] ? ' (' . number_format($totals['app']) . ' with accounts, ' . number_format($totals['walkin']) . ' walk-in)' : '' ?></div>
        </div>
        <div class="vo-split mt-2" role="img" aria-label="<?= $pLocal ?>% local, <?= $pIntl ?>% international">
          <i class="is-local" style="width:<?= $pLocal ?>%"></i><i class="is-intl" style="width:<?= $pIntl ?>%"></i>
        </div>
        <div class="vo-muted mt-1"><span class="vo-dot" style="background:var(--maroon-mid)"></span>Local <?= $pLocal ?>% &nbsp; <span class="vo-dot" style="background:var(--sand-dark)"></span>International <?= $pIntl ?>%</div>

        <div class="mt-3">
          <?php foreach ($shown as $r): ?>
            <div class="vo-row">
              <div class="d-flex justify-content-between gap-2">
                <span class="text-truncate"><?= e($r['label']) ?></span>
                <span class="text-nowrap"><strong><?= number_format($r['total']) ?></strong> <span class="vo-muted">· <?= rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') ?>%</span></span>
              </div>
              <div class="vo-bar"><i style="width:<?= (int) round($r['total'] / $max * 100) ?>%"></i></div>
            </div>
          <?php endforeach; ?>
          <?php if (count($summary) > $top): ?>
            <div class="vo-muted mt-2">+ <?= count($summary) - $top ?> more in the full report.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
}
