<?php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/osztaly.php';

const TICKY_REPO_LESSON_BATCH = 200;
const TICKY_REPO_ENTITY_BATCH = 100;
const TICKY_REPO_FETCH_LIMIT  = '20000';

class TickyRepoException extends RuntimeException
{
}

// ─────────────────────────────────────────────────────────────────
// Séma ellenőrzés
// ─────────────────────────────────────────────────────────────────

/**
 * Megnézi, hogy a migráció (sql/001_orarend_verziok.sql) le van-e futtatva.
 * Enélkül minden insert érthetetlen PostgREST hibával bukna el.
 *
 * @return array{ok:bool,hiany:array<int,string>}
 */
function ticky_repo_schema_status(): array
{
    $missing = [];

    $versions = sb_request('GET', 'orarend_verziok', null, ['select' => 'id', 'limit' => '1'], 'service');
    if (!$versions['success']) {
        $missing[] = 'orarend_verziok tábla';
    }

    $imports = sb_request('GET', 'importok', null, ['select' => 'id', 'limit' => '1'], 'service');
    if (!$imports['success']) {
        $missing[] = 'importok tábla';
    }

    $column = sb_request('GET', 'orarendek', null, ['select' => 'id,verzio_id,csoport', 'limit' => '1'], 'service');
    if (!$column['success']) {
        $missing[] = 'orarendek.verzio_id / orarendek.csoport oszlop';
    }

    return ['ok' => $missing === [], 'hiany' => $missing];
}

// ─────────────────────────────────────────────────────────────────
// Ismert entitások
// ─────────────────────────────────────────────────────────────────

/** @return array<string,mixed> normalizált kód => id */
function ticky_repo_teacher_map(): array
{
    $rows = sb_get('tanarok', ['select' => 'id,rovid_nev', 'limit' => TICKY_REPO_FETCH_LIMIT], 'service');

    $map = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $code = trim((string) ($row['rovid_nev'] ?? ''));
        if ($code !== '') {
            $map[osztaly_lower($code)] = $row['id'];
        }
    }

    return $map;
}

/** @return array<string,mixed> normalizált kód => id */
function ticky_repo_room_map(): array
{
    $rows = sb_get('termek', ['select' => 'id,terem_szam', 'limit' => TICKY_REPO_FETCH_LIMIT], 'service');

    $map = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $code = trim((string) ($row['terem_szam'] ?? ''));
        if ($code !== '') {
            $map[osztaly_lower($code)] = $row['id'];
        }
    }

    return $map;
}

/** Az aktív órarendben szereplő osztálykódok. */
function ticky_repo_active_class_codes(): array
{
    $rows = sb_get('orarendek', [
        'select' => 'osztaly',
        'aktiv'  => 'eq.true',
        'limit'  => TICKY_REPO_FETCH_LIMIT,
    ], 'service');

    $codes = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $code = trim((string) ($row['osztaly'] ?? ''));
        if ($code !== '') {
            $codes[osztaly_lower($code)] = $code;
        }
    }

    return array_values($codes);
}

/**
 * Felveszi a még nem létező tanárokat/termeket.
 *
 * Szándékosan csak beszúr, sosem töröl: a régi verziók sorai hivatkoznak
 * rájuk (ON DELETE CASCADE), így egy takarítás visszamenőleg tenné tönkre
 * az archív órarendeket.
 *
 * @return array{tanarok:int,termek:int}
 */
