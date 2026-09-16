<?php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';
require_once __DIR__ . '/../utils/tanarok_source.php';
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

/**
 * A tanárok.js sorait a közös import formátumra hozza, hogy ugyanaz a
 * validátor és ugyanaz a draft-írás fusson rá, mint az Excel importnál.
 *
 * @return array{formatum:string,orak:array,problemak:array}
 */
function ticky_import_rows_from_source(): array
{
    $lessons = [];
    $issues = [];

    foreach (ticky_source_expected_lessons() as $index => $lesson) {
        $where = 'tanárok.js#' . ($index + 1);

        $start = substr((string) ($lesson['kezdes'] ?? ''), 0, 5);
        $end   = substr((string) ($lesson['vegzes'] ?? ''), 0, 5);

        // Egy bejegyzés átfoghat több tanórát (pl. 12:05–13:35 = 6. és 7. óra).
        // Óránként külön sort készítünk, különben a köztes órák elvesznének.
        $slots = ticky_timetable_expand_period_range($start, $end);

        if ($slots === []) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ISMERETLEN_ORASAV',
                $where,
                'A(z) "' . $start . '" kezdés nem szerepel a csengetési rendben.'
            );
            continue;
        }

        foreach ($slots as $slot) {
            $lessons[] = [
                'tanar'       => ticky_timetable_single_line((string) ($lesson['tanar'] ?? '')),
                'terem'       => ticky_timetable_single_line((string) ($lesson['terem'] ?? '')),
                'osztaly'     => ticky_timetable_single_line((string) ($lesson['osztaly'] ?? '')),
                'tantargy'    => ticky_timetable_single_line((string) ($lesson['tantargy'] ?? '')),
                'csoport'     => null, // a tanárok.js nem tartalmaz csoportszámot
                'het_napja'   => (int) ($lesson['het_napja'] ?? 0),
                'ora_sorszam' => $slot['ora_sorszam'],
                'kezdes'      => $slot['kezdes'],
                'vegzes'      => $slot['vegzes'],
                'forras'      => $where,
            ];
        }
    }

    return ['formatum' => 'tanarok.js', 'orak' => $lessons, 'problemak' => $issues];
}

// ── A) Forrás ellenőrzése ────────────────────────────────────────
$source_path = ticky_source_path();
if (!is_file($source_path) || !is_readable($source_path)) {
    json_response([
        'ok'     => false,
        'kod'    => 'HIANYZO_FORRAS',
        'uzenet' => 'tanárok.js nem található vagy nem olvasható: ' . $source_path,
    ], 400);
}

if (ticky_source_load_schedule_entries() === []) {
    json_response([
        'ok'     => false,
        'kod'    => 'URES_FORRAS',
        'uzenet' => 'Nem sikerült bejegyzéseket olvasni a tanárok.js fájlból.',
    ], 400);
}

$schema = ticky_repo_schema_status();
if (!$schema['ok']) {
    json_response([
        'ok'     => false,
        'kod'    => 'HIANYZO_SEMA',
        'uzenet' => 'Hiányzó adatbázis szerkezet: ' . implode(', ', $schema['hiany'])
            . '. Futtasd le a ticky-backend/sql/001_orarend_verziok.sql szkriptet.',
    ], 503);
}

// ── B) + C) Validáció ────────────────────────────────────────────
$parsed = ticky_import_rows_from_source();
$report = ticky_validator_run(
    $parsed,
    array_keys(ticky_repo_teacher_map()),
    array_keys(ticky_repo_room_map()),
    ticky_repo_active_class_codes()
);

$lessons = $parsed['orak'];
$current_user = ticky_current_user();
$created_by = is_array($current_user) ? ($current_user['id'] ?? null) : null;

$import_log = [
    'verzio_id'              => null,
    'fajlnev'                => basename($source_path),
    'fajl_meret'             => (int) (filesize($source_path) ?: 0),
    'fajl_sha256'            => (string) hash_file('sha256', $source_path),
    'formatum'               => 'tanarok.js',
    'statusz'                => 'ervenytelen',
    'sorok_szama'            => count($lessons),
    'hibak_szama'            => $report['hibak_szama'],
    'figyelmeztetesek_szama' => $report['figyelmeztetesek_szama'],
    'hibak'                  => $report['hibak'],
    'figyelmeztetesek'       => $report['figyelmeztetesek'],
    'statisztika'            => $report['statisztika'],
    'letrehozta'             => $created_by,
];

