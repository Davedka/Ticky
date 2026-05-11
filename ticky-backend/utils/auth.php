<?php
// =============================================================
//  utils/auth.php
//  Session-alapú authentikáció a felhasznalok táblán keresztül.
//  Cseréld le a régi ADMIN_PATH alapú "secret URL" megoldást
//  erre. A SUPABASE_SERVICE_KEY továbbra is server-side only!
// =============================================================

require_once __DIR__ . '/../config/supabase.php';
// supabase_query($table, $method, $payload, $filter) függvényt
// feltételezem itt – a tiéd már létezik a config/supabase.php-ban.

const TICKY_COOKIE_NAME    = 'ticky_session';
const TICKY_SESSION_DAYS   = 7;
const TICKY_MAX_LOGIN_FAIL = 5;   // 15 perc alatt

// -------------------------------------------------------------
// LOGIN
// -------------------------------------------------------------
function ticky_login(string $felhasznalonev, string $jelszo): array {
    $ip = $_SERVER['REMOTE_ADDR']     ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $azonosito = $felhasznalonev . ':' . ($ip ?? 'unknown');

    // 1) Throttle check
    $sikertelen = supabase_rpc('sikertelen_loginok_15_perc', [
        'p_azonosito' => $azonosito,
    ]);
    if ($sikertelen >= TICKY_MAX_LOGIN_FAIL) {
        ticky_log_naplo(null, 'login_blocked', [
            'felhasznalonev' => $felhasznalonev,
            'ok'             => 'too_many_attempts',
        ], $ip, $ua);
        return ['ok' => false, 'hiba' => 'Túl sok próbálkozás. Próbáld 15 perc múlva.'];
    }

    // 2) Felhasználó lekérdezése
    $user = supabase_query('felhasznalok', 'GET', null, [
        'felhasznalonev' => 'eq.' . $felhasznalonev,
        'aktiv'          => 'eq.true',
    ])[0] ?? null;

    // 3) Jelszó ellenőrzés (constant-time, password_verify ezt teszi)
    $ok = $user && password_verify($jelszo, $user['jelszo_hash']);

    // 4) Kísérlet rögzítése
    supabase_query('login_kiserletek', 'POST', [
        'azonosito' => $azonosito,
        'sikeres'   => $ok,
        'ip_cim'    => $ip,
    ]);

    if (!$ok) {
        // FONTOS: ugyanaz a hibaüzenet, akár nincs ilyen user,
        // akár rossz a jelszó. Nehogy enumerálható legyen.
        ticky_log_naplo($user['id'] ?? null, 'login_fail', [
            'felhasznalonev' => $felhasznalonev,
        ], $ip, $ua);
        return ['ok' => false, 'hiba' => 'Hibás felhasználónév vagy jelszó.'];
    }

    // 5) Session létrehozása
    $token      = bin2hex(random_bytes(32));   // 64 hex char, ez megy a cookie-ba
    $token_hash = hash('sha256', $token);      // ez megy a DB-be
    $lejarat    = (new DateTimeImmutable("+" . TICKY_SESSION_DAYS . " days"))
                    ->format(DateTime::ATOM);

    supabase_query('munkamenetek', 'POST', [
        'felhasznalo_id' => $user['id'],
        'token_hash'     => $token_hash,
        'lejarat'        => $lejarat,
        'ip_cim'         => $ip,
        'user_agent'     => $ua,
    ]);

    // 6) Cookie beállítása
    setcookie(TICKY_COOKIE_NAME, $token, [
        'expires'  => time() + TICKY_SESSION_DAYS * 86400,
        'path'     => '/',
        'secure'   => true,            // CSAK HTTPS
        'httponly' => true,            // JS nem fér hozzá
        'samesite' => 'Lax',           // CSRF védelem alap szintje
    ]);

    // 7) Utolsó belépés frissítése + napló
    supabase_query('felhasznalok', 'PATCH', [
        'utolso_belepes'    => date(DateTime::ATOM),
        'utolso_belepes_ip' => $ip,
    ], ['id' => 'eq.' . $user['id']]);

    ticky_log_naplo($user['id'], 'login_success', null, $ip, $ua);

    return ['ok' => true, 'user' => $user];
}

