<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Login Page (Polished v2)
// ============================================================
$page_title  = 'Login';
$active_page = '';


// Redirect already-logged-in users BEFORE any output
if (is_logged_in()) {
    $__role = current_user()['role'] ?? 'tourist';
    if ($__role === 'shop_owner')  { header('Location: ' . APP_URL . '/pages/shop-dashboard.php');  exit; }
    if ($__role === 'hotel_owner') { header('Location: ' . APP_URL . '/pages/hotel-dashboard.php'); exit; }
    if ($__role === 'admin')       { header('Location: ' . APP_URL . '/pages/admin-dashboard.php'); exit; }
    header('Location: ' . APP_URL); exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim(input('email', 'post', '')));
    $password = input('password', 'post', '');

    $blockedSeconds = $email ? login_attempt_blocked($email) : null;

    if ($blockedSeconds !== null) {
        $mins  = ceil($blockedSeconds / 60);
        $error = "Too many failed attempts. Please try again in about {$mins} minute" . ($mins != 1 ? 's' : '') . '.';
    } elseif (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user && password_verify($password, $user['password'])) {
            reset_login_attempts($email);
            login_user($user);
            $role     = $user['role'] ?? 'tourist';
            $redirect = input('redirect', 'get', '');
            if ($redirect) {
                header('Location: ' . $redirect); exit;
            }
            if ($role === 'shop_owner') {
                header('Location: ' . APP_URL . '/pages/shop-dashboard.php'); exit;
            }
            if ($role === 'hotel_owner') {
                header('Location: ' . APP_URL . '/pages/hotel-dashboard.php'); exit;
            }
            if ($role === 'admin') {
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php'); exit;
            }
            $_SESSION['flash']['success'] = 'Welcome back, ' . $user['name'] . '!';
            header('Location: ' . APP_URL); exit;
        } else {
            $remaining = record_failed_login($email);
            $error = $remaining > 0
                ? "Incorrect email or password. You have {$remaining} attempt" . ($remaining != 1 ? 's' : '') . " remaining before your account is temporarily locked."
                : 'Too many failed attempts. Your account is now temporarily locked — please try again in about 5 minutes.';
        }
    }
}

// Real photo for the split-screen panel, same source as the homepage hero
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
      <h2>Pick up right where<br>you left off.</h2>
      <p>Your saved spots, hotels, and itineraries are waiting — log back in to keep planning your Laguna trip.</p>
    </div>
  </div>

  <div class="auth-split-form">
    <div class="auth-form-inner fade-up">
      <h2 class="auth-heading">Welcome Back</h2>
      <p class="auth-subheading">Log in to access your saved itineraries</p>

      <?php if ($error): ?>
        <div class="alert alert-danger small mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="input-icon-wrap">
            <i class="bi bi-envelope"></i>
            <input type="email" class="form-control" name="email"
                   value="<?= e($email) ?>" placeholder="juan@email.com" autofocus required>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="input-group">
            <div class="input-icon-wrap flex-grow-1">
              <i class="bi bi-lock"></i>
              <input type="password" class="form-control" name="password"
                     id="pw-field" placeholder="Your password" required
                     style="border-radius:var(--radius-sm) 0 0 var(--radius-sm)">
            </div>
            <button type="button" class="btn btn-outline-secondary" id="pw-toggle"
                    style="border-color:var(--border);border-left:none">
              <i class="bi bi-eye" id="pw-icon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary-app w-100 py-2">
          <i class="bi bi-box-arrow-in-right me-2"></i>Log In
        </button>
      </form>

      <hr class="my-3" style="border-color:var(--border)">
      <p class="text-center text-muted small mb-0">
        Don't have an account?
        <a href="register.php" class="fw-bold text-green">Sign up free</a>
      </p>
    </div>
  </div>
</section>

<script>
document.getElementById('pw-toggle').addEventListener('click', function() {
  const f = document.getElementById('pw-field');
  const i = document.getElementById('pw-icon');
  f.type = f.type === 'password' ? 'text' : 'password';
  i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
