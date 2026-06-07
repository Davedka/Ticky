<?php
// utils/csoport_terkep.php
// AUTOMATIKUSAN GENERÁLVA az orarend_csoportonkent.xlsx-ből.
// Csak azokat a (osztály, nap, óra-kezdés) sávokat tartalmazza, ahol
// NEM minden csoportnak van órája (a többi csoport lyukas).
// Kulcs: osztaly => nap(1-5) => kezdes('HH:MM') => [jelenlévő csoportok].
// Újrageneráláshoz futtasd ugyanazt a kinyerést az Excelből.

function ticky_csoport_partial_map(): array {
    static $map = null;
    if ($map !== null) { return $map; }
    $map = [
        '10.a' => [
            1 => ['12:55' => [2]],
            4 => ['13:40' => [2]],
        ],
        '10.b' => [
            2 => ['12:55' => [2]],
        ],
        '10.c' => [
            2 => ['13:40' => [2]],
        ],
        '10.d' => [
            4 => ['13:40' => [2]],
        ],
        '10.e' => [
            2 => ['13:40' => [2]],
            3 => ['13:40' => [2]],
        ],
        '10.f' => [
            1 => ['07:30' => [2]],
            4 => ['13:40' => [2]],
        ],
        '11.a' => [
            1 => ['12:55' => [2]],
            3 => ['12:55' => [2]],
        ],
        '11.b' => [
            1 => ['12:55' => [2]],
            2 => ['07:30' => [2], '09:15' => [2], '10:15' => [2], '11:10' => [2], '12:05' => [2]],
            3 => ['13:40' => [2]],
            5 => ['11:10' => [2], '12:05' => [2], '12:55' => [2]],
        ],
        '11.c' => [
            2 => ['12:55' => [2]],
        ],
        '11.d' => [
            1 => ['07:30' => [2]],
            2 => ['07:30' => [2], '08:20' => [2], '11:10' => [2], '12:05' => [2], '12:55' => [2]],
            3 => ['07:30' => [2], '08:20' => [2], '09:15' => [2], '12:05' => [2]],
            4 => ['07:30' => [2], '08:20' => [2], '10:15' => [2], '11:10' => [2], '12:05' => [2]],
        ],
        '11.e' => [
            1 => ['12:05' => [2], '12:55' => [2]],
            4 => ['07:30' => [2]],
        ],
        '11.f' => [
            3 => ['13:40' => [2]],
            4 => ['13:40' => [2]],
        ],
        '12.a' => [
            2 => ['12:55' => [2], '13:40' => [2]],
        ],
        '12.b' => [
            1 => ['12:55' => [2], '13:40' => [2]],
            2 => ['07:30' => [2], '08:20' => [2], '09:15' => [2], '13:40' => [2]],
            5 => ['07:30' => [2]],
        ],
        '12.c' => [
            3 => ['13:40' => [2]],
        ],
        '12.d' => [
            2 => ['12:55' => [2], '13:40' => [2]],
            4 => ['12:55' => [2]],
        ],
        '12.e' => [
            2 => ['12:55' => [2]],
            3 => ['13:40' => [2]],
        ],
        '12.f' => [
            5 => ['12:55' => [2]],
        ],
        '13.a' => [
            4 => ['13:40' => [2]],
        ],
        '13.b' => [
            1 => ['12:55' => [2]],
            2 => ['12:55' => [2]],
            4 => ['11:10' => [2], '12:05' => [2]],
        ],
        '13.c' => [
            3 => ['07:30' => [2]],
        ],
        '13.d' => [
            2 => ['12:55' => [2], '13:40' => [2]],
            5 => ['12:55' => [2]],
        ],
        '13.e' => [
            4 => ['13:40' => [2]],
        ],
        '9.a' => [
            4 => ['07:30' => [2], '13:40' => [2]],
        ],
        '9.c' => [
            2 => ['08:20' => [2], '09:15' => [2]],
        ],
        '9.d' => [
            4 => ['13:40' => [2]],
        ],
        '9.e' => [
            2 => ['07:30' => [2]],
        ],
        'HT_13.ir' => [
            1 => ['13:40' => [2]],
            2 => ['07:30' => [2]],
        ],
    ];
    return $map;
}

// ─────────────────────────────────────────────────────────────────
// Segédfüggvények a részleges (csak-egyik-csoport) sávok kezeléséhez.
// ─────────────────────────────────────────────────────────────────

// Visszaadja, mely csoportok vannak jelen az adott sávban, HA nem mind.
// Üres tömb = teljes osztály (vagy normál bontás) → nincs külön címke.
function ticky_reszleges_csoportok(string $class, int $day, string $start): array {
    $map   = ticky_csoport_partial_map();
    $start = substr($start, 0, 5);
    return array_values(array_map('intval', $map[$class][$day][$start] ?? []));
}

// Emberi címke: "csak a 2. csoport" / "csak az 1. csoport".
function ticky_reszleges_szoveg(array $groups): string {
    if ($groups === []) {
        return '';
    }
    if (count($groups) === 1) {
        $n   = (int) $groups[0];
        $art = in_array($n, [1, 5], true) ? 'az' : 'a';
        return 'csak ' . $art . ' ' . $n . '. csoport';
    }
    $parts = array_map(static fn($n) => (int) $n . '.', $groups);
    return 'csak a ' . implode(' és ', $parts) . ' csoport';
}
