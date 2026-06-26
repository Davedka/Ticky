<?php
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/utils/_nav.php';


send_security_headers();
handle_cors();

if (function_exists('ticky_read_session')) {
    ticky_read_session();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Statikus fájlok védelme (Favicon fix)
$file = __DIR__ . $uri;
if (file_exists($file) && is_file($file)) {
    return false;
}

// FŐOLDAL
if ($uri === '/') {
    $nap_nevek = [0=>'Hétvége',1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
    $nap = mai_nap();
    $ido = aktualis_ido();

    // Navbar állapot
    $nav_user        = ticky_current_user();
    $nav_show_admin  = admin_can_see_ui();
    $nav_show_tester = $nav_user && ($nav_user['szerep'] ?? '') === 'tester';
    $nav_logged_in   = $nav_show_admin || is_array($nav_user);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#070b15;
    --surface:#0e1729;
    --surface-2:#13203a;
    --line:rgba(255,255,255,.08);
    --line-strong:rgba(255,255,255,.15);
    --gold:#e8b94a;
    --gold-soft:#f3cf7a;
    --gold-dim:#bf9540;
    --green:#5fd08a;
    --busy:#f0734f;
    --text:#eef2fb;
    --muted:#8a98b4;
    --faint:#57637f;
  }
  *,*::before,*::after{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0;font-family:'DM Sans',system-ui,sans-serif;color:var(--text);background:var(--bg);
    min-height:100vh;overscroll-behavior:none;-webkit-font-smoothing:antialiased;
    background-image:
      radial-gradient(ellipse 70% 45% at 50% -8%,rgba(31,64,124,.34) 0%,transparent 62%),
      radial-gradient(ellipse 45% 40% at 88% 108%,rgba(232,185,74,.07) 0%,transparent 55%);
  }
  a{text-decoration:none;color:inherit;}
  .mono{font-family:'JetBrains Mono',monospace;}

  .top-line{position:fixed;top:0;left:0;right:0;height:1px;z-index:300;
    background:linear-gradient(90deg,transparent,rgba(232,185,74,.5),transparent);}

  /* ---------- NAV ---------- */
  .navbar{position:sticky;top:0;z-index:200;height:60px;padding:0 22px;display:flex;align-items:center;
    justify-content:space-between;background:rgba(7,11,21,.78);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    border-bottom:1px solid var(--line);}
  .brand{display:flex;align-items:center;gap:10px;font-family:'Fraunces',serif;font-size:21px;font-weight:600;letter-spacing:-.01em;}
  .brand .dot{width:8px;height:8px;border-radius:50%;background:var(--gold);box-shadow:0 0 12px rgba(232,185,74,.9);animation:pulse 2.6s infinite;}
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
  .nav-links{display:flex;align-items:center;gap:3px;}
  .nav-link{font-size:13.5px;font-weight:500;color:var(--muted);padding:8px 12px;border-radius:9px;transition:color .15s,background .15s;white-space:nowrap;}
  .nav-link:hover{color:var(--text);background:rgba(255,255,255,.05);}
  .nav-link.login{color:var(--gold-soft);border:1px solid rgba(232,185,74,.25);padding:7px 14px;}
  .nav-link.login:hover{background:rgba(232,185,74,.1);color:#ffd97e;}
  .nav-link.gold{color:var(--gold-soft);border:1px solid rgba(232,185,74,.22);}
  .nav-link.gold:hover{color:#ffd97e;background:rgba(232,185,74,.1);}
  .nav-link.blue{color:#7fb2f5;border:1px solid rgba(127,178,245,.25);}
  .nav-link.blue:hover{color:#a9ccfb;background:rgba(127,178,245,.1);}

  .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:9px;border-radius:9px;border:1px solid var(--line);background:rgba(255,255,255,.04);}
  .hamburger span{width:19px;height:2px;background:rgba(255,255,255,.7);border-radius:2px;transition:.25s;}
  .hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
  .hamburger.open span:nth-child(2){opacity:0;}
  .hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
  .mobile-menu{display:none;position:fixed;top:60px;left:0;right:0;z-index:190;background:rgba(7,11,21,.98);
    backdrop-filter:blur(20px);border-bottom:1px solid var(--line);padding:12px 18px 22px;flex-direction:column;gap:4px;}
  .mobile-menu.open{display:flex;}
  .mobile-menu a{font-size:15px;font-weight:500;padding:13px 15px;border-radius:11px;color:var(--muted);border:1px solid transparent;transition:.15s;display:flex;align-items:center;gap:11px;}
  .mobile-menu a:hover{background:rgba(255,255,255,.05);color:var(--text);}
  .mobile-menu a svg{width:18px;height:18px;flex-shrink:0;opacity:.7;}
  .mm-gold{color:var(--gold-soft)!important;border-color:rgba(232,185,74,.2)!important;background:rgba(232,185,74,.05);}
  .mm-blue{color:#a9ccfb!important;border-color:rgba(127,178,245,.22)!important;background:rgba(127,178,245,.05);}

  /* ---------- MAIN ---------- */
  .wrap{position:relative;z-index:10;max-width:760px;margin:0 auto;padding:60px 22px 56px;}

  .hero{text-align:center;margin-bottom:30px;}
  .kicker{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.24em;text-transform:uppercase;color:var(--gold-dim);
    display:inline-flex;align-items:center;gap:9px;}
  .kicker .kd{width:5px;height:5px;border-radius:50%;background:var(--gold);box-shadow:0 0 8px var(--gold);}
  .wordmark{font-family:'Fraunces',serif;font-weight:600;font-size:clamp(62px,14vw,104px);line-height:.94;letter-spacing:-.025em;
    color:#f5f8fd;margin:18px 0 0;}
  .hero-rule{width:60px;height:2px;margin:22px auto 0;border-radius:2px;
    background:linear-gradient(90deg,transparent,var(--gold),transparent);}
  .tagline{margin:20px 0 0;color:var(--muted);font-size:16px;font-weight:300;}
  .status{margin-top:15px;display:inline-flex;align-items:center;gap:9px;font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:.05em;color:var(--green);}
  .status .live{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 10px var(--green);animation:pulse 2.6s infinite;}
  .status .sp{color:var(--faint);}

  /* live counts strip */
  .livebar{display:flex;align-items:center;justify-content:center;gap:22px;flex-wrap:wrap;margin:26px auto 0;padding:13px 22px;
    width:fit-content;border:1px solid var(--line);border-radius:14px;background:rgba(255,255,255,.02);}
  .lstat{display:flex;align-items:center;gap:9px;font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);}
  .lstat .ln{font-size:16px;color:var(--text);min-width:14px;}
  .lstat .ld{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
  .ld.free{background:var(--green);box-shadow:0 0 7px var(--green);}
  .ld.busy{background:var(--busy);box-shadow:0 0 7px var(--busy);}
  .ld.all{background:rgba(255,255,255,.45);}
  .ldiv{width:1px;height:16px;background:var(--line);}
  .skel{display:inline-block;width:16px;height:13px;border-radius:4px;
    background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.13) 50%,rgba(255,255,255,.06) 75%);
    background-size:200% 100%;animation:sh 1.4s infinite;vertical-align:-1px;}
  @keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}

  /* cards */
  .cards{display:flex;flex-direction:column;gap:11px;margin-top:34px;}
  .card{position:relative;display:flex;align-items:center;gap:18px;padding:20px 22px;border-radius:16px;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(255,255,255,0)),var(--surface);
    border:1px solid var(--line);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 18px 40px -24px rgba(0,0,0,.75);
    transition:transform .2s cubic-bezier(.22,1,.36,1),border-color .2s,box-shadow .2s;}
  .card:hover{transform:translateY(-3px);border-color:rgba(232,185,74,.28);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 26px 50px -24px rgba(0,0,0,.85),0 0 0 1px rgba(232,185,74,.1);}
  .card .ico{width:50px;height:50px;flex-shrink:0;border-radius:13px;display:flex;align-items:center;justify-content:center;color:var(--gold-soft);
    background:linear-gradient(180deg,#18243f,#0e1830);border:1px solid var(--line);box-shadow:inset 0 1px 0 rgba(255,255,255,.05);transition:color .2s;}
  .card:hover .ico{color:#ffd97e;}
  .card .ico svg{width:24px;height:24px;}
  .card .body{flex:1;min-width:0;}
  .card .kick{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold-dim);}
  .card .ttl{font-size:19px;font-weight:600;color:var(--text);margin-top:3px;letter-spacing:-.01em;}
  .card .sub{font-size:13px;color:var(--muted);margin-top:2px;}
  .card .arr{width:36px;height:36px;flex-shrink:0;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--muted);
    border:1px solid var(--line);background:rgba(255,255,255,.02);transition:.2s;}
  .card:hover .arr{color:var(--gold-soft);transform:translateX(3px);border-color:rgba(232,185,74,.25);}
  .card.primary{padding:24px;border-color:rgba(232,185,74,.16);background:linear-gradient(180deg,rgba(232,185,74,.05),rgba(255,255,255,0)),var(--surface);}
  .card.primary .ico{width:56px;height:56px;}
  .card.primary .ttl{font-size:22px;}

  .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px;}
  .grid3 .card{flex-direction:column;align-items:flex-start;gap:14px;padding:20px;}
  .grid3 .card .arr{display:none;}
  .grid3 .card .ttl{font-size:17px;}

  /* footer */
  .foot{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:34px;padding-top:20px;border-top:1px solid var(--line);flex-wrap:wrap;}
  .util{display:flex;align-items:center;gap:6px;}
  .util a{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--muted);border:1px solid var(--line);background:rgba(255,255,255,.02);transition:.15s;}
  .util a:hover{color:var(--gold-soft);border-color:rgba(232,185,74,.22);background:rgba(232,185,74,.05);}
  .util a svg{width:18px;height:18px;}
  .foot .meta{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.05em;color:var(--faint);}

  /* entrance */
  .reveal{opacity:0;animation:up .55s cubic-bezier(.22,1,.36,1) both;}
  @keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
  .r1{animation-delay:.05s}.r2{animation-delay:.13s}.r3{animation-delay:.2s}.r4{animation-delay:.27s}
  .r5{animation-delay:.36s}.r6{animation-delay:.46s}.r7{animation-delay:.56s}

  @media(max-width:640px){
    .nav-links{display:none;}.hamburger{display:flex;}
    .wrap{padding:38px 16px 44px;}
    .grid3{grid-template-columns:1fr;}
    .livebar{gap:16px;padding:12px 18px;}
  }
  @media(min-width:641px){.mobile-menu{display:none!important;}}
  @media(prefers-reduced-motion:reduce){*{animation:none!important;}.reveal{opacity:1!important;transform:none!important;}}
</style>
</head>
<body>
<div class="top-line"></div>

<nav class="navbar">
  <a href="/" class="brand"><span class="dot"></span>Ticky</a>
  <div class="nav-links">
    <a href="/termek"  class="nav-link">Termek</a>
    <a href="/tanar"   class="nav-link">Tanár</a>
    <a href="/osztaly" class="nav-link">Osztály</a>
    <a href="/qr"      class="nav-link">QR</a>
    <a href="/kijelzo" class="nav-link">Kijelző</a>
    <?php if ($nav_show_admin): ?>
      <a href="/admin" class="nav-link gold">Admin</a>
    <?php elseif ($nav_show_tester): ?>
      <a href="/tester" class="nav-link blue">Tester</a>
    <?php endif; ?>
    <?php if ($nav_logged_in): ?>
      <a href="/logout" class="nav-link">Kilépés</a>
    <?php else: ?>
      <a href="/login" class="nav-link login">Belépés</a>
    <?php endif; ?>
  </div>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
</nav>

<div class="mobile-menu" id="mobile-menu">
  <a href="/termek"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M9.5 8h.5M14 8h.5M9.5 12h.5M14 12h.5"/></svg>Termek</a>
  <a href="/tanar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>Tanár kereső</a>
  <a href="/osztaly"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2 9l10 5 10-5-10-5Z"/><path d="M5 11v5c0 1.5 3.1 3 7 3s7-1.5 7-3v-5"/></svg>Osztály nézet</a>
  <a href="/qr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M7.5 12h9"/></svg>QR Generátor</a>
  <a href="/kijelzo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>Kijelző</a>
  <a href="/support"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="m4 6.5 8 5.5 8-5.5"/></svg>Support</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V5.5a3 3 0 0 1 6 0V7"/><rect x="7" y="7" width="10" height="11" rx="5"/><path d="M7 11H4M7 15H4M20 11h-3M20 15h-3M12 7v11"/></svg>Bug report</a>
  <?php if ($nav_show_admin): ?>
    <a href="/admin" class="mm-gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>Admin panel</a>
  <?php elseif ($nav_show_tester): ?>
    <a href="/tester" class="mm-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3"/></svg>Tester felület</a>
  <?php endif; ?>
  <?php if ($nav_logged_in): ?>
    <a href="/logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2M9 12h11M17 9l3 3-3 3"/></svg>Kilépés</a>
  <?php else: ?>
    <a href="/login"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2v-2M4 12h11M11 9l3 3-3 3"/></svg>Belépés</a>
  <?php endif; ?>
</div>

<div class="wrap">
  <div class="hero">
    <div class="kicker reveal r1"><span class="kd"></span>Terem-információ · élő</div>
    <h1 class="wordmark reveal r2">Ticky</h1>
    <div class="hero-rule reveal r2"></div>
    <p class="tagline reveal r3">Digitális terem-azonosító rendszer</p>
    <div class="status reveal r3">
      <span class="live"></span>
      <?= htmlspecialchars($nap_nevek[$nap]) ?><span class="sp">·</span><span id="clock"><?= htmlspecialchars($ido) ?></span><span class="sp">·</span>Aktív
    </div>
  </div>

  <div class="livebar reveal r4" id="livebar">
    <div class="lstat"><span class="ld free"></span>Szabad <span class="ln" id="ls-free"><span class="skel"></span></span></div>
    <div class="ldiv"></div>
    <div class="lstat"><span class="ld busy"></span>Foglalt <span class="ln" id="ls-busy"><span class="skel"></span></span></div>
    <div class="ldiv"></div>
    <div class="lstat"><span class="ld all"></span>Termek <span class="ln" id="ls-all"><span class="skel"></span></span></div>
  </div>

  <div class="cards">
    <a href="/termek" class="card primary reveal r5">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M9.5 8h.5M14 8h.5M9.5 12h.5M14 12h.5M9.5 16h.5M14 16h.5"/></svg></span>
      <span class="body">
        <span class="kick">Élő nézet</span>
        <span class="ttl">Összes terem</span>
        <span class="sub">Szabad &amp; foglalt termek valós időben</span>
      </span>
      <span class="arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg></span>
    </a>

    <div class="grid3 reveal r6">
      <a href="/tanar" class="card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg></span>
        <span class="body"><span class="kick">Tanár</span><span class="ttl">Tanár kereső</span><span class="sub">Hol van most?</span></span>
      </a>
      <a href="/osztaly" class="card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2 9l10 5 10-5-10-5Z"/><path d="M5 11v5c0 1.5 3.1 3 7 3s7-1.5 7-3v-5"/></svg></span>
        <span class="body"><span class="kick">Osztály</span><span class="ttl">Osztály nézet</span><span class="sub">Hol van most?</span></span>
      </a>
      <a href="/qr" class="card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M7.5 12h9"/></svg></span>
        <span class="body"><span class="kick">QR</span><span class="ttl">QR Generátor</span><span class="sub">Nyomtatható kódok</span></span>
      </a>
    </div>
  </div>

  <div class="foot reveal r7">
    <div class="util">
      <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" title="Eseménynaptár"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M3.5 9h17M8 3v3M16 3v3"/></svg></a>
      <a href="/support" title="Support"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="m4 6.5 8 5.5 8-5.5"/></svg></a>
      <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" title="Bug report"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V5.5a3 3 0 0 1 6 0V7"/><rect x="7" y="7" width="10" height="11" rx="5"/><path d="M7 11H4M7 15H4M20 11h-3M20 15h-3M12 7v11"/></svg></a>
    </div>
    <span class="meta">Ticky v1.0 · Render · Supabase · PHP</span>
  </div>
</div>

<script>
  // mobil menü
  function toggleMenu(){
    const m=document.getElementById('mobile-menu'),h=document.getElementById('hamburger');
    const open=m.classList.toggle('open');h.classList.toggle('open',open);
  }
  document.addEventListener('click',function(e){
    if(!e.target.closest('#mobile-menu')&&!e.target.closest('#hamburger')){
      document.getElementById('mobile-menu').classList.remove('open');
      document.getElementById('hamburger').classList.remove('open');
    }
  });

  // élő óra (perc)
  (function(){
    const el=document.getElementById('clock');
    function tick(){
      const n=new Date();
      el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
    }
    setInterval(tick,15000);
  })();

  // élő terem-számláló (valós adat a /api/termek-ből)
  (function(){
    const bar=document.getElementById('livebar');
    fetch('/api/termek?allapot=1',{headers:{'Accept':'application/json'},cache:'no-store'})
      .then(r=>r.json())
      .then(d=>{
        const rooms=d.termek||[];
        const free=rooms.filter(r=>r.allapot==='szabad').length;
        const busy=rooms.filter(r=>r.allapot==='foglalt').length;
        document.getElementById('ls-all').textContent=rooms.length;
        if(free+busy>0){
          document.getElementById('ls-free').textContent=free;
          document.getElementById('ls-busy').textContent=busy;
        }else{
          // hétvége / tanításon kívül: nincs státusz
          document.getElementById('ls-free').textContent='–';
          document.getElementById('ls-busy').textContent='–';
        }
      })
      .catch(function(){ bar.style.display='none'; });
  })();
</script>
</body>
</html>
<?php
    exit;
}

// ROUTES – $params mindig inicializálva van, nincs undefined warning
if ($uri === '/api/ping') { json_response(['status'=>'ok','time'=>date('Y-m-d H:i:s')]); }

if ($uri === '/termek') { require __DIR__.'/pages/termek.php'; exit; }

// Tanár – fix: $params inicializálva mielőtt olvasódna
$params = match_route('/tanar/{kod}', $uri);
if ($uri === '/tanar' || $params !== false) {
    if ($params !== false) $_GET['kod'] = $params['kod'];
    require __DIR__.'/pages/tanar.php'; exit;
}

// Osztály
$params = match_route('/osztaly/{kod}', $uri);
if ($uri === '/osztaly' || $params !== false) {
    if ($params !== false) $_GET['kod'] = $params['kod'];
    require __DIR__.'/pages/osztaly.php'; exit;
}

if ($uri === '/qr')      { require __DIR__.'/pages/qr.php'; exit; }
if ($uri === '/kijelzo') { require __DIR__.'/pages/kijelzo.php'; exit; }
if ($uri === '/support') { require __DIR__.'/pages/support.php'; exit; }

// Napirend – előbb mint /terem/{szam}!
$params = match_route('/terem/{szam}/nap', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/pages/napirend.php'; exit;
}

$params = match_route('/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/pages/terem.php'; exit;
}

if ($uri === '/api/termek')   { require __DIR__.'/api/termek.php'; exit; }
if ($uri === '/api/tanarok')  { require __DIR__.'/api/tanarok.php'; exit; }
if ($uri === '/api/osztalyok') { require __DIR__.'/api/osztalyok.php'; exit; }

$params = match_route('/api/tanar/{kod}/orarend', $uri);
if ($params !== false) {
    $_GET['kod'] = $params['kod'];
    require __DIR__.'/api/tanar_orarend.php'; exit;
}

$params = match_route('/api/osztaly/{kod}/orarend', $uri);
if ($params !== false) {
    $_GET['kod'] = $params['kod'];
    require __DIR__.'/api/osztaly_orarend.php'; exit;
}

$params = match_route('/api/osztaly/{kod}/het', $uri);
if ($params !== false) {
    $_GET['kod'] = $params['kod'];
    require __DIR__.'/api/osztaly_het.php'; exit;
}

$params = match_route('/api/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/terem.php'; exit;
}

$params = match_route('/api/napirend/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/napirend.php'; exit;
}

if ($uri === '/login')  { require __DIR__.'/pages/login.php';  exit; }
if ($uri === '/logout') { require __DIR__.'/pages/logout.php'; exit; }

if ($uri === '/api/auth/login')  { require __DIR__.'/api/auth/login.php'; exit; }
if ($uri === '/api/auth/logout') { require __DIR__.'/api/auth/logout.php'; exit; }


if ($uri === '/admin') {
    if (!admin_can_see_ui()) {
        header('Location: /login?from=/admin');
        exit;
    }
    require __DIR__.'/pages/admin.php';
    exit;
}

// Tester route – csak admin vagy tester szerepű usereknek
if ($uri === '/tester') {
    $tu = ticky_current_user();
    if (!$tu || !in_array($tu['szerep'] ?? '', ['admin', 'tester'], true)) {
        header('Location: /login?from=/tester');
        exit;
    }
    require __DIR__.'/pages/tester.php';
    exit;
}


if ($uri === '/api/admin/felhasznalok') { require __DIR__.'/api/admin_felhasznalok.php'; exit; }
$params = match_route('/api/admin/felhasznalo/{id}', $uri);
if ($uri === '/api/admin/felhasznalo' || $params !== false) {
    if ($params !== false) $_GET['id'] = $params['id'];
    require __DIR__.'/api/admin_felhasznalo.php'; exit;
}
if ($uri === '/api/admin/tanar') { require __DIR__.'/api/admin_tanar.php'; exit; }
if ($uri === '/api/admin/diagnosztika') { require __DIR__.'/api/admin_diagnosztika.php'; exit; }
if ($uri === '/api/admin/import') { require __DIR__.'/api/admin_import.php'; exit; }
if ($uri === '/api/admin/github_sync') { require __DIR__.'/api/admin_github_sync.php'; exit; }
if ($uri === '/api/admin/szunetek') { require __DIR__.'/api/admin_szunet.php'; exit; }
$params = match_route('/api/admin/szunet/{id}', $uri);
if ($uri === '/api/admin/szunet' || $params !== false) {
    if ($params !== false) $_GET['id'] = $params['id'];
    require __DIR__.'/api/admin_szunet.php'; exit;
}

$params = match_route('/api/admin/terem/{szam}', $uri);
if ($params !== false) {
    $_GET['szam'] = $params['szam'];
    require __DIR__.'/api/admin_terem.php'; exit;
}


// 404
http_response_code(404);
echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>404</title><link rel="icon" type="image/png" href="/favicon.png"><style>body{background:#070b15;color:rgba(255,255,255,.5);font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:12px;}h1{color:white;font-size:48px;}a{color:#f3cf7a;text-decoration:none;}</style></head><body><h1>404</h1><p>Az oldal nem található</p><a href="/">← Vissza</a></body></html>';
exit;
