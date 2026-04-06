<?php
// api/admin_szunet.php
// GET    /api/admin/szunetek          – lista
// POST   /api/admin/szunet            – új létrehozás
// PATCH  /api/admin/szunet/{id}       – módosítás
// DELETE /api/admin/szunet/{id}       – törlés

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

handle_cors(['GET','POST','PATCH','DELETE','OPTIONS'], ['Content-Type']);
private_response_headers();

if (!admin_is_authenticated()) { json_error('Hozzáférés megtagadva', 401); }

$base = SUPABASE_URL . '/rest/v1/szunetek';
$key  = SUPABASE_SERVICE_KEY;

function szunet_req(string $method, string $url, array $body = []): array {
    global $key;
    $hdrs = ["apikey: $key","Authorization: Bearer $key","Content-Type: application/json","Prefer: return=representation"];
    $ctx  = stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$hdrs),'content'=>$body?json_encode($body,JSON_UNESCAPED_UNICODE):'','timeout'=>6,'ignore_errors'=>true]]);
    $raw  = @file_get_contents($url, false, $ctx);
    $code = 200;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $code=(int)$m[1]; break; }
    }
    return ['code'=>$code,'body'=>$raw!==false?(json_decode($raw,true)??[]):[]];
}

// GET – lista
if ($method === 'GET') {
    $res = szunet_req('GET', $base . '?order=kezdet.asc');
    json_response(['szunetek' => $res['body'], 'count' => count($res['body'])]);
}

// POST – új szünet
if ($method === 'POST') {
    $d     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $nev   = trim((string)($d['nev']    ?? ''));
    $kezdet= trim((string)($d['kezdet'] ?? ''));
    $vege  = trim((string)($d['vege']   ?? ''));
    if ($nev === '' || $kezdet === '' || $vege === '') json_error('Hiányzó adatok', 400);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $kezdet) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vege)) json_error('Érvénytelen dátum formátum (YYYY-MM-DD)', 400);
    if ($kezdet > $vege) json_error('A kezdet nem lehet a vége után', 400);
    $res = szunet_req('POST', $base, ['nev'=>$nev,'kezdet'=>$kezdet,'vege'=>$vege]);
    if ($res['code'] >= 400) json_error('Supabase hiba', 500);
    $created = is_array($res['body']) && isset($res['body'][0]) ? $res['body'][0] : $res['body'];
    json_response(['ok'=>true,'szunet'=>$created]);
}

// PATCH – módosítás
if ($method === 'PATCH') {
    $parts = explode('/', trim($uri, '/'));
    $id    = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);
    $d      = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    $update = [];
    if (isset($d['nev']))    $update['nev']    = trim((string)$d['nev']);
    if (isset($d['kezdet'])) $update['kezdet'] = trim((string)$d['kezdet']);
    if (isset($d['vege']))   $update['vege']   = trim((string)$d['vege']);
    if (empty($update))      json_error('Nincs mit frissíteni', 400);
    $res = szunet_req('PATCH', $base . '?id=eq.' . urlencode($id), $update);
    if ($res['code'] >= 400) json_error('Frissítési hiba', 500);
    json_response(['ok'=>true]);
}

// DELETE
if ($method === 'DELETE') {
    $parts = explode('/', trim($uri, '/'));
    $id    = end($parts);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) json_error('Érvénytelen ID', 400);
    $res = szunet_req('DELETE', $base . '?id=eq.' . urlencode($id));
    if ($res['code'] >= 400) json_error('Törlési hiba', 500);
    json_response(['ok'=>true]);
}

json_error('Érvénytelen kérés', 405);
