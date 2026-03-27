<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Termek</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family:'DM Sans',sans-serif; background-color:#060f1e; background-image: radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%); min-height:100vh; }
  body::before { content:''; position:fixed; inset:0; pointer-events:none; z-index:0; background-image: linear-gradient(rgba(255,255,255,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.02) 1px, transparent 1px); background-size:40px 40px; }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent); z-index:200; }
  .glass { background:rgba(255,255,255,.05); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.10); }
  .pulse { animation:pulseDot 2s infinite; }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .card-in { animation:cardIn .4s cubic-bezier(.22,1,.36,1) both; }
  @keyframes cardIn { from{opacity:0;transform:translateY(14px) scale(.98)} to{opacity:1;transform:none} }
  .skeleton { background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:8px; }
  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes spin { to{transform:rotate(360deg)} }
  .spinning { animation:spin .6s linear; }
  .room-card { transition:transform .15s ease, border-color .15s ease; cursor:pointer; }
  .room-card:hover { transform:translateY(-2px); border-color:rgba(255,255,255,.22) !important; }
  .filter-btn { transition:all .15s ease; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.5); border-radius:10px; padding:8px 16px; font-size:13px; font-weight:500; cursor:pointer; display:flex; align-items:center; gap:8px; width:auto; margin-top:0; }
  .filter-btn:hover { background:rgba(255,255,255,.09); color:white; }
  .filter-btn.active { background:rgba(255,255,255,.12); color:white; border-color:rgba(255,255,255,.25); }
  .filter-btn.active-szabad { background:rgba(26,138,74,.2); color:#4ade80; border-color:rgba(26,138,74,.4); }
  .filter-btn.active-foglalt { background:rgba(200,16,46,.2); color:#ff6b82; border-color:rgba(200,16,46,.4); }
  input[type=search] { background:rgba(255,255,255,.06); border:1.5px solid rgba(255,255,255,.10); color:white; border-radius:10px; padding:8px 16px 8px 36px; font-size:13px; width:100%; transition:border-color .2s; font-family:'DM Sans',sans-serif; }
  input[type=search]::placeholder { color:rgba(255,255,255,.3); }
  input[type=search]:focus { outline:none; border-color:rgba(200,151,42,.4); }
  input[type=search]::-webkit-search-cancel-button { display:none; }
  @keyframes modalIn { from{opacity:0;transform:translateY(24px) scale(.97)} to{opacity:1;transform:none} }
  a { text-decoration:none; }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Navbar -->
<nav style="background:rgba(6,15,30,.7);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);" class="sticky top-0 z-50 px-5 h-16 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;" class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span class="text-sm" style="color:rgba(255,255,255,.45);">Összes terem</span>
  </div>
  <button onclick="refresh()" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.12);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
    <svg id="refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    <span id="footer-ido">–</span>
  </button>
</nav>

<!-- Hétvége banner -->
<div id="weekend-info" class="hidden relative z-10 max-w-5xl mx-auto px-4 pt-4">
  <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm" style="background:rgba(200,151,42,.10);border:1px solid rgba(200,151,42,.25);color:#f0c76b;">
    <span>🌙</span>
    <span>Hétvége – az órarendek hétfőn frissülnek. A termek listája elérhető, de foglaltság nem jelenik meg.</span>
  </div>
</div>

<!-- Filter bar -->
<div class="relative z-10 max-w-5xl mx-auto px-4 pt-5 pb-3">
  <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
    <div class="flex items-center gap-2 flex-wrap">
      <button class="filter-btn active" id="btn-mind" onclick="setFilter('mind')">
        Összes <span id="cnt-mind" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
      <button class="filter-btn" id="btn-szabad" onclick="setFilter('szabad')">
        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#4ade80;display:inline-block;"></span>
        Szabad <span id="cnt-szabad" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
      <button class="filter-btn" id="btn-foglalt" onclick="setFilter('foglalt')">
        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#ff6b82;display:inline-block;"></span>
        Foglalt <span id="cnt-foglalt" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
    </div>
    <div class="relative w-full sm:w-48">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2" style="color:rgba(255,255,255,.3);" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="search" id="search-input" placeholder="Terem…" oninput="filterRooms()">
    </div>
  </div>
</div>

<!-- Grid -->
<main class="relative z-10 max-w-5xl mx-auto px-4 pb-16">
  <div id="skeleton-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
    <div class="skeleton h-32 rounded-2xl"></div><div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div><div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div><div class="skeleton h-32 rounded-2xl"></div>
    <div class="skeleton h-32 rounded-2xl"></div><div class="skeleton h-32 rounded-2xl"></div>
  </div>
  <div id="rooms-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
  <div id="empty-state" class="hidden text-center py-20"><span class="text-5xl block mb-3">🔍</span><p class="font-semibold" style="color:rgba(255,255,255,.7);">Nincs találat</p></div>
</main>

<!-- Modal -->
<div id="modal-overlay" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" style="background:rgba(6,15,30,.7);backdrop-filter:blur(8px);" onclick="handleOverlayClick(event)">
  <div id="modal-box" class="glass w-full max-w-sm rounded-2xl overflow-hidden" style="animation:modalIn .3s cubic-bezier(.22,1,.36,1);">
    <div id="modal-content"></div>
  </div>
</div>

<script>
let allRooms=[], curFilter='mind', curSearch=''

async function fetchRooms() {
  try {
    const d=await fetch('/api/termek?allapot=1').then(r=>r.json())
    if(d.error){showError(d.error);return}
    // Hétvégén (nap:0) nincs allapot mező – alapból szabad minden terem
    allRooms=(d.termek||[]).map(r=>({
      ...r,
      allapot: r.allapot ?? 'szabad',
      aktualis: r.aktualis ?? null,
    }))
    if(d.nap===0){
      const wi=document.getElementById('weekend-info')
      if(wi) wi.style.display='flex'
    }
    updateCounts(); renderGrid()
    document.getElementById('footer-ido').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
  } catch(e){showError('Nem sikerült csatlakozni')}
}

function updateCounts() {
  const sz=allRooms.filter(r=>r.allapot==='szabad').length
  const fo=allRooms.filter(r=>r.allapot==='foglalt').length
  document.getElementById('cnt-mind').textContent=allRooms.length
  document.getElementById('cnt-szabad').textContent=sz
  document.getElementById('cnt-foglalt').textContent=fo
}

function setFilter(f) {
  curFilter=f
  ;['mind','szabad','foglalt'].forEach(k=>{
    const b=document.getElementById('btn-'+k)
    b.className='filter-btn'+(k===f?(f==='szabad'?' active-szabad':f==='foglalt'?' active-foglalt':' active'):'')
  })
  renderGrid()
}

function filterRooms() {
  curSearch=(document.getElementById('search-input').value||'').toLowerCase()
  renderGrid()
}

function calcPct(k,v){const n=new Date();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number);const c=n.getHours()*60+n.getMinutes();return Math.min(100,Math.max(0,Math.round(((c-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))}

function renderGrid() {
  const grid=document.getElementById('rooms-grid'), empty=document.getElementById('empty-state')
  document.getElementById('skeleton-grid').classList.add('hidden')
  let rooms=allRooms
  if(curFilter!=='mind') rooms=rooms.filter(r=>r.allapot===curFilter)
  if(curSearch) rooms=rooms.filter(r=>r.terem_szam.toLowerCase().includes(curSearch))
  if(!rooms.length){grid.classList.add('hidden');empty.classList.remove('hidden');return}
  empty.classList.add('hidden'); grid.classList.remove('hidden')
  grid.innerHTML=rooms.map((r,i)=>{
    const sz=r.allapot==='szabad'
    let body=''
    if(sz){
      body=`<p class="text-xs mt-2" style="color:rgba(255,255,255,.3);">Nincs óra</p>`
    } else {
      const a=r.aktualis, pct=calcPct(a.kezdes,a.vegzes)
      body=`<p class="text-xs font-medium mt-2 truncate" style="color:rgba(255,255,255,.7);">${a.tanar} · ${a.osztaly}</p><p class="text-xs truncate" style="color:rgba(255,255,255,.35);">${a.tantargy} · ${a.kezdes}–${a.vegzes}</p><div class="mt-2 h-1 rounded-full overflow-hidden" style="background:rgba(255,255,255,.1);"><div class="h-full rounded-full" style="width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div>`
    }
    const pillBg=sz?'rgba(26,138,74,.2)':'rgba(200,16,46,.2)'
    const pillColor=sz?'#4ade80':'#ff6b82'
    const dotBg=sz?'#4ade80':'#ff6b82'
    const pillTxt=sz?'SZABAD':'FOGLALT'
    return `<div class="room-card glass card-in rounded-2xl p-4" style="animation-delay:${i*25}ms;" onclick="openModal('${r.terem_szam}')">
      <div class="flex items-start justify-between gap-1">
        <div>
          <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Terem</p>
          <h2 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:white;line-height:1.1;">${r.terem_szam}</h2>
        </div>
        <div class="flex items-center gap-1 px-2 py-1 rounded-full flex-shrink-0" style="background:${pillBg};border:1px solid ${pillColor}33;font-size:9px;font-weight:700;color:${pillColor};letter-spacing:.05em;">
          <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" style="background:${dotBg};display:inline-block;"></span>
          ${pillTxt}
        </div>
      </div>
      ${body}
    </div>`
  }).join('')
}

async function openModal(szam) {
  const overlay=document.getElementById('modal-overlay'), content=document.getElementById('modal-content')
  overlay.classList.remove('hidden')
  content.innerHTML=`<div class="px-6 py-6"><div class="flex justify-between items-center mb-4"><div><p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">Terem</p><h2 style="font-family:'Playfair Display',serif;font-size:40px;font-weight:700;color:white;line-height:1;">${szam}</h2></div><button onclick="closeModal()" style="background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="flex flex-col gap-2"><div class="skeleton h-5 w-2/5"></div><div class="skeleton h-7 w-4/5"></div><div class="skeleton h-4 w-1/2"></div></div></div>`
  try {
    const data=await fetch(`/api/terem/${szam}`).then(r=>r.json())
    renderModal(data)
  } catch(e) {
    content.innerHTML=`<div class="px-6 py-8 text-center"><p style="color:rgba(255,255,255,.4);">Hiba a betöltésnél</p></div>`
  }
}

function renderModal(data) {
  const c=document.getElementById('modal-content'), sz=data.allapot==='szabad'
  let main=''
  if(sz){
    main=`<div class="text-center py-3"><span class="text-4xl block mb-2">✅</span><p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#4ade80;">Szabad terem</p><p class="text-sm mt-1" style="color:rgba(255,255,255,.4);">Nincs aktív foglalás</p></div>`
  } else {
    const a=data.aktualis, pct=calcPct(a.kezdes,a.vegzes)
    main=`<div class="flex flex-col gap-3"><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.35);">Tanár</p><p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:white;">${a.tanar_nev||a.tanar}</p></div><div class="grid grid-cols-2 gap-3"><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.35);">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${a.osztaly}</p></div><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.35);">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${a.tantargy}</p></div></div><div><div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.1);"><div class="h-full rounded-full" style="width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div><div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.4);"><span>${a.kezdes}</span><span style="color:#ff6b82;font-weight:600;">még ${a.perc_maradt} perc</span><span>${a.vegzes}</span></div></div></div>`
  }
  let kov=''
  if(data.kovetkezo){const k=data.kovetkezo;kov=`<div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);"><p class="text-xs font-semibold tracking-widest uppercase mb-1.5" style="color:rgba(255,255,255,.3);">Következő</p><div class="flex items-center justify-between gap-2 flex-wrap"><span class="text-sm font-medium" style="color:rgba(255,255,255,.7);">${k.tanar} · ${k.osztaly} · ${k.tantargy}</span><span class="text-xs" style="color:rgba(255,255,255,.35);">${k.kezdes}–${k.vegzes}</span></div></div>`}
  const pillBg=sz?'rgba(26,138,74,.2)':'rgba(200,16,46,.2)'
  const pillColor=sz?'#4ade80':'#ff6b82'
  c.innerHTML=`<div class="px-6 pt-6 pb-2"><div class="flex items-start justify-between mb-5"><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Terem</p><h2 style="font-family:'Playfair Display',serif;font-size:40px;font-weight:700;color:white;line-height:1;">${data.terem}</h2></div><div class="flex items-center gap-2 mt-1"><div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold" style="background:${pillBg};border:1px solid ${pillColor}55;color:${pillColor};"><span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" style="background:${pillColor};display:inline-block;"></span>${sz?'SZABAD':'FOGLALT'}</div><button onclick="closeModal()" style="background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div></div>${main}${kov}</div><div class="px-6 py-4 flex items-center justify-between" style="border-top:1px solid rgba(255,255,255,.08);"><a href="/terem/${data.terem}" class="text-sm font-medium" style="color:#f0c76b;">Napirend nézet →</a><span style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.2);font-size:13px;font-weight:700;">Ticky</span></div>`
}

function closeModal() { document.getElementById('modal-overlay').classList.add('hidden') }
function handleOverlayClick(e) { if(e.target===document.getElementById('modal-overlay')) closeModal() }

function showError(msg) {
  document.getElementById('skeleton-grid').classList.add('hidden')
  document.getElementById('rooms-grid').innerHTML=`<div class="col-span-4 text-center py-16"><span class="text-4xl block mb-3">⚠️</span><p style="color:rgba(255,255,255,.5);">${msg}</p></div>`
  document.getElementById('rooms-grid').classList.remove('hidden')
}

function refresh() {
  const icon=document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  fetchRooms().finally(()=>setTimeout(()=>icon.classList.remove('spinning'),600))
}

fetchRooms()
setInterval(fetchRooms, 60_000)
</script>
</body>
</html>
