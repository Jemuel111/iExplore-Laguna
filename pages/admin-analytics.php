<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Admin: Tourist Demographics & Spot Analytics
// pages/admin-analytics.php
// ============================================================
$page_title  = 'Analytics';
$active_page = '';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }

ensure_user_demographic_columns();
ensure_spot_views_table();
ensure_spot_checkins_table();

// ── Tourist demographics ─────────────────────────────────────
$total_tourists = (int) (db_fetch_one(
    "SELECT COUNT(*) AS c FROM users WHERE role = 'tourist'"
)['c'] ?? 0);

$tourist_type_split = db_fetch_all(
    "SELECT tourist_type, COUNT(*) AS c FROM users
     WHERE role = 'tourist' AND tourist_type IS NOT NULL
     GROUP BY tourist_type"
);
$local_count = 0; $intl_count = 0;
foreach ($tourist_type_split as $row) {
    if ($row['tourist_type'] === 'local') $local_count = (int) $row['c'];
    if ($row['tourist_type'] === 'international') $intl_count = (int) $row['c'];
}

$gender_split = db_fetch_all(
    "SELECT gender, COUNT(*) AS c FROM users
     WHERE role = 'tourist' AND gender IS NOT NULL
     GROUP BY gender"
);

$age_buckets = db_fetch_all(
    "SELECT
        CASE
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 24 THEN '18–24'
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 25 AND 34 THEN '25–34'
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 35 AND 44 THEN '35–44'
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 45 AND 54 THEN '45–54'
            ELSE '55+'
        END AS bucket,
        COUNT(*) AS c
     FROM users
     WHERE role = 'tourist' AND birthdate IS NOT NULL
     GROUP BY bucket"
);
// Force a consistent display order regardless of what SQL returned
$age_order = ['Under 18', '18–24', '25–34', '35–44', '45–54', '55+'];
$age_map = array_column($age_buckets, 'c', 'bucket');
$age_ordered = [];
foreach ($age_order as $b) $age_ordered[$b] = (int) ($age_map[$b] ?? 0);

$top_provinces = db_fetch_all(
    "SELECT province, COUNT(*) AS c FROM users
     WHERE role='tourist' AND tourist_type='local' AND province IS NOT NULL AND province != ''
     GROUP BY province ORDER BY c DESC LIMIT 8"
);
$top_nationalities = db_fetch_all(
    "SELECT nationality, COUNT(*) AS c FROM users
     WHERE role='tourist' AND tourist_type='international' AND nationality IS NOT NULL AND nationality != ''
     GROUP BY nationality ORDER BY c DESC LIMIT 8"
);

// ── Spot visit analytics ──────────────────────────────────────
// "Interest views" (automatic page views) vs "Confirmed visits"
// (GPS-verified check-ins) — kept as two distinct, honestly-labeled
// columns rather than one blended number. See haversine_meters() /
// api/spot-checkin.php for how a check-in gets verified.
$spot_stats = db_fetch_all(
    "SELECT s.id, s.name, c.name AS city_name,
            (SELECT COUNT(*) FROM spot_views v WHERE v.spot_id = s.id) AS total_views,
            (SELECT COUNT(*) FROM spot_checkins k WHERE k.spot_id = s.id) AS total_checkins,
            (SELECT MIN(k2.checked_in_at) FROM spot_checkins k2 WHERE k2.spot_id = s.id) AS first_checkin
     FROM tourist_spots s
     JOIN cities c ON s.city_id = c.id
     WHERE s.is_active = 1
     ORDER BY total_checkins DESC, total_views DESC"
);
foreach ($spot_stats as &$row) {
    $monthsActive = 1;
    if ($row['first_checkin']) {
        $monthsActive = max(1, (int) ((strtotime('now') - strtotime($row['first_checkin'])) / (30 * 86400)) + 1);
    }
    $row['avg_per_month'] = $row['total_checkins'] > 0 ? round($row['total_checkins'] / $monthsActive, 1) : 0;
}
unset($row);

$site_total_views     = array_sum(array_column($spot_stats, 'total_views'));
$site_total_checkins  = array_sum(array_column($spot_stats, 'total_checkins'));

