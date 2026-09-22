<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
// ============================================================
// IEXPLORE LAGUNA — Forgot Password
// ============================================================
$page_title  = 'Forgot Password';
$active_page = '';

if (is_logged_in()) {
    header('Location: ' . APP_URL); exit;
}

$error   = '';
$sent    = false;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = strtolower(trim(input('email', 'post', '')));
    $blockedSeconds = $email ? password_reset_request_blocked($email) : null;

    if ($blockedSeconds !== null) {
        $error = "Please wait about a minute before requesting another reset link.";
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        password_reset_request_touch($email);

        // Always show the same success message whether or not the email
        // exists — otherwise this form becomes a way to check which
        // emails are registered.
        $user = db_fetch_one("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if ($user) {
            $token     = create_password_reset_token((int) $user['id']);
            $reset_url = APP_URL . '/pages/reset-password.php?token=' . urlencode($token);
            send_password_reset_email($user['email'], $user['name'], $reset_url);
        }
        $sent = true;
    }
}

// Same source as login.php's split-screen photo, so this page matches
// the rest of the auth flow instead of showing a blank panel.
$auth_photo = db_fetch_one(
    "SELECT url FROM spot_photos WHERE photo_type='main' ORDER BY RAND() LIMIT 1"
)['url'] ?? null;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-split">
  <div class="auth-split-photo">
    <?php if ($auth_photo): ?>
    <div class="auth-split-bg" style="background-image:url('<?= e($auth_photo) ?>')"></div>
    <?php endif; ?>
    <div class="auth-split-content">
      <a href="<?= APP_URL ?>" class="auth-wordmark">
        <i class="bi bi-map-fill me-2"></i><em>i</em>Explore <span>Laguna</span>
      </a>
      <h2>Forgot your<br>password?</h2>
      <p>No worries — we'll email you a link to set a new one so you can get back to planning your Laguna trip.</p>
    </div>
  </div>

  <div class="auth-split-form">
    <div class="auth-form-inner fade-up">
      <h2 class="auth-heading">Reset Your Password</h2>
      <p class="auth-subheading">Enter the email on your account</p>

      <?php if ($sent): ?>
        <div class="alert alert-success small mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-envelope-check-fill flex-shrink-0"></i>
          <span>If that email is registered, a reset link is on its way. It expires in 30 minutes.</span>
        </div>
        <a href="login.php" class="btn btn-primary-app w-100 py-2">
          <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
        </a>
      <?php else: ?>
        <?php if ($error): ?>
          <div class="alert alert-danger small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <div class="mb-4">
            <label class="form-label">Email Address</label>
            <div class="input-icon-wrap">
              <i class="bi bi-envelope"></i>
              <input type="email" class="form-control" name="email"
                     value="<?= e($email) ?>" placeholder="juan@email.com" autofocus required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary-app w-100 py-2">
            <i class="bi bi-send me-2"></i>Send Reset Link
          </button>
        </form>
      <?php endif; ?>

      <hr class="my-3" style="border-color:var(--border)">
      <p class="text-center text-muted small mb-0">
        Remembered it? <a href="login.php" class="fw-bold text-maroon">Log in</a>
      </p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
