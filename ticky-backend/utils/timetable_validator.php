<?php


require_once __DIR__ . '/timetable_import.php';

const TICKY_VALIDATOR_MAX_REPORTED = 200; // riportban visszaadott problémák felső határa

// ─────────────────────────────────────────────────────────────────
// B) Tartalmi validáció
// ─────────────────────────────────────────────────────────────────

/** Tanárkód: betű, szám, pont, kötőjel; ékezet megengedett (SZiÁ, PÁI, MÉ). */
function ticky_validator_teacher_code_is_valid(string $code): bool
{
    return preg_match('/^[\p{L}\p{N}.\-]{1,' . TICKY_IMPORT_MAX_TEACHER_LENGTH . '}$/u', $code) === 1;
}

/** Teremkód: betű és szám (204, K101, m17, T1, Kt). */
function ticky_validator_room_code_is_valid(string $code): bool
{
    return preg_match('/^[\p{L}\p{N}.\-]{1,' . TICKY_IMPORT_MAX_ROOM_LENGTH . '}$/u', $code) === 1;
}

/** Osztálykód: legyen benne betű, hogy ne keveredjen a teremszámokkal. */
function ticky_validator_class_code_is_valid(string $code): bool
{
    if (preg_match('/^[\p{L}\p{N}\s._\/-]{1,' . TICKY_IMPORT_MAX_CLASS_LENGTH . '}$/u', $code) !== 1) {
        return false;
    }

    return preg_match('/[\p{L}]/u', $code) === 1;
}

/**
 * Soronkénti tartalmi ellenőrzés.
 *
 * @return array<int,array> problémák
 */
function ticky_validator_check_rows(array $lessons): array
{
    $issues = [];
    $valid_days = [1, 2, 3, 4, 5];
    $slots = ticky_timetable_period_slots();

    foreach ($lessons as $lesson) {
        $where = (string) ($lesson['forras'] ?? '?');

        if (!ticky_validator_teacher_code_is_valid((string) $lesson['tanar'])) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ERVENYTELEN_TANARKOD',
                $where,
                'Érvénytelen tanárkód: "' . (string) $lesson['tanar'] . '"'
            );
        }

        if (!ticky_validator_room_code_is_valid((string) $lesson['terem'])) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ERVENYTELEN_TEREMKOD',
                $where,
                'Érvénytelen teremkód: "' . (string) $lesson['terem'] . '"'
            );
        }

        if (!ticky_validator_class_code_is_valid((string) $lesson['osztaly'])) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ERVENYTELEN_OSZTALYKOD',
                $where,
                'Érvénytelen osztálykód: "' . (string) $lesson['osztaly'] . '"'
            );
        }

        if (!in_array((int) $lesson['het_napja'], $valid_days, true)) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ISMERETLEN_NAP',
                $where,
                'A nap sorszáma nem 1–5 közötti: ' . (string) $lesson['het_napja']
            );
        }

        $number = (int) $lesson['ora_sorszam'];
        if (!isset($slots[$number])) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ISMERETLEN_ORASAV',
                $where,
                'Ismeretlen óra sorszám: ' . $number
            );
            continue;
        }

        [$slot_start, $slot_end] = $slots[$number];
        if ((string) $lesson['kezdes'] !== $slot_start || (string) $lesson['vegzes'] !== $slot_end) {
            $issues[] = ticky_timetable_issue(
                'error',
                'ORASAV_ELTERES',
                $where,
                'A(z) ' . $number . '. óra ideje nem a csengetési rend szerinti ('
                    . $slot_start . '–' . $slot_end . ').'
            );
        }

        if ((string) ($lesson['tantargy'] ?? '') === '') {
            $issues[] = ticky_timetable_issue('warning', 'HIANYZO_TANTARGY', $where, 'Nincs tantárgy megadva.');
        }
    }

    return $issues;
}

