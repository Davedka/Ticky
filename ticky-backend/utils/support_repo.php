<?php
// ticky-backend/utils/support_repo.php
//
// Support üzenetek: validáció és adatbázis-műveletek.
//
// A validáció szándékosan TISZTA függvény (nincs benne hálózat, nincs benne
// $_POST olvasás), így tesztelhető a tests/php/support_repo.test.php-bol.
// Az adatbázis-műveletek külön függvényekben vannak.

declare(strict_types=1);

require_once __DIR__ . '/../config/supabase.php';

const TICKY_SUPPORT_TABLA = 'support_uzenetek';

/** A /support oldal kategória-választójával EGYEZO kulcsok. */
const TICKY_SUPPORT_KATEGORIAK = [
    'hiba'     => 'Hibajelentés',
    'kerdes'   => 'Általános kérdés',
    'terem'    => 'Terem / órarend probléma',
    'tanar'    => 'Tanár adat módosítás',
    'osztaly'  => 'Osztály / osztálynézet probléma',
    'javaslat' => 'Fejlesztési javaslat',
    'egyeb'    => 'Egyéb',
];

const TICKY_SUPPORT_STATUSZOK = ['uj', 'folyamatban', 'lezart'];

// Hosszkorlátok. A felső határ nem esztétika: enélkül egy szkript
// megabájtos üzenettel tölthetné a Supabase 500 MB-os kvótáját.
const TICKY_SUPPORT_NEV_MIN      = 2;
const TICKY_SUPPORT_NEV_MAX      = 80;
const TICKY_SUPPORT_EMAIL_MAX    = 160;
const TICKY_SUPPORT_UZENET_MIN   = 10;
const TICKY_SUPPORT_UZENET_MAX   = 4000;

/**
 * Egysoros mezo tisztítása.
 *
 * A sortörés kiszedése nem kozmetika: a nevet és az email címet email
 * fejlécbe írjuk, ott egy beszúrt \r\n fejléc-injekció lenne.
 */
function ticky_support_egysoros(string $ertek): string
{
    $ertek = str_replace(["\r", "\n", "\t"], ' ', $ertek);
    $ertek = preg_replace('/\s+/u', ' ', $ertek) ?? $ertek;

    return trim($ertek);
}

/** Többsoros mezo tisztítása: a sortörés marad, a vezérlokarakterek nem. */
function ticky_support_tobbsoros(string $ertek): string
{
    $ertek = str_replace("\r\n", "\n", $ertek);
    $ertek = str_replace("\r", "\n", $ertek);
    $ertek = preg_replace('/[^\P{C}\n]+/u', '', $ertek) ?? $ertek;

    return trim($ertek);
}

/**
 * Beküldött adatok validálása.
 *
 * @param array $adat nyers bemenet: nev, email, kategoria, uzenet, weboldal
 * @return array{ok: bool, hibak: array<string,string>, adat: array<string,string>}
 */
function ticky_support_validalas(array $adat): array
{
    $hibak = [];

    $nev       = ticky_support_egysoros((string) ($adat['nev'] ?? ''));
    $email     = ticky_support_egysoros((string) ($adat['email'] ?? ''));
    $kategoria = ticky_support_egysoros((string) ($adat['kategoria'] ?? ''));
    $uzenet    = ticky_support_tobbsoros((string) ($adat['uzenet'] ?? ''));

    // Honeypot: valódi felhasználó nem látja ezt a mezőt, tehát nem tölti ki.
    // Ha ki van töltve, robot küldte. Nem mondjuk meg neki, hogy lebukott.
    $csapda = ticky_support_egysoros((string) ($adat['weboldal'] ?? ''));
    if ($csapda !== '') {
        return ['ok' => false, 'hibak' => ['weboldal' => 'Automatikus küldés.'], 'adat' => []];
    }

    $nev_hossz = mb_strlen($nev, 'UTF-8');
    if ($nev_hossz < TICKY_SUPPORT_NEV_MIN) {
        $hibak['nev'] = 'A név legalább ' . TICKY_SUPPORT_NEV_MIN . ' karakter legyen.';
    } elseif ($nev_hossz > TICKY_SUPPORT_NEV_MAX) {
        $hibak['nev'] = 'A név legfeljebb ' . TICKY_SUPPORT_NEV_MAX . ' karakter lehet.';
    }

    if ($email === '') {
        $hibak['email'] = 'Az email cím kötelező.';
    } elseif (mb_strlen($email, 'UTF-8') > TICKY_SUPPORT_EMAIL_MAX) {
        $hibak['email'] = 'Az email cím legfeljebb ' . TICKY_SUPPORT_EMAIL_MAX . ' karakter lehet.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $hibak['email'] = 'Érvénytelen email cím.';
    }

    if (!array_key_exists($kategoria, TICKY_SUPPORT_KATEGORIAK)) {
        $hibak['kategoria'] = 'Válassz kategóriát.';
    }

    $uzenet_hossz = mb_strlen($uzenet, 'UTF-8');
    if ($uzenet_hossz < TICKY_SUPPORT_UZENET_MIN) {
        $hibak['uzenet'] = 'Az üzenet legalább ' . TICKY_SUPPORT_UZENET_MIN . ' karakter legyen.';
    } elseif ($uzenet_hossz > TICKY_SUPPORT_UZENET_MAX) {
        $hibak['uzenet'] = 'Az üzenet legfeljebb ' . TICKY_SUPPORT_UZENET_MAX . ' karakter lehet.';
    }

    if ($hibak !== []) {
        return ['ok' => false, 'hibak' => $hibak, 'adat' => []];
    }

    return [
        'ok'    => true,
        'hibak' => [],
        'adat'  => [
            'nev'       => $nev,
            'email'     => $email,
            'kategoria' => $kategoria,
            'uzenet'    => $uzenet,
        ],
    ];
}

