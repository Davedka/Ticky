<?php
// api/tanarok.php
// GET /api/tanarok – összes tanár listája

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

$tanarok = sb_get('tanarok', [
    'select' => 'rovid_nev,nev',
    'order'  => 'rovid_nev.asc',
]);

json_response([
    'tanarok' => $tanarok,
    'count'   => count($tanarok),
]);
