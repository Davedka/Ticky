<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Termek</title>
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png?v=2">

<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="apple-touch-icon" href="/favicon.png?v=2">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;color:white;
  background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
a{text-decoration:none;color:inherit;}
.glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
.pulse{animation:pulseDot 2s infinite;}
@keyframes pulseDot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.card-in{animation:cardIn .4s cubic-bezier(.22,1,.36,1) both;}
@keyframes cardIn{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:none}}
.skeleton{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:8px;}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinning{animation:spin .6s linear;}
.room-card{transition:transform .15s,border-color .15s;cursor:pointer;}
.room-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.22)!important;}
.filter-btn{transition:all .15s;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:rgba(255,255,255,.5);border-radius:10px;padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:7px;white-space:nowrap;}
.filter-btn:hover{background:rgba(255,255,255,.09);color:white;}
.filter-btn.active{background:rgba(255,255,255,.12);color:white;border-color:rgba(255,255,255,.25);}
.filter-btn.active-szabad{background:rgba(26,138,74,.2);color:#4ade80;border-color:rgba(26,138,74,.4);}
.filter-btn.active-foglalt{background:rgba(200,16,46,.2);color:#ff6b82;border-color:rgba(200,16,46,.4);}
.search-wrap{position:relative;}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);pointer-events:none;}
input[type=search]{background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.10);color:white;border-radius:10px;padding:8px 14px 8px 32px;font-size:13px;width:100%;transition:border-color .2s;font-family:'DM Sans',sans-serif;outline:none;}
input[type=search]::placeholder{color:rgba(255,255,255,.3);}
input[type=search]:focus{border-color:rgba(200,151,42,.4);}
input[type=search]::-webkit-search-cancel-button{display:none;}
@keyframes modalIn{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}
/* Modal overlay */
#modal-overlay{overscroll-behavior:contain;}
</style>
</head>
<body>
<?php
if (!function_exists('ticky_nav')) {
function ticky_nav(string $aktiv = '', string $cim = '') {
    $links = [
        ['href'=>'/termek',  'label'=>'Termek',  'key'=>'termek'],
        ['href'=>'/tanar',   'label'=>'Tanár',   'key'=>'tanar'],
        ['href'=>'/osztaly', 'label'=>'Osztály', 'key'=>'osztaly'],
        ['href'=>'/qr',      'label'=>'QR',      'key'=>'qr'],
        ['href'=>'/kijelzo', 'label'=>'Kijelző', 'key'=>'kijelzo'],
    ];
    ?>
<style>
.ticky-navbar{position:sticky;top:0;z-index:200;height:60px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,15,30,.88);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);}
.tn-brand{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;display:flex;align-items:center;gap:8px;color:white;text-decoration:none;}
.tn-brand span.dot{width:7px;height:7px;border-radius:50%;background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;flex-shrink:0;animation:pd 2s infinite;}
.tn-sep{color:rgba(255,255,255,.2);font-weight:400;margin:0 2px;}
.tn-cim{font-size:14px;color:rgba(255,255,255,.45);font-weight:400;font-family:'DM Sans',sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;}
.tn-links{display:flex;align-items:center;gap:2px;}
.tn-link{font-size:13px;font-weight:500;padding:7px 10px;border-radius:8px;color:rgba(255,255,255,.55);transition:all .15s;white-space:nowrap;text-decoration:none;}
.tn-link:hover{color:white;background:rgba(255,255,255,.09);}
.tn-link.active{color:rgba(200,151,42,.9);background:rgba(200,151,42,.1);}
.tn-link.gold{color:rgba(200,151,42,.8);border:1px solid rgba(200,151,42,.2);}
.tn-link.gold:hover{color:#f0c76b;background:rgba(200,151,42,.1);}
.tn-right{display:flex;align-items:center;gap:6px;}
.tn-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:7px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);}
.tn-hamburger span{width:18px;height:2px;background:rgba(255,255,255,.7);border-radius:2px;transition:all .25s;}
.tn-hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.tn-hamburger.open span:nth-child(2){opacity:0;}
.tn-hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
.tn-mobile{display:none;position:fixed;top:60px;left:0;right:0;z-index:190;background:rgba(6,15,30,.97);backdrop-filter:blur(24px);border-bottom:1px solid rgba(255,255,255,.08);padding:10px 16px 18px;flex-direction:column;gap:3px;}
.tn-mobile.open{display:flex;}
.tn-mobile a{font-size:15px;font-weight:500;padding:12px 14px;border-radius:10px;color:rgba(255,255,255,.7);text-decoration:none;transition:all .15s;border:1px solid transparent;}
.tn-mobile a:hover{background:rgba(255,255,255,.07);color:white;}
.tn-mobile a.active{background:rgba(200,151,42,.1);border-color:rgba(200,151,42,.2);color:#f0c76b;}
.tn-mobile a.mm-gold{color:#f0c76b;border-color:rgba(200,151,42,.2);background:rgba(200,151,42,.06);}
.ticky-sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:150;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;background:rgba(6,15,30,.85);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-left:none;border-radius:0 12px 12px 0;}
.tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;color:rgba(255,255,255,.6);transition:all .18s;text-decoration:none;}
.tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
.tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:rgba(255,255,255,.88);font-size:12px;font-family:'DM Sans',sans-serif;font-weight:500;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
.tsb-item:hover::after{opacity:1;}
.tsb-divider{width:20px;height:1px;background:rgba(255,255,255,.10);margin:2px 0;}
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
@media(max-width:640px){.ticky-sidebar{display:none;}.tn-links{display:none;}.tn-hamburger{display:flex;}}
@media(min-width:641px){.tn-mobile{display:none!important;}.tn-hamburger{display:none!important;}}
</style>
<div style="position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:300;"></div>
<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="tsb-item" data-label="Eseménynaptár">📅</a>
  <div class="tsb-divider"></div>
  <a href="/support" class="tsb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="tsb-item" data-label="Bug report">🐛</a>
