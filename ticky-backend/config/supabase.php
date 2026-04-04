<?php
// config/supabase.php - cURL ALAPÚ VERZIÓ

define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('TZ',                   getenv('TIMEZONE')             ?: 'Europe/Budapest');

date_default_timezone_set(TZ);

/**
 * Univerzális cURL hívó függvény
 */
function sb_request(string $method, string $path, array $body = null, array $params = [], string $key = 'service') {
    $apiKey = ($key === 'service') ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');
    
    if (!empty($params)) {
        $url .= '?' . str_replace('%3D', '=', http_build_query($params));
    }

    $ch = curl_init($url);
    $headers = [
        "apikey: $apiKey",
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['success' => false, 'error' => "cURL hiba: $error"];
    
    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }

    return ['success' => false, 'error' => "HTTP $httpCode", 'details' => $data];
}

// Régi függvények megtartása az összeférhetőség miatt, de az új motorral
function sb_get($table, $params = [], $key = 'anon') {
    $res = sb_request('GET', $table, null, $params, $key);
    return $res['success'] ? $res['data'] : [];
}

function sb_update($table, $body, $filters, $key = 'service') {
    return sb_request('PATCH', $table, $body, $filters, $key);
}
