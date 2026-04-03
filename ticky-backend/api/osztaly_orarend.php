<?php
// api/osztaly_orarend.php
// GET /api/osztaly/{kod}/orarend
// Visszaadja az adott osztaly mai orarendjet, a parhuzamos orakat osszevonva.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

function osztaly_valid_code(string $value): bool {
    return preg_match('/^[\p{L}\p{N}.-]{1,32}$/u', $value) === 1;
}

function osztaly_sort_compare_api(string $left, string $right): int {
    $left = trim($left);
    $right = trim($right);

    $left_match = [];
    $right_match = [];
    $left_has_grade = preg_match('/^(\d+)\.(.+)$/u', $left, $left_match) === 1;
    $right_has_grade = preg_match('/^(\d+)\.(.+)$/u', $right, $right_match) === 1;

    if ($left_has_grade && $right_has_grade) {
        $grade_compare = (int) $left_match[1] <=> (int) $right_match[1];
        if ($grade_compare !== 0) {
            return $grade_compare;
        }

        return strnatcasecmp($left_match[2], $right_match[2]);
    }

    return strnatcasecmp($left, $right);
}

function osztaly_resolve_code(string $requested_code): ?string {
    $rows = sb_get('orarendek', [
        'select' => 'osztaly',
        'aktiv' => 'eq.true',
        'order' => 'osztaly.asc',
    ]);

    $requested_normalized = function_exists('mb_strtolower')
        ? mb_strtolower($requested_code, 'UTF-8')
        : strtolower($requested_code);

    $classes = [];
    foreach ($rows as $row) {
        $class_code = trim((string) ($row['osztaly'] ?? ''));
        if ($class_code === '') {
            continue;
        }

        $classes[$class_code] = true;
        $normalized_code = function_exists('mb_strtolower')
            ? mb_strtolower($class_code, 'UTF-8')
            : strtolower($class_code);

        if ($normalized_code === $requested_normalized) {
            return $class_code;
        }
    }

    if (isset($classes[$requested_code])) {
        return $requested_code;
    }

    return null;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/osztaly/{kod}/orarend', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hianyzo osztaly kod', 400);
}

$requested_code = trim(urldecode((string) $params['kod']));
if (!osztaly_valid_code($requested_code)) {
    json_error('Ervenytelen osztaly kod', 400);
}

$class_code = osztaly_resolve_code($requested_code);
if ($class_code === null) {
    json_error('Osztaly nem talalhato: ' . $requested_code, 404);
}

$day = mai_nap();
if ($day === 0) {
    json_response([
        'osztaly' => $class_code,
        'orak' => [],
        'uzenet' => 'Hetvege - nincs tanitas',
    ]);
}

$lessons_raw = sb_get('orarendek', [
    'osztaly' => 'ilike.' . $class_code,
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
    $teachers_codes = [];
    $teachers_names = [];
    $subjects = [];

    foreach ($lesson['csoportok'] as $group) {
        if (!in_array($group['terem'], $rooms, true)) {
            $rooms[] = $group['terem'];
        }
        if (!in_array($group['tanar'], $teachers_codes, true)) {
            $teachers_codes[] = $group['tanar'];
        }
        if (!empty($group['tanar_nev']) && !in_array($group['tanar_nev'], $teachers_names, true)) {
            $teachers_names[] = $group['tanar_nev'];
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
        'tanar' => implode(' / ', $teachers_codes),
        'tanar_nev' => implode(' / ', $teachers_names),
        'tantargy' => implode(' / ', $subjects),
        'csoportok' => $lesson['csoportok'],
    ];
}

usort($lessons, static fn(array $left, array $right): int => strcmp($left['kezdes'], $right['kezdes']));

json_response([
    'osztaly' => $class_code,
    'orak' => $lessons,
]);
