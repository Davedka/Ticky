<?php
// utils/_layout.php — Ticky (újratervezett dizájn): közös fejléc, navigáció és lábléc.
//
// Használat egy oldalon (a projekt gyökeréből indulva):
//   require __DIR__ . '/utils/_layout.php';
//   ticky_head('Termek', 'termek');     // 1. cím, 2. aktív menüpont kulcsa
//   ... az oldal HTML-je ...
//   ticky_foot();
//
// Opciók (4. paraméter, asszociatív tömb):
//   'qr'         => true   -> betölti a qrcode.js könyvtárat (csak a QR oldalon kell)
//   'chrome'     => false  -> elrejti a navigációt/sidebart (teljes képernyős oldalakhoz: kijelző, belépés)
//   'body_class' => '...'  -> extra class a <body>-ra

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/helpers.php';

function ticky_head(string $title = 'Ticky', string $aktiv = '', string $cim = '', array $opts = []): void
{
    if (function_exists('send_security_headers')) {
        send_security_headers();
    }

    $chrome   = $opts['chrome'] ?? true;
    $with_qr  = $opts['qr'] ?? false;
    $bodyCls  = trim((string) ($opts['body_class'] ?? ''));
    $GLOBALS['__ticky_chrome'] = $chrome;

    $links = [
        ['/termek',  'Termek',  'termek'],
        ['/tanar',   'Tanár',   'tanar'],
        ['/osztaly', 'Osztály', 'osztaly'],
        ['/qr',      'QR',      'qr'],
        ['/kijelzo', 'Kijelző', 'kijelzo'],
    ];

    $nav_user        = function_exists('ticky_current_user') ? ticky_current_user() : null;
    $nav_show_admin  = function_exists('admin_can_see_ui') && admin_can_see_ui();
    $nav_show_tester = $nav_user && (($nav_user['szerep'] ?? '') === 'tester');
    $nav_logged_in   = $nav_show_admin || is_array($nav_user);

    $page_title = ($title === 'Ticky') ? 'Ticky — Élő terem-tábla' : ('Ticky · ' . $title);
    ?><!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="theme-color" content="#060c16">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;0,900;1,600;1,700&family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php if ($with_qr): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php endif; ?>
<style>
:root{
  --bg:#060c16; --bg-2:#091324; --ink:#f3efe6;
  --muted:rgba(243,239,230,.46); --faint:rgba(243,239,230,.28); --ghost:rgba(243,239,230,.16);
  --line:rgba(243,239,230,.09); --line-2:rgba(243,239,230,.06);
  --gold:#cda349; --gold-2:#f0c76b;
  --green:#34d9a4; --green-2:#7bf0cf; --green-soft:rgba(52,217,164,.12);
  --red:#ff6b82; --red-2:#ff96a6; --red-soft:rgba(255,107,130,.12);
  --navy-glow:rgba(31,86,160,.42);
  --r:16px;
  --shadow:0 1px 0 rgba(255,255,255,.05) inset,0 24px 50px -30px rgba(0,0,0,.85);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--bg);min-height:100vh;
  line-height:1.5;-webkit-font-smoothing:antialiased;overflow-x:hidden;transition:background .6s ease;
  background-image:
    radial-gradient(120% 85% at 12% -12%, var(--navy-glow) 0%, transparent 56%),
    radial-gradient(95% 75% at 102% 112%, rgba(205,163,73,.12) 0%, transparent 55%),
    radial-gradient(70% 55% at 50% 38%, rgba(9,19,36,.55), transparent 72%);
}
body.t-szabad{background-image:radial-gradient(120% 85% at 12% -12%, rgba(52,217,164,.22) 0%, transparent 56%),radial-gradient(95% 75% at 102% 112%, rgba(31,86,160,.18) 0%, transparent 55%);}
body.t-foglalt{background-image:radial-gradient(120% 85% at 12% -12%, rgba(255,107,130,.20) 0%, transparent 56%),radial-gradient(95% 75% at 102% 112%, rgba(205,163,73,.10) 0%, transparent 55%);}

