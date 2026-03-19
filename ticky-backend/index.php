<?php
// index.php – Ticky API Router
// Minden kérés ide érkezik (.htaccess / render.yaml redirect)

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ─── Routing táblázat ─────────────────────────────────
// GET /api/terem/{szam}     → aktuális óra egy teremben
// GET /api/termek           → összes terem listája
// GET /api/napirend/{szam}  → napi/heti órarend
// GET /api/ping             → health check

if ($uri === '/api/ping' || $uri === '/') {
    json_response([
        'app'     => 'Ticky API',
        'version' => '1.0',
        'time'    => date('Y-m-d H:i:s'),
        'nap'     => mai_nap(),
        'status'  => 'ok',
    ]);
}

if ($uri === '/api/termek') {
    require __DIR__ . '/api/termek.php';
    exit;
}

if (match_route('/api/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/api/terem.php';
    exit;
}

if (match_route('/api/napirend/{szam}', $uri) !== false) {
    require __DIR__ . '/api/napirend.php';
    exit;
}

// 404
json_error('Nem találtam: ' . $uri, 404);