require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,#1b4332,#2d6a4f);color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-bar-chart-line-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Analytics</h1>
        <p class="mb-0 small opacity-75">Tourist demographics and spot visit trends</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- ── Tourist Demographics ──────────────────────────────── -->
  <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--red-dark)">
    <i class="bi bi-people-fill me-2"></i>Tourist Demographics
  </h5>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="p-3 h-100" style="background:#dbeafe;border-radius:var(--radius);border:1.5px solid #1e40af22">
        <div style="font-size:1.5rem;color:#1e40af"><i class="bi bi-people-fill"></i></div>
        <div style="font-size:1.4rem;font-weight:800;color:#1e40af"><?= number_format($total_tourists) ?></div>
        <div style="font-size:.78rem;color:#1e40af;opacity:.8">Registered Tourists</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="p-3 h-100" style="background:#d4edda;border-radius:var(--radius);border:1.5px solid #15572422">
        <div style="font-size:1.5rem;color:#155724"><i class="bi bi-house-door-fill"></i></div>
        <div style="font-size:1.4rem;font-weight:800;color:#155724"><?= number_format($local_count) ?></div>
        <div style="font-size:.78rem;color:#155724;opacity:.8">Local Tourists</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="p-3 h-100" style="background:#fef3c7;border-radius:var(--radius);border:1.5px solid #92400e22">
        <div style="font-size:1.5rem;color:#92400e"><i class="bi bi-airplane-fill"></i></div>
        <div style="font-size:1.4rem;font-weight:800;color:#92400e"><?= number_format($intl_count) ?></div>
        <div style="font-size:.78rem;color:#92400e;opacity:.8">International Tourists</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="p-3 h-100" style="background:#fbdede;border-radius:var(--radius);border:1.5px solid #6b0f1422">
        <div style="font-size:1.5rem;color:#6b0f14"><i class="bi bi-geo-alt-fill"></i></div>
        <div style="font-size:1.4rem;font-weight:800;color:#6b0f14"><?= number_format($site_total_checkins) ?></div>
        <div style="font-size:.78rem;color:#6b0f14;opacity:.8">Confirmed Spot Visits</div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Gender -->
    <div class="col-md-4">
      <div class="form-panel h-100">
        <h6 class="fw-bold mb-3" style="color:var(--red-dark)">Gender</h6>
        <?php if (empty($gender_split)): ?>
          <p class="text-muted small mb-0">No data yet.</p>
        <?php else:
          $genderLabels = ['male'=>'Male','female'=>'Female','prefer_not_to_say'=>'Prefer not to say'];
          $genderTotal = array_sum(array_column($gender_split, 'c'));
          foreach ($gender_split as $g):
            $pct = $genderTotal > 0 ? round($g['c'] / $genderTotal * 100) : 0;
        ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span><?= $genderLabels[$g['gender']] ?? ucfirst($g['gender']) ?></span>
            <span class="fw-bold"><?= $g['c'] ?> (<?= $pct ?>%)</span>
          </div>
          <div style="background:#eee;border-radius:10px;height:8px;overflow:hidden">
            <div style="background:var(--red-mid);height:100%;width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Age -->
    <div class="col-md-4">
      <div class="form-panel h-100">
        <h6 class="fw-bold mb-3" style="color:var(--red-dark)">Age Range</h6>
        <?php
        $ageTotal = array_sum($age_ordered);
        if ($ageTotal === 0): ?>
          <p class="text-muted small mb-0">No data yet.</p>
        <?php else: foreach ($age_ordered as $bucket => $count):
          $pct = round($count / $ageTotal * 100);
        ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span><?= $bucket ?></span>
            <span class="fw-bold"><?= $count ?> (<?= $pct ?>%)</span>
          </div>
          <div style="background:#eee;border-radius:10px;height:8px;overflow:hidden">
            <div style="background:var(--sand-dark);height:100%;width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Top origins -->
    <div class="col-md-4">
      <div class="form-panel h-100">
        <h6 class="fw-bold mb-3" style="color:var(--red-dark)">Top Provinces &amp; Nationalities</h6>
        <?php if (empty($top_provinces) && empty($top_nationalities)): ?>
          <p class="text-muted small mb-0">No data yet.</p>
        <?php else: ?>
          <?php if (!empty($top_provinces)): ?>
          <div class="small fw-bold text-muted mb-1"><i class="bi bi-house-door me-1"></i>Local — by Province</div>
          <div class="d-flex flex-wrap gap-1 mb-3">
            <?php foreach ($top_provinces as $p): ?>
            <span class="badge" style="background:var(--red-pale);color:var(--red-dark);font-weight:600">
              <?= e($p['province']) ?> (<?= $p['c'] ?>)
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($top_nationalities)): ?>
          <div class="small fw-bold text-muted mb-1"><i class="bi bi-airplane me-1"></i>International — by Nationality</div>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($top_nationalities as $n): ?>
            <span class="badge" style="background:#fef3c7;color:#92400e;font-weight:600">
              <?= e($n['nationality']) ?> (<?= $n['c'] ?>)
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Spot Visit Analytics ──────────────────────────────── -->
  <h5 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--red-dark)">
    <i class="bi bi-signpost-2-fill me-2"></i>Spot Visit Analytics
  </h5>
  <p class="text-muted small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Interest views</strong> count every time a spot's page is opened — a rough popularity signal only.
    <strong>Confirmed visits</strong> are GPS-verified check-ins from tourists physically at the location —
    this is the number to trust for real foot traffic.
  </p>

  <div class="form-panel">
    <?php if (empty($spot_stats)): ?>
      <p class="text-muted text-center py-4 mb-0">No tourist spots yet.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">
            <th>Spot</th>
            <th>City</th>
            <th class="text-end">Interest Views</th>
            <th class="text-end">Confirmed Visits</th>
            <th class="text-end">Avg / Month</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($spot_stats as $row): ?>
          <tr>
            <td class="fw-bold"><?= e($row['name']) ?></td>
            <td class="text-muted small"><?= e($row['city_name']) ?></td>
            <td class="text-end"><?= number_format($row['total_views']) ?></td>
            <td class="text-end">
              <span class="fw-bold" style="color:var(--red-dark)"><?= number_format($row['total_checkins']) ?></span>
            </td>
            <td class="text-end"><?= number_format($row['avg_per_month'], 1) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border);font-weight:700">
            <td colspan="2">Site Total</td>
            <td class="text-end"><?= number_format($site_total_views) ?></td>
            <td class="text-end" style="color:var(--red-dark)"><?= number_format($site_total_checkins) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
