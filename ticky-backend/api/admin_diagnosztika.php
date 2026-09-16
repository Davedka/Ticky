<?php
// api/admin_diagnosztika.php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';
require_once __DIR__ . '/../utils/valasz_cache.php';


if (!admin_can_see_ui()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['hiba' => 'Bejelentkezés szükséges']);
    exit;
}

require_admin_api_request(['GET']);

// ── Source fájl elemzés ───────────────────────────────
$source_path   = ticky_source_path();
$file_exists   = is_file($source_path) && is_readable($source_path);

$entries       = $file_exists ? ticky_source_load_schedule_entries() : [];
$src_teachers  = $file_exists ? ticky_source_unique_teachers()       : [];
$src_rooms     = $file_exists ? ticky_source_unique_rooms()          : [];
$src_classes   = $file_exists ? ticky_source_class_codes()           : [];
$teacher_names = $file_exists ? ticky_source_teacher_names()         : [];

// ── Database lekérések ────────────────────────────────
$db_teachers   = sb_get('tanarok',   ['select' => 'id,rovid_nev,nev'],   'service');
$db_rooms      = sb_get('termek',    ['select' => 'id,terem_szam'],       'service');
$db_orarendek  = sb_get_all('orarendek', ['select' => 'id,terem_id,tanar_id,aktiv'], 'service');

if (!is_array($db_teachers))  $db_teachers  = [];
if (!is_array($db_rooms))     $db_rooms     = [];
if (!is_array($db_orarendek)) $db_orarendek = [];

$db_teacher_codes   = array_map(static fn($t) => (string) ($t['rovid_nev'] ?? ''), $db_teachers);
$db_room_codes      = array_map(static fn($r) => (string) ($r['terem_szam'] ?? ''), $db_rooms);
$db_teacher_ids     = array_map(static fn($t) => (string) ($t['id'] ?? ''), $db_teachers);
$db_room_ids        = array_map(static fn($r) => (string) ($r['id'] ?? ''), $db_rooms);

$orarendek_aktiv    = 0;
$orphan_teacher_ids = [];
$orphan_terem_ids   = [];
$teacher_id_set     = array_flip($db_teacher_ids);
$room_id_set        = array_flip($db_room_ids);

foreach ($db_orarendek as $o) {
    if (!empty($o['aktiv'])) $orarendek_aktiv++;

    $tid = (string) ($o['tanar_id'] ?? '');
    $rid = (string) ($o['terem_id'] ?? '');
    if ($tid !== '' && !isset($teacher_id_set[$tid])) $orphan_teacher_ids[$tid] = true;
    if ($rid !== '' && !isset($room_id_set[$rid]))    $orphan_terem_ids[$rid] = true;
}

// ── Összehasonlítás ───────────────────────────────────
$missing_teachers   = array_values(array_diff($src_teachers, $db_teacher_codes));
$extra_db_teachers  = array_values(array_diff($db_teacher_codes, $src_teachers));
$missing_rooms      = array_values(array_diff($src_rooms, $db_room_codes));
$extra_db_rooms     = array_values(array_diff($db_room_codes, $src_rooms));

$teachers_without_names = [];
foreach ($db_teachers as $t) {
    $nev = trim((string) ($t['nev'] ?? ''));
    if ($nev === '') {
        $teachers_without_names[] = (string) ($t['rovid_nev'] ?? '');
    }
}
sort($teachers_without_names);

sort($missing_teachers);
sort($extra_db_teachers);
sort($missing_rooms);
sort($extra_db_rooms);

// ── Health status ─────────────────────────────────────
$issues = [];

if (!$file_exists) {
    $issues[] = ['level' => 'error', 'message' => 'tanárok.js nem található vagy nem olvasható'];
}
if (count($missing_teachers) > 0) {
    $issues[] = ['level' => 'error', 'message' => count($missing_teachers) . ' tanár hiányzik az adatbázisból: ' . implode(', ', array_slice($missing_teachers, 0, 10))];
}
if (count($missing_rooms) > 0) {
    $issues[] = ['level' => 'error', 'message' => count($missing_rooms) . ' terem hiányzik az adatbázisból: ' . implode(', ', array_slice($missing_rooms, 0, 10))];
}
if (count($extra_db_teachers) > 0) {
    $issues[] = ['level' => 'warning', 'message' => count($extra_db_teachers) . ' tanár az adatbázisban de nincs a forrásban: ' . implode(', ', array_slice($extra_db_teachers, 0, 10))];
}
if (count($extra_db_rooms) > 0) {
    $issues[] = ['level' => 'warning', 'message' => count($extra_db_rooms) . ' terem az adatbázisban de nincs a forrásban: ' . implode(', ', array_slice($extra_db_rooms, 0, 10))];
}
if (count($teachers_without_names) > 0) {
    $issues[] = ['level' => 'warning', 'message' => count($teachers_without_names) . ' tanárnak nincs megadva neve'];
}
if (count($orphan_teacher_ids) > 0) {
    $issues[] = ['level' => 'warning', 'message' => count($orphan_teacher_ids) . ' órarend sor hivatkozik nem létező tanárra'];
}
if (count($orphan_terem_ids) > 0) {
    $issues[] = ['level' => 'warning', 'message' => count($orphan_terem_ids) . ' órarend sor hivatkozik nem létező teremre'];
}
if (count($db_orarendek) === 0 && $file_exists) {
    $issues[] = ['level' => 'error', 'message' => 'Nincs egyetlen órarend sor sem az adatbázisban – futtass importot'];
}

