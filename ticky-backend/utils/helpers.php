<?php
// utils/helpers.php - Közös segédfüggvények

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

function mai_nap(): int {
    $n = (int) date('N');
    return $n <= 5 ? $n : 0;
}

function aktualis_ido(): string {
    return date('H:i');
}

function merge_consecutive_orak(array $orak): array {
    if (count($orak) <= 1) {
        return $orak;
    }

    $merged = [$orak[0]];
    for ($i = 1; $i < count($orak); $i++) {
        $last = &$merged[count($merged) - 1];
        $curr = $orak[$i];
        $lp = (int) ($last['ora_sorszam'] ?? 0);
        $cp = (int) ($curr['ora_sorszam'] ?? 0);

        if (
            $lp > 0
            && $cp === $lp + 1
            && ($curr['tantargy'] ?? '') === ($last['tantargy'] ?? '')
            && ($curr['terem'] ?? '') === ($last['terem'] ?? '')
            && ($curr['osztaly'] ?? '') === ($last['osztaly'] ?? '')
            && ($curr['tanar'] ?? '') === ($last['tanar'] ?? '')
        ) {
            $last['vegzes'] = $curr['vegzes'];
        } else {
            $merged[] = $curr;
        }

        unset($last);
    }

    return $merged;
}

function ticky_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwarded_proto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded_proto !== '') {
        $parts = explode(',', $forwarded_proto);
        return strtolower(trim($parts[0])) === 'https';
    }

    return false;
}

function ticky_current_origin_parts(): array {
    $forwarded_host = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    $host = $forwarded_host !== '' ? $forwarded_host : trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $scheme = ticky_is_https() ? 'https' : 'http';

    if ($host === '') {
        return ['scheme' => $scheme, 'host' => '', 'port' => null, 'origin' => null];
    }

    $origin = $scheme . '://' . $host;
    return [
        'scheme' => $scheme,
        'host' => strtolower((string) parse_url($origin, PHP_URL_HOST)),
        'port' => parse_url($origin, PHP_URL_PORT),
        'origin' => $origin,
    ];
}

function ticky_request_origin_is_same_host(): bool {
    $current = ticky_current_origin_parts();
    if (($current['host'] ?? '') === '') {
        return true;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value === '') {
            continue;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if ($host === '' || $host !== $current['host']) {
            return false;
        }

        $port = parse_url($value, PHP_URL_PORT);
        if ($current['port'] !== null && $port !== null && (int) $port !== (int) $current['port']) {
            return false;
        }

        return true;
    }

    return true;
}

function private_response_headers(): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function admin_path_value(): string {
    static $path = null;

    if ($path !== null) {
        return $path;
    }

    $raw = trim((string) (getenv('ADMIN_PATH') ?: ($_ENV['ADMIN_PATH'] ?? '')));
    $raw = trim($raw, " \t\n\r\0\x0B\"'");
    $raw = preg_replace('/\\\\[nrt]/', '', $raw) ?? $raw;
    if ($raw === '') {
        $path = '/admin';
        return $path;
    }

    if (preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $raw)) {
        $parsed_path = parse_url($raw, PHP_URL_PATH);
        $raw = is_string($parsed_path) ? $parsed_path : '';
    }

    $raw = preg_replace('/[?#].*$/', '', $raw) ?? '';
    $raw = preg_replace('/[^A-Za-z0-9_\\/-]/', '', $raw) ?? '';
    $raw = preg_replace('#/+#', '/', $raw) ?? '';
    $raw = trim($raw, '/');

    $path = $raw === '' ? '/admin' : '/' . $raw;
    return $path;
}

function admin_url(array $query = []): string {
    $path = admin_path_value();
    if (empty($query)) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

function admin_path_matches_request(string $uri): bool {
    $admin_path = admin_path_value();
    $normalized_uri = $uri === '/' ? '/' : rtrim($uri, '/');
    $normalized_admin = $admin_path === '/' ? '/' : rtrim($admin_path, '/');
    return $normalized_uri === $normalized_admin;
}

function admin_password(): string {
    static $password = null;

    if ($password !== null) {
        return $password;
    }

    $password = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
    return $password;
}

function admin_is_configured(): bool {
    return admin_password() !== '';
}

function admin_cookie_name(): string {
    return 'ticky_admin_session';
}

function admin_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => ticky_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ];
}

