<?php
// api/admin_felhasznalo.php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

handle_cors(['POST', 'PATCH', 'DELETE', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
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

if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
    json_error('Érvénytelen admin kérés', 400);
}

if (!ticky_request_origin_is_same_host()) {
    json_error('Tiltott origin', 403);
}

$sb_url_base = SUPABASE_URL . '/rest/v1/felhasznalok';
$key         = SUPABASE_SERVICE_KEY;

function _fa_sb_req(string $method, string $url, array $body = [], bool $represent = false): array {
    global $key;
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=" . ($represent ? 'representation' : 'minimal'),
    ];

    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '',
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);

    $raw  = @file_get_contents($url, false, $ctx);
    $code = 200;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $code = (int) $m[1]; break; }
    }
    return ['code' => $code, 'body' => ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : []];
}

// ── POST – felhasználó létrehozása ───────────────────────────────────
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $fnev   = trim((string) ($data['felhasznalonev'] ?? ''));
    $pw     = (string) ($data['jelszo'] ?? '');
    $nev    = trim((string) ($data['nev'] ?? ''));
    $szerep = in_array($data['szerep'] ?? '', ['user', 'admin'], true) ? $data['szerep'] : 'user';

    if ($fnev === '' || $pw === '') {
        json_error('Hiányzó felhasználónév vagy jelszó', 400);
    }
    if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $fnev)) {
        json_error('Érvénytelen felhasználónév (3-32 karakter, a-z 0-9 . _ -)', 400);
    }
    if (strlen($pw) < 6) {
        json_error('A jelszó legalább 6 karakter legyen', 400);
    }

    $hash    = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
    $payload = ['felhasznalonev' => $fnev, 'jelszo_hash' => $hash, 'szerep' => $szerep];
    if ($nev !== '') $payload['nev'] = $nev;

    $res = _fa_sb_req('POST', $sb_url_base, $payload, true);

    if ($res['code'] >= 400) {
        $msg = (string) ($res['body']['message'] ?? ($res['body']['details'] ?? 'Supabase hiba'));
        if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate') || $res['code'] === 409) {
            json_error('Ez a felhasználónév már foglalt', 409);
        }
        json_error('Létrehozási hiba: ' . $msg, 500);
    }

    $created = isset($res['body'][0]) ? $res['body'][0] : ($res['body'] ?? []);
    json_response([
        'ok'             => true,
        'id'             => $created['id'] ?? null,
        'felhasznalonev' => $fnev,
    ]);
}

// ── PATCH – jelszó / aktív / szerep módosítás ────────────────────────
if ($method === 'PATCH') {
    $parts = explode('/', trim($uri, '/'));
    $id    = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);

    $data   = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];

    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        if (strlen((string) $data['jelszo']) < 6) json_error('A jelszó legalább 6 karakter', 400);
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

    if (empty($update)) json_error('Nincs mit frissíteni', 400);

    $res = _fa_sb_req('PATCH', $sb_url_base . '?id=eq.' . urlencode($id), $update, false);
    if ($res['code'] >= 400) json_error('Frissítési hiba', 500);

    json_response(['ok' => true]);
}

// ── DELETE – felhasználó törlése ─────────────────────────────────────
if ($method === 'DELETE') {
    $parts = explode('/', trim($uri, '/'));
    $id    = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);

    $res = _fa_sb_req('DELETE', $sb_url_base . '?id=eq.' . urlencode($id), [], false);
    if ($res['code'] >= 400) json_error('Törlési hiba', 500);

    json_response(['ok' => true]);
}

json_error('Érvénytelen kérés', 405);
