<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Super Admin: Manage City Admins
// pages/admin-city-admins.php
// Create city admin accounts, assign one city each, reset
// passwords, activate/deactivate. Super admin only.
// ============================================================
$page_title  = 'City Admins';
$active_page = 'admin-city-admins';

$u = require_admin_area(true);   // super admin only

$self   = APP_URL . '/pages/admin-city-admins.php';
$errors = [];
$old    = ['name' => '', 'email' => '', 'phone' => '', 'city_id' => 0];

$cities = db_fetch_all("SELECT id, name FROM cities ORDER BY name");
$cityIds = array_map('intval', array_column($cities, 'id'));

/** Load a user only if they really are a city admin. */
function load_city_admin(int $id): ?array {
    $row = db_fetch_one("SELECT * FROM users WHERE id = ? AND role = 'city_admin'", [$id]);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) input('action', 'post', '');

    // ── Create ──────────────────────────────────────────────
    if ($action === 'create_city_admin') {
        $old['name']    = trim((string) input('name', 'post', ''));
        $old['email']   = strtolower(trim((string) input('email', 'post', '')));
        $old['phone']   = trim((string) input('phone', 'post', ''));
        $old['city_id'] = (int) input('city_id', 'post', 0);
        $password       = (string) input('password', 'post', '');

        if (strlen($old['name']) < 2)                          $errors[] = 'Name must be at least 2 characters.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8)                             $errors[] = 'Password must be at least 8 characters.';
        if (!in_array($old['city_id'], $cityIds, true))        $errors[] = 'Please choose a city.';

        if (!$errors && db_fetch_one("SELECT id FROM users WHERE email = ?", [$old['email']])) {
            $errors[] = 'An account with that email already exists.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute(
                "INSERT INTO users (name, email, role, city_id, phone, password, is_active)
                 VALUES (?, ?, 'city_admin', ?, ?, ?, 1)",
                [$old['name'], $old['email'], $old['city_id'], $old['phone'] ?: null, $hash]
            );
            $newId = (int) db_last_id();
            log_admin_activity('create_city_admin', 'user', $newId, $old['city_id'], $old['name']);
            $_SESSION['flash']['success'] = "City admin \"{$old['name']}\" created. Share their email and password with them privately.";
            header('Location: ' . $self); exit;
        }
    }

    // ── Change city ─────────────────────────────────────────
    if ($action === 'change_city') {
        $uid    = (int) input('user_id', 'post', 0);
        $cityId = (int) input('city_id', 'post', 0);
        $target = load_city_admin($uid);
        if (!$target || !in_array($cityId, $cityIds, true)) {
            $_SESSION['flash']['danger'] = 'Could not update that account.';
        } else {
            db_execute("UPDATE users SET city_id = ? WHERE id = ?", [$cityId, $uid]);
            log_admin_activity('change_admin_city', 'user', $uid, $cityId, (string) $target['name']);
            $_SESSION['flash']['success'] = "{$target['name']} now manages the selected city.";
        }
        header('Location: ' . $self); exit;
    }

    // ── Activate / deactivate ───────────────────────────────
    if ($action === 'toggle_city_admin') {
        $uid    = (int) input('user_id', 'post', 0);
        $target = load_city_admin($uid);
        if (!$target) {
            $_SESSION['flash']['danger'] = 'Could not update that account.';
        } else {
            $new = $target['is_active'] ? 0 : 1;
            db_execute("UPDATE users SET is_active = ? WHERE id = ?", [$new, $uid]);
            log_admin_activity($new ? 'activate_city_admin' : 'deactivate_city_admin', 'user', $uid, $target['city_id'] ? (int) $target['city_id'] : null, (string) $target['name']);
            $_SESSION['flash']['success'] = $new ? "{$target['name']} reactivated." : "{$target['name']} deactivated. They can no longer manage their city.";
        }
        header('Location: ' . $self); exit;
    }

    // ── Reset password ──────────────────────────────────────
    if ($action === 'reset_password') {
        $uid      = (int) input('user_id', 'post', 0);
        $password = (string) input('password', 'post', '');
        $target   = load_city_admin($uid);
        if (!$target) {
            $_SESSION['flash']['danger'] = 'Could not update that account.';
        } elseif (strlen($password) < 8) {
            $_SESSION['flash']['danger'] = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db_execute("UPDATE users SET password = ? WHERE id = ?", [$hash, $uid]);
            log_admin_activity('reset_admin_password', 'user', $uid, $target['city_id'] ? (int) $target['city_id'] : null, (string) $target['name']);
            $_SESSION['flash']['success'] = "Password updated for {$target['name']}.";
        }
        header('Location: ' . $self); exit;
    }
}

// ── Data ──────────────────────────────────────────────────────
ensure_admin_activity_table();

$admins = db_fetch_all(
    "SELECT u.id, u.name, u.email, u.phone, u.city_id, u.is_active, u.created_at, c.name AS city_name
     FROM users u LEFT JOIN cities c ON c.id = u.city_id
     WHERE u.role = 'city_admin'
     ORDER BY c.name, u.name"
);

