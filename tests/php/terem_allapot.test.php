<?php
// tests/php/terem_allapot.test.php
// A utils/terem_allapot.php tiszta szurologikájának tesztje.
//
// Ez a logika korábban a Supabase oldalán volt (kezdes lte / vegzes gte).
// A cache miatt átkerült PHP-ba, ezért a viselkedésének bizonyíthatóan
// azonosnak kell maradnia – kulönösen a zárt határok miatt.

declare(strict_types=1);

$tests_run = 0;
$failures = [];

require_once dirname(__DIR__, 2) . '/ticky-backend/utils/terem_allapot.php';

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

/** Egy tipikus adatbázis sor. */
function ora(array $felulir = []): array
{
    return $felulir + [
        'terem_id' => 17,
        'tanar_id' => 42,
        'osztaly'  => '9/A',
        'tantargy' => 'Matematika',
        'kezdes'   => '10:15:00',
        'vegzes'   => '11:00:00',
    ];
}

echo 'terem_allapot' . PHP_EOL;

test('az ido "ÓÓ:PP"-re normalizálódik', function (): void {
    assert_same('10:15', ticky_ido_perc('10:15:00'));
    assert_same('10:15', ticky_ido_perc('10:15'));
    assert_same('10:15', ticky_ido_perc(' 10:15:00 '), 'a körülvevo szóköz sem zavarhat');
});

test('az óra alatt foglalt', function (): void {
    assert_same(true, ticky_ora_tart_e(ora(), '10:30'));
});

test('a határok zártak – a becsengetés és a kicsengetés perce is beleszámít', function (): void {
    assert_same(true, ticky_ora_tart_e(ora(), '10:15'), 'becsengetés perce');
    assert_same(true, ticky_ora_tart_e(ora(), '11:00'), 'kicsengetés perce');
});

test('az órán kívül szabad', function (): void {
    assert_same(false, ticky_ora_tart_e(ora(), '10:14'));
    assert_same(false, ticky_ora_tart_e(ora(), '11:01'));
});

test('a több tanórát átfogó blokk végig tart', function (): void {
    // A produkciós kód szándékosan támogatja a 6+7. órát átfogó blokkokat.
    $blokk = ora(['kezdes' => '12:05:00', 'vegzes' => '13:35:00']);
    assert_same(true, ticky_ora_tart_e($blokk, '12:50'), 'a blokk közepén is tart');
    assert_same(true, ticky_ora_tart_e($blokk, '13:35'));
    assert_same(false, ticky_ora_tart_e($blokk, '13:36'));
});

test('hiányos idomezo nem tesz foglalttá egy termet', function (): void {
    assert_same(false, ticky_ora_tart_e(ora(['kezdes' => '']), '10:30'));
    assert_same(false, ticky_ora_tart_e(ora(['vegzes' => '']), '10:30'));
});

test('a foglaltsági térkép terem_id szerint kulcsolódik', function (): void {
    $orak = [
        ora(['terem_id' => 17]),
        ora(['terem_id' => 18, 'osztaly' => '10/B']),
        ora(['terem_id' => 19, 'kezdes' => '08:00:00', 'vegzes' => '08:45:00']),
    ];
    $nevek = ['42' => 'NKA'];

    $map = ticky_terem_foglalt_map($orak, $nevek, '10:30');

    // A PHP a numerikus szöveg-kulcsot automatikusan int-re alakítja, ezért
    // a kulcsok int-ként jönnek vissza. Ez pont egyezik a hívóval, ami a
    // JSON-ból szintén int terem_id-vel keres.
    assert_same([17, 18], array_keys($map), 'a 19-es terem órája már véget ért');
    assert_same('NKA', $map[17]['tanar']);
    assert_same('10/B', $map[18]['osztaly']);
    assert_same('10:15', $map[17]['kezdes'], 'a megjelenítés "ÓÓ:PP"');
    assert_same('11:00', $map[17]['vegzes']);
});

test('ismeretlen tanár esetén kérdojel, nem hiba', function (): void {
    $map = ticky_terem_foglalt_map([ora(['tanar_id' => 999])], ['42' => 'NKA'], '10:30');
    assert_same('?', $map['17']['tanar']);
});

test('a terem_id nélküli sort átugorja', function (): void {
    $map = ticky_terem_foglalt_map([ora(['terem_id' => null])], [], '10:30');
    assert_same([], $map);
});

test('a terem-szuro kizárja az ismeretlen termeket', function (): void {
    $orak = [ora(['terem_id' => 17]), ora(['terem_id' => 99])];

    $mind = ticky_terem_foglalt_map($orak, [], '10:30');
    assert_same([17, 99], array_keys($mind));

    $szurt = ticky_terem_foglalt_map($orak, [], '10:30', [17, 18]);
    assert_same([17], array_keys($szurt), 'a 99-es terem nincs a listában');
});

test('a szuro szám és szöveg azonosítóval is muködik', function (): void {
    $orak = [ora(['terem_id' => '17'])];
    assert_same([17], array_keys(ticky_terem_foglalt_map($orak, [], '10:30', [17])));
    assert_same([17], array_keys(ticky_terem_foglalt_map($orak, [], '10:30', ['17'])));
});

test('egy teremben több egyidejo óra esetén az utolsó nyer', function (): void {
    // Csoportbontásnál elofordul. A korábbi kód is felülírta a térképet,
    // ezt a viselkedést szándékosan megtartjuk.
    $orak = [
        ora(['terem_id' => 17, 'osztaly' => '9/A']),
        ora(['terem_id' => 17, 'osztaly' => '9/B']),
    ];
    $map = ticky_terem_foglalt_map($orak, [], '10:30');
    assert_same('9/B', $map['17']['osztaly']);
});

test('a hívó int terem_id-vel megtalálja a bejegyzést', function (): void {
    // Ez a lényeg: a termek tábla sora JSON-ból int id-t hoz, és a
    // foglaltsági térképnek pont arra kell illeszkednie.
    $map = ticky_terem_foglalt_map([ora(['terem_id' => 17])], [], '10:30');

    $terem = ['id' => 17, 'terem_szam' => 'K205'];
    assert_same(true, isset($map[$terem['id']]), 'int id-vel kell találnia');
});

test('a tanár-térkép id szerint épül', function (): void {
    $map = ticky_tanar_nev_map([
        ['id' => 42, 'rovid_nev' => 'NKA'],
        ['id' => 7,  'rovid_nev' => 'SZP'],
        ['id' => '', 'rovid_nev' => 'HIBAS'],
        ['rovid_nev' => 'NINCS_ID'],
    ]);

    assert_same([42 => 'NKA', 7 => 'SZP'], $map);
});

test('a rövid név nélküli tanár kérdojelet kap', function (): void {
    assert_same([5 => '?'], ticky_tanar_nev_map([['id' => 5]]));
});

echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
