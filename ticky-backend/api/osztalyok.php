<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly.php';

handle_cors();

$rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv' => 'eq.true',
    'order' => 'osztaly.asc',
]);

$class_list = osztaly_unique_list(array_column($rows, 'osztaly'));
usort($class_list, 'osztaly_sort_compare');

json_response([
    'osztalyok' => $class_list,
    'count' => count($class_list),
]);
