<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers(true);

$csrf_token = ticky_csrf_token();

if (ticky_is_admin_authed()) {
    header('Location: ' . admin_url());
    exit;
}

if (ticky_current_user() !== null) {
    header('Location: /termek');
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Bejelentkezés</title>
<link rel="icon" type="image/png" href="/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body{font-family:'DM Sans',sans-serif;background:#060f1e;min-height:100vh;color:white;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:2px;z-index:200;background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);}
  .card{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);border-radius:20px;}
  .inp{width:100%;padding:12px 16px;border-radius:10px;border:1.5px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:white;font-family:'DM Sans',sans-serif;font-size:15px;transition:border-color .2s,background .2s;outline:none;}
  .inp::placeholder{color:rgba(255,255,255,.3);}
  .inp:focus{border-color:rgba(200,151,42,.5);background:rgba(255,255,255,.09);}
  .btn-gold{background:linear-gradient(135deg,#c8972a,#a07020);color:white;padding:13px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;cursor:pointer;border:none;width:100%;transition:transform .2s,box-shadow .2s;}
  .btn-gold:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(200,151,42,.35);}
  .btn-gold:disabled{opacity:.6;cursor:not-allowed;transform:none;}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="top-line"></div>
<div class="relative z-10 flex items-center justify-center min-h-screen px-4">
  <div class="w-full max-w-sm slide-up">
    <div class="text-center mb-8">
      <a href="/" style="font-family:'Playfair Display',serif;font-size:36px;font-weight:700;color:white;display:inline-flex;align-items:center;gap:12px;text-decoration:none;">
        <span class="pulse" style="width:10px;height:10px;border-radius:50%;background:#c8972a;box-shadow:0 0 12px #c8972a;display:inline-block;flex-shrink:0;"></span>
        Ticky
      </a>
      <p style="font-size:14px;color:rgba(255,255,255,.4);margin-top:10px;">Bejelentkezés szükséges</p>
    </div>

    <div class="card" style="padding:32px;">
      <div style="margin-bottom:20px;">
        <label style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:8px;">Felhasználónév</label>
        <input type="text" id="fnev" class="inp" placeholder="Felhasználónév" autocomplete="username" autofocus>
      </div>
      <div style="margin-bottom:24px;">
        <label style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:8px;">Jelszó</label>
        <input type="password" id="pw" class="inp" placeholder="••••••••" autocomplete="current-password">
      </div>
      <button class="btn-gold" id="login-btn" onclick="doLogin()">Bejelentkezés →</button>
      <div id="login-err" style="display:none;margin-top:14px;font-size:13px;color:#ff6b82;text-align:center;font-weight:600;padding:10px;background:rgba(232,51,74,.1);border-radius:8px;border:1px solid rgba(232,51,74,.25);"></div>
    </div>

    <p style="text-align:center;margin-top:16px;font-size:12px;color:rgba(255,255,255,.25);">
      <a href="/" style="color:rgba(255,255,255,.35);text-decoration:none;">← Vissza a főoldalra</a>
    </p>
  </div>
</div>

<script>
const fnevEl = document.getElementById('fnev')
const pwEl = document.getElementById('pw')
const btn = document.getElementById('login-btn')
const errEl = document.getElementById('login-err')
const csrfToken = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>

async function doLogin() {
  const fnev = fnevEl.value.trim()
  const pw = pwEl.value
  errEl.style.display = 'none'

  if (!fnev || !pw) {
    errEl.textContent = 'Töltsd ki mindkét mezőt!'
    errEl.style.display = 'block'
    return
  }

  btn.disabled = true
  btn.textContent = 'Bejelentkezés…'

  try {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ felhasznalonev: fnev, jelszo: pw })
    })
    const d = await res.json()

    if (d.ok) {
      window.location.href = d.redirect || '/termek'
    } else {
      errEl.textContent = d.error || 'Hibás felhasználónév vagy jelszó'
      errEl.style.display = 'block'
      pwEl.value = ''
      pwEl.focus()
    }
  } catch (error) {
    errEl.textContent = 'Szerverhiba. Próbáld újra.'
    errEl.style.display = 'block'
  } finally {
    btn.disabled = false
    btn.textContent = 'Bejelentkezés →'
  }
}

pwEl.addEventListener('keydown', event => { if (event.key === 'Enter') doLogin() })
fnevEl.addEventListener('keydown', event => { if (event.key === 'Enter') pwEl.focus() })
</script>
</body>
</html>