function admin_visibility_cookie_name(): string {
    return 'ticky_admin_visible';
}

function admin_visibility_cookie_options(int $expires): array {
    return admin_cookie_options($expires);
}

function admin_user_agent_hash(): string {
    return substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 24);
}

function admin_sign_token(int $expires_at): string {
    return hash_hmac('sha256', 'ticky_admin|' . $expires_at . '|' . admin_user_agent_hash(), admin_password());
}

function admin_issue_token(?int $expires_at = null): string {
    $expires_at ??= time() + (8 * 3600);
    return $expires_at . '.' . admin_sign_token($expires_at);
}

function admin_set_auth_cookie(): void {
    $expires_at = time() + (8 * 3600);
    setcookie(admin_cookie_name(), admin_issue_token($expires_at), admin_cookie_options($expires_at));
}

function admin_issue_visibility_token(?int $expires_at = null): string {
    $expires_at ??= time() + (30 * 24 * 3600);
    return $expires_at . '.' . hash_hmac('sha256', 'ticky_admin_visible|' . $expires_at . '|' . admin_user_agent_hash(), admin_password());
}

function admin_set_visibility_cookie(): void {
    $expires_at = time() + (30 * 24 * 3600);
    setcookie(
        admin_visibility_cookie_name(),
        admin_issue_visibility_token($expires_at),
        admin_visibility_cookie_options($expires_at)
    );
}

function admin_clear_auth_cookie(): void {
    setcookie(admin_cookie_name(), '', admin_cookie_options(time() - 3600));
}

function admin_clear_visibility_cookie(): void {
    setcookie(admin_visibility_cookie_name(), '', admin_visibility_cookie_options(time() - 3600));
}

function admin_is_authenticated(): bool {
    if (!admin_is_configured()) {
        return false;
    }

    $cookie = trim((string) ($_COOKIE[admin_cookie_name()] ?? ''));
    if ($cookie === '' || !str_contains($cookie, '.')) {
        return false;
    }

    [$expires_raw, $signature] = explode('.', $cookie, 2);
    if (!ctype_digit($expires_raw) || $signature === '') {
        return false;
    }

    $expires_at = (int) $expires_raw;
    if ($expires_at < time()) {
        return false;
    }

    return hash_equals(admin_sign_token($expires_at), $signature);
}

function admin_visibility_cookie_is_valid(): bool {
    if (!admin_is_configured()) {
        return false;
    }

    $cookie = trim((string) ($_COOKIE[admin_visibility_cookie_name()] ?? ''));
    if ($cookie === '' || !str_contains($cookie, '.')) {
        return false;
    }

    [$expires_raw, $signature] = explode('.', $cookie, 2);
    if (!ctype_digit($expires_raw) || $signature === '') {
        return false;
    }

    $expires_at = (int) $expires_raw;
    if ($expires_at < time()) {
        return false;
    }

    $expected = hash_hmac('sha256', 'ticky_admin_visible|' . $expires_at . '|' . admin_user_agent_hash(), admin_password());
    return hash_equals($expected, $signature);
}

function admin_can_see_ui(): bool {
    return admin_is_authenticated() || admin_visibility_cookie_is_valid() || ticky_current_user_is_admin();
}

function ticky_user_cookie_name(): string {
    return 'ticky_user_session';
}

function ticky_user_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => ticky_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ];
}

function ticky_session_signing_key(): string {
    static $key = null;

    if ($key !== null) {
        return $key;
    }

    $parts = array_filter([
        admin_password(),
        SUPABASE_SERVICE_KEY,
        SUPABASE_ANON_KEY,
        'ticky_user_session',
    ], static fn($value) => $value !== '');

    $key = implode('|', $parts);
    return $key === '' ? 'ticky_user_session_fallback' : $key;
}

