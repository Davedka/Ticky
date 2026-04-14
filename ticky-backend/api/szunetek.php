<?php
// api/szunetek.php
// GET    /api/admin/szunetek        – lista (auth szükséges)
// POST   /api/admin/szunetek        – hozzáadás
// DELETE /api/admin/szunetek/{id}   – törlés

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';

handle_cors(['GET', 'POST', 'DELETE', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
private_response_headers();

// ── Auth (ticky_auth cookie – ugyanaz mint admin.php) ─────────────────
function _sz_check_admin(): bool {
    $pw = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
    if ($pw === '') return false;
    $expected = hash_hmac('sha256', 'ticky_admin_' . $pw, $pw);
    $cookie   = $_COOKIE['ticky_auth'] ?? '';
    return $cookie !== '' && hash_equals($expected, $cookie);
}

if (!_sz_check_admin()) {
    json_error('Hozzáférés megtagadva', 401);
}

$method  = $_SERVER['REQUEST_METHOD'];
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$sb_base = SUPABASE_URL . '/rest/v1/szunetek';
$key     = SUPABASE_SERVICE_KEY;

function _sz_sb(string $method, string $url, array $body = [], bool $rep = false): array {
    global $key;
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=" . ($rep ? 'representation' : 'minimal'),
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
    return ['code' => $code, 'data' => json_decode($raw ?: '[]', true) ?? []];
}

// ── GET – lista ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $res = _sz_sb('GET', $sb_base . '?select=id,nev,kezdet,vege&order=kezdet.asc');
    json_response([
        'szunetek' => is_array($res['data']) ? $res['data'] : [],
        'count'    => is_array($res['data']) ? count($res['data']) : 0,
    ]);
}

// ── POST – létrehozás ─────────────────────────────────────────────────
if ($method === 'POST') {
    if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
        json_error('Érvénytelen admin kérés', 400);
    }

    $data = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $nev    = trim((string) ($data['nev']    ?? ''));
    $kezdet = trim((string) ($data['kezdet'] ?? ''));
    $vege   = trim((string) ($data['vege']   ?? ''));

    if ($nev === '' || $kezdet === '' || $vege === '') {
        json_error('Hiányzó mezők (nev, kezdet, vege)', 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $kezdet) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vege)) {
        json_error('Érvénytelen dátum formátum – YYYY-MM-DD szükséges', 400);
    }
    if ($kezdet > $vege) {
        json_error('A kezdet nem lehet a vége után', 400);
    }

    $res = _sz_sb('POST', $sb_base, [
        'nev'    => $nev,
        'kezdet' => $kezdet,
        'vege'   => $vege,
    ], true);

    if ($res['code'] >= 400) {
        $msg = (string) ($res['data']['message'] ?? ('Supabase hiba (' . $res['code'] . ')'));
        json_error($msg, 500);
    }

    // Cache törlés hogy az APIs rögtön az új adatot lássák
    szunet_invalidate_cache();

    $created = $res['data'][0] ?? null;
    json_response(['ok' => true, 'szunet' => $created]);
}

// ── DELETE – törlés ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
        json_error('Érvénytelen admin kérés', 400);
    }

    // ID kinyerése: /api/admin/szunetek/{id}
    $parts = array_filter(explode('/', $uri));
    $id    = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', (string) $id)) {
        json_error('Érvénytelen szünet ID', 400);
    }

    $res = _sz_sb('DELETE', $sb_base . '?id=eq.' . urlencode($id));
    if ($res['code'] >= 400) {
        json_error('Törlési hiba (' . $res['code'] . ')', 500);
    }

    szunet_invalidate_cache();
    json_response(['ok' => true]);
}

json_error('Nem támogatott metódus', 405);
