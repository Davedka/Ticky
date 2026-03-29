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

function assistant_get_teacher_directory(): array {
    static $teachers = null;

    if ($teachers !== null) {
        return $teachers;
    }

    $teachers = sb_get('tanarok', [
        'select' => 'id,rovid_nev,nev',
        'order' => 'rovid_nev.asc',
    ]);

    return $teachers;
}

function assistant_teacher_code(string $value): string {
    $value = preg_replace('/[^[:alnum:]]/u', '', $value) ?? '';
    return strtoupper($value);
}

function assistant_extract_teacher_candidates(string $message): array {
    preg_match_all('/[\p{L}\p{N}.\-]+/u', $message, $matches);
    $tokens = $matches[0] ?? [];
    $stopwords = [
        'a', 'az', 'es', 'hol', 'van', 'most', 'ma', 'melyik', 'teremben',
        'terem', 'tanar', 'tanart', 'tanarnal', 'tanarnak', 'tanarhoz',
        'ora', 'oraja', 'orarend', 'napirend', 'kereso', 'keresd', 'itt',
        'merre', 'lesz', 'mi', 'meg', 'nyisd', 'oldal',
    ];

    $candidates = [];
    foreach ($tokens as $token) {
        $normalized = assistant_normalize($token);
        if ($normalized === '' || in_array($normalized, $stopwords, true)) {
            continue;
        }

        $code = assistant_teacher_code($token);
        if (strlen($code) >= 2) {
            $candidates[] = $code;
        }
    }

    return array_values(array_unique($candidates));
}

function assistant_find_teacher(string $message, string $normalized): ?array {
    $teachers = assistant_get_teacher_directory();
    if (empty($teachers)) {
        return null;
    }

    $candidates = assistant_extract_teacher_candidates($message);
    foreach ($candidates as $candidate) {
        foreach ($teachers as $teacher) {
            if (assistant_teacher_code((string) ($teacher['rovid_nev'] ?? '')) === $candidate) {
                return $teacher;
            }
        }
    }

    foreach ($teachers as $teacher) {
        $full_name = assistant_normalize((string) ($teacher['nev'] ?? ''));
        if ($full_name !== '' && ($normalized === $full_name || str_contains($normalized, $full_name))) {
            return $teacher;
        }
    }

    $filtered_words = array_values(array_filter(
        preg_split('/\s+/', preg_replace('/[^[:alnum:]\p{L}]+/u', ' ', $normalized) ?? ''),
        static fn(string $word): bool => strlen($word) >= 3
    ));

    $matches = [];
    foreach ($teachers as $teacher) {
        $haystack = assistant_normalize(trim((string) ($teacher['rovid_nev'] ?? '') . ' ' . (string) ($teacher['nev'] ?? '')));
        if ($haystack === '') {
            continue;
        }

        $all_found = true;
        foreach ($filtered_words as $word) {
            if (!str_contains($haystack, $word)) {
                $all_found = false;
                break;
            }
        }

        if ($all_found && !empty($filtered_words)) {
            $matches[] = $teacher;
        }
    }

    return count($matches) === 1 ? $matches[0] : null;
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

function assistant_room_label(string $room): string {
    return str_contains($room, '/')
        ? $room . '. termek'
        : $room . '. terem';
}

function assistant_room_location_label(string $room): string {
    return str_contains($room, '/')
        ? $room . '. termekben'
        : $room . '. teremben';
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

function assistant_get_teacher_lessons(string $teacher_id, int $day): array {
    if ($day === 0) {
        return [];
    }

    $lessons_raw = sb_get('orarendek', [
        'tanar_id' => 'eq.' . $teacher_id,
        'het_napja' => 'eq.' . $day,
        'aktiv' => 'eq.true',
        'select' => 'ora_sorszam,kezdes,vegzes,osztaly,tantargy,termek(terem_szam)',
        'order' => 'kezdes.asc,ora_sorszam.asc',
    ]);

    $grouped = [];
    foreach ($lessons_raw as $lesson) {
        $terem = $lesson['termek']['terem_szam'] ?? '?';
        $osztaly = $lesson['osztaly'] ?? '?';
        $kezdes = substr((string) ($lesson['kezdes'] ?? ''), 0, 5);
        $vegzes = substr((string) ($lesson['vegzes'] ?? ''), 0, 5);
        $key = $kezdes . '_' . $vegzes;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'kezdes' => $kezdes,
                'vegzes' => $vegzes,
                'ora_sorszam' => $lesson['ora_sorszam'] ?? null,
                'tantargy' => $lesson['tantargy'] ?? '',
                'csoportok' => [],
            ];
        }

        $exists = false;
        foreach ($grouped[$key]['csoportok'] as $group) {
            if ($group['terem'] === $terem && $group['osztaly'] === $osztaly) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $grouped[$key]['csoportok'][] = [
                'terem' => $terem,
                'osztaly' => $osztaly,
            ];
        }
    }

    $lessons = [];
    foreach ($grouped as $lesson) {
        $rooms = [];
        $classes = [];
        foreach ($lesson['csoportok'] as $group) {
            if (!in_array($group['terem'], $rooms, true)) {
                $rooms[] = $group['terem'];
            }
            if (!in_array($group['osztaly'], $classes, true)) {
                $classes[] = $group['osztaly'];
            }
        }

        $lessons[] = [
            'kezdes' => $lesson['kezdes'],
            'vegzes' => $lesson['vegzes'],
            'ora_sorszam' => $lesson['ora_sorszam'],
            'tantargy' => $lesson['tantargy'],
            'is_csoport' => count($lesson['csoportok']) > 1,
            'terem' => implode(' / ', $rooms),
            'osztaly' => implode(', ', $classes),
            'csoportok' => $lesson['csoportok'],
        ];
    }

    usort($lessons, static fn(array $a, array $b): int => strcmp($a['kezdes'], $b['kezdes']));
    return $lessons;
}

