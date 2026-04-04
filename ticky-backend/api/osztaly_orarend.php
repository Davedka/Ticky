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

$payload = ticky_source_class_lessons_for_day($class_code, $day);
if ($payload === null) {
    json_error('Osztály nem található: ' . $requested_code, 404);
}

if (SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
    $teacher_rows = sb_get('tanarok', [
        'select' => 'rovid_nev,nev',
        'order' => 'rovid_nev.asc',
    ]);

    $teacher_map = [];
    foreach ($teacher_rows as $teacher_row) {
        $teacher_code = osztaly_lower((string) ($teacher_row['rovid_nev'] ?? ''));
        $teacher_name = trim((string) ($teacher_row['nev'] ?? ''));
        if ($teacher_code === '' || $teacher_name === '') {
            continue;
        }

        $teacher_map[$teacher_code] = $teacher_name;
    }

    foreach ($payload['orak'] as &$lesson) {
        $lesson_teacher_codes = [];
        $lesson_teacher_names = [];

        foreach ($lesson['csoportok'] as &$group) {
            $group_code = trim((string) ($group['tanar'] ?? ''));
            $group_name = $teacher_map[osztaly_lower($group_code)] ?? null;
            $group['tanar_nev'] = $group_name;

            if ($group_code !== '' && !in_array($group_code, $lesson_teacher_codes, true)) {
                $lesson_teacher_codes[] = $group_code;
            }
            if ($group_name !== null && !in_array($group_name, $lesson_teacher_names, true)) {
                $lesson_teacher_names[] = $group_name;
            }
        }
        unset($group);

        $lesson['tanar'] = implode(' / ', $lesson_teacher_codes);
        $lesson['tanar_nev'] = implode(' / ', $lesson_teacher_names);
    }
    unset($lesson);
}

json_response($payload);
