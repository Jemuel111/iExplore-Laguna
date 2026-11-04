<?php
// ============================================================
// IEXPLORE LAGUNA — Order safety
// includes/order_safety.php
//
// Protects shop owners from fake / abandoned orders:
//   • no-show strikes with graduated penalties
//   • risk-based deposit rules (owner uploads a GCash QR)
//   • one place that decides which order status changes are legal
//   • order chat helpers
//
// Everything creates its own tables/columns the first time it runs
// (same approach as the other ensure_*() helpers), so there is no
// separate migration to run. A copy of the SQL is also provided in
// migrations/order_safety.sql for people who prefer to run it by hand.
// ============================================================
require_once __DIR__ . '/helpers.php';

/** All the tunable numbers live here. */
function order_safety_config(): array {
    return [
        'max_active_orders'  => 2,     // orders a tourist may have open at once
        'new_cash_cap'       => 300,   // ₱ — a brand-new customer may order this much without a deposit
        'large_order'        => 1000,  // ₱ — orders above this need a deposit (when the shop takes deposits)
        'deposit_window_min' => 30,    // minutes the tourist has to pay once the owner accepts
        'noshow_grace_min'   => 45,    // minutes after pickup time before the owner may report a no-show
        'strikes_deposit'    => 2,     // at this many strikes every order needs a deposit
        'strikes_block'      => 3,     // at this many strikes ordering is blocked
        'block_days'         => 30,
        'default_percent'    => 50,
    ];
}

// ── Schema (runs once, then remembers) ─────────────────────────

function order_safety_add_column(string $table, string $col, string $def): void {
    $has = db_fetch_one(
        "SELECT 1 AS x FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $col]
    );
    if (!$has) db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
}

