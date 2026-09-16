<?php
// utils/mascot.php
// A Ticky kabalája: keleti (kínai) stílusú sárkány, beágyazott SVG-ként.
// Nincs képfájl, nincs külső kérés, nincs új függőség.
//
// A kabala a rendszer VALÓS állapotát mutatja, nem dekoráció. Az állapot
// színét a mancsában tartott gyöngy viszi – klasszikus kínai sárkánymotívum:
//   aktiv   – tanítási nap, éber tekintet, zölden izzó gyöngy
//   szunet  – szünet van, alszik, halvány arany gyöngy
//   hetvege – hétvége, elégedett, kék gyöngy
//
// Az állapot szerveroldalon dől el a már meglévő adatból (mai_nap(),
// ticky_aktiv_szunet_nev()), így nincs villanás betöltéskor.

/**
 * A kabala állapota. Paraméter nélkül a mai napból és az aktív szünetből
 * számol; teszteléshez és előnézethez felülbírálható.
 */
function ticky_mascot_allapot(?int $nap = null, ?string $szunet = null): string
{
    $nap ??= function_exists('mai_nap') ? mai_nap() : 1;
    $szunet ??= function_exists('ticky_aktiv_szunet_nev') ? ticky_aktiv_szunet_nev() : null;

    // A szünet elsőbbséget élvez: hétvégén is alvó arcot mutatunk, ha van.
    if ($szunet !== null && trim($szunet) !== '') {
        return 'szunet';
    }

    return $nap === 0 ? 'hetvege' : 'aktiv';
}

/** Állapotonkénti gyöngyszín, tekintet és felirat. */
function ticky_mascot_temak(): array
{
    return [
        'aktiv' => [
            'ko'      => '#4ade80',
            'felirat' => 'Ticky sárkány – éber, tanítási nap van',
            'szem'    => 'nyitott',
        ],
        'szunet' => [
            'ko'      => '#f0c76b',
            'felirat' => 'Ticky sárkány – alszik, szünet van',
            'szem'    => 'csukott',
        ],
        'hetvege' => [
            'ko'      => '#93c5fd',
            'felirat' => 'Ticky sárkány – pihen, hétvége van',
            'szem'    => 'mosolygo',
        ],
    ];
}

/**
 * Kirajzolja a kabalát.
 *
 * @param array $opciok  allapot: állapot felülbírálása
 *                       meret:   CSS méret
 */
