<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}

// Terem szűrés: ami ide beesik, az NEM jelenik meg az osztálylistában
function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    // Ha van benne pont vagy alsóvonal, az tuti OSZTÁLY
    if (str_contains($compact, '.') || str_contains($compact, '_')) return false;
    // Ha tiszta szám és 30 felett van, az terem
    if (preg_match('/^\d+$/', $compact)) return (int)$compact > 30;
    // Speciális teremkódok (K102, T2, stb.)
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    // Perjeles osztályok (pl 1/9) kezelése egyben
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
// 1. Adatbázis (Supabase)
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) _osz_split_and_collect($row['osztaly'], $codes);
    }
}
// 2. JS Fallback (tanárok.js)
$js_path = __DIR__ . '/../tanárok.js';
if (is_file($js_path)) {
    $contents = file_get_contents($js_path);
    preg_match_all("/\bclass\s*:\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
    foreach ($matches[1] as $raw) _osz_split_and_collect($raw, $codes);
}

$result = array_values($codes);

// --- SZIGORÚ SORREND ÉS KATEGORIZÁLÁS ---
usort($result, function($a, $b) {
    $get_grade = function($name) {
        $upper = strtoupper($name);
        
        // 1. SZABÁLY (Legmagasabb prioritás): HT vagy Alsóvonal -> EGYÉB (999)
        // Ez elkapja a 13.c_du-t is, hiába van benne a 13-as szám!
        if (str_contains($upper, 'HT') || str_contains($name, '_')) {
            return 999;
        }
        
        // 2. SZABÁLY: Normál évfolyam keresés (9.f, 13.c, 1/9)
        if (preg_match('/^(\d+)\./', $name, $m)) return (int)$m[1]; // "9.f" -> 9
        if (preg_match('/\/(\d+)/', $name, $m))  return (int)$m[1]; // "1/9" -> 9
        if (preg_match('/^(\d+)/', $name, $m))   return (int)$m[1]; // "9f" -> 9 (pont nélkül is)
        
        return 999; // Bármi más -> Egyéb
    };

    $ga = $get_grade($a);
    $gb = $get_grade($b);

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
