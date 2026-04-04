<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    // Ha van benne pont, aláhúzás vagy perjel, az OSZTÁLY
    if (preg_match('/[._\/]/', $compact)) return false;
    if (preg_match('/^\d{1,4}$/', $compact) === 1) return true;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    // Az 1/9 Déri és hasonlókat ne daraboljuk, maradjon egyben
    if (preg_match('/^\d+\/\d+/', trim($raw))) {
        $c = _osz_normalize($raw);
        if ($c !== '' && !_osz_is_room($c)) $codes[mb_strtolower($c, 'UTF-8')] = $c;
        return;
    }
    if (str_contains($raw, '/')) {
        foreach (explode('/', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    $c = _osz_normalize($raw);
    if ($c !== '' && !_osz_is_room($c)) {
        $codes[mb_strtolower($c, 'UTF-8')] = $c;
    }
}

$codes = [];
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) _osz_split_and_collect($row['osztaly'], $codes);
    }
}

$js_path = __DIR__ . '/../tanárok.js';
if (is_file($js_path)) {
    $contents = file_get_contents($js_path);
    preg_match_all("/\bclass\s*:\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
    foreach ($matches[1] as $raw) {
        _osz_split_and_collect($raw, $codes);
    }
}

$result = array_values($codes);

usort($result, function($a, $b) {
    $lowA = mb_strtolower($a, 'UTF-8');
    $lowB = mb_strtolower($b, 'UTF-8');

    // Évfolyam kinyerése okosan:
    // 1/9 -> 9 | HT_13 -> 13 | 9.f -> 9
    auto_get_grade: 
    $ga = 999; $gb = 999;
    
    // A-ra:
    if (preg_match('/(\d+)/', $a, $m)) {
        $val = (int)$m[1];
        // Ha "1/9", akkor a 9-est keressük
        if (str_contains($a, '/') && preg_match('/\/(\d+)/', $a, $m2)) $val = (int)$m2[1];
        if ($val >= 9 && $val <= 14) $ga = $val;
    }
    // B-re:
    if (preg_match('/(\d+)/', $b, $m)) {
        $val = (int)$m[1];
        if (str_contains($b, '/') && preg_match('/\/(\d+)/', $b, $m2)) $val = (int)$m2[1];
        if ($val >= 9 && $val <= 14) $gb = $val;
    }

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
