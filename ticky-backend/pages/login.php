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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#050b15;
    --gold:#c8972a; --gold-l:#f0c76b;
    --red:#fb7185; --red-soft:rgba(244,63,94,.1);
    --line:rgba(96,150,220,.16); --line-strong:rgba(96,150,220,.30);
    --surface:rgba(255,255,255,.025); --surface-2:rgba(255,255,255,.05);
    --border:rgba(255,255,255,.09); --border-strong:rgba(255,255,255,.17);
    --text:rgba(255,255,255,.93); --dim:rgba(255,255,255,.55); --faint:rgba(255,255,255,.34);
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);min-height:100vh;overflow-x:hidden;}
  .bp{position:fixed;inset:0;z-index:0;pointer-events:none;}
  .bp::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 55% at 12% -8%,rgba(34,86,156,.42) 0%,transparent 58%),radial-gradient(ellipse 50% 45% at 90% 108%,rgba(200,151,42,.1) 0%,transparent 55%);}
  .bp::after{content:'';position:absolute;inset:0;background-image:linear-gradient(var(--line) 1px,transparent 1px),linear-gradient(90deg,var(--line) 1px,transparent 1px),linear-gradient(var(--line-strong) 1px,transparent 1px),linear-gradient(90deg,var(--line-strong) 1px,transparent 1px);background-size:28px 28px,28px 28px,140px 140px,140px 140px;mask-image:radial-gradient(ellipse 90% 80% at 50% 40%,#000 50%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 90% 80% at 50% 40%,#000 50%,transparent 100%);opacity:.55;}
  .vig{position:fixed;inset:0;z-index:1;pointer-events:none;box-shadow:inset 0 0 220px 50px rgba(0,0,0,.6);}
  .grain{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.05;mix-blend-mode:overlay;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");}
  .reg{position:fixed;z-index:2;pointer-events:none;width:26px;height:26px;opacity:.5;}
  .reg::before,.reg::after{content:'';position:absolute;background:var(--gold);}
  .reg::before{left:50%;top:0;bottom:0;width:1px;transform:translateX(-.5px);}
  .reg::after{top:50%;left:0;right:0;height:1px;transform:translateY(-.5px);}
  .reg.tl{top:22px;left:22px;} .reg.tr{top:22px;right:22px;} .reg.bl{bottom:22px;left:22px;} .reg.br{bottom:22px;right:22px;}
  .topline{position:fixed;top:0;left:0;right:0;height:2px;z-index:60;background:linear-gradient(90deg,transparent,var(--gold) 28%,var(--gold-l) 50%,var(--gold) 72%,transparent);box-shadow:0 0 18px rgba(200,151,42,.4);}
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  @keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

  .shell{position:relative;z-index:10;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .auth{width:100%;max-width:380px;animation:rise .55s cubic-bezier(.22,1,.36,1) both;}
  .brand{display:flex;align-items:center;justify-content:center;gap:11px;font-family:'Playfair Display',serif;font-size:32px;font-weight:800;color:#fff;letter-spacing:-.5px;}
  .brand .bd{width:11px;height:11px;border-radius:50%;background:var(--gold);box-shadow:0 0 14px var(--gold);animation:pulse 2.4s infinite;}
  .tag{text-align:center;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-top:12px;}

  .card{position:relative;margin-top:26px;border:1px solid var(--border-strong);border-radius:20px;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.012));padding:28px 26px;}
  .card::before{content:'';position:absolute;inset:0 0 auto 0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);}
  .lbl{display:block;font-size:10px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-bottom:8px;}
  .inp{width:100%;border-radius:11px;border:1px solid var(--border);background:var(--surface);padding:12px 14px;color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .18s,box-shadow .18s;}
  .inp::placeholder{color:var(--faint);}
  .inp:focus{border-color:rgba(200,151,42,.55);box-shadow:0 0 0 3px rgba(200,151,42,.09);}
  .err{color:var(--red);background:var(--red-soft);border:1px solid rgba(244,63,94,.3);padding:11px 14px;border-radius:11px;font-size:13px;}
  .btn-gold{width:100%;border:none;cursor:pointer;border-radius:11px;padding:13px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;letter-spacing:.02em;color:#1a1206;background:linear-gradient(135deg,var(--gold-l),var(--gold));transition:transform .15s,box-shadow .18s;}
  .btn-gold:hover{transform:translateY(-1px);box-shadow:0 10px 26px -10px rgba(200,151,42,.7);}
  .hint{text-align:center;font-size:11.5px;color:var(--faint);margin-top:18px;}
  .back{display:block;text-align:center;font-size:12px;color:var(--gold-l);margin-top:20px;text-decoration:none;}
  .back:hover{color:#fff;}
  @media (prefers-reduced-motion: reduce){*{animation:none!important;}}
</style>
</head>
<body>
<div class="bp"></div><div class="vig"></div><div class="grain"></div><div class="topline"></div>
<span class="reg tl"></span><span class="reg tr"></span><span class="reg bl"></span><span class="reg br"></span>

<main class="shell">
  <div class="auth">
    <a href="/" class="brand"><span class="bd"></span>Ticky</a>
    <p class="tag">Belépés · admin / tester</p>

    <div class="card">
      <form method="POST" action="/login<?= $safe_from !== '' ? ('?from=' . htmlspecialchars($safe_from, ENT_QUOTES, 'UTF-8')) : '' ?>" style="display:flex;flex-direction:column;gap:16px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div>
          <label class="lbl">Felhasználónév</label>
          <input type="text" name="felhasznalonev" class="inp" autocomplete="username" autofocus required>
        </div>

        <div>
          <label class="lbl">Jelszó</label>
          <input type="password" name="jelszo" class="inp" autocomplete="current-password" required>
        </div>

        <?php if ($error !== ''): ?>
          <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <button type="submit" class="btn-gold">Belépés →</button>
      </form>

      <p class="hint">Admin vagy tester fiókkal tudsz belépni.</p>
    </div>

    <a href="/" class="back">← Vissza a főoldalra</a>
  </div>
</main>

</body>
</html>
