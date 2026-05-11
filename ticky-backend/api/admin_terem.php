<?php
// api/admin_terem.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';

if (!admin_can_see_ui()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['hiba' => 'Bejelentkezés szükséges']);
    exit;
}

require_admin_api_request(['PATCH']);
handle_cors(['PATCH', 'OPTIONS'], ['Content-Type']);
private_response_headers();

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
$sb_url  = SUPABASE_URL . '/rest/v1/termek?terem_szam=eq.' . urlencode($szam);
$sb_key  = SUPABASE_SERVICE_KEY;
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
    'ok'     => true,
    'szam'   => $szam,
    'update' => $update,
]);
