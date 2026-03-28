<?php

function request_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function request_method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function ticky_asset_version(): string {
    return '20260328-refactor';
}

function render_app_head(string $title, array $options = []): void {
    $include_tailwind = $options['tailwind'] ?? true;
    $include_dm_mono = $options['dm_mono'] ?? false;
    $extra_scripts = is_array($options['scripts'] ?? null) ? $options['scripts'] : [];

    $font_parts = [
        'family=Playfair+Display:wght@600;700',
        'family=DM+Sans:wght@300;400;500;600',
    ];

    if ($include_dm_mono) {
        $font_parts[] = 'family=DM+Mono:wght@400;500';
    }

    $font_href = 'https://fonts.googleapis.com/css2?' . implode('&', $font_parts) . '&display=swap';
    $asset_version = rawurlencode(ticky_asset_version());
    ?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="<?= htmlspecialchars((string) ($options['viewport'] ?? 'width=device-width, initial-scale=1.0'), ENT_QUOTES, 'UTF-8') ?>">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" type="image/png" href="/favicon.png?v=<?= $asset_version ?>">
<link rel="shortcut icon" href="/favicon.ico?v=<?= $asset_version ?>">
<?php if ($include_tailwind): ?>
<script src="https://cdn.tailwindcss.com"></script>
<?php endif; ?>
<link href="<?= htmlspecialchars($font_href, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php foreach ($extra_scripts as $script): ?>
<script src="<?= htmlspecialchars((string) $script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php
}

function send_default_security_headers(): void {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

function serve_static_asset_if_exists(string $base_dir, string $uri): bool {
    if ($uri === '/') {
        return false;
    }

    $requested_file = realpath($base_dir . rawurldecode($uri));
    if (
        $requested_file === false
        || !is_file($requested_file)
        || !str_starts_with($requested_file, $base_dir . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }

    if (PHP_SAPI === 'cli-server') {
        return true;
    }

    $mime_types = [
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
    ];

    $extension = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION));
    if (isset($mime_types[$extension])) {
        header('Content-Type: ' . $mime_types[$extension]);
    }

    header('Content-Length: ' . filesize($requested_file));
    readfile($requested_file);
    exit;
}

function dispatch_exact_routes(string $uri, array $routes): bool {
    if (!isset($routes[$uri])) {
        return false;
    }

    require $routes[$uri];
    return true;
}

function dispatch_pattern_routes(string $uri, array $routes): bool {
    foreach ($routes as $pattern => $file) {
        if (match_route($pattern, $uri) !== false) {
            require $file;
            return true;
        }
    }

    return false;
}

function render_not_found_page(): never {
    http_response_code(404);
    $asset_version = rawurlencode(ticky_asset_version());
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="icon" type="image/png" href="/favicon.png?v=' . $asset_version . '"><link rel="shortcut icon" href="/favicon.ico?v=' . $asset_version . '"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza a főoldalra</a></body></html>';
    exit;
}