function render_ticky_mascot(array $opciok = []): void
{
    static $stilus_kiirva = false;
    static $sorszam = 0;

    $temak   = ticky_mascot_temak();
    $allapot = (string) ($opciok['allapot'] ?? ticky_mascot_allapot());
    if (!isset($temak[$allapot])) {
        $allapot = 'aktiv';
    }

    $tema  = $temak[$allapot];
    $meret = (string) ($opciok['meret'] ?? 'clamp(84px, 22vw, 124px)');
    $azon  = 'tm' . (++$sorszam); // egyedi SVG azonosítók, ha többször rajzoljuk

    if (!$stilus_kiirva) {
        $stilus_kiirva = true;
        ticky_mascot_stilus();
    }
    ?>
<figure class="tm" data-tm-allapot="<?= htmlspecialchars($allapot, ENT_QUOTES) ?>"
        style="--tm-meret:<?= htmlspecialchars($meret, ENT_QUOTES) ?>;--tm-ko:<?= htmlspecialchars($tema['ko'], ENT_QUOTES) ?>">
  <svg viewBox="-10 0 124 146" role="img" aria-label="<?= htmlspecialchars($tema['felirat'], ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($tema['felirat'], ENT_QUOTES) ?></title>

    <defs>
      <linearGradient id="<?= $azon ?>-bor" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0"   stop-color="#5aa0ec"/>
        <stop offset=".55" stop-color="#2a6bbd"/>
        <stop offset="1"   stop-color="#0d3163"/>
      </linearGradient>
      <linearGradient id="<?= $azon ?>-arany" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#f7dda0"/>
        <stop offset="1" stop-color="#c8972a"/>
      </linearGradient>
      <radialGradient id="<?= $azon ?>-gyongy">
        <stop offset="0"  stop-color="#ffffff" stop-opacity=".95"/>
        <stop offset=".45" stop-color="var(--tm-ko)"/>
        <stop offset="1"  stop-color="var(--tm-ko)" stop-opacity=".35"/>
      </radialGradient>
    </defs>

    <!-- Csillagszórás (2. referencia) -->
    <g class="tm-csillagok">
      <?php ticky_mascot_csillag(16, 30, 4.5); ?>
      <?php ticky_mascot_csillag(100, 62, 3.4); ?>
      <?php ticky_mascot_csillag(14, 70, 3.8); ?>
      <?php ticky_mascot_csillag(92, 120, 3.0); ?>
    </g>

    <!-- Szárny a test mögött, jobbra kifeszítve (2. referencia).
         Profilból csak az egyik látszik, a másik a test mögé esik. -->
    <g class="tm-szarny">
      <path class="tm-szarny-membran" d="M 52 68 Q 28 44 -4 52 Q 5 62 7 70
            Q 15 65 19 74 Q 27 68 31 78 Q 39 71 43 80 Q 49 74 52 68 Z"
            fill="url(#<?= $azon ?>-bor)"/>
      <path class="tm-szarny-csont" d="M 52 68 Q 28 44 -4 52"/>
      <path class="tm-szarny-ujj" d="M 19 57 L 19 74"/>
      <path class="tm-szarny-ujj" d="M 33 62 L 31 78"/>
    </g>

    <!-- Sörénypamacsok a gerinc külső ívén (1. és 3. referencia) -->
    <g class="tm-sorenyy">
      <path d="M 49 45 Q 40 35 29 34 Q 40 40 46 49 Z"/>
      <path d="M 41 60 Q 30 54 21 58 Q 32 60 38 68 Z"/>
      <path d="M 39 78 Q 28 80 23 90 Q 31 82 40 85 Z"/>
    </g>

    <!-- Kígyózó test: elvékonyodó szegmensek -->
    <g class="tm-test" stroke="url(#<?= $azon ?>-bor)">
      <path class="tm-t1" d="M 56 42 Q 44 50 40 64"/>
      <path class="tm-t2" d="M 40 64 Q 36 80 52 88"/>
      <path class="tm-t3" d="M 52 88 Q 72 96 68 110"/>
      <path class="tm-t4" d="M 68 110 Q 62 126 46 124"/>
      <path class="tm-t5" d="M 46 124 Q 35 122 31 114"/>
    </g>

    <!-- Pikkelyívek: enélkül a test sima csőnek látszik -->
    <g class="tm-pikkely">
      <path d="M 47 50 Q 52 55 47 60"/>
      <path d="M 43 58 Q 48 63 43 68"/>
      <path d="M 42 70 Q 47 75 43 80"/>
      <path d="M 50 84 Q 54 89 49 93"/>
      <path d="M 60 92 Q 63 97 58 100"/>
      <path d="M 66 104 Q 68 109 63 111"/>
    </g>

    <!-- Farokvég lángja -->
    <path class="tm-farok-lang" d="M 32 117 Q 21 124 17 137 Q 27 127 36 123 Z"
          fill="url(#<?= $azon ?>-arany)"/>

    <!-- Mellső mancs a gyöngy felé -->
    <path class="tm-mancs" d="M 45 86 Q 34 84 27 89" stroke="url(#<?= $azon ?>-bor)"/>

    <!-- Gyöngy: ez viszi az állapot színét -->
    <circle class="tm-ko-fenyy" cx="21" cy="93" r="12"/>
    <circle class="tm-ko" cx="21" cy="93" r="7.5" fill="url(#<?= $azon ?>-gyongy)"/>

    <!-- Fej, profilból, a cím felé fordulva -->
    <path class="tm-fej" d="M 48 26 Q 54 13 72 14 Q 94 15 110 28 Q 112 33 104 35 Q 95 37 86 36 Q 88 44 78 45 Q 60 47 52 39 Z"
          fill="url(#<?= $azon ?>-bor)"/>

    <!-- Szarvak, hátrafelé söpörve -->
    <g class="tm-szarv" fill="url(#<?= $azon ?>-arany)">
      <path d="M 57 19 Q 47 7 33 3 Q 48 10 62 17 Z"/>
      <path d="M 70 15 Q 63 4 51 1 Q 64 7 74 14 Z"/>
    </g>

    <!-- Bajusz (3. referencia) -->
    <path class="tm-bajusz" d="M 107 32 Q 114 37 112 27"/>
    <path class="tm-bajusz" d="M 102 38 Q 108 47 113 46"/>

    <!-- Orrlyuk és száj -->
    <circle class="tm-orrlyuk" cx="102" cy="29" r="1.9"/>
    <path class="tm-szaj" d="M 104 35 Q 90 41 79 41"/>

    <?php ticky_mascot_szemek($tema['szem']); ?>

    <?php if ($tema['szem'] === 'csukott'): ?>
      <g class="tm-zzz">
        <text x="76" y="12" class="tm-z1">z</text>
        <text x="86" y="6" class="tm-z2">z</text>
      </g>
    <?php endif; ?>
  </svg>
</figure>
    <?php
}

