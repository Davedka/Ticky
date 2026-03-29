<?php
// config/supabase.php – Supabase kapcsolat + alap HTTP hívások

define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('TZ',                   getenv('TIMEZONE')             ?: 'Europe/Budapest');

date_default_timezone_set(TZ);

/**
 * Supabase REST API hívás
 *
 * @param string $table      tábla neve
 * @param array  $params     query paraméterek (PostgREST filter szintaxis)
 * @param string $key        'anon' | 'service'
 * @return array             dekódolt JSON tömb
 */
function sb_get(string $table, array $params = [], string $key = 'anon'): array {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url    = SUPABASE_URL . '/rest/v1/' . $table;

    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", [
                'apikey: '        . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ]),
            'timeout' => 5,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return [];
    }

    return json_decode($raw, true) ?? [];
}

/**
 * Supabase RPC hívás (tárolt eljárás)
 */
function sb_rpc(string $fn, array $body = [], string $key = 'anon'): mixed {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url    = SUPABASE_URL . '/rest/v1/rpc/' . $fn;

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'apikey: '        . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]),
            'content' => json_encode($body),
            'timeout' => 5,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? json_decode($raw, true) : null;
}