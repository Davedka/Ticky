<?php
define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('TZ',                   getenv('TIMEZONE')             ?: 'Europe/Budapest');


const SB_CONNECT_TIMEOUT = 3;   // TCP + TLS felépítés másodpercben
const SB_TIMEOUT         = 8;   // olvasás felso korlátja
const SB_TIMEOUT_IRAS    = 30;  // import/publikálás: sok sor, lassabb válasz
date_default_timezone_set(TZ);

function sb_request($method, $path, $body = null, $params = [], $key = 'service') {
    $apiKey = ($key === 'service') ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');
    if (!empty($params)) $url .= '?' . str_replace('%3D', '=', http_build_query($params));

    $ch = curl_init($url);
    $headers = ["apikey: $apiKey", "Authorization: Bearer $apiKey", "Content-Type: application/json", "Prefer: return=representation"];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, SB_CONNECT_TIMEOUT);
    // Az írás (POST/PATCH/DELETE) sok sort mozgathat, annak több ido kell.
    curl_setopt($ch, CURLOPT_TIMEOUT, $method === 'GET' ? SB_TIMEOUT : SB_TIMEOUT_IRAS);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['success' => ($httpCode >= 200 && $httpCode < 300), 'data' => json_decode($response, true), 'error' => $response];
}

function sb_get($table, $params = [], $key = 'anon') { 
    $res = sb_request('GET', $table, null, $params, $key); 
    return $res['success'] ? $res['data'] : []; 
}

function sb_update($table, $body, $filters, $key = 'service') { 
    return sb_request('PATCH', $table, $body, $filters, $key); 
}

// ─────────────────────────────────────────────────────────────────
// Nagy találathalmazok
//
// A PostgREST felső korlátot tesz az egy válaszban visszaadott sorokra
// (Supabase-en tipikusan 1000). Ha ezt figyelmen kívül hagyjuk, a lekérdezés
// NEM hibázik, csak csendben kevesebb sort ad vissza – ebből lesz a
// "2273 sorból 1000" jellegű hibás számolás. Ezért:
//   - darabszámhoz sosem töltünk le sorokat, hanem count=exact fejlécet kérünk
//   - ha tényleg minden sor kell, lapozunk
// ─────────────────────────────────────────────────────────────────

const SB_PAGE_SIZE     = 1000;
const SB_MAX_PAGES     = 100;   // 100 000 sor felső határ, védelem végtelen ciklus ellen

/**
 * Sorok darabszáma sorok letöltése nélkül.
 *
 * @param array $filters PostgREST szűrők, pl. ['aktiv' => 'eq.true']
 * @return int|null null, ha a lekérdezés hibázott (pl. nincs ilyen tábla)
 */
function sb_count(string $table, array $filters = [], string $key = 'service'): ?int {
    $apiKey = ($key === 'service') ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $params = array_merge($filters, ['select' => 'id']);
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($table, '/')
         . '?' . str_replace('%3D', '=', http_build_query($params));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,   // HEAD: nem jön vissza egyetlen sor sem
        CURLOPT_HEADER         => true,
        CURLOPT_CONNECTTIMEOUT => SB_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => SB_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            "apikey: $apiKey",
            "Authorization: Bearer $apiKey",
            'Prefer: count=exact',
        ],
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }

    // "Content-Range: 0-999/2273" vagy "*/2273"
    if (preg_match('#content-range:\s*[^/]+/(\d+)#i', (string) $response, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}

/**
 * Minden sor lekérése lapozással.
 *
 * Akkor használd, ha tényleg az összes sorra szükség van (pl. egyedi
 * osztálykódok gyűjtése). A hívó által megadott limit/offset figyelmen kívül
 * marad, mert a lapozást ez a függvény intézi.
 */
function sb_get_all(string $table, array $params = [], string $key = 'anon'): array {
    unset($params['limit'], $params['offset']);

    $rows = [];
    for ($page = 0; $page < SB_MAX_PAGES; $page++) {
        $batch = sb_get($table, array_merge($params, [
            'limit'  => (string) SB_PAGE_SIZE,
            'offset' => (string) ($page * SB_PAGE_SIZE),
        ]), $key);

        if (!is_array($batch) || $batch === []) {
            break;
        }

        foreach ($batch as $row) {
            $rows[] = $row;
        }

        // Az utolsó oldal rövidebb a lapméretnél.
        if (count($batch) < SB_PAGE_SIZE) {
            break;
        }
    }

    return $rows;
}
