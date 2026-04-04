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
    // Ha van benne pont, aláhúzás vagy a szám 30-nál kisebb, az OSZTÁLY
    if (preg_match('/[._]/', $compact)) return false;
    if (preg_match('/^\d+$/', $compact)) return (int)$compact > 30;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    // 1. Ha vessző van benne, azt mindig szétvágjuk
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }

    // 2. SPECIÁLIS: Ha "szám/szám" (pl 1/9), azt EGYBEN hagyjuk, nem vágjuk szét!
    if (preg_match('/^\d+\/\d+/', trim($raw))) {
        $c = _osz_normalize($raw);
        if ($c !== '' && !_osz_is_room($c)) $codes[mb_strtolower($c, 'UTF-8')] = $c;
        return;
    }

    // 3. Ha egyéb perjel van (pl 9.a/9.b), azt szétvágjuk
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
// Adatok Supabase-ből
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) _osz_split_and_collect($row['osztaly'], $codes);
    }
}

// Adatok a JS fájlból (próbáljuk mindkét néven, hátha az ékezet a baj)
$js_files = [__DIR__ . '/../tanárok.js', __DIR__ . '/../tanarok.js'];
foreach($js_files as $js_path) {
    if (is_file($js_path)) {
        $contents = file_get_contents($js_path);
        preg_match_all("/\bclass\s*:\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
        foreach ($matches[1] as $raw) {
            _osz_split_and_collect($raw, $codes);
        }
    }
}

$result = array_values($codes);

// RENDEZÉS FIX
usort($result, function($a, $b) {
    $ga = 999; $gb = 999;

    // Évfolyam kinyerése (HT_13 -> 13, 1/9 -> 9, 9.f -> 9)
    auto_detect:
    if (preg_match('/(\d+)\./', $a, $m)) $ga = (int)$m[1];
    elseif (preg_match('/\/(\d+)/', $a, $m)) $ga = (int)$m[1];
    elseif (preg_match('/_(\d+)/', $a, $m)) $ga = (int)$m[1];
    elseif (preg_match('/^(\d+)/', $a, $m)) $ga = (int)$m[1];

    if (preg_match('/(\d+)\./', $b, $m)) $gb = (int)$m[1];
    elseif (preg_match('/\/(\d+)/', $b, $m)) $gb = (int)$m[1];
    elseif (preg_match('/_(\d+)/', $b, $m)) $gb = (int)$m[1];
    elseif (preg_match('/^(\d+)/', $b, $m)) $gb = (int)$m[1];

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
