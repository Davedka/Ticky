<?php
// tests/php/supabase_paging.test.php


declare(strict_types=1);

$tests_run = 0;
$failures = [];

// A vizsgált szerver viselkedése: soha nem ad vissza SB_PAGE_SIZE-nál többet.
$GLOBALS['fake_total'] = 0;
$GLOBALS['fake_calls'] = [];

const SUPABASE_URL = '';
const SUPABASE_SERVICE_KEY = '';
const SUPABASE_ANON_KEY = '';
const SB_PAGE_SIZE = 1000;
const SB_MAX_PAGES = 100;

/** A valódi sb_get() helyettesítője: limit/offset szerint szeletel. */
function sb_get($table, $params = [], $key = 'anon') {
    $GLOBALS['fake_calls'][] = $params;

    $limit  = (int) ($params['limit'] ?? SB_PAGE_SIZE);
    $offset = (int) ($params['offset'] ?? 0);
    $limit  = min($limit, SB_PAGE_SIZE); // a szerver felső korlátja

    $rows = [];
    for ($index = $offset; $index < min($offset + $limit, $GLOBALS['fake_total']); $index++) {
        $rows[] = ['id' => $index];
    }

    return $rows;
}

// A vizsgált függvényt a config/supabase.php-ból emeljük ki, hogy a valódi
// forrás fusson, ne egy másolat.
$source = file_get_contents(dirname(__DIR__, 2) . '/ticky-backend/config/supabase.php');
$start = strpos($source, 'function sb_get_all(');
if ($start === false) {
    fwrite(STDERR, 'Nem található az sb_get_all() a config/supabase.php-ban.' . PHP_EOL);
    exit(1);
}
$end = strpos($source, "\n}", $start);
eval(substr($source, $start, ($end - $start) + 2));

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
            . 'várt: ' . json_encode($expected) . ', kapott: ' . json_encode($actual)
        );
    }
}

function fetch_all(int $total): array
{
    $GLOBALS['fake_total'] = $total;
    $GLOBALS['fake_calls'] = [];

    return sb_get_all('orarendek', ['select' => 'id']);
}

echo PHP_EOL . 'Lapozás' . PHP_EOL;

test('2273 sor: a levágás ellenére mind megvan', function (): void {
    // Pontosan ez a valós eset: az Örökölt órarend 2273 sora, amiből
    // a lapozás nélküli lekérdezés csak 1000-et adott vissza.
    $rows = fetch_all(2273);
    assert_same(2273, count($rows), 'hiányzó sorok a lapozás után');
    assert_same(3, count($GLOBALS['fake_calls']), '3 oldal kell (1000+1000+273)');
    assert_same(0, $rows[0]['id']);
    assert_same(2272, $rows[2272]['id'], 'az utolsó sor is megjött');
});

test('a lapméretnél kevesebb sor egy kérésből jön', function (): void {
    $rows = fetch_all(42);
    assert_same(42, count($rows));
    assert_same(1, count($GLOBALS['fake_calls']), 'nem kell második kérés');
});

test('üres tábla: nincs végtelen ciklus', function (): void {
    $rows = fetch_all(0);
    assert_same(0, count($rows));
    assert_same(1, count($GLOBALS['fake_calls']));
});

test('pontosan egy teli oldal: kell egy ellenőrző kérés', function (): void {
    $rows = fetch_all(1000);
    assert_same(1000, count($rows));
    // Az első oldal tele van, ezért nem tudhatjuk, hogy vége – a második
    // kérés üresen tér vissza, és ott állunk meg.
    assert_same(2, count($GLOBALS['fake_calls']));
});

test('a hívó limit/offset paraméterét felülírjuk', function (): void {
    $GLOBALS['fake_total'] = 1500;
    $GLOBALS['fake_calls'] = [];

    $rows = sb_get_all('orarendek', ['select' => 'id', 'limit' => '10', 'offset' => '900']);

    assert_same(1500, count($rows), 'a hívó limitje nem csonkíthatja a lapozást');
    assert_same('1000', $GLOBALS['fake_calls'][0]['limit']);
    assert_same('0', $GLOBALS['fake_calls'][0]['offset']);
});

test('a szűrők minden oldalon átmennek', function (): void {
    $GLOBALS['fake_total'] = 1200;
    $GLOBALS['fake_calls'] = [];

    sb_get_all('orarendek', ['select' => 'osztaly', 'aktiv' => 'eq.true']);

    foreach ($GLOBALS['fake_calls'] as $index => $call) {
        assert_same('eq.true', $call['aktiv'] ?? null, "a(z) {$index}. oldalon elveszett a szűrő");
        assert_same('osztaly', $call['select'] ?? null, "a(z) {$index}. oldalon elveszett a select");
    }
});

echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
