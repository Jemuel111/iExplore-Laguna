<?php
// ============================================================
// IEXPLORE LAGUNA — Dynamic XML Sitemap
// /sitemap.php  (linked from /robots.txt)
//
// Lists the static public pages plus every active spot, verified
// hotel, verified shop, and active package straight from the DB —
// so new listings show up here automatically, with nothing to
// regenerate by hand.
// ============================================================
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');

/** Escape a URL for safe inclusion inside <loc> (mainly the & in ?id=..&x=..). */
function sitemap_url(string $path): string {
    return e(APP_URL . $path);
}

$urls = [];

// Static, public, indexable pages.
foreach ([
    '/index.php'               => '1.0',
    '/pages/spots.php'         => '0.9',
    '/pages/hotels.php'        => '0.9',
    '/pages/shops.php'         => '0.8',
    '/pages/packages.php'      => '0.8',
    '/pages/explore.php'       => '0.7',
    '/pages/privacy-policy.php'    => '0.3',
    '/pages/terms-of-service.php'  => '0.3',
] as $path => $priority) {
    $urls[] = ['loc' => $path, 'priority' => $priority];
}

// Tourist spots
foreach (db_fetch_all("SELECT id FROM tourist_spots WHERE is_active = 1") as $row) {
    $urls[] = ['loc' => '/pages/spot-detail.php?id=' . $row['id'], 'priority' => '0.8'];
}

// Hotels
foreach (db_fetch_all("SELECT id FROM hotels WHERE is_active = 1 AND is_verified = 1") as $row) {
    $urls[] = ['loc' => '/pages/hotel.php?id=' . $row['id'], 'priority' => '0.7'];
}

// Shops
foreach (db_fetch_all("SELECT id FROM shops WHERE is_active = 1 AND is_verified = 1") as $row) {
    $urls[] = ['loc' => '/pages/shop.php?id=' . $row['id'], 'priority' => '0.6'];
}

// Packages
foreach (db_fetch_all("SELECT id FROM packages WHERE is_active = 1") as $row) {
    $urls[] = ['loc' => '/pages/package.php?id=' . $row['id'], 'priority' => '0.7'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= sitemap_url($u['loc']) ?></loc>
    <priority><?= e($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
