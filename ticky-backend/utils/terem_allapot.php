<?php
// utils/terem_allapot.php
//
// A "melyik teremben most melyik óra folyik" kérdés tiszta, hálózatmentes
// logikája.
//
// Miért külön modul: korábban ezt a szurést a Supabase végezte kérésenként
// (kezdes lte $ido, vegzes gte $ido). Az a lekérdezés percenként változik,
// ezért gyakorlatilag nem cache-elheto. Ha viszont a nap ÖSSZES óráját kérjük
// le egyszer, az a lekérdezés napon belül állandó, tehát cache-elheto – és a
// "most melyik óra" szurés ide, PHP-ba kerül.

/**
 * Ido normalizálása "ÓÓ:PP" alakra.
 *
 * A Postgres "10:15:00" alakban adja vissza a time oszlopot, az aktualis_ido()
 * viszont "10:15"-öt. Az összehasonlítás csak akkor helyes, ha mindketto
 * ugyanolyan hosszú – a szöveges összehasonlítás ekkor idorendi is.
 */
function ticky_ido_perc(string $ido): string
{
    return substr(trim($ido), 0, 5);
}

/**
 * Folyik-e ez az óra a megadott idopontban?
 *
 * A határok zártak: a becsengetés és a kicsengetés perce is az órához tartozik.
 * Ez a korábbi Supabase szuro (lte/gte) viselkedése, szándékosan megtartva.
 */
function ticky_ora_tart_e(array $ora, string $ido): bool
{
    $kezdes = ticky_ido_perc((string) ($ora['kezdes'] ?? ''));
    $vegzes = ticky_ido_perc((string) ($ora['vegzes'] ?? ''));

    if ($kezdes === '' || $vegzes === '') {
        return false;   // hiányos sor – inkább szabadnak látszik, mint hibásan foglaltnak
    }

    $most = ticky_ido_perc($ido);

    return $kezdes <= $most && $most <= $vegzes;
}

/**
 * Teremazonosító → épp folyó óra leírása.
 *
 * @param array $orak         A nap összes aktív órája (nyers adatbázis sorok).
 * @param array $tanar_nevek  tanar_id => rövid név
 * @param string $ido         "ÓÓ:PP"
 * @param array|null $terem_szuro  Ha megadjuk, csak ezeket a terem_id-kat vesszük
 *                                 figyelembe (a korábbi in.(...) szuro megfeleloje).
 * @return array terem_id => ['tanar','osztaly','tantargy','kezdes','vegzes']
 */
function ticky_terem_foglalt_map(
    array $orak,
    array $tanar_nevek,
    string $ido,
    ?array $terem_szuro = null
): array {
    $engedett = $terem_szuro === null ? null : array_flip(array_map('strval', $terem_szuro));
    $foglalt = [];

    foreach ($orak as $ora) {
        $terem_id = (string) ($ora['terem_id'] ?? '');
        if ($terem_id === '') {
            continue;
        }
        if ($engedett !== null && !isset($engedett[$terem_id])) {
            continue;
        }
        if (!ticky_ora_tart_e($ora, $ido)) {
            continue;
        }

        $tanar_id = (string) ($ora['tanar_id'] ?? '');

        $foglalt[$terem_id] = [
            'tanar'    => $tanar_nevek[$tanar_id] ?? '?',
            'osztaly'  => $ora['osztaly'] ?? null,
            'tantargy' => $ora['tantargy'] ?? null,
            'kezdes'   => ticky_ido_perc((string) ($ora['kezdes'] ?? '')),
            'vegzes'   => ticky_ido_perc((string) ($ora['vegzes'] ?? '')),
        ];
    }

    return $foglalt;
}

/**
 * tanarok tábla sorai → tanar_id => rövid név.
 */
function ticky_tanar_nev_map(array $tanarok): array
{
    $map = [];
    foreach ($tanarok as $tanar) {
        $id = (string) ($tanar['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $map[$id] = (string) ($tanar['rovid_nev'] ?? '?');
    }
    return $map;
}
