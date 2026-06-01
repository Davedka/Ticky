<?php
// termek.php — Összes terem (/termek)
require __DIR__ . '/utils/_layout.php';
ticky_head('Összes terem', 'termek', 'Összes terem');
?>
<div class="wrap">
  <div class="page">
    <div class="shead">
      <div>
        <div class="ix">01 — Élő nézet</div>
        <h2>Összes terem</h2>
        <div class="sub" id="t-sub">Kattints egy teremre a részletekért</div>
      </div>
    </div>

    <div class="fbar">
      <div class="fgrp">
        <div class="fbtn on" data-filter="mind">Összes <span class="c" id="c-mind">–</span></div>
        <div class="fbtn" data-filter="szabad"><span class="d" style="background:var(--green)"></span>Szabad <span class="c" id="c-szabad">–</span></div>
        <div class="fbtn" data-filter="foglalt"><span class="d" style="background:var(--red)"></span>Foglalt <span class="c" id="c-foglalt">–</span></div>
      </div>
      <div class="search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="rsearch" placeholder="Terem keresése…" autocomplete="off">
      </div>
    </div>

    <div class="rgrid" id="rgrid">
      <?php for ($i = 0; $i < 10; $i++): ?><div class="rcard skel" style="border:none"></div><?php endfor; ?>
    </div>
  </div>
</div>

<script>
(function(){
  let ALL = [], curFilter = 'mind', curSearch = '';
  const grid = TK.$('#rgrid');

  function counts(){
    const all = ALL.length;
    const busy = ALL.filter(r => r.allapot === 'foglalt').length;
    return { all, busy, free: all - busy };
  }

  function render(){
    const c = counts();
    TK.$('#c-mind').textContent = c.all;
    TK.$('#c-szabad').textContent = c.free;
    TK.$('#c-foglalt').textContent = c.busy;

    let rooms = ALL.slice();
    if (curFilter === 'szabad') rooms = rooms.filter(r => r.allapot !== 'foglalt');
    else if (curFilter === 'foglalt') rooms = rooms.filter(r => r.allapot === 'foglalt');
    if (curSearch) rooms = rooms.filter(r => String(r.terem_szam).toLowerCase().includes(curSearch));

    if (!rooms.length){
      grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><span>🔍</span>Nincs találat</div>';
      return;
    }

    grid.innerHTML = rooms.map((r, i) => {
      const free = r.allapot !== 'foglalt';
      const szam = TK.esc(r.terem_szam);
      let body;
      if (free){
        body = '<div class="rc-body"><div class="rc-free">Szabad · nincs óra</div></div>';
      } else {
        const a = r.aktualis || {};
        const k = TK.hm(a.kezdes), v = TK.hm(a.vegzes);
        const who = [a.tanar, a.osztaly].filter(Boolean).map(TK.esc).join(' · ');
        const sub = [a.tantargy ? TK.esc(a.tantargy) : '', (k&&v)? (k+'–'+v):''].filter(Boolean).join(' · ');
        const p = (k && v) ? TK.pct(k, v) : 0;
        body = '<div class="rc-body"><div class="rc-t">'+(who||'Foglalt')+'</div><div class="rc-m">'+sub+'</div>'
             + '<div class="rc-bar"><i style="width:'+p+'%"></i></div></div>';
      }
      return '<a class="rcard '+(free?'free':'busy')+'" style="animation-delay:'+(i*16)+'ms" href="/terem/'+encodeURIComponent(r.terem_szam)+'">'
           + '<div class="edge"></div>'
           + '<div class="rc-top"><div><div class="rc-lab">Terem</div><div class="rc-num">'+szam+'</div></div>'
           + '<span class="pill '+(free?'free':'busy')+'"><span class="pd"></span>'+(free?'Szabad':'Foglalt')+'</span></div>'
           + body + '</a>';
    }).join('');
  }

  document.addEventListener('click', e => {
    const fb = e.target.closest('[data-filter]');
    if (!fb) return;
    curFilter = fb.dataset.filter;
    TK.$$('[data-filter]').forEach(b => {
      const on = b.dataset.filter === curFilter;
      b.className = 'fbtn' + (on ? (curFilter==='szabad'?' on-g':curFilter==='foglalt'?' on-r':' on') : '');
    });
    render();
  });
  TK.$('#rsearch').addEventListener('input', e => { curSearch = e.target.value.toLowerCase().trim(); render(); });

  async function load(){
    try{
      const data = await TK.api('/api/termek?allapot=1');
      ALL = Array.isArray(data.termek) ? data.termek : [];
      if (data && Number(data.nap) === 0) TK.$('#t-sub').textContent = 'Hétvége — minden terem szabad';
      render();
    }catch(e){
      grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><span>⚠️</span>Nem sikerült betölteni a termeket</div>';
    }
  }
  load();
  setInterval(load, 60000);
})();
</script>
<?php ticky_foot(); ?>
