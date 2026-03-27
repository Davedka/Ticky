<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – QR Generátor</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  /* ── Screen stílus ── */
  body {
    font-family:'DM Sans',sans-serif;
    background-color:#060f1e;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%);
    min-height:100vh; color:white;
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background-image: linear-gradient(rgba(255,255,255,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.02) 1px, transparent 1px);
    background-size:40px 40px;
  }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  .glass { background:rgba(255,255,255,.05); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.10); }
  .gold-btn { background:linear-gradient(135deg,#c8972a,#a07020); color:white; border:none; border-radius:10px; padding:11px 24px; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600; cursor:pointer; transition:all .2s; width:auto; margin-top:0; }
  .gold-btn:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(200,151,42,.35); }
  .nav-btn { background:rgba(255,255,255,.06); border:1.5px solid rgba(255,255,255,.12); color:rgba(255,255,255,.6); border-radius:10px; padding:9px 18px; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:500; cursor:pointer; transition:all .15s; width:auto; margin-top:0; }
  .nav-btn:hover { background:rgba(255,255,255,.10); color:white; }
  .nav-btn.active { background:rgba(200,151,42,.15); border-color:rgba(200,151,42,.4); color:#f0c76b; }

  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:10px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  .fade-in { animation:fadeIn .4s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }

  /* QR kártya – screen nézet */
  .qr-card {
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.10);
    border-radius:16px; padding:20px;
    display:flex; flex-direction:column; align-items:center; gap:12px;
    transition:transform .15s, border-color .15s;
    cursor:pointer; position:relative;
  }
  .qr-card:hover { transform:translateY(-2px); border-color:rgba(255,255,255,.22); }
  .qr-card.selected { border-color:#c8972a; background:rgba(200,151,42,.08); }
  .qr-card .check { position:absolute; top:10px; right:10px; width:22px; height:22px; border-radius:50%; border:2px solid rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; transition:all .15s; background:transparent; }
  .qr-card.selected .check { background:#c8972a; border-color:#c8972a; }
  .qr-card .check svg { opacity:0; transition:opacity .15s; }
  .qr-card.selected .check svg { opacity:1; }

  /* QR div (fehér háttér kell a QR-hoz) */
  .qr-wrap { background:white; border-radius:10px; padding:8px; display:flex; align-items:center; justify-content:center; }
  .qr-wrap canvas, .qr-wrap img { display:block !important; }

  a { text-decoration:none; }

  /* ── NYOMTATÁS ────────────────────────────── */
  @media print {
    * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    body { background:white !important; background-image:none !important; color:black !important; }
    body::before { display:none; }
    .top-line, nav, .screen-only { display:none !important; }

    .print-grid {
      display:grid !important;
      grid-template-columns: repeat(3, 1fr);
      gap:12mm;
      padding:8mm;
    }

    /* Csak a kijelölt kártyák, vagy összes ha nincs kijelölve */
    .qr-card { display:none !important; }
    .qr-card.print-me {
      display:flex !important;
      flex-direction:column !important;
      align-items:center !important;
      background:white !important;
      border:1.5px solid #ddd !important;
      border-radius:12px !important;
      padding:16px !important;
      gap:10px !important;
      page-break-inside:avoid !important;
      box-shadow:none !important;
    }

    .qr-card.print-me .check { display:none !important; }

    .print-room-num {
      font-family:'Playfair Display',serif !important;
      font-size:28px !important; font-weight:700 !important;
      color:#060f1e !important; line-height:1 !important;
    }
    .print-label {
      font-size:9px !important; font-weight:600 !important;
      letter-spacing:.1em !important; text-transform:uppercase !important;
      color:#888 !important;
    }
    .print-url {
      font-size:9px !important; color:#555 !important;
      text-align:center !important; word-break:break-all !important;
    }
    .print-ticky {
      font-family:'Playfair Display',serif !important;
      font-size:11px !important; color:#aaa !important; font-weight:600 !important;
    }
    .qr-wrap { padding:6px !important; border-radius:8px !important; border:1px solid #eee !important; }
  }
</style>
<?= ticky_head_assets('Ticky - QR generator', 'Nyomtathato QR kodok a ticky teremoldalakhoz.') ?>
</head>
<body class="tky-page">
<div class="top-line"></div>

<!-- Navbar -->
<nav class="tky-public-nav sticky top-0 z-50 px-5 h-16 flex items-center justify-between screen-only" style="background:rgba(6,15,30,.75);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);">
  <div class="flex items-center gap-3">
    <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;" class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;animation:pulseDot 2s infinite;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span class="text-sm" style="color:rgba(255,255,255,.45);">QR Generátor</span>
  </div>
  <div class="flex items-center gap-2">
    <a href="/termek" class="nav-btn">← Termek</a>
  </div>
</nav>

<section class="tky-section-card screen-only">
  <div class="tky-section-copy">
    <span class="tky-eyebrow">Nyomtathato kodok</span>
    <h1 class="tky-title">QR lapok teremajtokra</h1>
    <p class="tky-copy">Jelold ki csak azokat a termeket, amelyek kellenek, vagy nyomtasd ki az egesz csomagot egy lepesben.</p>
  </div>
  <div class="tky-chip-row">
    <div class="tky-chip">
      <span class="tky-chip-label">Termek</span>
      <strong id="hero-room-count">-</strong>
    </div>
    <div class="tky-chip tky-chip-note">
      <span class="tky-chip-label">Workflow</span>
      <strong>Kijelol, nyomtat, kitesz</strong>
    </div>
  </div>
</section>

<!-- Fejléc + akciók -->
<div class="relative z-10 max-w-5xl mx-auto px-5 pt-7 pb-4 screen-only">
  <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
      <h1 style="font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:white;letter-spacing:-.5px;">QR Kódok</h1>
      <p class="text-sm mt-1" style="color:rgba(255,255,255,.4);">Kattints egy teremre a kijelöléshez, aztán nyomtasd ki</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <button class="nav-btn" id="btn-select-all" onclick="selectAll()">Összes kijelölése</button>
      <button class="nav-btn" id="btn-clear" onclick="clearSelection()" style="display:none;">Kijelölés törlése</button>
      <button class="gold-btn" onclick="printSelected()" id="btn-print">
        <span id="print-label">🖨️ Összes nyomtatása</span>
      </button>
    </div>
  </div>

  <!-- Kijelölt szám -->
  <div id="selection-info" class="mt-3 text-sm" style="color:rgba(255,255,255,.4);display:none;">
    <span id="selected-count">0</span> terem kijelölve
  </div>
</div>

<!-- QR Grid -->
<main class="relative z-10 max-w-5xl mx-auto px-5 pb-16 print-grid" id="qr-grid">
  <!-- skeleton -->
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
  <div class="skeleton h-48 rounded-2xl screen-only"></div>
</main>

<style>
@keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
</style>

<script>
const BASE_URL = window.location.origin  // pl. https://ticky-6r32.onrender.com
let selectedRooms = new Set()
let allRooms = []

// ─── Betöltés ────────────────────────────────────────
async function loadRooms() {
  try {
    const data = await fetch('/api/termek').then(r => r.json())
    allRooms = (data.termek || []).map(t => t.terem_szam)
    const heroCount = document.getElementById('hero-room-count')
    if (heroCount) heroCount.textContent = allRooms.length
    renderCards()
  } catch(e) {
    document.getElementById('qr-grid').innerHTML = `
      <div class="col-span-full text-center py-16 screen-only">
        <span class="text-4xl block mb-3">⚠️</span>
        <p style="color:rgba(255,255,255,.5);">Nem sikerült betölteni a termeket</p>
      </div>`
  }
}

// ─── Kártyák renderelése ─────────────────────────────
function renderCards() {
  const grid = document.getElementById('qr-grid')

  // Skeleton eltávolítása
  grid.innerHTML = ''

  allRooms.forEach((szam, i) => {
    const url = `${BASE_URL}/terem/${szam}`

    const card = document.createElement('div')
    card.className = 'qr-card fade-in'
    card.id = `card-${szam}`
    card.style.animationDelay = `${i * 40}ms`
    card.onclick = () => toggleSelect(szam)

    card.innerHTML = `
      <div class="check">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
      </div>
      <div>
        <p class="print-label text-xs font-semibold tracking-widest uppercase text-center" style="color:rgba(255,255,255,.35);">Terem</p>
        <p class="print-room-num" style="font-family:'Playfair Display',serif;font-size:30px;font-weight:700;color:white;line-height:1;text-align:center;">${szam}</p>
      </div>
      <div class="qr-wrap" id="qr-${szam}"></div>
      <p class="print-url text-xs text-center" style="color:rgba(255,255,255,.3);word-break:break-all;max-width:140px;">${url}</p>
      <p class="print-ticky" style="font-family:'Playfair Display',serif;font-size:11px;color:rgba(255,255,255,.2);font-weight:600;">Ticky</p>
    `

    grid.appendChild(card)

    // QR generálás
    new QRCode(document.getElementById(`qr-${szam}`), {
      text:          url,
      width:         128,
      height:        128,
      colorDark:     '#060f1e',
      colorLight:    '#ffffff',
      correctLevel:  QRCode.CorrectLevel.M,
    })
  })

  updateUI()
}

// ─── Kijelölés ───────────────────────────────────────
function toggleSelect(szam) {
  if (selectedRooms.has(szam)) {
    selectedRooms.delete(szam)
    document.getElementById(`card-${szam}`).classList.remove('selected')
  } else {
    selectedRooms.add(szam)
    document.getElementById(`card-${szam}`).classList.add('selected')
  }
  updateUI()
}

function selectAll() {
  allRooms.forEach(szam => {
    selectedRooms.add(szam)
    document.getElementById(`card-${szam}`)?.classList.add('selected')
  })
  updateUI()
}

function clearSelection() {
  allRooms.forEach(szam => {
    selectedRooms.delete(szam)
    document.getElementById(`card-${szam}`)?.classList.remove('selected')
  })
  updateUI()
}

function updateUI() {
  const n = selectedRooms.size
  const total = allRooms.length

  const info  = document.getElementById('selection-info')
  const cnt   = document.getElementById('selected-count')
  const label = document.getElementById('print-label')
  const btnClear = document.getElementById('btn-clear')
  const btnSelectAll = document.getElementById('btn-select-all')

  if (n > 0) {
    info.style.display = 'block'
    cnt.textContent = n
    label.textContent = `🖨️ ${n} terem nyomtatása`
    btnClear.style.display = 'inline-flex'
    btnSelectAll.textContent = n === total ? 'Összes kijelölve ✓' : 'Összes kijelölése'
  } else {
    info.style.display = 'none'
    label.textContent = '🖨️ Összes nyomtatása'
    btnClear.style.display = 'none'
    btnSelectAll.textContent = 'Összes kijelölése'
  }
}

// ─── Nyomtatás ───────────────────────────────────────
function printSelected() {
  // Ha nincs kijelölve semmi → nyomtat mindent
  const toPrint = selectedRooms.size > 0 ? selectedRooms : new Set(allRooms)

  // Minden kártyán beállítjuk a print-me osztályt
  allRooms.forEach(szam => {
    const card = document.getElementById(`card-${szam}`)
    if (!card) return
    if (toPrint.has(szam)) {
      card.classList.add('print-me')
    } else {
      card.classList.remove('print-me')
    }
  })

  // Print párbeszédablak
  window.print()

  // print-me osztályok eltávolítása (cleanup)
  allRooms.forEach(szam => {
    document.getElementById(`card-${szam}`)?.classList.remove('print-me')
  })
}

// ─── Init ────────────────────────────────────────────
loadRooms()
</script>
</body>
</html>
