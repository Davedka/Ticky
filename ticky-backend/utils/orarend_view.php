<?php
// utils/orarend_view.php
// Publikus órarend-nézetek az AKTÍV (publikált) adatbázis-verzióból.
//
// Miért van rá szükség: az /osztaly és /tanar oldalak korábban a tanárok.js
// fájlból olvastak, ezért egy frissen publikált órarend nem látszott rajtuk –
// miközben a termek oldalak (amelyek az adatbázisból olvasnak) már az újat
// mutatták. Ez a modul ugyanazt a válaszszerkezetet állítja elő az
// adatbázisból, amit a tanarok_source.php adott, így a frontend változatlan.
//
// A "melyik verzió aktív" kérdést nem külön lekérdezés dönti el, hanem az
// orarendek.aktiv oszlop: a publikálás egyetlen tranzakcióban állítja át.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/osztaly.php';
require_once __DIR__ . '/valasz_cache.php';

if (is_file(__DIR__ . '/csoport_terkep.php')) {
    require_once __DIR__ . '/csoport_terkep.php';
}

// ─────────────────────────────────────────────────────────────────
// Lekérdezések
// ─────────────────────────────────────────────────────────────────

/** Az aktív órarend sorainak kiválasztása, beágyazott tanár- és teremnévvel. */
function ticky_view_select_columns(): string
{
    return 'het_napja,ora_sorszam,kezdes,vegzes,osztaly,tantargy,csoport,'
        . 'tanarok(rovid_nev,nev),termek(terem_szam)';
}

/**
 * Egy osztály teljes heti órarendje az aktív verzióból.
 *
 * @return array{osztaly:string,sorok:array}|null null, ha az osztály egyáltalán
 *         nem szerepel az aktív órarendben (ilyenkor a hívó a tanárok.js-re esik vissza)
 */
function ticky_view_class_rows(string $requested_class): ?array
{
    $requested_class = trim($requested_class);
    if ($requested_class === '') {
        return null;
    }

    $rows = ticky_view_fetch_class_rows($requested_class);
    if ($rows !== []) {
        return ['osztaly' => $requested_class, 'sorok' => $rows];
    }

    // Kis/nagybetű eltérés esetén megkeressük a tényleges írásmódot.
    $canonical = ticky_view_resolve_class_code($requested_class);
    if ($canonical === null) {
        return null;
    }

    $rows = ticky_view_fetch_class_rows($canonical);

    return $rows === [] ? null : ['osztaly' => $canonical, 'sorok' => $rows];
}

function ticky_view_fetch_class_rows(string $class): array
{
    // Cache-elve: az osztályok száma kicsi és zárt (~35), ezért végigkattintva
    // az egész iskolát is csak ennyi bejegyzés keletkezik. A sorok nyersek,
    // idofüggo mezo nincs bennük.
    return ticky_cache_lekerdez(
        'orarend:osztaly:' . $class,
        TICKY_CACHE_ORAREND_MP,
        static fn(): array => sb_get_all('orarendek', [
            'osztaly' => 'eq.' . $class,
            'aktiv'   => 'eq.true',
            'select'  => ticky_view_select_columns(),
            'order'   => 'het_napja.asc,kezdes.asc',
        ])
    );
}

/** Az aktív órarendben szereplő osztálykód megkeresése kis/nagybetű nélkül. */
function ticky_view_resolve_class_code(string $requested_class): ?string
{
    // Lapozva: az aktív órarend simán túllépi az egy válaszban visszaadható
    // sorszámot, és a hiányzó sorokból hiányzó osztályok lennének.
    // Ez a lekérdezés MINDEN aktív sort letölt (2273 sor, ~42 KB), csak azért,
    // hogy kis/nagybetutol függetlenül megtalálja az osztálykódot. Cache nélkül
    // ez minden elgépelt vagy eltéro írásmódú kérésnél újra lefutna.
    $rows = ticky_cache_lekerdez(
        'orarend:osztaly_kodok',
        TICKY_CACHE_LISTA_MP,
        static fn(): array => sb_get_all('orarendek', [
            'aktiv'  => 'eq.true',
            'select' => 'osztaly',
        ])
    );

    $requested_lower = osztaly_lower($requested_class);
    foreach ($rows as $row) {
        $code = trim((string) ($row['osztaly'] ?? ''));
        if ($code !== '' && osztaly_lower($code) === $requested_lower) {
            return $code;
        }
    }

    return null;
}

/**
 * Egy tanár órái az aktív verzióból.
 *
 * @return array{tanar:string,tanar_nev:?string,sorok:array}|null
 */
function ticky_view_teacher_rows(string $requested_teacher): ?array
{
    $requested_teacher = trim($requested_teacher);
    if ($requested_teacher === '') {
        return null;
    }

    $teachers = ticky_cache_lekerdez(
        'tanar:kod:' . $requested_teacher,
        TICKY_CACHE_LISTA_MP,
        static fn(): array => sb_get('tanarok', [
            'rovid_nev' => 'eq.' . $requested_teacher,
            'select'    => 'id,rovid_nev,nev',
            'limit'     => '1',
        ])
    );

    if (!is_array($teachers) || $teachers === []) {
        return null;
    }

    $teacher = $teachers[0];
    // 66 tanár = 66 cache-bejegyzés, ez is zárt halmaz.
    $rows = ticky_cache_lekerdez(
        'orarend:tanar:' . $teacher['id'],
        TICKY_CACHE_ORAREND_MP,
        static fn(): array => sb_get_all('orarendek', [
            'tanar_id' => 'eq.' . $teacher['id'],
            'aktiv'    => 'eq.true',
            'select'   => ticky_view_select_columns(),
            'order'    => 'het_napja.asc,kezdes.asc',
        ])
    );

    if ($rows === []) {
        return null;
    }

    return [
