<?php
// utils/helpers.php – Közös segédfüggvények

/**
 * JSON válasz küldése és kilépés
 */
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Hiba JSON válasz
 */
function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

/**
 * Mai nap sorszáma (1=Hétfő ... 5=Péntek, 0=hétvége)
 * Supabase-ben het_napja: 1–5
 * PHP date('N'): 1=Hétfő ... 7=Vasárnap
 */
function mai_nap(): int {
    $n = (int) date('N'); // 1=Mon, 7=Sun
    return $n <= 5 ? $n : 0;  // hétvégén 0
}

/**
 * Aktuális idő 'HH:MM' formátumban (Budapest timezone)
 */
function aktualis_ido(): string {
    return date('H:i');
}

/**
 * Idő összehasonlítás: $ido >= $start AND $ido <= $end ?
 */
function render_time_sync_bootstrap(): void {
    $server_epoch_ms = (int) round(microtime(true) * 1000);
    $timezone = TZ;
    ?>
<script>
(() => {
  const timezone = <?= json_encode($timezone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const serverEpochMs = <?= $server_epoch_ms ?>;
  const bootPerfNow = typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : null;
  const bootClientNow = Date.now();

  const partsFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const hmFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });
  const hmsFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const weekdayMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

  function nowMs() {
    if (bootPerfNow !== null && typeof performance !== 'undefined' && typeof performance.now === 'function') {
      return serverEpochMs + (performance.now() - bootPerfNow);
    }
    return serverEpochMs + (Date.now() - bootClientNow);
  }

  function nowDate() {
    return new Date(nowMs());
  }

  function nowParts() {
    const raw = {};
    for (const part of partsFormatter.formatToParts(nowDate())) {
      if (part.type !== 'literal') raw[part.type] = part.value;
    }
    return {
      year: Number(raw.year),
      month: Number(raw.month),
      day: Number(raw.day),
      weekday: weekdayMap[raw.weekday] ?? 0,
      hour: Number(raw.hour),
      minute: Number(raw.minute),
      second: Number(raw.second),
    };
  }

  function nowMinutes() {
    const parts = nowParts();
    return parts.hour * 60 + parts.minute;
  }

  window.TickyTime = {
    timezone,
    nowDate,
    nowMs,
    nowParts,
    nowMinutes,
    weekdayIndex() {
      return nowParts().weekday;
    },
    schoolDayIndex() {
      const day = nowParts().weekday;
      return (day === 0 || day === 6) ? 1 : day;
    },
    formatHM() {
      return hmFormatter.format(nowDate());
    },
    formatHMS() {
      return hmsFormatter.format(nowDate());
    },
  };
})();
</script>
    <?php
}

