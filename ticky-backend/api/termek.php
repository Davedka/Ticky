<?php
// api/termek.php
// GET /api/termek
// GET /api/termek?allapot=1  – élő foglaltság is

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$nap = mai_nap();
$ido = aktualis_ido();

// ─── Összes terem (Supabase) ────────────────────────────────────────
$termek_raw = [];
try {
    $termek_raw = sb_get('termek', [
        'select' => 'id,terem_szam,emelet',
        'order'  => 'terem_szam.asc',
    ]);
} catch (\Throwable $e) {
    $termek_raw = [];
}

// ─── Fallback: tanárok.js, ha a DB üres vagy elérhetetlen ───────────
if (empty($termek_raw)) {
    $tanarok_source = __DIR__ . '/../utils/tanarok_source.php';
    if (is_file($tanarok_source)) {
        try {
            require_once $tanarok_source;
            if (function_exists('ticky_source_unique_rooms')) {
                $rooms = ticky_source_unique_rooms();
                if (!empty($rooms)) {
                    $fallback = array_map(static fn($r) => [
                        'terem_szam' => (string) $r,
                        'emelet'     => null,
                    ], $rooms);
                    usort($fallback, static fn($a, $b) => strcmp((string) $a['terem_szam'], (string) $b['terem_szam']));
                    json_response([
                        'termek' => $fallback,
                        'count'  => count($fallback),
                        'nap'    => $nap,
                        'ido'    => $ido,
                        'source' => 'fallback',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // forrás nem elérhető – üres listát adunk vissza
        }
    }
    json_response(['termek' => [], 'count' => 0, 'nap' => $nap, 'ido' => $ido]);
}

$termek = $termek_raw;

// ─── Ha ?allapot=1 → foglaltság is ──────────────────────────────────
$allapot_kell = isset($_GET['allapot']) && $_GET['allapot'] === '1';

if ($allapot_kell && $nap > 0) {
    $terem_ids = array_column($termek, 'id');

    // FIX: in.() üres lista → Supabase 400 hibát dob → ezért checkeljük előre
    $foglalt_map = [];

    if (!empty($terem_ids)) {
        $id_filter = 'in.(' . implode(',', $terem_ids) . ')';

        $orak = [];
        try {
            $orak = sb_get('orarendek', [
                'terem_id'  => $id_filter,
                'het_napja' => 'eq.' . $nap,
                'aktiv'     => 'eq.true',
                'kezdes'    => 'lte.' . $ido . ':00',
                'vegzes'    => 'gte.' . $ido . ':00',
                'select'    => 'terem_id,osztaly,tantargy,kezdes,vegzes,tanar_id',
            ]);
        } catch (\Throwable $e) {
            $orak = [];
        }

        // Tanárnevek lekérése – csak ha van aktív óra
        $tanar_map = [];
        if (!empty($orak)) {
            $tanar_ids = array_unique(array_filter(array_column($orak, 'tanar_id')));
            if (!empty($tanar_ids)) {
                try {
                    $tanarok = sb_get('tanarok', [
                        'id'     => 'in.(' . implode(',', $tanar_ids) . ')',
                        'select' => 'id,rovid_nev',
                    ]);
                    foreach ($tanarok as $t) {
                        $tanar_map[$t['id']] = $t['rovid_nev'];
                    }
                } catch (\Throwable $e) {
                    // tanár lookup hiba – kód marad '?'
                }
            }
        }

        foreach ($orak as $o) {
            $foglalt_map[$o['terem_id']] = [
                'tanar'    => $tanar_map[$o['tanar_id']] ?? '?',
                'osztaly'  => $o['osztaly'],
                'tantargy' => $o['tantargy'],
                'kezdes'   => substr($o['kezdes'], 0, 5),
                'vegzes'   => substr($o['vegzes'], 0, 5),
            ];
        }
    }

    foreach ($termek as &$terem) {
        $terem['allapot']  = isset($foglalt_map[$terem['id']]) ? 'foglalt' : 'szabad';
        $terem['aktualis'] = $foglalt_map[$terem['id']] ?? null;
        unset($terem['id']);
    }
    unset($terem);

} else {
    foreach ($termek as &$terem) {
        unset($terem['id']);
    }
    unset($terem);
}

json_response([
    'termek' => $termek,
    'count'  => count($termek),
    'nap'    => $nap,
    'ido'    => $ido,
]);
