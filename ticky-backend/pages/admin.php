<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

private_response_headers();

if (isset($_GET['logout'])) {
    admin_clear_auth_cookie();
    admin_clear_visibility_cookie();
    ticky_clear_user_session();
    header('Location: ' . admin_url());
    exit;
}

$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    $input = trim((string) $_POST['pw']);
    if (admin_is_configured() && hash_equals(admin_password(), $input)) {
        admin_set_auth_cookie();
        admin_set_visibility_cookie();
        header('Location: ' . admin_url());
        exit;
    }
    $login_error = true;
}

$authed = ticky_is_admin_authed();
$no_pw_set = !admin_is_configured();
$current_user = ticky_current_user();
$display_name = $current_user['nev'] ?? $current_user['felhasznalonev'] ?? 'Secret admin';
$auth_mode = is_array($current_user) ? 'felhasznalo' : 'secret';
$admin_path = admin_url();
$logout_url = admin_url(['logout' => 1]);
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
body{font-family:'DM Sans',sans-serif;background:#04090f;color:#fff;min-height:100vh;background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 90% 100%,rgba(200,151,42,.10) 0%,transparent 50%)}body:before{content:'';position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);background-size:44px 44px}.top{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#c8972a 30%,#f0c76b 50%,#c8972a 70%,transparent);box-shadow:0 0 16px rgba(200,151,42,.3);z-index:50}.glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08)}.btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:9px 14px;font-size:13px;font-weight:600}.btn-gold{background:linear-gradient(135deg,#c8972a,#9e6d1e);color:#fff}.btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.78)}.inp{width:100%;border-radius:10px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);padding:10px 12px;color:#fff}.inp:focus{outline:none;border-color:rgba(200,151,42,.45)}.chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid rgba(255,255,255,.12)}.chip.gold{color:#f0c76b;background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.24)}.chip.green{color:#86efac;background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.24)}.chip.red{color:#fda4af;background:rgba(244,63,94,.12);border-color:rgba(244,63,94,.24)}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px}.table th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35)}.navbtn{width:100%;text-align:left;padding:10px 12px;border-radius:10px;font-size:14px;color:rgba(255,255,255,.65);border:1px solid transparent}.navbtn.active{background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.24);color:#f0c76b}.toast{position:fixed;right:20px;bottom:20px;padding:12px 16px;border-radius:12px;z-index:200;border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(16px)}.toast.ok{background:rgba(34,197,94,.18);color:#86efac}.toast.err{background:rgba(244,63,94,.18);color:#fda4af}.toast.info{background:rgba(200,151,42,.18);color:#f0c76b}.section{display:none}.section.active{display:block}.mono{font-family:'DM Mono',monospace}.stat{padding:18px;border-radius:14px}.small{font-size:12px;color:rgba(255,255,255,.5)}@media(max-width:900px){.layout{display:block}.sidebar{width:auto;margin-bottom:16px}.content{padding:16px}}@media(min-width:901px){.layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:20px}.content{padding:24px 24px 40px 0}.sidebar{padding:24px 0 24px 24px}}
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
      <p class="small mt-1 mono"><?= htmlspecialchars($admin_path, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="glass p-8 rounded-2xl">
      <?php if ($no_pw_set): ?>
        <p class="text-lg font-semibold text-amber-300">Nincs ADMIN_PASSWORD beallitva.</p>
        <p class="small mt-3">A secret path route mar mukodik, de jelszo nelkul ezen a login panelen nem lehet belepni.</p>
        <p class="small mt-2">Ha van admin szerepu felhasznalod, a <a href="/login" class="text-amber-300">/login</a> oldalon be tud lepni es ide fog erkezni.</p>
      <?php else: ?>
        <form method="POST" action="<?= htmlspecialchars($admin_path, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
          <div>
            <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Secret admin jelszo</label>
            <input type="password" name="pw" class="inp" placeholder="ADMIN_PASSWORD" autofocus>
          </div>
          <?php if ($login_error): ?>
            <p class="text-sm text-rose-300">Hibas admin jelszo.</p>
          <?php endif; ?>
          <button type="submit" class="btn btn-gold w-full justify-center">Belepes</button>
        </form>
        <p class="small mt-4">Alternativa: <a href="/login" class="text-amber-300">/login</a> admin szerepu felhasznaloval.</p>
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
      <span class="chip gold mono"><?= htmlspecialchars($admin_path, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="flex items-center gap-3">
      <div class="text-right">
        <div class="text-sm"><?= htmlspecialchars((string) $display_name, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="small"><?= $auth_mode === 'felhasznalo' ? 'admin felhasznalo' : 'secret admin' ?></div>
      </div>
      <div id="nav-time" class="small mono hidden sm:block"></div>
      <a href="<?= htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-ghost">Kilepes</a>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div class="glass rounded-2xl p-3">
        <button class="navbtn active" data-section="dashboard">Dashboard</button>
        <button class="navbtn" data-section="felhasznalok">Felhasznalok</button>
        <button class="navbtn" data-section="szunetek">Szunetek</button>
        <button class="navbtn" data-section="tanarok">Tanarok</button>
        <button class="navbtn" data-section="termek">Termek</button>
        <button class="navbtn" data-section="termek">Termek</button>
        <button class="navbtn" data-section="diagnosztika">Diagnosztika</button>
        <div class="mt-4 pt-4 border-t border-white/10 space-y-2">
        <div class="mt-4 pt-4 border-t border-white/10 space-y-2">
          <a href="/termek" class="navbtn block">Termek live</a>
          <a href="/kijelzo" class="navbtn block">Kijelzo</a>
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
            <p class="small mt-2">Admin vagy user account letrehozasa, szerepkor es aktiv allapot kezelese.</p>
          </div>
          <button class="btn btn-ghost" id="reload-users">Frissites</button>
        </div>
        <div class="glass rounded-2xl p-5 mb-5 grid md:grid-cols-4 gap-3">
          <input id="new-username" class="inp" placeholder="Felhasznalonev">
          <input id="new-name" class="inp" placeholder="Nev">
          <input id="new-password" class="inp" placeholder="Jelszo" type="password">
          <select id="new-role" class="inp"><option value="user">user</option><option value="admin">admin</option></select>
          <div class="md:col-span-4">
            <button class="btn btn-gold" id="create-user">Felhasznalo letrehozasa</button>
          </div>
        </div>
        <div class="glass rounded-2xl p-3 overflow-auto">
          <table class="table" id="users-table"></table>
        </div>
      </section>

      <section id="section-szunetek" class="section">
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h1 class="text-3xl font-bold" style="font-family:'Playfair Display',serif;">Szunetek</h1>
            <p class="small mt-2">Uj idoszakok letrehozasa, szerkesztese es torlese.</p>
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
          <button class="btn btn-ghost" id="reload-teachers">Frissites</button>
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
    </main>
  </div>
</div>

<script>
const state={section:'dashboard',users:[],breaks:[],teachers:[],rooms:[]};
const currentUserId=<?= json_encode($current_user['id'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function toast(msg,type='ok'){const n=document.createElement('div');n.className='toast '+type;n.textContent=msg;document.body.appendChild(n);setTimeout(()=>n.remove(),3200)}
function q(id){return document.getElementById(id)}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function attrLiteral(v){return esc(JSON.stringify(v??''))}
function nowTick(){q('nav-time').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit',second:'2-digit'})}
nowTick();setInterval(nowTick,1000)

async function readJson(res){try{return await res.json()}catch{return {}}}
async function adminFetch(url,options={}){
  const init={credentials:'same-origin',...options};
  const headers=new Headers(init.headers||{});
  headers.set('X-Ticky-Admin','1');
  if(init.body&&typeof init.body!=='string'){headers.set('Content-Type','application/json');init.body=JSON.stringify(init.body)}
  init.headers=headers;
  const res=await fetch(url,init);
  const data=await readJson(res);
  if(!res.ok){throw new Error(data.error||('HTTP '+res.status))}
  return data;
}

function setSection(name){
  state.section=name;
  document.querySelectorAll('.section').forEach(el=>el.classList.toggle('active',el.id==='section-'+name));
  document.querySelectorAll('.navbtn[data-section]').forEach(el=>el.classList.toggle('active',el.dataset.section===name));
  const loaders={dashboard:loadDashboard,felhasznalok:loadUsers,szunetek:loadBreaks,tanarok:loadTeachers,termek:loadRooms};
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
    q('dashboard-cards').innerHTML=`
      <div class="glass stat"><div class="small">Termek</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${rooms.count||0}</div></div>
      <div class="glass stat"><div class="small">Tanarok</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${teachers.count||0}</div></div>
      <div class="glass stat"><div class="small">Felhasznalok</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${users.count||0}</div></div>
      <div class="glass stat"><div class="small">Szunetek</div><div class="text-3xl mt-2" style="font-family:'Playfair Display',serif;">${breaks.count||0}</div></div>
    `;
    q('dashboard-status').innerHTML=`
      <div class="grid md:grid-cols-2 gap-3">
        <div>${statusChip('Admin auth rendben','green')} <span class="small ml-2"><?= $auth_mode === 'felhasznalo' ? 'Belepve admin accounttal.' : 'Belepve secret pathon.' ?></span></div>
        <div>${statusChip('Supabase API elerheto','green')} <span class="small ml-2">Valaszok sikeresek.</span></div>
        <div>${statusChip('Foglalt most: ${occupied}','gold')} <span class="small ml-2">Szabad: ${free}</span></div>
        <div>${statusChip('Admin path aktiv','gold')} <span class="small ml-2 mono"><?= htmlspecialchars($admin_path, ENT_QUOTES, 'UTF-8') ?></span></div>
      </div>
    `;
  }catch(error){q('dashboard-status').textContent=error.message;toast(error.message,'err')}
}

function renderUsers(){
  const rows=state.users.map(user=>`
    <tr>
      <td class="mono">${esc(user.felhasznalonev)}</td>
      <td>${esc(user.nev||'-')}</td>
      <td>${statusChip(user.szerep||'user',user.szerep==='admin'?'gold':'green')}</td>
      <td>${statusChip((user.aktiv??true)?'aktiv':'tiltott',(user.aktiv??true)?'green':'red')}</td>
      <td class="mono">${esc((user.letrehozva||'').replace('T',' ').slice(0,16)||'-')}</td>
      <td>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-ghost" onclick="toggleUser('${esc(user.id)}',${!(user.aktiv??true)})">${(user.aktiv??true)?'Tiltas':'Aktivalas'}</button>
          <button class="btn btn-ghost" onclick="setRole('${esc(user.id)}','${user.szerep==='admin'?'user':'admin'}')">${user.szerep==='admin'?'Userre':'Adminna'}</button>
          <button class="btn btn-ghost" onclick="renameUser('${esc(user.id)}',${attrLiteral(user.nev||'')})">Nev</button>
          <button class="btn btn-ghost" onclick="resetUserPassword('${esc(user.id)}')">Jelszo</button>
          ${user.id===currentUserId?'':`<button class="btn btn-ghost" onclick="deleteUser('${esc(user.id)}')">Torles</button>`}
        </div>
      </td>
    </tr>`).join('');
  q('users-table').innerHTML=`<thead><tr><th>Felhasznalonev</th><th>Nev</th><th>Szerep</th><th>Allapot</th><th>Letrehozva</th><th>Muveletek</th></tr></thead><tbody>${rows||'<tr><td colspan="6">Nincs adat.</td></tr>'}</tbody>`;
}
async function loadUsers(){try{const data=await adminFetch('/api/admin/felhasznalok');state.users=data.felhasznalok||[];renderUsers()}catch(error){toast(error.message,'err')}}
async function createUser(){
  const felhasznalonev=q('new-username').value.trim();
  const nev=q('new-name').value.trim();
  const jelszo=q('new-password').value;
  const szerep=q('new-role').value;
  try{
    await adminFetch('/api/admin/felhasznalo',{method:'POST',body:{felhasznalonev,nev,jelszo,szerep}});
    q('new-username').value='';q('new-name').value='';q('new-password').value='';q('new-role').value='user';
    toast('Felhasznalo letrehozva');
    loadUsers();
  }catch(error){toast(error.message,'err')}
}
async function toggleUser(id,aktiv){try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{aktiv}});toast('Felhasznalo frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function setRole(id,szerep){try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{szerep}});toast('Szerepkor frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function renameUser(id,currentName){const nev=prompt('Uj nev:',currentName||'');if(nev===null)return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{nev}});toast('Nev frissitve');loadUsers()}catch(error){toast(error.message,'err')}}
async function resetUserPassword(id){const jelszo=prompt('Uj jelszo (min. 6 karakter):');if(jelszo===null||jelszo==='')return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'PATCH',body:{jelszo}});toast('Jelszo frissitve')}catch(error){toast(error.message,'err')}}
async function deleteUser(id){if(!confirm('Biztosan torlod ezt a felhasznalot?'))return;try{await adminFetch('/api/admin/felhasznalo/'+encodeURIComponent(id),{method:'DELETE'});toast('Felhasznalo torolve');loadUsers()}catch(error){toast(error.message,'err')}}

