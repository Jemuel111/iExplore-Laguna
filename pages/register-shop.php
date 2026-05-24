<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
session_start_safe();
// ============================================================
// iEXPLORE LAGUNA — Shop Owner Registration
// pages/register-shop.php
// Step 1: Create account with role=shop_owner
// Step 2: Fill shop profile
// ============================================================
$page_title  = 'Register Your Shop';
$active_page = '';


// Auth redirect BEFORE any output
if (is_logged_in()) {
    $u = current_user();
    $role = $u['role'] ?? 'tourist';
    if ($role === 'shop_owner') {
        // Only redirect if they already have a shop — otherwise let them complete step 2
        $existing_shop = db_fetch_one("SELECT id FROM shops WHERE owner_id = ?", [$u['id']]);
        if ($existing_shop) {
            header('Location: ' . APP_URL . '/pages/shop-dashboard.php'); exit;
        }
        // No shop yet — fall through to show step 2 form
    } else {
        header('Location: ' . APP_URL); exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

$step   = (int) input('step', 'get', 1);
$errors = [];

// ── STEP 1 POST: create account ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $name     = trim(input('name',     'post', ''));
    $email    = strtolower(trim(input('email',    'post', '')));
    $phone    = trim(input('phone',    'post', ''));
    $password = input('password', 'post', '');
    $confirm  = input('confirm',  'post', '');

    if (strlen($name) < 2)                           $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)                       $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute(
                "INSERT INTO users (name, email, role, phone, password) VALUES (?, ?, 'shop_owner', ?, ?)",
                [$name, $email, $phone, $hash]
            );
            $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
            login_user($user);
            header('Location: ' . APP_URL . '/pages/register-shop.php?step=2'); exit;
        }
    }
}

// ── STEP 2 POST: create shop profile ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
    $u = current_user();

    $shop_name   = trim(input('shop_name',   'post', ''));
    $city_id     = (int) input('city_id',    'post', 0);
    $category    = input('category',  'post', 'other');
    $description = trim(input('description', 'post', ''));
    $address     = trim(input('address',     'post', ''));
    $phone       = trim(input('phone',       'post', ''));
    $email       = trim(input('shop_email',  'post', ''));
    $open_time   = input('open_time',  'post', null);
    $close_time  = input('close_time', 'post', null);
    $open_days   = trim(input('open_days',   'post', ''));

    if (strlen($shop_name) < 2) $errors[] = 'Shop name is required.';
    if (!$city_id)              $errors[] = 'Please select a city.';

    if (empty($errors)) {
        $slug = slugify($shop_name) . '-' . $u['id'];
        db_execute(
            "INSERT INTO shops
               (owner_id, city_id, name, slug, category, description,
                address, phone, email, open_time, close_time, open_days)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$u['id'], $city_id, $shop_name, $slug, $category,
             $description, $address, $phone, $email,
             $open_time ?: null, $close_time ?: null, $open_days]
        );
        $_SESSION['flash']['success'] = 'Shop registered! Now add your products.';
        header('Location: ' . APP_URL . '/pages/shop-dashboard.php'); exit;
    }
}

$cities = db_fetch_all("SELECT id, name FROM cities ORDER BY name");

$categories = [
    'milktea'    => '🧋 Milk Tea',
    'cafe'       => '☕ Café',
    'restaurant' => '🍜 Restaurant',
    'bakery'     => '🥐 Bakery',
    'street_food'=> '🍢 Street Food',
    'souvenir'   => '🛍️ Souvenir',
    'pasalubong' => '🎁 Pasalubong',
    'grocery'    => '🛒 Grocery',
    'other'      => '🏪 Other',
];
?>