$activity = db_fetch_all(
    "SELECT l.action, l.entity, l.details, l.created_at, u.name AS user_name, c.name AS city_name
     FROM admin_activity_log l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN cities c ON c.id = l.city_id
     ORDER BY l.id DESC LIMIT 30"
);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container d-flex align-items-center gap-3">
    <i class="bi bi-people fs-2" style="color:var(--sand-dark)"></i>
    <div>
      <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">City Admins</h1>
      <p class="mb-0 small opacity-75">Each city admin manages the spots, hotels and shops of one city</p>
    </div>
  </div>
</section>

<div class="container py-4">
<div class="row g-4">

  <!-- ── Create ─────────────────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
        <i class="bi bi-person-plus me-2"></i>New City Admin
      </h6>

      <?php if ($errors): ?>
      <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off"><?= csrf_field() ?>
        <input type="hidden" name="action" value="create_city_admin">
        <div class="mb-3">
          <label class="form-label">Full name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="name" required value="<?= e($old['name']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control" name="email" required value="<?= e($old['email']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" class="form-control" name="phone" value="<?= e($old['phone']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">City they manage <span class="text-danger">*</span></label>
          <select class="form-select" name="city_id" required>
            <option value="">Select…</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $old['city_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Temporary password <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="password" required minlength="8" autocomplete="new-password">
          <div class="form-text">At least 8 characters. Give it to them privately and ask them to change it.</div>
        </div>
        <button class="btn btn-primary-app w-100"><i class="bi bi-check-lg me-1"></i>Create City Admin</button>
      </form>
    </div>
  </div>

  <!-- ── List ───────────────────────────────────────────────── -->
  <div class="col-lg-8">
    <div class="form-panel mb-4">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
        <i class="bi bi-people me-2"></i>Accounts (<?= count($admins) ?>)
      </h6>

      <?php if (!$admins): ?>
        <p class="text-muted small mb-0">No city admins yet. Create the first one on the left.</p>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($admins as $a): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid <?= $a['is_active'] ? 'var(--border)' : '#e6c0c0' ?>;border-radius:var(--radius-sm)">
            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
              <div>
                <div class="fw-bold"><?= e((string) $a['name']) ?>
                  <?php if (!$a['is_active']): ?><span class="badge bg-secondary ms-1" style="font-size:.68rem">Inactive</span><?php endif; ?>
                </div>
                <div class="small text-muted">
                  <i class="bi bi-envelope me-1"></i><?= e((string) $a['email']) ?>
                  <?php if (!empty($a['phone'])): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e((string) $a['phone']) ?><?php endif; ?>
                </div>
                <div class="small mt-1">
                  <i class="bi bi-geo-alt me-1"></i>
                  <?= $a['city_name'] ? '<strong>' . e((string) $a['city_name']) . '</strong>' : '<span class="text-danger">No city assigned</span>' ?>
                </div>
              </div>
              <form method="POST" onsubmit="return confirm('<?= $a['is_active'] ? 'Deactivate this city admin? They will be locked out immediately.' : 'Reactivate this city admin?' ?>');"><?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_city_admin">
                <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-sm <?= $a['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" style="border-radius:var(--radius-pill)">
                  <?= $a['is_active'] ? '<i class="bi bi-slash-circle me-1"></i>Deactivate' : '<i class="bi bi-check-circle me-1"></i>Reactivate' ?>
                </button>
              </form>
            </div>

            <div class="row g-2 mt-2">
              <div class="col-md-6">
                <form method="POST" class="d-flex gap-2"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="change_city">
                  <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                  <select class="form-select form-select-sm" name="city_id">
                    <?php foreach ($cities as $c): ?>
                      <option value="<?= (int) $c['id'] ?>" <?= (int) $a['city_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-secondary flex-shrink-0">Change city</button>
                </form>
              </div>
              <div class="col-md-6">
                <form method="POST" class="d-flex gap-2" autocomplete="off"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                  <input type="text" class="form-control form-control-sm" name="password" minlength="8" placeholder="New password" required autocomplete="new-password">
                  <button class="btn btn-sm btn-outline-secondary flex-shrink-0">Reset</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Activity ─────────────────────────────────────────── -->
    <div class="form-panel">
      <h6 class="fw-bold mb-3" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
        <i class="bi bi-clock-history me-2"></i>Recent admin activity (all cities)
      </h6>
      <?php if (!$activity): ?>
        <p class="text-muted small mb-0">Nothing recorded yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle small mb-0">
            <thead><tr><th>When</th><th>Who</th><th>City</th><th>What</th></tr></thead>
            <tbody>
              <?php foreach ($activity as $row): ?>
              <tr>
                <td class="text-nowrap"><?= e(date('M d, g:i A', strtotime((string) $row['created_at']))) ?></td>
                <td><?= e((string) ($row['user_name'] ?? 'Unknown')) ?></td>
                <td><?= e((string) ($row['city_name'] ?? '—')) ?></td>
                <td><strong><?= e(ucwords(str_replace('_', ' ', (string) $row['action']))) ?></strong><?= $row['details'] ? ' — ' . e((string) $row['details']) : '' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
