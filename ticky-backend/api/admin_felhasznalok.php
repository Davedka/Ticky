<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';

if (!admin_can_see_ui()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['hiba' => 'Bejelentkezés szükséges']);
    exit;
}

require_admin_api_request(['GET']);

$rows = sb_get('felhasznalok', [
    'select' => 'id,felhasznalonev,nev,szerep,aktiv,letrehozva',
    'order' => 'letrehozva.desc',
], 'service');

json_response([
    'felhasznalok' => $rows,
    'count' => count($rows),
]);
