<?php
// api/osztalyok.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/osztaly_helpers.php';

// 1. ADATGYŰJTÉS (Supabase + JS fallback)
$codes = [];
$db_classes = sb_get('orarendek', ['select' => 'osztaly']);
if ($db_classes) {
    foreach ($db_classes as $row) {
        if (!empty($row['osztaly'])) {
            // Itt a meglévő _osz_split_and_collect logikádat használd
            $parts = explode(',', $row['osztaly']);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') $codes[mb_strtolower($p, 'UTF-8')] = $p;
            }
        }
    }
}

$final_codes = array_values($codes);
usort($final_codes, 'osztaly_sort_compare');

// 2. CSOPORTOSÍTÁS LOGIKA (A kérésed alapján)
$groups = [
    '9' => [], '10' => [], '11' => [], '12' => [], '13' => [], 'Egyéb' => []
];

$force_egyeb = ['ht_', '1/8'];

foreach ($final_codes as $kod) {
    $low = mb_strtolower($kod, 'UTF-8');
    $is_egyeb = false;
    foreach ($force_egyeb as $f) {
        if (str_contains($low, $f)) { $is_egyeb = true; break; }
    }

    if ($is_egyeb) {
        $groups['Egyéb'][] = $kod;
    } elseif (preg_match('/^(\d+)/', $kod, $m)) {
        $evf = $m[1];
        if (isset($groups[$evf])) $groups[$evf][] = $kod;
        else $groups['Egyéb'][] = $kod;
    } else {
        $groups['Egyéb'][] = $kod;
    }
}

// 3. MEGJELENÍTÉS (Visszatesszük a HTML-t, hogy ne legyen üres az oldal)
?>
<div class="space-y-8">
  <?php foreach ($groups as $label => $list): if (empty($list)) continue; ?>
    <section>
      <h3 class="text-xs font-bold uppercase tracking-widest text-white/30 mb-4 px-1">
        <?= $label === 'Egyéb' ? 'Egyéb / Tanfolyamok' : $label . '. évfolyam' ?>
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
