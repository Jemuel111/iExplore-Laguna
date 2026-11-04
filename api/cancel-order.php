<?php
// ============================================================
// iEXPLORE LAGUNA — Cancel Order
// api/cancel-order.php  POST → tourist cancels their own order
// Allowed while the shop has not started preparing it. If a deposit
// was already paid, the shop is told a refund is due.
// Plain form POST + redirect (matches pages/my-orders.php form)
// ============================================================
require_once __DIR__ . '/../includes/order_safety.php';

if (!is_logged_in()) { header('Location: ' . APP_URL . '/pages/login.php'); exit; }
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/pages/my-orders.php'); exit;
}

csrf_verify();
ensure_order_safety_schema();

$order_id = (int) input('order_id', 'post', 0);
$order = db_fetch_one("SELECT id FROM orders WHERE id = ? AND tourist_id = ?", [$order_id, $u['id']]);

if ($order) {
    [$ok, $msg] = order_do('tourist', $order_id, 'cancel');
    $_SESSION['flash'][$ok ? 'success' : 'danger'] = $msg;
} else {
    $_SESSION['flash']['danger'] = 'Order not found.';
}

header('Location: ' . APP_URL . '/pages/my-orders.php'); exit;
