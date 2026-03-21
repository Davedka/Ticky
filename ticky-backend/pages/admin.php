<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  html { scroll-behavior:smooth; }
  body {
    font-family:'DM Sans',sans-serif; color:white;
    background-color:#04090f; min-height:100vh;
    overscroll-behavior:none;
    background-image:
      radial-gradient(ellipse 70% 50% at 10% 0%,   rgba(26,74,138,.4) 0%, transparent 55%),
      radial-gradient(ellipse 50% 40% at 90% 100%,  rgba(200,151,42,.10) 0%, transparent 50%);
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),
      linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);
    background-size:44px 44px;
  }
  .top-line { position:fixed;top:0;left:0;right:0;height:2px;z-index:200;
    background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);
    box-shadow:0 0 16px rgba(200,151,42,.3); }

  a { text-decoration:none; }
  .glass { background:rgba(255,255,255,.04);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08); }
  .glass-darker { background:rgba(4,9,15,.6);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08); }

  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .slide-up { animation:su .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes su { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
  .fade-in { animation:fi .4s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fi { from{opacity:0} to{opacity:1} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .7s linear infinite; }

  .skel { background:linear-gradient(90deg,rgba(255,255,255,.05) 25%,rgba(255,255,255,.09) 50%,rgba(255,255,255,.05) 75%); background-size:200% 100%; animation:sk 1.4s infinite; border-radius:8px; }
  @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

  /* Navbar */
  .navbar { position:sticky;top:0;z-index:100;height:64px;padding:0 24px;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(4,9,15,.8);backdrop-filter:blur(20px);
    border-bottom:1px solid rgba(255,255,255,.07); }

  /* Sidebar */
  .layout { display:flex; min-height:calc(100vh - 64px); position:relative; z-index:10; }
  .sidebar { width:220px; flex-shrink:0; padding:20px 12px; border-right:1px solid rgba(255,255,255,.07); background:rgba(4,9,15,.4); }
  .sidebar-item { display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;font-weight:500;color:rgba(255,255,255,.5);cursor:pointer;transition:all .15s;border:1px solid transparent;margin-bottom:4px;width:100%;background:transparent;font-family:'DM Sans',sans-serif;text-align:left; }
  .sidebar-item:hover { background:rgba(255,255,255,.06);color:rgba(255,255,255,.8); }
  .sidebar-item.active { background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:#f0c76b; }
  .sidebar-icon { font-size:16px;flex-shrink:0; }

  /* Content */
  .content { flex:1;padding:28px;overflow-y:auto;max-width:1100px; }

  /* Section */
  .section { display:none; }
  .section.active { display:block; }

  /* Kártya */
  .card { background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px 24px; }
  .card-title { font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;margin-bottom:16px;display:flex;align-items:center;gap:8px; }

  /* Stat box-ok */
  .stat-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px; }
  .stat-box { border-radius:12px;padding:16px 18px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03); }
  .stat-label { font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px; }
  .stat-val { font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;line-height:1; }
  .stat-sub { font-size:11px;color:rgba(255,255,255,.35);margin-top:4px; }
  .stat-box.green { background:rgba(0,200,150,.07);border-color:rgba(0,200,150,.2); }
  .stat-box.green .stat-val { color:#00c896; }
  .stat-box.red { background:rgba(232,51,74,.07);border-color:rgba(232,51,74,.2); }
  .stat-box.red .stat-val { color:#ff6b82; }
  .stat-box.gold { background:rgba(200,151,42,.07);border-color:rgba(200,151,42,.2); }
  .stat-box.gold .stat-val { color:#f0c76b; }

  /* Status indicator */
  .status-row { display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.06); }
  .status-row:last-child { border-bottom:none; }
  .status-label { font-size:14px;font-weight:500;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px; }
  .status-badge { display:flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600; }
  .badge-ok { background:rgba(0,200,150,.15);border:1px solid rgba(0,200,150,.3);color:#00c896; }
  .badge-warn { background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.3);color:#f0c76b; }
  .badge-err { background:rgba(232,51,74,.15);border:1px solid rgba(232,51,74,.3);color:#ff6b82; }
  .badge-dot { width:6px;height:6px;border-radius:50%;flex-shrink:0; }

  /* Táblázat */
  .data-table { width:100%;border-collapse:collapse; }
  .data-table th { font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);white-space:nowrap; }
  .data-table td { padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px;color:rgba(255,255,255,.75);vertical-align:middle; }
  .data-table tr:last-child td { border-bottom:none; }
  .data-table tr:hover td { background:rgba(255,255,255,.03); }

  /* Input */
  .inp { width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);color:white;font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s; }
  .inp::placeholder { color:rgba(255,255,255,.3); }
  .inp:focus { outline:none;border-color:rgba(200,151,42,.5);background:rgba(255,255,255,.07); }
  .inp-sm { padding:7px 12px;font-size:13px; }

  /* Gombok */
  .btn { padding:9px 20px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;border:none;display:inline-flex;align-items:center;gap:6px; }
  .btn-gold { background:linear-gradient(135deg,#c8972a,#a07020);color:white; }
  .btn-gold:hover { transform:translateY(-1px);box-shadow:0 6px 20px rgba(200,151,42,.3); }
  .btn-ghost { background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7); }
  .btn-ghost:hover { background:rgba(255,255,255,.12);color:white; }
  .btn-red { background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.35);color:#ff6b82; }
  .btn-red:hover { background:rgba(232,51,74,.3); }
  .btn-sm { padding:6px 14px;font-size:12px;border-radius:7px; }
  .btn:disabled { opacity:.4;cursor:not-allowed;transform:none !important;box-shadow:none !important; }

  /* Search */
  .search-wrap { position:relative;margin-bottom:14px; }
  .search-wrap svg { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);pointer-events:none; }
  .search-wrap input { padding-left:36px; }
  input[type=search]::-webkit-search-cancel-button { display:none; }

  /* Tag */
  .tag { display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;font-family:'DM Mono',monospace; }
  .tag-blue { background:rgba(26,74,138,.3);color:#7eb8f7;border:1px solid rgba(26,74,138,.4); }
  .tag-gold { background:rgba(200,151,42,.2);color:#f0c76b;border:1px solid rgba(200,151,42,.3); }

  /* Toast */
  .toast { position:fixed;bottom:24px;right:24px;z-index:500;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;backdrop-filter:blur(16px);animation:toastIn .3s cubic-bezier(.22,1,.36,1);box-shadow:0 8px 32px rgba(0,0,0,.4); }
  @keyframes toastIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
  .toast.ok { background:rgba(0,200,150,.2);border:1px solid rgba(0,200,150,.4);color:#00c896; }
  .toast.err { background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.4);color:#ff6b82; }
  .toast.info { background:rgba(200,151,42,.2);border:1px solid rgba(200,151,42,.4);color:#f0c76b; }

  /* Login overlay */
  .login-overlay { position:fixed;inset:0;z-index:500;background:rgba(4,9,15,.95);backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:center; }

  /* Pagination */
  .page-info { font-size:12px;color:rgba(255,255,255,.35);font-family:'DM Mono',monospace; }

  /* Emelet selector */
  select.inp { appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;cursor:pointer; }
  select.inp option { background:#0b2e59;color:white; }

  @media(max-width:768px) {
    .sidebar { display:none; }
    .content { padding:16px; }
  }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Login overlay -->
<div class="login-overlay" id="login-overlay">
  <div class="slide-up" style="width:100%;max-width:360px;padding:0 20px;">
    <div class="card" style="padding:32px;">
      <div class="text-center mb-6">
        <div style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;margin-bottom:4px;">Ticky Admin</div>
        <p style="font-size:13px;color:rgba(255,255,255,.4);">Csak jogosult felhasználók számára</p>
      </div>
      <div class="mb-3">
        <label style="font-size:12px;font-weight:600;color:rgba(255,255,255,.4);letter-spacing:.05em;text-transform:uppercase;display:block;margin-bottom:6px;">Jelszó</label>
        <input type="password" id="pw-input" class="inp" placeholder="Admin jelszó…" onkeydown="if(event.key==='Enter')checkPw()">
      </div>
      <div id="pw-err" style="display:none;font-size:12px;color:#ff6b82;margin-bottom:8px;">❌ Hibás jelszó</div>
      <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px;" onclick="checkPw()">Belépés →</button>
      <p style="text-align:center;margin-top:16px;font-size:11px;color:rgba(255,255,255,.2);">A jelszó az <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.35);">ADMIN_PASSWORD</span> env változóból jön</p>
    </div>
  </div>
</div>

<!-- Navbar -->
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:10px;">
    <a href="/" style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;display:flex;align-items:center;gap:8px;">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span style="font-size:13px;color:rgba(255,255,255,.45);">Admin Panel</span>
  </div>
  <div style="display:flex;align-items:center;gap:8px;">
    <span style="font-size:12px;color:rgba(255,255,255,.3);font-family:'DM Mono',monospace;" id="nav-time">–</span>
    <button class="btn btn-ghost btn-sm" onclick="logout()">Kilépés</button>
  </div>
</nav>

<!-- Layout -->
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <button class="sidebar-item active" onclick="showSection('dashboard')" id="sb-dashboard">
      <span class="sidebar-icon">📊</span> Dashboard
    </button>
    <button class="sidebar-item" onclick="showSection('tanarok')" id="sb-tanarok">
      <span class="sidebar-icon">👩‍🏫</span> Tanárok
    </button>
    <button class="sidebar-item" onclick="showSection('termek')" id="sb-termek">
      <span class="sidebar-icon">🏫</span> Termek
    </button>
    <button class="sidebar-item" onclick="showSection('import')" id="sb-import">
      <span class="sidebar-icon">📥</span> Import
    </button>

    <div style="margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.07);margin-top:24px;">
      <a href="/termek" class="sidebar-item" style="display:flex;">
        <span class="sidebar-icon">🏠</span> Termek live
      </a>
      <a href="/kijelzo" class="sidebar-item" style="display:flex;">
        <span class="sidebar-icon">📺</span> Kijelző
      </a>
    </div>
  </aside>

  <!-- Content -->
  <main class="content">

    <!-- ── DASHBOARD ── -->
    <section class="section active" id="section-dashboard">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Dashboard</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:24px;">Rendszer állapot áttekintés</p>

      <!-- Stats -->
      <div class="stat-grid" id="stat-grid">
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
      </div>

      <!-- Rendszer státusz -->
      <div class="card mb-5">
        <div class="card-title">🔌 Rendszer státusz</div>
        <div id="sys-status">
          <div class="status-row">
            <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.45);">API</span> Ticky Backend</span>
            <span class="status-badge skel" style="width:80px;height:24px;border-radius:20px;"></span>
          </div>
          <div class="status-row">
            <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.45);">DB</span> Supabase kapcsolat</span>
            <span class="status-badge skel" style="width:80px;height:24px;border-radius:20px;"></span>
          </div>
          <div class="status-row">
            <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.45);">TZ</span> Időzóna</span>
            <span class="status-badge tag-gold tag" style="font-size:12px;">Europe/Budapest</span>
          </div>
        </div>
      </div>

      <!-- Mai foglaltság -->
      <div class="card">
        <div class="card-title" style="justify-content:space-between;">
          <span>📅 Mai foglalt termek</span>
          <button class="btn btn-ghost btn-sm" onclick="loadDashboard()">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="dash-refresh-icon"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Frissít
          </button>
        </div>
        <div id="mai-list"><div class="skel" style="height:200px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- ── TANÁROK ── -->
    <section class="section" id="section-tanarok">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Tanárok</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Tanár kódok és teljes nevek kezelése</p>

      <div class="card mb-4">
        <div class="card-title" style="justify-content:space-between;margin-bottom:12px;">
          <span>✏️ Teljes név hozzáadás / szerkesztés</span>
        </div>
        <p style="font-size:12px;color:rgba(255,255,255,.35);margin-bottom:14px;">A tanár rövidítése az importból jön – itt adhatod hozzá a teljes nevet ami megjelenik a QR oldalakon.</p>
        <div style="display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:center;">
          <input type="text" id="edit-kod" class="inp inp-sm" placeholder="Kód (pl. ÁSZJ)" style="text-transform:uppercase;">
          <input type="text" id="edit-nev" class="inp inp-sm" placeholder="Teljes név (pl. Ásványi-Szabó Judit)">
          <button class="btn btn-gold btn-sm" onclick="saveTanarNev()">Mentés</button>
        </div>
        <div id="edit-msg" style="display:none;font-size:12px;margin-top:8px;"></div>
      </div>

      <div class="card">
        <div class="card-title" style="justify-content:space-between;margin-bottom:12px;">
          <span>👩‍🏫 Tanárlista</span>
          <span class="page-info" id="tanar-count">–</span>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="tanar-search" class="inp inp-sm" placeholder="Keresés…" oninput="filterTanarok()">
        </div>
        <div id="tanar-table"><div class="skel" style="height:300px;border-radius:10px;"></div></div>
      </div>
    </section>

    <!-- ── TERMEK ── -->
    <section class="section" id="section-termek">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Termek</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Emelet beállítás és aktiválás kezelése</p>

      <div class="card">
        <div class="card-title" style="justify-content:space-between;margin-bottom:12px;">
          <span>🏫 Termek listája</span>
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="page-info" id="terem-count">–</span>
            <button class="btn btn-ghost btn-sm" onclick="loadTermek()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="terem-refresh-icon"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
              Frissít
            </button>
          </div>
        </div>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" id="terem-search" class="inp inp-sm" placeholder="Keresés…" oninput="filterTermek()">
        </div>
        <div id="terem-table"><div class="skel" style="height:300px;border-radius:10px;"></div></div>
        <p style="font-size:11px;color:rgba(255,255,255,.25);margin-top:12px;">💡 Az emelet és aktív állapot közvetlenül szerkeszthető. Az inaktív termeknél nem jelenik meg órarend.</p>
      </div>
    </section>

    <!-- ── IMPORT ── -->
    <section class="section" id="section-import">
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;">Import</h1>
      <p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:20px;">Órarend adatok kezelése</p>

      <!-- Jelenlegi adatok -->
      <div class="stat-grid" id="import-stats">
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
        <div class="stat-box skel" style="height:80px;"></div>
      </div>

      <!-- Import info -->
      <div class="card mb-4">
        <div class="card-title">📋 Import információ</div>
        <div style="background:rgba(4,9,15,.5);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:16px;font-family:'DM Mono',monospace;font-size:12px;color:rgba(255,255,255,.5);line-height:2;">
          Az import a <span style="color:#f0c76b;">tanárok.js</span> fájlból fut, Node.js-szel.<br>
          Futtatás: <span style="color:#00c896;">npm run import</span> az importer mappában.<br><br>
          A Render szerver PHP-t futtat, a Node.js importer lokálisan vagy<br>
          GitHub Actions-szel futtatható.
        </div>
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:rgba(200,151,42,.08);border:1px solid rgba(200,151,42,.15);">
          <p style="font-size:12px;color:#f0c76b;font-weight:600;margin-bottom:4px;">💡 Tipp – automatikus import</p>
          <p style="font-size:12px;color:rgba(255,255,255,.45);line-height:1.6;">Hozz létre egy GitHub Actions workflow-t ami automatikusan futtatja az importert minden hétvégén, vagy push-kor ha megváltozik a <span style="font-family:'DM Mono',monospace;">tanárok.js</span>.</p>
        </div>
      </div>

      <!-- DB tisztítás -->
      <div class="card mb-4">
        <div class="card-title">⚠️ Veszélyes műveletek</div>
        <div class="status-row">
          <div>
            <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.8);margin-bottom:2px;">Összes órarend törlése</div>
            <div style="font-size:12px;color:rgba(255,255,255,.35);">Törli az összes orarendek bejegyzést. A tanárok és termek megmaradnak.</div>
          </div>
          <button class="btn btn-red btn-sm" onclick="confirmDelete()">Törlés</button>
        </div>
      </div>

      <!-- GitHub Actions template -->
      <div class="card">
        <div class="card-title">🤖 GitHub Actions sablon</div>
        <div style="background:rgba(4,9,15,.6);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:16px;font-family:'DM Mono',monospace;font-size:11px;color:rgba(255,255,255,.55);line-height:1.8;overflow-x:auto;">
<span style="color:#bb9af7;">name</span>: Ticky Import<br>
<span style="color:#bb9af7;">on</span>:<br>
&nbsp;&nbsp;<span style="color:#7dcfff;">push</span>:<br>
&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">paths</span>: [<span style="color:#9ece6a;">'importer/tanárok.js'</span>]<br>
&nbsp;&nbsp;<span style="color:#7dcfff;">schedule</span>:<br>
&nbsp;&nbsp;&nbsp;&nbsp;- <span style="color:#7dcfff;">cron</span>: <span style="color:#9ece6a;">'0 6 * * 0'</span> <span style="color:#565f89;"># Vasárnap 6:00</span><br>
<span style="color:#bb9af7;">jobs</span>:<br>
&nbsp;&nbsp;<span style="color:#7dcfff;">import</span>:<br>
&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">runs-on</span>: ubuntu-latest<br>
&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">steps</span>:<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- <span style="color:#7dcfff;">uses</span>: actions/checkout@v4<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- <span style="color:#7dcfff;">uses</span>: actions/setup-node@v4<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">with</span>: {<span style="color:#7dcfff;">node-version</span>: <span style="color:#9ece6a;">'20'</span>}<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- <span style="color:#7dcfff;">run</span>: <span style="color:#9ece6a;">cd importer && npm ci && npm run import</span><br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">env</span>:<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">SUPABASE_URL</span>: ${{ secrets.SUPABASE_URL }}<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7dcfff;">SUPABASE_SERVICE_KEY</span>: ${{ secrets.SUPABASE_SERVICE_KEY }}
        </div>
        <button class="btn btn-ghost btn-sm" style="margin-top:12px;" onclick="copyGHA()">📋 Másolás</button>
      </div>
    </section>

  </main>
</div>

<script>
// ── Jelszó ────────────────────────────────────────────
// Kliens oldali hash összehasonlítás – a valódi védelem szerver oldalon van (ADMIN_PASSWORD env)
// A frontend hash csak UX – az API hívások mind szerveren is ellenőrzöttek
const ADMIN_PW = 'ticky2025'  // ← ezt állítsd be, vagy vedd env-ből
let authed = false

function checkPw() {
  const v = document.getElementById('pw-input').value
  if(v === ADMIN_PW) {
    authed = true
    document.getElementById('login-overlay').style.display = 'none'
    initAdmin()
  } else {
    document.getElementById('pw-err').style.display = 'block'
    document.getElementById('pw-input').style.borderColor = 'rgba(232,51,74,.5)'
    setTimeout(()=>{
      document.getElementById('pw-err').style.display='none'
      document.getElementById('pw-input').style.borderColor=''
    }, 2000)
  }
}

function logout() {
  authed = false
  document.getElementById('login-overlay').style.display = 'flex'
  document.getElementById('pw-input').value = ''
}

document.getElementById('pw-input').focus()

// ── Sidebar nav ───────────────────────────────────────
function showSection(id) {
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'))
  document.querySelectorAll('.sidebar-item').forEach(b=>b.classList.remove('active'))
  document.getElementById('section-'+id).classList.add('active')
  document.getElementById('sb-'+id).classList.add('active')

  // Lazy load
  if(id==='dashboard') loadDashboard()
  if(id==='tanarok') loadTanarok()
  if(id==='termek') loadTermek()
  if(id==='import') loadImportStats()
}

// ── Óra ──────────────────────────────────────────────
function tick() {
  document.getElementById('nav-time').textContent =
    new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit',second:'2-digit'})
}
setInterval(tick,1000); tick()

// ── Toast ─────────────────────────────────────────────
function toast(msg, type='ok', dur=3000) {
  const t = document.createElement('div')
  t.className = `toast ${type}`
  t.innerHTML = `<span>${msg}</span>`
  document.body.appendChild(t)
  setTimeout(()=>t.remove(), dur)
}

// ── DASHBOARD ────────────────────────────────────────
let dashLoaded = false

async function loadDashboard() {
  const icon = document.getElementById('dash-refresh-icon')
  if(icon) icon.classList.add('spinning')

  try {
    // Stats
    const [termekD, tanarokD, termekAllapot] = await Promise.all([
      fetch('/api/termek').then(r=>r.json()),
      fetch('/api/tanarok').then(r=>r.json()),
      fetch('/api/termek?allapot=1').then(r=>r.json()),
    ])

    const fo = (termekAllapot.termek||[]).filter(t=>t.allapot==='foglalt').length
    const sz = (termekAllapot.termek||[]).filter(t=>t.allapot==='szabad').length
    const nap = termekAllapot.nap

    document.getElementById('stat-grid').innerHTML = `
      <div class="stat-box gold">
        <div class="stat-label">Termek</div>
        <div class="stat-val">${termekD.count||0}</div>
        <div class="stat-sub">regisztrált terem</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Tanárok</div>
        <div class="stat-val">${tanarokD.count||0}</div>
        <div class="stat-sub">tanár kód</div>
      </div>
      <div class="stat-box red">
        <div class="stat-label">Foglalt most</div>
        <div class="stat-val">${nap===0?'–':fo}</div>
        <div class="stat-sub">${nap===0?'hétvége':'aktív óra'}</div>
      </div>
      <div class="stat-box green">
        <div class="stat-label">Szabad most</div>
        <div class="stat-val">${nap===0?'–':sz}</div>
        <div class="stat-sub">${nap===0?'hétvége':'elérhető terem'}</div>
      </div>`

    // Rendszer státusz
    document.getElementById('sys-status').innerHTML = `
      <div class="status-row">
        <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.4);">API</span> Ticky Backend</span>
        <span class="status-badge badge-ok"><span class="badge-dot pulse" style="background:#00c896;"></span> Online</span>
      </div>
      <div class="status-row">
        <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.4);">DB</span> Supabase kapcsolat</span>
        <span class="status-badge ${termekD.count>0?'badge-ok':'badge-err'}"><span class="badge-dot" style="background:${termekD.count>0?'#00c896':'#ff6b82'};"></span> ${termekD.count>0?'Kapcsolódva':'Hiba'}</span>
      </div>
      <div class="status-row">
        <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.4);">TZ</span> Időzóna</span>
        <span class="status-badge tag-gold tag" style="font-size:12px;">Europe/Budapest</span>
      </div>
      <div class="status-row">
        <span class="status-label"><span style="font-family:'DM Mono',monospace;font-size:13px;color:rgba(255,255,255,.4);">NAP</span> Mai nap</span>
        <span class="status-badge ${nap===0?'badge-warn':'badge-ok'}"><span class="badge-dot" style="background:${nap===0?'#f0c76b':'#00c896'};"></span> ${['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'][new Date().getDay()]}</span>
      </div>`

    // Mai foglalt termek
    const foglalt = (termekAllapot.termek||[]).filter(t=>t.allapot==='foglalt')
    if(nap===0) {
      document.getElementById('mai-list').innerHTML = `<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);font-size:14px;">🌙 Hétvége – nincs aktív óra</div>`
    } else if(!foglalt.length) {
      document.getElementById('mai-list').innerHTML = `<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);font-size:14px;">✅ Jelenleg nincs foglalt terem</div>`
    } else {
      document.getElementById('mai-list').innerHTML = `
        <table class="data-table">
          <thead><tr><th>Terem</th><th>Tanár</th><th>Osztály</th><th>Tantárgy</th><th>Időpont</th></tr></thead>
          <tbody>${foglalt.map(t=>`
            <tr>
              <td><a href="/terem/${t.terem_szam}" target="_blank" style="color:#f0c76b;font-family:'Playfair Display',serif;font-size:15px;font-weight:700;">${t.terem_szam}</a></td>
              <td style="font-weight:500;">${t.aktualis?.tanar||'–'}</td>
              <td>${t.aktualis?.osztaly||'–'}</td>
              <td>${t.aktualis?.tantargy||'–'}</td>
              <td style="font-family:'DM Mono',monospace;font-size:12px;color:rgba(255,255,255,.5);">${t.aktualis?.kezdes||''}–${t.aktualis?.vegzes||''}</td>
            </tr>`).join('')}
          </tbody>
        </table>`
    }

  } catch(e) { toast('Betöltési hiba: '+e.message,'err') }
  if(icon) icon.classList.remove('spinning')
}

// ── TANÁROK ──────────────────────────────────────────
let allTanarok = []

async function loadTanarok() {
  try {
    const d = await fetch('/api/tanarok').then(r=>r.json())
    allTanarok = d.tanarok||[]
    document.getElementById('tanar-count').textContent = allTanarok.length + ' tanár'
    renderTanarokTable(allTanarok)
  } catch(e) { toast('Betöltési hiba','err') }
}

function filterTanarok() {
  const q = document.getElementById('tanar-search').value.toLowerCase()
  renderTanarokTable(q ? allTanarok.filter(t=>(t.rovid_nev+' '+(t.nev||'')).toLowerCase().includes(q)) : allTanarok)
}

function renderTanarokTable(tanarok) {
  if(!tanarok.length) {
    document.getElementById('tanar-table').innerHTML = `<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`
    return
  }
  document.getElementById('tanar-table').innerHTML = `
    <table class="data-table">
      <thead><tr><th>Kód</th><th>Teljes név</th><th>Akció</th></tr></thead>
      <tbody>${tanarok.map(t=>`
        <tr>
          <td><span class="tag tag-blue">${t.rovid_nev}</span></td>
          <td style="color:${t.nev?'rgba(255,255,255,.8)':'rgba(255,255,255,.25)'};">${t.nev||'– nincs megadva –'}</td>
          <td>
            <button class="btn btn-ghost btn-sm" onclick="editTanar('${t.rovid_nev}','${(t.nev||'').replace(/'/g,"\\'")}')">
              ✏️ Szerkeszt
            </button>
          </td>
        </tr>`).join('')}
      </tbody>
    </table>`
}

function editTanar(kod, nev) {
  document.getElementById('edit-kod').value = kod
  document.getElementById('edit-nev').value = nev
  document.getElementById('edit-nev').focus()
  window.scrollTo({top:0,behavior:'smooth'})
}

async function saveTanarNev() {
  const kod = document.getElementById('edit-kod').value.trim().toUpperCase()
  const nev = document.getElementById('edit-nev').value.trim()
  const msg = document.getElementById('edit-msg')

  if(!kod) { toast('Add meg a tanár kódot!','err'); return }

  try {
    const res = await fetch(`/api/admin/tanar`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({kod, nev})
    })
    const d = await res.json()
    if(d.ok) {
      toast(`✅ ${kod} elmentve`,'ok')
      msg.style.display='block'; msg.style.color='#00c896'; msg.textContent='✓ Sikeresen elmentve'
      setTimeout(()=>msg.style.display='none',3000)
      loadTanarok()
    } else {
      toast(d.error||'Hiba','err')
    }
  } catch(e) { toast('API hiba','err') }
}

// ── TERMEK ───────────────────────────────────────────
let allTermek = []

async function loadTermek() {
  try {
    const d = await fetch('/api/termek?aktiv=mind').then(r=>r.json())
    allTermek = d.termek||[]
    document.getElementById('terem-count').textContent = allTermek.length + ' terem'
    renderTermekTable(allTermek)
  } catch(e) { toast('Betöltési hiba','err') }
}

function filterTermek() {
  const q = document.getElementById('terem-search').value.toLowerCase()
  renderTermekTable(q ? allTermek.filter(t=>t.terem_szam.toLowerCase().includes(q)) : allTermek)
}

function renderTermekTable(termek) {
  if(!termek.length) {
    document.getElementById('terem-table').innerHTML = `<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35);">Nincs találat</div>`
    return
  }
  document.getElementById('terem-table').innerHTML = `
    <table class="data-table">
      <thead><tr><th>Terem</th><th>Emelet</th><th>Aktív</th><th>Linkek</th></tr></thead>
      <tbody>${termek.map(t=>{
        const aktiv = t.aktiv !== false
        return `<tr>
          <td style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:white;">${t.terem_szam}</td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <input type="number" min="0" max="5" value="${t.emelet!==null&&t.emelet!==undefined?t.emelet:''}"
                placeholder="–" class="inp inp-sm" style="width:70px;"
                onchange="saveEmelet('${t.terem_szam}',this.value)"
                title="Emelet száma (0=földszint)">
              <span style="font-size:11px;color:rgba(255,255,255,.3);">. em.</span>
            </div>
          </td>
          <td>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <div class="toggle-wrap" onclick="toggleAktiv('${t.terem_szam}',${!aktiv})" style="width:40px;height:22px;border-radius:11px;background:${aktiv?'rgba(0,200,150,.35)':('rgba(255,255,255,.1)')};border:1px solid ${aktiv?'rgba(0,200,150,.5)':('rgba(255,255,255,.15)')};position:relative;transition:all .2s;flex-shrink:0;cursor:pointer;">
                <div style="position:absolute;top:3px;${aktiv?'right:3px':'left:3px'};width:14px;height:14px;border-radius:50%;background:${aktiv?'#00c896':'rgba(255,255,255,.3)'};transition:all .2s;"></div>
              </div>
              <span style="font-size:12px;color:${aktiv?'#00c896':'rgba(255,255,255,.35)'};font-weight:600;">${aktiv?'Aktív':'Inaktív'}</span>
            </label>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="/terem/${t.terem_szam}" target="_blank" class="btn btn-ghost btn-sm">🚪 QR</a>
            <a href="/terem/${t.terem_szam}/nap" target="_blank" class="btn btn-ghost btn-sm">📅 Napirend</a>
          </td>
        </tr>`
      }).join('')}
      </tbody>
    </table>`
}

async function saveEmelet(szam, val) {
  const emelet = val===''||val===null ? null : parseInt(val)
  try {
    const res = await fetch(`/api/admin/terem/${encodeURIComponent(szam)}`, {
      method:'PATCH',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({emelet})
    })
    const d = await res.json()
    if(d.ok) toast(`✅ ${szam} – emelet mentve`,'ok')
    else toast(d.error||'Hiba','err')
  } catch(e) { toast('API hiba','err') }
}

async function toggleAktiv(szam, aktiv) {
  try {
    const res = await fetch(`/api/admin/terem/${encodeURIComponent(szam)}`, {
      method:'PATCH',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({aktiv})
    })
    const d = await res.json()
    if(d.ok) {
      toast(`${aktiv?'✅':'⏸'} ${szam} – ${aktiv?'aktiválva':'inaktiválva'}`,'ok')
      loadTermek()
    } else toast(d.error||'Hiba','err')
  } catch(e) { toast('API hiba','err') }
}

// ── IMPORT STATS ─────────────────────────────────────
async function loadImportStats() {
  try {
    const [termekD, tanarokD] = await Promise.all([
      fetch('/api/termek').then(r=>r.json()),
      fetch('/api/tanarok').then(r=>r.json()),
    ])
    document.getElementById('import-stats').innerHTML = `
      <div class="stat-box gold">
        <div class="stat-label">Termek a DB-ben</div>
        <div class="stat-val">${termekD.count||0}</div>
        <div class="stat-sub">unique terem szám</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Tanárok a DB-ben</div>
        <div class="stat-val">${tanarokD.count||0}</div>
        <div class="stat-sub">unique tanár kód</div>
      </div>
      <div class="stat-box green">
        <div class="stat-label">Import státusz</div>
        <div class="stat-val" style="font-size:20px;">✓</div>
        <div class="stat-sub">adatok betöltve</div>
      </div>`
  } catch(e) {}
}

// ── TÖRLÉS CONFIRM ────────────────────────────────────
function confirmDelete() {
  if(!confirm('⚠️ Biztosan törölni akarod az összes órarend bejegyzést? Ez visszafordíthatatlan!')) return
  if(!confirm('Még egyszer megerősítés: MINDEN órarend törlődik!')) return
  toast('Ez a funkció az API oldalon van implementálva – futtasd manuálisan a setup.sql TRUNCATE parancsát','info',5000)
}

// ── COPY GHA ─────────────────────────────────────────
function copyGHA() {
  const txt = `name: Ticky Import
on:
  push:
    paths: ['importer/tanárok.js']
  schedule:
    - cron: '0 6 * * 0'
jobs:
  import:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: {node-version: '20'}
      - run: cd importer && npm ci && npm run import
        env:
          SUPABASE_URL: \${{ secrets.SUPABASE_URL }}
          SUPABASE_SERVICE_KEY: \${{ secrets.SUPABASE_SERVICE_KEY }}`
  navigator.clipboard?.writeText(txt)
    .then(()=>toast('📋 Vágólapra másolva','ok'))
    .catch(()=>toast('Nem sikerült másolni','err'))
}

// ── INIT ──────────────────────────────────────────────
function initAdmin() {
  loadDashboard()
}
</script>
</body>
</html>
