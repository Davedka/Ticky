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

/**
 * Common favicon, metadata, and shared UI assets for all frontend pages.
 */
function ticky_head_assets_html(
    string $title,
    string $description = 'Digitalis terem- es tanarkereso rendszer.'
): string {
    $title_attr = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $desc_attr  = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<meta name="description" content="{$desc_attr}">
<meta name="theme-color" content="#060f1e">
<meta name="application-name" content="Ticky">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Ticky">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Ticky">
<meta property="og:title" content="{$title_attr}">
<meta property="og:description" content="{$desc_attr}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{$title_attr}">
<meta name="twitter:description" content="{$desc_attr}">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="shortcut icon" href="/favicon.svg">
<link rel="apple-touch-icon" href="/favicon.svg">
<link rel="mask-icon" href="/favicon.svg" color="#c8972a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/assets/ui-upgrade.css">
HTML;
}

/**
 * Echo helper for shared UI head assets.
 */
function ticky_head_assets(
    string $title,
    string $description = 'Digitalis terem- es tanarkereso rendszer.'
): void {
    echo ticky_head_assets_html($title, $description);
}
