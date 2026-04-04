<?php
// api/osztalyok.php
// GET /api/osztalyok – összes egyedi osztály kód

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

// ─── Osztálykód segédfüggvények ───────────────────────────────────────

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function _osz_lower(string $v): string {
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}

/**
 * Teremszámnak látszó kód? (kiszűrjük belőle az osztálylistából)
 * Csak akkor terem, ha:
 *  - pusztán szám: 24, 204, 307 stb.
 *  - K + szám: K1, K101, K314 stb.
 *  - T + 1-2 számjegy: T1, T2, T3 (tornacsarnok)
 *  - M + szám: m17, m22 stb.
 *  - KT (tornaterem rövidítés)
 * FONTOS: a pont tartalmaz kódok (9.b, HT_13.ap stb.) soha nem számítanak teremnek.
 */
function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    // Ha tartalmaz pontot → osztálykód, nem terem
    if (str_contains($compact, '.')) return false;
    // Ha tartalmaz aláhúzást → HT_ típusú kód, nem terem
    if (str_contains($compact, '_')) return false;
    // Csak szám → terem (pl. 24, 204)
    if (preg_match('/^\d{1,4}$/', $compact)) return true;
    // K + szám (kollégium termek)
    if (preg_match('/^K\d{1,4}$/i', $compact)) return true;
    // T + 1-2 szám (tornaterem)
    if (preg_match('/^T\d{1,2}$/i', $compact)) return true;
    // m + szám (műhely)
    if (preg_match('/^[Mm]\d{1,3}$/', $compact)) return true;
    // KT
    if (strtoupper($compact) === 'KT') return true;
    return false;
}

/**
 * Érvényes osztálykód?
 * Átmennek: 9.a, 9.b, 12.c, HT_13.ap, 1/9 Déri, 13.c_du stb.
 * Kiesnek: teremszámok, üres stringek, csak számok
 */
function _osz_valid(string $v): bool {
    $v = _osz_normalize($v);
    if ($v === '') return false;
    if (_osz_is_room($v)) return false;
    // Kell tartalmaznia legalább egy betűt
    if (!preg_match('/[\p{L}]/u', $v)) return false;
    // Megengedett karakterek: betű, szám, szóköz, pont, aláhúzás, perjel, kötőjel
    return (bool) preg_match('/^[\p{L}\p{N}\s._\/_-]{1,64}$/u', $v);
}

/**
 * Rendezési összehasonlítás.
 * Elsődleges: évfolyamszám (1., 9., 10., 13. stb.)
 * Másodlagos: az osztályjelzés (a, b, c…) betű szerint
 * Különleges kódok (HT_, 1/9 Déri stb.) a végére kerülnek.
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

// ─── 1. Supabase – osztályok az orarendek táblából ───────────────────
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

// ─── 2. tanárok.js / ticky_source fallback ───────────────────────────
// Ha a Supabase üres (még nincs importálva), a JS forrásból töltjük fel.
// Ha a Supabase részleges (pl. hiányoznak évfolyamok), kiegészítjük.
$ticky_source_file = __DIR__ . '/../utils/ticky_source.php';
if (is_file($ticky_source_file)) {
    try {
        require_once $ticky_source_file;
        if (function_exists('ticky_source_expected_lessons')) {
            // Közvetlenül az összes bejegyzésből vonjuk ki az osztálykódokat,
            // megkerülve az osztaly.php-ban definiált segédfüggvényeket,
            // hogy ne ütközzünk esetleges névkonfliktusokkal.
            foreach (ticky_source_expected_lessons() as $lesson) {
                $code = _osz_normalize((string) ($lesson['osztaly'] ?? ''));
                if ($code === '') continue;
                $key = _osz_lower($code);
                if (!isset($codes[$key]) && _osz_valid($code)) {
                    $codes[$key] = $code;
                }
            }
        } elseif (function_exists('ticky_source_class_codes')) {
            // Régebbi fallback
            foreach (ticky_source_class_codes() as $code) {
                $key = _osz_lower($code);
                if (!isset($codes[$key])) {
                    $codes[$key] = $code;
                }
            }
        }
    } catch (\Throwable $e) {
        // Ha a ticky_source betöltése hibát dob, a Supabase lista marad
    }
}

// ─── 3. Ha még mindig üres, közvetlenül a SCHEDULE_DATA-ból olvassuk ─
// Ez a legbiztosabb fallback: beolvassuk a tanárok.js-t raw módon.
if (empty($codes)) {
    $js_file = dirname(__DIR__, 1) . '/tanárok.js';
    if (!is_file($js_file)) {
        // Alternatív útvonal
        $js_file = dirname(__DIR__, 2) . '/tanárok.js';
    }
    if (is_file($js_file) && is_readable($js_file)) {
        $contents = file_get_contents($js_file);
        if ($contents !== false) {
            // Osztály értékek kinyerése: class: '...' minták
            preg_match_all("/class\s*:\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
            foreach ($matches[1] as $raw) {
                // Compound értékek szétválasztása (pl. "9.b/9.e" vagy "12.c/12.b/12.e")
                $parts = explode('/', $raw);
                foreach ($parts as $part) {
                    $code = _osz_normalize($part);
                    if ($code === '') continue;
                    $key = _osz_lower($code);
                    if (!isset($codes[$key]) && _osz_valid($code)) {
                        $codes[$key] = $code;
                    }
                }
                // Az eredeti compound értéket is adjuk hozzá, ha egységként érvényes
                // (pl. "9.b/9.e" nem érvényes osztálykód – kizárjuk)
            }
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
