<?php

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/utils/app.php';
require_once __DIR__ . '/utils/domain.php';

$uri = request_path();

if (serve_static_asset_if_exists(__DIR__, $uri) && PHP_SAPI === 'cli-server') {
    return false;
}
send_default_security_headers();
handle_cors();

$exact_routes = [
    '/' => __DIR__ . '/pages/home.php',
    '/termek' => __DIR__ . '/pages/termek.php',
    '/tanar' => __DIR__ . '/pages/tanar.php',
    '/qr' => __DIR__ . '/pages/qr.php',
    '/assistant' => __DIR__ . '/pages/assistant.php',
    '/kijelzo' => __DIR__ . '/pages/kijelzo.php',
    '/admin' => __DIR__ . '/pages/admin.php',
    '/api/termek' => __DIR__ . '/api/termek.php',
    '/api/tanarok' => __DIR__ . '/api/tanarok.php',
    '/api/assistant' => __DIR__ . '/api/assistant.php',
    '/api/admin/tanar' => __DIR__ . '/api/admin_tanar.php',
];

$pattern_routes = [
    '/terem/{szam}/nap' => __DIR__ . '/pages/napirend.php',
    '/terem/{szam}' => __DIR__ . '/pages/terem.php',
    '/tanar/{kod}' => __DIR__ . '/pages/tanar.php',
    '/api/tanar/{kod}/orarend' => __DIR__ . '/api/tanar_orarend.php',
    '/api/terem/{szam}' => __DIR__ . '/api/terem.php',
    '/api/napirend/{szam}' => __DIR__ . '/api/napirend.php',
    '/api/admin/terem/{szam}' => __DIR__ . '/api/admin_terem.php',
];

if ($uri === '/api/ping') {
    json_response([
        'status' => 'ok',
        'time' => date('Y-m-d H:i:s'),
    ]);
}

if (dispatch_exact_routes($uri, $exact_routes) || dispatch_pattern_routes($uri, $pattern_routes)) {
    exit;
}

render_not_found_page();