function ticky_issue_user_session_token(string $user_id, ?int $expires_at = null): string {
    $expires_at ??= time() + (12 * 3600);
    $nonce = bin2hex(random_bytes(32));
    $payload = $nonce . '|' . $user_id . '|' . $expires_at;
    $signature = hash_hmac('sha256', $payload, ticky_session_signing_key());
    return base64_encode($payload) . '.' . $signature;
}

function ticky_set_user_session(string $user_id): void {
    $expires_at = time() + (12 * 3600);
    setcookie(
        ticky_user_cookie_name(),
        ticky_issue_user_session_token($user_id, $expires_at),
        ticky_user_cookie_options($expires_at)
    );
}

function ticky_clear_user_session(): void {
    setcookie(ticky_user_cookie_name(), '', ticky_user_cookie_options(time() - 3600));
}

function ticky_read_user_session_payload(): ?array {
    $cookie = trim((string) ($_COOKIE[ticky_user_cookie_name()] ?? ''));
    if ($cookie === '' || !str_contains($cookie, '.')) {
        return null;
    }

    [$payload_b64, $signature] = explode('.', $cookie, 2);
    if ($payload_b64 === '' || $signature === '') {
        return null;
    }

    $payload = base64_decode($payload_b64, true);
    if ($payload === false) {
        return null;
    }

    $expected = hash_hmac('sha256', $payload, ticky_session_signing_key());
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $parts = explode('|', $payload);
    if (count($parts) !== 3) {
        return null;
    }

    [$nonce, $user_id, $expires_raw] = $parts;
    if (!preg_match('/^[a-f0-9]{64}$/', $nonce)) {
        return null;
    }
    if (!preg_match('/^[0-9a-f-]{36}$/i', $user_id)) {
        return null;
    }
    if (!ctype_digit($expires_raw)) {
        return null;
    }

    $expires_at = (int) $expires_raw;
    if ($expires_at < time()) {
        return null;
    }

    return [
        'user_id' => $user_id,
        'expires_at' => $expires_at,
    ];
}

function ticky_current_user(): ?array {
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $payload = ticky_read_user_session_payload();
    if ($payload === null) {
        return null;
    }

    $rows = sb_get('felhasznalok', [
        'id' => 'eq.' . $payload['user_id'],
        'aktiv' => 'eq.true',
        'select' => 'id,felhasznalonev,nev,szerep,aktiv',
    ], 'service');

    if (empty($rows)) {
        return null;
    }

    $user = $rows[0];
    return $user;
}

function ticky_current_user_is_admin(): bool {
    $user = ticky_current_user();
    return is_array($user) && (($user['szerep'] ?? '') === 'admin');
}

function ticky_is_admin_authed(): bool {
    return admin_is_authenticated() || ticky_current_user_is_admin();
}

function require_admin_api_request(array $allowed_methods): void {
    handle_cors(array_merge($allowed_methods, ['OPTIONS']), ['Content-Type', 'X-Ticky-Admin']);
    private_response_headers();

    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed_methods, true)) {
        json_error('Érvénytelen kérés', 405);
    }

    if (!ticky_is_admin_authed()) {
        json_error('Hozzáférés megtagadva', 401);
    }

    if ((string) ($_SERVER['HTTP_X_TICKY_ADMIN'] ?? '') !== '1') {
        json_error('Érvénytelen admin kérés', 400);
    }

    if (!ticky_request_origin_is_same_host()) {
        json_error('Tiltott origin', 403);
    }
}

