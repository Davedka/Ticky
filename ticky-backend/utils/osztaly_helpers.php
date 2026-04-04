<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    $v = trim($value);
    return preg_replace('/\s+/u', ' ', $v) ?? $v;
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    // Teremszám szűrés (ha csak szám és 30-nál nagyobb, akkor terem)
    if (preg_match('/^\d+$/', $v) && (int)$v > 30) return false; 
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    $a = mb_strtolower($left, 'UTF-8');
    $b = mb_strtolower($right, 'UTF-8');

    $ga = 999; $gb = 999;
    
    // Évfolyam detektálás (kezeli a 1/9-et és a HT_13-at is)
    if (preg_match('/(\d+)/', $left, $m)) {
        $v = (int)$m[1];
        if (str_contains($left, '/') && preg_match('/\/(\d+)/', $left, $m2)) $v = (int)$m2[1];
        if ($v >= 9 && $v <= 14) $ga = $v;
    }
    if (preg_match('/(\d+)/', $right, $m)) {
        $v = (int)$m[1];
        if (str_contains($right, '/') && preg_match('/\/(\d+)/', $right, $m2)) $v = (int)$m2[1];
        if ($v >= 9 && $v <= 14) $gb = $v;
    }

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($left, $right);
}
