<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

require_admin_api_request(['GET', 'POST', 'PATCH', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
$base_url = SUPABASE_URL . '/rest/v1/szunetek';

function admin_break_request(string $method, string $url, array $body = [], bool $return_representation = true): array {
    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        $return_representation ? 'Prefer: return=representation' : 'Prefer: return=minimal',
    ];

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ];

    if (!empty($body)) {
        $options['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $context = stream_context_create($options);
    $raw = @file_get_contents($url, false, $context);

    $status = 200;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('{HTTP/\S+\s+(\d+)}', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'data' => json_decode($raw ?: '[]', true),
    ];
}

if ($method === 'GET') {
    $rows = sb_get('szunetek', [
        'select' => 'id,nev,kezdet,vege',
        'order' => 'kezdet.asc',
    ], 'service');
    json_response([
        'szunetek' => $rows,
        'count' => count($rows),
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $name = trim((string) ($data['nev'] ?? ''));
    $start = trim((string) ($data['kezdet'] ?? ''));
    $end = trim((string) ($data['vege'] ?? ''));

    if ($name === '' || $start === '' || $end === '') {
        json_error('Minden mező kötelező', 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        json_error('Érvénytelen dátum formátum', 400);
    }
    if ($start > $end) {
        json_error('A kezdő dátum nem lehet későbbi a zárónál', 400);
    }

    $response = admin_break_request('POST', $base_url, [
        'nev' => $name,
        'kezdet' => $start,
        'vege' => $end,
    ]);
    if ($response['status'] >= 400) {
        json_error('Supabase hiba (' . $response['status'] . ')', 500);
    }

    $created = is_array($response['data']) && isset($response['data'][0]) ? $response['data'][0] : $response['data'];
    json_response(['ok' => true, 'szunet' => $created]);
}

$params = match_route('/api/admin/szunet/{id}', $uri);
if ($params === false) {
    json_error('Hiányzó azonosító', 400);
}

$id = (string) $params['id'];
if (!preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
    json_error('Érvénytelen azonosító', 400);
}

if ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];

    if (array_key_exists('nev', $data)) {
        $update['nev'] = trim((string) $data['nev']);
    }
    if (array_key_exists('kezdet', $data)) {
        $update['kezdet'] = trim((string) $data['kezdet']);
    }
    if (array_key_exists('vege', $data)) {
        $update['vege'] = trim((string) $data['vege']);
    }

    if (empty($update)) {
        json_error('Nincs módosítandó adat', 400);
    }
    if (isset($update['kezdet']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $update['kezdet'])) {
        json_error('Érvénytelen kezdő dátum formátum', 400);
    }
    if (isset($update['vege']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $update['vege'])) {
        json_error('Érvénytelen záró dátum formátum', 400);
    }
    if (isset($update['nev']) && $update['nev'] === '') {
        json_error('A név nem lehet üres', 400);
    }

    $response = admin_break_request('PATCH', $base_url . '?id=eq.' . urlencode($id), $update);
    if ($response['status'] >= 400) {
        json_error('Frissítési hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $response = admin_break_request('DELETE', $base_url . '?id=eq.' . urlencode($id), [], false);
    if ($response['status'] >= 400) {
        json_error('Törlési hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

json_error('Nem támogatott metódus', 405);