/* texture layers */
.grid-tex{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.9;
  background-image:linear-gradient(rgba(243,239,230,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(243,239,230,.018) 1px,transparent 1px);
  background-size:46px 46px;mask-image:radial-gradient(120% 100% at 50% 0%,#000 35%,transparent 100%);}
.grain{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.05;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");}
.vignette{position:fixed;inset:0;z-index:1;pointer-events:none;background:radial-gradient(130% 110% at 50% 30%,transparent 55%,rgba(0,0,0,.45) 100%);}
.top-line{position:fixed;top:0;left:0;right:0;height:1px;z-index:300;background:linear-gradient(90deg,transparent,var(--gold) 30%,var(--gold-2) 50%,var(--gold) 70%,transparent);}

/* typography helpers */
.serif{font-family:'Playfair Display',serif}
.mono{font-family:'DM Mono',monospace;font-feature-settings:"tnum" 1;letter-spacing:.02em}
.kicker{font-size:11px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:var(--faint)}
.num{font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}

/* ===== NAV ===== */
.nav{position:sticky;top:0;z-index:120;height:62px;padding:0 26px;display:flex;align-items:center;justify-content:space-between;
  background:rgba(6,12,22,.72);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-bottom:1px solid var(--line);}
.nav-l{display:flex;align-items:center;gap:13px;min-width:0}
.brand{display:flex;align-items:center;gap:9px;font-family:'Playfair Display',serif;font-size:19px;font-weight:800;color:var(--ink);letter-spacing:.2px}
.b-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);box-shadow:0 0 10px var(--gold);animation:pd 2.4s infinite;flex-shrink:0}
.nav-sep{color:var(--ghost)}
.nav-sub{font-size:13px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:38vw}
.nav-r{display:flex;align-items:center;gap:4px}
.nav-links{display:flex;align-items:center;gap:2px}
.nav-link{font-size:13px;font-weight:500;color:var(--muted);padding:8px 12px;border-radius:9px;transition:.16s;white-space:nowrap}
.nav-link:hover{color:var(--ink);background:rgba(243,239,230,.05)}
.nav-link.on{color:var(--gold-2);background:rgba(205,163,73,.1)}
.nav-link.gold{color:rgba(205,163,73,.85);border:1px solid rgba(205,163,73,.24)}
.nav-link.gold:hover{color:var(--gold-2);background:rgba(205,163,73,.1)}
.nav-link.blue{color:rgba(123,170,240,.9);border:1px solid rgba(96,165,250,.28)}
.nav-live{display:flex;align-items:center;gap:8px;margin-left:8px;padding:7px 13px;border-radius:999px;border:1px solid var(--line);background:rgba(243,239,230,.03)}
.nav-live .d{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 8px var(--green);animation:pd 2s infinite}
.nav-live .t{font-size:12px;color:var(--muted)}
.nav-live .t b{color:var(--ink);font-weight:600}
.nav-burger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:9px;border:1px solid var(--line);background:rgba(243,239,230,.04)}
.nav-burger span{width:18px;height:2px;background:var(--ink);border-radius:2px;transition:.25s;opacity:.8}
.nav-burger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.nav-burger.open span:nth-child(2){opacity:0}
.nav-burger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.mobile-menu{display:none;position:fixed;top:62px;left:0;right:0;z-index:115;flex-direction:column;gap:3px;padding:12px 16px 18px;
  background:rgba(6,12,22,.97);backdrop-filter:blur(24px);border-bottom:1px solid var(--line)}
.mobile-menu.open{display:flex}
.mobile-menu a{font-size:15px;font-weight:500;padding:12px 14px;border-radius:10px;color:var(--muted);border:1px solid transparent;transition:.15s}
.mobile-menu a:hover{background:rgba(243,239,230,.06);color:var(--ink)}
.mobile-menu a.on{background:rgba(205,163,73,.1);border-color:rgba(205,163,73,.22);color:var(--gold-2)}

/* sidebar (desktop) */
.sidebar{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:110;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;
  background:rgba(6,12,22,.85);backdrop-filter:blur(20px);border:1px solid var(--line);border-left:none;border-radius:0 12px 12px 0}
.sb-item{position:relative;width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--muted);transition:.18s}
.sb-item:hover{background:rgba(243,239,230,.1);color:var(--ink)}
.sb-item::after{content:attr(data-label);position:absolute;left:46px;top:50%;transform:translateY(-50%);background:rgba(6,12,22,.96);color:rgba(243,239,230,.88);font-size:12px;font-weight:500;padding:5px 11px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:.15s;border:1px solid var(--line)}
.sb-item:hover::after{opacity:1}
.sb-div{width:20px;height:1px;background:var(--line);margin:2px 0}

/* ===== SHELL ===== */
.shell{position:relative;z-index:10}
.page{animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both}
.wrap{max-width:1180px;margin:0 auto;padding:0 26px}
@media(max-width:560px){.wrap{padding:0 14px}}

/* ===== CARD ===== */
.card{position:relative;border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);
  background:linear-gradient(180deg,rgba(243,239,230,.052),rgba(243,239,230,.014));backdrop-filter:blur(6px)}
.card::after{content:'';position:absolute;left:14px;right:14px;top:0;height:1px;background:linear-gradient(90deg,transparent,rgba(243,239,230,.18),transparent);opacity:.6}

/* buttons / controls */
.btn{display:inline-flex;align-items:center;gap:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;border-radius:10px;padding:9px 16px;cursor:pointer;transition:.18s;border:1px solid var(--line);background:rgba(243,239,230,.04);color:var(--muted)}
.btn:hover{color:var(--ink);background:rgba(243,239,230,.08)}
.btn-gold{border:none;color:#1a1206;background:linear-gradient(135deg,var(--gold-2),var(--gold));box-shadow:0 8px 22px -10px rgba(205,163,73,.6)}
.btn-gold:hover{transform:translateY(-1px);color:#1a1206}
.btn .ic{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.4}

.pill{display:inline-flex;align-items:center;gap:7px;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 10px;border-radius:999px}
.pill .pd{width:5px;height:5px;border-radius:50%;animation:pd 2s infinite}
.pill.free{background:var(--green-soft);border:1px solid rgba(52,217,164,.34);color:var(--green)}
.pill.free .pd{background:var(--green)}
.pill.busy{background:var(--red-soft);border:1px solid rgba(255,107,130,.4);color:var(--red)}
.pill.busy .pd{background:var(--red)}
.pill.idle{background:rgba(243,239,230,.06);color:var(--faint)}
.pill.idle .pd{background:var(--faint)}

.skel{position:relative;overflow:hidden;background:rgba(243,239,230,.04);border-radius:8px}
.skel::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(243,239,230,.06),transparent);animation:sh 1.3s infinite}
@keyframes sh{100%{transform:translateX(100%)}}
.empty{text-align:center;padding:70px 0;color:var(--muted)}
.empty span{font-size:42px;display:block;margin-bottom:12px;opacity:.7}

