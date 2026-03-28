<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$teachers = array_map(static fn(array $teacher): array => [
    'rovid_nev' => $teacher['rovid_nev'] ?? '',
    'nev' => $teacher['nev'] ?? null,
], ticky_get_teacher_directory());

json_response([
    'tanarok' => $teachers,
    'count' => count($teachers),
]);
