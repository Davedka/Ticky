<?php
// api/termek.php
//
// A legforróbb végpont: a kijelző 30 másodpercenként, a termek oldal
// percenként kéri. Ezért minden adatbázis-lekérdezés cache-elve van.
//
// A cache NYERS sorokat tárol, nem kész választ: a nap, az idő és az aktív
// szünet minden kérésnél frissen számolódik, így a cache nem mutathat
// elavult órát. A cache-t a publikálás üríti (ticky_cache_urit()).

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/room_sort.php';
require_once __DIR__ . '/../utils/valasz_cache.php';
require_once __DIR__ . '/../utils/terem_allapot.php';
require_once __DIR__ . '/../utils/orarend_nap_cache.php';
$aktiv_szunet = ticky_aktiv_szunet_nev();

handle_cors();

$nap = mai_nap();
$ido = aktualis_ido();

// ─── Összes terem (cache-elve) ──────────────────────────────────────
$termek_raw = [];
try {
    $termek_raw = ticky_termek_lista();
} catch (\Throwable $e) {
    $termek_raw = [];
}

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

                    // ── Fizikai bejárási sorrend a fallback ágban is ──
                    $fallback = ticky_sort_rooms($fallback);

                    json_response([
                        'termek' => $fallback,
                        'count'  => count($fallback),
                        'nap'    => $nap,
                        'ido'    => $ido,
                        'szunet' => $aktiv_szunet,
                        'source' => 'fallback',
                    ]);
                }
            }
        } catch (\Throwable $e) {
        }
    }
    json_response(['termek' => [], 'count' => 0, 'nap' => $nap, 'ido' => $ido, 'szunet' => $aktiv_szunet]);
}

$termek = $termek_raw;

// Szünet alatt (akár hétköznap) nincs tanítás → ne számoljunk foglaltságot,
// minden terem szabad. A hétvégét a $nap > 0 már kezeli.
$allapot_kell = isset($_GET['allapot']) && $_GET['allapot'] === '1';
if ($allapot_kell && $nap > 0 && $aktiv_szunet === null) {
    $terem_ids = array_column($termek, 'id');
    $foglalt_map = [];

    if (!empty($terem_ids)) {
        // A nap ÖSSZES aktív órája egyetlen, cache-elt lekérdezéssel.
        //
        // Korábban itt idő szerint szűrt lekérdezés ment ki, ami percenként
        // más – tehát cache-elhetetlen volt. A napi lekérdezés napon belül
        // állandó, a "most melyik óra" szűrés PHP-ban történik
        // (utils/terem_allapot.php).
        //
        // Ugyanezt a bejegyzést használja a /api/terem/{szam} is.
        $orak = [];
        try {
            $orak = ticky_nap_orai($nap);
        } catch (\Throwable $e) {
            $orak = [];
        }

        // A tanárok listája ritkán változik, és kicsi – egyben cache-elve,
        // így nincs szükség kérésenkénti in.(...) lekérdezésre.
        $tanar_map = [];
        if (!empty($orak)) {
            try {
                $tanar_map = ticky_tanar_nev_map(ticky_tanarok_mind());
            } catch (\Throwable $e) {
            }
        }

        $foglalt_map = ticky_terem_foglalt_map($orak, $tanar_map, $ido, $terem_ids);
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

$termek = ticky_sort_rooms($termek);

json_response([
    'termek' => $termek,
    'count'  => count($termek),
    'nap'    => $nap,
    'ido'    => $ido,
    'szunet' => $aktiv_szunet,
]);
