<?php
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/_nav.php';

// Kinyerjük az osztály kódot az URL-ből, ha van (pl. /osztaly/10.C)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route_match = match_route('/osztaly/{kod}', $uri);
$selected_kod = $route_match ? urldecode($route_match['kod']) : null;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png?v=2">

<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=2">

<link rel="apple-touch-icon" href="/favicon.png?v=2">
<title>Ticky – <?= $selected_kod ? htmlspecialchars($selected_kod) : 'Osztályok' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'DM Sans', sans-serif; background-color: #060f1e; color: white; min-height: 100vh;
    background-image: radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%), radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);
  }
  .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); }
  .osztaly-card { transition: all 0.2s; border: 1px solid rgba(255,255,255,0.05); cursor: pointer; }
  .osztaly-card:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); border-color: rgba(200,151,42,0.4); }
  .ora-row { border-bottom: 1px solid rgba(255,255,255,0.05); }
  .ora-row.aktiv { background: rgba(200,151,42,0.1); border-left: 3px solid #c8972a; }
  .ora-row.mult { opacity: 0.4; }
</style>
</head>
<body class="pb-20">

<?php ticky_nav('osztaly', $selected_kod ?: 'Osztályok'); ?>

<main class="max-w-5xl mx-auto px-4 mt-8">
  <?php if (!$selected_kod): ?>
    <div id="search-section" class="mb-10 text-center">
      <h1 style="font-family:'Playfair Display', serif;" class="text-4xl font-bold mb-3">Válassz osztályt</h1>
      <div class="max-w-md mx-auto relative">
        <input type="text" id="search" placeholder="Keresés (pl. 10.c)..." 
               class="w-full bg-white/5 border border-white/10 rounded-2xl py-3 px-5 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 transition-all text-center">
      </div>
    </div>
    <div id="loading" class="flex justify-center py-20">
      <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-yellow-500"></div>
    </div>
    <div id="groups-container" class="space-y-12"></div>

  <?php else: ?>
    <div class="mb-6 flex items-center justify-between">
        <a href="/osztaly" class="text-sm text-white/50 hover:text-white transition-colors">← Vissza az összeshez</a>
        <h2 class="text-xl font-bold"><?= htmlspecialchars($selected_kod) ?> mai órái</h2>
    </div>
    <div id="timetable-container" class="glass rounded-3xl p-4 min-h-[300px]">
        <div id="timetable-loading" class="flex justify-center py-20">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-yellow-500"></div>
        </div>
        <div id="timetable-content"></div>
    </div>
  <?php endif; ?>
</main>

<script>
const selectedKod = <?= json_encode($selected_kod) ?>;

async function init() {
  if (!selectedKod) {
    // Lista betöltése
    try {
      const res = await fetch('/api/osztalyok');
      const data = await res.json();
      renderGroups(data.osztalyok || []);
      document.getElementById('loading')?.classList.add('hidden');
    } catch (e) { console.error(e); }
  } else {
    // Adott osztály órarendjének betöltése
    loadTimetable(selectedKod);
  }
}

// Csoportosítási logika javítása (1/9 és társai -> Egyéb)
function renderGroups(list) {
  const container = document.getElementById('groups-container');
  if(!container) return;
  container.innerHTML = '';

  const groups = {};
  list.forEach(name => {
    let gradeLabel = 'Egyéb';
    
    // Csak akkor rakjuk évfolyamba, ha ponttal kezdődik a szám után (pl. 10.A)
    // és a szám nagyobb mint 1 (1. évfolyam nem létezik a kérésed szerint)
    const match = name.match(/^(\d+)\./);
    if (match) {
        const grade = parseInt(match[1]);
        if (grade > 1) {
            gradeLabel = grade + '. évfolyam';
        }
    }

    if (!groups[gradeLabel]) groups[gradeLabel] = [];
    groups[gradeLabel].push(name);
  });

  // Rendezett kulcsok: számok szerint, majd az Egyéb a végén
  const sortedKeys = Object.keys(groups).sort((a, b) => {
    if (a === 'Egyéb') return 1;
    if (b === 'Egyéb') return -1;
    return parseInt(a) - parseInt(b);
  });

  sortedKeys.forEach(label => {
    const section = document.createElement('div');
    section.className = "mb-8";
    section.innerHTML = `
      <h2 class="text-xs font-bold uppercase tracking-widest text-white/20 mb-4 ml-1">${label}</h2>
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

async function loadTimetable(kod) {
    const content = document.getElementById('timetable-content');
    try {
        const res = await fetch(`/api/osztaly/${encodeURIComponent(kod)}/orarend`);
        const data = await res.json();
        document.getElementById('timetable-loading').classList.add('hidden');

        if (!data.orak || data.orak.length === 0) {
            content.innerHTML = `<p class="text-center text-white/30 py-10">${data.uzenet || 'Nincs mára órarend.'}</p>`;
            return;
        }

        content.innerHTML = data.orak.map((o, i) => {
            const now = new Date();
            const [h, m] = o.kezdes.split(':');
            const [vh, vm] = o.vegzes.split(':');
            const start = new Date().setHours(h, m, 0);
            const end = new Date().setHours(vh, vm, 0);
            const isAktiv = now >= start && now <= end;
            const isMult = now > end;

            // Csoportbontásos logika megjelenítése
            let infoLine = '';
            if (o.is_csoport) {
                infoLine = o.csoportok.map(cs => `
                    <div class="flex justify-between items-center bg-white/5 rounded-lg px-3 py-2 mb-1">
                        <span class="text-yellow-500 font-medium">${cs.terem}</span>
                        <span class="text-xs text-white/60">${cs.tanar_nev || cs.tanar}</span>
                    </div>
                `).join('');
            } else {
                infoLine = `
                    <div class="flex justify-between items-center">
                        <span class="text-yellow-500 font-medium">${o.terem}</span>
                        <span class="text-sm text-white/70">${o.tanar_nev || o.tanar}</span>
                    </div>
                    <div class="text-xs text-white/40 mt-1">${o.tantargy}</div>
                `;
            }

            return `
                <div class="ora-row ${isAktiv ? 'aktiv' : ''} ${isMult ? 'mult' : ''} p-4 flex gap-4">
                    <div class="text-2xl font-bold text-white/20 w-8" style="font-family:'Playfair Display'">${o.ora_sorszam || i+1}</div>
                    <div class="flex-1">
                        <div class="text-xs text-white/30 mb-2">${o.kezdes} - ${o.vegzes}</div>
                        ${infoLine}
                    </div>
                </div>
            `;
        }).join('');

    } catch (e) {
        content.innerHTML = '<p class="text-center text-red-400 py-10">Hiba történt a betöltéskor.</p>';
    }
}

// Kereső logika
const searchInput = document.getElementById('search');
if (searchInput) {
    searchInput.addEventListener('input', async (e) => {
        const val = e.target.value.toLowerCase();
        const res = await fetch('/api/osztalyok');
        const data = await res.json();
        const filtered = data.osztalyok.filter(o => o.toLowerCase().includes(val));
        renderGroups(filtered);
    });
}

init();
</script>
</body>
</html>
