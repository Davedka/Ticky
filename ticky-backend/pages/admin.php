<?php
// pages/admin.php – FIXED: private_response_headers() called before ANY output

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

// ── Biztonsági fejlécek LEGELSŐ – header() csak output előtt mehet! ──
private_response_headers();

$admin_pw  = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
$no_pw_set = $admin_pw === '';

function makeToken(string $pw): string {
    return hash_hmac('sha256', 'ticky_admin_' . $pw, $pw);
}

$token  = $no_pw_set ? '' : makeToken($admin_pw);
$cookie = $_COOKIE['ticky_auth'] ?? '';
$authed = !$no_pw_set && $cookie !== '' && hash_equals($token, $cookie);

// Kijelentkezés
if (isset($_GET['logout'])) {
    $isSecure = ticky_is_https();
    setcookie('ticky_auth', '', time() - 3600, '/', '', $isSecure, true);
    header('Location: /admin');
    exit;
}

// Belépés
$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    sleep(1);
    $input = trim($_POST['pw']);
    if (!$no_pw_set && hash_equals($admin_pw, $input)) {
        $isSecure = ticky_is_https();
        setcookie('ticky_auth', $token, time() + 8 * 3600, '/', '', $isSecure, true);
        header('Location: /admin');
        exit;
    } else {
        $login_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
  .navbar{position:sticky;top:0;z-index:100;height:70px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;background:rgba(4,9,15,.95);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.08);box-shadow:0 2px 8px rgba(0,0,0,.3);}
  .layout{display:flex;min-height:calc(100vh - 70px);position:relative;z-index:10;}
  .sidebar{width:240px;flex-shrink:0;padding:24px 16px;border-right:1px solid rgba(255,255,255,.08);background:linear-gradient(180deg,rgba(4,9,15,.6),rgba(4,9,15,.4));backdrop-filter:blur(8px);}
  .sb-btn{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;color:rgba(255,255,255,.55);cursor:pointer;transition:all .2s cubic-bezier(.22,1,.36,1);border:1px solid transparent;margin-bottom:8px;width:100%;background:transparent;font-family:'DM Sans',sans-serif;text-align:left;}
  .sb-btn:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.85);border-color:rgba(200,151,42,.2);}
  .sb-btn.active{background:rgba(200,151,42,.15);border-color:rgba(200,151,42,.4);color:#f0c76b;box-shadow:0 0 12px rgba(200,151,42,.15);}
  .content{flex:1;padding:36px 40px;overflow-y:auto;}
  .section{display:none;} .section.active{display:block;}
  .card{background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:28px;margin-bottom:24px;transition:all .3s;backdrop-filter:blur(8px);}
  .card:hover{border-color:rgba(200,151,42,.2);background:linear-gradient(135deg,rgba(255,255,255,.06),rgba(255,255,255,.02));}
  .card-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}
  .stat-box{border-radius:16px;padding:24px;border:1px solid rgba(255,255,255,.08);background:linear-gradient(135deg,rgba(255,255,255,.05),rgba(255,255,255,.01));backdrop-filter:blur(8px);transition:all .3s cubic-bezier(.22,1,.36,1);position:relative;overflow:hidden;}
  .stat-box::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(200,151,42,.1),rgba(200,151,42,.05));opacity:0;transition:opacity .3s;}
  .stat-box:hover{border-color:rgba(200,151,42,.3);background:linear-gradient(135deg,rgba(255,255,255,.08),rgba(255,255,255,.03));transform:translateY(-2px);}
  .stat-box:hover::before{opacity:1;}
  .stat-label{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:8px;}
  .stat-val{font-family:'Playfair Display',serif;font-size:36px;font-weight:700;color:white;line-height:1;}
  .stat-sub{font-size:12px;color:rgba(255,255,255,.4);margin-top:8px;}
  .stat-box.green{background:linear-gradient(135deg,rgba(0,200,150,.1),rgba(0,200,150,.03));border-color:rgba(0,200,150,.25);} .stat-box.green .stat-val{color:#00c896;}
  .stat-box.red{background:linear-gradient(135deg,rgba(232,51,74,.1),rgba(232,51,74,.03));border-color:rgba(232,51,74,.25);} .stat-box.red .stat-val{color:#ff6b82;}
  .stat-box.gold{background:linear-gradient(135deg,rgba(200,151,42,.1),rgba(200,151,42,.03));border-color:rgba(200,151,42,.25);} .stat-box.gold .stat-val{color:#f0c76b;}
  .status-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.05);}
  .status-row:last-child{border-bottom:none;}
  .status-label{font-size:14px;font-weight:500;color:rgba(255,255,255,.75);}
  .status-badge{display:flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;}
  .badge-ok{background:rgba(0,200,150,.15);border:1px solid rgba(0,200,150,.35);color:#00c896;}
  .badge-warn{background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.35);color:#f0c76b;}
  .badge-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
  .data-table{width:100%;border-collapse:collapse;}
  .data-table th{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:14px 16px;text-align:left;border-bottom:1px solid rgba(255,255,255,.1);white-space:nowrap;}
  .data-table td{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px;color:rgba(255,255,255,.8);vertical-align:middle;}
  .data-table tr:last-child td{border-bottom:none;}
  .data-table tr:hover td{background:rgba(200,151,42,.04);}
  .inp{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid rgba(255,255,255,.09);background:rgba(255,255,255,.04);color:white;font-family:'DM Sans',sans-serif;font-size:14px;transition:all .2s cubic-bezier(.22,1,.36,1);}
  .inp::placeholder{color:rgba(255,255,255,.35);}
  .inp:focus{outline:none;border-color:rgba(200,151,42,.5);background:rgba(255,255,255,.07);box-shadow:0 0 0 3px rgba(200,151,42,.08);}
  .inp-sm{padding:9px 12px;font-size:13px;}
  .btn{padding:10px 20px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s cubic-bezier(.22,1,.36,1);border:none;display:inline-flex;align-items:center;gap:6px;}
  .btn-gold{background:linear-gradient(135deg,#c8972a,#a07020);color:white;box-shadow:0 4px 12px rgba(200,151,42,.2);}
  .btn-gold:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(200,151,42,.35);}
  .btn-gold:active{transform:translateY(0);}
  .btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.75);}
  .btn-ghost:hover{background:rgba(255,255,255,.1);color:rgba(255,255,255,.95);border-color:rgba(200,151,42,.25);}
  .btn-sm{padding:8px 14px;font-size:12px;border-radius:9px;}
  .search-wrap{position:relative;margin-bottom:16px;}
  .search-wrap svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.35);pointer-events:none;}
  .search-wrap input{padding-left:40px;}
  input[type=search]::-webkit-search-cancel-button{display:none;}
  .tag{display:inline-block;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600;font-family:'DM Mono',monospace;backdrop-filter:blur(4px);}
  .tag-blue{background:rgba(26,74,138,.25);color:#7eb8f7;border:1px solid rgba(26,74,138,.35);}
  .tag-gold{background:rgba(200,151,42,.2);color:#f0c76b;border:1px solid rgba(200,151,42,.35);}
  .tag-green{background:rgba(0,200,150,.15);color:#00c896;border:1px solid rgba(0,200,150,.35);}
  .tag-red{background:rgba(232,51,74,.15);color:#ff6b82;border:1px solid rgba(232,51,74,.35);}
  .tag-purple{background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.35);}
  .tag-gray{background:rgba(255,255,255,.05);color:rgba(255,255,255,.45);border:1px solid rgba(255,255,255,.1);}
  .toast{position:fixed;bottom:28px;right:28px;z-index:500;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;backdrop-filter:blur(16px);animation:toastIn .3s cubic-bezier(.22,1,.36,1);box-shadow:0 12px 40px rgba(0,0,0,.5);}
  @keyframes toastIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
  .toast.ok{background:rgba(0,200,150,.2);border:1px solid rgba(0,200,150,.4);color:#00c896;}
  .toast.err{background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.4);color:#ff6b82;}
  .toast.info{background:rgba(200,151,42,.2);border:1px solid rgba(200,151,42,.4);color:#f0c76b;}
  select.inp{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.6)' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;background-color:rgba(255,255,255,.04);}
  @media(max-width:768px){.sidebar{display:none;}.content{padding:24px;}}
</style>
</head>
<body>
<div class="top-line"></div>

<?php if (!$authed): ?>
<div class="relative z-10 flex items-center justify-center min-h-screen px-4">
  <div class="w-full max-w-sm slide-up">
    <div class="text-center mb-8">
      <a href="/" style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;display:inline-flex;align-items:center;gap:10px;">
        <span class="w-3 h-3 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
        Ticky
      </a>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-top:8px;">Admin Panel</p>
    </div>
    <div class="card" style="padding:40px;">
      <?php if ($no_pw_set): ?>
        <div style="text-align:center;padding:16px 0;">
          <span style="font-size:48px;display:block;margin-bottom:16px;animation:bounce 2s infinite;">⚠️</span>
          <p style="font-size:16px;font-weight:600;color:#f0c76b;margin-bottom:12px;">Nincs jelszó beállítva</p>
          <p style="font-size:13px;color:rgba(255,255,255,.45);line-height:1.8;">Add hozzá az <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.6);">ADMIN_PASSWORD</span> env változót.</p>
        </div>
        <style>@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}</style>
      <?php else: ?>
        <form method="POST" action="/admin" autocomplete="off">
          <div style="margin-bottom:20px;">
            <label style="font-size:11px;font-weight:600;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:8px;">Jelszó</label>
            <input type="password" name="pw" class="inp" placeholder="Admin jelszó…" autofocus autocomplete="current-password" style="<?= $login_error ? 'border-color:rgba(232,51,74,.5);' : '' ?>">
          </div>
          <?php if ($login_error): ?>
            <div style="font-size:13px;color:#ff6b82;margin-bottom:16px;display:flex;align-items:center;gap:6px;">❌ Hibás jelszó</div>
          <?php endif; ?>
          <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:14px;font-size:15px;">Belépés →</button>
        </form>
        <p style="text-align:center;margin-top:18px;font-size:12px;color:rgba(255,255,255,.25);">Jelszó: <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.35);">ADMIN_PASSWORD</span> env var</p>
      <?php endif; ?>
    </div>
    <p style="text-align:center;margin-top:14px;"><a href="/" style="font-size:12px;color:rgba(255,255,255,.3);">← Vissza a főoldalra</a></p>
  </div>
</div>

<?php else: ?>
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/" style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;display:flex;align-items:center;gap:8px;text-decoration:none;">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 10px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.15);">·</span>
    <span style="font-size:14px;color:rgba(255,255,255,.45);font-weight:500;">Admin Panel</span>
  </div>
  <div style="display:flex;align-items:center;gap:14px;">
    <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;letter-spacing:.05em;" id="nav-time">–</span>
    <a href="/admin?logout=1" class="btn btn-ghost btn-sm" style="color:rgba(255,255,255,.7);">Kilépés</a>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar">
    <div style="margin-bottom:8px;">
      <button class="sb-btn active" onclick="showSection('dashboard')" id="sb-dashboard"><span>📊</span> Dashboard</button>
      <button class="sb-btn" onclick="showSection('tanarok')" id="sb-tanarok"><span>👩‍🏫</span> Tanárok</button>
      <button class="sb-btn" onclick="showSection('termek')" id="sb-termek"><span>🏫</span> Teremek</button>
      <button class="sb-btn" onclick="showSection('felhasznalok')" id="sb-felhasznalok"><span>👤</span> Felhasználók</button>
      <button class="sb-btn" onclick="showSection('szunetek')" id="sb-szunetek"><span>🌙</span> Szünetek</button>
    </div>
    <div style="border-top:1px solid rgba(255,255,255,.08);margin:16px 0;padding:16px 0;">
      <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.25);padding:0 16px;margin-bottom:12px;">Nézetek</div>
      <a href="/termek" class="sb-btn"><span>🏠</span> Teremek live</a>
      <a href="/kijelzo" class="sb-btn"><span>📺</span> Kijelző</a>
      <a href="/qr" class="sb-btn"><span>🖨️</span> QR generátor</a>
    </div>
  </aside>

  <main class="content">
    <!-- DASHBOARD -->
    <section class="section active" id="section-dashboard">
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:8px;">Dashboard</h1>
      <p style="font-size:14px;color:rgba(255,255,255,.45);margin-bottom:32px;">Rendszer állapot és mai tevékenység áttekintése</p>
      <div class="stat-grid" id="stat-grid">
        <div class="stat-box skel" style="height:80px;"></div><div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div><div class="stat-box skel" style="height:80px;"></div>
      </div>
      <div class="card">
        <div class="card-title">🔌 Rendszer státusz</div>
        <div id="sys-status" style="padding:0;">
          <div class="status-row"><span class="status-label">API Backend</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">Supabase DB</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">Időzóna</span><span class="tag tag-gold">Europe/Budapest</span></div>
          <div class="status-row"><span class="status-label">Mai nap</span><span class="tag tag-gold"><?= htmlspecialchars(['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'][date('w')], ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
      </div>
      <div class="card">
        <div class="card-title">📅 Aktuális foglaltsági állapot
          <button class="btn btn-ghost btn-sm" onclick="loadDashboard()">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="dash-ri"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Frissít
          </button>
        </div>
        <div id="mai-list"><div class="skel" style="height:140px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- TANÁROK -->
    <section class="section" id="section-tanarok">
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:8px;">Tanárok</h1>
      <p style="font-size:14px;color:rgba(255,255,255,.45);margin-bottom:32px;">Tanárkódok és teljes nevek kezelése</p>
      <div class="card">
        <div class="card-title">✏️ Tanár nevének szerkesztése</div>
        <div style="display:grid;grid-template-columns:150px 1fr auto;gap:12px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Tanár kód</label>
            <input type="text" id="edit-kod" class="inp inp-sm" placeholder="ÁSZJ" style="text-transform:uppercase;font-family:'DM Mono',monospace;">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Teljes név</label>
            <input type="text" id="edit-nev" class="inp inp-sm" placeholder="Pl. Kovács János">
          </div>
          <button class="btn btn-gold btn-sm" onclick="saveTanarNev()">Mentés</button>
        </div>
        <div id="edit-msg" style="display:none;font-size:12px;margin-top:12px;color:#00c896;"></div>
      </div>
      <div class="card">
        <div class="card-title">👩‍🏫 Tanárlista <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="tanar-count">–</span></div>
        <div style="margin-bottom:16px;">
          <div class="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" id="tanar-search" class="inp inp-sm" placeholder="Keresés tanár kód vagy név alapján…" oninput="filterTanarok()">
          </div>
        </div>
        <div id="tanar-table"><div class="skel" style="height:280px;border-radius:12px;"></div></div>
      </div>
    </section>

    <!-- TERMEK -->
    <section class="section" id="section-termek">
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:8px;">Teremek</h1>
      <p style="font-size:14px;color:rgba(255,255,255,.45);margin-bottom:32px;">Teremek regisztrációja és kezelése</p>
      <div class="card" style="padding:16px 24px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span style="font-size:12px;color:rgba(255,255,255,.4);font-weight:600;margin-right:4px;">Épületek:</span>
          <span class="tag tag-blue">🏫 Főépület</span>
          <span class="tag tag-purple">🏠 Kollégium</span>
          <span class="tag" style="background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.35);">🔧 Műhely</span>
          <span class="tag tag-green">🏋️ Torna</span>
        </div>
      </div>
      <div class="card">
        <div class="card-title">🏫 Teremek listája
          <div style="display:flex;align-items:center;gap:12px;">
            <button class="btn btn-gold btn-sm" onclick="autoDetectAll()">⚡ Auto-detektálás</button>
            <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="terem-count">–</span>
          </div>
        </div>
        <div style="margin-bottom:16px;">
          <div class="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" id="terem-search" class="inp inp-sm" placeholder="Keresés terem száma alapján…" oninput="filterTermek()">
          </div>
        </div>
        <div id="terem-table"><div class="skel" style="height:300px;border-radius:12px;"></div></div>
      </div>
    </section>

    <!-- FELHASZNÁLÓK -->
    <section class="section" id="section-felhasznalok">
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:8px;">Felhasználók</h1>
      <p style="font-size:14px;color:rgba(255,255,255,.45);margin-bottom:32px;">Bejelentkezési fiókok és jogosultságok kezelése</p>
      <div class="card">
        <div class="card-title">➕ Új felhasználó létrehozása</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Felhasználónév *</label>
            <input type="text" id="new-fnev" class="inp inp-sm" placeholder="kovacs.peter" style="font-family:'DM Mono',monospace;" autocomplete="off">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Teljes név</label>
            <input type="text" id="new-nev" class="inp inp-sm" placeholder="Kovács Péter">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 120px 100px auto;gap:12px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Jelszó * (min 6 kar.)</label>
            <input type="password" id="new-pw" class="inp inp-sm" placeholder="••••••••" autocomplete="new-password">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Szerep</label>
            <select id="new-szerep" class="inp inp-sm" style="cursor:pointer;">
              <option value="user" style="background:#0b2e59;">👤 User</option>
              <option value="admin" style="background:#0b2e59;">⚙️ Admin</option>
            </select>
          </div>
          <button class="btn btn-gold btn-sm" onclick="createFelhasznalo()">Hozzáadás</button>
        </div>
        <div id="new-user-msg" style="display:none;font-size:12px;margin-top:12px;"></div>
      </div>
      <div class="card">
        <div class="card-title">👤 Felhasználók
          <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="user-count">–</span>
        </div>
        <div style="margin-bottom:16px;">
          <div class="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" id="user-search" class="inp inp-sm" placeholder="Keresés felhasználónév vagy név alapján…" oninput="filterFelhasznalok()">
          </div>
        </div>
        <div id="user-table"><div class="skel" style="height:200px;border-radius:12px;"></div></div>
      </div>
      <!-- Jelszó csere modal -->
      <div id="pw-modal" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(4,9,15,.95);backdrop-filter:blur(12px);align-items:center;justify-content:center;">
        <div style="background:linear-gradient(135deg,rgba(255,255,255,.06),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:32px;width:100%;max-width:400px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,.5);">
          <h3 style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;margin-bottom:20px;">Jelszó csere</h3>
          <input type="hidden" id="pw-modal-id">
          <div style="margin-bottom:12px;">
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Új jelszó (min 6 kar.)</label>
            <input type="password" id="pw-modal-pw" class="inp" placeholder="••••••••" autocomplete="new-password">
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-gold" style="flex:1;" onclick="savePw()">Mentés</button>
            <button class="btn btn-ghost" style="flex:1;" onclick="closePwModal()">Mégse</button>
          </div>
          <div id="pw-modal-msg" style="display:none;font-size:12px;margin-top:8px;color:#ff6b82;"></div>
        </div>
      </div>
    </section>

    <!-- SZÜNETEK -->
    <section class="section" id="section-szunetek">
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:8px;">Szünetek</h1>
      <p style="font-size:14px;color:rgba(255,255,255,.45);margin-bottom:32px;">Szünet időszakok beállítása és kezelése</p>

      <!-- Aktív szünet banner -->
      <div id="aktiv-szunet-banner" style="display:none;margin-bottom:24px;padding:18px 24px;border-radius:14px;background:linear-gradient(135deg,rgba(200,151,42,.15),rgba(200,151,42,.05));border:1px solid rgba(200,151,42,.35);border-left:3px solid rgba(200,151,42,.8);color:#f0c76b;font-weight:600;font-size:14px;backdrop-filter:blur(8px);"></div>

      <!-- Új szünet -->
      <div class="card">
        <div class="card-title">➕ Új szünet hozzáadása</div>
        <div style="display:grid;grid-template-columns:1fr 150px 150px auto;gap:12px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Szünet neve *</label>
            <input type="text" id="sz-nev" class="inp inp-sm" placeholder="Pl. Tavaszi szünet">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Kezdet *</label>
            <input type="date" id="sz-kezdet" class="inp inp-sm" style="color-scheme:dark;">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">Vége *</label>
            <input type="date" id="sz-vege" class="inp inp-sm" style="color-scheme:dark;">
          </div>
          <button class="btn btn-gold btn-sm" onclick="createSzunet()">Hozzáadás</button>
        </div>
        <div id="sz-msg" style="display:none;font-size:12px;margin-top:12px;"></div>
      </div>

      <!-- Szünetek listája -->
      <div class="card">
        <div class="card-title">📅 Beállított szünetek <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="sz-count">–</span></div>
        <div id="sz-table"><div class="skel" style="height:180px;border-radius:12px;"></div></div>
        <p style="font-size:11px;color:rgba(255,255,255,.3);margin-top:16px;">💡 A szünetek a Supabase <code style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.45);">szunetek</code> táblában tárolódnak.</p>
      </div>

      <!-- Szerkesztő modal -->
      <div id="sz-modal" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(4,9,15,.95);backdrop-filter:blur(12px);align-items:center;justify-content:center;">
        <div style="background:linear-gradient(135deg,rgba(255,255,255,.06),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:32px;width:100%;max-width:480px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,.5);">
          <h3 style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;margin-bottom:20px;">Szünet szerkesztése</h3>
          <input type="hidden" id="sz-modal-id">
          <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
            <div>
              <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Szünet neve</label>
              <input type="text" id="sz-modal-nev" class="inp" placeholder="pl. Tavaszi szünet">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <div>
                <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Kezdet</label>
                <input type="date" id="sz-modal-kezdet" class="inp" style="color-scheme:dark;">
              </div>
              <div>
                <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Vége</label>
                <input type="date" id="sz-modal-vege" class="inp" style="color-scheme:dark;">
              </div>
            </div>
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-gold" style="flex:1;" onclick="saveSzunet()">Mentés</button>
            <button class="btn btn-ghost" style="flex:1;" onclick="closeSzModal()">Mégse</button>
          </div>
          <div id="sz-modal-msg" style="display:none;font-size:12px;margin-top:8px;color:#ff6b82;"></div>
        </div>
      </div>
    </section>

  </main>
