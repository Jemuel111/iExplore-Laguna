<?php
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
// ============================================================
// iEXPLORE LAGUNA — Admin Dashboard
// pages/admin-dashboard.php
// Approve / reject shop & hotel registrations
// ============================================================
$page_title  = 'Admin Dashboard';
$active_page = '';


if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { header('Location: ' . APP_URL); exit; }


// ── Site branding / theme settings ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    if ($action === 'save_site_settings') {
        $colors = [
            'theme_dark'    => trim((string)input('theme_dark', 'post', '')),
            'theme_primary' => trim((string)input('theme_primary', 'post', '')),
            'theme_light'   => trim((string)input('theme_light', 'post', '')),
            'theme_pale'    => trim((string)input('theme_pale', 'post', '')),
            'theme_accent'  => trim((string)input('theme_accent', 'post', '')),
        ];

        $valid = true;
        foreach ($colors as $color) {
            if (!valid_hex_color($color)) {
                $valid = false;
                break;
            }
        }

        if (!$valid) {
            $_SESSION['flash']['danger'] = 'Please use valid 6-digit HEX colors (for example #6b0f14).';
            header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
        }

        foreach ($colors as $key => $value) {
            set_site_setting($key, $value);
        }

        if (!empty($_FILES['site_logo']['name'])) {
            $file = $_FILES['site_logo'];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $_SESSION['flash']['danger'] = 'The logo upload failed. Please try again.';
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
            }

            if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
                $_SESSION['flash']['danger'] = 'Logo must be 2 MB or smaller.';
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
            ];

            if (!isset($allowed[$mime])) {
                $_SESSION['flash']['danger'] = 'Logo must be PNG, JPG, WEBP, or SVG.';
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
            }

            $upload_dir = __DIR__ . '/../uploads';
            if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                $_SESSION['flash']['danger'] = 'The uploads folder is missing or not writable by PHP. Please check the uploads folder permissions.';
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
            }

            $filename = 'site-logo-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            $target = $upload_dir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $_SESSION['flash']['danger'] = 'Could not save the uploaded logo. Please check that the uploads folder is writable by PHP.';
                header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
            }

            $old_logo = trim((string)(site_settings()['logo_path'] ?? ''));
            if ($old_logo && strpos($old_logo, 'uploads/site-logo-') === 0) {
                $old_file = dirname(__DIR__) . '/' . $old_logo;
                if (is_file($old_file)) @unlink($old_file);
            }

            set_site_setting('logo_path', 'uploads/' . $filename);
        }

        $_SESSION['flash']['success'] = 'Site branding and color theme updated successfully.';
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
    }

    if ($action === 'reset_site_theme') {
        // Restore the original iExplore Laguna color theme without changing the logo.
        $default_theme = [
            'theme_dark'    => '#B0281C',
            'theme_primary' => '#D9481F',
            'theme_light'   => '#FF7A45',
            'theme_pale'    => '#FFE3D2',
            'theme_accent'  => '#e9c46a',
        ];
        foreach ($default_theme as $key => $value) {
            set_site_setting($key, $value);
        }
        $_SESSION['flash']['success'] = 'Color theme restored to the default iExplore Laguna colors.';
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
    }

    if ($action === 'save_route_fare') {
        $route_id = (int) input('route_id', 'post', 0);
        $origin_city_id = (int) input('origin_city_id', 'post', 0);
        $dest_city_id = (int) input('dest_city_id', 'post', 0);
        $transport_type = trim((string) input('transport_type', 'post', ''));
        $fare_php = (float) input('fare_php', 'post', 0);
        $notes = trim((string) input('notes', 'post', ''));
        $allowed_types = ['jeepney','bus','tricycle','fx_uv','private_car'];
        if (!$origin_city_id || !$dest_city_id || $origin_city_id === $dest_city_id) { $_SESSION['flash']['danger']='Please choose two different cities.'; header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit; }
        if (!in_array($transport_type, $allowed_types, true)) { $_SESSION['flash']['danger']='Please choose a valid transportation type.'; header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit; }
        if ($fare_php < 0 || $fare_php > 10000) { $_SESSION['flash']['danger']='Please enter a valid fare between ₱0 and ₱10,000.'; header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit; }
        $origin = db_fetch_one("SELECT id,name FROM cities WHERE id=?",[$origin_city_id]);
        $dest = db_fetch_one("SELECT id,name FROM cities WHERE id=?",[$dest_city_id]);
        if (!$origin || !$dest) { $_SESSION['flash']['danger']='One of the selected cities could not be found.'; header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit; }
        if ($route_id) {
            $existing=db_fetch_one("SELECT id FROM routes WHERE id=?",[$route_id]);
            if (!$existing) { $_SESSION['flash']['danger']='The selected fare record no longer exists.'; header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit; }
            db_execute("UPDATE routes SET origin_city_id=?, dest_city_id=?, transport_type=?, fare_php=?, notes=? WHERE id=?",[$origin_city_id,$dest_city_id,$transport_type,$fare_php,$notes!==''?$notes:null,$route_id]);
            $_SESSION['flash']['success']="Fare updated: {$origin['name']} → {$dest['name']}.";
        } else {
            $duplicate=db_fetch_one("SELECT id FROM routes WHERE origin_city_id=? AND dest_city_id=? AND transport_type=?",[$origin_city_id,$dest_city_id,$transport_type]);
            if ($duplicate) { db_execute("UPDATE routes SET fare_php=?, notes=? WHERE id=?",[$fare_php,$notes!==''?$notes:null,$duplicate['id']]); $_SESSION['flash']['success']="Existing fare updated: {$origin['name']} → {$dest['name']}."; }
            else { db_execute("INSERT INTO routes (origin_city_id,dest_city_id,transport_type,fare_php,notes) VALUES (?,?,?,?,?)",[$origin_city_id,$dest_city_id,$transport_type,$fare_php,$notes!==''?$notes:null]); $_SESSION['flash']['success']="Fare added: {$origin['name']} → {$dest['name']}."; }
        }
        header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit;
    }

    if ($action === 'delete_route_fare') {
        $route_id=(int) input('route_id','post',0);
        if ($route_id) { db_execute("DELETE FROM routes WHERE id=?",[$route_id]); $_SESSION['flash']['success']='Transportation fare removed.'; }
        header('Location: '.APP_URL.'/pages/admin-dashboard.php#transport-fares'); exit;
    }

    if ($action === 'remove_site_logo') {
        $old_logo = trim((string)(site_settings()['logo_path'] ?? ''));
        if ($old_logo && strpos($old_logo, 'uploads/site-logo-') === 0) {
            $old_file = dirname(__DIR__) . '/' . $old_logo;
            if (is_file($old_file)) @unlink($old_file);
        }
        set_site_setting('logo_path', '');
        $_SESSION['flash']['success'] = 'Custom logo removed. The default map icon is now being used.';
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#site-settings'); exit;
    }
}

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action', 'post', '');

    if ($action === 'approve_shop' || $action === 'reject_shop') {
        $sid  = (int) input('shop_id', 'post', 0);
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            if ($action === 'approve_shop') {
                db_execute("UPDATE shops SET is_verified = 1, is_active = 1 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'shop_approved', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Approved!',
                     "\"{$shop['name']}\" is now live on iExplore Laguna and visible to tourists.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                $_SESSION['flash']['success'] = "\"{$shop['name']}\" approved.";
            } else {
                db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'shop_rejected', ?, ?, ?)",
                    [$shop['owner_id'], 'Shop Registration Rejected',
                     "\"{$shop['name']}\" was not approved. Please review your details and contact support.",
                     APP_URL . '/pages/shop-dashboard.php']
                );
                $_SESSION['flash']['danger'] = "\"{$shop['name']}\" rejected.";
            }
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#shops'); exit;
    }

    if ($action === 'approve_hotel' || $action === 'reject_hotel') {
        $hid   = (int) input('hotel_id', 'post', 0);
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            if ($action === 'approve_hotel') {
                db_execute("UPDATE hotels SET is_verified = 1, is_active = 1 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, 'hotel_approved', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Approved!',
                         "\"{$hotel['name']}\" is now live on iExplore Laguna and visible to tourists.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                $_SESSION['flash']['success'] = "\"{$hotel['name']}\" approved.";
            } else {
                db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
                if ($hotel['owner_id']) {
                    db_execute(
                        "INSERT INTO notifications (user_id, type, title, message, link)
                         VALUES (?, 'hotel_rejected', ?, ?, ?)",
                        [$hotel['owner_id'], 'Hotel Registration Rejected',
                         "\"{$hotel['name']}\" was not approved. Please review your details and contact support.",
                         APP_URL . '/pages/hotel-dashboard.php']
                    );
                }
                $_SESSION['flash']['danger'] = "\"{$hotel['name']}\" rejected.";
            }
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#hotels'); exit;
    }

    if ($action === 'revoke_shop') {
        $sid  = (int) input('shop_id', 'post', 0);
        $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$sid]);
        if ($shop) {
            db_execute("UPDATE shops SET is_verified = 0, is_active = 0 WHERE id = ?", [$sid]);
            db_execute(
                "INSERT INTO notifications (user_id, type, title, message, link)
                 VALUES (?, 'shop_revoked', ?, ?, ?)",
                [$shop['owner_id'], 'Shop Unpublished',
                 "\"{$shop['name']}\" has been taken offline by an admin and is no longer visible to tourists.",
                 APP_URL . '/pages/shop-dashboard.php']
            );
            $_SESSION['flash']['danger'] = "\"{$shop['name']}\" unpublished.";
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#shops'); exit;
    }

    if ($action === 'revoke_hotel') {
        $hid   = (int) input('hotel_id', 'post', 0);
        $hotel = db_fetch_one("SELECT * FROM hotels WHERE id = ?", [$hid]);
        if ($hotel) {
            db_execute("UPDATE hotels SET is_verified = 0, is_active = 0 WHERE id = ?", [$hid]);
            if ($hotel['owner_id']) {
                db_execute(
                    "INSERT INTO notifications (user_id, type, title, message, link)
                     VALUES (?, 'hotel_revoked', ?, ?, ?)",
                    [$hotel['owner_id'], 'Hotel Unpublished',
                     "\"{$hotel['name']}\" has been taken offline by an admin and is no longer visible to tourists.",
                     APP_URL . '/pages/hotel-dashboard.php']
                );
            }
            $_SESSION['flash']['danger'] = "\"{$hotel['name']}\" unpublished.";
        }
        header('Location: ' . APP_URL . '/pages/admin-dashboard.php#hotels'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────
$pending_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM shops s
     JOIN users u  ON s.owner_id = u.id
     JOIN cities c ON s.city_id  = c.id
     WHERE s.is_verified = 0
     ORDER BY s.created_at DESC"
);

$pending_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM hotels h
     LEFT JOIN users u ON h.owner_id = u.id
     JOIN cities c      ON h.city_id  = c.id
     WHERE h.is_verified = 0
     ORDER BY h.id DESC"
);

