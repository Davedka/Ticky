<?php
// api/admin_terem.php
// PATCH /api/admin/terem/{szam}  { emelet, nev }

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors(['PATCH', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
private_response_headers();

// ── Auth ─────────────────────────────────────────────────────────────
$admin_pw = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
if ($admin_pw === '') {
    json_error('Admin nincs konfigurálva', 500);
}

$expected = hash_hmac('sha256', 'ticky_admin_' . $admin_pw, $admin_pw);
$cookie   = $_COOKIE['ticky_auth'] ?? '';
if ($cookie === '' || !hash_equals($expected, $cookie)) {
    json_error('Hozzáférés megtagadva', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    json_error('Csak PATCH kérés', 405);
}

if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
    json_error('Érvénytelen admin kérés', 400);
}

// ── Terem szám kinyerése URL-ből ─────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/admin/terem/{szam}', $uri);
$szam   = $params['szam'] ?? ($_GET['szam'] ?? '');

if ($szam === '' || $szam === null) {
    json_error('Hiányzó terem szám', 400);
}

// ── Body ──────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    json_error('Érvénytelen JSON', 400);
}

$update = [];

if (array_key_exists('emelet', $input)) {
    if ($input['emelet'] === null || $input['emelet'] === '') {
        $update['emelet'] = null;
    } else {
        $e = (int) $input['emelet'];
        if ($e < 0 || $e > 10) {
            json_error('Érvénytelen emelet érték (0–10)', 400);
        }
        $update['emelet'] = $e;
    }
}

if (array_key_exists('nev', $input)) {
    $update['nev'] = trim((string) $input['nev']) ?: null;
}

if (empty($update)) {
    json_error('Nincs módosítandó adat', 400);
}

// ── Supabase PATCH ────────────────────────────────────────────────────
$sb_url = SUPABASE_URL . '/rest/v1/termek?terem_szam=eq.' . urlencode($szam);
$sb_key = SUPABASE_SERVICE_KEY;
$payload = json_encode($update, JSON_UNESCAPED_UNICODE);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'PATCH',
        'header'        => implode("\r\n", [
            'apikey: '               . $sb_key,
            'Authorization: Bearer ' . $sb_key,
            'Content-Type: application/json',
            'Content-Length: '       . strlen($payload),
            'Prefer: return=minimal',
        ]),
        'content'       => $payload,
        'timeout'       => 8,
        'ignore_errors' => true,
    ],
]);

$result    = @file_get_contents($sb_url, false, $ctx);
$http_code = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $http_code = (int) $m[1];
            break;
        }
    }
}

if ($result === false || $http_code >= 400) {
    json_error('Supabase frissítési hiba (HTTP ' . $http_code . ')', 500);
}

json_response([
    'ok'    => true,
    'szam'  => $szam,
    'update' => $update,
]);
