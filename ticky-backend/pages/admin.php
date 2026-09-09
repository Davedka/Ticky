<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers(true);

$login_error = false;
$login_error_message = '';
$secret_login_wait = 0;
$csrf_token = ticky_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    if (!ticky_has_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $login_error = true;
        $login_error_message = 'Ervenytelen CSRF token.';
    } else {
        $secret_login_wait = ticky_rate_limit_wait_seconds('secret_admin', 300, 8, 900);
        if ($secret_login_wait > 0) {
            $login_error = true;
            $login_error_message = 'Tul sok sikertelen secret admin probalkozas. Varj ' . $secret_login_wait . ' masodpercet.';
        } else {
            $input = trim((string) $_POST['pw']);
            if (admin_is_configured() && hash_equals(admin_password(), $input)) {
                ticky_clear_rate_limit('secret_admin');
                admin_set_auth_cookie();
                header('Location: /admin');
                exit;
            }

            ticky_record_rate_limit_failure('secret_admin', 300, 8, 900);
            $secret_login_wait = ticky_rate_limit_wait_seconds('secret_admin', 300, 8, 900);
            $login_error = true;
            $login_error_message = $secret_login_wait > 0
                ? 'Tul sok sikertelen secret admin probalkozas. Varj ' . $secret_login_wait . ' masodpercet.'
                : 'Hibas admin jelszo.';
        }
    }
}

