<?php
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route_match = match_route('/tanar/{kod}', $uri);
$selected_kod = $route_match ? strtoupper(urldecode($route_match['kod'])) : null;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
<title>Ticky – <?= $selected_kod ? htmlspecialchars($selected_kod) : 'Tanár kereső' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;overscroll-behavior:none;transition:background-image .5s ease;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
  body.tant{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(200,16,46,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
  body.szabad{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,138,74,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(26,74,138,.20) 0%,transparent 55%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
  a{text-decoration:none;}
  .glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
  .skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
  @keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .spinning{animation:spin .6s linear;}
  .custom-select{width:100%;padding:12px 40px 12px 16px;border-radius:10px;border:1.5px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:white;font-family:'DM Sans',sans-serif;font-size:15px;appearance:none;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;transition:border-color .2s;}
  .custom-select:focus{outline:none;border-color:rgba(200,151,42,.5);}
  .custom-select option{background:#0b2e59;color:white;}
  .custom-select optgroup{background:#071b3a;color:rgba(255,255,255,.5);font-size:11px;font-weight:700;letter-spacing:.07em;}
  .ora-row{transition:background .15s;border-radius:10px;}
  .ora-row.aktiv{background:rgba(200,16,46,.12);border-left:3px solid #e8334a;border-radius:0 10px 10px 0;}
  .ora-row.mult{opacity:.38;}
  .aktiv-card{animation:cardIn .35s cubic-bezier(.22,1,.36,1) both;}
  @keyframes cardIn{from{opacity:0;transform:scale(.97) translateY(8px)}to{opacity:1;transform:none}}
  .lista-in{animation:listaIn .3s cubic-bezier(.22,1,.36,1) both;}
  @keyframes listaIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  /* Floating sidebar + mobile bar */
  .mobile-bottom-bar{display:none;position:fixed;bottom:0;left:0;right:0;z-index:150;background:rgba(6,15,30,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,.08);padding:6px 0 max(6px,env(safe-area-inset-bottom));flex-direction:row;justify-content:space-around;align-items:center;}
  .mbb-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:4px 12px;text-decoration:none;color:rgba(255,255,255,.5);font-size:10px;font-weight:500;}
  .mbb-icon{font-size:19px;line-height:1;}
  .mbb-item.active{color:rgba(200,151,42,.9);}
  @media(max-width:640px){
    .mobile-bottom-bar{display:flex!important;}
    body{padding-bottom:68px;}
    .card-inner{padding:20px 16px!important;}
  }
</style>
</head>
<body>

<?php ticky_nav('tanar', $selected_kod ?: 'Tanár kereső'); ?>

<div class="mobile-bottom-bar">
  <a href="/" class="mbb-item"><span class="mbb-icon">🏠</span>Főoldal</a>
  <a href="/termek" class="mbb-item"><span class="mbb-icon">🏫</span>Termek</a>
  <a href="/tanar" class="mbb-item active"><span class="mbb-icon">👩‍🏫</span>Tanár</a>
  <a href="/osztaly" class="mbb-item"><span class="mbb-icon">🎒</span>Osztály</a>
  <a href="/qr" class="mbb-item"><span class="mbb-icon">🖨️</span>QR</a>
</div>

<div class="relative z-10 flex flex-col items-center px-4 pt-6 sm:pt-8 pb-16">
<div class="w-full max-w-sm slide-up">
  <div class="glass rounded-2xl overflow-hidden">

    <!-- Ticky brand -->
    <div class="px-5 pt-4 pb-0 flex items-center justify-between">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:15px;font-weight:700;display:flex;align-items:center;gap:6px;"
         onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.35)'">
        <span style="width:6px;height:6px;border-radius:50%;background:#c8972a;box-shadow:0 0 6px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>
        Ticky
      </a>
      <span class="text-xs" style="color:rgba(255,255,255,.28);">Tanár kereső</span>
    </div>

    <!-- Fejléc + select -->
    <div class="px-5 sm:px-6 pt-4 pb-5 card-inner" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Válassz tanárt</p>
        <div id="status-pill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0"
             style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" id="sd" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
          <span id="st">–</span>
        </div>
      </div>
      <select id="sel" class="custom-select" onchange="onSelect()">
        <option value="">— Válassz tanárt —</option>
      </select>
    </div>

    <!-- Aktuális blokk -->
    <div class="px-5 sm:px-6 py-5" id="aktblock" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="text-center py-3">
        <span style="font-size:36px;" class="block mb-2">👆</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p>
      </div>
    </div>

    <!-- Mai napirend -->
    <div class="px-5 sm:px-6 py-5">
      <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color:rgba(255,255,255,.28);">Mai napirend</p>
      <div id="napiblock">
        <p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="px-5 sm:px-6 py-3 flex items-center justify-between" style="border-top:1px solid rgba(255,255,255,.08);">
      <span class="text-xs" style="color:rgba(255,255,255,.35);" id="ido">–</span>
      <button onclick="refresh()" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;cursor:pointer;">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
</div>
</div>

<script>
const REFRESH = 60000
let curKod = null

function updateTime(){ document.getElementById('ido').textContent = new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'}) }

function getUrlKod(){
  const p = location.pathname.split('/').filter(Boolean)
  if(p[0]==='tanar' && p[1]) return decodeURIComponent(p[1]).toUpperCase()
  return null
}

// ── Tanárlista betöltése ─────────────────────────────
async function loadTanarok(){
  try{
    const d = await fetch('/api/tanarok').then(r=>r.json())
    const sel = document.getElementById('sel')
    ;(d.tanarok||[]).forEach(t=>{
      const o = document.createElement('option')
      o.value = t.rovid_nev
      o.textContent = t.nev ? `${t.rovid_nev} – ${t.nev}` : t.rovid_nev
      sel.appendChild(o)
    })
    const url = getUrlKod()
    if(url){ sel.value=url; if(sel.value){ curKod=url; loadData() } }
  }catch(e){}
}

function onSelect(){
  const v = document.getElementById('sel').value
  if(!v){ curKod=null; reset(); return }
  curKod = v
  history.replaceState(null,'','/tanar/'+encodeURIComponent(v))
  loadData()
}

function reset(){
  document.getElementById('aktblock').innerHTML=`<div class="text-center py-3"><span style="font-size:36px;" class="block mb-2">👆</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p></div>`
  document.getElementById('napiblock').innerHTML=`<p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p>`
  setAllapot('idle')
}

function setAllapot(a){
  const pill=document.getElementById('status-pill'),dot=document.getElementById('sd'),txt=document.getElementById('st')
  if(a==='tant'){
    document.body.className='tant'
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;'
    dot.style.background='#ff6b82'; txt.textContent='TANÍT'
  }else if(a==='szabad'){
    document.body.className='szabad'
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;'
    dot.style.background='#4ade80'; txt.textContent='SZABAD'
  }else{
    document.body.className=''
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;'
    dot.style.background='rgba(255,255,255,.3)'; txt.textContent='–'
  }
}

function toMin(t){ const[h,m]=t.split(':').map(Number); return h*60+m }
function isAktiv(k,v){ const c=new Date().getHours()*60+new Date().getMinutes(); return c>=toMin(k)&&c<=toMin(v) }
function isMult(v){ return new Date().getHours()*60+new Date().getMinutes()>toMin(v) }
function calcPct(k,v){ const c=new Date().getHours()*60+new Date().getMinutes(); return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100))) }

async function loadData(){
  if(!curKod) return
  document.getElementById('aktblock').innerHTML=`<div class="flex flex-col gap-2 py-1"><div class="skel h-3 w-2/5"></div><div class="skel h-6 w-3/5"></div><div class="skel h-3 w-full mt-1"></div></div>`

  try{
    const d = await fetch(`/api/tanar/${encodeURIComponent(curKod)}/orarend`).then(r=>r.json())

    // Szünet kezelés
    if(d.szunet){
      document.getElementById('aktblock').innerHTML=`
        <div class="aktiv-card text-center py-4">
          <div style="font-size:40px;margin-bottom:10px;">🌙</div>
          <p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#f0c76b;margin-bottom:4px;">${d.szunet}</p>
          <p class="text-sm" style="color:rgba(255,255,255,.45);">Szünet idején nincs tanítás</p>
        </div>`
      document.getElementById('napiblock').innerHTML=''
      setAllapot('idle'); return
    }

    if(d.uzenet && !d.orak?.length){
      document.getElementById('aktblock').innerHTML=`<div class="aktiv-card text-center py-4"><span style="font-size:28px;" class="block mb-2">🌙</span><p class="text-sm" style="color:rgba(255,255,255,.4);">${d.uzenet}</p></div>`
      document.getElementById('napiblock').innerHTML=''
      setAllapot('idle'); return
    }

    if(d.error){
      document.getElementById('aktblock').innerHTML=`<div class="text-center py-3"><span style="font-size:28px;" class="block mb-2">⚠️</span><p class="text-sm" style="color:rgba(255,255,255,.4);">${d.error}</p></div>`
      setAllapot('idle'); return
    }

    const orak = d.orak||[]
    const akt  = orak.find(o=>isAktiv(o.kezdes,o.vegzes))
    const kov  = orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))

    setAllapot(akt?'tant':orak.length>0?'szabad':'idle')
    renderAkt(akt, kov)
    renderLista(orak)

    if(d.tanar_nev){
      const opt = document.querySelector(`#sel option[value="${curKod}"]`)
      if(opt && !opt.textContent.includes('–')) opt.textContent = `${curKod} – ${d.tanar_nev}`
    }
  }catch(e){
    document.getElementById('aktblock').innerHTML=`<div class="text-center py-3"><span style="font-size:28px;" class="block mb-2">⚠️</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Betöltési hiba</p></div>`
    setAllapot('idle')
  }
  updateTime()
}

