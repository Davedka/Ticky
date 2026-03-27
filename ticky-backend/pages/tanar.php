<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Tanár kereső</title>
<link rel="icon" type="image/png" href="/favicon.png?v=20260327c">
<link rel="shortcut icon" href="/favicon.ico?v=20260327c">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  html { scroll-behavior:smooth; }
  body {
    font-family:'DM Sans',sans-serif; background-color:#060f1e; min-height:100vh;
    overscroll-behavior:none; transition:background-image .5s ease;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%);
  }
  body.tant   { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(200,16,46,.35) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%); }
  body.szabad { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,138,74,.35) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(26,74,138,.20) 0%, transparent 55%); }
  body::before { content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px; }
  .top-line { position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200; }
  .glass { background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .slide-up { animation:su .5s cubic-bezier(.22,1,.36,1) both; }
  @keyframes su { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:none} }
  .skel { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px; }
  @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }

  .custom-select {
    width:100%;padding:12px 40px 12px 16px;border-radius:10px;
    border:1.5px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);
    color:white;font-family:'DM Sans',sans-serif;font-size:15px;
    appearance:none;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;
    transition:border-color .2s,box-shadow .2s;
  }
  .custom-select:focus { outline:none;border-color:rgba(200,151,42,.5);box-shadow:0 0 0 4px rgba(200,151,42,.10); }
  .custom-select option { background:#0b2e59;color:white; }

  /* Óra sorok */
  .ora-row { transition:background .15s ease;border-radius:10px; }
  .ora-row:hover { background:rgba(255,255,255,.05); }
  .ora-row.aktiv { background:rgba(200,16,46,.12);border-left:3px solid #e8334a;border-radius:0 10px 10px 0; }
  .ora-row.mult { opacity:.38; }

  /* Csoportbontásos badge */
  .csoport-badge {
    display:inline-flex;align-items:center;gap:3px;
    padding:2px 6px;border-radius:5px;font-size:10px;font-weight:600;
    letter-spacing:.04em;text-transform:uppercase;
    background:rgba(200,151,42,.15);color:rgba(200,151,42,.85);
    border:1px solid rgba(200,151,42,.22);flex-shrink:0;
  }

  /* Aktuális kártya animáció */
  .aktiv-card { animation:cardIn .35s cubic-bezier(.22,1,.36,1) both; }
  @keyframes cardIn { from{opacity:0;transform:scale(.97) translateY(8px)} to{opacity:1;transform:none} }

  /* Napirend lista animáció */
  .lista-in { animation:listaIn .3s cubic-bezier(.22,1,.36,1) both; }
  @keyframes listaIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }

  a { text-decoration:none; }
</style>
</head>
<body class="flex flex-col items-center justify-start p-4 pb-16">
<div class="top-line"></div>

<div class="w-full max-w-sm slide-up relative z-10 mt-6">
  <div class="glass rounded-2xl overflow-hidden">

    <!-- Fejléc -->
    <div class="px-6 pt-6 pb-5" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Tanár kereső</p>
        <div id="status-pill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" id="sd" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
          <span id="st">–</span>
        </div>
      </div>
      <select id="sel" class="custom-select" onchange="onSelect()">
        <option value="">— Válassz tanárt —</option>
      </select>
    </div>

    <!-- Aktuális blokk -->
    <div class="px-6 py-5" id="aktblock" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="text-center py-3">
        <span style="font-size:36px;" class="block mb-2">👆</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p>
      </div>
    </div>

    <!-- Napirend -->
    <div class="px-6 py-5">
      <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color:rgba(255,255,255,.28);">Mai napirend</p>
      <div id="lista"><p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p></div>
    </div>

    <!-- Footer -->
    <div class="px-6 py-4 flex items-center justify-between gap-2" style="border-top:1px solid rgba(255,255,255,.08);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:14px;font-weight:700;">Ticky</a>
      <span class="text-xs" style="color:rgba(255,255,255,.28);" id="ido">–</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const { formatHM, nowMinutes } = window.TickyTime
const REFRESH = 60_000
let curKod = null

// ── URL ──────────────────────────────────────────────
function getUrlKod() {
  const p = location.pathname.split('/').filter(Boolean)
  const q = new URLSearchParams(location.search).get('tanar')
  if (p[0] === 'tanar' && p[1]) return decodeURIComponent(p[1]).toUpperCase()
  if (q) return decodeURIComponent(q).toUpperCase()
  return null
}

// ── Tanárlista betöltése ─────────────────────────────
async function loadTanarok() {
  try {
    const d = await fetch('/api/tanarok').then(r => r.json())
    const sel = document.getElementById('sel')
    ;(d.tanarok || []).forEach(t => {
      const o = document.createElement('option')
      o.value = t.rovid_nev
      o.textContent = t.nev ? `${t.rovid_nev} – ${t.nev}` : t.rovid_nev
      sel.appendChild(o)
    })
    const url = getUrlKod()
    if (url) { sel.value = url; if (sel.value) { curKod = url; loadData() } }
  } catch(e) {}
}

function onSelect() {
  const v = document.getElementById('sel').value
  if (!v) { curKod = null; reset(); return }
  curKod = v
  history.replaceState(null, '', '/tanar/' + encodeURIComponent(v))
  loadData()
}

function reset() {
  document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:36px;" class="block mb-2">👆</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz tanárt a legördülő menüből</p></div>`
  document.getElementById('lista').innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva tanár</p>`
  setAllapot('idle')
}

// ── Állapot ──────────────────────────────────────────
function setAllapot(a) {
  const pill = document.getElementById('status-pill')
  const dot  = document.getElementById('sd')
  const txt  = document.getElementById('st')
  if (a === 'tant') {
    document.body.className = 'flex flex-col items-center justify-start p-4 pb-16 tant'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;'
    dot.style.background = '#ff6b82'; txt.textContent = 'TANÍT'
  } else if (a === 'szabad') {
    document.body.className = 'flex flex-col items-center justify-start p-4 pb-16 szabad'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;'
    dot.style.background = '#4ade80'; txt.textContent = 'SZABAD'
  } else {
    document.body.className = 'flex flex-col items-center justify-start p-4 pb-16'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;'
    dot.style.background = 'rgba(255,255,255,.3)'; txt.textContent = '–'
  }
}

// ── Idő segédfüggvények ───────────────────────────────
function toMin(t) { const [h,m] = t.split(':').map(Number); return h*60+m }
function nowMin()  { return nowMinutes() }
function isAktiv(k,v) { const c = nowMin(); return c >= toMin(k) && c <= toMin(v) }
function isMult(v)     { return nowMin() > toMin(v) }
function calcPct(k,v)  { return Math.min(100, Math.max(0, Math.round(((nowMin()-toMin(k))/(toMin(v)-toMin(k)))*100))) }

// ── Adatbetöltés ─────────────────────────────────────
// A csoportosítás a PHP API-ban történik, itt csak megjelenítés van.
async function loadData() {
  if (!curKod) return

  document.getElementById('aktblock').innerHTML = `<div class="flex flex-col gap-3"><div class="skel h-4 w-2/5"></div><div class="skel h-8 w-3/5"></div><div class="skel h-4 w-full mt-1"></div></div>`

  try {
    const d = await fetch(`/api/tanar/${encodeURIComponent(curKod)}/orarend`).then(r => r.json())

    if (d.error) {
      document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:32px;" class="block mb-2">⚠️</span><p class="text-sm" style="color:rgba(255,255,255,.4);">${d.error}</p></div>`
      setAllapot('idle'); return
    }

    // Az API már visszaadja a csoportosított orak tömböt
    const orak = d.orak || []
    const akt  = orak.find(o => isAktiv(o.kezdes, o.vegzes))
    const kov  = orak.find(o => !isMult(o.vegzes) && !isAktiv(o.kezdes, o.vegzes))

    setAllapot(akt ? 'tant' : orak.length > 0 ? 'szabad' : 'idle')
    renderAkt(akt, kov)
    renderLista(orak)

    // Teljes név frissítése a listában ha az API visszaadja
    if (d.tanar_nev) {
      const opt = document.querySelector(`#sel option[value="${curKod}"]`)
      if (opt && !opt.textContent.includes('–'))
        opt.textContent = `${curKod} – ${d.tanar_nev}`
    }

  } catch(e) {
    document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:32px;" class="block mb-2">⚠️</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Betöltési hiba</p></div>`
    setAllapot('idle')
  }

  document.getElementById('ido').textContent = formatHM()
}

