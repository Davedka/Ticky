<?php
// api/admin_github_sync.php
// POST /api/admin/github_sync
//
// A tanarok.js file letöltése GitHub-ról és lokális mentés.
// Ezáltal a következő import a friss GitHub-os állapotot fogja használni.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

if (!admin_can_see_ui()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['hiba' => 'Bejelentkezés szükséges']);
    exit;
}

require_admin_api_request(['GET', 'POST']);

// ── Konfiguráció ────────────────────────────────────────
function github_config(): array {
    return [
        'owner'  => trim((string) (getenv('GITHUB_OWNER')  ?: 'davedka')),
        'repo'   => trim((string) (getenv('GITHUB_REPO')   ?: 'Ticky')),
        'branch' => trim((string) (getenv('GITHUB_BRANCH') ?: 'main')),
        'file'   => trim((string) (getenv('GITHUB_FILE')   ?: 'tanárok.js')),
        'token'  => trim((string) (getenv('GITHUB_TOKEN')  ?: '')),
    ];
}

$config = github_config();

// ── GET: csak információ visszaadása (sync nélkül) ──────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $local_path = ticky_source_path();
    $local_size = is_file($local_path) ? filesize($local_path) : 0;
    $local_mtime = is_file($local_path) ? filemtime($local_path) : 0;
    $local_sha1 = is_file($local_path) ? sha1_file($local_path) : '';

    json_response([
        'config' => [
            'owner'  => $config['owner'],
            'repo'   => $config['repo'],
            'branch' => $config['branch'],
            'file'   => $config['file'],
            'has_token' => $config['token'] !== '',
        ],
        'local' => [
            'path'   => $local_path,
            'exists' => is_file($local_path),
            'size'   => $local_size,
            'mtime'  => $local_mtime ? date('Y-m-d H:i:s', $local_mtime) : null,
            'sha1'   => $local_sha1,
        ],
        'raw_url' => sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            rawurlencode($config['owner']),
            rawurlencode($config['repo']),
            rawurlencode($config['branch']),
            rawurlencode($config['file'])
        ),
    ]);
}

// ── POST: tényleges szinkronizálás ──────────────────────
ticky_require_fresh_admin_auth();
set_time_limit(60);

$raw_url = sprintf(
    'https://raw.githubusercontent.com/%s/%s/%s/%s',
    rawurlencode($config['owner']),
    rawurlencode($config['repo']),
    rawurlencode($config['branch']),
    rawurlencode($config['file'])
);

// Optional branch/commit override from body
$body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
if (!empty($body['branch']) && preg_match('/^[A-Za-z0-9._\/-]{1,80}$/', $body['branch'])) {
    $raw_url = sprintf(
        'https://raw.githubusercontent.com/%s/%s/%s/%s',
        rawurlencode($config['owner']),
        rawurlencode($config['repo']),
        rawurlencode((string) $body['branch']),
        rawurlencode($config['file'])
    );
}

// ── HTTP letöltés ──────────────────────────────────────
$ch = curl_init($raw_url);
$headers = ['User-Agent: Ticky-Admin-Sync/1.0', 'Accept: text/plain'];
if ($config['token'] !== '') {
    $headers[] = 'Authorization: token ' . $config['token'];
}
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => $headers,
]);
$content = curl_exec($ch);
$status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err     = curl_error($ch);
curl_close($ch);

if ($content === false || $status !== 200) {
    json_error('GitHub letöltés sikertelen (HTTP ' . $status . '): ' . ($err ?: $raw_url), 502);
}

if (strlen($content) < 100) {
    json_error('A letöltött fájl gyanúsan kicsi (' . strlen($content) . ' byte). Ellenőrizd a branch/file neveket.', 502);
}

// Alapszintű validáció: SCHEDULE_DATA kell hogy legyen benne
if (!str_contains($content, 'SCHEDULE_DATA')) {
    json_error('A letöltött fájl nem tartalmaz SCHEDULE_DATA-t. Rossz forrás?', 502);
}

// ── Mentés ──────────────────────────────────────────────
$target = ticky_source_path();
$old_sha1 = is_file($target) ? sha1_file($target) : '';
$new_sha1 = sha1($content);
$changed  = $old_sha1 !== $new_sha1;

// Backup
$backup_path = null;
if (is_file($target)) {
    $backup_path = $target . '.bak';
    @copy($target, $backup_path);
}

$bytes = file_put_contents($target, $content);
if ($bytes === false) {
    json_error('Nem sikerült írni a fájlt: ' . $target, 500);
}

// Cache invalidálás: az opcache reset ha be van kapcsolva
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($target, true);
}

json_response([
    'ok'           => true,
    'url'          => $raw_url,
    'target'       => $target,
    'bytes'        => $bytes,
    'old_sha1'     => $old_sha1,
    'new_sha1'     => $new_sha1,
    'changed'      => $changed,
    'backup_path'  => $backup_path,
    'timestamp'    => date('Y-m-d H:i:s'),
]);
