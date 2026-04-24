<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend
// FIX: Nem require-olja az osztaly_helpers.php-t külön – a ticky_source.php
//      már betölti az osztaly.php-t. Duplikált require → PHP fatal error → betöltési hiba.

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

// Szünet ellenőrzés (Supabase-ből)
$sz = aktiv_szunet();
if ($sz !== null) {
    json_response([
        'osztaly' => $kod,
        'orak'    => [],
        'szunet'  => true,
        'uzenet'  => $sz['nev'] . ' – nincs tanítás (' . $sz['kezdet'] . ' – ' . $sz['vege'] . ')',
    ]);
}

// ─── Supabase lekérés ────────────────────────────────────────────────
$orak_raw = sb_get('orarendek', [
    'osztaly'   => 'eq.' . $kod,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,tantargy,terem_id,tanar_id',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

// ─── Ha nincs Supabase adat → tanárok.js fallback ────────────────────
if (empty($orak_raw)) {
    $src = __DIR__ . '/../utils/tanarok_source.php';
    if (is_file($src)) {
        try {
            // ticky_source.php betölti az osztaly.php-t (ne töltsük újra!)
            require_once $src;
            if (function_exists('ticky_source_class_lessons_for_day')) {
                $result = ticky_source_class_lessons_for_day($kod, $nap);
                if ($result !== null) {
                    $orak = [];
                    foreach ($result['orak'] ?? [] as $o) {
                        $cs = $o['csoportok'] ?? [];
                        $norm_cs = array_map(fn($c) => [
                            'terem'    => (string) ($c['terem']    ?? '?'),
                            'tanar'    => (string) ($c['tanar']    ?? '?'),
                            'tanar_nev'=> $c['tanar_nev'] ?? null,
                            'tantargy' => (string) ($c['tantargy'] ?? ''),
                        ], $cs);
                        $orak[] = [
                            'kezdes'      => (string) ($o['kezdes']      ?? ''),
                            'vegzes'      => (string) ($o['vegzes']      ?? ''),
                            'ora_sorszam' => $o['ora_sorszam'] ?? null,
                            'tantargy'    => (string) ($o['tantargy']    ?? ''),
                            'is_csoport'  => $o['is_csoport']  ?? (count($cs) > 1),
                            'terem'       => (string) ($o['terem']       ?? '?'),
                            'tanar'       => (string) ($o['tanar']       ?? '?'),
                            'tanar_nev'   => $o['tanar_nev'] ?? null,
                            'csoportok'   => $norm_cs,
                        ];
                    }
                    json_response(['osztaly' => $result['osztaly'], 'orak' => merge_consecutive_orak($orak)]);
                }
            }
        } catch (\Throwable $e) {
            // ticky_source nem elérhető vagy hiba → üres napirend
        }
    }
    json_response(['osztaly' => $kod, 'orak' => []]);
}

// ─── Tanárnevek ──────────────────────────────────────────────────────
$tanar_map = [];
$source_teacher_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];
$ids = array_unique(array_filter(array_column($orak_raw, 'tanar_id')));
if (!empty($ids)) {
    foreach (sb_get('tanarok', ['id' => 'in.(' . implode(',', $ids) . ')', 'select' => 'id,rovid_nev,nev']) as $t) {
        $rovid = (string) ($t['rovid_nev'] ?? '?');
        $tanar_map[$t['id']] = [
            'rovid_nev' => $rovid,
            'nev' => $t['nev'] ?? ($source_teacher_names[$rovid] ?? null),
        ];
    }
}

// ─── Termek ──────────────────────────────────────────────────────────
$terem_map = [];
$ids = array_unique(array_filter(array_column($orak_raw, 'terem_id')));
if (!empty($ids)) {
    foreach (sb_get('termek', ['id' => 'in.(' . implode(',', $ids) . ')', 'select' => 'id,terem_szam']) as $t) {
        $terem_map[$t['id']] = $t['terem_szam'];
    }
}

// ─── Csoportosítás ───────────────────────────────────────────────────
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
        'terem'    => $terem,
        'tanar'    => $tanar['rovid_nev'] ?? '?',
        'tanar_nev'=> $tanar['nev']       ?? ($source_teacher_names[$tanar['rovid_nev'] ?? ''] ?? null),
        'tantargy' => $tantargy,
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
        'tanar'       => implode(', ', $tanarok_lista),
        'tanar_nev'   => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
        'csoportok'   => $cs,
    ];
}

usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));
$orak = merge_consecutive_orak($orak);

json_response(['osztaly' => $kod, 'orak' => $orak]);
