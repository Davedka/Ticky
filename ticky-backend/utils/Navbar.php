<?php
// utils/navbar.php – Közös navbar + sidebar minden Ticky oldalhoz

function ticky_navbar(string $aktiv = '') {
    ?>
    <nav style="background:rgba(6,15,30,.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.07);position:sticky;top:0;z-index:100;height:64px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;">
      <a href="/" style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:white;text-decoration:none;display:flex;align-items:center;gap:8px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#c8972a;box-shadow:0 0 8px #c8972a;display:inline-block;animation:navPulse 2s infinite;flex-shrink:0;"></span>
        Ticky
      </a>
      <div style="display:flex;align-items:center;gap:2px;flex-wrap:wrap;">
        <?php
        $links = [
          ['href' => '/termek',  'label' => 'Termek',   'key' => 'termek'],
          ['href' => '/tanar',   'label' => 'Tanár',    'key' => 'tanar'],
          ['href' => '/kijelzo', 'label' => 'Kijelző',  'key' => 'kijelzo'],
          ['href' => '/qr',      'label' => 'QR',       'key' => 'qr'],
          ['href' => '/admin',   'label' => '⚙️ Admin', 'key' => 'admin'],
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
      </div>
    </nav>
    <style>
      @keyframes navPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
    </style>
    <?php
}

// ── Bal oldali sidebar (Eseménynaptár, Support, Bug) ─────────────────
function ticky_sidebar() {
    ?>
    <div class="ticky-sidebar">
      <a href="https://esemenynaptar.onrender.com/" target="_blank" rel="noopener"
         class="tsb-item" data-label="Eseménynaptár">📅</a>
      <div class="tsb-divider"></div>
      <a href="mailto:tickysupport@gmail.com?subject=Ticky%20support"
         class="tsb-item" data-label="Support">✉️</a>
      <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener"
         class="tsb-item" data-label="Bug report">🐛</a>
    </div>
    <style>
      .ticky-sidebar {
        position:fixed; left:0; top:50%; transform:translateY(-50%);
        z-index:150; display:flex; flex-direction:column; align-items:center; gap:2px;
        padding:8px 6px;
        background:rgba(6,15,30,.78); backdrop-filter:blur(20px);
        border:1px solid rgba(255,255,255,.08); border-left:none;
        border-radius:0 12px 12px 0;
      }
      .tsb-item {
        position:relative; width:36px; height:36px; border-radius:8px;
        display:flex; align-items:center; justify-content:center;
        font-size:17px; text-decoration:none;
        color:rgba(255,255,255,.6); transition:all .18s;
      }
      .tsb-item:hover { background:rgba(255,255,255,.10); color:white; }
      .tsb-item::after {
        content:attr(data-label);
        position:absolute; left:46px; top:50%; transform:translateY(-50%);
        background:rgba(6,15,30,.96); color:rgba(255,255,255,.88);
        font-size:12px; font-family:'DM Sans',sans-serif; font-weight:500;
        padding:5px 11px; border-radius:8px; white-space:nowrap;
        opacity:0; pointer-events:none; transition:opacity .15s;
        border:1px solid rgba(255,255,255,.10);
      }
      .tsb-item:hover::after { opacity:1; }
      .tsb-divider { width:20px; height:1px; background:rgba(255,255,255,.10); margin:2px 0; }
      @media (max-width:600px) { .ticky-sidebar { display:none; } }
    </style>
    <?php
}
