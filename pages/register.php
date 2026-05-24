<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
// ============================================================
// iEXPLORE LAGUNA — Register Page (Polished v2)
// ============================================================
$page_title  = 'Create Account';
$active_page = '';


if (is_logged_in()) { header('Location: ' . APP_URL); exit; }

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim(input('name',     'post', ''));
    $email    = strtolower(trim(input('email',    'post', '')));
    $password = input('password', 'post', '');
    $confirm  = input('confirm',  'post', '');

    if (strlen($name) < 2)                          $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute("INSERT INTO users (name, email, password) VALUES (?, ?, ?)", [$name, $email, $hash]);
            $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
            login_user($user);
            $_SESSION['flash']['success'] = 'Welcome to iExplore Laguna, ' . $name . '!';
            header('Location: ' . APP_URL); exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section style="min-height:80vh;display:flex;align-items:center;background:linear-gradient(135deg,var(--green-pale) 0%,var(--sand) 100%)">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">

        <div class="text-center mb-4 fade-up">
          <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--green-mid),var(--green-dark));border-radius:18px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(45,106,79,.3);margin-bottom:.75rem">
            <i class="bi bi-person-plus-fill fs-3" style="color:#fff"></i>
          </div>
          <h2 class="mt-2 mb-1" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
            Create Your Account
          </h2>
          <p class="text-muted small">Save itineraries and plan future trips</p>
        </div>

        <div class="form-panel fade-up fade-up-1">
          <?php if ($errors): ?>
            <div class="alert alert-danger mb-3">
              <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <div class="input-icon-wrap">
                <i class="bi bi-person"></i>
                <input type="text" class="form-control" name="name"
                       value="<?= e($name) ?>" placeholder="Juan dela Cruz" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-icon-wrap">
                <i class="bi bi-envelope"></i>
                <input type="email" class="form-control" name="email"
                       value="<?= e($email) ?>" placeholder="juan@email.com" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <div class="input-icon-wrap flex-grow-1">
                  <i class="bi bi-lock"></i>
                  <input type="password" class="form-control" name="password"
                         id="pw-field" placeholder="Min. 8 characters" required
                         style="border-radius:var(--radius-sm) 0 0 var(--radius-sm)">
                </div>
                <button type="button" class="btn btn-outline-secondary" id="pw-toggle"
                        style="border-color:var(--border);border-left:none">
                  <i class="bi bi-eye" id="pw-icon"></i>
                </button>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">Confirm Password</label>
              <div class="input-icon-wrap">
                <i class="bi bi-lock-fill"></i>
                <input type="password" class="form-control" name="confirm"
                       placeholder="Repeat password" required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary-app w-100 py-2">
              <i class="bi bi-person-plus me-2"></i>Create Account
            </button>
          </form>

          <hr class="my-3" style="border-color:var(--border)">
          <p class="text-center text-muted small mb-0">
            Already have an account?
            <a href="login.php" class="fw-bold text-green">Log in here</a>
          </p>
          <hr class="my-3" style="border-color:var(--border)">
          <p class="text-center small mb-0" style="color:var(--text-muted)">
            Own a shop or hotel?
            <a href="register-shop.php" class="fw-bold" style="color:var(--terracotta)">Register as Shop Owner</a>
            &nbsp;·&nbsp;
            <a href="register-hotel.php" class="fw-bold" style="color:var(--terracotta)">Register as Hotel Owner</a>
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
