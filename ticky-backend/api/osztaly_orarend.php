<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend
// Visszaadja az osztály mai órarendjét kizárólag tanárok.js-ből

require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';
require_once __DIR__ . '/../utils/ticky_source.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/osztaly/{kod}/orarend', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó osztály kód', 400);
}

$kod = trim(urldecode((string) $params['kod']));

if ($kod === '' || !preg_match('/^[\p{L}\p{N}\s._\/-]{1,64}$/u', $kod)) {
    json_error('Érvénytelen osztály kód', 400);
}

$nap = mai_nap();

if ($nap === 0) {
    json_response([
        'osztaly' => $kod,
        'orak'    => [],
        'uzenet'  => 'Hétvége – nincs tanítás',
    ]);
}

$result = ticky_source_class_lessons_for_day($kod, $nap);

if ($result === null) {
    json_error('Osztály nem található: ' . $kod, 404);
}

$raw_orak = $result['orak'] ?? [];
$orak     = [];

foreach ($raw_orak as $o) {
    $cs         = $o['csoportok'] ?? [];
    $is_csoport = $o['is_csoport'] ?? (count($cs) > 1);

    $normalized_cs = [];
    foreach ($cs as $c) {
        $normalized_cs[] = [
            'terem'    => (string) ($c['terem']    ?? '?'),
            'tanar'    => (string) ($c['tanar']    ?? '?'),
            'tanar_nev'=> $c['tanar_nev'] ?? null,
        ];
    }

    $orak[] = [
        'kezdes'      => (string) ($o['kezdes']      ?? ''),
        'vegzes'      => (string) ($o['vegzes']      ?? ''),
        'ora_sorszam' => $o['ora_sorszam'] ?? null,
        'tantargy'    => (string) ($o['tantargy']    ?? ''),
        'is_csoport'  => $is_csoport,
        'terem'       => (string) ($o['terem']       ?? '?'),
        'tanar'       => (string) ($o['tanar']       ?? '?'),
        'tanar_nev'   => $o['tanar_nev'] ?? null,
        'csoportok'   => $normalized_cs,
    ];
}

json_response([
    'osztaly' => $result['osztaly'],
    'orak'    => $orak,
]);
