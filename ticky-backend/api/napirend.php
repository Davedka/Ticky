<?php
// api/napirend.php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

handle_cors();

$szam = strtoupper(trim((string) ($_GET['szam'] ?? '')));
if ($szam === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $params = match_route('/api/napirend/{szam}', $uri);
    if ($params !== false) {
        $szam = strtoupper(trim((string) ($params['szam'] ?? '')));
    }
}

if ($szam === '') {
    json_error('Hiányzó terem szám', 400);
}

$nap_param = $_GET['nap'] ?? null;
$het_egeszben = ($nap_param === 'heten');
$sz = aktiv_szunet();
$nap = $het_egeszben ? null : ($nap_param !== null ? (int) $nap_param : mai_nap());
$ido = aktualis_ido();
$nap_nevek = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek'];
$source_teacher_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];

$termek = sb_get('termek', [
    'terem_szam' => 'eq.' . $szam,
    'select' => 'id,terem_szam,emelet',
]);

$terem = $termek[0] ?? null;
$terem_id = $terem['id'] ?? null;
$emelet = $terem['emelet'] ?? null;

if (!$het_egeszben && ($nap === null || $nap < 1 || $nap > 5)) {
    json_response([
        'terem' => $szam,
        'emelet' => $emelet,
        'nap' => $nap,
        'uzenet' => 'Nincs tanítás (hétvége)',
        'orak' => [],
    ]);
}

$orak = [];
if ($terem_id !== null) {
    $orarendek_params = [
        'terem_id' => 'eq.' . $terem_id,
        'aktiv' => 'eq.true',
        'select' => 'osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja,tanar_id',
        'order' => 'het_napja.asc,kezdes.asc',
    ];

    if ($het_egeszben) {
        $orarendek_params['het_napja'] = 'in.(1,2,3,4,5)';
    } else {
        $orarendek_params['het_napja'] = 'eq.' . $nap;
    }

    $orak = sb_get('orarendek', $orarendek_params);
}

if ($het_egeszben) {
    if (!empty($orak)) {
        $tanar_map = [];
        $ids = array_unique(array_filter(array_column($orak, 'tanar_id')));
        if (!empty($ids)) {
            foreach (sb_get('tanarok', [
                'id' => 'in.(' . implode(',', $ids) . ')',
                'select' => 'id,rovid_nev,nev',
            ]) as $tanar) {
                $rovid = (string) ($tanar['rovid_nev'] ?? '?');
                $tanar_map[$tanar['id']] = [
                    'rovid_nev' => $rovid,
                    'nev' => $tanar['nev'] ?? ($source_teacher_names[$rovid] ?? null),
                ];
            }
        }

        $het = [];
        foreach ($orak as $ora) {
            $day = (int) ($ora['het_napja'] ?? 0);
            if ($day < 1 || $day > 5) {
                continue;
            }

            $tanar = $tanar_map[$ora['tanar_id']] ?? null;
            $het[$day][] = [
                'ora_sorszam' => $ora['ora_sorszam'] ?? null,
                'tanar' => $tanar['rovid_nev'] ?? '?',
                'tanar_nev' => $tanar['nev'] ?? null,
                'osztaly' => $ora['osztaly'] ?? '?',
                'tantargy' => $ora['tantargy'] ?? '',
                'kezdes' => substr((string) ($ora['kezdes'] ?? ''), 0, 5),
                'vegzes' => substr((string) ($ora['vegzes'] ?? ''), 0, 5),
            ];
        }

        $napok = [];
        for ($day = 1; $day <= 5; $day++) {
            $napok[] = [
                'nap' => $day,
                'nap_neve' => $nap_nevek[$day],
                'orak' => merge_consecutive_orak($het[$day] ?? []),
            ];
        }

        json_response([
            'terem' => $szam,
            'emelet' => $emelet,
            'het' => $napok,
            'szunet' => $sz ? $sz['nev'] : null,
        ]);
    }

    $source_week = function_exists('ticky_source_room_lessons_for_week')
        ? ticky_source_room_lessons_for_week($szam)
        : null;

    if ($source_week !== null) {
        $napok = [];
        foreach ($source_week['het'] as $nap_adat) {
            $day = (int) ($nap_adat['nap'] ?? 0);
            if ($day < 1 || $day > 5) {
                continue;
            }

            $napok[] = [
                'nap' => $day,
                'nap_neve' => $nap_nevek[$day],
                'orak' => merge_consecutive_orak($nap_adat['orak'] ?? []),
            ];
        }

        json_response([
            'terem' => $source_week['terem'] ?? $szam,
            'emelet' => $emelet,
            'het' => $napok,
            'szunet' => $sz ? $sz['nev'] : null,
        ]);
    }

    json_error('Terem nem található: ' . $szam, 404);
}

