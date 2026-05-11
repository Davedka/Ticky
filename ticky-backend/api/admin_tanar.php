<?php
// api/admin_tanar.php
// POST /api/admin/tanar  { kod, nev }

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

if (!admin_can_see_ui()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['hiba' => 'Bejelentkezés szükséges']);
    exit;
}

require_admin_api_request(['POST']);

// ── Body ────────────────────────────────────────────
$raw_body = file_get_contents('php://input') ?: '';
$body     = json_decode($raw_body, true);

if (!is_array($body)) {
    json_error('Érvénytelen JSON', 400);
}

$kod = strtoupper(trim((string) ($body['kod'] ?? '')));
$nev = trim((string) ($body['nev'] ?? ''));

if ($kod === '') {
    json_error('Hiányzó tanár kód', 400);
}
if (!preg_match('/^[\p{L}\p{N}._\-]{1,32}$/u', $kod)) {
    json_error('Érvénytelen tanár kód', 400);
}

// ── Tanár ID keresés ────────────────────────────────
$tanarok = sb_get('tanarok', [
    'rovid_nev' => 'eq.' . $kod,
    'select'    => 'id,rovid_nev',
]);

if (empty($tanarok)) {
    json_error('Tanár nem található: ' . $kod, 404);
}

$id = $tanarok[0]['id'];

// ── Supabase PATCH ───────────────────────────────────
$sb_url  = SUPABASE_URL . '/rest/v1/tanarok?id=eq.' . urlencode((string) $id);
$sb_key  = SUPABASE_SERVICE_KEY;
$payload = json_encode(['nev' => ($nev === '' ? null : $nev)], JSON_UNESCAPED_UNICODE);

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

$result      = @file_get_contents($sb_url, false, $ctx);
$http_code   = 200;

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
    'ok'  => true,
    'kod' => $kod,
    'nev' => ($nev === '' ? null : $nev),
]);
