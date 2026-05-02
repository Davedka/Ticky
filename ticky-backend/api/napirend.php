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

        $het_grouped = [];
        foreach ($orak as $ora) {
            $day = (int) ($ora['het_napja'] ?? 0);
            if ($day < 1 || $day > 5) {
                continue;
            }

            $tanar = $tanar_map[$ora['tanar_id']] ?? null;
            $kezdes = substr((string) ($ora['kezdes'] ?? ''), 0, 5);
            $vegzes = substr((string) ($ora['vegzes'] ?? ''), 0, 5);
            $key = $kezdes . '_' . $vegzes;
            $tanar_code = $tanar['rovid_nev'] ?? '?';
            $tanar_nev = $tanar['nev'] ?? null;
            $osztaly = (string) ($ora['osztaly'] ?? '?');
            $tantargy = (string) ($ora['tantargy'] ?? '');

            if (!isset($het_grouped[$day][$key])) {
                $het_grouped[$day][$key] = [
                    'ora_sorszam' => $ora['ora_sorszam'] ?? null,
                    'kezdes' => $kezdes,
                    'vegzes' => $vegzes,
                    'csoportok' => [],
                ];
            }

            $exists = false;
            foreach ($het_grouped[$day][$key]['csoportok'] as $g) {
                if ($g['tanar'] === $tanar_code && $g['osztaly'] === $osztaly && $g['tantargy'] === $tantargy) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $het_grouped[$day][$key]['csoportok'][] = [
                    'tanar' => $tanar_code,
                    'tanar_nev' => $tanar_nev,
                    'osztaly' => $osztaly,
                    'tantargy' => $tantargy,
                ];
            }
        }

        $napok = [];
        for ($day = 1; $day <= 5; $day++) {
            $day_orak = [];
            foreach ($het_grouped[$day] ?? [] as $slot) {
                $cs = $slot['csoportok'];
                $tanarok = $osztalyok = $tantargyak = [];
                foreach ($cs as $c) {
                    if (!in_array($c['tanar'], $tanarok, true)) $tanarok[] = $c['tanar'];
                    if (!in_array($c['osztaly'], $osztalyok, true)) $osztalyok[] = $c['osztaly'];
                    if ($c['tantargy'] !== '' && !in_array($c['tantargy'], $tantargyak, true)) $tantargyak[] = $c['tantargy'];
                }
                $day_orak[] = [
                    'ora_sorszam' => $slot['ora_sorszam'],
                    'tanar' => implode(' / ', $tanarok),
                    'tanar_nev' => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
                    'osztaly' => implode('/', $osztalyok),
                    'tantargy' => implode(' / ', $tantargyak),
                    'kezdes' => $slot['kezdes'],
                    'vegzes' => $slot['vegzes'],
                    'is_csoport' => count($cs) > 1,
                    'csoportok' => $cs,
                ];
            }
            usort($day_orak, static fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));
            $napok[] = [
                'nap' => $day,
                'nap_neve' => $nap_nevek[$day],
                'orak' => $day_orak,
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
                'orak' => $nap_adat['orak'] ?? [],
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

    $grouped = [];
    foreach ($orak as $ora) {
        $tanar = $tanar_map[$ora['tanar_id']] ?? null;
        $kezdes = substr((string) ($ora['kezdes'] ?? ''), 0, 5);
        $vegzes = substr((string) ($ora['vegzes'] ?? ''), 0, 5);
        $key = $kezdes . '_' . $vegzes;
        $tanar_code = $tanar['rovid_nev'] ?? '?';
        $tanar_nev = $tanar['nev'] ?? null;
        $osztaly = (string) ($ora['osztaly'] ?? '?');
        $tantargy = (string) ($ora['tantargy'] ?? '');

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'ora_sorszam' => $ora['ora_sorszam'] ?? null,
                'kezdes' => $kezdes,
                'vegzes' => $vegzes,
                'csoportok' => [],
            ];
        }

        $exists = false;
        foreach ($grouped[$key]['csoportok'] as $g) {
            if ($g['tanar'] === $tanar_code && $g['osztaly'] === $osztaly && $g['tantargy'] === $tantargy) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $grouped[$key]['csoportok'][] = [
                'tanar' => $tanar_code,
                'tanar_nev' => $tanar_nev,
                'osztaly' => $osztaly,
                'tantargy' => $tantargy,
            ];
        }
    }

    $result = [];
    foreach ($grouped as $slot) {
        $cs = $slot['csoportok'];
        $tanarok = $osztalyok = $tantargyak = [];
        foreach ($cs as $c) {
            if (!in_array($c['tanar'], $tanarok, true)) $tanarok[] = $c['tanar'];
            if (!in_array($c['osztaly'], $osztalyok, true)) $osztalyok[] = $c['osztaly'];
            if ($c['tantargy'] !== '' && !in_array($c['tantargy'], $tantargyak, true)) $tantargyak[] = $c['tantargy'];
        }
        $result[] = [
            'ora_sorszam' => $slot['ora_sorszam'],
            'tanar' => implode(' / ', $tanarok),
            'tanar_nev' => count($cs) === 1 ? ($cs[0]['tanar_nev'] ?? null) : null,
            'osztaly' => implode('/', $osztalyok),
            'tantargy' => implode(' / ', $tantargyak),
            'kezdes' => $slot['kezdes'],
            'vegzes' => $slot['vegzes'],
            'folyamatban' => ($ido >= $slot['kezdes'] && $ido <= $slot['vegzes']),
            'is_csoport' => count($cs) > 1,
            'csoportok' => $cs,
        ];
    }
    usort($result, static fn($a, $b) => strcmp($a['kezdes'], $b['kezdes']));

    json_response([
        'terem' => $szam,
        'emelet' => $emelet,
        'nap' => $nap,
        'nap_neve' => $nap_nevek[$nap] ?? '',
        'ido' => $ido,
        'orak' => $result,
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
        $entry = $ora;
        $entry['kezdes'] = $kezdes;
        $entry['vegzes'] = $vegzes;
        $entry['folyamatban'] = ($ido >= $kezdes && $ido <= $vegzes);
        $result[] = $entry;
    }

    json_response([
        'terem' => $source_day['terem'] ?? $szam,
        'emelet' => $emelet,
        'nap' => $nap,
        'nap_neve' => $nap_nevek[$nap] ?? '',
        'ido' => $ido,
        'orak' => $result,
        'szunet' => $sz ? $sz['nev'] : null,
    ]);
}

json_error('Terem nem található: ' . $szam, 404);
