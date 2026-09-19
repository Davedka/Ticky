<?php
// tests/php/support_repo.test.php
// A utils/support_repo.php és utils/support_mail.php tiszta függvényeinek tesztje.
//
// Hálózat nincs benne: a validáció, a mezőtisztítás, az IP-hash és a
// levéltörzs mind hálózat nélkül eldönthető. Az adatbázis- és email-küldő
// függvények integrációs felületek, azokat nem mockoljuk.

declare(strict_types=1);

$tests_run = 0;
$failures = [];

// A support_repo a service key-t használja az IP-hash sójához. A teszt saját
// értéket ad neki, hogy a hash determinisztikus és a valódi kulcstól
// független legyen.
putenv('SUPABASE_SERVICE_KEY=teszt_so_nem_valodi_kulcs');

require_once dirname(__DIR__, 2) . '/ticky-backend/utils/support_repo.php';
require_once dirname(__DIR__, 2) . '/ticky-backend/utils/support_mail.php';

function test(string $name, callable $body): void
{
    global $tests_run, $failures;
    $tests_run++;

    try {
        $body();
        echo '.';
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
        echo 'F';
    }
}

function assert_same(mixed $varhato, mixed $kapott, string $uzenet = ''): void
{
    if ($varhato !== $kapott) {
        throw new RuntimeException(
            ($uzenet !== '' ? $uzenet . ' – ' : '')
            . 'várt: ' . var_export($varhato, true)
            . ', kapott: ' . var_export($kapott, true)
        );
    }
}

function assert_igaz(bool $allitas, string $uzenet): void
{
    if (!$allitas) {
        throw new RuntimeException($uzenet);
    }
}

/** Érvényes beküldés, amibol a tesztek egy-egy mezot rontanak el. */
function ervenyes_bemenet(array $felulir = []): array
{
    return array_merge([
        'nev'       => 'Kiss Péter',
        'email'     => 'kiss.peter@iskola.hu',
        'kategoria' => 'terem',
        'uzenet'    => 'A 208-as terem szabadnak látszik, pedig ott most óra van.',
        'weboldal'  => '',
    ], $felulir);
}

// ── Validáció: sikeres eset ──────────────────────────────────────

test('érvényes beküldés átmegy', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet());

    assert_same(true, $eredmeny['ok']);
    assert_same([], $eredmeny['hibak']);
    assert_same('Kiss Péter', $eredmeny['adat']['nev']);
    assert_same('terem', $eredmeny['adat']['kategoria']);
});

test('a felesleges szóköz lekerül a mezőkről', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'nev'   => '   Kiss    Péter  ',
        'email' => '  kiss.peter@iskola.hu ',
    ]));

    assert_same(true, $eredmeny['ok']);
    assert_same('Kiss Péter', $eredmeny['adat']['nev']);
    assert_same('kiss.peter@iskola.hu', $eredmeny['adat']['email']);
});

// ── Validáció: hibás esetek ──────────────────────────────────────

test('túl rövid név elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['nev' => 'K']));

    assert_same(false, $eredmeny['ok']);
    assert_igaz(isset($eredmeny['hibak']['nev']), 'a névre kell hibaüzenet');
    assert_same([], $eredmeny['adat'], 'hibás beküldésbol nem adunk vissza adatot');
});

test('túl hosszú név elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'nev' => str_repeat('a', TICKY_SUPPORT_NEV_MAX + 1),
    ]));

    assert_same(false, $eredmeny['ok']);
    assert_igaz(isset($eredmeny['hibak']['nev']), 'a hosszú nevet el kell utasítani');
});

test('érvénytelen email elbukik', function (): void {
    foreach (['nincs-kukac', 'a@b', 'a@@b.hu', ''] as $rossz) {
        $eredmeny = ticky_support_validalas(ervenyes_bemenet(['email' => $rossz]));
        assert_same(false, $eredmeny['ok'], 'ezt el kellett volna utasítani: ' . $rossz);
        assert_igaz(isset($eredmeny['hibak']['email']), 'hiányzik az email hibaüzenet: ' . $rossz);
    }
});

test('ismeretlen kategória elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['kategoria' => 'sajat_kategoria']));

    assert_same(false, $eredmeny['ok']);
    assert_igaz(isset($eredmeny['hibak']['kategoria']), 'csak ismert kategória fogadható el');
});

test('üres kategória elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['kategoria' => '']));

    assert_same(false, $eredmeny['ok']);
});

