<!DOCTYPE html>
<html lang="hu">
<?php render_app_head('Ticky - Folyosoi kijelzo', [
  'tailwind' => false,
  'dm_mono' => true,
  'viewport' => 'width=device-width, initial-scale=1.0, maximum-scale=1.0',
]); ?>
<style>
  :root {
    --bg:       #04090f;
    --navy:     #0b2e59;
    --gold:     #c8972a;
    --gold-l:   #f0c76b;
    --red:      #e8334a;
    --red-glow: rgba(232,51,74,.18);
    --green:    #00c896;
    --green-glow: rgba(0,200,150,.12);
    --border:   rgba(255,255,255,.07);
    --muted:    rgba(255,255,255,.35);
  }

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  html, body {
    width:100%; height:100%; overflow:hidden;
    font-family:'DM Sans',sans-serif; color:white;
    background:#04090f;
    user-select:none;
  }

  /* HÃ¡ttÃ©r rÃ©tegek */
  body::before {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background:
      radial-gradient(ellipse 80% 60% at 10% 0%,   rgba(26,74,138,.45) 0%, transparent 55%),
      radial-gradient(ellipse 60% 50% at 90% 100%,  rgba(200,151,42,.12) 0%, transparent 50%),
      radial-gradient(ellipse 40% 40% at 50% 50%,   rgba(7,29,58,.7) 0%, transparent 60%);
  }

  /* Finom rÃ¡cs textÃºra */
  body::after {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background-image:
      linear-gradient(rgba(255,255,255,.015) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.015) 1px, transparent 1px);
    background-size:48px 48px;
  }

  /* Scanline overlay â€“ igazi kijelzÅ‘ hangulat */
  .scanlines {
    position:fixed; inset:0; z-index:1; pointer-events:none;
    background:repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0,0,0,.06) 2px,
      rgba(0,0,0,.06) 4px
    );
  }

  /* Arany vonal tetejÃ©n */
  .top-line {
    position:fixed; top:0; left:0; right:0; height:2px; z-index:100;
    background:linear-gradient(90deg, transparent 0%, var(--gold) 30%, var(--gold-l) 50%, var(--gold) 70%, transparent 100%);
    box-shadow: 0 0 20px rgba(200,151,42,.4);
  }

  /* â”€â”€ TOPBAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .topbar {
    position:relative; z-index:50;
    height:64px; padding:0 28px;
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(4,9,15,.7);
    backdrop-filter:blur(20px);
    border-bottom:1px solid rgba(255,255,255,.08);
  }

  .tb-brand {
    display:flex; align-items:center; gap:10px;
    font-family:'Playfair Display',serif; font-size:20px; font-weight:700;
  }
  .brand-dot {
    width:9px; height:9px; border-radius:50%; background:var(--gold);
    box-shadow:0 0 12px var(--gold); animation:pd 2s infinite;
  }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }

  .tb-center {
    display:flex; flex-direction:column; align-items:center; gap:1px;
  }
  .tb-datum {
    font-size:12px; font-weight:500; letter-spacing:.08em;
    text-transform:uppercase; color:rgba(255,255,255,.4);
  }
  .tb-nap {
    font-family:'Playfair Display',serif; font-size:16px; font-weight:700;
    color:rgba(255,255,255,.8);
  }

  .tb-right {
    display:flex; align-items:center; gap:16px;
  }

  /* Ã‰lÅ‘ Ã³ra */
  .live-clock {
    font-family:'DM Mono',monospace; font-size:28px; font-weight:500;
    color:white; letter-spacing:.05em;
    text-shadow:0 0 20px rgba(255,255,255,.2);
  }
  .clock-sec { color:var(--gold-l); font-size:20px; opacity:.7; }

  /* Filter gombok */
  .filter-grp { display:flex; gap:6px; }
  .fbtn {
    padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600;
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10);
    color:rgba(255,255,255,.5); cursor:pointer; transition:all .2s;
    font-family:'DM Sans',sans-serif; letter-spacing:.03em;
  }
  .fbtn:hover { background:rgba(255,255,255,.10); color:white; }
  .fbtn.active { background:rgba(200,151,42,.18); border-color:rgba(200,151,42,.45); color:var(--gold-l); }
  .fbtn.active-red { background:rgba(232,51,74,.18); border-color:rgba(232,51,74,.45); color:#ff6b82; }
  .fbtn.active-green { background:rgba(0,200,150,.14); border-color:rgba(0,200,150,.4); color:var(--green); }

  /* Fullscreen gomb */
  .fs-btn {
    width:36px; height:36px; border-radius:8px; cursor:pointer;
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10);
    display:flex; align-items:center; justify-content:center;
    color:rgba(255,255,255,.5); transition:all .2s;
    flex-shrink:0;
  }
  .fs-btn:hover { background:rgba(255,255,255,.12); color:white; }

  /* â”€â”€ STATUS BAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .statusbar {
    position:relative; z-index:50;
    height:36px; padding:0 28px;
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(4,9,15,.5);
    border-bottom:1px solid rgba(255,255,255,.05);
  }
  .sb-stat {
    display:flex; align-items:center; gap:6px;
    font-size:11px; font-weight:500; color:rgba(255,255,255,.35);
    letter-spacing:.04em; text-transform:uppercase;
  }
  .sb-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
  .sb-num { font-family:'DM Mono',monospace; font-size:13px; font-weight:500; }
  .sb-divider { width:1px; height:16px; background:rgba(255,255,255,.08); }
  .sb-update { font-size:11px; color:rgba(255,255,255,.25); font-family:'DM Mono',monospace; }
  .sb-refresh-dot { width:6px; height:6px; border-radius:50%; background:var(--green); flex-shrink:0; }
  .sb-refresh-dot.loading { background:var(--gold); animation:pd .8s infinite; }

  /* â”€â”€ MAIN GRID â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .main {
    position:relative; z-index:10;
    /* teljes kÃ©pernyÅ‘ mÃ­nusz topbar + statusbar */
    height:calc(100vh - 100px);
    padding:16px 20px;
    overflow:hidden;
  }

  .rooms-grid {
    display:grid;
    /* Auto-fill: TV-n nagy kÃ¡rtyÃ¡k, tableten kisebb */
    grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
    gap:10px;
    height:100%;
    align-content:start;
    overflow-y:auto;
    scrollbar-width:none;
  }
  .rooms-grid::-webkit-scrollbar { display:none; }

  /* â”€â”€ TEREM KÃRTYÃK â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .room-card {
    border-radius:14px; padding:14px 16px;
    border:1px solid rgba(255,255,255,.07);
    background:rgba(255,255,255,.04);
    backdrop-filter:blur(8px);
    transition:all .5s cubic-bezier(.22,1,.36,1);
    cursor:default; position:relative; overflow:hidden;
    min-height:120px; display:flex; flex-direction:column; justify-content:space-between;
  }

  /* Szabad */
  .room-card.szabad {
    background:rgba(0,200,150,.06);
    border-color:rgba(0,200,150,.15);
  }
  .room-card.szabad:hover {
    border-color:rgba(0,200,150,.3);
    background:rgba(0,200,150,.09);
  }

  /* Foglalt */
  .room-card.foglalt {
    background:rgba(232,51,74,.08);
    border-color:rgba(232,51,74,.2);
    box-shadow:0 0 20px rgba(232,51,74,.06), inset 0 0 20px rgba(232,51,74,.04);
  }
  .room-card.foglalt:hover {
    border-color:rgba(232,51,74,.4);
    box-shadow:0 0 32px rgba(232,51,74,.12), inset 0 0 24px rgba(232,51,74,.06);
  }

  /* Foglalt hÃ¡ttÃ©r glow szegÃ©ly */
  .room-card.foglalt::before {
    content:''; position:absolute; inset:0; border-radius:14px; pointer-events:none;
    background:linear-gradient(135deg, rgba(232,51,74,.06) 0%, transparent 60%);
  }

  /* KÃ¡rtya animÃ¡ciÃ³ megjelenÃ©skor */
  .room-card.card-in {
    animation:cardIn .4s cubic-bezier(.22,1,.36,1) both;
  }
  @keyframes cardIn {
    from { opacity:0; transform:scale(.94) translateY(8px); }
    to   { opacity:1; transform:none; }
  }

  /* StÃ¡tusz vÃ¡ltozÃ¡s flash */
  .room-card.flash {
    animation:flash .6s ease;
  }
  @keyframes flash {
    0%,100% { filter:brightness(1); }
    50%      { filter:brightness(1.5); }
  }

  /* KÃ¡rtya teteje: terem szÃ¡m + stÃ¡tusz pill */
  .card-top {
    display:flex; align-items:flex-start; justify-content:space-between; gap:6px;
  }

  .room-num {
    font-family:'Playfair Display',serif; font-size:30px; font-weight:700;
    color:white; line-height:1; letter-spacing:-.5px;
  }

  .status-pill {
    display:flex; align-items:center; gap:5px;
    padding:4px 10px; border-radius:20px; flex-shrink:0;
    font-size:10px; font-weight:700; letter-spacing:.06em;
    text-transform:uppercase;
  }
  .status-pill.szabad {
    background:rgba(0,200,150,.18); border:1px solid rgba(0,200,150,.35); color:var(--green);
  }
  .status-pill.foglalt {
    background:rgba(232,51,74,.22); border:1px solid rgba(232,51,74,.4); color:#ff6b82;
  }
  .pill-dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; animation:pd 2s infinite; }
  .pill-dot.szabad { background:var(--green); }
  .pill-dot.foglalt { background:#ff6b82; }

  /* KÃ¡rtya alja: foglalt adatok */
  .card-body { flex:1; display:flex; flex-direction:column; justify-content:flex-end; gap:4px; margin-top:8px; }

  .card-tanar {
    font-family:'Playfair Display',serif; font-size:15px; font-weight:700;
    color:white; line-height:1.2;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  .card-meta {
    font-size:11px; color:rgba(255,255,255,.45);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  /* Progress bar */
  .card-prog {
    height:2px; border-radius:2px; overflow:hidden;
    background:rgba(255,255,255,.08); margin-top:6px;
  }
  .card-prog-fill {
    height:100%; border-radius:2px;
    background:linear-gradient(90deg, var(--red), #ff6b82);
    transition:width .8s ease;
  }
  .card-time {
    display:flex; justify-content:space-between;
    font-size:10px; color:rgba(255,255,255,.3); margin-top:3px;
    font-family:'DM Mono',monospace;
  }
  .card-time .remaining { color:#ff6b82; font-weight:500; }

  /* Szabad kÃ¡rtya */
  .card-free-txt {
    font-size:12px; color:rgba(0,200,150,.6); font-weight:500; margin-top:8px;
  }
  .card-next {
    font-size:10px; color:rgba(255,255,255,.3); margin-top:2px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  /* Skeleton */
  .skel {
    background:linear-gradient(90deg,rgba(255,255,255,.05) 25%,rgba(255,255,255,.09) 50%,rgba(255,255,255,.05) 75%);
    background-size:200% 100%; animation:sk 1.4s infinite; border-radius:8px;
  }
  @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

  /* â”€â”€ EMPTY STATE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .empty-state {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    height:100%; gap:12px; opacity:.5;
  }
  .empty-state span { font-size:48px; }
  .empty-state p { font-size:16px; color:rgba(255,255,255,.6); }

  /* â”€â”€ SZÃœNET BANNER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  .szunet-banner {
    display:none; position:fixed; bottom:40px; left:50%;
    transform:translateX(-50%);
    background:rgba(200,151,42,.15); border:1px solid rgba(200,151,42,.35);
    backdrop-filter:blur(16px); border-radius:14px;
    padding:12px 28px; z-index:200;
    font-size:14px; font-weight:600; color:var(--gold-l);
    letter-spacing:.05em; text-transform:uppercase;
    animation:slideUp .4s cubic-bezier(.22,1,.36,1);
    display:flex; align-items:center; gap:10px;
  }
  @keyframes slideUp { from{opacity:0;transform:translateX(-50%) translateY(12px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }

  /* â”€â”€ FULLSCREEN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
  :fullscreen body { background:#04090f; }
  :-webkit-full-screen body { background:#04090f; }

  /* Auto-refresh progress bar */
  .refresh-bar {
    position:fixed; bottom:0; left:0; right:0; height:2px; z-index:200;
    background:rgba(255,255,255,.06);
  }
  .refresh-bar-fill {
    height:100%; background:linear-gradient(90deg,var(--navy),var(--gold));
    transition:width .5s linear;
  }
</style>
</head>
<body>
<div class="top-line"></div>
<div class="scanlines"></div>

<!-- Topbar -->
<div class="topbar">
  <div class="tb-brand">
    <div class="brand-dot"></div>
    <a href="/" style="color:white;text-decoration:none;">Ticky</a>
    <span style="color:rgba(255,255,255,.2);font-weight:400;font-size:16px;">Â·</span>
    <span style="color:rgba(255,255,255,.45);font-size:15px;font-weight:400;font-family:'DM Sans',sans-serif;">FolyosÃ³i kijelzÅ‘</span>
  </div>

  <div class="tb-center">
    <div class="tb-datum" id="tb-datum">â€“</div>
    <div class="tb-nap" id="tb-nap">â€“</div>
  </div>

  <div class="tb-right">
    <!-- Ã‰lÅ‘ Ã³ra -->
    <div class="live-clock" id="clock">
      <span id="clock-hm">â€“â€“:â€“â€“</span><span class="clock-sec">:<span id="clock-s">00</span></span>
    </div>

    <!-- Filter -->
    <div class="filter-grp">
      <button class="fbtn active" id="fb-mind" onclick="setFilter('mind')">Ã–sszes</button>
      <button class="fbtn" id="fb-foglalt" onclick="setFilter('foglalt')">Foglalt</button>
      <button class="fbtn" id="fb-szabad" onclick="setFilter('szabad')">Szabad</button>
    </div>

    <!-- Fullscreen -->
    <button class="fs-btn" onclick="toggleFS()" title="Teljes kÃ©pernyÅ‘">
      <svg id="fs-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
      </svg>
    </button>
  </div>
</div>

<!-- Status bar -->
<div class="statusbar">
  <div style="display:flex;align-items:center;gap:16px;">
    <div class="sb-stat">
      <div class="sb-dot" style="background:white;opacity:.3;"></div>
      Ã–sszes: <span class="sb-num" id="cnt-osszes">â€“</span>
    </div>
    <div class="sb-divider"></div>
    <div class="sb-stat" style="color:rgba(255,107,130,.7);">
      <div class="sb-dot" style="background:#ff6b82;box-shadow:0 0 6px #ff6b82;"></div>
      Foglalt: <span class="sb-num" id="cnt-foglalt">â€“</span>
    </div>
    <div class="sb-divider"></div>
    <div class="sb-stat" style="color:rgba(0,200,150,.7);">
      <div class="sb-dot" style="background:#00c896;box-shadow:0 0 6px #00c896;"></div>
      Szabad: <span class="sb-num" id="cnt-szabad">â€“</span>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:8px;">
    <div class="sb-refresh-dot" id="refresh-dot"></div>
    <span class="sb-update">FrissÃ­tve: <span id="last-update">â€“</span></span>
    <a href="/" style="font-size:11px;color:rgba(255,255,255,.2);text-decoration:none;margin-left:8px;">â† FÅ‘oldal</a>
  </div>
</div>

<!-- Main grid -->
<div class="main">
  <div class="rooms-grid" id="grid">
    <!-- Skeleton cards -->
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
    <div class="room-card skel" style="min-height:120px;"></div>
  </div>
</div>

<!-- Auto-refresh progress bar -->
<div class="refresh-bar"><div class="refresh-bar-fill" id="prog-bar" style="width:0%;"></div></div>

<?php render_time_sync_bootstrap(); ?>
<script>
const { formatHMS, nowMinutes, nowParts, weekdayIndex } = window.TickyTime
// â”€â”€ Konfig â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const REFRESH_SEC = 30
const NAP_NEVEK   = ['VasÃ¡rnap','HÃ©tfÅ‘','Kedd','Szerda','CsÃ¼tÃ¶rtÃ¶k','PÃ©ntek','Szombat']
const HONAP_NEVEK = ['januÃ¡r','februÃ¡r','mÃ¡rcius','Ã¡prilis','mÃ¡jus','jÃºnius','jÃºlius','augusztus','szeptember','oktÃ³ber','november','december']

// Iskolai Ã³ra idÅ‘pontok
const ORA_IDOK = [
  {kezdes:'07:30',vegzes:'08:10'},
  {kezdes:'08:20',vegzes:'09:05'},
  {kezdes:'09:15',vegzes:'10:00'},
  {kezdes:'10:15',vegzes:'11:00'},
  {kezdes:'11:10',vegzes:'11:55'},
  {kezdes:'12:05',vegzes:'12:50'},
  {kezdes:'12:50',vegzes:'13:35'},
  {kezdes:'13:40',vegzes:'14:20'},
]

let allRooms      = []
let curFilter     = 'mind'
let refreshTimer  = null
let progTimer     = null
let progStart     = null
let prevStates    = {}

// â”€â”€ Ã“ra â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function updateClock() {
  const p   = nowParts()
  const hm  = formatHMS().slice(0,5)
  const s   = p.second.toString().padStart(2,'0')
  document.getElementById('clock-hm').textContent = hm
  document.getElementById('clock-s').textContent  = s
}

function updateDate() {
  const p = nowParts()
  document.getElementById('tb-nap').textContent   = NAP_NEVEK[weekdayIndex()]
  document.getElementById('tb-datum').textContent =
    `${p.year}. ${HONAP_NEVEK[p.month-1]} ${p.day}.`
}

// â”€â”€ SzÃ¼net detektor â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function isSzunet() {
  const c=nowMinutes()
  const isOra = ORA_IDOK.some(o=>{
    const[kh,km]=o.kezdes.split(':').map(Number)
    const[vh,vm]=o.vegzes.split(':').map(Number)
    return c>=kh*60+km&&c<=vh*60+vm
  })
  const iskolaIdoben = c>=7*60+30&&c<=14*60+30
  return iskolaIdoben&&!isOra
}

// â”€â”€ SegÃ©dfÃ¼ggvÃ©nyek â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toMin(t){const[h,m]=t.split(':').map(Number);return h*60+m}
function calcPct(k,v){
  const c=nowMinutes()
  return Math.min(100,Math.max(0,Math.round(((c-toMin(k))/(toMin(v)-toMin(k)))*100)))
}

// â”€â”€ Fetch â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function fetchRooms() {
  const dot = document.getElementById('refresh-dot')
  dot.classList.add('loading')
  try {
    const d = await fetch('/api/termek?allapot=1').then(r=>r.json())
    if(d.error){ console.error(d.error); return }
    allRooms = (d.termek||[]).map(r=>({
      ...r,
      allapot: r.allapot??'szabad',
      aktualis: r.aktualis??null,
    }))
    renderGrid()
    document.getElementById('last-update').textContent =
      formatHMS()
  } catch(e){ console.error(e) }
  dot.classList.remove('loading')
}

// â”€â”€ Render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function setFilter(f) {
  curFilter = f
  ;['mind','foglalt','szabad'].forEach(k=>{
    const btn=document.getElementById('fb-'+k); if(!btn) return
    btn.className='fbtn'+(k===f?(f==='foglalt'?' active-red':f==='szabad'?' active-green':' active'):'')
  })
  renderGrid()
}

function renderGrid() {
  let rooms = allRooms
  if(curFilter==='foglalt') rooms=rooms.filter(r=>r.allapot==='foglalt')
  if(curFilter==='szabad')  rooms=rooms.filter(r=>r.allapot==='szabad')

  // Stats
  const fo = allRooms.filter(r=>r.allapot==='foglalt').length
  const sz = allRooms.filter(r=>r.allapot==='szabad').length
  document.getElementById('cnt-osszes').textContent  = allRooms.length
  document.getElementById('cnt-foglalt').textContent = fo
  document.getElementById('cnt-szabad').textContent  = sz

  const grid = document.getElementById('grid')

  if(!rooms.length) {
    grid.innerHTML=`<div class="empty-state" style="grid-column:1/-1;"><span>ðŸ”</span><p>Nincs talÃ¡lat</p></div>`
    return
  }

  // Diff alapÃº frissÃ­tÃ©s â€“ csak vÃ¡ltozott kÃ¡rtyÃ¡kat flash-eli
  const newHTML = rooms.map((r,i)=>{
    const isFoglalt = r.allapot==='foglalt'
    const a = r.aktualis
    const prevState = prevStates[r.terem_szam]
    const changed = prevState && prevState !== r.allapot

    let bodyHTML = ''
    if(isFoglalt && a) {
      const pct = calcPct(a.kezdes, a.vegzes)
      const percMaradt = Math.round(toMin(a.vegzes) - nowMinutes())
      bodyHTML = `
        <div class="card-body">
          <div class="card-tanar">${a.tanar}</div>
          <div class="card-meta">${a.osztaly} Â· ${a.tantargy}</div>
          <div class="card-prog"><div class="card-prog-fill" style="width:${pct}%;"></div></div>
          <div class="card-time">
            <span>${a.kezdes}</span>
            <span class="remaining">${percMaradt > 0 ? percMaradt+'p' : 'vÃ©ge'}</span>
            <span>${a.vegzes}</span>
          </div>
        </div>`
    } else {
      bodyHTML = `<div class="card-body"><div class="card-free-txt">Szabad</div></div>`
    }

    const cl = `room-card ${r.allapot} card-in${changed?' flash':''}`
    return `<div class="${cl}" style="animation-delay:${i*20}ms;" onclick="window.open('/terem/${r.terem_szam}','_blank')">
      <div class="card-top">
        <div class="room-num">${r.terem_szam}</div>
        <div class="status-pill ${r.allapot}">
          <div class="pill-dot ${r.allapot}"></div>
          ${isFoglalt?'FOGLALT':'SZABAD'}
        </div>
      </div>
      ${bodyHTML}
    </div>`
  }).join('')

  grid.innerHTML = newHTML

  // Prev states frissÃ­tÃ©se
  allRooms.forEach(r => { prevStates[r.terem_szam] = r.allapot })
}

// â”€â”€ Auto-refresh progress bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function startProgressBar() {
  if(progTimer) clearInterval(progTimer)
  progStart = Date.now()
  const bar = document.getElementById('prog-bar')
  bar.style.transition = 'none'
  bar.style.width = '0%'

  progTimer = setInterval(()=>{
    const elapsed = (Date.now()-progStart)/1000
    const pct = Math.min(100,(elapsed/REFRESH_SEC)*100)
    bar.style.transition = 'width .5s linear'
    bar.style.width = pct+'%'
    if(pct>=100) {
      clearInterval(progTimer)
      bar.style.width='0%'
    }
  },500)
}

// â”€â”€ Fullscreen â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleFS() {
  if(!document.fullscreenElement) {
    document.documentElement.requestFullscreen?.()
    document.getElementById('fs-icon').innerHTML=`<path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/>`
  } else {
    document.exitFullscreen?.()
    document.getElementById('fs-icon').innerHTML=`<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>`
  }
}

// â”€â”€ Init â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
updateClock()
updateDate()
setInterval(updateClock, 1000)
setInterval(updateDate, 60_000)

// ElsÅ‘ betÃ¶ltÃ©s
fetchRooms().then(()=>{ startProgressBar() })

// Auto-refresh
refreshTimer = setInterval(()=>{
  fetchRooms().then(()=>{ startProgressBar() })
}, REFRESH_SEC * 1000)

// PercenkÃ©nt az idÅ‘k frissÃ­tÃ©se (perc maradt stb.)
setInterval(renderGrid, 60_000)

// BillentyÅ± shortcut: F = fullscreen, R = refresh
document.addEventListener('keydown', e=>{
  if(e.key==='f'||e.key==='F') toggleFS()
  if(e.key==='r'||e.key==='R') { fetchRooms(); startProgressBar(); }
  if(e.key==='1') setFilter('mind')
  if(e.key==='2') setFilter('foglalt')
  if(e.key==='3') setFilter('szabad')
})
</script>
</body>
</html>
