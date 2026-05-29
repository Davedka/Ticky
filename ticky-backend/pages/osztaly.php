<?php
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route_match = match_route('/osztaly/{kod}', $uri);
$selected_kod = $route_match ? urldecode($route_match['kod']) : null;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Osztály kereső</title>
<link rel="icon" type="image/png" href="/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;overscroll-behavior:none;transition:background-image .5s ease;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
  body.tanul{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(200,16,46,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
  body.szabad{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,138,74,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(26,74,138,.20) 0%,transparent 55%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200;}
  .glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
  .skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
  @keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .spinning{animation:spin .6s linear;}
  .custom-select{width:100%;padding:12px 40px 12px 16px;border-radius:10px;border:1.5px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:white;font-family:'DM Sans',sans-serif;font-size:15px;appearance:none;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;transition:border-color .2s;}
  .custom-select:focus{outline:none;border-color:rgba(200,151,42,.5);}
  .custom-select option{background:#0b2e59;color:white;}
  .custom-select optgroup{background:#071b3a;color:rgba(255,255,255,.5);font-size:11px;font-weight:700;letter-spacing:.07em;}
  .ora-row{transition:background .15s ease;border-radius:10px;}
  .ora-row:hover{background:rgba(255,255,255,.05);}
  .ora-row.aktiv{background:rgba(200,16,46,.12);border-left:3px solid #e8334a;border-radius:0 10px 10px 0;}
  .ora-row.mult{opacity:.38;}
  .aktiv-card{animation:cardIn .35s cubic-bezier(.22,1,.36,1) both;}
  @keyframes cardIn{from{opacity:0;transform:scale(.97) translateY(8px)}to{opacity:1;transform:none}}
  .lista-in{animation:listaIn .3s cubic-bezier(.22,1,.36,1) both;}
  @keyframes listaIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  a{text-decoration:none;}
  /* Csoport badge */
  .csoport-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.3);color:#f0c76b;letter-spacing:.04em;}
  /* Csoport-bontásos sor – lila/indigo háttér jelzi hogy a két csoport mást csinál */
  .ora-row.bontas{background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.18);}
  .ora-row.bontas:hover{background:rgba(139,92,246,.13);}
  /* Időtartam badge (több órás összevont blokk) */
  .dur-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:6px;font-size:9px;font-weight:700;letter-spacing:.04em;background:rgba(96,165,250,.16);border:1px solid rgba(96,165,250,.3);color:#93c5fd;white-space:nowrap;}
  /* Csoportsorok – fix, sosem törik szét csúnyán (akárhány csoport) */
  .cs-list{display:flex;flex-direction:column;gap:5px;}
  .cs-row{display:flex;align-items:flex-start;gap:6px;min-width:0;}
  .cs-mini{font-size:9px;padding:1px 6px;flex-shrink:0;margin-top:1px;}
  /* Floating sidebar */
  .ticky-sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:150;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;background:rgba(6,15,30,.78);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-left:none;border-radius:0 12px 12px 0;}
  .tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;text-decoration:none;color:rgba(255,255,255,.6);transition:all .18s;}
  .tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
  .tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:rgba(255,255,255,.88);font-size:12px;font-family:'DM Sans',sans-serif;font-weight:500;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
  .tsb-item:hover::after{opacity:1;}
  .tsb-divider{width:20px;height:1px;background:rgba(255,255,255,.10);margin:2px 0;}
  /* Mobile bottom bar */
  .mobile-bottom-bar{display:none;position:fixed;bottom:0;left:0;right:0;z-index:150;background:rgba(6,15,30,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,.08);padding:6px 0 max(6px,env(safe-area-inset-bottom));flex-direction:row;justify-content:space-around;align-items:center;}
  .mbb-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:4px 12px;text-decoration:none;color:rgba(255,255,255,.5);font-size:10px;font-weight:500;}
  .mbb-icon{font-size:19px;line-height:1;}
  .mbb-item.active{color:rgba(200,151,42,.9);}
  @media(max-width:768px){
    .ticky-sidebar{display:none!important;}
    .mobile-bottom-bar{display:flex!important;}
    body{padding-bottom:68px;}
    .card-inner{padding:20px 16px!important;}
  }
