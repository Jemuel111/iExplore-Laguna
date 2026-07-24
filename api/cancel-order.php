<?php
// ============================================================
// iEXPLORE LAGUNA — Cancel Order
// api/cancel-order.php  POST → tourist cancels their own pending order
// Plain form POST + redirect (matches pages/my-orders.php form)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/pages/my-orders.php'); exit;
}

csrf_verify();

$order_id = (int) input('order_id', 'post', 0);

$order = db_fetch_one(
    "SELECT * FROM orders WHERE id = ? AND tourist_id = ?",
    [$order_id, $u['id']]
);

if ($order && $order['status'] === 'pending') {
    db_execute(
        "UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?",
        [$order_id]
    );

    // Notify shop owner
    $shop = db_fetch_one("SELECT * FROM shops WHERE id = ?", [$order['shop_id']]);
    if ($shop) {
        db_execute(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, 'order_cancelled', ?, ?, ?)",
            [
                $shop['owner_id'],
                '❌ Order Cancelled: ' . $order['order_number'],
                "{$u['name']} cancelled order #{$order['order_number']}.",
                APP_URL . '/pages/shop-dashboard.php#orders'
            ]
        );
    }

    $_SESSION['flash']['success'] = 'Order cancelled.';
} else {
    $_SESSION['flash']['danger'] = 'This order can no longer be cancelled.';
}

header('Location: ' . APP_URL . '/pages/my-orders.php'); exit;