function render_assistant_widget(array $options = []): void {
    $widget_id = $options['id'] ?? 'ticky-assistant-widget';
    $title = $options['title'] ?? 'AI asszisztens';
    $eyebrow = $options['eyebrow'] ?? 'Ticky Assist';
    $intro = $options['intro'] ?? 'Kérdezhetsz szabad termekről, foglaltságról, vagy egy konkrét terem állapotáról.';
    $prompts = $options['prompts'] ?? [
        'Melyik termek szabadok most?',
        'Melyik termek foglaltak most?',
        'Mi van most a 204-es teremben?',
        'Nyisd meg a tanárkeresőt',
    ];

    $config = [
        'widgetId' => $widget_id,
        'title' => $title,
        'eyebrow' => $eyebrow,
        'intro' => $intro,
        'prompts' => array_values($prompts),
    ];
    ?>
<style>
  .ta-shell, .ta-shell * { box-sizing:border-box; }
  .ta-shell { position:fixed; right:22px; top:50%; transform:translateY(-50%); z-index:160; font-family:'DM Sans',sans-serif; color:white; }
  .ta-launcher {
    display:flex; align-items:center; gap:10px; border:none; cursor:pointer; width:auto; margin-top:0;
    padding:13px 14px; border-radius:18px; color:white;
    background:linear-gradient(160deg, rgba(200,151,42,.22), rgba(11,46,89,.88));
    border:1px solid rgba(255,255,255,.12); box-shadow:0 18px 45px rgba(6,15,30,.35);
    backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }
  .ta-launcher:hover { transform:translateX(-2px); border-color:rgba(240,199,107,.4); box-shadow:0 22px 55px rgba(6,15,30,.45); }
  .ta-launcher-badge {
    width:38px; height:38px; border-radius:14px; display:grid; place-items:center; flex-shrink:0;
    background:linear-gradient(145deg, rgba(240,199,107,.22), rgba(255,255,255,.06));
    border:1px solid rgba(255,255,255,.08); font-family:'Playfair Display',serif; font-size:18px; font-weight:700;
  }
  .ta-launcher-copy { display:flex; flex-direction:column; align-items:flex-start; text-align:left; }
  .ta-launcher-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.45); font-weight:700; }
  .ta-launcher-title { font-size:13px; color:white; font-weight:600; line-height:1.2; }
  .ta-backdrop {
    position:fixed; inset:0; background:rgba(6,15,30,.45); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
    opacity:0; pointer-events:none; transition:opacity .22s ease; z-index:158;
  }
  .ta-shell.open .ta-backdrop { opacity:1; pointer-events:auto; }
  .ta-panel {
    position:fixed; top:20px; right:20px; bottom:20px; width:min(400px, calc(100vw - 20px)); z-index:159;
    display:flex; flex-direction:column; overflow:hidden; border-radius:26px;
    background:rgba(10,24,44,.82); border:1px solid rgba(255,255,255,.10);
    box-shadow:0 30px 70px rgba(6,15,30,.55); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
    transform:translateX(calc(100% + 36px)); transition:transform .24s cubic-bezier(.22,1,.36,1);
  }
  .ta-shell.open .ta-panel { transform:translateX(0); }
  .ta-panel-head { padding:18px 18px 16px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
  .ta-panel-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.35); font-weight:700; }
  .ta-panel-title { font-family:'Playfair Display',serif; font-size:25px; line-height:1.08; color:white; margin-top:6px; }
  .ta-panel-close {
    width:36px; height:36px; border:none; cursor:pointer; margin-top:0; border-radius:12px;
    background:rgba(255,255,255,.06); color:rgba(255,255,255,.55); display:grid; place-items:center;
  }
  .ta-panel-close:hover { background:rgba(255,255,255,.10); color:white; }
  .ta-messages { flex:1; overflow:auto; padding:18px; display:flex; flex-direction:column; gap:12px; }
  .ta-msg { display:flex; }
  .ta-msg.user { justify-content:flex-end; }
  .ta-bubble {
    max-width:100%; border-radius:18px; padding:14px 14px 13px; border:1px solid rgba(255,255,255,.09);
    box-shadow:0 10px 28px rgba(6,15,30,.22);
  }
  .ta-msg.assistant .ta-bubble { background:rgba(255,255,255,.05); }
  .ta-msg.user .ta-bubble { background:linear-gradient(135deg, rgba(200,151,42,.20), rgba(26,74,138,.22)); }
  .ta-bubble-kicker { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.38); font-weight:700; margin-bottom:8px; }
  .ta-bubble-text { font-size:13px; line-height:1.6; color:rgba(255,255,255,.88); }
  .ta-cards { display:grid; gap:8px; margin-top:10px; }
  .ta-card { padding:12px; border-radius:15px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); }
  .ta-card-eyebrow { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(240,199,107,.76); font-weight:700; margin-bottom:4px; }
  .ta-card-title { font-family:'Playfair Display',serif; font-size:17px; line-height:1.12; color:white; }
  .ta-card-meta { margin-top:4px; font-size:12px; color:rgba(255,255,255,.68); }
  .ta-card-detail { margin-top:4px; font-size:11px; color:rgba(255,255,255,.45); }
  .ta-actions, .ta-prompt-list { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
  .ta-pill {
    display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:8px 12px; width:auto; margin-top:0;
    border:1px solid rgba(255,255,255,.11); background:rgba(255,255,255,.05); color:rgba(255,255,255,.82);
    font-size:12px; font-weight:500; text-decoration:none; cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease;
  }
  .ta-pill:hover { transform:translateY(-1px); background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.18); color:white; }
  .ta-pill.primary { background:rgba(200,151,42,.14); border-color:rgba(200,151,42,.26); color:#f0c76b; }
  .ta-panel-foot { padding:16px 18px 18px; border-top:1px solid rgba(255,255,255,.08); }
  .ta-composer { display:flex; flex-direction:column; gap:10px; }
  .ta-input {
    width:100%; resize:none; min-height:52px; max-height:136px; border-radius:18px; border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.05); color:white; padding:14px 15px; outline:none; font-size:13px; line-height:1.5;
  }
  .ta-input::placeholder { color:rgba(255,255,255,.30); }
  .ta-input:focus { border-color:rgba(200,151,42,.35); box-shadow:0 0 0 4px rgba(200,151,42,.08); }
  .ta-submit {
    display:inline-flex; align-items:center; justify-content:center; height:48px; width:auto; margin-top:0; cursor:pointer;
    border:none; border-radius:15px; font-size:13px; font-weight:700; color:#06101d;
    background:linear-gradient(135deg, #f0c76b, #c8972a);
  }
  .ta-submit:disabled { opacity:.55; cursor:not-allowed; }
  .ta-loading { display:inline-flex; align-items:center; gap:8px; color:rgba(255,255,255,.55); font-size:12px; }
  .ta-loading span { width:7px; height:7px; border-radius:999px; background:rgba(255,255,255,.35); animation:taPulse 1s infinite; }
  .ta-loading span:nth-child(2) { animation-delay:.15s; }
  .ta-loading span:nth-child(3) { animation-delay:.3s; }
  @keyframes taPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.45;transform:scale(.75)} }
  @media (max-width: 900px) {
    .ta-shell { right:16px; top:auto; bottom:18px; transform:none; }
    .ta-launcher-copy { display:none; }
    .ta-launcher { border-radius:16px; padding:12px; }
    .ta-panel { top:auto; right:12px; bottom:12px; left:12px; width:auto; max-height:min(82vh, 720px); }
  }
