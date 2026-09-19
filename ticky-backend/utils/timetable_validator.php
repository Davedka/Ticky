<?php
// utils/timetable_validator.php
// Órarend validáció három szinten.
//
//   A) STRUKTURÁLIS – a feltöltött fájlra (méret, típus, olvashatóság).
//      Ez a timetable_upload.php-ban fut, még a parse előtt.
//   B) TARTALMI     – soronként: kötelező mezők, karakterkészlet, hosszak,
//                     érvényes nap és órasáv.
//   C) ÜZLETI       – az egész órarendre: tanárütközés, teremütközés,
//                     osztályütközés, ismeretlen entitások.
//
// ERROR  = nem publikálható.
// WARNING = publikálható, de az adminnak látnia kell.

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
/**
 * Ütközés-ellenorzés.
 *
 * FONTOS, hogy ezek FIGYELMEZTETÉSEK és nem hibák.
 *
 * Eredetileg hibaként kezeltük, hogy egy tanár egyszerre két teremben van.
 * Az iskola valós órarendjén ez 591 találatot adott, amibol 527 különbözo
 * osztályt érint – és az iskola szerint ezek szándékosak: összevont nyelvi
 * csoportok, muhelybontás, kísért csoportok. Hibaként kezelve az egész
 * órarend publikálhatatlan lett volna.
 *
