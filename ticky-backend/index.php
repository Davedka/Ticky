<?php
// index.php – Ticky Router

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Serve existing static assets like /favicon.png or /favicon.ico before routing everything through PHP.
$requested_file = realpath(__DIR__ . rawurldecode($uri));
if (
    $uri !== '/'
    && $requested_file !== false
    && is_file($requested_file)
    && str_starts_with($requested_file, __DIR__ . DIRECTORY_SEPARATOR)
) {
    if (PHP_SAPI === 'cli-server') {
        return false;
    }

    $mime_types = [
        'ico'  => 'image/x-icon',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'txt'  => 'text/plain; charset=utf-8',
    ];
    $extension = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION));
    if (isset($mime_types[$extension])) {
        header('Content-Type: ' . $mime_types[$extension]);
    }
    header('Content-Length: ' . filesize($requested_file));
    readfile($requested_file);
    exit;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');

handle_cors();

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
<link rel="icon" type="image/png" href="/favicon.png?v=20260327c">
<link rel="shortcut icon" href="/favicon.ico?v=20260327c">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  html { scroll-behavior:smooth; }
  body { font-family:'DM Sans',sans-serif; background-color:#060f1e; min-height:100vh; overscroll-behavior:none;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%), radial-gradient(ellipse 60% 30% at 30% 90%, rgba(7,29,58,.8) 0%, transparent 50%); }
  body::before { content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px; }
  .top-line { position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200; }
  .glass { background:rgba(255,255,255,.04);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10); }
  .card-hover { transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
  .card-hover:hover { transform:translateY(-3px);border-color:rgba(255,255,255,.20) !important;box-shadow:0 12px 40px rgba(6,15,30,.7); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .fade-up   { animation:fu .5s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-2 { animation:fu .5s .15s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-3 { animation:fu .5s .28s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fu { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
  .gold-line { height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#1a4a8a,#c8972a,#1a4a8a); }
  a { text-decoration:none; }
</style>
</head>
<body class="relative">
  <div class="top-line"></div>
  <nav style="background:rgba(6,15,30,.7);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);" class="sticky top-0 z-50 px-6 h-16 flex items-center justify-between">
    <span style="font-family:'Playfair Display',serif;color:white;font-size:20px;font-weight:700;" class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
      Ticky
    </span>
    <div class="flex items-center gap-1">
      <a href="/termek" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Termek</a>
      <a href="/tanar" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Tanár</a>
      <a href="/qr" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">QR</a>
      <a href="/kijelzo" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(255,255,255,.6);transition:all .2s" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.09)'" onmouseout="this.style.color='rgba(255,255,255,.6)';this.style.background='transparent'">Kijelző</a>
      <a href="/admin" class="text-sm font-medium px-4 py-2 rounded-md" style="color:rgba(200,151,42,.7);border:1px solid rgba(200,151,42,.2);border-radius:8px;transition:all .2s" onmouseover="this.style.color='#f0c76b';this.style.background='rgba(200,151,42,.1)'" onmouseout="this.style.color='rgba(200,151,42,.7)';this.style.background='transparent'">⚙️ Admin</a>
    </div>
  </nav>
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
      <div class="fade-up-2 glass rounded-2xl px-5 py-4 mb-4" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;">
        <div>
          <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.28);">Mai fókusz</p>
          <p class="text-sm mt-1" style="color:rgba(255,255,255,.58);">Élő teremnézet, gyors tanárkeresés és beépített AI támogatás egy felületen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="px-3 py-2 rounded-full text-xs font-semibold" style="background:rgba(200,151,42,.14);border:1px solid rgba(200,151,42,.28);color:#f0c76b;width:auto;margin-top:0;" onclick="window.openTickyAssistant?.('Melyik termek szabadok most?')">AI assist</button>
          <a href="/termek" class="px-3 py-2 rounded-full text-xs font-semibold" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.7);">Élő termek</a>
          <a href="/tanar" class="px-3 py-2 rounded-full text-xs font-semibold" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.7);">Tanár</a>
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
      <div class="fade-up-3 grid grid-cols-2 gap-3">
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
      <div class="fade-up-3 mt-3">
        <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
        <a href="/assistant" class="glass card-hover block px-6 py-5 flex items-center justify-between gap-4" style="border-radius:0 0 14px 14px;border-top:none;">
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Új · AI asszisztens</p>
            <h2 style="font-family:'Playfair Display',serif;color:white;font-size:22px;font-weight:700;">Kérdezz a Ticky adatairól</h2>
            <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.45);">Szabad termek, teremállapot és gyors navigáció egy helyen</p>
          </div>
          <span style="font-size:30px;">✦</span>
        </a>
      </div>
      <p class="fade-up-3 text-center text-xs mt-8" style="color:rgba(255,255,255,.18);">Ticky v1.0 · Render · Supabase · PHP</p>
    </div>
  </div>
  <?php render_assistant_widget([
    'title' => 'Ticky AI',
    'eyebrow' => 'Főoldal',
    'intro' => 'Kérdezhetsz szabad termekről, foglaltságról, adott terem állapotáról vagy gyors navigációról.',
    'prompts' => [
      'Melyik termek szabadok most?',
      'Melyik termek foglaltak most?',
      'Mi van most a 204-es teremben?',
      'Nyisd meg a tanárkeresőt',
    ],
  ]); ?>
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
if ($uri === '/assistant') {
    require __DIR__ . '/pages/assistant.php'; exit;
}
if ($uri === '/kijelzo') {
    require __DIR__ . '/pages/kijelzo.php'; exit;
}
// Napirend ELŐBB mint /terem/{szam}!
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
if ($uri === '/api/assistant') {
    require __DIR__ . '/api/assistant.php'; exit;
}
// Tanár órarend – ELŐBB mint /api/terem/{szam}!
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
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png?v=20260327c"><link rel="shortcut icon" href="/favicon.ico?v=20260327c"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza a főoldalra</a></body></html>';
