<?php
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');
handle_cors();
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége',1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
    $nap = mai_nap(); $ido = aktualis_ido();
    include __DIR__ . '/pages/_landing.php';
    exit;
}
if ($uri === '/api/ping') { json_response(['status'=>'ok','time'=>date('Y-m-d H:i:s')]); }
if ($uri === '/termek') { require __DIR__ . '/pages/termek.php'; exit; }
if ($uri === '/tanar' || match_route('/tanar/{kod}', $uri) !== false) { require __DIR__ . '/pages/tanar.php'; exit; }
if ($uri === '/qr') { require __DIR__ . '/pages/qr.php'; exit; }
if ($uri === '/kijelzo') { require __DIR__ . '/pages/kijelzo.php'; exit; }
if (match_route('/terem/{szam}/nap', $uri) !== false) { require __DIR__ . '/pages/napirend.php'; exit; }
if (match_route('/terem/{szam}', $uri) !== false) { require __DIR__ . '/pages/terem.php'; exit; }
if ($uri === '/api/termek') { require __DIR__ . '/api/termek.php'; exit; }
if ($uri === '/api/tanarok') { require __DIR__ . '/api/tanarok.php'; exit; }
if ($uri === '/api/ai/chat') { require __DIR__ . '/api/ai_chat.php'; exit; }
if (match_route('/api/tanar/{kod}/orarend', $uri) !== false) { require __DIR__ . '/api/tanar_orarend.php'; exit; }
if (match_route('/api/terem/{szam}', $uri) !== false) { require __DIR__ . '/api/terem.php'; exit; }
if (match_route('/api/napirend/{szam}', $uri) !== false) { require __DIR__ . '/api/napirend.php'; exit; }
if ($uri === '/admin') { require __DIR__ . '/pages/admin.php'; exit; }
if ($uri === '/api/admin/tanar') { require __DIR__ . '/api/admin_tanar.php'; exit; }
if (match_route('/api/admin/terem/{szam}', $uri) !== false) { require __DIR__ . '/api/admin_terem.php'; exit; }
http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza a főoldalra</a></body></html>';
