<?php
// kijelzo.php — Folyosói kijelző (/kijelzo) — teljes képernyős élő tábla
require __DIR__ . '/utils/_layout.php';
ticky_head('Folyosói kijelző', 'kijelzo', '', ['chrome' => false, 'body_class' => 'board-page']);
?>
<div class="board" id="board">
  <div class="scan"></div>
  <div class="bd-top">
    <div class="bd-brand"><span class="b-dot"></span>Ticky<span style="color:var(--ghost);font-weight:400;font-size:16px">·</span><span style="color:var(--muted);font-size:15px;font-weight:400;font-family:'DM Sans',sans-serif">Folyosói kijelző</span></div>
    <div class="bd-center"><div class="dt" id="bd-date">—</div><div class="np" id="bd-day">—</div></div>
    <div class="bd-right">
      <div class="bd-clock"><span id="bd-hm">––:––</span><span class="sec">:<span id="bd-s">00</span></span></div>
      <div class="bd-fg">
        <span class="bd-fb on" data-bf="mind">Összes</span>
        <span class="bd-fb" data-bf="foglalt">Foglalt</span>
        <span class="bd-fb" data-bf="szabad">Szabad</span>
      </div>
      <a class="bd-exit" href="/">⤺ Kilépés</a>
    </div>
  </div>
  <div class="bd-status">
    <div style="display:flex;align-items:center;gap:15px">
      <div class="bd-stat"><span class="d" style="background:#fff;opacity:.3"></span>Összes: <span class="v" id="bd-c-osszes">—</span></div>
      <div class="bd-div"></div>
      <div class="bd-stat" style="color:rgba(255,107,130,.7)"><span class="d" style="background:var(--red);box-shadow:0 0 6px var(--red)"></span>Foglalt: <span class="v" id="bd-c-foglalt">—</span></div>
      <div class="bd-div"></div>
      <div class="bd-stat" style="color:rgba(52,217,164,.75)"><span class="d" style="background:var(--green);box-shadow:0 0 6px var(--green)"></span>Szabad: <span class="v" id="bd-c-szabad">—</span></div>
    </div>
    <div class="bd-upd">Frissítve: <span id="bd-upd">—</span></div>
  </div>
  <div class="bd-main">
    <div class="bgrid" id="bgrid"></div>
    <div class="refresh-bar"><i id="bd-prog"></i></div>
  </div>
</div>

<script>
(function(){
  const HO = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
  const NAP = ['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'];
  const REFRESH = 30000;
  let ALL = [], bf = 'mind';

  function counts(){ const all=ALL.length, busy=ALL.filter(r=>r.allapot==='foglalt').length; return {all,busy,free:all-busy}; }

  function render(){
    const c = counts();
    TK.$('#bd-c-osszes').textContent = c.all;
    TK.$('#bd-c-foglalt').textContent = c.busy;
    TK.$('#bd-c-szabad').textContent = c.free;
    let rooms = ALL.slice();
    if (bf === 'foglalt') rooms = rooms.filter(r => r.allapot === 'foglalt');
    else if (bf === 'szabad') rooms = rooms.filter(r => r.allapot !== 'foglalt');
    const grid = TK.$('#bgrid');
    if (!rooms.length){ grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><span>🔍</span>Nincs találat</div>'; return; }
    grid.innerHTML = rooms.map((r,i) => {
      const free = r.allapot !== 'foglalt';
      let body;
      if (free){ body = '<div class="bc-body"><div class="bc-free">Szabad</div></div>'; }
      else {
        const a = r.aktualis || {};
        const k=TK.hm(a.kezdes), v=TK.hm(a.vegzes), p=(k&&v)?TK.pct(k,v):0, rem=(k&&v)?TK.left(v):0;
        const m = [a.tantargy, a.tanar].filter(Boolean).map(TK.esc).join(' · ');
        body = '<div class="bc-body"><div class="bc-t">'+TK.esc(a.osztaly||'Foglalt')+'</div><div class="bc-m">'+m+'</div>'
             + '<div class="bc-bar"><i style="width:'+p+'%"></i></div>'
             + '<div class="bc-time"><span>'+k+'</span><span class="rm">'+(rem>0?rem+'p':'vége')+'</span><span>'+v+'</span></div></div>';
      }
      return '<div class="bcard '+(free?'free':'busy')+'" style="animation-delay:'+(i*12)+'ms">'
           + '<div class="bc-top"><div class="bc-num">'+TK.esc(r.terem_szam)+'</div>'
           + '<span class="pill '+(free?'free':'busy')+'"><span class="pd"></span>'+(free?'Szabad':'Foglalt')+'</span></div>'+body+'</div>';
    }).join('');
  }

  function clock(){
    let hm, ss;
    if (window.TickyTime){ const p = TickyTime.nowParts(); hm = String(p.hour).padStart(2,'0')+':'+String(p.minute).padStart(2,'0'); ss = String(p.second).padStart(2,'0'); TK.$('#bd-day').textContent = NAP[p.weekday]||''; TK.$('#bd-date').textContent = p.year+'. '+HO[p.month-1]+' '+p.day+'.'; }
    else { const d=new Date(); hm=String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); ss=String(d.getSeconds()).padStart(2,'0'); TK.$('#bd-day').textContent=NAP[d.getDay()]; TK.$('#bd-date').textContent=d.getFullYear()+'. '+HO[d.getMonth()]+' '+d.getDate()+'.'; }
    TK.$('#bd-hm').textContent = hm; TK.$('#bd-s').textContent = ss;
  }

  function progressBar(){
    const bar = TK.$('#bd-prog');
    bar.style.transition='none'; bar.style.width='0%';
    requestAnimationFrame(()=>{ bar.style.transition='width '+(REFRESH/1000)+'s linear'; bar.style.width='100%'; });
  }

  async function load(){
    try{
      const data = await TK.api('/api/termek?allapot=1');
      ALL = Array.isArray(data.termek) ? data.termek : [];
      render();
      const u = window.TickyTime ? TickyTime.formatHMS() : new Date().toLocaleTimeString('hu-HU');
      TK.$('#bd-upd').textContent = u;
    }catch(e){
      TK.$('#bgrid').innerHTML = '<div class="empty" style="grid-column:1/-1"><span>⚠️</span>Nem sikerült frissíteni</div>';
    }
    progressBar();
  }

  TK.$$('[data-bf]').forEach(b => b.addEventListener('click', () => {
    bf = b.dataset.bf;
    TK.$$('[data-bf]').forEach(x => { const on=x.dataset.bf===bf; x.className='bd-fb'+(on?(bf==='szabad'?' on-g':bf==='foglalt'?' on-r':' on'):''); });
    render();
  }));

  clock(); setInterval(clock, 1000);
  load(); setInterval(load, REFRESH);
})();
</script>
<?php ticky_foot(); ?>