function render_time_sync_bootstrap(): void {
    $server_epoch_ms = (int) round(microtime(true) * 1000);
    $timezone = TZ;
    ?>
<script>
(() => {
  const timezone = <?= json_encode($timezone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const serverEpochMs = <?= $server_epoch_ms ?>;
  const bootPerfNow = typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : null;
  const bootClientNow = Date.now();

  const partsFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const hmFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });
  const hmsFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const weekdayMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

  function nowMs() {
    if (bootPerfNow !== null && typeof performance !== 'undefined' && typeof performance.now === 'function') {
      return serverEpochMs + (performance.now() - bootPerfNow);
    }
    return serverEpochMs + (Date.now() - bootClientNow);
  }

  function nowDate() {
    return new Date(nowMs());
  }

  function nowParts() {
    const raw = {};
    for (const part of partsFormatter.formatToParts(nowDate())) {
      if (part.type !== 'literal') raw[part.type] = part.value;
    }
    return {
      year: Number(raw.year),
      month: Number(raw.month),
      day: Number(raw.day),
      weekday: weekdayMap[raw.weekday] ?? 0,
      hour: Number(raw.hour),
      minute: Number(raw.minute),
      second: Number(raw.second),
    };
  }

  function nowMinutes() {
    const parts = nowParts();
    return parts.hour * 60 + parts.minute;
  }

  window.TickyTime = {
    timezone,
    nowDate,
    nowMs,
    nowParts,
    nowMinutes,
    weekdayIndex() {
      return nowParts().weekday;
    },
    schoolDayIndex() {
      const day = nowParts().weekday;
      return (day === 0 || day === 6) ? 1 : day;
    },
    formatHM() {
      return hmFormatter.format(nowDate());
    },
    formatHMS() {
      return hmsFormatter.format(nowDate());
    },
  };
})();
</script>
    <?php
}

