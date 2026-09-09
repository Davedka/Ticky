<?php
// api/admin_orarend_verziok.php
// GET /api/admin/orarend/verziok – verziólista + a legutóbbi importok naplója.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/timetable_repo.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['GET']);

$schema = ticky_repo_schema_status();
if (!$schema['ok']) {
    json_response([
        'ok'       => false,
        'kod'      => 'HIANYZO_SEMA',
        'uzenet'   => 'Hiányzó adatbázis szerkezet: ' . implode(', ', $schema['hiany'])
            . '. Futtasd le a ticky-backend/sql/001_orarend_verziok.sql szkriptet.',
        'verziok'  => [],
        'importok' => [],
    ], 503);
}

$versions = ticky_repo_versions();

// A sorszámokat egyben kérjük le, hogy ne legyen N+1 hívás a Supabase felé.
$counts = [];
$rows = sb_get('orarendek', [
    'select' => 'verzio_id',
    'limit'  => TICKY_REPO_FETCH_LIMIT,
], 'service');

foreach (is_array($rows) ? $rows : [] as $row) {
    $version_id = $row['verzio_id'] ?? null;
    if ($version_id !== null) {
        $counts[(int) $version_id] = ($counts[(int) $version_id] ?? 0) + 1;
    }
}

foreach ($versions as &$version) {
    $version['sorok_szama'] = $counts[(int) $version['id']] ?? 0;
}
unset($version);

json_response([
    'ok'       => true,
    'verziok'  => $versions,
    'importok' => ticky_repo_imports(),
]);
