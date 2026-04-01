<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Support</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="shortcut icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --gold: #c8972a;
    --gold-l: #f0c76b;
    --navy: #060f1e;
    --blue: #1a4a8a;
  }

  html { scroll-behavior: smooth; }

  body {
    font-family: 'DM Sans', sans-serif;
    background-color: var(--navy);
    min-height: 100vh;
    color: white;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%),
      radial-gradient(ellipse 40% 30% at 50% 50%, rgba(7,29,58,.6) 0%, transparent 60%);
  }

  body::before {
    content: '';
    position: fixed; inset: 0;
    pointer-events: none; z-index: 0;
    background-image:
      linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
    background-size: 40px 40px;
  }

  .top-line {
    position: fixed; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(200,151,42,.5), transparent);
    z-index: 200;
  }

  .glass {
    background: rgba(255,255,255,.05);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255,255,255,.10);
  }

  .gold-line {
    height: 2px; border-radius: 2px 2px 0 0;
    background: linear-gradient(90deg, #1a4a8a, #c8972a, #1a4a8a);
  }

  .pulse { animation: pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

  .fade-up   { animation: fu .5s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-2 { animation: fu .5s .12s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-3 { animation: fu .5s .22s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-4 { animation: fu .5s .32s cubic-bezier(.22,1,.36,1) both; }
  .fade-up-5 { animation: fu .5s .42s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fu { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }

  a { text-decoration: none; }

  /* Cards */
  .card-hover {
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
  }
  .card-hover:hover {
    transform: translateY(-3px);
    border-color: rgba(255,255,255,.2) !important;
    box-shadow: 0 16px 48px rgba(6,15,30,.8);
  }

  /* Contact method cards */
  .contact-card {
    border-radius: 0 0 16px 16px;
    border-top: none;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    transition: transform .2s, border-color .2s, background .2s;
  }
  .contact-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255,255,255,.2) !important;
    background: rgba(255,255,255,.07) !important;
  }

  /* FAQ accordion */
  .faq-item {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.08);
    background: rgba(255,255,255,.03);
    overflow: hidden;
    transition: border-color .2s;
    margin-bottom: 8px;
  }
  .faq-item:hover { border-color: rgba(255,255,255,.14); }
  .faq-item.open { border-color: rgba(200,151,42,.3); background: rgba(200,151,42,.04); }

  .faq-q {
    width: 100%; padding: 16px 18px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: transparent; border: none; color: white;
    font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
    cursor: pointer; text-align: left;
  }
  .faq-q:hover { background: rgba(255,255,255,.03); }

  .faq-icon {
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: rgba(255,255,255,.4);
    transition: all .2s;
  }
  .faq-item.open .faq-icon {
    background: rgba(200,151,42,.15);
    border-color: rgba(200,151,42,.4);
    color: #f0c76b;
    transform: rotate(45deg);
  }

  .faq-a {
    max-height: 0; overflow: hidden;
    transition: max-height .35s cubic-bezier(.22,1,.36,1), padding .2s;
    padding: 0 18px;
    font-size: 13px; line-height: 1.7;
    color: rgba(255,255,255,.55);
  }
  .faq-item.open .faq-a {
    max-height: 300px;
    padding: 0 18px 16px;
  }

  /* Status badge */
  .status-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; border-radius: 99px;
    font-size: 12px; font-weight: 600; letter-spacing: .05em;
    background: rgba(0,200,150,.12);
    border: 1px solid rgba(0,200,150,.3);
    color: #00c896;
  }

  /* Sidebar */
  .ticky-sidebar {
    position: fixed; left: 0; top: 50%; transform: translateY(-50%);
    z-index: 150; display: flex; flex-direction: column; align-items: center; gap: 2px;
    padding: 8px 6px;
    background: rgba(6,15,30,.78); backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.08); border-left: none;
    border-radius: 0 12px 12px 0;
  }
  .tsb-item {
    position: relative; width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; text-decoration: none;
    color: rgba(255,255,255,.6); transition: all .18s;
  }
  .tsb-item:hover { background: rgba(255,255,255,.10); color: white; }
  .tsb-item::after {
    content: attr(data-label);
    position: absolute; left: 46px; top: 50%; transform: translateY(-50%);
    background: rgba(6,15,30,.96); color: rgba(255,255,255,.88);
    font-size: 12px; font-family: 'DM Sans', sans-serif; font-weight: 500;
    padding: 5px 11px; border-radius: 8px; white-space: nowrap;
    opacity: 0; pointer-events: none; transition: opacity .15s;
    border: 1px solid rgba(255,255,255,.10);
  }
  .tsb-item:hover::after { opacity: 1; }
  .tsb-divider { width: 20px; height: 1px; background: rgba(255,255,255,.10); margin: 2px 0; }
  @media (max-width: 600px) { .ticky-sidebar { display: none; } }

  /* Toast */
  .toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 500;
    padding: 12px 20px; border-radius: 12px;
    font-size: 13px; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
    backdrop-filter: blur(16px);
    box-shadow: 0 8px 32px rgba(0,0,0,.4);
    animation: toastIn .3s cubic-bezier(.22,1,.36,1);
    background: rgba(0,200,150,.2); border: 1px solid rgba(0,200,150,.4); color: #00c896;
  }
  @keyframes toastIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }

  /* Form */
  .inp {
    width: 100%; padding: 12px 14px; border-radius: 10px;
    border: 1.5px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.05); color: white;
    font-family: 'DM Sans', sans-serif; font-size: 14px;
    transition: border-color .2s, background .2s;
    outline: none;
  }
  .inp::placeholder { color: rgba(255,255,255,.28); }
  .inp:focus {
    border-color: rgba(200,151,42,.45);
    background: rgba(255,255,255,.07);
  }
  textarea.inp { resize: vertical; min-height: 100px; }

  .gold-btn {
    width: 100%; padding: 13px;
    border-radius: 10px; border: none; cursor: pointer;
    background: linear-gradient(135deg, #c8972a, #a07020);
    color: white; font-family: 'DM Sans', sans-serif;
    font-size: 14px; font-weight: 700; letter-spacing: .03em;
    transition: all .2s;
  }
  .gold-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(200,151,42,.4); }
  .gold-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

  /* Sending spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .sending { animation: spin .7s linear infinite; display: inline-block; }
</style>
</head>
<body>
<div class="top-line"></div>

<!-- Bal oldali sidebar -->
<div class="ticky-sidebar">
  <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener" class="tsb-item" data-label="Eseménynaptár">📅</a>
  <div class="tsb-divider"></div>
  <a href="mailto:tickysupport@gmail.com?subject=Ticky%20support" class="tsb-item" data-label="Support">✉️</a>
  <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="tsb-item" data-label="Bug report">🐛</a>
</div>

<!-- Navbar -->
<nav style="background:rgba(6,15,30,.75);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);"
     class="sticky top-0 z-50 px-6 h-16 flex items-center justify-between">
  <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;"
     class="flex items-center gap-2">
    <span class="w-2 h-2 rounded-full pulse flex-shrink-0"
          style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
    Ticky
  </a>
  <div class="flex items-center gap-2">
    <a href="/termek" class="text-sm px-3 py-2 rounded-lg"
       style="color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);">
      Termek
    </a>
    <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener"
       class="text-sm px-3 py-2 rounded-lg"
       style="color:rgba(255,140,140,.8);border:1px solid rgba(255,80,80,.2);background:rgba(255,80,80,.06);">
      🐛 Bug report
    </a>
  </div>
</nav>

<!-- Főtartalom -->
<div class="relative z-10 max-w-2xl mx-auto px-5 pt-12 pb-20">

  <!-- Hero -->
  <div class="fade-up text-center mb-12">
    <div class="status-badge mb-5 mx-auto" style="width:fit-content;">
      <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0"
            style="background:#00c896;display:inline-block;"></span>
      Minden rendszer működik
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:52px;font-weight:700;color:white;line-height:1.05;letter-spacing:-1.5px;">
      Hogyan<br>segíthetünk?
    </h1>
    <p class="mt-4 text-sm" style="color:rgba(255,255,255,.45);max-width:360px;margin-left:auto;margin-right:auto;line-height:1.7;">
      A Ticky csapata általában <strong style="color:rgba(255,255,255,.7);">24 órán belül</strong> válaszol. Nézd meg a GYIK-et, lehet hogy a válasz már ott van.
    </p>
  </div>

  <!-- Kapcsolati módok -->
  <div class="fade-up-2 mb-10">
    <p class="text-xs font-semibold tracking-widest uppercase mb-4"
       style="color:rgba(255,255,255,.3);">Elérhetőség</p>

    <div class="grid grid-cols-1 gap-3">

      <!-- Email -->
      <div>
        <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
        <a href="mailto:tickysupport@gmail.com?subject=Ticky%20support"
           class="glass contact-card">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.25);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
            ✉️
          </div>
          <div class="flex-1 min-w-0">
            <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">
              Email support
            </p>
            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,.4);">
              tickysupport@gmail.com · Válasz ~24h
            </p>
          </div>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="rgba(255,255,255,.3)" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

      <!-- GitHub -->
      <div>
        <div style="height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#5a1a1a,#ff5050,#5a1a1a);"></div>
        <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener"
           class="glass contact-card">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,80,80,.10);border:1px solid rgba(255,80,80,.2);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
            🐛
          </div>
          <div class="flex-1 min-w-0">
            <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:white;">
              Bug report
            </p>
            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,.4);">
              GitHub Issues · Hibák jelzése
            </p>
          </div>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="rgba(255,255,255,.3)" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

    </div>
  </div>

  <!-- Kapcsolatfelvételi form -->
  <div class="fade-up-3 mb-10">
    <p class="text-xs font-semibold tracking-widest uppercase mb-4"
       style="color:rgba(255,255,255,.3);">Üzenet küldése</p>
    <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
    <div class="glass" style="border-radius:0 0 16px 16px;border-top:none;padding:24px;">
      <div class="flex flex-col gap-3" id="support-form">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-semibold tracking-widest uppercase block mb-1.5"
                   style="color:rgba(255,255,255,.3);">Neved</label>
            <input type="text" id="f-nev" class="inp" placeholder="pl. Kiss Péter">
          </div>
          <div>
            <label class="text-xs font-semibold tracking-widest uppercase block mb-1.5"
                   style="color:rgba(255,255,255,.3);">Email</label>
            <input type="email" id="f-email" class="inp" placeholder="email@iskola.hu">
          </div>
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest uppercase block mb-1.5"
                 style="color:rgba(255,255,255,.3);">Tárgy</label>
          <select id="f-targy" class="inp" style="appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 14px center;cursor:pointer;">
            <option value="" style="background:#0b2e59;">— Válassz kategóriát —</option>
            <option value="hiba" style="background:#0b2e59;">🐛 Hibajelentés</option>
            <option value="kerdes" style="background:#0b2e59;">❓ Általános kérdés</option>
            <option value="terem" style="background:#0b2e59;">🏫 Terem / órarend probléma</option>
            <option value="tanar" style="background:#0b2e59;">👩‍🏫 Tanár adat módosítás</option>
            <option value="javaslat" style="background:#0b2e59;">💡 Fejlesztési javaslat</option>
            <option value="egyeb" style="background:#0b2e59;">📋 Egyéb</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest uppercase block mb-1.5"
                 style="color:rgba(255,255,255,.3);">Üzenet</label>
          <textarea id="f-uzenet" class="inp" rows="4"
                    placeholder="Írd le részletesen a problémát vagy kérdésedet…"></textarea>
        </div>
        <button class="gold-btn" id="send-btn" onclick="sendForm()">
          Üzenet küldése →
        </button>
        <p class="text-xs text-center" style="color:rgba(255,255,255,.2);">
          Az üzenet a <span style="font-family:'DM Mono',monospace;color:rgba(255,255,255,.35);">tickysupport@gmail.com</span>-ra lesz elküldve
        </p>
      </div>

      <!-- Sikerüzenet (hidden) -->
      <div id="form-success" class="hidden text-center py-6">
        <div style="font-size:48px;" class="mb-3">✅</div>
        <p style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#4ade80;" class="mb-2">
          Elküldve!
        </p>
        <p class="text-sm" style="color:rgba(255,255,255,.45);">Hamarosan válaszolunk a megadott email címre.</p>
        <button onclick="resetForm()" class="mt-5 text-sm px-4 py-2 rounded-lg"
                style="color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.1);background:transparent;cursor:pointer;">
          Új üzenet
        </button>
      </div>
    </div>
  </div>

  <!-- GYIK -->
  <div class="fade-up-4">
    <p class="text-xs font-semibold tracking-widest uppercase mb-4"
       style="color:rgba(255,255,255,.3);">Gyakori kérdések</p>

    <div id="faq-list">
      <?php
      $faq = [
        [
          'q' => 'Miért nem jelenik meg helyesen a terem foglaltsága?',
          'a' => 'Az órarend adatbázisból töltődik be. Ha egy terem tévesen foglaltnak vagy szabadnak látszik, valószínűleg az órarend frissítésre szorul. Jelezd a support emailen vagy GitHub Issues-on a terem számával együtt.'
        ],
        [
          'q' => 'Hogyan lehet hozzáadni egy új tanárt a rendszerhez?',
          'a' => 'Tanárokat az Admin panelen lehet kezelni (⚙️ Admin gomb). Az admin jelszó az iskolai rendszergazdánál van. Ha nincs hozzáférésed, küldj emailt a support címre.'
        ],
        [
          'q' => 'A QR kód nem a megfelelő teremre mutat – mit tegyek?',
          'a' => 'A QR kódok a /terem/{szám} URL-re mutatnak. Ha a szám rossz, nyomtasd újra a QR Generátor oldalon. Ha az oldal tartalma hibás, jelezd a bug reporton.'
        ],
        [
          'q' => 'Hétvégén miért nem látszanak a foglaltsági adatok?',
          'a' => 'Ez szándékos: az órarend hétfőtől péntekig működik. Hétvégén minden terem szabadnak jelenik meg, és a foglaltsági logika ki van kapcsolva.'
        ],
        [
          'q' => 'Hogyan kerülhetek fel a fejlesztői csapatba?',
          'a' => 'A Ticky nyílt forráskódú projekt GitHub-on. Nyiss egy Pull Request-et a Davedka/Ticky repository-ban, és a csapat átnézi. Fejlesztési javaslatokat is szívesen fogadunk Issues-ban.'
        ],
        [
          'q' => 'Az oldal nem tölt be / fehér képernyőt látok.',
          'a' => 'Próbáld meg Ctrl+Shift+R kombinációval frissíteni (cache törlés). Ha ez nem segít, ellenőrizd az internetkapcsolatot, majd jelezd a bug reporton a böngésző verzióját és az oldal URL-jét.'
        ],
      ];
      foreach ($faq as $i => $item): ?>
        <div class="faq-item" id="faq-<?= $i ?>">
          <button class="faq-q" onclick="toggleFaq(<?= $i ?>)">
            <span><?= htmlspecialchars($item['q']) ?></span>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <?= htmlspecialchars($item['a']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Footer -->
  <div class="fade-up-5 mt-12 text-center">
    <p style="font-family:'Playfair Display',serif;font-size:13px;font-weight:700;color:rgba(255,255,255,.15);">
      Ticky v1.0 · MSZC Gépészeti Iskola
    </p>
  </div>

</div>

<script>
// ── FAQ ────────────────────────────────────────────
function toggleFaq(i) {
  const el = document.getElementById('faq-' + i)
  const wasOpen = el.classList.contains('open')
  document.querySelectorAll('.faq-item').forEach(f => f.classList.remove('open'))
  if (!wasOpen) el.classList.add('open')
}

// ── Form ──────────────────────────────────────────
function sendForm() {
  const nev    = document.getElementById('f-nev').value.trim()
  const email  = document.getElementById('f-email').value.trim()
  const targy  = document.getElementById('f-targy').value
  const uzenet = document.getElementById('f-uzenet').value.trim()

  if (!nev || !email || !targy || !uzenet) {
    showToast('Kérlek töltsd ki az összes mezőt!', 'err')
    return
  }
  if (!email.includes('@')) {
    showToast('Érvénytelen email cím!', 'err')
    return
  }

  const btn = document.getElementById('send-btn')
  btn.disabled = true
  btn.innerHTML = '<span class="sending">⟳</span> Küldés…'

  // mailto: megnyitása a kitöltött adatokkal
  const targyNevek = {
    hiba: '🐛 Hibajelentés',
    kerdes: '❓ Általános kérdés',
    terem: '🏫 Terem / órarend probléma',
    tanar: '👩‍🏫 Tanár adat módosítás',
    javaslat: '💡 Fejlesztési javaslat',
    egyeb: '📋 Egyéb'
  }

  const subject = encodeURIComponent('[Ticky Support] ' + (targyNevek[targy] || targy) + ' – ' + nev)
  const body = encodeURIComponent(
    'Feladó: ' + nev + '\n' +
    'Email: ' + email + '\n' +
    'Kategória: ' + (targyNevek[targy] || targy) + '\n\n' +
    'Üzenet:\n' + uzenet
  )

  setTimeout(() => {
    window.location.href = 'mailto:tickysupport@gmail.com?subject=' + subject + '&body=' + body
    document.getElementById('support-form').classList.add('hidden')
    document.getElementById('form-success').classList.remove('hidden')
    btn.disabled = false
    btn.innerHTML = 'Üzenet küldése →'
  }, 800)
}

function resetForm() {
  document.getElementById('f-nev').value = ''
  document.getElementById('f-email').value = ''
  document.getElementById('f-targy').value = ''
  document.getElementById('f-uzenet').value = ''
  document.getElementById('support-form').classList.remove('hidden')
  document.getElementById('form-success').classList.add('hidden')
}

// ── Toast ─────────────────────────────────────────
function showToast(msg) {
  const t = document.createElement('div')
  t.className = 'toast'
  t.style.cssText = 'background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.4);color:#ff6b82;'
  t.innerHTML = '⚠️ ' + msg
  document.body.appendChild(t)
  setTimeout(() => t.remove(), 3500)
}
</script>
</body>
</html>