function ensure_order_safety_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $flag = null;
    try {
        $row  = db_fetch_one("SELECT setting_value FROM site_settings WHERE setting_key = 'order_safety_schema'");
        $flag = $row['setting_value'] ?? null;
    } catch (Throwable $e) {
        ensure_site_settings_table();
    }
    if ($flag === '1') return;

    // orders.status gets two new values
    $col = db_fetch_one(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'"
    );
    if ($col && strpos($col['COLUMN_TYPE'], 'awaiting_deposit') === false) {
        db()->exec("ALTER TABLE orders MODIFY status
            ENUM('pending','awaiting_deposit','confirmed','preparing','ready','picked_up','cancelled','no_show')
            NOT NULL DEFAULT 'pending'");
    }

    foreach ([
        'deposit_required'  => "TINYINT(1) NOT NULL DEFAULT 0",
        'deposit_amount'    => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'deposit_status'    => "ENUM('none','requested','proof_submitted','paid','refund_due','refunded','forfeited') NOT NULL DEFAULT 'none'",
        'deposit_due_at'    => "DATETIME NULL",
        'deposit_proof_url' => "VARCHAR(255) NULL",
        'deposit_ref'       => "VARCHAR(60) NULL",
        'deposit_note'      => "VARCHAR(255) NULL",
        'no_show_at'        => "DATETIME NULL",
    ] as $c => $d) order_safety_add_column('orders', $c, $d);

    foreach ([
        'gcash_qr_url'    => "VARCHAR(255) NULL",
        'gcash_name'      => "VARCHAR(120) NULL",
        'gcash_number'    => "VARCHAR(20) NULL",
        'deposit_policy'  => "ENUM('none','new_customers','always') NOT NULL DEFAULT 'new_customers'",
        'deposit_percent' => "TINYINT UNSIGNED NOT NULL DEFAULT 50",
    ] as $c => $d) order_safety_add_column('shops', $c, $d);

    order_safety_add_column('users', 'no_show_count',       "INT NOT NULL DEFAULT 0");
    order_safety_add_column('users', 'order_blocked_until', "DATETIME NULL");

    db()->exec("CREATE TABLE IF NOT EXISTS order_messages (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id   INT UNSIGNED NOT NULL,
        sender_id  INT UNSIGNED NOT NULL,
        kind       ENUM('text','system') NOT NULL DEFAULT 'text',
        body       VARCHAR(1000) NOT NULL,
        read_at    DATETIME NULL,
        created_at DATETIME NOT NULL,
        KEY idx_order (order_id, id),
        KEY idx_unread (order_id, sender_id, read_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS order_reports (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id    INT UNSIGNED NOT NULL,
        reporter_id INT UNSIGNED NOT NULL,
        reason      VARCHAR(500) NOT NULL,
        status      ENUM('open','resolved') NOT NULL DEFAULT 'open',
        created_at  DATETIME NOT NULL,
        KEY idx_status (status, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Payment screenshots are private: block direct URL access, they are served by api/order-deposit.php
    $dir = __DIR__ . '/../uploads/deposits';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "# Payment proofs are private. They are served through api/order-deposit.php\nRequire all denied\n");
    }

    set_site_setting('order_safety_schema', '1');
}

// ── Small helpers ──────────────────────────────────────────────

function os_now(): string { return date('Y-m-d H:i:s'); }

function os_notify(int $userId, string $type, string $title, string $message, string $link): void {
    db_execute(
        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $link]
    );
}

/** A line in the order chat written by the system (shown centred, not attributed to anyone). */
function os_system_message(int $orderId, string $text): void {
    db_execute(
        "INSERT INTO order_messages (order_id, sender_id, kind, body, read_at, created_at) VALUES (?, 0, 'system', ?, ?, ?)",
        [$orderId, mb_substr($text, 0, 1000), os_now(), os_now()]
    );
}

function os_order_link_tourist(int $orderId): string { return APP_URL . '/pages/my-orders.php?chat=' . $orderId; }
function os_order_link_owner(int $orderId): string   { return APP_URL . '/pages/shop-dashboard.php?chat=' . $orderId . '#orders'; }

function os_peso(float $n): string { return '₱' . number_format($n, 2); }

// ── Tourist standing ───────────────────────────────────────────

function order_safety_user_state(int $userId): array {
    $cfg = order_safety_config();
    $u = db_fetch_one("SELECT no_show_count, order_blocked_until FROM users WHERE id = ?", [$userId]) ?: [];
    $strikes = (int) ($u['no_show_count'] ?? 0);
    $until   = $u['order_blocked_until'] ?? null;
    $blocked = $until && strtotime($until) > time();

    $completed = (int) (db_fetch_one("SELECT COUNT(*) n FROM orders WHERE tourist_id = ? AND status = 'picked_up'", [$userId])['n'] ?? 0);
    $active    = (int) (db_fetch_one(
        "SELECT COUNT(*) n FROM orders WHERE tourist_id = ? AND status IN ('pending','awaiting_deposit','confirmed','preparing','ready')",
        [$userId]
    )['n'] ?? 0);

    return [
        'strikes'           => $strikes,
        'blocked'           => (bool) $blocked,
        'blocked_until'     => $blocked ? $until : null,
        'completed_pickups' => $completed,
        'active_orders'     => $active,
        'always_deposit'    => $strikes >= $cfg['strikes_deposit'],
    ];
}

/** Human-readable standing for banners ("1 strike: warning" etc). */
function order_safety_standing_text(array $state): ?string {
    $cfg = order_safety_config();
    if ($state['blocked']) {
        return 'Ordering is paused on your account until ' . date('M j, Y', strtotime($state['blocked_until']))
             . ' because of repeated missed pickups. If you think this is a mistake, contact the site admin.';
    }
    if ($state['strikes'] >= $cfg['strikes_deposit']) {
        return 'You have ' . $state['strikes'] . ' missed pickups, so every new order needs a deposit. One more will pause ordering for ' . $cfg['block_days'] . ' days.';
    }
    if ($state['strikes'] === 1) {
        return 'Warning: you missed a pickup. Another missed pickup means every order will need a deposit.';
    }
    return null;
}

/**
 * Decides whether an order needs a deposit.
 * Returns ['required'=>bool, 'amount'=>float, 'reason'=>string, 'error'=>?string]
 */
function order_safety_deposit_requirement(array $shop, array $state, float $subtotal): array {
    $cfg    = order_safety_config();
    $hasQr  = !empty($shop['gcash_qr_url']);
    $policy = $shop['deposit_policy'] ?? 'new_customers';
    $pct    = max(10, min(100, (int) ($shop['deposit_percent'] ?: $cfg['default_percent'])));
    $amount = round($subtotal * $pct / 100, 2);

    $required = false; $reason = '';
    if ($state['always_deposit']) {
        $required = true;  $reason = 'Your account needs a deposit for every order because of missed pickups.';
    } elseif ($hasQr && $policy !== 'none') {
        if ($policy === 'always') {
            $required = true; $reason = 'This shop asks for a deposit on every order.';
        } elseif ($subtotal > $cfg['large_order']) {
            $required = true; $reason = 'Orders above ' . os_peso($cfg['large_order']) . ' need a deposit.';
        } elseif ($state['completed_pickups'] === 0 && $subtotal > $cfg['new_cash_cap']) {
            $required = true; $reason = 'New customers may pay cash on pickup up to ' . os_peso($cfg['new_cash_cap']) . '. Larger first orders need a deposit.';
        }
    }

    $error = null;
    if ($required && !$hasQr) {
        $error = 'Your account needs a deposit for each order, but this shop has not set up deposit payments yet. Please try another shop or contact this shop directly.';
    }
    return ['required' => $required, 'amount' => $amount, 'reason' => $reason, 'error' => $error, 'percent' => $pct];
}

// ── Auto-expiry (no cron needed: runs whenever order pages/APIs load) ──

function order_safety_sweep(): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    $now = os_now();
    $late = db_fetch_all(
        "SELECT o.id, o.order_number, o.tourist_id, s.owner_id
           FROM orders o JOIN shops s ON o.shop_id = s.id
          WHERE o.status = 'awaiting_deposit' AND o.deposit_status = 'requested'
            AND o.deposit_due_at IS NOT NULL AND o.deposit_due_at < ?
          LIMIT 50",
        [$now]
    );
    foreach ($late as $o) {
        // The WHERE re-checks the state so two requests can't both cancel it
        $n = db_execute(
            "UPDATE orders SET status = 'cancelled', cancelled_at = ?, cancel_reason = 'Deposit was not paid in time', deposit_status = 'none'
              WHERE id = ? AND status = 'awaiting_deposit' AND deposit_status = 'requested'",
            [$now, $o['id']]
        );
        if (!$n) continue;
        os_system_message((int) $o['id'], 'The deposit was not paid in time, so this order was cancelled automatically. Nothing was prepared and no penalty applies.');
        os_notify((int) $o['tourist_id'], 'order_cancelled', 'Order cancelled', "Order #{$o['order_number']} was cancelled because the deposit was not paid in time.", os_order_link_tourist((int) $o['id']));
        os_notify((int) $o['owner_id'], 'order_cancelled', 'Order cancelled', "Order #{$o['order_number']} was cancelled: the deposit was not paid in time.", os_order_link_owner((int) $o['id']));
    }
}