function assistant_get_teacher_context(array $teacher): array {
    $day = mai_nap();
    $time = aktualis_ido();
    $lessons = assistant_get_teacher_lessons((string) $teacher['id'], $day);

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
        'teacher' => $teacher,
        'day' => $day,
        'time' => $time,
        'lessons' => $lessons,
        'current' => $current,
        'next' => $next,
    ];
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

function assistant_respond_with_teacher(array $teacher, string $normalized): never {
    $teacher_context = assistant_get_teacher_context($teacher);
    $day = $teacher_context['day'];
    $current = $teacher_context['current'];
    $next = $teacher_context['next'];
    $lessons = $teacher_context['lessons'];
    $teacher_name = $teacher['nev'] ?: ($teacher['rovid_nev'] ?? 'Ismeretlen tanár');
    $teacher_code = (string) ($teacher['rovid_nev'] ?? '');
    $actions = [
        ['label' => 'Tanár oldal', 'href' => '/tanar/' . rawurlencode($teacher_code)],
    ];

    if ($day === 0) {
        assistant_response(
            $teacher_name . ' esetén most hétvége van, ezért nincs tanítási helyzet.',
            [],
            $actions
        );
    }

    if ($current !== null) {
        $cards = [[
            'eyebrow' => 'Most',
            'title' => assistant_room_label($current['terem']),
            'meta' => $current['kezdes'] . ' - ' . $current['vegzes'] . ' · ' . $current['osztaly'],
            'detail' => $current['tantargy'],
        ]];

        if ($next !== null) {
            $cards[] = [
                'eyebrow' => 'Következő',
                'title' => assistant_room_label($next['terem']),
                'meta' => $next['kezdes'] . ' - ' . $next['vegzes'] . ' · ' . $next['osztaly'],
                'detail' => $next['tantargy'],
            ];
        }

        assistant_response(
            $teacher_name . ' most a ' . assistant_room_location_label($current['terem']) . ' van.',
            $cards,
            $actions
        );
    }

    if (assistant_has_any($normalized, ['napirend', 'orarend', 'mai'])) {
        if (empty($lessons)) {
            assistant_response(
                $teacher_name . ' számára ma nincs óra beírva.',
                [],
                $actions
            );
        }

        $cards = [];
        foreach (array_slice($lessons, 0, 6) as $lesson) {
            $cards[] = [
                'eyebrow' => ($lesson['ora_sorszam'] ?? '?') . '. óra',
                'title' => assistant_room_label($lesson['terem']),
                'meta' => $lesson['kezdes'] . ' - ' . $lesson['vegzes'] . ' · ' . $lesson['osztaly'],
                'detail' => $lesson['tantargy'],
            ];
        }

        assistant_response(
            $teacher_name . ' mai napirendje ' . assistant_day_name($day) . ' napra.',
            $cards,
            $actions
        );
    }

    if ($next !== null) {
        assistant_response(
            $teacher_name . ' most nem tart órát. A következő órája ' . $next['kezdes'] . '-kor lesz a ' . assistant_room_location_label($next['terem']) . '.',
            [[
                'eyebrow' => 'Következő',
                'title' => assistant_room_label($next['terem']),
                'meta' => $next['kezdes'] . ' - ' . $next['vegzes'] . ' · ' . $next['osztaly'],
                'detail' => $next['tantargy'],
            ]],
            $actions
        );
    }

    if (!empty($lessons)) {
        assistant_response(
            $teacher_name . ' számára ma már nincs több óra.',
            [],
            $actions
        );
    }

    assistant_response(
        $teacher_name . ' számára ma nincs óra beírva.',
        [],
        $actions
    );
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
                'Itt a tanár oldalához segítek. Írhatsz tanár monogramot is, és megmondom, hol van most, mi a következő órája, vagy hogyan néz ki a mai napja.',
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
$teacher_candidates = assistant_extract_teacher_candidates($message);
$teacher = assistant_find_teacher($message, $normalized);
$teacher_lookup_intent = $teacher !== null && (
    $context === 'tanar'
    || assistant_has_any($normalized, ['hol van', 'melyik teremben', 'hol tanit', 'hol tart', 'merre van', 'tanar', 'orarend', 'napirend'])
);

if ($message === '' || assistant_has_any($normalized, ['help', 'segit', 'mit tudsz', 'szia', 'hello'])) {
    assistant_help($context, $context_data);
}

if ($teacher_lookup_intent) {
    assistant_respond_with_teacher($teacher, $normalized);
}

if (
    $teacher === null
    && $context === 'tanar'
    && !empty($teacher_candidates)
    && !assistant_has_any($normalized, ['szabad', 'foglalt', 'terem', 'termek', 'qr', 'kijelzo', 'admin'])
) {
    assistant_response(
        'Nem találtam ilyen tanárt a megadott monogram vagy név alapján.',
        [],
        [
            ['label' => 'Tanár oldal', 'href' => '/tanar'],
        ]
    );
}

if (
    assistant_has_any($normalized, ['tanar kereso', 'tanar oldal'])
    || (assistant_has_any($normalized, ['tanar']) && assistant_has_any($normalized, ['megnyit', 'nyisd', 'oldal', 'kereso']))
) {
    assistant_response(
        'Megnyitom neked a Tanár kereső oldalt.',
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
        'A publikus asszisztens nem ad ki admin belépési pontot.',
        []
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