/* ===== HOME ===== */
.home{padding:64px 0 60px;text-align:center}
.home .eyebrow{margin-bottom:22px}
.home h1{font-family:'Playfair Display',serif;font-weight:900;font-size:clamp(58px,11vw,128px);line-height:.92;letter-spacing:-2px;
  background:linear-gradient(180deg,#fff,#d8d2c4);-webkit-background-clip:text;background-clip:text;color:transparent}
.home h1 .dot{color:var(--gold);-webkit-text-fill-color:var(--gold)}
.home .tag{margin-top:14px;color:var(--muted);font-size:16px;letter-spacing:.02em}
.snap{display:inline-flex;align-items:stretch;gap:0;margin-top:30px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:rgba(243,239,230,.025);box-shadow:var(--shadow)}
.snap .s{padding:14px 26px;text-align:center;border-right:1px solid var(--line-2);min-width:96px}
.snap .s:last-child{border-right:none}
.snap .s .v{font-family:'DM Mono',monospace;font-size:30px;font-weight:500;line-height:1}
.snap .s .k{margin-top:6px;font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;display:flex;align-items:center;justify-content:center;gap:6px;color:var(--faint)}
.snap .s.g .v{color:var(--green)} .snap .s.r .v{color:var(--red)}
.dotmini{width:5px;height:5px;border-radius:50%}
.home-grid{margin-top:46px;display:grid;grid-template-columns:repeat(12,1fr);gap:14px;text-align:left}
.tile{position:relative;border:1px solid var(--line);border-radius:var(--r);padding:24px;cursor:pointer;overflow:hidden;transition:transform .2s,border-color .2s;background:linear-gradient(180deg,rgba(243,239,230,.05),rgba(243,239,230,.012));box-shadow:var(--shadow);display:block}
.tile:hover{transform:translateY(-3px);border-color:rgba(243,239,230,.22)}
.tile .ix{font-family:'DM Mono',monospace;font-size:12px;color:var(--faint)}
.tile h3{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;margin-top:10px}
.tile p{font-size:13px;color:var(--muted);margin-top:4px}
.tile .arr{position:absolute;right:22px;bottom:20px;color:var(--gold-2);font-size:18px;transition:transform .2s}
.tile:hover .arr{transform:translate(4px,-2px)}
.tile.hero{grid-column:span 12;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:30px 32px;
  background:linear-gradient(120deg,rgba(31,86,160,.18),rgba(243,239,230,.02) 60%)}
.tile.hero::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(205,163,73,.06));opacity:0;transition:.4s}
.tile.hero:hover::before{opacity:1}
.tile.hero .scan{position:absolute;top:0;bottom:0;width:120px;background:linear-gradient(90deg,transparent,rgba(243,239,230,.06),transparent);animation:scan 5s linear infinite}
.tile.hero h3{font-size:34px}
.tile.hero .mini{display:flex;gap:22px;align-items:center}
.tile.hero .mini .m{text-align:right}
.tile.hero .mini .m .v{font-family:'DM Mono',monospace;font-size:26px;line-height:1}
.tile.hero .mini .m .l{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--faint);margin-top:4px}
.tile.sm{grid-column:span 4}
@media(max-width:760px){.tile.sm{grid-column:span 12}.tile.hero{flex-direction:column;align-items:flex-start}.tile.hero .mini{align-self:stretch;justify-content:space-between}}
.home-foot{margin-top:40px;text-align:center;font-size:12px;color:var(--ghost);letter-spacing:.04em}

/* ===== SECTION HEAD ===== */
.shead{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:34px 0 18px;flex-wrap:wrap}
.shead .ix{font-family:'DM Mono',monospace;font-size:13px;color:var(--gold);margin-bottom:6px}
.shead h2{font-family:'Playfair Display',serif;font-size:clamp(28px,4vw,40px);font-weight:700;letter-spacing:-.5px}
.shead .sub{font-size:13px;color:var(--muted);margin-top:4px}

