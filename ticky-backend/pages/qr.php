<?php
// qr.php — QR kód generátor (/qr)
require __DIR__ . '/utils/_layout.php';
ticky_head('QR kódok', 'qr', 'QR Generátor', ['qr' => true]);
?>
<div class="wrap">
  <div class="page">
    <div class="qrtop">
      <div>
        <div class="ix">04 — Eszközök</div>
        <h2 class="serif" style="font-size:clamp(28px,4vw,40px);font-weight:700;letter-spacing:-.5px">QR Kódok</h2>
        <div class="sub" style="font-size:13px;color:var(--muted);margin-top:4px">Kattints egy teremre a kijelöléshez, aztán nyomtasd ki</div>
      </div>
      <div class="acts">
        <span class="btn" id="qr-all">Összes kijelölése</span>
        <span class="btn" id="qr-clear" style="display:none">Kijelölés törlése</span>
        <span class="btn btn-gold" id="qr-print">🖨️ <span id="qr-plabel">Összes nyomtatása</span></span>
      </div>
    </div>
    <div class="qsel-info" id="qr-info" style="display:none"><span class="mono" id="qr-cnt">0</span> terem kijelölve</div>
    <div class="qgrid" id="qgrid">
      <?php for ($i=0;$i<8;$i++): ?><div class="qcard skel" style="border:none;min-height:230px"></div><?php endfor; ?>
    </div>
  </div>
</div>

<script>
(function(){
  const BASE = window.location.origin;
  const grid = TK.$('#qgrid');
  const sel = new Set();
  let ROOMS = [];

  function ui(){
    const n = sel.size, total = ROOMS.length;
    TK.$('#qr-info').style.display = n ? 'block' : 'none';
    TK.$('#qr-cnt').textContent = n;
    TK.$('#qr-plabel').textContent = n ? (n + ' terem nyomtatása') : 'Összes nyomtatása';
    TK.$('#qr-clear').style.display = n ? 'inline-flex' : 'none';
    TK.$('#qr-all').textContent = (n && n === total) ? 'Összes kijelölve ✓' : 'Összes kijelölése';
  }

  function build(){
    grid.innerHTML = ROOMS.map((s,i) =>
      '<div class="qcard" style="animation-delay:'+(i*14)+'ms" data-qr="'+TK.esc(s)+'">'
      + '<div class="chk"><svg width="12" height="12" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div>'
      + '<div style="text-align:center"><div class="lab">Terem</div><div class="qn">'+TK.esc(s)+'</div></div>'
      + '<div class="qwrap" id="qw-'+i+'"></div>'
      + '<div class="url">'+TK.esc(BASE+'/terem/'+s)+'</div><div class="qbr">Ticky</div></div>'
    ).join('');
    ROOMS.forEach((s,i) => {
      try{
        new QRCode(document.getElementById('qw-'+i), {
          text: BASE + '/terem/' + s,
          width: 118, height: 118,
          colorDark: '#060c16', colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      }catch(e){}
    });
    ui();
  }

  grid.addEventListener('click', e => {
    const card = e.target.closest('[data-qr]'); if (!card) return;
    const s = card.dataset.qr;
    if (sel.has(s)){ sel.delete(s); card.classList.remove('sel'); }
    else { sel.add(s); card.classList.add('sel'); }
    ui();
  });
  TK.$('#qr-all').addEventListener('click', () => { ROOMS.forEach(s => { sel.add(s); const c=grid.querySelector('[data-qr="'+CSS.escape(s)+'"]'); c&&c.classList.add('sel'); }); ui(); });
  TK.$('#qr-clear').addEventListener('click', () => { sel.clear(); TK.$$('[data-qr]',grid).forEach(c=>c.classList.remove('sel')); ui(); });
  TK.$('#qr-print').addEventListener('click', () => {
    const set = sel.size ? sel : new Set(ROOMS);
    ROOMS.forEach(s => { const c=grid.querySelector('[data-qr="'+CSS.escape(s)+'"]'); if(c){ set.has(s)?c.classList.add('print-me'):c.classList.remove('print-me'); } });
    window.print();
    setTimeout(() => TK.$$('[data-qr]',grid).forEach(c=>c.classList.remove('print-me')), 400);
  });

  (async function(){
    try{
      const data = await TK.api('/api/termek');
      ROOMS = (Array.isArray(data.termek) ? data.termek : []).map(r => String(r.terem_szam));
      if (!ROOMS.length){ grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><span>🔍</span>Nincs terem</div>'; return; }
      build();
    }catch(e){
      grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><span>⚠️</span>Nem sikerült betölteni a termeket</div>';
    }
  })();
})();
</script>
<?php ticky_foot(); ?>
