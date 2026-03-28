<?php
// api/tanar_orarend.php
// GET /api/tanar/{kod}/orarend
// Visszaadja a tanár mai órarendjét, csoportbontásos órákat összevonva.
//
// Csoportbontás logika:
//   Az importer a "12.c/12.b" osztályokat és "204/25" termeket külön sorokba
//   bontja. Ez az endpoint ugyanolyan kezdes+vegzes időpontú sorokat egyetlen
//   rekordba vonja össze, és jelöli, hogy csoportbontásos-e az az óra.
//
// Válasz formátum:
// {
//   "tanar_nev": "Példa Péter",
//   "orak": [
//     {
//       "kezdes": "08:20", "vegzes": "09:05",
//       "ora_sorszam": 2, "tantargy": "mt",
//       "is_csoport": false,
//       "terem": "207", "osztaly": "9.a",
//       "csoportok": [{"terem":"207","osztaly":"9.a"}]
//     },
//     {
//       "kezdes": "10:15", "vegzes": "11:00",
//       "ora_sorszam": 4, "tantargy": "nny",
//       "is_csoport": true,
//       "terem": "110 / 202",  // összesített megjelenítéshez
//       "osztaly": "11.d, 11.f",
//       "csoportok": [
//         {"terem":"110","osztaly":"11.d"},
//         {"terem":"202","osztaly":"11.f"}
//       ]
//     }
//   ]
// }

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

// ── Tanár kód kinyerése az URL-ből ──────────────────────
// Router: /api/tanar/{kod}/orarend
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/tanar/{kod}/orarend', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó tanár kód', 400);
}

$kod = strtoupper(urldecode($params['kod']));
$nap = mai_nap(); // 1=Hétfő … 5=Péntek

// ── Hétvége kezelés ─────────────────────────────────────
if ($nap === 0) {
    json_response([
        'tanar_nev' => null,
        'orak'      => [],
        'uzenet'    => 'Hétvége – nincs tanítás',
    ]);
}

// ── Tanár keresés ────────────────────────────────────────
$tanarok = sb_get('tanarok', [
    'rovid_nev' => 'eq.' . $kod,
    'select'    => 'id,rovid_nev,nev',
]);

if (empty($tanarok)) {
    json_error('Tanár nem található: ' . $kod, 404);
}

$tanar    = $tanarok[0];
$tanar_id = $tanar['id'];

// ── Órarend lekérés a mai napra ──────────────────────────
// JOIN: orarendek → termek (terem_szam)
$orak_raw = sb_get('orarendek', [
    'tanar_id'  => 'eq.' . $tanar_id,
    'het_napja' => 'eq.' . $nap,
    'aktiv'     => 'eq.true',
    'select'    => 'ora_sorszam,kezdes,vegzes,osztaly,tantargy,termek(terem_szam)',
    'order'     => 'kezdes.asc,ora_sorszam.asc',
]);

// ── Csoportosítás: azonos időszak = csoportbontásos óra ─
//
// Kulcs: "kezdes_vegzes" (pl. "10:15_11:00")
// Egy kulcsra több sor kerülhet, ha az importer szétbontotta
// a "12.c/12.b" osztályokat vagy a "110/202" termeket.
//
$csoportok_map = []; // ['10:15_11:00' => {...}, ...]

foreach ($orak_raw as $ora) {
    $terem   = $ora['termek']['terem_szam'] ?? '?';
    $osztaly = $ora['osztaly']              ?? '?';
    $kezdes  = $ora['kezdes']               ?? '';
    $vegzes  = $ora['vegzes']               ?? '';
    $key     = $kezdes . '_' . $vegzes;

    if (!isset($csoportok_map[$key])) {
        // Első bejegyzés az adott időszakra
        $csoportok_map[$key] = [
            'kezdes'      => $kezdes,
            'vegzes'      => $vegzes,
            'ora_sorszam' => $ora['ora_sorszam'] ?? null,
            'tantargy'    => $ora['tantargy']    ?? '',
            'csoportok'   => [],
        ];
    }

    // Duplikátum szűrés (ha az API kétszer adná vissza ugyanazt a sor)
    $mar_van = false;
    foreach ($csoportok_map[$key]['csoportok'] as $c) {
        if ($c['terem'] === $terem && $c['osztaly'] === $osztaly) {
            $mar_van = true;
            break;
        }
    }

    if (!$mar_van) {
        $csoportok_map[$key]['csoportok'][] = [
            'terem'   => $terem,
            'osztaly' => $osztaly,
        ];
    }
}

// ── Összesített megjelenítési mezők hozzáadása ───────────
//
// is_csoport: true  → csoportbontásos (2+ különböző alcsoport)
// terem:      "110 / 202"   (összesített, megjelenítéshez)
// osztaly:    "11.d, 11.f"  (összesített, megjelenítéshez)
//
$orak = [];

foreach ($csoportok_map as $o) {
    $csoportok   = $o['csoportok'];
    $is_csoport  = count($csoportok) > 1;

    // Egyedi termek és osztályok (eredeti sorrend megőrzve)
    $termek_lista   = [];
    $osztalyok_lista = [];
    foreach ($csoportok as $c) {
        if (!in_array($c['terem'],   $termek_lista,   true)) $termek_lista[]   = $c['terem'];
        if (!in_array($c['osztaly'], $osztalyok_lista, true)) $osztalyok_lista[] = $c['osztaly'];
    }

    $orak[] = [
        'kezdes'      => $o['kezdes'],
        'vegzes'      => $o['vegzes'],
        'ora_sorszam' => $o['ora_sorszam'],
        'tantargy'    => $o['tantargy'],
        'is_csoport'  => $is_csoport,
        // Összesített mezők a sima (nem-csoport) nézethez és a gyors megjelenítéshez
        'terem'       => implode(' / ', $termek_lista),
        'osztaly'     => implode(', ', $osztalyok_lista),
        // Részletes alcsoport lista a csoportbontásos megjelenítőhöz
        'csoportok'   => $csoportok,
    ];
}

// Rendezés kezdési idő szerint (a MAP nem garantálja a sorrendet)
usort($orak, fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));

// ── Válasz ───────────────────────────────────────────────
json_response([
    'tanar_nev' => $tanar['nev'] ?? null,
    'orak'      => $orak,
]);
