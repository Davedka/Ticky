<?php require_once __DIR__ . '/../utils/helpers.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Terem</title>
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" href="/favicon.png" type="image/png" sizes="64x64">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#050b15;
    --gold:#c8972a; --gold-l:#f0c76b; --gold-soft:rgba(200,151,42,.14);
    --green:#34d399; --green-txt:#6ee7b7; --green-soft:rgba(52,211,153,.13);
    --red:#fb7185; --red-deep:#e8334a; --red-soft:rgba(244,63,94,.13);
    --line:rgba(96,150,220,.16); --line-strong:rgba(96,150,220,.30);
    --surface:rgba(255,255,255,.022); --surface-2:rgba(255,255,255,.05);
    --border:rgba(255,255,255,.09); --border-strong:rgba(255,255,255,.17);
    --text:rgba(255,255,255,.93); --dim:rgba(255,255,255,.55); --faint:rgba(255,255,255,.34);
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);min-height:100vh;overflow-x:hidden;}

  .bp{position:fixed;inset:0;z-index:0;pointer-events:none;}
  .bp::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 65% 55% at 12% -8%,rgba(34,86,156,.45) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 92% 108%,rgba(200,151,42,.10) 0%,transparent 55%);}
  .bp::after{content:'';position:absolute;inset:0;background-image:linear-gradient(var(--line) 1px,transparent 1px),linear-gradient(90deg,var(--line) 1px,transparent 1px),linear-gradient(var(--line-strong) 1px,transparent 1px),linear-gradient(90deg,var(--line-strong) 1px,transparent 1px);background-size:28px 28px,28px 28px,140px 140px,140px 140px;mask-image:radial-gradient(ellipse 110% 95% at 50% 30%,#000 55%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 110% 95% at 50% 30%,#000 55%,transparent 100%);opacity:.6;}
  .vig{position:fixed;inset:0;z-index:1;pointer-events:none;box-shadow:inset 0 0 200px 40px rgba(0,0,0,.55);}
  .grain{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.05;mix-blend-mode:overlay;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");}
  .reg{position:fixed;z-index:2;pointer-events:none;width:26px;height:26px;opacity:.5;}
  .reg::before,.reg::after{content:'';position:absolute;background:var(--gold);}
  .reg::before{left:50%;top:0;bottom:0;width:1px;transform:translateX(-.5px);}
  .reg::after{top:50%;left:0;right:0;height:1px;transform:translateY(-.5px);}
  .reg.tl{top:74px;left:20px;} .reg.tr{top:74px;right:20px;} .reg.bl{bottom:24px;left:20px;} .reg.br{bottom:24px;right:20px;}
  .topline{position:fixed;top:0;left:0;right:0;height:2px;z-index:60;background:linear-gradient(90deg,transparent,var(--gold) 28%,var(--gold-l) 50%,var(--gold) 72%,transparent);box-shadow:0 0 18px rgba(200,151,42,.4);}
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.7)}}
  @keyframes spin{to{transform:rotate(360deg)}} .spinning{animation:spin .6s linear;}
  a{text-decoration:none;}

  .nav{position:sticky;top:0;z-index:50;height:62px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;background:rgba(5,11,21,.74);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-bottom:1px solid var(--border);}
  .brand{display:flex;align-items:center;gap:9px;font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#fff;}
  .brand .bd{width:8px;height:8px;border-radius:50%;background:var(--gold);box-shadow:0 0 12px var(--gold);animation:pulse 2.4s infinite;}
  .crumb{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--dim);} .crumb svg{width:15px;height:15px;color:var(--faint);}
  .sep{color:rgba(255,255,255,.18);}
  .navbtn{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:500;color:var(--dim);background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px 13px;cursor:pointer;transition:all .15s;}
  .navbtn:hover{color:var(--text);background:var(--surface-2);} .navbtn svg{width:14px;height:14px;}

  .wrap{position:relative;z-index:10;max-width:1080px;margin:0 auto;padding:28px 24px 84px;}

  .titleblock{position:relative;display:grid;grid-template-columns:1fr auto;gap:20px;border:1px solid var(--border-strong);border-radius:18px;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.012));margin-bottom:16px;animation:fade .6s ease both;}
  @keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
  .tb-left{padding:22px 24px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;}
  .tb-tag{display:inline-flex;align-items:center;gap:8px;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.12em;color:var(--gold-l);text-transform:uppercase;margin-bottom:10px;}
  .tb-tag .ln{width:24px;height:1px;background:var(--gold);}
  .tb-room{font-family:'Playfair Display',serif;font-weight:900;font-size:clamp(52px,11vw,100px);line-height:.85;letter-spacing:-3px;color:#fff;}
  .statuswrap{display:flex;flex-direction:column;gap:10px;}
  .bigpill{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:.05em;background:var(--surface);border:1px solid var(--border);color:var(--dim);}
  .bigpill .pd{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.3);animation:pulse 2.2s infinite;}
  .bigpill.busy{color:var(--red);background:var(--red-soft);border-color:rgba(244,63,94,.4);} .bigpill.busy .pd{background:var(--red);}
  .bigpill.free{color:var(--green-txt);background:var(--green-soft);border-color:rgba(52,211,153,.4);} .bigpill.free .pd{background:var(--green);}
  .clockcol{padding:20px 24px;display:flex;flex-direction:column;justify-content:center;gap:6px;background:rgba(200,151,42,.05);border-left:1px solid var(--border);}
  .clk{font-family:'DM Mono',monospace;font-size:30px;letter-spacing:.04em;color:#fff;line-height:1;} .clk .s{color:var(--gold-l);opacity:.7;font-size:20px;}
  .upd{font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.06em;color:var(--faint);text-transform:uppercase;display:flex;align-items:center;gap:7px;}
  .upd .rd{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);}

  /* now / next strip */
  .strip{display:grid;grid-template-columns:1.4fr 1fr;gap:14px;margin-bottom:24px;}
  .panel{border:1px solid var(--border);border-radius:16px;padding:18px 20px;background:var(--surface);position:relative;overflow:hidden;animation:fade .45s ease both;}
  .panel::before{content:'';position:absolute;inset:0 0 auto 0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.12),transparent);}
  .panel.now{background:linear-gradient(160deg,rgba(244,63,94,.08),var(--surface) 60%);border-color:rgba(244,63,94,.22);box-shadow:0 14px 38px -22px rgba(232,51,74,.6);}
  .panel.free{background:linear-gradient(160deg,rgba(52,211,153,.06),var(--surface) 60%);border-color:rgba(52,211,153,.2);}
  .p-lbl{font-family:'DM Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--faint);margin-bottom:12px;}
  .p-main{font-family:'Playfair Display',serif;font-weight:800;font-size:26px;line-height:1.05;color:#fff;}
  .p-meta{font-size:13px;color:var(--dim);margin-top:6px;} .p-meta .mono{font-family:'DM Mono',monospace;}
  .prog{height:4px;border-radius:99px;background:rgba(255,255,255,.09);overflow:hidden;margin-top:14px;}
  .prog>span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--red-deep),var(--red));box-shadow:0 0 10px rgba(232,51,74,.5);}
  .prog-meta{display:flex;justify-content:space-between;font-family:'DM Mono',monospace;font-size:11px;color:var(--faint);margin-top:7px;} .prog-meta .rem{color:var(--red);font-weight:500;}

  /* timetable */
  .tt-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap;}
  .tt-h-l{font-family:'DM Mono',monospace;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--gold-l);display:flex;align-items:center;gap:8px;} .tt-h-l .ln{width:24px;height:1px;background:var(--gold);}
  .ttcard{border:1px solid var(--border);border-radius:18px;padding:6px 10px 14px;background:var(--surface);overflow-x:auto;}
  .tt{display:grid;grid-template-columns:50px repeat(5,minmax(108px,1fr));min-width:660px;}
  .tt-hdr{padding:12px 6px;text-align:center;font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--dim);border-bottom:1px solid var(--border);}
  .tt-hdr.ma{color:var(--gold-l);}
  .tt-hdr .md{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--gold);margin-left:5px;vertical-align:middle;animation:pulse 2s infinite;}
  .tt-corner{border-bottom:1px solid var(--border);}
  .tt-time{position:relative;border-right:1px solid var(--border);}
  .tt-col{position:relative;border-right:1px solid rgba(255,255,255,.045);overflow:hidden;} .tt-col:last-child{border-right:none;} .tt-col.ma{background:rgba(200,151,42,.03);}
  .gl{position:absolute;left:0;right:0;height:1px;background:var(--line);pointer-events:none;} .gl.bold{background:var(--line-strong);}
  .tl{position:absolute;right:6px;font-family:'DM Mono',monospace;font-size:10px;color:var(--faint);transform:translateY(-50%);}
  .blk{position:absolute;left:3px;right:3px;border-radius:9px;padding:6px 8px;overflow:hidden;border:1px solid rgba(255,255,255,.08);background:linear-gradient(160deg,rgba(34,86,156,.55),rgba(11,38,72,.6));transition:filter .15s,transform .15s;}
  .blk:hover{filter:brightness(1.25);transform:scale(1.015);z-index:5;}
  .blk.aktiv{background:linear-gradient(160deg,rgba(200,151,42,.4),rgba(180,110,20,.32));border-color:rgba(200,151,42,.6);box-shadow:0 0 22px -6px rgba(200,151,42,.6);}
  .blk.mult{opacity:.32;}
  .ob-tanar{font-family:'Playfair Display',serif;font-size:12px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.15;}
  .blk.aktiv .ob-tanar{color:var(--gold-l);}
  .ob-meta{font-size:9px;color:rgba(255,255,255,.5);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
  .ob-prog{position:absolute;bottom:0;left:0;right:0;height:2px;background:rgba(255,255,255,.1);overflow:hidden;} .ob-prog-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-l));}
  .now-line{position:absolute;left:0;right:0;height:2px;z-index:8;pointer-events:none;background:linear-gradient(90deg,transparent,var(--red) 18%,var(--red) 82%,transparent);}
  .now-dot{position:absolute;left:0;top:-4px;width:9px;height:9px;border-radius:50%;background:var(--red);box-shadow:0 0 9px var(--red);animation:pulse 1.6s infinite;}

  .skel{background:linear-gradient(90deg,rgba(255,255,255,.05) 25%,rgba(255,255,255,.09) 50%,rgba(255,255,255,.05) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:8px;}
  @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

  @media(max-width:760px){.titleblock{grid-template-columns:1fr;}.clockcol{border-left:none;border-top:1px solid var(--border);}.strip{grid-template-columns:1fr;}}
  @media (prefers-reduced-motion: reduce){*{animation:none!important;}}
</style>
</head>
<body>
<div class="bp"></div><div class="vig"></div><div class="grain"></div><div class="topline"></div>
<span class="reg tl"></span><span class="reg tr"></span><span class="reg bl"></span><span class="reg br"></span>

<nav class="nav">
  <div style="display:flex;align-items:center;gap:13px;min-width:0;">
    <a href="/" class="brand"><span class="bd"></span>Ticky</a>
    <span class="sep">·</span>
    <span class="crumb">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/></svg>
      Terem
    </span>
  </div>
  <a class="navbtn" href="/termek">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    Összes terem
  </a>
</nav>

<div class="wrap">

  <!-- Title block: terem + státusz + óra -->
  <section class="titleblock">
    <div class="tb-left">
      <div>
        <div class="tb-tag"><span class="ln"></span>Terem · élő állapot</div>
        <div class="tb-room" id="terem-szam">–</div>
      </div>
      <div class="statuswrap">
        <span id="status-pill" class="bigpill"><span id="status-dot" class="pd"></span><span id="status-text">Betöltés…</span></span>
      </div>
    </div>
    <div class="clockcol">
      <div class="clk"><span id="clk-hm">––:––</span><span class="s">:<span id="clk-ss">00</span></span></div>
      <div class="upd"><span class="rd"></span>Frissítve <span id="footer-ido">–</span></div>
    </div>
  </section>

  <!-- Most / Következő -->
  <div id="content">
    <div class="strip">
      <div class="panel"><div class="p-lbl">Most</div><div class="skel" style="height:24px;width:70%;margin-top:6px"></div><div class="skel" style="height:14px;width:50%;margin-top:10px"></div></div>
      <div class="panel"><div class="p-lbl">Következő óra</div><div class="skel" style="height:18px;width:80%;margin-top:6px"></div></div>
    </div>
  </div>

  <!-- Heti órarend -->
  <div class="tt-head">
    <div class="tt-h-l"><span class="ln"></span>Heti órarend</div>
    <div style="display:flex;align-items:center;gap:10px;">
      <a id="napirend-link" href="#" class="navbtn">Teljes nézet →</a>
      <button class="navbtn" onclick="refresh()">
        <svg id="refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
  <div class="ttcard">
    <div id="tt-skel" style="display:flex;gap:6px;min-width:660px;">
      <div class="skel" style="width:50px;height:560px;flex-shrink:0;"></div>
      <div class="skel" style="flex:1;height:560px;"></div><div class="skel" style="flex:1;height:560px;"></div>
      <div class="skel" style="flex:1;height:560px;"></div><div class="skel" style="flex:1;height:560px;"></div>
      <div class="skel" style="flex:1;height:560px;"></div>
    </div>
    <div id="tt" class="tt" style="display:none;"></div>
  </div>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const REFRESH_MS = 60_000
const NAP   = {1:'H',2:'K',3:'Sze',4:'Cs',5:'P'}
const NAP_T = {1:'Hétfő',2:'Kedd',3:'Szerda',4:'Csütörtök',5:'Péntek'}
const START = 7*60+30
const END   = 14*60+30
const TOTAL = END-START   // 420 perc
const PPM   = 1.8         // px/perc
const H     = TOTAL*PPM

let teremSzam = null
let hetData   = null

function getTerem() {
  const p=location.pathname.split('/').filter(Boolean)
  const q=new URLSearchParams(location.search).get('terem')
  if(p[0]==='terem'&&p[1]) return p[1].toUpperCase()
  if(q) return q.toUpperCase()
  return null
}
function maiNap() {
  if (window.TickyTime) return window.TickyTime.schoolDayIndex()
  const d=new Date().getDay()
  return(d===0||d===6)?1:d
}
function toMin(t) { const[h,m]=t.split(':').map(Number); return h*60+m }
function topPx(m) { return Math.max(0,(m-START)*PPM) }
function nowMin() { return window.TickyTime ? window.TickyTime.nowMinutes() : (new Date().getHours()*60+new Date().getMinutes()) }
function isAktiv(k,v) { const c=nowMin(); return c>=toMin(k)&&c<=toMin(v) }
function isMult(v) { return nowMin()>toMin(v) }
function calcPct(k,v) { const c=nowMin(); return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100))) }
function nowM() { return nowMin() }

