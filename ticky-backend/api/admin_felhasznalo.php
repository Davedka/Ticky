<?php
// api/admin_felhasznalo.php
// POST   /api/admin/felhasznalo        – új felhasználó létrehozása
// PATCH  /api/admin/felhasznalo/{id}   – jelszó/aktiv módosítás
// DELETE /api/admin/felhasznalo/{id}   – törlés

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

handle_cors(['POST', 'PATCH', 'DELETE', 'OPTIONS'], ['Content-Type']);
private_response_headers();

// Admin auth ellenőrzés
if (!admin_is_authenticated()) {
    json_error('Hozzáférés megtagadva', 401);
}

$sb_url_base = SUPABASE_URL . '/rest/v1/felhasznalok';
$key = SUPABASE_SERVICE_KEY;

function sb_request(string $method, string $url, array $body = [], string $prefer = ''): array {
    global $key;
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=representation",
    ];
    if ($prefer !== '') $headers[] = "Prefer: $prefer";

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

    return ['code' => $code, 'body' => $raw !== false ? (json_decode($raw, true) ?? []) : []];
}

// ── POST – felhasználó létrehozása ──────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $fnev = trim((string) ($data['felhasznalonev'] ?? ''));
    $pw   = (string) ($data['jelszo'] ?? '');
    $nev  = trim((string) ($data['nev'] ?? ''));
    $szerep = in_array($data['szerep'] ?? '', ['user', 'admin'], true) ? $data['szerep'] : 'user';

    if ($fnev === '' || $pw === '') {
        json_error('Hiányzó felhasználónév vagy jelszó', 400);
    }
    if (strlen($fnev) < 3 || !preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $fnev)) {
        json_error('Érvénytelen felhasználónév (3-32 karakter, a-z 0-9 . _ -)', 400);
    }
    if (strlen($pw) < 6) {
        json_error('A jelszó legalább 6 karakter legyen', 400);
    }

    $hash = password_hash($pw, PASSWORD_BCRYPT);

    $payload = ['felhasznalonev' => $fnev, 'jelszo_hash' => $hash, 'szerep' => $szerep];
    if ($nev !== '') $payload['nev'] = $nev;

    $res = sb_request('POST', $sb_url_base, $payload, 'return=representation');

    if ($res['code'] >= 400) {
        $msg = isset($res['body']['message']) ? $res['body']['message'] : 'Supabase hiba';
        // Unique constraint violation = már létezik
        if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate') || $res['code'] === 409) {
            json_error('Ez a felhasználónév már foglalt', 409);
        }
        json_error('Létrehozási hiba: ' . $msg, 500);
    }

    $created = is_array($res['body']) && isset($res['body'][0]) ? $res['body'][0] : ($res['body'] ?? []);
    json_response([
        'ok' => true,
        'id' => $created['id'] ?? null,
        'felhasznalonev' => $fnev,
    ]);
}

// ── PATCH – jelszó vagy aktív módosítás ─────────────────────────────
if ($method === 'PATCH') {
    $parts = explode('/', trim($uri, '/'));
    $id = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);

    $data   = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];

    if (isset($data['jelszo']) && $data['jelszo'] !== '') {
        if (strlen((string) $data['jelszo']) < 6) json_error('A jelszó legalább 6 karakter', 400);
        $update['jelszo_hash'] = password_hash((string) $data['jelszo'], PASSWORD_BCRYPT);
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

    $res = sb_request('PATCH', $sb_url_base . '?id=eq.' . urlencode($id), $update, 'return=minimal');
    if ($res['code'] >= 400) json_error('Frissítési hiba', 500);

    json_response(['ok' => true]);
}

// ── DELETE – felhasználó törlése ────────────────────────────────────
if ($method === 'DELETE') {
    $parts = explode('/', trim($uri, '/'));
    $id = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);

    $res = sb_request('DELETE', $sb_url_base . '?id=eq.' . urlencode($id), [], 'return=minimal');
    if ($res['code'] >= 400) json_error('Törlési hiba', 500);

    json_response(['ok' => true]);
}

json_error('Érvénytelen kérés', 405);
