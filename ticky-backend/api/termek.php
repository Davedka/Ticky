<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/domain.php';

handle_cors();

$day = mai_nap();
$time = aktualis_ido();
$include_state = ($_GET['allapot'] ?? '') === '1';

if ($include_state) {
    $rooms = ticky_get_rooms_snapshot($day, $time);
} else {
    $rooms = array_map(static fn(array $room): array => [
        'terem_szam' => $room['terem_szam'],
        'emelet' => $room['emelet'],
    ], ticky_get_rooms());
}

json_response([
    'termek' => $rooms,
    'count' => count($rooms),
    'nap' => $day,
    'ido' => $time,
]);