if (!empty($orak)) {
    $tanar_map = [];
    $ids = array_unique(array_filter(array_column($orak, 'tanar_id')));
    if (!empty($ids)) {
        foreach (sb_get('tanarok', [
            'id' => 'in.(' . implode(',', $ids) . ')',
            'select' => 'id,rovid_nev,nev',
        ]) as $tanar) {
            $rovid = (string) ($tanar['rovid_nev'] ?? '?');
            $tanar_map[$tanar['id']] = [
                'rovid_nev' => $rovid,
                'nev' => $tanar['nev'] ?? ($source_teacher_names[$rovid] ?? null),
            ];
        }
    }

    $result = [];
    foreach ($orak as $ora) {
        $tanar = $tanar_map[$ora['tanar_id']] ?? null;
        $kezdes = substr((string) ($ora['kezdes'] ?? ''), 0, 5);
        $vegzes = substr((string) ($ora['vegzes'] ?? ''), 0, 5);
        $result[] = [
            'ora_sorszam' => $ora['ora_sorszam'] ?? null,
            'tanar' => $tanar['rovid_nev'] ?? '?',
            'tanar_nev' => $tanar['nev'] ?? null,
            'osztaly' => $ora['osztaly'] ?? '?',
            'tantargy' => $ora['tantargy'] ?? '',
            'kezdes' => $kezdes,
            'vegzes' => $vegzes,
            'folyamatban' => ($ido >= $kezdes && $ido <= $vegzes),
        ];
    }

    json_response([
        'terem' => $szam,
        'emelet' => $emelet,
        'nap' => $nap,
        'nap_neve' => $nap_nevek[$nap] ?? '',
        'ido' => $ido,
        'orak' => merge_consecutive_orak($result),
        'szunet' => $sz ? $sz['nev'] : null,
    ]);
}

$source_day = function_exists('ticky_source_room_lessons_for_day')
    ? ticky_source_room_lessons_for_day($szam, (int) $nap)
    : null;

if ($source_day !== null) {
    $result = [];
    foreach ($source_day['orak'] as $ora) {
        $kezdes = substr((string) ($ora['kezdes'] ?? ''), 0, 5);
        $vegzes = substr((string) ($ora['vegzes'] ?? ''), 0, 5);
        $result[] = [
            'ora_sorszam' => $ora['ora_sorszam'] ?? null,
            'tanar' => $ora['tanar'] ?? '?',
            'tanar_nev' => $ora['tanar_nev'] ?? null,
            'osztaly' => $ora['osztaly'] ?? '?',
            'tantargy' => $ora['tantargy'] ?? '',
            'kezdes' => $kezdes,
            'vegzes' => $vegzes,
            'folyamatban' => ($ido >= $kezdes && $ido <= $vegzes),
        ];
    }

    json_response([
        'terem' => $source_day['terem'] ?? $szam,
        'emelet' => $emelet,
        'nap' => $nap,
        'nap_neve' => $nap_nevek[$nap] ?? '',
        'ido' => $ido,
        'orak' => merge_consecutive_orak($result),
        'szunet' => $sz ? $sz['nev'] : null,
    ]);
}

json_error('Terem nem található: ' . $szam, 404);
