<?php
// api/admin_felhasznalo.php
// GET    /api/admin/felhasznalo        – (nem használt, ld. admin_felhasznalok.php)

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

handle_cors(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
private_response_headers();

// ── Auth ─────────────────────────────────────────────────────────────
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
    if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
        json_error('Érvénytelen admin kérés', 400);
    }
    if (!ticky_request_origin_is_same_host()) {
        json_error('Tiltott külső kérés', 403);
    }
}

$sb_url_base = SUPABASE_URL . '/rest/v1/felhasznalok';
$key         = SUPABASE_SERVICE_KEY;

function _fa_sb_req(string $method, string $url, array $body = [], bool $return_rep = false): array {
    global $key;
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        $return_rep ? "Prefer: return=representation" : "Prefer: return=minimal",
    ];

    $opts = ['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'timeout'       => 8,
        'ignore_errors' => true,
    ]];

    if (!empty($body)) {
        $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $ctx  = stream_context_create($opts);
    $raw  = @file_get_contents($url, false, $ctx);

    $code = 200;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('{HTTP/\S+\s+(\d+)}', $h, $m)) { $code = (int)$m[1]; break; }
    }

    return ['code' => $code, 'data' => json_decode($raw ?: '[]', true)];
}

// ── POST – Új felhasználó ─────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];

    $fnev   = trim((string) ($data['felhasznalonev'] ?? ''));
    $nev    = trim((string) ($data['nev'] ?? '')) ?: null;
    $jelszo = (string) ($data['jelszo'] ?? '');
    $szerep = (string) ($data['szerep'] ?? 'admin');

    if ($fnev === '') json_error('Hiányzó felhasználónév', 400);
    if (strlen($jelszo) < 6) json_error('A jelszónak legalább 6 karakter kell', 400);
    if (!in_array($szerep, ['admin', 'user'], true)) $szerep = 'admin';

    // Csak betű, szám, pont, kötőjel, alulvonás
    if (!preg_match('/^[\p{L}\p{N}._\-]{2,60}$/u', $fnev)) {
        json_error('Érvénytelen felhasználónév (2–60 karakter, betű/szám/pont/kötőjel)', 400);
    }

    // Egyediség ellenőrzés
    $check = _fa_sb_req('GET', $sb_url_base . '?felhasznalonev=eq.' . urlencode($fnev) . '&select=id');
    if (!empty($check['data'])) {
        json_error('Ez a felhasználónév már foglalt', 409);
    }

    $hash = password_hash($jelszo, PASSWORD_BCRYPT, ['cost' => 12]);
    $insert = [
        'felhasznalonev' => $fnev,
        'nev'            => $nev,
        'jelszo_hash'    => $hash,
        'szerep'         => $szerep,
        'aktiv'          => true,
    ];

    $res = _fa_sb_req('POST', $sb_url_base, $insert, false);
    if ($res['code'] >= 400) {
        json_error('Supabase hiba a létrehozáskor (' . $res['code'] . ')', 500);
    }

    json_response(['ok' => true, 'felhasznalonev' => $fnev]);
}

// ── PATCH – Módosítás ─────────────────────────────────────────────────
if ($method === 'PATCH') {
    $params = match_route('/api/admin/felhasznalo/{id}', $uri);
    if (!$params) json_error('Hiányzó azonosító', 400);
    $id = $params['id'];
    if (!preg_match('/^[0-9a-f\-]{36}$/i', $id)) json_error('Érvénytelen azonosító', 400);

    $data   = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];

    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        if (strlen((string)$data['jelszo']) < 6) json_error('Legalább 6 karakter', 400);
        $update['jelszo_hash'] = password_hash((string)$data['jelszo'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    if (isset($data['aktiv']))  $update['aktiv']  = (bool)$data['aktiv'];
    if (isset($data['nev']))    $update['nev']    = trim((string)$data['nev']) ?: null;
    if (isset($data['szerep']) && in_array($data['szerep'], ['user','admin'], true)) {
        $update['szerep'] = $data['szerep'];
    }

    if (empty($update)) json_error('Nincs módosítandó adat', 400);

    $res = _fa_sb_req('PATCH', $sb_url_base . '?id=eq.' . urlencode($id), $update, false);
    if ($res['code'] >= 400) json_error('Frissítési hiba (' . $res['code'] . ')', 500);

    json_response(['ok' => true]);
}

// ── DELETE – Törlés ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    $params = match_route('/api/admin/felhasznalo/{id}', $uri);
    if (!$params) json_error('Hiányzó azonosító', 400);
    $id = $params['id'];
    if (!preg_match('/^[0-9a-f\-]{36}$/i', $id)) json_error('Érvénytelen azonosító', 400);

    $res = _fa_sb_req('DELETE', $sb_url_base . '?id=eq.' . urlencode($id), [], false);
    if ($res['code'] >= 400) json_error('Törlési hiba (' . $res['code'] . ')', 500);

    json_response(['ok' => true]);
}

json_error('Nem támogatott metódus', 405);
