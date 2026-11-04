<?php
// ============================================================
// IEXPLORE LAGUNA — Order chat API
// api/order-chat.php
//
// Chat between the tourist and the shop owner about ONE order, plus the
// order's status buttons, so both sides can sort out details and deposits.
//
// GET  ?action=list&order_id=N&after=LAST_MESSAGE_ID  → order summary + new messages
// POST ?action=send    {order_id, body}                → send a message
// POST ?action=do      {order_id, do, reason?}         → status change (accept, cancel, ...)
// POST ?action=report  {order_id, reason}              → report a problem to the admin
// Only the tourist and the shop owner of that order (and admins, read-only) can use it.
// ============================================================
require_once __DIR__ . '/../includes/order_safety.php';
set_api_headers();
session_start_safe();

$user = current_user();
if (!$user) json_error('Please log in.', 401);

ensure_order_safety_schema();
order_safety_sweep();

$action = (string) input('action', 'get', 'list');
$body   = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_header();
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
}
$orderId = (int) ($body['order_id'] ?? input('order_id', 'get', 0));
[$order, $role] = order_for_party($orderId, $user);
if (!$order) json_error('Order not found.', 404);

/** Everything the chat window needs to draw the order header, deposit box and buttons. */
function chat_order_payload(array $o, string $role): array {
    $meta = order_status_meta($o['status']);
    $closed = in_array($o['status'], ['cancelled', 'picked_up', 'no_show'], true);
    $avail  = order_noshow_available_at($o);
    $actions = [];
    foreach (order_available_actions($role, $o) as $a) {
        $actions[] = ['key' => $a[0], 'label' => $a[1], 'style' => $a[2], 'confirm' => $a[3] ?? null, 'disabled' => $a[4] ?? null];
    }
    return [
        'id'          => (int) $o['id'],
        'number'      => $o['order_number'],
        'status'      => $o['status'],
        'status_label'=> $meta['label'],
        'status_bg'   => $meta['bg'], 'status_fg' => $meta['fg'],
        'total'       => (float) $o['total_amount'],
        'shop_name'   => $o['shop_name'],
        'tourist_name'=> $o['tourist_name'],
        'pickup_code' => $role === 'admin' ? null : $o['pickup_code'],
        'pickup'      => $o['pickup_date'] ? trim(date('D, M j', strtotime($o['pickup_date'])) . ($o['pickup_time'] ? ' ' . date('g:i A', strtotime($o['pickup_time'])) : '')) : null,
        'role'        => $role,
        'closed'      => $closed,
        'deposit'     => [
            'required' => (bool) $o['deposit_required'],
            'amount'   => (float) $o['deposit_amount'],
            'balance'  => max(0, (float) $o['total_amount'] - (in_array($o['deposit_status'], ['paid'], true) ? (float) $o['deposit_amount'] : 0)),
            'status'   => $o['deposit_status'],
            'due_ts'   => $o['deposit_due_at'] ? strtotime($o['deposit_due_at']) : null,
            'ref'      => $o['deposit_ref'],
            'has_proof'=> !empty($o['deposit_proof_url']),
            'note'     => $o['deposit_note'],
        ],
        'gcash'       => ['qr' => $o['gcash_qr_url'], 'name' => $o['gcash_name'], 'number' => $o['gcash_number']],
        'actions'     => $actions,
        'noshow_ts'   => $avail,
        'server_ts'   => time(),
    ];
}

