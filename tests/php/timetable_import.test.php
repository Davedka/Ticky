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

    try {
        ticky_xlsx_open($path);
        throw new RuntimeException('nem dobott kivételt');
    } catch (TickyXlsxException $error) {
        assert_true($error->getMessage() !== '', 'üres hibaüzenet');
    } finally {
        unlink($path);
    }
});

test('DOCTYPE-ot tartalmazó XML elutasításra kerül (XXE védelem)', function (): void {
    try {
        ticky_xlsx_parse_xml('<!DOCTYPE x [<!ENTITY a "b">]><x/>', 'teszt');
        throw new RuntimeException('nem dobott kivételt');
    } catch (TickyXlsxException) {
        // várt viselkedés
    }
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'FLAT formátum' . PHP_EOL;

test('érvényes fejléces tábla hibátlanul beolvasható', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['BUP', '202', '9.a', 'matematika', 'Hétfő', '07:30', '08:10', ''],
        ['NYMK', 'K2', '9.a', 'angol', 'Kedd', '08:20', '09:05', '1'],
    ]]]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(TICKY_IMPORT_FORMAT_FLAT, $parsed['formatum']);
    assert_same(2, count($parsed['orak']));
    assert_same([], $parsed['problemak']);

    assert_same('BUP', $parsed['orak'][0]['tanar']);
    assert_same(1, $parsed['orak'][0]['het_napja']);
    assert_same(1, $parsed['orak'][0]['ora_sorszam']);
    assert_same(null, $parsed['orak'][0]['csoport']);
    assert_same(1, $parsed['orak'][1]['csoport']);
    unlink($path);
});

test('hiányzó kötelező oszlop nem ismerhető fel formátumként', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        ['Terem', 'Osztály', 'Tantárgy', 'Nap', 'Kezdés', 'Vége'], // nincs Tanár
        ['202', '9.a', 'matematika', 'Hétfő', '07:30', '08:10'],
    ]]]);

    try {
        ticky_timetable_parse_file($path);
        throw new RuntimeException('nem dobott kivételt');
    } catch (TickyXlsxException $error) {
        assert_true(str_contains($error->getMessage(), 'Tanár'), 'a hibaüzenet nem sorolja fel a várt oszlopokat');
    } finally {
        unlink($path);
    }
});

test('hibás sorok kódolt hibát adnak, a jó sorok átmennek', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['BUP', '202', '9.a', 'matematika', 'Hétfő', '07:30', '08:10', ''],
        ['BUP', '202', '9.a', 'matematika', 'Szombat', '07:30', '08:10', ''],   // rossz nap
        ['BUP', '202', '9.a', 'matematika', 'Kedd', '10:00', '09:00', ''],      // fordított idő
        ['BUP', '202', '9.a', 'matematika', 'Kedd', '06:00', '06:45', ''],      // nincs ilyen órasáv
        ['', '202', '9.a', 'matematika', 'Kedd', '08:20', '09:05', ''],         // nincs tanár
    ]]]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(1, count($parsed['orak']), 'csak az érvényes sor maradhat');
    assert_same(
        ['ISMERETLEN_NAP', 'FORDITOTT_IDO', 'ISMERETLEN_ORASAV', 'HIANYZO_TANAR'],
        issue_codes($parsed['problemak'])
    );
    unlink($path);
});

test('több tanórát átfogó sorból óránként külön sor lesz', function (): void {
    // 12:05–13:35 = 6. ÉS 7. óra. Ha egyetlen sorként importálnánk, a 7. óra
    // eltűnne az órarendből.
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['TM', '111', '10.c', 'viai', 'Szerda', '12:05', '13:35', ''],
    ]]]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(2, count($parsed['orak']), 'a blokkból két tanóra lesz');
    assert_same([6, 7], array_column($parsed['orak'], 'ora_sorszam'));
    assert_same(['12:05', '12:55'], array_column($parsed['orak'], 'kezdes'));
    assert_same(['12:50', '13:35'], array_column($parsed['orak'], 'vegzes'));
    assert_same([], $parsed['problemak'], 'a szabályos blokk nem hiba');
    unlink($path);
});