</div>

<script>
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

function detectEpulet(szam) {
  const s=szam.toUpperCase()
  if(s.startsWith('K')){const num=s.slice(1);if(['1','2','3'].includes(num)||num==='T')return{epulet:'Kollégium',emelet:0,tag:'tag-purple',emoji:'🏠'};if(num.startsWith('1'))return{epulet:'Kollégium',emelet:1,tag:'tag-purple',emoji:'🏠'};if(num.startsWith('2'))return{epulet:'Kollégium',emelet:2,tag:'tag-purple',emoji:'🏠'};if(num.startsWith('3'))return{epulet:'Kollégium',emelet:3,tag:'tag-purple',emoji:'🏠'};return{epulet:'Kollégium',emelet:0,tag:'tag-purple',emoji:'🏠'}}
  if(s.startsWith('M'))return{epulet:'Műhely',emelet:0,tag:'tag-orange',emoji:'🔧'}
  if(s.startsWith('T')||s==='KT')return{epulet:'Tornacsarnok',emelet:0,tag:'tag-green',emoji:'🏋️'}
  const n=parseInt(s)
  if(!isNaN(n)){if(n>=1&&n<=99)return{epulet:'Főépület',emelet:0,tag:'tag-blue',emoji:'🏫'};if(n>=100&&n<=199)return{epulet:'Főépület',emelet:1,tag:'tag-blue',emoji:'🏫'};if(n>=200&&n<=299)return{epulet:'Főépület',emelet:2,tag:'tag-blue',emoji:'🏫'};if(n>=300&&n<=399)return{epulet:'Főépület',emelet:3,tag:'tag-blue',emoji:'🏫'}}
  return{epulet:'Ismeretlen',emelet:null,tag:'tag-gray',emoji:'❓'}
}