// ── Status changes ─────────────────────────────────────────────

/** When may the owner report a no-show? Returns a unix time, or null if the order isn't ready. */
function order_noshow_available_at(array $order): ?int {
    $cfg = order_safety_config();
    if ($order['status'] !== 'ready') return null;
    $readyAt = !empty($order['ready_at']) ? strtotime($order['ready_at']) : time();
    if (!empty($order['pickup_date'])) {
        $base = strtotime($order['pickup_date'] . ' ' . ($order['pickup_time'] ?: '18:00:00'));
        return max($base + $cfg['noshow_grace_min'] * 60, $readyAt + 15 * 60);
    }
    return $readyAt + 60 * 60;
}

/**
 * The one place that changes an order's status. $actor is 'owner' or 'tourist'.
 * Returns [bool ok, string message].
 */
function order_do(string $actor, int $orderId, string $do, array $opt = []): array {
    $cfg = order_safety_config();
    $o = db_fetch_one(
        "SELECT o.*, s.owner_id, s.name AS shop_name, u.name AS tourist_name
           FROM orders o JOIN shops s ON o.shop_id = s.id JOIN users u ON o.tourist_id = u.id
          WHERE o.id = ?", [$orderId]
    );
    if (!$o) return [false, 'Order not found.'];

    $now = os_now(); $num = $o['order_number'];
    $oid = (int) $o['id']; $tid = (int) $o['tourist_id']; $owner = (int) $o['owner_id'];
    $toT = fn($type, $t, $m) => os_notify($tid,   $type, $t, $m, os_order_link_tourist($oid));
    $toO = fn($type, $t, $m) => os_notify($owner, $type, $t, $m, os_order_link_owner($oid));
    $st  = $o['status'];

    // Run an UPDATE that only succeeds if the order is still in the state we expect
    $move = function (string $set, array $params, array $fromStatuses) use ($oid): bool {
        $in = implode(',', array_fill(0, count($fromStatuses), '?'));
        return db_execute("UPDATE orders SET {$set} WHERE id = ? AND status IN ({$in})", array_merge($params, [$oid], $fromStatuses)) > 0;
    };
    $stale = [false, 'This order was just updated. Please refresh and try again.'];

    if ($actor === 'owner') {
        switch ($do) {
            case 'accept':
                if ($st !== 'pending') return [false, 'This order is no longer waiting for acceptance.'];
                if ($o['deposit_required'] && $o['deposit_status'] !== 'paid') {
                    $due = date('Y-m-d H:i:s', time() + $cfg['deposit_window_min'] * 60);
                    if (!$move("status='awaiting_deposit', deposit_status='requested', deposit_due_at=?", [$due], ['pending'])) return $stale;
                    os_system_message($oid, 'The shop accepted your order. To start preparing it, please pay the ' . os_peso((float) $o['deposit_amount'])
                        . ' deposit using the shop\'s GCash QR within ' . $cfg['deposit_window_min'] . ' minutes, then upload your proof of payment here.');
                    $toT('order_deposit', 'Deposit needed', "Order #{$num} was accepted. Pay the " . os_peso((float) $o['deposit_amount']) . ' deposit within ' . $cfg['deposit_window_min'] . ' minutes.');
                    return [true, 'Accepted. The customer has ' . $cfg['deposit_window_min'] . ' minutes to pay the deposit.'];
                }
                if (!$move("status='confirmed', confirmed_at=?", [$now], ['pending'])) return $stale;
                $toT('order_confirmed', 'Order Confirmed!', "Your order #{$num} has been confirmed by the shop.");
                return [true, 'Order accepted.'];

            case 'decline':
                if ($st !== 'pending') return [false, 'Only new orders can be declined.'];
                if (!$move("status='cancelled', cancelled_at=?, cancel_reason='Declined by the shop'", [$now], ['pending'])) return $stale;
                $toT('order_cancelled', 'Order declined', "The shop could not accept order #{$num}.");
                return [true, 'Order declined.'];

            case 'confirm_deposit':
                if ($st !== 'awaiting_deposit') return [false, 'This order is not waiting for a deposit.'];
                if (!$move("status='confirmed', confirmed_at=?, deposit_status='paid', deposit_note=NULL", [$now], ['awaiting_deposit'])) return $stale;
                os_system_message($oid, 'The shop confirmed your deposit. Your order is confirmed.');
                $toT('order_confirmed', 'Deposit confirmed', "Your deposit for order #{$num} was received. The shop will start preparing it.");
                return [true, 'Deposit confirmed. You can start preparing.'];

            case 'reject_proof':
                if ($st !== 'awaiting_deposit' || $o['deposit_status'] !== 'proof_submitted') return [false, 'There is no proof of payment to reject.'];
                $reason = trim((string) ($opt['reason'] ?? '')) ?: 'The payment could not be verified.';
                $due = date('Y-m-d H:i:s', time() + 15 * 60);
                if (!$move("deposit_status='requested', deposit_note=?, deposit_due_at=?", [mb_substr($reason, 0, 250), $due], ['awaiting_deposit'])) return $stale;
                os_system_message($oid, 'The shop could not verify your payment: ' . $reason . ' You have 15 more minutes to send a clear proof.');
                $toT('order_deposit', 'Payment not verified', "The shop could not verify the deposit for order #{$num}: {$reason}");
                return [true, 'The customer was asked to send the proof again.'];

            case 'start_preparing':
                if ($st !== 'confirmed') return [false, 'Only confirmed orders can be prepared.'];
                if (!$move("status='preparing'", [], ['confirmed'])) return $stale;
                $toT('order_preparing', 'Order Being Prepared', "Your order #{$num} is now being prepared!");
                return [true, 'Marked as preparing.'];

            case 'mark_ready':
                if ($st !== 'preparing') return [false, 'Only orders being prepared can be marked ready.'];
                if (!$move("status='ready', ready_at=?", [$now], ['preparing'])) return $stale;
                $toT('order_ready', 'Ready for Pickup!', "Your order #{$num} is ready. Show your pickup code: {$o['pickup_code']}");
                return [true, 'Marked as ready.'];

            case 'picked_up':
                if ($st !== 'ready') return [false, 'Only ready orders can be marked as picked up.'];
                if (!$move("status='picked_up', picked_up_at=?", [$now], ['ready'])) return $stale;
                $toT('order_picked_up', 'Order Completed', "Your order #{$num} has been picked up. Thanks for ordering!");
                return [true, 'Marked as picked up.'];

            case 'no_show':
                $avail = order_noshow_available_at($o);
                if ($avail === null) return [false, 'Only ready orders can be reported as a no-show.'];
                if (time() < $avail) return [false, 'You can report a no-show after ' . date('g:i A', $avail) . ', to give the customer a fair chance to arrive.'];
                $dep = $o['deposit_status'] === 'paid' ? 'forfeited' : $o['deposit_status'];
                if (!$move("status='no_show', no_show_at=?, deposit_status=?", [$now, $dep], ['ready'])) return $stale;
                db_execute("UPDATE users SET no_show_count = no_show_count + 1 WHERE id = ?", [$tid]);
                $strikes = (int) db_fetch_one("SELECT no_show_count n FROM users WHERE id = ?", [$tid])['n'];
                $msg = "You did not pick up order #{$num}. That is strike {$strikes}.";
                if ($strikes >= $cfg['strikes_block']) {
                    db_execute("UPDATE users SET order_blocked_until = ? WHERE id = ?", [date('Y-m-d H:i:s', time() + $cfg['block_days'] * 86400), $tid]);
                    $msg .= ' Ordering is paused for ' . $cfg['block_days'] . ' days.';
                } elseif ($strikes >= $cfg['strikes_deposit']) {
                    $msg .= ' From now on every order needs a deposit.';
                } else {
                    $msg .= ' This is a warning. Another missed pickup means every order will need a deposit.';
                }
                if ($dep === 'forfeited') $msg .= ' Your deposit goes to the shop.';
                os_system_message($oid, 'The shop reported that this order was not picked up.');
                $toT('order_no_show', 'Missed pickup', $msg);
                return [true, 'Reported as a no-show. The customer received a strike.'];

            case 'owner_cancel':
                if (!in_array($st, ['awaiting_deposit', 'confirmed', 'preparing', 'ready'], true)) return [false, 'This order can no longer be cancelled.'];
                $dep = $o['deposit_status'] === 'paid' ? 'refund_due' : ($o['deposit_status'] === 'proof_submitted' ? 'refund_due' : 'none');
                if (!$move("status='cancelled', cancelled_at=?, cancel_reason='Cancelled by the shop', deposit_status=?", [$now, $dep], ['awaiting_deposit', 'confirmed', 'preparing', 'ready'])) return $stale;
                os_system_message($oid, 'The shop cancelled this order.' . ($dep === 'refund_due' ? ' Your deposit will be refunded.' : ''));
                $toT('order_cancelled', 'Order Cancelled', "Your order #{$num} was cancelled by the shop." . ($dep === 'refund_due' ? ' Your deposit will be refunded.' : ''));
                return [true, 'Order cancelled.' . ($dep === 'refund_due' ? ' Please refund the deposit and mark it as refunded.' : '')];

            case 'mark_refunded':
                if ($o['deposit_status'] !== 'refund_due') return [false, 'No refund is pending for this order.'];
                db_execute("UPDATE orders SET deposit_status='refunded' WHERE id = ? AND deposit_status='refund_due'", [$oid]);
                os_system_message($oid, 'The shop marked the deposit as refunded.');
                $toT('order_deposit', 'Deposit refunded', "The shop refunded your deposit for order #{$num}.");
                return [true, 'Marked as refunded.'];
        }
        return [false, 'Unknown action.'];
    }

    if ($actor === 'tourist' && $do === 'cancel') {
        if (!in_array($st, ['pending', 'awaiting_deposit', 'confirmed'], true)) {
            return [false, 'The shop has already started on this order. Please message the shop if you need to change it.'];
        }
        $dep = in_array($o['deposit_status'], ['paid', 'proof_submitted'], true) ? 'refund_due' : 'none';
        if (!$move("status='cancelled', cancelled_at=?, cancel_reason='Cancelled by the customer', deposit_status=?", [$now, $dep], ['pending', 'awaiting_deposit', 'confirmed'])) return $stale;
        os_system_message($oid, 'The customer cancelled this order.' . ($dep === 'refund_due' ? ' The shop will arrange the deposit refund.' : ''));
        $toO('order_cancelled', 'Order Cancelled: ' . $num, "{$o['tourist_name']} cancelled order #{$num}." . ($dep === 'refund_due' ? ' A deposit refund is due.' : ''));
        return [true, 'Order cancelled.'];
    }
    return [false, 'Unknown action.'];
}

