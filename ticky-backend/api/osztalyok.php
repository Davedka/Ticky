<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

// ─── Osztálykód segédfüggvények (inline, fájlnév-független) ──────────
function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}
function _osz_lower(string $v): string {
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}
function _osz_is_room(string $v): bool {
    $c = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($c === '') return false;
    if (preg_match('/^\d{1,4}$/', $c)) return true;
    return (bool) preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $c);
}
function _osz_valid(string $v): bool {
    $v = _osz_normalize($v);
    if ($v === '' || _osz_is_room($v)) return false;
    if (!preg_match('/[\p{L}]/u', $v)) return false;
    return (bool) preg_match('/^[\p{L}\p{N}\s._\/-]{1,64}$/u', $v);
}
function _osz_sort(string $a, string $b): int {
    $am = $bm = [];
    $ah = preg_match('/^(\d+)\.(.+)$/u', _osz_normalize($a), $am) === 1;
    $bh = preg_match('/^(\d+)\.(.+)$/u', _osz_normalize($b), $bm) === 1;
    if ($ah && $bh) {
        $c = (int)$am[1] <=> (int)$bm[1];
        return $c !== 0 ? $c : strnatcasecmp($am[2], $bm[2]);
    }
    if ($ah !== $bh) return $ah ? -1 : 1;
    return strnatcasecmp($a, $b);
}

// ─── 1. Supabase – EREDETI LOGIKA (nem változott) ────────────────────
$rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv'  => 'eq.true',
    'order'  => 'osztaly.asc',
]);

$codes = [];
foreach ($rows as $row) {
    $code = _osz_normalize((string) ($row['osztaly'] ?? ''));
    if (_osz_valid($code)) {
        $codes[_osz_lower($code)] = $code;
    }
}

// ─── 2. tanárok.js – hozzáadjuk ami hiányzik ─────────────────────────
// ticky_source.php biztonságosan betöltjük; ha nem sikerül, a Supabase-lista marad
$ticky_source_file = __DIR__ . '/../utils/ticky_source.php';
if (is_file($ticky_source_file)) {
    try {
        require_once $ticky_source_file;
        if (function_exists('ticky_source_class_codes')) {
            foreach (ticky_source_class_codes() as $code) {
                $key = _osz_lower($code);
                if (!isset($codes[$key])) {
                    $codes[$key] = $code;
                }
            }
        }
    } catch (\Throwable $e) {
        // Ha bármi hiba van a ticky_source-ban, a Supabase-lista megmarad
    }
}

// ─── 3. Rendezés + válasz ─────────────────────────────────────────────
$list = array_values($codes);
usort($list, '_osz_sort');

json_response([
    'osztalyok' => $list,
    'count'     => count($list),
]);