$authed = ticky_is_admin_authed();
$no_pw_set = !admin_is_configured();
$current_user = ticky_current_user();
$display_name = $current_user['nev'] ?? $current_user['felhasznalonev'] ?? 'Secret admin';
$auth_mode = is_array($current_user) ? 'felhasznalo' : 'secret';
$admin_path = '/admin';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky - Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
body{font-family:'DM Sans',sans-serif;background:#04090f;color:#fff;min-height:100vh;background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 90% 100%,rgba(200,151,42,.10) 0%,transparent 50%)}body:before{content:'';position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);background-size:44px 44px}.top{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);box-shadow:0 0 16px rgba(200,151,42,.3);z-index:50}.glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08)}.btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:9px 14px;font-size:13px;font-weight:600}.btn-gold{background:linear-gradient(135deg,#c8972a,#9e6d1e);color:#fff}.btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.78)}.inp{width:100%;border-radius:10px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);padding:10px 12px;color:#fff}.inp:focus{outline:none;border-color:rgba(200,151,42,.45)}.chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid rgba(255,255,255,.12)}.chip.gold{color:#f0c76b;background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.24)}.chip.green{color:#86efac;background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.24)}.chip.red{color:#fda4af;background:rgba(244,63,94,.12);border-color:rgba(244,63,94,.24)}.chip.blue{color:#93c5fd;background:rgba(96,165,250,.12);border-color:rgba(96,165,250,.24)}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px}.table th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35)}.navbtn{width:100%;text-align:left;padding:10px 12px;border-radius:10px;font-size:14px;color:rgba(255,255,255,.65);border:1px solid transparent}.navbtn.active{background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.24);color:#f0c76b}.toast{position:fixed;right:20px;bottom:20px;padding:12px 16px;border-radius:12px;z-index:200;border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(16px)}.toast.ok{background:rgba(34,197,94,.18);color:#86efac}.toast.err{background:rgba(244,63,94,.18);color:#fda4af}.toast.info{background:rgba(200,151,42,.18);color:#f0c76b}.section{display:none}.section.active{display:block}.mono{font-family:'DM Mono',monospace}.stat{padding:18px;border-radius:14px}.small{font-size:12px;color:rgba(255,255,255,.5)}@media(max-width:900px){.layout{display:block}.sidebar{width:auto;margin-bottom:16px}.content{padding:16px}}@media(min-width:901px){.layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:20px}.content{padding:24px 24px 40px 0}.sidebar{padding:24px 0 24px 24px}}
</style>
<style>
/* ── a11y + szünet/feature kiegészítések ── */
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,[tabindex]:focus-visible{outline:2px solid rgba(200,151,42,.6);outline-offset:2px}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}}
.break-banner{display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:14px;font-size:13px;font-weight:500}
.break-banner.aktiv{background:rgba(200,151,42,.14);border:1px solid rgba(200,151,42,.35);color:#f0c76b}
.break-banner.jovo{background:rgba(96,165,250,.10);border:1px solid rgba(96,165,250,.25);color:#93c5fd}
.break-banner .bb-ico{font-size:18px;line-height:1;flex-shrink:0}
.break-banner .bb-strong{font-family:'Playfair Display',serif;font-weight:700}
.table tr.row-aktiv td{background:rgba(200,151,42,.07)}
.mini-input{width:100%;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);padding:7px 9px;color:#fff;font-size:13px}
.mini-input:focus{outline:none;border-color:rgba(200,151,42,.45)}
.days-pill{font-size:10px;color:rgba(255,255,255,.42);font-family:'DM Mono',monospace}
.tool-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
</style>
</head>
<body>
<div class="top"></div>

<?php if (!$authed): ?>
<main class="min-h-screen flex items-center justify-center p-4 relative z-10">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <a href="/" class="inline-flex items-center gap-3 text-white text-3xl font-bold" style="font-family:'Playfair Display',serif;">
        <span class="inline-block w-3 h-3 rounded-full" style="background:#c8972a;box-shadow:0 0 10px #c8972a;"></span>
        Ticky
      </a>
      <p class="small mt-3">Admin dashboard</p>
      <p class="small mt-1 mono">/admin</p>
    </div>

    <div class="glass p-8 rounded-2xl">
      <?php if ($no_pw_set): ?>
        <p class="text-lg font-semibold text-amber-300">Nincs ADMIN_PASSWORD beallitva.</p>
        <p class="small mt-3">Hasznald a <a href="/login" class="text-amber-300">/login</a> oldalt admin szerepu felhasznaloval.</p>
      <?php else: ?>
        <form method="POST" action="/admin" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
          <div>
            <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Secret admin jelszo (legacy)</label>
            <input type="password" name="pw" class="inp" placeholder="ADMIN_PASSWORD" autofocus>
          </div>
          <?php if ($login_error): ?>
            <p class="text-sm text-rose-300"><?= htmlspecialchars($login_error_message, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php if ($secret_login_wait > 0): ?>
            <p class="small">A secret admin login ideiglenesen rate limitelve van. Ujraprobalas: <?= (int) $secret_login_wait ?> mp.</p>
          <?php endif; ?>
          <button type="submit" class="btn btn-gold w-full justify-center">Belepes</button>
        </form>
        <p class="small mt-4">Ajanlott: <a href="/login" class="text-amber-300">/login</a> admin szerepu felhasznaloval.</p>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php else: ?>
<div class="relative z-10">
  <header class="glass sticky top-0 z-40 flex items-center justify-between px-5 h-16 border-x-0 border-t-0">
    <div class="flex items-center gap-3 min-w-0">
      <a href="/" class="text-white text-lg font-bold inline-flex items-center gap-2" style="font-family:'Playfair Display',serif;">
        <span class="inline-block w-2 h-2 rounded-full" style="background:#c8972a;box-shadow:0 0 8px #c8972a;"></span>
        Ticky
      </a>
      <span class="text-white/20">·</span>
      <span class="text-sm text-white/50">Admin</span>
      <span class="chip gold mono">/admin</span>
    </div>
    <div class="flex items-center gap-3">
      <div class="text-right">
        <div class="text-sm"><?= htmlspecialchars((string) $display_name, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="small"><?= $auth_mode === 'felhasznalo' ? 'admin felhasznalo' : 'secret admin' ?></div>
      </div>
      <div id="nav-time" class="small mono hidden sm:block"></div>
      <button type="button" class="btn btn-ghost" id="logout-btn">Kilepes</button>
    </div>
  </header>

  <!-- Aktív / közelgő szünet sáv (globális) -->
  <div style="padding:14px 20px 0;">
    <div id="break-banner" class="break-banner" style="display:none;"></div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <div class="glass rounded-2xl p-3">
        <button class="navbtn active" data-section="dashboard">Dashboard</button>
        <button class="navbtn" data-section="orarend">Órarend</button>
        <button class="navbtn" data-section="felhasznalok">Felhasznalok</button>
        <button class="navbtn" data-section="szunetek">Szunetek</button>
        <button class="navbtn" data-section="tanarok">Tanarok</button>
        <button class="navbtn" data-section="termek">Termek</button>
        <button class="navbtn" data-section="diagnosztika">Diagnosztika</button>
        <div class="mt-4 pt-4 border-t border-white/10 space-y-2">
          <a href="/termek" class="navbtn block">Termek live</a>
          <a href="/kijelzo" class="navbtn block">Kijelzo</a>
          <a href="/tester" class="navbtn block">🧪 Tester felulet</a>
          <a href="/support" class="navbtn block">Support</a>
        </div>
      </div>
    </aside>

    <main class="content">
      <section id="section-dashboard" class="section active">
        <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Dashboard</h1>
        <p class="small mt-2 mb-6">Az admin most mar ugyanazzal a jogosultsagi reteggel fut secret pathrol es admin felhasznalobol is.</p>
        <div id="dashboard-cards" class="grid md:grid-cols-4 gap-3 mb-6"></div>
        <div class="glass rounded-2xl p-5">
          <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-xl font-bold" style="font-family:'Playfair Display',serif;">Aktiv allapot</h2>
            <button class="btn btn-ghost" id="reload-dashboard">Frissites</button>
          </div>
          <div id="dashboard-status" class="small">Betoltes...</div>
        </div>
      </section>

      <section id="section-felhasznalok" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Felhasznalok</h1>
            <p class="small mt-2">Admin vagy tester account letrehozasa, szerepkor es aktiv allapot kezelese.</p>
          </div>
          <div class="tool-row">
            <button class="btn btn-ghost" id="export-users">CSV export</button>
            <button class="btn btn-ghost" id="reload-users">Frissites</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-5 mb-5 grid md:grid-cols-4 gap-3">
          <input id="new-username" class="inp" placeholder="Felhasznalonev">
          <input id="new-name" class="inp" placeholder="Nev">
          <input id="new-password" class="inp" placeholder="Jelszo (min. 10)" type="password">
          <select id="new-role" class="inp"><option value="tester">tester</option><option value="admin">admin</option></select>
          <div class="md:col-span-4">
            <button class="btn btn-gold" id="create-user">Felhasznalo letrehozasa</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-4 mb-5">
          <input id="user-search" class="inp" placeholder="Kereses: felhasznalonev vagy nev">
        </div>
        <div class="glass rounded-2xl p-3 overflow-auto">
          <table class="table" id="users-table"></table>
        </div>
      </section>

      <section id="section-szunetek" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Szunetek</h1>
            <p class="small mt-2">Uj idoszakok letrehozasa, szerkesztese es torlese. Az aktiv szunet kiemelve.</p>
          </div>
          <button class="btn btn-ghost" id="reload-breaks">Frissites</button>
        </div>
        <div class="glass rounded-2xl p-5 mb-5 grid md:grid-cols-3 gap-3">
          <input id="break-name" class="inp" placeholder="Nev">
          <input id="break-start" class="inp" type="date">
          <input id="break-end" class="inp" type="date">
          <div class="md:col-span-3">
            <button class="btn btn-gold" id="create-break">Szunet letrehozasa</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-3 overflow-auto">
          <table class="table" id="breaks-table"></table>
        </div>
      </section>

      <section id="section-tanarok" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Tanarok</h1>
            <p class="small mt-2">A secret pathon vagy admin felhasznaloval belepve ugyanaz a tanar-modositas jogosultsag ervenyes.</p>
          </div>
          <div class="tool-row">
            <button class="btn btn-ghost" id="export-teachers">CSV export</button>
            <button class="btn btn-ghost" id="reload-teachers">Frissites</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-5 mb-5 grid md:grid-cols-[160px_1fr_auto] gap-3 items-center">
          <input id="teacher-code" class="inp mono" placeholder="Kod">
          <input id="teacher-name" class="inp" placeholder="Teljes nev">
          <button class="btn btn-gold" id="save-teacher">Mentes</button>
        </div>
        <div class="glass rounded-2xl p-5 mb-5">
          <input id="teacher-search" class="inp" placeholder="Kereses kod vagy nev alapjan">
        </div>
        <div class="glass rounded-2xl p-3 overflow-auto">
          <table class="table" id="teachers-table"></table>
        </div>
      </section>

      <section id="section-termek" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Termek</h1>
            <p class="small mt-2">Emelet modositasa mar megy mindket admin auth moddal.</p>
          </div>
          <div class="flex gap-2">
            <button class="btn btn-ghost" id="export-rooms">CSV export</button>
            <button class="btn btn-ghost" id="autofill-rooms">Auto emelet</button>
            <button class="btn btn-ghost" id="reload-rooms">Frissites</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-5 mb-5">
          <input id="room-search" class="inp" placeholder="Terem keresese">
        </div>
        <div class="glass rounded-2xl p-3 overflow-auto">
          <table class="table" id="rooms-table"></table>
        </div>
      </section>

      <section id="section-orarend" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Órarend</h1>
            <p class="small mt-2">Excel feltöltés → validáció → draft → előnézet → publikálás. A feltöltés soha nem írja felül azonnal az éles órarendet.</p>
          </div>
          <button class="btn btn-ghost" id="reload-orarend">Frissítés</button>
        </div>

        <div id="orarend-schema" class="mb-5"></div>

        <div class="glass rounded-2xl p-5 mb-5">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Aktív verzió</h3>
          <div id="orarend-active" class="small">Betöltés...</div>
        </div>

        <div class="glass rounded-2xl p-5 mb-5">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Forrás</h3>
          <p class="small mb-4">Támogatott formátumok: <strong>Ticky Import Format v1</strong> (fejléces tábla), illetve az iskolai csoportonkénti órarend export.</p>
          <div class="grid md:grid-cols-2 gap-3 mb-4">
            <label class="small block">Verzió neve (opcionális)
              <input id="orarend-nev" class="inp mt-1" placeholder="pl. 2026/2027 szeptember">
            </label>
            <label class="small block">Tanév (opcionális)
              <input id="orarend-tanev" class="inp mt-1" placeholder="pl. 2026/2027">
            </label>
          </div>
          <input id="orarend-fajl" type="file" accept=".xlsx,.xlsm" class="inp mb-4">
          <div class="flex items-center gap-3 flex-wrap">
            <button class="btn btn-gold" id="orarend-upload">Excel feltöltése és ellenőrzése</button>
            <button class="btn btn-ghost" id="orarend-template">Sablon letöltése</button>
            <span id="orarend-status" class="small"></span>
          </div>
        </div>

        <div id="orarend-report"></div>

        <div class="glass rounded-2xl p-3 overflow-auto">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3 px-2 pt-2" style="color:rgba(255,255,255,.4)">Verziók</h3>
          <table class="table" id="orarend-versions"></table>
        </div>
      </section>

      <section id="section-diagnosztika" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Diagnosztika</h1>
            <p class="small mt-2">Forras (tanarok.js) es adatbazis osszehasonlitasa, import inditas.</p>
          </div>
          <button class="btn btn-ghost" id="reload-diag">Frissites</button>
        </div>

        <div id="diag-health" class="glass rounded-2xl p-5 mb-5"></div>

        <div class="grid md:grid-cols-2 gap-4 mb-5">
          <div class="glass rounded-2xl p-5">
            <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Forras (tanarok.js)</h3>
            <div id="diag-source" class="space-y-1 small">Betoltes...</div>
          </div>
          <div class="glass rounded-2xl p-5">
            <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Adatbazis</h3>
            <div id="diag-db" class="space-y-1 small">Betoltes...</div>
          </div>
        </div>

        <div class="glass rounded-2xl p-5 mb-5">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Osszehasonlitas</h3>
          <div id="diag-comparison" class="small">Betoltes...</div>
        </div>

        <div class="glass rounded-2xl p-5 mb-5">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">GitHub szinkronizálás</h3>
          <p class="small mb-3">A friss <code>tanárok.js</code> letöltése GitHub-ról és a lokális fájl felülírása. Utána futtasd a Teljes importot a változások adatbázisba mentéséhez.</p>
          <div id="github-info" class="small mb-3" style="color:rgba(255,255,255,.55)">Betöltés...</div>
          <div class="flex items-center gap-3 flex-wrap">
            <button class="btn btn-ghost" id="run-github-sync">Friss tanárok.js letöltése GitHub-ról</button>
            <span id="github-sync-status" class="small"></span>
          </div>
          <div id="github-sync-result" class="mt-3"></div>
        </div>

        <div class="glass rounded-2xl p-5">
          <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Import</h3>
          <p class="small mb-4">Draft órarend verzió készítése a tanárok.js forrásból. Semmi nem törlődik és nem lép azonnal életbe – a draftot az <strong>Órarend</strong> szekcióban tudod ellenőrizni és publikálni.</p>
          <div class="flex items-center gap-3">
            <button class="btn btn-gold" id="run-import">Draft készítése</button>
            <span id="import-status" class="small"></span>
          </div>
          <div id="import-result" class="mt-4"></div>
        </div>
      </section>

    </main>
  </div>
</div>

<script>
const state={section:'dashboard',users:[],breaks:[],teachers:[],rooms:[],versions:[],draft:null};
const currentUserId=<?= json_encode($current_user['id'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const csrfToken=<?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function toast(msg,type='ok'){const n=document.createElement('div');n.className='toast '+type;n.textContent=msg;document.body.appendChild(n);setTimeout(()=>n.remove(),3200)}
function q(id){return document.getElementById(id)}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function attrLiteral(v){return esc(JSON.stringify(v??''))}
function nowTick(){q('nav-time').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit',second:'2-digit'})}
nowTick();setInterval(nowTick,1000)

// ── Szünet segédfüggvények (kliensoldali állapot) ──────────────
function admToday(){const n=new Date();return n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0')+'-'+String(n.getDate()).padStart(2,'0')}
function admDayDiff(a,b){return Math.round((Date.parse(a+'T00:00:00')-Date.parse(b+'T00:00:00'))/86400000)}
function breakStatus(b){
  const t=admToday(),k=String(b.kezdet||''),v=String(b.vege||'');
  if(k&&v&&k<=t&&t<=v) return {key:'aktiv',label:'Aktiv',chip:'gold',rem:admDayDiff(v,t)+1};
  if(k&&t<k)           return {key:'jovo', label:'Kozelgo',chip:'blue',rem:admDayDiff(k,t)};
  return {key:'mult',label:'Lejart',chip:'gray',rem:admDayDiff(t,v)};
}
function sortBreaks(list){
  const order={aktiv:0,jovo:1,mult:2};
  return [...list].sort((a,b)=>{
    const sa=breakStatus(a),sb=breakStatus(b);
    if(order[sa.key]!==order[sb.key]) return order[sa.key]-order[sb.key];
    if(sa.key==='mult') return String(b.vege||'').localeCompare(String(a.vege||''));
    return String(a.kezdet||'').localeCompare(String(b.kezdet||''));
  });
}
function renderBreakBanner(list){
  const el=q('break-banner'); if(!el) return;
  const active=list.find(b=>breakStatus(b).key==='aktiv');
  if(active){
    const s=breakStatus(active);
    el.className='break-banner aktiv';
    el.innerHTML=`<span class="bb-ico">🌙</span><div><span class="bb-strong">${esc(active.nev)}</span> – jelenleg szunet van · meg <strong>${s.rem}</strong> nap (${esc(active.vege)}-ig)</div>`;
    el.style.display='flex'; return;
  }
  const up=sortBreaks(list.filter(b=>breakStatus(b).key==='jovo'))[0];
  if(up){
    const s=breakStatus(up);
    el.className='break-banner jovo';
    el.innerHTML=`<span class="bb-ico">📅</span><div>Kovetkezo szunet: <span class="bb-strong">${esc(up.nev)}</span> · ${s.rem} nap mulva (${esc(up.kezdet)})</div>`;
    el.style.display='flex'; return;
  }
  el.style.display='none';
}
async function refreshBreakBanner(){
  try{const d=await adminFetch('/api/admin/szunetek');state.breaks=d.szunetek||[];renderBreakBanner(state.breaks)}catch(e){}
}

// ── CSV export (kliensoldali, nincs backend hívás) ─────────────
function downloadCsv(name,header,rows){
  const all=[header,...rows];
  const csv=all.map(r=>r.map(c=>'"'+String(c??'').replace(/"/g,'""')+'"').join(',')).join('\r\n');
  const blob=new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;a.click();
  setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}
function exportUsers(){downloadCsv('felhasznalok.csv',['Felhasznalonev','Nev','Szerep','Aktiv','Letrehozva'],state.users.map(u=>[u.felhasznalonev,u.nev||'',u.szerep||'',(u.aktiv??true)?'aktiv':'tiltott',(u.letrehozva||'').replace('T',' ').slice(0,16)]))}
function exportTeachers(){downloadCsv('tanarok.csv',['Kod','Nev'],state.teachers.map(t=>[t.rovid_nev,t.nev||'']))}
function exportRooms(){downloadCsv('termek.csv',['Terem','Emelet'],state.rooms.map(t=>[t.terem_szam,t.emelet??'']))}

async function readJson(res){try{return await res.json()}catch{return {}}}
async function adminFetch(url,options={}){
  const init={credentials:'same-origin',...options};
  const headers=new Headers(init.headers||{});
  headers.set('X-Ticky-Admin','1');
  headers.set('X-CSRF-Token',csrfToken);
  // FormData eseten a bongeszo allitja be a Content-Type-ot a boundary-val.
  const isFormData=(typeof FormData!=='undefined')&&(init.body instanceof FormData);
  if(init.body&&typeof init.body!=='string'&&!isFormData){headers.set('Content-Type','application/json');init.body=JSON.stringify(init.body)}
  init.headers=headers;
  const res=await fetch(url,init);
  const data=await readJson(res);
  if(!res.ok){throw new Error(data.error||data.uzenet||('HTTP '+res.status))}
  return data;
}

// Az import valaszara 4xx eseten is szukseg van (a validacios riport miatt),
// ezert itt nem dobunk, hanem visszaadjuk a torzset a statusszal egyutt.
async function adminFetchReport(url,options={}){
  const init={credentials:'same-origin',...options};
  const headers=new Headers(init.headers||{});
  headers.set('X-Ticky-Admin','1');
  headers.set('X-CSRF-Token',csrfToken);
  init.headers=headers;
  const res=await fetch(url,init);
  return {status:res.status,data:await readJson(res)};
}

async function logout(){
  try{
    await fetch('/api/auth/logout',{
      method:'POST',
      credentials:'same-origin',
      headers:{'X-CSRF-Token':csrfToken}
    });
  }finally{
    window.location.href='/login';
  }
}

function setSection(name){
  state.section=name;
  document.querySelectorAll('.section').forEach(el=>el.classList.toggle('active',el.id==='section-'+name));
  document.querySelectorAll('.navbtn[data-section]').forEach(el=>el.classList.toggle('active',el.dataset.section===name));
  const loaders={dashboard:loadDashboard,orarend:loadOrarendVersions,felhasznalok:loadUsers,szunetek:loadBreaks,tanarok:loadTeachers,termek:loadRooms,diagnosztika:()=>{loadDiagnosztika();loadGithubInfo()}};
  loaders[name]?.();
}
document.querySelectorAll('.navbtn[data-section]').forEach(btn=>btn.addEventListener('click',()=>setSection(btn.dataset.section)));

function statusChip(label,type){return `<span class="chip ${type}">${esc(label)}</span>`}
function detectFloor(room){
  const s=String(room||'').toUpperCase();
  if(s.startsWith('K')||s.startsWith('M')||s.startsWith('T')||s==='KT') return 0;
  const n=parseInt(s,10);
  if(Number.isNaN(n)) return null;
  if(n<100) return 0;
  if(n<200) return 1;
  if(n<300) return 2;
  if(n<400) return 3;
  return null;
}

async function loadDashboard(){
  q('dashboard-cards').innerHTML='<div class="glass stat">Betoltes...</div>';
  q('dashboard-status').textContent='Betoltes...';
  try{
    const [rooms,teachers,live,users,breaks]=await Promise.all([
      fetch('/api/termek').then(r=>r.json()),
      fetch('/api/tanarok').then(r=>r.json()),
      fetch('/api/termek?allapot=1').then(r=>r.json()),
      adminFetch('/api/admin/felhasznalok'),
      adminFetch('/api/admin/szunetek'),
    ]);
    const occupied=(live.termek||[]).filter(x=>x.allapot==='foglalt').length;
    const free=(live.termek||[]).filter(x=>x.allapot==='szabad').length;
    const blist=breaks.szunetek||[]; state.breaks=blist; renderBreakBanner(blist);
    const act=blist.find(b=>breakStatus(b).key==='aktiv');
    const up=sortBreaks(blist.filter(b=>breakStatus(b).key==='jovo'))[0];
    const breakChip=act
      ? `${statusChip('Szunet aktiv','gold')} <span class="small ml-2">${esc(act.nev)} · meg ${breakStatus(act).rem} nap (${esc(act.vege)}-ig)</span>`
      : `${statusChip('Nincs aktiv szunet','green')} <span class="small ml-2">${up?('Kovetkezo: '+esc(up.nev)+' · '+breakStatus(up).rem+' nap mulva'):'nincs kozelgo szunet'}</span>`;
    q('dashboard-cards').innerHTML=`
      <div class="glass stat"><div class="small">Termek</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${rooms.count||0}</div></div>
      <div class="glass stat"><div class="small">Tanarok</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${teachers.count||0}</div></div>
      <div class="glass stat"><div class="small">Felhasznalok</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${users.count||0}</div></div>
      <div class="glass stat"><div class="small">Szunetek</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${breaks.count||0}</div></div>
    `;
    q('dashboard-status').innerHTML=`
      <div class="grid md:grid-cols-2 gap-3">
        <div>${breakChip}</div>
        <div>${statusChip('Admin auth rendben','green')} <span class="small ml-2"><?= $auth_mode === 'felhasznalo' ? 'Belepve admin accounttal.' : 'Belepve secret pathon.' ?></span></div>
        <div>${statusChip('Supabase API elerheto','green')} <span class="small ml-2">Valaszok sikeresek.</span></div>
        <div>${statusChip('Foglalt most: '+occupied,'gold')} <span class="small ml-2">Szabad: ${free}</span></div>
      </div>
    `;
  }catch(error){q('dashboard-status').textContent=error.message;toast(error.message,'err')}
}

function renderUsers(){
  const f=(q('user-search')?.value||'').trim().toLowerCase();
  const list=state.users.filter(u=>((u.felhasznalonev||'')+' '+(u.nev||'')).toLowerCase().includes(f));
  const rows=list.map(user=>`
    <tr>
      <td class="mono">${esc(user.felhasznalonev)}</td>
      <td>${esc(user.nev||'-')}</td>
      <td>${statusChip(user.szerep||'tester',user.szerep==='admin'?'gold':'blue')}</td>
      <td>${statusChip((user.aktiv??true)?'aktiv':'tiltott',(user.aktiv??true)?'green':'red')}</td>
      <td class="mono">${esc((user.letrehozva||'').replace('T',' ').slice(0,16)||'-')}</td>
      <td>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-ghost" onclick="toggleUser('${esc(user.id)}',${!(user.aktiv??true)})">${(user.aktiv??true)?'Tiltas':'Aktivalas'}</button>
          <button class="btn btn-ghost" onclick="setRole('${esc(user.id)}','${user.szerep==='admin'?'tester':'admin'}')">${user.szerep==='admin'?'Testerre':'Adminna'}</button>
          <button class="btn btn-ghost" onclick="renameUser('${esc(user.id)}',${attrLiteral(user.nev||'')})">Nev</button>
          <button class="btn btn-ghost" onclick="resetUserPassword('${esc(user.id)}')">Jelszo</button>
          ${user.id===currentUserId?'':`<button class="btn btn-ghost" onclick="deleteUser('${esc(user.id)}')">Torles</button>`}
        </div>
      </td>
    </tr>`).join('');
  q('users-table').innerHTML=`<thead><tr><th>Felhasznalonev</th><th>Nev</th><th>Szerep</th><th>Allapot</th><th>Letrehozva</th><th>Muveletek</th></tr></thead><tbody>${rows||'<tr><td colspan="6">Nincs talalat.</td></tr>'}</tbody>`;
}
async function loadUsers(){try{const data=await adminFetch('/api/admin/felhasznalok');state.users=data.felhasznalok||[];renderUsers()}catch(error){toast(error.message,'err')}}
async function createUser(){
  const felhasznalonev=q('new-username').value.trim();
  const nev=q('new-name').value.trim();
  const jelszo=q('new-password').value;
  const szerep=q('new-role').value;
  try{
    await adminFetch('/api/admin/felhasznalo',{method:'POST',body:{felhasznalonev,nev,jelszo,szerep}});
    q('new-username').value='';q('new-name').value='';q('new-password').value='';q('new-role').value='tester';
    toast('Felhasznalo letrehozva');
    loadUsers();
  }catch(error){toast(error.message,'err')}
}
async function toggleUser(id,aktiv){try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{aktiv}});toast('Felhasznalo frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function setRole(id,szerep){try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{szerep}});toast('Szerepkor frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function renameUser(id,currentName){const nev=prompt('Uj nev:',currentName||'');if(nev===null)return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{nev}});toast('Nev frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function resetUserPassword(id){const jelszo=prompt('Uj jelszo (min. 10 karakter):');if(jelszo===null||jelszo==='')return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{jelszo}});toast('Jelszo frissitve')}catch(error){toast(error.message,'err')}}
async function deleteUser(id){if(!confirm('Biztosan torlod ezt a felhasznalot?'))return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'DELETE'});toast('Felhasznalo torolve');loadUsers()}catch(error){toast(error.message,'err')}}

// ── Szünetek: állapot-badge, kiemelés, rendezés, inline szerk., lejárt-elrejtés ──
let editingBreak=null;
let hidePastBreaks=true;
function renderBreaks(){
  const tbl=q('breaks-table');
  let list=sortBreaks(state.breaks);
  const pastCount=list.filter(b=>breakStatus(b).key==='mult').length;
  if(hidePastBreaks) list=list.filter(b=>breakStatus(b).key!=='mult');
  const rows=list.map(item=>{
    const s=breakStatus(item);
    const rowCls=s.key==='aktiv'?' class="row-aktiv"':'';
    if(editingBreak===item.id){
      return `<tr${rowCls}>
        <td><input class="mini-input" id="edit-nev-${esc(item.id)}" value="${esc(item.nev)}"></td>
        <td><input class="mini-input" id="edit-kezdet-${esc(item.id)}" type="date" value="${esc(item.kezdet)}"></td>
        <td><input class="mini-input" id="edit-vege-${esc(item.id)}" type="date" value="${esc(item.vege)}"></td>
        <td><div class="flex flex-wrap gap-2">
          <button class="btn btn-gold" onclick="saveEditBreak('${esc(item.id)}')">Mentes</button>
          <button class="btn btn-ghost" onclick="cancelEditBreak()">Megse</button>
        </div></td></tr>`;
    }
    const startNote=s.key==='jovo'?`<span class="days-pill">· ${s.rem} nap mulva</span>`:'';
    const endNote=s.key==='aktiv'?`<span class="days-pill">· meg ${s.rem} nap</span>`:'';
    return `<tr${rowCls}>
      <td>${statusChip(s.label,s.chip)} <span style="margin-left:6px">${esc(item.nev)}</span></td>
      <td class="mono">${esc(item.kezdet)} ${startNote}</td>
      <td class="mono">${esc(item.vege)} ${endNote}</td>
      <td>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-ghost" onclick="startEditBreak('${esc(item.id)}')">Szerkesztes</button>
          <button class="btn btn-ghost" onclick="deleteBreak('${esc(item.id)}')">Torles</button>
        </div>
      </td>
    </tr>`;
  }).join('');
  const toggle=pastCount>0
    ? `<tr><td colspan="4" style="text-align:center;padding-top:14px"><button class="btn btn-ghost" onclick="togglePastBreaks()" style="font-size:12px">${hidePastBreaks?('Lejart szunetek mutatasa ('+pastCount+')'):'Lejart szunetek elrejtese'}</button></td></tr>`
    : '';
  tbl.innerHTML=`<thead><tr><th>Nev / allapot</th><th>Kezdet</th><th>Vege</th><th>Muveletek</th></tr></thead><tbody>${rows||'<tr><td colspan="4">Nincs megjelenitheto szunet.</td></tr>'}${toggle}</tbody>`;
}
function togglePastBreaks(){hidePastBreaks=!hidePastBreaks;renderBreaks()}
function startEditBreak(id){editingBreak=id;renderBreaks()}
function cancelEditBreak(){editingBreak=null;renderBreaks()}
async function saveEditBreak(id){
  const nev=q('edit-nev-'+id).value.trim();
  const kezdet=q('edit-kezdet-'+id).value;
  const vege=q('edit-vege-'+id).value;
  if(!nev){toast('A nev nem lehet ures','err');return}
  if(!/^\d{4}-\d{2}-\d{2}$/.test(kezdet)||!/^\d{4}-\d{2}-\d{2}$/.test(vege)){toast('Ervenytelen datum','err');return}
  if(kezdet>vege){toast('A kezdo datum nem lehet kesobb a zaronal','err');return}
  try{await adminFetch('/api/admin/szunet/'+encodeURIComponent(id),{method:'PATCH',body:{nev,kezdet,vege}});toast('Szunet frissitve');editingBreak=null;loadBreaks()}catch(error){toast(error.message,'err')}
}
async function loadBreaks(){try{const data=await adminFetch('/api/admin/szunetek');state.breaks=data.szunetek||[];renderBreaks();renderBreakBanner(state.breaks)}catch(error){toast(error.message,'err')}}
async function createBreak(){
  const nev=q('break-name').value.trim(), kezdet=q('break-start').value, vege=q('break-end').value;
  if(!nev||!kezdet||!vege){toast('Minden mezo kotelezo','err');return}
  if(kezdet>vege){toast('A kezdo datum nem lehet kesobb a zaronal','err');return}
  const overlap=(state.breaks||[]).find(b=>!(vege<String(b.kezdet||'')||kezdet>String(b.vege||'')));
  if(overlap&&!confirm(`Atfedes a(z) "${overlap.nev}" szunettel (${overlap.kezdet} – ${overlap.vege}). Megis letrehozod?`)) return;
  try{
    await adminFetch('/api/admin/szunetek',{method:'POST',body:{nev,kezdet,vege}});
    q('break-name').value='';q('break-start').value='';q('break-end').value='';
    toast('Szunet letrehozva');
    loadBreaks();
  }catch(error){toast(error.message,'err')}
}
async function deleteBreak(id){if(!confirm('Biztosan torlod ezt a szunetet?'))return;try{await adminFetch('/api/admin/szunet/'+encodeURIComponent(id),{method:'DELETE'});toast('Szunet torolve');loadBreaks()}catch(error){toast(error.message,'err')}}

function renderTeachers(){
  const filter=q('teacher-search').value.trim().toLowerCase();
  const rows=state.teachers.filter(t=>((t.rovid_nev||'')+' '+(t.nev||'')).toLowerCase().includes(filter)).map(t=>`
    <tr>
      <td class="mono">${esc(t.rovid_nev)}</td>
      <td>${esc(t.nev||'-')}</td>
      <td><button class="btn btn-ghost" onclick="fillTeacher(${attrLiteral(t.rovid_nev)},${attrLiteral(t.nev||'')})">Szerkesztes</button></td>
    </tr>`).join('');
  q('teachers-table').innerHTML=`<thead><tr><th>Kod</th><th>Teljes nev</th><th>Muvelet</th></tr></thead><tbody>${rows||'<tr><td colspan="3">Nincs adat.</td></tr>'}</tbody>`;
}
async function loadTeachers(){try{const data=await fetch('/api/tanarok').then(r=>r.json());state.teachers=data.tanarok||[];renderTeachers()}catch(error){toast(error.message,'err')}}
function fillTeacher(code,name){q('teacher-code').value=code;q('teacher-name').value=name}
async function saveTeacher(){
  try{await adminFetch('/api/admin/tanar',{method:'POST',body:{kod:q('teacher-code').value.trim().toUpperCase(),nev:q('teacher-name').value.trim()}});toast('Tanar frissitve');loadTeachers()}catch(error){toast(error.message,'err')}
}

function renderRooms(){
  const filter=q('room-search').value.trim().toLowerCase();
  const rows=state.rooms.filter(t=>(t.terem_szam||'').toLowerCase().includes(filter)).map(t=>`
    <tr>
      <td class="mono">${esc(t.terem_szam)}</td>
      <td>${detectFloor(t.terem_szam)===null?'-':detectFloor(t.terem_szam)+'. szint / auto'}</td>
      <td><input class="inp" style="max-width:88px" id="floor-${esc(t.terem_szam)}" type="number" min="0" max="5" value="${t.emelet??''}" placeholder="${detectFloor(t.terem_szam)??''}"></td>
      <td><button class="btn btn-ghost" onclick="saveRoom(${attrLiteral(t.terem_szam)})">Mentes</button></td>
    </tr>`).join('');
  q('rooms-table').innerHTML=`<thead><tr><th>Terem</th><th>Auto becsles</th><th>Emelet</th><th>Muvelet</th></tr></thead><tbody>${rows||'<tr><td colspan="4">Nincs adat.</td></tr>'}</tbody>`;
}
async function loadRooms(){try{const data=await fetch('/api/termek').then(r=>r.json());state.rooms=data.termek||[];renderRooms()}catch(error){toast(error.message,'err')}}
async function saveRoom(room){
  const raw=q('floor-'+room).value.trim();
  const emelet=raw===''?null:Number(raw);
  try{await adminFetch('/api/admin/terem/'+encodeURIComponent(room),{method:'PATCH',body:{emelet}});toast('Terem frissitve')}catch(error){toast(error.message,'err')}
}
async function autofillRooms(){
  let changed=0;
  for(const room of state.rooms){
    if(room.emelet!==null&&room.emelet!==undefined&&room.emelet!=='') continue;
    const guess=detectFloor(room.terem_szam);
    if(guess===null) continue;
    try{await adminFetch('/api/admin/terem/'+encodeURIComponent(room.terem_szam),{method:'PATCH',body:{emelet:guess}});changed++}catch{}
  }
  toast(changed+' terem auto kitoltve','info');
  loadRooms();
}
async function loadDiagnosztika(){
  q('diag-health').innerHTML='<div class="small">Betoltes...</div>';
  q('diag-source').textContent='Betoltes...';
  q('diag-db').textContent='Betoltes...';
  q('diag-comparison').textContent='Betoltes...';
  try{
    const d=await adminFetch('/api/admin/diagnosztika');
    const s=d.source||{};
    const db=d.database||{};
    const c=d.comparison||{};
    const h=d.health||{};
    const statusClass=h.status==='ok'?'green':h.status==='warning'?'gold':'red';
    let healthHtml=`<div class="flex items-center gap-3 mb-3"><span class="chip ${statusClass}">${esc(h.status||'?').toUpperCase()}</span><span class="small">${esc(d.timestamp||'')}</span></div>`;
    if((h.issues||[]).length>0){
      healthHtml+='<ul class="space-y-1 mt-2">';
      for(const issue of h.issues){
        const ic=issue.level==='error'?'red':'gold';
        healthHtml+=`<li><span class="chip ${ic}" style="font-size:10px">${esc(issue.level)}</span> <span class="small">${esc(issue.message)}</span></li>`;
      }
      healthHtml+='</ul>';
    }else{
      healthHtml+='<p class="small" style="color:#86efac">Minden rendben – nincs hiba vagy figyelmeztetes.</p>';
    }
    q('diag-health').innerHTML=healthHtml;
    q('diag-source').innerHTML=s.file_exists
      ?`<p>Bejegyzesek: <strong>${s.entries_count??0}</strong></p><p>Tanarok: <strong>${s.unique_teachers??0}</strong></p><p>Termek: <strong>${s.unique_rooms??0}</strong></p><p>Osztalyok: <strong>${s.unique_classes??0}</strong></p><p>Tanar nevek: <strong>${s.teacher_names_count??0}</strong></p>`
      :'<p style="color:#fda4af">Forras fajl nem talalhato!</p>';
    q('diag-db').innerHTML=`<p>Tanarok: <strong>${db.tanarok_count??0}</strong></p><p>Termek: <strong>${db.termek_count??0}</strong></p><p>Orarendek: <strong>${db.orarendek_count??0}</strong></p><p>Aktiv orarendek: <strong>${db.orarendek_aktiv_count??0}</strong></p>`;
    let compHtml='';
    const show=(label,arr)=>{if(!arr||arr.length===0) return '';return `<p>${esc(label)}: <strong style="color:#fda4af">${arr.join(', ')}</strong></p>`};
    compHtml+=show('Hianyzo tanarok (DB-bol)',c.missing_teachers);
    compHtml+=show('Extra tanarok (DB-ben de nincs forrasban)',c.extra_db_teachers);
    compHtml+=show('Hianyzo termek (DB-bol)',c.missing_rooms);
    compHtml+=show('Extra termek (DB-ben)',c.extra_db_rooms);
    compHtml+=show('Nev nelkuli tanarok',c.teachers_without_names);
    if(c.orphan_orarendek_teacher_count>0) compHtml+=`<p>Arva tanar FK-k: <strong style="color:#fda4af">${c.orphan_orarendek_teacher_count}</strong></p>`;
    if(c.orphan_orarendek_terem_count>0) compHtml+=`<p>Arva terem FK-k: <strong style="color:#fda4af">${c.orphan_orarendek_terem_count}</strong></p>`;
    q('diag-comparison').innerHTML=compHtml||'<p style="color:#86efac">Nincs elteres – forras es adatbazis szinkronban.</p>';
  }catch(error){toast(error.message,'err')}
}

async function runImport(){
  if(!confirm('Draft orarend verzio keszul a tanarok.js forrasbol.\n\nSemmi nem torlodik es nem lep azonnal eletbe – a publikalas kulon lepes. Folytatod?')) return;
  q('import-status').textContent='Feldolgozas...';
  q('import-result').innerHTML='';
  q('run-import').disabled=true;
  try{
    const {data}=await adminFetchReport('/api/admin/import',{method:'POST'});
    q('import-status').textContent='';

    if(data.ok){
      const s=data.statisztika||{};
      q('import-result').innerHTML=`<div class="glass rounded-xl p-4 mt-3">
        <p><strong style="color:#86efac">Draft verzio #${Number(data.verzio_id)} letrejott.</strong></p>
        <p class="small mt-1">Orak: ${Number(s.orak||0)} | Tanarok: ${Number(s.tanarok||0)} | Termek: ${Number(s.termek||0)} | Osztalyok: ${Number(s.osztalyok||0)}</p>
        <p class="small">Figyelmeztetesek: ${Number(data.figyelmeztetesek_szama||0)} | Ido: ${Number(data.idotartam_ms||0)} ms</p>
        <p class="small mt-2">Az elesitesehez valtsd az Orarend szekciora.</p></div>`;
      toast('Draft letrejott – publikalas az Orarend szekcioban','ok');
    }else{
      q('import-result').innerHTML=`<div class="glass rounded-xl p-4 mt-3" style="border-color:rgba(244,63,94,.3)">
        <p style="color:#fda4af">${esc(data.uzenet||'Sikertelen import')}</p></div>`;
      toast(data.uzenet||'Sikertelen import','err');
    }

    // A reszletes hiba/figyelmeztetes riportot az Orarend szekcio jeleniti meg.
    renderOrarendReport(data);
    await loadOrarendVersions();
    loadDiagnosztika();
  }catch(error){
    q('import-status').textContent='';
    q('import-result').innerHTML=`<div class="glass rounded-xl p-4 mt-3" style="border-color:rgba(244,63,94,.3)"><p style="color:#fda4af">${esc(error.message)}</p></div>`;
    toast(error.message,'err');
  }finally{q('run-import').disabled=false}
}

async function loadGithubInfo(){
  try{
    const d=await adminFetch('/api/admin/github_sync',{method:'GET'});
    const cfg=d.config||{};
    const loc=d.local||{};
    const url=d.raw_url||'';
    const sizeKb=loc.size?(loc.size/1024).toFixed(1):'?';
    q('github-info').innerHTML=
      `<div><strong>Repo:</strong> ${esc(cfg.owner)}/${esc(cfg.repo)} <span style="color:rgba(255,255,255,.35)">(branch: ${esc(cfg.branch)})</span>${cfg.has_token?' 🔒':''}</div>`+
      `<div class="mt-1"><strong>Lokális fájl:</strong> ${loc.exists?'✓ '+sizeKb+' KB':'✗ nincs'}${loc.mtime?' · módosítva: '+esc(loc.mtime):''}</div>`+
      `<div class="mt-1" style="color:rgba(255,255,255,.3);word-break:break-all;font-size:11px;">${esc(url)}</div>`;
  }catch(error){
    q('github-info').innerHTML=`<span style="color:#fda4af">${esc(error.message)}</span>`;
  }
}

async function runGithubSync(){
  if(!confirm('Letölti és felülírja a lokális tanárok.js fájlt a GitHub branch tartalmával. Folytatod?')) return;
  q('github-sync-status').textContent='Letöltés...';
  q('github-sync-result').innerHTML='';
  q('run-github-sync').disabled=true;
  try{
    const d=await adminFetch('/api/admin/github_sync',{method:'POST',body:{}});
    q('github-sync-status').textContent='';
    const changed=d.changed?'<strong style="color:#86efac">változott</strong>':'<strong style="color:rgba(255,255,255,.5)">változatlan</strong>';
    let html=`<div class="glass rounded-xl p-4 mt-2">`
      +`<p><strong style="color:#86efac">GitHub sync sikeres!</strong></p>`
      +`<p class="small mt-1">Letöltve: ${(d.bytes/1024).toFixed(1)} KB · ${changed}</p>`
      +`<p class="small" style="color:rgba(255,255,255,.4)">SHA1: ${esc((d.new_sha1||'').slice(0,12))}…</p>`;
    if(d.changed){
      html+=`<p class="small mt-2" style="color:#f0c76b">⚠️ Friss adatok! Most futtasd a Teljes importot lent.</p>`;
    }
    html+=`</div>`;
    q('github-sync-result').innerHTML=html;
    toast('GitHub szinkronizálva!');
    loadGithubInfo();
    loadDiagnosztika();
  }catch(error){
    q('github-sync-status').textContent='';
    q('github-sync-result').innerHTML=`<div class="glass rounded-xl p-4 mt-2" style="border-color:rgba(244,63,94,.3)"><p style="color:#fda4af">${esc(error.message)}</p></div>`;
    toast(error.message,'err');
  }finally{q('run-github-sync').disabled=false}
}

q('reload-diag').addEventListener('click',()=>{loadDiagnosztika();loadGithubInfo()});
q('run-import').addEventListener('click',runImport);
q('run-github-sync').addEventListener('click',runGithubSync);
q('reload-dashboard').addEventListener('click',loadDashboard);
q('create-user').addEventListener('click',createUser);
q('reload-users').addEventListener('click',loadUsers);
// ── Órarend verziókezelés ────────────────────────────────────────
const ORAREND_STATUS={draft:{label:'DRAFT',chip:'blue'},active:{label:'AKTÍV',chip:'green'},archived:{label:'ARCHIVÁLT',chip:'gold'}};

function orarendStatusChip(status){
  const meta=ORAREND_STATUS[status]||{label:String(status||'?').toUpperCase(),chip:'red'};
  return statusChip(meta.label,meta.chip);
}
function orarendDate(value){return String(value||'').replace('T',' ').slice(0,16)}
function orarendSigned(n){const v=Number(n)||0;return v>0?('+'+v):String(v)}

function renderOrarendSchemaWarning(payload){
  const el=q('orarend-schema'); if(!el) return;
  if(payload&&payload.kod==='HIANYZO_SEMA'){
    el.innerHTML=`<div class="glass rounded-2xl p-5" style="border-color:rgba(244,63,94,.28)">
      <div style="color:#fda4af;font-weight:700;margin-bottom:6px">Hiányzó adatbázis szerkezet</div>
      <div class="small">${esc(payload.uzenet||'')}</div></div>`;
    return;
  }
  el.innerHTML='';
}

function renderOrarendActive(active,versions){
  const el=q('orarend-active'); if(!el) return;
  if(!active){
    el.innerHTML='<span style="color:rgba(255,255,255,.5)">Még nincs publikált órarend verzió.</span>';
    return;
  }
  const row=(versions||[]).find(v=>Number(v.id)===Number(active.id));
  el.innerHTML=`<div style="font-size:18px;color:#fff;font-family:'Playfair Display',serif">${esc(active.nev||'')}</div>
    <div class="mt-1">${esc(active.tanev||'')} · publikálva: ${esc(orarendDate(active.published_at))}
    ${row?(' · '+row.sorok_szama+' órarend sor'):''}</div>`;
}

function renderOrarendVersions(versions){
  const table=q('orarend-versions'); if(!table) return;
  if(!versions.length){table.innerHTML='<tr><td class="small">Még nincs verzió.</td></tr>';return}

  const rows=versions.map(v=>{
    const id=Number(v.id);
    const actions=[];
    if(v.statusz==='draft'){
      actions.push(`<button class="btn btn-gold" data-orarend-publish="${id}">Publikálás</button>`);
      actions.push(`<button class="btn btn-ghost" data-orarend-delete="${id}">Törlés</button>`);
    }else if(v.statusz==='archived'){
      actions.push(`<button class="btn btn-ghost" data-orarend-rollback="${id}">Visszaállítás</button>`);
    }
    return `<tr>
      <td>${orarendStatusChip(v.statusz)}</td>
      <td>${esc(v.nev||'')}<div class="small">${esc(v.megjegyzes||'')}</div></td>
      <td>${esc(v.tanev||'')}</td>
      <td class="mono">${Number(v.sorok_szama||0)}</td>
      <td class="small">${esc(orarendDate(v.created_at))}</td>
      <td><div class="flex gap-2 flex-wrap">${actions.join('')}</div></td>
    </tr>`;
  }).join('');

  table.innerHTML=`<thead><tr><th>Állapot</th><th>Név</th><th>Tanév</th><th>Sorok</th><th>Létrehozva</th><th>Művelet</th></tr></thead><tbody>${rows}</tbody>`;
}

function renderOrarendIssues(title, issues, total, tone){
  if(!total) return '';
  const shown=issues.slice(0,25).map(i=>`<li style="margin-bottom:6px">
    <span class="chip ${tone}">${esc(i.kod||'')}</span>
    <span class="mono small" style="margin:0 6px">${esc(i.hely||'')}</span>
    ${esc(i.uzenet||'')}</li>`).join('');
  const more=total>shown.length?`<div class="small mt-2">… és további ${total-issues.slice(0,25).length} tétel.</div>`:'';
  return `<div class="glass rounded-2xl p-5 mb-4">
    <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">${esc(title)} (${total})</h3>
    <ul class="small" style="list-style:none;padding:0;margin:0">${shown}</ul>${more}</div>`;
}

function renderOrarendDiff(diff){
  if(!diff) return '';
  const labels={orak:'órarend sor',tanarok:'tanár',termek:'terem',osztalyok:'osztály'};
  const items=Object.keys(labels).filter(k=>diff[k]).map(k=>`<div class="stat glass rounded-2xl">
      <div class="small">${esc(labels[k])}</div>
      <div style="font-size:22px;font-weight:700">${Number(diff[k].utana)}</div>
      <div class="small">előtte ${Number(diff[k].elotte)} · ${esc(orarendSigned(diff[k].valtozas))}</div>
    </div>`).join('');
  return `<div class="grid md:grid-cols-4 gap-3 mb-4">${items}</div>`;
}

function renderOrarendReport(payload){
  const el=q('orarend-report'); if(!el) return;
  if(!payload){el.innerHTML='';return}

  const stats=payload.statisztika||{};
  const valid=payload.ok===true;
  const head=`<div class="glass rounded-2xl p-5 mb-4">
    <div class="flex items-center gap-3 flex-wrap mb-3">
      ${valid?statusChip('ELLENŐRZÉS RENDBEN','green'):statusChip('HIBÁS IMPORT','red')}
      ${payload.formatum?statusChip(String(payload.formatum).toUpperCase()+' formátum','blue'):''}
      ${statusChip(Number(payload.hibak_szama||0)+' hiba',Number(payload.hibak_szama||0)?'red':'green')}
      ${statusChip(Number(payload.figyelmeztetesek_szama||0)+' figyelmeztetés','gold')}
    </div>
    <div class="small">${esc(payload.uzenet||'')}</div>
    <div class="grid md:grid-cols-5 gap-3 mt-4">
      ${['orak','tanarok','termek','osztalyok','tantargyak'].map(k=>`<div class="stat glass rounded-2xl">
        <div class="small">${esc(k)}</div><div style="font-size:22px;font-weight:700">${Number(stats[k]||0)}</div></div>`).join('')}
    </div>
  </div>`;

  const publishBox=valid&&payload.verzio_id?`<div class="glass rounded-2xl p-5 mb-4">
    <h3 class="text-sm font-bold uppercase tracking-wider mb-3" style="color:rgba(255,255,255,.4)">Előnézet – draft #${Number(payload.verzio_id)}</h3>
    ${renderOrarendDiff(payload.elteresek)}
    <p class="small mb-3">A publikálás lecseréli az aktuális élő órarendet. A régi verzió archiválódik, bármikor visszaállítható.</p>
    <div class="flex items-center gap-3 flex-wrap">
      <button class="btn btn-gold" data-orarend-publish="${Number(payload.verzio_id)}">Órarend publikálása</button>
      <button class="btn btn-ghost" data-orarend-delete="${Number(payload.verzio_id)}">Draft elvetése</button>
    </div></div>`:'';

  el.innerHTML=head+publishBox
    +renderOrarendIssues('Hibák – ezek nélkül nem publikálható',payload.hibak||[],Number(payload.hibak_szama||0),'red')
    +renderOrarendIssues('Figyelmeztetések – publikálható, de nézd át',payload.figyelmeztetesek||[],Number(payload.figyelmeztetesek_szama||0),'gold');
}

// A sablon vedett admin vegponton van, ezert fetch-csel kerjuk le a fejlecekkel,
// es blobkent mentjuk – egy sima <a href> nem tudna CSRF tokent kuldeni.
async function downloadOrarendTemplate(){
  try{
    const res=await fetch('/api/admin/orarend/sablon',{
      credentials:'same-origin',
      headers:{'X-Ticky-Admin':'1','X-CSRF-Token':csrfToken}
    });
    if(!res.ok){throw new Error((await readJson(res)).uzenet||('HTTP '+res.status))}
    const blob=await res.blob();
    const url=URL.createObjectURL(blob);
    const link=document.createElement('a');
    link.href=url; link.download='Ticky_Import_Template.xlsx';
    link.click();
    URL.revokeObjectURL(url);
  }catch(error){toast(error.message,'err')}
}

async function loadOrarendVersions(){
  try{
    const data=await adminFetch('/api/admin/orarend/verziok');
    renderOrarendSchemaWarning(null);
    state.versions=data.verziok||[];
    renderOrarendVersions(state.versions);
    renderOrarendActive(state.versions.find(v=>v.statusz==='active')||null,state.versions);
  }catch(error){
    // A hiányzó migrációt külön jelezzük, mert az adminnak SQL-t kell futtatnia.
    const res=await adminFetchReport('/api/admin/orarend/verziok');
    if(res.data&&res.data.kod==='HIANYZO_SEMA'){renderOrarendSchemaWarning(res.data)}
    else{toast(error.message,'err')}
    renderOrarendVersions([]);
    renderOrarendActive(null,[]);
  }
}

async function uploadOrarend(){
  const input=q('orarend-fajl');
  const file=input&&input.files&&input.files[0];
  if(!file){toast('Válassz ki egy .xlsx fájlt','err');return}

  const body=new FormData();
  body.append('fajl',file);
  body.append('nev',q('orarend-nev').value.trim());
  body.append('tanev',q('orarend-tanev').value.trim());

  const button=q('orarend-upload');
  button.disabled=true;
  q('orarend-status').textContent='Feldolgozás…';
  renderOrarendReport(null);

  try{
    const {data}=await adminFetchReport('/api/admin/orarend/import',{method:'POST',body});
    renderOrarendReport(data);
    if(data.ok){
      q('orarend-status').textContent='Draft létrejött ('+Number(data.idotartam_ms||0)+' ms)';
      toast('Ellenőrzés kész – a draft publikálásra vár','ok');
    }else{
      q('orarend-status').textContent='Az import elutasítva';
      toast(data.uzenet||'Hibás import','err');
    }
    await loadOrarendVersions();
  }catch(error){
    q('orarend-status').textContent='';
    toast(error.message,'err');
  }finally{
    button.disabled=false;
  }
}

async function publishOrarendVersion(id,mode){
  const version=state.versions.find(v=>Number(v.id)===Number(id));
  const active=state.versions.find(v=>v.statusz==='active');
  const question=mode==='rollback'
    ? 'Visszaállítod ezt a verziót?\n\n'+(version?version.nev:('#'+id))
    : 'Publikálod ezt az órarendet?\n\nAktuális: '+(active?active.nev:'nincs')+'\nÚj: '+(version?version.nev:('#'+id));
  if(!window.confirm(question)) return;

  try{
    const data=await adminFetch('/api/admin/orarend/'+Number(id)+'/'+(mode==='rollback'?'rollback':'publish'),{method:'POST'});
    toast(data.uzenet||'Kész','ok');
    renderOrarendReport(null);
    await loadOrarendVersions();
  }catch(error){toast(error.message,'err')}
}

async function deleteOrarendDraft(id){
  if(!window.confirm('Biztosan törlöd ezt a draft verziót a hozzá tartozó sorokkal együtt?')) return;
  try{
    await adminFetch('/api/admin/orarend/'+Number(id),{method:'DELETE'});
    toast('Draft törölve','ok');
    renderOrarendReport(null);
    await loadOrarendVersions();
  }catch(error){toast(error.message,'err')}
}

// Egy delegált figyelő az egész szekcióra: a gombok dinamikusan jönnek létre.
document.getElementById('section-orarend')?.addEventListener('click',event=>{
  const publish=event.target.closest('[data-orarend-publish]');
  if(publish){publishOrarendVersion(publish.dataset.orarendPublish,'publish');return}
  const rollback=event.target.closest('[data-orarend-rollback]');
  if(rollback){publishOrarendVersion(rollback.dataset.orarendRollback,'rollback');return}
  const remove=event.target.closest('[data-orarend-delete]');
  if(remove){deleteOrarendDraft(remove.dataset.orarendDelete)}
});

q('user-search').addEventListener('input',renderUsers);
q('export-users').addEventListener('click',exportUsers);
q('create-break').addEventListener('click',createBreak);
q('reload-breaks').addEventListener('click',loadBreaks);
q('teacher-search').addEventListener('input',renderTeachers);
q('save-teacher').addEventListener('click',saveTeacher);
q('reload-teachers').addEventListener('click',loadTeachers);
q('export-teachers').addEventListener('click',exportTeachers);
q('room-search').addEventListener('input',renderRooms);
q('reload-rooms').addEventListener('click',loadRooms);
q('autofill-rooms').addEventListener('click',autofillRooms);
q('export-rooms').addEventListener('click',exportRooms);
q('orarend-upload').addEventListener('click',uploadOrarend);
q('orarend-template').addEventListener('click',downloadOrarendTemplate);
q('reload-orarend').addEventListener('click',loadOrarendVersions);
q('logout-btn').addEventListener('click',logout);

loadDashboard();
refreshBreakBanner();
</script>
<?php endif; ?>
</body>
</html>