function renderAkt(a, k){
  const el = document.getElementById('aktblock')
  if(a){
    const p = calcPct(a.kezdes,a.vegzes)
    el.innerHTML=`<div class="aktiv-card flex flex-col gap-3">
      <div>
        <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Most itt van</p>
        <p style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:white;line-height:1.1;">${a.terem}. terem</p>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${a.osztaly}</p></div>
        <div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${a.tantargy}</p></div>
      </div>
      <div>
        <div style="height:5px;border-radius:3px;overflow:hidden;background:rgba(255,255,255,.08);">
          <div style="height:100%;width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s;"></div>
        </div>
        <div class="flex justify-between mt-1" style="font-size:11px;color:rgba(255,255,255,.35);">
          <span>${a.kezdes}</span><span style="color:#ff6b82;font-weight:600;">${a.vegzes}-ig</span><span>${a.vegzes}</span>
        </div>
      </div>
    </div>`
  }else if(k){
    el.innerHTML=`<div class="aktiv-card flex items-center gap-3 py-1">
      <span style="font-size:26px;">☕</span>
      <div>
        <p style="font-weight:600;color:rgba(255,255,255,.8);">Jelenleg szabad</p>
        <p style="font-size:12px;margin-top:2px;color:rgba(255,255,255,.4);">Következő: <strong style="color:rgba(255,255,255,.7);">${k.terem}. terem</strong> · ${k.kezdes}–${k.vegzes}</p>
      </div>
    </div>`
  }else{
    el.innerHTML=`<div class="aktiv-card flex items-center gap-3 py-1">
      <span style="font-size:26px;">✅</span>
      <div><p style="font-weight:600;color:#4ade80;">Szabad</p><p style="font-size:12px;margin-top:2px;color:rgba(255,255,255,.4);">Ma már nincs több óra</p></div>
    </div>`
  }
}

