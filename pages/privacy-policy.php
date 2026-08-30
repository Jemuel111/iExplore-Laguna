<?php
// ============================================================
// IEXPLORE LAGUNA — Privacy Policy Page
// pages/privacy-policy.php
// Plain-language explanation of what personal data this site
// collects and why, in line with the Philippines' Data Privacy
// Act of 2012 (RA 10173). This is a template — have this
// reviewed against your actual practices (and ideally by
// someone qualified) before treating it as a finished legal
// document; it is not legal advice.
// ============================================================
$page_title  = 'Privacy Policy';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-shield-lock-fill fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Privacy Policy</h1>
        <p class="mb-0 small opacity-75">Last updated: <?= date('F j, Y') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="py-4">
<div class="container" style="max-width:820px">

  <div class="form-panel mb-4">
    <p>
      <?= e(APP_NAME) ?> ("we", "our", "the platform") respects your privacy and is committed to
      protecting any personal information you share with us, in line with the
      <strong>Philippines' Data Privacy Act of 2012 (Republic Act No. 10173)</strong>.
      This page explains what we collect, why, how it's used, and the rights you have over it.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-collection me-2" style="color:var(--maroon-light)"></i>What we collect
    </h5>
    <ul class="mb-0">
      <li><strong>Account information</strong> — your name, email address, password (stored encrypted, never in plain text), birthdate, gender, and whether you're a local or international tourist (plus province/city or nationality).</li>
      <li><strong>Business registration information</strong> — if you register a hotel or shop, we additionally collect your business name, address, contact number, and any documents submitted for verification.</li>
      <li><strong>Trip planning data</strong> — routes, cities, tourist spots, and itineraries you build or save, so you can revisit them later under "My Itineraries".</li>
      <li><strong>Booking and order details</strong> — information needed to fulfill a hotel booking or shop order (e.g. dates, quantities, contact details), shared only with the specific business you're booking or ordering from.</li>
      <li><strong>Usage information</strong> — basic technical data like your session and the pages you visit, used only to keep the site working correctly (e.g. keeping you logged in, preventing cross-site request forgery).</li>
    </ul>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-question-circle me-2" style="color:var(--maroon-light)"></i>Why we collect it
    </h5>
    <ul class="mb-0">
      <li>To create and secure your account, and let you log back in.</li>
      <li>To generate trip itineraries, budget estimates, and route information tailored to you.</li>
      <li>To let you book hotels, order from local shops, and save your travel plans.</li>
      <li>To verify hotel and shop registrations before they're listed publicly.</li>
      <li>To improve the platform and understand which features are actually useful.</li>
    </ul>
    <p class="mb-0 mt-3 small text-muted">We do not sell your personal information to third parties.</p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-share me-2" style="color:var(--maroon-light)"></i>Who we share it with
    </h5>
    <p class="mb-0">
      Booking or order details are shared only with the specific hotel or shop you're
      transacting with, so they can fulfill your request. We don't share your personal
      information with any other third party except where required by Philippine law.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-person-check me-2" style="color:var(--maroon-light)"></i>Your rights
    </h5>
    <p>Under the Data Privacy Act, you have the right to:</p>
    <ul class="mb-0">
      <li><strong>Access</strong> the personal data we hold about you.</li>
      <li><strong>Correct</strong> any inaccurate or outdated information.</li>
      <li><strong>Request deletion</strong> of your account and associated personal data.</li>
      <li><strong>Withdraw consent</strong> at any time, understanding this may limit what features are available to you.</li>
    </ul>
    <p class="mb-0 mt-3">
      To exercise any of these rights, contact us using the details below, or use the
      account settings available after logging in.
    </p>
  </div>

  <div class="form-panel mb-4">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-lock me-2" style="color:var(--maroon-light)"></i>How we protect your data
    </h5>
    <p class="mb-0">
      Passwords are stored using industry-standard one-way hashing (never in plain text).
      Access to personal data is restricted to what each part of the system needs to function.
      Every form submission that changes data is protected against cross-site request forgery.
    </p>
  </div>

  <div class="form-panel">
    <h5 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
      <i class="bi bi-envelope me-2" style="color:var(--maroon-light)"></i>Contact us
    </h5>
    <p class="mb-0">
      Questions about this policy or your personal data? Reach out through the contact
      details on your booking confirmations, or the email address you used to register.
    </p>
  </div>

</div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
