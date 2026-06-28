<?php
// api/osztaly_het.php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';
require_once __DIR__ . '/../utils/tanarok_source.php';
$aktiv_szunet = ticky_aktiv_szunet_nev();

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = match_route('/api/osztaly/{kod}/het', $uri);

if ($params === false || empty($params['kod'])) {
    json_error('Hiányzó osztály kód', 400);
}

$kod = trim(urldecode((string) $params['kod']));
if ($kod === '' || !preg_match('/^[\p{L}\p{N}\s._\/-]{1,64}$/u', $kod)) {
    json_error('Érvénytelen osztály kód', 400);
}

// Szünet idején is mutathatjuk a hetet (csak az aktuális napra van értelme a "szünet" üzenetnek).
if (function_exists('ticky_source_class_lessons_for_week')) {
    try {
        $result = ticky_source_class_lessons_for_week($kod);
        if ($result !== null) {
            json_response([
                'osztaly' => $result['osztaly'],
                'het'     => $result['het'],
            ]);
        }
    } catch (\Throwable $e) {
        // Fallback alább
    }
}

json_response(['osztaly' => $kod, 'het' => []]);
