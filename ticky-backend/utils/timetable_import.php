<?php
// utils/timetable_import.php
// Órarend import: beolvasás → normalizálás.
//
// Két bemeneti formátumot ismer:
//
//   FLAT  – "Ticky Import Format v1": egy munkalap, fejléces tábla
//           | Tanár | Terem | Osztály | Tantárgy | Nap | Kezdés | Vége | (Csoport)
//           Ez az ajánlott formátum, mert minden mező explicit.
//
//   GRID  – az iskolai "órarend csoportonként" export: munkalaponként egy
//           (osztály, csoport) pár, soronként egy nap, oszloponként egy tanóra.
//           A cellák szövege tördelt ("tantárgy\nterem tanár"), ezért itt
//           lényegesen több a bizonytalanság – amit nem tudunk biztosan
//           értelmezni, azt hibaként jelentjük, NEM találgatunk.
//
// A modul nem ír adatbázisba és nem validál üzleti szabályt: csak sorokat és
// beolvasási problémákat ad vissza. A validálás a timetable_validator.php-ban van.

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
 * Egy [kezdés, vége] tartomány kibontása tanórasávokra.
 *
 * Egy sor átfoghat több tanórát (pl. 12:05–13:35 = 6. és 7. óra). Ilyenkor
 * óránként külön sort kell készíteni, különben a köztes órák elvesznének az
 * órarendből – a megjelenítés a merge_consecutive_orak()-kal úgyis
