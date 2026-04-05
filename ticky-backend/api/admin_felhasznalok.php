<?php
// api/admin_felhasznalok.php
// GET /api/admin/felhasznalok – összes felhasználó listája (admin)
// Jelszó hash NEM kerül vissza!

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors(['GET', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
private_response_headers();

// ── Auth – ugyanaz mint admin.php (ticky_auth cookie) ───────────────
function _check_admin_auth(): bool {
    $admin_pw = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
    if (empty($admin_pw)) return false;
    $expected = hash_hmac('sha256', 'ticky_admin_' . $admin_pw, $admin_pw);
    $cookie   = $_COOKIE['ticky_auth'] ?? '';
    return $cookie !== '' && hash_equals($expected, $cookie);
}

if (!_check_admin_auth()) {
    json_error('Hozzáférés megtagadva', 401);
}

if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
    json_error('Érvénytelen admin kérés', 400);
}

$url = SUPABASE_URL . '/rest/v1/felhasznalok?select=id,felhasznalonev,nev,szerep,aktiv,letrehozva&order=letrehozva.desc';
$key = SUPABASE_SERVICE_KEY;

$ctx = stream_context_create(['http' => [
    'method'  => 'GET',
    'header'  => "apikey: $key\r\nAuthorization: Bearer $key\r\nAccept: application/json",
    'timeout' => 5,
]]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    json_error('Supabase hiba', 500);
}

$rows = json_decode($raw, true) ?? [];
json_response([
    'felhasznalok' => $rows,
    'count'        => count($rows),
]);
