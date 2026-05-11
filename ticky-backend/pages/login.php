<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers(true);

// Ha már be van lépve, irányítsuk át
$existing_user = ticky_current_user();
if (is_array($existing_user)) {
    header('Location: ' . (($existing_user['szerep'] ?? '') === 'admin' ? '/admin' : '/'));
    exit;
}

$hiba = null;
$csrf = ticky_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF check
    if (!ticky_has_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $hiba = 'Érvénytelen CSRF token. Töltsd újra az oldalt.';
    } else {
        // 2. Rate limit: 5 sikertelen / 15 perc → 15 perc tiltás
        $wait = ticky_rate_limit_wait_seconds('user_login', 900, 5, 900);
        if ($wait > 0) {
            $hiba = 'Túl sok sikertelen próbálkozás. Várj ' . $wait . ' másodpercet.';
        } else {
            $username = trim((string) ($_POST['felhasznalonev'] ?? ''));
            $password = (string) ($_POST['jelszo'] ?? '');

            if ($username === '' || $password === '') {
                $hiba = 'Add meg a felhasználónevet és a jelszót.';
            } else {
                // 3. User lookup
                $rows = sb_get('felhasznalok', [
                    'felhasznalonev' => 'eq.' . $username,
                    'aktiv'          => 'eq.true',
                    'select'         => 'id,felhasznalonev,nev,szerep,jelszo_hash',
                ], 'service');

                $user = !empty($rows) ? $rows[0] : null;

                // 4. Password check (constant-time)
                if ($user && password_verify($password, (string) ($user['jelszo_hash'] ?? ''))) {
                    // SIKER
                    ticky_clear_rate_limit('user_login');
                    ticky_set_user_session((string) $user['id']);

                    $redirect = (($user['szerep'] ?? '') === 'admin') ? '/admin' : '/';
                    header('Location: ' . $redirect);
                    exit;
                }

                // SIKERTELEN – nem áruljuk el hogy létezik-e a user
                ticky_record_rate_limit_failure('user_login', 900, 5, 900);
                $hiba = 'Hibás felhasználónév vagy jelszó.';
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
<link rel="icon" type="image/png" href="/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body{font-family:'DM Sans',sans-serif;background:#04090f;color:#fff;min-height:100vh;
    background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),
                     radial-gradient(ellipse 50% 40% at 90% 100%,rgba(200,151,42,.10) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;
    background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);
    background-size:44px 44px;}
  .top{position:fixed;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);
    box-shadow:0 0 16px rgba(200,151,42,.3);z-index:50;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);}
  .inp{width:100%;border-radius:10px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);padding:12px 14px;color:#fff;font-size:14px;}
  .inp:focus{outline:none;border-color:rgba(200,151,42,.45);box-shadow:0 0 0 3px rgba(200,151,42,.08);}
  .inp::placeholder{color:rgba(255,255,255,.30);}
  .btn-gold{background:linear-gradient(135deg,#c8972a,#9e6d1e);color:#fff;font-weight:700;padding:12px;border-radius:10px;width:100%;font-size:14px;}
  .btn-gold:hover{background:linear-gradient(135deg,#d9a83b,#b07e2a);}
</style>
</head>
<body>
<div class="top"></div>

<main class="min-h-screen flex items-center justify-center p-4 relative z-10">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <a href="/" class="inline-flex items-center gap-3 text-white text-3xl font-bold" style="font-family:'Playfair Display',serif;">
        <span class="inline-block w-3 h-3 rounded-full" style="background:#c8972a;box-shadow:0 0 10px #c8972a;"></span>
        Ticky
      </a>
      <p class="text-sm text-white/45 mt-3">Belépés</p>
    </div>

    <div class="glass p-8 rounded-2xl">
      <?php if ($hiba): ?>
        <div class="bg-rose-900/40 border border-rose-700 text-rose-200 px-3 py-2 rounded text-sm mb-4">
          <?= htmlspecialchars($hiba, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Felhasználónév</label>
          <input name="felhasznalonev" class="inp" autofocus required autocomplete="username">
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Jelszó</label>
          <input name="jelszo" type="password" class="inp" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-gold">Belépés</button>
      </form>
    </div>

    <p class="text-center text-xs text-white/30 mt-6">
      <a href="/" class="hover:text-white/60">← Vissza a főoldalra</a>
    </p>
  </div>
</main>
</body>
</html>
