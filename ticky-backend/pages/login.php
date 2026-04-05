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
  *,*::before,*::after{box-sizing:border-box;}
  body{font-family:'DM Sans',sans-serif;background-color:#04090f;min-height:100vh;color:white;display:flex;align-items:center;justify-content:center;
    background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 90% 100%,rgba(200,151,42,.10) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);background-size:44px 44px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:2px;z-index:200;background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);}
  a{text-decoration:none;color:inherit;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
  .inp{width:100%;padding:12px 14px;border-radius:9px;border:1.5px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);color:white;font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s;outline:none;}
  .inp::placeholder{color:rgba(255,255,255,.3);}
  .inp:focus{border-color:rgba(200,151,42,.5);background:rgba(255,255,255,.07);}
  .btn-gold{width:100%;padding:13px;border-radius:9px;border:none;cursor:pointer;background:linear-gradient(135deg,#c8972a,#a07020);color:white;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;transition:all .2s;}
  .btn-gold:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(200,151,42,.4);}
  .btn-gold:disabled{opacity:.5;cursor:not-allowed;transform:none;}
  .error-msg{font-size:13px;color:#ff6b82;padding:10px 14px;border-radius:8px;background:rgba(232,51,74,.12);border:1px solid rgba(232,51,74,.3);}
</style>
</head>
<body>
<div class="top-line"></div>

<div class="relative z-10 w-full max-w-sm px-4 slide-up">
  <div class="text-center mb-8">
    <a href="/" style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;display:inline-flex;align-items:center;gap:10px;">
      <span class="pulse" style="width:10px;height:10px;border-radius:50%;background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <p style="font-size:13px;color:rgba(255,255,255,.4);margin-top:8px;">Bejelentkezés</p>
  </div>

  <div class="glass" style="border-radius:16px;padding:28px;">
    <div id="error-box" style="display:none;margin-bottom:16px;" class="error-msg"></div>
    <div style="display:flex;flex-direction:column;gap:14px;">
      <div>
        <label style="font-size:11px;font-weight:600;color:rgba(255,255,255,.35);letter-spacing:.07em;text-transform:uppercase;display:block;margin-bottom:7px;">Felhasználónév</label>
        <input type="text" id="fnev" class="inp" placeholder="felhasznalonev" autocomplete="username" autofocus>
      </div>
      <div>
        <label style="font-size:11px;font-weight:600;color:rgba(255,255,255,.35);letter-spacing:.07em;text-transform:uppercase;display:block;margin-bottom:7px;">Jelszó</label>
        <input type="password" id="pw" class="inp" placeholder="••••••••" autocomplete="current-password">
      </div>
      <button class="btn-gold" id="login-btn" onclick="doLogin()">Bejelentkezés</button>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:11px;color:rgba(255,255,255,.2);">
      Admin belépés: <a href="/admin" style="color:rgba(255,255,255,.4);">Admin panel</a>
    </p>
  </div>
  <p style="text-align:center;margin-top:14px;">
    <a href="/" style="font-size:12px;color:rgba(255,255,255,.3);">← Vissza a főoldalra</a>
  </p>
</div>

<script>
const redirectTo = new URLSearchParams(location.search).get('redirect') || '/'
function showError(msg) {
  const box = document.getElementById('error-box')
  box.textContent = msg; box.style.display = 'block'
}
async function doLogin() {
  const fnev = document.getElementById('fnev').value.trim()
  const pw   = document.getElementById('pw').value
  if (!fnev || !pw) { showError('Töltsd ki mindkét mezőt!'); return }
  const btn = document.getElementById('login-btn')
  btn.disabled = true; btn.textContent = 'Bejelentkezés...'
  document.getElementById('error-box').style.display = 'none'
  try {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ felhasznalonev: fnev, jelszo: pw })
    })
    const d = await res.json()
    if (d.ok) {
      const dest = decodeURIComponent(redirectTo)
      window.location.href = dest.startsWith('/') ? dest : '/'
    } else {
      showError(d.error || 'Hibás felhasználónév vagy jelszó')
    }
  } catch(e) {
    showError('Nem sikerült csatlakozni a szerverhez')
  } finally {
    btn.disabled = false; btn.textContent = 'Bejelentkezés'
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin() })
</script>
</body>
</html>
