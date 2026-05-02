<?php

require_once __DIR__ . '/osztaly.php';

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
    static $map = [
        'hétfő' => 1,
        'kedd' => 2,
        'szerda' => 3,
        'csütörtök' => 4,
        'péntek' => 5,
    ];

    $normalized = osztaly_lower(ticky_source_normalize_token($value));
    return $map[$normalized] ?? null;
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
        '12:50' => 7,
        '12:55' => 7,
        '13:40' => 8,
    ];

    $start = substr(ticky_source_normalize_token($start), 0, 5);
    return $map[$start] ?? null;
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
            // ZIP: room 1→class 12.a, room 308→class 12.b
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

function ticky_source_class_lessons_for_day(string $requested_code, int $day): ?array
{
    $class_code = ticky_source_resolve_class_code($requested_code);
    if ($class_code === null) {
        return null;
    }

    $grouped = [];
    $class_lower = osztaly_lower($class_code);

    foreach (ticky_source_expected_lessons() as $lesson) {
        if ((int) ($lesson['het_napja'] ?? 0) !== $day) {
            continue;
        }

        if (osztaly_lower((string) ($lesson['osztaly'] ?? '')) !== $class_lower) {
            continue;
        }

        $start = (string) ($lesson['kezdes'] ?? '');
        $end = (string) ($lesson['vegzes'] ?? '');
        $key = $start . '_' . $end;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'kezdes' => $start,
                'vegzes' => $end,
                'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
                'csoportok' => [],
            ];
        }

        $already_exists = false;
        foreach ($grouped[$key]['csoportok'] as $group) {
            if (
                $group['terem'] === $lesson['terem']
                && $group['tanar'] === $lesson['tanar']
                && $group['tantargy'] === $lesson['tantargy']
            ) {
                $already_exists = true;
                break;
            }
        }

        if (!$already_exists) {
            $grouped[$key]['csoportok'][] = [
                'terem' => (string) ($lesson['terem'] ?? '?'),
                'tanar' => (string) ($lesson['tanar'] ?? '?'),
                'tanar_nev' => $lesson['tanar_nev'] ?? null,
                'tantargy' => (string) ($lesson['tantargy'] ?? ''),
            ];
        }
    }

    $lessons = [];
    foreach ($grouped as $lesson) {
        $rooms = [];
        $teacher_codes = [];
        $subjects = [];

        foreach ($lesson['csoportok'] as $group) {
            if (!in_array($group['terem'], $rooms, true)) {
                $rooms[] = $group['terem'];
            }
            if (!in_array($group['tanar'], $teacher_codes, true)) {
                $teacher_codes[] = $group['tanar'];
            }
            if ($group['tantargy'] !== '' && !in_array($group['tantargy'], $subjects, true)) {
                $subjects[] = $group['tantargy'];
            }
        }

        $lessons[] = [
            'kezdes' => $lesson['kezdes'],
            'vegzes' => $lesson['vegzes'],
            'ora_sorszam' => $lesson['ora_sorszam'],
            'is_csoport' => count($lesson['csoportok']) > 1,
            'terem' => implode(' / ', $rooms),
            'tanar' => implode(' / ', $teacher_codes),
            'tanar_nev' => count($lesson['csoportok']) === 1 ? ($lesson['csoportok'][0]['tanar_nev'] ?? null) : null,
            'tantargy' => implode(' / ', $subjects),
            'csoportok' => $lesson['csoportok'],
        ];
    }

    usort($lessons, static fn(array $left, array $right): int => strcmp($left['kezdes'], $right['kezdes']));

    return [
        'osztaly' => $class_code,
        'orak' => $lessons,
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

function ticky_source_group_teacher_lessons(array $raw_lessons): array
{
    $grouped = [];

    foreach ($raw_lessons as $lesson) {
        $start = (string) ($lesson['kezdes'] ?? '');
        $end = (string) ($lesson['vegzes'] ?? '');
        $key = $start . '_' . $end;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'kezdes' => $start,
                'vegzes' => $end,
                'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
                'tantargy' => (string) ($lesson['tantargy'] ?? ''),
                'csoportok' => [],
            ];
        }

        $osztaly = (string) ($lesson['osztaly'] ?? '?');
        $terem = (string) ($lesson['terem'] ?? '?');
        $tantargy = (string) ($lesson['tantargy'] ?? '');

        $already_exists = false;
        foreach ($grouped[$key]['csoportok'] as $group) {
            if (
                $group['terem'] === $terem
                && $group['osztaly'] === $osztaly
                && ($group['tantargy'] ?? '') === $tantargy
            ) {
                $already_exists = true;
                break;
            }
        }

        if (!$already_exists) {
            $grouped[$key]['csoportok'][] = [
                'terem' => $terem,
                'osztaly' => $osztaly,
                'tantargy' => $tantargy,
            ];
        }
    }

    $lessons = [];
    foreach ($grouped as $lesson) {
        $rooms = [];
        $classes = [];
        foreach ($lesson['csoportok'] as $group) {
            if (!in_array($group['terem'], $rooms, true)) {
                $rooms[] = $group['terem'];
            }
            if (!in_array($group['osztaly'], $classes, true)) {
                $classes[] = $group['osztaly'];
            }
        }

        $lessons[] = [
            'kezdes' => $lesson['kezdes'],
            'vegzes' => $lesson['vegzes'],
            'ora_sorszam' => $lesson['ora_sorszam'],
            'tantargy' => (string) ($lesson['tantargy'] ?? ''),
            'is_csoport' => count($lesson['csoportok']) > 1,
            'terem' => implode(' / ', $rooms),
            'osztaly' => implode('/', $classes),
            'csoportok' => $lesson['csoportok'],
        ];
    }

    usort($lessons, static fn(array $left, array $right): int => strcmp((string) ($left['kezdes'] ?? ''), (string) ($right['kezdes'] ?? '')));

    return $lessons;
}

function ticky_source_teacher_day_schedule(string $requested_teacher, int $day): ?array
{
    $teacher_code = ticky_source_resolve_teacher_code($requested_teacher);
    if ($teacher_code === null) {
        return null;
    }

    $teacher_lower = osztaly_lower($teacher_code);
    $teacher_names = ticky_source_teacher_names();
    $lessons = [];

    foreach (ticky_source_expected_lessons() as $lesson) {
        if ((int) ($lesson['het_napja'] ?? 0) !== $day) {
            continue;
        }

        if (osztaly_lower((string) ($lesson['tanar'] ?? '')) !== $teacher_lower) {
            continue;
        }

        $lessons[] = [
            'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
            'terem' => (string) ($lesson['terem'] ?? '?'),
            'osztaly' => (string) ($lesson['osztaly'] ?? '?'),
            'tantargy' => (string) ($lesson['tantargy'] ?? ''),
            'kezdes' => (string) ($lesson['kezdes'] ?? ''),
            'vegzes' => (string) ($lesson['vegzes'] ?? ''),
        ];
    }

    return [
        'tanar' => $teacher_code,
        'tanar_nev' => $teacher_names[$teacher_code] ?? null,
        'orak' => ticky_source_group_teacher_lessons($lessons),
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
