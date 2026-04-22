<?php
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS'], ['Content-Type', 'X-CSRF-Token']);
send_security_headers(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST keres', 405);
}

ticky_require_same_origin_request();
ticky_require_csrf_token();
ticky_require_rate_limit_json('login', 300, 10, 900, 'Tul sok sikertelen kiserlet. Varj egy kicsit.');

$body = json_decode(file_get_contents('php://input') ?: '', true);
$fnev = trim((string) ($body['felhasznalonev'] ?? ''));
$pw = (string) ($body['jelszo'] ?? '');

if ($fnev === '' || $pw === '') {
    json_error('Hianyzo adatok', 400);
}

$rows = sb_get('felhasznalok', [
    'felhasznalonev' => 'eq.' . $fnev,
    'aktiv' => 'eq.true',
    'select' => 'id,felhasznalonev,nev,jelszo_hash,szerep',
], 'service');

$dummy_hash = '$2y$12$PZwVhR6et3az0mS4Qwq5zeM6V2DqYm8pX7f1D4qLr0uGmX2sG6MLe';
if (empty($rows)) {
    password_verify($pw, $dummy_hash);
    $wait = ticky_record_rate_limit_failure('login', 300, 10, 900);
    if ($wait > 0) {
        header('Retry-After: ' . $wait);
        json_error('Tul sok sikertelen kiserlet. 15 percig blokkolva.', 429);
    }
    json_error('Hibas felhasznalonev vagy jelszo', 401);
}

$user = $rows[0];
if (!password_verify($pw, (string) ($user['jelszo_hash'] ?? ''))) {
    $wait = ticky_record_rate_limit_failure('login', 300, 10, 900);
    if ($wait > 0) {
        header('Retry-After: ' . $wait);
        json_error('Tul sok sikertelen kiserlet. 15 percig blokkolva.', 429);
    }
    json_error('Hibas felhasznalonev vagy jelszo', 401);
}

ticky_clear_rate_limit('login');
ticky_set_user_session((string) $user['id']);

$redirect = (($user['szerep'] ?? '') === 'admin') ? admin_url() : '/termek';

json_response([
    'ok' => true,
    'nev' => $user['nev'] ?? $user['felhasznalonev'],
    'szerep' => $user['szerep'] ?? 'user',
    'redirect' => $redirect,
]);
