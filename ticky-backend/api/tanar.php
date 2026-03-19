<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Tanár</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          display: ['Syne', 'sans-serif'],
          body:    ['DM Sans', 'sans-serif'],
        }
      }
    }
  }
</script>
<style>
  body { font-family: 'DM Sans', sans-serif; transition: background-color .5s ease; }

  .animate-slide-up { animation: slideUp .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .pulse-dot { animation: pulseDot 2s infinite; }
  @keyframes pulseDot {
    0%,100% { opacity:1; transform:scale(1); }
    50%     { opacity:.4; transform:scale(.75); }
  }

  .skeleton {
    background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 8px;
  }
  @keyframes shimmer {
    0%   { background-position:200% 0; }
    100% { background-position:-200% 0; }
  }

  @keyframes spin { to { transform:rotate(360deg); } }
  .spinning { animation: spin .6s linear; }

  .ora-row {
    transition: background .15s ease;
  }
  .ora-row:hover { background: #f8fafc; border-radius: 12px; }

  .ora-row.aktiv {
    background: linear-gradient(90deg, #fef2f2, #fff5f5);
    border-left: 3px solid #ef4444;
    border-radius: 0 12px 12px 0;
  }

  .ora-row.elment {
    opacity: .45;
  }

  .ora-num {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
  }
</style>
</head>

<body class="min-h-dvh flex flex-col items-center justify-start p-6 bg-slate-50" id="body">
<div class="w-full max-w-sm animate-slide-up mt-8 mb-16">

  <!-- ─── TANÁR KÁRTYA ─────────────────────────────── -->
  <div class="bg-white rounded-3xl shadow-xl overflow-hidden">

    <!-- Fejléc -->
    <div class="px-8 pt-7 pb-6 border-b border-slate-100 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-1">Tanár</p>
        <h1 id="tanar-nev" class="font-display font-extrabold text-4xl text-slate-900 leading-tight tracking-tight">–</h1>
        <p id="tanar-telnev" class="text-sm text-slate-400 mt-0.5 hidden"></p>
      </div>
      <div id="status-pill" class="flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 text-slate-500 text-sm font-medium shrink-0">
        <div id="status-dot" class="w-2 h-2 rounded-full bg-slate-400 pulse-dot"></div>
        <span id="status-text">Betöltés…</span>
      </div>
    </div>

    <!-- Aktuális tartalom -->
    <div class="px-8 py-7 border-b border-slate-100" id="aktualis-content">
      <div class="flex flex-col gap-3">
        <div class="skeleton h-4 w-2/5"></div>
        <div class="skeleton h-8 w-3/5"></div>
        <div class="skeleton h-4 w-full mt-1"></div>
      </div>
    </div>

    <!-- Napi menetrend -->
    <div class="px-8 py-6" id="napirend-content">
      <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-4">Mai napirend</p>
      <div id="ora-lista" class="flex flex-col gap-1">
        <div class="skeleton h-10 w-full rounded-xl"></div>
        <div class="skeleton h-10 w-full rounded-xl"></div>
        <div class="skeleton h-10 w-full rounded-xl mt-1"></div>
      </div>
    </div>

    <!-- Footer -->
    <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between gap-2">
      <a href="/termek" class="font-display font-extrabold text-base text-slate-800 tracking-tight hover:opacity-60 transition-opacity">Ticky</a>
      <span class="text-xs text-slate-400" id="footer-ido">–</span>
      <button onclick="refresh()"
        class="flex items-center gap-1.5 text-xs text-slate-400 border border-slate-200 rounded-lg px-2.5 py-1.5 hover:bg-slate-50 transition-all">
        <svg id="refresh-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
        Frissít
      </button>
    </div>
  </div>

</div>

<script>
const API_BASE   = 'https://ticky-6r32.onrender.com'
const REFRESH_MS = 60_000

// URL-ből tanár kód kinyerése
// /tanar/ÁSZJ  vagy  ?tanar=ÁSZJ
function getTanarKod() {
  const path  = location.pathname.split('/').filter(Boolean)
  const query = new URLSearchParams(location.search).get('tanar')
  if (path[0] === 'tanar' && path[1]) return decodeURIComponent(path[1]).toUpperCase()
  if (query) return decodeURIComponent(query).toUpperCase()
  return null
}

function setAllapot(allapot) {
  const pill = document.getElementById('status-pill')
  const dot  = document.getElementById('status-dot')
  const text = document.getElementById('status-text')
  const body = document.getElementById('body')

  if (allapot === 'tant') {
    body.className = 'min-h-dvh flex flex-col items-center justify-start p-6 bg-red-50'
    pill.className = 'flex items-center gap-2 px-4 py-2 rounded-full bg-red-100 text-red-600 text-sm font-medium shrink-0'
    dot.className  = 'w-2 h-2 rounded-full bg-red-500 pulse-dot'
    text.textContent = 'TANÍT'
  } else if (allapot === 'szabad') {
    body.className = 'min-h-dvh flex flex-col items-center justify-start p-6 bg-green-50'
    pill.className = 'flex items-center gap-2 px-4 py-2 rounded-full bg-green-100 text-green-700 text-sm font-medium shrink-0'
    dot.className  = 'w-2 h-2 rounded-full bg-green-500 pulse-dot'
    text.textContent = 'SZABAD'
  } else {
    body.className = 'min-h-dvh flex flex-col items-center justify-start p-6 bg-slate-50'
    pill.className = 'flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 text-slate-500 text-sm font-medium shrink-0'
    dot.className  = 'w-2 h-2 rounded-full bg-slate-400 pulse-dot'
    text.textContent = allapot
  }
}

function calcPct(kezdes, vegzes) {
  const now = new Date()
  const [kh,km] = kezdes.split(':').map(Number)
  const [vh,vm] = vegzes.split(':').map(Number)
  const cur = now.getHours()*60+now.getMinutes()
  return Math.min(100, Math.max(0, Math.round(((cur-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))
}

function isMult(vegzes) {
  const now = new Date()
  const [vh,vm] = vegzes.split(':').map(Number)
  return now.getHours()*60+now.getMinutes() > vh*60+vm
}

function isAktiv(kezdes, vegzes) {
  const now = new Date()
  const [kh,km] = kezdes.split(':').map(Number)
  const [vh,vm] = vegzes.split(':').map(Number)
  const cur = now.getHours()*60+now.getMinutes()
  return cur >= kh*60+km && cur <= vh*60+vm
}

// ─── Fetch ──────────────────────────────────────────────
// Mivel nincs direkt /api/tanar/{kod} endpoint, a napirend API-t használjuk.
// A tanár kereséshez az összes terem napi napirendjét kellene lekérni –
// viszont az API-ból a tanár kód alapján szűrünk a /api/termek endpoint után.
// Egyszerűsített megközelítés: a termek?allapot=1 + napirend/{terem} kombó.

async function fetchTanarData(tanarKod) {
  // 1. Összes terem mai napirendje – párhuzamos fetch
  // Először megszerezzük a terem listát
  const termekRes  = await fetch(`${API_BASE}/api/termek?allapot=1`)
  const termekData = await termekRes.json()
  const termek     = termekData.termek || []

  // 2. Párhuzamos napirend fetch minden teremre
  const napirendek = await Promise.all(
    termek.map(t =>
      fetch(`${API_BASE}/api/napirend/${t.terem_szam}`)
        .then(r => r.json())
        .then(d => ({ terem: t.terem_szam, orak: d.orak || [] }))
        .catch(() => ({ terem: t.terem_szam, orak: [] }))
    )
  )

  // 3. Szűrés tanár kód alapján
  const tanarorak = []
  for (const nd of napirendek) {
    for (const o of nd.orak) {
      if (o.tanar?.toUpperCase() === tanarKod || o.tanar_nev?.toUpperCase()?.includes(tanarKod)) {
        tanarorak.push({ ...o, terem: nd.terem })
      }
    }
  }

  tanarorak.sort((a, b) => a.kezdes.localeCompare(b.kezdes))
  return tanarorak
}

let tanarKod = null

async function loadData() {
  if (!tanarKod) return

  try {
    const orak = await fetchTanarData(tanarKod)

    // Aktuális óra
    const aktualis  = orak.find(o => isAktiv(o.kezdes, o.vegzes))
    const kovetkezo = orak.find(o => !isMult(o.vegzes) && !isAktiv(o.kezdes, o.vegzes))

    // Állapot
    if (aktualis) setAllapot('tant')
    else if (orak.length > 0) setAllapot('szabad')
    else setAllapot('–')

    // Aktuális blokk
    renderAktualis(aktualis, kovetkezo)

    // Napi lista
    renderNapirend(orak)

  } catch(e) {
    showError('Nem sikerült betölteni')
  }

  document.getElementById('footer-ido').textContent =
    new Date().toLocaleTimeString('hu-HU', { hour:'2-digit', minute:'2-digit' })
}

function renderAktualis(a, k) {
  const el = document.getElementById('aktualis-content')

  if (a) {
    const pct = calcPct(a.kezdes, a.vegzes)
    el.innerHTML = `
      <div class="flex flex-col gap-3">
        <div>
          <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-0.5">Most itt van</p>
          <p class="font-display font-bold text-3xl text-slate-900">${a.terem}. terem</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-0.5">Osztály</p>
            <p class="font-display font-bold text-xl text-slate-800">${a.osztaly}</p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-widest uppercase text-slate-400 mb-0.5">Tantárgy</p>
            <p class="font-display font-bold text-xl text-slate-800">${a.tantargy}</p>
          </div>
        </div>
        <div>
          <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-red-400 rounded-full" style="width:${pct}%"></div>
          </div>
          <div class="flex justify-between mt-1.5 text-xs text-slate-400">
            <span>${a.kezdes}</span>
            <span class="text-red-500 font-medium">${a.vegzes}‑ig</span>
            <span>${a.vegzes}</span>
          </div>
        </div>
      </div>`
  } else if (k) {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-2">
        <span class="text-3xl">☕</span>
        <div>
          <p class="font-semibold text-slate-700">Jelenleg szabad</p>
          <p class="text-sm text-slate-400">Következő: <strong class="text-slate-600">${k.terem}. terem</strong> · ${k.kezdes}–${k.vegzes}</p>
        </div>
      </div>`
  } else {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-2">
        <span class="text-3xl">✅</span>
        <div>
          <p class="font-semibold text-green-700">Szabad</p>
          <p class="text-sm text-slate-400">Ma már nincs több óra</p>
        </div>
      </div>`
  }
}

function renderNapirend(orak) {
  const el = document.getElementById('ora-lista')

  if (orak.length === 0) {
    el.innerHTML = `<p class="text-sm text-slate-400 py-2">Nincs mai óra</p>`
    return
  }

  el.innerHTML = orak.map((o, i) => {
    const aktiv = isAktiv(o.kezdes, o.vegzes)
    const mult  = isMult(o.vegzes)
    const rowCl = aktiv ? 'ora-row aktiv' : mult ? 'ora-row elment' : 'ora-row'

    return `
      <div class="${rowCl} flex items-center gap-3 px-3 py-2.5 -mx-1">
        <span class="ora-num text-slate-${mult ? '300' : '900'} text-lg w-6 text-right shrink-0">${o.ora_sorszam || i+1}</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-baseline gap-1.5 flex-wrap">
            <span class="text-sm font-medium text-slate-${mult ? '400' : '800'} truncate">${o.terem}. terem</span>
            <span class="text-xs text-slate-400">${o.osztaly} · ${o.tantargy}</span>
          </div>
          <p class="text-xs text-slate-400">${o.kezdes} – ${o.vegzes}</p>
        </div>
        ${aktiv ? `<div class="w-1.5 h-1.5 rounded-full bg-red-500 pulse-dot shrink-0"></div>` : ''}
      </div>`
  }).join('')
}

function showError(msg) {
  document.getElementById('aktualis-content').innerHTML = `
    <div class="text-center py-4">
      <span class="text-4xl block mb-2">⚠️</span>
      <p class="text-sm text-slate-400">${msg}</p>
    </div>`
  document.getElementById('ora-lista').innerHTML = ''
  setAllapot('Hiba')
}

function refresh() {
  const icon = document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  loadData().finally(() => setTimeout(() => icon.classList.remove('spinning'), 600))
}

// ─── Init ────────────────────────────────────────────────
tanarKod = getTanarKod()

if (!tanarKod) {
  document.getElementById('tanar-nev').textContent = '?'
  setAllapot('Nincs megadva')
  document.getElementById('aktualis-content').innerHTML = `
    <div class="text-center py-4">
      <span class="text-4xl block mb-2">🔍</span>
      <p class="font-semibold text-slate-700">Nincs tanár megadva</p>
      <p class="text-sm text-slate-400">URL: /tanar/ÁSZJ</p>
    </div>`
  document.getElementById('ora-lista').innerHTML = ''
} else {
  document.getElementById('tanar-nev').textContent = tanarKod
  loadData()
  setInterval(loadData, REFRESH_MS)
}
</script>
</body>
</html>
