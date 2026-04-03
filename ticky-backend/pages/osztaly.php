<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky - Osztaly kereso</title>
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
  body.oraban { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(200,16,46,.34) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.15) 0%, transparent 55%); }
  body.szunet { background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,138,74,.32) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(26,74,138,.20) 0%, transparent 55%); }
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
  .ora-row { transition:background .15s ease;border-radius:10px; }
  .ora-row:hover { background:rgba(255,255,255,.05); }
  .ora-row.aktiv { background:rgba(200,16,46,.12);border-left:3px solid #e8334a;border-radius:0 10px 10px 0; }
  .ora-row.mult { opacity:.38; }
  .csoport-badge {
    display:inline-flex;align-items:center;gap:3px;
    padding:2px 6px;border-radius:5px;font-size:10px;font-weight:600;
    letter-spacing:.04em;text-transform:uppercase;
    background:rgba(200,151,42,.15);color:rgba(200,151,42,.85);
    border:1px solid rgba(200,151,42,.22);flex-shrink:0;
  }
  .aktiv-card { animation:cardIn .35s cubic-bezier(.22,1,.36,1) both; }
  @keyframes cardIn { from{opacity:0;transform:scale(.97) translateY(8px)} to{opacity:1;transform:none} }
  .lista-in { animation:listaIn .3s cubic-bezier(.22,1,.36,1) both; }
  @keyframes listaIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
  a { text-decoration:none; }
  @media (max-width: 640px) {
    body::before { background-size:28px 28px; }
    .osztaly-shell { margin-top:16px; }
    .custom-select { font-size:16px; padding:13px 40px 13px 14px; }
  }
</style>
</head>
<body class="flex flex-col items-center justify-start px-4 pt-4 sm:pt-6 pb-24">
<div class="top-line"></div>

<div class="osztaly-shell w-full max-w-md slide-up relative z-10 mt-6">
  <div class="glass rounded-2xl overflow-hidden">
    <div class="px-6 pt-6 pb-5" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-3">
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Osztaly nezet</p>
        <div id="status-pill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" id="sd" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
          <span id="st">-</span>
        </div>
      </div>
      <select id="sel" class="custom-select" onchange="onSelect()">
        <option value="">- Valassz osztalyt -</option>
      </select>
    </div>

    <div class="px-6 py-5" id="aktblock" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="text-center py-3">
        <span style="font-size:36px;" class="block mb-2">#</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Valassz osztalyt a legordulo menubol</p>
      </div>
    </div>

    <div class="px-6 py-5">
      <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color:rgba(255,255,255,.28);">Mai napirend</p>
      <div id="lista"><p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kivalasztva osztaly</p></div>
    </div>

    <div class="px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3" style="border-top:1px solid rgba(255,255,255,.08);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:14px;font-weight:700;">Ticky</a>
      <span class="text-xs" style="color:rgba(255,255,255,.28);" id="ido">-</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissit
      </button>
    </div>
  </div>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const { formatHM, nowMinutes } = window.TickyTime
const REFRESH = 60_000
let curClass = null

function getUrlClass() {
  const p = location.pathname.split('/').filter(Boolean)
  const q = new URLSearchParams(location.search).get('osztaly')
  if (p[0] === 'osztaly' && p[1]) return decodeURIComponent(p[1])
  if (q) return decodeURIComponent(q)
  return null
}

function esc(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;')
}

function cssEsc(value) {
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(String(value ?? ''))
  }
  return String(value ?? '').replace(/["\\#.:,[\]()]/g, '\\$&')
}

function roomLabel(room) {
  const value = String(room ?? '').trim()
  return value.includes('/') ? `${esc(value)}. termek` : `${esc(value)}. terem`
}

function getTeacherLabel(data) {
  const full = String(data.tanar_nev ?? '').trim()
  const short = String(data.tanar ?? '').trim()
  return full || short || '?'
}

async function loadOsztalyok() {
  try {
    const d = await fetch('/api/osztalyok').then(r => r.json())
    const sel = document.getElementById('sel')
    ;(d.osztalyok || []).forEach(code => {
      const o = document.createElement('option')
      o.value = code
      o.textContent = code
      sel.appendChild(o)
    })
    const url = getUrlClass()
    if (url) {
      if (![...sel.options].some(option => option.value === url)) {
        const extra = document.createElement('option')
        extra.value = url
        extra.textContent = url
        sel.appendChild(extra)
      }
      sel.value = url
      if (sel.value) {
        curClass = url
        loadData()
      }
    }
  } catch (e) {}
}

function onSelect() {
  const v = document.getElementById('sel').value
  if (!v) { curClass = null; reset(); return }
  curClass = v
  history.replaceState(null, '', '/osztaly/' + encodeURIComponent(v))
  document.title = 'Ticky - ' + v
  loadData()
}

function reset() {
  document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:36px;" class="block mb-2">#</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Valassz osztalyt a legordulo menubol</p></div>`
  document.getElementById('lista').innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kivalasztva osztaly</p>`
  setAllapot('idle')
}

function setAllapot(state) {
  const pill = document.getElementById('status-pill')
  const dot = document.getElementById('sd')
  const txt = document.getElementById('st')
  if (state === 'oraban') {
    document.body.className = 'flex flex-col items-center justify-start px-4 pt-4 sm:pt-6 pb-24 oraban'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;'
    dot.style.background = '#ff6b82'; txt.textContent = 'ORAN VAN'
  } else if (state === 'szunet') {
    document.body.className = 'flex flex-col items-center justify-start px-4 pt-4 sm:pt-6 pb-24 szunet'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;'
    dot.style.background = '#4ade80'; txt.textContent = 'SZUNET'
  } else {
    document.body.className = 'flex flex-col items-center justify-start px-4 pt-4 sm:pt-6 pb-24'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;'
    dot.style.background = 'rgba(255,255,255,.3)'; txt.textContent = '-'
  }
}

function toMin(t) { const [h,m] = t.split(':').map(Number); return h * 60 + m }
function nowMin() { return nowMinutes() }
function isAktiv(k, v) { const c = nowMin(); return c >= toMin(k) && c <= toMin(v) }
function isMult(v) { return nowMin() > toMin(v) }
function calcPct(k, v) { return Math.min(100, Math.max(0, Math.round(((nowMin() - toMin(k)) / (toMin(v) - toMin(k))) * 100))) }

async function loadData() {
  if (!curClass) return
  document.title = 'Ticky - ' + curClass

  document.getElementById('aktblock').innerHTML = `<div class="flex flex-col gap-3"><div class="skel h-4 w-2/5"></div><div class="skel h-8 w-3/5"></div><div class="skel h-4 w-full mt-1"></div></div>`

  try {
    const d = await fetch(`/api/osztaly/${encodeURIComponent(curClass)}/orarend`).then(r => r.json())

    if (d.error) {
      document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:32px;" class="block mb-2">!</span><p class="text-sm" style="color:rgba(255,255,255,.4);">${esc(d.error)}</p></div>`
      setAllapot('idle')
      return
    }

    const lessons = d.orak || []
    const current = lessons.find(item => isAktiv(item.kezdes, item.vegzes))
    const next = lessons.find(item => !isMult(item.vegzes) && !isAktiv(item.kezdes, item.vegzes))

    setAllapot(current ? 'oraban' : lessons.length > 0 ? 'szunet' : 'idle')
    renderAkt(current, next, lessons)
    renderLista(lessons)

    if (d.osztaly) {
      const opt = document.querySelector(`#sel option[value="${cssEsc(d.osztaly)}"]`)
      if (!opt) {
        const sel = document.getElementById('sel')
        const option = document.createElement('option')
        option.value = d.osztaly
        option.textContent = d.osztaly
        sel.appendChild(option)
      }
    }
  } catch (e) {
    document.getElementById('aktblock').innerHTML = `<div class="text-center py-3"><span style="font-size:32px;" class="block mb-2">!</span><p class="text-sm" style="color:rgba(255,255,255,.4);">Betoltesi hiba</p></div>`
    setAllapot('idle')
  }

  document.getElementById('ido').textContent = formatHM()
}

