<?php
// api/tanarok.php
// GET /api/tanarok – összes tanár listája

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

handle_cors();

$tanarok = sb_get('tanarok', [
    'select' => 'rovid_nev,nev',
    'order'  => 'rovid_nev.asc',
]);

$source_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];
$merged = [];

foreach ($tanarok as $tanar) {
    $rovid = trim((string) ($tanar['rovid_nev'] ?? ''));
    if ($rovid === '') {
        continue;
    }

    $merged[$rovid] = [
        'rovid_nev' => $rovid,
        'nev' => $tanar['nev'] ?? ($source_names[$rovid] ?? null),
    ];
}

foreach ($source_names as $rovid => $nev) {
    if (!isset($merged[$rovid])) {
        $merged[$rovid] = [
            'rovid_nev' => $rovid,
            'nev' => $nev,
        ];
    } elseif (empty($merged[$rovid]['nev'])) {
        $merged[$rovid]['nev'] = $nev;
    }
}

$tanarok = array_values($merged);
usort($tanarok, static fn(array $left, array $right): int => strnatcasecmp($left['rovid_nev'], $right['rovid_nev']));

json_response([
    'tanarok' => $tanarok,
    'count'   => count($tanarok),
]);
