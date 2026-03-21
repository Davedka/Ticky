<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Napirend</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #060f1e;
    --navy:      #0b2e59;
    --navy-l:    #1a4a8a;
    --gold:      #c8972a;
    --gold-l:    #f0c76b;
    --border:    rgba(255,255,255,.08);
    --text:      rgba(255,255,255,.85);
    --muted:     rgba(255,255,255,.35);
    --glass:     rgba(255,255,255,.05);
    /* Idővonal: 07:30 – 14:30 = 420 perc, 1 perc = 2px */
    --px-per-min: 2px;
    --day-start: 450;  /* 7*60+30 */
    --day-end:   870;  /* 14*60+30 */
    --total-min: 420;
  }

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body {
    font-family:'DM Sans',sans-serif; color:var(--text);
    background-color:var(--bg); min-height:100vh;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.5) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%);
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);
    background-size:40px 40px;
  }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  a { text-decoration:none; }

  /* ── NAVBAR ── */
  .navbar {
    position:sticky; top:0; z-index:100;
    height:64px; padding:0 20px;
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(6,15,30,.75); backdrop-filter:blur(20px);
    border-bottom:1px solid rgba(255,255,255,.07);
  }
  .navbar-brand { font-family:'Playfair Display',serif; font-size:18px; font-weight:700; color:white; display:flex; align-items:center; gap:8px; }
  .pulse { animation:pulseDot 2s infinite; }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .nav-btn { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10); color:rgba(255,255,255,.55); border-radius:8px; padding:7px 14px; font-family:'DM Sans',sans-serif; font-size:13px; cursor:pointer; transition:all .15s; width:auto; margin-top:0; }
  .nav-btn:hover { background:rgba(255,255,255,.10); color:white; }

  /* ── FEJLÉC ── */
  .page-header { position:relative; z-index:10; padding:24px 20px 0; max-width:1200px; margin:0 auto; }
  .terem-num { font-family:'Playfair Display',serif; font-size:52px; font-weight:700; color:white; line-height:1; letter-spacing:-1.5px; }

  /* ── MOBIL NAP TABS ── */
  .mob-tabs { display:none; }
  @media (max-width:768px) {
    .mob-tabs { display:flex; gap:4px; overflow-x:auto; padding:16px 20px 0; -ms-overflow-style:none; scrollbar-width:none; }
    .mob-tabs::-webkit-scrollbar { display:none; }
    .desktop-grid { display:none !important; }
    .mob-view { display:block !important; }
  }
  .mob-tab { padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500; background:transparent; border:1px solid transparent; color:rgba(255,255,255,.4); cursor:pointer; transition:all .15s; width:auto; margin-top:0; font-family:'DM Sans',sans-serif; white-space:nowrap; }
  .mob-tab:hover { color:rgba(255,255,255,.8); background:rgba(255,255,255,.06); }
  .mob-tab.active { background:rgba(200,151,42,.15); border-color:rgba(200,151,42,.4); color:var(--gold-l); font-weight:600; }
  .mob-tab.ma { border-color:rgba(255,255,255,.15); color:rgba(255,255,255,.7); }

  /* ── HETI GRID WRAPPER ── */
  .timetable-wrap {
    position:relative; z-index:10;
    max-width:1200px; margin:24px auto 40px;
    padding:0 20px;
    overflow-x:auto;
  }

  /* ── TIMETABLE ── */
  .timetable {
    display:grid;
    /* col 0: idővonal | col 1-5: napok */
    grid-template-columns: 44px repeat(5, minmax(120px, 1fr));
    gap:0;
    min-width:600px;
  }

  /* Nap fejlécek */
  .day-header {
    padding:10px 8px 12px;
    text-align:center; font-size:12px; font-weight:600;
    letter-spacing:.04em; text-transform:uppercase;
    color:var(--muted);
    border-bottom:1px solid var(--border);
  }
  .day-header.ma { color:var(--gold-l); }
  .day-header .day-name { display:block; }
  .day-header .ma-dot {
    display:inline-block; width:6px; height:6px; border-radius:50%;
    background:var(--gold); margin-top:4px;
    animation:pulseDot 2s infinite;
  }
  .time-header { padding:10px 0 12px; border-bottom:1px solid var(--border); }

  /* Idővonal oszlop */
  .time-col {
    position:relative;
    border-right:1px solid var(--border);
  }

  /* Nap oszlop */
  .day-col {
    position:relative;
    border-right:1px solid rgba(255,255,255,.04);
  }
  .day-col:last-child { border-right:none; }
  .day-col.ma { background:rgba(200,151,42,.025); }

  /* Közös magasság: 420 perc × 2px = 840px */
  .time-col, .day-col { height:840px; }

  /* Vízszintes rácsvonalak (óránként) */
  .hour-line {
    position:absolute; left:0; right:0;
    height:1px; background:rgba(255,255,255,.05);
    pointer-events:none;
  }
  .hour-line.bold { background:rgba(255,255,255,.09); }

  /* Idő feliratok */
  .time-label {
    position:absolute; right:6px;
    font-size:10px; font-weight:500;
    color:rgba(255,255,255,.28);
    transform:translateY(-50%);
    white-space:nowrap;
  }

  /* ── ÓRA BLOKKOK ── */
  .ora-block {
    position:absolute; left:3px; right:3px;
    border-radius:8px;
    padding:6px 8px;
    overflow:hidden;
    cursor:default;
    transition:filter .15s, transform .15s;
    border:1px solid rgba(255,255,255,.08);
  }
  .ora-block:hover { filter:brightness(1.15); transform:scaleX(1.01); z-index:10; }

  /* Normál óra */
  .ora-block.normal {
    background:linear-gradient(160deg, rgba(26,74,138,.85), rgba(11,46,89,.9));
  }
  /* Mai aktív óra */
  .ora-block.aktiv {
    background:linear-gradient(160deg, rgba(200,151,42,.35), rgba(200,100,20,.3));
    border-color:rgba(200,151,42,.5);
  }
  /* Elmúlt óra */
  .ora-block.mult {
    opacity:.35;
    background:linear-gradient(160deg, rgba(26,74,138,.4), rgba(11,46,89,.5));
  }

  .ora-block .ora-tanar {
    font-family:'Playfair Display',serif;
    font-size:13px; font-weight:700;
    color:white; line-height:1.2;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .ora-block .ora-meta {
    font-size:10px; color:rgba(255,255,255,.5);
    margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .ora-block.aktiv .ora-tanar { color:var(--gold-l); }
  .ora-block.aktiv .ora-meta { color:rgba(240,199,107,.6); }

  /* Aktív óra belső progress sáv */
  .ora-progress {
    position:absolute; bottom:0; left:0; right:0; height:3px;
    background:rgba(255,255,255,.06); border-radius:0 0 8px 8px; overflow:hidden;
  }
  .ora-progress-fill {
    height:100%; background:linear-gradient(90deg,var(--gold),var(--gold-l));
    transition:width .6s ease;
  }

  /* ── JELENLEGI IDŐ VONAL ── */
  .now-line {
    position:absolute; left:0; right:0; height:2px;
    background:linear-gradient(90deg, transparent, #ff6b82, transparent);
    z-index:20; pointer-events:none;
  }
  .now-dot {
    position:absolute; left:-1px; top:-4px;
    width:10px; height:10px; border-radius:50%;
    background:#ff6b82; box-shadow:0 0 8px #ff6b82;
    animation:pulseDot 1.5s infinite;
  }
  .now-time-label {
    position:absolute; left:0; right:6px; top:-9px;
    font-size:9px; font-weight:700; color:#ff6b82;
    text-align:right; white-space:nowrap;
  }

  /* ── MOBIL LISTA NÉZET ── */
  .mob-view { display:none; }
  .mob-ora-sor {
    display:flex; align-items:stretch; border-radius:12px; overflow:hidden;
    border:1px solid rgba(255,255,255,.07); transition:border-color .15s; margin-bottom:8px;
  }
  .mob-ora-sor.aktiv { border-color:rgba(200,151,42,.5); background:rgba(200,151,42,.06); }
  .mob-ora-sor.mult { opacity:.38; }
  .mob-szam { font-family:'Playfair Display',serif; font-weight:700; font-size:20px; color:rgba(255,255,255,.2); width:44px; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.03); border-right:1px solid rgba(255,255,255,.06); }
  .mob-ora-sor.aktiv .mob-szam { color:var(--gold-l); background:rgba(200,151,42,.08); border-right-color:rgba(200,151,42,.2); }
  .mob-body { flex:1; padding:11px 14px; min-width:0; }
  .mob-ido { font-size:11px; color:rgba(255,255,255,.28); font-weight:500; margin-bottom:3px; }
  .mob-ora-sor.aktiv .mob-ido { color:rgba(240,199,107,.55); }
  .mob-tanar { font-family:'Playfair Display',serif; font-size:16px; font-weight:700; color:white; line-height:1.2; }
  .mob-meta { font-size:12px; color:rgba(255,255,255,.38); margin-top:2px; }
  .mob-progress { width:4px; flex-shrink:0; background:rgba(255,255,255,.05); position:relative; overflow:hidden; }
  .mob-progress-fill { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(to top,var(--gold),var(--gold-l)); transition:height .6s ease; }
  .szunet-sor { display:flex; align-items:center; gap:10px; padding:4px 12px 8px; color:rgba(255,255,255,.18); font-size:11px; }
  .szunet-vonal { flex:1; height:1px; background:rgba(255,255,255,.05); }

  /* ── ÖSSZEFOGLALÓ BAR ── */
  .summary-bar {
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
    padding:10px 0 18px;
  }
  .sum-pill {
    display:flex; align-items:center; gap:6px;
    padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500;
    background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.55);
  }
  .sum-pill.gold { background:rgba(200,151,42,.12); border-color:rgba(200,151,42,.25); color:var(--gold-l); }
  .sum-pill.green { background:rgba(26,138,74,.12); border-color:rgba(26,138,74,.25); color:#4ade80; }

  /* ── ANIMÁCIÓK ── */
  .fade-in { animation:fadeIn .4s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:10px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Navbar -->
<nav class="navbar">
  <a href="/" class="navbar-brand">
    <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
    Ticky
  </a>
  <div class="flex items-center gap-2">
    <a id="nav-qr" href="#" class="nav-btn">← QR nézet</a>
    <button onclick="refresh()" class="nav-btn flex items-center gap-1">
      <svg id="refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    </button>
  </div>
</nav>

<!-- Fejléc -->
<div class="page-header">
  <div class="flex items-end justify-between gap-4 flex-wrap pb-2">
    <div>
      <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.28);">Terem · Heti napirend</p>
      <h1 class="terem-num" id="terem-cim">–</h1>
    </div>
    <div class="summary-bar" id="summary-bar">
      <div class="sum-pill skeleton" style="width:100px;height:32px;"></div>
      <div class="sum-pill skeleton" style="width:120px;height:32px;"></div>
    </div>
  </div>
</div>

<!-- Mobil nap tabs -->
<div class="mob-tabs" id="mob-tabs"></div>

<!-- Timetable -->
<div class="timetable-wrap" id="timetable-wrap">
  <!-- Skeleton -->
  <div id="skeleton" style="display:grid;grid-template-columns:44px repeat(5,1fr);gap:8px;min-width:600px;">
    <div></div>
    <div class="skeleton" style="height:40px;border-radius:8px;"></div>
    <div class="skeleton" style="height:40px;border-radius:8px;"></div>
    <div class="skeleton" style="height:40px;border-radius:8px;"></div>
    <div class="skeleton" style="height:40px;border-radius:8px;"></div>
    <div class="skeleton" style="height:40px;border-radius:8px;"></div>
    <div></div>
    <div class="skeleton col-span-5" style="height:600px;border-radius:12px;grid-column:span 5;"></div>
  </div>
  <!-- Tényleges timetable -->
  <div class="timetable desktop-grid" id="timetable" style="display:none;"></div>
</div>

<!-- Mobil lista nézet -->
<div class="mob-view relative z-10 px-4 pb-16" id="mob-content" style="max-width:500px;margin:16px auto 0;"></div>

<script>
// ── Konstansok ──────────────────────────────────────
const NAP_NEVEK = {1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
const NAP_ROVID = {1:'H',2:'K',3:'Sze',4:'Cs',5:'P'}
const DAY_START = 7*60+30   // 450 perc (07:30)
const DAY_END   = 14*60+30  // 870 perc (14:30)
const TOTAL_MIN = DAY_END - DAY_START  // 420 perc
const PX_PER_MIN = 2         // 840px összmagasság
const TOTAL_H    = TOTAL_MIN * PX_PER_MIN  // 840px

// Rácsvonal időpontok (7:30-tól 14:30-ig, óránként)
const HOUR_LABELS = []
for (let m = DAY_START; m <= DAY_END; m += 30) {
  const h = Math.floor(m/60).toString().padStart(2,'0')
  const min = (m%60).toString().padStart(2,'0')
  HOUR_LABELS.push({ label:`${h}:${min}`, min:m, bold:(m%60===0) })
}

let teremSzam = null
let hetData   = null
let curMobNap = maiNap()

// ── Segédfüggvények ──────────────────────────────────
function getTeremSzam() {
  const p = location.pathname.split('/').filter(Boolean)
  if (p[0]==='terem' && p[2]==='nap') return p[1].toUpperCase()
  return null
}

function maiNap() {
  const d = new Date().getDay()
  return (d===0||d===6) ? 1 : d
}

function toMin(t) {
  const [h,m] = t.split(':').map(Number)
  return h*60+m
}

function minToTop(m) { return (m - DAY_START) * PX_PER_MIN }

function isAktiv(k,v) {
  const n=new Date(); const c=n.getHours()*60+n.getMinutes()
  return c>=toMin(k) && c<=toMin(v)
}
function isMult(v) {
  const n=new Date(); return n.getHours()*60+n.getMinutes()>toMin(v)
}
function calcPct(k,v) {
  const n=new Date(); const c=n.getHours()*60+n.getMinutes()
  return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)))
}
function nowMin() {
  const n=new Date(); return n.getHours()*60+n.getMinutes()
}
function szunetPerc(v,k) { return toMin(k)-toMin(v) }

