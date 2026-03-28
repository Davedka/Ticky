<?php
// api/terem.php
// GET /api/terem/{szam}
// Visszaadja az adott terem aktuális + következő óráját

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

// URL-ből terem szám kinyerése
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params   = match_route('/api/terem/{szam}', $uri);

if ($params === false) {
    json_error('Hiányzó terem szám', 400);
}

$terem_szam = strtoupper(trim($params['szam']));
$nap        = mai_nap();
$ido        = aktualis_ido();

// ─── 1. Terem ID lekérése ──────────────────────────────
$termek = sb_get('termek', [
    'terem_szam' => 'eq.' . $terem_szam,
    'select'     => 'id,terem_szam,emelet',
]);

if (empty($termek)) {
    json_error('Terem nem található: ' . $terem_szam, 404);
}

$terem = $termek[0];

// ─── 2. Hétvége kezelés ───────────────────────────────
if ($nap === 0) {
    json_response([
        'terem'    => $terem_szam,
        'emelet'   => $terem['emelet'],
        'allapot'  => 'szabad',
        'uzenet'   => 'Hétvége – nincs tanítás',
        'aktualis' => null,
        'kovetkezo'=> null,
    ]);
}

// ─── 3. Mai összes óra ebben a teremben ───────────────
$orak = sb_get('orarendek', [
    'terem_id'  => 'eq.' . $terem['id'],
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'id,osztaly,tantargy,kezdes,vegzes,ora_sorszam,tanar_id',
    'order'     => 'kezdes.asc',
]);

// Tanár nevek lekérése (ha van óra)
$tanar_nevek = [];
if (!empty($orak)) {
    $tanar_ids = array_unique(array_column($orak, 'tanar_id'));
    $id_filter = 'in.(' . implode(',', $tanar_ids) . ')';
    $tanarok   = sb_get('tanarok', [
        'id'     => $id_filter,
        'select' => 'id,rovid_nev,nev',
    ]);
    foreach ($tanarok as $t) {
        $tanar_nevek[$t['id']] = $t;
    }
}

// Óra adatok gazdagítása tanár névvel
foreach ($orak as &$o) {
    $t = $tanar_nevek[$o['tanar_id']] ?? null;
    $o['tanar']     = $t['rovid_nev'] ?? '?';
    $o['tanar_nev'] = $t['nev']       ?? null;
    unset($o['tanar_id']);
}
unset($o);

// ─── 4. Aktuális és következő óra meghatározása ───────
$aktualis  = null;
$kovetkezo = null;

foreach ($orak as $ora) {
    $k = substr($ora['kezdes'], 0, 5); // 'HH:MM'
    $v = substr($ora['vegzes'], 0, 5);

    if ($ido >= $k && $ido <= $v) {
        $aktualis = $ora;
    } elseif ($ido < $k && $kovetkezo === null) {
        $kovetkezo = $ora;
    }
}

// ─── 5. Válasz összeállítása ──────────────────────────
if ($aktualis !== null) {
    $k = substr($aktualis['kezdes'], 0, 5);
    $v = substr($aktualis['vegzes'], 0, 5);
    // Hány perc van még hátra?
    $perc_maradt = (strtotime($v) - strtotime($ido)) / 60;

    json_response([
        'terem'        => $terem_szam,
        'emelet'       => $terem['emelet'],
        'allapot'      => 'foglalt',
        'aktualis'     => [
            'ora_sorszam'  => $aktualis['ora_sorszam'],
            'tanar'        => $aktualis['tanar'],
            'tanar_nev'    => $aktualis['tanar_nev'],
            'osztaly'      => $aktualis['osztaly'],
            'tantargy'     => $aktualis['tantargy'],
            'kezdes'       => $k,
            'vegzes'       => $v,
            'perc_maradt'  => max(0, (int) $perc_maradt),
        ],
        'kovetkezo'    => $kovetkezo ? [
            'ora_sorszam' => $kovetkezo['ora_sorszam'],
            'tanar'       => $kovetkezo['tanar'],
            'osztaly'     => $kovetkezo['osztaly'],
            'tantargy'    => $kovetkezo['tantargy'],
            'kezdes'      => substr($kovetkezo['kezdes'], 0, 5),
            'vegzes'      => substr($kovetkezo['vegzes'], 0, 5),
        ] : null,
    ]);
} else {
    json_response([
        'terem'     => $terem_szam,
        'emelet'    => $terem['emelet'],
        'allapot'   => 'szabad',
        'aktualis'  => null,
        'kovetkezo' => $kovetkezo ? [
            'ora_sorszam' => $kovetkezo['ora_sorszam'],
            'tanar'       => $kovetkezo['tanar'],
            'osztaly'     => $kovetkezo['osztaly'],
            'tantargy'    => $kovetkezo['tantargy'],
            'kezdes'      => substr($kovetkezo['kezdes'], 0, 5),
            'vegzes'      => substr($kovetkezo['vegzes'], 0, 5),
        ] : null,
    ]);
}