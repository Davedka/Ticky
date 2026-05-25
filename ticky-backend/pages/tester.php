<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

send_security_headers(true);

// ── Extra védelmi fejlécek (a tester egy bejelentkezett, érzékeny felület) ──
// A header() alapból FELÜLÍRJA az azonos nevű korábbi fejlécet, így ha a
// send_security_headers() is állít CSP-t, ez lesz az érvényes. Ha ott már van
// egy szigorúbb globális CSP, egyeztesd a kettőt.
// Megjegyzés: a Tailwind Play CDN (cdn.tailwindcss.com) 'unsafe-eval'-t igényel.
// Élesben érdemes a Tailwindet build-időben fordítani, hogy ez elhagyható legyen.
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' https://cdn.tailwindcss.com 'unsafe-eval'; "
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

$csrf = ticky_csrf_token();
$feedback_sent  = false;
$feedback_error = null;

// ── Bemenet-tisztító segédfüggvények ───────────────────
function tester_clean_text(string $value): string {
    // Vezérlőkarakterek kiszűrése (tab/újsor marad), majd trim
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    return trim($value);
}

// ── Feedback POST handler ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'feedback') {

    // (1) Honeypot: a 'kapcsolat' mezőnek üresnek kell lennie (botok kitöltik).
    //     Ha ki van töltve, csendben "sikert" jelzünk, de NEM mentünk.
    $honeypot = (string) ($_POST['kapcsolat'] ?? '');

    // (2) Minimális kitöltési idő (a render óta) – túl gyors submit = bot.
    $rendered_at = (int) ($_SESSION['tester_form_render'] ?? 0);
    $too_fast    = $rendered_at > 0 && (time() - $rendered_at) < 2;

    if ($honeypot !== '' || $too_fast) {
        // Ne áruljuk el a botnak, hogy kiszűrtük.
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

            // UTF-8 érvényesség (a hibás bájtsorozat adatbázis/JSON hibát okozhat)
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
                // Csak belső, relatív útvonal engedett (nem teljes URL, nem //host)
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

// Új render-időbélyeg a (lassú-submit) bot-szűréshez
$_SESSION['tester_form_render'] = time();

// ── Saját feedback-ek lekérdezése (utolsó 10) ──────────
$my_feedback = sb_get('tester_feedback', [
    'user_id' => 'eq.' . $user['id'],
    'select'  => 'id,category,title,status,created_at,admin_note',
    'order'   => 'created_at.desc',
    'limit'   => '10',
], 'service') ?: [];

// ── Build info ─────────────────────────────────────────
$build_commit = substr(trim((string) (getenv('RENDER_GIT_COMMIT') ?: '')), 0, 7);
$build_branch = trim((string) (getenv('RENDER_GIT_BRANCH') ?: 'main'));
$build_time   = filemtime(__DIR__ . '/../index.php') ?: time();

$test_areas = [
    [
        'icon' => '🏫',
        'title' => 'Termek live nézet',
        'desc' => 'Ellenőrizd a /termek oldalt: minden terem státusza valóban frissül? Foglalt/szabad helyesen jelenik meg?',
        'href' => '/termek',
        'check' => 'Próbáld ki több böngészőben, mobilon is.',
    ],
    [
        'icon' => '👩‍🏫',
        'title' => 'Tanár kereső',
        'desc' => 'Válassz ki egy tanárt és nézd meg a napi órarendjét. Stimmel az adat?',
        'href' => '/tanar',
        'check' => 'Próbálj ki olyan tanárt is, akinek lyukasórája van.',
    ],
    [
        'icon' => '🎓',
        'title' => 'Osztály nézet',
        'desc' => 'Válaszd ki a saját osztályod, ellenőrizd a megjelenő órarendet.',
        'href' => '/osztaly',
        'check' => 'Hibás óraszám / terem / tanár? Több órás (összevont) blokk jól jelenik meg?',
    ],
    [
        'icon' => '📺',
        'title' => 'Kijelző',
        'desc' => 'A folyosói kijelző nézet. Próbáld ki nagy képernyőn, illetve hogy auto-frissül-e.',
        'href' => '/kijelzo',
        'check' => 'Olvasható távolról? Frissül 30 másodpercenként?',
    ],
    [
        'icon' => '📱',
        'title' => 'QR kódok',
        'desc' => 'Generálj QR-t egy teremhez, szkenneld be telefonnal.',
        'href' => '/qr',
        'check' => 'A QR a helyes /terem/{szam} oldalra visz?',
    ],
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
  .btn-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:700;padding:10px 16px;border-radius:10px;font-size:13px;}
  .btn-blue:hover{background:linear-gradient(135deg,#60a5fa,#3b82f6);}
  .btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.78);padding:8px 14px;border-radius:10px;font-size:13px;}
  .btn-ghost:hover{background:rgba(255,255,255,.10);color:white;}
  .mono{font-family:'DM Mono',monospace;}
  .small{font-size:12px;color:rgba(255,255,255,.5);}
  .card-link{display:block;padding:18px;border-radius:14px;transition:all .15s;text-decoration:none;color:inherit;}
  .card-link:hover{transform:translateY(-2px);}
  /* Honeypot – ember számára láthatatlan, botnak csábító */
  .hp-field{position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px;height:1px;overflow:hidden;opacity:0;}
</style>
</head>
<body>
<div class="top"></div>

<!-- Navbar (egyszerű, tester témájú) -->
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

  <!-- Welcome banner -->
  <header class="mb-8">
    <h1 class="text-4xl font-bold" style="font-family:'Playfair Display',serif;">
      Üdv, <?= htmlspecialchars($user['nev'] ?? $user['felhasznalonev'], ENT_QUOTES, 'UTF-8') ?>!
    </h1>
    <p class="small mt-3 max-w-2xl">
      Ez a Ticky <strong>tester felülete</strong>. Itt a feladatod, hogy átfusd a fontosabb felhasználói folyamatokat,
      kipróbáld a friss verziókat, és visszajelzéseket küldj a fejlesztőnek.
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

  <!-- Test areas -->
  <section class="mb-10">
    <h2 class="text-xs uppercase tracking-[0.16em] text-white/40 mb-4">📋 Tesztelendő területek</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach ($test_areas as $area): ?>
        <a href="<?= htmlspecialchars($area['href'], ENT_QUOTES, 'UTF-8') ?>" class="glass card-link">
          <div class="text-2xl mb-3"><?= $area['icon'] ?></div>
          <div class="text-lg font-bold mb-1" style="font-family:'Playfair Display',serif;">
            <?= htmlspecialchars($area['title'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <p class="text-sm text-white/65 mb-2"><?= htmlspecialchars($area['desc'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="text-xs text-white/40">💡 <?= htmlspecialchars($area['check'], ENT_QUOTES, 'UTF-8') ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Feedback form + saját feedback-ek -->
  <section class="grid lg:grid-cols-[1fr_400px] gap-6 mb-10">

    <!-- Feedback form -->
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

        <!-- Honeypot: hagyd üresen (ember nem látja) -->
        <div class="hp-field" aria-hidden="true">
          <label>Ne töltsd ki ezt a mezőt
            <input type="text" name="kapcsolat" tabindex="-1" autocomplete="off">
          </label>
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
          <input name="title" class="inp" maxlength="200" required
                 placeholder="Pl.: A 204-es terem státusza nem frissül">
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Részletes leírás</label>
          <textarea name="description" class="inp" rows="6" maxlength="5000" required
                    placeholder="Mi történik? Mi kellene történjen? Mikor és hogyan reprodukálható?"></textarea>
          <div class="small mt-1 text-right"><span id="desc-count">0</span>/5000</div>
        </div>

        <div>
          <label class="block text-xs uppercase tracking-[0.16em] text-white/40 mb-2">Melyik oldalon? (opcionális)</label>
          <input name="page_url" class="inp mono" maxlength="500" placeholder="/terem/204"
                 pattern="^/[\w\-./%?=&]*$" title="Belső útvonal, pl. /terem/204">
        </div>

        <button type="submit" class="btn-blue">Visszajelzés küldése</button>
      </form>
    </div>

    <!-- Saját feedbackjeim -->
    <aside class="glass rounded-2xl p-6">
      <h2 class="text-xl font-bold mb-4" style="font-family:'Playfair Display',serif;">📜 Visszajelzéseim</h2>

      <?php if (empty($my_feedback)): ?>
        <p class="small">Még nem küldtél visszajelzést.</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($my_feedback as $fb):
            $cat_chips = ['bug' => '🐛 bug', 'feature' => '💡 ötlet', 'feedback' => '💬 vissza'];
            $status_chips = [
              'new'      => ['chip blue',  'új'],
              'reviewed' => ['chip gold',  'átnézve'],
              'fixed'    => ['chip green', 'javítva'],
              'wont_fix' => ['chip red',   'elutasítva'],
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
    🧪 Ticky Tester · v1.0 · Ha bármi nem világos, írj a <a href="/support" class="text-blue-300 hover:text-blue-200">support</a> oldalon
  </p>
</main>

<script>
  // Karakterszámláló a leíráshoz
  (function(){
    var ta = document.querySelector('textarea[name="description"]');
    var c  = document.getElementById('desc-count');
    if (ta && c) {
      var upd = function(){ c.textContent = ta.value.length; };
      ta.addEventListener('input', upd); upd();
    }
  })();
</script>
</body>
</html>
