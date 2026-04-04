<?php
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/utils/_nav.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Osztályok</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'DM Sans', sans-serif; background-color: #060f1e; color: white; min-height: 100vh;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);
  }
  .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); }
  .osztaly-card { transition: all 0.2s; border: 1px solid rgba(255,255,255,0.05); }
  .osztaly-card:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); border-color: rgba(200,151,42,0.4); }
</style>
</head>
<body class="pb-20">

<?php ticky_nav('osztaly', 'Osztályok'); ?>

<main class="max-w-5xl mx-auto px-4 mt-8">
  <div class="mb-10 text-center">
    <h1 style="font-family:'Playfair Display', serif;" class="text-4xl font-bold mb-3">Válassz osztályt</h1>
    <div class="max-w-md mx-auto relative">
      <input type="text" id="search" placeholder="Keresés (pl. 10.c)..." 
             class="w-full bg-white/5 border border-white/10 rounded-2xl py-3 px-5 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 transition-all text-center">
    </div>
  </div>

  <div id="loading" class="flex justify-center py-20">
    <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-yellow-500"></div>
  </div>

  <div id="groups-container" class="space-y-12 opacity-0 transition-opacity duration-500"></div>
</main>

<script>
let allOsztalyok = [];

async function init() {
  try {
    const res = await fetch('/api/osztalyok');
    const data = await res.json();
    allOsztalyok = data.osztalyok || [];
    renderGroups(allOsztalyok);
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('groups-container').classList.replace('opacity-0', 'opacity-100');
  } catch (e) {
    console.error("Hiba az osztályok betöltésekor", e);
  }
}

function renderGroups(list) {
  const container = document.getElementById('groups-container');
  container.innerHTML = '';
  
  if (list.length === 0) {
    container.innerHTML = '<p class="text-center text-white/40 py-10">Nincs találat</p>';
    return;
  }

  // CSOPORTOSÍTÁS LOGIKA JAVÍTÁSA
  const groups = {};
  list.forEach(name => {
    let gradeLabel;
    const upper = name.toUpperCase();
    
    // Itt dől el: ha van benne HT vagy alulvonás, akkor "Egyéb"
    if (upper.includes('HT') || name.includes('_')) {
      gradeLabel = 'Egyéb';
    } else {
      const match = name.match(/^(\d+)/);
      if (match) {
        gradeLabel = match[1] + '. évfolyam';
      } else {
        gradeLabel = 'Egyéb';
      }
    }

    if (!groups[gradeLabel]) groups[gradeLabel] = [];
    groups[gradeLabel].push(name);
  });

  // Megjelenítés
  Object.keys(groups).forEach(label => {
    const section = document.createElement('div');
    section.innerHTML = `
      <h2 class="text-sm font-bold uppercase tracking-widest text-white/30 mb-4 ml-1">${label}</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        ${groups[label].map(o => `
          <a href="/osztaly/${encodeURIComponent(o)}" class="osztaly-card glass rounded-xl py-4 px-2 text-center block">
            <span class="text-lg font-medium">${o}</span>
          </a>
        `).join('')}
      </div>
    `;
    container.appendChild(section);
  });
}

document.getElementById('search').addEventListener('input', (e) => {
  const val = e.target.value.toLowerCase();
  const filtered = allOsztalyok.filter(o => o.toLowerCase().includes(val));
  renderGroups(filtered);
});

init();
</script>

</body>
</html>
