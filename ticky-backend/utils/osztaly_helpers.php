<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    $v = trim($value);
    return preg_replace('/\s+/u', ' ', $v) ?? $v;
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    // Csak a 30-nál nagyobb tiszta számokat szűrjük ki (termek)
    if (preg_match('/^\d+$/', $v) && (int)$v > 30) return false; 
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    // Kényszerített "Egyéb" kódok (ezek a lista végére kerülnek)
    $egyeb_mintak = ['ht_', '1/8'];
    
    $left_is_egyeb = false;
    foreach($egyeb_mintak as $m) if(str_contains(mb_strtolower($left), $m)) $left_is_egyeb = true;
    
    $right_is_egyeb = false;
    foreach($egyeb_mintak as $m) if(str_contains(mb_strtolower($right), $m)) $right_is_egyeb = true;

    if ($left_is_egyeb && !$right_is_egyeb) return 1;
    if (!$left_is_egyeb && $right_is_egyeb) return -1;

    // Normál évfolyam alapú rendezés (9, 10, 11, 12, 13)
    preg_match('/^(\d+)/', $left, $m1);
    preg_match('/^(\d+)/', $right, $m2);
    
    $n1 = isset($m1[1]) ? (int)$m1[1] : 999;
    $n2 = isset($m2[1]) ? (int)$m2[1] : 999;
    
    if ($n1 !== $n2) return $n1 <=> $n2;
    return strnatcasecmp($left, $right);
}
