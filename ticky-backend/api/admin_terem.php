<?php
// api/admin_terem.php
// PATCH /api/admin/terem/{szam}  → emelet és aktív állapot frissítése

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/admin/terem/{szam}', $uri);

if (!$params || $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    json_error('Érvénytelen kérés', 405);
}

$terem_szam = strtoupper(trim(urldecode($params['szam'])));
$body       = json_decode(file_get_contents('php://input'), true);

// Csak engedélyezett mezők
$update = [];
if (isset($body['emelet']))  $update['emelet'] = is_numeric($body['emelet']) ? (int)$body['emelet'] : null;
if (isset($body['aktiv']))   $update['aktiv']  = (bool)$body['aktiv'];

if (empty($update)) json_error('Nincs mit frissíteni', 400);

// Terem keresés
$termek = sb_get('termek', [
    'terem_szam' => 'eq.' . $terem_szam,
    'select'     => 'id',
]);
if (empty($termek)) json_error('Terem nem található: ' . $terem_szam, 404);

$id  = $termek[0]['id'];
$url = SUPABASE_URL . '/rest/v1/termek?id=eq.' . $id;
$key = SUPABASE_SERVICE_KEY;

$ctx = stream_context_create([
    'http' => [
        'method'  => 'PATCH',
        'header'  => implode("\r\n", [
            'apikey: '               . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ]),
        'content' => json_encode($update),
        'timeout' => 5,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) json_error('Supabase frissítési hiba', 500);

json_response(['ok' => true, 'terem' => $terem_szam, 'update' => $update]);
