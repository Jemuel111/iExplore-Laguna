<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Register Page (Polished v2)
// ============================================================
$page_title  = 'Create Account';
$active_page = '';


if (is_logged_in()) { header('Location: ' . APP_URL); exit; }

$errors = [];
$name = $email = '';
$birthdate = $gender = $tourist_type = $nationality = $province = $city = '';

ensure_user_demographic_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim(input('name',     'post', ''));
    $email    = strtolower(trim(input('email',    'post', '')));
    $password = input('password', 'post', '');
    $confirm  = input('confirm',  'post', '');

    $birthdate    = input('birthdate',    'post', '');
    $gender       = input('gender',       'post', '');
    $tourist_type = input('tourist_type', 'post', '');
    $nationality  = trim(input('nationality', 'post', ''));
    $province     = trim(input('province',    'post', ''));
    $city         = trim(input('city',        'post', ''));

    if (strlen($name) < 2)                          $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

    // Demographics — required per the signup flow
    $birthdateObj = $birthdate ? DateTime::createFromFormat('Y-m-d', $birthdate) : null;
    if (!$birthdateObj) {
        $errors[] = 'Please enter your birthdate.';
    } else {
        $age = $birthdateObj->diff(new DateTime())->y;
        if ($age < 13)  $errors[] = 'You must be at least 13 years old to register.';
        if ($age > 120) $errors[] = 'Please enter a valid birthdate.';
    }
    if (!in_array($gender, ['male','female','prefer_not_to_say'], true)) {
        $errors[] = 'Please select your gender.';
    }
    if (!in_array($tourist_type, ['local','international'], true)) {
        $errors[] = 'Please select whether you are a local or international tourist.';
    } elseif ($tourist_type === 'local') {
        if ($province === '') $errors[] = 'Please enter your province.';
        if ($city === '')     $errors[] = 'Please enter your city/municipality.';
    } elseif ($tourist_type === 'international') {
        if ($nationality === '') $errors[] = 'Please enter your nationality.';
    }

    if (empty($errors)) {
        $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute(
                "INSERT INTO users (name, email, password, birthdate, gender, tourist_type, nationality, province, city)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $name, $email, $hash, $birthdate, $gender, $tourist_type,
                    $tourist_type === 'international' ? $nationality : null,
                    $tourist_type === 'local' ? $province : null,
                    $tourist_type === 'local' ? $city : null,
                ]
            );
            $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
            login_user($user);
            $_SESSION['flash']['success'] = 'Welcome to iExplore Laguna, ' . $name . '!';
            header('Location: ' . APP_URL); exit;
        }
    }
}

// Real photo for the split-screen panel — different from login's via a
// separate random pick, so the two pages don't feel like copy-paste twins
$auth_photo = db_fetch_one(
    "SELECT url FROM spot_photos WHERE photo_type='main' ORDER BY RAND() LIMIT 1"
)['url'] ?? null;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-split auth-split-reverse">
  <div class="auth-split-form">
    <div class="auth-form-inner fade-up">
      <h2 class="auth-heading">Create Your Account</h2>
      <p class="auth-subheading">Save itineraries and plan future trips</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger mb-3">
          <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate><?= csrf_field() ?>
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

        <hr class="my-3" style="border-color:var(--border)">
        <p class="small fw-bold mb-3" style="color:var(--green-dark)">
          <i class="bi bi-clipboard-data me-1"></i>Tell us a bit about yourself
        </p>

        <div class="row g-3 mb-3">
          <div class="col-sm-7">
            <label class="form-label">Birthdate</label>
            <input type="date" class="form-control" name="birthdate"
                   value="<?= e($birthdate) ?>" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-sm-5">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender" required>
              <option value="">Select…</option>
              <option value="male"               <?= $gender==='male'?'selected':'' ?>>Male</option>
              <option value="female"             <?= $gender==='female'?'selected':'' ?>>Female</option>
              <option value="prefer_not_to_say"  <?= $gender==='prefer_not_to_say'?'selected':'' ?>>Prefer not to say</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label d-block">Are you a local or international tourist?</label>
          <div class="btn-group w-100" role="group" id="tourist-type-group">
            <input type="radio" class="btn-check" name="tourist_type" id="tt-local" value="local" autocomplete="off"
                   <?= $tourist_type==='local'?'checked':'' ?> required>
            <label class="btn btn-outline-secondary" for="tt-local"><i class="bi bi-house-door me-1"></i>Local (Filipino)</label>

            <input type="radio" class="btn-check" name="tourist_type" id="tt-intl" value="international" autocomplete="off"
                   <?= $tourist_type==='international'?'checked':'' ?> required>
            <label class="btn btn-outline-secondary" for="tt-intl"><i class="bi bi-airplane me-1"></i>International</label>
          </div>
        </div>

        <!-- Shown when "Local" is selected -->
        <div class="row g-3 mb-3 tt-field tt-field-local" style="display:none">
          <div class="col-sm-6">
            <label class="form-label">Province</label>
            <input type="text" class="form-control" name="province"
                   value="<?= e($province) ?>" placeholder="e.g. Laguna">
          </div>
          <div class="col-sm-6">
            <label class="form-label">City / Municipality</label>
            <input type="text" class="form-control" name="city"
                   value="<?= e($city) ?>" placeholder="e.g. Santa Rosa">
          </div>
        </div>

        <!-- Shown when "International" is selected -->
        <div class="mb-3 tt-field tt-field-intl" style="display:none">
          <label class="form-label">Nationality</label>
          <input type="text" class="form-control" name="nationality"
                 value="<?= e($nationality) ?>" placeholder="e.g. American, Korean, Japanese">
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

  <div class="auth-split-photo">
    <?php if ($auth_photo): ?>
    <div class="auth-split-bg" style="background-image:url('<?= e($auth_photo) ?>')"></div>
    <?php endif; ?>
    <div class="auth-split-content">
      <a href="<?= APP_URL ?>" class="auth-wordmark">
        <i class="bi bi-map-fill me-2"></i><em>i</em>Explore <span>Laguna</span>
      </a>
      <h2>Your next Laguna<br>trip starts here.</h2>
      <p>Create a free account to save spots, build itineraries, and book hotels — all in one place.</p>
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

// Show/hide + require the right fields based on Local vs International
function updateTouristTypeFields() {
  const isLocal = document.getElementById('tt-local').checked;
  const isIntl  = document.getElementById('tt-intl').checked;

  document.querySelectorAll('.tt-field-local').forEach(el => {
    el.style.display = isLocal ? '' : 'none';
    el.querySelectorAll('input').forEach(inp => inp.required = isLocal);
  });
  document.querySelectorAll('.tt-field-intl').forEach(el => {
    el.style.display = isIntl ? '' : 'none';
    el.querySelectorAll('input').forEach(inp => inp.required = isIntl);
  });
}
document.querySelectorAll('input[name="tourist_type"]').forEach(r => {
  r.addEventListener('change', updateTouristTypeFields);
});
updateTouristTypeFields(); // run once on load in case of a validation-error reload with a value already selected
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