// -------------------------------------------------------------
// SESSION ELLENŐRZÉS – minden védett oldalon ezt kell hívni
// -------------------------------------------------------------
function ticky_aktualis_user(): ?array {
    $token = $_COOKIE[TICKY_COOKIE_NAME] ?? null;
    if (!$token) return null;

    $token_hash = hash('sha256', $token);

    $session = supabase_query('munkamenetek', 'GET', null, [
        'token_hash'  => 'eq.' . $token_hash,
        'visszavonva' => 'eq.false',
        'lejarat'     => 'gt.' . date(DateTime::ATOM),
    ])[0] ?? null;

    if (!$session) return null;

    // Frissítjük az utolsó aktivitást (de csak ha >5 perce volt utoljára,
    // hogy ne legyen minden kérésnél DB write)
    if (strtotime($session['utolso_aktivitas']) < time() - 300) {
        supabase_query('munkamenetek', 'PATCH', [
            'utolso_aktivitas' => date(DateTime::ATOM),
        ], ['id' => 'eq.' . $session['id']]);
    }

    return supabase_query('felhasznalok', 'GET', null, [
        'id'    => 'eq.' . $session['felhasznalo_id'],
        'aktiv' => 'eq.true',
    ])[0] ?? null;
}

// -------------------------------------------------------------
// JOGOSULTSÁG GUARD-OK – tedd minden védett oldal tetejére
// -------------------------------------------------------------
function ticky_megkovetel_login(): array {
    $user = ticky_aktualis_user();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    return $user;
}

function ticky_megkovetel_admin(): array {
    $user = ticky_megkovetel_login();
    if ($user['szerep'] !== 'admin') {
        ticky_log_naplo($user['id'], 'jogosultsag_megtagadva', [
            'kert_oldal' => $_SERVER['REQUEST_URI'] ?? null,
            'szerep'     => $user['szerep'],
        ], $_SERVER['REMOTE_ADDR'] ?? null,
           $_SERVER['HTTP_USER_AGENT'] ?? null);
        http_response_code(403);
        exit('403 – Nincs jogosultságod.');
    }
    return $user;
}

function ticky_megkovetel_admin_vagy_tester(): array {
    $user = ticky_megkovetel_login();
    if (!in_array($user['szerep'], ['admin','tester'], true)) {
        http_response_code(403);
        exit('403 – Nincs jogosultságod.');
    }
    return $user;
}

// -------------------------------------------------------------
// LOGOUT
// -------------------------------------------------------------
function ticky_logout(): void {
    $token = $_COOKIE[TICKY_COOKIE_NAME] ?? null;
    if ($token) {
        $token_hash = hash('sha256', $token);
        supabase_query('munkamenetek', 'PATCH', [
            'visszavonva'     => true,
            'visszavonas_oka' => 'logout',
        ], ['token_hash' => 'eq.' . $token_hash]);
    }
    setcookie(TICKY_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path'    => '/',
    ]);
}

// -------------------------------------------------------------
// AUDIT NAPLÓ
// -------------------------------------------------------------
function ticky_log_naplo(?string $user_id, string $esemeny,
                          ?array $reszletek = null,
                          ?string $ip = null,
                          ?string $ua = null): void {
    supabase_query('naplo', 'POST', [
        'felhasznalo_id' => $user_id,
        'esemeny'        => $esemeny,
        'reszletek'      => $reszletek ? json_encode($reszletek) : null,
        'ip_cim'         => $ip,
        'user_agent'     => $ua,
    ]);
}

// -------------------------------------------------------------
// CSRF TOKEN – minden írást végző formra kell
// -------------------------------------------------------------
function ticky_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ticky_csrf_ellenoriz(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}
