<?php
// ============================================================
// IEXPLORE LAGUNA — My Itineraries Page
// pages/itineraries.php
// ============================================================
$page_title  = 'My Itineraries';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';

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
              <a href="planner.php?origin=<?= $it['origin_city_id'] ?>&destination=<?= $it['dest_city_id'] ?>&days=<?= $it['num_days'] ?>&persons=<?= $it['num_persons'] ?>&budget_level=<?= $it['budget_level'] ?>"
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