function render_assistant_widget(array $options = []): void {
    $widget_id = $options['id'] ?? 'ticky-assistant-widget';
    $title = $options['title'] ?? 'AI asszisztens';
    $eyebrow = $options['eyebrow'] ?? 'Ticky Assist';
    $empty_state = $options['empty_state'] ?? 'Írj egy kérdést az aktuális oldalhoz kapcsolódóan.';
    $placeholder = $options['placeholder'] ?? 'Írd be a kérdésed...';
    $context = $options['context'] ?? 'general';
    $submit_label = $options['submit_label'] ?? 'Küldés';
    $context_data = is_array($options['context_data'] ?? null) ? $options['context_data'] : [];

    $config = [
        'widgetId' => $widget_id,
        'title' => $title,
        'eyebrow' => $eyebrow,
        'emptyState' => $empty_state,
        'placeholder' => $placeholder,
        'context' => $context,
        'contextData' => $context_data,
        'submitLabel' => $submit_label,
    ];
    ?>
<style>
  .ta-shell, .ta-shell * { box-sizing:border-box; }
  .ta-shell {
    position:fixed; right:18px; bottom:18px; z-index:160; display:flex; flex-direction:column;
    align-items:flex-end; gap:10px; font-family:'DM Sans',sans-serif; color:white;
  }
  .ta-launcher {
    display:flex; align-items:center; gap:10px; border:none; cursor:pointer; width:auto; margin-top:0;
    padding:12px 14px; border-radius:18px; color:white;
    background:linear-gradient(160deg, rgba(200,151,42,.22), rgba(11,46,89,.92));
    border:1px solid rgba(255,255,255,.12); box-shadow:0 18px 45px rgba(6,15,30,.35);
    backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }
  .ta-launcher:hover {
    transform:translateY(-2px);
    border-color:rgba(240,199,107,.42);
    box-shadow:0 22px 55px rgba(6,15,30,.42);
  }
  .ta-launcher-badge {
    width:38px; height:38px; border-radius:14px; display:grid; place-items:center; flex-shrink:0;
    background:linear-gradient(145deg, rgba(240,199,107,.22), rgba(255,255,255,.06));
    border:1px solid rgba(255,255,255,.08); font-family:'Playfair Display',serif; font-size:18px; font-weight:700;
  }
  .ta-launcher-copy { display:flex; flex-direction:column; align-items:flex-start; text-align:left; }
  .ta-launcher-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.45); font-weight:700; }
  .ta-launcher-title { font-size:13px; color:white; font-weight:600; line-height:1.2; }
  .ta-panel {
    width:min(380px, calc(100vw - 28px)); max-height:min(72vh, 620px); display:flex; flex-direction:column;
    overflow:hidden; border-radius:24px; background:rgba(10,24,44,.88); border:1px solid rgba(255,255,255,.10);
    box-shadow:0 30px 70px rgba(6,15,30,.50); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
    opacity:0; pointer-events:none; transform:translateY(12px) scale(.98); transform-origin:bottom right;
    transition:opacity .18s ease, transform .18s ease;
  }
  .ta-shell.open .ta-panel { opacity:1; pointer-events:auto; transform:translateY(0) scale(1); }
  .ta-panel-head {
    padding:16px 16px 14px; border-bottom:1px solid rgba(255,255,255,.08);
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
  }
  .ta-panel-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.35); font-weight:700; }
  .ta-panel-title { font-family:'Playfair Display',serif; font-size:24px; line-height:1.08; color:white; margin-top:6px; }
  .ta-panel-close {
    width:36px; height:36px; border:none; cursor:pointer; margin-top:0; border-radius:12px;
    background:rgba(255,255,255,.06); color:rgba(255,255,255,.55); display:grid; place-items:center;
  }
  .ta-panel-close:hover { background:rgba(255,255,255,.10); color:white; }
  .ta-messages {
    flex:1; overflow:auto; padding:16px; display:flex; flex-direction:column; gap:12px; min-height:160px;
  }
  .ta-empty {
    padding:14px 15px; border-radius:18px; border:1px dashed rgba(255,255,255,.12);
    background:rgba(255,255,255,.03); color:rgba(255,255,255,.58); font-size:12px; line-height:1.6;
  }
  .ta-msg { display:flex; }
  .ta-msg.user { justify-content:flex-end; }
  .ta-bubble {
    max-width:100%; border-radius:18px; padding:14px 14px 13px; border:1px solid rgba(255,255,255,.09);
    box-shadow:0 10px 28px rgba(6,15,30,.22);
  }
  .ta-msg.assistant .ta-bubble { background:rgba(255,255,255,.05); }
  .ta-msg.user .ta-bubble { background:linear-gradient(135deg, rgba(200,151,42,.20), rgba(26,74,138,.22)); }
  .ta-bubble-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.38); font-weight:700; margin-bottom:8px; }
  .ta-bubble-text { font-size:13px; line-height:1.6; color:rgba(255,255,255,.88); }
  .ta-cards { display:grid; gap:8px; margin-top:10px; }
  .ta-card { padding:12px; border-radius:15px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); }
  .ta-card-eyebrow { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(240,199,107,.76); font-weight:700; margin-bottom:4px; }
  .ta-card-title { font-family:'Playfair Display',serif; font-size:17px; line-height:1.12; color:white; }
  .ta-card-meta { margin-top:4px; font-size:12px; color:rgba(255,255,255,.68); }
  .ta-card-detail { margin-top:4px; font-size:11px; color:rgba(255,255,255,.45); }
  .ta-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
  .ta-pill {
    display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:8px 12px; width:auto; margin-top:0;
    border:1px solid rgba(255,255,255,.11); background:rgba(255,255,255,.05); color:rgba(255,255,255,.82);
    font-size:12px; font-weight:500; text-decoration:none; cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease;
  }
  .ta-pill:hover { transform:translateY(-1px); background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.18); color:white; }
  .ta-pill.primary { background:rgba(200,151,42,.14); border-color:rgba(200,151,42,.26); color:#f0c76b; }
  .ta-panel-foot { padding:14px 16px 16px; border-top:1px solid rgba(255,255,255,.08); }
  .ta-composer { display:flex; flex-direction:column; gap:10px; }
  .ta-input {
    width:100%; resize:none; min-height:52px; max-height:136px; border-radius:18px; border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.05); color:white; padding:14px 15px; outline:none; font-size:13px; line-height:1.5;
  }
  .ta-input::placeholder { color:rgba(255,255,255,.30); }
  .ta-input:focus { border-color:rgba(200,151,42,.35); box-shadow:0 0 0 4px rgba(200,151,42,.08); }
  .ta-foot-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .ta-foot-copy { font-size:11px; color:rgba(255,255,255,.34); line-height:1.5; }
  .ta-submit {
    display:inline-flex; align-items:center; justify-content:center; height:44px; width:auto; margin-top:0; cursor:pointer;
    border:none; border-radius:14px; padding:0 18px; font-size:13px; font-weight:700; color:#06101d;
    background:linear-gradient(135deg, #f0c76b, #c8972a);
  }
  .ta-submit:disabled { opacity:.55; cursor:not-allowed; }
  .ta-loading { display:inline-flex; align-items:center; gap:8px; color:rgba(255,255,255,.55); font-size:12px; }
  .ta-loading span { width:7px; height:7px; border-radius:999px; background:rgba(255,255,255,.35); animation:taPulse 1s infinite; }
  .ta-loading span:nth-child(2) { animation-delay:.15s; }
  .ta-loading span:nth-child(3) { animation-delay:.3s; }
  @keyframes taPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.45;transform:scale(.75)} }
  @media (max-width: 900px) {
    .ta-shell { right:12px; bottom:12px; }
    .ta-launcher-copy { display:none; }
    .ta-launcher { border-radius:16px; padding:12px; }
    .ta-panel { width:min(380px, calc(100vw - 24px)); max-height:min(68vh, 560px); }
  }
