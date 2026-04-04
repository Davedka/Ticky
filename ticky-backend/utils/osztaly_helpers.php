<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    $v = trim($value);
    return preg_replace('/\\s+/u', ' ', $v) ?? $v;
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    
    // VÁLTOZTATÁS: Kivettük a szigorú 10,11,12 szűrést.
    // Csak a tisztán számokat (pl. terem 204) szűrjük ki, ami 30-nál nagyobb. 
    // Így az 1/9 vagy a 13.e gond nélkül átmegy!
    if (preg_match('/^\\d{1,4}$/', $v) && (int)$v > 30) return false; 
    
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    preg_match('/^(\d+)/', $left, $m1);
    preg_match('/^(\d+)/', $right, $m2);
    
    $n1 = isset($m1[1]) ? (int)$m1[1] : 999;
    $n2 = isset($m2[1]) ? (int)$m2[1] : 999;
    
    if ($n1 !== $n2) return $n1 <=> $n2;
    return strnatcasecmp($left, $right);
}
