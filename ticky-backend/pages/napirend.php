<?php require_once __DIR__ . '/../utils/helpers.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Napirend</title>
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" href="/favicon.png" type="image/png" sizes="64x64">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{ --gold:#c8972a; --gold-l:#f0c76b; --green:#4ade80; --red:#ff6b82; --dim:rgba(255,255,255,.55); --faint:rgba(255,255,255,.3); --border:rgba(255,255,255,.10); }
  *{box-sizing:border-box;}
  body {
    font-family:'DM Sans',sans-serif; color:white; margin:0;
    background-color:#060f1e; min-height:100vh;
    scroll-behavior:smooth; -webkit-overflow-scrolling:touch; overscroll-behavior:none;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.5) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%);
  }
  body::before { content:''; position:fixed; inset:0; pointer-events:none; z-index:0; background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px); background-size:40px 40px; }
  .top-line { position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200; }
  a { text-decoration:none; color:inherit; }
  svg{display:block;}
  a:focus-visible, button:focus-visible, [tabindex]:focus-visible { outline:2px solid rgba(200,151,42,.6); outline-offset:2px; border-radius:8px; }
  @media (prefers-reduced-motion: reduce){ *{animation:none!important; transition:none!important;} }

  .navbar { position:sticky;top:0;z-index:100;height:64px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.8);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .nav-btn { display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--dim);border-radius:8px;padding:7px 12px;font-family:'DM Sans',sans-serif;font-size:13px;cursor:pointer;transition:all .15s;width:auto;margin-top:0; }
  .nav-btn:hover { background:rgba(255,255,255,.10);color:white; }
  .nav-btn svg{width:13px;height:13px;}

  .page-header { position:relative;z-index:10;padding:20px 20px 0;max-width:1200px;margin:0 auto; }
  .terem-num { font-family:'Playfair Display',serif;font-size:48px;font-weight:700;color:white;line-height:1;letter-spacing:-1px; }

  .sum-pill { display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:500;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--dim); }
  .sum-pill svg{width:13px;height:13px;flex-shrink:0;}
  .sum-pill.gold { background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:var(--gold-l); }
  .sum-pill.green { background:rgba(26,138,74,.12);border-color:rgba(26,138,74,.25);color:var(--green); }

  .tt-outer { position:relative;z-index:10;max-width:1200px;margin:20px auto 0;padding:0 20px;overflow-x:auto;scroll-behavior:smooth;-webkit-overflow-scrolling:touch; }
  .tt-grid { display:grid; grid-template-columns: 44px repeat(5, minmax(110px, 1fr)); min-width:580px; }
  .tt-hdr { padding:8px 6px 10px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--faint);border-bottom:1px solid rgba(255,255,255,.08); }
  .tt-hdr.ma { color:var(--gold-l); }
  .tt-hdr-time { border-bottom:1px solid rgba(255,255,255,.08); }
  .tt-body { display:contents; }
  .tt-timecol { position:relative;border-right:1px solid rgba(255,255,255,.08); }
  .tt-daycol { position:relative;border-right:1px solid rgba(255,255,255,.04);overflow:hidden; }
  .tt-daycol:last-child { border-right:none; }
  .tt-daycol.ma { background:rgba(200,151,42,.02); }
  .hline { position:absolute;left:0;right:0;height:1px;background:rgba(255,255,255,.05);pointer-events:none; }
  .hline.bold { background:rgba(255,255,255,.09); }
  .tlabel { position:absolute;right:5px;font-family:'DM Mono',monospace;font-size:10px;font-weight:500;color:rgba(255,255,255,.25);transform:translateY(-50%);white-space:nowrap; }
  .ora-blk {
    position:absolute;left:3px;right:3px;border-radius:8px;padding:5px 7px;overflow:hidden;
    border:1px solid rgba(255,255,255,.08);transition:filter .15s,transform .15s;cursor:pointer;
    background:linear-gradient(160deg,rgba(26,74,138,.85),rgba(11,46,89,.9));
  }
  .ora-blk:hover { filter:brightness(1.2);transform:scaleX(1.02);z-index:20; }
  .ora-blk.aktiv { background:linear-gradient(160deg,rgba(200,151,42,.35),rgba(180,100,20,.3));border-color:rgba(200,151,42,.55); }
  .ora-blk.mult { opacity:.32;background:linear-gradient(160deg,rgba(26,74,138,.4),rgba(11,46,89,.4)); }
  .ob-tanar { font-family:'Playfair Display',serif;font-size:12px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2; }
  .ob-meta { font-size:10px;color:rgba(255,255,255,.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px; }
  .ora-blk.aktiv .ob-tanar { color:var(--gold-l); }
  .ora-blk.aktiv .ob-meta { color:rgba(240,199,107,.55); }
  .ob-prog { position:absolute;bottom:0;left:0;right:0;height:3px;background:rgba(255,255,255,.06);border-radius:0 0 8px 8px;overflow:hidden; }
  .ob-prog-fill { height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-l)); }
  .now-line { position:absolute;left:0;right:0;height:2px;pointer-events:none;z-index:15; background:linear-gradient(90deg,transparent 0%,var(--red) 20%,var(--red) 80%,transparent 100%); }
  .now-dot { position:absolute;left:0;top:-4px;width:10px;height:10px;border-radius:50%;background:var(--red);box-shadow:0 0 8px var(--red);animation:pd 1.5s infinite; }

  .mob-section { display:none; }
  @media (max-width:700px) { .tt-outer { display:none; } .mob-section { display:block; } }
  .mob-tabs { display:flex;gap:4px;overflow-x:auto;padding:16px 16px 0;-ms-overflow-style:none;scrollbar-width:none; }
  .mob-tabs::-webkit-scrollbar { display:none; }
  .mob-tab { padding:8px 12px;border-radius:8px;font-size:13px;font-weight:500;background:transparent;border:1px solid transparent;color:rgba(255,255,255,.4);cursor:pointer;transition:all .15s;width:auto;margin-top:0;font-family:'DM Sans',sans-serif;white-space:nowrap; }
  .mob-tab:hover { color:rgba(255,255,255,.8);background:rgba(255,255,255,.06); }
  .mob-tab.active { background:rgba(200,151,42,.15);border-color:rgba(200,151,42,.4);color:var(--gold-l);font-weight:600; }
  .mob-tab.ma { border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.7); }
  .mob-list { padding:12px 16px 40px;max-width:500px;margin:0 auto; }
  .mob-row { display:flex;align-items:stretch;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.07);margin-bottom:8px;transition:border-color .15s; }
  .mob-row.aktiv { border-color:rgba(200,151,42,.5);background:rgba(200,151,42,.06); }
  .mob-row.mult { opacity:.38; }
  .mob-num { font-family:'Playfair Display',serif;font-weight:700;font-size:18px;color:rgba(255,255,255,.2);width:40px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.03);border-right:1px solid rgba(255,255,255,.06); }
  .mob-row.aktiv .mob-num { color:var(--gold-l);background:rgba(200,151,42,.08);border-right-color:rgba(200,151,42,.2); }
  .mob-body { flex:1;padding:10px 12px;min-width:0; }
  .mob-ido { font-family:'DM Mono',monospace;font-size:10px;color:rgba(255,255,255,.28);font-weight:500;margin-bottom:2px; }
  .mob-row.aktiv .mob-ido { color:rgba(240,199,107,.5); }
  .mob-tanar { font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:white;line-height:1.2; }
  .mob-meta { font-size:11px;color:rgba(255,255,255,.38);margin-top:1px; }
  .mob-prog { width:4px;flex-shrink:0;background:rgba(255,255,255,.05);position:relative;overflow:hidden; }
  .mob-prog-fill { position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,var(--gold),var(--gold-l)); }
  .szu-row { display:flex;align-items:center;gap:8px;padding:3px 10px 8px;color:rgba(255,255,255,.18);font-size:10px; }
  .szu-line { flex:1;height:1px;background:rgba(255,255,255,.05); }

  .skel { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px; }
  @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Navbar -->
