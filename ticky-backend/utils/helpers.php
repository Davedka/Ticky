<?php
// utils/helpers.php – Közös segédfüggvények

/**
 * JSON válasz küldése és kilépés
 */
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Hiba JSON válasz
 */
function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

/**
 * Mai nap sorszáma (1=Hétfő ... 5=Péntek, 0=hétvége)
 * Supabase-ben het_napja: 1–5
 * PHP date('N'): 1=Hétfő ... 7=Vasárnap
 */
function mai_nap(): int {
    $n = (int) date('N'); // 1=Mon, 7=Sun
    return $n <= 5 ? $n : 0;  // hétvégén 0
}

/**
 * Aktuális idő 'HH:MM' formátumban (Budapest timezone)
 */
function aktualis_ido(): string {
    return date('H:i');
}

/**
 * Idő összehasonlítás: $ido >= $start AND $ido <= $end ?
 */
function ido_kozott(string $ido, string $start, string $end): bool {
    return $ido >= $start && $ido <= $end;
}

/**
 * URL routing: egyszerű minta illesztés
 * Példa: match_route('/api/terem/{szam}', $uri) → ['szam' => '204'] vagy false
 */
function match_route(string $pattern, string $uri): array|false {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

/**
 * CORS preflight kezelés
 */
function handle_cors(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }
}