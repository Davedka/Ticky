<?php
// api/termek.php
// GET /api/termek
// Visszaadja az összes terem listáját, opcionálisan mai állapottal

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$nap = mai_nap();
$ido = aktualis_ido();

// ─── 1. Összes terem ─────────────────────────────────
$termek = sb_get('termek', [
    'select' => 'id,terem_szam,emelet,aktiv',
    'order'  => 'terem_szam.asc',
]);

if (empty($termek)) {
    json_response(['termek' => [], 'count' => 0]);
}

// ─── 2. Ha ?allapot=1 query param, mai foglaltságot is mutat ──
$allapot_kell = isset($_GET['allapot']) && $_GET['allapot'] === '1';

if ($allapot_kell && $nap > 0) {
    // Lekérjük az összes mai aktív órát egyszerre
    $terem_ids = array_column($termek, 'id');
    $id_filter = 'in.(' . implode(',', $terem_ids) . ')';

    $orak = sb_get('orarendek', [
        'terem_id'  => $id_filter,
        'het_napja' => 'eq.' . $nap,
        'aktiv'     => 'eq.true',
        'kezdes'    => 'lte.' . $ido . ':00',
        'vegzes'    => 'gte.' . $ido . ':00',
        'select'    => 'terem_id,osztaly,tantargy,kezdes,vegzes,tanar_id',
    ]);

    // Tanárokat is lekérjük
    $tanar_ids = array_unique(array_column($orak, 'tanar_id'));
    $tanar_map = [];
    if (!empty($tanar_ids)) {
        $tanarok = sb_get('tanarok', [
            'id'     => 'in.(' . implode(',', $tanar_ids) . ')',
            'select' => 'id,rovid_nev',
        ]);
        foreach ($tanarok as $t) $tanar_map[$t['id']] = $t['rovid_nev'];
    }

    // Foglalt termek map
    $foglalt_map = [];
    foreach ($orak as $o) {
        $foglalt_map[$o['terem_id']] = [
            'tanar'    => $tanar_map[$o['tanar_id']] ?? '?',
            'osztaly'  => $o['osztaly'],
            'tantargy' => $o['tantargy'],
            'kezdes'   => substr($o['kezdes'], 0, 5),
            'vegzes'   => substr($o['vegzes'], 0, 5),
        ];
    }

    // Termek kiegészítése állapottal
    foreach ($termek as &$terem) {
        $terem['allapot'] = isset($foglalt_map[$terem['id']]) ? 'foglalt' : 'szabad';
        $terem['aktualis'] = $foglalt_map[$terem['id']] ?? null;
        unset($terem['id']); // ID nem kell a frontendnek
    }
} else {
    foreach ($termek as &$terem) {
        unset($terem['id']);
    }
}
unset($terem);

json_response([
    'termek'  => $termek,
    'count'   => count($termek),
    'nap'     => $nap,
    'ido'     => $ido,
]);
