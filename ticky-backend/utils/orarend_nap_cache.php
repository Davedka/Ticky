<?php
// utils/orarend_nap_cache.php
//
// Egy tanítási nap óráinak és a tanárok listájának cache-elt lekérése.
//
// Miért közös modul: a /api/termek, a /api/terem/{szam} és a /api/napirend
// ugyanannak a napnak az óráit kéri, csak más szűréssel. Ha mindegyik ugyanezt
// a két cache-bejegyzést használja, akkor az EGÉSZ iskolára naponta két
// Supabase lekérdezés jut, akárhány felhasználó nézi.
//
// A select szándékosan bőséges (minden mező, amit bármelyik hívó igényel),
// hogy tényleg egyetlen bejegyzés szolgálja ki mindet.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/valasz_cache.php';

/**
 * Egy tanítási nap ÖSSZES aktív órája.
 *
 * sb_get_all, mert egy nap sorszáma megközelítheti a PostgREST 1000-es
 * korlátját, és a csendben levágott sorokból üres termek lennének.
 *
 * @param int $nap 1-5 (hétfő-péntek)
 */
function ticky_nap_orai(int $nap): array
{
    if ($nap < 1 || $nap > 5) {
        return [];
    }

    return ticky_cache_lekerdez(
        'orarend:nap:' . $nap,
        TICKY_CACHE_ORAREND_MP,
        static fn(): array => sb_get_all('orarendek', [
            'het_napja' => 'eq.' . $nap,
            'aktiv'     => 'eq.true',
            'select'    => 'id,terem_id,tanar_id,osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja',
            'order'     => 'kezdes.asc',
        ])
    );
}

/**
 * A tanárok teljes listája (66 sor), azonosítóval és névvel.
 *
 * Kérésenkénti in.(...) szűrés helyett egyben kérjük le: kicsi, ritkán
 * változik, és így egyetlen cache-bejegyzés szolgál ki minden végpontot.
 */
function ticky_tanarok_mind(): array
{
    return ticky_cache_lekerdez(
        'tanarok:id_rovid_nev',
        TICKY_CACHE_LISTA_MP,
        static fn(): array => sb_get('tanarok', ['select' => 'id,rovid_nev,nev'])
    );
}

/**
 * A termek tábla teljes listája (52 sor), cache-elve.
 */
function ticky_termek_lista(): array
{
    $termek = ticky_cache_lekerdez(
        'termek:lista',
        TICKY_CACHE_LISTA_MP,
        static fn(): array => sb_get('termek', [
            'select' => 'id,terem_szam,emelet',
            'order'  => 'terem_szam.asc',
        ])
    );

    return is_array($termek) ? $termek : [];
}

/**
 * Egyetlen terem sora terem_szam alapján.
 *
 * A hívó nagybetűsíti a bemenetet, és az adatbázis is nagybetűs kódokat tárol,
 * ezért pontos egyezést keresünk – ugyanúgy, ahogy a korábbi eq. szűrő tette.
 *
 * @return array|null
 */
function ticky_terem_sor(string $terem_szam): ?array
{
    foreach (ticky_termek_lista() as $terem) {
        if ((string) ($terem['terem_szam'] ?? '') === $terem_szam) {
            return $terem;
        }
    }

    return null;
}
