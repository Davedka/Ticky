<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';
require_once __DIR__ . '/../utils/ticky_source.php';

handle_cors();

// ─── 1. Supabase (eredeti logika) ────────────────────────────────────
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

// ─── 2. tanárok.js – ami még nincs benne ─────────────────────────────
foreach (ticky_source_class_codes() as $code) {
    $key = osztaly_lower($code);
    if (!isset($codes[$key])) {
        $codes[$key] = $code;
    }
}

// ─── 3. Rendezés ─────────────────────────────────────────────────────
$list = array_values($codes);
usort($list, 'osztaly_sort_compare');

json_response([
    'osztalyok' => $list,
    'count'     => count($list),
]);
