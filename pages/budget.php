<?php
// ============================================================
// IEXPLORE LAGUNA — Budget Estimator Page
// pages/budget.php
// ============================================================
$page_title  = 'Budget Estimator';
$active_page = 'budget';
require_once __DIR__ . '/../includes/header.php';

$cities = db_fetch_all("SELECT id, name, slug FROM cities ORDER BY name");
?>

<section class="py-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-calculator-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Budget Estimator</h1>
        <p class="mb-0 small opacity-75">Get a detailed cost breakdown for your Laguna trip</p>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
<div class="container">
<div class="row g-4 justify-content-center">

  <!-- Input form -->
  <div class="col-lg-5">
    <div class="form-panel">
      <h5 class="fw-bold mb-4" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
        Trip Details
      </h5>

      <div class="mb-3">
        <label class="form-label">From (Origin)</label>
        <select class="form-select" id="b-origin">
          <option value="">— Select city —</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">To (Destination)</label>
        <select class="form-select" id="b-dest">
          <option value="">— Select city —</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-4">
          <label class="form-label">Days</label>
          <select class="form-select" id="b-days">
            <?php for ($d=1;$d<=7;$d++): ?>
              <option value="<?=$d?>"><?=$d?> day<?=$d>1?'s':''?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-4">
          <label class="form-label">Persons</label>
          <select class="form-select" id="b-persons">
            <?php for ($p=1;$p<=10;$p++): ?>
              <option value="<?=$p?>"><?=$p?> pax</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-4">
          <label class="form-label">Level</label>
          <select class="form-select" id="b-level">
            <option value="budget">💰 Budget</option>
            <option value="midrange" selected>💳 Mid</option>
            <option value="upscale">💎 Upscale</option>
          </select>
        </div>
      </div>

      <!-- Entrance fees toggle -->
      <div class="mb-4">
        <label class="form-label d-block">Include entrance fees for</label>
        <div class="d-flex flex-wrap gap-2" id="fee-toggles">
          <?php
          $defaultSpots = [
            ['Pagsanjan Falls', 300],
            ['Villa Escudero', 600],
            ['Rizal Shrine', 30],
            ['Sampaloc Lake', 30],
            ['Nagcarlan Cemetery', 30],
          ];
          foreach ($defaultSpots as [$name, $fee]):
          ?>
          <label class="d-flex align-items-center gap-2 p-2 rounded-2"
                 style="border:1px solid var(--border);cursor:pointer;font-size:.82rem;background:#fff">
            <input type="checkbox" class="fee-check" data-fee="<?= $fee ?>" checked>
            <?= e($name) ?> <span style="color:var(--terracotta);font-weight:700">₱<?= $fee ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="btn btn-primary-app w-100" id="calc-btn">
        <i class="bi bi-calculator me-2"></i>Calculate Budget
      </button>
    </div>
  </div>

  <!-- Results -->
  <div class="col-lg-5">

    <!-- Placeholder -->
    <div id="budget-placeholder" class="form-panel text-center py-5" style="color:var(--text-muted)">
      <i class="bi bi-calculator fs-1 d-block mb-3" style="color:var(--green-pale)"></i>
      <p class="mb-0 small">Fill in the form and click<br><strong>Calculate Budget</strong></p>
    </div>

    <!-- Result panel (hidden) -->
    <div id="budget-result" class="d-none">

      <!-- Total -->
      <div class="budget-panel mb-3">
        <div class="total-label">Estimated Total Cost</div>
        <div class="total-amount mb-1" id="res-total">₱ 0.00</div>
        <div style="font-size:.8rem;opacity:.7" id="res-meta"></div>
      </div>

      <!-- Breakdown card -->
      <div class="form-panel mb-3">
        <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          Cost Breakdown
        </h6>
        <div id="res-breakdown"></div>
      </div>

      <!-- Per-person per-day card -->
      <div class="form-panel mb-3" style="background:var(--green-pale);border-color:var(--green-light)">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold" style="color:var(--green-dark)">Per Person / Per Day</div>
            <div class="small text-muted">Average daily spending</div>
          </div>
          <div class="fw-bold fs-4" style="color:var(--green-mid)" id="res-per-day">₱ 0</div>
        </div>
      </div>

      <!-- Tips -->
      <div class="form-panel" id="budget-tips">
        <h6 class="fw-bold mb-2" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
          💡 Money-Saving Tips
        </h6>
        <ul class="small text-muted mb-0 ps-3" id="tips-list"></ul>
      </div>

    </div>
  </div>

</div>
</div>
</section>

