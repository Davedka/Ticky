<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

handle_cors();

$class_list = ticky_source_class_codes();

json_response([
    'osztalyok' => $class_list,
    'count' => count($class_list),
]);
