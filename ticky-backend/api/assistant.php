<?php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST keressel hasznalhato', 405);
}

function assistant_response(
    string $reply,
    array $cards = [],
    array $actions = [],
    array $suggestions = []
): never {
    json_response([
        'reply' => $reply,
        'cards' => $cards,
        'actions' => $actions,
        'suggestions' => $suggestions,
    ]);
}

function assistant_lower(string $text): string {
    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
}

function assistant_normalize(string $text): string {
    $text = assistant_lower(trim($text));

    return strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
}

function assistant_context_key(string $context): string {
    $context = assistant_normalize($context);
    $allowed = ['general', 'home', 'termek', 'tanar', 'terem', 'napirend'];
    return in_array($context, $allowed, true) ? $context : 'general';
}

function assistant_has_any(string $text, array $needles): bool {
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($text, $needle)) {
            return true;
        }
    }

    return false;
}

function assistant_extract_room(string $text): ?string {
    if (preg_match('/(?<!\d)(\d{1,4}[A-Za-z]?)(?!\d)/', $text, $matches)) {
        return strtoupper($matches[1]);
    }

    return null;
}

function assistant_context_room_code(array $context_data): ?string {
    $room = trim((string) ($context_data['room'] ?? ''));
    if ($room === '') {
        return null;
    }

    return strtoupper($room);
}

function assistant_day_name(int $day): string {
    return [
        0 => 'Hétvége',
        1 => 'Hétfő',
        2 => 'Kedd',
        3 => 'Szerda',
        4 => 'Csütörtök',
        5 => 'Péntek',
    ][$day] ?? 'Ismeretlen nap';
}

function assistant_get_teachers_by_ids(array $teacher_ids): array {
    $teacher_ids = array_values(array_filter(array_unique($teacher_ids)));
    if (empty($teacher_ids)) {
        return [];
    }

    $teachers = sb_get('tanarok', [
        'id' => 'in.(' . implode(',', $teacher_ids) . ')',
        'select' => 'id,rovid_nev,nev',
    ]);

    $teacher_map = [];
    foreach ($teachers as $teacher) {
        $teacher_map[$teacher['id']] = $teacher;
    }

    return $teacher_map;
}

function assistant_get_room_record(string $room_code): ?array {
    $rooms = sb_get('termek', [
        'terem_szam' => 'eq.' . strtoupper($room_code),
        'select' => 'id,terem_szam,emelet',
    ]);

    return $rooms[0] ?? null;
}

function assistant_get_room_lessons(string $room_id, int $day): array {
    if ($day === 0) {
        return [];
    }

    return sb_get('orarendek', [
        'terem_id' => 'eq.' . $room_id,
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'select' => 'ora_sorszam,osztaly,tantargy,kezdes,vegzes,tanar_id',
        'order' => 'kezdes.asc',
    ]);
}

function assistant_enrich_lessons(array $lessons): array {
    if (empty($lessons)) {
        return [];
    }

    $teacher_map = assistant_get_teachers_by_ids(array_column($lessons, 'tanar_id'));
    $result = [];

    foreach ($lessons as $lesson) {
        $teacher = $teacher_map[$lesson['tanar_id']] ?? null;
        $result[] = [
            'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
            'osztaly' => $lesson['osztaly'] ?? '',
            'tantargy' => $lesson['tantargy'] ?? '',
            'kezdes' => substr((string) ($lesson['kezdes'] ?? ''), 0, 5),
            'vegzes' => substr((string) ($lesson['vegzes'] ?? ''), 0, 5),
            'tanar' => $teacher['rovid_nev'] ?? '?',
            'tanar_nev' => $teacher['nev'] ?? null,
        ];
    }

    return $result;
}

function assistant_get_room_context(string $room_code): ?array {
    $room = assistant_get_room_record($room_code);
    if ($room === null) {
        return null;
    }

    $day = mai_nap();
    $time = aktualis_ido();
    $lessons = assistant_enrich_lessons(assistant_get_room_lessons((string) $room['id'], $day));

    $current = null;
    $next = null;
    foreach ($lessons as $lesson) {
        if ($time >= $lesson['kezdes'] && $time <= $lesson['vegzes']) {
            $current = $lesson;
            continue;
        }

        if ($time < $lesson['kezdes'] && $next === null) {
            $next = $lesson;
        }
    }

    return [
        'room' => $room,
        'day' => $day,
        'time' => $time,
        'lessons' => $lessons,
        'current' => $current,
        'next' => $next,
    ];
}

function assistant_minutes_until(string $hhmm): int {
    $target = strtotime($hhmm);
    $now = strtotime(aktualis_ido());
    if ($target === false || $now === false) {
        return 0;
    }

    return max(0, (int) round(($target - $now) / 60));
}

