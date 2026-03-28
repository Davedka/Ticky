<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/app.php';

// Env var olvasÃƒÂ¡s
$admin_pw = trim(getenv('ADMIN_PASSWORD') ?: '');
if (empty($admin_pw)) $admin_pw = trim($_ENV['ADMIN_PASSWORD'] ?? '');

// Token generÃƒÂ¡lÃƒÂ¡s: HMAC-SHA256 a jelszÃƒÂ³bÃƒÂ³l
function makeToken(string $pw): string {
    return hash_hmac('sha256', 'ticky_admin_' . $pw, $pw);
}

$token    = makeToken($admin_pw);
$cookie   = $_COOKIE['ticky_auth'] ?? '';
$authed   = !empty($admin_pw) && hash_equals($token, $cookie);
$no_pw_set = empty($admin_pw);

// KijelentkezÃƒÂ©s
if (isset($_GET['logout'])) {
    setcookie('ticky_auth', '', time()-3600, '/', '', false, true);
    header('Location: /admin');
    exit;
}

// BelÃƒÂ©pÃƒÂ©s
$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    $input = trim($_POST['pw']);
    if ($admin_pw && $input === $admin_pw) {
        setcookie('ticky_auth', $token, time() + 8*3600, '/', '', false, true);
        header('Location: /admin');
        exit;
    } else {
        $login_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<?php render_app_head('Ticky - Admin', ['dm_mono' => true]); ?>
<style>
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;color:white;background-color:#04090f;min-height:100vh;overscroll-behavior:none;
    background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 90% 100%,rgba(200,151,42,.10) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);background-size:44px 44px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:2px;z-index:200;background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);box-shadow:0 0 16px rgba(200,151,42,.3);}
  a{text-decoration:none;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .spinning{animation:spin .7s linear infinite;}
  .skel{background:linear-gradient(90deg,rgba(255,255,255,.05) 25%,rgba(255,255,255,.09) 50%,rgba(255,255,255,.05) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
  @keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
  .navbar{position:sticky;top:0;z-index:100;height:64px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;background:rgba(4,9,15,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
  .layout{display:flex;min-height:calc(100vh - 64px);position:relative;z-index:10;}
  .sidebar{width:220px;flex-shrink:0;padding:20px 12px;border-right:1px solid rgba(255,255,255,.07);background:rgba(4,9,15,.4);}
  .sb-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;font-weight:500;color:rgba(255,255,255,.5);cursor:pointer;transition:all .15s;border:1px solid transparent;margin-bottom:4px;width:100%;background:transparent;font-family:'DM Sans',sans-serif;text-align:left;}
  .sb-btn:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.8);}
  .sb-btn.active{background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:#f0c76b;}
  .content{flex:1;padding:28px;overflow-y:auto;}
  .section{display:none;} .section.active{display:block;}
  .card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px 24px;margin-bottom:20px;}
  .card-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px;}
  .stat-box{border-radius:12px;padding:16px 18px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);}
  .stat-label{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px;}
  .stat-val{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;line-height:1;}
  .stat-sub{font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;}
  .stat-box.green{background:rgba(0,200,150,.07);border-color:rgba(0,200,150,.2);} .stat-box.green .stat-val{color:#00c896;}
  .stat-box.red{background:rgba(232,51,74,.07);border-color:rgba(232,51,74,.2);} .stat-box.red .stat-val{color:#ff6b82;}
  .stat-box.gold{background:rgba(200,151,42,.07);border-color:rgba(200,151,42,.2);} .stat-box.gold .stat-val{color:#f0c76b;}
  .status-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.06);}
  .status-row:last-child{border-bottom:none;}
  .status-label{font-size:14px;font-weight:500;color:rgba(255,255,255,.7);}
  .status-badge{display:flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
  .badge-ok{background:rgba(0,200,150,.15);border:1px solid rgba(0,200,150,.3);color:#00c896;}
  .badge-warn{background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.3);color:#f0c76b;}
  .badge-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
  .data-table{width:100%;border-collapse:collapse;}
  .data-table th{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);white-space:nowrap;}
  .data-table td{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px;color:rgba(255,255,255,.75);vertical-align:middle;}
  .data-table tr:last-child td{border-bottom:none;}
  .data-table tr:hover td{background:rgba(255,255,255,.025);}
  .inp{width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);color:white;font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s;}
  .inp::placeholder{color:rgba(255,255,255,.3);}
  .inp:focus{outline:none;border-color:rgba(200,151,42,.5);background:rgba(255,255,255,.07);}
  .inp-sm{padding:7px 12px;font-size:13px;}
  .btn{padding:9px 20px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;border:none;display:inline-flex;align-items:center;gap:6px;}
  .btn-gold{background:linear-gradient(135deg,#c8972a,#a07020);color:white;}
  .btn-gold:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(200,151,42,.3);}
  .btn-ghost{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);}
  .btn-ghost:hover{background:rgba(255,255,255,.12);color:white;}
  .btn-sm{padding:6px 14px;font-size:12px;border-radius:7px;}
  .search-wrap{position:relative;margin-bottom:14px;}
  .search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);pointer-events:none;}
  .search-wrap input{padding-left:36px;}
  input[type=search]::-webkit-search-cancel-button{display:none;}
  .tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;font-family:'DM Mono',monospace;}
  .tag-blue{background:rgba(26,74,138,.3);color:#7eb8f7;border:1px solid rgba(26,74,138,.4);}
  .tag-gold{background:rgba(200,151,42,.2);color:#f0c76b;border:1px solid rgba(200,151,42,.3);}
  .tag-green{background:rgba(0,200,150,.15);color:#00c896;border:1px solid rgba(0,200,150,.3);}
  .tag-red{background:rgba(232,51,74,.15);color:#ff6b82;border:1px solid rgba(232,51,74,.3);}
  .tag-purple{background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.3);}
  .tag-gray{background:rgba(255,255,255,.06);color:rgba(255,255,255,.45);border:1px solid rgba(255,255,255,.1);}
  .toast{position:fixed;bottom:24px;right:24px;z-index:500;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;backdrop-filter:blur(16px);animation:toastIn .3s cubic-bezier(.22,1,.36,1);box-shadow:0 8px 32px rgba(0,0,0,.4);}
  @keyframes toastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
  .toast.ok{background:rgba(0,200,150,.2);border:1px solid rgba(0,200,150,.4);color:#00c896;}
  .toast.err{background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.4);color:#ff6b82;}
  .toast.info{background:rgba(200,151,42,.2);border:1px solid rgba(200,151,42,.4);color:#f0c76b;}
  @media(max-width:768px){.sidebar{display:none;}.content{padding:16px;}}
</style>
</head>
<body>
<div class="top-line"></div>

<?php if (!$authed): ?>
<!-- LOGIN -->
<div class="relative z-10 flex items-center justify-center min-h-screen px-4">
  <div class="w-full max-w-sm slide-up">
    <div class="text-center mb-8">
      <a href="/" style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;display:inline-flex;align-items:center;gap:10px;">
        <span class="w-3 h-3 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
        Ticky
      </a>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-top:8px;">Admin Panel</p>
    </div>
    <div class="card" style="padding:32px;">
      <?php if ($no_pw_set): ?>
        <div style="text-align:center;padding:8px 0;">
          <span style="font-size:36px;display:block;margin-bottom:12px;">Ã¢Å¡Â Ã¯Â¸Â</span>
          <p style="font-size:14px;font-weight:600;color:#f0c76b;margin-bottom:8px;">Nincs jelszÃƒÂ³ beÃƒÂ¡llÃƒÂ­tva</p>
          <p style="font-size:12px;color:rgba(255,255,255,.4);line-height:1.7;">Add hozzÃƒÂ¡ az <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.65);">ADMIN_PASSWORD</span> env vÃƒÂ¡ltozÃƒÂ³t a Render dashboardon.</p>
        </div>
      <?php else: ?>
        <form method="POST" action="/admin">
          <div style="margin-bottom:16px;">
            <label style="font-size:11px;font-weight:600;color:rgba(255,255,255,.35);letter-spacing:.07em;text-transform:uppercase;display:block;margin-bottom:8px;">JelszÃƒÂ³</label>
            <input type="password" name="pw" class="inp" placeholder="Admin jelszÃƒÂ³Ã¢â‚¬Â¦" autofocus style="<?= $login_error?'border-color:rgba(232,51,74,.5);':'' ?>">
          </div>
          <?php if($login_error): ?>
            <div style="font-size:12px;color:#ff6b82;margin-bottom:12px;">Ã¢ÂÅ’ HibÃƒÂ¡s jelszÃƒÂ³</div>
          <?php endif; ?>
          <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:12px;font-size:14px;">BelÃƒÂ©pÃƒÂ©s Ã¢â€ â€™</button>
        </form>
        <p style="text-align:center;margin-top:14px;font-size:11px;color:rgba(255,255,255,.2);">JelszÃƒÂ³: <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.3);">ADMIN_PASSWORD</span> env var</p>
      <?php endif; ?>
    </div>
    <p style="text-align:center;margin-top:14px;"><a href="/" style="font-size:12px;color:rgba(255,255,255,.3);">Ã¢â€ Â Vissza a fÃ…â€˜oldalra</a></p>
  </div>
</div>

<?php else: ?>
<!-- ADMIN PANEL -->
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:10px;">
    <a href="/" style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;display:flex;align-items:center;gap:8px;">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">Ã‚Â·</span>
    <span style="font-size:13px;color:rgba(255,255,255,.45);">Admin</span>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <span style="font-size:12px;color:rgba(255,255,255,.3);font-family:'DM Mono',monospace;" id="nav-time">Ã¢â‚¬â€œ</span>
    <a href="/admin?logout=1" class="btn btn-ghost btn-sm">KilÃƒÂ©pÃƒÂ©s</a>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar">
    <button class="sb-btn active" onclick="showSection('dashboard')" id="sb-dashboard"><span>Ã°Å¸â€œÅ </span> Dashboard</button>
    <button class="sb-btn" onclick="showSection('tanarok')" id="sb-tanarok"><span>Ã°Å¸â€˜Â©Ã¢â‚¬ÂÃ°Å¸ÂÂ«</span> TanÃƒÂ¡rok</button>
    <button class="sb-btn" onclick="showSection('termek')" id="sb-termek"><span>Ã°Å¸ÂÂ«</span> Termek</button>
    <div style="border-top:1px solid rgba(255,255,255,.07);margin-top:16px;padding-top:16px;">
      <a href="/termek" class="sb-btn" style="display:flex;"><span>Ã°Å¸ÂÂ </span> Termek live</a>
      <a href="/kijelzo" class="sb-btn" style="display:flex;"><span>Ã°Å¸â€œÂº</span> KijelzÃ…â€˜</a>
      <a href="/qr" class="sb-btn" style="display:flex;"><span>Ã°Å¸â€“Â¨Ã¯Â¸Â</span> QR generÃƒÂ¡tor</a>
    </div>
  </aside>

  <main class="content">

    <!-- DASHBOARD -->
    <section class="section active" id="section-dashboard">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Dashboard</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:24px;">Rendszer ÃƒÂ¡llapot ÃƒÂ¡ttekintÃƒÂ©s</p>
      <div class="stat-grid" id="stat-grid">
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
      </div>
      <div class="card">
        <div class="card-title">Ã°Å¸â€Å’ Rendszer stÃƒÂ¡tusz</div>
        <div id="sys-status">
          <div class="status-row"><span class="status-label">API Backend</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">Supabase DB</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">IdÃ…â€˜zÃƒÂ³na</span><span class="tag tag-gold">Europe/Budapest</span></div>
          <div class="status-row"><span class="status-label">Mai nap</span><span class="tag tag-gold"><?= ['VasÃƒÂ¡rnap','HÃƒÂ©tfÃ…â€˜','Kedd','Szerda','CsÃƒÂ¼tÃƒÂ¶rtÃƒÂ¶k','PÃƒÂ©ntek','Szombat'][date('w')] ?></span></div>
        </div>
      </div>
      <div class="card">
        <div class="card-title">
          Ã°Å¸â€œâ€¦ Mai foglalt termek
          <button class="btn btn-ghost btn-sm" onclick="loadDashboard()">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="dash-ri"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            FrissÃƒÂ­t
          </button>
        </div>
        <div id="mai-list"><div class="skel" style="height:140px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- TANÃƒÂROK -->
    <section class="section" id="section-tanarok">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">TanÃƒÂ¡rok</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Teljes nevek hozzÃƒÂ¡adÃƒÂ¡sa a tanÃƒÂ¡r kÃƒÂ³dokhoz</p>
      <div class="card">
        <div class="card-title">Ã¢Å“ÂÃ¯Â¸Â NÃƒÂ©v szerkesztÃƒÂ©se</div>
        <p style="font-size:12px;color:rgba(255,255,255,.35);margin-bottom:14px;">Kattints a listÃƒÂ¡ban a Ã¢Å“ÂÃ¯Â¸Â gombra, vagy tÃƒÂ¶ltsd ki kÃƒÂ©zzel.</p>
        <div style="display:grid;grid-template-columns:140px 1fr auto;gap:8px;align-items:center;">
          <input type="text" id="edit-kod" class="inp inp-sm" placeholder="KÃƒÂ³d (ÃƒÂSZJ)" style="text-transform:uppercase;font-family:'DM Mono',monospace;">
          <input type="text" id="edit-nev" class="inp inp-sm" placeholder="Teljes nÃƒÂ©v (pl. KovÃƒÂ¡cs JÃƒÂ¡nos)">
          <button class="btn btn-gold btn-sm" onclick="saveTanarNev()">MentÃƒÂ©s</button>
        </div>
        <div id="edit-msg" style="display:none;font-size:12px;margin-top:8px;color:#00c896;"></div>
      </div>
      <div class="card">
        <div class="card-title">
          Ã°Å¸â€˜Â©Ã¢â‚¬ÂÃ°Å¸ÂÂ« TanÃƒÂ¡rlista
          <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="tanar-count">Ã¢â‚¬â€œ</span>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="tanar-search" class="inp inp-sm" placeholder="KeresÃƒÂ©s kÃƒÂ³d vagy nÃƒÂ©v alapjÃƒÂ¡nÃ¢â‚¬Â¦" oninput="filterTanarok()">
        </div>
        <div id="tanar-table"><div class="skel" style="height:280px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- TERMEK -->
    <section class="section" id="section-termek">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Termek</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:16px;">Emelet ÃƒÂ©s helyszÃƒÂ­n kezelÃƒÂ©se Ã¢â‚¬â€œ az ÃƒÂ©pÃƒÂ¼let/szÃƒÂ¡rny automatikusan fel van ismerve a terem szÃƒÂ¡m alapjÃƒÂ¡n</p>

      <!-- JelmagyarÃƒÂ¡zat -->
      <div class="card" style="padding:14px 20px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span style="font-size:12px;color:rgba(255,255,255,.35);font-weight:600;margin-right:4px;">Ãƒâ€°pÃƒÂ¼letek:</span>
          <span class="tag tag-blue">Ã°Å¸ÂÂ« FÃ…â€˜ÃƒÂ©pÃƒÂ¼let</span>
          <span class="tag tag-purple">Ã°Å¸ÂÂ  KollÃƒÂ©gium</span>
          <span class="tag" style="background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3);">Ã°Å¸â€Â§ MÃ…Â±hely</span>
          <span class="tag tag-green">Ã°Å¸Ââ€¹Ã¯Â¸Â Torna</span>
          <span style="font-size:12px;color:rgba(255,255,255,.35);margin-left:8px;">Az emelet mezÃ…â€˜t kÃƒÂ©zzel is megadhatod.</span>
        </div>
      </div>

      <div class="card">
        <div class="card-title">
          Ã°Å¸ÂÂ« Termek listÃƒÂ¡ja
          <div style="display:flex;align-items:center;gap:10px;">
            <button class="btn btn-gold btn-sm" onclick="autoDetectAll()">Ã¢Å¡Â¡ Auto-detektÃƒÂ¡lÃƒÂ¡s mind</button>
            <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="terem-count">Ã¢â‚¬â€œ</span>
          </div>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="terem-search" class="inp inp-sm" placeholder="KeresÃƒÂ©sÃ¢â‚¬Â¦" oninput="filterTermek()">
        </div>
        <div id="terem-table"><div class="skel" style="height:300px;border-radius:10px;"></div></div>
        <p style="font-size:11px;color:rgba(255,255,255,.22);margin-top:10px;">Ã°Å¸â€™Â¡ Az emelet szÃƒÂ¡ma elmenthetÃ…â€˜ Enter-rel vagy a mezÃ…â€˜ elhagyÃƒÂ¡sÃƒÂ¡val. Az Ã¢Å¡Â¡ Auto gomb az ÃƒÂ¶sszes termet detektÃƒÂ¡lja egyszerre.</p>
      </div>
    </section>

  </main>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const { formatHMS, weekdayIndex } = window.TickyTime
// Ã¢â€â‚¬Ã¢â€â‚¬ Ãƒâ€œra Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
setInterval(()=>{document.getElementById('nav-time').textContent=formatHMS()},1000)

// Ã¢â€â‚¬Ã¢â€â‚¬ Sidebar Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function showSection(id) {
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'))
  document.querySelectorAll('.sb-btn').forEach(b=>b.classList.remove('active'))
  document.getElementById('section-'+id).classList.add('active')
  document.getElementById('sb-'+id)?.classList.add('active')
  if(id==='dashboard') loadDashboard()
  if(id==='tanarok') loadTanarok()
  if(id==='termek') loadTermek()
}

// Ã¢â€â‚¬Ã¢â€â‚¬ Toast Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function toast(msg,type='ok',dur=3000) {
  const t=document.createElement('div'); t.className=`toast ${type}`; t.textContent=msg
  document.body.appendChild(t); setTimeout(()=>t.remove(),dur)
}

// Ã¢â€â‚¬Ã¢â€â‚¬ Ãƒâ€°PÃƒÅ“LET AUTO-DETEKTÃƒÂLÃƒÂS Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function detectEpulet(szam) {
  const s = szam.toUpperCase()

  // KollÃƒÂ©gium
  if (s.startsWith('K')) {
    const num = s.slice(1)
    if (['1','2','3'].includes(num) || num==='T') return { epulet:'KollÃƒÂ©gium', emelet: 0, tag:'tag-purple', emoji:'Ã°Å¸ÂÂ ' }
    if (num.startsWith('1')) return { epulet:'KollÃƒÂ©gium', emelet:1, tag:'tag-purple', emoji:'Ã°Å¸ÂÂ ' }
    if (num.startsWith('2')) return { epulet:'KollÃƒÂ©gium', emelet:2, tag:'tag-purple', emoji:'Ã°Å¸ÂÂ ' }
    if (num.startsWith('3')) return { epulet:'KollÃƒÂ©gium', emelet:3, tag:'tag-purple', emoji:'Ã°Å¸ÂÂ ' }
    return { epulet:'KollÃƒÂ©gium', emelet:0, tag:'tag-purple', emoji:'Ã°Å¸ÂÂ ' }
  }

  // MÃ…Â±hely
  if (s.startsWith('M')) {
    return { epulet:'MÃ…Â±hely folyosÃƒÂ³', emelet:0, tag:'tag-orange', emoji:'Ã°Å¸â€Â§' }
  }

  // Tornacsarnok
  if (s.startsWith('T') || s==='KT') {
    return { epulet:'Tornacsarnok', emelet:0, tag:'tag-green', emoji:'Ã°Å¸Ââ€¹Ã¯Â¸Â' }
  }

  // FÃ…â€˜ÃƒÂ©pÃƒÂ¼let Ã¢â‚¬â€œ szÃƒÂ¡m alapjÃƒÂ¡n
  const n = parseInt(s)
  if (!isNaN(n)) {
    if (n >= 1 && n <= 99)   return { epulet:'FÃ…â€˜ÃƒÂ©pÃƒÂ¼let', emelet:0, tag:'tag-blue', emoji:'Ã°Å¸ÂÂ«' }
    if (n >= 100 && n <= 199) return { epulet:'FÃ…â€˜ÃƒÂ©pÃƒÂ¼let', emelet:1, tag:'tag-blue', emoji:'Ã°Å¸ÂÂ«' }
    if (n >= 200 && n <= 299) return { epulet:'FÃ…â€˜ÃƒÂ©pÃƒÂ¼let', emelet:2, tag:'tag-blue', emoji:'Ã°Å¸ÂÂ«' }
    if (n >= 300 && n <= 399) return { epulet:'FÃ…â€˜ÃƒÂ©pÃƒÂ¼let', emelet:3, tag:'tag-blue', emoji:'Ã°Å¸ÂÂ«' }
  }

  return { epulet:'Ismeretlen', emelet:null, tag:'tag-gray', emoji:'Ã¢Ââ€œ' }
}

