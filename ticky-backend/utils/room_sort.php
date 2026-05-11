<?php
// utils/room_sort.php
// =============================================================
//  Az iskola termeinek FIZIKAI bejárási sorrendje
//  (úgy, ahogy lépésről lépésre el lehet menni a termekhez).
//
//  Ha új termet veszel fel a rendszerhez, fűzd hozzá a megfelelő
//  helyre a `ticky_room_sort_order_list()`-be, és minden frontend
//  (api/termek, kijelző, admin) automatikusan a helyes sorrendben
//  fogja mutatni.
// =============================================================


/**
 * A bejárási sorrend listája (case-insensitive a kereséshez).
 * A lista *sorrendje* dönti el a megjelenítés sorrendjét.
 */
function ticky_room_sort_order_list(): array {
    return [
        // ── Tornatermek (tesi) ──
        // FIGYELEM: ha új tornaterem épül 1 emelettel feljebb,
        // azokat fűzd ide hozzá (t4, t5, stb. vagy más néven)
        't1', 't2', 't3',

        // ── Földszint, bejárattól jobbra ──
        'Kt', '1',

        // ── Tanári folyosó vége ──
        '14',

        // ── Műhely folyosó ──
        // jobb oldalról a folyosó végéig, majd vissza balon
        // ELLENŐRIZD: ez most numerikus sorrendben van, lehet hogy
        // a fizikai sorrend más. Cseréld át tetszés szerint.
        '19', '20', '21', '24', '25',

        // ── 1. emelet (újonnan épült szárny) ──
        '101', '102', '104', '105', '106', '109', '110', '111',

        // ── 2. emelet (202-211, 206 kivétel) ──
        '202', '203', '204', '205', '207', '208', '209', '210', '211',

        // ── 3. emelet ──
        '301', '303', '306', '307', '308', '310',

        // ── Kollégium földszint ──
        'k1', 'k2', 'k3',

        // ── Kollégium 1. emelet (k101-k108) ──
        'k101', 'k102', 'k103', 'k104', 'k105', 'k106', 'k107', 'k108',

        // ── Kollégium 2. emelet ──
        'k202', 'k203',

        // ── Kollégium 3. emelet ──
        'k307', 'k314',
    ];
}


/**
 * Visszaadja egy adott terem sort indexét.
 * Listán kívüli termek a végére kerülnek (99999).
 *
 * Cache-elt – csak egyszer építi fel a hash-map-et requestenként.
 */
function ticky_room_sort_index(string $room): int {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        foreach (ticky_room_sort_order_list() as $idx => $name) {
            $cache[strtolower(trim($name))] = $idx;
        }
    }

    $key = strtolower(trim($room));
    return $cache[$key] ?? 99999;
}


/**
 * Termek tömb rendezése a fizikai bejárási sorrendbe.
 * 
 * @param array  $rooms  ['terem_szam' => '101', ...] sorok tömbje
 * @param string $key    melyik mezőben van a teremszám (default: 'terem_szam')
 * @return array         rendezett tömb (új sorrendben)
 */
function ticky_sort_rooms(array $rooms, string $key = 'terem_szam'): array {
    usort($rooms, function ($a, $b) use ($key) {
        $aName = (string) ($a[$key] ?? '');
        $bName = (string) ($b[$key] ?? '');

        $aIdx = ticky_room_sort_index($aName);
        $bIdx = ticky_room_sort_index($bName);

        if ($aIdx !== $bIdx) {
            return $aIdx <=> $bIdx;
        }

        // Listán kívüli termek között natural-case sort
        // (pl. ha új teremszám kerül a DB-be ami nincs a listában)
        return strnatcasecmp($aName, $bName);
    });

    return $rooms;
}


/**
 * Termek csoportosítása emelet/szekció szerint, a sort orderből
 * "felismerve" a logikai szekciókat. Hasznos lehet a kijelzőhöz,
 * ha vizuális szeparátorokat akarsz közéjük tenni.
 *
 * Visszaad egy tömböt: [
 *   ['cim' => 'Földszint', 'termek' => [...]],
 *   ['cim' => '1. emelet', 'termek' => [...]],
 *   ...
 * ]
 */
function ticky_group_rooms_by_section(array $rooms, string $key = 'terem_szam'): array {
    $sorted = ticky_sort_rooms($rooms, $key);

    $sections = [
        'Tornatermek'        => [],
        'Földszint'          => [],
        'Műhely'             => [],
        '1. emelet'          => [],
        '2. emelet'          => [],
        '3. emelet'          => [],
        'Kollégium földszint'=> [],
        'Kollégium 1. em.'   => [],
        'Kollégium 2. em.'   => [],
        'Kollégium 3. em.'   => [],
        'Egyéb'              => [],
    ];

    foreach ($sorted as $room) {
        $name = strtolower(trim((string) ($room[$key] ?? '')));
        $section = ticky_room_section_name($name);
        $sections[$section][] = $room;
    }

    // Csak a nem üres szekciókat adjuk vissza
    $result = [];
    foreach ($sections as $cim => $items) {
        if (!empty($items)) {
            $result[] = ['cim' => $cim, 'termek' => $items];
        }
    }

    return $result;
}


/**
 * Eldönti, hogy egy teremszám melyik szekcióba tartozik.
 */
function ticky_room_section_name(string $room): string {
    $r = strtolower(trim($room));

    // Tornatermek
    if (preg_match('/^t\d+$/', $r)) return 'Tornatermek';

    // Kollégium
    if (preg_match('/^k(\d+)$/', $r, $m)) {
        $n = (int) $m[1];
        if ($n < 100) return 'Kollégium földszint';
        if ($n < 200) return 'Kollégium 1. em.';
        if ($n < 300) return 'Kollégium 2. em.';
        return 'Kollégium 3. em.';
    }

    // Műhely (M prefix vagy 19-29 közötti termek)
    if (preg_match('/^m\d+$/', $r)) return 'Műhely';
    if (in_array($r, ['19','20','21','22','23','24','25','26','27','28','29'], true)) return 'Műhely';

    // Földszinti speciális
    if (in_array($r, ['kt','1','14'], true)) return 'Földszint';

    // Számozott emeletek
    if (ctype_digit($r)) {
        $n = (int) $r;
        if ($n < 100)  return 'Földszint';
        if ($n < 200)  return '1. emelet';
        if ($n < 300)  return '2. emelet';
        if ($n < 400)  return '3. emelet';
    }

    return 'Egyéb';
}