/** Buttons the given side may press right now: [[key, label, style, confirm?, disabledReason?]] */
function order_available_actions(string $role, array $o): array {
    $st = $o['status']; $a = [];
    if ($role === 'owner') {
        if ($st === 'pending')            { $a[] = ['accept', 'Accept', 'primary']; $a[] = ['decline', 'Decline', 'quiet', 'Decline this order?']; }
        if ($st === 'awaiting_deposit')   {
            $a[] = ['confirm_deposit', 'Deposit received', 'primary'];
            if ($o['deposit_status'] === 'proof_submitted') $a[] = ['reject_proof', 'Proof not valid', 'quiet', 'ask-reason'];
            $a[] = ['owner_cancel', 'Cancel order', 'quiet', 'Cancel this order?'];
        }
        if ($st === 'confirmed')          { $a[] = ['start_preparing', 'Start preparing', 'sand']; $a[] = ['owner_cancel', 'Cancel order', 'quiet', 'Cancel this order?']; }
        if ($st === 'preparing')          { $a[] = ['mark_ready', 'Mark ready', 'primary']; $a[] = ['owner_cancel', 'Cancel order', 'quiet', 'Cancel this order?']; }
        if ($st === 'ready') {
            $a[] = ['picked_up', 'Mark picked up', 'primary'];
            $avail = order_noshow_available_at($o);
            $a[] = ['no_show', 'Customer did not show up', 'danger', 'Report this as a no-show? The customer gets a strike' . ($o['deposit_status'] === 'paid' ? ' and the deposit is kept by the shop.' : '.'),
                    ($avail && time() < $avail) ? 'Available after ' . date('g:i A', $avail) : null];
        }
        if ($o['deposit_status'] === 'refund_due') $a[] = ['mark_refunded', 'Mark deposit refunded', 'sand'];
    } elseif ($role === 'tourist') {
        if (in_array($st, ['pending', 'awaiting_deposit', 'confirmed'], true)) $a[] = ['cancel', 'Cancel order', 'quiet', 'Cancel this order?'];
    }
    return $a;
}

/** Which side of an order is this user? Returns [order row, 'tourist'|'owner'|'admin'] or [null, null]. */
function order_for_party(int $orderId, ?array $user): array {
    if (!$user) return [null, null];
    $o = db_fetch_one(
        "SELECT o.*, s.owner_id, s.name AS shop_name, s.gcash_qr_url, s.gcash_name, s.gcash_number,
                u.name AS tourist_name
           FROM orders o JOIN shops s ON o.shop_id = s.id JOIN users u ON o.tourist_id = u.id
          WHERE o.id = ?", [$orderId]
    );
    if (!$o) return [null, null];
    if ((int) $o['tourist_id'] === (int) $user['id']) return [$o, 'tourist'];
    if ((int) $o['owner_id']   === (int) $user['id']) return [$o, 'owner'];
    if (($user['role'] ?? '') === 'admin')            return [$o, 'admin'];
    return [null, null];
}
