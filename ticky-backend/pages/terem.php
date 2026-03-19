<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Terem</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif;
    background-color:#060f1e;
    min-height:100vh;
    transition:background-image .6s ease;
  }
  body.szabad {
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,138,74,.40) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(26,138,74,.15) 0%, transparent 55%);
  }
  body.foglalt {
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(200,16,46,.35) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%);
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background-image: linear-gradient(rgba(255,255,255,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.02) 1px, transparent 1px);
    background-size:40px 40px;
  }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  .glass { background:rgba(255,255,255,.05); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.10); }
  .glass-dark { background:rgba(6,15,30,.5); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.08); }
  .pulse { animation:pulseDot 2s infinite; }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .slide-up { animation:slideUp .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes slideUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:8px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .progress-bar { transition:width .6s ease; }
  a { text-decoration:none; }
</style>
</head>
<body class="flex flex-col items-center justify-center min-h-dvh p-6">
<div class="top-line"></div>

<div class="w-full max-w-sm slide-up relative z-10">

  <div class="glass rounded-2xl overflow-hidden">

    <!-- Fejléc -->
    <div class="px-7 pt-6 pb-5 flex items-center justify-between gap-3" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div>
        <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Terem</p>
        <h1 id="terem-szam" style="font-family:'Playfair Display',serif;font-size:52px;font-weight:700;color:white;line-height:1;letter-spacing:-1px;">–</h1>
      </div>
      <div id="status-pill" class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);">
        <div id="status-dot" class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:rgba(255,255,255,.3);display:inline-block;"></div>
        <span id="status-text">Betöltés…</span>
      </div>
    </div>

    <!-- Tartalom -->
    <div class="px-7 py-6" id="content">
      <div class="flex flex-col gap-3">
        <div class="skeleton h-4 w-2/5"></div>
        <div class="skeleton h-9 w-4/5"></div>
        <div class="skeleton h-4 w-1/2"></div>
        <div class="skeleton h-2 w-full mt-2"></div>
      </div>
    </div>

    <!-- Footer -->
    <div class="px-7 py-4 flex items-center justify-between gap-2" style="border-top:1px solid rgba(255,255,255,.08);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.4);font-size:15px;font-weight:700;">Ticky</a>
      <span class="text-xs" style="color:rgba(255,255,255,.3);" id="footer-ido">–</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg transition-all" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.12);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
        <svg id="refresh-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
        </svg>
        Frissít
      </button>
    </div>
  </div>

</div>

<script>
const API_BASE   = ''  // same origin
const REFRESH_MS = 60_000

function getTeremSzam() {
  const path  = location.pathname.split('/').filter(Boolean)
  const query = new URLSearchParams(location.search).get('terem')
  if (path[0] === 'terem' && path[1]) return path[1].toUpperCase()
  if (query) return query.toUpperCase()
  return null
}

function setAllapot(allapot) {
  const pill = document.getElementById('status-pill')
  const dot  = document.getElementById('status-dot')
  const text = document.getElementById('status-text')
  if (allapot === 'foglalt') {
    document.body.className = 'flex flex-col items-center justify-center min-h-dvh p-6 foglalt'
    pill.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:9999px;font-size:14px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);'
    dot.style.background = '#ff6b82'
    text.textContent = 'FOGLALT'
  } else {
    document.body.className = 'flex flex-col items-center justify-center min-h-dvh p-6 szabad'
    pill.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:9999px;font-size:14px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);'
    dot.style.background = '#4ade80'
    text.textContent = 'SZABAD'
  }
}

function calcPct(k, v) {
  const now = new Date()
  const [kh,km] = k.split(':').map(Number)
  const [vh,vm] = v.split(':').map(Number)
  const cur = now.getHours()*60+now.getMinutes()
  return Math.min(100, Math.max(0, Math.round(((cur-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))
}

function kovHtml(k) {
  if (!k) return `<div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);"><p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.3);">Következő óra</p><p class="text-sm" style="color:rgba(255,255,255,.35);">Ma már nincs több óra</p></div>`
  return `<div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);"><p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:rgba(255,255,255,.3);">Következő óra</p><div class="flex items-center justify-between gap-2 flex-wrap"><span class="text-sm font-medium" style="color:rgba(255,255,255,.75);">${k.tanar} · ${k.osztaly} · ${k.tantargy}</span><span class="text-xs" style="color:rgba(255,255,255,.4);">${k.kezdes}–${k.vegzes}</span></div></div>`
}

function renderData(data) {
  setAllapot(data.allapot)
  const content = document.getElementById('content')

  if (data.allapot === 'szabad') {
    content.innerHTML = `
      <div class="text-center py-3">
        <span class="text-5xl block mb-3">✅</span>
        <h2 style="font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:#4ade80;" class="mb-1">Szabad terem</h2>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Nincs aktív foglalás</p>
      </div>
      ${kovHtml(data.kovetkezo)}`
  } else {
    const a = data.aktualis
    const pct = calcPct(a.kezdes, a.vegzes)
    content.innerHTML = `
      <div class="flex flex-col gap-4">
        <div>
          <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Tanár</p>
          <p style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;line-height:1.2;">${a.tanar_nev || a.tanar}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Osztály</p>
            <p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;">${a.osztaly}</p>
          </div>
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.35);">Tantárgy</p>
            <p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;">${a.tantargy}</p>
          </div>
        </div>
        <div>
          <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.1);">
            <div class="h-full rounded-full progress-bar" style="width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div>
          </div>
          <div class="flex justify-between mt-2 text-xs" style="color:rgba(255,255,255,.4);">
            <span>${a.kezdes}</span>
            <span style="color:#ff6b82;font-weight:600;">még ${a.perc_maradt} perc</span>
            <span>${a.vegzes}</span>
          </div>
        </div>
      </div>
      ${kovHtml(data.kovetkezo)}`
  }
}

function showError(msg) {
  document.getElementById('content').innerHTML = `<div class="text-center py-6"><span class="text-4xl block mb-3">⚠️</span><p class="font-semibold" style="color:rgba(255,255,255,.7);">Hiba történt</p><p class="text-sm mt-1" style="color:rgba(255,255,255,.4);">${msg}</p></div>`
}

let teremSzam = null

async function fetchData() {
  if (!teremSzam) return
  try {
    const res  = await fetch(`/api/terem/${teremSzam}`)
    const data = await res.json()
    if (data.error) { showError(data.error); return }
    document.getElementById('terem-szam').textContent = data.terem
    renderData(data)
  } catch(e) { showError('Nem sikerült csatlakozni') }
  document.getElementById('footer-ido').textContent = new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
}

function refresh() {
  const icon = document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  fetchData().finally(() => setTimeout(() => icon.classList.remove('spinning'), 600))
}

teremSzam = getTeremSzam()

if (!teremSzam) {
  document.getElementById('terem-szam').textContent = '?'
  document.getElementById('content').innerHTML = `<div class="text-center py-6"><span class="text-4xl block mb-3">🔍</span><p class="font-semibold" style="color:rgba(255,255,255,.7);">Nincs terem megadva</p><p class="text-sm mt-1" style="color:rgba(255,255,255,.4);">URL: /terem/204</p></div>`
} else {
  document.getElementById('terem-szam').textContent = teremSzam
  fetchData()
  setInterval(fetchData, REFRESH_MS)
}
</script>
</body>
</html>