function ticky_repo_ensure_entities(array $teacher_codes, array $room_codes): array
{
    $created = ['tanarok' => 0, 'termek' => 0];

    $existing_teachers = ticky_repo_teacher_map();
    $new_teachers = [];
    foreach ($teacher_codes as $code) {
        if (!isset($existing_teachers[osztaly_lower($code)])) {
            $new_teachers[osztaly_lower($code)] = ['rovid_nev' => $code];
        }
    }

    foreach (array_chunk(array_values($new_teachers), TICKY_REPO_ENTITY_BATCH) as $batch) {
        $result = sb_request('POST', 'tanarok', $batch, [], 'service');
        if (!$result['success']) {
            throw new TickyRepoException('Tanár felvétele sikertelen: ' . ticky_repo_error_text($result));
        }
        $created['tanarok'] += count($batch);
    }

    $existing_rooms = ticky_repo_room_map();
    $new_rooms = [];
    foreach ($room_codes as $code) {
        if (!isset($existing_rooms[osztaly_lower($code)])) {
            $new_rooms[osztaly_lower($code)] = ['terem_szam' => $code];
        }
    }

    foreach (array_chunk(array_values($new_rooms), TICKY_REPO_ENTITY_BATCH) as $batch) {
        $result = sb_request('POST', 'termek', $batch, [], 'service');
        if (!$result['success']) {
            throw new TickyRepoException('Terem felvétele sikertelen: ' . ticky_repo_error_text($result));
        }
        $created['termek'] += count($batch);
    }

    return $created;
}

// ─────────────────────────────────────────────────────────────────
// Verziók
// ─────────────────────────────────────────────────────────────────

function ticky_repo_create_draft(string $name, string $school_year, ?string $created_by, ?string $note = null): int
{
    $result = sb_request('POST', 'orarend_verziok', [[
        'nev'        => $name,
        'tanev'      => $school_year,
        'statusz'    => 'draft',
        'letrehozta' => $created_by,
        'megjegyzes' => $note,
    ]], [], 'service');

    if (!$result['success']) {
        throw new TickyRepoException('Draft verzió létrehozása sikertelen: ' . ticky_repo_error_text($result));
    }

    $id = $result['data'][0]['id'] ?? null;
    if ($id === null) {
        throw new TickyRepoException('A draft verzió azonosítója nem érkezett vissza.');
    }

    return (int) $id;
}

/**
 * Beszúrja a draft órarend sorait. Hiba esetén eltakarítja a félkész
 * verziót, hogy ne maradjon csonka draft az adatbázisban.
 */
function ticky_repo_insert_lessons(int $version_id, array $lessons): int
{
    $teacher_map = ticky_repo_teacher_map();
    $room_map = ticky_repo_room_map();

    $rows = [];
    foreach ($lessons as $lesson) {
        $teacher_id = $teacher_map[osztaly_lower((string) $lesson['tanar'])] ?? null;
        $room_id    = $room_map[osztaly_lower((string) $lesson['terem'])] ?? null;

        if ($teacher_id === null || $room_id === null) {
            throw new TickyRepoException(
                'Hiányzó tanár- vagy terem-azonosító: ' . (string) $lesson['tanar'] . ' / ' . (string) $lesson['terem']
            );
        }

        $rows[] = [
            'terem_id'    => $room_id,
            'tanar_id'    => $teacher_id,
            'osztaly'     => (string) $lesson['osztaly'],
            'tantargy'    => (string) ($lesson['tantargy'] ?? ''),
            'het_napja'   => (int) $lesson['het_napja'],
            'ora_sorszam' => (int) $lesson['ora_sorszam'],
            'kezdes'      => (string) $lesson['kezdes'],
            'vegzes'      => (string) $lesson['vegzes'],
            'csoport'     => $lesson['csoport'] === null ? null : (int) $lesson['csoport'],
            'verzio_id'   => $version_id,
            'aktiv'       => false, // draft: a publikus API sosem látja
        ];
    }

    $inserted = 0;
    foreach (array_chunk($rows, TICKY_REPO_LESSON_BATCH) as $batch) {
        $result = sb_request('POST', 'orarendek', $batch, ['select' => 'id'], 'service');
        if (!$result['success']) {
            throw new TickyRepoException('Órarend sor beszúrása sikertelen: ' . ticky_repo_error_text($result));
        }
        $inserted += count($batch);
    }

    return $inserted;
}

