<?php
// config/supabase.php

define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('TZ',                   getenv('TIMEZONE')             ?: 'Europe/Budapest');

date_default_timezone_set(TZ);

function sb_get(string $table, array $params = [], string $key = 'anon'): array {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url    = SUPABASE_URL . '/rest/v1/' . $table;
    if ($params) $url .= '?' . http_build_query($params);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "apikey: $apiKey\r\nAuthorization: Bearer $apiKey\r\nContent-Type: application/json",
            'timeout' => 5,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? (json_decode($raw, true) ?: []) : [];
}

/**
 * JAVÍTOTT UPDATE: Kezeli a hibaüzeneteket is
 */
function sb_update(string $table, array $body, array $filters, string $key = 'service'): array {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    // Fontos: a http_build_query-t kézzel korrigáljuk, mert a Supabase eq.érték formátumot vár
    $query = str_replace('%3D', '=', http_build_query($filters)); 
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?' . $query;

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'PATCH',
            'header'  => "apikey: $apiKey\r\nAuthorization: Bearer $apiKey\r\nContent-Type: application/json\r\nPrefer: return=minimal",
            'content' => json_encode($body),
            'ignore_errors' => true, // Így látjuk a hibaüzenetet is, ha 400-as hibát kapunk
            'timeout' => 5,
        ],
    ]);

    $raw = file_get_contents($url, false, $ctx);
    $status_line = $http_response_header[0];
    
    if (strpos($status_line, '200') !== false || strpos($status_line, '204') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $raw ?: 'Hálózati hiba'];
}

function sb_rpc(string $fn, array $body = [], string $key = 'anon'): mixed {
    $apiKey = $key === 'service' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url = SUPABASE_URL . '/rest/v1/rpc/' . $fn;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "apikey: $apiKey\r\nAuthorization: Bearer $apiKey\r\nContent-Type: application/json",
            'content' => json_encode($body),
            'timeout' => 5,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? json_decode($raw, true) : null;
}
