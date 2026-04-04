<?php
$route_match = match_route('/terem/{szam}', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
$current_room = is_array($route_match) ? strtoupper((string)($route_match['szam'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Terem</title>
<link rel="icon" type="image/png" href="/favicon.png?v=<?= filemtime('favicon.png') ?>">
<link rel="shortcut icon" href="/favicon.ico?v=<?= filemtime('favicon.ico') ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;color:white;min-height:100vh;background-color:#060f1e;transition:background-image .6s;
  background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.5) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
body.szabad{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,138,74,.38) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(26,74,138,.2) 0%,transparent 55%);}
body.foglalt{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(200,16,46,.32) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.12) 0%,transparent 55%);}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:40px 40px;}
a{text-decoration:none;color:inherit;}
.glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
.pulse{animation:pd 2s infinite;}
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinning{animation:spin .6s linear;}
.slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
.prog-bar{transition:width .6s;}
/* Timetable smooth scroll */
.tt-wrap{position:relative;z-index:10;max-width:600px;margin:20px auto 0;padding:0 16px;overflow-x:auto;-webkit-overflow-scrolling:touch;transform:translateZ(0);will-change:transform;overscroll-behavior:contain;}
.tt-grid{display:grid;grid-template-columns:38px repeat(5,minmax(72px,1fr));min-width:430px;}
.tt-hdr{padding:6px 3px 8px;text-align:center;font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:rgba(255,255,255,.3);border-bottom:1px solid rgba(255,255,255,.07);}
.tt-hdr.ma{color:#f0c76b;}
.tt-hdr-empty{border-bottom:1px solid rgba(255,255,255,.07);}
.tt-timecol{position:relative;border-right:1px solid rgba(255,255,255,.07);contain:layout style;}
.tt-daycol{position:relative;border-right:1px solid rgba(255,255,255,.04);overflow:hidden;contain:layout style;}
.tt-daycol:last-child{border-right:none;}
.tt-daycol.ma{background:rgba(200,151,42,.025);}
.hline{position:absolute;left:0;right:0;height:1px;background:rgba(255,255,255,.04);pointer-events:none;}
.hline.bold{background:rgba(255,255,255,.08);}
.tlabel{position:absolute;right:3px;font-size:9px;font-weight:500;color:rgba(255,255,255,.22);transform:translateY(-50%);white-space:nowrap;}
.ora-blk{position:absolute;left:2px;right:2px;border-radius:6px;padding:3px 5px;overflow:hidden;border:1px solid rgba(255,255,255,.07);transition:filter .15s;cursor:default;background:linear-gradient(160deg,rgba(26,74,138,.8),rgba(11,46,89,.85));}
.ora-blk:hover{filter:brightness(1.2);z-index:20;}
.ora-blk.aktiv{background:linear-gradient(160deg,rgba(200,151,42,.35),rgba(180,100,20,.3));border-color:rgba(200,151,42,.55);}
.ora-blk.mult{opacity:.3;}
.ob-tanar{font-family:'Playfair Display',serif;font-size:10px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;}
.ob-meta{font-size:8px;color:rgba(255,255,255,.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.ora-blk.aktiv .ob-tanar{color:#f0c76b;}
.ob-prog{position:absolute;bottom:0;left:0;right:0;height:2px;background:rgba(255,255,255,.06);border-radius:0 0 6px 6px;overflow:hidden;}
.ob-prog-fill{height:100%;background:linear-gradient(90deg,#c8972a,#f0c76b);}
.now-line{position:absolute;left:0;right:0;height:2px;pointer-events:none;z-index:15;background:linear-gradient(90deg,transparent,#ff6b82 20%,#ff6b82 80%,transparent);}
.now-dot{position:absolute;left:0;top:-4px;width:8px;height:8px;border-radius:50%;background:#ff6b82;box-shadow:0 0 6px #ff6b82;animation:pd 1.5s infinite;}
.tt-header-row{position:relative;z-index:10;max-width:600px;margin:28px auto 0;padding:0 16px;display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.page-wrap{position:relative;z-index:10;max-width:440px;margin:0 auto;padding:24px 16px 0;}
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

<div class="page-wrap slide-up">
  <div class="glass" style="border-radius:18px;overflow:hidden;">

    <!-- Ticky tetején -->
    <div style="padding:14px 20px 0;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.05);">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;padding-bottom:12px;" onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.35)'">
        <span style="width:6px;height:6px;border-radius:50%;background:#c8972a;box-shadow:0 0 6px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>Ticky
      </a>
      <span style="font-size:11px;color:rgba(255,255,255,.25);padding-bottom:12px;">Terem nézet</span>
    </div>

    <!-- Terem + státusz -->
    <div style="padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:10px;">
      <div>
        <p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:4px;">Terem</p>
        <h1 id="terem-szam" style="font-family:'Playfair Display',serif;font-size:clamp(40px,12vw,56px);font-weight:700;color:white;line-height:1;letter-spacing:-1px;">–</h1>
      </div>
      <div id="status-pill" style="display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:99px;font-size:12px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;">
        <span id="status-dot" style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.3);display:inline-block;animation:pd 2s infinite;"></span>
        <span id="status-text">Betöltés…</span>
      </div>
    </div>

    <!-- Tartalom -->
    <div id="content" style="padding:20px;">
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div class="skel" style="height:12px;width:40%;"></div>
        <div class="skel" style="height:24px;width:60%;"></div>
        <div class="skel" style="height:12px;width:100%;"></div>
      </div>
    </div>

    <!-- Footer: idő + frissít -->
    <div style="padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:12px;color:rgba(255,255,255,.28);" id="footer-ido">–</span>
      <button onclick="refresh()" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;cursor:pointer;">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissít
      </button>
    </div>
  </div>
</div>

<!-- Heti órarend fejléc -->
<div class="tt-header-row">
  <p style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.28);">Heti órarend</p>
  <a id="napirend-link" href="#" style="font-size:12px;color:#f0c76b;font-weight:500;text-decoration:none;">Teljes nézet →</a>
</div>

<!-- Skeleton -->
<div id="tt-skel" style="max-width:600px;margin:0 auto;padding:0 16px;display:flex;gap:5px;">
  <div class="skel" style="width:38px;height:460px;border-radius:8px;flex-shrink:0;"></div>
  <div class="skel" style="flex:1;height:460px;border-radius:8px;"></div>
  <div class="skel" style="flex:1;height:460px;border-radius:8px;"></div>
  <div class="skel" style="flex:1;height:460px;border-radius:8px;"></div>
  <div class="skel" style="flex:1;height:460px;border-radius:8px;"></div>
  <div class="skel" style="flex:1;height:460px;border-radius:8px;"></div>
</div>

<div class="tt-wrap"><div id="tt" class="tt-grid" style="display:none;"></div></div>

<div style="position:relative;z-index:10;max-width:600px;margin:10px auto 40px;padding:0 16px;">
  <span style="font-size:11px;color:rgba(255,255,255,.2);" id="footer-ido2">–</span>
</div>

<?php render_time_sync_bootstrap(); ?>
<script>
const{formatHM,nowMinutes,schoolDayIndex}=window.TickyTime
const REFRESH_MS=60000
const NAP={1:'H',2:'K',3:'Sze',4:'Cs',5:'P'}
const START=7*60+30,END=14*60+30,TOTAL=END-START,PPM=1.7,H=TOTAL*PPM
let teremSzam=null,hetData=null
function updateTime(){const t=formatHM();document.getElementById('footer-ido').textContent=t;document.getElementById('footer-ido2').textContent=t;}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function roomPath(v){return encodeURIComponent(String(v??''));}
function getTerem(){const p=location.pathname.split('/').filter(Boolean);const q=new URLSearchParams(location.search).get('terem');if(p[0]==='terem'&&p[1])return p[1].toUpperCase();if(q)return q.toUpperCase();return null;}
function maiNap(){return schoolDayIndex();}
function toMin(t){const[h,m]=t.split(':').map(Number);return h*60+m;}
function topPx(m){return Math.max(0,(m-START)*PPM);}
function isAktiv(k,v){const c=nowMinutes();return c>=toMin(k)&&c<=toMin(v);}
function isMult(v){return nowMinutes()>toMin(v);}
function calcPct(k,v){const c=nowMinutes();return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)));}
function setAllapot(a){
  const pill=document.getElementById('status-pill'),dot=document.getElementById('status-dot'),txt=document.getElementById('status-text')
  if(a==='foglalt'){document.body.className='foglalt';pill.style.cssText='display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:99px;font-size:12px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;';dot.style.background='#ff6b82';txt.textContent='FOGLALT';}
  else{document.body.className='szabad';pill.style.cssText='display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:99px;font-size:12px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;';dot.style.background='#4ade80';txt.textContent='SZABAD';}
}
function kovHtml(k){
  if(!k)return`<div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);"><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.25);margin-bottom:4px;">Következő óra</p><p style="font-size:13px;color:rgba(255,255,255,.35);">Ma már nincs több óra</p></div>`
  return`<div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);"><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.25);margin-bottom:6px;">Következő óra</p><div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;"><span style="font-size:13px;color:rgba(255,255,255,.7);">${esc(k.tanar)} · ${esc(k.osztaly)} · ${esc(k.tantargy)}</span><span style="font-size:11px;color:rgba(255,255,255,.35);white-space:nowrap;">${esc(k.kezdes)}–${esc(k.vegzes)}</span></div></div>`
}
function renderStatus(data){
  setAllapot(data.allapot)
  const el=document.getElementById('content')
  if(data.allapot==='szabad'){
    el.innerHTML=`<div style="text-align:center;padding:16px 0;"><span style="font-size:40px;display:block;margin-bottom:10px;">✅</span><p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#4ade80;margin-bottom:4px;">Szabad terem</p><p style="font-size:13px;color:rgba(255,255,255,.4);">Nincs aktív foglalás</p></div>${kovHtml(data.kovetkezo)}`
  }else{
    const a=data.aktualis,pct=calcPct(a.kezdes,a.vegzes)
    el.innerHTML=`<div style="display:flex;flex-direction:column;gap:14px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Tanár</p><p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:white;line-height:1.2;">${esc(a.tanar_nev||a.tanar)}</p></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Osztály</p><p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${esc(a.osztaly)}</p></div><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Tantárgy</p><p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">${esc(a.tantargy)}</p></div></div><div><div style="height:5px;border-radius:3px;overflow:hidden;background:rgba(255,255,255,.08);"><div class="prog-bar" style="height:100%;width:${pct}%;background:linear-gradient(90deg,#e8334a,#ff6b82);"></div></div><div style="display:flex;justify-content:space-between;margin-top:5px;font-size:11px;color:rgba(255,255,255,.35);"><span>${esc(a.kezdes)}</span><span style="color:#ff6b82;font-weight:600;">még ${esc(a.perc_maradt)} perc</span><span>${esc(a.vegzes)}</span></div></div></div>${kovHtml(data.kovetkezo)}`
  }
}
function buildTT(){
  const mai=maiNap(),el=document.getElementById('tt')
  const ticks=[];for(let m=START;m<=END;m+=30)ticks.push(m)
  let html=`<div class="tt-hdr-empty"></div>`
  for(let n=1;n<=5;n++)html+=`<div class="tt-hdr${n===mai?' ma':''}">${NAP[n]}${n===mai?'<span style="display:inline-block;width:4px;height:4px;border-radius:50%;background:#c8972a;margin-left:2px;vertical-align:middle;animation:pd 2s infinite;"></span>':''}</div>`
  let tc=`<div class="tt-timecol" style="height:${H}px;position:relative;">`
  ticks.forEach(m=>{const top=topPx(m);const hh=Math.floor(m/60).toString().padStart(2,'0');const mm=(m%60).toString().padStart(2,'0');tc+=`<span class="tlabel" style="top:${top}px;">${hh}:${mm}</span>`})
  tc+=`</div>`;html+=tc
  for(let n=1;n<=5;n++){
    const isMa=n===mai,orak=hetData[n]||[]
    let col=`<div class="tt-daycol${isMa?' ma':''}" style="height:${H}px;">`
    ticks.forEach(m=>{col+=`<div class="hline${m%60===0?' bold':''}" style="top:${topPx(m)}px;"></div>`})
    if(isMa){const nm=nowMinutes();if(nm>=START&&nm<=END)col+=`<div class="now-line" id="now-line" style="top:${topPx(nm)}px;"><div class="now-dot"></div></div>`}
    orak.forEach(o=>{
      const top=topPx(toMin(o.kezdes)),h=Math.max(18,(toMin(o.vegzes)-toMin(o.kezdes))*PPM)
      const ak=isMa&&isAktiv(o.kezdes,o.vegzes),mu=isMa&&isMult(o.vegzes),p=ak?calcPct(o.kezdes,o.vegzes):0
      col+=`<div class="ora-blk${ak?' aktiv':mu?' mult':''}" style="top:${top}px;height:${h}px;" title="${esc(o.tanar_nev||o.tanar)} · ${esc(o.osztaly)} · ${esc(o.tantargy)}"><div class="ob-tanar">${esc(o.tanar_nev||o.tanar)}</div>${h>28?`<div class="ob-meta">${esc(o.osztaly)} · ${esc(o.tantargy)}</div>`:''} ${ak?`<div class="ob-prog"><div class="ob-prog-fill" style="width:${p}%;"></div></div>`:''}</div>`
    })
    col+=`</div>`;html+=col
  }
  document.getElementById('tt-skel').style.display='none'
  el.innerHTML=html;el.style.display='grid'
  setInterval(()=>{const nl=document.getElementById('now-line');if(nl){const nm=nowMinutes();if(nm>=START&&nm<=END)nl.style.top=topPx(nm)+'px'}},60000)
}
async function fetchStatus(){
  try{const d=await fetch(`/api/terem/${roomPath(teremSzam)}`).then(r=>r.json());if(!d.error){document.getElementById('terem-szam').textContent=d.terem;renderStatus(d)}}catch(e){}
  updateTime()
}
async function fetchTimetable(){
  try{const d=await fetch(`/api/napirend/${roomPath(teremSzam)}?nap=heten`).then(r=>r.json());if(d.error)return;hetData={};(d.het||[]).forEach(nd=>{hetData[nd.nap]=nd.orak||[]});buildTT()}
  catch(e){document.getElementById('tt-skel').style.display='none';}
}
function refresh(){const ic=document.getElementById('ri');ic.classList.add('spinning');Promise.all([fetchStatus(),fetchTimetable()]).finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600));}
teremSzam=getTerem()
if(!teremSzam){
  document.getElementById('terem-szam').textContent='?'
  document.getElementById('content').innerHTML=`<div style="text-align:center;padding:20px 0;"><span style="font-size:36px;display:block;margin-bottom:10px;">🔍</span><p style="color:rgba(255,255,255,.6);">Nincs terem megadva</p><p style="font-size:12px;margin-top:4px;color:rgba(255,255,255,.35);">URL: /terem/204</p></div>`
}else{
  document.getElementById('terem-szam').textContent=teremSzam
  document.getElementById('napirend-link').href='/terem/'+roomPath(teremSzam)+'/nap'
  document.title='Ticky – '+teremSzam
  updateTime()
  fetchStatus();fetchTimetable()
  setInterval(fetchStatus,REFRESH_MS);setInterval(fetchTimetable,5*60000);setInterval(updateTime,60000)
}
</script>
</body>
</html>