</style>
</head>
<body>
<?php ticky_nav('osztaly', $selected_kod ?: 'Osztály kereső'); ?>

<div class="mobile-bottom-bar">
  <a href="/" class="mbb-item"><span class="mbb-icon">🏠</span>Főoldal</a>
  <a href="/termek" class="mbb-item"><span class="mbb-icon">🏫</span>Termek</a>
  <a href="/tanar" class="mbb-item"><span class="mbb-icon">👩‍🏫</span>Tanár</a>
  <a href="/osztaly" class="mbb-item active"><span class="mbb-icon">🎒</span>Osztály</a>

</div>

<div class="relative z-10 flex flex-col items-center px-4 pt-6 sm:pt-8 pb-16">
<div class="w-full max-w-sm slide-up">
  <div class="glass rounded-2xl overflow-hidden">

    <!-- Ticky top -->
    <div class="px-5 pt-4 pb-0 flex items-center justify-between">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:15px;font-weight:700;display:flex;align-items:center;gap:6px;"
         onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.35)'">
        <span style="width:6px;height:6px;border-radius:50%;background:#c8972a;box-shadow:0 0 6px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>
        Ticky
      </a>
      <span class="text-xs" style="color:rgba(255,255,255,.28);">Osztály kereső</span>
    </div>

    <!-- Fejléc + select -->
    <div class="px-5 sm:px-6 pt-4 pb-5 card-inner" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Válassz osztályt</p>
        <div id="status-pill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold flex-shrink-0"
             style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" id="sd" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
          <span id="st">–</span>
        </div>
      </div>
      <select id="sel" class="custom-select" onchange="onSelect()">
        <option value="">— Válassz osztályt —</option>
      </select>
    </div>

    <!-- Aktuális állapot -->
    <div class="px-5 sm:px-6 py-5" id="aktblock" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div class="text-center py-3">
        <span style="font-size:36px;" class="block mb-2">🎒</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz osztályt a legördülő menüből</p>
      </div>
    </div>

    <!-- Mai napirend -->
    <div class="px-5 sm:px-6 py-5">
      <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color:rgba(255,255,255,.28);">Mai napirend</p>
      <div id="lista"><p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva osztály</p></div>
    </div>

    <!-- Footer -->
    <div class="px-5 sm:px-6 py-4 flex items-center justify-between gap-2" style="border-top:1px solid rgba(255,255,255,.08);">
      <span class="text-xs" style="color:rgba(255,255,255,.35);" id="ido">–</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg"
              style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;font-size:12px;">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
        </svg>
        Frissít
      </button>
    </div>

  </div>
</div>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const REFRESH = 60_000
function escHtml(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
let curKod = null

function updateTime() {
  document.getElementById('ido').textContent = window.TickyTime ? window.TickyTime.formatHM() : new Date().toLocaleTimeString('hu-HU', { hour:'2-digit', minute:'2-digit' })
}

function getUrlKod() {
  const p = location.pathname.split('/').filter(Boolean)
  const q = new URLSearchParams(location.search).get('osztaly')
  if (p[0] === 'osztaly' && p[1]) return decodeURIComponent(p[1])
  if (q) return decodeURIComponent(q)
  return null
}

// ─── Osztályok betöltése ──────────────────────────────────────────────
async function loadOsztalyok() {
  try {
    const d = await fetch('/api/osztalyok').then(r => r.json())
    const sel = document.getElementById('sel')
    const list = d.osztalyok || []

    // Évfolyamok csoportosítása
    const evfolyamok = {}
    list.forEach(kod => {
      // _du, _ff stb. → Egyéb; különben évfolyam alapján
      const m = kod.match(/^(\d+)\./)
      const hasUnderscore = kod.includes('_')
      const ev = (m && !hasUnderscore) ? m[1] + '. évfolyam' : 'Egyéb'
      if (!evfolyamok[ev]) evfolyamok[ev] = []
      evfolyamok[ev].push(kod)
    })

    Object.keys(evfolyamok).sort((a,b) => {
      const na = parseInt(a), nb = parseInt(b)
      return isNaN(na) || isNaN(nb) ? a.localeCompare(b) : na - nb
    }).forEach(ev => {
      const group = document.createElement('optgroup')
      group.label = ev
      evfolyamok[ev].forEach(kod => {
        const o = document.createElement('option')
        o.value = kod
        o.textContent = kod
        group.appendChild(o)
      })
      sel.appendChild(group)
    })

    // URL alapú előválasztás
    const url = getUrlKod()
    if (url) {
      // Keresés optionok között (case-insensitive)
      const opts = sel.querySelectorAll('option')
      for (const o of opts) {
        if (o.value.toLowerCase() === url.toLowerCase()) {
          sel.value = o.value
          curKod = o.value
          loadData()
          break
        }
      }
    }
  } catch(e) {}
}

function onSelect() {
  const v = document.getElementById('sel').value
  if (!v) { curKod = null; reset(); return }
  curKod = v
  history.replaceState(null, '', '/osztaly/' + encodeURIComponent(v))
  document.title = 'Ticky – ' + v
  loadData()
}

function reset() {
  document.getElementById('aktblock').innerHTML = `
    <div class="text-center py-3">
      <span style="font-size:36px;" class="block mb-2">🎒</span>
      <p class="text-sm" style="color:rgba(255,255,255,.4);">Válassz osztályt a legördülő menüből</p>
    </div>`
  document.getElementById('lista').innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.3);">Nincs kiválasztva osztály</p>`
  setAllapot('idle')
}

