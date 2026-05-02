<?php
// ============================================================
// LAKBAY LAGUNA — Database Connection Test
// test_db.php
// DELETE this file before going to production!
// ============================================================
require_once 'includes/db.php';

echo '<pre style="font-family:monospace;background:#1c1c1e;color:#52b788;padding:2rem;margin:0">';
echo "LAKBAY LAGUNA — Database Connection Test\n";
echo str_repeat('─', 50) . "\n\n";

try {
    $pdo = db();
    echo "✅ Database connected successfully!\n\n";

    // Test each table
    $tables = ['cities','tourist_spots','hotels','routes','budget_estimates','itineraries','users'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo "  📋 {$table}: {$count} row(s)\n";
    }

    echo "\n✅ All tables verified.\n";
    echo "\n🗺️  Sample cities:\n";
    $cities = $pdo->query("SELECT name, latitude, longitude FROM cities LIMIT 5")->fetchAll();
    foreach ($cities as $c) {
        echo "  • {$c['name']} ({$c['latitude']}, {$c['longitude']})\n";
    }

    echo "\n📍 Sample tourist spots:\n";
    $spots = $pdo->query("SELECT name, entrance_fee, rating FROM tourist_spots LIMIT 5")->fetchAll();
    foreach ($spots as $s) {
        echo "  • {$s['name']} — ₱{$s['entrance_fee']} — ★{$s['rating']}\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('─', 50) . "\n";
echo "⚠️  DELETE this file (test_db.php) before deployment!\n";
echo '</pre>';
