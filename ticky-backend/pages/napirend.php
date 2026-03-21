<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Napirend</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif; background-color:#060f1e; min-height:100vh;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%);
  }
  body::before { content:''; position:fixed; inset:0; pointer-events:none; z-index:0; background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px); background-size:40px 40px; }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  .slide-up { animation:slideUp .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:10px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .pulse { animation:pulseDot 2s infinite; }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

  .nap-tab {
    padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500;
    background:transparent; border:1px solid transparent; color:rgba(255,255,255,.4);
    cursor:pointer; transition:all .15s; width:auto; margin-top:0;
    font-family:'DM Sans',sans-serif; white-space:nowrap;
  }
  .nap-tab:hover { color:rgba(255,255,255,.8); background:rgba(255,255,255,.06); }
  .nap-tab.active { background:rgba(200,151,42,.15); border-color:rgba(200,151,42,.4); color:#f0c76b; font-weight:600; }
  .nap-tab.ma { border-color:rgba(255,255,255,.15); color:rgba(255,255,255,.7); }

  .ora-sor { display:flex; align-items:stretch; border-radius:12px; overflow:hidden; border:1px solid rgba(255,255,255,.07); transition:border-color .15s; }
  .ora-sor:hover { border-color:rgba(255,255,255,.18); }
  .ora-sor.aktiv { border-color:rgba(200,151,42,.5); background:rgba(200,151,42,.06); }
  .ora-sor.mult { opacity:.38; }

  .ora-szam {
    font-family:'Playfair Display',serif; font-weight:700; font-size:20px;
    color:rgba(255,255,255,.2); width:44px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,.03); border-right:1px solid rgba(255,255,255,.06);
  }
  .ora-sor.aktiv .ora-szam { color:#f0c76b; background:rgba(200,151,42,.08); border-right-color:rgba(200,151,42,.2); }

  .ora-body { flex:1; padding:11px 14px; min-width:0; }
  .ora-ido { font-size:11px; color:rgba(255,255,255,.28); font-weight:500; letter-spacing:.02em; margin-bottom:3px; }
  .ora-sor.aktiv .ora-ido { color:rgba(240,199,107,.55); }
  .ora-tanar { font-family:'Playfair Display',serif; font-size:16px; font-weight:700; color:white; line-height:1.2; }
  .ora-meta { font-size:12px; color:rgba(255,255,255,.38); margin-top:2px; }
  .ora-sor.aktiv .ora-meta { color:rgba(255,255,255,.6); }

  .ora-progress { width:4px; flex-shrink:0; background:rgba(255,255,255,.05); position:relative; overflow:hidden; }
  .ora-progress-fill { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(to top,#c8972a,#f0c76b); transition:height .6s ease; }

  .szunet-sor { display:flex; align-items:center; gap:10px; padding:5px 12px; color:rgba(255,255,255,.18); font-size:11px; }
  .szunet-vonal { flex:1; height:1px; background:rgba(255,255,255,.05); }

  .ora-lista { animation:fadeOrak .3s ease both; }
  @keyframes fadeOrak { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
  a { text-decoration:none; }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Navbar -->
<nav class="sticky top-0 z-50 px-5 h-16 flex items-center justify-between" style="background:rgba(6,15,30,.75);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);">
  <div class="flex items-center gap-3">
    <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;" class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span class="text-sm font-medium" id="nav-cim" style="color:rgba(255,255,255,.45);">Napirend</span>
  </div>
  <div class="flex items-center gap-2">
    <a id="nav-vissza" href="/termek" class="text-sm px-3 py-2 rounded-lg" style="color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.5)'">← Vissza</a>
    <button onclick="refresh()" class="flex items-center gap-1 px-3 py-2 rounded-lg" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.4)'">
      <svg id="refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    </button>
  </div>
</nav>

<div class="relative z-10 max-w-lg mx-auto px-4 pt-6 pb-16 slide-up">

  <!-- Fejléc -->
  <div class="mb-5">
    <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.28);">Terem · Heti napirend</p>
    <div class="flex items-end justify-between gap-3">
      <h1 id="terem-cim" style="font-family:'Playfair Display',serif;font-size:56px;font-weight:700;color:white;line-height:1;letter-spacing:-1.5px;">–</h1>
      <div id="aktiv-pill" class="mb-2 hidden items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0" style="background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.4);color:#f0c76b;">
        <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" style="background:#f0c76b;display:inline-block;"></span>
        <span id="aktiv-pill-txt">Folyamatban</span>
      </div>
    </div>
  </div>

  <!-- Nap tabs -->
  <div id="nap-tabs" class="flex gap-1 mb-5 overflow-x-auto pb-1" style="-ms-overflow-style:none;scrollbar-width:none;"></div>

  <!-- Tartalom -->
  <div id="content">
    <div class="flex flex-col gap-2">
      <div class="skeleton h-16"></div>
      <div class="skeleton h-16"></div>
      <div class="skeleton h-16"></div>
      <div class="skeleton h-16"></div>
      <div class="skeleton h-16"></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="mt-6 flex items-center justify-between">
    <span style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.18);font-size:13px;font-weight:700;">Ticky</span>
    <span class="text-xs" style="color:rgba(255,255,255,.22);" id="footer-ido">–</span>
    <a id="qr-link" href="#" class="text-sm font-medium" style="color:#f0c76b;">QR nézet →</a>
  </div>
</div>

<script>
const NAP_NEVEK = {1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
let teremSzam = null
let curNap    = maiNap()
let hetData   = null

function getTeremSzam() {
  const p = location.pathname.split('/').filter(Boolean)
  // /terem/204/nap
  if (p[0]==='terem' && p[2]==='nap') return p[1].toUpperCase()
  return null
}

function maiNap() {
  const d = new Date().getDay()
  return (d===0||d===6) ? 1 : d
}

function isAktiv(k,v) {
  const n=new Date();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number)
  const c=n.getHours()*60+n.getMinutes(); return c>=kh*60+km&&c<=vh*60+vm
}
function isMult(v) {
  const n=new Date();const[vh,vm]=v.split(':').map(Number)
  return n.getHours()*60+n.getMinutes()>vh*60+vm
}
function calcPct(k,v) {
  const n=new Date();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number)
  const c=n.getHours()*60+n.getMinutes()
  return Math.min(100,Math.max(0,Math.round(((c-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))
}
function szunetPerc(v,k) {
  const[vh,vm]=v.split(':').map(Number);const[kh,km]=k.split(':').map(Number)
  return (kh*60+km)-(vh*60+vm)
}

function renderTabs() {
  const mai = maiNap()
  document.getElementById('nap-tabs').innerHTML = [1,2,3,4,5].map(n =>
    `<button onclick="setNap(${n})" id="tab-${n}" class="nap-tab${n===curNap?' active':''}${n===mai&&n!==curNap?' ma':''}">${NAP_NEVEK[n]}${n===mai?' · Ma':''}</button>`
  ).join('')
}

function setNap(n) {
  curNap = n
  ;[1,2,3,4,5].forEach(k => {
    const t=document.getElementById('tab-'+k); if(!t) return
    const mai=maiNap()
    t.className='nap-tab'+(k===n?' active':'')+(k===mai&&k!==n?' ma':'')
  })
  renderOrak()
}

async function fetchData() {
  if (!teremSzam) return
  try {
    const d = await fetch(`/api/napirend/${teremSzam}?nap=heten`).then(r=>r.json())
    if (d.error) { showError(d.error); return }
    hetData = {}
    ;(d.het||[]).forEach(nd => { hetData[nd.nap] = nd.orak||[] })
    renderOrak()
  } catch(e) { showError('Nem sikerült csatlakozni') }
  document.getElementById('footer-ido').textContent = new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
}

function renderOrak() {
  const el   = document.getElementById('content')
  const orak = hetData?.[curNap] || []
  const isMaTab = curNap === maiNap()

  // Aktív pill
  const akt = isMaTab ? orak.find(o=>isAktiv(o.kezdes,o.vegzes)) : null
  const pill = document.getElementById('aktiv-pill')
  if (akt) {
    pill.style.display='flex'
    document.getElementById('aktiv-pill-txt').textContent=`${akt.ora_sorszam}. óra folyamatban`
  } else {
    pill.style.display='none'
  }

  if (!orak.length) {
    el.innerHTML=`<div class="text-center py-12"><span class="text-4xl block mb-3">📭</span><p class="font-semibold" style="color:rgba(255,255,255,.55);">Nincs óra ezen a napon</p><p class="text-sm mt-1" style="color:rgba(255,255,255,.28);">A terem szabad ${NAP_NEVEK[curNap]}n</p></div>`
    return
  }

  let html='<div class="flex flex-col gap-2 ora-lista">'
  orak.forEach((o,i) => {
    const ak = isMaTab&&isAktiv(o.kezdes,o.vegzes)
    const mu = isMaTab&&isMult(o.vegzes)
    const pct = ak ? calcPct(o.kezdes,o.vegzes) : 0
    const cl  = ak?'ora-sor aktiv':mu?'ora-sor mult':'ora-sor'
    html += `
      <div class="${cl}">
        <div class="ora-szam">${o.ora_sorszam||i+1}</div>
        <div class="ora-body">
          <div class="ora-ido">${o.kezdes} – ${o.vegzes}</div>
          <div class="ora-tanar">${o.tanar_nev||o.tanar}</div>
          <div class="ora-meta">${o.osztaly} · ${o.tantargy}</div>
        </div>
        <div class="ora-progress"><div class="ora-progress-fill" style="height:${pct}%;"></div></div>
      </div>`
    // Szünet jelző
    const kov = orak[i+1]
    if (kov) {
      const perc = szunetPerc(o.vegzes, kov.kezdes)
      if (perc > 0) {
        html += `<div class="szunet-sor"><div class="szunet-vonal"></div><span>${perc} perc szünet</span><div class="szunet-vonal"></div></div>`
      }
    }
  })
  html += '</div>'
  el.innerHTML = html
}

function showError(msg) {
  document.getElementById('content').innerHTML=`<div class="text-center py-10"><span class="text-4xl block mb-3">⚠️</span><p class="font-semibold" style="color:rgba(255,255,255,.6);">${msg}</p></div>`
}

function refresh() {
  const icon=document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  hetData=null
  fetchData().finally(()=>setTimeout(()=>icon.classList.remove('spinning'),600))
}

// ─── Init ────────────────────────────────────────────
teremSzam = getTeremSzam()

if (!teremSzam) {
  document.getElementById('terem-cim').textContent='?'
  showError('Nincs terem megadva · URL: /terem/204/nap')
} else {
  document.getElementById('terem-cim').textContent = teremSzam
  document.getElementById('nav-cim').textContent   = teremSzam + ' · Napirend'
  document.getElementById('nav-vissza').href = '/terem/' + teremSzam
  document.getElementById('qr-link').href   = '/terem/' + teremSzam
  renderTabs()
  fetchData()
  setInterval(renderOrak, 60_000)
  setInterval(fetchData, 5*60_000)
}
</script>
</body>
</html>
