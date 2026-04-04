<?php
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';

// Biztonsági fejlécek
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');

handle_cors();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 1. STATIKUS FÁJLOK VÉDELME (Favicon fix)
$file = __DIR__ . $uri;
if (file_exists($file) && is_file($file)) {
    return false;
}

// 2. FŐOLDAL (Landing Page)
if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége',1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
    $nap = mai_nap();
    $ido = aktualis_ido();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky</title>
<link rel="icon" type="image/png" href="/favicon.png?v=<?= filemtime('favicon.png') ?>">
<link rel="shortcut icon" href="/favicon.ico?v=<?= filemtime('favicon.ico') ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;overscroll-behavior:none;color:white;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%),radial-gradient(ellipse 60% 30% at 30% 90%,rgba(7,29,58,.8) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:300;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .fade-up{animation:fu .5s cubic-bezier(.22,1,.36,1) both;}
  .gold-line{height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#1a4a8a,#c8972a,#1a4a8a);}
  .card-hover{transition:transform .2s,border-color .2s,box-shadow .2s;}
  .card-hover:hover{transform:translateY(-3px);border-color:rgba(255,255,255,.2)!important;box-shadow:0 12px 40px rgba(6,15,30,.7);}
  /* NAVBAR */
  .navbar{position:sticky;top:0;z-index:200;height:60px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
  .nav-brand{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;}
  .nav-links{display:flex;align-items:center;gap:2px;}
  .nav-link{font-size:13px;font-weight:500;padding:7px 11px;border-radius:8px;color:rgba(255,255,255,.6);transition:all .15s;}
  .nav-link:hover{color:white;background:rgba(255,255,255,.09);}
  .nav-link.gold{color:rgba(200,151,42,.8);border:1px solid rgba(200,151,42,.2);}
  /* SIDEBAR */
  .ticky-sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:150;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-left:none;border-radius:0 12px 12px 0;}
  .tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;color:rgba(255,255,255,.6);transition:all .18s;text-decoration:none;}
  .tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
  .tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:white;font-size:12px;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
  .tsb-item:hover::after{opacity:1;}
  /* GRID & MAIN */
  .main{position:relative;z-index:10;padding:48px 20px 60px;max-width:680px;margin:0 auto;}
  .hero{text-align:center;margin-bottom:40px;}
  .hero h1{font-family:'Playfair Display',serif;font-size:clamp(56px,16vw,80px);font-weight:700;line-height:1;letter-spacing:-2px;}
  .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px;}
  @media(max-width:600px){.ticky-sidebar{display:none;}.grid3{grid-template-columns:1fr;}}
  @keyframes fu{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="top-line"></div>

<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" class="tsb-item" data-label="Eseménynaptár">📅</a>
  <div style="width:20px;height:1px;background:rgba(255,255,255,.1);margin:2px 0;"></div>
  <a href="/support" class="tsb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" class="tsb-item" data-label="Bug report">🐛</a>
</div>

<nav class="navbar">
  <a href="/" class="nav-brand">
    <span class="pulse" style="width:8px;height:8px;border-radius:50%;background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
    Ticky
  </a>
  <div class="nav-links">
    <a href="/termek" class="nav-link">Termek</a>
    <a href="/tanar" class="nav-link">Tanár</a>
    <a href="/osztaly" class="nav-link">Osztály</a>
    <a href="/qr" class="nav-link">QR</a>
    <a href="/kijelzo" class="nav-link">Kijelző</a>
    <a href="/admin" class="nav-link gold">⚙️ Admin</a>
  </div>
</nav>

<div class="main">
  <div class="hero fade-up">
    <h1>Ticky</h1>
    <p style="color:rgba(255,255,255,0.45); margin-top:10px;">Digitális terem-azonosító rendszer</p>
    <div style="color:#4ade80; font-size:12px; margin-top:16px;">
      <span class="pulse" style="width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;"></span>
      <?= htmlspecialchars($nap_nevek[$nap]) ?> · <?= htmlspecialchars($ido) ?> · Aktív
    </div>
  </div>

  <div class="card-wrap fade-up" style="animation-delay: 0.1s">
    <div class="gold-line"></div>
    <a href="/termek" class="glass card-hover" style="display:flex; justify-content:space-between; padding:20px 22px; border-radius:0 0 14px 14px; align-items:center; text-decoration:none;">
      <div>
        <p style="font-size:10px; text-transform:uppercase; color:rgba(255,255,255,0.35); letter-spacing:0.1em;">Élő nézet</p>
        <h2 style="font-family:'Playfair Display',serif; font-size:21px; color:white;">Összes terem</h2>
        <p style="font-size:13px; color:rgba(255,255,255,0.45);">Szabad &amp; foglalt termek valós időben</p>
      </div>
      <span style="font-size:30px;">🏛️</span>
    </a>
  </div>

  <div class="grid3 fade-up" style="animation-delay: 0.2s">
    <div style="display:flex; flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/tanar" class="glass card-hover" style="padding:18px; border-radius:0 0 14px 14px; text-decoration:none; flex-grow:1;">
        <span style="font-size:24px; display:block; margin-bottom:8px;">👩‍🏫</span>
        <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:white;">Tanár kereső</h3>
        <p style="font-size:12px; color:rgba(255,255,255,0.4);">Hol van most?</p>
      </a>
    </div>

    <div style="display:flex; flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/osztaly" class="glass card-hover" style="padding:18px; border-radius:0 0 14px 14px; text-decoration:none; flex-grow:1;">
        <span style="font-size:24px; display:block; margin-bottom:8px;">🎓</span>
        <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:white;">Osztály nézet</h3>
        <p style="font-size:12px; color:rgba(255,255,255,0.4);">Hol van most?</p>
      </a>
    </div>

    <div style="display:flex; flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/qr" class="glass card-hover" style="padding:18px; border-radius:0 0 14px 14px; text-decoration:none; flex-grow:1;">
        <span style="font-size:24px; display:block; margin-bottom:8px;">📟</span>
        <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:white;">QR Generátor</h3>
        <p style="font-size:12px; color:rgba(255,255,255,0.4);">Nyomtatható kódok</p>
      </a>
    </div>
  </div>

  <p style="text-align:center; font-size:11px; color:rgba(255,255,255,0.18); margin-top:24px;">Ticky v1.0 · Render · Supabase · PHP</p>
</div>

</body>
</html>
<?php
    exit;
}

