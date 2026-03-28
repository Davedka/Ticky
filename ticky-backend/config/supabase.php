<?php
// config/supabase.php – Supabase kapcsolat + alap HTTP hívások

define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('TZ',                   getenv('TIMEZONE')             ?: 'Europe/Budapest');

date_default_timezone_set(TZ);

function sb_request(
    string $method,
    string $path,
    array $query = [],
    mixed $body = null,
    string $key = 'anon',
    array $extra_headers = []
): string|false {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url = SUPABASE_URL . $path;

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];

    if (strtoupper($method) === 'GET') {
        $headers[] = 'Accept: application/json';
    }

    foreach ($extra_headers as $header) {
        $headers[] = $header;
    }

    $options = [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headers),
        'timeout' => 5,
    ];

    if ($body !== null) {
        $options['content'] = json_encode($body);
    }

    $ctx = stream_context_create(['http' => $options]);
    return @file_get_contents($url, false, $ctx);
}

/**
 * Supabase REST API hívás
 *
 * @param string $table      tábla neve
 * @param array  $params     query paraméterek (PostgREST filter szintaxis)
 * @param string $key        'anon' | 'service'
 * @return array             dekódolt JSON tömb
 */
function sb_get(string $table, array $params = [], string $key = 'anon'): array {
    $raw = sb_request('GET', '/rest/v1/' . $table, $params, null, $key);
    if ($raw === false) {
        return [];
    }

    return json_decode($raw, true) ?? [];
}

/**
 * Supabase RPC hívás (tárolt eljárás)
 */
function sb_rpc(string $fn, array $body = [], string $key = 'anon'): mixed {
    $raw = sb_request('POST', '/rest/v1/rpc/' . $fn, [], $body, $key);
    return $raw !== false ? json_decode($raw, true) : null;
}

function sb_patch(string $table, array $filters, array $data, string $key = 'service'): bool {
    if (empty($filters)) {
        return false;
    }

    $raw = sb_request(
        'PATCH',
        '/rest/v1/' . $table,
        $filters,
        $data,
        $key,
        ['Prefer: return=minimal']
    );

    return $raw !== false;
}