function assistant_get_rooms_snapshot(): array {
    $day = mai_nap();
    $time = aktualis_ido();
    $rooms = sb_get('termek', [
        'select' => 'id,terem_szam,emelet',
        'order' => 'terem_szam.asc',
    ]);

    if (empty($rooms)) {
        return [];
    }

    if ($day === 0) {
        return array_map(static fn(array $room): array => [
            'terem_szam' => $room['terem_szam'],
            'emelet' => $room['emelet'],
            'allapot' => 'szabad',
            'aktualis' => null,
        ], $rooms);
    }

    $room_ids = array_column($rooms, 'id');
    $active_lessons = sb_get('orarendek', [
        'terem_id' => 'in.(' . implode(',', $room_ids) . ')',
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'kezdes' => 'lte.' . $time . ':00',
        'vegzes' => 'gte.' . $time . ':00',
        'select' => 'terem_id,osztaly,tantargy,kezdes,vegzes,tanar_id',
    ]);

    $teacher_map = assistant_get_teachers_by_ids(array_column($active_lessons, 'tanar_id'));
    $busy_by_room = [];

    foreach ($active_lessons as $lesson) {
        $teacher = $teacher_map[$lesson['tanar_id']] ?? null;
        $busy_by_room[$lesson['terem_id']] = [
            'tanar' => $teacher['rovid_nev'] ?? '?',
            'tanar_nev' => $teacher['nev'] ?? null,
            'osztaly' => $lesson['osztaly'] ?? '',
            'tantargy' => $lesson['tantargy'] ?? '',
            'kezdes' => substr((string) ($lesson['kezdes'] ?? ''), 0, 5),
            'vegzes' => substr((string) ($lesson['vegzes'] ?? ''), 0, 5),
        ];
    }

    $snapshot = [];
    foreach ($rooms as $room) {
        $snapshot[] = [
            'terem_szam' => $room['terem_szam'],
            'emelet' => $room['emelet'],
            'allapot' => isset($busy_by_room[$room['id']]) ? 'foglalt' : 'szabad',
            'aktualis' => $busy_by_room[$room['id']] ?? null,
        ];
    }

    return $snapshot;
}

function assistant_help(string $context, array $context_data = []): never {
    $room = assistant_context_room_code($context_data);

    switch ($context) {
        case 'home':
            assistant_response(
                'Itt a főoldalhoz segítek. Kérdezhetsz termekről, konkrét teremállapotról vagy arról, melyik nézetet érdemes megnyitni.',
                [],
                [
                    ['label' => 'Összes terem', 'href' => '/termek'],
                    ['label' => 'Tanár kereső', 'href' => '/tanar'],
                ]
            );

        case 'termek':
            assistant_response(
                'Itt a termek oldalához segítek. Kérdezhetsz szabad vagy foglalt termekről, illetve egy konkrét terem állapotáról.',
                [],
                [
                    ['label' => 'Összes terem', 'href' => '/termek'],
                ]
            );

        case 'tanar':
            assistant_response(
                'Itt a tanár oldalához segítek. Tudok szabad termet keresni, konkrét teremállapotot mondani, vagy megnyitni a tanár keresőt.',
                [],
                [
                    ['label' => 'Tanár kereső', 'href' => '/tanar'],
                    ['label' => 'Összes terem', 'href' => '/termek'],
                ]
            );

        case 'terem':
            $reply = $room !== null
                ? 'Itt a ' . $room . '. teremhez segítek. Kérdezhetsz arról, mi van most itt, mi lesz később, vagy van-e másik szabad terem.'
                : 'Itt a terem oldalához segítek. Kérdezhetsz az aktuális terem állapotáról vagy szabad másik termekről.';
            $actions = [];
            if ($room !== null) {
                $actions[] = ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room)];
                $actions[] = ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($room) . '/nap'];
            }
            assistant_response($reply, [], $actions);

        case 'napirend':
            $reply = $room !== null
                ? 'Itt a ' . $room . '. terem napirendjéhez segítek. Kérdezhetsz a mai állapotról, a következő óráról vagy más szabad termekről.'
                : 'Itt a napirend oldalhoz segítek. Kérdezhetsz egy terem mai állapotáról vagy szabad termekről.';
            $actions = [];
            if ($room !== null) {
                $actions[] = ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room)];
                $actions[] = ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($room) . '/nap'];
            }
            assistant_response($reply, [], $actions);

        default:
            assistant_response(
                'Segítek eligazodni a Tickyben. Meg tudom nézni, melyik termek szabadok vagy foglaltak most, és mi történik egy adott teremben.',
                [],
                [
                    ['label' => 'Összes terem', 'href' => '/termek'],
                    ['label' => 'Tanár kereső', 'href' => '/tanar'],
                ]
            );
    }
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$message = trim((string) ($body['message'] ?? ''));
$normalized = assistant_normalize($message);
$context = assistant_context_key((string) ($body['context'] ?? 'general'));
$context_data = is_array($body['contextData'] ?? null) ? $body['contextData'] : [];

