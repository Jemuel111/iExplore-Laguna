<?php
// ============================================================
// iEXPLORE LAGUNA — Login Page (Polished v2)
// ============================================================
$page_title  = 'Login';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) { header('Location: ' . APP_URL); exit; }

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim(input('email', 'post', '')));
    $password = input('password', 'post', '');
    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user && password_verify($password, $user['password'])) {
            login_user($user);
            $redirect = input('redirect', 'get', APP_URL);
            $_SESSION['flash']['success'] = 'Welcome back, ' . $user['name'] . '!';
            header('Location: ' . $redirect); exit;
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>

<section style="min-height:80vh;display:flex;align-items:center;background:linear-gradient(135deg,var(--green-pale) 0%,var(--sand) 100%)">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">

        <div class="text-center mb-4 fade-up">
          <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--green-mid),var(--green-dark));border-radius:18px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(45,106,79,.3);margin-bottom:.75rem">
            <i class="bi bi-map-fill fs-3" style="color:#fff"></i>
          </div>
          <h2 class="mt-2 mb-1" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
            Welcome Back
          </h2>
          <p class="text-muted small">Log in to access your saved itineraries</p>
        </div>

        <div class="form-panel fade-up fade-up-1">
          <?php if ($error): ?>
            <div class="alert alert-danger small mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
              <span><?= e($error) ?></span>
            </div>
          <?php endif; ?>

          <form method="POST" novalidate>
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