/** @return array<int,array> verziók, legújabb elöl */
function ticky_repo_versions(int $limit = 50): array
{
    $rows = sb_get('orarend_verziok', [
        'select' => 'id,nev,tanev,statusz,megjegyzes,created_at,published_at',
        'order'  => 'created_at.desc',
        'limit'  => (string) $limit,
    ], 'service');

    return is_array($rows) ? $rows : [];
}

function ticky_repo_version(int $version_id): ?array
{
    $rows = sb_get('orarend_verziok', [
        'id'     => 'eq.' . $version_id,
        'select' => 'id,nev,tanev,statusz,megjegyzes,created_at,published_at',
        'limit'  => '1',
    ], 'service');

    return (is_array($rows) && $rows !== []) ? $rows[0] : null;
}

function ticky_repo_active_version(): ?array
{
    $rows = sb_get('orarend_verziok', [
        'statusz' => 'eq.active',
        'select'  => 'id,nev,tanev,statusz,created_at,published_at',
        'limit'   => '1',
    ], 'service');

    return (is_array($rows) && $rows !== []) ? $rows[0] : null;
}

/** Egy verzióhoz tartozó sorok száma. */
function ticky_repo_version_lesson_count(int $version_id): int
{
    $rows = sb_get('orarendek', [
        'verzio_id' => 'eq.' . $version_id,
        'select'    => 'id',
        'limit'     => TICKY_REPO_FETCH_LIMIT,
    ], 'service');

    return is_array($rows) ? count($rows) : 0;
}

/** Egy verzió összegzése: tanárok, termek, osztályok, sorszám. */
function ticky_repo_version_summary(int $version_id): array
{
    $rows = sb_get('orarendek', [
        'verzio_id' => 'eq.' . $version_id,
        'select'    => 'tanar_id,terem_id,osztaly',
        'limit'     => TICKY_REPO_FETCH_LIMIT,
    ], 'service');

    $teachers = $rooms = $classes = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $teachers[(string) ($row['tanar_id'] ?? '')] = true;
        $rooms[(string) ($row['terem_id'] ?? '')] = true;
        $classes[osztaly_lower((string) ($row['osztaly'] ?? ''))] = true;
    }

    return [
        'orak'      => is_array($rows) ? count($rows) : 0,
        'tanarok'   => count($teachers),
        'termek'    => count($rooms),
        'osztalyok' => count($classes),
    ];
}

/**
 * Két verzió különbsége az admin előnézethez.
 * Ha nincs aktív verzió, mindent újnak tekintünk.
 */
function ticky_repo_diff(int $draft_id, ?int $active_id): array
{
    $draft = ticky_repo_version_summary($draft_id);

    if ($active_id === null) {
        return [
            'orak'      => ['elotte' => 0, 'utana' => $draft['orak'],      'valtozas' => $draft['orak']],
            'tanarok'   => ['elotte' => 0, 'utana' => $draft['tanarok'],   'valtozas' => $draft['tanarok']],
            'termek'    => ['elotte' => 0, 'utana' => $draft['termek'],    'valtozas' => $draft['termek']],
            'osztalyok' => ['elotte' => 0, 'utana' => $draft['osztalyok'], 'valtozas' => $draft['osztalyok']],
        ];
    }

    $active = ticky_repo_version_summary($active_id);

    $diff = [];
    foreach (['orak', 'tanarok', 'termek', 'osztalyok'] as $key) {
        $diff[$key] = [
            'elotte'   => $active[$key],
            'utana'    => $draft[$key],
            'valtozas' => $draft[$key] - $active[$key],
        ];
    }

    return $diff;
}

// ─────────────────────────────────────────────────────────────────
// Import napló
// ─────────────────────────────────────────────────────────────────

