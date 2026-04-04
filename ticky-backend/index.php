<?php
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
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png?v=2">

<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="apple-touch-icon" href="/favicon.png?v=2">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;overscroll-behavior:none;color:white;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%),radial-gradient(ellipse 60% 30% at 30% 90%,rgba(7,29,58,.8) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
  a{text-decoration:none;color:inherit;}
  .top-line{position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:300;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .fade-up  {animation:fu .5s cubic-bezier(.22,1,.36,1) both;}
  .fade-up-2{animation:fu .5s .12s cubic-bezier(.22,1,.36,1) both;}
  .fade-up-3{animation:fu .5s .22s cubic-bezier(.22,1,.36,1) both;}
  .fade-up-4{animation:fu .5s .32s cubic-bezier(.22,1,.36,1) both;}
  @keyframes fu{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
  .gold-line{height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#1a4a8a,#c8972a,#1a4a8a);}
  .card-hover{transition:transform .2s,border-color .2s,box-shadow .2s;}
  .card-hover:hover{transform:translateY(-3px);border-color:rgba(255,255,255,.2)!important;box-shadow:0 12px 40px rgba(6,15,30,.7);}
  /* NAVBAR */
  .navbar{position:sticky;top:0;z-index:200;height:60px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
  .nav-brand{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;}
  .nav-links{display:flex;align-items:center;gap:2px;}
  .nav-link{font-size:13px;font-weight:500;padding:7px 11px;border-radius:8px;color:rgba(255,255,255,.6);transition:all .15s;white-space:nowrap;}
  .nav-link:hover{color:white;background:rgba(255,255,255,.09);}
  .nav-link.gold{color:rgba(200,151,42,.8);border:1px solid rgba(200,151,42,.2);}
  .nav-link.gold:hover{color:#f0c76b;background:rgba(200,151,42,.1);}
  /* HAMBURGER */
  .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);}
  .hamburger span{width:20px;height:2px;background:rgba(255,255,255,.7);border-radius:2px;transition:all .25s;}
  .hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
  .hamburger.open span:nth-child(2){opacity:0;}
  .hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
  /* MOBILE MENU */
  .mobile-menu{display:none;position:fixed;top:60px;left:0;right:0;z-index:190;background:rgba(6,15,30,.97);backdrop-filter:blur(24px);border-bottom:1px solid rgba(255,255,255,.08);padding:12px 16px 20px;flex-direction:column;gap:4px;}
  .mobile-menu.open{display:flex;}
  .mobile-menu a{font-size:15px;font-weight:500;padding:13px 16px;border-radius:10px;color:rgba(255,255,255,.7);border:1px solid transparent;transition:all .15s;}
  .mobile-menu a:hover{background:rgba(255,255,255,.07);color:white;}
  .mobile-menu .mm-gold{color:#f0c76b;border-color:rgba(200,151,42,.2);background:rgba(200,151,42,.06);}
  /* SIDEBAR */
  .ticky-sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:150;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-left:none;border-radius:0 12px 12px 0;}
  .tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;color:rgba(255,255,255,.6);transition:all .18s;}
  .tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
  .tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:rgba(255,255,255,.88);font-size:12px;font-family:'DM Sans',sans-serif;font-weight:500;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
  .tsb-item:hover::after{opacity:1;}
  .tsb-divider{width:20px;height:1px;background:rgba(255,255,255,.10);margin:2px 0;}
  /* MAIN */
  .main{position:relative;z-index:10;padding:48px 20px 60px;max-width:680px;margin:0 auto;}
  .hero{text-align:center;margin-bottom:40px;}
  .hero h1{font-family:'Playfair Display',serif;font-size:clamp(56px,16vw,80px);font-weight:700;line-height:1;letter-spacing:-2px;color:white;}
  .hero .sub{font-size:14px;color:rgba(255,255,255,.45);margin-top:10px;}
  .hero .status{display:inline-flex;align-items:center;gap:8px;margin-top:16px;font-size:12px;font-weight:500;color:#4ade80;}
  .card-wrap{margin-bottom:10px;}
  .card-big{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-radius:0 0 14px 14px;border-top:none;}
  .card-big .info p.eyebrow{font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:4px;}
  .card-big .info h2{font-family:'Playfair Display',serif;font-size:21px;font-weight:700;color:white;}
  .card-big .info p.desc{font-size:13px;color:rgba(255,255,255,.45);margin-top:2px;}
  .card-big .emoji{font-size:30px;flex-shrink:0;}
  .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:10px;align-items:stretch;}
  .grid3 .card-wrap{margin-bottom:0;display:flex;}
  .card-sm{display:flex;flex-direction:column;align-items:flex-start;justify-content:space-between;gap:8px;width:100%;height:100%;padding:18px;border-radius:0 0 14px 14px;border-top:none;}
  .card-sm .emoji{font-size:24px;margin-bottom:10px;}
  .card-sm h3{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;line-height:1.15;min-height:2.3em;}
  .card-sm p{font-size:12px;color:rgba(255,255,255,.4);margin-top:3px;}
  .footer-note{text-align:center;font-size:11px;color:rgba(255,255,255,.18);margin-top:24px;}
  @media(max-width:600px){
    .ticky-sidebar{display:none;}.nav-links{display:none;}.hamburger{display:flex;}
    .main{padding:32px 16px 60px;}.card-big{padding:16px 18px;}.card-sm{padding:14px 16px;}
    .grid3{grid-template-columns:repeat(3,minmax(0,1fr));}.card-sm h3{font-size:15px;min-height:2.4em;}.card-sm p{font-size:11px;}
  }
  @media(min-width:601px){.mobile-menu{display:none!important;}.hamburger{display:none!important;}}
</style>
</head>
<body>
<div class="top-line"></div>

<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="tsb-item" data-label="Eseménynaptár">📅</a>
  <div class="tsb-divider"></div>
  <a href="/support" class="tsb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="tsb-item" data-label="Bug report">🐛</a>
</div>

<nav class="navbar">
  <a href="/" class="nav-brand">
    <span class="pulse" style="width:8px;height:8px;border-radius:50%;background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;flex-shrink:0;"></span>
    Ticky
  </a>
  <div class="nav-links">
    <a href="/termek"  class="nav-link">Termek</a>
    <a href="/tanar"   class="nav-link">Tanár</a>
    <a href="/osztaly" class="nav-link">Osztály</a>
    <a href="/qr"      class="nav-link">QR</a>
    <a href="/kijelzo" class="nav-link">Kijelző</a>
    <a href="/admin"   class="nav-link gold">&#9881;&#65039; Admin</a>
  </div>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </div>
</nav>

<div class="mobile-menu" id="mobile-menu">
  <a href="/termek">&#127983; Termek</a>
  <a href="/tanar">&#128105;&#8205;&#127979; Tanár kereső</a>
  <a href="/osztaly">&#127891; Osztály nézet</a>
  <a href="/qr">&#128424;&#65039; QR Generátor</a>
  <a href="/kijelzo">&#128250; Kijelző</a>
  <a href="/support">&#9993;&#65039; Support</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener">&#128027; Bug report</a>
  <a href="/admin" class="mm-gold">&#9881;&#65039; Admin panel</a>
</div>

<div class="main">
  <div class="hero fade-up">
    <h1>Ticky</h1>
    <p class="sub">Digitális terem-azonosító rendszer</p>
    <div class="status">
      <span class="pulse" style="width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;flex-shrink:0;"></span>
      <?= htmlspecialchars($nap_nevek[$nap]) ?> · <?= htmlspecialchars($ido) ?> · Aktív
    </div>
  </div>

  <div class="card-wrap fade-up-2">
    <div class="gold-line"></div>
    <a href="/termek" class="glass card-hover card-big">
      <div class="info">
        <p class="eyebrow">Élő nézet</p>
        <h2>Összes terem</h2>
        <p class="desc">Szabad &amp; foglalt termek valós időben</p>
      </div>
      <span class="emoji">&#127983;</span>
    </a>
  </div>

  <div class="grid3 fade-up-3">
    <div class="card-wrap">
      <div class="gold-line"></div>
      <a href="/tanar" class="glass card-hover card-sm">
        <span class="emoji">&#128105;&#8205;&#127979;</span>
        <h3>Tanár kereső</h3>
        <p>Hol van most?</p>
      </a>
    </div>
    <div class="card-wrap">
      <div class="gold-line"></div>
      <a href="/osztaly" class="glass card-hover card-sm">
        <span class="emoji">&#127891;</span>
        <h3>Osztály nézet</h3>
        <p>Hol van most?</p>
      </a>
    </div>
    <div class="card-wrap">
      <div class="gold-line"></div>
      <a href="/qr" class="glass card-hover card-sm">
        <span class="emoji">&#128424;&#65039;</span>
        <h3>QR Generátor</h3>
        <p>Nyomtatható kódok</p>
      </a>
    </div>
  </div>

  <p class="footer-note fade-up-4">Ticky v1.0 · Render · Supabase · PHP</p>
</div>

<script>
function toggleMenu(){
  const m=document.getElementById('mobile-menu'),h=document.getElementById('hamburger');
  const open=m.classList.toggle('open');h.classList.toggle('open',open);
}
document.addEventListener('click',function(e){
  if(!e.target.closest('#mobile-menu')&&!e.target.closest('#hamburger')){
    document.getElementById('mobile-menu').classList.remove('open');
    document.getElementById('hamburger').classList.remove('open');
  }
});
</script>
</body>
</html>
<?php
    exit;
}

// Routes
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

http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza</a></body></html>';
