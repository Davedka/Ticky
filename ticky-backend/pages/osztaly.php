<?php
$route_match = match_route('/osztaly/{kod}', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
$current_class = is_array($route_match) ? trim((string) ($route_match['kod'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky - Osztaly nezet</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;color:white;transition:background-image .5s;
  background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
body.oraban{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(200,16,46,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.15) 0%,transparent 55%);}
body.szabad{background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,138,74,.35) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(26,74,138,.2) 0%,transparent 55%);}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;}
a{text-decoration:none;color:inherit;}
.glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
.pulse{animation:pd 2s infinite;}
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.slide-up{animation:su .5s cubic-bezier(.22,1,.36,1) both;}
@keyframes su{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
.skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.06) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes spin{to{transform:rotate(360deg)}}
.spinning{animation:spin .6s linear;}
.custom-select{width:100%;padding:12px 40px 12px 14px;border-radius:10px;border:1.5px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:white;font-family:'DM Sans',sans-serif;font-size:15px;appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;transition:border-color .2s;outline:none;}
.custom-select:focus{border-color:rgba(200,151,42,.5);}
.custom-select option{background:#0b2e59;color:white;}
.ora-row{transition:background .15s;border-radius:10px;}
.ora-row.aktiv{background:rgba(200,16,46,.12);border-left:3px solid #e8334a;border-radius:0 10px 10px 0;}
.ora-row.mult{opacity:.38;}
.aktiv-card{animation:cardIn .35s cubic-bezier(.22,1,.36,1) both;}
@keyframes cardIn{from{opacity:0;transform:scale(.97) translateY(8px)}to{opacity:1;transform:none}}
.lista-in{animation:listaIn .3s cubic-bezier(.22,1,.36,1) both;}
@keyframes listaIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.group-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;background:rgba(200,151,42,.15);color:#f0c76b;border:1px solid rgba(200,151,42,.22);}
</style>
</head>
<body>
<?php
if (!function_exists('ticky_nav')) {
function ticky_nav(string $aktiv = '', string $cim = '') {
    $links = [
        ['href'=>'/termek',  'label'=>'Termek',  'key'=>'termek'],
        ['href'=>'/tanar',   'label'=>'Tanar',   'key'=>'tanar'],
        ['href'=>'/osztaly', 'label'=>'Osztaly', 'key'=>'osztaly'],
        ['href'=>'/qr',      'label'=>'QR',      'key'=>'qr'],
        ['href'=>'/kijelzo', 'label'=>'Kijelzo', 'key'=>'kijelzo'],
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
@media(max-width:640px){.ticky-sidebar{display:none;}.tn-links{display:none;}.tn-hamburger{display:flex;}}
@media(min-width:641px){.tn-mobile{display:none!important;}.tn-hamburger{display:none!important;}}
</style>
<div style="position:fixed;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,151,42,.5),transparent);z-index:300;"></div>
<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="tsb-item" data-label="Esemenynaptar">📅</a>
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
<?php ticky_nav('osztaly','Osztaly nezet'); ?>

<div style="position:relative;z-index:10;display:flex;justify-content:center;padding:24px 16px 60px;">
<div style="width:100%;max-width:440px;" class="slide-up">
  <div class="glass" style="border-radius:18px;overflow:hidden;">

    <div style="padding:14px 20px 0;display:flex;align-items:center;justify-content:space-between;">
      <a href="/" style="font-family:'Playfair Display',serif;color:rgba(255,255,255,.35);font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;text-decoration:none;" onmouseover="this.style.color='rgba(200,151,42,.8)'" onmouseout="this.style.color='rgba(255,255,255,.35)'">
        <span style="width:6px;height:6px;border-radius:50%;background:#c8972a;box-shadow:0 0 6px #c8972a;display:inline-block;animation:pd 2s infinite;"></span>Ticky
      </a>
      <span style="font-size:11px;color:rgba(255,255,255,.28);">Osztaly nezet</span>
    </div>

    <div style="padding:14px 20px 18px;border-bottom:1px solid rgba(255,255,255,.08);">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
        <p style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);">Valassz osztalyt</p>
        <div id="status-pill" style="display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;">
          <span id="sd" style="width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.3);display:inline-block;animation:pd 2s infinite;"></span>
          <span id="st">-</span>
        </div>
      </div>
      <select id="sel" class="custom-select" onchange="onSelect()">
        <option value="">- Valassz osztalyt -</option>
      </select>
    </div>

    <div id="aktblock" style="padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);">
      <div style="text-align:center;padding:12px 0;">
        <span style="font-size:32px;display:block;margin-bottom:8px;">🎓</span>
        <p style="font-size:13px;color:rgba(255,255,255,.4);">Valassz osztalyt a legordulo menubol</p>
      </div>
    </div>

    <div style="padding:16px 20px;">
      <p style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:12px;">Mai napirend</p>
      <div id="lista"><p style="font-size:13px;color:rgba(255,255,255,.3);">Nincs kivalasztva osztaly</p></div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:12px;color:rgba(255,255,255,.35);" id="ido">-</span>
      <button onclick="refresh()" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.10);background:transparent;cursor:pointer;">
        <svg id="ri" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Frissit
      </button>
    </div>
  </div>
</div>
</div>

<script>
const REFRESH=60000
let curClass=null

function updateTime(){document.getElementById('ido').textContent=new Date().toLocaleTimeString('hu-HU',{hour:'2-digit',minute:'2-digit'})}

function getUrlClass(){
  const p=location.pathname.split('/').filter(Boolean)
  const q=new URLSearchParams(location.search).get('osztaly')
  if(p[0]==='osztaly'&&p[1])return decodeURIComponent(p[1])
  if(q)return decodeURIComponent(q)
  return null
}

function esc(value){
  return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')
}

function cssEsc(value){
  if(window.CSS&&typeof window.CSS.escape==='function')return window.CSS.escape(String(value??''))
  return String(value??'').replace(/["\\#.:,[\]()]/g,'\\$&')
}

function roomLabel(room){
  const value=String(room??'').trim()
  return value.includes('/')?`${esc(value)}. termek`:`${esc(value)}. terem`
}

function getTeacherLabel(data){
  const full=String(data.tanar_nev??'').trim()
  const short=String(data.tanar??'').trim()
  return full||short||'?'
}

async function loadOsztalyok(){
  try{
    const d=await fetch('/api/osztalyok').then(r=>r.json())
    const sel=document.getElementById('sel')
    ;(d.osztalyok||[]).forEach(code=>{
      const o=document.createElement('option')
      o.value=code
      o.textContent=code
      sel.appendChild(o)
    })
    const url=getUrlClass()
    if(url){
      if(![...sel.options].some(option=>option.value===url)){
        const extra=document.createElement('option')
        extra.value=url
        extra.textContent=url
        sel.appendChild(extra)
      }
      sel.value=url
      if(sel.value){curClass=url;loadData()}
    }
  }catch(e){}
}

function onSelect(){
  const v=document.getElementById('sel').value
  if(!v){curClass=null;reset();return}
  curClass=v
  history.replaceState(null,'','/osztaly/'+encodeURIComponent(v))
  document.title='Ticky - '+v
  loadData()
}

function reset(){
  document.getElementById('aktblock').innerHTML=`<div style="text-align:center;padding:12px 0;"><span style="font-size:32px;display:block;margin-bottom:8px;">🎓</span><p style="font-size:13px;color:rgba(255,255,255,.4);">Valassz osztalyt a legordulo menubol</p></div>`
  document.getElementById('lista').innerHTML=`<p style="font-size:13px;color:rgba(255,255,255,.3);">Nincs kivalasztva osztaly</p>`
  setAllapot('idle')
}

function setAllapot(state){
  const pill=document.getElementById('status-pill'),dot=document.getElementById('sd'),txt=document.getElementById('st')
  if(state==='oraban'){document.body.className='oraban';pill.style.cssText='display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(200,16,46,.25);color:#ff6b82;border:1px solid rgba(200,16,46,.4);flex-shrink:0;';dot.style.background='#ff6b82';txt.textContent='ORAN VAN'}
  else if(state==='szabad'){document.body.className='szabad';pill.style.cssText='display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(26,138,74,.25);color:#4ade80;border:1px solid rgba(26,138,74,.4);flex-shrink:0;';dot.style.background='#4ade80';txt.textContent='SZABAD'}
  else{document.body.className='';pill.style.cssText='display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);flex-shrink:0;';dot.style.background='rgba(255,255,255,.3)';txt.textContent='-'}
}

function toMin(t){const[h,m]=t.split(':').map(Number);return h*60+m}
function isAktiv(k,v){const c=new Date().getHours()*60+new Date().getMinutes();return c>=toMin(k)&&c<=toMin(v)}
function isMult(v){return new Date().getHours()*60+new Date().getMinutes()>toMin(v)}
function calcPct(k,v){const c=new Date().getHours()*60+new Date().getMinutes();return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)))}

async function loadData(){
  if(!curClass)return
  document.getElementById('aktblock').innerHTML=`<div style="display:flex;flex-direction:column;gap:8px;padding:4px 0;"><div class="skel" style="height:12px;width:40%;"></div><div class="skel" style="height:22px;width:60%;"></div><div class="skel" style="height:12px;width:100%;"></div></div>`
  try{
    const d=await fetch(`/api/osztaly/${encodeURIComponent(curClass)}/orarend`).then(r=>r.json())
    if(d.error){document.getElementById('aktblock').innerHTML=`<div style="text-align:center;padding:12px 0;"><span style="font-size:28px;display:block;margin-bottom:8px;">⚠️</span><p style="font-size:13px;color:rgba(255,255,255,.4);">${esc(d.error)}</p></div>`;setAllapot('idle');return}
    const orak=d.orak||[]
    const akt=orak.find(o=>isAktiv(o.kezdes,o.vegzes))
    const kov=orak.find(o=>!isMult(o.vegzes)&&!isAktiv(o.kezdes,o.vegzes))
    setAllapot(akt?'oraban':orak.length>0?'szabad':'idle')
    renderAkt(akt,kov,orak)
    renderLista(orak)
    if(d.osztaly){
      const sel=document.getElementById('sel')
      if(!document.querySelector(`#sel option[value="${cssEsc(d.osztaly)}"]`)){
        const option=document.createElement('option')
        option.value=d.osztaly
        option.textContent=d.osztaly
        sel.appendChild(option)
      }
      sel.value=d.osztaly
      curClass=d.osztaly
      history.replaceState(null,'','/osztaly/'+encodeURIComponent(d.osztaly))
      document.title='Ticky - '+d.osztaly
    }
  }catch(e){document.getElementById('aktblock').innerHTML=`<div style="text-align:center;padding:12px 0;"><span style="font-size:28px;display:block;margin-bottom:8px;">⚠️</span><p style="font-size:13px;color:rgba(255,255,255,.4);">Betoltesi hiba</p></div>`;setAllapot('idle')}
  updateTime()
}

function renderGroupLines(groups){
  if(!Array.isArray(groups)||groups.length<=1)return''
  return `<div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">${groups.map(group=>`<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span style="font-size:10px;font-weight:600;padding:0 5px;border-radius:3px;background:rgba(255,255,255,.07);color:rgba(255,255,255,.4);flex-shrink:0;">${roomLabel(group.terem)}</span><span style="font-size:11px;color:rgba(255,255,255,.35);">${esc(getTeacherLabel(group))} · ${esc(group.tantargy)}</span></div>`).join('')}</div>`
}

function renderAkt(current,next,orak){
  const el=document.getElementById('aktblock')
  if(current){
    const p=calcPct(current.kezdes,current.vegzes)
    el.innerHTML=`<div style="display:flex;flex-direction:column;gap:12px;" class="aktiv-card"><div><div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:2px;"><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);">Most itt van</p>${current.is_csoport?'<span class="group-badge">Parhuzamos</span>':''}</div><p style="font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:white;line-height:1.1;">${roomLabel(current.terem)}</p></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Tanar</p><p style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:white;">${esc(current.tanar_nev||current.tanar)}</p></div><div><p style="font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;">Tantargy</p><p style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:white;">${esc(current.tantargy)}</p></div></div>${renderGroupLines(current.csoportok)}<div><div style="height:5px;border-radius:3px;overflow:hidden;background:rgba(255,255,255,.08);"><div style="height:100%;width:${p}%;background:linear-gradient(90deg,#e8334a,#ff6b82);transition:width .6s;"></div></div><div style="display:flex;justify-content:space-between;margin-top:4px;font-size:11px;color:rgba(255,255,255,.35);"><span>${esc(current.kezdes)}</span><span style="color:#ff6b82;font-weight:600;">${esc(current.vegzes)}-ig</span><span>${esc(current.vegzes)}</span></div></div></div>`
  }else if(next){
    el.innerHTML=`<div style="display:flex;align-items:center;gap:12px;padding:4px 0;" class="aktiv-card"><span style="font-size:26px;">☕</span><div><p style="font-weight:600;color:rgba(255,255,255,.8);">Jelenleg szabad</p><p style="font-size:12px;margin-top:2px;color:rgba(255,255,255,.4);">Kovetkezo: <strong style="color:rgba(255,255,255,.7);">${roomLabel(next.terem)}</strong> · ${esc(next.kezdes)}-${esc(next.vegzes)} ${next.is_csoport?'<span class="group-badge">Parhuzamos</span>':''}</p></div></div>`
  }else{
    el.innerHTML=`<div style="display:flex;align-items:center;gap:12px;padding:4px 0;" class="aktiv-card"><span style="font-size:26px;">✅</span><div><p style="font-weight:600;color:#4ade80;">Szabad</p><p style="font-size:12px;margin-top:2px;color:rgba(255,255,255,.4);">${orak.length?'Ma mar nincs tobb ora':'Ma nincs ora beirva'}</p></div></div>`
  }
}

function renderLista(orak){
  const el=document.getElementById('lista')
  if(!orak.length){el.innerHTML=`<p style="font-size:13px;color:rgba(255,255,255,.35);">Nincs mai ora</p>`;return}
  el.innerHTML=`<div class="lista-in">`+orak.map((o,i)=>{
    const ak=isAktiv(o.kezdes,o.vegzes),mu=isMult(o.vegzes)
    return `<div class="ora-row${ak?' aktiv':mu?' mult':''}" style="display:flex;align-items:flex-start;gap:10px;padding:10px 10px;margin:0 -4px;"><span style="font-family:'Playfair Display',serif;font-weight:700;font-size:16px;color:${mu?'rgba(255,255,255,.2)':'rgba(255,255,255,.85)'};width:20px;text-align:right;flex-shrink:0;line-height:1.4;">${o.ora_sorszam||i+1}</span><div style="flex:1;min-width:0;"><div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span style="font-size:13px;font-weight:500;color:${mu?'rgba(255,255,255,.3)':'rgba(255,255,255,.8)'};">${roomLabel(o.terem)}</span>${o.is_csoport?'<span class="group-badge">Parhuzamos</span>':''}<span style="font-size:11px;color:rgba(255,255,255,.35);">${esc(o.tanar_nev||o.tanar)} · ${esc(o.tantargy)}</span></div>${renderGroupLines(o.csoportok)}<p style="font-size:11px;color:rgba(255,255,255,.28);">${esc(o.kezdes)} - ${esc(o.vegzes)}</p></div>${ak?`<span style="width:7px;height:7px;border-radius:50%;background:#ff6b82;display:inline-block;animation:pd 2s infinite;flex-shrink:0;margin-top:6px;"></span>`:''}</div>`
  }).join('')+`</div>`
}

function refresh(){const ic=document.getElementById('ri');ic.classList.add('spinning');loadData().finally(()=>setTimeout(()=>ic.classList.remove('spinning'),600))}

updateTime()
document.title=<?= json_encode($current_class !== '' ? 'Ticky - ' . $current_class : 'Ticky - Osztaly nezet', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
setInterval(updateTime,60000)
loadOsztalyok()
setInterval(()=>{if(curClass)loadData()},REFRESH)
</script>
</body>
</html>