function emeletLabel(emelet) {
  if (emelet===null||emelet===undefined) return 'Ã¢â‚¬â€œ'
  if (emelet===0) return 'FÃƒÂ¶ldszint'
  return emelet+'. emelet'
}

// Ã¢â€â‚¬Ã¢â€â‚¬ DASHBOARD Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
async function loadDashboard() {
  const ic=document.getElementById('dash-ri'); ic?.classList.add('spinning')
  try {
    const [td,tnd,ta]=await Promise.all([
      fetch('/api/termek').then(r=>r.json()),
      fetch('/api/tanarok').then(r=>r.json()),
      fetch('/api/termek?allapot=1').then(r=>r.json()),
    ])
    const fo=(ta.termek||[]).filter(t=>t.allapot==='foglalt').length
    const sz=(ta.termek||[]).filter(t=>t.allapot==='szabad').length
    const nap=ta.nap
    document.getElementById('stat-grid').innerHTML=`
      <div class="stat-box gold"><div class="stat-label">Termek</div><div class="stat-val">${td.count||0}</div><div class="stat-sub">regisztrÃƒÂ¡lt terem</div></div>
      <div class="stat-box"><div class="stat-label">TanÃƒÂ¡rok</div><div class="stat-val">${tnd.count||0}</div><div class="stat-sub">tanÃƒÂ¡r kÃƒÂ³d</div></div>
      <div class="stat-box red"><div class="stat-label">Foglalt most</div><div class="stat-val">${nap===0?'Ã¢â‚¬â€œ':fo}</div><div class="stat-sub">${nap===0?'hÃƒÂ©tvÃƒÂ©ge':'aktÃƒÂ­v ÃƒÂ³ra'}</div></div>
      <div class="stat-box green"><div class="stat-label">Szabad most</div><div class="stat-val">${nap===0?'Ã¢â‚¬â€œ':sz}</div><div class="stat-sub">${nap===0?'hÃƒÂ©tvÃƒÂ©ge':'elÃƒÂ©rhetÃ…â€˜'}</div></div>`
    document.getElementById('sys-status').innerHTML=`
      <div class="status-row"><span class="status-label">API Backend</span><span class="status-badge badge-ok"><span class="badge-dot pulse" style="background:#00c896;"></span> Online</span></div>
      <div class="status-row"><span class="status-label">Supabase DB</span><span class="status-badge ${td.count>0?'badge-ok':'badge-warn'}"><span class="badge-dot" style="background:${td.count>0?'#00c896':'#f0c76b'};"></span> ${td.count>0?'KapcsolÃƒÂ³dva':'EllenÃ…â€˜rizd'}</span></div>
      <div class="status-row"><span class="status-label">IdÃ…â€˜zÃƒÂ³na</span><span class="tag tag-gold">Europe/Budapest</span></div>
      <div class="status-row"><span class="status-label">Mai nap</span><span class="status-badge ${nap===0?'badge-warn':'badge-ok'}"><span class="badge-dot" style="background:${nap===0?'#f0c76b':'#00c896'};"></span>${['VasÃƒÂ¡rnap','HÃƒÂ©tfÃ…â€˜','Kedd','Szerda','CsÃƒÂ¼tÃƒÂ¶rtÃƒÂ¶k','PÃƒÂ©ntek','Szombat'][weekdayIndex()]}</span></div>`
    const fo2=(ta.termek||[]).filter(t=>t.allapot==='foglalt')
    document.getElementById('mai-list').innerHTML=nap===0
      ?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Ã°Å¸Å’â„¢ HÃƒÂ©tvÃƒÂ©ge</div>`
      :!fo2.length
        ?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Ã¢Å“â€¦ Jelenleg nincs foglalt terem</div>`
        :`<table class="data-table"><thead><tr><th>Terem</th><th>TanÃƒÂ¡r</th><th>OsztÃƒÂ¡ly</th><th>TantÃƒÂ¡rgy</th><th>IdÃ…â€˜pont</th></tr></thead><tbody>${fo2.map(t=>`<tr><td><a href="/terem/${t.terem_szam}" target="_blank" style="color:#f0c76b;font-family:'Playfair Display',serif;font-size:15px;font-weight:700;">${t.terem_szam}</a></td><td>${t.aktualis?.tanar||'Ã¢â‚¬â€œ'}</td><td>${t.aktualis?.osztaly||'Ã¢â‚¬â€œ'}</td><td>${t.aktualis?.tantargy||'Ã¢â‚¬â€œ'}</td><td style="font-family:'DM Mono',monospace;font-size:12px;color:rgba(255,255,255,.4);">${t.aktualis?.kezdes||''}Ã¢â‚¬â€œ${t.aktualis?.vegzes||''}</td></tr>`).join('')}</tbody></table>`
  } catch(e){toast('BetÃƒÂ¶ltÃƒÂ©si hiba','err')}
  ic?.classList.remove('spinning')
}

