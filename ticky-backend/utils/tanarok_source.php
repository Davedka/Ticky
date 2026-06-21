<?php

require_once __DIR__ . '/osztaly.php';

if (is_file(__DIR__ . '/csoport_terkep.php')) {
    require_once __DIR__ . '/csoport_terkep.php';
}

function ticky_source_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $base_dirs = array_unique(array_filter([
        dirname(__DIR__),
        dirname(__DIR__, 2),
        __DIR__,
        getcwd() ?: dirname(__DIR__),
    ], static fn($dir) => is_string($dir) && $dir !== '' && is_dir($dir)));

    $filenames = [
        'tanárok.js',
        "tana\xcc\x81rok.js",
        'tanarok.js',
    ];

    foreach ($base_dirs as $dir) {
        foreach ($filenames as $name) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_readable($candidate)) {
                $cached = $candidate;
                return $cached;
            }
        }

        $files = @scandir($dir);
        if (is_array($files)) {
            foreach ($files as $file) {
                $ascii = preg_replace('/[^a-zA-Z0-9._\- ]/u', '', $file) ?? '';
                if (
                    (stripos($ascii, 'tanrok') !== false || stripos($ascii, 'tanarok') !== false)
                    && str_ends_with(trim($file), 'js')
                ) {
                    $full = $dir . DIRECTORY_SEPARATOR . $file;
                    if (is_file($full) && is_readable($full)) {
                        $cached = $full;
                        return $cached;
                    }
                }
            }
        }
    }

    $cached = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tanárok.js';
    return $cached;
}


