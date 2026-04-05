<?php
// api/auth/login.php
// POST /api/auth/login  { felhasznalonev, jelszo }

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés', 405);
}


function _login_rate_limit(): void {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $parts = explode(',', $fwd);
        $ip = trim($parts[0]);
    }
    $safe_ip  = preg_replace('/[^a-f0-9.:]/i', '_', $ip);
    $tmp_dir  = sys_get_temp_dir();
    $file     = $tmp_dir . '/ticky_login_' . $safe_ip . '.json';
    $now      = time();
    $window   = 300; // 5 perc
    $max_attempts = 10;

    $data = ['attempts' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $data = json_decode($raw, true) ?? $data;
        }
    }

    // Blokk ellenőrzés
    if (isset($data['blocked_until']) && $data['blocked_until'] > $now) {
        $wait = $data['blocked_until'] - $now;
        http_response_code(429);
        header('Retry-After: ' . $wait);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Túl sok sikertelen kísérlet. Várd meg a ' . $wait . ' másodpercet.']);
        exit;
    }

    // Ablak előtti kísérleteket töröljük
    $data['attempts'] = array_filter(
        $data['attempts'] ?? [],
        fn($t) => $t > ($now - $window)
    );

    if (count($data['attempts']) >= $max_attempts) {
        $data['blocked_until'] = $now + 900; // 15 perces blokk
        @file_put_contents($file, json_encode($data), LOCK_EX);
        http_response_code(429);
        header('Retry-After: 900');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Túl sok sikertelen kísérlet. 15 percig blokkolva.']);
        exit;
    }

    // Kísérlet rögzítése
    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function _login_clear_rate_limit(): void {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $parts = explode(',', $fwd);
        $ip = trim($parts[0]);
    }
    $safe_ip = preg_replace('/[^a-f0-9.:]/i', '_', $ip);
    $file    = sys_get_temp_dir() . '/ticky_login_' . $safe_ip . '.json';
    @unlink($file);
}

_login_rate_limit();

$body = json_decode(file_get_contents('php://input') ?: '', true);
$fnev = trim((string) ($body['felhasznalonev'] ?? ''));
$pw   = (string) ($body['jelszo'] ?? '');

if ($fnev === '' || $pw === '') {
    json_error('Hiányzó adatok', 400);
}

// Felhasználó keresése
$url = SUPABASE_URL . '/rest/v1/felhasznalok?felhasznalonev=eq.' . urlencode($fnev)
    . '&aktiv=eq.true&select=id,felhasznalonev,nev,jelszo_hash,szerep';
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

// Timing-safe: mindig fut hash check (timing attack ellen)
$dummy_hash = '$2y$12$invalidhashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
if (empty($rows)) {
    password_verify($pw, $dummy_hash);
    json_error('Hibás felhasználónév vagy jelszó', 401);
}

$user = $rows[0];
if (!password_verify($pw, (string) $user['jelszo_hash'])) {
    json_error('Hibás felhasználónév vagy jelszó', 401);
}


_login_clear_rate_limit();

$random_bytes = bin2hex(random_bytes(32));
$expires_at   = time() + (12 * 3600);
$user_id      = (string) $user['id'];


$hmac_key   = admin_password() . '|user_session|' . $user_id;
$token_data = $random_bytes . '|' . $user_id . '|' . $expires_at;
$signature  = hash_hmac('sha256', $token_data, $hmac_key);
$token      = base64_encode($token_data) . '.' . $signature;

$is_https = ticky_is_https();
setcookie('ticky_user_session', $token, [
    'expires'  => $expires_at,
    'path'     => '/',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Strict',
]);

json_response([
    'ok'     => true,
    'nev'    => $user['nev'] ?? $user['felhasznalonev'],
    'szerep' => $user['szerep'],
]);