</style>
<div id="<?= htmlspecialchars($widget_id, ENT_QUOTES, 'UTF-8') ?>" class="ta-shell">
  <aside class="ta-panel" aria-hidden="true">
    <div class="ta-panel-head">
      <div>
        <div class="ta-panel-kicker"><?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="ta-panel-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <button class="ta-panel-close" type="button" data-ta-close aria-label="Bezárás">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="ta-messages" data-ta-messages>
      <div class="ta-empty" data-ta-empty><?= htmlspecialchars($empty_state, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="ta-panel-foot">
      <form class="ta-composer" data-ta-form>
        <textarea class="ta-input" rows="1" data-ta-input placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"></textarea>
        <div class="ta-foot-row">
          <span class="ta-foot-copy">Az aktuális oldalhoz igazodva válaszol.</span>
          <button class="ta-submit" type="submit" data-ta-submit><?= htmlspecialchars($submit_label, ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </aside>
  <button class="ta-launcher" type="button" data-ta-open>
    <span class="ta-launcher-badge">AI</span>
    <span class="ta-launcher-copy">
      <span class="ta-launcher-kicker"><?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="ta-launcher-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
    </span>
  </button>
</div>
<script>
(() => {
  const config = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const root = document.getElementById(config.widgetId);
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  const openButton = root.querySelector('[data-ta-open]');
  const closeButton = root.querySelector('[data-ta-close]');
  const panel = root.querySelector('.ta-panel');
  const messagesEl = root.querySelector('[data-ta-messages]');
  const emptyEl = root.querySelector('[data-ta-empty]');
  const formEl = root.querySelector('[data-ta-form]');
  const inputEl = root.querySelector('[data-ta-input]');
  const submitEl = root.querySelector('[data-ta-submit]');

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function autoGrow() {
    inputEl.style.height = '52px';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 136) + 'px';
  }

  function safeHref(value) {
    const href = String(value ?? '').trim();
    if (!href.startsWith('/') || href.startsWith('//')) {
      return '#';
    }
    return href;
  }

  function syncEmpty() {
    const hasMessages = messagesEl.querySelector('.ta-msg') !== null;
    if (emptyEl) {
      emptyEl.style.display = hasMessages ? 'none' : 'block';
    }
  }

  function openPanel() {
    root.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => inputEl.focus(), 60);
  }

  function closePanel() {
    root.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
  }

  function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function appendUser(text) {
    const row = document.createElement('div');
    row.className = 'ta-msg user';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">Te</div>
        <div class="ta-bubble-text">${esc(text)}</div>
      </div>
    `;
    messagesEl.appendChild(row);
    syncEmpty();
    scrollToBottom();
  }

  function appendLoading() {
    const row = document.createElement('div');
    row.className = 'ta-msg assistant';
    row.dataset.loading = '1';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">${esc(config.eyebrow)}</div>
        <div class="ta-loading"><span></span><span></span><span></span> Válasz készül</div>
      </div>
    `;
    messagesEl.appendChild(row);
    syncEmpty();
    scrollToBottom();
  }

  function removeLoading() {
    messagesEl.querySelector('[data-loading="1"]')?.remove();
  }

  function appendAssistant(payload) {
    const cards = Array.isArray(payload.cards) ? payload.cards : [];
    const actions = Array.isArray(payload.actions) ? payload.actions : [];
    const cardsHtml = cards.length ? `
      <div class="ta-cards">
        ${cards.map(card => `
          <div class="ta-card">
            ${card.eyebrow ? `<div class="ta-card-eyebrow">${esc(card.eyebrow)}</div>` : ''}
            ${card.title ? `<div class="ta-card-title">${esc(card.title)}</div>` : ''}
            ${card.meta ? `<div class="ta-card-meta">${esc(card.meta)}</div>` : ''}
            ${card.detail ? `<div class="ta-card-detail">${esc(card.detail)}</div>` : ''}
          </div>
        `).join('')}
      </div>
    ` : '';
    const actionsHtml = actions.length ? `
      <div class="ta-actions">
        ${actions.map((action, index) => `
          <a class="ta-pill ${index === 0 ? 'primary' : ''}" href="${esc(safeHref(action.href || '#'))}">${esc(action.label || 'Megnyitás')}</a>
        `).join('')}
      </div>
    ` : '';

    const row = document.createElement('div');
    row.className = 'ta-msg assistant';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">${esc(config.eyebrow)}</div>
        <div class="ta-bubble-text">${esc(payload.reply || 'Most nem tudtam válaszolni erre.')}</div>
        ${cardsHtml}
        ${actionsHtml}
      </div>
    `;
    messagesEl.appendChild(row);
    syncEmpty();
    scrollToBottom();
  }

  async function submitPrompt(text) {
    const message = String(text || '').trim();
    if (!message) return;

    openPanel();
    appendUser(message);
    inputEl.value = '';
    autoGrow();
    submitEl.disabled = true;
    appendLoading();

    try {
      const response = await fetch('/api/assistant', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message,
          context: config.context,
          contextData: config.contextData,
        })
      });
      const payload = await response.json();
      removeLoading();
      appendAssistant(payload);
    } catch (error) {
      removeLoading();
      appendAssistant({
        reply: 'Most nem sikerült elérnem az asszisztenst. Próbáld meg újra egy pillanat múlva.'
      });
    } finally {
      submitEl.disabled = false;
    }
  }

  openButton?.addEventListener('click', () => {
    if (root.classList.contains('open')) {
      closePanel();
      return;
    }
    openPanel();
  });
  closeButton?.addEventListener('click', closePanel);

  formEl?.addEventListener('submit', event => {
    event.preventDefault();
    submitPrompt(inputEl.value);
  });

  inputEl?.addEventListener('input', autoGrow);
  inputEl?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      formEl.requestSubmit();
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && root.classList.contains('open')) {
      closePanel();
    }
  });

  window.openTickyAssistant = prompt => {
    openPanel();
    if (typeof prompt === 'string' && prompt.trim()) {
      inputEl.value = prompt.trim();
      autoGrow();
    }
    inputEl.focus();
  };

  syncEmpty();
  autoGrow();
})();
</script>
    <?php
}

function ido_kozott(string $ido, string $start, string $end): bool {
    return $ido >= $start && $ido <= $end;
}

function match_route(string $pattern, string $uri): array|false {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

function handle_cors(array $methods = ['GET', 'POST', 'PATCH', 'OPTIONS'], array $headers = ['Content-Type', 'Authorization']): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (!ticky_request_origin_is_same_host()) {
            http_response_code(403);
            exit;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        header('Access-Control-Max-Age: 600');
        http_response_code(204);
        exit;
    }
}
