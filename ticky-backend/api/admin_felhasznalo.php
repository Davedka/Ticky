<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

require_admin_api_request(['POST', 'PATCH', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
$base_url = SUPABASE_URL . '/rest/v1/felhasznalok';

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

function admin_fetch_user_by_id(string $id): ?array {
    $rows = sb_get('felhasznalok', [
        'id' => 'eq.' . $id,
        'select' => 'id,felhasznalonev,nev,szerep,aktiv',
        'limit' => '1',
    ], 'service');

    return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
}

function admin_active_admin_count(): int {
    $rows = sb_get('felhasznalok', [
        'szerep' => 'eq.admin',
        'aktiv' => 'eq.true',
        'select' => 'id',
        'limit' => '1000',
    ], 'service');

    return is_array($rows) ? count($rows) : 0;
}

function admin_string_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function admin_validate_display_name(?string $name): ?string {
    $trimmed = trim((string) $name);
    if ($trimmed === '') {
        return null;
    }
    if (admin_string_length($trimmed) > 120) {
        json_error('A nev legfeljebb 120 karakter lehet', 400);
    }

    return $trimmed;
}

if ($method === 'POST') {
    ticky_require_fresh_admin_auth();

    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];

    $username = trim((string) ($data['felhasznalonev'] ?? ''));
    $name = admin_validate_display_name($data['nev'] ?? null);
    $password = (string) ($data['jelszo'] ?? '');
    $role = (string) ($data['szerep'] ?? 'user');

    if ($username === '') {
        json_error('Hianyzo felhasznalonev', 400);
    }
    if (!preg_match('/^[\p{L}\p{N}._\-]{2,60}$/u', $username)) {
        json_error('Ervenytelen felhasznalonev', 400);
    }
    if (admin_string_length($password) < 10) {
        json_error('A jelszonak legalabb 10 karakter kell', 400);
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    $existing = sb_get('felhasznalok', [
        'felhasznalonev' => 'eq.' . $username,
        'select' => 'id',
    ], 'service');
    if (!empty($existing)) {
        json_error('Ez a felhasznalonev mar foglalt', 409);
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
        json_error('Supabase hiba a letrehozasnal (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true, 'felhasznalonev' => $username]);
}

$params = match_route('/api/admin/felhasznalo/{id}', $uri);
if ($params === false) {
    json_error('Hianyzo azonosito', 400);
}

$id = (string) $params['id'];
if (!preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
    json_error('Ervenytelen azonosito', 400);
}

$target_user = admin_fetch_user_by_id($id);
if (!is_array($target_user)) {
    json_error('A felhasznalo nem talalhato', 404);
}

if ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];

    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        ticky_require_fresh_admin_auth();
        if (admin_string_length((string) $data['jelszo']) < 10) {
            json_error('Legalabb 10 karakteres jelszo kell', 400);
        }
        $update['jelszo_hash'] = password_hash((string) $data['jelszo'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (array_key_exists('aktiv', $data)) {
        ticky_require_fresh_admin_auth();
        $aktiv = filter_var($data['aktiv'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($aktiv === null) {
            json_error('Ervenytelen aktiv ertek', 400);
        }
        $update['aktiv'] = $aktiv;
    }

    if (array_key_exists('nev', $data)) {
        $update['nev'] = admin_validate_display_name($data['nev']);
    }

    if (isset($data['szerep']) && in_array($data['szerep'], ['admin', 'user'], true)) {
        ticky_require_fresh_admin_auth();
        $update['szerep'] = $data['szerep'];
    }

    if (empty($update)) {
        json_error('Nincs modositando adat', 400);
    }

    $current_role = (string) ($target_user['szerep'] ?? 'user');
    $current_active = (bool) ($target_user['aktiv'] ?? false);
    $next_role = (string) ($update['szerep'] ?? $current_role);
    $next_active = array_key_exists('aktiv', $update) ? (bool) $update['aktiv'] : $current_active;

    if ($current_role === 'admin' && $current_active && ($next_role !== 'admin' || !$next_active) && admin_active_admin_count() <= 1) {
        json_error('Az utolso admin nem tilthato le vagy nem veheto el tole az admin szerep', 400);
    }

    $response = admin_user_request('PATCH', $base_url . '?id=eq.' . urlencode($id), $update);
    if ($response['status'] >= 400) {
        json_error('Frissitesi hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    ticky_require_fresh_admin_auth();

    $current_user = ticky_current_user();
    if (is_array($current_user) && (string) ($current_user['id'] ?? '') === $id) {
        json_error('A sajat felhasznalod nem torolheto', 400);
    }

    if ((string) ($target_user['szerep'] ?? '') === 'admin' && !empty($target_user['aktiv']) && admin_active_admin_count() <= 1) {
        json_error('Az utolso admin nem torolheto', 400);
    }

    $response = admin_user_request('DELETE', $base_url . '?id=eq.' . urlencode($id));
    if ($response['status'] >= 400) {
        json_error('Torlesi hiba (' . $response['status'] . ')', 500);
    }

    json_response(['ok' => true]);
}

json_error('Nem tamogatott metodus', 405);
