<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód, kizárólag tanárok.js-ből

require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';
require_once __DIR__ . '/../utils/ticky_source.php';

handle_cors();

$list = ticky_source_class_codes();

json_response([
    'osztalyok' => $list,
    'count'     => count($list),
]);
