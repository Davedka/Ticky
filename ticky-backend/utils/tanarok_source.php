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

function ticky_source_expected_lessons(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $lessons = [];
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
                'tanar_nev' => null,
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
            'tanar_nev' => null,
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