// ── Aktuális blokk ────────────────────────────────────
// Az `o` objektumban az API már benne van:
//   o.is_csoport  → boolean
//   o.terem       → "110 / 202"  (összesített)
//   o.osztaly     → "11.d, 11.f" (összesített)
//   o.csoportok   → [{terem, osztaly}, ...]

function renderAkt(a, k) {
  const el = document.getElementById('aktblock')

  if (a) {
    const p = calcPct(a.kezdes, a.vegzes)

    // Csoportbontásos: alcsoportok felsorolása kis sorokként
    const csoportSorok = a.is_csoport
      ? `<div class="flex flex-col gap-1.5 mt-2">
          ${a.csoportok.map(c =>
            `<div class="flex items-center gap-2">
              <span style="font-size:11px;font-weight:600;padding:1px 7px;border-radius:4px;
                background:rgba(255,255,255,.08);color:rgba(255,255,255,.55);flex-shrink:0;">
                ${esc(c.terem)}.
              </span>
              <span style="font-size:12px;color:rgba(255,255,255,.5);">${esc(c.osztaly)}</span>
            </div>`
          ).join('')}
         </div>`
      : ''

    el.innerHTML = `
      <div class="flex flex-col gap-4 aktiv-card">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Most itt van</p>
            ${a.is_csoport ? '<span class="csoport-badge">Csoportbontás</span>' : ''}
          </div>
          <p style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;line-height:1.1;">
            ${esc(a.terem)}. terem
          </p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Osztály</p>
            <p style="font-family:'Playfair Display',serif;font-size:${a.is_csoport?'14px':'18px'};font-weight:700;color:white;line-height:1.3;">
              ${esc(a.osztaly)}
            </p>
          </div>
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantárgy</p>
            <p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">
              ${esc(a.tantargy)}
            </p>
          </div>
        </div>
        ${csoportSorok}
        <div>
          <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.08);">
            <div class="h-full rounded-full" style="width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s ease;"></div>
          </div>
          <div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.35);">
            <span>${esc(a.kezdes)}</span>
            <span style="color:#ff6b82;font-weight:600;">${esc(a.vegzes)}-ig</span>
            <span>${esc(a.vegzes)}</span>
          </div>
        </div>
      </div>`

  } else if (k) {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-1 aktiv-card">
        <span style="font-size:28px;">☕</span>
        <div>
          <p class="font-semibold" style="color:rgba(255,255,255,.8);">Jelenleg szabad</p>
          <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">
            Következő: <strong style="color:rgba(255,255,255,.7);">${esc(k.terem)}. terem</strong>
            · ${esc(k.kezdes)}–${esc(k.vegzes)}
            ${k.is_csoport ? '&nbsp;<span class="csoport-badge">Csoportbontás</span>' : ''}
          </p>
        </div>
      </div>`
  } else {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-1 aktiv-card">
        <span style="font-size:28px;">✅</span>
        <div>
          <p class="font-semibold" style="color:#4ade80;">Szabad</p>
          <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">Ma már nincs több óra</p>
        </div>
      </div>`
  }
}