// ── Státusz pill ─────────────────────────────────────
function setAllapot(a) {
  const pill=document.getElementById('status-pill')
  const txt=document.getElementById('status-text')
  if(a==='foglalt'){
    pill.className='bigpill busy'; txt.textContent='FOGLALT'
  } else {
    pill.className='bigpill free'; txt.textContent='SZABAD'
  }
}

function kovHtml(k) {
  if(!k) return `<div class="panel next"><div class="p-lbl">Következő óra</div><div class="p-main" style="font-size:18px;color:rgba(255,255,255,.7);">Ma már nincs több óra</div></div>`
  return `<div class="panel next"><div class="p-lbl">Következő óra</div><div class="p-main" style="font-size:18px;">${k.tanar} · ${k.osztaly}</div><div class="p-meta">${k.tantargy} · <span class="mono">${k.kezdes}–${k.vegzes}</span></div></div>`
}

function renderStatus(data) {
  setAllapot(data.allapot)
  const el=document.getElementById('content')
  if(data.allapot==='szabad'){
    el.innerHTML=`<div class="strip">
      <div class="panel free"><div class="p-lbl">Most</div><div class="p-main" style="color:var(--green-txt);">Szabad terem</div><div class="p-meta">Nincs aktív foglalás</div></div>
      ${kovHtml(data.kovetkezo)}
    </div>`
  } else {
    const a=data.aktualis
    const pct=calcPct(a.kezdes,a.vegzes)
    el.innerHTML=`<div class="strip">
      <div class="panel now">
        <div class="p-lbl">Most itt zajlik</div>
        <div class="p-main">${a.tanar_nev||a.tanar}</div>
        <div class="p-meta">${a.osztaly} · ${a.tantargy} · <span class="mono">${a.kezdes}–${a.vegzes}</span></div>
        <div class="prog" style="height:5px;"><span style="width:${pct}%;"></span></div>
        <div class="prog-meta"><span>${a.kezdes}</span><span class="rem">még ${a.perc_maradt} perc</span><span>${a.vegzes}</span></div>
      </div>
      ${kovHtml(data.kovetkezo)}
    </div>`
  }
}

