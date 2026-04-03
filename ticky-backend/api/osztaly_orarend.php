<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly.php';

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

$class_rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv' => 'eq.true',
    'order' => 'osztaly.asc',
]);

$class_code = osztaly_resolve_code_from_values($requested_code, array_column($class_rows, 'osztaly'));
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

$lessons_raw = sb_get('orarendek', [
    'osztaly' => 'eq.' . $class_code,
    'het_napja' => 'eq.' . $day,
    'aktiv' => 'eq.true',
    'select' => 'ora_sorszam,kezdes,vegzes,tantargy,tanar_id,termek(terem_szam)',
    'order' => 'kezdes.asc,ora_sorszam.asc',
]);

$teacher_ids = array_values(array_filter(array_unique(array_column($lessons_raw, 'tanar_id'))));
$teacher_map = [];
if (!empty($teacher_ids)) {
    $teachers = sb_get('tanarok', [
        'id' => 'in.(' . implode(',', $teacher_ids) . ')',
        'select' => 'id,rovid_nev,nev',
    ]);

    foreach ($teachers as $teacher) {
        $teacher_map[$teacher['id']] = $teacher;
    }
}

$grouped = [];
foreach ($lessons_raw as $lesson) {
    $room = $lesson['termek']['terem_szam'] ?? '?';
    $teacher = $teacher_map[$lesson['tanar_id']] ?? null;
    $teacher_code = $teacher['rovid_nev'] ?? '?';
    $teacher_name = $teacher['nev'] ?? null;
    $subject = trim((string) ($lesson['tantargy'] ?? ''));
    $start = substr((string) ($lesson['kezdes'] ?? ''), 0, 5);
    $end = substr((string) ($lesson['vegzes'] ?? ''), 0, 5);
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
            'tanar_nev' => $teacher_name,
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
