<?php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/timetable_validator.php';
require_once __DIR__ . '/../utils/timetable_repo.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['POST']);
ticky_require_fresh_admin_auth();

set_time_limit(180);
ignore_user_abort(true);

$started_at = microtime(true);

// ── A) Strukturális validáció ────────────────────────────────────
$upload = ticky_validator_check_upload($_FILES['fajl'] ?? null);
if (!$upload['ok']) {
    json_response([
        'ok'     => false,
        'kod'    => $upload['kod'],
        'uzenet' => $upload['uzenet'],
    ], 400);
}

// A migráció megléte nélkül minden további lépés érthetetlen hibával bukna.
$schema = ticky_repo_schema_status();
if (!$schema['ok']) {
    json_response([
        'ok'     => false,
        'kod'    => 'HIANYZO_SEMA',
        'uzenet' => 'Hiányzó adatbázis szerkezet: ' . implode(', ', $schema['hiany'])
            . '. Futtasd le a ticky-backend/sql/001_orarend_verziok.sql szkriptet a Supabase SQL Editorban.',
    ], 503);
}

// ── Parse ────────────────────────────────────────────────────────
try {
    $parsed = ticky_timetable_parse_file((string) $_FILES['fajl']['tmp_name']);
} catch (TickyXlsxException $error) {
    json_response([
        'ok'     => false,
        'kod'    => 'OLVASASI_HIBA',
        'uzenet' => $error->getMessage(),
    ], 400);
}

// ── B) + C) Tartalmi és üzleti validáció ─────────────────────────
$known_teachers = array_keys(ticky_repo_teacher_map());
$known_rooms    = array_keys(ticky_repo_room_map());
$known_classes  = ticky_repo_active_class_codes();

$report = ticky_validator_run($parsed, $known_teachers, $known_rooms, $known_classes);
$lessons = $parsed['orak'];

$current_user = ticky_current_user();
$created_by = is_array($current_user) ? ($current_user['id'] ?? null) : null;

$import_log = [
    'verzio_id'              => null,
    'fajlnev'                => $upload['fajlnev'],
    'fajl_meret'             => $upload['meret'],
    'fajl_sha256'            => $upload['sha256'],
    'formatum'               => (string) $parsed['formatum'],
    'statusz'                => 'ervenytelen',
    'sorok_szama'            => count($lessons),
    'hibak_szama'            => $report['hibak_szama'],
    'figyelmeztetesek_szama' => $report['figyelmeztetesek_szama'],
    'hibak'                  => $report['hibak'],
    'figyelmeztetesek'       => $report['figyelmeztetesek'],
    'statisztika'            => $report['statisztika'],
    'letrehozta'             => $created_by,
];

// ── Hiba esetén: nincs draft, csak riport ────────────────────────
if (!$report['ervenyes'] || $lessons === []) {
    if ($lessons === []) {
        $report['hibak'][] = ticky_timetable_issue(
            'error',
            'URES_IMPORT',
            $upload['fajlnev'],
            'A fájlból egyetlen órarend sort sem sikerült kiolvasni.'
        );
        $report['hibak_szama']++;
        $import_log['hibak'] = $report['hibak'];
        $import_log['hibak_szama'] = $report['hibak_szama'];
    }

    $import_id = ticky_repo_log_import($import_log);

    json_response([
        'ok'                     => false,
        'kod'                    => 'VALIDACIOS_HIBA',
        'uzenet'                 => $report['hibak_szama'] . ' hiba miatt nem jött létre draft verzió.',
        'import_id'              => $import_id,
        'verzio_id'              => null,
        'formatum'               => $parsed['formatum'],
        'ervenyes'               => false,
        'statisztika'            => $report['statisztika'],
        'hibak'                  => $report['hibak'],
        'figyelmeztetesek'       => $report['figyelmeztetesek'],
        'hibak_szama'            => $report['hibak_szama'],
        'figyelmeztetesek_szama' => $report['figyelmeztetesek_szama'],
        'idotartam_ms'           => (int) round((microtime(true) - $started_at) * 1000),
    ]);
}

// ── Draft létrehozása ────────────────────────────────────────────
$version_name = ticky_timetable_single_line((string) ($_POST['nev'] ?? ''));
$school_year  = ticky_timetable_single_line((string) ($_POST['tanev'] ?? ''));

if ($version_name === '') {
    $version_name = $upload['fajlnev'] . ' – ' . date('Y-m-d H:i');
}
if ($school_year === '') {
    // Szeptembertől már a következő tanév megy.
    $year = (int) date('Y');
    $school_year = ((int) date('n') >= 8) ? ($year . '/' . ($year + 1)) : (($year - 1) . '/' . $year);
}

$version_name = mb_substr($version_name, 0, 120, 'UTF-8');
$school_year  = mb_substr($school_year, 0, 32, 'UTF-8');

$version_id = null;
try {
    $teacher_codes = [];
    $room_codes = [];
    foreach ($lessons as $lesson) {
        $teacher_codes[osztaly_lower((string) $lesson['tanar'])] = (string) $lesson['tanar'];
        $room_codes[osztaly_lower((string) $lesson['terem'])] = (string) $lesson['terem'];
    }

    $created_entities = ticky_repo_ensure_entities(array_values($teacher_codes), array_values($room_codes));

    $version_id = ticky_repo_create_draft($version_name, $school_year, $created_by, 'Feltöltve: ' . $upload['fajlnev']);
    $inserted = ticky_repo_insert_lessons($version_id, $lessons);
} catch (TickyRepoException $error) {
    // Ne maradjon félkész draft: takarítunk, majd jelentjük a hibát.
    if ($version_id !== null) {
        try {
            ticky_repo_delete_draft($version_id);
        } catch (TickyRepoException) {
            // A takarítás hibáját elnyeljük, az eredeti hiba a fontosabb.
        }
    }

    $import_log['statusz'] = 'ervenytelen';
    ticky_repo_log_import($import_log);

    json_response(['ok' => false, 'kod' => 'ADATBAZIS_HIBA', 'uzenet' => $error->getMessage()], 502);
}

$active_version = ticky_repo_active_version();
$diff = ticky_repo_diff($version_id, $active_version === null ? null : (int) $active_version['id']);

$import_log['verzio_id'] = $version_id;
$import_log['statusz'] = 'feldolgozva';
$import_id = ticky_repo_log_import($import_log);

json_response([
    'ok'                     => true,
    'import_id'              => $import_id,
    'verzio_id'              => $version_id,
    'verzio_nev'             => $version_name,
    'tanev'                  => $school_year,
    'formatum'               => $parsed['formatum'],
    'ervenyes'               => true,
    'beszurt_sorok'          => $inserted,
    'uj_entitasok'           => $created_entities,
    'aktiv_verzio'           => $active_version,
    'elteresek'              => $diff,
    'statisztika'            => $report['statisztika'],
    'hibak'                  => [],
    'figyelmeztetesek'       => $report['figyelmeztetesek'],
    'hibak_szama'            => 0,
    'figyelmeztetesek_szama' => $report['figyelmeztetesek_szama'],
    'idotartam_ms'           => (int) round((microtime(true) - $started_at) * 1000),
]);
