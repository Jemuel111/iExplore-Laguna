<?php
// ============================================================
// IEXPLORE LAGUNA — Register Page
// pages/register.php
// ============================================================
$page_title  = 'Create Account';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) {
    header('Location: ' . APP_URL);
    exit;
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim(input('name', 'post', ''));
    $email    = strtolower(trim(input('email', 'post', '')));
    $password = input('password', 'post', '');
    $confirm  = input('confirm',  'post', '');

    // Validate
    if (strlen($name) < 2)          $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)      $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check duplicate email
        $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
                [$name, $email, $hash]
            );
            $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
            login_user($user);
            $_SESSION['flash']['success'] = 'Welcome to iExplore Laguna, ' . $name . '!';
            header('Location: ' . APP_URL);
            exit;
        }
    }
}
?>

<section class="py-5" style="background:var(--green-pale);min-height:80vh;display:flex;align-items:center">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">

  <div class="text-center mb-4">
    <span style="font-size:2.5rem">🗺️</span>
    <h2 class="mt-2" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
      Create Your Account
    </h2>
    <p class="text-muted small">Save itineraries and plan future trips</p>
  </div>

  <div class="form-panel">
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
        <input type="text" class="form-control" name="name"
               value="<?= e($name) ?>" placeholder="Juan dela Cruz" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" class="form-control" name="email"
               value="<?= e($email) ?>" placeholder="juan@email.com" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" class="form-control" name="password"
                 id="pw-field" placeholder="Min. 8 characters" required>
          <button type="button" class="btn btn-outline-secondary" id="pw-toggle"
                  style="border-color:var(--border)">
            <i class="bi bi-eye" id="pw-icon"></i>
          </button>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Confirm Password</label>
        <input type="password" class="form-control" name="confirm"
               placeholder="Repeat password" required>
      </div>
      <button type="submit" class="btn btn-primary-app w-100">
        <i class="bi bi-person-plus me-2"></i>Create Account
      </button>
    </form>

    <hr class="my-3">
    <p class="text-center text-muted small mb-0">
      Already have an account?
      <a href="login.php" class="fw-bold text-green">Log in here</a>
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
  if (f.type === 'password') {
    f.type = 'text';
    i.className = 'bi bi-eye-slash';
  } else {
    f.type = 'password';
    i.className = 'bi bi-eye';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>