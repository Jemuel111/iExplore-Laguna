<?php
// ============================================================
// IEXPLORE LAGUNA — Itineraries API
// api/itineraries.php
// POST ?action=save    → save itinerary
// POST ?action=delete&id=N → delete itinerary
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
set_api_headers();
session_start_safe();

$action = input('action', 'get', 'save');

// Must be logged in
$user = current_user();
if (!$user) {
    json_error('Authentication required.', 401);
}

switch ($action) {

    case 'save':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) json_error('Invalid request body.', 400);

        $origin_id   = (int)($body['origin_id']   ?? 0);
        $dest_id     = (int)($body['dest_id']      ?? 0);
        $days        = (int)($body['days']         ?? 1);
        $persons     = (int)($body['persons']      ?? 1);
        $level       = $body['budget_level'] ?? 'midrange';
        $transport   = $body['transport_pref'] ?? 'any';
        $total       = (float)($body['total_budget'] ?? 0);
        $plan_json   = isset($body['itinerary_json']) ? json_encode($body['itinerary_json']) : null;
        $title       = trim($body['title'] ?? '');
        $travel_date = $body['travel_date'] ?? null;

        if (!$origin_id || !$dest_id) json_error('Origin and destination required.', 400);

        // Build default title if not provided
        if (!$title) {
            $origin_name = db_fetch_one("SELECT name FROM cities WHERE id=?",[$origin_id])['name'] ?? 'Unknown';
            $dest_name   = db_fetch_one("SELECT name FROM cities WHERE id=?",[$dest_id])['name']   ?? 'Unknown';
            $title = $origin_name . ' → ' . $dest_name . ' (' . $days . 'd)';
        }

        db_execute(
            "INSERT INTO itineraries
               (user_id, title, origin_city_id, dest_city_id, travel_date,
                num_days, num_persons, budget_level, transport_pref,
                itinerary_json, total_budget)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $user['id'], $title, $origin_id, $dest_id,
                $travel_date ?: null,
                $days, $persons, $level, $transport,
                $plan_json, $total
            ]
        );

        json_ok(['id' => db_last_id()], 'Itinerary saved successfully.');
        break;

    case 'delete':
        $id = (int) input('id', 'get');
        if (!$id) json_error('Itinerary ID required.', 400);

        $rows = db_execute(
            "DELETE FROM itineraries WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );

        if ($rows === 0) json_error('Itinerary not found or not yours.', 404);
        json_ok(null, 'Itinerary deleted.');
        break;

    default:
        json_error('Unknown action.', 400);
}