if ($message === '' || assistant_has_any($normalized, ['help', 'segit', 'mit tudsz', 'szia', 'hello'])) {
    assistant_help($context, $context_data);
}

if (assistant_has_any($normalized, ['tanar', 'tanar kereso'])) {
    assistant_response(
        'Tanár alapján a Tanár kereső oldalon tudsz biztosan keresni. Ott valós listából választhatsz, így nem kell fejből tudnod a neveket.',
        [],
        [
            ['label' => 'Tanár kereső megnyitása', 'href' => '/tanar'],
        ]
    );
}

if (assistant_has_any($normalized, ['qr'])) {
    assistant_response(
        'Megnyitom neked a QR oldalt.',
        [],
        [
            ['label' => 'QR oldal', 'href' => '/qr'],
        ]
    );
}

if (assistant_has_any($normalized, ['kijelzo'])) {
    assistant_response(
        'A folyosói kijelző nézethez itt tudsz továbblépni.',
        [],
        [
            ['label' => 'Kijelző oldal', 'href' => '/kijelzo'],
        ]
    );
}

if (assistant_has_any($normalized, ['admin'])) {
    assistant_response(
        'Az admin felületet innen tudod megnyitni.',
        [],
        [
            ['label' => 'Admin oldal', 'href' => '/admin'],
        ]
    );
}

$room_code = assistant_extract_room($message);
$context_room = assistant_context_room_code($context_data);
if (
    $room_code === null
    && $context_room !== null
    && in_array($context, ['terem', 'napirend'], true)
    && assistant_has_any($normalized, ['itt', 'ebben', 'ebbe', 'most', 'kovetkezo', 'mi van', 'mi lesz', 'napirend', 'orarend', 'terem'])
) {
    $room_code = $context_room;
}

if ($room_code !== null) {
    $room_context = assistant_get_room_context($room_code);
    if ($room_context === null) {
        assistant_response(
            'Nem találtam ilyen termet: ' . $room_code . '.',
            [],
            [
                ['label' => 'Összes terem', 'href' => '/termek'],
            ]
        );
    }

    $room = $room_context['room'];
    $day = $room_context['day'];
    $current = $room_context['current'];
    $next = $room_context['next'];
    $lessons = $room_context['lessons'];

    if (
        assistant_has_any($normalized, ['napirend', 'orarend', 'ma', 'mi lesz'])
        || (assistant_has_any($normalized, ['mutasd']) && assistant_has_any($normalized, ['terem', 'itt']))
    ) {
        if ($day === 0) {
            assistant_response(
                'Ma hétvége van, ezért a ' . $room['terem_szam'] . ' teremhez nincs tanítási napirend.',
                [],
                [
                    ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
                ]
            );
        }

        if (empty($lessons)) {
            assistant_response(
                'A ' . $room['terem_szam'] . ' teremben ma nincs tanóra.',
                [],
                [
                    ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
                ]
            );
        }

        $cards = [];
        foreach (array_slice($lessons, 0, 6) as $lesson) {
            $cards[] = [
                'eyebrow' => ($lesson['ora_sorszam'] ?? '?') . '. óra',
                'title' => $lesson['kezdes'] . ' - ' . $lesson['vegzes'],
                'meta' => $lesson['tanar'] . ' · ' . $lesson['osztaly'],
                'detail' => $lesson['tantargy'],
            ];
        }

        assistant_response(
            'A ' . $room['terem_szam'] . ' terem mai napirendje ' . assistant_day_name($day) . ' napra.',
            $cards,
            [
                ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($room['terem_szam']) . '/nap'],
                ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
            ]
        );
    }

    if ($day === 0) {
        assistant_response(
            'Most hétvége van, ezért a ' . $room['terem_szam'] . ' terem jelenleg szabad.',
            [],
            [
                ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
            ]
        );
    }

    if ($current !== null) {
        $cards = [[
            'eyebrow' => 'Most',
            'title' => $current['tanar_nev'] ?: $current['tanar'],
            'meta' => $current['osztaly'] . ' · ' . $current['tantargy'],
            'detail' => $current['kezdes'] . ' - ' . $current['vegzes'] . ' · még ' . assistant_minutes_until($current['vegzes']) . ' perc',
        ]];

        if ($next !== null) {
            $cards[] = [
                'eyebrow' => 'Következő',
                'title' => $next['tanar_nev'] ?: $next['tanar'],
                'meta' => $next['osztaly'] . ' · ' . $next['tantargy'],
                'detail' => $next['kezdes'] . ' - ' . $next['vegzes'],
            ];
        }

        assistant_response(
            'A ' . $room['terem_szam'] . ' terem most foglalt.',
            $cards,
            [
                ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
                ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($room['terem_szam']) . '/nap'],
            ]
        );
    }

    $reply = 'A ' . $room['terem_szam'] . ' terem most szabad.';
    $cards = [];

    if ($next !== null) {
        $cards[] = [
            'eyebrow' => 'Következő óra',
            'title' => $next['kezdes'] . ' - ' . $next['vegzes'],
            'meta' => $next['tanar'] . ' · ' . $next['osztaly'],
            'detail' => $next['tantargy'],
        ];
    } else {
        $reply .= ' Ma már nincs több óra benne.';
    }

    assistant_response(
        $reply,
        $cards,
        [
            ['label' => 'Terem oldal', 'href' => '/terem/' . rawurlencode($room['terem_szam'])],
            ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($room['terem_szam']) . '/nap'],
        ]
    );
}

