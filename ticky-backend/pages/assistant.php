<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Asszisztens</title>
<link rel="icon" type="image/png" href="/favicon.png?v=20260327c">
<link rel="shortcut icon" href="/favicon.ico?v=20260327c">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family:'DM Sans',sans-serif;
    background-color:#060f1e;
    min-height:100vh;
    color:white;
    background-image:
      radial-gradient(ellipse 70% 55% at 15% 10%, rgba(26,74,138,.55) 0%, transparent 60%),
      radial-gradient(ellipse 50% 45% at 85% 85%, rgba(200,151,42,.18) 0%, transparent 55%),
      radial-gradient(ellipse 60% 38% at 55% 100%, rgba(11,46,89,.6) 0%, transparent 60%);
  }
  body::before {
    content:'';
    position:fixed;
    inset:0;
    pointer-events:none;
    z-index:0;
    background-image:
      linear-gradient(rgba(255,255,255,.02) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.02) 1px, transparent 1px);
    background-size:40px 40px;
  }
  .top-line { position:fixed; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg, transparent, rgba(200,151,42,.5), transparent); z-index:200; }
  .glass { background:rgba(255,255,255,.05); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.10); }
  .pulse { animation:pd 2s infinite; }
  @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .fade-up { animation:fu .45s cubic-bezier(.22,1,.36,1) both; }
  @keyframes fu { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
  .ai-layout { position:relative; z-index:10; max-width:1180px; margin:0 auto; padding:32px 20px 24px; display:grid; grid-template-columns:320px minmax(0,1fr); gap:18px; }
  .ai-note { padding:22px; border-radius:24px; align-self:start; }
  .ai-shell { border-radius:26px; overflow:hidden; min-height:72vh; display:flex; flex-direction:column; }
  .ai-head { padding:20px 22px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
  .ai-status { display:inline-flex; align-items:center; gap:8px; padding:7px 12px; border-radius:999px; font-size:11px; font-weight:600; background:rgba(26,138,74,.16); color:#86efac; border:1px solid rgba(26,138,74,.3); }
  .ai-messages { flex:1; overflow:auto; padding:22px; display:flex; flex-direction:column; gap:14px; }
  .ai-msg { display:flex; }
  .ai-msg.user { justify-content:flex-end; }
  .ai-bubble { max-width:min(620px, 100%); border-radius:20px; padding:16px 16px 14px; border:1px solid rgba(255,255,255,.09); box-shadow:0 14px 40px rgba(6,15,30,.28); }
  .ai-msg.assistant .ai-bubble { background:rgba(255,255,255,.05); }
  .ai-msg.user .ai-bubble { background:linear-gradient(135deg, rgba(200,151,42,.22), rgba(26,74,138,.18)); border-color:rgba(200,151,42,.2); }
  .ai-kicker { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.35); font-weight:600; margin-bottom:8px; }
  .ai-text { font-size:14px; line-height:1.6; color:rgba(255,255,255,.86); }
  .ai-cards { display:grid; gap:10px; margin-top:12px; }
  .ai-card { padding:13px 14px; border-radius:16px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); }
  .ai-card-eyebrow { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:rgba(200,151,42,.75); margin-bottom:4px; }
  .ai-card-title { font-family:'Playfair Display',serif; font-size:18px; line-height:1.15; color:white; }
  .ai-card-meta { margin-top:4px; font-size:13px; color:rgba(255,255,255,.68); }
  .ai-card-detail { margin-top:4px; font-size:12px; color:rgba(255,255,255,.42); }
  .ai-actions, .ai-suggest-list, .ai-note-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
  .ai-pill, .ai-chip {
    display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:9px 13px;
    border:1px solid rgba(255,255,255,.11); background:rgba(255,255,255,.06);
    color:rgba(255,255,255,.78); font-size:12px; font-weight:500; text-decoration:none; cursor:pointer;
    transition:transform .15s ease, border-color .15s ease, background .15s ease, color .15s ease;
  }
  .ai-pill:hover, .ai-chip:hover { transform:translateY(-1px); background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.18); color:white; }
  .ai-pill.primary { background:rgba(200,151,42,.12); border-color:rgba(200,151,42,.28); color:#f0c76b; }
  .ai-suggest-wrap { padding:0 22px 14px; }
  .ai-foot { padding:16px 22px 20px; border-top:1px solid rgba(255,255,255,.08); }
  .ai-form { display:flex; gap:10px; align-items:flex-end; }
  .ai-input {
    flex:1; resize:none; border-radius:18px; border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.05); color:white; padding:14px 16px; min-height:56px; max-height:140px;
    outline:none; font-size:14px; line-height:1.5; transition:border-color .2s ease, box-shadow .2s ease;
  }
  .ai-input::placeholder { color:rgba(255,255,255,.32); }
  .ai-input:focus { border-color:rgba(200,151,42,.35); box-shadow:0 0 0 4px rgba(200,151,42,.08); }
  .ai-send {
    height:56px; padding:0 18px; border-radius:16px; border:none; cursor:pointer; width:auto; margin-top:0;
    font-size:13px; font-weight:700; color:#06101d; background:linear-gradient(135deg, #f0c76b, #c8972a);
    transition:transform .15s ease, filter .15s ease, opacity .15s ease;
  }
  .ai-send:hover { transform:translateY(-1px); filter:brightness(1.04); }
  .ai-send:disabled { opacity:.55; cursor:not-allowed; transform:none; }
  .ai-note-list { display:flex; flex-direction:column; gap:10px; margin-top:18px; }
  .ai-note-item { display:flex; gap:10px; align-items:flex-start; font-size:13px; color:rgba(255,255,255,.7); }
  .ai-note-dot { width:8px; height:8px; border-radius:999px; margin-top:6px; flex-shrink:0; background:#c8972a; box-shadow:0 0 10px rgba(200,151,42,.45); }
  .ai-loading { display:inline-flex; align-items:center; gap:8px; color:rgba(255,255,255,.55); font-size:13px; }
  .ai-loading span { width:7px; height:7px; border-radius:999px; background:rgba(255,255,255,.36); animation:pd 1s infinite; }
  .ai-loading span:nth-child(2) { animation-delay:.15s; }
  .ai-loading span:nth-child(3) { animation-delay:.3s; }
  a { text-decoration:none; }
  @media (max-width: 980px) {
    .ai-layout { grid-template-columns:1fr; }
    .ai-note { order:2; }
    .ai-shell { min-height:70vh; }
  }
  @media (max-width: 640px) {
    .ai-layout { padding:18px 14px 20px; }
    .ai-head, .ai-messages, .ai-suggest-wrap, .ai-foot { padding-left:16px; padding-right:16px; }
    .ai-bubble { max-width:100%; }
    .ai-form { flex-direction:column; }
    .ai-send { width:100%; }
  }
</style>
</head>
<body>
<div class="top-line"></div>

<nav style="background:rgba(6,15,30,.78);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);" class="sticky top-0 z-50 px-5 h-16 flex items-center justify-between">
  <div class="flex items-center gap-3 min-w-0">
    <a href="/" style="font-family:'Playfair Display',serif;color:white;font-size:18px;font-weight:700;" class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full pulse flex-shrink-0" style="background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;"></span>
      Ticky
    </a>
    <span style="color:rgba(255,255,255,.2);">·</span>
    <span class="text-sm truncate" style="color:rgba(255,255,255,.45);">Asszisztens</span>
  </div>
  <div class="flex items-center gap-2">
    <a href="/termek" class="px-3 py-2 rounded-lg text-xs font-medium" style="color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);">Termek</a>
    <a href="/tanar" class="px-3 py-2 rounded-lg text-xs font-medium" style="color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);">Tanár</a>
  </div>
</nav>

<main class="ai-layout">
  <aside class="ai-note glass fade-up">
    <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.32);">Új funkció</p>
    <h1 style="font-family:'Playfair Display',serif;font-size:40px;line-height:1.02;font-weight:700;color:white;margin-top:10px;">Ticky Asszisztens</h1>
    <p class="text-sm mt-4" style="color:rgba(255,255,255,.62);line-height:1.7;">
      Ez az első verzió nem találgat, hanem a jelenlegi terem- és órarend-adatokból dolgozik. Így gyors, visszafogott és a mostani UI-hoz illő marad.
    </p>

    <div class="ai-note-list">
      <div class="ai-note-item">
        <span class="ai-note-dot"></span>
        <span>Megmondja, melyik termek szabadok vagy foglaltak most.</span>
      </div>
      <div class="ai-note-item">
        <span class="ai-note-dot"></span>
        <span>Lekéri, mi történik egy adott teremben, és mi jön utána.</span>
      </div>
      <div class="ai-note-item">
        <span class="ai-note-dot"></span>
        <span>Gyorsan át tud dobni a megfelelő oldalra, ha inkább a dedikált nézet kell.</span>
      </div>
    </div>

    <div class="ai-note-chips">
      <button class="ai-chip" type="button" onclick="seedPrompt('Melyik termek szabadok most?')">Szabad termek</button>
      <button class="ai-chip" type="button" onclick="seedPrompt('Melyik termek foglaltak most?')">Foglalt termek</button>
      <button class="ai-chip" type="button" onclick="seedPrompt('Nyisd meg a tanárkeresőt')">Tanár kereső</button>
      <button class="ai-chip" type="button" onclick="seedPrompt('Mi van most a 204-es teremben?')">Adott terem</button>
    </div>
  </aside>

  <section class="ai-shell glass fade-up" style="animation-delay:.08s;">
    <div class="ai-head">
      <div>
        <p class="text-xs font-semibold tracking-widest uppercase" style="color:rgba(255,255,255,.3);">AI asszisztens</p>
        <h2 style="font-family:'Playfair Display',serif;font-size:30px;font-weight:700;color:white;line-height:1.05;margin-top:8px;">Kérdezz rá a Ticky adataira</h2>
      </div>
      <div class="ai-status">
        <span class="w-1.5 h-1.5 rounded-full pulse flex-shrink-0" style="background:#4ade80;display:inline-block;"></span>
        Élő adatokból válaszol
      </div>
    </div>

    <div id="ai-messages" class="ai-messages"></div>
    <div class="ai-suggest-wrap">
      <div id="ai-suggest-list" class="ai-suggest-list"></div>
    </div>
    <div class="ai-foot">
      <form id="ai-form" class="ai-form">
        <textarea id="ai-input" class="ai-input" rows="1" placeholder="Például: Melyik termek szabadok most?"></textarea>
        <button id="ai-send" class="ai-send" type="submit">Küldés</button>
      </form>
    </div>
  </section>
