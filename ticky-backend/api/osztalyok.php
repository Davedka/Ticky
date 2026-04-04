<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';

// 1. Összes osztálykód begyűjtése (Supabase-ből)
$codes = [];
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) {
            $parts = explode(',', $row['osztaly']);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $codes[mb_strtolower($p, 'UTF-8')] = $p;
                }
            }
        }
    }
}

// 2. Csoportosítás előkészítése
$groups = [
    '9'     => [],
    '10'    => [],
    '11'    => [],
    '12'    => [],
    '13'    => [],
    'Egyéb' => []
];

// Ezeket kényszerítjük az "Egyéb" kategóriába (kisbetűvel nézzük)
$force_egyeb_patterns = ['ht_', '1/9'];

$final_list = array_values($codes);
usort($final_list, 'strnatcasecmp'); // Alap rendezés

foreach ($final_list as $kod) {
    $low_kod = mb_strtolower($kod, 'UTF-8');
    $placed = false;

    // SZABÁLY 1: Ha HT_ vagy 1/8 van benne, AZONNAL az Egyébhez megy
    foreach ($force_egyeb_patterns as $pattern) {
        if (str_contains($low_kod, $pattern)) {
            $groups['Egyéb'][] = $kod;
            $placed = true;
            break;
        }
    }
    if ($placed) continue;

    // SZABÁLY 2: Ha számmal kezdődik (9.f, 13.c_du), az évfolyamhoz megy
    if (preg_match('/^(\d+)/', $kod, $m)) {
        $evf = $m[1];
        if (isset($groups[$evf])) {
            $groups[$evf][] = $kod;
            $placed = true;
        }
    }

    // SZABÁLY 3: Minden más megy az Egyébhez
    if (!$placed) {
        $groups['Egyéb'][] = $kod;
    }
}

// 3. MEGJELENÍTÉS (HTML)
?>
<div class="space-y-8">
  <?php 
  // Meghatározott sorrendben megyünk végig az évfolyamokon
  $display_order = ['9', '10', '11', '12', '13', 'Egyéb'];
  
  foreach ($display_order as $key): 
    $list = $groups[$key];
    if (empty($list)) continue;
    ?>
    <section>
      <h3 class="text-xs font-bold uppercase tracking-widest text-white/30 mb-4 px-1">
        <?= ($key === 'Egyéb') ? 'Egyéb / Tanfolyamok' : $key . '. évfolyam' ?>
      </h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <?php foreach ($list as $kod): ?>
          <a href="/osztaly/<?= urlencode($kod) ?>" 
             class="group relative overflow-hidden bg-white/5 hover:bg-white/10 border border-white/10 hover:border-[#f0c76b]/50 rounded-xl p-4 transition-all duration-300">
            <div class="relative z-10">
              <span class="text-lg font-semibold text-white/90 group-hover:text-[#f0c76b] transition-colors">
                <?= htmlspecialchars($kod) ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
