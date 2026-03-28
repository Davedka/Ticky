<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

if (request_method() !== 'POST') {
    json_error('Csak POST kÃ©rÃ©s engedÃ©lyezett', 405);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$teacher_code = strtoupper(trim((string) ($body['kod'] ?? '')));
$teacher_name = trim((string) ($body['nev'] ?? ''));

if ($teacher_code === '') {
    json_error('HiÃ¡nyzÃ³ tanÃ¡r kÃ³d', 400);
}

$teacher = ticky_find_teacher_by_code($teacher_code);
if ($teacher === null) {
    json_error('TanÃ¡r nem talÃ¡lhatÃ³: ' . $teacher_code, 404);
}

$ok = sb_patch('tanarok', [
    'id' => 'eq.' . $teacher['id'],
], [
    'nev' => $teacher_name !== '' ? $teacher_name : null,
]);

if (!$ok) {
    json_error('Supabase frissÃ­tÃ©si hiba', 500);
}

json_response([
    'ok' => true,
    'kod' => $teacher_code,
    'nev' => $teacher_name,
]);
