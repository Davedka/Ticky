<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Napirend</title>
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png?v=2">

<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="apple-touch-icon" href="/favicon.png?v=2">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;color:white;background-color:#060f1e;min-height:100vh;
  background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.5) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:40px 40px;}
a{text-decoration:none;color:inherit;}
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinning{animation:spin .6s linear;}
.skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
/* Page header */
.page-header{position:relative;z-index:10;padding:16px 16px 0;max-width:1200px;margin:0 auto;}
.terem-num{font-family:'Playfair Display',serif;font-size:clamp(36px,10vw,52px);font-weight:700;color:white;line-height:1;letter-spacing:-1px;}
.sum-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:8px;font-size:11px;font-weight:500;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.55);}
.sum-pill.gold{background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.25);color:#f0c76b;}
.sum-pill.green{background:rgba(26,138,74,.12);border-color:rgba(26,138,74,.25);color:#4ade80;}
/* Desktop timetable – smooth scroll */
.tt-outer{position:relative;z-index:10;max-width:1200px;margin:16px auto 0;padding:0 16px;overflow-x:auto;-webkit-overflow-scrolling:touch;transform:translateZ(0);will-change:transform;overscroll-behavior:contain;}
.tt-grid{display:grid;grid-template-columns:42px repeat(5,minmax(100px,1fr));min-width:560px;}
.tt-hdr{padding:7px 5px 9px;text-align:center;font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:rgba(255,255,255,.35);border-bottom:1px solid rgba(255,255,255,.08);}
.tt-hdr.ma{color:#f0c76b;}
.tt-hdr-time{border-bottom:1px solid rgba(255,255,255,.08);}
.tt-timecol{position:relative;border-right:1px solid rgba(255,255,255,.08);contain:layout style;}
.tt-daycol{position:relative;border-right:1px solid rgba(255,255,255,.04);overflow:hidden;contain:layout style;}
.tt-daycol:last-child{border-right:none;}
.tt-daycol.ma{background:rgba(200,151,42,.02);}
.hline{position:absolute;left:0;right:0;height:1px;background:rgba(255,255,255,.05);pointer-events:none;}
.hline.bold{background:rgba(255,255,255,.09);}
.tlabel{position:absolute;right:4px;font-size:9px;font-weight:500;color:rgba(255,255,255,.25);transform:translateY(-50%);white-space:nowrap;}
.ora-blk{position:absolute;left:2px;right:2px;border-radius:7px;padding:4px 6px;overflow:hidden;border:1px solid rgba(255,255,255,.08);transition:filter .15s;cursor:default;background:linear-gradient(160deg,rgba(26,74,138,.85),rgba(11,46,89,.9));}
.ora-blk:hover{filter:brightness(1.15);z-index:20;}
.ora-blk.aktiv{background:linear-gradient(160deg,rgba(200,151,42,.35),rgba(180,100,20,.3));border-color:rgba(200,151,42,.55);}
.ora-blk.mult{opacity:.32;}
.ob-tanar{font-family:'Playfair Display',serif;font-size:11px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;}
.ob-meta{font-size:9px;color:rgba(255,255,255,.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.ora-blk.aktiv .ob-tanar{color:#f0c76b;}
.ob-prog{position:absolute;bottom:0;left:0;right:0;height:3px;background:rgba(255,255,255,.06);border-radius:0 0 7px 7px;overflow:hidden;}
.ob-prog-fill{height:100%;background:linear-gradient(90deg,#c8972a,#f0c76b);}
.now-line{position:absolute;left:0;right:0;height:2px;pointer-events:none;z-index:15;background:linear-gradient(90deg,transparent,#ff6b82 20%,#ff6b82 80%,transparent);}
.now-dot{position:absolute;left:0;top:-4px;width:9px;height:9px;border-radius:50%;background:#ff6b82;box-shadow:0 0 8px #ff6b82;animation:pd 1.5s infinite;}
/* Mobile view */
.mob-section{display:none;}
@media(max-width:680px){.tt-outer{display:none;}.mob-section{display:block;}}
.mob-tabs{display:flex;gap:4px;overflow-x:auto;padding:12px 16px 0;-ms-overflow-style:none;scrollbar-width:none;}
.mob-tabs::-webkit-scrollbar{display:none;}
.mob-tab{padding:7px 12px;border-radius:8px;font-size:12px;font-weight:500;background:transparent;border:1px solid transparent;color:rgba(255,255,255,.4);cursor:pointer;transition:all .15s;white-space:nowrap;font-family:'DM Sans',sans-serif;}
.mob-tab:hover{color:rgba(255,255,255,.8);background:rgba(255,255,255,.06);}
.mob-tab.active{background:rgba(200,151,42,.15);border-color:rgba(200,151,42,.4);color:#f0c76b;font-weight:600;}
.mob-tab.ma{border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.7);}
.mob-list{padding:10px 16px 40px;max-width:480px;margin:0 auto;}
.mob-row{display:flex;align-items:stretch;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.07);margin-bottom:7px;}
.mob-row.aktiv{border-color:rgba(200,151,42,.5);background:rgba(200,151,42,.06);}
.mob-row.mult{opacity:.38;}
.mob-num{font-family:'Playfair Display',serif;font-weight:700;font-size:17px;color:rgba(255,255,255,.2);width:38px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.03);border-right:1px solid rgba(255,255,255,.06);}
.mob-row.aktiv .mob-num{color:#f0c76b;background:rgba(200,151,42,.08);border-right-color:rgba(200,151,42,.2);}
.mob-body{flex:1;padding:9px 12px;min-width:0;}
.mob-ido{font-size:10px;color:rgba(255,255,255,.28);font-weight:500;margin-bottom:2px;}
.mob-tanar{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;color:white;line-height:1.2;}
.mob-meta{font-size:11px;color:rgba(255,255,255,.38);margin-top:2px;}
.mob-prog{width:4px;flex-shrink:0;background:rgba(255,255,255,.05);position:relative;overflow:hidden;}
.mob-prog-fill{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,#c8972a,#f0c76b);}
.szu-row{display:flex;align-items:center;gap:8px;padding:2px 10px 7px;color:rgba(255,255,255,.18);font-size:10px;}
.szu-line{flex:1;height:1px;background:rgba(255,255,255,.05);}
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
<?php ticky_nav('',''); ?>

<!-- Fejléc -->
<div class="page-header">
  <!-- Ticky visszalink -->
  <div style="margin-bottom:6px;">
    <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.3);font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;" onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.3)'">
      <span style="width:5px;height:5px;border-radius:50%;background:#c8972a;box-shadow:0 0 5px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>Ticky
    </a>
  </div>
  <p style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:3px;">Terem · Heti napirend</p>
  <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px;flex-wrap:wrap;padding-bottom:8px;">
    <h1 class="terem-num" id="terem-cim">–</h1>
    <div id="summary" style="display:flex;gap:6px;flex-wrap:wrap;padding-bottom:4px;">
      <span class="sum-pill skel" style="width:80px;height:26px;"></span>
      <span class="sum-pill skel" style="width:100px;height:26px;"></span>
    </div>
  </div>
  <!-- Idő + refresh sor -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:8px;">
    <span id="header-ido" style="font-size:12px;color:rgba(255,255,255,.3);">–</span>
    <div style="display:flex;align-items:center;gap:8px;">
      <a id="nav-vissza" href="/termek" style="font-size:12px;padding:6px 12px;border-radius:8px;color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);">← Vissza</a>
      <button onclick="refresh()" style="display:flex;align-items:center;gap:4px;padding:6px 10px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;cursor:pointer;">
        <svg id="ri" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Desktop timetable -->
<div class="tt-outer" id="tt-outer">
  <div id="tt-skel" style="min-width:560px;">
    <div style="display:grid;grid-template-columns:42px repeat(5,1fr);gap:5px;margin-bottom:5px;">
      <div></div>
      <div class="skel" style="height:32px;"></div><div class="skel" style="height:32px;"></div>
      <div class="skel" style="height:32px;"></div><div class="skel" style="height:32px;"></div>
      <div class="skel" style="height:32px;"></div>
    </div>
    <div class="skel" style="height:600px;width:100%;"></div>
  </div>
  <div id="tt" class="tt-grid" style="display:none;"></div>
</div>

<!-- Mobile view -->
<div class="mob-section">
  <div class="mob-tabs" id="mob-tabs"></div>
  <div class="mob-list" id="mob-list"></div>
</div>

<script>
const NAP={1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
const START=7*60+30,END=14*60+30,TOTAL=END-START,PPM=1.9
let teremSzam=null,hetData=null,curMob=maiNap(),nowTimer=null
function getTerem(){const p=location.pathname.split('/').filter(Boolean);return(p[0]==='terem'&&p[2]==='nap')?p[1].toUpperCase():null}
function maiNap(){const d=new Date().getDay();return(d===0||d===6)?1:d}
function toMin(t){const[h,m]=t.split(':').map(Number);return h*60+m}
function topPx(m){return Math.max(0,(m-START)*PPM)}
function isAktiv(k,v){const c=new Date().getHours()*60+new Date().getMinutes();return c>=toMin(k)&&c<=toMin(v)}
function isMult(v){return new Date().getHours()*60+new Date().getMinutes()>toMin(v)}
function pct(k,v){const c=new Date().getHours()*60+new Date().getMinutes();return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)))}
function nowM(){return new Date().getHours()*60+new Date().getMinutes()}
function updateTime(){document.getElementById('header-ido').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})}
async function fetchData(){
  try{
    const d=await fetch(`/api/napirend/${encodeURIComponent(teremSzam)}?nap=heten`).then(r=>r.json())
    if(d.error){showErr(d.error);return}
    hetData={};(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]});build()
  }catch(e){showErr('Nem sikerült csatlakozni')}
}
function buildSummary(){
  const mai=maiNap(),orak=hetData[mai]||[],el=document.getElementById('summary')
  if(!orak.length){el.innerHTML=`<span class="sum-pill">📭 Ma nincs óra</span>`;return}
  const akt=orak.find(o=>isAktiv(o.kezdes,o.vegzes)),kov=orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
  let h=`<span class="sum-pill">📚 ${orak.length} óra ma</span><span class="sum-pill">🕐 ${orak[0].kezdes}–${orak[orak.length-1].vegzes}</span>`
  if(akt)h+=`<span class="sum-pill gold">⚡ ${100-pct(akt.kezdes,akt.vegzes)}% van hátra</span>`
  else if(kov)h+=`<span class="sum-pill green">⏭ ${kov.kezdes}</span>`
  else h+=`<span class="sum-pill">✅ Mára vége</span>`
  el.innerHTML=h
}
function buildTT(){
  const mai=maiNap(),el=document.getElementById('tt')
  const ticks=[];for(let m=START;m<=END;m+=30)ticks.push(m)
  let html=`<div class="tt-hdr-time"></div>`
  for(let n=1;n<=5;n++){const isMa=n===mai;html+=`<div class="tt-hdr${isMa?' ma':''}">${NAP[n]}${isMa?' <span style="display:inline-block;width:4px;height:4px;border-radius:50%;background:#c8972a;margin-left:3px;vertical-align:middle;animation:pd 2s infinite;"></span>':''}</div>`}
  let tcol=`<div class="tt-timecol" style="height:${TOTAL*PPM}px;position:relative;">`
  ticks.forEach(m=>{const t=topPx(m);const h=Math.floor(m/60).toString().padStart(2,'0');const mn=(m%60).toString().padStart(2,'0');tcol+=`<span class="tlabel" style="top:${t}px;">${h}:${mn}</span>`})
  tcol+=`</div>`;html+=tcol
  for(let n=1;n<=5;n++){
    const isMa=n===mai,orak=hetData[n]||[]
    let col=`<div class="tt-daycol${isMa?' ma':''}" style="height:${TOTAL*PPM}px;">`
    ticks.forEach(m=>{col+=`<div class="hline${m%60===0?' bold':''}" style="top:${topPx(m)}px;"></div>`})
    if(isMa){const nm=nowM();if(nm>=START&&nm<=END)col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`}
    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes)),h=Math.max(22,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes),mu=isMa&&isMult(o.vegzes),p=ak?pct(o.kezdes,o.vegzes):0
      const nm=o.tanar_nev||o.tanar
      col+=`<div class="ora-blk ${ak?'aktiv':mu?'mult':''}" style="top:${top}px;height:${h}px;" title="${nm} · ${o.osztaly} · ${o.tantargy}"><div class="ob-tanar">${nm}</div>${h>34?`<div class="ob-meta">${o.osztaly} · ${o.tantargy}</div>`:''} ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}</div>`
    })
    col+=`</div>`;html+=col
  }
  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html;el.style.display='grid'
}
function buildMobTabs(){
  const mai=maiNap()
  document.getElementById('mob-tabs').innerHTML=[1,2,3,4,5].map(n=>`<button onclick="setMob(${n})" id="mt${n}" class="mob-tab${n===curMob?' active':''}${n===mai&&n!==curMob?' ma':''}">${NAP[n]}${n===mai?' · Ma':''}</button>`).join('')
}
function setMob(n){curMob=n;[1,2,3,4,5].forEach(k=>{const t=document.getElementById('mt'+k);if(!t)return;const mai=maiNap();t.className='mob-tab'+(k===n?' active':'')+(k===mai&&k!==n?' ma':'')});buildMobList()}
function buildMobList(){
  const el=document.getElementById('mob-list'),orak=hetData?.[curMob]||[],isMa=curMob===maiNap()
  if(!orak.length){el.innerHTML=`<div style="text-align:center;padding:32px 0;"><span style="font-size:32px;">📭</span><p style="color:rgba(255,255,255,.5);margin-top:10px;">Nincs óra ezen a napon</p></div>`;return}
  let h=''
  orak.forEach((o,i)=>{
    const ak=isMa&&isAktiv(o.kezdes,o.vegzes),mu=isMa&&isMult(o.vegzes),p=ak?pct(o.kezdes,o.vegzes):0
    h+=`<div class="mob-row${ak?' aktiv':mu?' mult':''}"><div class="mob-num">${o.ora_sorszam||i+1}</div><div class="mob-body"><div class="mob-ido">${o.kezdes} – ${o.vegzes}</div><div class="mob-tanar">${o.tanar_nev||o.tanar}</div><div class="mob-meta">${o.osztaly} · ${o.tantargy}</div></div><div class="mob-prog"><div class="mob-prog-fill" style="height:${p}%;"></div></div></div>`
    const kov=orak[i+1];if(kov){const sz=toMin(kov.kezdes)-toMin(o.vegzes);if(sz>0)h+=`<div class="szu-row"><div class="szu-line"></div><span>${sz} perc szünet</span><div class="szu-line"></div></div>`}
  })
  el.innerHTML=h
}
function build(){buildSummary();buildTT();buildMobTabs();buildMobList();if(nowTimer)clearInterval(nowTimer);nowTimer=setInterval(()=>{const nl=document.getElementById('now-line');if(nl){const nm=nowM();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'};buildSummary()},60000)}
function showErr(msg){document.getElementById('tt-skel').style.display='none';document.getElementById('tt-outer').innerHTML=`<div style="text-align:center;padding:48px 20px;position:relative;z-index:10;"><span style="font-size:36px;">⚠️</span><p style="color:rgba(255,255,255,.5);margin-top:10px;">${msg}</p></div>`}
function refresh(){const ic=document.getElementById('ri');ic.classList.add('spinning');hetData=null;document.getElementById('tt-skel').style.display='block';document.getElementById('tt').style.display='none';fetchData().finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))}
teremSzam=getTerem()
if(!teremSzam){showErr('Nincs terem megadva · URL: /terem/204/nap')}
else{
  document.getElementById('terem-cim').textContent=teremSzam
  document.getElementById('nav-vissza').href='/terem/'+teremSzam
  document.title='Ticky – '+teremSzam+' napirend'
  updateTime();setInterval(updateTime,60000)
  fetchData();setInterval(fetchData,5*60000)
}
</script>
</body>
</html>