$verified_shops = db_fetch_all(
    "SELECT s.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM shops s
     JOIN users u  ON s.owner_id = u.id
     JOIN cities c ON s.city_id  = c.id
     WHERE s.is_verified = 1
     ORDER BY s.name"
);

$verified_hotels = db_fetch_all(
    "SELECT h.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, c.name AS city_name
     FROM hotels h
     LEFT JOIN users u ON h.owner_id = u.id
     JOIN cities c      ON h.city_id  = c.id
     WHERE h.is_verified = 1
     ORDER BY h.name"
);

$verified_shops_count  = count($verified_shops);
$verified_hotels_count = count($verified_hotels);
$total_users            = db_fetch_one("SELECT COUNT(*) n FROM users")['n'] ?? 0;
$site_settings = site_settings();

$cities = db_fetch_all("SELECT id,name FROM cities ORDER BY name");
$route_fares = db_fetch_all("SELECT r.id,r.origin_city_id,r.dest_city_id,r.transport_type,r.fare_php,r.notes,o.name AS origin_name,d.name AS dest_name FROM routes r JOIN cities o ON r.origin_city_id=o.id JOIN cities d ON r.dest_city_id=d.id ORDER BY o.name,d.name,r.fare_php ASC");
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show m-0" role="alert">
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>

