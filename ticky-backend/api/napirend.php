<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$params = match_route('/api/napirend/{szam}', request_path());
if ($params === false) {
    json_error('Hiányzó terem szám', 400);
}

$room_code = strtoupper(trim(urldecode((string) ($params['szam'] ?? ''))));
$day_param = $_GET['nap'] ?? null;
$room = ticky_find_room_by_code($room_code);

if ($room === null) {
    json_error('Terem nem található: ' . $room_code, 404);
}

$day_names = ticky_day_names();
$whole_week = $day_param === 'heten';

if ($whole_week) {
    $lessons = ticky_enrich_room_lessons(ticky_get_room_lessons_for_days((string) $room['id'], [1, 2, 3, 4, 5]));
    $by_day = [];

    foreach ($lessons as $lesson) {
        $day = (int) ($lesson['het_napja'] ?? 0);
        $by_day[$day][] = [
            'ora_sorszam' => $lesson['ora_sorszam'],
            'tanar' => $lesson['tanar'],
            'tanar_nev' => $lesson['tanar_nev'],
            'osztaly' => $lesson['osztaly'],
            'tantargy' => $lesson['tantargy'],
            'kezdes' => $lesson['kezdes'],
            'vegzes' => $lesson['vegzes'],
        ];
    }

    $week = [];
    for ($day = 1; $day <= 5; $day++) {
        $week[] = [
            'nap' => $day,
            'nap_neve' => $day_names[$day],
            'orak' => $by_day[$day] ?? [],
        ];
    }

    json_response([
        'terem' => $room['terem_szam'],
        'emelet' => $room['emelet'],
        'het' => $week,
    ]);
}

$day = $day_param !== null ? (int) $day_param : mai_nap();
if ($day < 1 || $day > 5) {
    json_response([
        'terem' => $room['terem_szam'],
        'nap' => $day,
        'uzenet' => 'Nincs tanítás (hétvége)',
        'orak' => [],
    ]);
}

$time = aktualis_ido();
$lessons = ticky_enrich_room_lessons(ticky_get_room_lessons_raw((string) $room['id'], $day));
$result = [];

foreach ($lessons as $lesson) {
    $result[] = [
        'ora_sorszam' => $lesson['ora_sorszam'],
        'tanar' => $lesson['tanar'],
        'tanar_nev' => $lesson['tanar_nev'],
        'osztaly' => $lesson['osztaly'],
        'tantargy' => $lesson['tantargy'],
        'kezdes' => $lesson['kezdes'],
        'vegzes' => $lesson['vegzes'],
        'folyamatban' => $time >= $lesson['kezdes'] && $time <= $lesson['vegzes'],
    ];
}

json_response([
    'terem' => $room['terem_szam'],
    'emelet' => $room['emelet'],
    'nap' => $day,
    'nap_neve' => $day_names[$day] ?? '',
    'ido' => $time,
    'orak' => $result,
]);
