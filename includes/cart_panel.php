<?php
// ============================================================
// iEXPLORE LAGUNA — Cart Panel Partial
// includes/cart_panel.php
// Included twice: once for desktop sidebar, once for offcanvas
// The $panel_id variable distinguishes which instance.
// ============================================================
$panel_id = $panel_id ?? ('cart-panel-' . (isset($cart_panel_count) ? ++$cart_panel_count : ($cart_panel_count = 1 ) * 1));
// Simpler: just pass a static id via the include context
// We use a static counter trick
static $cart_include_count = 0;
$cart_include_count++;
$pid = $cart_include_count === 1 ? 'cart-panel-desktop' : 'cart-panel-offcanvas';
?>
<div id="<?= $pid ?>" style="display:flex;flex-direction:column;height:100%">

  <!-- Header -->
  <div class="cart-header">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div style="font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700">
          <i class="bi bi-basket3-fill me-2" style="color:var(--sand-dark)"></i>My List
        </div>
        <div style="font-size:.78rem;opacity:.75" class="cart-item-count">0 items</div>
      </div>
      <button onclick="clearCart()" class="btn btn-sm"
              style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);font-size:.75rem;padding:.25rem .65rem;border-radius:var(--radius-pill)">
        Clear all
      </button>
    </div>
  </div>

  <!-- Items -->
  <div class="cart-items-list">
    <div class="cart-empty-msg">
      <i class="bi bi-basket3"></i>
      <p class="mb-0 small">Nothing added yet.<br>Browse spots &amp; hotels and tap <strong>Add</strong>.</p>
    </div>
  </div>

  <!-- Footer -->
  <div class="cart-footer">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="small text-muted">Estimated entrance/room cost:</span>
      <span class="fw-bold" style="color:var(--green-dark)" class="cart-total-cost">—</span>
    </div>
    <div class="d-grid gap-2">
      <button class="btn btn-sm w-100"
              style="background:linear-gradient(135deg,var(--green-mid),var(--green-dark));color:#fff;font-weight:600;border-radius:var(--radius-pill)"
              onclick="generateItinerary()">
        <i class="bi bi-magic me-2"></i>Generate Itinerary
      </button>
      <a href="<?= APP_URL ?>/pages/planner.php" class="btn btn-sm btn-outline-secondary w-100" style="border-radius:var(--radius-pill);font-size:.82rem">
        <i class="bi bi-compass me-1"></i>Open Trip Planner
      </a>
    </div>
  </div>

</div>