function renderLista(orak){
  const el = document.getElementById('napiblock')
  if(!orak.length){ el.innerHTML=`<p class="text-sm" style="color:rgba(255,255,255,.35);">Nincs mai óra</p>`; return }
  el.innerHTML='<div class="lista-in">'+orak.map((o,i)=>{
    const ak=isAktiv(o.kezdes,o.vegzes), mu=isMult(o.vegzes)
    return `<div class="ora-row${ak?' aktiv':mu?' mult':''}" style="display:flex;align-items:center;gap:10px;padding:9px 8px;margin:0 -4px;">
      <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:16px;color:${mu?'rgba(255,255,255,.2)':'rgba(255,255,255,.8)'};width:20px;text-align:right;flex-shrink:0;">${o.ora_sorszam||i+1}</span>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">
          <span style="font-size:13px;font-weight:500;color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${o.terem}. terem</span>
          <span style="font-size:11px;color:rgba(255,255,255,.35);">${o.osztaly} · ${o.tantargy}</span>
        </div>
        <p style="font-size:11px;color:rgba(255,255,255,.28);">${o.kezdes} – ${o.vegzes}</p>
      </div>
      ${ak?`<span style="width:7px;height:7px;border-radius:50%;background:#ff6b82;display:inline-block;animation:pd 2s infinite;flex-shrink:0;"></span>`:''}
    </div>`
  }).join('')+'</div>'
}

function refresh(){ const ic=document.getElementById('ri'); ic.classList.add('spinning'); loadData().finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600)) }

updateTime()
setInterval(updateTime, 60000)
loadTanarok()
setInterval(()=>{ if(curKod) loadData() }, REFRESH)
</script>
</body>
</html>
