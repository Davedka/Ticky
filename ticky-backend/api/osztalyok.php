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
    // Ha van benne pont, perjel vagy alsóvonal, az OSZTÁLY
    if (preg_match('/[._\/]/', $compact)) return false;
    if (preg_match('/^\d+$/', $compact)) return (int)$compact > 30;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    // 1/9 formátum egyben tartása
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
    foreach ($matches[1] as $raw) _osz_split_and_collect($raw, $codes);
}

$result = array_values($codes);

// SPECIÁLIS RENDEZÉS ÉS KATEGORIZÁLÁS
usort($result, function($a, $b) {
    $get_grade = function($name) {
        $upper = strtoupper($name);
        
        // 1. SZABÁLY: Ha benne van a "HT" (akár HT, akár HT_), menjen az Egyébbe (999)
        if (str_contains($upper, 'HT')) return 999;
        
        // 2. SZABÁLY: Ha van benne alsóvonal (pl. 13.c_du), menjen az Egyébbe (999)
        if (str_contains($name, '_')) return 999;
        
        // 3. NORMÁL BESOROLÁS (9.f, 1/9, 10.b)
        // Ha számmal kezdődik és pont követi (9.f)
        if (preg_match('/^(\d+)\./', $name, $m)) return (int)$m[1];
        // Ha perjel után van a szám (1/9 -> 9)
        if (preg_match('/\/(\d+)/', $name, $m)) return (int)$m[1];
        // Ha csak simán számmal kezdődik
        if (preg_match('/^(\d+)/', $name, $m)) return (int)$m[1];
        
        return 999; // Minden más "Egyéb"
    };

    $ga = $get_grade($a);
    $gb = $get_grade($b);

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
