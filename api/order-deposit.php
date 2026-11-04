<?php
// ============================================================
// IEXPLORE LAGUNA — Order deposit API
// api/order-deposit.php
//
// POST ?action=submit_proof  (multipart: order_id, proof image and/or ref)
//      the tourist uploads a payment screenshot / GCash reference number
// GET  ?action=proof&order_id=N
//      shows the payment screenshot, to the tourist, that shop's owner and admins only
// ============================================================
require_once __DIR__ . '/../includes/order_safety.php';
session_start_safe();

$user = current_user();
$action = (string) input('action', 'get', '');

// ── Private proof image (not a JSON response, so no JSON headers) ──
if ($action === 'proof') {
    if (!$user) { http_response_code(401); exit; }
    ensure_order_safety_schema();
    [$order, $role] = order_for_party((int) input('order_id', 'get', 0), $user);
    if (!$order || empty($order['deposit_proof_url'])) { http_response_code(404); exit; }
    $file = realpath(__DIR__ . '/../uploads/deposits/' . basename($order['deposit_proof_url']));
    $root = realpath(__DIR__ . '/../uploads/deposits');
    if (!$file || !$root || strpos($file, $root) !== 0 || !is_file($file)) { http_response_code(404); exit; }
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

set_api_headers();
if (!$user) json_error('Please log in.', 401);
ensure_order_safety_schema();
order_safety_sweep();

if ($action === 'submit_proof') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST method required.', 405);
    csrf_verify_header();

    [$order, $role] = order_for_party((int) input('order_id', 'post', 0), $user);
    if (!$order || $role !== 'tourist') json_error('Order not found.', 404);
    if ($order['status'] !== 'awaiting_deposit' || $order['deposit_status'] !== 'requested') {
        json_error($order['status'] === 'cancelled'
            ? 'This order was cancelled (the deposit window ended).'
            : 'This order is not waiting for a deposit right now.', 409);
    }

    $ref = trim((string) input('ref', 'post', ''));
    if ($ref !== '' && !preg_match('/^[A-Za-z0-9 \-]{4,60}$/', $ref)) json_error('The reference number should be letters and numbers only (4 to 60 characters).', 400);

    try {
        $url = handle_image_upload('proof', 'deposits');
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 400);
    }
    if (!$url && $ref === '') json_error('Upload a screenshot of your payment or enter the GCash reference number.', 400);

    // Keep the old screenshot if only a new reference number was sent
    $url = $url ?: $order['deposit_proof_url'];
    $n = db_execute(
        "UPDATE orders SET deposit_status = 'proof_submitted', deposit_proof_url = ?, deposit_ref = ?, deposit_note = NULL
          WHERE id = ? AND status = 'awaiting_deposit' AND deposit_status = 'requested'",
        [$url, $ref !== '' ? $ref : $order['deposit_ref'], $order['id']]
    );
    if (!$n) json_error('This order was just updated. Please refresh and try again.', 409);

    os_system_message((int) $order['id'], 'The customer sent proof of the ' . os_peso((float) $order['deposit_amount']) . ' deposit' . ($ref !== '' ? " (ref {$ref})" : '') . '. Waiting for the shop to confirm.');
    os_notify((int) $order['owner_id'], 'order_deposit', 'Deposit proof received',
              "{$order['tourist_name']} sent proof of payment for order #{$order['order_number']}. Please check your GCash and confirm.",
              os_order_link_owner((int) $order['id']));
    json_ok(null, 'Sent. The shop will confirm your deposit.');
}

json_error('Unknown action.', 400);