test('minden felsorolt kategória elfogadott', function (): void {
    foreach (array_keys(TICKY_SUPPORT_KATEGORIAK) as $kulcs) {
        $eredmeny = ticky_support_validalas(ervenyes_bemenet(['kategoria' => $kulcs]));
        assert_same(true, $eredmeny['ok'], 'ezt el kellett volna fogadni: ' . $kulcs);
    }
});

test('túl rövid üzenet elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['uzenet' => 'hiba']));

    assert_same(false, $eredmeny['ok']);
    assert_igaz(isset($eredmeny['hibak']['uzenet']), 'a rövid üzenetet el kell utasítani');
});

test('túl hosszú üzenet elbukik', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'uzenet' => str_repeat('a', TICKY_SUPPORT_UZENET_MAX + 1),
    ]));

    assert_same(false, $eredmeny['ok'], 'enélkül egy szkript megabájtos sorokkal tölthetné a kvótát');
});

test('több hibás mező mindegyike megjelenik a riportban', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'nev'    => 'K',
        'email'  => 'rossz',
        'uzenet' => 'rövid',
    ]));

    assert_same(false, $eredmeny['ok']);
    assert_same(3, count($eredmeny['hibak']), 'mindhárom hibát jelezni kell, ne csak az elsot');
});

// ── Visszaélés elleni védelem ────────────────────────────────────

test('a kitöltött csapda mező elutasít', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['weboldal' => 'http://spam.example']));

    assert_same(false, $eredmeny['ok']);
    assert_igaz(isset($eredmeny['hibak']['weboldal']), 'a robot beküldését el kell dobni');
});

test('a csapda akkor is fog, ha a többi mező hibátlan', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet(['weboldal' => ' x ']));

    assert_same(false, $eredmeny['ok']);
});

// ── Fejléc-injekció ──────────────────────────────────────────────

test('a névbol kikerül a sortörés', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'nev' => "Kiss Péter\r\nBcc: aldozat@example.com",
    ]));

    assert_same(true, $eredmeny['ok']);
    assert_igaz(
        !str_contains($eredmeny['adat']['nev'], "\n") && !str_contains($eredmeny['adat']['nev'], "\r"),
        'a beszúrt sortörés email fejléc-injekció lenne'
    );
});

test('a sortörést tartalmazó email cím elbukik', function (): void {
    // A tisztítás szóközzé alakítja a sortörést, a szóközös cím pedig
    // már nem érvényes email – így két rétegen is fennakad.
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'email' => "kiss@iskola.hu\r\nBcc: aldozat@example.com",
    ]));

    assert_same(false, $eredmeny['ok']);
});

test('az üzenet sortörései megmaradnak', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'uzenet' => "Elso sor\r\nMásodik sor\r\nHarmadik sor",
    ]));

    assert_same(true, $eredmeny['ok']);
    assert_same("Elso sor\nMásodik sor\nHarmadik sor", $eredmeny['adat']['uzenet']);
});

test('az üzenetbol kikerülnek a vezérlőkarakterek', function (): void {
    $eredmeny = ticky_support_validalas(ervenyes_bemenet([
        'uzenet' => "A terem foglalt\x00\x07 pedig nem az kellene legyen",
    ]));

    assert_same(true, $eredmeny['ok']);
    assert_igaz(
        !str_contains($eredmeny['adat']['uzenet'], "\x00"),
        'a nulla bájt a Postgres text mezot is megakasztaná'
    );
});

// ── IP-hash ──────────────────────────────────────────────────────

test('az IP-hash determinisztikus', function (): void {
    assert_same(
        ticky_support_ip_hash('10.0.0.1'),
        ticky_support_ip_hash('10.0.0.1'),
        'ugyanarra az IP-re ugyanaz a hash kell'
    );
});

test('különbözo IP különbözo hasht ad', function (): void {
    assert_igaz(
        ticky_support_ip_hash('10.0.0.1') !== ticky_support_ip_hash('10.0.0.2'),
        'két gép nem kaphat azonos hasht'
    );
});

test('az IP-hash nem tartalmazza a nyers IP-t', function (): void {
    $hash = ticky_support_ip_hash('192.168.1.55');

    assert_igaz(!str_contains($hash, '192.168.1.55'), 'a nyers IP nem tárolható');
    assert_same(32, strlen($hash));
});

