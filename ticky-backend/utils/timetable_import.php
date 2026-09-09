require_once __DIR__ . '/xlsx_reader.php';
require_once __DIR__ . '/osztaly.php';

const TICKY_IMPORT_FORMAT_FLAT = 'flat';
const TICKY_IMPORT_FORMAT_GRID = 'grid';

const TICKY_IMPORT_MAX_SUBJECT_LENGTH = 64;
const TICKY_IMPORT_MAX_TEACHER_LENGTH = 32;
const TICKY_IMPORT_MAX_ROOM_LENGTH    = 16;
const TICKY_IMPORT_MAX_CLASS_LENGTH   = 64;

// ─────────────────────────────────────────────────────────────────
// Alap normalizálók
// ─────────────────────────────────────────────────────────────────

function ticky_timetable_normalize_text(string $value): string
{
    // Nem törhető szóköz és keskeny szóköz normál szóközre; sortörés marad,
    // mert a GRID cellák tagolása azon alapul.
    $value = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\r\n", "\r"], [' ', ' ', "\n", "\n"], $value);
    $value = preg_replace('/[^\S\n]+/u', ' ', $value) ?? $value;

    return trim($value);
}

function ticky_timetable_single_line(string $value): string
{
    $value = str_replace("\n", ' ', ticky_timetable_normalize_text($value));

    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

/** Ékezetek nélküli, kisbetűs alak – fejléc- és napfelismeréshez. */
function ticky_timetable_fold(string $value): string
{
    $value = osztaly_lower(ticky_timetable_single_line($value));

    return strtr($value, [
        'á' => 'a', 'é' => 'e', 'í' => 'i',
        'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
}

/** "Hétfő", "Hé", "H", "1" → 1 … "Péntek" → 5. Ismeretlen esetén null. */
function ticky_timetable_day_index(string $value): ?int
{
    static $map = [
        'hetfo' => 1, 'he' => 1, 'h' => 1, '1' => 1,
        'kedd'  => 2, 'ke' => 2, 'k' => 2, '2' => 2,
        'szerda' => 3, 'sze' => 3, 'sz' => 3, '3' => 3,
        'csutortok' => 4, 'cs' => 4, 'cse' => 4, '4' => 4,
        'pentek' => 5, 'pe' => 5, 'p' => 5, '5' => 5,
    ];

    $key = rtrim(ticky_timetable_fold($value), '.');

    return $map[$key] ?? null;
}

/**
 * Idő normalizálás "HH:MM" alakra.
 * Elfogadja: "7:30", "07:30", "7.30", "0730", illetve az Excel numerikus
 * idő/dátum sorozatszámát (pl. 0.3125 = 07:30).
 */
function ticky_timetable_parse_time(string $value): ?string
{
    $value = ticky_timetable_single_line($value);
    if ($value === '') {
        return null;
    }

    // Kettőspontos alak, opcionális másodperccel ("07:30", "07:30:00").
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
        return ticky_timetable_format_time((int) $matches[1], (int) $matches[2]);
    }

    // Pontos alak CSAK pontosan két tizedesjeggyel ("7.30" = 07:30).
    // Ennél több tizedes már Excel időtört (0.3125 = 07:30), nem H.MM.
    if (preg_match('/^(\d{1,2})\.(\d{2})$/', $value, $matches) === 1) {
        return ticky_timetable_format_time((int) $matches[1], (int) $matches[2]);
    }

    if (preg_match('/^(\d{3,4})$/', $value, $matches) === 1) {
        $digits = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
        return ticky_timetable_format_time((int) substr($digits, 0, 2), (int) substr($digits, 2, 2));
    }

    if (is_numeric($value)) {
        $fraction = (float) $value - floor((float) $value);
        $minutes = (int) round($fraction * 1440);
        if ($minutes >= 1440) {
            $minutes = 1439;
        }

        return ticky_timetable_format_time(intdiv($minutes, 60), $minutes % 60);
    }

    return null;
}

function ticky_timetable_format_time(int $hour, int $minute): ?string
{
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return null;
    }

    return sprintf('%02d:%02d', $hour, $minute);
}

/** A 8 tanóra hivatalos sávja – a tanarok_source.php-vel azonos csengetési rend. */
function ticky_timetable_period_slots(): array
{
    return [
        1 => ['07:30', '08:10'],
        2 => ['08:20', '09:05'],
        3 => ['09:15', '10:00'],
        4 => ['10:15', '11:00'],
        5 => ['11:10', '11:55'],
        6 => ['12:05', '12:50'],
        7 => ['12:55', '13:35'],
        8 => ['13:40', '14:20'],
    ];
}

/**
 * Kezdés alapján megkeresi a tanórasávot. Visszaadja a sorszámot és a
 * hivatalos kezdés/végzés párt, hogy az adatbázisban egységes idők legyenek.
 *
 * @return array{ora_sorszam:int,kezdes:string,vegzes:string}|null
 */
function ticky_timetable_match_slot(string $start): ?array
{
    foreach (ticky_timetable_period_slots() as $number => [$slot_start, $slot_end]) {
        if ($slot_start === $start) {
            return ['ora_sorszam' => $number, 'kezdes' => $slot_start, 'vegzes' => $slot_end];
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────
// Token felismerés (terem vs. tanár)
// ─────────────────────────────────────────────────────────────────

/**
 * Teremkód-e a token?
 *
 * Szándékosan szigorú és részben kis-nagybetű érzékeny: a "Kt" (könyvtár)
 * terem, a "KT" viszont tanárkód. Ha ezt kis-nagybetű érzéketlenül néznénk,
 * minden KT-s óra elveszne.
 */
function ticky_timetable_is_room_token(string $token): bool
{
    if ($token === '') {
        return false;
    }

    if ($token === 'Kt') {
        return true;
    }

    // Számjegyet tartalmazó kódok egyértelműek, ott a kis/nagybetű nem számít.
    return preg_match('/^(?:\d{1,4}|[KTM]\d{1,4})$/i', $token) === 1;
}

/** Tanárkód-e a token? Csak betű, 1–8 karakter, nagybetűvel kezdődik. */
function ticky_timetable_is_teacher_token(string $token): bool
{
    if ($token === '' || ticky_timetable_is_room_token($token)) {
        return false;
    }

    return preg_match('/^\p{Lu}[\p{L}]{0,7}$/u', $token) === 1;
}

// ─────────────────────────────────────────────────────────────────
// Formátum felismerés
// ─────────────────────────────────────────────────────────────────

/** Fejlécnév → belső mezőnév. */
function ticky_timetable_header_field(string $header): ?string
{
    static $map = [
        'tanar'      => 'tanar',
        'tanarkod'   => 'tanar',
        'terem'      => 'terem',
        'teremszam'  => 'terem',
        'osztaly'    => 'osztaly',
        'tantargy'   => 'tantargy',
        'targy'      => 'tantargy',
        'nap'        => 'nap',
        'kezdes'     => 'kezdes',
        'kezdet'     => 'kezdes',
        'vege'       => 'vegzes',
        'vegzes'     => 'vegzes',
        'befejezes'  => 'vegzes',
        'csoport'    => 'csoport',
    ];

    $key = preg_replace('/[^a-z0-9]/', '', ticky_timetable_fold($header)) ?? '';

    return $map[$key] ?? null;
}

function ticky_timetable_required_flat_fields(): array
{
    return ['tanar', 'terem', 'osztaly', 'tantargy', 'nap', 'kezdes', 'vegzes'];
}

/**
 * Megkeresi a FLAT fejlécsort egy munkalapon.
 *
 * @return array{sor:int,mezok:array<string,string>}|null
 */
function ticky_timetable_find_flat_header(array $rows): ?array
{
    $checked = 0;
    foreach ($rows as $row_number => $cells) {
        if (++$checked > 10) {
            break; // a fejléc az első pár sorban van, különben nem FLAT
        }

        $fields = [];
        foreach ($cells as $column => $value) {
            $field = ticky_timetable_header_field((string) $value);
            if ($field !== null && !isset($fields[$field])) {
                $fields[$field] = $column;
            }
        }

        $missing = array_diff(ticky_timetable_required_flat_fields(), array_keys($fields));
        if ($missing === []) {
            return ['sor' => $row_number, 'mezok' => $fields];
        }
    }

    return null;
}

/**
 * GRID munkalap fejléce: A1 = "9.a – 1. csoport", A2 = "Nap".
 *
 * @return array{osztaly:string,csoport:?int}|null
 */
function ticky_timetable_parse_grid_title(string $title, string $sheet_name): ?array
{
    $title = ticky_timetable_single_line($title);

    if ($title !== '' && preg_match('/^(.+?)\s*[–—-]\s*(\d+)\.\s*csoport$/u', $title, $matches) === 1) {
        return [
            'osztaly' => ticky_timetable_normalize_class(trim($matches[1])),
            'csoport' => (int) $matches[2],
        ];
    }

    // Tartalék: a munkalap nevéből ("9.a 1.cs").
    $name = ticky_timetable_single_line($sheet_name);
    if (preg_match('/^(.+?)\s+(\d+)\.\s*cs\.?$/ui', $name, $matches) === 1) {
        return [
            'osztaly' => ticky_timetable_normalize_class(trim($matches[1])),
            'csoport' => (int) $matches[2],
        ];
    }

    return null;
}

/**
 * Az Excel munkalapnév nem tartalmazhat "/" jelet, ezért az iskola azt
 * aláhúzásra cseréli ("1_9 Déri"). Csak a "szám_szám" előtagot állítjuk
 * vissza – a "13.c_du" és "HT_13.ap" kódokban az aláhúzás valódi.
 */
function ticky_timetable_normalize_class(string $value): string
{
    $value = ticky_timetable_single_line($value);

    return preg_replace('/^(\d+)_(\d+)\b/', '$1/$2', $value) ?? $value;
}

/** 'flat' | 'grid' | null (felismerhetetlen) */
function ticky_timetable_detect_format(array $workbook): ?string
{
    foreach ($workbook['munkalapok'] as $sheet) {
        if (ticky_timetable_find_flat_header($sheet['sorok']) !== null) {
            return TICKY_IMPORT_FORMAT_FLAT;
        }
    }

    foreach ($workbook['munkalapok'] as $sheet) {
        $title = (string) ($sheet['sorok'][1]['A'] ?? '');
        if (ticky_timetable_parse_grid_title($title, (string) $sheet['nev']) !== null) {
            return TICKY_IMPORT_FORMAT_GRID;
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────
// Hiba/figyelmeztetés gyűjtő
// ─────────────────────────────────────────────────────────────────

function ticky_timetable_issue(string $level, string $code, string $where, string $message): array
{
    return [
        'szint'  => $level,
        'kod'    => $code,
        'hely'   => $where,
        'uzenet' => $message,
    ];
}

// ─────────────────────────────────────────────────────────────────
// FLAT parser
// ─────────────────────────────────────────────────────────────────

function ticky_timetable_parse_flat(array $workbook): array
{
    $lessons = [];
    $issues = [];

    foreach ($workbook['munkalapok'] as $sheet) {
        $header = ticky_timetable_find_flat_header($sheet['sorok']);
        if ($header === null) {
            continue;
        }

        $sheet_name = (string) $sheet['nev'];
        foreach ($sheet['sorok'] as $row_number => $cells) {
            if ($row_number <= $header['sor']) {
                continue;
            }

            $where = $sheet_name . '!' . $row_number;
            $values = [];
            foreach ($header['mezok'] as $field => $column) {
                $values[$field] = ticky_timetable_single_line((string) ($cells[$column] ?? ''));
            }

            // Teljesen üres sor: az Excel gyakran hagy maga után ilyet.
            if (implode('', $values) === '') {
                continue;
            }

            $lesson = ticky_timetable_build_lesson($values, $where, $issues);
            if ($lesson !== null) {
                $lessons[] = $lesson;
            }
        }
    }

    return ['orak' => $lessons, 'problemak' => $issues];
}

/**
 * Egy nyers mezőhalmazból normalizált óra-sor. A hiányzó/érvénytelen
 * alapmezők itt hibát adnak, mert nélkülük a sor értelmezhetetlen.
 */
function ticky_timetable_build_lesson(array $values, string $where, array &$issues): ?array
{
    $day = ticky_timetable_day_index((string) ($values['nap'] ?? ''));
    if ($day === null) {
        $issues[] = ticky_timetable_issue(
            'error',
            'ISMERETLEN_NAP',
            $where,
            'Ismeretlen nap: "' . (string) ($values['nap'] ?? '') . '" (Hétfő–Péntek várható)'
        );
        return null;
    }

    $start = ticky_timetable_parse_time((string) ($values['kezdes'] ?? ''));
    $end   = ticky_timetable_parse_time((string) ($values['vegzes'] ?? ''));

    if ($start === null || $end === null) {
        $issues[] = ticky_timetable_issue('error', 'ERVENYTELEN_IDO', $where, 'Nem értelmezhető kezdés vagy vége időpont.');
        return null;
    }

    if ($end <= $start) {
        $issues[] = ticky_timetable_issue(
            'error',
            'FORDITOTT_IDO',
            $where,
            'A befejezés (' . $end . ') nem későbbi, mint a kezdés (' . $start . ').'
        );
        return null;
    }

    $slot = ticky_timetable_match_slot($start);
    if ($slot === null) {
        $issues[] = ticky_timetable_issue(
            'error',
            'ISMERETLEN_ORASAV',
            $where,
            'A(z) ' . $start . ' kezdés nem szerepel a csengetési rendben.'
        );
        return null;
    }

    $teacher  = ticky_timetable_single_line((string) ($values['tanar'] ?? ''));
    $room     = ticky_timetable_single_line((string) ($values['terem'] ?? ''));
    $class    = ticky_timetable_normalize_class((string) ($values['osztaly'] ?? ''));
    $subject  = ticky_timetable_single_line((string) ($values['tantargy'] ?? ''));
    $group    = ticky_timetable_single_line((string) ($values['csoport'] ?? ''));

    if ($teacher === '') {
        $issues[] = ticky_timetable_issue('error', 'HIANYZO_TANAR', $where, 'Hiányzó tanárkód.');
        return null;
    }
    if ($room === '') {
        $issues[] = ticky_timetable_issue('error', 'HIANYZO_TEREM', $where, 'Hiányzó teremkód.');
        return null;
    }
    if ($class === '') {
        $issues[] = ticky_timetable_issue('error', 'HIANYZO_OSZTALY', $where, 'Hiányzó osztálykód.');
        return null;
    }

    return [
        'tanar'       => $teacher,
        'terem'       => $room,
        'osztaly'     => $class,
        'tantargy'    => $subject,
        'csoport'     => ($group !== '' && ctype_digit($group)) ? (int) $group : null,
        'het_napja'   => $day,
        'ora_sorszam' => $slot['ora_sorszam'],
        'kezdes'      => $slot['kezdes'],
        'vegzes'      => $slot['vegzes'],
        'forras'      => $where,
    ];
}

// ─────────────────────────────────────────────────────────────────
// GRID parser
// ─────────────────────────────────────────────────────────────────

function ticky_timetable_parse_grid(array $workbook): array
{
    $lessons = [];
    $issues = [];
    $groups_by_class = [];

    foreach ($workbook['munkalapok'] as $sheet) {
        $sheet_name = (string) $sheet['nev'];
        $rows = $sheet['sorok'];
        $title = ticky_timetable_parse_grid_title((string) ($rows[1]['A'] ?? ''), $sheet_name);

        if ($title === null) {
            $issues[] = ticky_timetable_issue(
                'warning',
                'ATUGROTT_MUNKALAP',
                $sheet_name,
                'A munkalap fejléce nem "osztály – N. csoport" alakú, ezért kimaradt az importból.'
            );
            continue;
        }

        $periods = ticky_timetable_grid_periods($rows, $sheet_name, $issues);
        if ($periods === []) {
            continue;
        }

        $groups_by_class[$title['osztaly']][$title['csoport']] = true;

        foreach ($rows as $row_number => $cells) {
            if ($row_number <= 2) {
                continue;
            }

            $day = ticky_timetable_day_index((string) ($cells['A'] ?? ''));
            if ($day === null) {
                continue; // láblécek, üres sorok
            }

            foreach ($periods as $column => $slot) {
                $raw = ticky_timetable_normalize_text((string) ($cells[$column] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $where = $sheet_name . '!' . $column . $row_number;
                foreach (ticky_timetable_parse_grid_cell($raw, $where, $issues) as $part) {
                    $lessons[] = [
                        'tanar'       => $part['tanar'],
                        'terem'       => $part['terem'],
                        'osztaly'     => $title['osztaly'],
                        'tantargy'    => $part['tantargy'],
                        'csoport'     => $title['csoport'],
                        'het_napja'   => $day,
                        'ora_sorszam' => $slot['ora_sorszam'],
                        'kezdes'      => $slot['kezdes'],
                        'vegzes'      => $slot['vegzes'],
                        'forras'      => $where,
                    ];
                }
            }
        }
    }

    // Osztályonként hány csoportlap volt – a deduplikáció ebből tudja
    // eldönteni, hogy egy óra az egész osztályé-e.
    $group_counts = array_map('count', $groups_by_class);

    return [
        'orak'              => ticky_timetable_dedupe_grid_rows($lessons, $group_counts),
        'problemak'         => $issues,
        'osztaly_csoportok' => $group_counts,
    ];
}

/**
 * A 2. sor fejlécéből ("1.\n7:30 - 8:10") oszloponkénti tanórasáv.
 *
 * @return array<string,array{ora_sorszam:int,kezdes:string,vegzes:string}>
 */
function ticky_timetable_grid_periods(array $rows, string $sheet_name, array &$issues): array
{
    $header = $rows[2] ?? [];
    $periods = [];

    foreach ($header as $column => $value) {
        if ($column === 'A') {
            continue;
        }

        $text = ticky_timetable_normalize_text((string) $value);
        if ($text === '') {
            continue;
        }

        if (preg_match('/(\d{1,2}[:.]\d{2})\s*[-–—]\s*(\d{1,2}[:.]\d{2})/u', $text, $matches) !== 1) {
            continue;
        }

        $start = ticky_timetable_parse_time($matches[1]);
        if ($start === null) {
            continue;
        }

        $slot = ticky_timetable_match_slot($start);
        if ($slot === null) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ISMERETLEN_ORASAV',
                $sheet_name . '!' . $column . '2',
                'A(z) ' . $start . ' kezdés nem szerepel a csengetési rendben.'
            );
            continue;
        }

        $periods[$column] = $slot;
    }

    if ($periods === []) {
        $issues[] = ticky_timetable_issue(
            'error',
            'HIANYZO_ORASAV_FEJLEC',
            $sheet_name . '!2',
            'A munkalap 2. sorában nincs értelmezhető óraidő fejléc.'
        );
    }

    return $periods;
}

/**
 * Egy GRID cella feldolgozása.
 *
 * A cella utolsó nem üres sora tartalmazza a termet és a tanárt, az azt
 * megelőző sorok a (tördelt) tantárgynevet. Ha a terem/tanár rész nem
 * bontható egyértelműen, hibát adunk – nem tippelünk.
 *
 * Szándékosan NEM próbáljuk az utolsó két sort összevonva is értelmezni:
 * a valós exportban ez nem hiányzó adatot pótol, hanem a szomszéd cellából
 * átcsúszott töredékekből ("A" + "1") gyárt nem létező órát. Inkább hibát
 * jelentünk, és az admin javítja a forrást.
 *
 * @return array<int,array{terem:string,tanar:string,tantargy:string}>
 */
function ticky_timetable_parse_grid_cell(string $raw, string $where, array &$issues): array
{
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $raw)),
        static fn(string $line): bool => $line !== ''
    ));

    if (count($lines) < 2) {
        $issues[] = ticky_timetable_issue(
            'error',
            'TOREDEK_CELLA',
            $where,
            'A cella nem tartalmaz "tantárgy + terem tanár" párost: "' . ticky_timetable_single_line($raw) . '"'
        );
        return [];
    }

    $assignment = array_pop($lines);
    $subject = ticky_timetable_clean_subject($lines);

    if ($subject === '') {
        $issues[] = ticky_timetable_issue('warning', 'HIANYZO_TANTARGY', $where, 'Nem sikerült tantárgynevet kiolvasni.');
    } elseif (ticky_timetable_subject_looks_garbled($subject)) {
        $issues[] = ticky_timetable_issue(
            'warning',
            'GYANUS_TANTARGY',
            $where,
            'A tantárgynév valószínűleg összecsúszott szöveg: "' . $subject . '"'
        );
    }

    $rooms = [];
    $teachers = [];
    foreach (preg_split('#[\s/]+#u', $assignment) ?: [] as $token) {
        $token = trim($token, " \t.,;");
        if ($token === '') {
            continue;
        }

        if (ticky_timetable_is_room_token($token)) {
            $rooms[] = $token;
        } elseif (ticky_timetable_is_teacher_token($token)) {
            $teachers[] = $token;
        } else {
            $issues[] = ticky_timetable_issue(
                'error',
                'ERTELMEZHETETLEN_TOKEN',
                $where,
                'Nem eldönthető, hogy terem vagy tanár: "' . $token . '" (teljes cella: "'
                    . ticky_timetable_single_line($raw) . '")'
            );
            return [];
        }
    }

    if ($rooms === [] || $teachers === []) {
        $issues[] = ticky_timetable_issue(
            'error',
            'HIANYZO_TEREM_VAGY_TANAR',
            $where,
            'A cellából hiányzik a terem vagy a tanár: "' . ticky_timetable_single_line($raw) . '"'
        );
        return [];
    }

    return ticky_timetable_pair_rooms_and_teachers($rooms, $teachers, $subject, $raw, $where, $issues);
}

/**
 * Terem- és tanárlista párosítása.
 *  - azonos elemszám  → sorrendben egymáshoz (nyelvi csoportbontás)
 *  - egy terem, több tanár → mindenki ugyanabban a teremben
 *  - egy tanár, több terem → a tanár mindegyik teremben szerepel
 *  - minden más → nem egyértelmű, hiba
 */
function ticky_timetable_pair_rooms_and_teachers(
    array $rooms,
    array $teachers,
    string $subject,
    string $raw,
    string $where,
    array &$issues
): array {
    $room_count = count($rooms);
    $teacher_count = count($teachers);
    $pairs = [];

    if ($room_count === $teacher_count) {
        for ($index = 0; $index < $room_count; $index++) {
            $pairs[] = ['terem' => $rooms[$index], 'tanar' => $teachers[$index], 'tantargy' => $subject];
        }
    } elseif ($room_count === 1) {
        foreach ($teachers as $teacher) {
            $pairs[] = ['terem' => $rooms[0], 'tanar' => $teacher, 'tantargy' => $subject];
        }
    } elseif ($teacher_count === 1) {
        foreach ($rooms as $room) {
            $pairs[] = ['terem' => $room, 'tanar' => $teachers[0], 'tantargy' => $subject];
        }
    } else {
        $issues[] = ticky_timetable_issue(
            'error',
            'PAROSITHATATLAN_CELLA',
            $where,
            $room_count . ' terem és ' . $teacher_count . ' tanár nem párosítható egyértelműen: "'
                . ticky_timetable_single_line($raw) . '"'
        );
    }

    return $pairs;
}

/**
 * Tantárgynév összerakása a cella tördelt sorai közül.
 *
 * Az export a csoport-annotációt ("-1. csoport", "-r. csoport") külön sorként
 * a tantárgynév KÖZEPÉBE szúrja be, a nevet pedig szó közben tördeli:
 *
 *     "kommu" / "-rt1. csoport" / "nikáció"  →  "kommunikáció"
 *
 * Ezért előbb az annotációs sorokat dobjuk el, és a maradékot elválasztó
 * nélkül fűzzük össze – az Excel sortörés nem tesz be szóközt.
 *
 * @param array<int,string> $lines a cella sorai az utolsó (terem+tanár) nélkül
 */
function ticky_timetable_clean_subject(array $lines): string
{
    // Soronként külön nem lehet takarítani: az annotáció átnyúlhat két sorra
    // ("-ir. csoport/-szf." + "csoport"). Ezért egyben dolgozunk, a sortöréseket
    // megtartva elválasztóként, és csak a végén tüntetjük el őket.
    $value = implode("\n", array_map('ticky_timetable_single_line', $lines));
    $value = ticky_timetable_strip_group_annotation($value);

    // A sortörés az Excel szótördelése, nem szóhatár → nyom nélkül tűnik el.
    $value = str_replace("\n", '', $value);
    $value = preg_replace('/^[\s\/-]+|[\s\/-]+$/u', '', $value) ?? $value;
    $value = trim(preg_replace('/[^\S]+/u', ' ', $value) ?? $value);

    return mb_substr($value, 0, TICKY_IMPORT_MAX_SUBJECT_LENGTH, 'UTF-8');
}

/**
 * Eltávolítja a "-1. csoport" / "-r. csoport" / "csoport" mintákat.
 * A \s* miatt a minta sortörésen át is illeszkedik.
 */
function ticky_timetable_strip_group_annotation(string $value): string
{
    return preg_replace('/-?\s*\p{L}*\d*\.?\s*csoport\b/ui', '', $value) ?? $value;
}

/**
 * Összecsúszott tantárgynév gyanúja: a hibás exportokban a szomszédos cellák
 * szövege egybeolvad, amit "/" jelek és feltűnő hossz árul el.
 */
function ticky_timetable_subject_looks_garbled(string $subject): bool
{
    return str_contains($subject, '/') || mb_strlen($subject, 'UTF-8') > 32;
}

/**
 * A csoportonkénti munkalapokból származó duplikátumok összevonása.
 *
 * Ha ugyanaz az óra minden csoport lapján szerepel, az egész osztályé:
 * egyetlen sor lesz belőle csoport nélkül. Enélkül minden osztályszintű óra
 * ütközésnek látszana önmagával, és feleslegesen duplázódna az adatbázisban.
 */
function ticky_timetable_dedupe_grid_rows(array $lessons, array $groups_by_class): array
{
    $buckets = [];

    foreach ($lessons as $lesson) {
        $signature = implode('|', [
            $lesson['osztaly'],
            $lesson['het_napja'],
            $lesson['kezdes'],
            $lesson['terem'],
            $lesson['tanar'],
            $lesson['tantargy'],
        ]);

        if (!isset($buckets[$signature])) {
            $buckets[$signature] = ['ora' => $lesson, 'csoportok' => []];
        }

        if ($lesson['csoport'] !== null) {
            $buckets[$signature]['csoportok'][(int) $lesson['csoport']] = true;
        }
    }

    $result = [];
    foreach ($buckets as $bucket) {
        $lesson = $bucket['ora'];
        $class_groups = (int) ($groups_by_class[$lesson['osztaly']] ?? 1);
        $present_groups = count($bucket['csoportok']);

        // Egyetlen csoportlapos osztálynál nincs valódi bontás, és a minden
        // csoportnál megjelenő óra is az egész osztályé → csoport = null.
        $lesson['csoport'] = ($class_groups <= 1 || $present_groups >= $class_groups)
            ? null
            : (int) array_key_first($bucket['csoportok']);

        $result[] = $lesson;
    }

    return $result;
}

// ─────────────────────────────────────────────────────────────────
// Belépési pont
// ─────────────────────────────────────────────────────────────────

/**
 * @return array{formatum:string,orak:array,problemak:array,osztaly_csoportok:array}
 * @throws TickyXlsxException érvénytelen vagy olvashatatlan fájl esetén
 */
function ticky_timetable_parse_file(string $path): array
{
    $workbook = ticky_xlsx_open($path);
    $format = ticky_timetable_detect_format($workbook);

    if ($format === null) {
        throw new TickyXlsxException(
            'Ismeretlen munkafüzet-szerkezet. Várt formátumok: '
            . 'fejléces tábla (Tanár, Terem, Osztály, Tantárgy, Nap, Kezdés, Vége), '
            . 'vagy csoportonkénti órarend munkalapok ("9.a – 1. csoport").'
        );
    }

    $parsed = $format === TICKY_IMPORT_FORMAT_FLAT
        ? ticky_timetable_parse_flat($workbook)
        : ticky_timetable_parse_grid($workbook);

    return [
        'formatum'          => $format,
        'orak'              => $parsed['orak'],
        'problemak'         => $parsed['problemak'],
        'osztaly_csoportok' => $parsed['osztaly_csoportok'] ?? [],
    ];
}
