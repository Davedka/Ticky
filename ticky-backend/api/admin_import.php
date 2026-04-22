<?php
// api/admin_import.php
// POST /api/admin/import
// Teljes újraimportálás a tanárok.js forrásból – PHP-ből, Node.js nélkül.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

require_admin_api_request(['POST']);
ticky_require_fresh_admin_auth();

set_time_limit(120);
ignore_user_abort(true);

$start_time = microtime(true);
$errors     = [];

// ── 1. Forrás validáció ─────────────────────────────
$source_path = ticky_source_path();
if (!is_file($source_path) || !is_readable($source_path)) {
    json_error('tanárok.js nem található vagy nem olvasható: ' . $source_path, 400);
}

$entries = ticky_source_load_schedule_entries();
if ($entries === []) {
    json_error('Nem sikerült bejegyzéseket olvasni a tanárok.js fájlból', 400);
}

$expected      = ticky_source_expected_lessons();
$teacher_codes = ticky_source_unique_teachers();
$room_codes    = ticky_source_unique_rooms();
$teacher_names = ticky_source_teacher_names();

if ($teacher_codes === [] || $room_codes === []) {
    json_error('Üres tanár- vagy teremlista a forrásban', 400);
}

// ── 2. Emelet értékek mentése ────────────────────────
$existing_rooms = sb_get('termek', ['select' => 'terem_szam,emelet'], 'service');
$emelet_map     = [];
if (is_array($existing_rooms)) {
    foreach ($existing_rooms as $r) {
        $szam   = (string) ($r['terem_szam'] ?? '');
        $emelet = $r['emelet'] ?? null;
        if ($szam !== '' && $emelet !== null) {
            $emelet_map[$szam] = $emelet;
        }
    }
}

// ── 3. Törlés ────────────────────────────────────────
$rpc_ok = false;
$rpc    = sb_request('POST', 'rpc/truncate_all_data', (object) [], [], 'service');
if ($rpc['success']) {
    usleep(600_000);
    $rpc_ok = true;
}

if (!$rpc_ok) {
    $delete_tables = ['aktualis_orak', 'napi_orarend', 'orak_rendje', 'orarendek', 'termek', 'tanarok'];
    foreach ($delete_tables as $table) {
        $del = sb_request('DELETE', $table, null, ['id' => 'not.is.null'], 'service');
        if (!$del['success']) {
            $errors[] = 'Törlés sikertelen: ' . $table;
        }
        usleep(200_000);
    }
}

// ── 4. Tanárok insert ────────────────────────────────
$teacher_rows = [];
foreach ($teacher_codes as $code) {
    $teacher_rows[] = [
        'rovid_nev' => $code,
        'nev'       => $teacher_names[$code] ?? null,
    ];
}

$tanarok_inserted = 0;
foreach (array_chunk($teacher_rows, 100) as $batch) {
    $res = sb_request('POST', 'tanarok', $batch, [], 'service');
    if ($res['success']) {
        $tanarok_inserted += count($batch);
    } else {
        $errors[] = 'Tanár insert hiba: ' . substr((string) ($res['error'] ?? ''), 0, 200);
    }
}

// ── 5. Termek insert ─────────────────────────────────
$room_rows = [];
foreach ($room_codes as $room) {
    $room_rows[] = ['terem_szam' => $room];
}

$termek_inserted = 0;
foreach (array_chunk($room_rows, 100) as $batch) {
    $res = sb_request('POST', 'termek', $batch, [], 'service');
    if ($res['success']) {
        $termek_inserted += count($batch);
    } else {
        $errors[] = 'Terem insert hiba: ' . substr((string) ($res['error'] ?? ''), 0, 200);
    }
}

// ── 6. ID-k lekérése ─────────────────────────────────
$db_teachers = sb_get('tanarok', ['select' => 'id,rovid_nev', 'limit' => '10000'], 'service');
$db_rooms    = sb_get('termek',  ['select' => 'id,terem_szam', 'limit' => '10000'], 'service');

$teacher_id_map = [];
if (is_array($db_teachers)) {
    foreach ($db_teachers as $t) {
        $teacher_id_map[ticky_source_normalize_token((string) ($t['rovid_nev'] ?? ''))] = $t['id'];
    }
}

$room_id_map = [];
if (is_array($db_rooms)) {
    foreach ($db_rooms as $r) {
        $room_id_map[ticky_source_normalize_token((string) ($r['terem_szam'] ?? ''))] = $r['id'];
    }
}

// ── 7. Órarendek összeállítás + insert ───────────────
$lesson_rows = [];
foreach ($expected as $lesson) {
    $teacher_key = ticky_source_normalize_token((string) ($lesson['tanar'] ?? ''));
    $room_key    = ticky_source_normalize_token((string) ($lesson['terem'] ?? ''));

    $teacher_id = $teacher_id_map[$teacher_key] ?? null;
    $room_id    = $room_id_map[$room_key] ?? null;

    if ($teacher_id === null) {
        $errors[] = 'Nem találom tanár ID-t: ' . $teacher_key;
        continue;
    }
    if ($room_id === null) {
        $errors[] = 'Nem találom terem ID-t: ' . $room_key;
        continue;
    }

    $lesson_rows[] = [
        'terem_id'    => $room_id,
        'tanar_id'    => $teacher_id,
        'osztaly'     => (string) ($lesson['osztaly'] ?? ''),
        'tantargy'    => (string) ($lesson['tantargy'] ?? ''),
        'het_napja'   => (int) ($lesson['het_napja'] ?? 0),
        'ora_sorszam' => $lesson['ora_sorszam'],
        'kezdes'      => (string) ($lesson['kezdes'] ?? ''),
        'vegzes'      => (string) ($lesson['vegzes'] ?? ''),
        'aktiv'       => true,
    ];
}

$orarendek_inserted = 0;
foreach (array_chunk($lesson_rows, 200) as $batch) {
    $res = sb_request('POST', 'orarendek', $batch, [], 'service');
    if ($res['success']) {
        $orarendek_inserted += count($batch);
    } else {
        $errors[] = 'Órarend insert hiba: ' . substr((string) ($res['error'] ?? ''), 0, 200);
    }
}

// ── 8. Emelet helyreállítás ──────────────────────────
$emelet_restored = 0;
foreach ($emelet_map as $szam => $emelet) {
    $res = sb_update('termek', ['emelet' => $emelet], ['terem_szam' => 'eq.' . $szam], 'service');
    if ($res['success']) {
        $emelet_restored++;
    }
}

// ── Válasz ───────────────────────────────────────────
$duration_ms = (int) round((microtime(true) - $start_time) * 1000);

$unique_errors = array_values(array_unique($errors));

json_response([
    'ok'                  => count($unique_errors) === 0 || $orarendek_inserted > 0,
    'tanarok_inserted'    => $tanarok_inserted,
    'termek_inserted'     => $termek_inserted,
    'orarendek_inserted'  => $orarendek_inserted,
    'emelet_restored'     => $emelet_restored,
    'errors'              => array_slice($unique_errors, 0, 30),
    'duration_ms'         => $duration_ms,
]);
