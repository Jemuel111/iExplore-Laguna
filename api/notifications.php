<?php
// ============================================================
// IEXPLORE LAGUNA — Notifications API
// api/notifications.php
// GET  ?action=list           → recent notifications + unread count
// POST ?action=mark_read      → mark one notification as read
// POST ?action=mark_all_read  → mark all of the user's notifications as read
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();
session_start_safe();

if (!is_logged_in()) {
    json_error('Not logged in.', 401);
}

$user_id = (int) (current_user()['id'] ?? 0);
$action  = input('action', 'get', 'list');

switch ($action) {

    case 'list':
        $notifications = db_fetch_all(
            "SELECT id, type, title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 15",
            [$user_id]
        );
        $unread_count = (int) (db_fetch_one(
            "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0",
            [$user_id]
        )['c'] ?? 0);

        json_ok([
            'notifications' => $notifications,
            'unread_count'  => $unread_count,
        ]);
        break;

    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST method required.', 405);
        csrf_verify_header();
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = (int) ($data['id'] ?? 0);
        if ($id) {
            db_execute("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$id, $user_id]);
        }
        json_ok(null, 'Marked as read.');
        break;

    case 'mark_all_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST method required.', 405);
        csrf_verify_header();
        db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$user_id]);
        json_ok(null, 'All marked as read.');
        break;

    default:
        json_error('Unknown action.', 400);
}
