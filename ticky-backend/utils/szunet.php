<?php
// utils/szunet.php – Szünetek Supabase-ből lekérve
// Include: require_once __DIR__ . '/../utils/szunet.php';

function _szunet_get_all(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $url = SUPABASE_URL . '/rest/v1/szunetek?order=kezdet.asc';
    $key = SUPABASE_SERVICE_KEY;
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "apikey: $key\r\nAuthorization: Bearer $key\r\nAccept: application/json",
        'timeout' => 3,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false) {
        $rows = json_decode($raw, true) ?? [];
        if (is_array($rows)) { $cache = $rows; return $cache; }
    }

    // Fallback: hardcoded (ha a tábla még nem létezik)
    $cache = [
        ['id'=>'fallback-1','nev'=>'Őszi szünet',   'kezdet'=>'2025-10-27','vege'=>'2025-10-31'],
        ['id'=>'fallback-2','nev'=>'Téli szünet',    'kezdet'=>'2025-12-22','vege'=>'2026-01-02'],
        ['id'=>'fallback-3','nev'=>'Tavaszi szünet', 'kezdet'=>'2026-04-02','vege'=>'2026-04-13'],
    ];
    return $cache;
}

function aktiv_szunet(): ?array {
    $ma = date('Y-m-d');
    foreach (_szunet_get_all() as $sz) {
        if ($ma >= $sz['kezdet'] && $ma <= $sz['vege']) return $sz;
    }
    return null;
}

function is_szunet(): bool {
    return aktiv_szunet() !== null;
}

function osszes_szunet(): array {
    return _szunet_get_all();
}
