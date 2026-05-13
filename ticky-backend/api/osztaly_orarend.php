<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend
//
// ÚJ FLOW: a source helper (tanarok.js alapú) ELSŐDLEGES.
// A DB-t csak akkor használjuk, ha a source nem talál egyezést.
// Ez megoldja a csoportbontas-display bugot, mert a DB-ben (importer hibája
// miatt) cross-product van ZIP helyett, és emiatt rossz osztály->terem
// hozzárendelések vannak benne.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

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

// Hétvége
if ($nap === 0) {
    json_response(['osztaly' => $kod, 'orak' => [], 'uzenet' => 'Hétvége – nincs tanítás']);
}

// Szünet ellenőrzés
$sz = aktiv_szunet();
if ($sz !== null) {
    json_response([
        'osztaly' => $kod,
        'orak'    => [],
        'szunet'  => true,
        'uzenet'  => $sz['nev'] . ' – nincs tanítás (' . $sz['kezdet'] . ' – ' . $sz['vege'] . ')',
    ]);
}

// ───────────────────────────────────────────────────────────────
// ELSŐDLEGES: source helper (tanárok.js)
// ───────────────────────────────────────────────────────────────
if (function_exists('ticky_source_class_lessons_for_day')) {
    try {
        $result = ticky_source_class_lessons_for_day($kod, $nap);
        if ($result !== null && !empty($result['orak'] ?? [])) {
            json_response([
                'osztaly' => $result['osztaly'],
                'orak'    => merge_consecutive_orak($result['orak']),
            ]);
        }
    } catch (\Throwable $e) {
        // Folytatás a DB fallback-kal
    }
}

// ───────────────────────────────────────────────────────────────
// MÁSODLAGOS: DB fallback (ha source nem talál egyezést)
// ───────────────────────────────────────────────────────────────
$orak_raw = sb_get('orarendek', [
    'osztaly'   => 'eq.' . $kod,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,tantargy,terem_id,tanar_id',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

if (empty($orak_raw)) {
    json_response(['osztaly' => $kod, 'orak' => []]);
}

$source_teacher_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];

// Tanárnevek
$tanar_map = [];
$ids = array_unique(array_filter(array_column($orak_raw, 'tanar_id')));
if (!empty($ids)) {
    foreach (sb_get('tanarok', ['id' => 'in.(' . implode(',', $ids) . ')', 'select' => 'id,rovid_nev,nev']) as $t) {
        $rovid = (string) ($t['rovid_nev'] ?? '?');
        $tanar_map[$t['id']] = [
            'rovid_nev' => $rovid,
            'nev'       => $t['nev'] ?? ($source_teacher_names[$rovid] ?? null),
        ];
    }
}

// Termek
$terem_map = [];
$ids = array_unique(array_filter(array_column($orak_raw, 'terem_id')));
if (!empty($ids)) {
    foreach (sb_get('termek', ['id' => 'in.(' . implode(',', $ids) . ')', 'select' => 'id,terem_szam']) as $t) {
        $terem_map[$t['id']] = $t['terem_szam'];
    }
}

// Csoportosítás idő szerint
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
            'tantargyak'  => [],
            'csoportok'   => [],
        ];
    }

    $tantargy = (string) ($o['tantargy'] ?? '');
    if ($tantargy !== '' && !in_array($tantargy, $map[$key]['tantargyak'], true)) {
        $map[$key]['tantargyak'][] = $tantargy;
    }

    foreach ($map[$key]['csoportok'] as $c) {
        if (
            $c['terem'] === $terem
            && $c['tanar'] === ($tanar['rovid_nev'] ?? '?')
            && ($c['tantargy'] ?? '') === $tantargy
        ) {
            continue 2;
        }
    }

    $map[$key]['csoportok'][] = [
        'terem'     => $terem,
        'osztaly'   => $kod,
        'tanar'     => $tanar['rovid_nev'] ?? '?',
        'tanar_nev' => $tanar['nev'] ?? ($source_teacher_names[$tanar['rovid_nev'] ?? ''] ?? null),
        'tantargy'  => $tantargy,
    ];
}

$orak = [];
foreach ($map as $o) {
    $cs = $o['csoportok'];
    $termek_lista = $tanarok_lista = [];
    foreach ($cs as $c) {
        if (!in_array($c['terem'], $termek_lista,  true)) $termek_lista[]  = $c['terem'];
        if (!in_array($c['tanar'], $tanarok_lista, true)) $tanarok_lista[] = $c['tanar'];
    }
    $orak[] = [
        'kezdes'      => substr($o['kezdes'], 0, 5),
        'vegzes'      => substr($o['vegzes'], 0, 5),
        'ora_sorszam' => $o['ora_sorszam'],
        'tantargy'    => implode(' / ', $o['tantargyak']),
        'is_csoport'  => count($cs) > 1,
        'terem'       => implode(' / ', $termek_lista),
        'tanar'       => implode(' / ', $tanarok_lista),
        'tanar_nev'   => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
        'csoportok'   => $cs,
    ];
}

usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));
$orak = merge_consecutive_orak($orak);

json_response(['osztaly' => $kod, 'orak' => $orak]);