if (!$report['ervenyes'] || $lessons === []) {
    $import_id = ticky_repo_log_import($import_log);

    json_response([
        'ok'                     => false,
        'kod'                    => 'VALIDACIOS_HIBA',
        'uzenet'                 => $report['hibak_szama'] . ' hiba miatt nem jött létre draft verzió.',
        'import_id'              => $import_id,
        'verzio_id'              => null,
        'formatum'               => 'tanarok.js',
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
$version_id = null;
try {
    $teacher_codes = [];
    $room_codes = [];
    foreach ($lessons as $lesson) {
        $teacher_codes[osztaly_lower((string) $lesson['tanar'])] = (string) $lesson['tanar'];
        $room_codes[osztaly_lower((string) $lesson['terem'])] = (string) $lesson['terem'];
    }

    $created_entities = ticky_repo_ensure_entities(array_values($teacher_codes), array_values($room_codes));

    // A tanárok.js TEACHER_NAMES blokkjából pótoljuk a hiányzó teljes neveket.
    // Csak azokat írjuk, ahol tényleg nincs név – így nem küldünk feleslegesen
    // tucatnyi PATCH kérést minden importnál.
    $teacher_names = [];
    foreach (ticky_source_teacher_names() as $code => $name) {
        $teacher_names[osztaly_lower($code)] = $name;
    }

    $named = 0;
    $stored_teachers = sb_get_all('tanarok', ['select' => 'id,rovid_nev,nev'], 'service');
    foreach ($stored_teachers as $stored) {
        if (trim((string) ($stored['nev'] ?? '')) !== '') {
            continue;
        }

        $name = $teacher_names[osztaly_lower((string) ($stored['rovid_nev'] ?? ''))] ?? null;
        if ($name === null) {
            continue;
        }

        if (sb_update('tanarok', ['nev' => $name], ['id' => 'eq.' . $stored['id']], 'service')['success']) {
            $named++;
        }
    }

    $version_id = ticky_repo_create_draft(
        'tanárok.js – ' . date('Y-m-d H:i'),
        ((int) date('n') >= 8) ? (date('Y') . '/' . ((int) date('Y') + 1)) : (((int) date('Y') - 1) . '/' . date('Y')),
        $created_by,
        'Importálva a tanárok.js forrásból.'
    );

    $inserted = ticky_repo_insert_lessons($version_id, $lessons);
} catch (TickyRepoException $error) {
    if ($version_id !== null) {
        try {
            ticky_repo_delete_draft($version_id);
        } catch (TickyRepoException) {
            // Az eredeti hiba a fontosabb.
        }
    }

    ticky_repo_log_import($import_log);
    json_response(['ok' => false, 'kod' => 'ADATBAZIS_HIBA', 'uzenet' => $error->getMessage()], 502);
}

$active_version = ticky_repo_active_version();

$import_log['verzio_id'] = $version_id;
$import_log['statusz'] = 'feldolgozva';
$import_id = ticky_repo_log_import($import_log);

json_response([
    'ok'                     => true,
    'import_id'              => $import_id,
    'verzio_id'              => $version_id,
    'formatum'               => 'tanarok.js',
    'ervenyes'               => true,
    'beszurt_sorok'          => $inserted,
    'uj_entitasok'           => $created_entities,
    'tanar_nevek_frissitve'  => $named,
    'aktiv_verzio'           => $active_version,
    'elteresek'              => ticky_repo_diff($version_id, $active_version === null ? null : (int) $active_version['id']),
    'statisztika'            => $report['statisztika'],
    'hibak'                  => [],
    'figyelmeztetesek'       => $report['figyelmeztetesek'],
    'hibak_szama'            => 0,
    'figyelmeztetesek_szama' => $report['figyelmeztetesek_szama'],
    'uzenet'                 => 'Draft verzió létrejött. Az élesítéshez publikáld az Órarend szekcióban.',
    'idotartam_ms'           => (int) round((microtime(true) - $started_at) * 1000),
]);
