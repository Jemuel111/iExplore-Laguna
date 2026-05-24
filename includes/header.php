<?php
// ============================================================
// LAKBAY LAGUNA — HTML Header Partial
// includes/header.php
// Usage: require_once 'includes/header.php';
//        Set $page_title before including.
// ============================================================
require_once __DIR__ . '/helpers.php';
session_start_safe();

$page_title  = $page_title  ?? APP_NAME;
$active_page = $active_page ?? '';
$user        = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="IExplore Laguna — Smart Travel Planner for Laguna Province">
  <title><?= e($page_title) ?> | <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Leaflet CSS — must load before any map renders -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<!-- ── Navbar ─────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="main-nav">
  <div class="container">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>">
      <span class="brand-icon"><i class="bi bi-map-fill"></i></span>
      <span class="brand-text"><span class="brand-i">i</span>Explore <span class="brand-accent">Laguna</span></span>
    </a>

    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'home'      ? 'active' : '' ?>"
             href="<?= APP_URL ?>">
            <i class="bi bi-house-door me-1"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'planner'   ? 'active' : '' ?>"
             href="<?= APP_URL ?>/pages/planner.php">
            <i class="bi bi-compass me-1"></i>Trip Planner
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'spots'     ? 'active' : '' ?>"
             href="<?= APP_URL ?>/pages/spots.php">
            <i class="bi bi-geo-alt me-1"></i>Tourist Spots
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'hotels'    ? 'active' : '' ?>"
             href="<?= APP_URL ?>/pages/hotels.php">
            <i class="bi bi-building me-1"></i>Hotels
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'explore'   ? 'active' : '' ?>"
             href="<?= APP_URL ?>/pages/explore.php">
            <i class="bi bi-basket3 me-1"></i>Explore
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_page === 'budget'    ? 'active' : '' ?>"
             href="<?= APP_URL ?>/pages/budget.php">
            <i class="bi bi-calculator me-1"></i>Budget
          </a>
        </li>

        <!-- Auth links -->
        <?php if ($user):
          $__role = $user['role'] ?? 'tourist'; ?>
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle btn btn-sm btn-outline-light px-3" href="#"
               data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i><?= e($user['name']) ?>
              <?php if ($__role === 'shop_owner'): ?>
                <span class="badge ms-1" style="background:var(--terracotta);font-size:.65rem">Shop</span>
              <?php elseif ($__role === 'hotel_owner'): ?>
                <span class="badge ms-1" style="background:#534AB7;font-size:.65rem">Hotel</span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">

              <?php if ($__role === 'shop_owner'): ?>
                <li><a class="dropdown-item fw-bold" href="<?= APP_URL ?>/pages/shop-dashboard.php">
                  <i class="bi bi-shop me-2" style="color:var(--terracotta)"></i>My Shop Dashboard</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/shop-dashboard.php#orders">
                  <i class="bi bi-receipt me-2"></i>Manage Orders</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/shop-dashboard.php#products">
                  <i class="bi bi-grid me-2"></i>Manage Products</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/shop-dashboard.php#settings">
                  <i class="bi bi-gear me-2"></i>Shop Settings</a></li>

              <?php elseif ($__role === 'hotel_owner'): ?>
                <li><a class="dropdown-item fw-bold" href="<?= APP_URL ?>/pages/hotel-dashboard.php">
                  <i class="bi bi-building me-2" style="color:#534AB7"></i>My Hotel Dashboard</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/hotel-dashboard.php#bookings">
                  <i class="bi bi-calendar-check me-2"></i>Manage Bookings</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/hotel-dashboard.php#rooms">
                  <i class="bi bi-door-open me-2"></i>Manage Rooms</a></li>

              <?php else: ?>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/itineraries.php">
                  <i class="bi bi-journal-bookmark me-2"></i>My Itineraries</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/my-orders.php">
                  <i class="bi bi-bag-check me-2"></i>My Orders</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/explore.php">
                  <i class="bi bi-basket3 me-2"></i>Explore & Plan</a></li>
              <?php endif; ?>

              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/pages/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a class="nav-link" href="<?= APP_URL ?>/pages/login.php">Login</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-accent btn-sm px-3"
               href="<?= APP_URL ?>/pages/register.php">Sign Up</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>

  </div>
</nav>
<!-- ── End Navbar ─────────────────────────────────────────── -->

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash'])): ?>
<div class="container mt-3">
  <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach;
  unset($_SESSION['flash']); ?>
</div>
<?php endif; ?>