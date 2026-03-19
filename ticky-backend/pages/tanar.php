<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Tanár kereső</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif; background-color:#060f1e; min-height:100vh; transition:background-image .5s ease;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%);
  }
  body.tant   { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(200,16,46,.35) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%); }
  body.szabad { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,138,74,.35) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(26,74,138,.20) 0%, transparent 55%); }
  body::before { content:''; position:fixed; inset:0; pointer-events:none; z-index:0; background-image: linear-gradient(rgba(255,255,255,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.02) 1px, transparent 1px); background-size:40px 40px; }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  .glass { background:rgba(255,255,255,.05); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.10); }
  .pulse { animation:pulseDot 2s infinite; }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .slide-up { animation:slideUp .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes slideUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:8px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .custom-select { width:100%; padding:12px 40px 12px 16px; border-radius:10px; border:1.5px solid rgba(255,255,255,.12); background:rgba(255,255,255,.06); color:white; font-family:'DM Sans',sans-serif; font-size:15px; appearance:none; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; transition:border-color .2s, box-shadow .2s; }
  .custom-select:focus { outline:none; border-color:rgba(200,151,42,.5); box-shadow:0 0 0 4px rgba(200,151,42,.10); }
  .custom-select option { background:#0b2e59; color:white; }
  .ora-row { transition:background .15s ease; border-radius:10px; }
  .ora-row:hover { background:rgba(255,255,255,.05); }
  .ora-row.aktiv { background:rgba(200,16,46,.12); border-left:3px solid #e8334a; border-radius:0 10px 10px 0; }
  .ora-row.mult { opacity:.4; }
  a { text-decoration:none; }
</style>
</head>
<body class="flex flex-col items-center justify-start p-6">
<div class="top-line"></div>

<div class="w-full max-w-sm slide-up relative z-10 mt-8 mb-16">
  <div class="glass rounded-2xl overflow-hidden">

    <!-- Fejléc -->
    <div class="px-7 pt-6 pb-5" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.35);">Tanár kereső</p>
        <div id="status-pill" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" id="status-dot" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
          <span id="status-text">–</span>
        </div>
      </div>
      <select id="tanar-select" class="custom-select" onchange="onSelectChange()">
        <option value="">— Válassz tanárt —</option>
      </select>
    </div>

    <!-- Aktuális -->
    <div class="px-7 py-6" id="aktualis-content" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="text-center py-4">
        <span class="text-4xl block mb-2">👆</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p>
      </div>
    </div>

    <!-- Napirend -->
    <div class="px-7 py-5">
      <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color:rgba(255,255,255,.3);">Mai napirend</p>
      <div id="ora-lista"><p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p></div>
    </div>

    <!-- Footer -->
    <div class="px-7 py-4 flex items-center justify-between gap-2" style="border-top:1px solid rgba(255,255,255,.08);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.4);font-size:15px;font-weight:700;">Ticky</a>
      <span class="text-xs" style="color:rgba(255,255,255,.3);" id="footer-ido">–</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.12);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
        <svg id="refresh-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
</div>

<script>
const REFRESH_MS = 60_000
let curKod = null

function getTanarFromUrl() {
  const p = location.pathname.split('/').filter(Boolean)
  const q = new URLSearchParams(location.search).get('tanar')
  if (p[0]==='tanar' && p[1]) return decodeURIComponent(p[1]).toUpperCase()
  if (q) return decodeURIComponent(q).toUpperCase()
  return null
}

async function loadTanarok() {
  try {
    const res = await fetch('/api/tanarok')
    const d   = await res.json()
    const sel = document.getElementById('tanar-select')
    ;(d.tanarok||[]).forEach(t => {
      const o = document.createElement('option')
      o.value = t.rovid_nev
      o.textContent = t.nev ? `${t.rovid_nev} – ${t.nev}` : t.rovid_nev
      sel.appendChild(o)
    })
    const url = getTanarFromUrl()
    if (url) { sel.value = url; if (sel.value) { curKod = url; loadData() } }
  } catch(e) {}
}

function onSelectChange() {
  const v = document.getElementById('tanar-select').value
  if (!v) { curKod = null; resetView(); return }
  curKod = v
  history.replaceState(null,'','/tanar/'+encodeURIComponent(v))
  loadData()
}

function resetView() {
  document.getElementById('aktualis-content').innerHTML = `<div class="text-center py-4"><span class="text-4xl block mb-2">👆</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p></div>`
  document.getElementById('ora-lista').innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p>`
  setAllapot('idle')
}

function setAllapot(a) {
  const pill=document.getElementById('status-pill'), dot=document.getElementById('status-dot'), txt=document.getElementById('status-text')
  if (a==='tant') {
    document.body.className='flex flex-col items-center justify-start p-6 tant'
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;'
    dot.style.background='#ff6b82'; txt.textContent='TANÍT'
  } else if (a==='szabad') {
    document.body.className='flex flex-col items-center justify-start p-6 szabad'
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;'
    dot.style.background='#4ade80'; txt.textContent='SZABAD'
  } else {
    document.body.className='flex flex-col items-center justify-start p-6'
    pill.style.cssText='display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;'
    dot.style.background='rgba(255,255,255,.3)'; txt.textContent='–'
  }
}

function calcPct(k,v){const n=new Date();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number);const c=n.getHours()*60+n.getMinutes();return Math.min(100,Math.max(0,Math.round(((c-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))}
function isMult(v){const n=new Date();const[vh,vm]=v.split(':').map(Number);return n.getHours()*60+n.getMinutes()>vh*60+vm}
function isAktiv(k,v){const n=new Date();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number);const c=n.getHours()*60+n.getMinutes();return c>=kh*60+km&&c<=vh*60+vm}

async function fetchTanarData(kod) {
  const t = await fetch('/api/termek?allapot=1').then(r=>r.json())
  const termek = t.termek||[]
  const nd = await Promise.all(termek.map(t=>fetch(`/api/napirend/${t.terem_szam}`).then(r=>r.json()).then(d=>({terem:t.terem_szam,orak:d.orak||[]})).catch(()=>({terem:t.terem_szam,orak:[]}))))
  const orak=[]
  for(const n of nd) for(const o of n.orak) if(o.tanar?.toUpperCase()===kod) orak.push({...o,terem:n.terem})
  return orak.sort((a,b)=>a.kezdes.localeCompare(b.kezdes))
}

async function loadData() {
  if (!curKod) return
  document.getElementById('aktualis-content').innerHTML=`<div class="flex flex-col gap-3"><div class="skeleton h-4 w-2/5"></div><div class="skeleton h-8 w-3/5"></div><div class="skeleton h-4 w-full mt-1"></div></div>`
  try {
    const orak=await fetchTanarData(curKod)
    const akt=orak.find(o=>isAktiv(o.kezdes,o.vegzes))
    const kov=orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
    setAllapot(akt?'tant':orak.length>0?'szabad':'idle')
    renderAktualis(akt,kov)
    renderNapirend(orak)
  } catch(e) {
    document.getElementById('aktualis-content').innerHTML=`<div class="text-center py-4"><span class="text-3xl block mb-2">⚠️</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Betöltési hiba</p></div>`
  }
  document.getElementById('footer-ido').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
}

function renderAktualis(a,k) {
  const el=document.getElementById('aktualis-content')
  if(a) {
    const pct=calcPct(a.kezdes,a.vegzes)
    el.innerHTML=`<div class="flex flex-col gap-4"><div><p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Most itt van</p><p style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;">${a.terem}. terem</p></div><div class="grid grid-cols-2 gap-3"><div><p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;">${a.osztaly}</p></div><div><p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;">${a.tantargy}</p></div></div><div><div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.1);"><div class="h-full rounded-full" style="width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s ease;"></div></div><div class="flex justify-between mt-2 text-xs" style="color:rgba(255,255,255,.4);"><span>${a.kezdes}</span><span style="color:#ff6b82;font-weight:600;">${a.vegzes}-ig</span><span>${a.vegzes}</span></div></div></div>`
  } else if(k) {
    el.innerHTML=`<div class="flex items-center gap-4 py-2"><span class="text-3xl">☕</span><div><p class="font-semibold" style="color:rgba(255,255,255,.8);">Jelenleg szabad</p><p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">Következő: <strong style="color:rgba(255,255,255,.7);">${k.terem}. terem</strong> · ${k.kezdes}–${k.vegzes}</p></div></div>`
  } else {
    el.innerHTML=`<div class="flex items-center gap-4 py-2"><span class="text-3xl">✅</span><div><p class="font-semibold" style="color:#4ade80;">Szabad</p><p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">Ma már nincs több óra</p></div></div>`
  }
}

