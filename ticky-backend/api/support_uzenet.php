<?php
// ticky-backend/api/support_uzenet.php
// POST /api/support/uzenet   (application/json)
//
// A /support oldal űrlapja ide küld. A sorrend szándékos:
//   1. visszaélés-védelem (rate limit + honeypot + same-origin)
//   2. validáció
//   3. MENTÉS adatbázisba      ← ez a lépés nem hibázhat némán
//   4. email továbbítás        ← best-effort, a válasz nem múlik rajta
//
// Korábban ez az oldal `mailto:` linkre navigált. Az nem küld levelet, csak
// átadja a böngészo levelezojének – beállított levelezo nélkül (iskolai gép,
// mobil, webes Gmail) NEM TÖRTÉNT SEMMI, miközben az űrlap sikert jelzett.

declare(strict_types=1);

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/support_repo.php';
require_once __DIR__ . '/../utils/support_mail.php';

handle_cors(['POST', 'OPTIONS']);
send_security_headers(true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_error('Csak POST kérés engedélyezett', 405);
}

// Az űrlap a saját oldalunkról jön; idegen oldalról nem fogadunk beküldést.
ticky_require_same_origin_request();

// Óránként 5 üzenet IP-nként. A blokk 30 perc: elég ahhoz, hogy egy szkript
// ne tudja teleírni a Supabase kvótát, és rövid ahhoz, hogy egy valódi diák
// ne ragadjon kint egy napra.
const TICKY_SUPPORT_ABLAK       = 3600;
const TICKY_SUPPORT_MAX_KULDES  = 5;
const TICKY_SUPPORT_BLOKK       = 1800;

ticky_require_rate_limit_json(
    'support_uzenet',
    TICKY_SUPPORT_ABLAK,
    TICKY_SUPPORT_MAX_KULDES,
    TICKY_SUPPORT_BLOKK,
    'Túl sok üzenetet küldtél rövid idő alatt. Próbáld újra később, vagy írj közvetlenül a support címre.'
);

$nyers = file_get_contents('php://input') ?: '';
$adat  = json_decode($nyers, true);
if (!is_array($adat)) {
    json_error('Hibás kérés formátum', 400);
}

$ellenorzes = ticky_support_validalas($adat);
if (!$ellenorzes['ok']) {
    // A csapda mezo találatát a keretbe számoljuk: azt csak robot tölti ki,
    // és enélkül korlátlanul próbálkozhatna. A sima elgépelést NEM számoljuk –
    // ezek a kérések nem írnak adatbázist, és egy ügyetlenül kitöltött űrlap
    // nem zárhatja ki fél órára a diákot.
    if (isset($ellenorzes['hibak']['weboldal'])) {
        ticky_record_rate_limit_failure(
            'support_uzenet',
            TICKY_SUPPORT_ABLAK,
            TICKY_SUPPORT_MAX_KULDES,
            TICKY_SUPPORT_BLOKK
        );
    }

    json_response([
        'ok'     => false,
        'kod'    => 'VALIDACIOS_HIBA',
        'uzenet' => 'Az űrlap hiányosan van kitöltve.',
        'hibak'  => $ellenorzes['hibak'],
    ], 422);
}

$uzenet = $ellenorzes['adat'];

// ── Mentés: innentol az üzenet nem veszhet el ────────────────────
$id = ticky_support_beszuras(
    $uzenet,
    ticky_support_ip_hash(ticky_client_ip()),
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

if ($id === null) {
    json_response([
        'ok'     => false,
        'kod'    => 'MENTESI_HIBA',
        'uzenet' => 'Az üzenetet nem sikerült elmenteni. Írj közvetlenül a '
            . TICKY_SUPPORT_MAIL_ALAP_CIMZETT . ' címre.',
    ], 502);
}

ticky_record_rate_limit_failure(
    'support_uzenet',
    TICKY_SUPPORT_ABLAK,
    TICKY_SUPPORT_MAX_KULDES,
    TICKY_SUPPORT_BLOKK
);

// ── Továbbítás emailben: ha elbukik, az üzenet akkor is megvan ───
$kezbesites = ticky_support_mail_kuld($uzenet, $id);
ticky_support_kezbesites_frissites($id, $kezbesites['statusz'], $kezbesites['hiba']);

json_response([
    'ok'         => true,
    'azonosito'  => $id,
    'kezbesites' => $kezbesites['statusz'],
    'uzenet'     => 'Köszönjük, megkaptuk az üzenetet. Hamarosan válaszolunk.',
]);
