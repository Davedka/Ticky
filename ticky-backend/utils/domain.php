<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/helpers.php';

function ticky_day_names(): array {
    return [
        0 => 'Hétvége',
        1 => 'Hétfő',
        2 => 'Kedd',
        3 => 'Szerda',
        4 => 'Csütörtök',
        5 => 'Péntek',
    ];
}

function ticky_day_name(int $day): string {
    return ticky_day_names()[$day] ?? 'Ismeretlen nap';
}

function ticky_normalize_hhmm(?string $time): string {
    return substr((string) $time, 0, 5);
}

function ticky_teacher_code(string $value): string {
    $value = preg_replace('/[^[:alnum:]]/u', '', $value) ?? '';
    return strtoupper($value);
}

function ticky_get_teacher_directory(): array {
    static $teachers = null;

    if ($teachers !== null) {
        return $teachers;
    }

    $teachers = sb_get('tanarok', [
        'select' => 'id,rovid_nev,nev',
        'order' => 'rovid_nev.asc',
    ]);

    return $teachers;
}

function ticky_find_teacher_by_code(string $code): ?array {
    $teachers = sb_get('tanarok', [
        'rovid_nev' => 'eq.' . strtoupper(trim($code)),
        'select' => 'id,rovid_nev,nev',
    ]);

    return $teachers[0] ?? null;
}

function ticky_get_teachers_by_ids(array $teacher_ids): array {
    $teacher_ids = array_values(array_filter(array_unique($teacher_ids)));
    if (empty($teacher_ids)) {
        return [];
    }

    $teachers = sb_get('tanarok', [
        'id' => 'in.(' . implode(',', $teacher_ids) . ')',
        'select' => 'id,rovid_nev,nev',
    ]);

    $teacher_map = [];
    foreach ($teachers as $teacher) {
        $teacher_map[$teacher['id']] = $teacher;
    }

    return $teacher_map;
}

function ticky_get_rooms(): array {
    return sb_get('termek', [
        'select' => 'id,terem_szam,emelet,aktiv',
        'order' => 'terem_szam.asc',
    ]);
}

function ticky_find_room_by_code(string $room_code): ?array {
    $rooms = sb_get('termek', [
        'terem_szam' => 'eq.' . strtoupper(trim($room_code)),
        'select' => 'id,terem_szam,emelet,aktiv',
    ]);

    return $rooms[0] ?? null;
}

function ticky_get_room_lessons_raw(string $room_id, int $day): array {
    if ($day === 0) {
        return [];
    }

    return sb_get('orarendek', [
        'terem_id' => 'eq.' . $room_id,
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'select' => 'id,osztaly,tantargy,kezdes,vegzes,ora_sorszam,tanar_id,het_napja',
        'order' => 'kezdes.asc',
    ]);
}

function ticky_get_room_lessons_for_days(string $room_id, array $days): array {
    $days = array_values(array_unique(array_filter(
        array_map('intval', $days),
        static fn(int $day): bool => $day >= 1 && $day <= 5
    )));

    if (empty($days)) {
        return [];
    }

    return sb_get('orarendek', [
        'terem_id' => 'eq.' . $room_id,
        'het_napja' => count($days) === 1 ? 'eq.' . $days[0] : 'in.(' . implode(',', $days) . ')',
        'aktiv' => 'eq.true',
        'select' => 'osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja,tanar_id',
        'order' => 'het_napja.asc,kezdes.asc',
    ]);
}

function ticky_enrich_room_lessons(array $lessons): array {
    if (empty($lessons)) {
        return [];
    }

    $teacher_map = ticky_get_teachers_by_ids(array_column($lessons, 'tanar_id'));
    $result = [];

    foreach ($lessons as $lesson) {
        $teacher = $teacher_map[$lesson['tanar_id']] ?? null;
        $result[] = [
            'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
            'osztaly' => $lesson['osztaly'] ?? '',
            'tantargy' => $lesson['tantargy'] ?? '',
            'kezdes' => ticky_normalize_hhmm($lesson['kezdes'] ?? ''),
            'vegzes' => ticky_normalize_hhmm($lesson['vegzes'] ?? ''),
            'het_napja' => isset($lesson['het_napja']) ? (int) $lesson['het_napja'] : null,
            'tanar' => $teacher['rovid_nev'] ?? '?',
            'tanar_nev' => $teacher['nev'] ?? null,
        ];
    }

    return $result;
}

function ticky_pick_current_and_next_lesson(array $lessons, ?string $time = null): array {
    $time = $time ?? aktualis_ido();
    $current = null;
    $next = null;

    foreach ($lessons as $lesson) {
        $start = ticky_normalize_hhmm($lesson['kezdes'] ?? '');
        $end = ticky_normalize_hhmm($lesson['vegzes'] ?? '');

        if ($start === '' || $end === '') {
            continue;
        }

        if ($time >= $start && $time <= $end) {
            $current = $lesson;
            continue;
        }

        if ($time < $start && $next === null) {
            $next = $lesson;
        }
    }

    return ['current' => $current, 'next' => $next];
}