/** Négyágú csillag a háttérbe. */
function ticky_mascot_csillag(float $x, float $y, float $meret): void
{
    // Homorú ágak: a rombusz laposnak látszott, ez tényleg csillogásnak néz ki.
    printf(
        '<path class="tm-csillag" d="M %1$s %2$s Q %1$s %3$s %4$s %3$s Q %1$s %3$s %1$s %5$s'
        . ' Q %1$s %3$s %6$s %3$s Q %1$s %3$s %1$s %2$s Z"/>' . "\n",
        $x,
        $y - $meret,
        $y,
        $x + $meret,
        $y + $meret,
        $x - $meret
    );
}

/** Az állapothoz tartozó szem (profilból, ezért csak egy látszik). */
function ticky_mascot_szemek(string $tipus): void
{
    if ($tipus === 'csukott') {
        ?>
        <path class="tm-szem-iv" d="M 63 26 Q 69 31 75 26"/>
        <?php
        return;
    }

    if ($tipus === 'mosolygo') {
        ?>
        <path class="tm-szem-iv" d="M 63 29 Q 69 22 75 29"/>
        <?php
        return;
    }
    ?>
    <g class="tm-szemek">
      <ellipse class="tm-szem" cx="69" cy="27" rx="7" ry="6"/>
      <g class="tm-pupillak">
        <ellipse class="tm-pupilla" cx="70" cy="27" rx="2.8" ry="4.4"/>
        <circle class="tm-csillam" cx="71.6" cy="24.8" r="1.3"/>
      </g>
    </g>
    <?php
}