<nav class="navbar">
  <div style="display:flex;align-items:center;gap:8px;">
    <span id="nav-cim" style="font-size:14px;color:rgba(255,255,255,.45);font-weight:400;">Napirend</span>
  </div>
  <div style="display:flex;align-items:center;gap:8px;">
    <a id="nav-vissza" href="/termek" class="nav-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Terem
    </a>
    <button onclick="refresh()" class="nav-btn" style="padding:7px 10px;">
      <svg id="ri" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    </button>
  </div>
</nav>

<!-- Ticky brand -->
<div class="page-header" style="padding-top:16px;padding-bottom:4px;">
  <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.3);font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;">
    <span style="width:5px;height:5px;border-radius:50%;background:var(--gold);display:inline-block;"></span>
    Ticky
  </a>
</div>

<!-- Fejléc -->
<div class="page-header">
  <p style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:4px;">Terem · Heti napirend</p>
  <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:12px;">
    <h1 class="terem-num" id="terem-cim">–</h1>
    <div id="summary" style="display:flex;gap:6px;flex-wrap:wrap;padding-bottom:6px;">
      <span class="sum-pill skel" style="width:90px;height:30px;"></span>
      <span class="sum-pill skel" style="width:110px;height:30px;"></span>
    </div>
  </div>
</div>