function ticky_source_normalize_token(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function ticky_source_split_compound_value(string $value): array
{
    $normalized = ticky_source_normalize_token($value);
    if ($normalized === '') {
        return [];
    }

    if (str_contains($normalized, ' ')) {
        return [$normalized];
    }

    return array_values(array_filter(array_map(
        'ticky_source_normalize_token',
        explode('/', $normalized)
    ), static fn(string $token): bool => $token !== ''));
}

function ticky_source_day_to_index(string $value): ?int
{
    // Ékezet-érzéketlen kulcsok: így a 'Hétfő' ÉS a 'Hétfo' (sima o) is működik,
    // a 'HÉTFŐ', 'hetfo' stb. is. Korábban a 'Hétfo' írásmód miatt a HÉTFŐ
    // összes órája kiesett (üres nap), mert a map csak 'hétfő'-t fogadott el.
    static $map = [
        'hetfo' => 1,
        'kedd' => 2,
        'szerda' => 3,
        'csutortok' => 4,
        'pentek' => 5,
    ];

    $normalized = osztaly_lower(ticky_source_normalize_token($value));
    $folded = strtr($normalized, [
        'á' => 'a', 'é' => 'e', 'í' => 'i',
        'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);

    return $map[$folded] ?? null;
}

function ticky_source_period_number(string $start): ?int
{
    static $map = [
        '07:30' => 1,
        '08:20' => 2,
        '09:15' => 3,
        '10:15' => 4,
        '11:10' => 5,
        '12:05' => 6,
        '12:55' => 7,
        '13:40' => 8,
    ];

    $start = substr(ticky_source_normalize_token($start), 0, 5);
    return $map[$start] ?? null;
}

// ─────────────────────────────────────────────────────────────────
// Idősávok (8 tanóra). Ezt használjuk a több órát átfogó (összevont)
// blokkok óránkénti szétbontásához.
// ─────────────────────────────────────────────────────────────────
function ticky_source_period_slots(): array
{
    static $slots = [
        ['07:30', '08:10'],
        ['08:20', '09:05'],
        ['09:15', '10:00'],
        ['10:15', '11:00'],
        ['11:10', '11:55'],
        ['12:05', '12:50'],
        ['12:55', '13:35'],
        ['13:40', '14:20'],
    ];

    return $slots;
}

// Egy [start, end] sávot óránként szétbont, ha több tanórát fog át.
// 1 órás (vagy nem szabványos idejű) bejegyzés változatlanul tér vissza,
// így a sima órák viselkedése nem változik.
function ticky_source_expand_period_slots(string $start, string $end): array
{
    $start = substr($start, 0, 5);
    $end   = substr($end, 0, 5);

    $slots = ticky_source_period_slots();

    $start_idx = null;
    $end_idx   = null;
    foreach ($slots as $i => $slot) {
        if ($slot[0] === $start) {
            $start_idx = $i;
        }
        if ($slot[1] === $end) {
            $end_idx = $i;
        }
    }

    // Nem szabványos időpont vagy egyetlen tanóra → marad, ahogy van.
    if ($start_idx === null || $end_idx === null || $end_idx <= $start_idx) {
        return [[$start, $end]];
    }

    $expanded = [];
    for ($i = $start_idx; $i <= $end_idx; $i++) {
        $expanded[] = $slots[$i];
    }

    return $expanded;
}

function ticky_source_load_schedule_entries(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $path = ticky_source_path();
    if (!is_file($path) || !is_readable($path)) {
        $cache = [];
        return $cache;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        $cache = [];
        return $cache;
    }

    $start = strpos($contents, 'SCHEDULE_DATA');
    if ($start === false) {
        $cache = [];
        return $cache;
    }

    $array_start = strpos($contents, '[', $start);
    if ($array_start === false) {
        $cache = [];
        return $cache;
    }

    $depth = 0;
    $array_end = null;
    $length = strlen($contents);
    for ($index = $array_start; $index < $length; $index++) {
        $char = $contents[$index];
        if ($char === '[') {
            $depth++;
        } elseif ($char === ']') {
            $depth--;
            if ($depth === 0) {
                $array_end = $index;
                break;
            }
        }
    }

    if ($array_end === null) {
        $cache = [];
        return $cache;
    }

    $body = substr($contents, $array_start, ($array_end - $array_start) + 1);
    preg_match_all('/\{([^}]*)\}/u', $body, $blocks);

    $entries = [];
    foreach ($blocks[1] as $block) {
        preg_match_all('/(\w+)\s*:\s*[\'"]([^\'"]*)[\'"]/u', $block, $matches, PREG_SET_ORDER);

        $entry = [];
        foreach ($matches as $match) {
            $entry[$match[1]] = $match[2];
        }

        if (
            !isset($entry['teacher'], $entry['room'], $entry['class'], $entry['subject'], $entry['day'], $entry['start'], $entry['end'])
        ) {
            continue;
        }

        $entries[] = [
            'teacher' => ticky_source_normalize_token((string) $entry['teacher']),
            'room' => ticky_source_normalize_token((string) $entry['room']),
            'class' => ticky_source_normalize_token((string) $entry['class']),
            'subject' => ticky_source_normalize_token((string) $entry['subject']),
            'day' => ticky_source_normalize_token((string) $entry['day']),
            'start' => substr(ticky_source_normalize_token((string) $entry['start']), 0, 5),
            'end' => substr(ticky_source_normalize_token((string) $entry['end']), 0, 5),
        ];
    }

    $cache = $entries;
    return $cache;
}

function ticky_source_class_codes(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $unique = [];
    foreach (ticky_source_load_schedule_entries() as $entry) {
        foreach (ticky_source_split_compound_value((string) ($entry['class'] ?? '')) as $token) {
            if (!osztaly_is_valid_code($token)) {
                continue;
            }

            $unique[osztaly_lower($token)] = $token;
        }
    }

    $cache = array_values($unique);
    usort($cache, 'osztaly_sort_compare');
    return $cache;
}

function ticky_source_teacher_names(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $path = ticky_source_path();
    if (!is_file($path) || !is_readable($path)) {
        $cache = [];
        return $cache;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        $cache = [];
        return $cache;
    }

    $start = strpos($contents, 'TEACHER_NAMES');
    if ($start === false) {
        $cache = [];
        return $cache;
    }

    $brace_start = strpos($contents, '{', $start);
    if ($brace_start === false) {
        $cache = [];
        return $cache;
    }

    $depth = 0;
    $brace_end = null;
    $length = strlen($contents);
    for ($index = $brace_start; $index < $length; $index++) {
        $char = $contents[$index];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                $brace_end = $index;
                break;
            }
        }
    }

    if ($brace_end === null) {
        $cache = [];
        return $cache;
    }

    $body = substr($contents, $brace_start + 1, ($brace_end - $brace_start) - 1);
    preg_match_all('/[\'"]([^\'"]+)[\'"]\s*:\s*[\'"]([^\'"]+)[\'"]/u', $body, $matches, PREG_SET_ORDER);

    $names = [];
    foreach ($matches as $match) {
        $code = ticky_source_normalize_token((string) $match[1]);
        $name = ticky_source_normalize_token((string) $match[2]);
        if ($code !== '' && $name !== '') {
            $names[$code] = $name;
        }
    }

    $cache = $names;
    return $cache;
}

function ticky_source_unique_teachers(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $unique = [];
    foreach (ticky_source_load_schedule_entries() as $entry) {
        $code = ticky_source_normalize_token((string) ($entry['teacher'] ?? ''));
        if ($code !== '') {
            $unique[$code] = true;
        }
    }

    $cache = array_keys($unique);
    sort($cache);
    return $cache;
}

function ticky_source_unique_rooms(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $unique = [];
    foreach (ticky_source_load_schedule_entries() as $entry) {
        foreach (ticky_source_split_compound_value((string) ($entry['room'] ?? '')) as $token) {
            if ($token !== '') {
                $unique[$token] = true;
            }
        }
    }

    $cache = array_keys($unique);
    sort($cache);
    return $cache;
}


function ticky_source_expected_lessons(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $lessons = [];
    $teacher_names = ticky_source_teacher_names();
    foreach (ticky_source_load_schedule_entries() as $entry) {
        $day = ticky_source_day_to_index((string) ($entry['day'] ?? ''));
        if ($day === null) {
            continue;
        }

        $class_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['class'] ?? '')),
            'osztaly_is_valid_code'
        ));
        if ($class_codes === []) {
            continue;
        }

        $room_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['room'] ?? '')),
            'osztaly_is_room_like_code'
        ));
        if ($room_codes === []) {
            $room_codes = [ticky_source_normalize_token((string) ($entry['room'] ?? '?'))];
        }

        $teacher = ticky_source_normalize_token((string) ($entry['teacher'] ?? '?'));
        $subject = ticky_source_normalize_token((string) ($entry['subject'] ?? ''));
        $start = substr(ticky_source_normalize_token((string) ($entry['start'] ?? '')), 0, 5);
        $end = substr(ticky_source_normalize_token((string) ($entry['end'] ?? '')), 0, 5);
        $period = ticky_source_period_number($start);

        if (count($room_codes) === count($class_codes) && count($room_codes) > 1) {
            // ZIP: room 1→class 12.a, room 14→class 12.b
            for ($zi = 0; $zi < count($room_codes); $zi++) {
                $lessons[] = [
                    'terem' => $room_codes[$zi],
                    'tanar' => $teacher,
                    'tanar_nev' => null,
                    'osztaly' => $class_codes[$zi],
                    'tantargy' => $subject,
                    'het_napja' => $day,
                    'ora_sorszam' => $period,
                    'kezdes' => $start,
                    'vegzes' => $end,
                ];
            }
        } else {
            // Cross product (1 room × N classes, or N rooms × 1 class)
            foreach ($room_codes as $room) {
                foreach ($class_codes as $class_code) {
                    $lessons[] = [
                        'terem' => $room,
                        'tanar' => $teacher,
                        'tanar_nev' => null,
                        'osztaly' => $class_code,
                        'tantargy' => $subject,
                        'het_napja' => $day,
                        'ora_sorszam' => $period,
                        'kezdes' => $start,
                        'vegzes' => $end,
                    ];
                }
            }
        }
    }

    $cache = $lessons;
    return $cache;
}

