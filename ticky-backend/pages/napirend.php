<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Napirend</title>
<link rel="icon" type="image/png" href="/favicon.png?v=20260327c">
<link rel="shortcut icon" href="/favicon.ico?v=20260327c">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif; color:white;
    background-color:#060f1e; min-height:100vh;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.5) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%);
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),
      linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);
    background-size:40px 40px;
  }
  .top-line { position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200; }
  a { text-decoration:none; color:inherit; }

  /* Navbar */
  .navbar { position:sticky;top:0;z-index:100;height:64px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.8);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .nav-btn { background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.55);border-radius:8px;padding:7px 14px;font-family:'DM Sans',sans-serif;font-size:13px;cursor:pointer;transition:all .15s;width:auto;margin-top:0; }
  .nav-btn:hover { background:rgba(255,255,255,.10);color:white; }

  /* Page header */
  .page-header { position:relative;z-index:10;padding:20px 20px 0;max-width:1200px;margin:0 auto; }
  .terem-num { font-family:'Playfair Display',serif;font-size:48px;font-weight:700;color:white;line-height:1;letter-spacing:-1px; }

  /* Summary pills */
  .sum-pill { display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:500;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.55); }
  .sum-pill.gold { background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:#f0c76b; }
  .sum-pill.green { background:rgba(26,138,74,.12);border-color:rgba(26,138,74,.25);color:#4ade80; }

  /* ── TIMETABLE ── */
  .tt-outer { position:relative;z-index:10;max-width:1200px;margin:20px auto 0;padding:0 20px;overflow-x:auto; }

  .tt-grid {
    display:grid;
    grid-template-columns: 44px repeat(5, minmax(110px, 1fr));
    min-width:580px;
  }

  /* Header row */
  .tt-hdr { padding:8px 6px 10px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:rgba(255,255,255,.35);border-bottom:1px solid rgba(255,255,255,.08); }
  .tt-hdr.ma { color:#f0c76b; }
  .tt-hdr-time { border-bottom:1px solid rgba(255,255,255,.08); }

  /* Body: time col + day cols side by side */
  .tt-body { display:contents; }

  /* Time column */
  .tt-timecol { position:relative;border-right:1px solid rgba(255,255,255,.08); }

  /* Day column */
  .tt-daycol { position:relative;border-right:1px solid rgba(255,255,255,.04);overflow:hidden; }
  .tt-daycol:last-child { border-right:none; }
  .tt-daycol.ma { background:rgba(200,151,42,.02); }

  /* Hour grid lines */
  .hline { position:absolute;left:0;right:0;height:1px;background:rgba(255,255,255,.05);pointer-events:none; }
  .hline.bold { background:rgba(255,255,255,.09); }

  /* Time labels */
  .tlabel { position:absolute;right:5px;font-size:10px;font-weight:500;color:rgba(255,255,255,.25);transform:translateY(-50%);white-space:nowrap; }

  /* Ora block */
  .ora-blk {
    position:absolute;left:3px;right:3px;
    border-radius:8px;padding:5px 7px;overflow:hidden;
    border:1px solid rgba(255,255,255,.08);
    transition:filter .15s,transform .15s;cursor:default;
    background:linear-gradient(160deg,rgba(26,74,138,.85),rgba(11,46,89,.9));
  }
  .ora-blk:hover { filter:brightness(1.2);transform:scaleX(1.02);z-index:20; }
  .ora-blk.aktiv { background:linear-gradient(160deg,rgba(200,151,42,.35),rgba(180,100,20,.3));border-color:rgba(200,151,42,.55); }
  .ora-blk.mult { opacity:.32;background:linear-gradient(160deg,rgba(26,74,138,.4),rgba(11,46,89,.4)); }
  .ob-tanar { font-family:'Playfair Display',serif;font-size:12px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2; }
  .ob-meta { font-size:10px;color:rgba(255,255,255,.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px; }
  .ora-blk.aktiv .ob-tanar { color:#f0c76b; }
  .ora-blk.aktiv .ob-meta { color:rgba(240,199,107,.55); }

  /* Progress bar inside active block */
  .ob-prog { position:absolute;bottom:0;left:0;right:0;height:3px;background:rgba(255,255,255,.06);border-radius:0 0 8px 8px;overflow:hidden; }
  .ob-prog-fill { height:100%;background:linear-gradient(90deg,#c8972a,#f0c76b); }

  /* Now line */
  .now-line { position:absolute;left:0;right:0;height:2px;pointer-events:none;z-index:15; background:linear-gradient(90deg,transparent 0%,#ff6b82 20%,#ff6b82 80%,transparent 100%); }
  .now-dot { position:absolute;left:0;top:-4px;width:10px;height:10px;border-radius:50%;background:#ff6b82;box-shadow:0 0 8px #ff6b82;animation:pd 1.5s infinite; }

  /* ── MOBIL ── */
  .mob-section { display:none; }
  @media (max-width:700px) {
    .tt-outer { display:none; }
    .mob-section { display:block; }
  }
  .mob-tabs { display:flex;gap:4px;overflow-x:auto;padding:16px 16px 0;-ms-overflow-style:none;scrollbar-width:none; }
  .mob-tabs::-webkit-scrollbar { display:none; }
  .mob-tab { padding:8px 12px;border-radius:8px;font-size:13px;font-weight:500;background:transparent;border:1px solid transparent;color:rgba(255,255,255,.4);cursor:pointer;transition:all .15s;width:auto;margin-top:0;font-family:'DM Sans',sans-serif;white-space:nowrap; }
  .mob-tab:hover { color:rgba(255,255,255,.8);background:rgba(255,255,255,.06); }
  .mob-tab.active { background:rgba(200,151,42,.15);border-color:rgba(200,151,42,.4);color:#f0c76b;font-weight:600; }
  .mob-tab.ma { border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.7); }
  .mob-list { padding:12px 16px 40px;max-width:500px;margin:0 auto; }
  .mob-row { display:flex;align-items:stretch;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.07);margin-bottom:8px;transition:border-color .15s; }
  .mob-row.aktiv { border-color:rgba(200,151,42,.5);background:rgba(200,151,42,.06); }
  .mob-row.mult { opacity:.38; }
  .mob-num { font-family:'Playfair Display',serif;font-weight:700;font-size:18px;color:rgba(255,255,255,.2);width:40px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.03);border-right:1px solid rgba(255,255,255,.06); }
  .mob-row.aktiv .mob-num { color:#f0c76b;background:rgba(200,151,42,.08);border-right-color:rgba(200,151,42,.2); }
  .mob-body { flex:1;padding:10px 12px;min-width:0; }
  .mob-ido { font-size:10px;color:rgba(255,255,255,.28);font-weight:500;margin-bottom:2px; }
  .mob-row.aktiv .mob-ido { color:rgba(240,199,107,.5); }
  .mob-tanar { font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:white;line-height:1.2; }
  .mob-meta { font-size:11px;color:rgba(255,255,255,.38);margin-top:1px; }
  .mob-prog { width:4px;flex-shrink:0;background:rgba(255,255,255,.05);position:relative;overflow:hidden; }
  .mob-prog-fill { position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,#c8972a,#f0c76b); }
  .szu-row { display:flex;align-items:center;gap:8px;padding:3px 10px 8px;color:rgba(255,255,255,.18);font-size:10px; }
  .szu-line { flex:1;height:1px;background:rgba(255,255,255,.05); }

  /* Skeleton */
  .skel { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px; }
  @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Navbar -->
<nav class="navbar">
  <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;display:flex;align-items:center;gap:8px;">
    <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
    <a href="/">Ticky</a>
    <span style="color:rgba(255,255,255,.2);font-weight:400;">·</span>
    <span id="nav-cim" style="color:rgba(255,255,255,.45);font-size:15px;font-weight:400;">Napirend</span>
  </div>
  <div style="display:flex;align-items:center;gap:8px;">
    <a id="nav-vissza" href="/termek" class="nav-btn">← QR nézet</a>
    <button onclick="refresh()" class="nav-btn" style="display:flex;align-items:center;gap:4px;padding:7px 10px;">
      <svg id="ri" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    </button>
  </div>
</nav>

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
  <!-- Skeleton -->
  <div id="tt-skel" style="min-width:580px;">
    <div style="display:grid;grid-template-columns:44px repeat(5,1fr);gap:6px;margin-bottom:6px;">
      <div></div>
      <div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div>
      <div class="skel" style="height:36px;"></div>
    </div>
    <div class="skel" style="height:640px;width:100%;"></div>
  </div>
  <!-- Real timetable (hidden until data loads) -->
  <div id="tt" class="tt-grid" style="display:none;"></div>
</div>

<!-- Mobil -->
<div class="mob-section">
  <div class="mob-tabs" id="mob-tabs"></div>
  <div class="mob-list" id="mob-list"></div>
</div>

<script>
const NAP   = {1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
const START = 7*60+30   // 450
const END   = 14*60+30  // 870
const TOTAL = END-START // 420
const PPM   = 2         // px per perc → 840px magasság

let teremSzam = null
let hetData   = null
let curMob    = maiNap()
let nowTimer  = null

function getTerem() {
  const p=location.pathname.split('/').filter(Boolean)
  return (p[0]==='terem'&&p[2]==='nap') ? p[1].toUpperCase() : null
}
function maiNap() { const d=new Date().getDay(); return(d===0||d===6)?1:d }
function toMin(t) { const[h,m]=t.split(':').map(Number); return h*60+m }
function topPx(m) { return Math.max(0,(m-START)*PPM) }
function isAktiv(k,v) { const c=new Date().getHours()*60+new Date().getMinutes(); return c>=toMin(k)&&c<=toMin(v) }
function isMult(v) { return new Date().getHours()*60+new Date().getMinutes()>toMin(v) }
function pct(k,v) { const c=new Date().getHours()*60+new Date().getMinutes(); return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100))) }
function nowM() { return new Date().getHours()*60+new Date().getMinutes() }

// ── Fetch ────────────────────────────────────────────
async function fetchData() {
  try {
    const d=await fetch(`/api/napirend/${teremSzam}?nap=heten`).then(r=>r.json())
    if(d.error){showErr(d.error);return}
    hetData={}
    ;(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]})
    build()
  } catch(e){showErr('Nem sikerült csatlakozni')}
}

// ── Summary ──────────────────────────────────────────
function buildSummary() {
  const mai=maiNap(), orak=hetData[mai]||[]
  const el=document.getElementById('summary')
  if(!orak.length){el.innerHTML=`<span class="sum-pill">📭 Ma nincs óra</span>`;return}
  const akt=orak.find(o=>isAktiv(o.kezdes,o.vegzes))
  const kov=orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
  let h=`<span class="sum-pill">📚 ${orak.length} óra ma</span>`
  h+=`<span class="sum-pill">🕐 ${orak[0].kezdes} – ${orak[orak.length-1].vegzes}</span>`
  if(akt) h+=`<span class="sum-pill gold">⚡ ${akt.ora_sorszam}. óra · ${100-pct(akt.kezdes,akt.vegzes)}% van hátra</span>`
  else if(kov) h+=`<span class="sum-pill green">⏭ Következő: ${kov.kezdes}</span>`
  else h+=`<span class="sum-pill">✅ Mára vége</span>`
  el.innerHTML=h
}

// ── Desktop timetable ────────────────────────────────
function buildTT() {
  const mai=maiNap()
  const el=document.getElementById('tt')

  // Rácsvonal időpontok
  const ticks=[]
  for(let m=START;m<=END;m+=30) ticks.push(m)

  let html=''

  // ── Fejléc sor ──
  html+=`<div class="tt-hdr-time"></div>`
  for(let n=1;n<=5;n++){
    const isMa=n===mai
    html+=`<div class="tt-hdr${isMa?' ma':''}">
      ${NAP[n]}${isMa?' <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#c8972a;margin-left:4px;vertical-align:middle;animation:pd 2s infinite;"></span>':''}
    </div>`
  }

  // ── Idő oszlop ──
  let tcol=`<div class="tt-timecol" style="height:${TOTAL*PPM}px;position:relative;">`
  ticks.forEach(m=>{
    const t=topPx(m)
    const h=Math.floor(m/60).toString().padStart(2,'0')
    const mn=(m%60).toString().padStart(2,'0')
    tcol+=`<span class="tlabel" style="top:${t}px;">${h}:${mn}</span>`
  })
  tcol+=`</div>`
  html+=tcol

  // ── Nap oszlopok ──
  for(let n=1;n<=5;n++){
    const isMa=n===mai
    const orak=hetData[n]||[]
    let col=`<div class="tt-daycol${isMa?' ma':''}" style="height:${TOTAL*PPM}px;">`

    // Rácsvonalak
    ticks.forEach(m=>{
      const t=topPx(m)
      const bold=(m%60===0)
      col+=`<div class="hline${bold?' bold':''}" style="top:${t}px;"></div>`
    })

    // Jelenlegi idő vonal (csak mai)
    if(isMa){
      const nm=nowM()
      if(nm>=START&&nm<=END){
        col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`
      }
    }

    // Óra blokkok
    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes))
      const h=Math.max(24,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes)
      const mu=isMa&&isMult(o.vegzes)
      const p=ak?pct(o.kezdes,o.vegzes):0
      const cl=ak?'aktiv':mu?'mult':''
      const nm=o.tanar_nev||o.tanar

      col+=`<div class="ora-blk ${cl}" style="top:${top}px;height:${h}px;"
        title="${nm} · ${o.osztaly} · ${o.tantargy} · ${o.kezdes}–${o.vegzes}">
        <div class="ob-tanar">${nm}</div>
        ${h>38?`<div class="ob-meta">${o.osztaly} · ${o.tantargy}</div>`:''}
        ${h>56?`<div class="ob-meta">${o.kezdes}–${o.vegzes}</div>`:''}
        ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}
      </div>`
    })

    col+=`</div>`
    html+=col
  }

  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html
  el.style.display='grid'
}

// ── Mobil tabs ───────────────────────────────────────
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
  if(!orak.length){el.innerHTML=`<div style="text-align:center;padding:40px 0;"><span style="font-size:36px;">📭</span><p style="color:rgba(255,255,255,.5);margin-top:12px;">Nincs óra ezen a napon</p></div>`;return}
  let h=''
  orak.forEach((o,i)=>{
    const ak=isMa&&isAktiv(o.kezdes,o.vegzes)
    const mu=isMa&&isMult(o.vegzes)
    const p=ak?pct(o.kezdes,o.vegzes):0
    h+=`<div class="mob-row${ak?' aktiv':mu?' mult':''}">
      <div class="mob-num">${o.ora_sorszam||i+1}</div>
      <div class="mob-body">
        <div class="mob-ido">${o.kezdes} – ${o.vegzes}</div>
        <div class="mob-tanar">${o.tanar_nev||o.tanar}</div>
        <div class="mob-meta">${o.osztaly} · ${o.tantargy}</div>
      </div>
      <div class="mob-prog"><div class="mob-prog-fill" style="height:${p}%;"></div></div>
    </div>`
    const kov=orak[i+1]
    if(kov){const sz=toMin(kov.kezdes)-toMin(o.vegzes);if(sz>0)h+=`<div class="szu-row"><div class="szu-line"></div><span>${sz} perc szünet</span><div class="szu-line"></div></div>`}
  })
  el.innerHTML=h
}

// ── Build all ────────────────────────────────────────
function build(){
  buildSummary()
  buildTT()
  buildMobTabs()
  buildMobList()
  // Now-line frissítése percenként
  if(nowTimer) clearInterval(nowTimer)
  nowTimer=setInterval(()=>{
    const nl=document.getElementById('now-line')
    if(nl){const nm=nowM();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'}
    buildSummary()
  },60_000)
}

function showErr(msg){
  document.getElementById('tt-skel').style.display='none'
  document.getElementById('tt-outer').innerHTML=`<div style="text-align:center;padding:60px 20px;position:relative;z-index:10;"><span style="font-size:40px;">⚠️</span><p style="color:rgba(255,255,255,.5);margin-top:12px;">${msg}</p></div>`
}

function refresh(){
  const ic=document.getElementById('ri');ic.classList.add('spinning')
  hetData=null
  document.getElementById('tt-skel').style.display='block'
  document.getElementById('tt').style.display='none'
  fetchData().finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))
}

// ── Init ─────────────────────────────────────────────
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
