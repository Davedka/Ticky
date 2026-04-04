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

// 1. STATIKUS FÁJLOK KISZOLGÁLÁSA (Favicon, CSS, képek)
$file = __DIR__ . $uri;
if (file_exists($file) && is_file($file)) {
    return false;
}

// 2. FŐOLDAL (Landing Page)
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
    <link rel="icon" type="image/png" href="/favicon.png?v=<?= filemtime('favicon.png') ?>">
    <link rel="shortcut icon" href="/favicon.ico?v=<?= filemtime('favicon.ico') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;}
        html{scroll-behavior:smooth;}
        body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;color:white;
        background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
        .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
        .pulse{animation:pd 2s infinite;}
        @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
        .navbar{position:sticky;top:0;z-index:200;height:60px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
        .nav-brand{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;}
        .nav-link{font-size:13px;padding:7px 11px;color:rgba(255,255,255,.6);text-decoration:none;}
        .nav-link.gold{color:#c8972a;border:1px solid rgba(200,151,42,.2);border-radius:8px;}
        .main{padding:48px 20px;max-width:680px;margin:0 auto;text-align:center;}
        .hero h1{font-family:'Playfair Display',serif;font-size:70px;margin:0;}
        .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:20px;}
        @media(max-width:600px){.grid3{grid-template-columns:1fr;}}
        .card{padding:20px;border-radius:14px;text-decoration:none;color:white;display:block;}
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="nav-brand"><span class="pulse" style="width:8px;height:8px;border-radius:50%;background:#c8972a;display:inline-block;"></span> Ticky</a>
        <div class="nav-links">
            <a href="/termek" class="nav-link">Termek</a>
            <a href="/tanar" class="nav-link">Tanár</a>
            <a href="/admin" class="nav-link gold">⚙️ Admin</a>
        </div>
    </nav>

    <div class="main">
        <div class="hero">
            <h1>Ticky</h1>
            <p style="color:#4ade80;"><?= htmlspecialchars($nap_nevek[$nap]) ?> · <?= htmlspecialchars($ido) ?> · Aktív</p>
        </div>

        <a href="/termek" class="glass card" style="margin-top:40px; text-align:left;">
            <p style="font-size:10px; color:rgba(255,255,255,0.4);">ÉLŐ NÉZET</p>
            <h2 style="font-size:22px;">Összes terem 🏛️</h2>
        </a>

        <div class="grid3">
            <a href="/tanar" class="glass card"><h3>Tanár 👩‍🏫</h3></a>
            <a href="/osztaly" class="glass card"><h3>Osztály 🎓</h3></a>
            <a href="/qr" class="glass card"><h3>QR 📟</h3></a>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// 3. ÚTVONALAK (ROUTES) - JAVÍTOTT PARAMÉTER ÁTADÁSSAL
if ($uri === '/api/ping') { json_response(['status'=>'ok','time'=>date('Y-m-d H:i:s')]); }
if ($uri === '/termek') { require __DIR__.'/pages/termek.php'; exit; }
if ($uri === '/tanar' || ($params = match_route('/tanar/{kod}',$uri))) { $_GET['kod'] = $params['kod'] ?? null; require __DIR__.'/pages/tanar.php'; exit; }
if ($uri === '/osztaly' || ($params = match_route('/osztaly/{kod}',$uri))) { $_GET['kod'] = $params['kod'] ?? null; require __DIR__.'/pages/osztaly.php'; exit; }
if ($uri === '/qr') { require __DIR__.'/pages/qr.php'; exit; }
if ($uri === '/kijelzo') { require __DIR__.'/pages/kijelzo.php'; exit; }
if ($uri === '/support') { require __DIR__.'/pages/support.php'; exit; }
if ($params = match_route('/terem/{szam}/nap',$uri)) { $_GET['szam'] = $params['szam']; require __DIR__.'/pages/napirend.php'; exit; }
if ($params = match_route('/terem/{szam}',$uri)) { $_GET['szam'] = $params['szam']; require __DIR__.'/pages/terem.php'; exit; }

// API ÚTVONALAK
if ($uri === '/api/termek') { require __DIR__.'/api/termek.php'; exit; }
if ($uri === '/api/tanarok') { require __DIR__.'/api/tanarok.php'; exit; }
if ($uri === '/api/osztalyok') { require __DIR__.'/api/osztalyok.php'; exit; }
if ($params = match_route('/api/tanar/{kod}/orarend',$uri)) { $_GET['kod'] = $params['kod']; require __DIR__.'/api/tanar_orarend.php'; exit; }
if ($params = match_route('/api/osztaly/{kod}/orarend',$uri)) { $_GET['kod'] = $params['kod']; require __DIR__.'/api/osztaly_orarend.php'; exit; }
if ($params = match_route('/api/terem/{szam}',$uri)) { $_GET['szam'] = $params['szam']; require __DIR__.'/api/terem.php'; exit; }
if ($params = match_route('/api/napirend/{szam}',$uri)) { $_GET['szam'] = $params['szam']; require __DIR__.'/api/napirend.php'; exit; }

// ADMIN ÚTVONALAK
if ($uri === '/admin') { require __DIR__.'/pages/admin.php'; exit; }
if ($uri === '/api/admin/tanar') { require __DIR__.'/api/admin_tanar.php'; exit; }

// --- EZ A KRITIKUS SOR ---
if ($params = match_route('/api/admin/terem/{szam}',$uri)) { 
    $_GET['szam'] = $params['szam']; // Átadjuk a számot az API fájlnak!
    require __DIR__.'/api/admin_terem.php'; 
    exit; 
}

// 4. 404 HIBA
http_response_code(404);
echo "404 - Oldal nem található";
