<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$params = match_route('/api/tanar/{kod}/orarend', request_path());
if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó tanár kód', 400);
}

$teacher_code = strtoupper(urldecode((string) $params['kod']));
$day = mai_nap();

if ($day === 0) {
    json_response([
        'tanar_nev' => null,
        'orak' => [],
        'uzenet' => 'Hétvége – nincs tanítás',
    ]);
}

$teacher = ticky_find_teacher_by_code($teacher_code);
if ($teacher === null) {
    json_error('Tanár nem található: ' . $teacher_code, 404);
}

json_response([
    'tanar_nev' => $teacher['nev'] ?? null,
    'orak' => ticky_get_teacher_lessons((string) $teacher['id'], $day),
]);
