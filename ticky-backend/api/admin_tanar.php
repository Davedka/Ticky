<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

require_admin_api_request(['POST']);

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    json_error('Ervenytelen JSON', 400);
}

$kod = strtoupper(trim((string) ($body['kod'] ?? '')));
$nev = trim((string) ($body['nev'] ?? ''));

if ($kod === '') {
    json_error('Hiányzó tanár kód', 400);
}

if (!preg_match('/^[\p{L}\p{N}._-]{1,32}$/u', $kod)) {
    json_error('Ervenytelen tanar kod', 400);
}

if ($nev !== '' && preg_match('/[\x00-\x1F\x7F]/u', $nev)) {
    json_error('Ervenytelen tanar nev', 400);
}

$tanarok = sb_get('tanarok', [
    'rovid_nev' => 'eq.' . $kod,
    'select' => 'id,rovid_nev',
]);

if (empty($tanarok)) {
    json_error('Tanár nem található: ' . $kod, 404);
}

$id = $tanarok[0]['id'];
$url = SUPABASE_URL . '/rest/v1/tanarok?id=eq.' . $id;
$key = SUPABASE_SERVICE_KEY;

$ctx = stream_context_create([
    'http' => [
        'method' => 'PATCH',
        'header' => implode("\r\n", [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ]),
        'content' => json_encode(['nev' => $nev !== '' ? $nev : null], JSON_UNESCAPED_UNICODE),
        'timeout' => 5,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    json_error('Supabase frissítési hiba', 500);
}

json_response(['ok' => true, 'kod' => $kod, 'nev' => $nev]);
