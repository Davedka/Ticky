<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Termek</title>
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
  body { font-family: 'DM Sans', sans-serif; }

  .card-enter {
    animation: cardIn .4s cubic-bezier(.22,1,.36,1) both;
  }
  @keyframes cardIn {
    from { opacity:0; transform:translateY(16px) scale(.98); }
    to   { opacity:1; transform:translateY(0) scale(1); }
  }

  .pulse-dot { animation: pulseDot 2s infinite; }
  @keyframes pulseDot {
    0%,100% { opacity:1; transform:scale(1); }
    50%     { opacity:.4; transform:scale(.7); }
  }

  .header-in { animation: headerSlide .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes headerSlide {
    from { opacity:0; transform:translateY(-12px); }
    to   { opacity:1; transform:translateY(0); }
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

  .room-card {
    transition: transform .15s ease, box-shadow .15s ease;
    cursor: default;
  }
  .room-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,0,0,.08);
  }
  .room-card.clickable { cursor: pointer; }

  .filter-btn {
    transition: all .15s ease;
  }
  .filter-btn.active-all    { background:#0f172a; color:#fff; border-color:#0f172a; }
  .filter-btn.active-szabad { background:#16a34a; color:#fff; border-color:#16a34a; }
  .filter-btn.active-foglalt{ background:#dc2626; color:#fff; border-color:#dc2626; }

  .count-badge {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
  }

  .progress-bar {
    transition: width .6s ease;
  }

  input[type=search]::-webkit-search-cancel-button { display:none; }
</style>
</head>

<body class="min-h-dvh bg-slate-50">

<!-- ─── FEJLÉC ─────────────────────────────────────────── -->
<header class="bg-white border-b border-slate-100 sticky top-0 z-30 header-in">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">

    <div class="flex items-center gap-3">
      <a href="/" class="font-display font-extrabold text-xl text-slate-900 tracking-tight hover:opacity-70 transition-opacity">Ticky</a>
      <span class="text-slate-200 text-lg">·</span>
      <span class="text-sm font-medium text-slate-500">Összes terem</span>
    </div>

    <div class="flex items-center gap-3">
      <!-- Keresés -->
      <div class="relative hidden sm:block">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input id="search-input" type="search" placeholder="Terem keresése…"
          class="pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:bg-white w-44 transition-all"
          oninput="filterRooms()">
      </div>

      <!-- Refresh -->
      <button onclick="refresh()"
        class="flex items-center gap-1.5 text-xs text-slate-400 border border-slate-200 rounded-xl px-3 py-2 hover:bg-slate-50 transition-all">
        <svg id="refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
        <span id="footer-ido">–</span>
      </button>
    </div>
  </div>
</header>

<!-- ─── STATS + FILTER BAR ─────────────────────────────── -->
<div class="max-w-5xl mx-auto px-4 sm:px-6 pt-6 pb-4">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">

    <!-- Stat pill-ok -->
    <div class="flex items-center gap-2 flex-wrap">
      <button onclick="setFilter('mind')" id="btn-mind"
        class="filter-btn active-all flex items-center gap-2 px-4 py-2 rounded-xl border text-sm font-medium transition-all">
        <span>Összes</span>
        <span id="cnt-mind" class="count-badge text-xs">–</span>
      </button>
      <button onclick="setFilter('szabad')" id="btn-szabad"
        class="filter-btn flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-600 text-sm font-medium">
        <div class="w-2 h-2 rounded-full bg-green-500"></div>
        <span>Szabad</span>
        <span id="cnt-szabad" class="count-badge text-xs">–</span>
      </button>
      <button onclick="setFilter('foglalt')" id="btn-foglalt"
        class="filter-btn flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-600 text-sm font-medium">
        <div class="w-2 h-2 rounded-full bg-red-500"></div>
        <span>Foglalt</span>
        <span id="cnt-foglalt" class="count-badge text-xs">–</span>
      </button>
    </div>

    <!-- Mobile keresés -->
    <div class="relative sm:hidden">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input id="search-input-mobile" type="search" placeholder="Terem keresése…"
        class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
        oninput="filterRooms()">
    </div>

  </div>
</div>

<!-- ─── GRID ────────────────────────────────────────────── -->
<main class="max-w-5xl mx-auto px-4 sm:px-6 pb-16">

  <!-- Skeleton loader -->
  <div id="skeleton-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div>
  </div>

  <!-- Tényleges grid -->
  <div id="rooms-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>

  <!-- Üres állapot -->
  <div id="empty-state" class="hidden text-center py-20">
    <span class="text-5xl block mb-3">🔍</span>
    <p class="font-semibold text-slate-700">Nincs találat</p>
    <p class="text-sm text-slate-400 mt-1">Próbálj más szót</p>
  </div>

  <!-- Hiba állapot -->
  <div id="error-state" class="hidden text-center py-20">
    <span class="text-5xl block mb-3">⚠️</span>
    <p class="font-semibold text-slate-700">Hiba történt</p>
    <p id="error-msg" class="text-sm text-slate-400 mt-1"></p>
    <button onclick="refresh()" class="mt-4 text-sm text-slate-500 border border-slate-200 rounded-xl px-4 py-2 hover:bg-slate-50">
      Újrapróbálás
    </button>
  </div>

</main>

<!-- ─── DETAIL MODAL ─────────────────────────────────────── -->
<div id="modal-overlay" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
  onclick="closeModal(event)">
  <div id="modal-box" class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden"
    style="animation: modalIn .3s cubic-bezier(.22,1,.36,1)">
    <div id="modal-content"></div>
  </div>
</div>

<style>
  @keyframes modalIn {
    from { opacity:0; transform:translateY(32px) scale(.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
  }
</style>

<script>
const API_BASE   = 'https://ticky-6r32.onrender.com'
const REFRESH_MS = 60_000

let allRooms  = []
let curFilter = 'mind'
let curSearch = ''

// ─── Fetch ──────────────────────────────────────────────
async function fetchRooms() {
  try {
    const res  = await fetch(`${API_BASE}/api/termek?allapot=1`)
    const data = await res.json()
    if (data.error) { showError(data.error); return }
    allRooms = data.termek || []
    updateCounts()
    renderGrid()
    document.getElementById('footer-ido').textContent =
      new Date().toLocaleTimeString('hu-HU', { hour:'2-digit', minute:'2-digit' })
  } catch(e) {
    showError('Nem sikerült csatlakozni')
  }
}

// ─── Counts ─────────────────────────────────────────────
function updateCounts() {
  const szabad  = allRooms.filter(r => r.allapot === 'szabad').length
  const foglalt = allRooms.filter(r => r.allapot === 'foglalt').length
  document.getElementById('cnt-mind').textContent    = allRooms.length
  document.getElementById('cnt-szabad').textContent  = szabad
  document.getElementById('cnt-foglalt').textContent = foglalt
}

// ─── Filter ─────────────────────────────────────────────
function setFilter(f) {
  curFilter = f
  ;['mind','szabad','foglalt'].forEach(k => {
    const btn = document.getElementById('btn-' + k)
    btn.className = btn.className
      .replace(/active-\w+/g, '')
      .replace(/bg-\w+-\d+\s?/g, '')
      .replace(/text-white\s?/g, '')
      .replace(/border-\w+-\d+\s?/g, '')
      .trim()
    btn.classList.add('filter-btn')
  })
  const activeMap = { mind:'active-all', szabad:'active-szabad', foglalt:'active-foglalt' }
  document.getElementById('btn-' + f).classList.add(activeMap[f])
  renderGrid()
}

function filterRooms() {
  const s1 = document.getElementById('search-input')?.value || ''
  const s2 = document.getElementById('search-input-mobile')?.value || ''
  curSearch = (s1 || s2).toLowerCase()
  renderGrid()
}

// ─── Render ─────────────────────────────────────────────
function calcPct(kezdes, vegzes) {
  const now = new Date()
  const [kh,km] = kezdes.split(':').map(Number)
  const [vh,vm] = vegzes.split(':').map(Number)
  const cur = now.getHours()*60+now.getMinutes()
  return Math.min(100, Math.max(0, Math.round(((cur-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))
}

function renderGrid() {
  const grid = document.getElementById('rooms-grid')
  const empty = document.getElementById('empty-state')
  const skel  = document.getElementById('skeleton-grid')

  skel.classList.add('hidden')

  let rooms = allRooms
  if (curFilter !== 'mind') rooms = rooms.filter(r => r.allapot === curFilter)
  if (curSearch) rooms = rooms.filter(r => r.terem_szam.toLowerCase().includes(curSearch))

  if (rooms.length === 0) {
    grid.classList.add('hidden')
    empty.classList.remove('hidden')
    return
  }

  empty.classList.add('hidden')
  grid.classList.remove('hidden')

  grid.innerHTML = rooms.map((r, i) => {
    const szabad   = r.allapot === 'szabad'
    const bg       = szabad ? 'bg-white' : 'bg-white'
    const dotColor = szabad ? 'bg-green-500' : 'bg-red-500'
    const pillBg   = szabad ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'
    const pillTxt  = szabad ? 'SZABAD' : 'FOGLALT'
    const numColor = szabad ? 'text-slate-900' : 'text-slate-900'
    const borderCl = szabad ? 'border-slate-100' : 'border-slate-100'

    let bodyHtml = ''
    if (szabad) {
      bodyHtml = `<p class="text-xs text-slate-400 mt-2 leading-tight">Nincs óra</p>`
    } else {
      const a = r.aktualis
      const pct = calcPct(a.kezdes, a.vegzes)
      bodyHtml = `
        <p class="text-xs font-medium text-slate-700 mt-2 leading-tight truncate">${a.tanar} · ${a.osztaly}</p>
        <p class="text-xs text-slate-400 truncate">${a.tantargy} · ${a.kezdes}–${a.vegzes}</p>
        <div class="mt-2 h-1 bg-slate-100 rounded-full overflow-hidden">
          <div class="h-full bg-red-400 rounded-full progress-bar" style="width:${pct}%"></div>
        </div>`
    }

    return `
      <div class="room-card clickable ${bg} border ${borderCl} rounded-2xl p-4 card-enter"
        style="animation-delay:${i * 30}ms"
        onclick="openModal('${r.terem_szam}')">
        <div class="flex items-start justify-between gap-1">
          <div>
            <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400">Terem</p>
            <h2 class="font-display font-extrabold text-2xl ${numColor} leading-none mt-0.5">${r.terem_szam}</h2>
          </div>
          <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full ${pillBg} text-[10px] font-semibold tracking-wide">
            <div class="w-1.5 h-1.5 rounded-full ${dotColor} pulse-dot"></div>
            ${pillTxt}
          </div>
        </div>
        ${bodyHtml}
      </div>`
  }).join('')
}

// ─── Modal ──────────────────────────────────────────────
async function openModal(teremSzam) {
  const overlay = document.getElementById('modal-overlay')
  const content = document.getElementById('modal-content')

  overlay.classList.remove('hidden')
  content.innerHTML = `
    <div class="px-7 py-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400">Terem</p>
          <h2 class="font-display font-extrabold text-4xl text-slate-900 leading-none">${teremSzam}</h2>
        </div>
        <button onclick="closeModal()" class="text-slate-300 hover:text-slate-600 transition-colors">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="flex flex-col gap-3">
        <div class="skeleton h-5 w-2/5 rounded-lg"></div>
        <div class="skeleton h-8 w-4/5 rounded-lg"></div>
        <div class="skeleton h-4 w-1/2 rounded-lg"></div>
      </div>
    </div>`

  try {
    const res  = await fetch(`${API_BASE}/api/terem/${teremSzam}`)
    const data = await res.json()
    renderModal(data)
  } catch(e) {
    content.innerHTML = `<div class="px-7 py-8 text-center">
      <p class="text-slate-500">Hiba a betöltésnél</p>
    </div>`
  }
}

function renderModal(data) {
  const content = document.getElementById('modal-content')
  const szabad = data.allapot === 'szabad'

  let mainHtml = ''
  if (szabad) {
    mainHtml = `
      <div class="text-center py-4">
        <span class="text-4xl block mb-2">✅</span>
        <p class="font-display font-bold text-xl text-green-600">Szabad terem</p>
        <p class="text-sm text-slate-400 mt-1">Nincs aktív foglalás</p>
      </div>`
  } else {
    const a = data.aktualis
    const pct = calcPct(a.kezdes, a.vegzes)
    mainHtml = `
      <div class="flex flex-col gap-4">
        <div>
          <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400 mb-0.5">Tanár</p>
          <p class="font-display font-bold text-2xl text-slate-900">${a.tanar_nev || a.tanar}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400 mb-0.5">Osztály</p>
            <p class="font-display font-bold text-lg text-slate-800">${a.osztaly}</p>
          </div>
          <div>
            <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400 mb-0.5">Tantárgy</p>
            <p class="font-display font-bold text-lg text-slate-800">${a.tantargy}</p>
          </div>
        </div>
        <div>
          <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-red-400 rounded-full" style="width:${pct}%"></div>
          </div>
          <div class="flex justify-between mt-1.5 text-xs text-slate-400">
            <span>${a.kezdes}</span>
            <span class="text-red-500 font-medium">még ${a.perc_maradt} perc</span>
            <span>${a.vegzes}</span>
          </div>
        </div>
      </div>`
  }

  let kovHtml = ''
  if (data.kovetkezo) {
    const k = data.kovetkezo
    kovHtml = `
      <div class="mt-4 bg-slate-50 rounded-2xl px-4 py-3">
        <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400 mb-1.5">Következő</p>
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <span class="text-sm font-medium text-slate-700">${k.tanar} · ${k.osztaly} · ${k.tantargy}</span>
          <span class="text-xs text-slate-400 whitespace-nowrap">${k.kezdes}–${k.vegzes}</span>
        </div>
      </div>`
  }

  const navUrl = `/terem/${data.terem}`
  content.innerHTML = `
    <div class="px-7 pt-6 pb-2">
      <div class="flex items-center justify-between mb-5">
        <div>
          <p class="text-[10px] font-medium tracking-widest uppercase text-slate-400">Terem</p>
          <h2 class="font-display font-extrabold text-4xl text-slate-900 leading-none">${data.terem}</h2>
        </div>
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full ${szabad ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'} text-xs font-semibold">
            <div class="w-1.5 h-1.5 rounded-full ${szabad ? 'bg-green-500' : 'bg-red-500'} pulse-dot"></div>
            ${szabad ? 'SZABAD' : 'FOGLALT'}
          </div>
          <button onclick="closeModal()" class="text-slate-300 hover:text-slate-600 transition-colors ml-1">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      ${mainHtml}
      ${kovHtml}
    </div>
    <div class="px-7 py-4 border-t border-slate-100 mt-4 flex items-center justify-between">
      <a href="/terem/${data.terem}" class="text-xs font-medium text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-1">
        Napirend →
      </a>
      <span class="font-display font-extrabold text-sm text-slate-300">Ticky</span>
    </div>`
}

function closeModal(e) {
  if (!e || e.target === document.getElementById('modal-overlay') || e.currentTarget === document.getElementById('modal-overlay') && e.target !== document.getElementById('modal-box')) {
    if (e?.target === document.getElementById('modal-box')) return
    document.getElementById('modal-overlay').classList.add('hidden')
  }
}
document.getElementById('modal-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal(e)
})

// ─── Misc ────────────────────────────────────────────────
function showError(msg) {
  document.getElementById('skeleton-grid').classList.add('hidden')
  document.getElementById('rooms-grid').classList.add('hidden')
  document.getElementById('error-state').classList.remove('hidden')
  document.getElementById('error-msg').textContent = msg
}

function refresh() {
  const icon = document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  fetchRooms().finally(() => setTimeout(() => icon.classList.remove('spinning'), 600))
}

// ─── Init ────────────────────────────────────────────────
fetchRooms()
setInterval(fetchRooms, REFRESH_MS)
</script>
</body>
</html>