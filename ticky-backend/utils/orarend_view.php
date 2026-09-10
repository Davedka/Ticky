<?php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/osztaly.php';

if (is_file(__DIR__ . '/csoport_terkep.php')) {
    require_once __DIR__ . '/csoport_terkep.php';
}

const TICKY_VIEW_FETCH_LIMIT = '5000';

// ─────────────────────────────────────────────────────────────────
// Lekérdezések
// ─────────────────────────────────────────────────────────────────

/** Az aktív órarend sorainak kiválasztása, beágyazott tanár- és teremnévvel. */
function ticky_view_select_columns(): string
{
    return 'het_napja,ora_sorszam,kezdes,vegzes,osztaly,tantargy,csoport,'
        . 'tanarok(rovid_nev,nev),termek(terem_szam)';
}

/**
 * Egy osztály teljes heti órarendje az aktív verzióból.
 *
 * @return array{osztaly:string,sorok:array}|null null, ha az osztály egyáltalán
 *         nem szerepel az aktív órarendben (ilyenkor a hívó a tanárok.js-re esik vissza)
 */
function ticky_view_class_rows(string $requested_class): ?array
{
    $requested_class = trim($requested_class);
    if ($requested_class === '') {
        return null;
    }

    $rows = ticky_view_fetch_class_rows($requested_class);
    if ($rows !== []) {
        return ['osztaly' => $requested_class, 'sorok' => $rows];
    }

    // Kis/nagybetű eltérés esetén megkeressük a tényleges írásmódot.
    $canonical = ticky_view_resolve_class_code($requested_class);
    if ($canonical === null) {
        return null;
    }

    $rows = ticky_view_fetch_class_rows($canonical);

    return $rows === [] ? null : ['osztaly' => $canonical, 'sorok' => $rows];
}

function ticky_view_fetch_class_rows(string $class): array
{
    $rows = sb_get('orarendek', [
        'osztaly' => 'eq.' . $class,
        'aktiv'   => 'eq.true',
        'select'  => ticky_view_select_columns(),
        'order'   => 'het_napja.asc,kezdes.asc',
        'limit'   => TICKY_VIEW_FETCH_LIMIT,
    ]);

    return is_array($rows) ? $rows : [];
}

/** Az aktív órarendben szereplő osztálykód megkeresése kis/nagybetű nélkül. */
function ticky_view_resolve_class_code(string $requested_class): ?string
{
    $rows = sb_get('orarendek', [
        'aktiv'  => 'eq.true',
        'select' => 'osztaly',
        'limit'  => TICKY_VIEW_FETCH_LIMIT,
    ]);

    $requested_lower = osztaly_lower($requested_class);
    foreach (is_array($rows) ? $rows : [] as $row) {
        $code = trim((string) ($row['osztaly'] ?? ''));
        if ($code !== '' && osztaly_lower($code) === $requested_lower) {
            return $code;
        }
    }

    return null;
}

/**
 * Egy tanár órái az aktív verzióból.
 *
 * @return array{tanar:string,tanar_nev:?string,sorok:array}|null
 */
function ticky_view_teacher_rows(string $requested_teacher): ?array
{
    $requested_teacher = trim($requested_teacher);
    if ($requested_teacher === '') {
        return null;
    }

    $teachers = sb_get('tanarok', [
        'rovid_nev' => 'eq.' . $requested_teacher,
        'select'    => 'id,rovid_nev,nev',
        'limit'     => '1',
    ]);

    if (!is_array($teachers) || $teachers === []) {
        return null;
    }

    $teacher = $teachers[0];
    $rows = sb_get('orarendek', [
        'tanar_id' => 'eq.' . $teacher['id'],
        'aktiv'    => 'eq.true',
        'select'   => ticky_view_select_columns(),
        'order'    => 'het_napja.asc,kezdes.asc',
        'limit'    => TICKY_VIEW_FETCH_LIMIT,
    ]);

    if (!is_array($rows) || $rows === []) {
        return null;
    }

    return [
        'tanar'     => (string) ($teacher['rovid_nev'] ?? $requested_teacher),
        'tanar_nev' => $teacher['nev'] ?? null,
        'sorok'     => $rows,
    ];
}