<script>
document.getElementById('calc-btn').addEventListener('click', calculateBudget);

async function calculateBudget() {
  const origin  = document.getElementById('b-origin').value;
  const dest    = document.getElementById('b-dest').value;
  const days    = parseInt(document.getElementById('b-days').value);
  const persons = parseInt(document.getElementById('b-persons').value);
  const level   = document.getElementById('b-level').value;

  if (!origin || !dest) {
    IExploreApp.toast('Please select origin and destination.', 'warning');
    return;
  }
  if (origin === dest) {
    IExploreApp.toast('Origin and destination must be different.', 'warning');
    return;
  }

  const btn = document.getElementById('calc-btn');
  IExploreApp.setLoading(btn, true);

  const API_BASE = '<?= APP_URL ?>/api/';
  const res = await fetch(
    API_BASE + `budget.php?action=estimate&origin=${origin}&dest=${dest}&days=${days}&persons=${persons}&level=${level}`
  ).then(r => r.json());

  IExploreApp.setLoading(btn, false);

  if (!res.success) {
    IExploreApp.toast(res.message || 'Error calculating budget.', 'error');
    return;
  }

  const b = res.data;

  // Selected entrance fees
  const feesTotal = [...document.querySelectorAll('.fee-check:checked')]
    .reduce((sum, el) => sum + parseFloat(el.dataset.fee), 0) * persons;

  const grand = b.grand_total + feesTotal;
  const perPersonPerDay = grand / persons / days;

  // Show result
  document.getElementById('budget-placeholder').classList.add('d-none');
  document.getElementById('budget-result').classList.remove('d-none');

  document.getElementById('res-total').textContent = formatPeso(grand);
  document.getElementById('res-meta').textContent =
    `${persons} person${persons>1?'s':''} · ${days} day${days>1?'s':''} · ${level} level`;
  document.getElementById('res-per-day').textContent = formatPeso(perPersonPerDay);

  // Breakdown rows
  const rows = [
    ['bi-bus-front',   'Transport (round trip)', b.transport * persons],
    ['bi-house-door',  `Accommodation (${Math.max(0,days-1)} night${days>2?'s':''})`, b.accommodation * persons * Math.max(0,days-1)],
    ['bi-cup-hot',     `Food (${days} day${days>1?'s':''})`, b.food * persons * days],
    ['bi-arrows-move', `Local Transport (${days} day${days>1?'s':''})`, b.local * persons * days],
    ['bi-ticket',      'Entrance Fees', feesTotal],
    ['bi-three-dots',  'Miscellaneous (10%)', b.misc * persons],
  ];

  document.getElementById('res-breakdown').innerHTML = rows.map(([icon, label, amount]) => `
    <div class="d-flex justify-content-between align-items-center py-2"
         style="border-bottom:1px solid var(--border);font-size:.9rem">
      <span style="color:var(--text-muted)"><i class="bi ${icon} me-2"></i>${label}</span>
      <span class="fw-bold">${formatPeso(amount)}</span>
    </div>
  `).join('') + `
    <div class="d-flex justify-content-between align-items-center pt-2 fw-bold">
      <span>TOTAL</span>
      <span style="color:var(--green-mid);font-size:1.1rem">${formatPeso(grand)}</span>
    </div>
  `;

  // Tips based on level
  const allTips = {
    budget: [
      'Take jeepneys instead of tricycles for longer distances.',
      'Eat at public market carinderias — authentic and affordable.',
      'Visit free spots like Rizal Parks and public beaches.',
      'Buy snacks and water at local sari-sari stores.',
      'Travel on weekdays to avoid weekend surcharges at resorts.',
    ],
    midrange: [
      'Book accommodations 1–2 weeks in advance for better rates.',
      'Use FX/UV Express for faster inter-city travel at moderate fares.',
      'Look for resorts that include breakfast in their room rates.',
      'Split costs for private car hire if traveling in a group.',
      'Visit during off-peak months (June–October) for lower prices.',
    ],
    upscale: [
      'Rent a private van or car for flexible door-to-door travel.',
      'Pansol and Los Baños hot spring resorts offer premium villas.',
      'Villa Escudero all-inclusive packages cover meals and shows.',
      'Book resort accommodations with private pools for exclusivity.',
      'Hire a local guide at Pagsanjan Falls for a better experience.',
    ],
  };

  document.getElementById('tips-list').innerHTML =
    (allTips[level] || allTips.midrange)
      .map(t => `<li class="mb-1">${t}</li>`).join('');
}

function formatPeso(amount) {
  return '₱ ' + parseFloat(amount||0).toLocaleString('en-PH',
    {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>