// Ã¢â€â‚¬Ã¢â€â‚¬ TANÃƒÂROK Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
let allTanarok=[]
async function loadTanarok() {
  try {
    const d=await fetch('/api/tanarok').then(r=>r.json())
    allTanarok=d.tanarok||[]
    document.getElementById('tanar-count').textContent=allTanarok.length+' tanÃƒÂ¡r'
    renderTanarok(allTanarok)
  } catch(e){toast('BetÃƒÂ¶ltÃƒÂ©si hiba','err')}
}
function filterTanarok() {
  const q=document.getElementById('tanar-search').value.toLowerCase()
  renderTanarok(q?allTanarok.filter(t=>(t.rovid_nev+' '+(t.nev||'')).toLowerCase().includes(q)):allTanarok)
}
function renderTanarok(list) {
  if(!list.length){document.getElementById('tanar-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs talÃƒÂ¡lat</div>`;return}
  document.getElementById('tanar-table').innerHTML=`<table class="data-table"><thead><tr><th>KÃƒÂ³d</th><th>Teljes nÃƒÂ©v</th><th></th></tr></thead><tbody>${list.map(t=>`<tr>
    <td><span class="tag tag-blue">${t.rovid_nev}</span></td>
    <td style="color:${t.nev?'rgba(255,255,255,.85)':'rgba(255,255,255,.25)'};">${t.nev||'Ã¢â‚¬â€œ nincs megadva Ã¢â‚¬â€œ'}</td>
    <td><button class="btn btn-ghost btn-sm" onclick="editTanar('${t.rovid_nev}','${(t.nev||'').replace(/'/g,"\\'")}')">Ã¢Å“ÂÃ¯Â¸Â</button></td>
  </tr>`).join('')}</tbody></table>`
}
function editTanar(kod,nev) {
  document.getElementById('edit-kod').value=kod
  document.getElementById('edit-nev').value=nev
  document.getElementById('edit-nev').focus()
  window.scrollTo({top:0,behavior:'smooth'})
}
async function saveTanarNev() {
  const kod=document.getElementById('edit-kod').value.trim().toUpperCase()
  const nev=document.getElementById('edit-nev').value.trim()
  if(!kod){toast('Add meg a tanÃƒÂ¡r kÃƒÂ³dot!','err');return}
  try {
    const res=await fetch('/api/admin/tanar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({kod,nev})})
    const d=await res.json()
    if(d.ok){
      toast(`Ã¢Å“â€¦ ${kod} elmentve`,'ok')
      const m=document.getElementById('edit-msg'); m.style.display='block'; m.textContent='Ã¢Å“â€œ Elmentve'
      setTimeout(()=>m.style.display='none',2500)
      loadTanarok()
    } else toast(d.error||'Hiba','err')
  } catch(e){toast('API hiba','err')}
}

// Ã¢â€â‚¬Ã¢â€â‚¬ TERMEK Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
let allTermek=[]
async function loadTermek() {
  try {
    const d=await fetch('/api/termek').then(r=>r.json())
    allTermek=d.termek||[]
    document.getElementById('terem-count').textContent=allTermek.length+' terem'
    renderTermek(allTermek)
  } catch(e){toast('BetÃƒÂ¶ltÃƒÂ©si hiba','err')}
}
function filterTermek() {
  const q=document.getElementById('terem-search').value.toLowerCase()
  renderTermek(q?allTermek.filter(t=>t.terem_szam.toLowerCase().includes(q)):allTermek)
}
function renderTermek(list) {
  if(!list.length){document.getElementById('terem-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs talÃƒÂ¡lat</div>`;return}
  document.getElementById('terem-table').innerHTML=`
    <table class="data-table">
      <thead><tr><th>Terem</th><th>Ãƒâ€°pÃƒÂ¼let / SzÃƒÂ¡rny</th><th>Emelet (DB)</th><th>Linkek</th></tr></thead>
      <tbody>${list.map(t=>{
        const det=detectEpulet(t.terem_szam)
        const tagStyle=det.tag==='tag-orange'?'background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3);':''
        return `<tr>
          <td style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;">${t.terem_szam}</td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <span class="tag ${det.tag!=='tag-orange'?det.tag:''}" style="${tagStyle}">${det.emoji} ${det.epulet}</span>
              <span style="font-size:11px;color:rgba(255,255,255,.3);">${emeletLabel(det.emelet)}</span>
            </div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <input type="number" min="0" max="5"
                value="${t.emelet!==null&&t.emelet!==undefined?t.emelet:''}"
                placeholder="${det.emelet!==null?det.emelet:'Ã¢â‚¬â€œ'}"
                class="inp inp-sm" style="width:72px;"
                onblur="saveEmelet('${t.terem_szam}',this.value)"
                onkeydown="if(event.key==='Enter')this.blur()"
                title="0=FÃƒÂ¶ldszint, 1=1.em stb. Ã¢â‚¬â€œ ÃƒÂ¼res = auto">
              <span style="font-size:10px;color:rgba(255,255,255,.28);">em.</span>
              ${t.emelet===null?`<span title="Auto-detektÃƒÂ¡lt ÃƒÂ©rtÃƒÂ©k: ${det.emelet!==null?emeletLabel(det.emelet):'?'}" style="font-size:10px;color:rgba(200,151,42,.6);cursor:help;">auto</span>`:''}
            </div>
          </td>
          <td style="display:flex;gap:6px;">
            <a href="/terem/${t.terem_szam}" target="_blank" class="btn btn-ghost btn-sm">Ã°Å¸Å¡Âª</a>
            <a href="/terem/${t.terem_szam}/nap" target="_blank" class="btn btn-ghost btn-sm">Ã°Å¸â€œâ€¦</a>
          </td>
        </tr>`
      }).join('')}
      </tbody>
    </table>`
}

async function saveEmelet(szam,val) {
  const emelet=val===''?null:parseInt(val)
  try {
    const res=await fetch(`/api/admin/terem/${encodeURIComponent(szam)}`,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({emelet})})
    const d=await res.json()
    if(d.ok) toast(`Ã¢Å“â€¦ ${szam} Ã¢â‚¬â€œ ${emelet!==null?emelet+'. emelet':'auto'}`)
    else toast(d.error||'Hiba','err')
  } catch(e){toast('API hiba','err')}
}

async function autoDetectAll() {
  if(!allTermek.length){toast('ElÃ…â€˜bb tÃƒÂ¶ltsd be a termeket','info');return}
  toast('Ã¢Å¡Â¡ Auto-detektÃƒÂ¡lÃƒÂ¡s futÃ¢â‚¬Â¦','info',5000)
  let ok=0,err=0
  for(const t of allTermek) {
    if(t.emelet!==null) continue // skip ahol mÃƒÂ¡r van beÃƒÂ¡llÃƒÂ­tva
    const det=detectEpulet(t.terem_szam)
    if(det.emelet===null) continue
    try {
      const res=await fetch(`/api/admin/terem/${encodeURIComponent(t.terem_szam)}`,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({emelet:det.emelet})})
      const d=await res.json()
      d.ok?ok++:err++
    } catch(e){err++}
  }
  toast(`Ã¢Å“â€¦ ${ok} terem frissÃƒÂ­tve${err ? ', '+err+' hiba' : ''}`,'ok')
  loadTermek()
}

// Ã¢â€â‚¬Ã¢â€â‚¬ Init Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
loadDashboard()
</script>
<?php endif; ?>
</body>
</html>
