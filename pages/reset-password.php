<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// IEXPLORE LAGUNA — Reset Password (landing page for the emailed link)
// ============================================================
$page_title  = 'Reset Password';
$active_page = '';

if (is_logged_in()) {
    header('Location: ' . APP_URL); exit;
}

$token = input('token', 'get', '');
$error = '';
$done  = false;

// Look the token up once, up front, so both the GET view and the POST
// handler agree on whether it's still valid — a token consumed between
// page-load and submit (e.g. reused twice) fails safely either way.
$reset_row = $token ? find_user_by_reset_token($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $token     = input('token', 'post', '');
    $reset_row = $token ? find_user_by_reset_token($token) : null;
    $password  = input('password', 'post', '');
    $confirm   = input('confirm_password', 'post', '');

    if (!$reset_row) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        db_execute(
            "UPDATE users SET password = ? WHERE id = ?",
            [password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $reset_row['id']]
        );
        consume_password_reset_token((int) $reset_row['reset_id']);
        reset_login_attempts($reset_row['email']); // clear any lockout tied to the old password
        $done = true;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-split">
  <div class="auth-split-photo">
    <div class="auth-split-content">
      <a href="<?= APP_URL ?>" class="auth-wordmark">
        <i class="bi bi-map-fill me-2"></i><em>i</em>Explore <span>Laguna</span>
      </a>
      <h2>Almost there.</h2>
      <p>Set a new password to get back into your account.</p>
    </div>
  </div>

  <div class="auth-split-form">
    <div class="auth-form-inner fade-up">

      <?php if ($done): ?>
        <h2 class="auth-heading">Password Updated</h2>
        <div class="alert alert-success small mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-check-circle-fill flex-shrink-0"></i>
          <span>Your password has been reset. You can now log in with your new password.</span>
        </div>
        <a href="login.php" class="btn btn-primary-app w-100 py-2">
          <i class="bi bi-box-arrow-in-right me-2"></i>Log In
        </a>

      <?php elseif (!$reset_row): ?>
        <h2 class="auth-heading">Link Invalid or Expired</h2>
        <div class="alert alert-danger small mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span><?= $error ? e($error) : 'This reset link is invalid or has expired. Please request a new one.' ?></span>
        </div>
        <a href="forgot-password.php" class="btn btn-primary-app w-100 py-2">
          <i class="bi bi-arrow-repeat me-2"></i>Request a New Link
        </a>

      <?php else: ?>
        <h2 class="auth-heading">Set a New Password</h2>
        <p class="auth-subheading">for <?= e($reset_row['email']) ?></p>

        <?php if ($error): ?>
          <div class="alert alert-danger small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <div class="mb-3">
            <label class="form-label">New Password</label>
            <div class="input-icon-wrap">
              <i class="bi bi-lock"></i>
              <input type="password" class="form-control" name="password"
                     minlength="8" placeholder="At least 8 characters" autofocus required>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm New Password</label>
            <div class="input-icon-wrap">
              <i class="bi bi-lock-fill"></i>
              <input type="password" class="form-control" name="confirm_password"
                     minlength="8" placeholder="Re-enter your new password" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary-app w-100 py-2">
            <i class="bi bi-check2-circle me-2"></i>Reset Password
          </button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
