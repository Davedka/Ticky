<?php


require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/timetable_repo.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['POST']);
ticky_require_fresh_admin_auth();

set_time_limit(120);

$version_id = (int) ($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'publish') === 'rollback' ? 'rollback' : 'publish';

if ($version_id <= 0) {
    json_error('Érvénytelen verzió azonosító', 400);
}

$version = ticky_repo_version($version_id);
if ($version === null) {
    json_error('Nincs ilyen órarend verzió', 404);
}

$status = (string) ($version['statusz'] ?? '');

if ($status === 'active') {
    json_response(['ok' => false, 'uzenet' => 'Ez a verzió már aktív.'], 409);
}

if ($mode === 'publish' && $status !== 'draft') {
    json_response([
        'ok'     => false,
        'uzenet' => 'Publikálni csak draft verziót lehet. Archivált verzióhoz használd a visszaállítást.',
    ], 409);
}

if ($mode === 'rollback' && $status !== 'archived') {
    json_response([
        'ok'     => false,
        'uzenet' => 'Visszaállítani csak archivált verziót lehet.',
    ], 409);
}

// Publikálás előtti újraellenőrzés az adatbázisból: nem a kliens állítására
// hagyatkozunk, hanem megnézzük, hogy a verzióban tényleg van-e sor, és hogy
// a legutóbbi import riportja hibamentes volt-e.
$summary = ticky_repo_version_summary($version_id);
if (($summary['orak'] ?? 0) === 0) {
    json_response(['ok' => false, 'uzenet' => 'A verzióhoz egyetlen órarend sor sem tartozik.'], 409);
}

if ($mode === 'publish') {
    $import = ticky_repo_import_for_version($version_id);
    if (is_array($import) && (int) ($import['hibak_szama'] ?? 0) > 0) {
        json_response([
            'ok'     => false,
            'uzenet' => 'A verzió importja ' . (int) $import['hibak_szama'] . ' hibát tartalmaz, ezért nem publikálható.',
        ], 409);
    }
}

$previous = ticky_repo_active_version();

try {
    $result = ticky_repo_publish($version_id);
} catch (TickyRepoException $error) {
    json_response(['ok' => false, 'uzenet' => $error->getMessage()], 502);
}

json_response([
    'ok'             => true,
    'mod'            => $mode,
    'verzio'         => ticky_repo_version($version_id),
    'elozo_verzio'   => $previous,
    'eredmeny'       => $result,
    'uzenet'         => $mode === 'rollback'
        ? 'Az órarend visszaállítva a korábbi verzióra.'
        : 'Órarend sikeresen publikálva.',
]);
