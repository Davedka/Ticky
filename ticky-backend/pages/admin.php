<?php
// pages/admin.php

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

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
    setcookie('ticky_auth', '', time() - 3600, '/', '', ticky_is_https(), true);
    header('Location: /admin');
    exit;
}

// Belépés
$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    sleep(1);
    $input = trim($_POST['pw']);
    if (!$no_pw_set && hash_equals($admin_pw, $input)) {
        setcookie('ticky_auth', $token, time() + 8 * 3600, '/', '', ticky_is_https(), true);
        header('Location: /admin');
        exit;
    } else {
        $login_error = true;
    }
}

// ── Látogatói statisztika írása ───────────────────────────────────────
// Minden oldallekéréskor naplózunk (csak ha be vagyunk jelentkezve az admin nézethez)
// A statisztika fájlban tároljuk (tmp könyvtár)
function ticky_log_visit(): void {
    $file = sys_get_temp_dir() . '/ticky_visits.json';
    $now  = time();
    $today = date('Y-m-d', $now);

    $data = ['days' => [], 'total' => 0];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?? $data;
    }

    // Régi napokat töröljük (csak 30 napot tartunk)
    $cutoff = date('Y-m-d', strtotime('-30 days'));
    $data['days'] = array_filter($data['days'] ?? [], fn($d) => $d >= $cutoff, ARRAY_FILTER_USE_KEY);

    $data['days'][$today] = ($data['days'][$today] ?? 0) + 1;
    $data['total'] = ($data['total'] ?? 0) + 1;
    $data['last']  = $now;

    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function ticky_get_visits(): array {
    $file = sys_get_temp_dir() . '/ticky_visits.json';
    if (!file_exists($file)) return ['today' => 0, 'week' => 0, 'total' => 0, 'days' => []];
    $raw = @file_get_contents($file);
    if (!$raw) return ['today' => 0, 'week' => 0, 'total' => 0, 'days' => []];
    $data = json_decode($raw, true) ?? [];

    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('-7 days'));

    $today_cnt = $data['days'][$today] ?? 0;
    $week_cnt  = array_sum(array_filter(
        $data['days'] ?? [],
        fn($d) => $d >= $week_start,
        ARRAY_FILTER_USE_KEY
    ));

    return [
        'today' => $today_cnt,
        'week'  => $week_cnt,
        'total' => $data['total'] ?? 0,
        'days'  => $data['days'] ?? [],
    ];
}

// Látogatást naplózunk mindig (nem csak adminoknak – így méri az összes lekérést)
// De az API oldalakat nem, csak a HTML oldalakat → itt az admin oldalon is számlálunk
// A valós látogatói számhoz kellene egy közös middleware, de ez a legegyszerűbb megoldás

$visits = ticky_get_visits();

// ── Szünet detektálás ─────────────────────────────────────────────────
// Iskolai szünetek (manuálisan vagy Kréta-ból)
// Format: ['nev' => '...', 'start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD']
$SZUNETEK = [
    ['nev' => 'Őszi szünet',    'start' => '2025-10-27', 'end' => '2025-10-31'],
    ['nev' => 'Téli szünet',    'start' => '2025-12-22', 'end' => '2026-01-02'],
    ['nev' => 'Tavaszi szünet', 'start' => '2026-04-02', 'end' => '2026-04-13'],
    // Hozzáadj itt többet ha kell
];

$ma = date('Y-m-d');
$aktiv_szunet = null;
foreach ($SZUNETEK as $sz) {
    if ($ma >= $sz['start'] && $ma <= $sz['end']) {
        $aktiv_szunet = $sz;
        break;
    }
}