</style>
<div id="<?= htmlspecialchars($widget_id, ENT_QUOTES, 'UTF-8') ?>" class="ta-shell">
  <button class="ta-launcher" type="button" data-ta-open>
    <span class="ta-launcher-badge">AI</span>
    <span class="ta-launcher-copy">
      <span class="ta-launcher-kicker"><?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="ta-launcher-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
    </span>
  </button>
  <div class="ta-backdrop" data-ta-close></div>
  <aside class="ta-panel" aria-hidden="true">
    <div class="ta-panel-head">
      <div>
        <div class="ta-panel-kicker"><?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="ta-panel-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <button class="ta-panel-close" type="button" data-ta-close aria-label="Bezárás">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="ta-messages" data-ta-messages></div>
    <div class="ta-panel-foot">
      <div class="ta-prompt-list" data-ta-prompts></div>
      <form class="ta-composer" data-ta-form>
        <textarea class="ta-input" rows="1" data-ta-input placeholder="Kérdezz például a szabad termekről..."></textarea>
        <button class="ta-submit" type="submit" data-ta-submit>Küldés</button>
      </form>
    </div>
  </aside>
</div>
<script>
(() => {
  const config = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const root = document.getElementById(config.widgetId);
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  const openButton = root.querySelector('[data-ta-open]');
  const closeButtons = root.querySelectorAll('[data-ta-close]');
  const panel = root.querySelector('.ta-panel');
  const messagesEl = root.querySelector('[data-ta-messages]');
  const promptsEl = root.querySelector('[data-ta-prompts]');
  const formEl = root.querySelector('[data-ta-form]');
  const inputEl = root.querySelector('[data-ta-input]');
  const submitEl = root.querySelector('[data-ta-submit]');

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function autoGrow() {
    inputEl.style.height = '52px';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 136) + 'px';
  }

  function openPanel() {
    root.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
  }

  function closePanel() {
    root.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
  }

  function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function setPrompts(items) {
    const prompts = Array.isArray(items) && items.length ? items : config.prompts;
    promptsEl.innerHTML = prompts.map(prompt =>
      `<button class="ta-pill" type="button" data-ta-prompt="${esc(prompt)}">${esc(prompt)}</button>`
    ).join('');
  }

  function appendUser(text) {
    const row = document.createElement('div');
    row.className = 'ta-msg user';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">Te</div>
        <div class="ta-bubble-text">${esc(text)}</div>
      </div>
    `;
    messagesEl.appendChild(row);
    scrollToBottom();
  }

  function appendLoading() {
    const row = document.createElement('div');
    row.className = 'ta-msg assistant';
    row.dataset.loading = '1';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">${esc(config.eyebrow)}</div>
        <div class="ta-loading"><span></span><span></span><span></span> Válasz készül</div>
      </div>
    `;
    messagesEl.appendChild(row);
    scrollToBottom();
  }

  function removeLoading() {
    messagesEl.querySelector('[data-loading="1"]')?.remove();
  }

  function appendAssistant(payload) {
    const cards = Array.isArray(payload.cards) ? payload.cards : [];
    const actions = Array.isArray(payload.actions) ? payload.actions : [];
    const cardsHtml = cards.length ? `
      <div class="ta-cards">
        ${cards.map(card => `
          <div class="ta-card">
            ${card.eyebrow ? `<div class="ta-card-eyebrow">${esc(card.eyebrow)}</div>` : ''}
            ${card.title ? `<div class="ta-card-title">${esc(card.title)}</div>` : ''}
            ${card.meta ? `<div class="ta-card-meta">${esc(card.meta)}</div>` : ''}
            ${card.detail ? `<div class="ta-card-detail">${esc(card.detail)}</div>` : ''}
          </div>
        `).join('')}
      </div>
    ` : '';
    const actionsHtml = actions.length ? `
      <div class="ta-actions">
        ${actions.map((action, index) => `
          <a class="ta-pill ${index === 0 ? 'primary' : ''}" href="${esc(action.href || '#')}">${esc(action.label || 'Megnyitás')}</a>
        `).join('')}
      </div>
    ` : '';

    const row = document.createElement('div');
    row.className = 'ta-msg assistant';
    row.innerHTML = `
      <div class="ta-bubble">
        <div class="ta-bubble-kicker">${esc(config.eyebrow)}</div>
        <div class="ta-bubble-text">${esc(payload.reply || 'Most nem tudtam válaszolni erre.')}</div>
        ${cardsHtml}
        ${actionsHtml}
      </div>
    `;
    messagesEl.appendChild(row);
    setPrompts(payload.suggestions);
    scrollToBottom();
  }

  async function submitPrompt(text) {
    const message = String(text || '').trim();
    if (!message) return;

    openPanel();
    appendUser(message);
    inputEl.value = '';
    autoGrow();
    submitEl.disabled = true;
    appendLoading();

    try {
      const response = await fetch('/api/assistant', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
      });
      const payload = await response.json();
      removeLoading();
      appendAssistant(payload);
    } catch (error) {
      removeLoading();
      appendAssistant({
        reply: 'Most nem sikerült elérnem az asszisztenst. Próbáld meg újra egy pillanat múlva.',
        suggestions: config.prompts
      });
    } finally {
      submitEl.disabled = false;
    }
  }

  openButton?.addEventListener('click', openPanel);
  closeButtons.forEach(button => button.addEventListener('click', closePanel));

  formEl?.addEventListener('submit', event => {
    event.preventDefault();
    submitPrompt(inputEl.value);
  });

  inputEl?.addEventListener('input', autoGrow);
  inputEl?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      formEl.requestSubmit();
    }
  });

  promptsEl?.addEventListener('click', event => {
    const target = event.target.closest('[data-ta-prompt]');
    if (!target) return;
    submitPrompt(target.getAttribute('data-ta-prompt') || '');
  });

  window.openTickyAssistant = prompt => {
    openPanel();
    if (typeof prompt === 'string' && prompt.trim()) {
      submitPrompt(prompt.trim());
      return;
    }
    inputEl.focus();
  };

  appendAssistant({
    reply: config.intro,
    suggestions: config.prompts
  });
  setPrompts(config.prompts);
  autoGrow();
})();
</script>
    <?php
}

function ido_kozott(string $ido, string $start, string $end): bool {
    return $ido >= $start && $ido <= $end;
}

/**
 * URL routing: egyszerű minta illesztés
 * Példa: match_route('/api/terem/{szam}', $uri) → ['szam' => '204'] vagy false
 */
function match_route(string $pattern, string $uri): array|false {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

/**
 * CORS preflight kezelés
 */
function handle_cors(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }
}
