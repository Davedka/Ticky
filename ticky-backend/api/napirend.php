<?php
// api/napirend.php
// GET /api/napirend/{szam}           → mai napirend
// GET /api/napirend/{szam}?nap=1     → adott nap (1=H..5=P)
// GET /api/napirend/{szam}?nap=heten → egész heti napirend

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/napirend/{szam}', $uri);

if ($params === false) {
    json_error('Hiányzó terem szám', 400);
}

$terem_szam = strtoupper(trim($params['szam']));
$nap_param  = $_GET['nap'] ?? null;

// ─── 1. Terem lekérése ────────────────────────────────
$termek = sb_get('termek', [
    'terem_szam' => 'eq.' . $terem_szam,
    'select'     => 'id,terem_szam,emelet',
]);

if (empty($termek)) {
    json_error('Terem nem található: ' . $terem_szam, 404);
}

$terem = $termek[0];

// ─── 2. Nap(ok) meghatározása ─────────────────────────
$het_egeszben = ($nap_param === 'heten');
$orarendek_params = [
    'terem_id' => 'eq.' . $terem['id'],
    'aktiv'    => 'eq.true',
    'select'   => 'osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja,tanar_id',
    'order'    => 'het_napja.asc,kezdes.asc',
];

if ($het_egeszben) {
    // Egész hét: 1-5
    $orarendek_params['het_napja'] = 'in.(1,2,3,4,5)';
} else {
    $nap = $nap_param !== null ? (int) $nap_param : mai_nap();
    if ($nap < 1 || $nap > 5) {
        json_response([
            'terem'    => $terem_szam,
            'nap'      => $nap,
            'uzenet'   => 'Nincs tanítás (hétvége)',
            'orak'     => [],
        ]);
    }
    $orarendek_params['het_napja'] = 'eq.' . $nap;
}

// ─── 3. Órák lekérése ─────────────────────────────────
$orak = sb_get('orarendek', $orarendek_params);

// ─── 4. Tanárnevek ────────────────────────────────────
$tanar_map = [];
if (!empty($orak)) {
    $tanar_ids = array_unique(array_column($orak, 'tanar_id'));
    $tanarok   = sb_get('tanarok', [
        'id'     => 'in.(' . implode(',', $tanar_ids) . ')',
        'select' => 'id,rovid_nev,nev',
    ]);
    foreach ($tanarok as $t) $tanar_map[$t['id']] = $t;
}

// ─── 5. Válasz összeállítása ──────────────────────────
$NAP_NEVEK = [1=>'Hétfő', 2=>'Kedd', 3=>'Szerda', 4=>'Csütörtök', 5=>'Péntek'];
$ido       = aktualis_ido();

if ($het_egeszben) {
    // Csoportosítás napok szerint
    $het = [];
    foreach ($orak as $o) {
        $d  = $o['het_napja'];
        $tr = $tanar_map[$o['tanar_id']] ?? null;
        $het[$d][] = [
            'ora_sorszam' => $o['ora_sorszam'],
            'tanar'       => $tr['rovid_nev'] ?? '?',
            'tanar_nev'   => $tr['nev']       ?? null,
            'osztaly'     => $o['osztaly'],
            'tantargy'    => $o['tantargy'],
            'kezdes'      => substr($o['kezdes'], 0, 5),
            'vegzes'      => substr($o['vegzes'], 0, 5),
        ];
    }

    $napok = [];
    for ($d = 1; $d <= 5; $d++) {
        $napok[] = [
            'nap'       => $d,
            'nap_neve'  => $NAP_NEVEK[$d],
            'orak'      => $het[$d] ?? [],
        ];
    }

    json_response([
        'terem'  => $terem_szam,
        'emelet' => $terem['emelet'],
        'het'    => $napok,
    ]);
} else {
    $result = [];
    foreach ($orak as $o) {
        $tr  = $tanar_map[$o['tanar_id']] ?? null;
        $k   = substr($o['kezdes'], 0, 5);
        $v   = substr($o['vegzes'], 0, 5);
        $result[] = [
            'ora_sorszam' => $o['ora_sorszam'],
            'tanar'       => $tr['rovid_nev'] ?? '?',
            'tanar_nev'   => $tr['nev']       ?? null,
            'osztaly'     => $o['osztaly'],
            'tantargy'    => $o['tantargy'],
            'kezdes'      => $k,
            'vegzes'      => $v,
            'folyamatban' => ($ido >= $k && $ido <= $v),
        ];
    }

    json_response([
        'terem'    => $terem_szam,
        'emelet'   => $terem['emelet'],
        'nap'      => $nap ?? mai_nap(),
        'nap_neve' => $NAP_NEVEK[$nap ?? mai_nap()] ?? '',
        'ido'      => $ido,
        'orak'     => $result,
    ]);
}