// ─────────────────────────────────────────────────────────────────
// OSZTÁLY NÉZET: minden tanárok.js bejegyzés = EGY csoport, combined
// osztály/terem mezőkkel. Nem ZIP-elünk a megjelenítéshez.
//
// A több tanórát átfogó (összevont) blokkokat óránként szétbontjuk,
// hogy a 2–4. óra ne egyetlen sorként jelenjen meg. A megjelenítésnél
// a merge_consecutive_orak() vonja vissza össze az azonos szomszédokat.
// ─────────────────────────────────────────────────────────────────
function ticky_source_class_lessons_for_day(string $requested_code, int $day): ?array
{
    $class_code = ticky_source_resolve_class_code($requested_code);
    if ($class_code === null) {
        return null;
    }

    $class_lower = osztaly_lower($class_code);
    $teacher_names = ticky_source_teacher_names();
    $grouped = [];

    foreach (ticky_source_load_schedule_entries() as $entry) {
        $day_idx = ticky_source_day_to_index((string) ($entry['day'] ?? ''));
        if ($day_idx !== $day) {
            continue;
        }

        // Osztálykódok kinyerése
        $class_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['class'] ?? '')),
            'osztaly_is_valid_code'
        ));
        if ($class_codes === []) {
            continue;
        }

        // Tartalmazza-e a keresett osztályt?
        $contains = false;
        foreach ($class_codes as $cc) {
            if (osztaly_lower($cc) === $class_lower) {
                $contains = true;
                break;
            }
        }
        if (!$contains) {
            continue;
        }

        // Termek
        $room_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['room'] ?? '')),
            'osztaly_is_room_like_code'
        ));
        if ($room_codes === []) {
            $room_codes = [ticky_source_normalize_token((string) ($entry['room'] ?? '?'))];
        }

        $raw_start = substr(ticky_source_normalize_token((string) ($entry['start']   ?? '')), 0, 5);
        $raw_end   = substr(ticky_source_normalize_token((string) ($entry['end']     ?? '')), 0, 5);
        $teacher   = ticky_source_normalize_token((string) ($entry['teacher'] ?? '?'));
        $subject   = ticky_source_normalize_token((string) ($entry['subject'] ?? ''));

        $osztaly_str = implode('/', $class_codes);
        $terem_str   = implode('/', $room_codes);

        // Több órát átfogó blokk → óránként külön idősávra bontjuk.
        foreach (ticky_source_expand_period_slots($raw_start, $raw_end) as $slot) {
            [$start, $end] = $slot;
            $key = $start . '_' . $end;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'kezdes'      => $start,
                    'vegzes'      => $end,
                    'ora_sorszam' => ticky_source_period_number($start),
                    'csoportok'   => [],
                ];
            }

            // Deduplikáció az adott idősávon belül
            $exists = false;
            foreach ($grouped[$key]['csoportok'] as $existing) {
                if (
                    $existing['tanar']    === $teacher
                    && $existing['terem']    === $terem_str
                    && $existing['osztaly']  === $osztaly_str
                    && $existing['tantargy'] === $subject
                ) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }

            // EGY source bejegyzés = EGY csoport (combined)
            $grouped[$key]['csoportok'][] = [
                'tanar'     => $teacher,
                'tanar_nev' => $teacher_names[$teacher] ?? null,
                'osztaly'   => $osztaly_str,  // '12.a/12.b'
                'terem'     => $terem_str,     // '1/14'
                'tantargy'  => $subject,
            ];
        }
    }

    // Az osztály ÖSSZES valós csoportja (pl. [1,2]). Ha csak 1 csoport van,
    // nincs értelme "csak a X. csoport" jelzésnek.
    $class_groups = function_exists('ticky_csoport_osztaly_csoportszamok')
        ? ticky_csoport_osztaly_csoportszamok($class_code)
        : [];
    if (count($class_groups) < 2) {
        $class_groups = []; // nincs valódi bontás → sosem jelzünk részleges csoportot
    }

    // Output: minden idősáv csoportokkal + aggregált info
    $lessons = [];
    foreach ($grouped as $g) {
        $cs = $g['csoportok'];

        // Excel alapján: tanár → tényleges csoport(ok). Ezzel a "1. csoport / 2. csoport"
        // címke a TANÁROK.JS sorrend helyett a VALÓS Excel-hozzárendelést követi.
        // Egyúttal pontosan megállapítjuk, MELY csoportoknak van itt órája.
        $present = [];          // csoportok, amelyeknek BIZTOSAN van itt órája
        $has_unknown = false;   // van-e olyan al-óra, aminek nem ismerjük a csoportját
        foreach ($cs as &$cc) {
            $cs_groups = function_exists('ticky_csoport_szam_tanarbol')
                ? ticky_csoport_szam_tanarbol($class_code, $day, (string) $g['kezdes'], (string) $cc['tanar'])
                : [];
            if ($cs_groups !== []) {
                $cc['csoport_szam'] = count($cs_groups) === 1
                    ? (int) $cs_groups[0]
                    : array_map('intval', $cs_groups);
                foreach ($cs_groups as $gg) {
                    $present[(int) $gg] = true;
                }
            } else {
                $has_unknown = true;
            }
        }
        unset($cc);

        $present_groups = array_keys($present);
        sort($present_groups);

        // RÉSZLEGES (csak-egyik-csoport) DÖNTÉS — kizárólag a tényleges tanárok.js
        // adatból: CSAK akkor jelezzük, ha
        //   (a) minden al-óra csoportja ismert (nincs bizonytalan), ÉS
        //   (b) az osztálynak van >1 valódi csoportja, ÉS
        //   (c) a jelenlévő csoportok valódi RÉSZHALMAZA az összes csoportnak
        //       (pl. {2} ⊊ {1,2} → az 1. csoportnak tényleg nincs itt órája).
        $reszleges = [];
        $hianyzo   = [];
        if (
            !$has_unknown
            && $present_groups !== []
            && count($class_groups) > 1
            && count($present_groups) < count($class_groups)
        ) {
            $reszleges = $present_groups;
            $hianyzo   = array_values(array_diff($class_groups, $present_groups));
        }

        // Ha pontosan egy csoport van jelen, a csoport-szám nélküli al-órákat is
        // ehhez a csoporthoz rendeljük (megjelenítési címkéhez).
        if (count($present_groups) === 1) {
            foreach ($cs as &$cc) {
                if (!isset($cc['csoport_szam'])) {
                    $cc['csoport_szam'] = (int) $present_groups[0];
                }
            }
            unset($cc);
        }

        // Rendezzük a csoportokat a tényleges csoport-szám szerint (1, 2, 3),
        // hogy a megjelenítés is sorrendben legyen.
        usort($cs, static function (array $a, array $b): int {
            $sa = is_array($a['csoport_szam'] ?? null) ? min($a['csoport_szam']) : ($a['csoport_szam'] ?? 999);
            $sb = is_array($b['csoport_szam'] ?? null) ? min($b['csoport_szam']) : ($b['csoport_szam'] ?? 999);
            return $sa <=> $sb;
        });

        $all_rooms = $all_teachers = $all_subjects = [];

        foreach ($cs as $c) {
            foreach (explode('/', (string) $c['terem']) as $r) {
                $r = trim($r);
                if ($r !== '' && !in_array($r, $all_rooms, true)) {
                    $all_rooms[] = $r;
                }
            }
            if (!in_array($c['tanar'], $all_teachers, true)) {
                $all_teachers[] = $c['tanar'];
            }
            if ($c['tantargy'] !== '' && !in_array($c['tantargy'], $all_subjects, true)) {
                $all_subjects[] = $c['tantargy'];
            }
        }

        // Hiányzó csoport(ok) szövege: "az 1. csoportnak nincs órája"
        $hianyzo_szoveg = ($hianyzo !== [] && function_exists('ticky_hianyzo_csoport_szoveg_lista'))
            ? ticky_hianyzo_csoport_szoveg_lista($hianyzo)
            : '';

        $lessons[] = [
            'kezdes'              => $g['kezdes'],
            'vegzes'              => $g['vegzes'],
            'ora_sorszam'         => $g['ora_sorszam'],
            'is_csoport'          => count($cs) > 1,
            'terem'               => implode(' / ', $all_rooms),
            'tanar'               => implode(' / ', $all_teachers),
            'tanar_nev'           => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
            'tantargy'            => implode(' / ', $all_subjects),
            'csoportok'           => $cs,
            'reszleges_csoport'   => $reszleges !== [],
            'reszleges_csoportok' => $reszleges,
            'reszleges_szoveg'    => ($reszleges !== [] && function_exists('ticky_reszleges_szoveg')) ? ticky_reszleges_szoveg($reszleges) : '',
            'hianyzo_szoveg'      => $hianyzo_szoveg,
        ];
    }

    usort($lessons, static fn(array $a, array $b): int => strcmp((string) ($a['kezdes'] ?? ''), (string) ($b['kezdes'] ?? '')));

    return [
        'osztaly' => $class_code,
        'orak'    => $lessons,
    ];
}

