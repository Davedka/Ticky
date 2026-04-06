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

// Statikus fájlok védelme (Favicon fix)
$file = __DIR__ . $uri;
if (file_exists($file) && is_file($file)) {
    return false;
}

// FŐOLDAL
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
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
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
  .fade-up{animation:fu .5s cubic-bezier(.22,1,.36,1) both;}
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
  .tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;color:rgba(255,255,255,.6);transition:all .18s;text-decoration:none;}
  .tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
  .tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:rgba(255,255,255,.88);font-size:12px;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
  .tsb-item:hover::after{opacity:1;}
  .tsb-divider{width:20px;height:1px;background:rgba(255,255,255,.10);margin:2px 0;}
  /* MAIN */
  .main{position:relative;z-index:10;padding:48px 20px 60px;max-width:680px;margin:0 auto;}
  .hero{text-align:center;margin-bottom:40px;}
  .hero h1{font-family:'Playfair Display',serif;font-size:clamp(56px,16vw,80px);font-weight:700;line-height:1;letter-spacing:-2px;}
  .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px;}
  @keyframes fu{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
  @media(max-width:600px){
    .ticky-sidebar{display:none;}.nav-links{display:none;}.hamburger{display:flex;}
    .grid3{grid-template-columns:1fr;}
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
    <a href="/admin"   class="nav-link gold">⚙️ Admin</a>
  </div>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </div>
</nav>

<div class="mobile-menu" id="mobile-menu">
  <a href="/termek">🏫 Termek</a>
  <a href="/tanar">👩‍🏫 Tanár kereső</a>
  <a href="/osztaly">🎓 Osztály nézet</a>
  <a href="/qr">🖨️ QR Generátor</a>
  <a href="/kijelzo">📺 Kijelző</a>
  <a href="/support">✉️ Support</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener">🐛 Bug report</a>
  <a href="/admin" class="mm-gold">⚙️ Admin panel</a>
</div>

<div class="main">
  <div class="hero fade-up">
    <h1>Ticky</h1>
    <p style="color:rgba(255,255,255,.45);margin-top:10px;">Digitális terem-azonosító rendszer</p>
    <div style="color:#4ade80;font-size:12px;margin-top:16px;display:inline-flex;align-items:center;gap:8px;">
      <span class="pulse" style="width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;flex-shrink:0;"></span>
      <?= htmlspecialchars($nap_nevek[$nap]) ?> · <?= htmlspecialchars($ido) ?> · Aktív
    </div>
  </div>

  <div class="fade-up" style="margin-bottom:10px;animation-delay:.1s">
    <div class="gold-line"></div>
    <a href="/termek" class="glass card-hover" style="display:flex;justify-content:space-between;padding:20px 22px;border-radius:0 0 14px 14px;align-items:center;">
      <div>
        <p style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,.35);letter-spacing:.1em;">Élő nézet</p>
        <h2 style="font-family:'Playfair Display',serif;font-size:21px;color:white;">Összes terem</h2>
        <p style="font-size:13px;color:rgba(255,255,255,.45);">Szabad &amp; foglalt termek valós időben</p>
      </div>
      <span style="font-size:30px;">🏫</span>
    </a>
  </div>

  <div class="grid3 fade-up" style="animation-delay:.2s">
    <div style="display:flex;flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/tanar" class="glass card-hover" style="padding:18px;border-radius:0 0 14px 14px;flex-grow:1;">
        <span style="font-size:24px;display:block;margin-bottom:8px;">👩‍🏫</span>
        <h3 style="font-family:'Playfair Display',serif;font-size:17px;color:white;">Tanár kereső</h3>
        <p style="font-size:12px;color:rgba(255,255,255,.4);">Hol van most?</p>
      </a>
    </div>
    <div style="display:flex;flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/osztaly" class="glass card-hover" style="padding:18px;border-radius:0 0 14px 14px;flex-grow:1;">
        <span style="font-size:24px;display:block;margin-bottom:8px;">🎓</span>
        <h3 style="font-family:'Playfair Display',serif;font-size:17px;color:white;">Osztály nézet</h3>
        <p style="font-size:12px;color:rgba(255,255,255,.4);">Hol van most?</p>
      </a>
    </div>
    <div style="display:flex;flex-direction:column;">
      <div class="gold-line"></div>
      <a href="/qr" class="glass card-hover" style="padding:18px;border-radius:0 0 14px 14px;flex-grow:1;">
        <span style="font-size:24px;display:block;margin-bottom:8px;">🖨️</span>
        <h3 style="font-family:'Playfair Display',serif;font-size:17px;color:white;">QR Generátor</h3>
        <p style="font-size:12px;color:rgba(255,255,255,.4);">Nyomtatható kódok</p>
      </a>
    </div>
  </div>

  <p style="text-align:center;font-size:11px;color:rgba(255,255,255,.18);margin-top:24px;">Ticky v1.0 · Render · Supabase · PHP</p>
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

// ROUTES – $params mindig inicializálva van, nincs undefined warning
if ($uri === '/api/ping') { json_response(['status'=>'ok','time'=>date('Y-m-d H:i:s')]); }

if ($uri === '/termek') { require __DIR__.'/pages/termek.php'; exit; }

// Tanár – fix: $params inicializálva mielőtt olvasódna
$params = match_route('/tanar/{kod}', $uri);
if ($uri === '/tanar' || $params !== false) {
    if ($params !== false) $_GET['kod'] = $params['kod'];
    require __DIR__.'/pages/tanar.php'; exit;
}

// Osztály
$params = match_route('/osztaly/{kod}', $uri);
if ($uri === '/osztaly' || $params !== false) {
    if ($params !== false) $_GET['kod'] = $params['kod'];
    require __DIR__.'/pages/osztaly.php'; exit;
}

if ($uri === '/qr')      { require __DIR__.'/pages/qr.php'; exit; }
if ($uri === '/kijelzo') { require __DIR__.'/pages/kijelzo.php'; exit; }
if ($uri === '/support') { require __DIR__.'/pages/support.php'; exit; }

// Napirend – előbb mint /terem/{szam}!
$params = match_route('/terem/{szam}/nap', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/pages/napirend.php'; exit;
}

$params = match_route('/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/pages/terem.php'; exit;
}

if ($uri === '/api/termek')   { require __DIR__.'/api/termek.php'; exit; }
if ($uri === '/api/tanarok')  { require __DIR__.'/api/tanarok.php'; exit; }
if ($uri === '/api/osztalyok') { require __DIR__.'/api/osztalyok.php'; exit; }

$params = match_route('/api/tanar/{kod}/orarend', $uri);
if ($params !== false) {
    $_GET['kod'] = $params['kod'];
    require __DIR__.'/api/tanar_orarend.php'; exit;
}

$params = match_route('/api/osztaly/{kod}/orarend', $uri);
if ($params !== false) {
    $_GET['kod'] = $params['kod'];
    require __DIR__.'/api/osztaly_orarend.php'; exit;
}

$params = match_route('/api/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/terem.php'; exit;
}

$params = match_route('/api/napirend/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/napirend.php'; exit;
}

if ($uri === '/login') { require __DIR__.'/pages/login.php'; exit; }
if ($uri === '/api/auth/login')  { require __DIR__.'/api/auth/login.php'; exit; }
if ($uri === '/api/auth/logout') { require __DIR__.'/api/auth/logout.php'; exit; }
if ($uri === '/api/admin/felhasznalok') { require __DIR__.'/api/admin_felhasznalok.php'; exit; }
$params = match_route('/api/admin/felhasznalo/{id}', $uri);
if ($uri === '/api/admin/felhasznalo' || $params !== false) {
    if ($params !== false) $_GET['id'] = $params['id'];
    require __DIR__.'/api/admin_felhasznalo.php'; exit;
}
if ($uri === '/admin')          { require __DIR__.'/pages/admin.php'; exit; }
if ($uri === '/api/admin/tanar') { require __DIR__.'/api/admin_tanar.php'; exit; }
if ($uri === '/api/admin/szunetek') { require __DIR__.'/api/admin_szunet.php'; exit; }
$params = match_route('/api/admin/szunet/{id}', $uri);
if ($uri === '/api/admin/szunet' || $params !== false) {
    if ($params !== false) $_GET['id'] = $params['id'];
    require __DIR__.'/api/admin_szunet.php'; exit;
}

$params = match_route('/api/admin/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/admin_terem.php'; exit;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png"><style>body{background:#060f1e;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f0c76b;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza</a></body></html>';
exit;
