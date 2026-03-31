<?php
// utils/navbar.php – Közös navbar minden Ticky oldalhoz
// Használat: require_once __DIR__ . '/../utils/navbar.php'; ticky_navbar($aktiv);
// vagy az index.php-ban: ticky_navbar_inline($aktiv);

function ticky_navbar(string $aktiv = '') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    ?>
    <nav style="background:rgba(6,15,30,.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);position:sticky;top:0;z-index:100;height:64px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;">

      <!-- Logo -->
      <a href="/" style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;text-decoration:none;display:flex;align-items:center;gap:8px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;animation:navPulse 2s infinite;flex-shrink:0;"></span>
        Ticky
      </a>

      <!-- Linkek -->
      <div style="display:flex;align-items:center;gap:2px;flex-wrap:wrap;">
        <?php
        $links = [
          ['href' => '/termek',  'label' => 'Termek',       'key' => 'termek'],
          ['href' => '/tanar',   'label' => 'Tanár',        'key' => 'tanar'],
          ['href' => '/kijelzo', 'label' => 'Kijelző',      'key' => 'kijelzo'],
          ['href' => '/qr',      'label' => 'QR',           'key' => 'qr'],
          ['href' => '/admin',   'label' => '⚙️ Admin',     'key' => 'admin'],
        ];
        foreach ($links as $l):
            $isActive = ($aktiv === $l['key']);
            $style = $isActive
                ? 'color:rgba(200,151,42,.9);background:rgba(200,151,42,.1);border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;'
                : 'color:rgba(255,255,255,.55);border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;';
        ?>
          <a href="<?= $l['href'] ?>" style="<?= $style ?>"
            onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.08)'"
            onmouseout="this.style.color='<?= $isActive ? 'rgba(200,151,42,.9)' : 'rgba(255,255,255,.55)' ?>';this.style.background='<?= $isActive ? 'rgba(200,151,42,.1)' : 'transparent' ?>'">
            <?= $l['label'] ?>
          </a>
        <?php endforeach; ?>

        <!-- Eseménynaptár – külső link, kiemelve -->
        <a href="https://esemenynaptar.onrender.com/"
           target="_blank"
           rel="noopener"
           style="display:flex;align-items:center;gap:6px;margin-left:6px;color:rgba(0,200,200,.8);background:rgba(0,200,200,.08);border:1px solid rgba(0,200,200,.2);border-radius:8px;padding:7px 14px;font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;"
           onmouseover="this.style.color='white';this.style.background='rgba(0,200,200,.15)';this.style.borderColor='rgba(0,200,200,.4)'"
           onmouseout="this.style.color='rgba(0,200,200,.8)';this.style.background='rgba(0,200,200,.08)';this.style.borderColor='rgba(0,200,200,.2)'">
          📅 Eseménynaptár
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
      </div>
    </nav>
    <style>
      @keyframes navPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
    </style>
    <?php
}