test('a teljes napot átfogó blokk mind a nyolc órára bomlik', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['FL', 'T1', '9.a', 'tn.', 'Hétfő', '07:30', '14:20', ''],
    ]]]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(8, count($parsed['orak']));
    unlink($path);
});

test('tanórahatárra nem eső vége figyelmeztetést kap', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['BUP', '202', '9.a', 'mt', 'Hétfő', '07:30', '08:05', ''],
    ]]]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(1, count($parsed['orak']));
    assert_same('08:10', $parsed['orak'][0]['vegzes'], 'a hivatalos sávvégre igazítjuk');
    assert_same(['IGAZITOTT_IDO'], issue_codes($parsed['problemak']));
    assert_same('warning', $parsed['problemak'][0]['szint']);
    unlink($path);
});

test('a kibontás önmagában is helyes', function (): void {
    assert_same(
        [['ora_sorszam' => 6, 'kezdes' => '12:05', 'vegzes' => '12:50'],
         ['ora_sorszam' => 7, 'kezdes' => '12:55', 'vegzes' => '13:35']],
        ticky_timetable_expand_period_range('12:05', '13:35')
    );
    assert_same(
        [['ora_sorszam' => 1, 'kezdes' => '07:30', 'vegzes' => '08:10']],
        ticky_timetable_expand_period_range('07:30', '08:10')
    );
    assert_same([], ticky_timetable_expand_period_range('06:00', '06:45'), 'ismeretlen kezdés');
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'GRID formátum' . PHP_EOL;

/** Csoportonkénti munkalap az iskolai export szerkezetében. */
function grid_sheet(string $class, int $group, array $day_rows): array
{
    $rows = [
        [$class . ' – ' . $group . '. csoport'],
        ['Nap', "1.\n7:30 - 8:10", "2.\n8:20 - 9:05", "3.\n9:15 - 10:00"],
    ];

    foreach ($day_rows as $day => $cells) {
        $rows[] = array_merge([$day], $cells);
    }

    return ['nev' => $class . ' ' . $group . '.cs', 'sorok' => $rows];
}

test('csoportonkénti munkalap beolvasása', function (): void {
    $path = make_workbook([
        grid_sheet('9.a', 1, ['Hé' => ["mt\n202 BUP", "any\nK2 NYMK", '']]),
        grid_sheet('9.a', 2, ['Hé' => ["mt\n202 BUP", "nny\n203 TPI", '']]),
    ]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(TICKY_IMPORT_FORMAT_GRID, $parsed['formatum']);
    assert_same([], $parsed['problemak']);

    $by_subject = [];
    foreach ($parsed['orak'] as $lesson) {
        $by_subject[$lesson['tantargy']] = $lesson;
    }

    // A mindkét csoportnál szereplő óra az egész osztályé → csoport nélkül.
    assert_same(null, $by_subject['mt']['csoport'], 'a közös óra nem lehet csoportos');
    assert_same(1, $by_subject['any']['csoport']);
    assert_same(2, $by_subject['nny']['csoport']);
    assert_same(3, count($parsed['orak']), 'a közös órát nem szabad duplán felvenni');
    unlink($path);
});

test('egyetlen csoportlapos osztálynál nincs csoportszám', function (): void {
    $path = make_workbook([grid_sheet('13.c_du', 1, ['Hé' => ["mt\n202 BUP", '', '']])]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(1, count($parsed['orak']));
    assert_same(null, $parsed['orak'][0]['csoport'], 'nincs valódi bontás, nem lehet csoportszám');
    unlink($path);
});

test('több terem és több tanár párosítása', function (): void {
    $path = make_workbook([grid_sheet('9.b', 1, [
        'Hé' => ["any\nK101 / 102 BZ / PÁI", "tn.\nT1 / T2 FL", ''],
    ])]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same([], $parsed['problemak']);

    $pairs = [];
    foreach ($parsed['orak'] as $lesson) {
        $pairs[] = $lesson['terem'] . '/' . $lesson['tanar'];
    }
    sort($pairs);

    // Azonos elemszám → sorrendben párosít; egy tanár + két terem → mindkettőben.
    assert_same(['102/PÁI', 'K101/BZ', 'T1/FL', 'T2/FL'], $pairs);
    unlink($path);
});

test('értelmezhetetlen cella hibát ad, nem talál ki órát', function (): void {
    $path = make_workbook([grid_sheet('9.c', 1, [
        'Hé' => ['308', "gé\nm22", "9.cd -angol1\nany\nK101 / 19 /N 1Y1M0K"],
    ])]);

    $parsed = ticky_timetable_parse_file($path);
    assert_same(0, count($parsed['orak']), 'egyetlen órát sem szabad kitalálni');
    assert_same(
        ['TOREDEK_CELLA', 'HIANYZO_TEREM_VAGY_TANAR', 'ERTELMEZHETETLEN_TOKEN'],
        issue_codes($parsed['problemak'])
    );
    unlink($path);
});

test('a csoport-annotáció kikerül a tantárgynévből', function (): void {
    // Az export a szó KÖZEPÉBE szúrja az annotációt, ezért a maradék
    // sorokat elválasztó nélkül kell összefűzni.
    assert_same('kommunikáció', ticky_timetable_clean_subject(['kommu', '-rt1. csoport', 'nikáció']));
    assert_same('any', ticky_timetable_clean_subject(['-r. csoport', 'any']));
    assert_same('min', ticky_timetable_clean_subject(['-ir. csoport/-szf.', 'csoport', 'min']));
    assert_same('mt', ticky_timetable_clean_subject(['mt']));
    assert_same('', ticky_timetable_clean_subject(['-2. csoport']));
});

test('összecsúszott tantárgynév figyelmeztetést kap', function (): void {
    assert_true(ticky_timetable_subject_looks_garbled('9.be -angol1/9.beany-angol2'), 'a "/"-t tartalmazó név gyanús');
    assert_true(!ticky_timetable_subject_looks_garbled('kommunikáció'), 'a normál név nem gyanús');
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'Üzleti validáció' . PHP_EOL;

/** Órarend sor a validátor teszteléséhez. */
function lesson(string $teacher, string $room, string $class, int $day = 1, int $period = 1, ?int $group = null): array
{
    $slots = ticky_timetable_period_slots();

    return [
        'tanar' => $teacher, 'terem' => $room, 'osztaly' => $class,
        'tantargy' => 'tárgy', 'csoport' => $group,
        'het_napja' => $day, 'ora_sorszam' => $period,
        'kezdes' => $slots[$period][0], 'vegzes' => $slots[$period][1],
        'forras' => $teacher . '@' . $room,
    ];
}

test('tanárütközés: egy tanár két teremben egyszerre', function (): void {
    $issues = ticky_validator_check_conflicts([
        lesson('ÁSZJ', '204', '9.b'),
        lesson('ÁSZJ', '310', '12.a'),
    ]);

    assert_same(['TANAR_UTKOZES'], issue_codes($issues));
    assert_same('error', $issues[0]['szint']);
});

test('összevont osztály nem tanárütközés', function (): void {
    // Ugyanaz a tanár, ugyanaz a terem, két osztály együtt: ez legális.
    $issues = ticky_validator_check_conflicts([
        lesson('GR', '1', '12.a'),
        lesson('GR', '1', '12.b'),
    ]);

    assert_same([], array_values(array_filter(
        issue_codes($issues),
        static fn(string $code): bool => $code === 'TANAR_UTKOZES'
    )));
});

test('teremütközés: két tanár egy teremben egyszerre', function (): void {
    $issues = ticky_validator_check_conflicts([
        lesson('ÁSZJ', '204', '9.b'),
        lesson('BUP', '204', '11.c'),
    ]);

    assert_true(in_array('TEREM_UTKOZES', issue_codes($issues), true), 'nem jelezte a teremütközést');
});

test('osztály két teremben csak figyelmeztetés', function (): void {
    // Nyelvi alcsoport-bontás miatt ez lehet legális, ezért nem hiba.
    $issues = ticky_validator_check_conflicts([
        lesson('BZ', 'K101', '9.b', 1, 1, 1),
        lesson('PÁI', '102', '9.b', 1, 1, 1),
    ]);

    foreach ($issues as $issue) {
        if ($issue['kod'] === 'OSZTALY_UTKOZES') {
            assert_same('warning', $issue['szint']);
            return;
        }
    }

    throw new RuntimeException('nem keletkezett OSZTALY_UTKOZES figyelmeztetés');
});

test('különböző napok és sávok nem ütköznek', function (): void {
    $issues = ticky_validator_check_conflicts([
        lesson('ÁSZJ', '204', '9.b', 1, 1),
        lesson('ÁSZJ', '310', '9.b', 2, 1),
        lesson('ÁSZJ', '310', '9.b', 1, 2),
    ]);

    assert_same([], $issues);
});

test('új tanár/terem/osztály csak figyelmeztetés', function (): void {
    $issues = ticky_validator_check_new_entities(
        [lesson('UJTANAR', 'UJTEREM', 'UJ.OSZTALY')],
        ['BUP'],
        ['204'],
        ['9.a']
    );

    assert_same(['UJ_TANAR', 'UJ_TEREM', 'UJ_OSZTALY'], issue_codes($issues));
    foreach ($issues as $issue) {
        assert_same('warning', $issue['szint'], 'új entitás nem buktathatja meg az importot');
    }
});

test('összegzés: hiba esetén nem érvényes az import', function (): void {
    $lessons = [lesson('ÁSZJ', '204', '9.b'), lesson('ÁSZJ', '310', '12.a')];
    $summary = ticky_validator_summarize($lessons, ticky_validator_check_conflicts($lessons));

    assert_same(false, $summary['ervenyes']);
    assert_same(1, $summary['hibak_szama']);
    assert_same(2, $summary['statisztika']['orak']);
    assert_same(1, $summary['statisztika']['tanarok']);
    assert_same(2, $summary['statisztika']['termek']);
});

test('teljes futás hibátlan importon érvényes eredményt ad', function (): void {
    $path = make_workbook([['nev' => 'Órarend', 'sorok' => [
        flat_header(),
        ['BUP', '202', '9.a', 'matematika', 'Hétfő', '07:30', '08:10', ''],
        ['NYMK', 'K2', '9.b', 'angol', 'Hétfő', '07:30', '08:10', ''],
    ]]]);

    $report = ticky_validator_run(ticky_timetable_parse_file($path), ['BUP', 'NYMK'], ['202', 'K2'], ['9.a', '9.b']);

    assert_same(true, $report['ervenyes']);
    assert_same(0, $report['hibak_szama']);
    assert_same(0, $report['figyelmeztetesek_szama']);
    assert_same(2, $report['statisztika']['orak']);
    unlink($path);
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL . 'Feltöltés validáció' . PHP_EOL;

test('hiányzó fájl esetén beszédes hiba', function (): void {
    $result = ticky_validator_check_upload(null);
    assert_same(false, $result['ok']);
    assert_same('NINCS_FAJL', $result['kod']);
});

test('rossz kiterjesztés elutasítása', function (): void {
    $result = ticky_validator_check_upload([
        'name' => 'orarend.csv', 'tmp_name' => '/nonexistent',
        'error' => UPLOAD_ERR_OK, 'size' => 100,
    ]);

    assert_same(false, $result['ok']);
    // A feltöltés-ellenőrzés hamarabb fut, mint a kiterjesztés vizsgálat.
    assert_true(in_array($result['kod'], ['NEM_FELTOLTOTT_FAJL', 'ERVENYTELEN_KITERJESZTES'], true), $result['kod']);
});

test('fájlnév megtisztítása a naplózáshoz', function (): void {
    assert_same('orarend.xlsx', ticky_validator_safe_file_name('../../etc/orarend.xlsx'));
    assert_same('orarend.xlsx', ticky_validator_safe_file_name("C:\\temp\\orarend.xlsx"));
});

// ─────────────────────────────────────────────────────────────────
echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
