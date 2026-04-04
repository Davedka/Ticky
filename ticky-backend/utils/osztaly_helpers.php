<?php
// utils/osztaly_helpers.php

function osztaly_normalize_code(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function osztaly_is_valid_code(string $value): bool {
    $v = osztaly_normalize_code($value);
    if ($v === '') return false;
    // Csak a 30-nál nagyobb tiszta számok termek (pl. 204), a többi osztály
    if (preg_match('/^\d+$/', $v)) return (int)$v < 31;
    return true;
}

function osztaly_sort_compare(string $left, string $right): int {
    $helper_get_grade = function($name) {
        $upper = strtoupper($name);
        // HT vagy alsóvonal = Egyéb
        if (str_contains($upper, 'HT') || str_contains($name, '_')) return 999;
        
        if (preg_match('/^(\d+)\./', $name, $m)) return (int)$m[1];
        if (preg_match('/\/(\d+)/', $name, $m)) return (int)$m[1];
        if (preg_match('/^(\d+)/', $name, $m)) return (int)$m[1];
        return 999;
    };

    $ga = $helper_get_grade($left);
    $gb = $helper_get_grade($right);

    if ($ga !== $gb) return $ga <=> $gb;
    return strnatcasecmp($left, $right);
}