/* filter bar */
.fbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.fgrp{display:flex;gap:8px;flex-wrap:wrap}
.fbtn{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:500;padding:8px 15px;border-radius:10px;cursor:pointer;transition:.15s;border:1px solid var(--line);background:rgba(243,239,230,.04);color:var(--muted)}
.fbtn:hover{color:var(--ink);background:rgba(243,239,230,.08)}
.fbtn .c{font-family:'DM Mono',monospace;font-size:13px;font-weight:500}
.fbtn .d{width:7px;height:7px;border-radius:50%}
.fbtn.on{color:var(--ink);border-color:rgba(243,239,230,.24);background:rgba(243,239,230,.1)}
.fbtn.on-g{color:var(--green);border-color:rgba(52,217,164,.4);background:var(--green-soft)}
.fbtn.on-r{color:var(--red);border-color:rgba(255,107,130,.4);background:var(--red-soft)}
.search{position:relative}
.search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);stroke:var(--faint);fill:none;stroke-width:2.4;width:14px;height:14px}
.search input{width:230px;max-width:60vw;background:rgba(243,239,230,.05);border:1px solid var(--line);color:var(--ink);border-radius:10px;padding:9px 14px 9px 34px;font-size:13px;font-family:'DM Sans',sans-serif;transition:.18s}
.search input::placeholder{color:var(--faint)}
.search input:focus{outline:none;border-color:rgba(205,163,73,.45);background:rgba(243,239,230,.07)}

/* ROOM GRID */
.rgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(186px,1fr));gap:13px;padding-bottom:56px}
.rcard{position:relative;border:1px solid var(--line);border-radius:14px;padding:16px;cursor:pointer;overflow:hidden;min-height:120px;display:block;
  background:linear-gradient(180deg,rgba(243,239,230,.045),rgba(243,239,230,.012));transition:transform .16s,border-color .16s;animation:cardIn .4s cubic-bezier(.22,1,.36,1) both;box-shadow:var(--shadow)}
