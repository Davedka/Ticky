<?php
// tests/php/orarend_nap_cache.test.php


declare(strict_types=1);

$tests_run = 0;
$failures = [];

// ── Elkülönített cache-könyvtár ────────────────────────────────────
$sandbox = sys_get_temp_dir() . '/ticky_nap_cache_teszt_' . getmypid();
if (!is_dir($sandbox) && !mkdir($sandbox, 0700, true) && !is_dir($sandbox)) {
    fwrite(STDERR, 'Nem hozható létre a teszt könyvtár.' . PHP_EOL);
    exit(1);
}
putenv('TICKY_CACHE_DIR=' . $sandbox);

require_once dirname(__DIR__, 2) . '/ticky-backend/utils/valasz_cache.php';

if (ticky_cache_konyvtar() !== $sandbox) {
    fwrite(STDERR, 'A cache nem a teszt könyvtárát használja.' . PHP_EOL);
    exit(1);
}

register_shutdown_function(static function () use ($sandbox): void {
    foreach (glob($sandbox . '/*') ?: [] as $fajl) {
        @unlink($fajl);
    }
    @rmdir($sandbox);
});

// ── Hamis Supabase réteg ───────────────────────────────────────────
$GLOBALS['hivasok'] = [];

function sb_get($table, $params = [], $key = 'anon') {
    $GLOBALS['hivasok'][] = $table;

    if ($table === 'termek') {
        return [
            ['id' => 1, 'terem_szam' => 'K205', 'emelet' => 2],
            ['id' => 2, 'terem_szam' => '111',  'emelet' => 1],
        ];
    }
    if ($table === 'tanarok') {
        return [['id' => 42, 'rovid_nev' => 'NKA', 'nev' => 'Nagy Katalin']];
    }
    return [];
}

function sb_get_all($table, $params = [], $key = 'anon') {
    $GLOBALS['hivasok'][] = $table . ':all';

    return [
        ['id' => 9, 'terem_id' => 1, 'tanar_id' => 42, 'osztaly' => '9/A',
         'tantargy' => 'Matematika', 'kezdes' => '10:15:00', 'vegzes' => '11:00:00', 'ora_sorszam' => 3],
    ];
}

// ── A vizsgált függvények kiemelése a valódi forrásból ─────────────
$forras = file_get_contents(dirname(__DIR__, 2) . '/ticky-backend/utils/orarend_nap_cache.php');
foreach (['ticky_nap_orai', 'ticky_tanarok_mind', 'ticky_termek_lista', 'ticky_terem_sor'] as $fv) {
    $kezd = strpos($forras, 'function ' . $fv . '(');
    if ($kezd === false) {
        fwrite(STDERR, "Nem található a(z) {$fv}() az orarend_nap_cache.php-ban." . PHP_EOL);
        exit(1);
    }
    $veg = strpos($forras, "\n}", $kezd);
    eval(substr($forras, $kezd, ($veg - $kezd) + 2));
}

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

/** Minden teszt tiszta cache-sel és nullázott számlálóval indul. */
function alaphelyzet(): void
{
    ticky_cache_urit();
    $GLOBALS['hivasok'] = [];
}

echo 'orarend_nap_cache' . PHP_EOL;

test('a napi órák másodszorra már nem mennek ki a hálózatra', function (): void {
    alaphelyzet();

    $elso = ticky_nap_orai(1);
    assert_same(['orarendek:all'], $GLOBALS['hivasok'], 'elsore egy lekérdezés');

    $masodik = ticky_nap_orai(1);
    assert_same(['orarendek:all'], $GLOBALS['hivasok'], 'másodszorra NEM lehet új lekérdezés');
    assert_same($elso, $masodik);
});

test('különbözo napok külön bejegyzést kapnak', function (): void {
    alaphelyzet();

    ticky_nap_orai(1);
    ticky_nap_orai(2);
    ticky_nap_orai(1);

    assert_same(2, count($GLOBALS['hivasok']), 'két nap = két lekérdezés, a harmadik cache');
});

test('hétvégére nem megy ki lekérdezés', function (): void {
    alaphelyzet();

    assert_same([], ticky_nap_orai(0));
    assert_same([], ticky_nap_orai(6));
    assert_same([], $GLOBALS['hivasok'], 'érvénytelen napra nincs hálózati kérés');
});

test('a tanárlista egyszer töltodik le', function (): void {
    alaphelyzet();

    ticky_tanarok_mind();
    ticky_tanarok_mind();
    ticky_tanarok_mind();

    assert_same(['tanarok'], $GLOBALS['hivasok']);
});

test('a teremlista egyszer töltodik le', function (): void {
    alaphelyzet();

    ticky_termek_lista();
    ticky_termek_lista();

    assert_same(['termek'], $GLOBALS['hivasok']);
});

test('a terem keresés is a közös listát használja', function (): void {
    alaphelyzet();

    $terem = ticky_terem_sor('K205');
    assert_same(1, $terem['id'] ?? null);

    ticky_terem_sor('111');
    ticky_terem_sor('K205');

    assert_same(['termek'], $GLOBALS['hivasok'], 'három keresés, egyetlen lekérdezés');
});

test('ismeretlen terem null, nem hiba', function (): void {
    alaphelyzet();
    assert_same(null, ticky_terem_sor('NINCSILYEN'));
});

test('a terem keresés pontos egyezést vár', function (): void {
    // A hívó nagybetusíti a bemenetet; a korábbi eq. szuro is pontosan egyezett.
    alaphelyzet();
    assert_same(null, ticky_terem_sor('k205'), 'kisbetus alak nem találhat');
});

test('a /api/termek és a /api/terem ugyanazon a bejegyzésen osztozik', function (): void {
    // Ez a refaktor lényege: két végpont, egyetlen Supabase lekérdezés naponta.
    alaphelyzet();

    // /api/termek?allapot=1 útja
    ticky_termek_lista();
    ticky_nap_orai(3);
    ticky_tanarok_mind();

    // /api/terem/{szam} útja
    ticky_terem_sor('K205');
    ticky_nap_orai(3);
    ticky_tanarok_mind();

    assert_same(
        ['termek', 'orarendek:all', 'tanarok'],
        $GLOBALS['hivasok'],
        'a második végpont egyetlen új lekérdezést sem indíthat'
    );
});

test('az ürítés után újra lekérdez', function (): void {
    alaphelyzet();

    ticky_nap_orai(1);
    ticky_cache_urit();
    ticky_nap_orai(1);

    assert_same(['orarendek:all', 'orarendek:all'], $GLOBALS['hivasok'],
        'publikálás után friss adatot kell hozni');
});

echo PHP_EOL;
if ($failures !== []) {
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
