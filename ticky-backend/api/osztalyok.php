<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

// ─── Segédfüggvények ─────────────────────────────────────────────────

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function _osz_lower(string $v): string {
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}

/**
 * Teremszámnak látszó kód? → kiszűrjük az osztálylistából.
 * Terem: csak szám (24, 204), K+szám (K1, K107), T+1-2szám (T1, T3),
 *        m/M+szám (m17, m22), KT, Kt.
 * FONTOS: ha tartalmaz pontot (9.a, 12.c) vagy aláhúzást (HT_13.ap) → NEM terem.
 */
function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    if (str_contains($compact, '.') || str_contains($compact, '_')) return false;
    if (preg_match('/^\d{1,4}$/', $compact)) return true;
    if (preg_match('/^K\d{1,4}$/i', $compact)) return true;
    if (preg_match('/^T\d{1,2}$/i', $compact)) return true;
    if (preg_match('/^[Mm]\d{1,3}$/', $compact)) return true;
    if (strtoupper($compact) === 'KT') return true;
    return false;
}

/**
 * Érvényes osztálykód-e?
 */
function _osz_valid(string $v): bool {
    $v = _osz_normalize($v);
    if ($v === '') return false;
    if (_osz_is_room($v)) return false;
    if (preg_match('/[\p{L}]/u', $v) !== 1) return false;
    return (bool) preg_match('/^[\p{L}\p{N}\s._\/_-]{1,64}$/u', $v);
}

/**
 * Rendezés: évfolyamszám szerint növekvő, azon belül betű szerint.
 * Speciális kódok a végére kerülnek.
 */
function _osz_sort(string $a, string $b): int {
    $an = _osz_normalize($a);
    $bn = _osz_normalize($b);
    $am = $bm = [];
    $ah = preg_match('/^(\d+)\.(.+)$/u', $an, $am) === 1;
    $bh = preg_match('/^(\d+)\.(.+)$/u', $bn, $bm) === 1;
    if ($ah && $bh) {
        $c = (int) $am[1] <=> (int) $bm[1];
        return $c !== 0 ? $c : strnatcasecmp($am[2], $bm[2]);
    }
    if ($ah !== $bh) return $ah ? -1 : 1;
    return strnatcasecmp($an, $bn);
}

/**
 * Compound class értékek szétválasztása és egyedi kódok gyűjtése.
 * Pl. "9.b/9.e" → ["9.b", "9.e"]
 * Pl. "1/9 Déri" → egységes kód, nem bontjuk szét (szóközt tartalmaz)
 * Pl. "HT_13.ap" → egységes kód (aláhúzást tartalmaz)
 */
function _osz_split_and_collect(string $raw, array &$codes): void {
    $raw = _osz_normalize($raw);
    if ($raw === '') return;

    // Szóközt tartalmaz → egységes összetett kód (pl. "1/9 Déri")
    if (str_contains($raw, ' ')) {
        $key = _osz_lower($raw);
        if (!isset($codes[$key]) && _osz_valid($raw)) {
            $codes[$key] = $raw;
        }
        return;
    }

    // Aláhúzást tartalmaz → HT_ típusú kód (pl. "HT_13.ap", "13.c_du")
    if (str_contains($raw, '_')) {
        $key = _osz_lower($raw);
        if (!isset($codes[$key]) && _osz_valid($raw)) {
            $codes[$key] = $raw;
        }
        return;
    }

    // Perjellel elválasztott compound értékek szétválasztása
    $parts = explode('/', $raw);
    foreach ($parts as $part) {
        $part = _osz_normalize($part);
        if ($part === '') continue;
        $key = _osz_lower($part);
        if (!isset($codes[$key]) && _osz_valid($part)) {
            $codes[$key] = $part;
        }
    }
}

// ─── 1. Supabase – osztályok az orarendek táblából ───────────────────
$rows = sb_get('orarendek', [
    'select' => 'osztaly',
    'aktiv'  => 'eq.true',
    'order'  => 'osztaly.asc',
]);

$codes = [];
foreach ($rows as $row) {
    $raw = _osz_normalize((string) ($row['osztaly'] ?? ''));
    _osz_split_and_collect($raw, $codes);
}

// ─── 2. tanárok.js közvetlen olvasás ─────────────────────────────────
// Megkeressük a fájlt az ismert útvonalakon.
$js_candidates = [
    dirname(__DIR__) . '/tanárok.js',
    dirname(__DIR__) . '/tanarok.js',
    dirname(__DIR__, 2) . '/tanárok.js',
    dirname(__DIR__, 2) . '/tanarok.js',
    '/var/www/html/tanárok.js',
    '/var/www/html/tanarok.js',
    '/app/tanárok.js',
    '/app/tanarok.js',
];

$js_loaded = false;
foreach ($js_candidates as $js_path) {
    if (is_file($js_path) && is_readable($js_path)) {
        $contents = file_get_contents($js_path);
        if ($contents !== false) {
            preg_match_all(
                "/\bclass\s*:\s*['\"]([^'\"]+)['\"]/u",
                $contents,
                $matches
            );
            foreach ($matches[1] as $raw) {
                _osz_split_and_collect($raw, $codes);
            }
            $js_loaded = true;
            break;
        }
    }
}

// ─── 3. ticky_source.php fallback (ha a JS nem volt elérhető) ────────
if (!$js_loaded) {
    $ticky_source_file = __DIR__ . '/../utils/ticky_source.php';
    if (is_file($ticky_source_file)) {
        try {
            require_once $ticky_source_file;
            if (function_exists('ticky_source_expected_lessons')) {
                foreach (ticky_source_expected_lessons() as $lesson) {
                    $raw = _osz_normalize((string) ($lesson['osztaly'] ?? ''));
                    _osz_split_and_collect($raw, $codes);
                }
            }
        } catch (\Throwable $e) {
            // elnyelük
        }
    }
}

// ─── 4. Rendezés + válasz ─────────────────────────────────────────────
$list = array_values($codes);
usort($list, '_osz_sort');

json_response([
    'osztalyok' => $list,
    'count'     => count($list),
]);