</div>
<nav class="ticky-navbar">
  <div style="display:flex;align-items:center;gap:6px;min-width:0;overflow:hidden;">
    <a href="/" class="tn-brand"><span class="dot"></span>Ticky</a>
    <?php if ($cim): ?><span class="tn-sep">·</span><span class="tn-cim"><?= htmlspecialchars($cim) ?></span><?php endif; ?>
  </div>
  <div class="tn-right">
    <div class="tn-links">
      <?php foreach ($links as $l): ?>
        <a href="<?= $l['href'] ?>" class="tn-link<?= $aktiv===$l['key']?' active':'' ?>"><?= $l['label'] ?></a>
      <?php endforeach; ?>
      <a href="/admin" class="tn-link gold">⚙️ Admin</a>
    </div>
    <div class="tn-hamburger" id="tn-hamburger" onclick="tnToggle()"><span></span><span></span><span></span></div>
  </div>
</nav>
<div class="tn-mobile" id="tn-mobile">
  <?php foreach ($links as $l): ?>
    <a href="<?= $l['href'] ?>"<?= $aktiv===$l['key']?' class="active"':'' ?>><?= $l['label'] ?></a>
  <?php endforeach; ?>
  <a href="/support">✉️ Support</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener">🐛 Bug report</a>
  <a href="/admin" class="mm-gold">⚙️ Admin</a>
</div>
<script>
function tnToggle(){const m=document.getElementById('tn-mobile'),h=document.getElementById('tn-hamburger');const o=m.classList.toggle('open');h.classList.toggle('open',o);}
document.addEventListener('click',function(e){if(!e.target.closest('#tn-mobile')&&!e.target.closest('#tn-hamburger')){document.getElementById('tn-mobile')?.classList.remove('open');document.getElementById('tn-hamburger')?.classList.remove('open');}});
</script>
<?php
}
}
?>
<?php ticky_nav('termek','Összes terem'); ?>