if (assistant_has_any($normalized, ['szabad']) && assistant_has_any($normalized, ['terem', 'termek'])) {
    $snapshot = assistant_get_rooms_snapshot();
    $free_rooms = array_values(array_filter($snapshot, static fn(array $room): bool => $room['allapot'] === 'szabad'));

    if (empty($free_rooms)) {
        assistant_response(
            'Most egyetlen szabad termet sem találtam.',
            [],
            [
                ['label' => 'Összes terem', 'href' => '/termek'],
            ]
        );
    }

    $cards = [];
    foreach (array_slice($free_rooms, 0, 8) as $room) {
        $cards[] = [
            'eyebrow' => 'Szabad',
            'title' => $room['terem_szam'],
            'meta' => 'Emelet: ' . ($room['emelet'] ?? '?'),
        ];
    }

    assistant_response(
        'Most ' . count($free_rooms) . ' szabad terem van.',
        $cards,
        [
            ['label' => 'Összes terem', 'href' => '/termek'],
        ]
    );
}

if (assistant_has_any($normalized, ['foglalt']) && assistant_has_any($normalized, ['terem', 'termek'])) {
    $snapshot = assistant_get_rooms_snapshot();
    $busy_rooms = array_values(array_filter($snapshot, static fn(array $room): bool => $room['allapot'] === 'foglalt'));

    if (empty($busy_rooms)) {
        assistant_response(
            'Most nem látok foglalt termet.',
            [],
            [
                ['label' => 'Összes terem', 'href' => '/termek'],
            ]
        );
    }

    $cards = [];
    foreach (array_slice($busy_rooms, 0, 8) as $room) {
        $current = $room['aktualis'];
        $cards[] = [
            'eyebrow' => 'Foglalt',
            'title' => $room['terem_szam'],
            'meta' => ($current['tanar'] ?? '?') . ' · ' . ($current['osztaly'] ?? ''),
            'detail' => ($current['tantargy'] ?? '') . ' · ' . ($current['kezdes'] ?? '') . ' - ' . ($current['vegzes'] ?? ''),
        ];
    }

    assistant_response(
        'Most ' . count($busy_rooms) . ' foglalt terem van.',
        $cards,
        [
            ['label' => 'Összes terem', 'href' => '/termek'],
        ]
    );
}

if (assistant_has_any($normalized, ['most']) || assistant_has_any($normalized, ['mi tortenik'])) {
    $snapshot = assistant_get_rooms_snapshot();
    $free_count = count(array_filter($snapshot, static fn(array $room): bool => $room['allapot'] === 'szabad'));
    $busy_count = count($snapshot) - $free_count;

    assistant_response(
        'Jelenleg ' . $free_count . ' terem szabad és ' . $busy_count . ' terem foglalt.',
        [
            [
                'eyebrow' => 'Most',
                'title' => $free_count . ' szabad terem',
                'meta' => 'A teljes listát egy kattintással meg tudod nézni.',
            ],
            [
                'eyebrow' => 'Most',
                'title' => $busy_count . ' foglalt terem',
                'meta' => 'Ha kell, megmutatom a részleteket is.',
            ],
        ],
        [
            ['label' => 'Összes terem', 'href' => '/termek'],
            ['label' => 'Tanár kereső', 'href' => '/tanar'],
        ]
    );
}

assistant_help($context, $context_data);
