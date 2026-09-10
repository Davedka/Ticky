?php
// api/admin_orarend_verzio.php
// GET    /api/admin/orarend/{id} – egy verzió részletei és import riportja
// DELETE /api/admin/orarend/{id} – draft verzió törlése

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/timetable_repo.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['GET', 'DELETE']);

$version_id = (int) ($_GET['id'] ?? 0);
if ($version_id <= 0) {
    json_error('Érvénytelen verzió azonosító', 400);
}

$version = ticky_repo_version($version_id);
if ($version === null) {
    json_error('Nincs ilyen órarend verzió', 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ticky_require_fresh_admin_auth();

    if (($version['statusz'] ?? '') !== 'draft') {
        json_error('Csak draft állapotú verzió törölhető.', 409);
    }

    try {
        $result = ticky_repo_delete_draft($version_id);
    } catch (TickyRepoException $error) {
        json_response(['ok' => false, 'uzenet' => $error->getMessage()], 502);
    }

    json_response(['ok' => true, 'eredmeny' => $result]);
}

$active = ticky_repo_active_version();

json_response([
    'ok'           => true,
    'verzio'       => $version,
    'osszegzes'    => ticky_repo_version_summary($version_id),
    'import'       => ticky_repo_import_for_version($version_id),
    'aktiv_verzio' => $active,
    'elteresek'    => ($active !== null && (int) $active['id'] === $version_id)
        ? null
        : ticky_repo_diff($version_id, $active === null ? null : (int) $active['id']),
]);
