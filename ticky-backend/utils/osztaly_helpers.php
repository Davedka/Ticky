<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    $v = trim($value);
    return preg_replace('/\s+/u', ' ', $v) ?? $v;
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    // Csak a túl nagy teremszámokat szűrjük (30 felett)
    if (preg_match('/^\d{1,4}$/', $v) && (int)$v > 30) return false; 
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    $lowL = mb_strtolower($left, 'UTF-8');
    $lowR = mb_strtolower($right, 'UTF-8');

    // 1. Speciális prefixek (HT_) kényszerítése a végére
    $isHtL = str_starts_with($lowL, 'ht_');
    $isHtR = str_starts_with($lowR, 'ht_');
    if ($isHtL && !$isHtR) return 1;
    if (!$isHtL && $isHtR) return -1;

    // 2. Évfolyam kinyerése
    // Megkeressük az első számcsoportot (pl. 9, 10, 13, vagy az 1/9-nél az 1-est)
    preg_match('/(\d+)/', $left, $m1);
    preg_match('/(\d+)/', $right, $m2);
    
    $n1 = isset($m1[1]) ? (int)$m1[1] : 999;
    $n2 = isset($m2[1]) ? (int)$m2[1] : 999;

    // Ha 1/9, 2/10 stb van, az menjen az egyéb (999) kategóriába, ne az 1. évfolyamhoz
    if ($n1 < 9) $n1 = 999;
    if ($n2 < 9) $n2 = 999;
    
    if ($n1 !== $n2) return $n1 <=> $n2;
    
    // 3. Ha az évfolyam azonos, akkor természetes ABC (9.a, 9.f, 9.k...)
    return strnatcasecmp($left, $right);
}
