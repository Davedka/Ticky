<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';
require_once __DIR__ . '/../utils/valasz_cache.php';


handle_cors();

function _osz_normalize(string $v): string {
    $v = trim($v);
    return $v === '' ? '' : (preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function _osz_is_room(string $v): bool {
    $compact = preg_replace('/\s+/u', '', _osz_normalize($v)) ?? '';
    if ($compact === '') return false;
    if (str_contains($compact, '.') || str_contains($compact, '_')) return false;
    if (preg_match('/^\d+$/', $compact)) return (int)$compact > 30;
    return preg_match('/^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/iu', $compact) === 1;
}

function _osz_split_and_collect(string $raw, array &$codes): void {
    if (str_contains($raw, ',')) {
        foreach (explode(',', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    if (preg_match('/^\d+\/\d+/', trim($raw))) {
        $c = _osz_normalize($raw);
        if ($c !== '' && !_osz_is_room($c)) $codes[mb_strtolower($c, 'UTF-8')] = $c;
        return;
    }
    if (str_contains($raw, '/')) {
        foreach (explode('/', $raw) as $part) _osz_split_and_collect($part, $codes);
        return;
    }
    $c = _osz_normalize($raw);
    if ($c !== '' && !_osz_is_room($c)) {
        $codes[mb_strtolower($c, 'UTF-8')] = $c;
    }
}

/**
 * Az aktĆ­v Ć³rarendben Ć©s a tanĆ�rok.js-ben szereplÅ‘ osztĆ�lykĆ³dok listĆ�ja,
 * Ć©vfolyam szerint rendezve.
 *
 * Minden aktĆ­v sort vĆ©gig kell olvasni (2273 sorbĆ³l lesz ~35 kĆ³d), ezĆ©rt a
 * hĆ­vĆ³ cache-eli. IdÅ‘fĆ¼ggÅ‘ adat nincs benne.
 */
function _osz_osztaly_lista(): array {
    $codes = [];

    // Csak az aktĆ­v verziĆ³ sorai: a draft Ć³rarend osztĆ�lyai nem szivĆ�roghatnak ki
    // a publikus API-n keresztĆ¼l.
    $db_classes = sb_get_all('orarendek', ['select' => 'osztaly', 'aktiv' => 'eq.true']);
    if ($db_classes) {
        foreach ($db_classes as $row) {
            if (!empty($row['osztaly'])) _osz_split_and_collect($row['osztaly'], $codes);
        }
    }

    $js_path = ticky_source_path();
    if (is_file($js_path)) {
        $contents = file_get_contents($js_path);
        preg_match_all("/\bclass\s*:\s*['\"]([^'\"]+)['\"]/u", $contents, $matches);
        foreach ($matches[1] as $raw) _osz_split_and_collect($raw, $codes);
    }

    $result = array_values($codes);

    usort($result, function($a, $b) {
        $get_grade = function($name) {
            $upper = strtoupper($name);
            if (str_contains($upper, 'HT') || str_contains($name, '_')) return 999;
            if (preg_match('/^(\d+)\./', $name, $m)) return (int)$m[1];
            if (preg_match('/\/(\d+)/', $name, $m))  return (int)$m[1];
            if (preg_match('/^(\d+)/', $name, $m))   return (int)$m[1];
            return 999;
        };
        $ga = $get_grade($a);
        $gb = $get_grade($b);
        if ($ga !== $gb) return $ga <=> $gb;
        return strnatcasecmp($a, $b);
    });

    return $result;
}

$result = ticky_cache_lekerdez('osztalyok:lista', TICKY_CACHE_LISTA_MP, '_osz_osztaly_lista');

json_response(['osztalyok' => $result, 'count' => count($result)]);
