<?php
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés', 405);
}

function ticky_login_rate_limit(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
    }

    $safe_ip = preg_replace('/[^a-f0-9.:]/i', '_', $ip);
    $file = sys_get_temp_dir() . '/ticky_login_' . $safe_ip . '.json';
    $now = time();
    $window = 300;
    $max_attempts = 10;
    $data = ['attempts' => [], 'blocked_until' => 0];

    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $data = json_decode($raw, true) ?? $data;
        }
    }

    if (($data['blocked_until'] ?? 0) > $now) {
        $wait = (int) $data['blocked_until'] - $now;
        http_response_code(429);
        header('Retry-After: ' . $wait);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Túl sok sikertelen kísérlet. Várj ' . $wait . ' másodpercet.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data['attempts'] = array_values(array_filter(
        $data['attempts'] ?? [],
        static fn($attempt) => $attempt > (time() - $window)
    ));

    if (count($data['attempts']) >= $max_attempts) {
        $data['blocked_until'] = $now + 900;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        http_response_code(429);
        header('Retry-After: 900');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Túl sok sikertelen kísérlet. 15 percig blokkolva.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function ticky_login_clear_rate_limit(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
    }

    $safe_ip = preg_replace('/[^a-f0-9.:]/i', '_', $ip);
    @unlink(sys_get_temp_dir() . '/ticky_login_' . $safe_ip . '.json');
}

ticky_login_rate_limit();

$body = json_decode(file_get_contents('php://input') ?: '', true);
$fnev = trim((string) ($body['felhasznalonev'] ?? ''));
$pw = (string) ($body['jelszo'] ?? '');

if ($fnev === '' || $pw === '') {
    json_error('Hiányzó adatok', 400);
}

$rows = sb_get('felhasznalok', [
    'felhasznalonev' => 'eq.' . $fnev,
    'aktiv' => 'eq.true',
    'select' => 'id,felhasznalonev,nev,jelszo_hash,szerep',
], 'service');

$dummy_hash = '$2y$12$PZwVhR6et3az0mS4Qwq5zeM6V2DqYm8pX7f1D4qLr0uGmX2sG6MLe';
if (empty($rows)) {
    password_verify($pw, $dummy_hash);
    json_error('Hibás felhasználónév vagy jelszó', 401);
}

$user = $rows[0];
if (!password_verify($pw, (string) $user['jelszo_hash'])) {
    json_error('Hibás felhasználónév vagy jelszó', 401);
}

ticky_login_clear_rate_limit();
ticky_set_user_session((string) $user['id']);

$redirect = (($user['szerep'] ?? '') === 'admin') ? admin_url() : '/termek';

json_response([
    'ok' => true,
    'nev' => $user['nev'] ?? $user['felhasznalonev'],
    'szerep' => $user['szerep'] ?? 'user',
    'redirect' => $redirect,
]);
