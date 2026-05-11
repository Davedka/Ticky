<?php
// pages/login.php
// Felhasználói belépés (admin VAGY tester) – szerep alapján redirectol.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers();

// ─────────────────────────────────────────────────────────────
// Ha már be van lépve, ne mutassuk a formot – egyből a dashboardjára.
// ─────────────────────────────────────────────────────────────
if (function_exists('ticky_current_user')) {
    $alreadyUser = ticky_current_user();
    if (is_array($alreadyUser)) {
        $role   = (string) ($alreadyUser['szerep'] ?? 'tester');
        $target = $role === 'admin' ? '/admin' : '/tester';
        header('Location: ' . $target);
        exit;
    }
}

$csrf  = ticky_csrf_token();
$from  = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$error = '';
$wait  = 0;

// Csak relatív, biztonságos `from` URL-eket fogadunk el (XSS / open-redirect ellen)
$safe_from = '';
if ($from !== '' && preg_match('#^/(?!/)#', $from)) {
    $safe_from = $from;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF védelem
    if (!ticky_has_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Érvénytelen CSRF token. Frissítsd az oldalt és próbáld újra.';
    } else {
        // Rate limit ellenőrzés
        $wait = ticky_rate_limit_wait_seconds('login', 300, 8, 900);
        if ($wait > 0) {
            $error = "Túl sok sikertelen próbálkozás. Várj $wait másodpercet.";
        } else {
            $felh   = trim((string) ($_POST['felhasznalonev'] ?? ''));
            $jelszo = (string) ($_POST['jelszo'] ?? '');

            // Felhasználó keresése
            $user = null;
            try {
                $rows = sb_get('felhasznalok', [
                    'select'         => 'id,felhasznalonev,nev,szerep,aktiv,jelszo_hash',
                    'felhasznalonev' => 'eq.' . $felh,
                    'limit'          => '1',
                ]);
                if (!empty($rows)) {
                    $user = $rows[0];
                }
            } catch (\Throwable $e) {
                $user = null;
            }

            // Validációk
            $valid_password = $user && !empty($user['jelszo_hash'])
                ? password_verify($jelszo, (string) $user['jelszo_hash'])
                : false;

            $active        = $user && ($user['aktiv'] ?? true);
            $allowed_roles = ['admin', 'tester'];
            $valid_role    = $user && in_array((string) ($user['szerep'] ?? ''), $allowed_roles, true);

            if (!$user || !$valid_password) {
                ticky_record_rate_limit_failure('login', 300, 8, 900);
                $error = 'Hibás felhasználónév vagy jelszó.';
            } elseif (!$active) {
                ticky_record_rate_limit_failure('login', 300, 8, 900);
                $error = 'A felhasználói fiókod tiltva van. Fordulj az adminhoz.';
            } elseif (!$valid_role) {
                ticky_record_rate_limit_failure('login', 300, 8, 900);
                $error = 'A fiókod szerepköre nem engedélyezi a belépést.';
            } else {
                // ─── SIKERES LOGIN ────────────────────────────────────
                ticky_clear_rate_limit('login');
                ticky_set_user_session($user);

                // Szerep alapú default redirect:
                // - admin → /admin
                // - tester → /tester
                $role    = (string) $user['szerep'];
                $default = $role === 'admin' ? '/admin' : '/tester';

                // Ha van biztonságos `?from=` paraméter, azt használjuk
                // (pl. /admin-ról vagy /tester-ről redirectelt ide a guard)
                $redirect = $safe_from !== '' ? $safe_from : $default;

                // De tester ne tudjon /admin-ra redirecteltetni magát
                if ($role === 'tester' && str_starts_with($redirect, '/admin')) {
                    $redirect = '/tester';
                }

                header('Location: ' . $redirect);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Belépés – Ticky</title>
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" href="/favicon.png" type="image/png" sizes="64x64">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif;
    background:#04090f; color:white; min-height:100vh;
    background-image:
      radial-gradient(ellipse 70% 50% at 10% 0%, rgba(26,74,138,.4) 0%, transparent 55%),
      radial-gradient(ellipse 50% 40% at 90% 100%, rgba(200,151,42,.10) 0%, transparent 50%);
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none;
    background-image:
      linear-gradient(rgba(255,255,255,.015) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.015) 1px, transparent 1px);
    background-size:44px 44px;
  }
  .top-line { position:fixed; top:0; left:0; right:0; height:2px; z-index:50;
    background:linear-gradient(90deg, transparent, #c8972a 30%, #f0c76b 50%, #c8972a 70%, transparent);
    box-shadow:0 0 16px rgba(200,151,42,.3);
  }
  .glass { background:rgba(255,255,255,.04); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,.08); }
  .inp { width:100%; border-radius:10px; border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.05); padding:12px 14px; color:white; font-size:14px; }
  .inp:focus { outline:none; border-color:rgba(200,151,42,.45); }
  .btn-gold { background:linear-gradient(135deg,#c8972a,#9e6d1e); color:white;
    border-radius:10px; padding:12px; font-weight:600; font-size:14px; width:100%;
    border:none; cursor:pointer; transition:transform .15s; }
  .btn-gold:hover { transform:translateY(-1px); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .err { color:#fda4af; background:rgba(244,63,94,.10); border:1px solid rgba(244,63,94,.24);
    padding:10px 14px; border-radius:10px; font-size:13px; }
</style>
</head>
<body>
<div class="top-line"></div>

<main class="min-h-screen flex items-center justify-center p-4 relative z-10">
  <div class="w-full max-w-sm">

    <!-- Brand -->
    <div class="text-center mb-8">
      <a href="/" class="inline-flex items-center gap-3 text-white text-3xl font-bold" style="font-family:'Playfair Display',serif;">
        <span class="pulse inline-block w-3 h-3 rounded-full" style="background:#c8972a;box-shadow:0 0 12px #c8972a;"></span>
        Ticky
      </a>
      <p class="mt-3 text-sm" style="color:rgba(255,255,255,.45);">Belépés</p>
    </div>

    <!-- Form -->
    <div class="glass p-7 rounded-2xl">
      <form method="POST" action="/login<?= $safe_from !== '' ? ('?from=' . htmlspecialchars($safe_from, ENT_QUOTES, 'UTF-8')) : '' ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] mb-2" style="color:rgba(255,255,255,.4);">Felhasználónév</label>
          <input type="text" name="felhasznalonev" class="inp" autocomplete="username" autofocus required>
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] mb-2" style="color:rgba(255,255,255,.4);">Jelszó</label>
          <input type="password" name="jelszo" class="inp" autocomplete="current-password" required>
        </div>

        <?php if ($error !== ''): ?>
          <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <button type="submit" class="btn-gold">Belépés</button>
      </form>

      <p class="text-center text-xs mt-5" style="color:rgba(255,255,255,.3);">
        Admin vagy tester fiókkal tudsz belépni.
      </p>
    </div>

    <p class="text-center text-xs mt-5" style="color:rgba(255,255,255,.18);">
      <a href="/" style="color:#f0c76b;">← Vissza a főoldalra</a>
    </p>
  </div>
</main>

</body>
</html>