// 3. ROUTES
if ($uri === '/api/ping') { json_response(['status'=>'ok','time'=>date('Y-m-d H:i:s')]); }
if ($uri === '/termek') { require __DIR__.'/pages/termek.php'; exit; }
if ($uri === '/tanar' || match_route('/tanar/{kod}',$uri)!==false) { require __DIR__.'/pages/tanar.php'; exit; }
if ($uri === '/osztaly' || match_route('/osztaly/{kod}',$uri)!==false) { require __DIR__.'/pages/osztaly.php'; exit; }
if ($uri === '/qr') { require __DIR__.'/pages/qr.php'; exit; }
if ($uri === '/kijelzo') { require __DIR__.'/pages/kijelzo.php'; exit; }
if ($uri === '/support') { require __DIR__.'/pages/support.php'; exit; }
if (match_route('/terem/{szam}/nap',$uri)!==false) { require __DIR__.'/pages/napirend.php'; exit; }
if (match_route('/terem/{szam}',$uri)!==false) { require __DIR__.'/pages/terem.php'; exit; }
if ($uri === '/api/termek') { require __DIR__.'/api/termek.php'; exit; }
if ($uri === '/api/tanarok') { require __DIR__.'/api/tanarok.php'; exit; }
if ($uri === '/api/osztalyok') { require __DIR__.'/api/osztalyok.php'; exit; }
if (match_route('/api/tanar/{kod}/orarend',$uri)!==false) { require __DIR__.'/api/tanar_orarend.php'; exit; }
if (match_route('/api/osztaly/{kod}/orarend',$uri)!==false) { require __DIR__.'/api/osztaly_orarend.php'; exit; }
if (match_route('/api/terem/{szam}',$uri)!==false) { require __DIR__.'/api/terem.php'; exit; }
if (match_route('/api/napirend/{szam}',$uri)!==false) { require __DIR__.'/api/napirend.php'; exit; }
if ($uri === '/admin') { require __DIR__.'/pages/admin.php'; exit; }
if ($uri === '/api/admin/tanar') { require __DIR__.'/api/admin_tanar.php'; exit; }
if (match_route('/api/admin/terem/{szam}',$uri)!==false) { require __DIR__.'/api/admin_terem.php'; exit; }

// 4. 404 HIBAOLDAL
http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza</a></body></html>';
exit;
