<?php
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

json_response($payload);
