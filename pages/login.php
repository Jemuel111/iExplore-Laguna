<?php
// ============================================================
// IEXPLORE LAGUNA — Login Page
// pages/login.php
// ============================================================
$page_title  = 'Login';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) {
    header('Location: ' . APP_URL);
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim(input('email',    'post', '')));
    $password = input('password', 'post', '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user && password_verify($password, $user['password'])) {
            login_user($user);
            $redirect = input('redirect', 'get', APP_URL);
            $_SESSION['flash']['success'] = 'Welcome back, ' . $user['name'] . '!';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>

<section class="py-5" style="background:var(--green-pale);min-height:80vh;display:flex;align-items:center">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-4">

  <div class="text-center mb-4">
    <span style="font-size:2.5rem">🗺️</span>
    <h2 class="mt-2" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
      Welcome Back
    </h2>
    <p class="text-muted small">Log in to access your saved itineraries</p>
  </div>

  <div class="form-panel">
    <?php if ($error): ?>
      <div class="alert alert-danger small mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" class="form-control" name="email"
               value="<?= e($email) ?>" placeholder="juan@email.com" autofocus required>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" class="form-control" name="password"
                 id="pw-field" placeholder="Your password" required>
          <button type="button" class="btn btn-outline-secondary" id="pw-toggle"
                  style="border-color:var(--border)">
            <i class="bi bi-eye" id="pw-icon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary-app w-100">
        <i class="bi bi-box-arrow-in-right me-2"></i>Log In
      </button>
    </form>

    <hr class="my-3">
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