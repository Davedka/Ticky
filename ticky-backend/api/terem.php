<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$params = match_route('/api/terem/{szam}', request_path());
if ($params === false) {
    json_error('Hiányzó terem szám', 400);
}

$room_code = strtoupper(trim(urldecode((string) ($params['szam'] ?? ''))));
$context = ticky_get_room_context($room_code);

if ($context === null) {
    json_error('Terem nem található: ' . $room_code, 404);
}

$room = $context['room'];
$day = $context['day'];
$time = $context['time'];
$current = $context['current'];
$next = $context['next'];

if ($day === 0) {
    json_response([
        'terem' => $room['terem_szam'],
        'emelet' => $room['emelet'],
        'allapot' => 'szabad',
        'uzenet' => 'Hétvége – nincs tanítás',
        'aktualis' => null,
        'kovetkezo' => null,
    ]);
}

$response = [
    'terem' => $room['terem_szam'],
    'emelet' => $room['emelet'],
    'allapot' => $current !== null ? 'foglalt' : 'szabad',
    'aktualis' => null,
    'kovetkezo' => $next ? [
        'ora_sorszam' => $next['ora_sorszam'],
        'tanar' => $next['tanar'],
        'tanar_nev' => $next['tanar_nev'],
        'osztaly' => $next['osztaly'],
        'tantargy' => $next['tantargy'],
        'kezdes' => $next['kezdes'],
        'vegzes' => $next['vegzes'],
    ] : null,
];

if ($current !== null) {
    $response['aktualis'] = [
        'ora_sorszam' => $current['ora_sorszam'],
        'tanar' => $current['tanar'],
        'tanar_nev' => $current['tanar_nev'],
        'osztaly' => $current['osztaly'],
        'tantargy' => $current['tantargy'],
        'kezdes' => $current['kezdes'],
        'vegzes' => $current['vegzes'],
        'perc_maradt' => ticky_minutes_until($current['vegzes'], $time),
    ];
}

json_response($response);