switch ($action) {

    case 'list':
        $after = (int) input('after', 'get', 0);
        $rows  = db_fetch_all(
            "SELECT id, sender_id, kind, body, created_at FROM order_messages
              WHERE order_id = ? AND id > ? ORDER BY id ASC LIMIT 200",
            [$orderId, $after]
        );
        if ($role !== 'admin' && $rows) {
            db_execute(
                "UPDATE order_messages SET read_at = ? WHERE order_id = ? AND sender_id <> ? AND kind = 'text' AND read_at IS NULL",
                [os_now(), $orderId, $user['id']]
            );
        }
        $msgs = array_map(fn($m) => [
            'id'      => (int) $m['id'],
            'kind'    => $m['kind'],
            'mine'    => $m['kind'] === 'text' && (int) $m['sender_id'] === (int) $user['id'],
            'from'    => $m['kind'] === 'system' ? 'system' : ((int) $m['sender_id'] === (int) $order['tourist_id'] ? 'tourist' : 'owner'),
            'body'    => $m['body'],
            'time'    => date('M j, g:i A', strtotime($m['created_at'])),
        ], $rows);
        json_ok(['order' => chat_order_payload($order, $role), 'messages' => $msgs]);
        break;

    case 'send':
        if ($role === 'admin') json_error('Admins can read order chats but not send messages.', 403);
        $text = trim((string) ($body['body'] ?? ''));
        if ($text === '') json_error('Type a message first.', 400);
        if (mb_strlen($text) > 500) json_error('Messages can be up to 500 characters.', 400);
        $closedLong = in_array($order['status'], ['cancelled', 'picked_up', 'no_show'], true)
            && strtotime($order['updated_at']) < time() - 3 * 86400;
        if ($closedLong) json_error('This order is closed, so the chat is now read-only.', 403);

        $recent = (int) (db_fetch_one(
            "SELECT COUNT(*) n FROM order_messages WHERE sender_id = ? AND kind = 'text' AND created_at > ?",
            [$user['id'], date('Y-m-d H:i:s', time() - 60)]
        )['n'] ?? 0);
        if ($recent >= 15) json_error('You are sending messages too fast. Please wait a moment.', 429);

        $clean = censor_profanity($text)['text'];
        db_execute(
            "INSERT INTO order_messages (order_id, sender_id, kind, body, created_at) VALUES (?, ?, 'text', ?, ?)",
            [$orderId, $user['id'], $clean, os_now()]
        );
        $newId = (int) db_last_id();

        // One unread "new message" notification per order is enough
        $toId   = $role === 'tourist' ? (int) $order['owner_id'] : (int) $order['tourist_id'];
        $link   = $role === 'tourist' ? os_order_link_owner($orderId) : os_order_link_tourist($orderId);
        $exists = db_fetch_one(
            "SELECT 1 x FROM notifications WHERE user_id = ? AND type = 'order_message' AND link = ? AND is_read = 0 LIMIT 1",
            [$toId, $link]
        );
        if (!$exists) {
            os_notify($toId, 'order_message', 'New message about order ' . $order['order_number'],
                      ($role === 'tourist' ? $order['tourist_name'] : $order['shop_name']) . ' sent you a message.', $link);
        }
        json_ok(['id' => $newId]);
        break;

    case 'do':
        if ($role === 'admin') json_error('Admins cannot change order status.', 403);
        [$ok, $msg] = order_do($role, $orderId, (string) ($body['do'] ?? ''), ['reason' => (string) ($body['reason'] ?? '')]);
        if (!$ok) json_error($msg, 409);
        json_ok(null, $msg);
        break;

    case 'report':
        if ($role === 'admin') json_error('Admins cannot report orders.', 403);
        $reason = trim((string) ($body['reason'] ?? ''));
        if (mb_strlen($reason) < 5) json_error('Please describe the problem in a few words.', 400);
        $dup = db_fetch_one("SELECT 1 x FROM order_reports WHERE order_id = ? AND reporter_id = ? AND status = 'open'", [$orderId, $user['id']]);
        if ($dup) json_ok(null, 'You already reported this order. The admin will review it.');
        db_execute(
            "INSERT INTO order_reports (order_id, reporter_id, reason, created_at) VALUES (?, ?, ?, ?)",
            [$orderId, $user['id'], mb_substr($reason, 0, 500), os_now()]
        );
        foreach (db_fetch_all("SELECT id FROM users WHERE role = 'admin' AND is_active = 1") as $adm) {
            os_notify((int) $adm['id'], 'order_report', 'Order problem reported', "Order {$order['order_number']} was reported: " . mb_substr($reason, 0, 80), APP_URL . '/pages/admin-order-safety.php');
        }
        os_system_message($orderId, 'A problem was reported on this order. The site admin will review it.');
        json_ok(null, 'Reported. The admin will review this order.');
        break;

    default:
        json_error('Unknown action.', 400);
}