test('üres IP-re üres hash jár', function (): void {
    assert_same('', ticky_support_ip_hash(''));
});

// ── Kategória címkék ─────────────────────────────────────────────

test('a kategória kulcsból olvasható címke lesz', function (): void {
    assert_same('Hibajelentés', ticky_support_kategoria_neve('hiba'));
});

test('ismeretlen kategória a saját kulcsával jelenik meg', function (): void {
    assert_same('ismeretlen', ticky_support_kategoria_neve('ismeretlen'));
});

// ── Levéltörzs ───────────────────────────────────────────────────

test('a levél tárgya a kategóriát és a feladót tartalmazza', function (): void {
    $torzs = ticky_support_mail_torzs(ervenyes_bemenet(), 42);

    assert_igaz(str_starts_with($torzs['targy'], '[Ticky Support]'), 'kell az elotag a szuréshez');
    assert_igaz(str_contains($torzs['targy'], 'Terem / órarend probléma'), 'a kategória címkéje hiányzik');
    assert_igaz(str_contains($torzs['targy'], 'Kiss Péter'), 'a feladó neve hiányzik');
});

test('a levél törzse tartalmazza a feladó címét és az üzenetet', function (): void {
    $torzs = ticky_support_mail_torzs(ervenyes_bemenet(), 42);

    assert_igaz(str_contains($torzs['szoveg'], 'kiss.peter@iskola.hu'), 'a válaszcím hiányzik a törzsbol');
    assert_igaz(str_contains($torzs['szoveg'], '208-as terem'), 'az üzenet szövege hiányzik');
    assert_igaz(str_contains($torzs['szoveg'], '#42'), 'az azonosító hiányzik');
});

test('azonosító nélkül is összeáll a levél', function (): void {
    $torzs = ticky_support_mail_torzs(ervenyes_bemenet());

    assert_igaz(!str_contains($torzs['szoveg'], 'Azonosító:'), 'azonosító nélkül ne írjunk ki üres sort');
    assert_igaz($torzs['szoveg'] !== '', 'a törzs nem lehet üres');
});

test('a tárgy hossza korlátozott', function (): void {
    $torzs = ticky_support_mail_torzs(ervenyes_bemenet([
        'nev' => str_repeat('Hosszú Név ', 40),
    ]));

    assert_igaz(mb_strlen($torzs['targy'], 'UTF-8') <= 180, 'a túl hosszú tárgyat a levelezok levágják');
});

// ── Email beállítás ──────────────────────────────────────────────

test('API kulcs nélkül a küldés kikapcsolt', function (): void {
    putenv('SUPPORT_MAIL_API_KEY=');

    $beallitas = ticky_support_mail_beallitas();
    assert_same(false, $beallitas['bekapcsolva']);

    // Kulcs nélkül a küldés nem próbál hálózatot: azonnal 'kikapcsolva'.
    $eredmeny = ticky_support_mail_kuld(ervenyes_bemenet(), 1);
    assert_same('kikapcsolva', $eredmeny['statusz']);
    assert_same(null, $eredmeny['hiba']);
});

test('API kulccsal a küldés bekapcsolt', function (): void {
    putenv('SUPPORT_MAIL_API_KEY=re_teszt_kulcs');

    $beallitas = ticky_support_mail_beallitas();
    assert_same(true, $beallitas['bekapcsolva']);

    putenv('SUPPORT_MAIL_API_KEY=');
});

test('címzett hiányában a support postafiók az alapértelmezés', function (): void {
    putenv('SUPPORT_MAIL_TO=');

    $beallitas = ticky_support_mail_beallitas();
    assert_same(TICKY_SUPPORT_MAIL_ALAP_CIMZETT, $beallitas['cimzett']);
});

test('a beállított címzett felülírja az alapértelmezést', function (): void {
    putenv('SUPPORT_MAIL_TO=mas@iskola.hu');

    $beallitas = ticky_support_mail_beallitas();
    assert_same('mas@iskola.hu', $beallitas['cimzett']);

    putenv('SUPPORT_MAIL_TO=');
});

echo PHP_EOL;
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo '  ✗ ' . $failure . PHP_EOL;
    }
    echo count($failures) . ' teszt elbukott a(z) ' . $tests_run . ' közül.' . PHP_EOL;
    exit(1);
}

echo 'Mind a(z) ' . $tests_run . ' teszt sikeres.' . PHP_EOL;
exit(0);
