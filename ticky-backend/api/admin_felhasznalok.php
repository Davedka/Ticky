<?php
// api/admin_felhasznalok.php
// GET /api/admin/felhasznalok – összes felhasználó listája (admin)
// Jelszó hash NEM kerül vissza!

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors(['GET', 'OPTIONS'], ['Content-Type', 'X-Ticky-Admin']);
private_response_headers();

if (!admin_is_authenticated()) {
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
