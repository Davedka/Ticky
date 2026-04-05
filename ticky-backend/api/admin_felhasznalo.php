<?php
// api/admin_felhasznalo.php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

handle_cors(['POST', 'PATCH', 'DELETE', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin', 'X-CSRF-Token']);
private_response_headers();

function _fa_check_admin(): bool {
    $admin_pw = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
    if ($admin_pw === '') return false;
 
    $expected = hash_hmac('sha256', 'ticky_admin_' . $admin_pw, $admin_pw);
    $cookie   = $_COOKIE['ticky_auth'] ?? '';
    return $cookie !== '' && hash_equals($expected, $cookie);
}


if (!_fa_check_admin()) {
    json_error('Hozzáférés megtagadva', 401);
}

if (in_array($method, ['POST', 'PATCH', 'DELETE'])) {

    $client_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($client_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_token)) {
        json_error('Biztonsági hiba (CSRF token érvénytelen)', 403);
    }

    if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
        json_error('Érvénytelen admin kérés (Hiányzó biztonsági fejléc)', 400);
    }

    if (!ticky_request_origin_is_same_host()) {
        json_error('Tiltott külső kérés (CORS/Origin mismatch)', 403);
    }
}

$sb_url_base = SUPABASE_URL . '/rest/v1/felhasznalok';
$key         = SUPABASE_SERVICE_KEY;


function _fa_sb_req(string $method, string $url, array $body = [], bool $return_rep = true): array {
    global $key;
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
    ];
    if ($return_rep) {
        $headers[] = "Prefer: return=representation";
    } else {
        $headers[] = "Prefer: return=minimal";
    }

    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 8,
            'ignore_errors' => true
        ]
    ];
    
    if (!empty($body)) {
        $opts['http']['content'] = json_encode($body);
    }

    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    
    $status = 0;
    if (isset($http_response_header[0])) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $m);
        $status = (int)$m[1];
    }

    return [
        'code' => $status, 
        'data' => json_decode($raw ?: '[]', true)
    ];
}

if ($method === 'PATCH') {
    $params = match_route('/api/admin/felhasznalo/{id}', $uri);
    if (!$params) {
        json_error('Hiányzó felhasználó azonosító', 400);
    }
    $id = $params['id'];
    

    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) {
        json_error('Érvénytelen azonosító formátum', 400);
    }

    $data   = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];


    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        if (strlen((string) $data['jelszo']) < 6) {
            json_error('A jelszónak legalább 6 karakternek kell lennie', 400);
        }
        $update['jelszo_hash'] = password_hash((string) $data['jelszo'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    

    if (isset($data['aktiv'])) {
        $update['aktiv'] = (bool) $data['aktiv'];
    }


    if (isset($data['nev'])) {
        $update['nev'] = trim((string) $data['nev']) ?: null;
    }
    

    if (isset($data['szerep']) && in_array($data['szerep'], ['user', 'admin'], true)) {
        $update['szerep'] = $data['szerep'];
    }

    if (empty($update)) {
        json_error('Nincs módosítandó adat beküldve', 400);
    }


    $res = _fa_sb_req('PATCH', $sb_url_base . '?id=eq.' . urlencode($id), $update, false);
    
    if ($res['code'] >= 400) {
        json_error('Hiba történt a Supabase frissítés során: ' . ($res['code']), 500);
    }

    json_response(['ok' => true, 'message' => 'Felhasználó sikeresen frissítve']);
}

if ($method === 'DELETE') {
    $params = match_route('/api/admin/felhasznalo/{id}', $uri);
    if (!$params) {
        json_error('Hiányzó felhasználó azonosító', 400);
    }
    $id = $params['id'];

    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) {
        json_error('Érvénytelen azonosító formátum', 400);
    }


    $res = _fa_sb_req('DELETE', $sb_url_base . '?id=eq.' . urlencode($id), [], false);
    
    if ($res['code'] >= 400) {
        json_error('Hiba történt a törlés során', 500);
    }

    json_response(['ok' => true, 'message' => 'Felhasználó törölve']);
}


json_error('Nem támogatott metódus', 405);
