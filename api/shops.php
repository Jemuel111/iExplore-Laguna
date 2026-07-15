<?php
// ============================================================
// iEXPLORE LAGUNA — Shops API
// api/shops.php
// POST ?action=review → submit a shop review (must have a picked-up order)
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();

$action = input('action', 'get', '');

switch ($action) {

    case 'review':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('POST method required.', 405);
        }

        session_start_safe();
        if (!is_logged_in()) {
            json_error('You must be logged in to submit a review.', 401);
        }

        $u    = current_user();
        $data = json_decode(file_get_contents('php://input'), true);

        $shop_id  = (int) ($data['shop_id']  ?? 0);
        $order_id = (int) ($data['order_id'] ?? 0);
        $rating   = (int) ($data['rating']   ?? 0);
        $comment  = trim(  $data['comment']  ?? '');

        if (!$shop_id || !$order_id || $rating < 1 || $rating > 5) {
            json_error('Invalid shop, order, or rating (1–5 required).', 400);
        }

        // The order must belong to this tourist, be for this shop, and be picked up
        $order = db_fetch_one(
            "SELECT id FROM orders
             WHERE id = ? AND shop_id = ? AND tourist_id = ? AND status = 'picked_up'",
            [$order_id, $shop_id, $u['id']]
        );
        if (!$order) {
            json_error('You can only review shops after your order has been picked up.', 403);
        }

        // One review per order
        $existing = db_fetch_one(
            "SELECT id FROM shop_reviews WHERE order_id = ? AND tourist_id = ?",
            [$order_id, $u['id']]
        );
        if ($existing) {
            json_error('You have already reviewed this order.', 409);
        }

        db_execute(
            "INSERT INTO shop_reviews (shop_id, order_id, tourist_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)",
            [$shop_id, $order_id, $u['id'], $rating, $comment ?: null]
        );

        // Notify the shop owner
        $shop = db_fetch_one("SELECT owner_id, name FROM shops WHERE id = ?", [$shop_id]);
        if ($shop && $shop['owner_id']) {
            db_execute(
                "INSERT INTO notifications (user_id, type, title, message, link)
                 VALUES (?, 'shop_reviewed', ?, ?, ?)",
                [
                    $shop['owner_id'],
                    '⭐ New Review for ' . $shop['name'],
                    "{$u['name']} left a {$rating}-star review.",
                    APP_URL . '/pages/shop.php?id=' . $shop_id
                ]
            );
        }

        json_ok(null, 'Review submitted successfully!');
        break;

    default:
        json_error('Unknown action.', 400);
}
