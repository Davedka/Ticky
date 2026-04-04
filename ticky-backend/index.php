<?php
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';
handle_cors();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége',1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
    $nap = mai_nap(); $ido = aktualis_ido();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticky</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #060f1e; color: white; min-height: 100vh;
               background-image: radial-gradient(ellipse at 15% 10%, rgba(26,74,138,0.5), transparent), radial-gradient(ellipse at 85% 85%, rgba(200,151,42,0.15), transparent); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; transition: 0.3s; }
        .glass:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.2); }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar { position: fixed; left: 0; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 10px; padding: 10px; background: rgba(6,15,30,0.8); border-radius: 0 10px 10px 0; border: 1px solid rgba(255,255,255,0.1); }
        .main-container { max-width: 700px; margin: 60px auto; padding: 0 20px; text-align: center; }
        h1 { font-family: 'Playfair Display', serif; font-size: 80px; line-height: 1; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="https://esemenynaptar.onrender.com/" title="Naptár">📅</a>
        <a href="/support" title="Support">✉️</a>
        <a href="https://github.com/Davedka/Ticky/issues/new" title="Bug">🐛</a>
    </div>
    <nav class="navbar">
        <div style="font-weight:700; font-family:'Playfair Display';">Ticky</div>
        <div class="flex gap-4 text-xs opacity-60">
            <a href="/termek">Termek</a><a href="/tanar">Tanár</a><a href="/osztaly">Osztály</a><a href="/qr">QR</a>
            <a href="/admin" style="color:#c8972a; border: 1px solid rgba(200,151,42,0.3); padding: 2px 8px; border-radius:5px;">Admin</a>
        </div>
    </nav>
    <div class="main-container">
        <h1>Ticky</h1>
        <p class="opacity-40 mb-4">Digitális terem-azonosító rendszer</p>
        <p class="text-green-400 text-xs mb-10">● <?= $nap_nevek[$nap] ?> · <?= $ido ?> · Aktív</p>
        <a href="/termek" class="glass p-6 mb-4 block text-left relative">
            <span class="text-[10px] opacity-30 block">ÉLŐ NÉZET</span>
            <span class="text-xl font-bold">Összes terem</span>
            <span class="absolute right-6 top-1/2 -translate-y-1/2 text-2xl">🏛️</span>
        </a>
        <div class="grid grid-cols-3 gap-3">
            <a href="/tanar" class="glass p-5"> <span class="block text-xl mb-2">👩‍🏫</span> <span class="text-sm font-bold">Tanár kereső</span> </a>
            <a href="/osztaly" class="glass p-5"> <span class="block text-xl mb-2">🎓</span> <span class="text-sm font-bold">Osztály nézet</span> </a>
            <a href="/qr" class="glass p-5"> <span class="block text-xl mb-2">📟</span> <span class="text-sm font-bold">QR Generátor</span> </a>
        </div>
    </div>
</body>
</html>
<?php exit; }

// ROUTER - Itt adjuk át a változókat
if ($p = match_route('/api/admin/terem/{szam}', $uri)) { $_GET['szam'] = $p['szam']; require 'api/admin_terem.php'; exit; }
if ($p = match_route('/terem/{szam}', $uri)) { $_GET['szam'] = $p['szam']; require 'pages/terem.php'; exit; }
// ... a többi útvonalad változatlan marad ...