$has_error   = false;
$has_warning = false;
foreach ($issues as $it) {
    if ($it['level'] === 'error')   $has_error   = true;
    if ($it['level'] === 'warning') $has_warning = true;
}
$status = $has_error ? 'error' : ($has_warning ? 'warning' : 'ok');

// ── Válasz ────────────────────────────────────────────
json_response([
    'ok'        => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'source'    => [
        'file_exists'         => $file_exists,
        'entries_count'       => count($entries),
        'unique_teachers'     => count($src_teachers),
        'unique_rooms'        => count($src_rooms),
        'unique_classes'      => count($src_classes),
        'teacher_names_count' => count($teacher_names),
    ],
    'database'   => [
        'tanarok_count'        => count($db_teachers),
        'termek_count'         => count($db_rooms),
        'orarendek_count'      => count($db_orarendek),
        'orarendek_aktiv_count'=> $orarendek_aktiv,
    ],
    'comparison' => [
        'missing_teachers'              => $missing_teachers,
        'extra_db_teachers'             => $extra_db_teachers,
        'missing_rooms'                 => $missing_rooms,
        'extra_db_rooms'                => $extra_db_rooms,
        'teachers_without_names'        => $teachers_without_names,
        'orphan_orarendek_teacher_count'=> count($orphan_teacher_ids),
        'orphan_orarendek_terem_count'  => count($orphan_terem_ids),
    ],
    'health'     => [
        'status' => $status,
        'issues' => $issues,
    ],
    // ── Teljesítmény ──────────────────────────────────────────────
    // Ez a két blokk dönti el, hogy a szerver mennyi kérést bír el.
    // Az opcache nélkül minden kérés újraolvassa a ~190 KB PHP forrást,
    // ami mérve tízszeres CPU-igényt jelent kérésenként.
    'teljesitmeny' => ticky_diag_teljesitmeny(),
]);

/**
 * Az opcache és a válasz-cache állapota.
 *
 * Az opcache a php -S alatt a CLI SAPI-ban fut, ahol alapértelmezésben KI van
 * kapcsolva (opcache.enable_cli=0). A hivatalos php:8.2-cli image-ben a
 * kiterjesztés be van fordítva, de nincs engedélyezve.
 */
function ticky_diag_teljesitmeny(): array
{
    $statusz = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
    $bekapcsolva = is_array($statusz) ? (bool) ($statusz['opcache_enabled'] ?? false) : false;

    $javaslat = null;
    if (!function_exists('opcache_get_status')) {
        $javaslat = 'Az opcache kiterjesztés nincs betöltve. Dockerfile: docker-php-ext-enable opcache';
    } elseif (!$bekapcsolva) {
        $javaslat = 'Az opcache be van töltve, de ki van kapcsolva. Indítás: php -d opcache.enable_cli=1 ...';
    }

    return [
        'opcache' => [
            'kiterjesztes_betoltve' => function_exists('opcache_get_status'),
            'bekapcsolva'           => $bekapcsolva,
            'enable_cli_ini'        => ini_get('opcache.enable_cli'),
            'gyorsitotarazott_szkriptek' => is_array($statusz)
                ? ($statusz['opcache_statistics']['num_cached_scripts'] ?? null)
                : null,
            'javaslat'              => $javaslat,
        ],
        'valasz_cache' => ticky_cache_statisztika() + [
            'konyvtar'      => ticky_cache_konyvtar(),
            'irhato'        => is_writable(ticky_cache_konyvtar()),
            'orarend_ttl_mp'=> TICKY_CACHE_ORAREND_MP,
            'lista_ttl_mp'  => TICKY_CACHE_LISTA_MP,
        ],
        'szerver' => [
            'sapi'           => PHP_SAPI,
            'php_verzio'     => PHP_VERSION,
            'worker_szam'    => getenv('PHP_CLI_SERVER_WORKERS') ?: '1 (nincs beállítva)',
        ],
    ];
}