/** A kategória kulcsból ember által olvasható címke. */
function ticky_support_kategoria_neve(string $kulcs): string
{
    return TICKY_SUPPORT_KATEGORIAK[$kulcs] ?? $kulcs;
}

/**
 * IP cím visszakövetheto, de nem visszafejtheto formában.
 *
 * Nyers IP-t nem tárolunk: a visszaélés-vizsgálathoz elég, ha két bejelentés
 * ugyanarról a gépről AZONOS hasht ad. A só a service key-bol jön, ezért a
 * hash az adatbázisból kiszivárogva sem visszakereshető.
 */
function ticky_support_ip_hash(string $ip): string
{
    if ($ip === '') {
        return '';
    }

    return substr(hash_hmac('sha256', $ip, SUPABASE_SERVICE_KEY ?: 'ticky'), 0, 32);
}

/**
 * Üzenet beszúrása.
 *
 * @return int|null az új sor azonosítója, null ha a beszúrás nem sikerült
 */
function ticky_support_beszuras(array $adat, string $ip_hash, string $user_agent): ?int
{
    $sor = [
        'nev'        => $adat['nev'],
        'email'      => $adat['email'],
        'kategoria'  => $adat['kategoria'],
        'uzenet'     => $adat['uzenet'],
        'statusz'    => 'uj',
        'kezbesites' => 'fuggoben',
        'ip_hash'    => $ip_hash !== '' ? $ip_hash : null,
        'user_agent' => $user_agent !== '' ? mb_substr($user_agent, 0, 200, 'UTF-8') : null,
    ];

    $valasz = sb_request('POST', TICKY_SUPPORT_TABLA, $sor, [], 'service');
    if (!$valasz['success']) {
        return null;
    }

    $adatok = $valasz['data'];
    $elso = is_array($adatok) && isset($adatok[0]) && is_array($adatok[0]) ? $adatok[0] : $adatok;

    return isset($elso['id']) ? (int) $elso['id'] : null;
}

/** A kézbesítés eredményének visszaírása. Nem kritikus: hibáját elnyeljük. */
function ticky_support_kezbesites_frissites(int $id, string $allapot, ?string $hiba): void
{
    sb_update(
        TICKY_SUPPORT_TABLA,
        [
            'kezbesites'      => $allapot,
            'kezbesites_hiba' => $hiba === null ? null : mb_substr($hiba, 0, 300, 'UTF-8'),
        ],
        ['id' => 'eq.' . $id],
        'service'
    );
}

/**
 * Admin lista.
 *
 * @param string $statusz 'mind' vagy a TICKY_SUPPORT_STATUSZOK egyike
 * @param int    $limit   legfeljebb ennyi sor
 */
function ticky_support_lista(string $statusz = 'mind', int $limit = 100): array
{
    $params = [
        'select' => 'id,nev,email,kategoria,uzenet,statusz,kezbesites,kezbesites_hiba,created_at,lezarva_at',
        'order'  => 'created_at.desc',
        'limit'  => (string) max(1, min($limit, 500)),
    ];

    if (in_array($statusz, TICKY_SUPPORT_STATUSZOK, true)) {
        $params['statusz'] = 'eq.' . $statusz;
    }

    $sorok = sb_get(TICKY_SUPPORT_TABLA, $params, 'service');

    return is_array($sorok) ? $sorok : [];
}

/** Nyitott (még nem lezárt) üzenetek darabszáma – a dashboard jelvényéhez. */
function ticky_support_nyitott_darab(): int
{
    $darab = sb_count(TICKY_SUPPORT_TABLA, ['statusz' => 'neq.lezart'], 'service');

    return $darab ?? 0;
}

/** Ügykezelési státusz módosítása. Csak az admin hívja. */
function ticky_support_statusz_allitas(int $id, string $statusz): bool
{
    if (!in_array($statusz, TICKY_SUPPORT_STATUSZOK, true)) {
        return false;
    }

    $valasz = sb_update(
        TICKY_SUPPORT_TABLA,
        [
            'statusz'    => $statusz,
            'lezarva_at' => $statusz === 'lezart' ? date('c') : null,
        ],
        ['id' => 'eq.' . $id],
        'service'
    );

    return (bool) $valasz['success'];
}