<section style="min-height:85vh;display:flex;align-items:center;background:linear-gradient(135deg,var(--green-pale) 0%,var(--sand) 100%)">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">

        <!-- Progress steps -->
        <div class="d-flex align-items-center justify-content-center gap-0 mb-4 fade-up">
          <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;
                 background:<?= $step>=1?'var(--green-mid)':'#ddd' ?>;color:#fff">1</div>
            <span style="font-size:.82rem;font-weight:600;color:<?= $step>=1?'var(--green-dark)':'#999' ?>">Account</span>
          </div>
          <div style="width:48px;height:2px;background:<?= $step>=2?'var(--green-mid)':'#ddd' ?>;margin:0 .5rem"></div>
          <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;
                 background:<?= $step>=2?'var(--green-mid)':'#ddd' ?>;color:<?= $step>=2?'#fff':'#999' ?>">2</div>
            <span style="font-size:.82rem;font-weight:600;color:<?= $step>=2?'var(--green-dark)':'#999' ?>">Shop Profile</span>
          </div>
        </div>

        <!-- Header -->
        <div class="text-center mb-4 fade-up">
          <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--terracotta),var(--sand-dark));border-radius:18px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(199,124,72,.3);margin-bottom:.75rem">
            <i class="bi bi-shop fs-3" style="color:#fff"></i>
          </div>
          <h2 class="mt-2 mb-1" style="font-family:'Playfair Display',serif;color:var(--green-dark)">
            <?= $step === 1 ? 'Register as Shop Owner' : 'Set Up Your Shop' ?>
          </h2>
          <p class="text-muted small">
            <?= $step === 1
              ? 'Create your account to start selling on iExplore Laguna'
              : 'Tell tourists about your shop — location, hours, and what you sell' ?>
          </p>
        </div>

        <div class="form-panel fade-up fade-up-1">

          <?php if ($errors): ?>
            <div class="alert alert-danger small mb-3">
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                  <li><?= e($e) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <!-- ── STEP 1: Account form ── -->
          <?php if ($step === 1): ?>
          <form method="POST" action="?step=1" novalidate>
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <div class="input-icon-wrap">
                <i class="bi bi-person"></i>
                <input type="text" class="form-control" name="name" placeholder="Your full name" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-icon-wrap">
                <i class="bi bi-envelope"></i>
                <input type="email" class="form-control" name="email" placeholder="shop@email.com" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Mobile Number</label>
              <div class="input-icon-wrap">
                <i class="bi bi-phone"></i>
                <input type="text" class="form-control" name="phone" placeholder="09XXXXXXXXX">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <div class="input-icon-wrap flex-grow-1">
                  <i class="bi bi-lock"></i>
                  <input type="password" class="form-control" name="password" id="pw-field"
                         placeholder="Min. 8 characters" required
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
                <input type="password" class="form-control" name="confirm" placeholder="Repeat password" required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary-app w-100 py-2">
              <i class="bi bi-arrow-right-circle me-2"></i>Continue to Shop Setup
            </button>
          </form>

          <!-- ── STEP 2: Shop profile form ── -->
          <?php else: ?>
          <form method="POST" action="?step=2" novalidate>
            <div class="mb-3">
              <label class="form-label fw-600">Shop Name <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="bi bi-shop"></i>
                <input type="text" class="form-control" name="shop_name"
                       placeholder="e.g. Sip & Smile Milk Tea" required>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label fw-600">City / Municipality <span class="text-danger">*</span></label>
                <select class="form-select" name="city_id" required>
                  <option value="">Select city…</option>
                  <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-600">Shop Category <span class="text-danger">*</span></label>
                <select class="form-select" name="category">
                  <?php foreach ($categories as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Street Address</label>
              <div class="input-icon-wrap">
                <i class="bi bi-pin-map"></i>
                <input type="text" class="form-control" name="address"
                       placeholder="e.g. 123 Rizal St., Brgy. San Jose">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"
                        placeholder="Tell tourists what makes your shop special…"
                        style="resize:none"></textarea>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label">Shop Phone</label>
                <div class="input-icon-wrap">
                  <i class="bi bi-telephone"></i>
                  <input type="text" class="form-control" name="phone" placeholder="09XXXXXXXXX">
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label">Shop Email</label>
                <div class="input-icon-wrap">
                  <i class="bi bi-envelope"></i>
                  <input type="email" class="form-control" name="shop_email" placeholder="shop@email.com">
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-sm-4">
                <label class="form-label">Opening Time</label>
                <input type="time" class="form-control" name="open_time" value="08:00">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Closing Time</label>
                <input type="time" class="form-control" name="close_time" value="21:00">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Open Days</label>
                <input type="text" class="form-control" name="open_days" placeholder="Mon–Sat">
              </div>
            </div>

            <button type="submit" class="btn btn-primary-app w-100 py-2 mt-2">
              <i class="bi bi-check-circle me-2"></i>Register My Shop
            </button>
          </form>
          <?php endif; ?>

          <hr class="my-3" style="border-color:var(--border)">
          <p class="text-center text-muted small mb-0">
            Already have an account?
            <a href="login.php" class="fw-bold text-green">Log in here</a>
            &nbsp;·&nbsp;
            <a href="register.php" class="fw-bold text-green">Tourist account</a>
          </p>

        </div>
      </div>
    </div>
  </div>
</section>

<script>
const t = document.getElementById('pw-toggle');
if (t) t.addEventListener('click', function() {
  const f = document.getElementById('pw-field');
  const i = document.getElementById('pw-icon');
  f.type = f.type === 'password' ? 'text' : 'password';
  i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