// ─────────────────────────────────────────────────────────────────
// C) Üzleti validáció
// ─────────────────────────────────────────────────────────────────

/**
 * Ütközések keresése.
 *
 *  - Tanárütközés (ERROR): egy tanár ugyanabban a sávban két különböző teremben.
 *    Ha a terem azonos, az összevont osztály (pl. 12.a+12.b együtt), nem hiba.
 *  - Teremütközés (ERROR): egy teremben ugyanabban a sávban két különböző tanár.
 *  - Osztályütközés (WARNING): egy osztály ugyanazon csoportja két teremben.
 *    Ez lehet valós nyelvi alcsoport-bontás is, ezért csak figyelmeztetés.
 *
 * @return array<int,array>
 */
function ticky_validator_check_conflicts(array $lessons): array
{
    $issues = [];
    $by_teacher = [];
    $by_room = [];
    $by_class = [];

    foreach ($lessons as $lesson) {
        $slot = ((int) $lesson['het_napja']) . '|' . ((string) $lesson['kezdes']);

        $by_teacher[(string) $lesson['tanar']][$slot][(string) $lesson['terem']] = $lesson;
        $by_room[(string) $lesson['terem']][$slot][(string) $lesson['tanar']] = $lesson;

        $group = $lesson['csoport'] === null ? '-' : (string) $lesson['csoport'];
        $by_class[((string) $lesson['osztaly']) . '#' . $group][$slot][(string) $lesson['terem']] = $lesson;
    }

    foreach ($by_teacher as $teacher => $slots) {
        foreach ($slots as $slot => $rooms) {
            if (count($rooms) < 2) {
                continue;
            }

            $issues[] = ticky_validator_conflict_issue(
                'error',
                'TANAR_UTKOZES',
                $slot,
                $teacher . ' tanár egyszerre ' . count($rooms) . ' teremben: ' . implode(', ', array_keys($rooms)),
                $rooms
            );
        }
    }

    foreach ($by_room as $room => $slots) {
        foreach ($slots as $slot => $teachers) {
            if (count($teachers) < 2) {
                continue;
            }

            $issues[] = ticky_validator_conflict_issue(
                'error',
                'TEREM_UTKOZES',
                $slot,
                $room . ' teremben egyszerre ' . count($teachers) . ' tanár: ' . implode(', ', array_keys($teachers)),
                $teachers
            );
        }
    }

    foreach ($by_class as $class_key => $slots) {
        foreach ($slots as $slot => $rooms) {
            if (count($rooms) < 2) {
                continue;
            }

            $issues[] = ticky_validator_conflict_issue(
                'warning',
                'OSZTALY_UTKOZES',
                $slot,
                str_replace('#-', '', $class_key) . ' egyszerre ' . count($rooms)
                    . ' teremben: ' . implode(', ', array_keys($rooms)),
                $rooms
            );
        }
    }

    return $issues;
}

function ticky_validator_conflict_issue(
    string $level,
    string $code,
    string $slot,
    string $message,
    array $lessons
): array {
    [$day, $start] = explode('|', $slot, 2);
    $day_names = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek'];

    $sources = [];
    foreach ($lessons as $lesson) {
        $sources[] = (string) ($lesson['forras'] ?? '?');
    }

    return ticky_timetable_issue(
        $level,
        $code,
        implode(' + ', array_slice($sources, 0, 4)),
        ($day_names[(int) $day] ?? $day) . ' ' . $start . ' – ' . $message
    );
}

/**
 * Ismeretlen entitások figyelmeztetései: az importban szereplő, de az
 * adatbázisban még nem létező tanár/terem/osztály. Ezek NEM hibák – egy új
 * kolléga miatt nem szabad megbuknia az egész importnak.
 *
 * @param array<int,string> $known_teachers
 * @param array<int,string> $known_rooms
 * @param array<int,string> $known_classes
 * @return array<int,array>
 */
