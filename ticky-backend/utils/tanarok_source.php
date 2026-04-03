<?php

require_once __DIR__ . '/osztaly.php';

function ticky_source_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tanárok.js';
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