function ticky_repo_log_import(array $entry): ?int
{
    $result = sb_request('POST', 'importok', [$entry], [], 'service');

    return $result['success'] ? (int) ($result['data'][0]['id'] ?? 0) : null;
}

function ticky_repo_imports(int $limit = 25): array
{
    $rows = sb_get('importok', [
        'select' => 'id,verzio_id,fajlnev,fajl_meret,formatum,statusz,sorok_szama,hibak_szama,figyelmeztetesek_szama,created_at',
        'order'  => 'created_at.desc',
        'limit'  => (string) $limit,
    ], 'service');

    return is_array($rows) ? $rows : [];
}

function ticky_repo_import_for_version(int $version_id): ?array
{
    $rows = sb_get('importok', [
        'verzio_id' => 'eq.' . $version_id,
        'select'    => 'id,fajlnev,formatum,statusz,sorok_szama,hibak_szama,figyelmeztetesek_szama,hibak,figyelmeztetesek,statisztika,created_at',
        'order'     => 'created_at.desc',
        'limit'     => '1',
    ], 'service');

    return (is_array($rows) && $rows !== []) ? $rows[0] : null;
}

// ─────────────────────────────────────────────────────────────────
// Publikálás / visszaállítás / törlés
// ─────────────────────────────────────────────────────────────────

/**
 * Atomikus publikálás adatbázis-függvénnyel. Ugyanez az RPC szolgál a
 * rollbackre is: egy archivált verziót tesz újra aktívvá.
 */
function ticky_repo_publish(int $version_id): array
{
    $result = sb_request('POST', 'rpc/ticky_publish_orarend_verzio', ['p_verzio_id' => $version_id], [], 'service');

    if (!$result['success']) {
        throw new TickyRepoException(ticky_repo_translate_rpc_error(ticky_repo_error_text($result)));
    }

    $data = $result['data'];

    return is_array($data) ? $data : ['ok' => true, 'verzio_id' => $version_id];
}

function ticky_repo_delete_draft(int $version_id): array
{
    $result = sb_request('POST', 'rpc/ticky_delete_orarend_draft', ['p_verzio_id' => $version_id], [], 'service');

    if (!$result['success']) {
        throw new TickyRepoException(ticky_repo_translate_rpc_error(ticky_repo_error_text($result)));
    }

    $data = $result['data'];

    return is_array($data) ? $data : ['ok' => true, 'verzio_id' => $version_id];
}

// ─────────────────────────────────────────────────────────────────
// Hibakezelés
// ─────────────────────────────────────────────────────────────────

function ticky_repo_error_text(array $result): string
{
    $raw = (string) ($result['error'] ?? '');
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $parts = array_filter([
            (string) ($decoded['message'] ?? ''),
            (string) ($decoded['details'] ?? ''),
            (string) ($decoded['hint'] ?? ''),
        ]);

        if ($parts !== []) {
            return implode(' – ', $parts);
        }
    }

    return mb_substr($raw, 0, 300);
}

/** A plpgsql hibakódokat emberi mondattá fordítja. */
function ticky_repo_translate_rpc_error(string $message): string
{
    static $map = [
        'ISMERETLEN_VERZIO'    => 'Nincs ilyen órarend verzió.',
        'MAR_AKTIV'            => 'Ez a verzió már aktív.',
        'URES_VERZIO'          => 'A verzióhoz egyetlen órarend sor sem tartozik, ezért nem publikálható.',
        'CSAK_DRAFT_TOROLHETO' => 'Csak draft állapotú verzió törölhető.',
    ];

    foreach ($map as $code => $text) {
        if (str_contains($message, $code)) {
            return $text;
        }
    }

    if (str_contains($message, 'ticky_publish_orarend_verzio') || str_contains($message, 'PGRST202')) {
        return 'Az adatbázis-függvény hiányzik. Futtasd le a ticky-backend/sql/001_orarend_verziok.sql szkriptet.';
    }

    return 'Adatbázis hiba: ' . $message;
}