setInterval(()=>{document.getElementById('nav-time').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit',second:'2-digit'})},1000)

function showSection(id){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'))
  document.querySelectorAll('.sb-btn').forEach(b=>b.classList.remove('active'))
  document.getElementById('section-'+id).classList.add('active')
  document.getElementById('sb-'+id)?.classList.add('active')
  if(id==='dashboard')loadDashboard()
  if(id==='tanarok')loadTanarok()
  if(id==='termek')loadTermek()
  if(id==='felhasznalok')loadFelhasznalok()
  if(id==='szunetek')loadSzunetek()
}

function toast(msg,type='ok',dur=3000){
  const t=document.createElement('div');t.className=`toast ${type}`;t.textContent=msg
  document.body.appendChild(t);setTimeout(()=>t.remove(),dur)
}

function adminFetch(url,opts={}){
  return fetch(url,{...opts,headers:{'Content-Type':'application/json','X-Ticky-Admin':'1',...(opts.headers||{})}})
}

// DASHBOARD
async function loadDashboard(){
  const ic=document.getElementById('dash-ri');ic?.classList.add('spinning')
  try{
    const[td,tnd,ta]=await Promise.all([fetch('/api/termek').then(r=>r.json()),fetch('/api/tanarok').then(r=>r.json()),fetch('/api/termek?allapot=1').then(r=>r.json())])
    const fo=(ta.termek||[]).filter(t=>t.allapot==='foglalt').length,sz=(ta.termek||[]).filter(t=>t.allapot==='szabad').length,nap=ta.nap
    document.getElementById('stat-grid').innerHTML=`<div class="stat-box gold"><div class="stat-label">Termek</div><div class="stat-val">${td.count||0}</div><div class="stat-sub">regisztrált terem</div></div><div class="stat-box"><div class="stat-label">Tanárok</div><div class="stat-val">${tnd.count||0}</div><div class="stat-sub">tanár kód</div></div><div class="stat-box red"><div class="stat-label">Foglalt most</div><div class="stat-val">${nap===0?'–':fo}</div><div class="stat-sub">${nap===0?'hétvége':'aktív óra'}</div></div><div class="stat-box green"><div class="stat-label">Szabad most</div><div class="stat-val">${nap===0?'–':sz}</div><div class="stat-sub">${nap===0?'hétvége':'elérhető'}</div></div>`
    document.getElementById('sys-status').innerHTML=`<div class="status-row"><span class="status-label">API Backend</span><span class="status-badge badge-ok"><span class="badge-dot pulse" style="background:#00c896;"></span> Online</span></div><div class="status-row"><span class="status-label">Supabase DB</span><span class="status-badge ${td.count>0?'badge-ok':'badge-warn'}"><span class="badge-dot" style="background:${td.count>0?'#00c896':'#f0c76b'};"></span> ${td.count>0?'Kapcsolódva':'Ellenőrizd'}</span></div><div class="status-row"><span class="status-label">Időzóna</span><span class="tag tag-gold">Europe/Budapest</span></div><div class="status-row"><span class="status-label">Mai nap</span><span class="status-badge ${nap===0?'badge-warn':'badge-ok'}"><span class="badge-dot" style="background:${nap===0?'#f0c76b':'#00c896'};"></span>${esc(['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'][new Date().getDay()])}</span></div>`
    const fo2=(ta.termek||[]).filter(t=>t.allapot==='foglalt')
    document.getElementById('mai-list').innerHTML=nap===0?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">🌙 Hétvége</div>`:!fo2.length?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">✅ Jelenleg nincs foglalt terem</div>`:`<table class="data-table"><thead><tr><th>Terem</th><th>Tanár</th><th>Osztály</th><th>Tantárgy</th><th>Időpont</th></tr></thead><tbody>${fo2.map(t=>`<tr><td><a href="/terem/${esc(t.terem_szam)}" target="_blank" style="color:#f0c76b;font-family:'Playfair Display',serif;font-size:15px;font-weight:700;">${esc(t.terem_szam)}</a></td><td>${esc(t.aktualis?.tanar||'–')}</td><td>${esc(t.aktualis?.osztaly||'–')}</td><td>${esc(t.aktualis?.tantargy||'–')}</td><td style="font-family:'DM Mono',monospace;font-size:12px;color:rgba(255,255,255,.4);">${esc(t.aktualis?.kezdes||'')}–${esc(t.aktualis?.vegzes||'')}</td></tr>`).join('')}</tbody></table>`
  }catch(e){toast('Betöltési hiba','err')}
  ic?.classList.remove('spinning')
}

// TANÁROK
let allTanarok=[]
async function loadTanarok(){
  try{const d=await fetch('/api/tanarok').then(r=>r.json());allTanarok=d.tanarok||[];document.getElementById('tanar-count').textContent=allTanarok.length+' tanár';renderTanarok(allTanarok)}catch(e){toast('Betöltési hiba','err')}
}
function filterTanarok(){const q=document.getElementById('tanar-search').value.toLowerCase();renderTanarok(q?allTanarok.filter(t=>(t.rovid_nev+' '+(t.nev||'')).toLowerCase().includes(q)):allTanarok)}
function renderTanarok(list){
  if(!list.length){document.getElementById('tanar-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`;return}
  document.getElementById('tanar-table').innerHTML=`<table class="data-table"><thead><tr><th>Kód</th><th>Teljes név</th><th></th></tr></thead><tbody>${list.map(t=>`<tr><td><span class="tag tag-blue">${esc(t.rovid_nev)}</span></td><td style="color:${t.nev?'rgba(255,255,255,.85)':'rgba(255,255,255,.25)'};">${t.nev?esc(t.nev):'– nincs megadva –'}</td><td><button class="btn btn-ghost btn-sm" data-kod="${esc(t.rovid_nev)}" data-nev="${esc(t.nev||'')}" onclick="editTanarFromBtn(this)">✏️</button></td></tr>`).join('')}</tbody></table>`
}
function editTanarFromBtn(btn){editTanar(btn.dataset.kod, btn.dataset.nev)}
function editTanar(kod,nev){document.getElementById('edit-kod').value=kod;document.getElementById('edit-nev').value=nev;document.getElementById('edit-nev').focus();window.scrollTo({top:0,behavior:'smooth'})}
async function saveTanarNev(){
  const kod=document.getElementById('edit-kod').value.trim().toUpperCase(),nev=document.getElementById('edit-nev').value.trim()
  if(!kod){toast('Add meg a tanár kódot!','err');return}
  try{const res=await adminFetch('/api/admin/tanar',{method:'POST',body:JSON.stringify({kod,nev})});const d=await res.json();if(d.ok){toast(`✅ ${esc(kod)} elmentve`);const m=document.getElementById('edit-msg');m.style.display='block';m.textContent='✓ Elmentve';setTimeout(()=>m.style.display='none',2500);loadTanarok()}else toast(esc(d.error||'Hiba'),'err')}catch(e){toast('API hiba','err')}
}

// TERMEK
let allTermek=[]
async function loadTermek(){
  try{const d=await fetch('/api/termek').then(r=>r.json());allTermek=d.termek||[];document.getElementById('terem-count').textContent=allTermek.length+' terem';renderTermek(allTermek)}catch(e){toast('Betöltési hiba','err')}
}
function filterTermek(){const q=document.getElementById('terem-search').value.toLowerCase();renderTermek(q?allTermek.filter(t=>t.terem_szam.toLowerCase().includes(q)):allTermek)}
function renderTermek(list){
  if(!list.length){document.getElementById('terem-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`;return}
  document.getElementById('terem-table').innerHTML=`<table class="data-table"><thead><tr><th>Terem</th><th>Épület</th><th>Emelet (DB)</th><th>Linkek</th></tr></thead><tbody>${list.map(t=>{const det=detectEpulet(t.terem_szam);const tagStyle=det.tag==='tag-orange'?'background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3);':'';return`<tr><td style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;">${esc(t.terem_szam)}</td><td><span class="tag ${det.tag!=='tag-orange'?det.tag:''}" style="${tagStyle}">${det.emoji} ${esc(det.epulet)}</span></td><td><input type="number" min="0" max="5" value="${t.emelet!==null&&t.emelet!==undefined?Number(t.emelet):''}" placeholder="${det.emelet!==null?det.emelet:'–'}" class="inp inp-sm" style="width:72px;" onblur="saveEmelet(${JSON.stringify(t.terem_szam)},this.value)" onkeydown="if(event.key==='Enter')this.blur()"></td><td style="display:flex;gap:6px;"><a href="/terem/${esc(t.terem_szam)}" target="_blank" class="btn btn-ghost btn-sm">🚪</a><a href="/terem/${esc(t.terem_szam)}/nap" target="_blank" class="btn btn-ghost btn-sm">📅</a></td></tr>`}).join('')}</tbody></table>`
}
async function saveEmelet(szam,val){
  const emelet=val===''?null:parseInt(val)
  try{const res=await adminFetch(`/api/admin/terem/${encodeURIComponent(szam)}`,{method:'PATCH',body:JSON.stringify({emelet})});const d=await res.json();if(d.ok)toast(`✅ ${esc(szam)} – ${emelet!==null?emelet+'. emelet':'auto'}`);else toast(esc(d.error||'Hiba'),'err')}catch(e){toast('API hiba','err')}
}
async function autoDetectAll(){
  if(!allTermek.length){toast('Előbb töltsd be a termeket','info');return}
  toast('⚡ Auto-detektálás fut…','info',5000);let ok=0,err=0
  for(const t of allTermek){if(t.emelet!==null)continue;const det=detectEpulet(t.terem_szam);if(det.emelet===null)continue;try{const res=await adminFetch(`/api/admin/terem/${encodeURIComponent(t.terem_szam)}`,{method:'PATCH',body:JSON.stringify({emelet:det.emelet})});const d=await res.json();d.ok?ok++:err++}catch(e){err++}}
  toast(`✅ ${ok} terem frissítve${err?', '+err+' hiba':''}`)
  loadTermek()
}

// FELHASZNÁLÓK
let allFelhasznalok=[]
async function loadFelhasznalok(){
  try{const res=await adminFetch('/api/admin/felhasznalok');const d=await res.json();allFelhasznalok=d.felhasznalok||[];document.getElementById('user-count').textContent=allFelhasznalok.length+' fő';renderFelhasznalok(allFelhasznalok)}catch(e){toast('Betöltési hiba','err')}
}
function filterFelhasznalok(){const q=document.getElementById('user-search').value.toLowerCase();renderFelhasznalok(q?allFelhasznalok.filter(u=>(u.felhasznalonev+' '+(u.nev||'')).toLowerCase().includes(q)):allFelhasznalok)}
function renderFelhasznalok(list){
  const el=document.getElementById('user-table')
  if(!list.length){el.innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs felhasználó. Hozz létre egyet fent!</div>`;return}
  el.innerHTML=`<table class="data-table"><thead><tr><th>Felhasználónév</th><th>Teljes név</th><th>Szerep</th><th>Aktív</th><th>Létrehozva</th><th></th></tr></thead><tbody>${list.map(u=>`<tr>
    <td style="font-family:'DM Mono',monospace;">${esc(u.felhasznalonev)}</td>
    <td>${esc(u.nev||'–')}</td>
    <td><span class="tag ${u.szerep==='admin'?'tag-gold':'tag-blue'}">${u.szerep==='admin'?'⚙️ Admin':'👤 User'}</span></td>
    <td><button onclick="toggleAktiv(${JSON.stringify(u.id)},${!u.aktiv})" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:11px;${u.aktiv?'color:#4ade80;border-color:rgba(74,222,128,.3);':'color:#ff6b82;border-color:rgba(255,107,130,.3);'}">${u.aktiv?'✓ Aktív':'✗ Inaktív'}</button></td>
    <td style="font-family:'DM Mono',monospace;font-size:11px;color:rgba(255,255,255,.4);">${u.letrehozva?new Date(u.letrehozva).toLocaleDateString('hu-HU'):'–'}</td>
    <td style="display:flex;gap:5px;">
      <button class="btn btn-ghost btn-sm" onclick="openPwModal(${JSON.stringify(u.id)})" title="Jelszó csere">🔑</button>
      <button class="btn btn-ghost btn-sm" onclick="deleteFelhasznalo(${JSON.stringify(u.id)},${JSON.stringify(u.felhasznalonev)})" style="color:#ff6b82;" title="Törlés">🗑️</button>
    </td>
  </tr>`).join('')}</tbody></table>`
}
async function createFelhasznalo(){
  const fnev=document.getElementById('new-fnev').value.trim(),nev=document.getElementById('new-nev').value.trim(),pw=document.getElementById('new-pw').value,szerep=document.getElementById('new-szerep').value,msg=document.getElementById('new-user-msg')
  if(!fnev||!pw){msg.style.display='block';msg.style.color='#ff6b82';msg.textContent='❌ Felhasználónév és jelszó kötelező';return}
  try{
    const res=await adminFetch('/api/admin/felhasznalo',{method:'POST',body:JSON.stringify({felhasznalonev:fnev,nev,jelszo:pw,szerep})})
    const d=await res.json()
    if(d.ok){toast('✅ '+esc(fnev)+' létrehozva');document.getElementById('new-fnev').value='';document.getElementById('new-nev').value='';document.getElementById('new-pw').value='';msg.style.display='none';loadFelhasznalok()}
    else{msg.style.display='block';msg.style.color='#ff6b82';msg.textContent='❌ '+esc(d.error||'Hiba')}
  }catch(e){toast('API hiba','err')}
}
async function toggleAktiv(id,aktiv){
  try{const res=await adminFetch('/api/admin/felhasznalo/'+id,{method:'PATCH',body:JSON.stringify({aktiv})});const d=await res.json();if(d.ok){toast(aktiv?'✅ Aktiválva':'⛔ Deaktiválva');loadFelhasznalok()}else toast(esc(d.error||'Hiba'),'err')}catch(e){toast('API hiba','err')}
}
async function deleteFelhasznalo(id,fnev){
  if(!confirm(fnev+' törlése? Ez nem visszavonható.'))return
  try{const res=await adminFetch('/api/admin/felhasznalo/'+id,{method:'DELETE'});const d=await res.json();if(d.ok){toast('🗑️ '+esc(fnev)+' törölve');loadFelhasznalok()}else toast(esc(d.error||'Hiba'),'err')}catch(e){toast('API hiba','err')}
}
function openPwModal(id){document.getElementById('pw-modal-id').value=id;document.getElementById('pw-modal-pw').value='';document.getElementById('pw-modal-msg').style.display='none';document.getElementById('pw-modal').style.display='flex';setTimeout(()=>document.getElementById('pw-modal-pw').focus(),60)}
function closePwModal(){document.getElementById('pw-modal').style.display='none'}
async function savePw(){
  const id=document.getElementById('pw-modal-id').value,pw=document.getElementById('pw-modal-pw').value,msg=document.getElementById('pw-modal-msg')
  if(pw.length<6){msg.style.display='block';msg.textContent='Legalább 6 karakter';return}
  try{const res=await adminFetch('/api/admin/felhasznalo/'+id,{method:'PATCH',body:JSON.stringify({jelszo:pw})});const d=await res.json();if(d.ok){toast('✅ Jelszó frissítve');closePwModal()}else{msg.style.display='block';msg.textContent=esc(d.error||'Hiba')}}catch(e){msg.style.display='block';msg.textContent='API hiba'}
}
document.getElementById('pw-modal')?.addEventListener('click',e=>{if(e.target===document.getElementById('pw-modal'))closePwModal()})


// ── SZÜNETEK ─────────────────────────────────────────────────────────
let allSzunetek = []

async function loadSzunetek() {
  try {
    const res = await adminFetch('/api/admin/szunetek')
    const d   = await res.json()
    allSzunetek = d.szunetek || []
    document.getElementById('sz-count').textContent = allSzunetek.length + ' szünet'
    renderSzunetek(allSzunetek)
    // Aktív szünet banner
    const ma  = new Date().toISOString().slice(0,10)
    const aktiv = allSzunetek.find(s => ma >= s.kezdet && ma <= s.vege)
    const banner = document.getElementById('aktiv-szunet-banner')
    if (aktiv) { banner.style.display='block'; banner.innerHTML='🌙 MOST SZÜNET VAN: <strong>' + esc(aktiv.nev) + '</strong> &nbsp;(' + esc(aktiv.kezdet) + ' – ' + esc(aktiv.vege) + ')' }
    else { banner.style.display='none' }
  } catch(e) { toast('Szünetek betöltési hiba','err') }
}

function renderSzunetek(list) {
  const el = document.getElementById('sz-table')
  if (!list.length) { el.innerHTML = `<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincsenek szünetek beállítva</div>`; return }
  const ma = new Date().toISOString().slice(0,10)
  el.innerHTML = `<table class="data-table"><thead><tr><th>Szünet neve</th><th>Kezdet</th><th>Vége</th><th>Státusz</th><th></th></tr></thead><tbody>${list.map(s => {
    let status, stCls
    if (ma < s.kezdet)      { status='Közelgő';   stCls='tag-blue' }
    else if (ma <= s.vege)  { status='● Aktív most'; stCls='tag-gold' }
    else                    { status='Lejárt';     stCls='tag-gray' }
    return `<tr>
      <td style="font-weight:600;">${esc(s.nev)}</td>
      <td style="font-family:'DM Mono',monospace;font-size:12px;">${esc(s.kezdet)}</td>
      <td style="font-family:'DM Mono',monospace;font-size:12px;">${esc(s.vege)}</td>
      <td><span class="tag ${stCls}">${status}</span></td>
      <td style="display:flex;gap:5px;">
        <button class="btn btn-ghost btn-sm" onclick="openSzModal('${esc(s.id)}','${esc(s.nev)}','${esc(s.kezdet)}','${esc(s.vege)}')">✏️</button>
        <button class="btn btn-ghost btn-sm" onclick="deleteSzunet('${esc(s.id)}','${esc(s.nev)}')" style="color:#ff6b82;">🗑️</button>
      </td>
    </tr>`
  }).join('')}</tbody></table>`
}

async function createSzunet() {
  const nev    = document.getElementById('sz-nev').value.trim()
  const kezdet = document.getElementById('sz-kezdet').value
  const vege   = document.getElementById('sz-vege').value
  const msg    = document.getElementById('sz-msg')
  if (!nev || !kezdet || !vege) { msg.style.display='block'; msg.style.color='#ff6b82'; msg.textContent='❌ Minden mező kötelező'; return }
  try {
    const res = await adminFetch('/api/admin/szunet', { method:'POST', body: JSON.stringify({nev, kezdet, vege}) })
    const d   = await res.json()
    if (d.ok) {
      toast('✅ Szünet hozzáadva')
      document.getElementById('sz-nev').value    = ''
      document.getElementById('sz-kezdet').value = ''
      document.getElementById('sz-vege').value   = ''
      msg.style.display = 'none'
      loadSzunetek()
    } else { msg.style.display='block'; msg.style.color='#ff6b82'; msg.textContent='❌ ' + esc(d.error||'Hiba') }
  } catch(e) { toast('API hiba','err') }
}

function openSzModal(id, nev, kezdet, vege) {
  document.getElementById('sz-modal-id').value    = id
  document.getElementById('sz-modal-nev').value   = nev
  document.getElementById('sz-modal-kezdet').value= kezdet
  document.getElementById('sz-modal-vege').value  = vege
  document.getElementById('sz-modal-msg').style.display = 'none'
  document.getElementById('sz-modal').style.display = 'flex'
}
function closeSzModal() { document.getElementById('sz-modal').style.display = 'none' }

async function saveSzunet() {
  const id     = document.getElementById('sz-modal-id').value
  const nev    = document.getElementById('sz-modal-nev').value.trim()
  const kezdet = document.getElementById('sz-modal-kezdet').value
  const vege   = document.getElementById('sz-modal-vege').value
  const msg    = document.getElementById('sz-modal-msg')
  if (!nev || !kezdet || !vege) { msg.style.display='block'; msg.textContent='Minden mező kötelező'; return }
  try {
    const res = await adminFetch('/api/admin/szunet/' + id, { method:'PATCH', body: JSON.stringify({nev, kezdet, vege}) })
    const d   = await res.json()
    if (d.ok) { toast('✅ Szünet frissítve'); closeSzModal(); loadSzunetek() }
    else { msg.style.display='block'; msg.textContent=esc(d.error||'Hiba') }
  } catch(e) { msg.style.display='block'; msg.textContent='API hiba' }
}

async function deleteSzunet(id, nev) {
  if (!confirm(nev + ' törlése?')) return
  try {
    const res = await adminFetch('/api/admin/szunet/' + id, { method:'DELETE' })
    const d   = await res.json()
    if (d.ok) { toast('🗑️ ' + esc(nev) + ' törölve'); loadSzunetek() }
    else toast(esc(d.error||'Hiba'), 'err')
  } catch(e) { toast('API hiba','err') }
}

document.getElementById('sz-modal')?.addEventListener('click', e => {
  if (e.target === document.getElementById('sz-modal')) closeSzModal()
})

loadDashboard()
</script>
<?php endif; ?>
</body>
</html>
