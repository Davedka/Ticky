<?php
// api/tanar_orarend.php
// GET /api/tanar/{kod}/orarend
//
// ÚJ FLOW: source helper (tanárok.js) ELSŐDLEGES, DB csak fallback.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/tanar/{kod}/orarend', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó tanár kód', 400);
}

$kod = strtoupper(urldecode($params['kod']));
if (!preg_match('/^[\p{L}\p{N}._-]{1,32}$/u', $kod)) {
    json_error('Érvénytelen tanár kód', 400);
}

$nap = mai_nap();

// Hétvége
if ($nap === 0) {
    json_response(['tanar_nev' => null, 'orak' => [], 'uzenet' => 'Hétvége – nincs tanítás']);
}

// Szünet
$sz = aktiv_szunet();
if ($sz !== null) {
    json_response([
        'tanar_nev' => null,
        'orak'      => [],
        'szunet'    => true,
        'uzenet'    => $sz['nev'] . ' – nincs tanítás (' . $sz['kezdet'] . ' – ' . $sz['vege'] . ')',
    ]);
}

$source_teacher_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];

// ───────────────────────────────────────────────────────────────
// ELSŐDLEGES: source helper (tanárok.js)
// ───────────────────────────────────────────────────────────────
if (function_exists('ticky_source_teacher_day_schedule')) {
    try {
        $result = ticky_source_teacher_day_schedule($kod, $nap);
        if ($result !== null && !empty($result['orak'] ?? [])) {
            json_response([
                'tanar_nev' => $result['tanar_nev'] ?? ($source_teacher_names[$kod] ?? null),
                'orak'      => merge_consecutive_orak($result['orak'] ?? []),
            ]);
        }
    } catch (\Throwable $e) {
        // Folytatás a DB fallback-kal
    }
}

// ───────────────────────────────────────────────────────────────
// MÁSODLAGOS: DB fallback
// ───────────────────────────────────────────────────────────────
$tanarok = sb_get('tanarok', ['rovid_nev' => 'eq.' . $kod, 'select' => 'id,rovid_nev,nev']);
$tanar = $tanarok[0] ?? null;
$tanar_id = $tanar['id'] ?? null;
if ($tanar !== null) {
    $tanar['nev'] = $tanar['nev'] ?? ($source_teacher_names[$tanar['rovid_nev'] ?? $kod] ?? null);
}

if ($tanar === null) {
    json_error('Tanár nem található: ' . $kod, 404);
}

$orak_raw = sb_get('orarendek', [
    'tanar_id'  => 'eq.' . $tanar_id,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,osztaly,tantargy,termek(terem_szam)',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

// Csoportosítás: azonos időszak = csoportbontás
$csoportok_map = [];
foreach ($orak_raw as $ora) {
    $terem   = $ora['termek']['terem_szam'] ?? '?';
    $osztaly = $ora['osztaly']              ?? '?';
    $kezdes  = $ora['kezdes']               ?? '';
    $vegzes  = $ora['vegzes']               ?? '';
    $key     = $kezdes . '_' . $vegzes;

    if (!isset($csoportok_map[$key])) {
        $csoportok_map[$key] = [
            'kezdes'      => $kezdes,
            'vegzes'      => $vegzes,
            'ora_sorszam' => $ora['ora_sorszam'] ?? null,
            'tantargy'    => $ora['tantargy']    ?? '',
            'csoportok'   => [],
        ];
    }

    $mar_van = false;
    foreach ($csoportok_map[$key]['csoportok'] as $c) {
        if ($c['terem'] === $terem && $c['osztaly'] === $osztaly) { $mar_van = true; break; }
    }
    if (!$mar_van) {
        $csoportok_map[$key]['csoportok'][] = ['terem' => $terem, 'osztaly' => $osztaly];
    }
}

$orak = [];
foreach ($csoportok_map as $o) {
    $cs = $o['csoportok'];
    $termek_lista = $osztalyok_lista = [];
    foreach ($cs as $c) {
        if (!in_array($c['terem'],   $termek_lista,   true)) $termek_lista[]   = $c['terem'];
        if (!in_array($c['osztaly'], $osztalyok_lista, true)) $osztalyok_lista[] = $c['osztaly'];
    }
    $orak[] = [
        'kezdes'      => $o['kezdes'],
        'vegzes'      => $o['vegzes'],
        'ora_sorszam' => $o['ora_sorszam'],
        'tantargy'    => $o['tantargy'],
        'is_csoport'  => count($cs) > 1,
        'terem'       => implode(' / ', $termek_lista),
        'osztaly'     => implode('/', $osztalyok_lista),
        'csoportok'   => $cs,
    ];
}

usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));

json_response(['tanar_nev' => $tanar['nev'] ?? null, 'orak' => $orak]);