// ── Napirend lista ────────────────────────────────────
function renderLista(orak) {
  const el = document.getElementById('lista')

  if (!orak.length) {
    el.innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.35);">Nincs mai óra</p>`
    return
  }

  el.innerHTML = `<div class="lista-in">` + orak.map((o, i) => {
    const ak = isAktiv(o.kezdes, o.vegzes)
    const mu = isMult(o.vegzes)

    // Csoportbontásos sor: kis alcsoport sorok a fő sor alatt
    const csoportReszlet = o.is_csoport
      ? `<div class="flex flex-col gap-0.5 mt-1">
          ${o.csoportok.map(c =>
            `<div class="flex items-center gap-1.5">
              <span style="font-size:10px;font-weight:600;padding:0 5px;border-radius:3px;
                background:rgba(255,255,255,.07);color:rgba(255,255,255,.4);flex-shrink:0;">
                ${esc(c.terem)}.
              </span>
              <span style="font-size:11px;color:rgba(255,255,255,.35);">${esc(c.osztaly)}</span>
            </div>`
          ).join('')}
         </div>`
      : ''

    return `
      <div class="ora-row${ak?' aktiv':mu?' mult':''} flex items-center gap-3 px-3 py-2.5 -mx-1">
        <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:17px;
          color:${mu?'rgba(255,255,255,.2)':'rgba(255,255,255,.85)'};
          width:22px;text-align:right;flex-shrink:0;">
          ${o.ora_sorszam || (i+1)}
        </span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-sm font-medium" style="color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">
              ${esc(o.terem)}. terem
            </span>
            ${o.is_csoport ? '<span class="csoport-badge">Csoportbontás</span>' : ''}
            <span class="text-xs" style="color:rgba(255,255,255,.35);">
              ${esc(o.osztaly)} · ${esc(o.tantargy)}
            </span>
          </div>
          ${csoportReszlet}
          <p class="text-xs" style="color:rgba(255,255,255,.28);">${esc(o.kezdes)} – ${esc(o.vegzes)}</p>
        </div>
        ${ak ? `<span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#ff6b82;display:inline-block;"></span>` : ''}
      </div>`
  }).join('') + `</div>`
}

// ── XSS védelem ───────────────────────────────────────
function esc(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;')
}

function refresh() {
  const ic = document.getElementById('ri')
  ic.classList.add('spinning')
  loadData().finally(() => setTimeout(() => ic.classList.remove('spinning'), 600))
}

// ── Init ─────────────────────────────────────────────
loadTanarok()
setInterval(() => { if (curKod) loadData() }, REFRESH)
</script>
</body>
</html>
