<?php
// utils/room_sort.php
// Fizikai bejárási sorrend a termekhez.
// Új rend: földszint -> tanári -> műhely folyosó (m17-m22) -> műhely (19-25)
//          -> 1.em -> 2.em -> 3.em -> kollégium -> tornatermek (UTOLSÓ)

function ticky_room_sort_order_list(): array {
    return [
        // ── Földszint ──
        '1',
        'Kt',          // könyvtár

        // ── Tanári folyosó ──
        '14',

        // ── Műhely folyosó (m17-m22) ──
        'm17', 'm18', 'm19', 'm20', 'm21', 'm22',

        // ── Műhely (számozott termek) ──
        '19', '20', '21', '24', '25',

        // ── 1. emelet ──
        '101', '102', '104', '105', '106', '109', '110', '111',

        // ── 2. emelet (206 nincs) ──
        '202', '203', '204', '205', '207', '208', '209', '210', '211',

        // ── 3. emelet ──
        '301', '303', '306', '307', '308', '310',

        // ── Kollégium ──
        'K1', 'K2', 'K3',
        'K101', 'K102', 'K103', 'K104', 'K105', 'K106', 'K107', 'K108',
        'K202', 'K203',
        'K307', 'K314',

        // ── Tornatermek (UTOLSÓ) ──
        'T1', 'T2', 'T3',
    ];
}

function ticky_room_sort_index(string $room): int {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        foreach (ticky_room_sort_order_list() as $idx => $expected) {
            $key = mb_strtolower(trim($expected), 'UTF-8');
            $cache[$key] = $idx;
        }
    }

    $room_key = mb_strtolower(trim($room), 'UTF-8');
    return $cache[$room_key] ?? PHP_INT_MAX;
}

function ticky_sort_rooms(array $rooms): array {
    usort($rooms, static function ($a, $b): int {
        $a_key = is_array($a) ? (string) ($a['terem_szam'] ?? '') : (string) $a;
        $b_key = is_array($b) ? (string) ($b['terem_szam'] ?? '') : (string) $b;

        $ia = ticky_room_sort_index($a_key);
        $ib = ticky_room_sort_index($b_key);

        if ($ia !== $ib) {
            return $ia <=> $ib;
        }

        return strnatcasecmp($a_key, $b_key);
    });

    return $rooms;
}

function ticky_group_rooms_by_section(array $rooms): array {
    $sections = [
        'foldszint'      => ['name' => 'Földszint',        'rooms' => []],
        'tanari'         => ['name' => 'Tanári folyosó',   'rooms' => []],
        'muhely_folyoso' => ['name' => 'Műhely folyosó',   'rooms' => []],
        'muhely'         => ['name' => 'Műhely',           'rooms' => []],
        'emelet_1'       => ['name' => '1. emelet',        'rooms' => []],
        'emelet_2'       => ['name' => '2. emelet',        'rooms' => []],
        'emelet_3'       => ['name' => '3. emelet',        'rooms' => []],
        'kollegium'      => ['name' => 'Kollégium',        'rooms' => []],
        'tornaterem'     => ['name' => 'Tornatermek',      'rooms' => []],
        'egyeb'          => ['name' => 'Egyéb',            'rooms' => []],
    ];

    foreach (ticky_sort_rooms($rooms) as $room) {
        $key = is_array($room) ? (string) ($room['terem_szam'] ?? '') : (string) $room;
        $key_lower = mb_strtolower(trim($key), 'UTF-8');

        if (in_array($key_lower, ['1', 'kt'], true)) {
            $sections['foldszint']['rooms'][] = $room;
        } elseif ($key_lower === '14') {
            $sections['tanari']['rooms'][] = $room;
        } elseif (preg_match('/^m\d+$/', $key_lower)) {
            $sections['muhely_folyoso']['rooms'][] = $room;
        } elseif (in_array($key_lower, ['19', '20', '21', '24', '25'], true)) {
            $sections['muhely']['rooms'][] = $room;
        } elseif (preg_match('/^1\d{2}$/', $key_lower)) {
            $sections['emelet_1']['rooms'][] = $room;
        } elseif (preg_match('/^2\d{2}$/', $key_lower)) {
            $sections['emelet_2']['rooms'][] = $room;
        } elseif (preg_match('/^3\d{2}$/', $key_lower)) {
            $sections['emelet_3']['rooms'][] = $room;
        } elseif (preg_match('/^k/i', $key)) {
            $sections['kollegium']['rooms'][] = $room;
        } elseif (preg_match('/^t\d+$/i', $key)) {
            $sections['tornaterem']['rooms'][] = $room;
        } else {
            $sections['egyeb']['rooms'][] = $room;
        }
    }

    return array_filter($sections, static fn(array $s): bool => !empty($s['rooms']));
}
