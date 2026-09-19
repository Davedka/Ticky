<?php
// tests/php/timetable_import.test.php
// Az órarend import olvasó / normalizáló / validáló rétegének tesztjei.
// Futtatás: php tests/php/timetable_import.test.php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/ticky-backend/utils/xlsx_writer.php';
require_once $root . '/ticky-backend/utils/timetable_validator.php';

$tests_run = 0;
$failures = [];

function test(string $name, callable $body): void
{
    global $tests_run, $failures;
    $tests_run++;

    try {
        $body();
        echo "  ok   – " . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
        echo "  HIBA – " . $name . PHP_EOL . "         " . $error->getMessage() . PHP_EOL;
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ' – ' : '')
            . 'várt: ' . json_encode($expected, JSON_UNESCAPED_UNICODE)
            . ', kapott: ' . json_encode($actual, JSON_UNESCAPED_UNICODE)
        );
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Ideiglenes XLSX-et ír a megadott munkalapokból, és visszaadja az útvonalát. */
function make_workbook(array $sheets): string
{
    $path = tempnam(sys_get_temp_dir(), 'ticky_test_') . '.xlsx';
    file_put_contents($path, ticky_xlsx_write($sheets));

    return $path;
}

function flat_header(): array
{
    return ['Tanár', 'Terem', 'Osztály', 'Tantárgy', 'Nap', 'Kezdés', 'Vége', 'Csoport'];
}

function issue_codes(array $issues): array
{
    return array_values(array_unique(array_map(static fn(array $i): string => (string) $i['kod'], $issues)));
}

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'Normalizálók' . PHP_EOL;

test('nap felismerés magyar nevekből és rövidítésekből', function (): void {
    assert_same(1, ticky_timetable_day_index('Hétfő'));
    assert_same(1, ticky_timetable_day_index('hetfo'));   // ékezet nélkül
    assert_same(1, ticky_timetable_day_index('Hé'));      // a GRID export rövidítése
    assert_same(4, ticky_timetable_day_index('Csütörtök'));
    assert_same(4, ticky_timetable_day_index('Cs'));
    assert_same(5, ticky_timetable_day_index('Péntek'));
    assert_same(null, ticky_timetable_day_index('Szombat'));
    assert_same(null, ticky_timetable_day_index(''));
});

test('idő normalizálás minden bemeneti alakból', function (): void {
    assert_same('07:30', ticky_timetable_parse_time('7:30'));
    assert_same('07:30', ticky_timetable_parse_time('07:30'));
    assert_same('07:30', ticky_timetable_parse_time('7.30'));
    assert_same('07:30', ticky_timetable_parse_time('0730'));
    // Excel numerikus idő: 7:30 = 0.3125 nap
    assert_same('07:30', ticky_timetable_parse_time('0.3125'));
    assert_same('13:40', ticky_timetable_parse_time('0.5694444444444444'));
    assert_same(null, ticky_timetable_parse_time('délelőtt'));
    assert_same(null, ticky_timetable_parse_time('25:00'));
});

test('terem és tanár token megkülönböztetése (Kt vs KT)', function (): void {
    assert_true(ticky_timetable_is_room_token('204'), '204 terem');
    assert_true(ticky_timetable_is_room_token('K101'), 'K101 terem');
    assert_true(ticky_timetable_is_room_token('m17'), 'm17 terem');
    assert_true(ticky_timetable_is_room_token('T1'), 'T1 terem');
    assert_true(ticky_timetable_is_room_token('Kt'), 'Kt (könyvtár) terem');
    // A KT tanárkód, a Kt terem: ha ezt összemosnánk, a KT összes órája elveszne.
    assert_true(!ticky_timetable_is_room_token('KT'), 'KT nem terem');
    assert_true(ticky_timetable_is_teacher_token('KT'), 'KT tanár');
    assert_true(ticky_timetable_is_teacher_token('SZiÁ'), 'SZiÁ tanár');
    assert_true(ticky_timetable_is_teacher_token('NYMK'), 'NYMK tanár');
    assert_true(!ticky_timetable_is_teacher_token('Kt'), 'Kt nem tanár');
    assert_true(!ticky_timetable_is_teacher_token('101FKZS'), 'kevert token nem tanár');
});

test('osztálynév visszaalakítás munkalapnévből', function (): void {
    // Az Excel munkalapnév nem tartalmazhat "/" jelet.
    assert_same('1/9 Déri', ticky_timetable_normalize_class('1_9 Déri'));
    // A valódi aláhúzások maradnak.
    assert_same('13.c_du', ticky_timetable_normalize_class('13.c_du'));
    assert_same('HT_13.ir', ticky_timetable_normalize_class('HT_13.ir'));
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'XLSX olvasás' . PHP_EOL;

test('az írt munkafüzet visszaolvasható (round-trip)', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['BUP', '202', '9.a', 'matematika', 'Hétfő', '07:30', '08:10', ''],
    ]]]);

    $workbook = ticky_xlsx_open($path);
    assert_same(1, count($workbook['munkalapok']));
    assert_same('Órarend', $workbook['munkalapok'][0]['nev']);
    assert_same('BUP', $workbook['munkalapok'][0]['sorok'][2]['A']);
    unlink($path);
});

test('a saját ZIP olvasó ugyanazt adja, mint a ZipArchive', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [flat_header()]]]);

    $manual = ticky_zip_read_entries_manual($path);
    assert_true(isset($manual['xl/workbook.xml']), 'workbook.xml a saját olvasóval');

    if (class_exists('ZipArchive')) {
        $native = ticky_zip_read_entries_ziparchive($path);
        ksort($manual);
        ksort($native);
        assert_same($native, $manual, 'a két ZIP olvasó eredménye eltér');
    }

    unlink($path);
});

test('nem XLSX tartalom érthető hibát ad', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'ticky_test_') . '.xlsx';
    file_put_contents($path, 'ez nem egy zip');
