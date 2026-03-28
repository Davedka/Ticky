<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$params = match_route('/api/admin/terem/{szam}', request_path());
if ($params === false || request_method() !== 'PATCH') {
    json_error('Érvénytelen kérés', 405);
}

$room_code = strtoupper(trim(urldecode((string) ($params['szam'] ?? ''))));
$body = json_decode(file_get_contents('php://input') ?: '', true);

$update = [];
if (isset($body['emelet'])) {
    $update['emelet'] = is_numeric($body['emelet']) ? (int) $body['emelet'] : null;
}
if (isset($body['aktiv'])) {
    $update['aktiv'] = (bool) $body['aktiv'];
}

if (empty($update)) {
    json_error('Nincs mit frissíteni', 400);
}

$room = ticky_find_room_by_code($room_code);
if ($room === null) {
    json_error('Terem nem található: ' . $room_code, 404);
}

$ok = sb_patch('termek', [
    'id' => 'eq.' . $room['id'],
], $update);

if (!$ok) {
    json_error('Supabase frissítési hiba', 500);
}

json_response([
    'ok' => true,
    'terem' => $room_code,
    'update' => $update,
]);
