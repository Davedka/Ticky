<?php
// ticky-backend/utils/support_mail.php
//
// Support üzenet továbbítása emailben.
//
// Miért HTTP API és nem mail() vagy SMTP:
//   - mail(): a php:8.2-cli konténerben nincs MTA, a hívás csendben elbukik.
//   - SMTP: saját kliens kellene (függoséget nem veszünk fel), ÉS nem
//     ellenőrzött, hogy a Render ingyenes tier kifelé engedi-e az SMTP
//     portokat. A kimenő HTTPS-ről viszont tudjuk, hogy működik: az egész
//     alkalmazás azon beszél a Supabase-szel.
//
// A kézbesítés BEST-EFFORT. Az üzenet ekkor már benne van az adatbázisban,
// tehát egy sikertelen küldés nem adatvesztés, csak késleltetett értesítés.

declare(strict_types=1);

require_once __DIR__ . '/support_repo.php';

const TICKY_SUPPORT_MAIL_TIMEOUT = 8;
const TICKY_SUPPORT_MAIL_ALAP_CIMZETT = 'tickysupport@gmail.com';

/**
 * Email küldés beállításai environment változókból.
 *
 * SUPPORT_MAIL_API_KEY – Resend API kulcs (re_...). Enélkül nincs küldés.
 * SUPPORT_MAIL_FROM    – feladó, pl. "Ticky <support@ticky.hu>"
 * SUPPORT_MAIL_TO      – címzett, alapértelmezésben a support postafiók
 */
function ticky_support_mail_beallitas(): array
{
    $api_kulcs = trim((string) getenv('SUPPORT_MAIL_API_KEY'));
    $felado    = trim((string) getenv('SUPPORT_MAIL_FROM'));
    $cimzett   = trim((string) getenv('SUPPORT_MAIL_TO'));

    return [
        'api_kulcs' => $api_kulcs,
        // A Resend saját tesztdomainje azonnal működik, saját domain nélkül is.
        'felado'    => $felado !== '' ? $felado : 'Ticky Support <onboarding@resend.dev>',
        'cimzett'   => $cimzett !== '' ? $cimzett : TICKY_SUPPORT_MAIL_ALAP_CIMZETT,
        'bekapcsolva' => $api_kulcs !== '',
    ];
}

/**
 * A levél tárgya és törzse.
 *
 * Tiszta függvény, hogy tesztelhető legyen hálózat nélkül.
 *
 * @return array{targy: string, szoveg: string}
 */
function ticky_support_mail_torzs(array $uzenet, ?int $id = null): array
{
    $kategoria = ticky_support_kategoria_neve((string) ($uzenet['kategoria'] ?? ''));

    $targy = '[Ticky Support] ' . $kategoria . ' – ' . (string) ($uzenet['nev'] ?? '');

    $sorok = [
        'Feladó:    ' . (string) ($uzenet['nev'] ?? ''),
        'Email:     ' . (string) ($uzenet['email'] ?? ''),
        'Kategória: ' . $kategoria,
        'Érkezett:  ' . date('Y-m-d H:i'),
    ];

    if ($id !== null) {
        $sorok[] = 'Azonosító: #' . $id;
    }

    $sorok[] = '';
    $sorok[] = str_repeat('─', 40);
    $sorok[] = '';
    $sorok[] = (string) ($uzenet['uzenet'] ?? '');
    $sorok[] = '';
    $sorok[] = str_repeat('─', 40);
    $sorok[] = 'A válasz gomb közvetlenül a feladónak ír.';

    return [
        'targy'  => mb_substr($targy, 0, 180, 'UTF-8'),
        'szoveg' => implode("\n", $sorok),
    ];
}

/**
 * Levél küldése a Resend HTTP API-n keresztül.
 *
 * @return array{statusz: string, hiba: ?string}
 *         statusz: 'elkuldve' | 'sikertelen' | 'kikapcsolva'
 */
function ticky_support_mail_kuld(array $uzenet, ?int $id = null): array
{
    $beallitas = ticky_support_mail_beallitas();

    if (!$beallitas['bekapcsolva']) {
        return ['statusz' => 'kikapcsolva', 'hiba' => null];
    }

    $torzs = ticky_support_mail_torzs($uzenet, $id);

    $level = [
        'from'    => $beallitas['felado'],
        'to'      => [$beallitas['cimzett']],
        'subject' => $torzs['targy'],
        'text'    => $torzs['szoveg'],
        // Enélkül a Gmailben a "Válasz" a feladó domainjére menne, nem a
        // diáknak. Ez a mezo teszi a postafiókot ténylegesen használhatóvá.
        'reply_to' => [(string) ($uzenet['email'] ?? $beallitas['cimzett'])],
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $beallitas['api_kulcs'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($level, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    // Időkorlát nélkül egy beragadt kérés a php -S worker folyamatát fogná.
    curl_setopt($ch, CURLOPT_TIMEOUT, TICKY_SUPPORT_MAIL_TIMEOUT);

    $valasz = curl_exec($ch);
    $hibauzenet = curl_error($ch);
    $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($valasz === false) {
        return ['statusz' => 'sikertelen', 'hiba' => 'Hálózati hiba: ' . $hibauzenet];
    }

    if ($kod < 200 || $kod >= 300) {
        return ['statusz' => 'sikertelen', 'hiba' => 'HTTP ' . $kod . ': ' . substr((string) $valasz, 0, 200)];
    }

    return ['statusz' => 'elkuldve', 'hiba' => null];
}
