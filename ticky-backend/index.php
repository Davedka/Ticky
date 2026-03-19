<?php
// index.php – Ticky API Router

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ─── Gyökér → Landing oldal ───────────────────────────
if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége', 1=>'Hétfő', 2=>'Kedd', 3=>'Szerda', 4=>'Csütörtök', 5=>'Péntek'];
    $nap       = mai_nap();
    $ido       = aktualis_ido();
    ?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['DM Sans','sans-serif']}}}}</script>
<style>
  body { font-family:'DM Sans',sans-serif; }
  .fade-in { animation: fadeIn .6s cubic-bezier(.22,1,.36,1) both; }
  .fade-in-2 { animation: fadeIn .6s .15s cubic-bezier(.22,1,.36,1) both; }
  .fade-in-3 { animation: fadeIn .6s .3s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fadeIn {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .pulse-dot { animation: pulseDot 2s infinite; }
  @keyframes pulseDot {
    0%,100% { opacity:1; transform:scale(1); }
    50%     { opacity:.4; transform:scale(.7); }
  }
  .card-hover { transition: transform .15s ease, box-shadow .15s ease; }
  .card-hover:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,.1); }
</style>
</head>
<body class="min-h-dvh bg-slate-50 flex flex-col items-center justify-center p-6">

<div class="w-full max-w-sm flex flex-col gap-4">

  <!-- Logo + státusz -->
  <div class="fade-in text-center mb-2">
    <h1 class="font-display font-extrabold text-6xl text-slate-900 tracking-tight">Ticky</h1>
    <p class="text-slate-400 text-sm mt-1">Digitális terem-azonosító rendszer</p>
    <div class="flex items-center justify-center gap-2 mt-3">
      <div class="w-2 h-2 rounded-full bg-green-500 pulse-dot"></div>
      <span class="text-xs font-medium text-green-600"><?= $nap_nevek[$nap] ?> · <?= $ido ?> · Rendszer aktív</span>
    </div>
  </div>

  <!-- Fő navigáció kártyák -->
  <a href="/termek" class="fade-in-2 card-hover bg-white rounded-2xl shadow-sm border border-slate-100 px-6 py-5 flex items-center justify-between gap-4 no-underline">
    <div>
      <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-1">Nézet</p>
      <h2 class="font-display font-extrabold text-2xl text-slate-900">Összes terem</h2>
      <p class="text-sm text-slate-400 mt-0.5">Szabad & foglalt termek live</p>
    </div>
    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-2xl shrink-0">🏫</div>
  </a>

  <div class="fade-in-3 grid grid-cols-2 gap-3">
    <a href="/tanar/ÁSZJ" class="card-hover bg-white rounded-2xl shadow-sm border border-slate-100 px-5 py-4 no-underline">
      <div class="text-2xl mb-2">👩‍🏫</div>
      <h3 class="font-display font-bold text-lg text-slate-900 leading-tight">Tanár nézet</h3>
      <p class="text-xs text-slate-400 mt-0.5">/tanar/{kód}</p>
    </a>
    <a href="/terem/204" class="card-hover bg-white rounded-2xl shadow-sm border border-slate-100 px-5 py-4 no-underline">
      <div class="text-2xl mb-2">🚪</div>
      <h3 class="font-display font-bold text-lg text-slate-900 leading-tight">Terem QR</h3>
      <p class="text-xs text-slate-400 mt-0.5">/terem/{szám}</p>
    </a>
  </div>

  <!-- API info -->
  <div class="fade-in-3 bg-white rounded-2xl shadow-sm border border-slate-100 px-6 py-4">
    <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-3">API Endpointok</p>
    <div class="flex flex-col gap-2 font-mono text-xs">
      <div class="flex items-center gap-2">
        <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded font-sans font-medium">GET</span>
        <span class="text-slate-600">/api/terem/{szám}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded font-sans font-medium">GET</span>
        <span class="text-slate-600">/api/termek</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded font-sans font-medium">GET</span>
        <span class="text-slate-600">/api/napirend/{szám}</span>
      </div>
    </div>
  </div>

  <p class="fade-in-3 text-center text-xs text-slate-300 mt-2">Ticky v1.0 · Render + Supabase + PHP</p>
</div>

</body>
</html>
    <?php
    exit;
}

// ─── API Ping ─────────────────────────────────────────
if ($uri === '/api/ping') {
    json_response([
        'app'     => 'Ticky API',
        'version' => '1.0',
        'time'    => date('Y-m-d H:i:s'),
        'nap'     => mai_nap(),
        'status'  => 'ok',
    ]);
}

// ─── Frontend oldalak ─────────────────────────────────
if ($uri === '/termek') {
    require __DIR__ . '/pages/termek.php';
    exit;
}

if (match_route('/tanar/{kod}', $uri) !== false) {
    require __DIR__ . '/pages/tanar.php';
    exit;
}

if (match_route('/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/pages/terem.php';
    exit;
}

// ─── API Routes ───────────────────────────────────────
if ($uri === '/api/termek') {
    require __DIR__ . '/api/termek.php';
    exit;
}

if (match_route('/api/terem/{szam}', $uri) !== false) {
    require __DIR__ . '/api/terem.php';
    exit;
}

if (match_route('/api/napirend/{szam}', $uri) !== false) {
    require __DIR__ . '/api/napirend.php';
    exit;
}

// 404
json_error('Nem találtam: ' . $uri, 404);
