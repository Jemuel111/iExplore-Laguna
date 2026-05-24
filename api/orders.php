<?php
// ============================================================
// iEXPLORE LAGUNA — Orders API
// api/orders.php  POST → place order
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Login required']);
    exit;
}

$u    = current_user();
$data = json_decode(file_get_contents('php://input'), true);

$shop_id        = (int)   ($data['shop_id']        ?? 0);
$items          = (array) ($data['items']          ?? []);
$pickup_date    =          $data['pickup_date']    ?? null;
$pickup_time    =          $data['pickup_time']    ?? null;
$notes          =          $data['notes']          ?? '';
$payment_method =          $data['payment_method'] ?? 'cash_on_pickup';

// Validate
if (!$shop_id || empty($items)) {
    echo json_encode(['success'=>false,'message'=>'Missing shop or items']);
    exit;
}

$shop = db_fetch_one("SELECT * FROM shops WHERE id = ? AND is_active = 1", [$shop_id]);
if (!$shop) {
    echo json_encode(['success'=>false,'message'=>'Shop not found']);
    exit;
}

// Validate items and calculate total
$line_items = [];
$subtotal   = 0;

foreach ($items as $item) {
    $pid = (int) ($item['product_id'] ?? 0);
    $qty = (int) ($item['qty']        ?? 0);
    if ($pid < 1 || $qty < 1) continue;

    $product = db_fetch_one(
        "SELECT * FROM shop_products WHERE id = ? AND shop_id = ? AND is_available = 1",
        [$pid, $shop_id]
    );
    if (!$product) continue;

    $line_subtotal = round($product['price'] * $qty, 2);
    $subtotal     += $line_subtotal;
    $line_items[]  = [
        'product_id'   => $pid,
        'product_name' => $product['name'],
        'unit_price'   => $product['price'],
        'quantity'     => $qty,
        'subtotal'     => $line_subtotal,
    ];
}

if (empty($line_items)) {
    echo json_encode(['success'=>false,'message'=>'No valid products']);
    exit;
}

// Generate order number and pickup code
$order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
$pickup_code  = strtoupper(substr(md5(uniqid()), 0, 6));

// Insert order
db_execute(
    "INSERT INTO orders
       (order_number, tourist_id, shop_id, status, subtotal, total_amount,
        special_notes, pickup_date, pickup_time, pickup_code)
     VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)",
    [
        $order_number, $u['id'], $shop_id, $subtotal, $subtotal,
        $notes ?: null,
        $pickup_date ?: null,
        $pickup_time ?: null,
        $pickup_code,
    ]
);

$order_id = db_last_id();

// Insert line items
foreach ($line_items as $li) {
    db_execute(
        "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$order_id, $li['product_id'], $li['product_name'], $li['unit_price'], $li['quantity'], $li['subtotal']]
    );
}

// Record payment record (pending)
$allowed_methods = ['gcash','maya','credit_card','debit_card','bank_transfer','cash_on_pickup','cash_on_checkin'];
$method = in_array($payment_method, $allowed_methods) ? $payment_method : 'cash_on_pickup';

db_execute(
    "INSERT INTO payments (reference_type, reference_id, payer_id, method, amount, status)
     VALUES ('order', ?, ?, ?, ?, 'pending')",
    [$order_id, $u['id'], $method, $subtotal]
);

// Notify shop owner
db_execute(
    "INSERT INTO notifications (user_id, type, title, message, link)
     VALUES (?, 'order_placed', ?, ?, ?)",
    [
        $shop['owner_id'],
        '🛍️ New Order: ' . $order_number,
        "You have a new order from {$u['name']} — ₱" . number_format($subtotal, 2),
        APP_URL . '/pages/shop-dashboard.php#orders'
    ]
);

// Notify tourist
db_execute(
    "INSERT INTO notifications (user_id, type, title, message, link)
     VALUES (?, 'order_placed', ?, ?, ?)",
    [
        $u['id'],
        '✅ Order Placed! ' . $order_number,
        "Your order at {$shop['name']} has been placed. Pickup code: {$pickup_code}",
        APP_URL . '/pages/my-orders.php'
    ]
);

echo json_encode([
    'success'      => true,
    'order_number' => $order_number,
    'pickup_code'  => $pickup_code,
    'total'        => $subtotal,
]);