function renderNapirend(orak) {
  const el=document.getElementById('ora-lista')
  if(!orak.length){el.innerHTML=`<p class="text-sm" style="color:rgba(255,255,255,.35);">Nincs mai óra</p>`;return}
  el.innerHTML=orak.map((o,i)=>{
    const akt=isAktiv(o.kezdes,o.vegzes),mult=isMult(o.vegzes)
    const cl=akt?'ora-row aktiv':mult?'ora-row mult':'ora-row'
    return `<div class="${cl} flex items-center gap-3 px-3 py-2.5 -mx-1"><span style="font-family:'Playfair Display',serif;font-weight:700;font-size:18px;color:${mult?'rgba(255,255,255,.2)':'rgba(255,255,255,.9)'};width:24px;text-align:right;flex-shrink:0;">${o.ora_sorszam||i+1}</span><div class="flex-1 min-w-0"><div class="flex items-baseline gap-1.5 flex-wrap"><span class="text-sm font-medium" style="color:${mult?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${o.terem}. terem</span><span class="text-xs" style="color:rgba(255,255,255,.35);">${o.osztaly} · ${o.tantargy}</span></div><p class="text-xs" style="color:rgba(255,255,255,.3);">${o.kezdes} – ${o.vegzes}</p></div>${akt?`<span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#ff6b82;display:inline-block;"></span>`:''}</div>`
  }).join('')
}

function refresh() {
  const icon=document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  loadData().finally(()=>setTimeout(()=>icon.classList.remove('spinning'),600))
}

loadTanarok()
setInterval(()=>{if(curKod)loadData()},REFRESH_MS)
</script>
</body>
</html>
