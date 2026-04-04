<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend
// Visszaadja az osztály mai órarendjét, csoportbontással.
// Ha Supabase-ben nincs adat, a tanárok.js-ből tölti be.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
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

// ─── 1. Lekérés: összes mai óra ehhez az osztályhoz ──────────────────
$orak_raw = sb_get('orarendek', [
    'osztaly'   => 'eq.' . $kod,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,tantargy,terem_id,tanar_id',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

// ─── 2. Ha nincs Supabase adat → tanárok.js fallback ─────────────────
if (empty($orak_raw)) {
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
}

// ─── 3. Tanárnevek (Supabase) ─────────────────────────────────────────
$tanar_map = [];
$ids = array_unique(array_column($orak_raw, 'tanar_id'));
foreach (sb_get('tanarok', [
    'id'     => 'in.(' . implode(',', $ids) . ')',
    'select' => 'id,rovid_nev,nev',
]) as $t) {
    $tanar_map[$t['id']] = $t;
}

// ─── 4. Termek (Supabase) ─────────────────────────────────────────────
$terem_map = [];
$ids = array_unique(array_column($orak_raw, 'terem_id'));
foreach (sb_get('termek', [
    'id'     => 'in.(' . implode(',', $ids) . ')',
    'select' => 'id,terem_szam',
]) as $t) {
    $terem_map[$t['id']] = $t['terem_szam'];
}

// ─── 5. Csoportosítás ────────────────────────────────────────────────
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
            'tantargy'    => $o['tantargy']    ?? '',
            'csoportok'   => [],
        ];
    }

    foreach ($map[$key]['csoportok'] as $c) {
        if ($c['terem'] === $terem && $c['tanar'] === ($tanar['rovid_nev'] ?? '?')) {
            continue 2;
        }
    }

    $map[$key]['csoportok'][] = [
        'terem'    => $terem,
        'tanar'    => $tanar['rovid_nev'] ?? '?',
        'tanar_nev'=> $tanar['nev']       ?? null,
    ];
}

// ─── 6. Összesítés + rendezés ─────────────────────────────────────────
$orak = [];
foreach ($map as $o) {
    $cs         = $o['csoportok'];
    $is_csoport = count($cs) > 1;

    $termek_lista  = [];
    $tanarok_lista = [];
    foreach ($cs as $c) {
        if (!in_array($c['terem'], $termek_lista, true))  $termek_lista[]  = $c['terem'];
        if (!in_array($c['tanar'], $tanarok_lista, true)) $tanarok_lista[] = $c['tanar'];
    }

    $orak[] = [
        'kezdes'      => substr($o['kezdes'], 0, 5),
        'vegzes'      => substr($o['vegzes'], 0, 5),
        'ora_sorszam' => $o['ora_sorszam'],
        'tantargy'    => $o['tantargy'],
        'is_csoport'  => $is_csoport,
        'terem'       => implode(' / ', $termek_lista),
        'tanar'       => implode(', ', $tanarok_lista),
        'tanar_nev'   => !$is_csoport ? ($cs[0]['tanar_nev'] ?? null) : null,
        'csoportok'   => $cs,
    ];
}

usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));

json_response([
    'osztaly' => $kod,
    'orak'    => $orak,
]);
