<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

handle_cors();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/osztaly/{kod}/orarend', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó osztály kód', 400);
}

$requested_code = osztaly_normalize_code(urldecode((string) $params['kod']));
if (!osztaly_is_valid_code($requested_code)) {
    json_error('Érvénytelen osztály kód', 400);
}

$class_code = ticky_source_resolve_class_code($requested_code);
if ($class_code === null) {
    json_error('Osztály nem található: ' . $requested_code, 404);
}

$day = mai_nap();
if ($day === 0) {
    json_response([
        'osztaly' => $class_code,
        'orak' => [],
        'uzenet' => 'Hétvége – nincs tanítás',
    ]);
}

$teacher_map = [];
if (SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
    $teachers = sb_get('tanarok', [
        'select' => 'rovid_nev,nev',
        'order' => 'rovid_nev.asc',
    ]);

    foreach ($teachers as $teacher) {
        $code = osztaly_lower((string) ($teacher['rovid_nev'] ?? ''));
        if ($code === '') {
            continue;
        }

        $teacher_map[$code] = trim((string) ($teacher['nev'] ?? ''));
    }
}

$grouped = [];
foreach (ticky_source_load_schedule_entries() as $lesson) {
    if (ticky_source_day_to_index((string) ($lesson['day'] ?? '')) !== $day) {
        continue;
    }

    $lesson_classes = array_values(array_filter(
        ticky_source_split_compound_value((string) ($lesson['class'] ?? '')),
        'osztaly_is_valid_code'
    ));

    $matches_class = false;
    foreach ($lesson_classes as $lesson_class) {
        if (osztaly_lower($lesson_class) === osztaly_lower($class_code)) {
            $matches_class = true;
            break;
        }
    }

    if (!$matches_class) {
        continue;
    }

    $room = ticky_source_normalize_token((string) ($lesson['room'] ?? '?'));
    $teacher_code = ticky_source_normalize_token((string) ($lesson['teacher'] ?? '?'));
    $teacher_name = $teacher_map[osztaly_lower($teacher_code)] ?? null;
    $subject = ticky_source_normalize_token((string) ($lesson['subject'] ?? ''));
    $start = substr(ticky_source_normalize_token((string) ($lesson['start'] ?? '')), 0, 5);
    $end = substr(ticky_source_normalize_token((string) ($lesson['end'] ?? '')), 0, 5);
    $key = $start . '_' . $end;

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'kezdes' => $start,
            'vegzes' => $end,
            'ora_sorszam' => ticky_source_period_number($start),
            'csoportok' => [],
        ];
    }

    $already_exists = false;
    foreach ($grouped[$key]['csoportok'] as $group) {
        if (
            $group['terem'] === $room
            && $group['tanar'] === $teacher_code
            && $group['tantargy'] === $subject
        ) {
            $already_exists = true;
            break;
        }
    }

    if (!$already_exists) {
        $grouped[$key]['csoportok'][] = [
            'terem' => $room,
            'tanar' => $teacher_code,
            'tanar_nev' => $teacher_name !== '' ? $teacher_name : null,
            'tantargy' => $subject,
        ];
    }
}

$lessons = [];
foreach ($grouped as $lesson) {
    $rooms = [];
    $teacher_codes = [];
    $teacher_names = [];
    $subjects = [];

    foreach ($lesson['csoportok'] as $group) {
        if (!in_array($group['terem'], $rooms, true)) {
            $rooms[] = $group['terem'];
        }
        if (!in_array($group['tanar'], $teacher_codes, true)) {
            $teacher_codes[] = $group['tanar'];
        }
        if (!empty($group['tanar_nev']) && !in_array($group['tanar_nev'], $teacher_names, true)) {
            $teacher_names[] = $group['tanar_nev'];
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
        'tanar_nev' => implode(' / ', $teacher_names),
        'tantargy' => implode(' / ', $subjects),
        'csoportok' => $lesson['csoportok'],
    ];
}

usort($lessons, static fn(array $left, array $right): int => strcmp($left['kezdes'], $right['kezdes']));

json_response([
    'osztaly' => $class_code,
    'orak' => $lessons,
]);