function ticky_validator_check_new_entities(
    array $lessons,
    array $known_teachers,
    array $known_rooms,
    array $known_classes
): array {
    $lookup = static fn(array $values): array => array_flip(array_map('osztaly_lower', $values));

    $known = [
        'tanar'   => $lookup($known_teachers),
        'terem'   => $lookup($known_rooms),
        'osztaly' => $lookup($known_classes),
    ];

    $labels = ['tanar' => 'tanár', 'terem' => 'terem', 'osztaly' => 'osztály'];
    $codes  = ['tanar' => 'UJ_TANAR', 'terem' => 'UJ_TEREM', 'osztaly' => 'UJ_OSZTALY'];

    $seen = [];
    $issues = [];

    foreach ($lessons as $lesson) {
        foreach ($labels as $field => $label) {
            $value = (string) $lesson[$field];
            $key = osztaly_lower($value);

            if ($value === '' || isset($known[$field][$key]) || isset($seen[$field][$key])) {
                continue;
            }

            $seen[$field][$key] = true;
            $issues[] = ticky_timetable_issue(
                'warning',
                $codes[$field],
                (string) ($lesson['forras'] ?? '?'),
                'Új ' . $label . ' az adatbázishoz képest: "' . $value . '"'
            );
        }
    }

    return $issues;
}

// ─────────────────────────────────────────────────────────────────
// Összegzés
// ─────────────────────────────────────────────────────────────────

/**
 * Egyesíti a beolvasási és validációs problémákat, és statisztikát készít.
 *
 * @return array{
 *   ervenyes:bool,
 *   hibak:array,
 *   figyelmeztetesek:array,
 *   hibak_szama:int,
 *   figyelmeztetesek_szama:int,
 *   statisztika:array
 * }
 */
function ticky_validator_summarize(array $lessons, array $issues): array
{
    $errors = [];
    $warnings = [];

    foreach ($issues as $issue) {
        if (($issue['szint'] ?? '') === 'error') {
            $errors[] = $issue;
        } else {
            $warnings[] = $issue;
        }
    }

    $teachers = $rooms = $classes = $subjects = [];
    $per_day = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

    foreach ($lessons as $lesson) {
        $teachers[(string) $lesson['tanar']] = true;
        $rooms[(string) $lesson['terem']] = true;
        $classes[(string) $lesson['osztaly']] = true;
        if ((string) ($lesson['tantargy'] ?? '') !== '') {
            $subjects[(string) $lesson['tantargy']] = true;
        }
        $day = (int) $lesson['het_napja'];
        if (isset($per_day[$day])) {
            $per_day[$day]++;
        }
    }

    return [
        'ervenyes'               => $errors === [],
        'hibak'                  => array_slice($errors, 0, TICKY_VALIDATOR_MAX_REPORTED),
        'figyelmeztetesek'       => array_slice($warnings, 0, TICKY_VALIDATOR_MAX_REPORTED),
        'hibak_szama'            => count($errors),
        'figyelmeztetesek_szama' => count($warnings),
        'statisztika'            => [
            'orak'          => count($lessons),
            'tanarok'       => count($teachers),
            'termek'        => count($rooms),
            'osztalyok'     => count($classes),
            'tantargyak'    => count($subjects),
            'orak_naponta'  => $per_day,
        ],
    ];
}

/**
 * Teljes validáció egy már beolvasott importon.
 *
 * @param array $parsed a ticky_timetable_parse_file() eredménye
 */
function ticky_validator_run(
    array $parsed,
    array $known_teachers = [],
    array $known_rooms = [],
    array $known_classes = []
): array {
    $lessons = $parsed['orak'] ?? [];

    $issues = array_merge(
        $parsed['problemak'] ?? [],
        ticky_validator_check_rows($lessons),
        ticky_validator_check_conflicts($lessons),
        ticky_validator_check_new_entities($lessons, $known_teachers, $known_rooms, $known_classes)
    );

    $summary = ticky_validator_summarize($lessons, $issues);
    $summary['formatum'] = (string) ($parsed['formatum'] ?? '');

    return $summary;
}