// ─────────────────────────────────────────────────────────────────
// Sor → nézet mezők
// ─────────────────────────────────────────────────────────────────

function ticky_view_row_teacher(array $row): string
{
    return trim((string) ($row['tanarok']['rovid_nev'] ?? '?'));
}

function ticky_view_row_teacher_name(array $row): ?string
{
    $name = $row['tanarok']['nev'] ?? null;
    if (is_string($name) && trim($name) !== '') {
        return trim($name);
    }

    // A tanárok.js TEACHER_NAMES blokkja pótolja, ha az adatbázisban nincs név.
    if (function_exists('ticky_source_teacher_names')) {
        $names = ticky_source_teacher_names();
        return $names[ticky_view_row_teacher($row)] ?? null;
    }

    return null;
}

function ticky_view_row_room(array $row): string
{
    return trim((string) ($row['termek']['terem_szam'] ?? '?'));
}

function ticky_view_row_time(array $row, string $field): string
{
    return substr((string) ($row[$field] ?? ''), 0, 5);
}

// ─────────────────────────────────────────────────────────────────
// Csoportbontás
// ─────────────────────────────────────────────────────────────────

/**
 * Az osztály összes valós csoportszáma.
 *
 * Elsődlegesen az importált adatból (orarendek.csoport). Ha ott nincs
 * csoportinformáció – például a tanárok.js forrásból készült verzióban –,
 * a csoport_terkep.php Excel-alapú térképére esünk vissza, hogy a
 * "csak a 2. csoport" jellegű címkék ne tűnjenek el.
 *
 * @return array<int,int>
 */
function ticky_view_class_group_numbers(array $rows, string $class): array
{
    $groups = [];
    foreach ($rows as $row) {
        if ($row['csoport'] !== null) {
            $groups[(int) $row['csoport']] = true;
        }
    }

    if ($groups !== []) {
        $list = array_keys($groups);
        sort($list);
        return count($list) < 2 ? [] : $list;
    }

    if (function_exists('ticky_csoport_osztaly_csoportszamok')) {
        $list = ticky_csoport_osztaly_csoportszamok($class);
        return count($list) < 2 ? [] : $list;
    }

    return [];
}

/**
 * Egy sorhoz tartozó csoportszám(ok).
 * Az importált érték az elsődleges; hiányában az Excel-térkép segít.
 *
 * @return array<int,int> üres tömb = nem tudjuk
 */
function ticky_view_row_groups(array $row, string $class, int $day, string $start): array
{
    if ($row['csoport'] !== null) {
        return [(int) $row['csoport']];
    }

    if (function_exists('ticky_csoport_szam_tanarbol')) {
        return ticky_csoport_szam_tanarbol($class, $day, $start, ticky_view_row_teacher($row));
    }

    return [];
}

// ─────────────────────────────────────────────────────────────────
// Osztály nézet
// ─────────────────────────────────────────────────────────────────

/**
 * Egy nap órái egy osztálynak, a tanarok_source.php-val azonos szerkezetben.
 *
 * @param array<int,int> $class_groups az osztály összes csoportszáma
 */
function ticky_view_class_day_lessons(array $rows, string $class, int $day, array $class_groups): array
{
    $slots = [];

    foreach ($rows as $row) {
        if ((int) ($row['het_napja'] ?? 0) !== $day) {
            continue;
        }

        $start = ticky_view_row_time($row, 'kezdes');
        $end   = ticky_view_row_time($row, 'vegzes');
        $key   = $start . '_' . $end;

        if (!isset($slots[$key])) {
            $slots[$key] = [
                'kezdes'      => $start,
                'vegzes'      => $end,
                'ora_sorszam' => $row['ora_sorszam'] ?? null,
                'csoportok'   => [],
                'jelenlevo'   => [],
                'ismeretlen'  => false,
            ];
        }

        $group = [
            'tanar'     => ticky_view_row_teacher($row),
            'tanar_nev' => ticky_view_row_teacher_name($row),
            'osztaly'   => $class,
            'terem'     => ticky_view_row_room($row),
            'tantargy'  => (string) ($row['tantargy'] ?? ''),
        ];

        if (ticky_view_group_exists($slots[$key]['csoportok'], $group)) {
            continue;
        }

        $numbers = ticky_view_row_groups($row, $class, $day, $start);
        if ($numbers === []) {
            $slots[$key]['ismeretlen'] = true;
        } else {
            $group['csoport_szam'] = count($numbers) === 1 ? (int) $numbers[0] : $numbers;
            foreach ($numbers as $number) {
                $slots[$key]['jelenlevo'][(int) $number] = true;
            }
        }

        $slots[$key]['csoportok'][] = $group;
    }

    ksort($slots);

    $lessons = [];
    foreach ($slots as $slot) {
        $lessons[] = ticky_view_finalize_class_slot($slot, $class_groups);
    }

    return $lessons;
}

