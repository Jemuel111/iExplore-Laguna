<?php
// ============================================================
// IEXPLORE LAGUNA — Terms of Service Page
// pages/terms-of-service.php
// Plain-language terms covering how this platform actually
// works (trip planning, hotel/shop bookings between tourists
// and independent local businesses). This is a template — not
// legal advice. Have it reviewed by someone qualified before
// treating it as a finished legal document, especially before
// a public launch with real bookings and real user data.
// ============================================================
$page_title  = 'Terms of Service';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-file-earmark-text-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Terms of Service</h1>
        <p class="mb-0 small opacity-75">Last updated: <?= date('F j, Y') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="py-4">
<div class="container" style="max-width:820px">

  <div class="form-panel mb-4">
    <p class="mb-0">
      By creating an account or using <?= e(APP_NAME) ?> ("we", "our", "the platform"), you agree to these
      Terms of Service. If you don't agree with any part of them, please don't use the platform.
      These terms work alongside our <a href="<?= APP_URL ?>/pages/privacy-policy.php" style="color:var(--maroon-mid)">Privacy Policy</a>,
      which explains how we handle your personal data.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-info-circle me-2" style="color:var(--maroon-light)"></i>1. What this platform is
    </h5>
    <p class="mb-0">
      <?= e(APP_NAME) ?> is a trip-planning tool for Laguna province. We help you discover tourist spots,
      plan routes, estimate budgets, build itineraries, and connect with independently owned hotels and
      shops in the area. <strong><?= e(APP_NAME) ?> is not itself a hotel, tour operator, or shop</strong> —
      we're the platform that connects you with businesses that are separately owned and operated.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-person-badge me-2" style="color:var(--maroon-light)"></i>2. Your account
    </h5>
    <ul class="mb-0">
      <li>You must provide accurate information when registering, and keep your login credentials confidential.</li>
      <li>You're responsible for all activity that happens under your account.</li>
      <li>Hotel and shop owner accounts go through a verification step before their listings appear publicly — we may request documentation to confirm a business is real and legitimately represented.</li>
      <li>We may suspend or terminate accounts that violate these terms, provide false information, or are used for fraudulent activity.</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-calendar-check me-2" style="color:var(--maroon-light)"></i>3. Bookings and orders
    </h5>
    <p>When you book a hotel or place a shop order through the platform:</p>
    <ul class="mb-0">
      <li>The actual booking or sale contract is between you and that hotel or shop — <?= e(APP_NAME) ?> facilitates the connection but is not a party to that transaction.</li>
      <li>Availability, pricing, cancellation policies, and fulfillment are set by each individual business, not by us.</li>
      <li>We are not responsible for a business's failure to honor a booking, provide a refund, or fulfill an order — disputes about the transaction itself should be raised directly with that business.</li>
      <li>Please double-check details (dates, prices, room type, order contents) before confirming — we don't guarantee accuracy of listings, though we do ask businesses to keep their information current.</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-signpost-2 me-2" style="color:var(--maroon-light)"></i>4. Trip plans, routes, and budget estimates
    </h5>
    <p>Our Trip Planner, budget estimator, and route information use third-party mapping and routing
      data (including OpenRouteService, TomTom, and OpenStreetMap contributors), plus fares and travel
      times we maintain ourselves.</p>
    <ul class="mb-0">
      <li>Routes, distances, travel times, and transport fares are <strong>estimates</strong>, not guarantees — actual road conditions, traffic, fare changes, and transport availability can and do vary.</li>
      <li>Tourist spot information (hours, entrance fees, closures) is kept as current as we reasonably can, but always confirm directly with a spot before traveling, especially for time-sensitive plans.</li>
      <li>We're not liable for costs, missed connections, or inconvenience arising from relying solely on estimates generated by the platform.</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-chat-square-text me-2" style="color:var(--maroon-light)"></i>5. Reviews and user content
    </h5>
    <ul class="mb-0">
      <li>Reviews and photos you submit must be honest, based on genuine experience, and free of harassment, hate speech, or false claims about a business.</li>
      <li>You retain ownership of content you submit, but grant us permission to display it on the platform as part of the normal operation of the service.</li>
      <li>We may remove content that violates these terms or applicable law, at our discretion.</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-shop me-2" style="color:var(--maroon-light)"></i>6. If you register a hotel or shop
    </h5>
    <ul class="mb-0">
      <li>You confirm that you're authorized to list and represent the business you register.</li>
      <li>You're responsible for keeping your listing's information (pricing, availability, hours, photos) accurate and current.</li>
      <li>You agree to honor bookings and orders made in good faith through the platform, consistent with your own stated policies.</li>
      <li>We may unpublish a listing that receives credible reports of fraud, repeated inaccuracy, or policy violations.</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-exclamation-triangle me-2" style="color:var(--maroon-light)"></i>7. Disclaimers and limitation of liability
    </h5>
    <p class="mb-0">
      The platform is provided "as is," without warranties of any kind, express or implied. To the fullest
      extent permitted by Philippine law, <?= e(APP_NAME) ?> is not liable for indirect, incidental, or
      consequential damages arising from your use of the platform, including but not limited to travel
      disruptions, booking disputes with third-party businesses, or reliance on estimated route/fare
      information. Nothing in these terms limits any right you have under the Consumer Act of the
      Philippines (RA 7394) or other applicable consumer protection law that cannot be excluded by agreement.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-arrow-repeat me-2" style="color:var(--maroon-light)"></i>8. Changes to these terms
    </h5>
    <p class="mb-0">
      We may update these terms from time to time, for example as the platform adds new features. We'll
      update the "Last updated" date above when we do. Continuing to use the platform after changes take
      effect means you accept the updated terms.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-bank me-2" style="color:var(--maroon-light)"></i>9. Governing law
    </h5>
    <p class="mb-0">
      These terms are governed by the laws of the Republic of the Philippines. Any disputes arising from
      use of the platform will be subject to the jurisdiction of the appropriate Philippine courts.
    </p>
  </div>

  <div class="form-panel">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-envelope me-2" style="color:var(--maroon-light)"></i>10. Contact
    </h5>
    <p class="mb-0">
      Questions about these terms? Reach out through the contact details on your booking confirmations,
      or the email address you used to register.
    </p>
  </div>

</div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