// ── Adatbetöltés ─────────────────────────────────────
async function fetchData() {
  if (!teremSzam) return
  try {
    const d = await fetch(`/api/napirend/${teremSzam}?nap=heten`).then(r=>r.json())
    if (d.error) { showError(d.error); return }
    hetData = {}
    ;(d.het||[]).forEach(nd => { hetData[nd.nap] = nd.orak||[] })
    renderAll()
  } catch(e) { showError('Nem sikerült csatlakozni') }
}

// ── Összefoglaló bar ─────────────────────────────────
function renderSummary() {
  const mai = maiNap()
  const orak = hetData[mai] || []
  const bar  = document.getElementById('summary-bar')

  if (!orak.length) {
    bar.innerHTML = `<div class="sum-pill">📭 Ma nincs óra</div>`
    return
  }

  const aktOra  = orak.find(o=>isAktiv(o.kezdes,o.vegzes))
  const elsoOra = orak[0]
  const utolsoOra = orak[orak.length-1]
  const osszes = orak.length

  let html = `<div class="sum-pill">📚 ${osszes} óra ma</div>`
  html += `<div class="sum-pill">🕐 ${elsoOra.kezdes} – ${utolsoOra.vegzes}</div>`

  if (aktOra) {
    const pct = calcPct(aktOra.kezdes, aktOra.vegzes)
    html += `<div class="sum-pill gold">⚡ ${aktOra.ora_sorszam}. óra · ${100-pct}% van hátra</div>`
  } else {
    const kov = orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
    if (kov) html += `<div class="sum-pill green">⏭ Következő: ${kov.kezdes} (${kov.tanar})</div>`
    else html += `<div class="sum-pill">✅ Ma vége az óráknak</div>`
  }

  bar.innerHTML = html
}