function ticky_get_room_context(string $room_code, ?int $day = null, ?string $time = null): ?array {
    $room = ticky_find_room_by_code($room_code);
    if ($room === null) {
        return null;
    }

    $day = $day ?? mai_nap();
    $time = $time ?? aktualis_ido();
    $lessons = ticky_enrich_room_lessons(ticky_get_room_lessons_raw((string) $room['id'], $day));
    $state = ticky_pick_current_and_next_lesson($lessons, $time);

    return [
        'room' => $room,
        'day' => $day,
        'time' => $time,
        'lessons' => $lessons,
        'current' => $state['current'],
        'next' => $state['next'],
    ];
}

function ticky_group_teacher_lessons(array $lessons_raw): array {
    $grouped = [];

    foreach ($lessons_raw as $lesson) {
        $room = $lesson['termek']['terem_szam'] ?? '?';
        $class = $lesson['osztaly'] ?? '?';
        $start = ticky_normalize_hhmm($lesson['kezdes'] ?? '');
        $end = ticky_normalize_hhmm($lesson['vegzes'] ?? '');
        $key = $start . '_' . $end;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'kezdes' => $start,
                'vegzes' => $end,
                'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
                'tantargy' => $lesson['tantargy'] ?? '',
                'csoportok' => [],
            ];
        }

        $exists = false;
        foreach ($grouped[$key]['csoportok'] as $group) {
            if ($group['terem'] === $room && $group['osztaly'] === $class) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $grouped[$key]['csoportok'][] = [
                'terem' => $room,
                'osztaly' => $class,
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
            'tantargy' => $lesson['tantargy'],
            'is_csoport' => count($lesson['csoportok']) > 1,
            'terem' => implode(' / ', $rooms),
            'osztaly' => implode(', ', $classes),
            'csoportok' => $lesson['csoportok'],
        ];
    }

    usort($lessons, static fn(array $a, array $b): int => strcmp($a['kezdes'], $b['kezdes']));
    return $lessons;
}

function ticky_get_teacher_lessons(string $teacher_id, int $day): array {
    if ($day === 0) {
        return [];
    }

    $lessons_raw = sb_get('orarendek', [
        'tanar_id' => 'eq.' . $teacher_id,
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'select' => 'ora_sorszam,kezdes,vegzes,osztaly,tantargy,termek(terem_szam)',
        'order' => 'kezdes.asc,ora_sorszam.asc',
    ]);

    return ticky_group_teacher_lessons($lessons_raw);
}

function ticky_get_teacher_context(array $teacher, ?int $day = null, ?string $time = null): array {
    $day = $day ?? mai_nap();
    $time = $time ?? aktualis_ido();
    $lessons = ticky_get_teacher_lessons((string) $teacher['id'], $day);
    $state = ticky_pick_current_and_next_lesson($lessons, $time);

    return [
        'teacher' => $teacher,
        'day' => $day,
        'time' => $time,
        'lessons' => $lessons,
        'current' => $state['current'],
        'next' => $state['next'],
    ];
}

function ticky_get_rooms_snapshot(?int $day = null, ?string $time = null): array {
    $day = $day ?? mai_nap();
    $time = $time ?? aktualis_ido();
    $rooms = ticky_get_rooms();

    if (empty($rooms)) {
        return [];
    }

    if ($day === 0) {
        return array_map(static fn(array $room): array => [
            'terem_szam' => $room['terem_szam'],
            'emelet' => $room['emelet'],
            'allapot' => 'szabad',
            'aktualis' => null,
        ], $rooms);
    }

    $room_ids = array_column($rooms, 'id');
    $active_lessons = sb_get('orarendek', [
        'terem_id' => 'in.(' . implode(',', $room_ids) . ')',
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'kezdes' => 'lte.' . $time . ':00',
        'vegzes' => 'gte.' . $time . ':00',
        'select' => 'terem_id,osztaly,tantargy,kezdes,vegzes,tanar_id',
    ]);

    $teacher_map = ticky_get_teachers_by_ids(array_column($active_lessons, 'tanar_id'));
    $busy_by_room = [];

    foreach ($active_lessons as $lesson) {
        $teacher = $teacher_map[$lesson['tanar_id']] ?? null;
        $busy_by_room[$lesson['terem_id']] = [
            'tanar' => $teacher['rovid_nev'] ?? '?',
            'tanar_nev' => $teacher['nev'] ?? null,
            'osztaly' => $lesson['osztaly'] ?? '',
            'tantargy' => $lesson['tantargy'] ?? '',
            'kezdes' => ticky_normalize_hhmm($lesson['kezdes'] ?? ''),
            'vegzes' => ticky_normalize_hhmm($lesson['vegzes'] ?? ''),
        ];
    }

    $snapshot = [];
    foreach ($rooms as $room) {
        $snapshot[] = [
            'terem_szam' => $room['terem_szam'],
            'emelet' => $room['emelet'],
            'allapot' => isset($busy_by_room[$room['id']]) ? 'foglalt' : 'szabad',
            'aktualis' => $busy_by_room[$room['id']] ?? null,
        ];
    }

    return $snapshot;
}

function ticky_minutes_until(string $hhmm, ?string $from = null): int {
    $base_date = date('Y-m-d');
    $target = strtotime($base_date . ' ' . $hhmm);
    $current = strtotime($base_date . ' ' . ($from ?? aktualis_ido()));

    if ($target === false || $current === false) {
        return 0;
    }

    return max(0, (int) round(($target - $current) / 60));
}
