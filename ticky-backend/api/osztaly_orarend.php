<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';

handle_cors();

// Kód forrása: index.php már beállítja $_GET['kod']
$kod = trim(urldecode((string) ($_GET['kod'] ?? '')));

// Fallback: ha közvetlen hívás
if ($kod === '') {
    $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $params = match_route('/api/osztaly/{kod}/orarend', $uri);
    if ($params !== false) $kod = trim(urldecode((string) ($params['kod'] ?? '')));
}

if ($kod === '') {
    json_error('Hiányzó osztály kód', 400);
}

// ── Szünet check ─────────────────────────────────────
$sz = aktiv_szunet();
if ($sz !== null) {
    json_response([
        'osztaly' => $kod,
        'orak'    => [],
        'szunet'  => $sz['nev'],
        'uzenet'  => '🌙 ' . $sz['nev'] . ' – nincs tanítás (' . $sz['kezdet'] . ' – ' . $sz['vege'] . ')',
    ]);
}

$nap = mai_nap();
if ($nap === 0) {
    json_response([
        'osztaly' => $kod,
        'orak'    => [],
        'uzenet'  => 'Hétvége – nincs tanítás',
    ]);
}

// ─── Lekérés ─────────────────────────────────────────
$orak_raw = sb_get('orarendek', [
    'osztaly'   => 'eq.' . $kod,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,tantargy,terem_id,tanar_id',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

// Tanárnevek
$tanar_map = [];
if (!empty($orak_raw)) {
    $ids = array_unique(array_filter(array_column($orak_raw, 'tanar_id')));
    if (!empty($ids)) {
        foreach (sb_get('tanarok', [
            'id'     => 'in.(' . implode(',', $ids) . ')',
            'select' => 'id,rovid_nev,nev',
        ]) as $t) { $tanar_map[$t['id']] = $t; }
    }
}

// Termek
$terem_map = [];
if (!empty($orak_raw)) {
    $ids = array_unique(array_filter(array_column($orak_raw, 'terem_id')));
    if (!empty($ids)) {
        foreach (sb_get('termek', [
            'id'     => 'in.(' . implode(',', $ids) . ')',
            'select' => 'id,terem_szam',
        ]) as $t) { $terem_map[$t['id']] = $t['terem_szam']; }
    }
}

// Csoportosítás
$map = [];
foreach ($orak_raw as $o) {
    $key   = $o['kezdes'] . '_' . $o['vegzes'];
    $tanar = $tanar_map[$o['tanar_id']] ?? null;
    $terem = $terem_map[$o['terem_id']] ?? '?';
    if (!isset($map[$key])) {
        $map[$key] = [
            'kezdes'      => $o['kezdes'],
            'vegzes'      => $o['vegzes'],
            'ora_sorszam' => $o['ora_sorszam'] ?? null,
            'tantargy'    => $o['tantargy'] ?? '',
            'csoportok'   => [],
        ];
    }
    foreach ($map[$key]['csoportok'] as $c) {
        if ($c['terem'] === $terem && $c['tanar'] === ($tanar['rovid_nev'] ?? '?')) continue 2;
    }
    $map[$key]['csoportok'][] = [
        'terem'     => $terem,
        'tanar'     => $tanar['rovid_nev'] ?? '?',
        'tanar_nev' => $tanar['nev'] ?? null,
    ];
}

$orak = [];
foreach ($map as $o) {
    $cs = $o['csoportok'];
    $termek_lista = $tanarok_lista = [];
    foreach ($cs as $c) {
        if (!in_array($c['terem'], $termek_lista, true))  $termek_lista[]  = $c['terem'];
        if (!in_array($c['tanar'], $tanarok_lista, true)) $tanarok_lista[] = $c['tanar'];
    }
    $orak[] = [
        'kezdes'      => substr($o['kezdes'], 0, 5),
        'vegzes'      => substr($o['vegzes'], 0, 5),
        'ora_sorszam' => $o['ora_sorszam'],
        'tantargy'    => $o['tantargy'],
        'is_csoport'  => count($cs) > 1,
        'terem'       => implode(' / ', $termek_lista),
        'tanar'       => implode(', ', $tanarok_lista),
        'tanar_nev'   => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
        'csoportok'   => $cs,
    ];
}
usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));

json_response(['osztaly' => $kod, 'orak' => $orak]);