// Egy hét összes napjára visszaadja az osztály óráit. A merge_consecutive_orak()
// is rajta van futtatva (ahogy a napi nézetnél), hogy az összevont blokkok ne
// külön soronként jelenjenek meg.
function ticky_source_class_lessons_for_week(string $requested_code): ?array
{
    $class_code = ticky_source_resolve_class_code($requested_code);
    if ($class_code === null) {
        return null;
    }

    $week = [];
    for ($day = 1; $day <= 5; $day++) {
        $daily = ticky_source_class_lessons_for_day($class_code, $day);
        $orak = $daily['orak'] ?? [];
        if (function_exists('merge_consecutive_orak')) {
            $orak = merge_consecutive_orak($orak);
        }
        $week[] = [
            'nap' => $day,
            'orak' => $orak,
        ];
    }

    return [
        'osztaly' => $class_code,
        'het' => $week,
    ];
}

function ticky_source_resolve_class_code(string $requested_code): ?string
{
    $requested_code = osztaly_normalize_code($requested_code);
    if (!osztaly_is_valid_code($requested_code)) {
        return null;
    }

    $requested_lower = osztaly_lower($requested_code);
    foreach (ticky_source_class_codes() as $code) {
        if (osztaly_lower($code) === $requested_lower) {
            return $code;
        }
    }

    return null;
}