// ── Desktop timetable ────────────────────────────────
function renderTimetable() {
  const tt = document.getElementById('timetable')
  document.getElementById('skeleton').style.display='none'
  tt.style.display='grid'

  let html = ''

  // ─ Fejléc sor ─
  html += `<div class="time-header"></div>`
  const mai = maiNap()
  ;[1,2,3,4,5].forEach(n => {
    const isMa = n===mai
    html += `<div class="day-header ${isMa?'ma':''}">
      <span class="day-name">${NAP_NEVEK[n]}</span>
      ${isMa ? '<span class="ma-dot"></span>' : ''}
    </div>`
  })

  // ─ Idővonal oszlop ─
  let timecol = `<div class="time-col" style="position:relative;height:${TOTAL_H}px;">`
  HOUR_LABELS.forEach(hl => {
    const top = minToTop(hl.min)
    if (top < 0 || top > TOTAL_H) return
    timecol += `<span class="time-label" style="top:${top}px;">${hl.label}</span>`
  })
  timecol += `</div>`
  html += timecol

  // ─ Nap oszlopok ─
  ;[1,2,3,4,5].forEach(n => {
    const isMa = n===mai
    const orak = hetData[n] || []

    let col = `<div class="day-col ${isMa?'ma':''}" style="position:relative;height:${TOTAL_H}px;">`

    // Rácsvonalak
    HOUR_LABELS.forEach(hl => {
      const top = minToTop(hl.min)
      if (top < 0 || top > TOTAL_H) return
      col += `<div class="hour-line ${hl.bold?'bold':''}" style="top:${top}px;"></div>`
    })

    // Mai jelenlegi idő vonal
    if (isMa) {
      const nm = nowMin()
      if (nm >= DAY_START && nm <= DAY_END) {
        const top = minToTop(nm)
        col += `<div class="now-line" style="top:${top}px;">
          <div class="now-dot"></div>
        </div>`
      }
    }

    // Óra blokkok
    orak.forEach(o => {
      const top  = minToTop(toMin(o.kezdes))
      const h    = (toMin(o.vegzes) - toMin(o.kezdes)) * PX_PER_MIN
      const akt  = isMa && isAktiv(o.kezdes,o.vegzes)
      const mult = isMa && isMult(o.vegzes)
      const pct  = akt ? calcPct(o.kezdes,o.vegzes) : 0
      const cl   = akt?'aktiv':mult?'mult':'normal'
      const minH = 28

      col += `<div class="ora-block ${cl}" style="top:${top}px;height:${Math.max(minH,h)}px;"
        title="${o.tanar_nev||o.tanar} · ${o.osztaly} · ${o.tantargy} · ${o.kezdes}–${o.vegzes}">
        <div class="ora-tanar">${o.tanar_nev||o.tanar}</div>
        ${h>40?`<div class="ora-meta">${o.osztaly} · ${o.tantargy}</div>`:''}
        ${h>55?`<div class="ora-meta" style="margin-top:1px;">${o.kezdes}–${o.vegzes}</div>`:''}
        ${akt?`<div class="ora-progress"><div class="ora-progress-fill" style="width:${pct}%;"></div></div>`:''}
      </div>`
    })

    col += `</div>`
    html += col
  })

  tt.innerHTML = html
}