// ─────────────────────────────────────────────────────────────────
// A) Strukturális validáció – a feltöltött fájlra, még parse előtt
// ─────────────────────────────────────────────────────────────────

/**
 * A PHP feltöltési hibakódjaihoz tartozó magyar üzenetek.
 */
function ticky_validator_upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A fájl nagyobb a megengedettnél.',
        UPLOAD_ERR_PARTIAL                        => 'A fájl csak részben töltődött fel.',
        UPLOAD_ERR_NO_FILE                        => 'Nem érkezett fájl.',
        UPLOAD_ERR_NO_TMP_DIR                     => 'Hiányzik a szerver ideiglenes könyvtára.',
        UPLOAD_ERR_CANT_WRITE                     => 'A szerver nem tudta lemezre írni a fájlt.',
        UPLOAD_ERR_EXTENSION                      => 'Egy PHP kiterjesztés megszakította a feltöltést.',
        default                                   => 'Ismeretlen feltöltési hiba (' . $code . ').',
    };
}

/**
 * Feltöltött fájl strukturális ellenőrzése.
 *
 * @param array|null $file a $_FILES egy eleme
 * @return array{ok:bool,uzenet:?string,kod:?string,fajlnev:string,meret:int,sha256:string}
 */
function ticky_validator_check_upload(?array $file): array
{
    $failure = static fn(string $code, string $message): array => [
        'ok' => false, 'kod' => $code, 'uzenet' => $message,
        'fajlnev' => '', 'meret' => 0, 'sha256' => '',
    ];

    if (!is_array($file) || !isset($file['tmp_name'])) {
        return $failure('NINCS_FAJL', 'Nem érkezett fájl. Válassz ki egy .xlsx órarendet.');
    }

    $error_code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error_code !== UPLOAD_ERR_OK) {
        return $failure('FELTOLTESI_HIBA', ticky_validator_upload_error_text($error_code));
    }

    $tmp_path = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp_path)) {
        return $failure('NEM_FELTOLTOTT_FAJL', 'A megadott útvonal nem feltöltött fájl.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $failure('URES_FAJL', 'A feltöltött fájl üres.');
    }
    if ($size > TICKY_XLSX_MAX_FILE_BYTES) {
        return $failure(
            'TUL_NAGY_FAJL',
            'A fájl mérete ' . round($size / 1048576, 1) . ' MB, a megengedett legfeljebb '
                . (int) (TICKY_XLSX_MAX_FILE_BYTES / 1048576) . ' MB.'
        );
    }

    $original_name = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
        return $failure('ERVENYTELEN_KITERJESZTES', 'Csak .xlsx vagy .xlsm fájl tölthető fel (kapott: .' . $extension . ').');
    }

    // Az XLSX ZIP: a "PK\x03\x04" aláírás nélkül nem érdemes tovább menni.
    $handle = fopen($tmp_path, 'rb');
    $magic = $handle !== false ? (string) fread($handle, 4) : '';
    if ($handle !== false) {
        fclose($handle);
    }

    if ($magic !== "PK\x03\x04") {
        return $failure('NEM_XLSX', 'A fájl tartalma nem XLSX (hiányzik a ZIP aláírás). Mentsd újra Excelben.');
    }

    return [
        'ok'      => true,
        'kod'     => null,
        'uzenet'  => null,
        'fajlnev' => ticky_validator_safe_file_name($original_name),
        'meret'   => $size,
        'sha256'  => (string) hash_file('sha256', $tmp_path),
    ];
}

/** A fájlnevet naplózzuk, ezért megtisztítjuk az útvonal- és vezérlőkarakterektől. */
function ticky_validator_safe_file_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

    return mb_substr(trim($name), 0, 150, 'UTF-8');
}