.rcard:hover{transform:translateY(-2px);border-color:rgba(243,239,230,.22)}
.rcard .edge{position:absolute;top:0;left:0;right:0;height:2px}
.rcard.free .edge{background:linear-gradient(90deg,var(--green),transparent)}
.rcard.busy .edge{background:linear-gradient(90deg,var(--red),transparent)}
.rcard.free{background:linear-gradient(180deg,var(--green-soft),rgba(243,239,230,.01))}
.rcard.busy{background:linear-gradient(180deg,var(--red-soft),rgba(243,239,230,.01));box-shadow:var(--shadow),0 0 26px -16px rgba(255,107,130,.5)}
.rc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.rc-lab{font-size:9px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--faint)}
.rc-num{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;line-height:1;margin-top:2px}
.rc-body{margin-top:11px}
.rc-free{font-size:12px;color:var(--green);opacity:.85}
.rc-t{font-size:12.5px;font-weight:500;color:rgba(243,239,230,.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-m{font-size:11.5px;color:var(--faint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.rc-bar{height:3px;border-radius:3px;background:rgba(243,239,230,.1);overflow:hidden;margin-top:9px}
.rc-bar i{display:block;height:100%;border-radius:3px;background:linear-gradient(90deg,#e8334a,var(--red))}

/* ===== TEREM DETAIL ===== */
.detail{display:grid;grid-template-columns:380px 1fr;gap:22px;padding:34px 0 60px;align-items:start}
@media(max-width:920px){.detail{grid-template-columns:1fr}}
.detail.solo{grid-template-columns:minmax(0,520px);justify-content:center}
.stcard{overflow:hidden}
.stcard .head{padding:22px 24px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border-bottom:1px solid var(--line)}
.stcard .head .num{font-family:'Playfair Display',serif;font-size:52px;font-weight:800;line-height:.9;letter-spacing:-1px}
.stcard .body{padding:22px 24px}
.kv{margin-bottom:16px}
.kv .k{font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-bottom:3px}
.kv .v{font-family:'Playfair Display',serif;font-size:22px;font-weight:700}
.kv2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.prog{margin-top:6px}
.prog .track{height:6px;border-radius:6px;background:rgba(243,239,230,.1);overflow:hidden}
.prog .track i{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,#e8334a,var(--red));transition:width .6s}
.prog .lbl{display:flex;justify-content:space-between;margin-top:7px;font-size:11px;color:var(--faint)}
.prog .lbl .mid{color:var(--red);font-weight:600;font-family:'DM Mono',monospace}
.free-block{text-align:center;padding:14px 0}
.free-block .ic{font-size:42px;display:block;margin-bottom:10px}
.free-block .ttl{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--green)}
.free-block .sub{font-size:13px;color:var(--muted);margin-top:3px}
.next{margin-top:18px;border:1px solid var(--line);border-radius:12px;padding:13px 15px;background:rgba(243,239,230,.03)}
.next .k{font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-bottom:7px}
.next .row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.next .row .who{font-size:13px;font-weight:500;color:rgba(243,239,230,.78)}
.next .row .tm{font-size:12px;color:var(--faint);font-family:'DM Mono',monospace}
.stcard .foot{padding:16px 24px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line)}
.stcard .foot a{font-size:13px;font-weight:600;color:var(--gold-2)}
.stcard .foot .br{font-family:'Playfair Display',serif;color:var(--ghost);font-size:13px;font-weight:700}

/* timetable */
.ttcard{padding:22px 24px 26px;overflow:hidden}
.ttcard .tt-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.ttcard .tt-h .t{font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--faint)}
.tt{display:grid;grid-template-columns:42px repeat(5,1fr);min-width:480px}
.tt-wrap{overflow-x:auto}
.tt-col-h{padding:0 4px 10px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--faint);border-bottom:1px solid var(--line)}
.tt-col-h.today{color:var(--gold-2)}
.tt-time{position:relative;border-right:1px solid var(--line-2)}
.tt-day{position:relative;border-right:1px solid var(--line-2);overflow:hidden}
.tt-day:last-child{border-right:none}
.tt-day.today{background:rgba(205,163,73,.03)}
.hl{position:absolute;left:0;right:0;height:1px;background:var(--line-2)}
.hl.h{background:rgba(243,239,230,.08)}
.tlab{position:absolute;right:5px;transform:translateY(-50%);font-size:9px;color:var(--ghost);font-family:'DM Mono',monospace;white-space:nowrap}
.blk{position:absolute;left:3px;right:3px;border-radius:8px;padding:5px 7px;overflow:hidden;border:1px solid rgba(243,239,230,.08);
  background:linear-gradient(160deg,rgba(31,86,160,.55),rgba(11,30,58,.6));transition:.16s}
.blk:hover{filter:brightness(1.25);z-index:5}
.blk.now{background:linear-gradient(160deg,rgba(205,163,73,.4),rgba(160,110,30,.32));border-color:rgba(205,163,73,.55)}
.blk.past{opacity:.32}
.blk .a{font-family:'Playfair Display',serif;font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}
.blk.now .a{color:var(--gold-2)}
.blk .b{font-size:9px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.nowline{position:absolute;left:0;right:0;height:2px;z-index:8;background:linear-gradient(90deg,transparent,var(--red) 18%,var(--red) 82%,transparent)}
.nowline .nd{position:absolute;left:-1px;top:-3px;width:8px;height:8px;border-radius:50%;background:var(--red);box-shadow:0 0 7px var(--red);animation:pd 1.6s infinite}

/* ===== FINDER (tanar/osztaly) ===== */
.finder{max-width:440px;margin:0 auto;padding:34px 0 60px}
.fcard{overflow:hidden}
.fcard .top{padding:18px 22px 0;display:flex;align-items:center;justify-content:space-between}
.fcard .top .br{font-family:'Playfair Display',serif;color:var(--ghost);font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px}
.fcard .top .br .d{width:6px;height:6px;border-radius:50%;background:var(--gold);box-shadow:0 0 6px var(--gold)}
.fcard .top .lbl{font-size:12px;color:var(--faint)}
.fcard .sel-row{padding:16px 22px 20px;border-bottom:1px solid var(--line)}
.fcard .sel-row .hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:11px}
.fcard .sel-row .hdr .k{font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--faint)}
.csel{width:100%;appearance:none;padding:12px 38px 12px 14px;border-radius:11px;border:1px solid var(--line);background:rgba(243,239,230,.05);color:var(--ink);font-family:'DM Sans',sans-serif;font-size:15px;cursor:pointer;transition:.18s;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23cda349' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}
.csel:focus{outline:none;border-color:rgba(205,163,73,.5)}
.csel option{background:#0b1426;color:var(--ink)}
.csel optgroup{background:#081020;color:var(--muted);font-weight:700}
.fblock{padding:18px 22px;border-bottom:1px solid var(--line)}
.fblock .ph{text-align:center;padding:14px 0}
.fblock .ph .ic{font-size:34px;display:block;margin-bottom:9px}
.fblock .ph p{font-size:13px;color:var(--muted)}
.flist{padding:18px 22px}
.flist .ttl{font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-bottom:12px}
.orow{display:flex;align-items:flex-start;gap:11px;padding:9px 8px;margin:0 -4px;border-radius:10px;transition:.14s}
.orow:hover{background:rgba(243,239,230,.04)}
.orow.now{background:var(--red-soft);border-left:3px solid var(--red);border-radius:0 10px 10px 0}
.orow.past{opacity:.4}
.orow .n{font-family:'Playfair Display',serif;font-weight:700;font-size:16px;width:20px;text-align:right;flex-shrink:0;color:rgba(243,239,230,.8)}
.orow .ci{flex:1;min-width:0}
.orow .r1{display:flex;align-items:baseline;gap:7px;flex-wrap:wrap}
.orow .r1 .a{font-size:13px;font-weight:500;color:rgba(243,239,230,.85)}
.orow .r1 .b{font-size:11.5px;color:var(--faint)}
.orow .r2{font-size:11px;color:var(--ghost);font-family:'DM Mono',monospace;margin-top:2px}
.orow .dotn{width:7px;height:7px;border-radius:50%;background:var(--red);margin-top:6px;flex-shrink:0;animation:pd 2s infinite}
.gbadge{display:inline-flex;align-items:center;gap:4px;font-size:9px;font-weight:700;letter-spacing:.04em;padding:2px 8px;border-radius:6px;background:rgba(205,163,73,.13);border:1px solid rgba(205,163,73,.3);color:var(--gold-2)}
.grow{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 11px;border-radius:9px;background:rgba(243,239,230,.04);border:1px solid var(--line);margin-top:6px}
.grow .lft{display:flex;align-items:center;gap:8px;min-width:0;flex-wrap:wrap}
.grow .who{font-size:12.5px;color:rgba(243,239,230,.82)}
.grow .rm{font-family:'Playfair Display',serif;font-weight:700;color:var(--gold-2);font-size:14px;white-space:nowrap}
.fcard .foot{padding:14px 22px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line)}
.fcard .foot .ido{font-size:12px;color:var(--faint);font-family:'DM Mono',monospace}

/* ===== QR ===== */
.qrtop{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:34px 0 18px;flex-wrap:wrap}
.qrtop .acts{display:flex;gap:9px;flex-wrap:wrap}
.qsel-info{font-size:13px;color:var(--faint);margin-bottom:14px}
.qgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;padding-bottom:60px}
.qcard{position:relative;border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;flex-direction:column;align-items:center;gap:11px;cursor:pointer;transition:.15s;background:linear-gradient(180deg,rgba(243,239,230,.04),rgba(243,239,230,.01));box-shadow:var(--shadow);animation:cardIn .4s cubic-bezier(.22,1,.36,1) both}
.qcard:hover{transform:translateY(-2px);border-color:rgba(243,239,230,.22)}
.qcard.sel{border-color:var(--gold);background:rgba(205,163,73,.07)}
.qcard .chk{position:absolute;top:11px;right:11px;width:22px;height:22px;border-radius:50%;border:2px solid var(--ghost);display:flex;align-items:center;justify-content:center;transition:.15s}
.qcard.sel .chk{background:var(--gold);border-color:var(--gold)}
.qcard .chk svg{opacity:0;transition:.15s;stroke:#1a1206;fill:none;stroke-width:3}
.qcard.sel .chk svg{opacity:1}
.qcard .lab{font-size:9px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);text-align:center}
.qcard .qn{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;line-height:1}
.qwrap{background:#fff;border-radius:10px;padding:8px;display:flex}
.qwrap canvas,.qwrap img{display:block !important}
.qcard .url{font-size:10px;color:var(--ghost);text-align:center;word-break:break-all;max-width:150px}
.qcard .qbr{font-family:'Playfair Display',serif;font-size:11px;color:var(--ghost);font-weight:700}

/* ===== KIJELZO (fullscreen board) ===== */
.board{position:fixed;inset:0;z-index:200;background:var(--bg);display:flex;flex-direction:column;overflow:hidden;
  background-image:radial-gradient(80% 60% at 10% 0%,rgba(31,86,160,.4) 0%,transparent 55%),radial-gradient(60% 50% at 90% 100%,rgba(205,163,73,.1) 0%,transparent 50%)}
.board .scan{position:absolute;inset:0;z-index:2;pointer-events:none;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.05) 2px,rgba(0,0,0,.05) 4px)}
.bd-top{position:relative;z-index:5;height:62px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,12,22,.7);backdrop-filter:blur(16px);border-bottom:1px solid var(--line)}
.bd-brand{display:flex;align-items:center;gap:10px;font-family:'Playfair Display',serif;font-size:20px;font-weight:800}
.bd-center{display:flex;flex-direction:column;align-items:center;gap:1px}
.bd-center .dt{font-size:11px;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--faint)}
.bd-center .np{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:rgba(243,239,230,.85)}
.bd-right{display:flex;align-items:center;gap:14px}
.bd-clock{font-family:'DM Mono',monospace;font-size:28px;font-weight:500;letter-spacing:.04em;text-shadow:0 0 18px rgba(243,239,230,.18)}
.bd-clock .sec{color:var(--gold-2);font-size:19px;opacity:.75}
.bd-fg{display:flex;gap:6px}
.bd-fb{padding:6px 13px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--line);background:rgba(243,239,230,.05);color:var(--muted);transition:.18s}
.bd-fb:hover{color:var(--ink);background:rgba(243,239,230,.1)}
.bd-fb.on{background:rgba(205,163,73,.16);border-color:rgba(205,163,73,.42);color:var(--gold-2)}
.bd-fb.on-r{background:var(--red-soft);border-color:rgba(255,107,130,.42);color:var(--red)}
.bd-fb.on-g{background:var(--green-soft);border-color:rgba(52,217,164,.42);color:var(--green)}
.bd-exit{font-size:11px;color:var(--ghost)}
.bd-status{position:relative;z-index:5;height:34px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;background:rgba(6,12,22,.45);border-bottom:1px solid var(--line-2)}
.bd-stat{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:500;letter-spacing:.04em;text-transform:uppercase;color:var(--faint)}
.bd-stat .d{width:6px;height:6px;border-radius:50%}
.bd-stat .v{font-family:'DM Mono',monospace;font-size:13px}
.bd-div{width:1px;height:15px;background:var(--line)}
.bd-upd{font-size:11px;color:var(--ghost);font-family:'DM Mono',monospace}
.bd-main{position:relative;z-index:3;flex:1;padding:14px 18px;overflow:hidden}
.bgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:9px;height:100%;align-content:start;overflow-y:auto;scrollbar-width:none}
.bgrid::-webkit-scrollbar{display:none}
.bcard{border-radius:13px;padding:13px 15px;border:1px solid var(--line);min-height:112px;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;position:relative;animation:cardIn .4s cubic-bezier(.22,1,.36,1) both}
.bcard.free{background:rgba(52,217,164,.06);border-color:rgba(52,217,164,.16)}
.bcard.busy{background:rgba(255,107,130,.08);border-color:rgba(255,107,130,.2);box-shadow:0 0 22px -14px rgba(255,107,130,.5)}
.bc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:6px}
.bc-num{font-family:'Playfair Display',serif;font-size:29px;font-weight:700;line-height:1}
.bc-body{display:flex;flex-direction:column;justify-content:flex-end;gap:3px;margin-top:8px;flex:1}
.bc-t{font-family:'Playfair Display',serif;font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bc-m{font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bc-bar{height:2px;border-radius:2px;background:rgba(243,239,230,.1);overflow:hidden;margin-top:5px}
.bc-bar i{display:block;height:100%;background:linear-gradient(90deg,#e8334a,var(--red))}
.bc-time{display:flex;justify-content:space-between;font-size:10px;color:var(--ghost);margin-top:3px;font-family:'DM Mono',monospace}
.bc-time .rm{color:var(--red);font-weight:500}
.bc-free{font-size:12px;color:rgba(52,217,164,.6);font-weight:500}
.refresh-bar{position:absolute;bottom:0;left:0;right:0;height:2px;z-index:6;background:rgba(243,239,230,.06)}
.refresh-bar i{display:block;height:100%;background:linear-gradient(90deg,var(--navy-glow),var(--gold));width:0}
@media(max-width:640px){.bd-center{display:none}.bd-fg{display:none}.bd-top{padding:0 14px}.bd-status{padding:0 14px}}

/* ===== LOGIN ===== */
.login{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;
  background:#04090f;background-image:radial-gradient(70% 50% at 10% 0%,rgba(31,86,160,.34) 0%,transparent 55%),radial-gradient(50% 40% at 90% 100%,rgba(205,163,73,.1) 0%,transparent 50%)}
.login .box{width:100%;max-width:380px;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) both}
.login .brand-c{text-align:center;margin-bottom:30px}
.login .brand-c .b{display:inline-flex;align-items:center;gap:11px;font-family:'Playfair Display',serif;font-size:32px;font-weight:800;color:var(--ink)}
.login .brand-c .b .d{width:12px;height:12px;border-radius:50%;background:var(--gold);box-shadow:0 0 12px var(--gold);animation:pd 2.4s infinite}
.login .brand-c .sub{margin-top:11px;font-size:13px;color:var(--muted)}
.login .form{padding:28px}
.login label{display:block;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-bottom:8px}
.login .inp{width:100%;border-radius:10px;border:1px solid var(--line);background:rgba(243,239,230,.05);padding:12px 14px;color:var(--ink);font-size:14px;font-family:'DM Sans',sans-serif;margin-bottom:16px;transition:.18s}
.login .inp:focus{outline:none;border-color:rgba(205,163,73,.5);background:rgba(243,239,230,.08)}
.login .submit{width:100%;justify-content:center;padding:13px;font-size:14px;border:none}
.login .err{background:var(--red-soft);border:1px solid rgba(255,107,130,.34);color:var(--red-2);font-size:13px;padding:11px 14px;border-radius:10px;margin-bottom:16px}
.login .hint{text-align:center;font-size:12px;color:var(--faint);margin-top:18px}
.login .back{text-align:center;font-size:12px;margin-top:18px}
.login .back a{color:var(--gold-2)}

/* animations */
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.7)}}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin .6s linear}
@keyframes cardIn{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:none}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes scan{0%{left:-120px}100%{left:100%}}