function renderBreaks(){
  const rows=state.breaks.map(item=>`
    <tr>
      <td>${esc(item.nev)}</td>
      <td class="mono">${esc(item.kezdet)}</td>
      <td class="mono">${esc(item.vege)}</td>
      <td>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-ghost" onclick="editBreak('${esc(item.id)}',${attrLiteral(item.nev)},${attrLiteral(item.kezdet)},${attrLiteral(item.vege)})">Szerkesztes</button>
          <button class="btn btn-ghost" onclick="deleteBreak('${esc(item.id)}')">Torles</button>
        </div>
      </td>
    </tr>`).join('');
  q('breaks-table').innerHTML=`<thead><tr><th>Nev</th><th>Kezdet</th><th>Vege</th><th>Muveletek</th></tr></thead><tbody>${rows||'<tr><td colspan="4">Nincs adat.</td></tr>'}</tbody>`;
}
async function loadBreaks(){try{const data=await adminFetch('/api/admin/szunetek');state.breaks=data.szunetek||[];renderBreaks()}catch(error){toast(error.message,'err')}}
async function createBreak(){
  try{
    await adminFetch('/api/admin/szunetek',{method:'POST',body:{nev:q('break-name').value.trim(),kezdet:q('break-start').value,vege:q('break-end').value}});
    q('break-name').value='';q('break-start').value='';q('break-end').value='';
    toast('Szunet letrehozva');
    loadBreaks();
  }catch(error){toast(error.message,'err')}
}
async function editBreak(id,name,start,end){
  const nev=prompt('Nev:',name); if(nev===null) return;
  const kezdet=prompt('Kezdet (YYYY-MM-DD):',start); if(kezdet===null) return;
  const vege=prompt('Vege (YYYY-MM-DD):',end); if(vege===null) return;
  try{await adminFetch('/api/admin/szunet/'+encodeURIComponent(id),{method:'PATCH',body:{nev,kezdet,vege}});toast('Szunet frissitve');loadBreaks()}catch(error){toast(error.message,'err')}
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

q('reload-dashboard').addEventListener('click',loadDashboard);
q('create-user').addEventListener('click',createUser);
q('reload-users').addEventListener('click',loadUsers);
q('create-break').addEventListener('click',createBreak);
q('reload-breaks').addEventListener('click',loadBreaks);
q('teacher-search').addEventListener('input',renderTeachers);
q('save-teacher').addEventListener('click',saveTeacher);
q('reload-teachers').addEventListener('click',loadTeachers);
q('room-search').addEventListener('input',renderRooms);
q('reload-rooms').addEventListener('click',loadRooms);
q('autofill-rooms').addEventListener('click',autofillRooms);

loadDashboard();
</script>
<?php endif; ?>
</body>
</html>