/** Már felvettük ezt a (tanár, terem, tantárgy) hármast ebben a sávban? */
function ticky_view_group_exists(array $groups, array $candidate): bool
{
    foreach ($groups as $existing) {
        if (
            $existing['tanar'] === $candidate['tanar']
            && $existing['terem'] === $candidate['terem']
            && $existing['tantargy'] === $candidate['tantargy']
        ) {
            return true;
        }
    }

    return false;
}

/** Egy idősáv lezárása: aggregált mezők és a részleges-csoport címkék. */
function ticky_view_finalize_class_slot(array $slot, array $class_groups): array
{
    $groups = $slot['csoportok'];

    $present = array_keys($slot['jelenlevo']);
    sort($present);

    // Részleges csoport CSAK akkor, ha minden alóra csoportja ismert, az
    // osztálynak több csoportja van, és nincs mind jelen.
    $partial = [];
    $missing = [];
    if (
        !$slot['ismeretlen']
        && $present !== []
        && count($class_groups) > 1
        && count($present) < count($class_groups)
    ) {
        $partial = $present;
        $missing = array_values(array_diff($class_groups, $present));
    }

    // Ha pontosan egy csoport van jelen, a szám nélküli alórákat is hozzá kötjük.
    if (count($present) === 1) {
        foreach ($groups as &$group) {
            if (!isset($group['csoport_szam'])) {
                $group['csoport_szam'] = (int) $present[0];
            }
        }
        unset($group);
    }

    usort($groups, static function (array $left, array $right): int {
        $a = is_array($left['csoport_szam'] ?? null) ? min($left['csoport_szam']) : ($left['csoport_szam'] ?? 999);
        $b = is_array($right['csoport_szam'] ?? null) ? min($right['csoport_szam']) : ($right['csoport_szam'] ?? 999);
        return $a <=> $b;
    });

    $rooms = $teachers = $subjects = [];
    foreach ($groups as $group) {
        if (!in_array($group['terem'], $rooms, true)) {
            $rooms[] = $group['terem'];
        }
        if (!in_array($group['tanar'], $teachers, true)) {
            $teachers[] = $group['tanar'];
        }
        if ($group['tantargy'] !== '' && !in_array($group['tantargy'], $subjects, true)) {
            $subjects[] = $group['tantargy'];
        }
    }

    return [
        'kezdes'              => $slot['kezdes'],
        'vegzes'              => $slot['vegzes'],
        'ora_sorszam'         => $slot['ora_sorszam'],
        'is_csoport'          => count($groups) > 1,
        'terem'               => implode(' / ', $rooms),
        'tanar'               => implode(' / ', $teachers),
        'tanar_nev'           => count($groups) === 1 ? ($groups[0]['tanar_nev'] ?? null) : null,
        'tantargy'            => implode(' / ', $subjects),
        'csoportok'           => $groups,
        'reszleges_csoport'   => $partial !== [],
        'reszleges_csoportok' => $partial,
        'reszleges_szoveg'    => ($partial !== [] && function_exists('ticky_reszleges_szoveg'))
            ? ticky_reszleges_szoveg($partial) : '',
        'hianyzo_szoveg'      => ($missing !== [] && function_exists('ticky_hianyzo_csoport_szoveg_lista'))
            ? ticky_hianyzo_csoport_szoveg_lista($missing) : '',
    ];
}

/**
 * Egy osztály egy napja.
 *
 * @return array{osztaly:string,orak:array}|null
 */