// ── Mobil tabs ───────────────────────────────────────
function renderMobTabs() {
  const mai = maiNap()
  document.getElementById('mob-tabs').innerHTML = [1,2,3,4,5].map(n =>
    `<button onclick="setMobNap(${n})" id="mobtab-${n}" class="mob-tab${n===curMobNap?' active':''}${n===mai&&n!==curMobNap?' ma':''}">${NAP_NEVEK[n]}${n===mai?' · Ma':''}</button>`
  ).join('')
}

function setMobNap(n) {
  curMobNap = n
  ;[1,2,3,4,5].forEach(k=>{
    const t=document.getElementById('mobtab-'+k); if(!t) return
    const mai=maiNap()
    t.className='mob-tab'+(k===n?' active':'')+(k===mai&&k!==n?' ma':'')
  })
  renderMobList()
}

// ── Mobil lista ──────────────────────────────────────
function renderMobList() {
  const el   = document.getElementById('mob-content')
  const orak = hetData?.[curMobNap] || []
  const isMaTab = curMobNap === maiNap()

  if (!orak.length) {
    el.innerHTML = `<div class="text-center py-10"><span class="text-4xl block mb-3">📭</span><p style="color:rgba(255,255,255,.55);">Nincs óra ezen a napon</p></div>`
    return
  }

  let html = ''
  orak.forEach((o,i) => {
    const ak = isMaTab&&isAktiv(o.kezdes,o.vegzes)
    const mu = isMaTab&&isMult(o.vegzes)
    const pct = ak ? calcPct(o.kezdes,o.vegzes) : 0
    const cl = ak?'mob-ora-sor aktiv':mu?'mob-ora-sor mult':'mob-ora-sor'
    html += `<div class="${cl}">
      <div class="mob-szam">${o.ora_sorszam||i+1}</div>
      <div class="mob-body">
        <div class="mob-ido">${o.kezdes} – ${o.vegzes}</div>
        <div class="mob-tanar">${o.tanar_nev||o.tanar}</div>
        <div class="mob-meta">${o.osztaly} · ${o.tantargy}</div>
      </div>
      <div class="mob-progress"><div class="mob-progress-fill" style="height:${pct}%;"></div></div>
    </div>`
    const kov = orak[i+1]
    if (kov) {
      const perc = szunetPerc(o.vegzes, kov.kezdes)
      if (perc>0) html += `<div class="szunet-sor"><div class="szunet-vonal"></div><span>${perc} perc szünet</span><div class="szunet-vonal"></div></div>`
    }
  })
  el.innerHTML = html
}

