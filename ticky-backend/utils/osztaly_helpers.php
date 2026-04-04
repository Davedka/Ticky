<?php
// utils/osztaly_helpers.php – Osztálykód segédfüggvények

function osztaly_normalize_code(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function osztaly_lower(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function osztaly_is_room_like_code(string $value): bool
{
    $compact = preg_replace('/\s+/u', '', osztaly_normalize_code($value)) ?? '';
    if ($compact === '') return false;
    if (preg_match('/^\d{1,4}$/', $compact) === 1) return true;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function osztaly_is_valid_code(string $value): bool
{
    $value = osztaly_normalize_code($value);
    if ($value === '' || osztaly_is_room_like_code($value)) return false;
    if (preg_match('/[\p{L}]/u', $value) !== 1) return false;
    return preg_match('/^[\p{L}\p{N}\s._\/-]{1,64}$/u', $value) === 1;
}

function osztaly_sort_compare(string $left, string $right): int
{
    $left  = osztaly_normalize_code($left);
    $right = osztaly_normalize_code($right);
    $lm = $rm = [];
    $lh = preg_match('/^(\d+)\.(.+)$/u', $left,  $lm) === 1;
    $rh = preg_match('/^(\d+)\.(.+)$/u', $right, $rm) === 1;
    if ($lh && $rh) {
        $cmp = (int) $lm[1] <=> (int) $rm[1];
        return $cmp !== 0 ? $cmp : strnatcasecmp($lm[2], $rm[2]);
    }
    if ($lh !== $rh) return $lh ? -1 : 1;
    return strnatcasecmp($left, $right);
}

function osztaly_unique_list(array $values): array
{
    $unique = [];
    foreach ($values as $value) {
        $code = osztaly_normalize_code((string) $value);
        if (!osztaly_is_valid_code($code)) continue;
        $unique[osztaly_lower($code)] = $code;
    }
    return array_values($unique);
}
