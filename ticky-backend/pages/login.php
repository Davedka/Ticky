<?php
// pages/login.php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers();


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

$safe_from = '';
if ($from !== '' && preg_match('#^/(?!/)#', $from)) {
    $safe_from = $from;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!ticky_has_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Érvénytelen CSRF token. Frissítsd az oldalt és próbáld újra.';
    } else {

        $wait = ticky_rate_limit_wait_seconds('login', 300, 8, 900);
        if ($wait > 0) {
            $error = "Túl sok sikertelen próbálkozás. Várj $wait másodpercet.";
        } else {
            $felh   = trim((string) ($_POST['felhasznalonev'] ?? ''));
            $jelszo = (string) ($_POST['jelszo'] ?? '');


            $user = null;
            try {
                $rows = sb_get('felhasznalok', [
                    'select'         => 'id,felhasznalonev,nev,szerep,aktiv,jelszo_hash',
                    'felhasznalonev' => 'eq.' . $felh,
                    'limit'          => '1',
                ], 'service');
                if (!empty($rows)) {
                    $user = $rows[0];
                }
            } catch (\Throwable $e) {
                $user = null;
            }


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

                ticky_clear_rate_limit('login');


                ticky_set_user_session((string) $user['id']);

                try {
                    sb_patch(
                        'felhasznalok',
                        ['id' => 'eq.' . (string) $user['id']],
                        [
                            'utolso_belepes'    => date('c'),
                            'utolso_belepes_ip' => ticky_client_ip(),
                        ],
                        'service'
                    );
                } catch (\Throwable $e) {

                }


                $role    = (string) $user['szerep'];
                $default = $role === 'admin' ? '/admin' : '/tester';


                $redirect = $safe_from !== '' ? $safe_from : $default;


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
  :root{ --gold:#c8972a; --gold-l:#f0c76b; }
  *{box-sizing:border-box;}
  body {
    font-family:'DM Sans',sans-serif;
    background:#04090f; color:white; min-height:100vh; margin:0;
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
  svg{display:block;}
  a:focus-visible, button:focus-visible, input:focus-visible { outline:2px solid rgba(200,151,42,.6); outline-offset:2px; border-radius:10px; }
  @media (prefers-reduced-motion: reduce){ *{animation:none!important; transition:none!important;} }

  .glass { background:rgba(255,255,255,.04); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,.08); position:relative; }
  .glass::before{content:'';position:absolute;inset:0 0 auto 0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.16),transparent);pointer-events:none;border-radius:inherit;}
  .field{position:relative;}
  .field .ic{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.32);width:16px;height:16px;pointer-events:none;}
  .inp { width:100%; border-radius:10px; border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.05); padding:12px 14px 12px 40px; color:white; font-size:14px; font-family:'DM Sans',sans-serif; transition:border-color .18s, box-shadow .18s; }
  .inp:focus { outline:none; border-color:rgba(200,151,42,.45); box-shadow:0 0 0 3px rgba(200,151,42,.08); }
  .inp::placeholder{color:rgba(255,255,255,.3);}
  .btn-gold { background:linear-gradient(135deg,#c8972a,#9e6d1e); color:white;
    border-radius:10px; padding:12px; font-weight:600; font-size:14px; width:100%;
    border:none; cursor:pointer; transition:transform .15s, box-shadow .18s; display:flex;align-items:center;justify-content:center;gap:8px; }
  .btn-gold:hover { transform:translateY(-1px); box-shadow:0 10px 26px -10px rgba(200,151,42,.6); }
  .btn-gold svg{width:15px;height:15px;}
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .err { color:#fda4af; background:rgba(244,63,94,.10); border:1px solid rgba(244,63,94,.24);
    padding:11px 14px; border-radius:10px; font-size:13px; display:flex;align-items:flex-start;gap:8px; }
  .err svg{width:16px;height:16px;flex-shrink:0;margin-top:1px;}
  .lbl{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.16em;color:rgba(255,255,255,.4);margin-bottom:8px;}
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
          <label class="lbl">Felhasználónév</label>
          <div class="field">
            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" name="felhasznalonev" class="inp" autocomplete="username" autofocus required>
          </div>
        </div>

        <div>
          <label class="lbl">Jelszó</label>
          <div class="field">
            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="jelszo" class="inp" autocomplete="current-password" required>
          </div>
        </div>

        <?php if ($error !== ''): ?>
          <p class="err">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
          </p>
        <?php endif; ?>

        <button type="submit" class="btn-gold">
          Belépés
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
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