<!-- Hétvége banner -->
<div id="weekend-info" style="display:none;" class="relative z-10 max-w-5xl mx-auto px-4 pt-4">
  <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:rgba(200,151,42,.10);border:1px solid rgba(200,151,42,.25);color:#f0c76b;font-size:13px;">
    <span>🌙</span><span>Hétvége – az órarendek hétfőn frissülnek.</span>
  </div>
</div>

<!-- Filter bar -->
<div class="relative z-10 max-w-5xl mx-auto px-4 pt-5 pb-3">
  <div style="display:flex;flex-direction:column;gap:10px;">
    <!-- Gombok sor -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <button class="filter-btn active" id="btn-mind" onclick="setFilter('mind')">
        Összes <span id="cnt-mind" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
      <button class="filter-btn" id="btn-szabad" onclick="setFilter('szabad')">
        <span style="width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;flex-shrink:0;"></span>
        Szabad <span id="cnt-szabad" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
      <button class="filter-btn" id="btn-foglalt" onclick="setFilter('foglalt')">
        <span style="width:7px;height:7px;border-radius:50%;background:#ff6b82;display:inline-block;flex-shrink:0;"></span>
        Foglalt <span id="cnt-foglalt" style="font-family:'Playfair Display',serif;font-weight:700;font-size:14px;">–</span>
      </button>
      <button onclick="refresh()" style="margin-left:auto;display:flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;font-size:12px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.12);background:transparent;cursor:pointer;">
        <svg id="refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        <span id="footer-ido">–</span>
      </button>
    </div>
    <!-- Kereső teljes szélességben mobilon -->
    <div class="search-wrap">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="search" id="search-input" placeholder="Terem száma…" oninput="filterRooms()">
    </div>
  </div>
</div>

<!-- Grid -->
<main class="relative z-10 max-w-5xl mx-auto px-4 pb-16">
  <div id="skeleton-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
    <div class="skeleton" style="height:120px;border-radius:16px;"></div>
  </div>
  <div id="rooms-grid" style="display:none;grid-template-columns:repeat(2,1fr);gap:10px;"></div>
  <div id="empty-state" style="display:none;text-align:center;padding:60px 0;">
    <span style="font-size:40px;display:block;margin-bottom:10px;">🔍</span>
    <p style="color:rgba(255,255,255,.6);font-weight:600;">Nincs találat</p>
  </div>
</main>

<!-- Modal -->
<div id="modal-overlay" onclick="handleOverlayClick(event)" style="display:none;position:fixed;inset:0;z-index:500;display:none;align-items:flex-end;justify-content:center;padding:16px;background:rgba(6,15,30,.75);backdrop-filter:blur(8px);">
  <div id="modal-box" class="glass" style="width:100%;max-width:420px;border-radius:20px;overflow:hidden;animation:modalIn .3s cubic-bezier(.22,1,.36,1);">
    <div id="modal-content"></div>
  </div>
</div>

<style>
@media(min-width:480px){
  #rooms-grid,#skeleton-grid{grid-template-columns:repeat(3,1fr)!important;}
}
@media(min-width:768px){
  #rooms-grid,#skeleton-grid{grid-template-columns:repeat(4,1fr)!important;}
}
@media(min-width:480px){
  #modal-overlay{align-items:center!important;}
}
</style>