/** A kabala stíluslapja és a kurzorkövetés. Munkamenetenként egyszer fut le. */
function ticky_mascot_stilus(): void
{
    ?>
<style>
  .tm{margin:0;width:var(--tm-meret);flex-shrink:0;line-height:0;}
  .tm svg{width:100%;height:auto;overflow:visible;
    animation:tm-lebeg 6s ease-in-out infinite;
    transition:transform .3s cubic-bezier(.22,1,.36,1);}
  .tm:hover svg{transform:rotate(-3deg) scale(1.06);}

  /* Kígyózó test: a szegmensek vastagsága adja az elvékonyodást. */
  .tm-test path{fill:none;stroke-linecap:round;}
  .tm-t1{stroke-width:20;} .tm-t2{stroke-width:17;} .tm-t3{stroke-width:13;}
  .tm-t4{stroke-width:9;}  .tm-t5{stroke-width:5;}

  .tm-fej{stroke:rgba(200,151,42,.5);stroke-width:1.6;
    filter:drop-shadow(0 6px 16px rgba(6,15,30,.6));}
  .tm-mancs{fill:none;stroke-width:6;stroke-linecap:round;}
  .tm-orrlyuk{fill:#0a1b33;opacity:.7;}
  .tm-szaj{fill:none;stroke:#0a1b33;stroke-width:2;stroke-linecap:round;opacity:.65;}

  .tm-szarny-membran{opacity:.88;stroke:rgba(240,199,107,.7);stroke-width:1.8;}
  .tm-szarny-csont{fill:none;stroke:#f0c76b;stroke-width:2.6;stroke-linecap:round;}
  .tm-szarny-ujj{fill:none;stroke:rgba(240,199,107,.55);stroke-width:1.6;stroke-linecap:round;}
  .tm-szarny{transform-box:fill-box;transform-origin:right center;
    animation:tm-csapkod 4.2s ease-in-out infinite;}

  .tm-pikkely path{fill:none;stroke:rgba(255,255,255,.22);stroke-width:1.6;
    stroke-linecap:round;}
  .tm-sorenyy path{fill:url(#tm-soreny-nincs);fill:rgba(240,199,107,.85);}
  .tm-bajusz{fill:none;stroke:#f0c76b;stroke-width:2;stroke-linecap:round;opacity:.85;}
  .tm-farok-lang{opacity:.95;}

  .tm-ko{transform-box:fill-box;transform-origin:center;
    animation:tm-pulzal 2.6s ease-in-out infinite;}
  .tm-ko-fenyy{fill:var(--tm-ko);opacity:.16;
    transform-box:fill-box;transform-origin:center;
    animation:tm-glow 2.6s ease-in-out infinite;}

  .tm-szem{fill:#eaf2ff;}
  .tm-pupilla{fill:#0a1b33;}
  .tm-csillam{fill:#ffffff;opacity:.9;}
  .tm-pupillak{transition:transform .12s ease-out;}
  .tm-szemek{animation:tm-pislog 7s infinite;transform-origin:69px 27px;}
  .tm-szem-iv{fill:none;stroke:#0a1b33;stroke-width:2.6;stroke-linecap:round;opacity:.8;}

  .tm-csillag{fill:rgba(240,199,107,.75);}
  .tm-csillagok{animation:tm-csillamlik 4s ease-in-out infinite;}

  .tm-zzz text{fill:rgba(240,199,107,.8);font-family:'DM Sans',sans-serif;
    font-size:11px;font-weight:600;}
  .tm-z1{animation:tm-szall 3s ease-in-out infinite;}
  .tm-z2{animation:tm-szall 3s ease-in-out infinite .8s;}

  @keyframes tm-csapkod{0%,100%{transform:scaleY(1) rotate(0deg)}
    50%{transform:scaleY(.88) rotate(3deg)}}
  @keyframes tm-lebeg{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
  @keyframes tm-pulzal{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.75;transform:scale(.9)}}
  @keyframes tm-glow{0%,100%{opacity:.16;transform:scale(1)}50%{opacity:.3;transform:scale(1.12)}}
  @keyframes tm-pislog{0%,93%,100%{transform:scaleY(1)}96%{transform:scaleY(.1)}}
  @keyframes tm-csillamlik{0%,100%{opacity:1}50%{opacity:.45}}
  @keyframes tm-szall{0%{opacity:0;transform:translate(0,4px)}
    40%{opacity:1}100%{opacity:0;transform:translate(5px,-9px)}}

  /* A mozgásra érzékeny látogatóknál minden animáció áll. */
  @media (prefers-reduced-motion:reduce){
    .tm svg,.tm-ko,.tm-ko-fenyy,.tm-szemek,.tm-csillagok,.tm-szarny,
    .tm-z1,.tm-z2{animation:none!important;}
    .tm:hover svg{transform:none;}
  }
</style>
<script>
(() => {
  // A pupilla a kurzor felé néz. Csak ott fut, ahol van nyitott szem,
  // és kikapcsol, ha a látogató csökkentett mozgást kért.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const csoportok = document.querySelectorAll('.tm .tm-pupillak');
  if (!csoportok.length) return;

  const MAX = 1.8; // elmozdulás SVG egységben

  document.addEventListener('pointermove', esemeny => {
    for (const csoport of csoportok) {
      const doboz = csoport.ownerSVGElement.getBoundingClientRect();
      if (!doboz.width) continue;

      const tavX = (esemeny.clientX - (doboz.left + doboz.width / 2)) / (doboz.width / 2);
      const tavY = (esemeny.clientY - (doboz.top + doboz.height / 2)) / (doboz.height / 2);

      const x = Math.max(-1, Math.min(1, tavX)) * MAX;
      const y = Math.max(-1, Math.min(1, tavY)) * MAX;
      csoport.style.transform = `translate(${x.toFixed(2)}px, ${y.toFixed(2)}px)`;
    }
  }, { passive: true });
})();
</script>
    <?php
}