function ticky_source_resolve_room_code(string $requested_room): ?string
{
    $requested_room = ticky_source_normalize_token($requested_room);
    if ($requested_room === '') {
        return null;
    }

    $requested_lower = osztaly_lower($requested_room);
    foreach (ticky_source_unique_rooms() as $room) {
        if (osztaly_lower($room) === $requested_lower) {
            return $room;
        }
    }

    return null;
}

function ticky_source_resolve_teacher_code(string $requested_teacher): ?string
{
    $requested_teacher = ticky_source_normalize_token($requested_teacher);
    if ($requested_teacher === '') {
        return null;
    }

    $requested_lower = osztaly_lower($requested_teacher);
    foreach (ticky_source_unique_teachers() as $teacher) {
        if (osztaly_lower($teacher) === $requested_lower) {
            return $teacher;
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────
// TANÁR NÉZET: szintén a raw entries-eken iterálunk, minden source
// bejegyzés = EGY csoport combined adatokkal.
//
// JAVÍTÁS: itt is óránként szétbontjuk a több tanórát átfogó blokkokat
// (ticky_source_expand_period_slots), hogy a tanár-nézet UGYANÚGY
// viselkedjen, mint az osztály-nézet minden napon. A megjelenítésnél
// a merge_consecutive_orak() vonja vissza össze az azonos szomszédokat.
// ─────────────────────────────────────────────────────────────────
function ticky_source_teacher_day_schedule(string $requested_teacher, int $day): ?array
{
    $teacher_code = ticky_source_resolve_teacher_code($requested_teacher);
    if ($teacher_code === null) {
        return null;
    }

    $teacher_lower = osztaly_lower($teacher_code);
    $teacher_names = ticky_source_teacher_names();
    $grouped = [];

    foreach (ticky_source_load_schedule_entries() as $entry) {
        $day_idx = ticky_source_day_to_index((string) ($entry['day'] ?? ''));
        if ($day_idx !== $day) {
            continue;
        }

        if (osztaly_lower((string) ($entry['teacher'] ?? '')) !== $teacher_lower) {
            continue;
        }

        // Osztály és terem split
        $class_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['class'] ?? '')),
            'osztaly_is_valid_code'
        ));
        if ($class_codes === []) {
            // Pl. HT_13.ir-féle nem-szabványos: tartsd meg az eredetit
            $raw = ticky_source_normalize_token((string) ($entry['class'] ?? '?'));
            if ($raw !== '') $class_codes = [$raw];
        }

        $room_codes = array_values(array_filter(
            ticky_source_split_compound_value((string) ($entry['room'] ?? '')),
            'osztaly_is_room_like_code'
        ));
        if ($room_codes === []) {
            $room_codes = [ticky_source_normalize_token((string) ($entry['room'] ?? '?'))];
        }

        $raw_start = substr(ticky_source_normalize_token((string) ($entry['start'] ?? '')), 0, 5);
        $raw_end   = substr(ticky_source_normalize_token((string) ($entry['end']   ?? '')), 0, 5);
        $subject   = ticky_source_normalize_token((string) ($entry['subject'] ?? ''));

        $osztaly_str = implode('/', $class_codes);
        $terem_str   = implode('/', $room_codes);

        // Több tanórát átfogó blokk → óránként szétbontjuk (mint az osztály-nézetnél).
        foreach (ticky_source_expand_period_slots($raw_start, $raw_end) as $slot) {
            [$start, $end] = $slot;
            $key = $start . '_' . $end;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'kezdes'      => $start,
                    'vegzes'      => $end,
                    'ora_sorszam' => ticky_source_period_number($start),
                    'tantargy'    => $subject,
                    'csoportok'   => [],
                ];
            }

            // Deduplikáció az adott idősávon belül
            $exists = false;
            foreach ($grouped[$key]['csoportok'] as $existing) {
                if (
                    $existing['terem']    === $terem_str
                    && $existing['osztaly']  === $osztaly_str
                    && $existing['tantargy'] === $subject
                ) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }

            $grouped[$key]['csoportok'][] = [
                'terem'    => $terem_str,
                'osztaly'  => $osztaly_str,
                'tantargy' => $subject,
            ];
        }
    }

    $lessons = [];
    foreach ($grouped as $g) {
        $cs = $g['csoportok'];
        $all_rooms = $all_classes = $all_subjects = [];

        foreach ($cs as $c) {
            foreach (explode('/', (string) $c['terem']) as $r) {
                $r = trim($r);
                if ($r !== '' && !in_array($r, $all_rooms, true)) {
                    $all_rooms[] = $r;
                }
            }
            foreach (explode('/', (string) $c['osztaly']) as $oc) {
                $oc = trim($oc);
                if ($oc !== '' && !in_array($oc, $all_classes, true)) {
                    $all_classes[] = $oc;
                }
            }
            if (($c['tantargy'] ?? '') !== '' && !in_array($c['tantargy'], $all_subjects, true)) {
                $all_subjects[] = $c['tantargy'];
            }
        }

        $lessons[] = [
            'kezdes'      => $g['kezdes'],
            'vegzes'      => $g['vegzes'],
            'ora_sorszam' => $g['ora_sorszam'],
            'tantargy'    => implode(' / ', $all_subjects),
            'is_csoport'  => count($cs) > 1,
            'terem'       => implode(' / ', $all_rooms),
            'osztaly'     => implode('/', $all_classes),
            'csoportok'   => $cs,
        ];
    }

    usort($lessons, static fn(array $a, array $b): int => strcmp((string) ($a['kezdes'] ?? ''), (string) ($b['kezdes'] ?? '')));

    return [
        'tanar'     => $teacher_code,
        'tanar_nev' => $teacher_names[$teacher_code] ?? null,
        'orak'      => $lessons,
    ];
}

