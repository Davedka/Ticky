<?php
// index.php – Ticky Router

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ─── Gyökér → Landing ────────────────────────────────
if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége', 1=>'Hétfő', 2=>'Kedd', 3=>'Szerda', 4=>'Csütörtök', 5=>'Péntek'];
    $nap = mai_nap();
    $ido = aktualis_ido();
    ?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  html { scroll-behavior:smooth; }
  body { font-family:'DM Sans',sans-serif; background-color:#060f1e; min-height:100vh; overscroll-behavior:none;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%),
      radial-gradient(ellipse 60% 30% at 30% 90%, rgba(7,29,58,.8) 0%, transparent 50%); }
  body::before { content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
    background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);
    background-size:40px 40px; }
  .top-line { position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200; }
  .glass { background:rgba(255,255,255,.04);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10); }
  .card-hover { transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
  .card-hover:hover { transform:translateY(-3px);border-color:rgba(255,255,255,.20) !important;box-shadow:0 12px 40px rgba(6,15,30,.7); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .fade-up   { animation:fu .5s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-2 { animation:fu .5s .15s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-3 { animation:fu .5s .28s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-4 { animation:fu .5s .40s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fu { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
  .gold-line { height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#1a4a8a,#c8972a,#1a4a8a); }
  a { text-decoration:none; }

  /* ── AI Chat widget ── */
  .ai-fab {
    position:fixed; bottom:28px; right:28px; z-index:300;
    width:52px; height:52px; border-radius:50%;
    background:linear-gradient(135deg,#1a4a8a,#0b2e59);
    border:1.5px solid rgba(200,151,42,.45);
    box-shadow:0 4px 20px rgba(0,0,0,.5), 0 0 0 0 rgba(200,151,42,.3);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .2s; font-size:22px;
    animation:fabPulse 3s ease-in-out infinite;
  }
  @keyframes fabPulse {
    0%,100%{box-shadow:0 4px 20px rgba(0,0,0,.5),0 0 0 0 rgba(200,151,42,.3)}
    50%{box-shadow:0 4px 24px rgba(0,0,0,.6),0 0 0 8px rgba(200,151,42,.0)}
  }
  .ai-fab:hover { transform:scale(1.08); border-color:rgba(200,151,42,.7); }

  .ai-panel {
    position:fixed; bottom:92px; right:28px; z-index:300;
    width:320px; max-height:440px;
    background:rgba(6,15,30,.95); backdrop-filter:blur(24px);
    border:1px solid rgba(255,255,255,.10); border-radius:18px;
    display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 16px 48px rgba(0,0,0,.7);
    animation:panelIn .3s cubic-bezier(.22,1,.36,1) both;
    transform-origin:bottom right;
  }
  @keyframes panelIn { from{opacity:0;transform:scale(.9) translateY(12px)} to{opacity:1;transform:none} }
  .ai-panel.hidden { display:none; }

  .ai-header {
    padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08);
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(26,74,138,.2);
  }
  .ai-messages {
    flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px;
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.1) transparent;
  }
  .ai-msg { max-width:88%; padding:9px 12px; border-radius:12px; font-size:13px; line-height:1.5; }
  .ai-msg.user { background:rgba(26,74,138,.5); border:1px solid rgba(26,74,138,.4); color:white; align-self:flex-end; border-bottom-right-radius:4px; }
  .ai-msg.bot  { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); color:rgba(255,255,255,.88); align-self:flex-start; border-bottom-left-radius:4px; }
  .ai-msg.bot.typing { color:rgba(255,255,255,.45); font-style:italic; }

  .ai-input-row {
    padding:10px 12px; border-top:1px solid rgba(255,255,255,.08);
    display:flex; gap:8px; align-items:center;
  }
  .ai-input {
    flex:1; background:rgba(255,255,255,.06); border:1.5px solid rgba(255,255,255,.10);
    border-radius:10px; color:white; font-family:'DM Sans',sans-serif; font-size:13px;
    padding:9px 12px; outline:none; transition:border-color .2s;
  }
  .ai-input:focus { border-color:rgba(200,151,42,.45); }
  .ai-input::placeholder { color:rgba(255,255,255,.3); }
  .ai-send {
    width:34px; height:34px; border-radius:9px; flex-shrink:0;
    background:linear-gradient(135deg,#c8972a,#a07020); border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center; transition:all .15s;
  }
  .ai-send:hover { transform:scale(1.08); box-shadow:0 4px 12px rgba(200,151,42,.4); }
  .ai-send:disabled { opacity:.4; cursor:not-allowed; transform:none; }
</style>
</head>
<body class="relative">
<div class="top-line"></div>

<!-- Navbar -->
<nav style="background:rgba(6,15,30,.7);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);" class="sticky top-0 z-50 px-6 h-16 flex items-center justify-between">
  <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:20px;font-weight:700;" class="flex items-center gap-2">
    <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
    Ticky
  </a>
  <div class="flex items-center gap-1 flex-wrap">
    <a href="/termek" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Termek</a>
    <a href="/tanar" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Tanár</a>
    <a href="/qr" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">QR</a>
    <a href="/kijelzo" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Kijelző</a>
    <a href="/admin" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(200,151,42,.7);border:1px solid rgba(200,151,42,.2);border-radius:8px;transition:all .2s" onmouseover="this.style.color='#f0c76b';this.style.background='rgba(200,151,42,.1)'" onmouseout="this.style.color='rgba(200,151,42,.7)';this.style.background='transparent'">⚙️ Admin</a>
    <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="text-sm font-medium px-3 py-2 rounded-md" style="color:rgba(0,200,200,.85);background:rgba(0,200,200,.08);border:1px solid rgba(0,200,200,.2);border-radius:8px;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;" onmouseover="this.style.color='white';this.style.background='rgba(0,200,200,.16)';this.style.borderColor='rgba(0,200,200,.4)'" onmouseout="this.style.color='rgba(0,200,200,.85)';this.style.background='rgba(0,200,200,.08)';this.style.borderColor='rgba(0,200,200,.2)'">📅 Eseménynaptár</a>
  </div>
</nav>

<!-- Main tartalom -->
<div class="relative z-10 flex flex-col items-center px-6 pt-20 pb-16">
  <div class="w-full max-w-md">

    <div class="fade-up text-center mb-10">
      <h1 style="font-family:'Playfair Display',serif;font-size:72px;font-weight:700;color:white;line-height:1;letter-spacing:-2px;">Ticky</h1>
      <p class="text-sm mt-3" style="color:rgba(255,255,255,.45);">Digitális terem-azonosító rendszer</p>
      <div class="flex items-center justify-center gap-2 mt-4">
        <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#4ade80;display:inline-block;"></span>
        <span class="text-xs font-medium" style="color:#4ade80;"><?= htmlspecialchars($nap_nevek[$nap]) ?> · <?= htmlspecialchars($ido) ?> · Aktív</span>
      </div>
    </div>

    <div class="fade-up-2 mb-3">
      <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
      <a href="/termek" class="glass card-hover block px-6 py-5 flex items-center justify-between gap-4" style="border-radius:0 0 14px 14px;border-top:none;">
        <div>
          <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Élő nézet</p>
          <h2 style="font-family:'Playfair Display',serif;color:white;font-size:22px;font-weight:700;">Összes terem</h2>
          <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.45);">Szabad & foglalt termek valós időben</p>
        </div>
        <span style="font-size:32px;">🏫</span>
      </a>
    </div>

    <div class="fade-up-3 grid grid-cols-2 gap-3 mb-3">
      <div>
        <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
        <a href="/tanar" class="glass card-hover block px-5 py-4" style="border-radius:0 0 14px 14px;border-top:none;">
          <span style="font-size:24px;" class="block mb-2">👩‍🏫</span>
          <h3 style="font-family:'Playfair Display',serif;color:white;font-size:17px;font-weight:700;">Tanár kereső</h3>
          <p class="text-xs mt-0.5" style="color:rgba(255,255,255,.40);">Hol van most?</p>
        </a>
      </div>
      <div>
        <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
        <a href="/qr" class="glass card-hover block px-5 py-4" style="border-radius:0 0 14px 14px;border-top:none;">
          <span style="font-size:24px;" class="block mb-2">🖨️</span>
          <h3 style="font-family:'Playfair Display',serif;color:white;font-size:17px;font-weight:700;">QR Generátor</h3>
          <p class="text-xs mt-0.5" style="color:rgba(255,255,255,.40);">Nyomtatható kódok</p>
        </a>
      </div>
    </div>

    <!-- Eseménynaptár kártya -->
    <div class="fade-up-4 mb-3">
      <div style="height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#007a7a,#00c8c8,#007a7a);"></div>
      <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener"
         class="glass card-hover block px-6 py-4 flex items-center justify-between gap-4"
         style="border-radius:0 0 14px 14px;border-top:none;">
        <div>
          <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(0,200,200,.5);">Kapcsolódó oldal</p>
          <h3 style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;">Eseménynaptár</h3>
          <p class="text-xs mt-0.5" style="color:rgba(255,255,255,.40);">MSZC Gépészeti – iskolai események</p>
        </div>
        <span style="font-size:28px;">📅</span>
      </a>
    </div>

    <p class="fade-up-4 text-center text-xs mt-6" style="color:rgba(255,255,255,.18);">Ticky v1.0 · Render · Supabase · PHP</p>
  </div>
</div>

<!-- ── AI Chat asszisztens ── -->
<button class="ai-fab" onclick="toggleAI()" title="AI Asszisztens">🤖</button>

<div class="ai-panel hidden" id="ai-panel">
  <div class="ai-header">
    <div style="display:flex;align-items:center;gap:8px;">
      <span style="font-size:18px;">🤖</span>
      <div>
        <div style="font-size:13px;font-weight:600;color:white;">Ticky Asszisztens</div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);">Claude · Kérdezz bármit</div>
      </div>
    </div>
    <button onclick="toggleAI()" style="background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">✕</button>
  </div>
  <div class="ai-messages" id="ai-messages">
    <div class="ai-msg bot">Szia! 👋 Segíthetek a Ticky rendszerrel kapcsolatban. Kérdezz teremről, tanárról, órarendről – bármit!</div>
  </div>
  <div class="ai-input-row">
    <input class="ai-input" id="ai-input" placeholder="Kérdezz valamit…" onkeydown="if(event.key==='Enter')sendAI()">
    <button class="ai-send" id="ai-send" onclick="sendAI()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</div>

<script>
let aiOpen = false
let aiHistory = []

function toggleAI() {
  aiOpen = !aiOpen
  document.getElementById('ai-panel').classList.toggle('hidden', !aiOpen)
  if (aiOpen) document.getElementById('ai-input').focus()
}

async function sendAI() {
  const input = document.getElementById('ai-input')
  const msg   = input.value.trim()
  if (!msg) return

  input.value = ''
  document.getElementById('ai-send').disabled = true

  addMsg(msg, 'user')
  aiHistory.push({ role: 'user', content: msg })

  const typing = addMsg('Gondolkodok…', 'bot typing')

  try {
    const res  = await fetch('/api/ai/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: msg, history: aiHistory.slice(0,-1) })
    })
    const data = await res.json()
    const reply = data.reply || 'Valami hiba történt, próbáld újra!'

    typing.className  = 'ai-msg bot'
    typing.textContent = reply
    aiHistory.push({ role: 'assistant', content: reply })

    // Max 20 item a historiban
    if (aiHistory.length > 20) aiHistory = aiHistory.slice(-20)
  } catch(e) {
    typing.className  = 'ai-msg bot'
    typing.textContent = 'Kapcsolati hiba. Próbáld újra!'
  }

  document.getElementById('ai-send').disabled = false
  document.getElementById('ai-messages').scrollTop = 9999
}

function addMsg(text, cls) {
  const el = document.createElement('div')
  el.className = 'ai-msg ' + cls
  el.textContent = text
  document.getElementById('ai-messages').appendChild(el)
  document.getElementById('ai-messages').scrollTop = 9999
  return el
}
</script>

</body>
</html>
    <?php
    exit;
}

// ─── API Ping ─────────────────────────────────────────
if ($uri === '/api/ping') {
    json_response(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
}

// ─── Frontend oldalak ─────────────────────────────────
if ($uri === '/termek') {
    require __DIR__ . '/pages/termek.php'; exit;
}
if ($uri === '/tanar' || match_route('/tanar/{kod}', $uri) !== false) {
    require __DIR__ . '/pages/tanar.php'; exit;
}
if ($uri === '/qr') {
    require __DIR__ . '/pages/qr.php'; exit;
}
if ($uri === '/kijelzo') {
    require __DIR__ . '/pages/kijelzo.php'; exit;
}
if (match_route('/terem/{szam}/nap', $uri) !== false) {
    require __DIR__ . '/pages/napirend.php'; exit;
}
if (match_route('/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/pages/terem.php'; exit;
}

// ─── API Routes ───────────────────────────────────────
if ($uri === '/api/termek') {
    require __DIR__ . '/api/termek.php'; exit;
}
if ($uri === '/api/tanarok') {
    require __DIR__ . '/api/tanarok.php'; exit;
}
if ($uri === '/api/ai/chat') {
    require __DIR__ . '/api/ai_chat.php'; exit;
}
if (match_route('/api/tanar/{kod}/orarend', $uri) !== false) {
    require __DIR__ . '/api/tanar_orarend.php'; exit;
}
if (match_route('/api/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/api/terem.php'; exit;
}
if (match_route('/api/napirend/{szam}', $uri) !== false) {
    require __DIR__ . '/api/napirend.php'; exit;
}

// ─── Admin ────────────────────────────────────────────
if ($uri === '/admin') {
    require __DIR__ . '/pages/admin.php'; exit;
}
if ($uri === '/api/admin/tanar') {
    require __DIR__ . '/api/admin_tanar.php'; exit;
}
if (match_route('/api/admin/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/api/admin_terem.php'; exit;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza a főoldalra</a></body></html>';
