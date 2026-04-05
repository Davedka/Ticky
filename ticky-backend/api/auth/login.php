<?php
// api/auth/login.php
// POST /api/auth/login  { felhasznalonev, jelszo }
// Felhasználói bejelentkezés – session cookie beállítása

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés', 405);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$fnev = trim((string) ($body['felhasznalonev'] ?? ''));
$pw   = (string) ($body['jelszo'] ?? '');

if ($fnev === '' || $pw === '') {
    json_error('Hiányzó adatok', 400);
}

// Felhasználó keresése service key-jel (RLS megkerülése)
$url = SUPABASE_URL . '/rest/v1/felhasznalok?felhasznalonev=eq.' . urlencode($fnev) . '&aktiv=eq.true&select=id,felhasznalonev,nev,jelszo_hash,szerep';
$key = SUPABASE_SERVICE_KEY;

$ctx = stream_context_create(['http' => [
    'method'  => 'GET',
    'header'  => "apikey: $key\r\nAuthorization: Bearer $key\r\nAccept: application/json",
    'timeout' => 5,
]]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    json_error('Szerverhiba', 500);
}

$rows = json_decode($raw, true) ?? [];
if (empty($rows)) {
    // Timing-safe: mindig fut egy hash check
    password_verify($pw, '$2y$10$invalidhashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
    json_error('Hibás felhasználónév vagy jelszó', 401);
}

$user = $rows[0];

if (!password_verify($pw, (string) $user['jelszo_hash'])) {
    json_error('Hibás felhasználónév vagy jelszó', 401);
}

// Session token generálása
$expires_at = time() + (12 * 3600); // 12 óra
$user_id    = (string) $user['id'];
$token_data = $user_id . '|' . $expires_at . '|' . $fnev;
$signature  = hash_hmac('sha256', $token_data, admin_password() . '_user_session');
$token      = base64_encode($user_id) . '.' . $expires_at . '.' . $signature;

$is_https = ticky_is_https();
setcookie('ticky_user_session', $token, [
    'expires'  => $expires_at,
    'path'     => '/',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Strict',
]);

json_response([
    'ok'   => true,
    'nev'  => $user['nev'] ?? $user['felhasznalonev'],
    'szerep' => $user['szerep'],
]);
