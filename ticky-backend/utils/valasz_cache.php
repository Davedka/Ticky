<?php
// utils/valasz_cache.php
//
// Rövid életű, fájl alapú cache a forró olvasási utakra.
//
// Miért fájl és nem Redis: a projektnek szándékosan nincs függőségkezelése
// (lásd CLAUDE.md), és a Render egyetlen példányon futtatja a szervert, így a
// lokális fájl elég. Több példány esetén példányonként külön cache lesz – ez
// korrekt marad, csak annyival több Supabase kérés, ahány példány fut.
//
// FONTOS: mindig NYERS adatbázis-sorokat cache-elünk, soha nem kész választ.
// A válasz idofüggo mezoi (aktuális ido, mai nap, aktív szünet) minden
// kérésnél frissen számolódnak, így a cache nem tud elavult órát mutatni.
//
// Az órarend csak publikáláskor változik, és a publikálás hívja a
// ticky_cache_urit() függvényt, ezért a TTL bátran lehet percekben mérheto.

// Nyers órarend sorok élettartama.
const TICKY_CACHE_ORAREND_MP = 300;

// Ritkán változó listák (termek, tanarok, osztálykódok).
const TICKY_CACHE_LISTA_MP = 600;

/**
 * A cache könyvtára.
 *
 * Alapértelmezésben a rendszer ideiglenes könyvtára. A TICKY_CACHE_DIR
 * környezeti változóval felülírható – erre a teszteknek van szükségük, hogy
 * ne nyúlhassanak egy helyben futó példány cache-éhez.
 */
function ticky_cache_konyvtar(): string
{
    $egyedi = getenv('TICKY_CACHE_DIR');
    $konyvtar = is_string($egyedi) && $egyedi !== '' ? $egyedi : sys_get_temp_dir();

    return rtrim($konyvtar, DIRECTORY_SEPARATOR);
}

/** A cache-fájlok glob mintája. */
function ticky_cache_minta(): string
{
    return ticky_cache_konyvtar() . DIRECTORY_SEPARATOR . 'ticky_cache_*.json';
}

/** A cache-fájl teljes elérési útja egy kulcshoz. */
function ticky_cache_utvonal(string $kulcs): string
{
    return ticky_cache_konyvtar()
        . DIRECTORY_SEPARATOR . 'ticky_cache_' . hash('sha256', $kulcs) . '.json';
}

/**
 * Cache-elt érték olvasása.
 *
 * @return array{0:bool,1:mixed} [talalat, ertek]
 *         A találat külön jelzés, mert a null is érvényes cache-elt érték lehet.
 */
function ticky_cache_olvas(string $kulcs, int $max_kor_mp): array
{
    if ($max_kor_mp <= 0) {
        return [false, null];   // 0 vagy negatív TTL = kikapcsolt cache
    }

    $fajl = ticky_cache_utvonal($kulcs);
    if (!is_file($fajl)) {
        return [false, null];
    }

    $kor = time() - (int) @filemtime($fajl);
    if ($kor < 0 || $kor >= $max_kor_mp) {
        return [false, null];   // negatív kor: órabeállítás visszafelé – ne bízzunk benne
    }

    $nyers = @file_get_contents($fajl);
    if ($nyers === false || $nyers === '') {
        return [false, null];
    }

    $adat = json_decode($nyers, true);
    if (!is_array($adat) || !array_key_exists('ertek', $adat)) {
        return [false, null];   // sérült cache – kezeljük úgy, mintha nem lenne
    }

    return [true, $adat['ertek']];
}

/**
 * Cache-be írás.
 *
 * Atomi: ideiglenes fájl + rename, hogy egy párhuzamos olvasó soha ne lásson
 * félig kiírt JSON-t. A PHP_CLI_SERVER_WORKERS miatt ez tényleg elofordulhat.
 */
function ticky_cache_ir(string $kulcs, $ertek): void
{
    $fajl = ticky_cache_utvonal($kulcs);
    $json = json_encode(['ertek' => $ertek], JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;   // nem szerializálható érték – a cache kihagyása nem hiba
    }

    $ideiglenes = $fajl . '.' . getmypid() . '.tmp';
    if (@file_put_contents($ideiglenes, $json) === false) {
        return;   // írásvédett /tmp – a rendszer cache nélkül is muködjön
    }
    if (!@rename($ideiglenes, $fajl)) {
        @unlink($ideiglenes);
    }
}

/**
 * Publikáláskor hívandó: minden Ticky cache-fájl eldobása.
 *
 * Szándékosan mindent töröl – publikálás után nincs olyan cache-elt
 * órarend-adat, ami még érvényes lenne.
 *
 * @return int a törölt fájlok száma
 */
function ticky_cache_urit(): int
{
    $minta = ticky_cache_minta();

    $torolt = 0;
    foreach (glob($minta) ?: [] as $fajl) {
        if (@unlink($fajl)) {
            $torolt++;
        }
    }
    return $torolt;
}

/**
 * Cache-elt lekérdezés: találat esetén hálózat nélkül, különben lekéri és eltárolja.
 *
 * @param callable $lekerdezes Hálózati lekérdezés, ha nincs érvényes cache
 * @return mixed
 */
function ticky_cache_lekerdez(string $kulcs, int $max_kor_mp, callable $lekerdezes)
{
    [$talalat, $ertek] = ticky_cache_olvas($kulcs, $max_kor_mp);
    if ($talalat) {
        return $ertek;
    }

    $ertek = $lekerdezes();

    // Üres eredményt sem cache-elünk hosszan: ha a Supabase épp hibázott, az
    // sb_get() üres tömböt ad vissza, és azt nem akarjuk percekig mutogatni.
    if (is_array($ertek) && $ertek === []) {
        return $ertek;
    }

    ticky_cache_ir($kulcs, $ertek);
    return $ertek;
}

/** Diagnosztikához: hány cache-fájl van és mekkorák. */
function ticky_cache_statisztika(): array
{
    $fajlok = glob(ticky_cache_minta()) ?: [];
    $meret  = 0;
    $legregebbi = null;

    foreach ($fajlok as $fajl) {
        $meret += (int) @filesize($fajl);
        $ido = (int) @filemtime($fajl);
        if ($legregebbi === null || $ido < $legregebbi) {
            $legregebbi = $ido;
        }
    }

    return [
        'bejegyzesek'      => count($fajlok),
        'meret_bajt'       => $meret,
        'legregebbi_kor_mp' => $legregebbi === null ? null : max(0, time() - $legregebbi),
    ];
}