function ticky_view_class_day(string $requested_class, int $day): ?array
{
    $data = ticky_view_class_rows($requested_class);
    if ($data === null) {
        return null;
    }

    $class = $data['osztaly'];

    return [
        'osztaly' => $class,
        'orak'    => ticky_view_class_day_lessons(
            $data['sorok'],
            $class,
            $day,
            ticky_view_class_group_numbers($data['sorok'], $class)
        ),
    ];
}

/**
 * Egy osztály teljes hete. A napokat a hívó által megadott összevonó
 * függvénnyel futtatjuk át, hogy a többórás blokkok egy sorként jelenjenek meg.
 *
 * @return array{osztaly:string,het:array}|null
 */
function ticky_view_class_week(string $requested_class): ?array
{
    $data = ticky_view_class_rows($requested_class);
    if ($data === null) {
        return null;
    }

    $class = $data['osztaly'];
    $class_groups = ticky_view_class_group_numbers($data['sorok'], $class);

    $week = [];
    for ($day = 1; $day <= 5; $day++) {
        $lessons = ticky_view_class_day_lessons($data['sorok'], $class, $day, $class_groups);
        if (function_exists('merge_consecutive_orak')) {
            $lessons = merge_consecutive_orak($lessons);
        }

        $week[] = ['nap' => $day, 'orak' => $lessons];
    }

    return ['osztaly' => $class, 'het' => $week];
}

// ─────────────────────────────────────────────────────────────────
// Tanár nézet
// ─────────────────────────────────────────────────────────────────

/**
 * Egy tanár egy napja.
 *
 * @return array{tanar:string,tanar_nev:?string,orak:array}|null
 */
function ticky_view_teacher_day(string $requested_teacher, int $day): ?array
{
    $data = ticky_view_teacher_rows($requested_teacher);
    if ($data === null) {
        return null;
    }

    $slots = [];
    foreach ($data['sorok'] as $row) {
        if ((int) ($row['het_napja'] ?? 0) !== $day) {
            continue;
        }

        $start = ticky_view_row_time($row, 'kezdes');
        $end   = ticky_view_row_time($row, 'vegzes');
        $key   = $start . '_' . $end;

        if (!isset($slots[$key])) {
            $slots[$key] = [
                'kezdes'      => $start,
                'vegzes'      => $end,
                'ora_sorszam' => $row['ora_sorszam'] ?? null,
                'csoportok'   => [],
            ];
        }

        $group = [
            'terem'    => ticky_view_row_room($row),
            'osztaly'  => trim((string) ($row['osztaly'] ?? '?')),
            'tantargy' => (string) ($row['tantargy'] ?? ''),
        ];

        $exists = false;
        foreach ($slots[$key]['csoportok'] as $existing) {
            if (
                $existing['terem'] === $group['terem']
                && $existing['osztaly'] === $group['osztaly']
                && $existing['tantargy'] === $group['tantargy']
            ) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $slots[$key]['csoportok'][] = $group;
        }
    }

    ksort($slots);

    $lessons = [];
    foreach ($slots as $slot) {
        $groups = $slot['csoportok'];
        $rooms = $classes = $subjects = [];

        foreach ($groups as $group) {
            if (!in_array($group['terem'], $rooms, true)) {
                $rooms[] = $group['terem'];
            }
            if (!in_array($group['osztaly'], $classes, true)) {
                $classes[] = $group['osztaly'];
            }
            if ($group['tantargy'] !== '' && !in_array($group['tantargy'], $subjects, true)) {
                $subjects[] = $group['tantargy'];
            }
        }

        $lessons[] = [
            'kezdes'      => $slot['kezdes'],
            'vegzes'      => $slot['vegzes'],
            'ora_sorszam' => $slot['ora_sorszam'],
            'tantargy'    => implode(' / ', $subjects),
            'is_csoport'  => count($groups) > 1,
            'terem'       => implode(' / ', $rooms),
            'osztaly'     => implode('/', $classes),
            'csoportok'   => $groups,
        ];
    }

    return [
        'tanar'     => $data['tanar'],
        'tanar_nev' => $data['tanar_nev'],
        'orak'      => $lessons,
    ];
}