<!-- Desktop timetable -->
<div class="tt-outer" id="tt-outer">
  <div id="tt-skel" style="min-width:580px;">
    <div style="display:grid;grid-template-columns:44px repeat(5,1fr);gap:6px;margin-bottom:6px;">
      <div></div>
      <div class="skel" style="height:36px;"></div><div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div><div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div>
    </div>
    <div class="skel" style="height:640px;width:100%;"></div>
  </div>
  <div id="tt" class="tt-grid" style="display:none;"></div>
</div>

<!-- Mobil -->
<div class="mob-section">
  <div class="mob-tabs" id="mob-tabs"></div>
  <div class="mob-list" id="mob-list"></div>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const NAP   = {1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
const START = 7*60+30
const END   = 14*60+30
const TOTAL = END-START
const PPM   = 2
const ICO = {
  book:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>',
  clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  bolt:'<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13 2 4.1 13.4A1 1 0 0 0 5 15h5l-1 7 8.9-11.4A1 1 0 0 0 17 9h-5l1-7z"/></svg>',
  next:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 4 10 8-10 8z"/><path d="M19 5v14"/></svg>',
  check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  inbox:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>'
}

let teremSzam = null
let hetData   = null
let curMob    = maiNap()
let nowTimer  = null

function getTerem() {
  const p=location.pathname.split('/').filter(Boolean)
  return (p[0]==='terem'&&p[2]==='nap') ? p[1].toUpperCase() : null
}
function maiNap() {
  if (window.TickyTime) return window.TickyTime.schoolDayIndex()
  const d=new Date().getDay()
  return(d===0||d===6)?1:d
}
function toMin(t) { const[h,m]=t.split(':').map(Number); return h*60+m }
function topPx(m) { return Math.max(0,(m-START)*PPM) }
function nowMin() { return window.TickyTime ? window.TickyTime.nowMinutes() : (new Date().getHours()*60+new Date().getMinutes()) }
function isAktiv(k,v) { const c=nowMin(); return c>=toMin(k)&&c<=toMin(v) }
function isMult(v) { return nowMin()>toMin(v) }
function pct(k,v) { const c=nowMin(); return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100))) }
function nowM() { return nowMin() }

async function fetchData() {
  try {
    const d=await fetch(`/api/napirend/${teremSzam}?nap=heten`).then(r=>r.json())
    if(d.error){showErr(d.error);return}
    if(d.szunet){
      document.getElementById('tt-skel').style.display='none'
      document.getElementById('tt-outer').innerHTML=`<div style="text-align:center;padding:60px 20px;position:relative;z-index:10;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="width:54px;height:54px;margin:0 auto 16px;color:var(--gold-l);"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg><p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--gold-l);margin-bottom:8px;">${d.szunet}</p><p style="color:rgba(255,255,255,.4);">Szünet idején nincs tanítás</p></div>`
      return
    }
    hetData={}
    ;(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]})
    build()
  } catch(e){showErr('Nem sikerült csatlakozni')}
}

