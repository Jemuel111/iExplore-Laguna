<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';

// ============================================================
// IEXPLORE LAGUNA — My Itineraries Page
// pages/itineraries.php
// ============================================================
$page_title  = 'My Itineraries';
$active_page = '';


if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/pages/login.php?redirect=' . urlencode(APP_URL . '/pages/itineraries.php'));
    exit;
}

$user = current_user();
$itineraries = db_fetch_all(
    "SELECT i.*,
            o.name AS origin_name,
            d.name AS dest_name
     FROM itineraries i
     JOIN cities o ON i.origin_city_id = o.id
     JOIN cities d ON i.dest_city_id   = d.id
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC",
    [$user['id']]
);

// For each saved itinerary, check whether any of its included spots have
// since been marked temporarily closed in a way that actually conflicts
// with the tourist's planned travel date (or "now", if no date was set).
foreach ($itineraries as &$it) {
    $it['closure_warnings'] = [];
    $spotIds = parse_spot_ids($it['spot_ids'] ?? null);
    if (!$spotIds) continue;

    $placeholders = implode(',', array_fill(0, count($spotIds), '?'));
    $spotsInfo = db_fetch_all(
        "SELECT id, name, is_closed, closure_reason, closed_until, closure_updated_at
         FROM tourist_spots WHERE id IN ($placeholders)",
        $spotIds
    );

    foreach ($spotsInfo as $s) {
        $status = spot_closure_status($s, $it['travel_date'] ?: null);
        if (!$status['closed'] || $status['reopens_before_reference']) continue; // no real conflict

        $announcedAfterPlanning = !empty($s['closure_updated_at']) && !empty($it['created_at'])
            && strtotime($s['closure_updated_at']) > strtotime($it['created_at']);

        $it['closure_warnings'][] = [
            'name'                     => $s['name'],
            'reason'                   => $status['reason'],
            'closed_until'             => $status['closed_until'],
            'announced_after_planning' => $announcedAfterPlanning,
        ];
    }
}
unset($it);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-journal-bookmark-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">My Itineraries</h1>
        <p class="mb-0 small opacity-75">Welcome back, <?= e($user['name']) ?>!</p>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
<div class="container">

  <?php if (empty($itineraries)): ?>
    <div class="text-center py-5">
      <i class="bi bi-journal-plus fs-1 text-muted d-block mb-3"></i>
      <h5 class="fw-bold">No saved itineraries yet</h5>
      <p class="text-muted">Plan a trip and click "Save" to keep it here.</p>
      <a href="planner.php" class="btn btn-primary-app">
        <i class="bi bi-compass me-2"></i>Start Planning
      </a>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($itineraries as $it): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card-app h-100">
          <div class="card-body-app">
            <?php if (!empty($it['closure_warnings'])): ?>
            <div class="mb-3 p-2 d-flex gap-2 align-items-start" style="background:#fee2e2;border:1.5px solid #fca5a5;border-radius:var(--radius-sm)">
              <i class="bi bi-exclamation-triangle-fill" style="color:#a61c1c;flex-shrink:0;margin-top:.15rem"></i>
              <div style="font-size:.78rem;color:#7f1d1d">
                <?php foreach ($it['closure_warnings'] as $w): ?>
                  <div class="mb-1">
                    <strong><?= e($w['name']) ?></strong> is temporarily closed<?= $w['reason'] ? ' (' . e($w['reason']) . ')' : '' ?>
                    <?= $w['closed_until']
                          ? ', expected back around ' . date('M j, Y', strtotime($w['closed_until'])) . '.'
                          : ', with no confirmed reopening date yet.' ?>
                    <?php if ($w['announced_after_planning']): ?>
                      <em>This was announced after you saved this trip.</em>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
                <div class="fw-bold">You may want to adjust your plans before you go.</div>
              </div>
            </div>
            <?php endif; ?>

            <div class="card-meta mb-2">
              <i class="bi bi-calendar3 text-green"></i>
              <span><?= $it['travel_date'] ? date('M d, Y', strtotime($it['travel_date'])) : 'Date not set' ?></span>
              <span>·</span>
              <span><?= $it['num_days'] ?> day<?= $it['num_days']>1?'s':'' ?></span>
            </div>
            <h5 class="card-title-app mb-2"><?= e($it['title'] ?: $it['origin_name'].' → '.$it['dest_name']) ?></h5>
            <div class="d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;color:var(--text-muted)">
              <i class="bi bi-geo-alt text-green"></i>
              <span><?= e($it['origin_name']) ?> → <?= e($it['dest_name']) ?></span>
            </div>
            <div class="d-flex gap-2 mb-3 flex-wrap">
              <span class="badge rounded-pill" style="background:var(--green-pale);color:var(--green-dark);padding:.3rem .8rem;font-size:.75rem">
                <?= $it['num_persons'] ?> pax
              </span>
              <span class="badge rounded-pill" style="background:var(--sand);color:var(--charcoal);padding:.3rem .8rem;font-size:.75rem">
                <?= ucfirst($it['budget_level']) ?>
              </span>
            </div>
            <?php if ($it['total_budget']): ?>
            <div class="fw-bold mb-3" style="color:var(--green-mid)">
              Est. ₱<?= number_format($it['total_budget'], 2) ?>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
              <a href="planner.php?origin=<?= $it['origin_city_id'] ?>&destination=<?= $it['dest_city_id'] ?>&days=<?= $it['num_days'] ?>&persons=<?= $it['num_persons'] ?>&budget_level=<?= $it['budget_level'] ?><?= $it['travel_date'] ? '&travel_date=' . urlencode($it['travel_date']) : '' ?>"
                 class="btn btn-sm btn-primary-app flex-grow-1">
                <i class="bi bi-compass me-1"></i>Re-plan
              </a>
              <button class="btn btn-sm btn-outline-danger"
                      onclick="deleteItinerary(<?= $it['id'] ?>, this)">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
</section>

<script>
async function deleteItinerary(id, btn) {
  if (!confirm('Delete this itinerary?')) return;
  const API_BASE = '<?= APP_URL ?>/api/';
  const res = await fetch(API_BASE + `itineraries.php?action=delete&id=${id}`, { method: 'POST' }).then(r=>r.json());
  if (res.success) {
    btn.closest('.col-md-6').remove();
    IExploreApp.toast('Itinerary deleted.', 'success');
  } else {
    IExploreApp.toast('Could not delete. Try again.', 'error');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>