<?php
$route_match = match_route('/terem/{szam}', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
$current_room = is_array($route_match) ? strtoupper((string) ($route_match['szam'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Terem</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body{font-family:'DM Sans',sans-serif;color:white;min-height:100vh;background-color:#060f1e;transition:background-image .6s ease;
    background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.5) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
  body.szabad{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,138,74,.38) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(26,74,138,.2) 0%,transparent 55%);}
  body.foglalt{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(200,16,46,.32) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.12) 0%,transparent 55%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:40px 40px;}
  .top-line{position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:200;}
  a{text-decoration:none;}
  .glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
  .pulse{animation:pd 2s infinite;}
  @keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .spinning{animation:spin .6s linear;}
  .slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
  @keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
  .skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
  @keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
  .prog-bar{transition:width .6s ease;}
  /* Smooth scroll fix for timetable */
  .tt-wrap{position:relative;z-index:10;max-width:580px;margin:20px auto 0;padding:0 16px;
    overflow-x:auto;-webkit-overflow-scrolling:touch;
    transform:translateZ(0);will-change:transform;overscroll-behavior:contain;}
  .tt-grid{display:grid;grid-template-columns:38px repeat(5,minmax(80px,1fr));min-width:460px;}
  .tt-hdr{padding:7px 4px 9px;text-align:center;font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:rgba(255,255,255,.3);border-bottom:1px solid rgba(255,255,255,.07);}
  .tt-hdr.ma{color:#f0c76b;}
  .tt-hdr-empty{border-bottom:1px solid rgba(255,255,255,.07);}
  .tt-timecol{position:relative;border-right:1px solid rgba(255,255,255,.07);contain:layout style;}
  .tt-daycol{position:relative;border-right:1px solid rgba(255,255,255,.04);overflow:hidden;contain:layout style;}
  .tt-daycol:last-child{border-right:none;}
  .tt-daycol.ma{background:rgba(200,151,42,.025);}
  .hline{position:absolute;left:0;right:0;height:1px;background:rgba(255,255,255,.04);pointer-events:none;}
  .hline.bold{background:rgba(255,255,255,.08);}
  .tlabel{position:absolute;right:4px;font-size:9px;font-weight:500;color:rgba(255,255,255,.22);transform:translateY(-50%);white-space:nowrap;}
  .ora-blk{position:absolute;left:2px;right:2px;border-radius:7px;padding:4px 6px;overflow:hidden;border:1px solid rgba(255,255,255,.07);transition:filter .15s;cursor:default;background:linear-gradient(160deg,rgba(26,74,138,.8),rgba(11,46,89,.85));}
  .ora-blk:hover{filter:brightness(1.2);z-index:20;}
  .ora-blk.aktiv{background:linear-gradient(160deg,rgba(200,151,42,.35),rgba(180,100,20,.3));border-color:rgba(200,151,42,.55);}
  .ora-blk.mult{opacity:.3;}
  .ob-tanar{font-family:'Playfair Display',serif;font-size:11px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;}
  .ob-meta{font-size:9px;color:rgba(255,255,255,.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
  .ora-blk.aktiv .ob-tanar{color:#f0c76b;}
  .ob-prog{position:absolute;bottom:0;left:0;right:0;height:2px;background:rgba(255,255,255,.06);border-radius:0 0 7px 7px;overflow:hidden;}
  .ob-prog-fill{height:100%;background:linear-gradient(90deg,#c8972a,#f0c76b);}
  .now-line{position:absolute;left:0;right:0;height:2px;pointer-events:none;z-index:15;background:linear-gradient(90deg,transparent,#ff6b82 20%,#ff6b82 80%,transparent);}
  .now-dot{position:absolute;left:0;top:-4px;width:8px;height:8px;border-radius:50%;background:#ff6b82;box-shadow:0 0 6px #ff6b82;animation:pd 1.5s infinite;}
  .tt-title{position:relative;z-index:10;max-width:580px;margin:28px auto 0;padding:0 16px;}
  .footer{position:relative;z-index:10;max-width:580px;margin:12px auto 40px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;}
  /* sidebar */
  .ticky-sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:150;
    display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;
    background:rgba(6,15,30,.78);backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,.08);border-left:none;border-radius:0 12px 12px 0;}
  .tsb-item{position:relative;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;text-decoration:none;color:rgba(255,255,255,.6);transition:all .18s;}
  .tsb-item:hover{background:rgba(255,255,255,.10);color:white;}
  .tsb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,15,30,.96);color:rgba(255,255,255,.88);font-size:12px;font-family:'DM Sans',sans-serif;font-weight:500;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;border:1px solid rgba(255,255,255,.10);}
  .tsb-item:hover::after{opacity:1;}
  .tsb-divider{width:20px;height:1px;background:rgba(255,255,255,.10);margin:2px 0;}
  @media(max-width:600px){.ticky-sidebar{display:none;}}
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Bal oldali sidebar -->
<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="tsb-item" data-label="Eseménynaptár">📅</a>
  <div class="tsb-divider"></div>
  <a href="/support" class="tsb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="tsb-item" data-label="Bug report">🐛</a>
</div>

<div class="relative z-10 max-w-sm mx-auto px-4 pt-8 slide-up">
  <div class="glass rounded-2xl overflow-hidden">

    <!-- Ticky link – kártya TETEJÉN -->
    <div class="px-7 pt-4 pb-3 flex items-center justify-between" style="border-bottom:1px solid rgba(255,255,255,.05);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;" onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.35)'">
        <span style="width:6px;height:6px;border-radius:50%;background:#c8972a;box-shadow:0 0 6px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>
        Ticky
      </a>
      <span class="text-xs" style="color:rgba(255,255,255,.25);">Terem nézt</span>
    </div>

    <!-- Terem szám + státusz -->
    <div class="px-7 pt-5 pb-5 flex items-center justify-between gap-3" style="border-bottom:1px solid rgba(255,255,255,.08);">
      <div>
        <p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.3);">Terem</p>
        <h1 id="terem-szam" style="font-family:'Playfair Display',serif;font-size:52px;font-weight:700;color:white;line-height:1;letter-spacing:-1px;">–</h1>
      </div>
      <div id="status-pill" class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold flex-shrink-0" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);">
        <span id="status-dot" class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:rgba(255,255,255,.3);display:inline-block;"></span>
        <span id="status-text">Betöltés…</span>
      </div>
    </div>

    <div class="px-7 py-6" id="content">
      <div class="flex flex-col gap-3">
        <div class="skel h-4 w-2/5"></div>
        <div class="skel h-8 w-3/5"></div>
        <div class="skel h-4 w-full mt-1"></div>
      </div>
    </div>

    <!-- Footer: aktuális idő + frissít -->
    <div class="px-7 py-4 flex items-center justify-between gap-2" style="border-top:1px solid rgba(255,255,255,.08);">
      <span class="text-xs" style="color:rgba(255,255,255,.28);" id="footer-ido">–</span>
      <button onclick="refresh()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs" style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;width:auto;margin-top:0;font-size:12px;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
</div>

<div class="tt-title">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <p style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.28);">Heti órarend</p>
    <a id="napirend-link" href="#" style="font-size:12px;color:#f0c76b;font-weight:500;">Teljes nézet →</a>
  </div>
  <div id="tt-skel" style="display:flex;gap:6px;">
    <div class="skel" style="width:38px;height:500px;border-radius:8px;flex-shrink:0;"></div>
    <div class="skel" style="flex:1;height:500px;border-radius:8px;"></div>
    <div class="skel" style="flex:1;height:500px;border-radius:8px;"></div>
    <div class="skel" style="flex:1;height:500px;border-radius:8px;"></div>
    <div class="skel" style="flex:1;height:500px;border-radius:8px;"></div>
    <div class="skel" style="flex:1;height:500px;border-radius:8px;"></div>
  </div>
</div>

<div class="tt-wrap">
  <div id="tt" class="tt-grid" style="display:none;"></div>
</div>

<div class="footer">
  <span style="font-size:11px;color:rgba(255,255,255,.2);" id="footer-ido2">–</span>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const{formatHM,nowMinutes,schoolDayIndex}=window.TickyTime
const REFRESH_MS=60_000
const NAP={1:'H',2:'K',3:'Sze',4:'Cs',5:'P'}
const START=7*60+30,END=14*60+30,TOTAL=END-START,PPM=1.8,H=TOTAL*PPM
let teremSzam=null,hetData=null

function updateTime(){
  const t=formatHM()
  document.getElementById('footer-ido').textContent=t
  document.getElementById('footer-ido2').textContent=t
}

function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function roomPath(v){return encodeURIComponent(String(v??''))}
function getTerem(){const p=location.pathname.split('/').filter(Boolean);const q=new URLSearchParams(location.search).get('terem');if(p[0]==='terem'&&p[1])return p[1].toUpperCase();if(q)return q.toUpperCase();return null}
function maiNap(){return schoolDayIndex()}
function toMin(t){const[h,m]=t.split(':').map(Number);return h*60+m}
function topPx(m){return Math.max(0,(m-START)*PPM)}
function isAktiv(k,v){const c=nowMinutes();return c>=toMin(k)&&c<=toMin(v)}
function isMult(v){return nowMinutes()>toMin(v)}
function calcPct(k,v){const c=nowMinutes();return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)))}
function nowM(){return nowMinutes()}
function setAllapot(a){
  const pill=document.getElementById('status-pill'),dot=document.getElementById('status-dot'),txt=document.getElementById('status-text')
  if(a==='foglalt'){document.body.className='foglalt';pill.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:9999px;font-size:14px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;';dot.style.background='#ff6b82';txt.textContent='FOGLALT'}
  else{document.body.className='szabad';pill.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:9999px;font-size:14px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;';dot.style.background='#4ade80';txt.textContent='SZABAD'}
}
function kovHtml(k){
  if(!k)return`<div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);"><p class="text-xs font-semibold tracking-widest uppercase mb-1" style="color:rgba(255,255,255,.25);">Következő óra</p><p class="text-sm" style="color:rgba(255,255,255,.35);">Ma már nincs több óra</p></div>`
  return`<div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);"><p class="text-xs font-semibold tracking-widest uppercase mb-1.5" style="color:rgba(255,255,255,.25);">Következő óra</p><div class="flex items-center justify-between gap-2 flex-wrap"><span class="text-sm font-medium" style="color:rgba(255,255,255,.7);">${esc(k.tanar)} · ${esc(k.osztaly)} · ${esc(k.tantargy)}</span><span class="text-xs" style="color:rgba(255,255,255,.35);">${esc(k.kezdes)}–${esc(k.vegzes)}</span></div></div>`
}
function renderStatus(data){
  setAllapot(data.allapot)
  const el=document.getElementById('content')
  if(data.allapot==='szabad'){
    el.innerHTML=`<div class="text-center py-3"><span class="text-5xl block mb-3">✅</span><p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#4ade80;" class="mb-1">Szabad terem</p><p class="text-sm" style="color:rgba(255,255,255,.4);">Nincs aktív foglalás</p></div>${kovHtml(data.kovetkezo)}`
  }else{
    const a=data.aktualis,pct=calcPct(a.kezdes,a.vegzes)
    el.innerHTML=`<div class="flex flex-col gap-4"><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tanár</p><p style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:white;line-height:1.2;">${esc(a.tanar_nev||a.tanar)}</p></div><div class="grid grid-cols-2 gap-3"><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${esc(a.osztaly)}</p></div><div><p class="text-xs font-semibold tracking-widest uppercase mb-0.5" style="color:rgba(255,255,255,.3);">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;">${esc(a.tantargy)}</p></div></div><div><div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.08);"><div class="h-full rounded-full prog-bar" style="width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div><div class="flex justify-between mt-1.5 text-xs" style="color:rgba(255,255,255,.35);"><span>${esc(a.kezdes)}</span><span style="color:#ff6b82;font-weight:600;">még ${esc(a.perc_maradt)} perc</span><span>${esc(a.vegzes)}</span></div></div></div>${kovHtml(data.kovetkezo)}`
  }
}
function buildTT(){
  const mai=maiNap(),el=document.getElementById('tt')
  const ticks=[];for(let m=START;m<=END;m+=30)ticks.push(m)
  let html=`<div class="tt-hdr-empty"></div>`
  for(let n=1;n<=5;n++)html+=`<div class="tt-hdr${n===mai?' ma':''}">${NAP[n]}${n===mai?'<span style="display:inline-block;width:4px;height:4px;border-radius:50%;background:#c8972a;margin-left:3px;vertical-align:middle;animation:pd 2s infinite;"></span>':''}</div>`
  let tc=`<div class="tt-timecol" style="height:${H}px;position:relative;">`
  ticks.forEach(m=>{const top=topPx(m);const hh=Math.floor(m/60).toString().padStart(2,'0');const mm=(m%60).toString().padStart(2,'0');tc+=`<span class="tlabel" style="top:${top}px;">${hh}:${mm}</span>`})
  tc+=`</div>`;html+=tc
  for(let n=1;n<=5;n++){
    const isMa=n===mai,orak=hetData[n]||[]
    let col=`<div class="tt-daycol${isMa?' ma':''}" style="height:${H}px;">`
    ticks.forEach(m=>{col+=`<div class="hline${m%60===0?' bold':''}" style="top:${topPx(m)}px;"></div>`})
    if(isMa){const nm=nowM();if(nm>=START&&nm<=END)col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`}
    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes)),h=Math.max(20,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes),mu=isMa&&isMult(o.vegzes),p=ak?calcPct(o.kezdes,o.vegzes):0
      col+=`<div class="ora-blk${ak?' aktiv':mu?' mult':''}" style="top:${top}px;height:${h}px;" title="${esc(o.tanar_nev||o.tanar)} · ${esc(o.osztaly)} · ${esc(o.tantargy)} · ${esc(o.kezdes)}–${esc(o.vegzes)}"><div class="ob-tanar">${esc(o.tanar_nev||o.tanar)}</div>${h>32?`<div class="ob-meta">${esc(o.osztaly)} · ${esc(o.tantargy)}</div>`:''} ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}</div>`
    })
    col+=`</div>`;html+=col
  }
  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html;el.style.display='grid'
  setInterval(()=>{const nl=document.getElementById('now-line');if(nl){const nm=nowM();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'}},60_000)
}
async function fetchStatus(){
  try{const d=await fetch(`/api/terem/${roomPath(teremSzam)}`).then(r=>r.json());if(!d.error){document.getElementById('terem-szam').textContent=d.terem;renderStatus(d)}}catch(e){}
  updateTime()
}
async function fetchTimetable(){
  try{const d=await fetch(`/api/napirend/${roomPath(teremSzam)}?nap=heten`).then(r=>r.json());if(d.error)return;hetData={};(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]});buildTT()}
  catch(e){document.getElementById('tt-skel').style.display='none'}
}
function refresh(){const ic=document.getElementById('ri');ic.classList.add('spinning');Promise.all([fetchStatus(),fetchTimetable()]).finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))}
teremSzam=getTerem()
if(!teremSzam){
  document.getElementById('terem-szam').textContent='?'
  document.getElementById('content').innerHTML=`<div class="text-center py-6"><span class="text-4xl block mb-3">🔍</span><p style="color:rgba(255,255,255,.6);">Nincs terem megadva</p><p class="text-sm mt-1" style="color:rgba(255,255,255,.35);">URL: /terem/204</p></div>`
}else{
  document.getElementById('terem-szam').textContent=teremSzam
  document.getElementById('napirend-link').href='/terem/'+roomPath(teremSzam)+'/nap'
  document.title='Ticky – '+teremSzam
  updateTime()
  fetchStatus();fetchTimetable()
  setInterval(fetchStatus,REFRESH_MS);setInterval(fetchTimetable,5*60_000)
  setInterval(updateTime,60_000)
}
</script>
</body>
</html>
