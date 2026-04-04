<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    // Ha csak szám és > 30, akkor terem (pl. 204), egyébként osztály (pl. 9)
    if (preg_match('/^\d+$/', $v)) return (int)$v < 31;
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    $ga = 999; $gb = 999;
    
    // Ugyanaz a felismerő logika, mint az API-ban
    if (preg_match('/(\d+)\./', $left, $m)) $ga = (int)$m[1];
    elseif (preg_match('/\/(\d+)/', $left, $m)) $ga = (int)$m[1];
    elseif (preg_match('/_(\d+)/', $left, $m)) $ga = (int)$m[1];
    elseif (preg_match('/^(\d+)/', $left, $m)) $ga = (int)$m[1];

    if (preg_match('/(\d+)\./', $right, $m)) $gb = (int)$m[1];
    elseif (preg_match('/\/(\d+)/', $right, $m)) $gb = (int)$m[1];
    elseif (preg_match('/_(\d+)/', $right, $m)) $gb = (int)$m[1];
    elseif (preg_match('/^(\d+)/', $right, $m)) $gb = (int)$m[1];

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($left, $right);
}
