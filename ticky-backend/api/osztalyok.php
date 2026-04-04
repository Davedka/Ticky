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
    if (str_contains($compact, '.') || str_contains($compact, '/') || str_contains($compact, '_')) return false;
    if (preg_match('/^\d{1,4}$/', $compact) === 1) return true;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    // Speciális eset: 1/9 Déri és társai maradjanak egyben
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

// Adatgyűjtés
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

// RENDEZÉS ÉS CSOPORTOSÍTÁS LOGIKA
usort($result, function($a, $b) {
    $lowA = mb_strtolower($a, 'UTF-8');
    $lowB = mb_strtolower($b, 'UTF-8');

    // 1. HT_ és 1/x kényszerítése a lista legvégére
    $specA = (str_starts_with($lowA, 'ht_') || str_contains($lowA, '1/'));
    $specB = (str_starts_with($lowB, 'ht_') || str_contains($lowB, '1/'));

    if ($specA && !$specB) return 1;
    if (!$specA && $specB) return -1;

    // 2. Évfolyam alapú sorrend (9, 10, 11, 12, 13)
    preg_match('/^(\d+)/', $a, $ma);
    preg_match('/^(\d+)/', $b, $mb);
    
    $ga = isset($ma[1]) ? (int)$ma[1] : 999;
    $gb = isset($mb[1]) ? (int)$mb[1] : 999;
    
    // Ha az egyikük 1-gyel kezdődik (pl 1/9), az is 999 legyen, hogy az Egyébben kössön ki
    if ($ga < 9) $ga = 999;
    if ($gb < 9) $gb = 999;

    if ($ga !== $gb) return $ga <=> $gb;
    
    // 3. ABC rend az évfolyamon belül (9.a < 9.f)
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
