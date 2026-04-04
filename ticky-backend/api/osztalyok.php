<?php
// api/osztalyok.php vége felé, a válasz összeállítása:

// ... (a kód eleje marad: require-ek és a $codes feltöltése)

$final_codes = array_values($codes);
usort($final_codes, 'osztaly_sort_compare');

$groups = [
    '9' => [],
    '10' => [],
    '11' => [],
    '12' => [],
    '13' => [],
    'Egyéb' => []
];

// Speciális "Egyéb" kategóriába kényszerített szavak
$force_egyeb_prefixes = ['ht_', '1/8'];

foreach ($final_codes as $kod) {
    $low_kod = mb_strtolower($kod, 'UTF-8');
    $placed = false;

    // 1. Ellenőrizzük, hogy kényszerített egyéb-e (HT_13, 1/8 Déri)
    foreach ($force_egyeb_prefixes as $prefix) {
        if (str_starts_with($low_kod, $prefix)) {
            $groups['Egyéb'][] = $kod;
            $placed = true;
            break;
        }
    }
    if ($placed) continue;

    // 2. Évfolyam kinyerése (9.f, 13.c_du, stb.)
    if (preg_match('/^(\d+)/', $kod, $m)) {
        $evf = $m[1];
        if (isset($groups[$evf])) {
            $groups[$evf][] = $kod;
            $placed = true;
        }
    }

    // 3. Ha nem sikerült besorolni, megy az egyébhez
    if (!$placed) {
        $groups['Egyéb'][] = $kod;
    }
}

// Üres csoportok törlése
$groups = array_filter($groups, fn($g) => !empty($g));

// Tiszta PHP JSON válasz (HTML nélkül)
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'count' => count($final_codes),
    'groups' => $groups
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
