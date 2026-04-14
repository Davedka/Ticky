<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

require_admin_api_request(['GET']);

$rows = sb_get('felhasznalok', [
    'select' => 'id,felhasznalonev,nev,szerep,aktiv,letrehozva',
    'order' => 'letrehozva.desc',
], 'service');

json_response([
    'felhasznalok' => $rows,
    'count' => count($rows),
]);