// ── Timetable ────────────────────────────────────────
function buildTT() {
  const mai=maiNap()
  const el=document.getElementById('tt')
  const ticks=[]
  for(let m=START;m<=END;m+=30) ticks.push(m)
  let html=''

  // Fejléc
  html+=`<div class="tt-corner"></div>`
  for(let n=1;n<=5;n++){
    html+=`<div class="tt-hdr${n===mai?' ma':''}">${NAP[n]}${n===mai?'<span class="md"></span>':''}</div>`
  }

  // Idő oszlop
  let tc=`<div class="tt-time" style="height:${H}px;position:relative;">`
  ticks.forEach(m=>{
    const top=topPx(m)
    const hh=Math.floor(m/60).toString().padStart(2,'0')
    const mm=(m%60).toString().padStart(2,'0')
    tc+=`<span class="tl" style="top:${top}px;">${hh}:${mm}</span>`
  })
  tc+=`</div>`
  html+=tc

  // Nap oszlopok
  for(let n=1;n<=5;n++){
    const isMa=n===mai
    const orak=hetData[n]||[]
    let col=`<div class="tt-col${isMa?' ma':''}" style="height:${H}px;">`

    ticks.forEach(m=>{
      col+=`<div class="gl${m%60===0?' bold':''}" style="top:${topPx(m)}px;"></div>`
    })

    if(isMa){
      const nm=nowM()
      if(nm>=START&&nm<=END){
        col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`
      }
    }

    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes))
      const h=Math.max(20,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes)
      const mu=isMa&&isMult(o.vegzes)
      const p=ak?calcPct(o.kezdes,o.vegzes):0
      const osztKod=(o.osztaly||'').split('/')[0].trim()
      const href=osztKod?'/osztaly/'+encodeURIComponent(osztKod):'#'
      col+=`<a href="${href}" class="blk${ak?' aktiv':mu?' mult':''}" style="top:${top}px;height:${h}px;text-decoration:none;"
        title="${o.tanar_nev||o.tanar} · ${o.osztaly} · ${o.tantargy} · ${o.kezdes}–${o.vegzes} – Klikk: osztály nézet">
        <div class="ob-tanar">${o.tanar_nev||o.tanar}</div>
        ${h>32?`<div class="ob-meta">${o.osztaly} · ${o.tantargy}</div>`:''}
        ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}
      </a>`
    })
    col+=`</div>`
    html+=col
  }

  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html
  el.style.display='grid'

  // Now-line frissítés
  setInterval(()=>{
    const nl=document.getElementById('now-line')
    if(nl){const nm=nowM();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'}
  },60_000)
}

// ── Fetch ────────────────────────────────────────────
async function fetchStatus() {
  try {
    const d=await fetch(`/api/terem/${teremSzam}`).then(r=>r.json())
    if(!d.error){
      document.getElementById('terem-szam').textContent=d.terem||teremSzam
      if(d.szunet){
        setAllapot('szabad')
        document.getElementById('content').innerHTML=`<div class="strip"><div class="panel" style="grid-column:1/-1;text-align:center;padding:28px 0;"><div style="font-size:40px;margin-bottom:10px;">🌙</div><p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:var(--gold-l);margin-bottom:4px;">${d.szunet}</p><p style="font-size:13px;color:var(--faint);">Szünet idején nincs tanítás</p></div></div>`
      } else {
        renderStatus(d)
      }
    }
  } catch(e){}
  const t=window.TickyTime ? window.TickyTime.formatHM() : new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})
  document.getElementById('footer-ido').textContent=t
}