function setAllapot(a) {
  const pill = document.getElementById('status-pill')
  const dot  = document.getElementById('sd')
  const txt  = document.getElementById('st')
  if (a === 'tanul') {
    document.body.className = 'tanul'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;'
    dot.style.background = '#ff6b82'; txt.textContent = 'TANUL'
  } else if (a === 'szabad') {
    document.body.className = 'szabad'
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;'
    dot.style.background = '#4ade80'; txt.textContent = 'SZABAD'
  } else {
    document.body.className = ''
    pill.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;'
    dot.style.background = 'rgba(255,255,255,.3)'; txt.textContent = '–'
  }
}

function toMin(t) { const [h,m] = t.split(':').map(Number); return h*60+m }
function nowMin() { return window.TickyTime ? window.TickyTime.nowMinutes() : (new Date().getHours()*60+new Date().getMinutes()) }
function isAktiv(k,v) { const c=nowMin(); return c>=toMin(k)&&c<=toMin(v) }
function isMult(v)    { return nowMin()>toMin(v) }
function calcPct(k,v) { const c=nowMin(); return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100))) }

// Időtartam-jelölő: hány tanórát fog át a blokk
function oraSzam(o){ return Math.max(1, parseInt(o.ora_szam || 1, 10)) }
function durBadgeHtml(o){
  const n = oraSzam(o)
  return n > 1 ? `<span class="dur-badge">${n} óra</span>` : ''
}
function sorszamLabel(o, fallback){
  const tol = o.ora_sorszam || fallback
  if (oraSzam(o) > 1 && o.ora_sorszam_ig) return `${tol}–${o.ora_sorszam_ig}`
  return `${tol}`
}

// ─── Adatok betöltése ─────────────────────────────────────────────────
async function loadData() {
  if (!curKod) return
  document.getElementById('aktblock').innerHTML = `
    <div class="flex flex-col gap-3">
      <div class="skel h-4 w-2/5"></div>
      <div class="skel h-8 w-3/5"></div>
      <div class="skel h-4 w-full mt-1"></div>
    </div>`
  try {
    const d = await fetch('/api/osztaly/' + encodeURIComponent(curKod) + '/orarend').then(r => r.json())
    if (d.szunet) {
      // Szünet banner
      const uzenet = d.uzenet || ('🌙 ' + d.szunet + ' – nincs tanítás')
      document.getElementById('aktblock').innerHTML = `
        <div style="text-align:center;padding:32px 16px;">
          <div style="font-size:48px;margin-bottom:12px;">🌙</div>
          <h3 style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#f0c76b;margin-bottom:8px;">${escHtml(d.szunet)}</h3>
          <p style="font-size:14px;color:rgba(255,255,255,.5);">Szünet idején nincs tanítás</p>
          <p style="font-size:12px;color:rgba(255,255,255,.3);margin-top:6px;">${escHtml(d.uzenet||'')}</p>
        </div>`
      document.getElementById('lista').innerHTML = ''
      return
    }
    if (d.error) {
      document.getElementById('aktblock').innerHTML = `
        <div class="text-center py-3">
          <span style="font-size:32px;" class="block mb-2">⚠️</span>
          <p class="text-sm" style="color:rgba(255,255,255,.4);">${d.error}</p>
        </div>`
      setAllapot('idle'); return
    }

    const orak = d.orak || []
    const akt  = orak.find(o => isAktiv(o.kezdes, o.vegzes))
    const kov  = orak.find(o => !isMult(o.vegzes) && !isAktiv(o.kezdes, o.vegzes))

    setAllapot(akt ? 'tanul' : orak.length > 0 ? 'szabad' : 'idle')
    renderAkt(akt, kov)
    renderLista(orak)
  } catch(e) {
    document.getElementById('aktblock').innerHTML = `
      <div class="text-center py-3">
        <span style="font-size:32px;" class="block mb-2">⚠️</span>
        <p class="text-sm" style="color:rgba(255,255,255,.4);">Betöltési hiba</p>
      </div>`
    setAllapot('idle')
  }
  updateTime()
}