function renderCsoportok(groups) {
  if (!Array.isArray(groups) || groups.length <= 1) return ''

  return `<div class="flex flex-col gap-1.5 mt-2">
    ${groups.map(group => `
      <div class="flex items-center gap-2 flex-wrap">
        <span style="font-size:11px;font-weight:600;padding:1px 7px;border-radius:4px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.55);flex-shrink:0;">${roomLabel(group.terem)}</span>
        <span style="font-size:12px;color:rgba(255,255,255,.5);">${esc(getTeacherLabel(group))}</span>
        <span style="font-size:12px;color:rgba(255,255,255,.35);">${esc(group.tantargy)}</span>
      </div>
    `).join('')}
  </div>`
}

function renderAkt(current, next, lessons) {
  const el = document.getElementById('aktblock')

  if (current) {
    const p = calcPct(current.kezdes, current.vegzes)
    el.innerHTML = `
      <div class="flex flex-col gap-4 aktiv-card">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Most itt van</p>
            ${current.is_csoport ? '<span class="csoport-badge">Parhuzamos</span>' : ''}
          </div>
          <p style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;line-height:1.1;">${roomLabel(current.terem)}</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tanar</p>
            <p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;line-height:1.3;">${esc(current.tanar_nev || current.tanar)}</p>
          </div>
          <div>
            <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantargy</p>
            <p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${esc(current.tantargy)}</p>
          </div>
        </div>
        ${renderCsoportok(current.csoportok)}
        <div>
          <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.08);">
            <div class="h-full rounded-full" style="width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s ease;"></div>
          </div>
          <div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.35);">
            <span>${esc(current.kezdes)}</span>
            <span style="color:#ff6b82;font-weight:600;">${esc(current.vegzes)}-ig</span>
            <span>${esc(current.vegzes)}</span>
          </div>
        </div>
      </div>`
    return
  }

  if (next) {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-1 aktiv-card">
        <span style="font-size:28px;">#</span>
        <div>
          <p class="font-semibold" style="color:rgba(255,255,255,.8);">Jelenleg nincs oran</p>
          <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">
            Kovetkezo: <strong style="color:rgba(255,255,255,.7);">${roomLabel(next.terem)}</strong>
            · ${esc(next.kezdes)}-${esc(next.vegzes)}
            ${next.is_csoport ? '&nbsp;<span class="csoport-badge">Parhuzamos</span>' : ''}
          </p>
        </div>
      </div>`
    return
  }

  el.innerHTML = `
    <div class="flex items-center gap-4 py-1 aktiv-card">
      <span style="font-size:28px;">OK</span>
      <div>
        <p class="font-semibold" style="color:#4ade80;">Vege a napnak</p>
        <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">${lessons.length ? 'Ma mar nincs tobb ora' : 'Ma nincs ora beirva'}</p>
      </div>
    </div>`
}

function renderLista(lessons) {
  const el = document.getElementById('lista')

  if (!lessons.length) {
    el.innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.35);">Nincs mai ora</p>`
    return
  }

  el.innerHTML = `<div class="lista-in">` + lessons.map((lesson, i) => {
    const active = isAktiv(lesson.kezdes, lesson.vegzes)
    const past = isMult(lesson.vegzes)
    const groups = Array.isArray(lesson.csoportok) && lesson.csoportok.length > 1
      ? `<div class="flex flex-col gap-0.5 mt-1">
          ${lesson.csoportok.map(group => `
            <div class="flex items-center gap-1.5 flex-wrap">
              <span style="font-size:10px;font-weight:600;padding:0 5px;border-radius:3px;background:rgba(255,255,255,.07);color:rgba(255,255,255,.4);flex-shrink:0;">${roomLabel(group.terem)}</span>
              <span style="font-size:11px;color:rgba(255,255,255,.35);">${esc(getTeacherLabel(group))} · ${esc(group.tantargy)}</span>
            </div>
          `).join('')}
        </div>`
      : ''

    return `
      <div class="ora-row${active ? ' aktiv' : past ? ' mult' : ''} flex items-center gap-3 px-3 py-2.5 -mx-1">
        <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:17px;color:${past ? 'rgba(255,255,255,.2)' : 'rgba(255,255,255,.85)'};width:22px;text-align:right;flex-shrink:0;">${lesson.ora_sorszam || (i + 1)}</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-sm font-medium" style="color:${past ? 'rgba(255,255,255,.3)' : 'rgba(255,255,255,.8)'};">${roomLabel(lesson.terem)}</span>
            ${lesson.is_csoport ? '<span class="csoport-badge">Parhuzamos</span>' : ''}
            <span class="text-xs" style="color:rgba(255,255,255,.35);">${esc(lesson.tanar_nev || lesson.tanar)} · ${esc(lesson.tantargy)}</span>
          </div>
          ${groups}
          <p class="text-xs" style="color:rgba(255,255,255,.28);">${esc(lesson.kezdes)} - ${esc(lesson.vegzes)}</p>
        </div>
        ${active ? `<span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#ff6b82;display:inline-block;"></span>` : ''}
      </div>`
  }).join('') + `</div>`
}

function refresh() {
  const ic = document.getElementById('ri')
  ic.classList.add('spinning')
  loadData().finally(() => setTimeout(() => ic.classList.remove('spinning'), 600))
}

loadOsztalyok()
setInterval(() => { if (curClass) loadData() }, REFRESH)
</script>
</body>
</html>
