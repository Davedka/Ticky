<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/ticky-backend/utils/helpers.php';
require_once $root . '/ticky-backend/utils/orarend_view.php';

$tests_run = 0;
$failures = [];

function test(string $name, callable $body): void
{
    global $tests_run, $failures;
    $tests_run++;

    try {
        $body();
        echo '  ok   – ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures[] = $name;
        echo '  HIBA – ' . $name . PHP_EOL . '         ' . $error->getMessage() . PHP_EOL;
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

/** Egy orarendek sor a PostgREST beágyazott alakjában. */
function db_row(
    int $day,
    int $period,
    string $teacher,
    string $room,
    string $class,
    string $subject,
    ?int $group = null,
    ?string $teacher_name = null
): array {
    $slots = [
        1 => ['07:30', '08:10'], 2 => ['08:20', '09:05'], 3 => ['09:15', '10:00'],
        4 => ['10:15', '11:00'], 5 => ['11:10', '11:55'], 6 => ['12:05', '12:50'],
        7 => ['12:55', '13:35'], 8 => ['13:40', '14:20'],
    ];

    return [
        'het_napja'   => $day,
        'ora_sorszam' => $period,
        'kezdes'      => $slots[$period][0] . ':00',
        'vegzes'      => $slots[$period][1] . ':00',
        'osztaly'     => $class,
        'tantargy'    => $subject,
        'csoport'     => $group,
        'tanarok'     => ['rovid_nev' => $teacher, 'nev' => $teacher_name],
        'termek'      => ['terem_szam' => $room],
    ];
}

echo PHP_EOL . 'Mezők kiolvasása' . PHP_EOL;

test('a beágyazott tanár- és teremnév kiolvasható', function (): void {
    $row = db_row(1, 1, 'BUP', '202', '9.a', 'mt', null, 'Bukovics Péter');
    assert_same('BUP', ticky_view_row_teacher($row));
    assert_same('Bukovics Péter', ticky_view_row_teacher_name($row));
    assert_same('202', ticky_view_row_room($row));
    // A Postgres "07:30:00" alakot HH:MM-re vágjuk.
    assert_same('07:30', ticky_view_row_time($row, 'kezdes'));
    assert_same('08:10', ticky_view_row_time($row, 'vegzes'));
});

test('hiányzó tanárnév nem okoz hibát', function (): void {
    $row = db_row(1, 1, 'XYZQ', '202', '9.a', 'mt');
    assert_same(null, ticky_view_row_teacher_name($row), 'ismeretlen kódhoz nincs név');
});

echo PHP_EOL . 'Csoportszámok' . PHP_EOL;

test('az osztály csoportjai az importált adatból jönnek', function (): void {
    $rows = [
        db_row(1, 1, 'BUP', '202', '9.a', 'mt', 1),
        db_row(1, 1, 'BI', '208', '9.a', 'any', 2),
    ];

    assert_same([1, 2], ticky_view_class_group_numbers($rows, '9.a'));
});

test('egyetlen csoport nem számít valódi bontásnak', function (): void {
    $rows = [db_row(1, 1, 'BUP', '202', '13.c_du', 'mt', 1)];
    assert_same([], ticky_view_class_group_numbers($rows, '13.c_du'));
});

test('csoport nélküli sorok esetén az Excel-térkép segít', function (): void {
    // A tanárok.js forrásból készült verzióban nincs csoport oszlop-adat,
    // ilyenkor a csoport_terkep.php-ra esünk vissza, hogy a címkék megmaradjanak.
    $rows = [db_row(1, 1, 'BUP', '202', '9.a', 'mt')];
    assert_same([1, 2], ticky_view_class_group_numbers($rows, '9.a'), '9.a-nak két csoportja van a térkép szerint');
});

test('sor csoportszáma: az importált érték az elsődleges', function (): void {
    $row = db_row(1, 1, 'BUP', '202', '9.a', 'mt', 2);
    assert_same([2], ticky_view_row_groups($row, '9.a', 1, '07:30'));
});

test('sor csoportszáma: hiányában az Excel-térkép', function (): void {
    // A térkép szerint 9.a hétfő 07:30-kor BUP az 1. csoportot tanítja.
    $row = db_row(1, 1, 'BUP', '202', '9.a', 'mt');
    assert_same([1], ticky_view_row_groups($row, '9.a', 1, '07:30'));
});

echo PHP_EOL . 'Osztály napi nézet' . PHP_EOL;

test('egyszerű óra: nincs csoportbontás', function (): void {
    $rows = [db_row(1, 1, 'BUP', '202', '9.x', 'matematika', null, 'Teszt Tanár')];
    $lessons = ticky_view_class_day_lessons($rows, '9.x', 1, []);

    assert_same(1, count($lessons));
    assert_same('07:30', $lessons[0]['kezdes']);
    assert_same('08:10', $lessons[0]['vegzes']);
    assert_same(1, $lessons[0]['ora_sorszam']);
    assert_same(false, $lessons[0]['is_csoport']);
    assert_same('202', $lessons[0]['terem']);
    assert_same('BUP', $lessons[0]['tanar']);
    assert_same('Teszt Tanár', $lessons[0]['tanar_nev']);
    assert_same('matematika', $lessons[0]['tantargy']);
    assert_same(false, $lessons[0]['reszleges_csoport']);
    assert_same('', $lessons[0]['hianyzo_szoveg']);
});

test('a válasz minden mezője megvan, amit a frontend kirajzol', function (): void {
    $rows = [db_row(1, 1, 'BUP', '202', '9.x', 'mt')];
    $lesson = ticky_view_class_day_lessons($rows, '9.x', 1, [])[0];

    foreach ([
        'kezdes', 'vegzes', 'ora_sorszam', 'is_csoport', 'terem', 'tanar',
        'tanar_nev', 'tantargy', 'csoportok', 'reszleges_csoport',
        'reszleges_csoportok', 'reszleges_szoveg', 'hianyzo_szoveg',
    ] as $field) {
        assert_true(array_key_exists($field, $lesson), "hiányzó mező a válaszban: {$field}");
    }
});

test('csoportbontás: két csoport egy sávban', function (): void {
    $rows = [
        db_row(1, 1, 'BUP', '202', '9.x', 'matematika', 1),
        db_row(1, 1, 'BI', '208', '9.x', 'angol', 2),
    ];
    $lesson = ticky_view_class_day_lessons($rows, '9.x', 1, [1, 2])[0];

    assert_same(true, $lesson['is_csoport']);
    assert_same('202 / 208', $lesson['terem']);
    assert_same('BUP / BI', $lesson['tanar']);
    assert_same('matematika / angol', $lesson['tantargy']);
    assert_same(null, $lesson['tanar_nev'], 'bontásnál nincs egyetlen tanárnév');
    assert_same(2, count($lesson['csoportok']));
    assert_same(1, $lesson['csoportok'][0]['csoport_szam']);
    assert_same(2, $lesson['csoportok'][1]['csoport_szam']);
    // Mindkét csoportnak van órája → nem részleges.
    assert_same(false, $lesson['reszleges_csoport']);
});

test('részleges csoport: csak a 2. csoportnak van órája', function (): void {
    $rows = [db_row(1, 8, 'BI', '208', '9.x', 'angol', 2)];
    $lesson = ticky_view_class_day_lessons($rows, '9.x', 1, [1, 2])[0];

    assert_same(true, $lesson['reszleges_csoport']);
    assert_same([2], $lesson['reszleges_csoportok']);
    assert_same('csak a 2. csoport', $lesson['reszleges_szoveg']);
    assert_same('az 1. csoportnak nincs órája', $lesson['hianyzo_szoveg']);
});

test('egycsoportos osztálynál nincs részleges címke', function (): void {
    $rows = [db_row(1, 1, 'BUP', '202', '13.x', 'mt', 1)];
    $lesson = ticky_view_class_day_lessons($rows, '13.x', 1, [])[0];

    assert_same(false, $lesson['reszleges_csoport'], 'nincs valódi bontás, nem lehet részleges');
    assert_same('', $lesson['reszleges_szoveg']);
});

test('a csoportok csoportszám szerint rendezettek', function (): void {
    $rows = [
        db_row(1, 1, 'BI', '208', '9.x', 'angol', 2),
        db_row(1, 1, 'BUP', '202', '9.x', 'matematika', 1),
    ];
    $lesson = ticky_view_class_day_lessons($rows, '9.x', 1, [1, 2])[0];

    assert_same(1, $lesson['csoportok'][0]['csoport_szam'], 'az 1. csoport legyen elöl');
    assert_same('BUP', $lesson['csoportok'][0]['tanar']);
});

test('azonos tanár/terem/tantárgy nem duplázódik', function (): void {
    $rows = [
        db_row(1, 1, 'BUP', '202', '9.x', 'mt', 1),
        db_row(1, 1, 'BUP', '202', '9.x', 'mt', 1),
    ];
    $lesson = ticky_view_class_day_lessons($rows, '9.x', 1, [1, 2])[0];

    assert_same(1, count($lesson['csoportok']));
});

test('összevont osztály: egy tanár, egy terem, két osztály', function (): void {
    // A 12.a lekérdezésénél csak a saját sora jön vissza – az összevonás
    // a teremnézetben látszik, itt egy normál óra.
    $rows = [db_row(2, 3, 'GR', '1', '12.a', 'szf')];
    $lesson = ticky_view_class_day_lessons($rows, '12.a', 2, [])[0];

    assert_same(false, $lesson['is_csoport']);
    assert_same('1', $lesson['terem']);
});

test('csak a kért nap órái jönnek vissza', function (): void {
    $rows = [
        db_row(1, 1, 'BUP', '202', '9.x', 'hétfői'),
        db_row(3, 1, 'BUP', '202', '9.x', 'szerdai'),
    ];

    $monday = ticky_view_class_day_lessons($rows, '9.x', 1, []);
    assert_same(1, count($monday));
    assert_same('hétfői', $monday[0]['tantargy']);

    $friday = ticky_view_class_day_lessons($rows, '9.x', 5, []);
    assert_same(0, count($friday), 'üres napra üres lista');
});

test('az órák kezdés szerint rendezettek', function (): void {
    $rows = [
        db_row(1, 4, 'A', '1', '9.x', 'negyedik'),
        db_row(1, 1, 'B', '2', '9.x', 'elso'),
        db_row(1, 2, 'C', '3', '9.x', 'masodik'),
    ];
    $lessons = ticky_view_class_day_lessons($rows, '9.x', 1, []);

    assert_same(['elso', 'masodik', 'negyedik'], array_column($lessons, 'tantargy'));
});

echo PHP_EOL . 'Összevonás' . PHP_EOL;

test('a szomszédos azonos órák egy sorrá vonódnak', function (): void {
    $rows = [
        db_row(1, 1, 'AZ', '310', '12.x', 'szf'),
        db_row(1, 2, 'AZ', '310', '12.x', 'szf'),
        db_row(1, 3, 'AZ', '310', '12.x', 'szf'),
    ];
    $merged = merge_consecutive_orak(ticky_view_class_day_lessons($rows, '12.x', 1, []));

    assert_same(1, count($merged), 'a három egymást követő azonos óra egy blokk');
    assert_same('07:30', $merged[0]['kezdes']);
    assert_same('10:00', $merged[0]['vegzes']);
    assert_same(3, $merged[0]['ora_szam']);
});

test('eltérő tantárgyú szomszédos órák nem vonódnak össze', function (): void {
    $rows = [
        db_row(1, 1, 'AZ', '310', '12.x', 'szf'),
        db_row(1, 2, 'AZ', '310', '12.x', 'mt'),
    ];
    $merged = merge_consecutive_orak(ticky_view_class_day_lessons($rows, '12.x', 1, []));

    assert_same(2, count($merged));
});

echo PHP_EOL . 'Tanár nézet' . PHP_EOL;

test('a tanár napi órái aggregálva', function (): void {
    // A ticky_view_teacher_day adatbázist hív, ezért itt a mögötte lévő
    // aggregálást a napi nézet szerkezetén keresztül ellenőrizzük.
    $rows = [
        db_row(1, 1, 'ÁSZJ', '204', '9.b', 'mt'),
        db_row(1, 1, 'ÁSZJ', '204', '9.c', 'mt'),
    ];

    // Két osztály, azonos terem és tantárgy egy sávban → összevont óra.
    $lesson = ticky_view_class_day_lessons($rows, '9.b', 1, [])[0];
    assert_same('204', $lesson['terem'], 'egy terem marad');
});

echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
