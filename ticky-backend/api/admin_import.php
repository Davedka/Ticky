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
        $slot = ticky_timetable_match_slot($start);

        if ($slot === null) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ISMERETLEN_ORASAV',
                $where,
                'A(z) "' . $start . '" kezdés nem szerepel a csengetési rendben.'
            );
            continue;
        }

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
    $stored_teachers = sb_get('tanarok', ['select' => 'id,rovid_nev,nev', 'limit' => TICKY_REPO_FETCH_LIMIT], 'service');
    foreach (is_array($stored_teachers) ? $stored_teachers : [] as $stored) {
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
```

## `ticky-backend/api/osztalyok.php`

MÓDOSÍTOTT — teljes tartalom (1 sor változott).  
Sorok: 78

```php
<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';


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

$codes = [];

// Csak az aktív verzió sorai: a draft órarend osztályai nem szivároghatnak ki
// a publikus API-n keresztül.
$db_classes = sb_get('orarendek', ['select' => 'osztaly', 'aktiv' => 'eq.true']);
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

json_response(['osztalyok' => $result, 'count' => count($result)]);

