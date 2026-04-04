<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';

handle_cors();

$rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv'  => 'eq.true',
    'order'  => 'osztaly.asc',
]);

$codes = [];
foreach ($rows as $row) {
    $code = osztaly_normalize_code((string) ($row['osztaly'] ?? ''));
    if (osztaly_is_valid_code($code)) {
        $codes[osztaly_lower($code)] = $code;
    }
}

$list = array_values($codes);
usort($list, 'osztaly_sort_compare');

json_response([
    'osztalyok' => $list,
    'count'     => count($list),
]);