function buildSummary() {
  const mai=maiNap(), orak=hetData[mai]||[]
  const el=document.getElementById('summary')
  if(!orak.length){el.innerHTML=`<span class="sum-pill">${ICO.inbox} Ma nincs óra</span>`;return}
  const akt=orak.find(o=>isAktiv(o.kezdes,o.vegzes))
  const kov=orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
  let h=`<span class="sum-pill">${ICO.book} ${orak.length} óra ma</span>`
  h+=`<span class="sum-pill">${ICO.clock} <span class="mono">${orak[0].kezdes} – ${orak[orak.length-1].vegzes}</span></span>`
  if(akt) h+=`<span class="sum-pill gold">${ICO.bolt} ${akt.ora_sorszam}. óra · ${100-pct(akt.kezdes,akt.vegzes)}% van hátra</span>`
  else if(kov) h+=`<span class="sum-pill green">${ICO.next} Következő: <span class="mono">${kov.kezdes}</span></span>`
  else h+=`<span class="sum-pill">${ICO.check} Mára vége</span>`
  el.innerHTML=h
}

function buildTT() {
  const mai=maiNap()
  const el=document.getElementById('tt')
  const ticks=[]
  for(let m=START;m<=END;m+=30) ticks.push(m)
  let html=''
  html+=`<div class="tt-hdr-time"></div>`
  for(let n=1;n<=5;n++){
    const isMa=n===mai
    html+=`<div class="tt-hdr${isMa?' ma':''}">${NAP[n]}${isMa?' <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--gold);margin-left:4px;vertical-align:middle;animation:pd 2s infinite;"></span>':''}</div>`
  }
  let tcol=`<div class="tt-timecol" style="height:${TOTAL*PPM}px;position:relative;">`
  ticks.forEach(m=>{
    const t=topPx(m)
    const h=Math.floor(m/60).toString().padStart(2,'0')
    const mn=(m%60).toString().padStart(2,'0')
    tcol+=`<span class="tlabel" style="top:${t}px;">${h}:${mn}</span>`
  })
  tcol+=`</div>`
  html+=tcol
  for(let n=1;n<=5;n++){
    const isMa=n===mai
    const orak=hetData[n]||[]
    let col=`<div class="tt-daycol${isMa?' ma':''}" style="height:${TOTAL*PPM}px;">`
    ticks.forEach(m=>{
      const t=topPx(m)
      const bold=(m%60===0)
      col+=`<div class="hline${bold?' bold':''}" style="top:${t}px;"></div>`
    })
    if(isMa){
      const nm=nowM()
      if(nm>=START&&nm<=END){
        col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`
      }
    }
    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes))
      const h=Math.max(24,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes)
      const mu=isMa&&isMult(o.vegzes)
      const p=ak?pct(o.kezdes,o.vegzes):0
      const cl=ak?'aktiv':mu?'mult':''
      const nm=o.tanar_nev||o.tanar
      const osztKod=(o.osztaly||'').split('/')[0].trim()
      const href=osztKod?'/osztaly/'+encodeURIComponent(osztKod):'#'
      col+=`<a href="${href}" class="ora-blk ${cl}" style="top:${top}px;height:${h}px;"
        title="${nm} · ${o.osztaly} · ${o.tantargy} · ${o.kezdes}–${o.vegzes} – Klikk: osztály nézet">
        <div class="ob-tanar">${nm}</div>
        ${h>38?`<div class="ob-meta">${o.osztaly} · ${o.tantargy}</div>`:''}
        ${h>56?`<div class="ob-meta mono">${o.kezdes}–${o.vegzes}</div>`:''}
        ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}
      </a>`
    })
    col+=`</div>`
    html+=col
  }
  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html
  el.style.display='grid'
}

function buildMobTabs() {
  const mai=maiNap()
  document.getElementById('mob-tabs').innerHTML=[1,2,3,4,5].map(n=>
    `<button onclick="setMob(${n})" id="mt${n}" class="mob-tab${n===curMob?' active':''}${n===mai&&n!==curMob?' ma':''}">${NAP[n]}${n===mai?' · Ma':''}</button>`
  ).join('')
}

function setMob(n){
  curMob=n
  ;[1,2,3,4,5].forEach(k=>{
    const t=document.getElementById('mt'+k);if(!t)return
    const mai=maiNap()
    t.className='mob-tab'+(k===n?' active':'')+(k===mai&&k!==n?' ma':'')
  })
  buildMobList()
}

function buildMobList() {
  const el=document.getElementById('mob-list')
  const orak=hetData?.[curMob]||[]
  const isMa=curMob===maiNap()
  if(!orak.length){el.innerHTML=`<div style="text-align:center;padding:40px 0;color:rgba(255,255,255,.5);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;margin:0 auto 12px;color:rgba(255,255,255,.35);"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg><p style="color:rgba(255,255,255,.5);">Nincs óra ezen a napon</p></div>`;return}
  let h=''
  orak.forEach((o,i)=>{
    const ak=isMa&&isAktiv(o.kezdes,o.vegzes)
    const mu=isMa&&isMult(o.vegzes)
    const p=ak?pct(o.kezdes,o.vegzes):0
    const osztKod=(o.osztaly||'').split('/')[0].trim()
    const href=osztKod?'/osztaly/'+encodeURIComponent(osztKod):'#'
    h+=`<a href="${href}" class="mob-row${ak?' aktiv':mu?' mult':''}" style="display:flex;">
      <div class="mob-num">${o.ora_sorszam||i+1}</div>
      <div class="mob-body">
        <div class="mob-ido">${o.kezdes} – ${o.vegzes}</div>
        <div class="mob-tanar">${o.tanar_nev||o.tanar}</div>
        <div class="mob-meta">${o.osztaly} · ${o.tantargy}</div>
      </div>
      <div class="mob-prog"><div class="mob-prog-fill" style="height:${p}%;"></div></div>
    </a>`
    const kov=orak[i+1]
    if(kov){const sz=toMin(kov.kezdes)-toMin(o.vegzes);if(sz>0)h+=`<div class="szu-row"><div class="szu-line"></div><span>${sz} perc szünet</span><div class="szu-line"></div></div>`}
  })
  el.innerHTML=h
}

function build(){
  buildSummary()
  buildTT()
  buildMobTabs()
  buildMobList()
  if(nowTimer) clearInterval(nowTimer)
  nowTimer=setInterval(()=>{
    const nl=document.getElementById('now-line')
    if(nl){const nm=nowM();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'}
    buildSummary()
  },60_000)
}

function showErr(msg){
  document.getElementById('tt-skel').style.display='none'
  document.getElementById('tt-outer').innerHTML=`<div style="text-align:center;padding:60px 20px;position:relative;z-index:10;color:rgba(255,255,255,.5);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;margin:0 auto 12px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg><p style="color:rgba(255,255,255,.5);">${msg}</p></div>`
}

function refresh(){
  const ic=document.getElementById('ri');ic.classList.add('spinning')
  hetData=null
  document.getElementById('tt-skel').style.display='block'
  document.getElementById('tt').style.display='none'
  fetchData().finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))
}

teremSzam=getTerem()
if(!teremSzam){
  showErr('Nincs terem megadva · URL: /terem/204/nap')
}else{
  document.getElementById('terem-cim').textContent=teremSzam
  document.getElementById('nav-cim').textContent=teremSzam+' · Napirend'
  document.getElementById('nav-vissza').href='/terem/'+teremSzam
  document.title='Ticky – '+teremSzam+' napirend'
  fetchData()
  setInterval(fetchData,5*60_000)
}
</script>
</body>
</html>