async function fetchTimetable() {
  try {
    const d=await fetch(`/api/napirend/${teremSzam}?nap=heten`).then(r=>r.json())
    if(d.error) return
    hetData={}
    ;(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]})
    buildTT()
  } catch(e){
    document.getElementById('tt-skel').style.display='none'
  }
}

function refresh() {
  const ic=document.getElementById('refresh-icon'); ic.classList.add('spinning')
  Promise.all([fetchStatus(), fetchTimetable()])
    .finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))
}

// ── Élő óra (csak megjelenítés) ──────────────────────
function updateClock(){
  const hm=document.getElementById('clk-hm'); if(!hm) return
  let s
  if(window.TickyTime){ s=window.TickyTime.formatHMS() }
  else { const n=new Date(); const p=x=>String(x).padStart(2,'0'); s=p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds()) }
  const parts=String(s).split(':')
  hm.textContent=(parts[0]||'--')+':'+(parts[1]||'--')
  const ss=document.getElementById('clk-ss'); if(ss) ss.textContent=parts[2]||'00'
}

// ── Init ─────────────────────────────────────────────
teremSzam=getTerem()
if(!teremSzam){
  document.getElementById('terem-szam').textContent='?'
  document.getElementById('content').innerHTML=`<div class="strip"><div class="panel" style="grid-column:1/-1;text-align:center;padding:24px 0;"><span style="font-size:36px;display:block;margin-bottom:10px;">🔍</span><p style="color:var(--dim);">Nincs terem megadva</p><p style="font-size:13px;margin-top:4px;color:var(--faint);">URL: /terem/204</p></div></div>`
} else {
  document.getElementById('terem-szam').textContent=teremSzam
  document.getElementById('napirend-link').href='/terem/'+teremSzam+'/nap'
  document.title='Ticky – '+teremSzam
  fetchStatus()
  fetchTimetable()
  updateClock()
  setInterval(updateClock,1000)
  setInterval(fetchStatus, REFRESH_MS)
  setInterval(fetchTimetable, 5*60_000)
}
</script>
</body>
</html>