// ─── Aktuális blokk renderelése ───────────────────────────────────────
function renderAkt(a, k) {
  const el = document.getElementById('aktblock')

  if (a) {
    const p = calcPct(a.kezdes, a.vegzes)
    const percMaradt = Math.max(0, Math.round(toMin(a.vegzes) - nowMin()))
    const dur = durBadgeHtml(a)

    if (a.is_csoport) {
      // Csoportbontásos óra – sárga sávval és kitűzőkkel
      const csoportRows = a.csoportok.map((c,i) => `
        <div class="flex items-center justify-between gap-2 py-1.5 px-3 rounded-lg" style="background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.20);">
          <div style="display:flex;flex-direction:column;gap:2px;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;min-width:0;">
              <span class="csoport-badge">${i+1}. csoport</span>
              <span class="text-sm font-medium" style="color:rgba(255,255,255,.8);">${escHtml(c.tanar_nev||c.tanar)}</span>
            </div>
            <span class="text-xs" style="color:rgba(255,255,255,.4);">${escHtml(c.tantargy || a.tantargy)}</span>
          </div>
          <a href="/terem/${encodeURIComponent(c.terem)}" style="font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:#f0c76b;white-space:nowrap;">${escHtml(c.terem)}. terem</a>
        </div>`).join('')

      el.innerHTML = `
        <div class="flex flex-col gap-3 aktiv-card" style="background:rgba(139,92,246,.04);border-radius:12px;padding:12px;border:1px solid rgba(139,92,246,.15);">
          <div class="flex items-center justify-between gap-2">
            <div>
              <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantárgy</p>
              <p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:white;">${escHtml(a.tantargy)}</p>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
              ${dur}
              <span class="csoport-badge" style="font-size:11px;padding:4px 10px;">Csoportbontás</span>
            </div>
          </div>
          <div class="flex flex-col gap-2">${csoportRows}</div>
          <div>
            <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.08);">
              <div class="h-full rounded-full" style="width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s ease;"></div>
            </div>
            <div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.35);">
              <span>${a.kezdes}</span>
              <span style="color:#ff6b82;font-weight:600;">${percMaradt} perc</span>
              <span>${a.vegzes}</span>
            </div>
          </div>
        </div>`
    } else {
      // Egységes óra
      el.innerHTML = `
        <div class="flex flex-col gap-4 aktiv-card">
          <div>
            <div class="flex items-center gap-2 mb-0.5">
              <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Most itt van</p>
              ${dur}
            </div>
            <a href="/terem/${encodeURIComponent(a.terem)}" style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:white;line-height:1.1;display:block;">${escHtml(a.terem)}. terem <span style="font-size:18px;color:#f0c76b;">→</span></a>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tanár</p>
              <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${escHtml(a.tanar_nev||a.tanar)}</p>
            </div>
            <div>
              <p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantárgy</p>
              <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${escHtml(a.tantargy)}</p>
            </div>
          </div>
          <div>
            <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.08);">
              <div class="h-full rounded-full" style="width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s ease;"></div>
            </div>
            <div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.35);">
              <span>${a.kezdes}</span>
              <span style="color:#ff6b82;font-weight:600;">${percMaradt} perc</span>
              <span>${a.vegzes}</span>
            </div>
          </div>
        </div>`
    }
  } else if (k) {
    el.innerHTML = `
      <div class="flex items-center gap-4 py-1 aktiv-card">
        <span style="font-size:28px;">☕</span>
        <div>
          <p class="font-semibold" style="color:rgba(255,255,255,.8);">Jelenleg szabad</p>
          <p class="text-sm mt-0.5" style="color:rgba(255,255,255,.4);">
            Következő: <strong style="color:rgba(255,255,255,.7);">${escHtml(k.terem)}. terem</strong>
            · ${escHtml(k.tantargy)} · ${k.kezdes}–${k.vegzes}
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

// ─── Napirend lista ───────────────────────────────────────────────────
function renderLista(orak) {
  const el = document.getElementById('lista')
  if (!orak.length) {
    el.innerHTML = `<p class="text-sm" style="color:rgba(255,255,255,.35);">Nincs mai óra</p>`
    return
  }

  el.innerHTML = `<div class="lista-in">` + orak.map((o, i) => {
    const ak = isAktiv(o.kezdes, o.vegzes)
    const mu = isMult(o.vegzes)
    const numColor = mu ? 'rgba(255,255,255,.2)' : 'rgba(255,255,255,.85)'
    const multi = oraSzam(o) > 1
    const numLabel = sorszamLabel(o, i + 1)
    const dur = durBadgeHtml(o)

    let body = ''
    if (o.is_csoport) {
      // Csoportbontásos sor – minden csoport külön blokkban, csoport név + tárgy + terem · tanár
      const csRows = o.csoportok.map((c,ci) => `
        <div class="cs-row">
          <span class="csoport-badge cs-mini">${ci+1}. csoport</span>
          <div style="min-width:0;">
            <span class="text-xs font-medium" style="color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${escHtml(c.tantargy || o.tantargy)}</span>
            <span class="text-xs" style="display:block;color:rgba(255,255,255,.4);">${escHtml(c.terem)}. terem · ${escHtml(c.tanar_nev||c.tanar)}</span>
          </div>
        </div>`).join('')
      body = `
        <div class="flex items-center gap-1.5 flex-wrap mb-1">
          <span class="text-sm font-medium" style="color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${escHtml(o.tantargy)}</span>
          <span class="csoport-badge" style="font-size:9px;padding:1px 6px;">csoportbontás</span>
          ${dur}
        </div>
        <div class="cs-list">${csRows}</div>
        <p class="text-xs" style="color:rgba(255,255,255,.28);margin-top:3px;">${o.kezdes} – ${o.vegzes}</p>`
    } else {
      body = `
        <div class="flex items-baseline gap-1.5 flex-wrap">
          <span class="text-sm font-medium" style="color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${escHtml(o.terem)}. terem</span>
          <span class="text-xs" style="color:rgba(255,255,255,.35);">${escHtml(o.tantargy)} · ${escHtml(o.tanar_nev||o.tanar)}</span>
          ${dur}
        </div>
        <p class="text-xs" style="color:rgba(255,255,255,.28);">${o.kezdes} – ${o.vegzes}</p>`
    }

    const bontasCls = o.is_csoport ? ' bontas' : ''
    return `
      <div class="ora-row${ak?' aktiv':mu?' mult':''}${bontasCls} flex items-start gap-3 px-2 py-2.5 -mx-1">
        <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:${multi?'13px':'16px'};color:${numColor};min-width:22px;text-align:right;flex-shrink:0;margin-top:2px;white-space:nowrap;">${numLabel}</span>
        <div class="flex-1 min-w-0">${body}</div>
        ${ak ? `<span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#ff6b82;display:inline-block;margin-top:6px;"></span>` : ''}
      </div>`
  }).join('') + `</div>`
}

function refresh() {
  const ic = document.getElementById('ri')
  ic.classList.add('spinning')
  loadData().finally(() => setTimeout(() => ic.classList.remove('spinning'), 600))
}

updateTime()
setInterval(updateTime, 60_000)
loadOsztalyok()
setInterval(() => { if (curKod) loadData() }, REFRESH)
</script>
</body>
</html>