function ticky_source_room_lessons_for_day(string $requested_room, int $day): ?array
{
    $room_code = ticky_source_resolve_room_code($requested_room);
    if ($room_code === null) {
        return null;
    }

    $room_lower = osztaly_lower($room_code);
    $teacher_names = ticky_source_teacher_names();
    $grouped = [];

    foreach (ticky_source_expected_lessons() as $lesson) {
        if ((int) ($lesson['het_napja'] ?? 0) !== $day) {
            continue;
        }

        if (osztaly_lower((string) ($lesson['terem'] ?? '')) !== $room_lower) {
            continue;
        }

        $kezdes = (string) ($lesson['kezdes'] ?? '');
        $vegzes = (string) ($lesson['vegzes'] ?? '');
        $key = $kezdes . '_' . $vegzes;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'kezdes' => $kezdes,
                'vegzes' => $vegzes,
                'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
                'csoportok' => [],
            ];
        }

        $tanar = (string) ($lesson['tanar'] ?? '?');
        $osztaly = (string) ($lesson['osztaly'] ?? '?');
        $tantargy = (string) ($lesson['tantargy'] ?? '');

        $exists = false;
        foreach ($grouped[$key]['csoportok'] as $g) {
            if ($g['tanar'] === $tanar && $g['osztaly'] === $osztaly && $g['tantargy'] === $tantargy) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $grouped[$key]['csoportok'][] = [
                'tanar' => $tanar,
                'tanar_nev' => $teacher_names[$tanar] ?? null,
                'osztaly' => $osztaly,
                'tantargy' => $tantargy,
            ];
        }
    }

    $lessons = [];
    foreach ($grouped as $slot) {
        $cs = $slot['csoportok'];
        $tanarok = $osztalyok = $tantargyak = [];
        foreach ($cs as $c) {
            if (!in_array($c['tanar'], $tanarok, true)) $tanarok[] = $c['tanar'];
            if (!in_array($c['osztaly'], $osztalyok, true)) $osztalyok[] = $c['osztaly'];
            if ($c['tantargy'] !== '' && !in_array($c['tantargy'], $tantargyak, true)) $tantargyak[] = $c['tantargy'];
        }
        $lessons[] = [
            'ora_sorszam' => $slot['ora_sorszam'],
            'tanar' => implode(' / ', $tanarok),
            'tanar_nev' => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
            'osztaly' => implode('/', $osztalyok),
            'tantargy' => implode(' / ', $tantargyak),
            'kezdes' => $slot['kezdes'],
            'vegzes' => $slot['vegzes'],
            'is_csoport' => count($cs) > 1,
            'csoportok' => $cs,
        ];
    }

    usort($lessons, static fn(array $left, array $right): int => strcmp((string) ($left['kezdes'] ?? ''), (string) ($right['kezdes'] ?? '')));

    return [
        'terem' => $room_code,
        'orak' => $lessons,
    ];
}

function ticky_source_room_lessons_for_week(string $requested_room): ?array
{
    $room_code = ticky_source_resolve_room_code($requested_room);
    if ($room_code === null) {
        return null;
    }

    $week = [];
    for ($day = 1; $day <= 5; $day++) {
        $daily = ticky_source_room_lessons_for_day($room_code, $day);
        $week[] = [
            'nap' => $day,
            'orak' => $daily['orak'] ?? [],
        ];
    }

    return [
        'terem' => $room_code,
        'het' => $week,
    ];
}