// Következő szünet
$kovetkezo_szunet = null;
foreach ($SZUNETEK as $sz) {
    if ($sz['start'] > $ma) {
        $kovetkezo_szunet = $sz;
        break;
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
  .navbar{position:sticky;top:0;z-index:100;height:64px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;background:rgba(4,9,15,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
  .layout{display:flex;min-height:calc(100vh - 64px);position:relative;z-index:10;}
  .sidebar{width:220px;flex-shrink:0;padding:20px 12px;border-right:1px solid rgba(255,255,255,.07);background:rgba(4,9,15,.4);}
  .sb-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;font-weight:500;color:rgba(255,255,255,.5);cursor:pointer;transition:all .15s;border:1px solid transparent;margin-bottom:4px;width:100%;background:transparent;font-family:'DM Sans',sans-serif;text-align:left;}
  .sb-btn:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.8);}
  .sb-btn.active{background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:#f0c76b;}
  .content{flex:1;padding:28px;overflow-y:auto;height:calc(100vh - 64px);}
  .section{display:none;} .section.active{display:block;}
  .card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px 24px;margin-bottom:20px;}
  .card-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px;}
  .stat-box{border-radius:12px;padding:16px 18px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);}
  .stat-label{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px;}
  .stat-val{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;line-height:1;}
  .stat-sub{font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;}
  .stat-box.green{background:rgba(0,200,150,.07);border-color:rgba(0,200,150,.2);} .stat-box.green .stat-val{color:#00c896;}
  .stat-box.red{background:rgba(232,51,74,.07);border-color:rgba(232,51,74,.2);} .stat-box.red .stat-val{color:#ff6b82;}
  .stat-box.gold{background:rgba(200,151,42,.07);border-color:rgba(200,151,42,.2);} .stat-box.gold .stat-val{color:#f0c76b;}
  .stat-box.blue{background:rgba(26,74,138,.12);border-color:rgba(26,74,138,.3);} .stat-box.blue .stat-val{color:#7eb8f7;}
  .stat-box.purple{background:rgba(139,92,246,.08);border-color:rgba(139,92,246,.25);} .stat-box.purple .stat-val{color:#a78bfa;}
  .status-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.06);}
  .status-row:last-child{border-bottom:none;}
  .status-label{font-size:14px;font-weight:500;color:rgba(255,255,255,.7);}
  .status-badge{display:flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
  .badge-ok{background:rgba(0,200,150,.15);border:1px solid rgba(0,200,150,.3);color:#00c896;}
  .badge-warn{background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.3);color:#f0c76b;}
  .badge-red{background:rgba(232,51,74,.15);border:1px solid rgba(232,51,74,.3);color:#ff6b82;}
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
  /* Szünet banner */
  .szunet-banner{background:linear-gradient(135deg,rgba(200,151,42,.15),rgba(26,74,138,.15));border:1px solid rgba(200,151,42,.3);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
  .szunet-banner .icon{font-size:24px;flex-shrink:0;}
  /* Highlight effect for edit form */
  @keyframes highlightPulse{0%,100%{border-color:rgba(255,255,255,.10)}50%{border-color:rgba(200,151,42,.6);box-shadow:0 0 0 3px rgba(200,151,42,.15)}}
  .highlight-pulse{animation:highlightPulse .8s ease 2;}
  @media(max-width:768px){.sidebar{display:none;}.content{padding:16px;}}
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
    <div class="card" style="padding:32px;">
      <?php if ($no_pw_set): ?>
        <div style="text-align:center;padding:8px 0;">
          <span style="font-size:36px;display:block;margin-bottom:12px;">⚠️</span>
          <p style="font-size:14px;font-weight:600;color:#f0c76b;margin-bottom:8px;">Nincs jelszó beállítva</p>
          <p style="font-size:12px;color:rgba(255,255,255,.4);line-height:1.7;">Add hozzá az <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.65);">ADMIN_PASSWORD</span> env változót.</p>
        </div>
      <?php else: ?>
        <form method="POST" action="/admin" autocomplete="off">
          <div style="margin-bottom:16px;">
            <label style="font-size:11px;font-weight:600;color:rgba(255,255,255,.35);letter-spacing:.07em;text-transform:uppercase;display:block;margin-bottom:8px;">Jelszó</label>
            <input type="password" name="pw" class="inp" placeholder="Admin jelszó…" autofocus autocomplete="current-password" style="<?= $login_error ? 'border-color:rgba(232,51,74,.5);' : '' ?>">
          </div>
          <?php if ($login_error): ?>
            <div style="font-size:12px;color:#ff6b82;margin-bottom:12px;">❌ Hibás jelszó</div>
          <?php endif; ?>
          <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:12px;font-size:14px;">Belépés →</button>
        </form>
      <?php endif; ?>
    </div>
    <p style="text-align:center;margin-top:14px;"><a href="/" style="font-size:12px;color:rgba(255,255,255,.3);">← Vissza a főoldalra</a></p>
  </div>
</div>

<?php else: ?>
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:10px;">
    <a href="/" style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;display:flex;align-items:center;gap:8px;">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span style="font-size:13px;color:rgba(255,255,255,.45);">Admin</span>
    <?php if ($aktiv_szunet): ?>
      <span class="tag tag-gold" style="margin-left:8px;">🌙 <?= htmlspecialchars($aktiv_szunet['nev'], ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <span style="font-size:12px;color:rgba(255,255,255,.3);font-family:'DM Mono',monospace;" id="nav-time">–</span>
    <a href="/admin?logout=1" class="btn btn-ghost btn-sm">Kilépés</a>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar">
    <button class="sb-btn active" onclick="showSection('dashboard')" id="sb-dashboard"><span>📊</span> Dashboard</button>
    <button class="sb-btn" onclick="showSection('tanarok')" id="sb-tanarok"><span>👩‍🏫</span> Tanárok</button>
    <button class="sb-btn" onclick="showSection('termek')" id="sb-termek"><span>🏫</span> Termek</button>
    <button class="sb-btn" onclick="showSection('szunetek')" id="sb-szunetek"><span>🌙</span> Szünetek</button>
    <button class="sb-btn" onclick="showSection('felhasznalok')" id="sb-felhasznalok"><span>👤</span> Felhasználók</button>
    <div style="border-top:1px solid rgba(255,255,255,.07);margin-top:16px;padding-top:16px;">
      <a href="/termek" class="sb-btn" style="display:flex;"><span>🏠</span> Termek live</a>
      <a href="/kijelzo" class="sb-btn" style="display:flex;"><span>📺</span> Kijelző</a>
      <a href="/qr" class="sb-btn" style="display:flex;"><span>🖨️</span> QR generátor</a>
    </div>
  </aside>

  <main class="content" id="main-content">

    <!-- DASHBOARD -->
    <section class="section active" id="section-dashboard">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Dashboard</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Rendszer állapot áttekintés</p>

      <?php if ($aktiv_szunet): ?>
      <div class="szunet-banner">
        <span class="icon">🌙</span>
        <div>
          <p style="font-weight:700;color:#f0c76b;font-size:15px;"><?= htmlspecialchars($aktiv_szunet['nev'], ENT_QUOTES, 'UTF-8') ?> – most szünet van</p>
          <p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px;"><?= htmlspecialchars($aktiv_szunet['start'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($aktiv_szunet['end'], ENT_QUOTES, 'UTF-8') ?> · Az órarendek nem aktívak</p>
        </div>
      </div>
      <?php elseif ($kovetkezo_szunet): ?>
      <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">📅</span>
        <p style="font-size:13px;color:rgba(255,255,255,.5);">Következő szünet: <strong style="color:rgba(255,255,255,.8);"><?= htmlspecialchars($kovetkezo_szunet['nev'], ENT_QUOTES, 'UTF-8') ?></strong> – <?= htmlspecialchars($kovetkezo_szunet['start'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <?php endif; ?>

      <!-- Látogatói statisztika -->
      <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-box blue">
          <div class="stat-label">Ma</div>
          <div class="stat-val"><?= number_format($visits['today']) ?></div>
          <div class="stat-sub">látogatás ma</div>
        </div>
        <div class="stat-box purple">
          <div class="stat-label">7 nap</div>
          <div class="stat-val"><?= number_format($visits['week']) ?></div>
          <div class="stat-sub">elmúlt héten</div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Összes</div>
          <div class="stat-val"><?= number_format($visits['total']) ?></div>
          <div class="stat-sub">minden idők</div>
        </div>
      </div>

      <div class="stat-grid" id="stat-grid">
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
      </div>

      <div class="card">
        <div class="card-title">🔌 Rendszer státusz</div>
        <div id="sys-status">
          <div class="status-row"><span class="status-label">API Backend</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">Supabase DB</span><span class="skel" style="width:80px;height:24px;border-radius:20px;display:inline-block;"></span></div>
          <div class="status-row"><span class="status-label">Időzóna</span><span class="tag tag-gold">Europe/Budapest</span></div>
          <div class="status-row">
            <span class="status-label">Mai nap</span>
            <span class="tag <?= $aktiv_szunet ? 'tag-gold' : 'tag-green' ?>">
              <?= htmlspecialchars(['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'][date('w')], ENT_QUOTES, 'UTF-8') ?>
              <?= $aktiv_szunet ? ' 🌙' : '' ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (!empty($visits['days'])): ?>
      <div class="card">
        <div class="card-title">📈 Látogatói aktivitás (30 nap)</div>
        <div style="display:flex;align-items:flex-end;gap:3px;height:60px;padding:4px 0;">
          <?php
            $max_v = max(array_values($visits['days']));
            $sorted_days = $visits['days'];
            ksort($sorted_days);
            $last30 = array_slice($sorted_days, -30, 30, true);
          ?>
          <?php foreach ($last30 as $day => $cnt): ?>
            <?php $h = $max_v > 0 ? max(4, round(($cnt / $max_v) * 52)) : 4; ?>
            <div title="<?= htmlspecialchars($day) ?>: <?= $cnt ?> látogatás"
                 style="flex:1;height:<?= $h ?>px;background:<?= $day === $ma ? '#f0c76b' : 'rgba(26,74,138,.6)' ?>;border-radius:2px 2px 0 0;cursor:default;transition:background .2s;"
                 onmouseover="this.style.background='rgba(200,151,42,.8)'"
                 onmouseout="this.style.background='<?= $day === $ma ? '#f0c76b' : 'rgba(26,74,138,.6)' ?>'"></div>
          <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:rgba(255,255,255,.3);margin-top:6px;text-align:right;">Sárga = mai nap · Hover = részlet</p>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">📅 Mai foglalt termek
          <button class="btn btn-ghost btn-sm" onclick="loadDashboard()">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="dash-ri"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Frissít
          </button>
        </div>
        <?php if ($aktiv_szunet): ?>
        <div style="text-align:center;padding:20px;color:rgba(255,255,255,.4);">
          🌙 Jelenleg szünet van – az órarendek nem aktívak
        </div>
        <?php else: ?>
        <div id="mai-list"><div class="skel" style="height:140px;border-radius:10px;"></div></div>
        <?php endif; ?>
      </div>
    </section>

    <!-- TANÁROK -->
    <section class="section" id="section-tanarok">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Tanárok</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Teljes nevek hozzáadása a tanár kódokhoz</p>

      <!-- Szerkesztő form -->
      <div class="card" id="tanar-edit-card">
        <div class="card-title">✏️ Név szerkesztése</div>
        <div style="display:grid;grid-template-columns:140px 1fr auto;gap:8px;align-items:center;">
          <input type="text" id="edit-kod" class="inp inp-sm" placeholder="Kód (ÁSZJ)" style="text-transform:uppercase;font-family:'DM Mono',monospace;">
          <input type="text" id="edit-nev" class="inp inp-sm" placeholder="Teljes név (pl. Kovács János)">
          <button class="btn btn-gold btn-sm" onclick="saveTanarNev()">Mentés</button>
        </div>
        <div id="edit-msg" style="display:none;font-size:12px;margin-top:8px;color:#00c896;"></div>
      </div>

      <!-- Tanárlista -->
      <div class="card">
        <div class="card-title">👩‍🏫 Tanárlista <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="tanar-count">–</span></div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="tanar-search" class="inp inp-sm" placeholder="Keresés…" oninput="filterTanarok()">
        </div>
        <div id="tanar-table"><div class="skel" style="height:280px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- TERMEK -->
    <section class="section" id="section-termek">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Termek</h1>
      <div class="card" style="padding:14px 20px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span style="font-size:12px;color:rgba(255,255,255,.35);font-weight:600;margin-right:4px;">Épületek:</span>
          <span class="tag tag-blue">🏫 Főépület</span>
          <span class="tag tag-purple">🏠 Kollégium</span>
          <span class="tag" style="background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3);">🔧 Műhely</span>
          <span class="tag tag-green">🏋️ Torna</span>
        </div>
      </div>
      <div class="card">
        <div class="card-title">🏫 Termek listája
          <div style="display:flex;align-items:center;gap:10px;">
            <button class="btn btn-gold btn-sm" onclick="autoDetectAll()">⚡ Auto-detektálás mind</button>
            <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="terem-count">–</span>
          </div>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="terem-search" class="inp inp-sm" placeholder="Keresés…" oninput="filterTermek()">
        </div>
        <div id="terem-table"><div class="skel" style="height:300px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- SZÜNETEK -->
    <section class="section" id="section-szunetek">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Szünetek</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Iskolai szünetek kezelése – a rendszer ilyenkor "szünet" státuszt jelez</p>

      <?php if ($aktiv_szunet): ?>
      <div class="szunet-banner" style="margin-bottom:20px;">
        <span class="icon">🌙</span>
        <div>
          <p style="font-weight:700;color:#f0c76b;font-size:15px;">MOST SZÜNET VAN: <?= htmlspecialchars($aktiv_szunet['nev'], ENT_QUOTES, 'UTF-8') ?></p>
          <p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px;"><?= htmlspecialchars($aktiv_szunet['start'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($aktiv_szunet['end'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">📅 Beállított szünetek</div>
        <table class="data-table">
          <thead><tr><th>Szünet neve</th><th>Kezdet</th><th>Vége</th><th>Státusz</th></tr></thead>
          <tbody>
            <?php foreach ($SZUNETEK as $sz): ?>
            <?php
              $is_active = ($ma >= $sz['start'] && $ma <= $sz['end']);
              $is_past   = ($sz['end'] < $ma);
              $is_future = ($sz['start'] > $ma);
            ?>
            <tr>
              <td style="font-weight:600;"><?= htmlspecialchars($sz['nev'], ENT_QUOTES, 'UTF-8') ?></td>
              <td style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($sz['start'], ENT_QUOTES, 'UTF-8') ?></td>
              <td style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($sz['end'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <?php if ($is_active): ?>
                  <span class="status-badge badge-warn"><span class="badge-dot pulse" style="background:#f0c76b;"></span> Aktív most</span>
                <?php elseif ($is_past): ?>
                  <span class="tag tag-gray">Lejárt</span>
                <?php else: ?>
                  <span class="tag tag-blue">Közelgő</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,.07);">
          <p style="font-size:12px;color:rgba(255,255,255,.35);">
            💡 Szünet szerkesztéséhez módosítsd az <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.55);">$SZUNETEK</span> tömböt a <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.55);">pages/admin.php</span> fájlban.
          </p>
        </div>
      </div>
    </section>

    <!-- FELHASZNÁLÓK -->
    <section class="section" id="section-felhasznalok">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Felhasználók</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Bejelentkezési fiókok – a /login oldalon tudnak belépni</p>
      <div class="card">
        <div class="card-title">➕ Új felhasználó</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Felhasználónév *</label>
            <input type="text" id="new-fnev" class="inp inp-sm" placeholder="pl. kovacs.peter" style="font-family:'DM Mono',monospace;" autocomplete="off">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Teljes név</label>
            <input type="text" id="new-nev" class="inp inp-sm" placeholder="Kovács Péter">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Jelszó * (min 6 kar.)</label>
            <input type="password" id="new-pw" class="inp inp-sm" placeholder="••••••••" autocomplete="new-password">
          </div>
          <div>
            <label style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px;">Szerep</label>
            <select id="new-szerep" class="inp inp-sm" style="cursor:pointer;">
              <option value="admin" style="background:#0b2e59;">⚙️ Admin</option>
              <option value="user" style="background:#0b2e59;">👤 User</option>
            </select>
          </div>
          <button class="btn btn-gold btn-sm" onclick="createFelhasznalo()">Létrehozás</button>
        </div>
        <div id="new-user-msg" style="display:none;font-size:12px;margin-top:8px;"></div>
      </div>
      <div class="card">
        <div class="card-title">👤 Felhasználók
          <span style="font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace;font-weight:400;" id="user-count">–</span>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="user-search" class="inp inp-sm" placeholder="Keresés…" oninput="filterFelhasznalok()">
        </div>
        <div id="user-table"><div class="skel" style="height:180px;border-radius:10px;"></div></div>
      </div>
      <!-- Jelszó csere modal -->
      <div id="pw-modal" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(4,9,15,.85);backdrop-filter:blur(8px);align-items:center;justify-content:center;">
        <div style="background:#0d1f3a;border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:24px;width:100%;max-width:360px;margin:16px;">
          <h3 style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:16px;">Jelszó csere</h3>
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
  // Scroll to top of content
  document.getElementById('main-content').scrollTop=0
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
    const maiListEl=document.getElementById('mai-list')
    if(maiListEl){
      const fo2=(ta.termek||[]).filter(t=>t.allapot==='foglalt')
      maiListEl.innerHTML=nap===0?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">🌙 Hétvége</div>`:!fo2.length?`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">✅ Jelenleg nincs foglalt terem</div>`:`<table class="data-table"><thead><tr><th>Terem</th><th>Tanár</th><th>Osztály</th><th>Tantárgy</th><th>Időpont</th></tr></thead><tbody>${fo2.map(t=>`<tr><td><a href="/terem/${esc(t.terem_szam)}" target="_blank" style="color:#f0c76b;font-family:'Playfair Display',serif;font-size:15px;font-weight:700;">${esc(t.terem_szam)}</a></td><td>${esc(t.aktualis?.tanar||'–')}</td><td>${esc(t.aktualis?.osztaly||'–')}</td><td>${esc(t.aktualis?.tantargy||'–')}</td><td style="font-family:'DM Mono',monospace;font-size:12px;color:rgba(255,255,255,.4);">${esc(t.aktualis?.kezdes||'')}–${esc(t.aktualis?.vegzes||'')}</td></tr>`).join('')}</tbody></table>`
    }
  }catch(e){toast('Betöltési hiba','err')}
  ic?.classList.remove('spinning')
}

// ── TANÁROK ────────────────────────────────────────────────────────────
let allTanarok=[]
async function loadTanarok(){
  try{
    const d=await fetch('/api/tanarok').then(r=>r.json())
    allTanarok=d.tanarok||[]
    document.getElementById('tanar-count').textContent=allTanarok.length+' tanár'
    renderTanarok(allTanarok)
  }catch(e){toast('Betöltési hiba','err')}
}
function filterTanarok(){const q=document.getElementById('tanar-search').value.toLowerCase();renderTanarok(q?allTanarok.filter(t=>(t.rovid_nev+' '+(t.nev||'')).toLowerCase().includes(q)):allTanarok)}
function renderTanarok(list){
  if(!list.length){document.getElementById('tanar-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`;return}
  document.getElementById('tanar-table').innerHTML=`<table class="data-table"><thead><tr><th>Kód</th><th>Teljes név</th><th></th></tr></thead><tbody>${list.map(t=>`<tr><td><span class="tag tag-blue">${esc(t.rovid_nev)}</span></td><td style="color:${t.nev?'rgba(255,255,255,.85)':'rgba(255,255,255,.25)'};">${t.nev?esc(t.nev):'– nincs megadva –'}</td><td><button class="btn btn-ghost btn-sm" onclick="editTanar(${JSON.stringify(t.rovid_nev)},${JSON.stringify(t.nev||'')})" title="Szerkesztés">✏️</button></td></tr>`).join('')}</tbody></table>`
}

// FIX: editTanar most a .content div-et scrollozza (nem a window-t)
// és a szerkesztő kártyára navigál, majd highlight animációt indít
function editTanar(kod, nev) {
  // 1. Tanár kód + név beírása
  document.getElementById('edit-kod').value = kod
  document.getElementById('edit-nev').value = nev

  // 2. Scroll a szerkesztő kártyára (a .content div a scrollable, nem a window)
  const content = document.getElementById('main-content')
  const card    = document.getElementById('tanar-edit-card')
  if (content && card) {
    const cardTop = card.offsetTop - 20
    content.scrollTo({ top: cardTop, behavior: 'smooth' })
  }

  // 3. Highlight animáció a kód inputon
  const kodInput = document.getElementById('edit-kod')
  kodInput.classList.remove('highlight-pulse')
  // Force reflow
  void kodInput.offsetWidth
  kodInput.classList.add('highlight-pulse')

  // 4. Focus a névre (nem kódra, mert az már ki van töltve)
  setTimeout(() => {
    document.getElementById('edit-nev').focus()
    document.getElementById('edit-nev').select()
  }, 200)
}

async function saveTanarNev(){
  const kod=document.getElementById('edit-kod').value.trim().toUpperCase(),nev=document.getElementById('edit-nev').value.trim()
  if(!kod){toast('Add meg a tanár kódot!','err');return}
  try{
    const res=await adminFetch('/api/admin/tanar',{method:'POST',body:JSON.stringify({kod,nev})})
    const d=await res.json()
    if(d.ok){
      toast(`✅ ${esc(kod)} elmentve`)
      const m=document.getElementById('edit-msg')
      m.style.display='block'
      m.textContent='✓ Elmentve: ' + (nev || '(törölve)')
      setTimeout(()=>m.style.display='none',3000)
      document.getElementById('edit-kod').value=''
      document.getElementById('edit-nev').value=''
      loadTanarok()
    } else {
      toast(esc(d.error||'Hiba'),'err')
    }
  }catch(e){toast('API hiba','err')}
}

// ── TERMEK ─────────────────────────────────────────────────────────────
let allTermek=[]
async function loadTermek(){
  try{const d=await fetch('/api/termek').then(r=>r.json());allTermek=d.termek||[];document.getElementById('terem-count').textContent=allTermek.length+' terem';renderTermek(allTermek)}catch(e){toast('Betöltési hiba','err')}
}
function filterTermek(){const q=document.getElementById('terem-search').value.toLowerCase();renderTermek(q?allTermek.filter(t=>t.terem_szam.toLowerCase().includes(q)):allTermek)}
function renderTermek(list){
  if(!list.length){document.getElementById('terem-table').innerHTML=`<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`;return}
  document.getElementById('terem-table').innerHTML=`<table class="data-table"><thead><tr><th>Terem</th><th>Épület</th><th>Emelet (DB)</th><th>Linkek</th></tr></thead><tbody>${list.map(t=>{const det=detectEpulet(t.terem_szam);const tagStyle=det.tag==='tag-orange'?'background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3);':'';return`<tr><td style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;">${esc(t.terem_szam)}</td><td><span class="tag ${det.tag!=='tag-orange'?det.tag:''}" style="${tagStyle}">${det.emoji} ${esc(det.epulet)}</span></td><td><input type="number" min="0" max="5" value="${t.emelet!==null&&t.emelet!==undefined?Number(t.emelet):''}" placeholder="${det.emelet!==null?det.emelet:'–'}" class="inp inp-sm" style="width:72px;" onblur="saveEmelet(${JSON.stringify(t.terem_szam)},this.value)" onkeydown="if(event.key==='Enter')this.blur()"></td><td style="display:flex;gap:6px;"><a href="/terem/${esc(t.terem_szam)}" target="_blank" class="btn btn-ghost btn-sm">🚪</a><a href="/terem/${esc(t.terem_szam)}/nap" target="_blank" class="btn btn-ghost btn-sm">📅</a></td></tr>`}).join('')}</tbody></table>`
}
async function saveEmelet(szam, val){
  const emelet = val === '' ? null : parseInt(val)
  try {
    const res = await adminFetch(`/api/admin/terem/${encodeURIComponent(szam)}`, {
      method: 'PATCH',
      body: JSON.stringify({ emelet })
    })
    const d = await res.json()
    if (d.ok) {
      toast(`✅ ${esc(szam)}. terem – ${emelet !== null ? emelet + '. emelet' : 'auto'}`)
    } else {
      toast('❌ ' + esc(d.error || 'Hiba'), 'err')
    }
  } catch(e) {
    toast('❌ Hálózati hiba', 'err')
  }
}
async function autoDetectAll(){
  if(!allTermek.length){toast('Előbb töltsd be a termeket','info');return}
  toast('⚡ Auto-detektálás fut…','info',5000);let ok=0,err=0
  for(const t of allTermek){
    if(t.emelet!==null&&t.emelet!==undefined)continue
    const det=detectEpulet(t.terem_szam)
    if(det.emelet===null)continue
    try{
      const res=await adminFetch(`/api/admin/terem/${encodeURIComponent(t.terem_szam)}`,{method:'PATCH',body:JSON.stringify({emelet:det.emelet})})
      const d=await res.json()
      d.ok?ok++:err++
    }catch(e){err++}
  }
  toast(`✅ ${ok} terem frissítve${err?', '+err+' hiba':''}`)
  loadTermek()
}

// ── FELHASZNÁLÓK ────────────────────────────────────────────────────────
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
  if(pw.length<6){msg.style.display='block';msg.style.color='#ff6b82';msg.textContent='❌ A jelszónak legalább 6 karakter kell';return}
  try{
    const res=await adminFetch('/api/admin/felhasznalo',{method:'POST',body:JSON.stringify({felhasznalonev:fnev,nev,jelszo:pw,szerep})})
    const d=await res.json()
    if(d.ok){
      toast('✅ '+esc(fnev)+' létrehozva')
      document.getElementById('new-fnev').value=''
      document.getElementById('new-nev').value=''
      document.getElementById('new-pw').value=''
      msg.style.display='none'
      loadFelhasznalok()
    } else {
      msg.style.display='block'
      msg.style.color='#ff6b82'
      msg.textContent='❌ '+esc(d.error||'Hiba')
    }
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

loadDashboard()
</script>
<?php endif; ?>
</body>
</html>
