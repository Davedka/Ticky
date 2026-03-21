<?php
// api/tanar_orarend.php
// GET /api/tanar/{kod}/orarend  → adott tanár mai napirendje
// GET /api/tanar/{kod}/orarend?nap=heten → egész heti napirend

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/tanar/{kod}/orarend', $uri);

if ($params === false) {
    json_error('Hiányzó tanár kód', 400);
}

$tanar_kod = strtoupper(trim($params['kod']));
$nap_param = $_GET['nap'] ?? null;
$het_egeszben = ($nap_param === 'heten');

// ─── Tanár ID lekérése ────────────────────────────────
$tanarok = sb_get('tanarok', [
    'rovid_nev' => 'eq.' . $tanar_kod,
    'select'    => 'id,rovid_nev,nev',
]);

if (empty($tanarok)) {
    json_error('Tanár nem található: ' . $tanar_kod, 404);
}

$tanar = $tanarok[0];

// ─── Órarendek lekérése ───────────────────────────────
$ora_params = [
    'tanar_id' => 'eq.' . $tanar['id'],
    'aktiv'    => 'eq.true',
    'select'   => 'osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja,terem_id',
    'order'    => 'het_napja.asc,kezdes.asc',
];

if ($het_egeszben) {
    $ora_params['het_napja'] = 'in.(1,2,3,4,5)';
} else {
    $nap = ($nap_param !== null) ? (int)$nap_param : mai_nap();
    if ($nap < 1 || $nap > 5) {
        json_response([
            'tanar'    => $tanar_kod,
            'tanar_nev'=> $tanar['nev'],
            'nap'      => $nap,
            'uzenet'   => 'Nincs tanítás (hétvége)',
            'orak'     => [],
        ]);
    }
    $ora_params['het_napja'] = 'eq.' . $nap;
}

$orak = sb_get('orarendek', $ora_params);

// ─── Termek nevei ─────────────────────────────────────
$terem_map = [];
if (!empty($orak)) {
    $terem_ids = array_unique(array_column($orak, 'terem_id'));
    $termek    = sb_get('termek', [
        'id'     => 'in.(' . implode(',', $terem_ids) . ')',
        'select' => 'id,terem_szam',
    ]);
    foreach ($termek as $t) $terem_map[$t['id']] = $t['terem_szam'];
}

// ─── Válasz összeállítása ─────────────────────────────
$NAP_NEVEK = [1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
$ido       = aktualis_ido();

if ($het_egeszben) {
    $het = [];
    foreach ($orak as $o) {
        $d = $o['het_napja'];
        $het[$d][] = [
            'ora_sorszam' => $o['ora_sorszam'],
            'terem'       => $terem_map[$o['terem_id']] ?? '?',
            'osztaly'     => $o['osztaly'],
            'tantargy'    => $o['tantargy'],
            'kezdes'      => substr($o['kezdes'], 0, 5),
            'vegzes'      => substr($o['vegzes'], 0, 5),
        ];
    }
    $napok = [];
    for ($d = 1; $d <= 5; $d++) {
        $napok[] = [
            'nap'      => $d,
            'nap_neve' => $NAP_NEVEK[$d],
            'orak'     => $het[$d] ?? [],
        ];
    }
    json_response([
        'tanar'     => $tanar_kod,
        'tanar_nev' => $tanar['nev'],
        'het'       => $napok,
    ]);
} else {
    $result = [];
    foreach ($orak as $o) {
        $k = substr($o['kezdes'], 0, 5);
        $v = substr($o['vegzes'], 0, 5);
        $result[] = [
            'ora_sorszam' => $o['ora_sorszam'],
            'terem'       => $terem_map[$o['terem_id']] ?? '?',
            'osztaly'     => $o['osztaly'],
            'tantargy'    => $o['tantargy'],
            'kezdes'      => $k,
            'vegzes'      => $v,
            'folyamatban' => ($ido >= $k && $ido <= $v),
        ];
    }
    json_response([
        'tanar'     => $tanar_kod,
        'tanar_nev' => $tanar['nev'],
        'nap'       => $nap ?? mai_nap(),
        'nap_neve'  => $NAP_NEVEK[$nap ?? mai_nap()] ?? '',
        'ido'       => $ido,
        'orak'      => $result,
    ]);
}
