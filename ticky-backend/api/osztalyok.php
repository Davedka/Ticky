<?php
// api/osztalyok.php
// GET /api/osztalyok - osszes egyedi osztaly listaja

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

function osztaly_sort_compare(string $left, string $right): int {
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

$rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv' => 'eq.true',
    'order' => 'osztaly.asc',
]);

$classes = [];
foreach ($rows as $row) {
    $class_code = trim((string) ($row['osztaly'] ?? ''));
    if ($class_code === '') {
        continue;
    }

    $classes[$class_code] = true;
}

$class_list = array_keys($classes);
usort($class_list, 'osztaly_sort_compare');

json_response([
    'osztalyok' => $class_list,
    'count' => count($class_list),
]);