</main>

<script>
const defaultSuggestions = [
  'Melyik termek szabadok most?',
  'Melyik termek foglaltak most?',
  'Mi van most a 204-es teremben?',
  'Nyisd meg a tanárkeresőt'
]

const messagesEl = document.getElementById('ai-messages')
const suggestEl = document.getElementById('ai-suggest-list')
const inputEl = document.getElementById('ai-input')
const sendEl = document.getElementById('ai-send')
const formEl = document.getElementById('ai-form')

function esc(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function autoGrow() {
  inputEl.style.height = '56px'
  inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px'
}

function scrollToBottom() {
  messagesEl.scrollTop = messagesEl.scrollHeight
}

function setSuggestions(items) {
  const list = Array.isArray(items) && items.length ? items : defaultSuggestions
  suggestEl.innerHTML = list.map(item =>
    `<button type="button" class="ai-chip" data-suggestion="${esc(item)}">${esc(item)}</button>`
  ).join('')
}

function appendUserMessage(text) {
  const wrap = document.createElement('div')
  wrap.className = 'ai-msg user'
  wrap.innerHTML = `
    <div class="ai-bubble">
      <div class="ai-kicker">Te</div>
      <div class="ai-text">${esc(text)}</div>
    </div>
  `
  messagesEl.appendChild(wrap)
  scrollToBottom()
}

function appendLoading() {
  const wrap = document.createElement('div')
  wrap.className = 'ai-msg assistant'
  wrap.id = 'ai-loading-row'
  wrap.innerHTML = `
    <div class="ai-bubble">
      <div class="ai-kicker">Ticky Assist</div>
      <div class="ai-loading"><span></span><span></span><span></span> Válasz készül</div>
    </div>
  `
  messagesEl.appendChild(wrap)
  scrollToBottom()
}

function removeLoading() {
  document.getElementById('ai-loading-row')?.remove()
}

function appendAssistantMessage(payload) {
  const cards = Array.isArray(payload.cards) ? payload.cards : []
  const actions = Array.isArray(payload.actions) ? payload.actions : []

  const cardHtml = cards.length ? `
    <div class="ai-cards">
      ${cards.map(card => `
        <div class="ai-card">
          ${card.eyebrow ? `<div class="ai-card-eyebrow">${esc(card.eyebrow)}</div>` : ''}
          ${card.title ? `<div class="ai-card-title">${esc(card.title)}</div>` : ''}
          ${card.meta ? `<div class="ai-card-meta">${esc(card.meta)}</div>` : ''}
          ${card.detail ? `<div class="ai-card-detail">${esc(card.detail)}</div>` : ''}
        </div>
      `).join('')}
    </div>
  ` : ''

  const actionHtml = actions.length ? `
    <div class="ai-actions">
      ${actions.map((action, index) => `
        <a class="ai-pill ${index === 0 ? 'primary' : ''}" href="${esc(action.href || '#')}">${esc(action.label || 'Megnyitás')}</a>
      `).join('')}
    </div>
  ` : ''

  const wrap = document.createElement('div')
  wrap.className = 'ai-msg assistant'
  wrap.innerHTML = `
    <div class="ai-bubble">
      <div class="ai-kicker">Ticky Assist</div>
      <div class="ai-text">${esc(payload.reply || 'Nem sikerült választ adnom erre.')}</div>
      ${cardHtml}
      ${actionHtml}
    </div>
  `
  messagesEl.appendChild(wrap)
  scrollToBottom()
  setSuggestions(payload.suggestions)
}

async function submitPrompt(text) {
  const message = String(text || '').trim()
  if (!message) return

  appendUserMessage(message)
  inputEl.value = ''
  autoGrow()
  sendEl.disabled = true
  appendLoading()

  try {
    const response = await fetch('/api/assistant', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message })
    })

    const payload = await response.json()
    removeLoading()
    appendAssistantMessage(payload)
  } catch (error) {
    removeLoading()
    appendAssistantMessage({
      reply: 'Most nem sikerült elérnem az asszisztenst. Próbáld meg újra egy pillanat múlva.',
      suggestions: defaultSuggestions
    })
  } finally {
    sendEl.disabled = false
  }
}

function seedPrompt(text) {
  inputEl.value = text
  autoGrow()
  inputEl.focus()
}

formEl.addEventListener('submit', event => {
  event.preventDefault()
  submitPrompt(inputEl.value)
})

inputEl.addEventListener('input', autoGrow)
inputEl.addEventListener('keydown', event => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    formEl.requestSubmit()
  }
})

suggestEl.addEventListener('click', event => {
  const button = event.target.closest('[data-suggestion]')
  if (!button) return
  submitPrompt(button.getAttribute('data-suggestion') || '')
})

appendAssistantMessage({
  reply: 'Itt vagyok. Kérdezhetsz szabad vagy foglalt termekről, egy konkrét terem aktuális állapotáról, vagy kérhetsz gyors ugrást a megfelelő oldalra.',
  actions: [
    { label: 'Összes terem', href: '/termek' },
    { label: 'Tanár kereső', href: '/tanar' }
  ],
  suggestions: defaultSuggestions
})
autoGrow()
</script>
</body>
</html>
