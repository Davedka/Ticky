<?php
// tests/php/valasz_cache.test.php
// A utils/valasz_cache.php viselkedésének tesztje.
//
// A cache saját, elkülönített ideiglenes könyvtárban fut, hogy a teszt soha ne
// törölhesse egy helyben futó fejlesztoi példány cache-ét.

declare(strict_types=1);

$tests_run = 0;
$failures = [];

// ── Elkülönített TMPDIR ────────────────────────────────────────────
$sandbox = sys_get_temp_dir() . '/ticky_cache_teszt_' . getmypid();
if (!is_dir($sandbox) && !mkdir($sandbox, 0700, true) && !is_dir($sandbox)) {
    fwrite(STDERR, 'Nem hozható létre a teszt könyvtár: ' . $sandbox . PHP_EOL);
    exit(1);
}
putenv('TICKY_CACHE_DIR=' . $sandbox);

require_once dirname(__DIR__, 2) . '/ticky-backend/utils/valasz_cache.php';

if (ticky_cache_konyvtar() !== $sandbox) {
    fwrite(STDERR, 'A cache nem a teszt könyvtárát használja, a teszt megszakad.' . PHP_EOL);
    exit(1);
}

register_shutdown_function(static function () use ($sandbox): void {
    foreach (glob($sandbox . '/*') ?: [] as $fajl) {
        @unlink($fajl);
    }
    @rmdir($sandbox);
});

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

/** Egyedi kulcs tesztenként, hogy a tesztek ne írjanak egymás alá. */
function kulcs(string $nev): string
{
    return $nev . ':' . uniqid('', true);
}

echo 'valasz_cache' . PHP_EOL;

test('üres cache nem ad találatot', function (): void {
    assert_same(false, ticky_cache_olvas(kulcs('ures'), 30)[0]);
});

test('írás után az érték sértetlenül visszajön', function (): void {
    $k = kulcs('iras');
    $ertek = ['osztaly' => '9/A', 'tantargy' => 'Földrajz', 'ekezet' => 'árvíztűrő'];
    ticky_cache_ir($k, $ertek);

    [$talalat, $vissza] = ticky_cache_olvas($k, 30);
    assert_same(true, $talalat);
    assert_same($ertek, $vissza, 'az ékezetes szöveg sem sérülhet');
});

test('a null érvényes cache-elt érték, nem "nincs találat"', function (): void {
    $k = kulcs('null');
    ticky_cache_ir($k, null);

    [$talalat, $ertek] = ticky_cache_olvas($k, 30);
    assert_same(true, $talalat, 'a null-t is találatnak kell jeleznie');
    assert_same(null, $ertek);
});

test('a TTL lejárta után nincs találat', function (): void {
    $k = kulcs('lejar');
    ticky_cache_ir($k, 'régi');
    touch(ticky_cache_utvonal($k), time() - 100);

    assert_same(false, ticky_cache_olvas($k, 30)[0], '100 mp-es bejegyzés 30 mp TTL-lel lejárt');
    assert_same(true, ticky_cache_olvas($k, 300)[0], 'ugyanaz 300 mp TTL-lel még érvényes');
});

test('a 0 TTL kikapcsolja a cache-t', function (): void {
    $k = kulcs('nulla_ttl');
    ticky_cache_ir($k, 'adat');
    assert_same(false, ticky_cache_olvas($k, 0)[0]);
    assert_same(false, ticky_cache_olvas($k, -5)[0]);
});

test('a jövobe állított bejegyzés nem számít érvényesnek', function (): void {
    $k = kulcs('jovo');
    ticky_cache_ir($k, 'adat');
    touch(ticky_cache_utvonal($k), time() + 600);

    assert_same(false, ticky_cache_olvas($k, 300)[0], 'negatív kor gyanús, ne bízzunk benne');
});

test('sérült cache-fájl nem hiba, csak nincs találat', function (): void {
    $k = kulcs('serult');
    file_put_contents(ticky_cache_utvonal($k), '{ ez nem json');

    assert_same(false, ticky_cache_olvas($k, 30)[0]);
});

test('a lekérdezés csak egyszer fut le', function (): void {
    $k = kulcs('lekerdez');
    $hivasok = 0;
    $lekerdezes = function () use (&$hivasok): array {
        $hivasok++;
        return ['sorok' => [1, 2, 3]];
    };

    $elso   = ticky_cache_lekerdez($k, 30, $lekerdezes);
    $masodik = ticky_cache_lekerdez($k, 30, $lekerdezes);

    assert_same(1, $hivasok, 'a második hívásnak a cache-bol kell jönnie');
    assert_same(['sorok' => [1, 2, 3]], $elso);
    assert_same($elso, $masodik);
});

test('az üres eredményt nem cache-eljük', function (): void {
    // Ha a Supabase hibázik, az sb_get() üres tömböt ad vissza. Ezt nem
    // szabad percekig mutogatni – a következo kérés próbálkozzon újra.
    $k = kulcs('ures_eredmeny');
    $hivasok = 0;
    $lekerdezes = function () use (&$hivasok): array {
        $hivasok++;
        return [];
    };

    ticky_cache_lekerdez($k, 30, $lekerdezes);
    ticky_cache_lekerdez($k, 30, $lekerdezes);

    assert_same(2, $hivasok, 'üres eredmény után újra kell próbálkozni');
});

test('az ürítés minden bejegyzést eldob', function (): void {
    $a = kulcs('urit_a');
    $b = kulcs('urit_b');
    ticky_cache_ir($a, 'egy');
    ticky_cache_ir($b, 'ketto');

    $torolt = ticky_cache_urit();

    assert_same(true, $torolt >= 2, "legalább két fájlt kellett törölnie, törölt: {$torolt}");
    assert_same(false, ticky_cache_olvas($a, 300)[0]);
    assert_same(false, ticky_cache_olvas($b, 300)[0]);
});

test('az írás nem hagy maga után .tmp fájlt', function (): void {
    ticky_cache_urit();
    ticky_cache_ir(kulcs('tmp'), ['a' => 1]);

    $maradek = glob(ticky_cache_konyvtar() . '/*.tmp') ?: [];
    assert_same(0, count($maradek), 'az ideiglenes fájlt a rename-nek el kell tüntetnie');
});

test('a statisztika a tényleges bejegyzéseket számolja', function (): void {
    ticky_cache_urit();
    assert_same(0, ticky_cache_statisztika()['bejegyzesek']);

    ticky_cache_ir(kulcs('stat_a'), ['a' => 1]);
    ticky_cache_ir(kulcs('stat_b'), ['b' => 2]);

    $stat = ticky_cache_statisztika();
    assert_same(2, $stat['bejegyzesek']);
    assert_same(true, $stat['meret_bajt'] > 0);
    assert_same(true, is_int($stat['legregebbi_kor_mp']));
});

echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