<section class="py-3" style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon-mid));color:#fff">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-shield-check fs-2" style="color:var(--sand-dark)"></i>
      <div>
        <h1 class="mb-0 fs-3" style="font-family:'Playfair Display',serif">Admin Dashboard</h1>
        <p class="mb-0 small opacity-75">Review and approve shop &amp; hotel registrations</p>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <!-- Stats -->
  <div class="stat-strip mb-4" style="border-radius:var(--radius)">
    <div class="row g-0 text-center">
      <?php
      $stats = [
        ['bi-hourglass-split', 'Pending Shops',   count($pending_shops)],
        ['bi-hourglass-split', 'Pending Hotels',  count($pending_hotels)],
        ['bi-shop',            'Verified Shops',  $verified_shops_count],
        ['bi-building',        'Verified Hotels', $verified_hotels_count],
        ['bi-people-fill',     'Total Users',     $total_users],
      ];
      foreach ($stats as [$ico,$lbl,$val]): ?>
      <div class="col-6 col-lg">
        <div class="stat-item">
          <div class="stat-item-icon"><i class="bi <?= $ico ?>"></i></div>
          <div class="stat-num" style="font-size:1.5rem"><?= $val ?></div>
          <div class="stat-lbl"><?= $lbl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--border)">
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#site-settings" style="font-weight:600">
        <i class="bi bi-palette me-1"></i>Site Settings
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#transport-fares" style="font-weight:600">
        <i class="bi bi-signpost-2 me-1"></i>Transport &amp; Fares
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#shops" style="font-weight:600">
        <i class="bi bi-shop me-1"></i>Shops
        <?php if (count($pending_shops)): ?>
          <span class="badge rounded-pill ms-1" style="background:#c0392b;font-size:.7rem"><?= count($pending_shops) ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#hotels" style="font-weight:600">
        <i class="bi bi-building me-1"></i>Hotels
        <?php if (count($pending_hotels)): ?>
          <span class="badge rounded-pill ms-1" style="background:#c0392b;font-size:.7rem"><?= count($pending_hotels) ?></span>
        <?php endif; ?>
      </a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── SITE SETTINGS ── -->
    <div class="tab-pane fade" id="site-settings">
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="form-panel h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-image fs-4" style="color:var(--maroon-mid)"></i>
              <h4 class="mb-0">Website Logo</h4>
            </div>
            <p class="text-muted small mb-3">Upload a new logo and it will immediately replace the current logo across the website.</p>

            <?php if (!empty($site_settings['logo_path'])): ?>
              <div class="p-3 mb-3 text-center" style="border:1px solid var(--border);border-radius:var(--radius);background:#fff">
                <img src="<?= APP_URL . '/' . ltrim(e($site_settings['logo_path']), '/') ?>"
                     alt="Current website logo" style="max-height:110px;max-width:220px;object-fit:contain;margin:auto">
                <div class="small text-muted mt-2">Current logo</div>
              </div>
            <?php else: ?>
              <div class="p-4 mb-3 text-center" style="border:1px dashed var(--border);border-radius:var(--radius)">
                <i class="bi bi-map fs-1 text-muted"></i>
                <div class="small text-muted mt-2">Using the default map icon</div>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mb-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_site_settings">
              <input type="hidden" name="theme_dark" value="<?= e($site_settings['theme_dark']) ?>">
              <input type="hidden" name="theme_primary" value="<?= e($site_settings['theme_primary']) ?>">
              <input type="hidden" name="theme_light" value="<?= e($site_settings['theme_light']) ?>">
              <input type="hidden" name="theme_pale" value="<?= e($site_settings['theme_pale']) ?>">
              <input type="hidden" name="theme_accent" value="<?= e($site_settings['theme_accent']) ?>">
              <label class="form-label">Choose new logo</label>
              <input type="file" name="site_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" required>
              <div class="form-text">PNG, JPG, WEBP or SVG · maximum 2 MB</div>
              <button class="btn btn-primary-app w-100 mt-3">
                <i class="bi bi-upload me-1"></i>Upload &amp; Apply Logo
              </button>
            </form>

            <?php if (!empty($site_settings['logo_path'])): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_site_logo">
                <button class="btn btn-outline-secondary w-100" type="submit">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Use Default Logo
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="form-panel">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-palette2 fs-4" style="color:var(--maroon-mid)"></i>
              <h4 class="mb-0">Color Theme</h4>
            </div>
            <p class="text-muted small">Pick a preset or customize the colors. Changes are applied throughout the website after saving.</p>

            <div class="d-flex flex-wrap gap-2 mb-4">
              <button type="button" class="btn btn-sm btn-outline-secondary theme-preset" data-theme='{"dark":"#1a3a2a","primary":"#2d6a4f","light":"#52b788","pale":"#d8f3dc","accent":"#e9c46a"}'>Laguna Green</button>
              <button type="button" class="btn btn-sm btn-outline-secondary theme-preset" data-theme='{"dark":"#B0281C","primary":"#D9481F","light":"#FF7A45","pale":"#FFE3D2","accent":"#e9c46a"}'>Sunset Red</button>
              <button type="button" class="btn btn-sm btn-outline-secondary theme-preset" data-theme='{"dark":"#073b4c","primary":"#118ab2","light":"#06d6a0","pale":"#d8f3dc","accent":"#ffd166"}'>Laguna Blue</button>
              <button type="button" class="btn btn-sm btn-outline-secondary theme-preset" data-theme='{"dark":"#3b1f5f","primary":"#6c3aa3","light":"#9b72cf","pale":"#eee3ff","accent":"#f2c94c"}'>Royal Purple</button>
            </div>

            <form method="post" id="siteThemeForm">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_site_settings">
              <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

              <div class="row g-3">
                <?php
                $theme_fields = [
                  ['theme_dark', 'Dark / Header', $site_settings['theme_dark']],
                  ['theme_primary', 'Primary', $site_settings['theme_primary']],
                  ['theme_light', 'Light / Hover', $site_settings['theme_light']],
                  ['theme_pale', 'Pale / Background', $site_settings['theme_pale']],
                  ['theme_accent', 'Accent', $site_settings['theme_accent']],
                ];
                foreach ($theme_fields as [$name,$label,$value]): ?>
                  <div class="col-sm-6">
                    <label class="form-label"><?= e($label) ?></label>
                    <div class="d-flex align-items-center gap-2">
                      <input type="color" name="<?= e($name) ?>" value="<?= e($value) ?>" class="form-control form-control-color theme-color-picker" title="<?= e($label) ?>">
                      <input type="text" value="<?= e($value) ?>" class="form-control theme-hex" maxlength="7" pattern="#[0-9A-Fa-f]{6}" aria-label="<?= e($label) ?> HEX">
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-4 p-3" style="border-radius:var(--radius);background:var(--maroon-pale)">
                <div class="fw-bold mb-1">Theme preview</div>
                <div class="d-flex gap-2 flex-wrap">
                  <span class="badge" style="background:var(--maroon-dark)">Header</span>
                  <span class="badge" style="background:var(--maroon-mid)">Primary</span>
                  <span class="badge" style="background:var(--maroon-light)">Hover</span>
                  <span class="badge" style="background:var(--sand-dark);color:var(--maroon-dark)">Accent</span>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary-app">
                  <i class="bi bi-check2-circle me-1"></i>Save Color Theme
                </button>
              </form>

              <form method="post" class="d-inline" onsubmit="return confirm('Restore the default iExplore Laguna colors? Your uploaded logo will not be changed.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_site_theme">
                <button type="submit" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Default Colors
                </button>
              </form>
              </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TRANSPORT & FARES ── -->
    <div class="tab-pane fade" id="transport-fares">
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="form-panel h-100">
            <div class="d-flex align-items-start gap-3 mb-1">
              <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:42px;height:42px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--sand);color:var(--terracotta)">
                <i class="bi bi-signpost-2 fs-5"></i>
              </div>
              <div>
                <h4 class="mb-1">Transport &amp; Fares</h4>
                <p class="text-muted small mb-0">Manage the fares used by the Trip Planner for city-to-city travel legs.</p>
              </div>
            </div>

            <hr class="my-4" style="border-color:var(--border);opacity:1">

            <div class="d-flex align-items-center justify-content-between mb-3">
              <div>
                <div class="fw-semibold">Add / Update Fare</div>
                <div class="text-muted small">Stored fares replace the planner's estimated fare when a matching route is found.</div>
              </div>
              <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">Per passenger</span>
            </div>

            <form method="post" id="routeFareForm">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_route_fare">
              <input type="hidden" name="route_id" id="route_id" value="0">

              <div class="mb-3">
                <label class="form-label fw-semibold">From</label>
                <select name="origin_city_id" id="fare_origin" class="form-select" required>
                  <option value="">Select starting city</option>
                  <?php foreach ($cities as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">To</label>
                <select name="dest_city_id" id="fare_dest" class="form-select" required>
                  <option value="">Select destination city</option>
                  <?php foreach ($cities as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Transportation</label>
                <select name="transport_type" id="fare_transport" class="form-select" required>
                  <option value="jeepney">Jeepney</option>
                  <option value="bus">Bus</option>
                  <option value="tricycle">Tricycle</option>
                  <option value="fx_uv">FX / UV Express</option>
                  <option value="private_car">Private Car</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Fare per person</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" name="fare_php" id="fare_php" class="form-control" min="0" max="10000" step="0.01" required placeholder="e.g. 35">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="notes" id="fare_notes" class="form-control" rows="3" maxlength="500" placeholder="e.g. Regular passenger fare; verify current fare locally."></textarea>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary-app" type="submit">
                  <i class="bi bi-check2-circle me-1"></i><span id="fareSubmitLabel">Save Fare</span>
                </button>
                <button class="btn btn-outline-secondary" type="button" id="fareCancelEdit" style="display:none">
                  Cancel Edit
                </button>
              </div>
            </form>

            <div class="mt-4 p-3" style="background:var(--sand);border:1px solid var(--border);border-radius:var(--radius-sm)">
              <div class="fw-semibold small mb-1"><i class="bi bi-info-circle me-1"></i>How this affects the planner</div>
              <div class="small text-muted">When the planner finds a matching route and transport type, this stored fare is used. Otherwise, the planner can continue using an estimated fare.</div>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="form-panel h-100">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-1">
              <div>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-list-check fs-5" style="color:var(--terracotta)"></i>
                  <h4 class="mb-0">Saved Transportation Fares</h4>
                </div>
                <p class="text-muted small mb-0 mt-1">Review and maintain the fares currently available to the Trip Planner.</p>
              </div>
              <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">
                <span id="fare-visible-count"><?= count($route_fares) ?></span> / <?= count($route_fares) ?> shown
              </span>
            </div>

            <hr class="my-4" style="border-color:var(--border);opacity:1">

            <?php if (empty($route_fares)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-signpost fs-1 d-block mb-2"></i>
                <div class="fw-semibold">No stored fares yet</div>
                <div class="small">Add your first city-to-city fare using the form.</div>
              </div>
            <?php else: ?>
              <div class="input-group mb-3">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="fare-search" class="form-control"
                       placeholder="Search by city or transport type (e.g. &quot;Calamba&quot; or &quot;jeepney&quot;)…">
              </div>

              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Route</th>
                      <th>Transport</th>
                      <th>Fare</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="fare-table-body">
                    <?php $transport_labels=['jeepney'=>'Jeepney','bus'=>'Bus','tricycle'=>'Tricycle','fx_uv'=>'FX / UV Express','private_car'=>'Private Car']; foreach($route_fares as $r):
                      $transportLabel = $transport_labels[$r['transport_type']] ?? ucwords(str_replace('_',' ',$r['transport_type']));
                      $searchBlob = strtolower($r['origin_name'].' '.$r['dest_name'].' '.$transportLabel.' '.($r['notes']??''));
                    ?>
                      <tr class="fare-row" data-search="<?= e($searchBlob) ?>">
                        <td>
                          <div class="fw-semibold small"><?= e($r['origin_name']) ?> <span class="text-muted">→</span> <?= e($r['dest_name']) ?></div>
                          <?php if(!empty($r['notes'])): ?><div class="small text-muted mt-1"><?= e($r['notes']) ?></div><?php endif; ?>
                        </td>
                        <td>
                          <span class="badge border text-body" style="background:var(--sand);border-color:var(--border)!important">
                            <?= e($transportLabel) ?>
                          </span>
                        </td>
                        <td class="fw-bold">₱<?= number_format((float)$r['fare_php'],2) ?></td>
                        <td class="text-end text-nowrap">
                          <button type="button" class="btn btn-sm btn-outline-secondary edit-route-fare"
                            data-id="<?= (int)$r['id'] ?>"
                            data-origin="<?= (int)$r['origin_city_id'] ?>"
                            data-dest="<?= (int)$r['dest_city_id'] ?>"
                            data-transport="<?= e($r['transport_type']) ?>"
                            data-fare="<?= e((string)$r['fare_php']) ?>"
                            data-notes="<?= e((string)($r['notes']??'')) ?>">
                            <i class="bi bi-pencil me-1"></i>Edit
                          </button>
                          <form method="post" class="d-inline" onsubmit="return confirm('Remove this transportation fare? The planner will fall back to an estimated fare for this route.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_route_fare">
                            <input type="hidden" name="route_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Remove</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="text-center mt-3">
                <button type="button" id="fare-show-more" class="btn btn-outline-secondary btn-sm">
                  Show more <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <div id="fare-no-results" class="text-muted small py-3 d-none">
                  <i class="bi bi-search me-1"></i>No fares match "<span id="fare-no-results-query"></span>".
                </div>
              </div>

              <script>
              (function () {
                const PAGE_SIZE = 20;
                const rows = Array.from(document.querySelectorAll('#fare-table-body .fare-row'));
                const searchInput = document.getElementById('fare-search');
                const showMoreBtn = document.getElementById('fare-show-more');
                const visibleCountEl = document.getElementById('fare-visible-count');
                const noResultsEl = document.getElementById('fare-no-results');
                const noResultsQueryEl = document.getElementById('fare-no-results-query');
                let shownCount = PAGE_SIZE;

                function renderPaged() {
                  rows.forEach((row, i) => { row.style.display = i < shownCount ? '' : 'none'; });
                  visibleCountEl.textContent = Math.min(shownCount, rows.length);
                  showMoreBtn.classList.toggle('d-none', shownCount >= rows.length);
                  noResultsEl.classList.add('d-none');
                }

                function renderSearch(query) {
                  let matches = 0;
                  rows.forEach(row => {
                    const isMatch = row.dataset.search.includes(query);
                    row.style.display = isMatch ? '' : 'none';
                    if (isMatch) matches++;
                  });
                  visibleCountEl.textContent = matches;
                  showMoreBtn.classList.add('d-none'); // pagination doesn't apply while filtering
                  noResultsEl.classList.toggle('d-none', matches > 0);
                  noResultsQueryEl.textContent = query;
                }

                showMoreBtn.addEventListener('click', () => {
                  shownCount = Math.min(shownCount + PAGE_SIZE, rows.length);
                  renderPaged();
                });

                searchInput.addEventListener('input', () => {
                  const query = searchInput.value.trim().toLowerCase();
                  if (query) {
                    renderSearch(query);
                  } else {
                    shownCount = PAGE_SIZE;
                    renderPaged();
                  }
                });

                renderPaged();
              })();
              </script>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── PENDING SHOPS ── -->
    <div class="tab-pane fade show active" id="shops">
      <?php if (empty($pending_shops)): ?>
        <div class="text-center py-5">
          <i class="bi bi-check2-circle fs-1 text-muted d-block mb-3"></i>
          <h5>All caught up!</h5>
          <p class="text-muted">No pending shop registrations.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($pending_shops as $s): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e($s['name']) ?></span>
                  <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.72rem"><?= e(ucfirst($s['category'])) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($s['city_name']) ?><?= $s['address'] ? ' — '.e($s['address']) : '' ?></div>
                <?php if ($s['description']): ?>
                  <div class="small text-muted mb-1"><?= e($s['description']) ?></div>
                <?php endif; ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e($s['owner_name']) ?></strong>
                  &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e($s['owner_email']) ?>
                  <?php if ($s['owner_phone']): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e($s['owner_phone']) ?><?php endif; ?>
                </div>
                <div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i>Registered <?= date('M d, Y', strtotime($s['created_at'])) ?></div>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action"  value="approve_shop">
                  <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this shop registration?')"><?= csrf_field() ?>
                  <input type="hidden" name="action"  value="reject_shop">
                  <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-x-lg me-1"></i>Reject
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- ── VERIFIED SHOPS ── -->
      <div class="d-flex align-items-center gap-2 mt-5 mb-3 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--maroon-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          Verified Shops
        </h6>
        <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">
          <span id="shops-visible-count"><?= count($verified_shops) ?></span> / <?= count($verified_shops) ?> shown
        </span>
      </div>
      <?php if (empty($verified_shops)): ?>
        <p class="text-muted small">No verified shops yet.</p>
      <?php else: ?>
        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" id="shops-search" class="form-control"
                 placeholder="Search by shop name, city, or owner…">
        </div>
        <div class="d-flex flex-column gap-2" id="shops-list-body">
          <?php foreach ($verified_shops as $s):
            $shopSearchBlob = strtolower($s['name'].' '.$s['city_name'].' '.$s['owner_name'].' '.$s['category']);
          ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3 admin-list-row"
               data-search="<?= e($shopSearchBlob) ?>"
               style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold" style="font-size:.92rem"><?= e($s['name']) ?></span>
                <span class="badge" style="background:var(--maroon-pale);color:var(--maroon-dark);font-size:.68rem"><?= e(ucfirst($s['category'])) ?></span>
              </div>
              <div class="small text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($s['city_name']) ?>
                &nbsp;·&nbsp;<i class="bi bi-person-circle me-1"></i><?= e($s['owner_name']) ?>
              </div>
            </div>
            <form method="POST" onsubmit="return confirm('Unpublish this shop? It will be hidden from tourists until re-approved.')" class="flex-shrink-0"><?= csrf_field() ?>
              <input type="hidden" name="action"  value="revoke_shop">
              <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
          <button type="button" id="shops-show-more" class="btn btn-outline-secondary btn-sm">
            Show more <i class="bi bi-chevron-down ms-1"></i>
          </button>
          <div id="shops-no-results" class="text-muted small py-3 d-none">
            <i class="bi bi-search me-1"></i>No shops match "<span id="shops-no-results-query"></span>".
          </div>
        </div>
        <script>
        (function () {
          const PAGE_SIZE = 15;
          const rows = Array.from(document.querySelectorAll('#shops-list-body .admin-list-row'));
          const searchInput = document.getElementById('shops-search');
          const showMoreBtn = document.getElementById('shops-show-more');
          const visibleCountEl = document.getElementById('shops-visible-count');
          const noResultsEl = document.getElementById('shops-no-results');
          const noResultsQueryEl = document.getElementById('shops-no-results-query');
          let shownCount = PAGE_SIZE;

          function renderPaged() {
            rows.forEach((row, i) => { row.style.display = i < shownCount ? '' : 'none'; });
            visibleCountEl.textContent = Math.min(shownCount, rows.length);
            showMoreBtn.classList.toggle('d-none', shownCount >= rows.length);
            noResultsEl.classList.add('d-none');
          }
          function renderSearch(query) {
            let matches = 0;
            rows.forEach(row => {
              const isMatch = row.dataset.search.includes(query);
              row.style.display = isMatch ? '' : 'none';
              if (isMatch) matches++;
            });
            visibleCountEl.textContent = matches;
            showMoreBtn.classList.add('d-none');
            noResultsEl.classList.toggle('d-none', matches > 0);
            noResultsQueryEl.textContent = query;
          }
          showMoreBtn.addEventListener('click', () => {
            shownCount = Math.min(shownCount + PAGE_SIZE, rows.length);
            renderPaged();
          });
          searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            if (query) renderSearch(query); else { shownCount = PAGE_SIZE; renderPaged(); }
          });
          renderPaged();
        })();
        </script>
      <?php endif; ?>
    </div>

    <!-- ── PENDING HOTELS ── -->
    <div class="tab-pane fade" id="hotels">
      <?php if (empty($pending_hotels)): ?>
        <div class="text-center py-5">
          <i class="bi bi-check2-circle fs-1 text-muted d-block mb-3"></i>
          <h5>All caught up!</h5>
          <p class="text-muted">No pending hotel registrations.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($pending_hotels as $h): ?>
          <div class="p-3" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius)">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="fw-bold" style="font-family:'Playfair Display',serif;font-size:1.05rem"><?= e($h['name']) ?></span>
                  <span style="color:var(--sand-dark);font-size:.8rem"><?= str_repeat('★',(int)$h['star_rating']) ?></span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($h['city_name']) ?><?= $h['address'] ? ' — '.e($h['address']) : '' ?></div>
                <?php if ($h['description']): ?>
                  <div class="small text-muted mb-1"><?= e($h['description']) ?></div>
                <?php endif; ?>
                <?php if ($h['owner_name']): ?>
                <div class="small mt-2">
                  <i class="bi bi-person-circle me-1"></i><strong><?= e($h['owner_name']) ?></strong>
                  &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i><?= e($h['owner_email']) ?>
                  <?php if ($h['owner_phone']): ?>&nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= e($h['owner_phone']) ?><?php endif; ?>
                </div>
                <?php else: ?>
                <div class="small text-muted mt-2 fst-italic">No owner account linked (legacy listing)</div>
                <?php endif; ?>
              </div>
              <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="action"   value="approve_hotel">
                  <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
                  <button class="btn btn-sm" style="background:var(--maroon-mid);color:#fff;border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-check-lg me-1"></i>Approve
                  </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reject this hotel registration?')"><?= csrf_field() ?>
                  <input type="hidden" name="action"   value="reject_hotel">
                  <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);padding:.4rem 1rem">
                    <i class="bi bi-x-lg me-1"></i>Reject
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- ── VERIFIED HOTELS ── -->
      <div class="d-flex align-items-center gap-2 mt-5 mb-3 pb-2" style="border-bottom:2px solid var(--maroon-pale)">
        <i class="bi bi-patch-check-fill" style="color:var(--maroon-mid)"></i>
        <h6 class="fw-bold mb-0" style="font-family:'Playfair Display',serif;color:var(--maroon-dark)">
          Verified Hotels
        </h6>
        <span class="badge border text-body" style="background:#fff;border-color:var(--border)!important">
          <span id="hotels-visible-count"><?= count($verified_hotels) ?></span> / <?= count($verified_hotels) ?> shown
        </span>
      </div>
      <?php if (empty($verified_hotels)): ?>
        <p class="text-muted small">No verified hotels yet.</p>
      <?php else: ?>
        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" id="hotels-search" class="form-control"
                 placeholder="Search by hotel name, city, or owner…">
        </div>
        <div class="d-flex flex-column gap-2" id="hotels-list-body">
          <?php foreach ($verified_hotels as $h):
            $hotelSearchBlob = strtolower($h['name'].' '.$h['city_name'].' '.($h['owner_name']??''));
          ?>
          <div class="d-flex align-items-center justify-content-between gap-3 p-3 admin-list-row"
               data-search="<?= e($hotelSearchBlob) ?>"
               style="background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm)">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold" style="font-size:.92rem"><?= e($h['name']) ?></span>
                <span style="color:var(--sand-dark);font-size:.75rem"><?= str_repeat('★',(int)$h['star_rating']) ?></span>
              </div>
              <div class="small text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($h['city_name']) ?>
                <?php if ($h['owner_name']): ?>
                  &nbsp;·&nbsp;<i class="bi bi-person-circle me-1"></i><?= e($h['owner_name']) ?>
                <?php else: ?>
                  &nbsp;·&nbsp;<span class="fst-italic">Legacy listing (no owner account)</span>
                <?php endif; ?>
              </div>
            </div>
            <form method="POST" onsubmit="return confirm('Unpublish this hotel? It will be hidden from tourists until re-approved.')" class="flex-shrink-0"><?= csrf_field() ?>
              <input type="hidden" name="action"   value="revoke_hotel">
              <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" style="border-radius:var(--radius-pill);font-size:.78rem;padding:.3rem .8rem">
                <i class="bi bi-eye-slash me-1"></i>Unpublish
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
          <button type="button" id="hotels-show-more" class="btn btn-outline-secondary btn-sm">
            Show more <i class="bi bi-chevron-down ms-1"></i>
          </button>
          <div id="hotels-no-results" class="text-muted small py-3 d-none">
            <i class="bi bi-search me-1"></i>No hotels match "<span id="hotels-no-results-query"></span>".
          </div>
        </div>
        <script>
        (function () {
          const PAGE_SIZE = 15;
          const rows = Array.from(document.querySelectorAll('#hotels-list-body .admin-list-row'));
          const searchInput = document.getElementById('hotels-search');
          const showMoreBtn = document.getElementById('hotels-show-more');
          const visibleCountEl = document.getElementById('hotels-visible-count');
          const noResultsEl = document.getElementById('hotels-no-results');
          const noResultsQueryEl = document.getElementById('hotels-no-results-query');
          let shownCount = PAGE_SIZE;

          function renderPaged() {
            rows.forEach((row, i) => { row.style.display = i < shownCount ? '' : 'none'; });
            visibleCountEl.textContent = Math.min(shownCount, rows.length);
            showMoreBtn.classList.toggle('d-none', shownCount >= rows.length);
            noResultsEl.classList.add('d-none');
          }
          function renderSearch(query) {
            let matches = 0;
            rows.forEach(row => {
              const isMatch = row.dataset.search.includes(query);
              row.style.display = isMatch ? '' : 'none';
              if (isMatch) matches++;
            });
            visibleCountEl.textContent = matches;
            showMoreBtn.classList.add('d-none');
            noResultsEl.classList.toggle('d-none', matches > 0);
            noResultsQueryEl.textContent = query;
          }
          showMoreBtn.addEventListener('click', () => {
            shownCount = Math.min(shownCount + PAGE_SIZE, rows.length);
            renderPaged();
          });
          searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            if (query) renderSearch(query); else { shownCount = PAGE_SIZE; renderPaged(); }
          });
          renderPaged();
        })();
        </script>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash) {
    const tab = document.querySelector(`[href="${hash}"]`);
    if (tab) new bootstrap.Tab(tab).show();
  }

  const fields = ['dark', 'primary', 'light', 'pale', 'accent'];
  const map = {
    dark: 'theme_dark',
    primary: 'theme_primary',
    light: 'theme_light',
    pale: 'theme_pale',
    accent: 'theme_accent'
  };

  document.querySelectorAll('.theme-preset').forEach(button => {
    button.addEventListener('click', () => {
      const theme = JSON.parse(button.dataset.theme);
      fields.forEach(key => {
        const color = theme[key];
        const colorInput = document.querySelector(`input[name="${map[key]}"]`);
        const hexInput = colorInput?.closest('.d-flex')?.querySelector('.theme-hex');
        if (colorInput) colorInput.value = color;
        if (hexInput) hexInput.value = color;
      });
    });
  });

  document.querySelectorAll('.theme-color-picker').forEach(colorInput => {
    colorInput.addEventListener('input', () => {
      const hexInput = colorInput.closest('.d-flex')?.querySelector('.theme-hex');
      if (hexInput) hexInput.value = colorInput.value.toUpperCase();
    });
  });

  document.querySelectorAll('.theme-hex').forEach(hexInput => {
    hexInput.addEventListener('input', () => {
      const value = hexInput.value.trim();
      const colorInput = hexInput.closest('.d-flex')?.querySelector('.theme-color-picker');
      if (colorInput && /^#[0-9A-Fa-f]{6}$/.test(value)) colorInput.value = value;
    });
  });

  document.querySelectorAll('.edit-route-fare').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('route_id').value = button.dataset.id || '0';
      document.getElementById('fare_origin').value = button.dataset.origin || '';
      document.getElementById('fare_dest').value = button.dataset.dest || '';
      document.getElementById('fare_transport').value = button.dataset.transport || 'jeepney';
      document.getElementById('fare_php').value = button.dataset.fare || '';
      document.getElementById('fare_notes').value = button.dataset.notes || '';
      document.getElementById('fareSubmitLabel').textContent = 'Update Fare';
      document.getElementById('fareCancelEdit').style.display = 'inline-block';
      document.querySelector('a[href="#transport-fares"]')?.click();
      document.getElementById('fare_origin')?.scrollIntoView({behavior:'smooth',block:'center'});
    });
  });

  document.getElementById('fareCancelEdit')?.addEventListener('click', () => {
    document.getElementById('routeFareForm').reset();
    document.getElementById('route_id').value = '0';
    document.getElementById('fareSubmitLabel').textContent = 'Save Fare';
    document.getElementById('fareCancelEdit').style.display = 'none';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