// ── Renderelés ───────────────────────────────────────
function renderAll() {
  renderSummary()
  renderTimetable()
  renderMobTabs()
  renderMobList()
  // Jelenlegi idő vonal percenként frissül
  setInterval(updateNowLine, 60_000)
}

function updateNowLine() {
  // Csak a mai oszlopban frissítjük
  const mai = maiNap()
  const nm = nowMin()
  const existingLines = document.querySelectorAll('.now-line')
  existingLines.forEach(el => {
    el.style.top = (minToTop(nm)) + 'px'
  })
  renderSummary()
}

function showError(msg) {
  document.getElementById('skeleton').style.display='none'
  document.getElementById('timetable-wrap').innerHTML =
    `<div class="text-center py-16 relative z-10"><span class="text-4xl block mb-3">⚠️</span><p style="color:rgba(255,255,255,.5);">${msg}</p></div>`
}

function refresh() {
  const icon = document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  hetData = null
  fetchData().finally(()=>setTimeout(()=>icon.classList.remove('spinning'),600))
}

// ── Init ─────────────────────────────────────────────
teremSzam = getTeremSzam()

if (!teremSzam) {
  showError('Nincs terem megadva · URL: /terem/204/nap')
} else {
  document.getElementById('terem-cim').textContent = teremSzam
  document.getElementById('nav-qr').href = '/terem/' + teremSzam
  document.title = `Ticky – ${teremSzam} napirend`
  fetchData()
  setInterval(fetchData, 5*60_000)
}
</script>
</body>
</html>
