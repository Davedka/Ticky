<?php
// api/admin_tanar.php
// POST /api/admin/tanar  → tanár teljes nevének mentése

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés engedélyezett', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$kod  = strtoupper(trim($body['kod'] ?? ''));
$nev  = trim($body['nev'] ?? '');

if (!$kod) json_error('Hiányzó tanár kód', 400);

// Tanár keresés
$tanarok = sb_get('tanarok', [
    'rovid_nev' => 'eq.' . $kod,
    'select'    => 'id,rovid_nev',
]);

if (empty($tanarok)) {
    json_error('Tanár nem található: ' . $kod, 404);
}

$id  = $tanarok[0]['id'];
$url = SUPABASE_URL . '/rest/v1/tanarok?id=eq.' . $id;
$key = SUPABASE_SERVICE_KEY;

$ctx = stream_context_create([
    'http' => [
        'method'  => 'PATCH',
        'header'  => implode("\r\n", [
            'apikey: '           . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ]),
        'content' => json_encode(['nev' => $nev ?: null]),
        'timeout' => 5,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    json_error('Supabase frissítési hiba', 500);
}

json_response(['ok' => true, 'kod' => $kod, 'nev' => $nev]);
