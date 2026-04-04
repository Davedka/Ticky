<?php
require_once __DIR__ . '/../config/supabase.php'; // Ellenőrizd, hogy az elérési út nálad is ez-e!
require_once __DIR__ . '/../utils/helpers.php';

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\\s+/u', ' ', $v) ?? $v);
}

function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    
    // VÁLTOZTATÁS: Ha van benne pont, kötőjel, vagy perjel (pl. 1/9, 10.A), az biztosan osztály!
    if (str_contains($compact, '.') || str_contains($compact, '/') || str_contains($compact, '_')) return false;
    
    // Termek szűrése
    if (preg_match('/^\\d{1,4}$/', $compact) === 1) return true;
    return preg_match('/^(?:K\\d{1,4}|T\\d{1,2}|M\\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    if (str_contains($raw, '/')) {
        // VÁLTOZTATÁS: Kivétel a perjelre, hogy az 1/9 Déri egyben maradjon!
        if (preg_match('/^\d+\/\d+/', trim($raw))) {
            $c = _osz_normalize($raw);
            if ($c !== '' && !_osz_is_room($c)) $codes[mb_strtolower($c, 'UTF-8')] = $c;
            return;
        }
        foreach (explode('/', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    $c = _osz_normalize($raw);
    if ($c !== '' && !_osz_is_room($c)) {
        $codes[mb_strtolower($c, 'UTF-8')] = $c;
    }
}

$codes = [];

// Adatbázisból (Supabase)
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) _osz_split_and_collect($row['osztaly'], $codes);
    }
}

// JS fájlból (tanárok.js)
$js_path = __DIR__ . '/../tanárok.js'; // Ellenőrizd a mappaszerkezetet!
if (is_file($js_path)) {
    $contents = file_get_contents($js_path);
    preg_match_all("/\\bclass\\s*:\\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
    foreach ($matches[1] as $raw) {
        _osz_split_and_collect($raw, $codes);
    }
}

$result = array_values($codes);

// VÁLTOZTATÁS: Sorrendezés 9-től a végéig
usort($result, function($a, $b) {
    preg_match('/^(\d+)/', $a, $ma);
    preg_match('/^(\d+)/', $b, $mb);
    $ga = isset($ma[1]) ? (int)$ma[1] : 999; // Ha nincs szám az elején, megy a végére (Egyéb)
    $gb = isset($mb[1]) ? (int)$mb[1] : 999;
    
    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($a, $b);
});

json_response(['osztalyok' => $result, 'count' => count($result)]);