<script>
let allRooms=[],curFilter='mind',curSearch=''
async function fetchRooms(){
  try{
    const d=await fetch('/api/termek?allapot=1').then(r=>r.json())
    if(d.error){showError(d.error);return}
    allRooms=(d.termek||[]).map(r=>({...r,allapot:r.allapot??'szabad',aktualis:r.aktualis??null}))
    if(d.nap===0)document.getElementById('weekend-info').style.display='block'
    updateCounts();renderGrid()
    document.getElementById('footer-ido').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
  }catch(e){showError('Nem sikerült csatlakozni')}
}
function updateCounts(){
  const sz=allRooms.filter(r=>r.allapot==='szabad').length
  const fo=allRooms.filter(r=>r.allapot==='foglalt').length
  document.getElementById('cnt-mind').textContent=allRooms.length
  document.getElementById('cnt-szabad').textContent=sz
  document.getElementById('cnt-foglalt').textContent=fo
}
function setFilter(f){
  curFilter=f
  ;['mind','szabad','foglalt'].forEach(k=>{
    const b=document.getElementById('btn-'+k)
    b.className='filter-btn'+(k===f?(f==='szabad'?' active-szabad':f==='foglalt'?' active-foglalt':' active'):'')
  })
  renderGrid()
}
function filterRooms(){
  curSearch=(document.getElementById('search-input').value||'').toLowerCase()
  renderGrid()
}
function calcPct(k,v){const n=new Date(),c=n.getHours()*60+n.getMinutes();const[kh,km]=k.split(':').map(Number);const[vh,vm]=v.split(':').map(Number);return Math.min(100,Math.max(0,Math.round(((c-kh*60-km)/((vh*60+vm)-(kh*60+km)))*100)))}
function renderGrid(){
  const grid=document.getElementById('rooms-grid'),empty=document.getElementById('empty-state')
  document.getElementById('skeleton-grid').style.display='none'
  let rooms=allRooms
  if(curFilter!=='mind')rooms=rooms.filter(r=>r.allapot===curFilter)
  if(curSearch)rooms=rooms.filter(r=>r.terem_szam.toLowerCase().includes(curSearch))
  if(!rooms.length){grid.style.display='none';empty.style.display='block';return}
  empty.style.display='none';grid.style.display='grid'
  grid.innerHTML=rooms.map((r,i)=>{
    const sz=r.allapot==='szabad'
    let body=''
    if(!sz&&r.aktualis){
      const a=r.aktualis,pct=calcPct(a.kezdes,a.vegzes)
      body=`<p style="font-size:11px;font-weight:500;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:rgba(255,255,255,.7);">${a.tanar} · ${a.osztaly}</p><p style="font-size:10px;color:rgba(255,255,255,.35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${a.tantargy} · ${a.kezdes}–${a.vegzes}</p><div style="margin-top:6px;height:3px;border-radius:2px;overflow:hidden;background:rgba(255,255,255,.1);"><div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div>`
    }else{
      body=`<p style="font-size:11px;margin-top:6px;color:rgba(255,255,255,.3);">Nincs óra</p>`
    }
    const pillBg=sz?'rgba(26,138,74,.2)':'rgba(200,16,46,.2)'
    const pillColor=sz?'#4ade80':'#ff6b82'
    return `<div class="room-card glass card-in" style="border-radius:14px;padding:14px;animation-delay:${i*20}ms;" onclick="openModal('${r.terem_szam}')">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:4px;">
        <div>
          <p style="font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);">Terem</p>
          <h2 style="font-family:'Playfair Display',serif;font-size:clamp(22px,5vw,28px);font-weight:700;color:white;line-height:1.1;">${r.terem_szam}</h2>
        </div>
        <div style="display:flex;align-items:center;gap:4px;padding:3px 8px;border-radius:20px;background:${pillBg};border:1px solid ${pillColor}44;font-size:8px;font-weight:700;color:${pillColor};letter-spacing:.04em;flex-shrink:0;margin-top:2px;">
          <span style="width:5px;height:5px;border-radius:50%;background:${pillColor};display:inline-block;animation:pulseDot 2s infinite;"></span>
          ${sz?'SZABAD':'FOGLALT'}
        </div>
      </div>
      ${body}
    </div>`
  }).join('')
}
async function openModal(szam){
  const overlay=document.getElementById('modal-overlay'),content=document.getElementById('modal-content')
  overlay.style.display='flex'
  content.innerHTML=`<div style="padding:20px;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);">Terem</p><h2 style="font-family:'Playfair Display',serif;font-size:36px;font-weight:700;color:white;line-height:1;">${szam}</h2></div><button onclick="closeModal()" style="background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div style="display:flex;flex-direction:column;gap:8px;"><div style="height:12px;border-radius:6px;background:rgba(255,255,255,.07);width:40%;"></div><div style="height:20px;border-radius:6px;background:rgba(255,255,255,.07);width:70%;"></div></div></div>`
  try{
    const data=await fetch(`/api/terem/${encodeURIComponent(szam)}`).then(r=>r.json())
    renderModal(data)
  }catch(e){content.innerHTML=`<div style="padding:20px;text-align:center;color:rgba(255,255,255,.4);">Hiba a betöltésnél</div>`}
}
function renderModal(data){
  const c=document.getElementById('modal-content'),sz=data.allapot==='szabad'
  let main=''
  if(sz){
    main=`<div style="text-align:center;padding:16px 0;"><span style="font-size:36px;display:block;margin-bottom:8px;">✅</span><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#4ade80;">Szabad terem</p><p style="font-size:13px;color:rgba(255,255,255,.4);margin-top:4px;">Nincs aktív foglalás</p></div>`
  }else{
    const a=data.aktualis,pct=calcPct(a.kezdes,a.vegzes)
    main=`<div style="display:flex;flex-direction:column;gap:12px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:2px;">Tanár</p><p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:white;">${a.tanar_nev||a.tanar}</p></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:2px;">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${a.osztaly}</p></div><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:2px;">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${a.tantargy}</p></div></div><div><div style="height:5px;border-radius:3px;overflow:hidden;background:rgba(255,255,255,.1);"><div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div><div style="display:flex;justify-content:space-between;margin-top:5px;font-size:11px;color:rgba(255,255,255,.4);"><span>${a.kezdes}</span><span style="color:#ff6b82;font-weight:600;">még ${a.perc_maradt} perc</span><span>${a.vegzes}</span></div></div></div>`
  }
  let kov=''
  if(data.kovetkezo){const k=data.kovetkezo;kov=`<div style="margin-top:12px;padding:12px 14px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);"><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px;">Következő</p><div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;"><span style="font-size:13px;color:rgba(255,255,255,.7);">${k.tanar} · ${k.osztaly} · ${k.tantargy}</span><span style="font-size:11px;color:rgba(255,255,255,.35);white-space:nowrap;">${k.kezdes}–${k.vegzes}</span></div></div>`}
  const pillBg=sz?'rgba(26,138,74,.2)':'rgba(200,16,46,.2)'
  const pillColor=sz?'#4ade80':'#ff6b82'
  c.innerHTML=`<div style="padding:20px 20px 8px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
      <div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Terem</p><h2 style="font-family:'Playfair Display',serif;font-size:36px;font-weight:700;color:white;line-height:1;">${data.terem}</h2></div>
      <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
        <div style="display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:10px;font-weight:700;background:${pillBg};border:1px solid ${pillColor}55;color:${pillColor};"><span style="width:5px;height:5px;border-radius:50%;background:${pillColor};display:inline-block;animation:pulseDot 2s infinite;"></span>${sz?'SZABAD':'FOGLALT'}</div>
        <button onclick="closeModal()" style="background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
    </div>
    ${main}${kov}
  </div>
  <div style="padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;">
    <a href="/terem/${data.terem}" style="font-size:13px;font-weight:500;color:#f0c76b;text-decoration:none;">Napirend nézet →</a>
    <span style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.2);font-size:13px;font-weight:700;">Ticky</span>
  </div>`
}
function closeModal(){document.getElementById('modal-overlay').style.display='none';}
function handleOverlayClick(e){if(e.target===document.getElementById('modal-overlay'))closeModal();}
function showError(msg){
  document.getElementById('skeleton-grid').style.display='none'
  document.getElementById('rooms-grid').innerHTML=`<div style="grid-column:1/-1;text-align:center;padding:60px 0;"><span style="font-size:36px;display:block;margin-bottom:10px;">⚠️</span><p style="color:rgba(255,255,255,.5);">${msg}</p></div>`
  document.getElementById('rooms-grid').style.display='grid'
}
function refresh(){
  const icon=document.getElementById('refresh-icon')
  icon.classList.add('spinning')
  fetchRooms().finally(()=>setTimeout(()=>icon.classList.remove('spinning'),600))
}
fetchRooms()
setInterval(fetchRooms,60000)
</script>
</body>
</html>