@media(max-width:880px){.nav-sub{display:none}.nav-live .t b{display:inline}}
@media(max-width:760px){.sidebar{display:none}.nav-links{display:none}.nav-live{display:none}.nav-burger{display:flex}}
@media(min-width:761px){.mobile-menu{display:none!important}.nav-burger{display:none!important}}

/* print (QR) */
@media print{
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{background:#fff!important;background-image:none!important;color:#000!important}
  .grid-tex,.grain,.vignette,.top-line,.nav,.sidebar,.mobile-menu,.qrtop,.qsel-info{display:none!important}
  .qgrid{grid-template-columns:repeat(3,1fr)!important;gap:12mm!important;padding:8mm!important}
  .qcard{display:none!important}
  .qcard.print-me{display:flex!important;background:#fff!important;border:1.5px solid #ddd!important;box-shadow:none!important;page-break-inside:avoid!important}
  .qcard.print-me .chk{display:none!important}
  .qcard .lab{color:#888!important}.qcard .qn{color:#060c16!important}.qcard .url{color:#555!important}.qcard .qbr{color:#aaa!important}
  .qwrap{border:1px solid #eee!important}
}
</style>
<script>
/* Apró közös segéd a kliens-oldali kódhoz (minden oldalon elérhető: window.TK) */
window.TK = {
  $:  (s, r=document) => r.querySelector(s),
  $$: (s, r=document) => Array.from(r.querySelectorAll(s)),
  esc: s => String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),
  async api(path){
    const r = await fetch(path, { headers: { 'Accept':'application/json' }, credentials:'same-origin' });
    if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  },
  toMin(t){ if(!t) return 0; const p=String(t).slice(0,5).split(':'); return (+p[0]||0)*60+(+p[1]||0); },
  hm(t){ return String(t==null?'':t).slice(0,5); },
  nowMin(){ return window.TickyTime ? TickyTime.nowMinutes() : (new Date().getHours()*60+new Date().getMinutes()); },
  dayIndex(){ if(window.TickyTime) return TickyTime.weekdayIndex(); const d=new Date().getDay(); return d; },
  schoolDay(){ if(window.TickyTime) return TickyTime.schoolDayIndex(); const d=new Date().getDay(); return (d===0||d===6)?1:d; },
  pct(k,v){ const a=this.toMin(k), b=this.toMin(v), n=this.nowMin(); if(b<=a) return 0; return Math.min(100,Math.max(0,Math.round((n-a)/(b-a)*100))); },
  left(v){ return Math.max(0, this.toMin(v)-this.nowMin()); }
};
</script>
</head>
<body<?= $bodyCls ? ' class="'.htmlspecialchars($bodyCls).'"' : '' ?> data-page="<?= htmlspecialchars($aktiv) ?>">
<?php if (function_exists('render_time_sync_bootstrap')) render_time_sync_bootstrap(); ?>
<?php if ($chrome): ?>
<div class="grid-tex"></div><div class="grain"></div><div class="vignette"></div><div class="top-line"></div>

<aside class="sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="sb-item" data-label="Eseménynaptár">📅</a>
  <div class="sb-div"></div>
  <a href="/support" class="sb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="sb-item" data-label="Hibajelentés">🐛</a>
</aside>

<nav class="nav" id="appnav">
  <div class="nav-l">
    <a class="brand" href="/"><span class="b-dot"></span>Ticky</a>
    <?php if ($cim !== ''): ?><span class="nav-sep">·</span><span class="nav-sub"><?= htmlspecialchars($cim) ?></span><?php endif; ?>
  </div>
  <div class="nav-r">
    <div class="nav-links">
      <?php foreach ($links as [$href,$label,$key]): ?>
        <a class="nav-link<?= $aktiv === $key ? ' on' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <?php if ($nav_show_admin): ?>
        <a class="nav-link gold" href="/admin">⚙️ Admin</a>
      <?php elseif ($nav_show_tester): ?>
        <a class="nav-link blue" href="/tester">🧪 Tester</a>
      <?php endif; ?>
      <?php if ($nav_logged_in): ?>
        <a class="nav-link" href="/logout">Kilépés</a>
      <?php else: ?>
        <a class="nav-link<?= $aktiv === 'login' ? ' on' : '' ?>" href="/login">Belépés</a>
      <?php endif; ?>
    </div>
    <span class="nav-live"><span class="d"></span><span class="t"><b id="nav-day">—</b> · <span class="mono" id="nav-clock">—:—</span></span></span>
    <button class="nav-burger" id="navBurger" aria-label="Menü"><span></span><span></span><span></span></button>
  </div>
</nav>

<div class="mobile-menu" id="mmenu">
  <?php foreach ($links as [$href,$label,$key]): ?>
    <a class="<?= $aktiv === $key ? 'on' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
  <?php endforeach; ?>
  <a href="/support">✉️ Support</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener">🐛 Hibajelentés</a>
  <?php if ($nav_show_admin): ?><a class="gold" href="/admin">⚙️ Admin</a>
  <?php elseif ($nav_show_tester): ?><a class="blue" href="/tester">🧪 Tester</a><?php endif; ?>
  <?php if ($nav_logged_in): ?><a href="/logout">🚪 Kilépés</a><?php else: ?><a href="/login">🔑 Belépés</a><?php endif; ?>
</div>

<main class="shell">
<?php endif; /* chrome */ ?>
<?php
}

function ticky_foot(): void
{
    $chrome = $GLOBALS['__ticky_chrome'] ?? true;
    if ($chrome) echo "</main>\n";
    ?>
<script>
(function(){
  var days=['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'];
  function tick(){
    var hm = window.TickyTime ? TickyTime.formatHM() : (function(){var d=new Date();return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');})();
    document.querySelectorAll('#nav-clock,[data-clock]').forEach(function(e){e.textContent=hm;});
    var di = window.TickyTime ? TickyTime.weekdayIndex() : new Date().getDay();
    var d = document.getElementById('nav-day'); if(d) d.textContent = days[di] || '';
  }
  setInterval(tick, 1000); tick();

  var burger = document.getElementById('navBurger'), menu = document.getElementById('mmenu');
  if (burger && menu){
    burger.addEventListener('click', function(e){
      e.stopPropagation();
      var open = menu.classList.toggle('open');
      burger.classList.toggle('open', open);
    });
    document.addEventListener('click', function(e){
      if(!e.target.closest('#mmenu') && !e.target.closest('#navBurger')){
        menu.classList.remove('open'); burger.classList.remove('open');
      }
    });
  }
})();
</script>
</body>
</html>
<?php
}
