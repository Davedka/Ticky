<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

require_admin_api_request(['POST', 'PATCH', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
$base_url = SUPABASE_URL . '/rest/v1/felhasznalok';
$key = SUPABASE_SERVICE_KEY;

function admin_user_request(string $method, string $url, array $body = [], bool $return_representation = false): array {
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

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];

    $username = trim((string) ($data['felhasznalonev'] ?? ''));
    $name = trim((string) ($data['nev'] ?? '')) ?: null;
    $password = (string) ($data['jelszo'] ?? '');
    $role = (string) ($data['szerep'] ?? 'user');

    if ($username === '') {
        json_error('Hiányzó felhasználónév', 400);
    }
    if (!preg_match('/^[\p{L}\p{N}._\-]{2,60}$/u', $username)) {
        json_error('Érvénytelen felhasználónév', 400);
    }
    if (strlen($password) < 6) {
        json_error('A jelszónak legalább 6 karakter kell', 400);
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    $existing = sb_get('felhasznalok', [
        'felhasznalonev' => 'eq.' . $username,
        'select' => 'id',
    ], 'service');
    if (!empty($existing)) {
        json_error('Ez a felhasználónév már foglalt', 409);
    }

    $payload = [
        'felhasznalonev' => $username,
        'nev' => $name,
        'jelszo_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        'szerep' => $role,
        'aktiv' => true,
    ];

    $response = admin_user_request('POST', $base_url, $payload);
    if ($response['status'] >= 400) {
        json_error('Supabase hiba a létrehozáskor (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true, 'felhasznalonev' => $username]);
}

$params = match_route('/api/admin/felhasznalo/{id}', $uri);
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

    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        if (strlen((string) $data['jelszo']) < 6) {
            json_error('Legalább 6 karakteres jelszó kell', 400);
        }
        $update['jelszo_hash'] = password_hash((string) $data['jelszo'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    if (array_key_exists('aktiv', $data)) {
        $aktiv = filter_var($data['aktiv'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($aktiv === null) {
            json_error('Érvénytelen aktiv érték', 400);
        }
        $update['aktiv'] = $aktiv;
    }
    if (array_key_exists('nev', $data)) {
        $update['nev'] = trim((string) $data['nev']) ?: null;
    }
    if (isset($data['szerep']) && in_array($data['szerep'], ['admin', 'user'], true)) {
        $update['szerep'] = $data['szerep'];
    }

    if (empty($update)) {
        json_error('Nincs módosítandó adat', 400);
    }

    $response = admin_user_request('PATCH', $base_url . '?id=eq.' . urlencode($id), $update);
    if ($response['status'] >= 400) {
        json_error('Frissítési hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $current_user = ticky_current_user();
    if (is_array($current_user) && (string) ($current_user['id'] ?? '') === $id) {
        json_error('A saját felhasználód nem törölhető', 400);
    }

    $response = admin_user_request('DELETE', $base_url . '?id=eq.' . urlencode($id));
    if ($response['status'] >= 400) {
        json_error('Törlési hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

json_error('Nem támogatott metódus', 405);
