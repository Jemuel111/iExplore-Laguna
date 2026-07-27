<?php
/**
 * Renders a single package card.
 *
 * @param array $p A row from the packages query (with city_name, hotel_name,
 *                 star_rating, spot_count joined in).
 */
function render_package_card(array $p): void {
?>
<div class="card-app h-100">
  <div class="card-img-placeholder" style="height:120px;font-size:2.4rem;background:var(--green-pale)">
    <i class="bi bi-suitcase-lg-fill"></i>
  </div>
  <div class="card-body-app d-flex flex-column">
    <div class="mb-1">
      <span class="badge" style="background:var(--sand);color:var(--green-dark);font-size:.72rem">
        <?php if ($p['scope'] === 'single_city'): ?><i class="bi bi-geo-alt me-1"></i><?= e($p['city_name']) ?><?php else: ?><i class="bi bi-map me-1"></i>Multiple Cities<?php endif; ?>
      </span>
      <span class="badge" style="background:var(--green-pale);color:var(--green-dark);font-size:.72rem">
        <?= (int)$p['days'] ?> day<?= $p['days']!=1?'s':'' ?>
      </span>
    </div>
    <h6 class="card-title-app mb-1" style="font-size:.98rem"><?= e($p['title']) ?></h6>
    <?php if ($p['description']): ?>
    <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
      <?= e($p['description']) ?>
    </p>
    <?php endif; ?>
    <div class="small text-muted mb-1">
      <i class="bi bi-building me-1"></i><?= e($p['hotel_name'] ?? 'Hotel included') ?>
      <?php if ($p['star_rating']): ?> <?= str_repeat('★',(int)$p['star_rating']) ?><?php endif; ?>
    </div>
    <div class="small text-muted mb-3">
      <i class="bi bi-geo-alt me-1"></i><?= (int)$p['spot_count'] ?> spot<?= $p['spot_count']!=1?'s':'' ?> included
    </div>
    <div class="mt-auto">
      <div class="d-flex align-items-baseline justify-content-between mb-2">
        <span class="text-muted small">Est. total</span>
        <span class="fw-bold fs-5" style="color:var(--green-dark)">₱<?= number_format($p['estimated_price'],0) ?></span>
      </div>
      <a href="package.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary-app w-100">
        <i class="bi bi-box-arrow-up-right me-1"></i>View Package
      </a>
    </div>
  </div>
</div>
<?php
}
