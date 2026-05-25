<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers(true);

$user = ticky_current_user();
if (!is_array($user)) {
    header('Location: /login?from=/tester');
    exit;
}

$role = (string) ($user['szerep'] ?? '');
if (!in_array($role, ['admin', 'tester'], true)) {
    http_response_code(403);
    exit('403 – Nincs jogosultságod.');
}

// ── CSP nonce (az oldal saját inline scriptjéhez) ──────
$csp_nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'nonce-{$csp_nonce}' https://cdn.tailwindcss.com 'unsafe-eval'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, max-age=0');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

$csrf = ticky_csrf_token();
$feedback_sent  = false;
$feedback_error = null;

function tester_clean_text(string $value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    return trim($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'feedback') {
    $honeypot = (string) ($_POST['kapcsolat'] ?? '');
    $rendered_at = (int) ($_SESSION['tester_form_render'] ?? 0);
    $too_fast    = $rendered_at > 0 && (time() - $rendered_at) < 2;

    if ($honeypot !== '' || $too_fast) {
        $feedback_sent = true;
    } elseif (!ticky_has_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $feedback_error = 'Érvénytelen CSRF token. Töltsd újra az oldalt.';
    } else {
        $wait = ticky_rate_limit_wait_seconds('tester_feedback', 600, 10, 600);
        if ($wait > 0) {
            $feedback_error = 'Túl sok feedback rövid idő alatt. Várj ' . $wait . ' másodpercet.';
        } else {
            $category    = (string) ($_POST['category'] ?? '');
            $title       = tester_clean_text((string) ($_POST['title'] ?? ''));
            $description = tester_clean_text((string) ($_POST['description'] ?? ''));
            $page_url    = tester_clean_text((string) ($_POST['page_url'] ?? ''));

            if (!mb_check_encoding($title, 'UTF-8') || !mb_check_encoding($description, 'UTF-8') || !mb_check_encoding($page_url, 'UTF-8')) {
                $feedback_error = 'Érvénytelen karakterkódolás a bemenetben.';
            } elseif (!in_array($category, ['bug', 'feature', 'feedback'], true)) {
                $feedback_error = 'Érvénytelen kategória.';
            } elseif ($title === '' || $description === '') {
                $feedback_error = 'Cím és leírás kötelező.';
            } elseif (mb_strlen($title, 'UTF-8') > 200) {
                $feedback_error = 'A cím legfeljebb 200 karakter lehet.';
            } elseif (mb_strlen($description, 'UTF-8') > 5000) {
                $feedback_error = 'A leírás legfeljebb 5000 karakter lehet.';
            } elseif ($page_url !== '' && !preg_match('#^/[\w\-./%?=&]*$#', $page_url)) {
                $feedback_error = 'A megadott oldal csak belső útvonal lehet (pl. /terem/204).';
            } else {
                $res = sb_request('POST', 'tester_feedback', [
                    'user_id'     => $user['id'],
                    'category'    => $category,
                    'title'       => $title,
                    'description' => $description,
                    'page_url'    => $page_url !== '' ? mb_substr($page_url, 0, 500, 'UTF-8') : null,
                    'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500, 'UTF-8'),
                ], [], 'service');

                if ($res['success']) {
                    $feedback_sent = true;
                    ticky_record_rate_limit_failure('tester_feedback', 600, 10, 600);
                } else {
                    $feedback_error = 'Mentési hiba. Próbáld újra.';
                }
            }
        }
    }
}

$_SESSION['tester_form_render'] = time();

$my_feedback = sb_get('tester_feedback', [
    'user_id' => 'eq.' . $user['id'],
    'select'  => 'id,category,title,status,created_at,admin_note',
    'order'   => 'created_at.desc',
    'limit'   => '10',
], 'service') ?: [];

// Saját feedback statisztika
$fb_stats = ['new' => 0, 'reviewed' => 0, 'fixed' => 0, 'wont_fix' => 0];
foreach ($my_feedback as $fb) {
    $s = (string) ($fb['status'] ?? 'new');
    if (isset($fb_stats[$s])) $fb_stats[$s]++;
}

$build_commit = substr(trim((string) (getenv('RENDER_GIT_COMMIT') ?: '')), 0, 7);
$build_branch = trim((string) (getenv('RENDER_GIT_BRANCH') ?: 'main'));
$build_time   = filemtime(__DIR__ . '/../index.php') ?: time();
$server_now_ms = (int) round(microtime(true) * 1000);

// Mit teszteljünk most (friss változások)
$focus = [
    ['🆕', 'Több órás blokk', 'Az osztály nézetben az egymás utáni azonos órák egy blokk: lássd a „2 óra” / „3 óra” badge-t és a teljes idősávot (pl. 07:30–10:00).'],
    ['🆕', 'Csoportsorok', 'Csoportbontásnál a sorok ne törjenek szét hosszú tanárnévnél vagy 3+ csoportnál.'],
    ['🆕', 'Lyukasóra', 'A hiányzó óra valóban üres-e (nem hiba), és helyesen ugrik a következő meglévő órára.'],
];

// Edge-case gyorslinkek (valós, trükkös esetek)
$quick = [
    ['🎓', '12.e (péntek, csoportbontás)', '/osztaly/12.e'],
    ['🎓', '13.a (3 órás blokk: bpét)', '/osztaly/13.a'],
    ['👩‍🏫', 'KM tanár (sok óra)', '/tanar/KM'],
    ['👩‍🏫', 'BO tanár (utolsó órák)', '/tanar/BO'],
    ['🏫', '104. terem', '/terem/104'],
    ['📺', 'Kijelző', '/kijelzo'],
];

$test_areas = [
    ['🏫', 'Termek live nézet', 'Minden terem státusza valóban frissül? Foglalt/szabad helyes?', '/termek'],
    ['👩‍🏫', 'Tanár kereső', 'Tanár napi órarendje. Lyukasóra, csoportbontás OK?', '/tanar'],
    ['🎓', 'Osztály nézet', 'Órarend, több órás blokk, csoportsorok, aktuális óra.', '/osztaly'],
    ['📺', 'Kijelző', 'Olvasható távolról? Auto-frissül? Szünet banner?', '/kijelzo'],
    ['📱', 'QR kódok', 'QR generál, és a helyes /terem/{szam}-ra visz?', '/qr'],
];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="no-referrer">
<title>Tester – Ticky</title>
<link rel="icon" type="image/png" href="/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  body{font-family:'DM Sans',sans-serif;background:#04090f;color:#fff;min-height:100vh;
    background-image:radial-gradient(ellipse 70% 50% at 10% 0%,rgba(26,74,138,.4) 0%,transparent 55%),
                     radial-gradient(ellipse 50% 40% at 90% 100%,rgba(96,165,250,.10) 0%,transparent 50%);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;
    background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);
    background-size:44px 44px;}
  .top{position:fixed;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,#60a5fa 30%,#a5d4ff 50%,#60a5fa 70%,transparent);
    box-shadow:0 0 16px rgba(96,165,250,.3);z-index:50;}
  .glass{background:rgba(255,255,255,.04);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);}
  .glass:hover{border-color:rgba(255,255,255,.12);}
  .chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid rgba(255,255,255,.12)}
  .chip.blue{color:#93c5fd;background:rgba(96,165,250,.12);border-color:rgba(96,165,250,.24)}
  .chip.green{color:#86efac;background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.24)}
  .chip.gold{color:#f0c76b;background:rgba(200,151,42,.12);border-color:rgba(200,151,42,.24)}
  .chip.red{color:#fda4af;background:rgba(244,63,94,.12);border-color:rgba(244,63,94,.24)}
  .chip.gray{color:rgba(255,255,255,.5);background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.10)}
  .inp{width:100%;border-radius:10px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);padding:10px 12px;color:#fff;font-size:13px;}
  .inp:focus{outline:none;border-color:rgba(96,165,250,.45);box-shadow:0 0 0 3px rgba(96,165,250,.08);}
  .btn-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:700;padding:10px 16px;border-radius:10px;font-size:13px;cursor:pointer;}
  .btn-blue:hover{background:linear-gradient(135deg,#60a5fa,#3b82f6);}
  .btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.78);padding:8px 14px;border-radius:10px;font-size:13px;cursor:pointer;}
  .btn-ghost:hover{background:rgba(255,255,255,.10);color:white;}
  .mono{font-family:'DM Mono',monospace;}
  .small{font-size:12px;color:rgba(255,255,255,.5);}
  .sec-title{font-size:11px;text-transform:uppercase;letter-spacing:.16em;color:rgba(255,255,255,.4);margin-bottom:14px;}
  .card-link{display:block;padding:18px;border-radius:14px;transition:all .15s;text-decoration:none;color:inherit;}
  .card-link:hover{transform:translateY(-2px);}
  .hp-field{position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px;height:1px;overflow:hidden;opacity:0;}
  .dot{width:9px;height:9px;border-radius:50%;display:inline-block;flex-shrink:0;}
  .dot.ok{background:#4ade80;box-shadow:0 0 8px #4ade80;}
  .dot.bad{background:#fb7185;box-shadow:0 0 8px #fb7185;}
  .dot.wait{background:#fbbf24;animation:pulse 1s infinite;}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
  .bar{height:8px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;}
  .bar>span{display:block;height:100%;width:0;background:linear-gradient(90deg,#3b82f6,#60a5fa);transition:width .4s ease;}
  .check-row{display:flex;align-items:flex-start;gap:9px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05);}
  .check-row:last-child{border-bottom:none;}
  .check-row input{margin-top:3px;width:15px;height:15px;accent-color:#3b82f6;cursor:pointer;flex-shrink:0;}
  .check-row.done label{color:rgba(255,255,255,.35);text-decoration:line-through;}
  .qlink{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:#cfe0ff;font-size:12px;text-decoration:none;}
  .qlink:hover{background:rgba(255,255,255,.10);color:#fff;}
  .kv{display:flex;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:12px;}
  .kv:last-child{border-bottom:none;}
  .kv .k{color:rgba(255,255,255,.45);}
  .kv .v{color:rgba(255,255,255,.85);text-align:right;word-break:break-word;}
</style>
</head>
<body>
<div class="top"></div>

<nav class="sticky top-0 z-40 px-5 h-16 flex items-center justify-between glass border-x-0 border-t-0">
  <div class="flex items-center gap-3 min-w-0">
    <a href="/" class="text-white text-lg font-bold inline-flex items-center gap-2" style="font-family:'Playfair Display',serif;">
      <span class="inline-block w-2 h-2 rounded-full" style="background:#60a5fa;box-shadow:0 0 8px #60a5fa;"></span>
      Ticky
    </a>
    <span class="text-white/20">·</span>
    <span class="text-sm text-white/50">Tester</span>
    <span class="chip blue">🧪 BETA</span>
  </div>
  <div class="flex items-center gap-3">
    <div class="text-right">
      <div class="text-sm"><?= htmlspecialchars($user['nev'] ?? $user['felhasznalonev'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="small"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?> felhasználó</div>
    </div>
    <a href="/logout" class="btn-ghost">Kilépés</a>
  </div>
</nav>

<main class="max-w-6xl mx-auto px-5 py-8 relative z-10">

  <header class="mb-8">
    <h1 class="text-4xl font-bold" style="font-family:'Playfair Display',serif;">
      Üdv, <?= htmlspecialchars($user['nev'] ?? $user['felhasznalonev'], ENT_QUOTES, 'UTF-8') ?>!
    </h1>
    <p class="small mt-3 max-w-2xl">
      Ez a Ticky <strong>tester központja</strong>. Fent látod az élő állapotot és hogy mire figyelj most,
      lent a checklistet és a visszajelzés-küldést.
    </p>
  </header>

  <!-- Build info -->
  <div class="glass rounded-2xl p-5 mb-8 flex flex-wrap items-center gap-4">
    <div>
      <div class="small mb-1">Build info</div>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ($build_commit !== ''): ?>
          <span class="chip gray mono"><?= htmlspecialchars($build_commit, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <span class="chip gray mono"><?= htmlspecialchars($build_branch, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="chip gray mono">deploy: <?= date('Y-m-d H:i', $build_time) ?></span>
      </div>
    </div>
    <div class="ml-auto small">
      Jelentkeztél: <span class="mono"><?= date('Y-m-d H:i') ?></span>
    </div>
  </div>

  <!-- Rendszer-állapot + Mit teszteljünk -->
  <section class="grid lg:grid-cols-2 gap-6 mb-10">
    <div class="glass rounded-2xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="sec-title mb-0">🩺 Rendszer-állapot</h2>
        <button id="health-refresh" class="btn-ghost" style="padding:5px 10px;font-size:12px;">Újraellenőriz</button>
      </div>
      <div id="health-list" class="space-y-1"></div>
    </div>

    <div class="glass rounded-2xl p-6">
      <h2 class="sec-title">🎯 Mit teszteljünk most</h2>
      <div class="space-y-3">
        <?php foreach ($focus as $f): ?>
          <div class="flex gap-3">
            <span style="font-size:18px;flex-shrink:0;"><?= $f[0] ?></span>
            <div>
              <div class="text-sm font-semibold"><?= htmlspecialchars($f[1], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="small"><?= htmlspecialchars($f[2], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Edge-case gyorslinkek -->
  <section class="mb-10">
    <h2 class="sec-title">⚡ Trükkös esetek (gyorslinkek)</h2>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($quick as $q): ?>
        <a href="<?= htmlspecialchars($q[2], ENT_QUOTES, 'UTF-8') ?>" class="qlink">
          <span><?= $q[0] ?></span><?= htmlspecialchars($q[1], ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Teszt-területek -->
  <section class="mb-10">
    <h2 class="sec-title">📋 Tesztelendő területek</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach ($test_areas as $area): ?>
        <a href="<?= htmlspecialchars($area[3], ENT_QUOTES, 'UTF-8') ?>" class="glass card-link">
          <div class="text-2xl mb-3"><?= $area[0] ?></div>
          <div class="text-lg font-bold mb-1" style="font-family:'Playfair Display',serif;">
            <?= htmlspecialchars($area[1], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <p class="text-sm text-white/65"><?= htmlspecialchars($area[2], ENT_QUOTES, 'UTF-8') ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Checklist + Környezet -->
  <section class="grid lg:grid-cols-[1fr_360px] gap-6 mb-10">
    <div class="glass rounded-2xl p-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="sec-title mb-0">✅ Teszt-checklist</h2>
        <button id="check-reset" class="btn-ghost" style="padding:5px 10px;font-size:12px;">Nullázás</button>
      </div>
      <div class="flex items-center gap-3 mb-4">
        <div class="bar flex-1"><span id="check-bar"></span></div>
        <span class="small mono" id="check-pct">0%</span>
      </div>
      <div id="check-list"></div>
      <p class="small mt-3">A pipák a böngésződben tárolódnak (nem szerveroldal).</p>
    </div>

    <aside class="glass rounded-2xl p-6">
      <h2 class="sec-title">🖥️ Környezet</h2>
      <div id="env-list"></div>
    </aside>
  </section>

  <!-- Feedback + saját feedbackek -->
  <section class="grid lg:grid-cols-[1fr_400px] gap-6 mb-10">
    <div class="glass rounded-2xl p-6">
      <h2 class="text-xl font-bold mb-4" style="font-family:'Playfair Display',serif;">💬 Visszajelzés küldése</h2>

      <?php if ($feedback_sent): ?>
        <div class="bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 rounded mb-4 text-sm">
          ✅ Köszi, megkaptam! Az adminhoz továbbítva.
        </div>
      <?php endif; ?>
      <?php if ($feedback_error): ?>
        <div class="bg-rose-900/40 border border-rose-700 text-rose-200 px-4 py-3 rounded mb-4 text-sm">
          ⚠️ <?= htmlspecialchars($feedback_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4" autocomplete="off" novalidate>
        <input type="hidden" name="action" value="feedback">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="hp-field" aria-hidden="true">
          <label>Ne töltsd ki ezt a mezőt<input type="text" name="kapcsolat" tabindex="-1" autocomplete="off"></label>
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Kategória</label>
          <select name="category" class="inp" required>
            <option value="bug">🐛 Bug / hiba</option>
            <option value="feature">💡 Új ötlet / fejlesztés</option>
            <option value="feedback">💬 Általános visszajelzés</option>
          </select>
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Cím (rövid összefoglaló)</label>
          <input name="title" class="inp" maxlength="200" required placeholder="Pl.: A 204-es terem státusza nem frissül">
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Részletes leírás</label>
          <textarea name="description" class="inp" rows="6" maxlength="5000" required
                    placeholder="Mi történik? Mi kellene történjen? Mikor és hogyan reprodukálható?"></textarea>
          <div class="small mt-1 text-right"><span id="desc-count">0</span>/5000</div>
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Melyik oldalon? (opcionális)</label>
          <input name="page_url" class="inp mono" maxlength="500" placeholder="/terem/204" list="pages"
                 pattern="^/[\w\-./%?=&]*$" title="Belső útvonal, pl. /terem/204">
          <datalist id="pages">
            <option value="/termek"><option value="/tanar"><option value="/osztaly">
            <option value="/kijelzo"><option value="/qr">
            <option value="/osztaly/12.e"><option value="/osztaly/13.a"><option value="/terem/104">
          </datalist>
        </div>

        <button type="submit" class="btn-blue">Visszajelzés küldése</button>
      </form>
    </div>

    <aside class="glass rounded-2xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold" style="font-family:'Playfair Display',serif;">📜 Visszajelzéseim</h2>
      </div>
      <div class="flex flex-wrap gap-2 mb-4">
        <span class="chip blue"><?= (int) $fb_stats['new'] ?> új</span>
        <span class="chip gold"><?= (int) $fb_stats['reviewed'] ?> átnézve</span>
        <span class="chip green"><?= (int) $fb_stats['fixed'] ?> javítva</span>
        <span class="chip red"><?= (int) $fb_stats['wont_fix'] ?> elutasítva</span>
      </div>

      <?php if (empty($my_feedback)): ?>
        <p class="small">Még nem küldtél visszajelzést.</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($my_feedback as $fb):
            $cat_chips = ['bug' => '🐛 bug', 'feature' => '💡 ötlet', 'feedback' => '💬 vissza'];
            $status_chips = [
              'new' => ['chip blue','új'], 'reviewed' => ['chip gold','átnézve'],
              'fixed' => ['chip green','javítva'], 'wont_fix' => ['chip red','elutasítva'],
            ];
            $status = (string) ($fb['status'] ?? 'new');
            $status_info = $status_chips[$status] ?? ['chip gray', $status];
          ?>
            <div class="border border-white/10 rounded-lg p-3">
              <div class="flex items-start justify-between gap-2 mb-1">
                <div class="text-sm font-semibold"><?= htmlspecialchars($fb['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <span class="<?= $status_info[0] ?>" style="font-size:9px"><?= htmlspecialchars($status_info[1], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center gap-2 text-xs text-white/40">
                <span><?= htmlspecialchars($cat_chips[$fb['category']] ?? (string) $fb['category'], ENT_QUOTES, 'UTF-8') ?></span>
                <span>·</span>
                <span class="mono"><?= htmlspecialchars(substr((string)($fb['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <?php if (!empty($fb['admin_note'])): ?>
                <div class="mt-2 text-xs text-white/65 bg-white/5 p-2 rounded">
                  <strong class="text-white/45">Admin válasz:</strong> <?= htmlspecialchars($fb['admin_note'], ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>
  </section>

  <p class="text-center small mt-12">
    🧪 Ticky Tester · v1.1 · Ha bármi nem világos, írj a <a href="/support" class="text-blue-300 hover:text-blue-200">support</a> oldalon
  </p>
</main>

<script nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>">
(function(){
  'use strict';
  var SERVER_NOW_MS = <?= $server_now_ms ?>;

  // ── Karakterszámláló ──────────────────────────────
  var ta = document.querySelector('textarea[name="description"]'),
      cc = document.getElementById('desc-count');
  if (ta && cc) { var u = function(){ cc.textContent = ta.value.length; }; ta.addEventListener('input', u); u(); }

  // ── Rendszer-állapot ──────────────────────────────
  var endpoints = [
    { label: 'Osztály lista API', url: '/api/osztalyok' },
    { label: 'Órarend API (12.e)', url: '/api/osztaly/12.e/orarend' },
    { label: 'Főoldal', url: '/' }
  ];
  var healthList = document.getElementById('health-list');

  function renderHealth(states){
    healthList.innerHTML = states.map(function(s){
      var cls = s.status === 'ok' ? 'ok' : (s.status === 'wait' ? 'wait' : 'bad');
      var info = s.status === 'wait' ? 'ellenőrzés…'
               : (s.status === 'ok' ? (s.ms + ' ms') : (s.code ? ('HTTP ' + s.code) : 'hiba'));
      return '<div class="flex items-center justify-between py-1.5" style="border-bottom:1px solid rgba(255,255,255,.05)">'
           + '<span class="flex items-center gap-2 text-sm"><span class="dot ' + cls + '"></span>' + s.label + '</span>'
           + '<span class="small mono">' + info + '</span></div>';
    }).join('');
  }

  async function ping(ep){
    var ctrl = new AbortController();
    var to = setTimeout(function(){ ctrl.abort(); }, 6000);
    var t0 = performance.now();
    try {
      var r = await fetch(ep.url, { signal: ctrl.signal, headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      clearTimeout(to);
      return { label: ep.label, status: r.ok ? 'ok' : 'bad', code: r.status, ms: Math.round(performance.now() - t0) };
    } catch (e) {
      clearTimeout(to);
      return { label: ep.label, status: 'bad', code: 0, ms: 0 };
    }
  }

  async function runHealth(){
    var states = endpoints.map(function(e){ return { label: e.label, status: 'wait' }; });
    renderHealth(states);
    for (var i = 0; i < endpoints.length; i++){
      states[i] = await ping(endpoints[i]);
      renderHealth(states);
    }
  }
  document.getElementById('health-refresh').addEventListener('click', runHealth);
  runHealth();

  // ── Környezet ─────────────────────────────────────
  var driftMs = Date.now() - SERVER_NOW_MS;
  var driftTxt = (Math.abs(driftMs) < 4000 ? '≈ szinkronban' : (Math.round(driftMs/1000) + ' s eltérés'));
  var env = [
    ['Böngésző', navigator.userAgent.replace(/^Mozilla\/5\.0 /, '')],
    ['Nyelv', navigator.language || '—'],
    ['Ablak', window.innerWidth + ' × ' + window.innerHeight + ' px'],
    ['Pixelarány', (window.devicePixelRatio || 1) + 'x'],
    ['Kapcsolat', navigator.onLine ? 'online' : 'offline'],
    ['Időzóna', (Intl.DateTimeFormat().resolvedOptions().timeZone || '—')],
    ['Kliens↔szerver', driftTxt]
  ];
  document.getElementById('env-list').innerHTML = env.map(function(kv){
    return '<div class="kv"><span class="k">' + kv[0] + '</span><span class="v mono">'
         + String(kv[1]).replace(/</g,'&lt;') + '</span></div>';
  }).join('');

  // ── Checklist (localStorage) ──────────────────────
  var CHECK = [
    { area: '🏫 Termek', items: ['Minden terem listázódik', 'Foglalt/szabad helyes', 'Magától frissül', 'Mobil nézet OK'] },
    { area: '👩‍🏫 Tanár', items: ['Tanár kiválasztható', 'Napi órarend helyes', 'Lyukasóra jól látszik', 'Csoportbontás OK'] },
    { area: '🎓 Osztály', items: ['Órarend helyes', "Több órás blokk: 'N óra' badge + idősáv", 'Csoportsorok nem törnek szét', 'Aktuális óra kiemelve'] },
    { area: '📺 Kijelző', items: ['Olvasható távolról', '~30 mp-enként frissül', 'Szünet banner OK'] },
    { area: '📱 QR', items: ['QR generálódik', 'Beolvasva a jó oldalra visz'] },
    { area: '🌐 Általános', items: ['Bejelentkezés/kilépés', 'Hibás URL kezelése', 'Sötét háttér olvasható'] }
  ];
  var KEY = 'ticky_tester_checklist_v1';
  var listEl = document.getElementById('check-list');
  var barEl = document.getElementById('check-bar');
  var pctEl = document.getElementById('check-pct');

  function loadState(){ try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch(e){ return {}; } }
  function saveState(s){ try { localStorage.setItem(KEY, JSON.stringify(s)); } catch(e){} }

  function renderChecklist(){
    var state = loadState(), total = 0, done = 0, html = '';
    CHECK.forEach(function(group, gi){
      html += '<div class="text-sm font-semibold mt-3 mb-1">' + group.area + '</div>';
      group.items.forEach(function(item, ii){
        var id = 'c_' + gi + '_' + ii;
        var checked = !!state[id];
        total++; if (checked) done++;
        html += '<div class="check-row' + (checked ? ' done' : '') + '">'
              + '<input type="checkbox" id="' + id + '"' + (checked ? ' checked' : '') + '>'
              + '<label for="' + id + '" class="text-sm" style="cursor:pointer;color:rgba(255,255,255,.8)">' + item + '</label></div>';
      });
    });
    listEl.innerHTML = html;
    var pct = total ? Math.round(done / total * 100) : 0;
    barEl.style.width = pct + '%';
    pctEl.textContent = pct + '% (' + done + '/' + total + ')';

    listEl.querySelectorAll('input[type="checkbox"]').forEach(function(cb){
      cb.addEventListener('change', function(){
        var st = loadState();
        if (cb.checked) st[cb.id] = 1; else delete st[cb.id];
        saveState(st);
        renderChecklist();
      });
    });
  }
  document.getElementById('check-reset').addEventListener('click', function(){
    if (confirm('Biztosan nullázod a checklistet?')) { saveState({}); renderChecklist(); }
  });
  renderChecklist();
})();
</script>
</body>
</html>
