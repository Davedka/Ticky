<?php
// terem.php — Egy terem részletei (/terem/{szam})
require __DIR__ . '/utils/_layout.php';

$szam = strtoupper(trim((string) ($_GET['szam'] ?? '')));
if ($szam === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (function_exists('match_route') && is_string($uri)) {
        $p = match_route('/terem/{szam}', $uri);
        if ($p !== false && !empty($p['szam'])) {
            $szam = strtoupper(trim(urldecode((string) $p['szam'])));
        }
    }
}

ticky_head($szam !== '' ? ($szam . '. terem') : 'Terem', 'termek', 'Terem');
?>
<div class="wrap">
  <div class="page">
    <div class="detail" id="detail">
      <div class="card stcard" id="st-card">
        <div class="head"><div><div class="rc-lab">Terem</div><div class="num"><?= htmlspecialchars($szam ?: '—') ?></div></div>
          <span class="pill idle"><span class="pd"></span>Betöltés</span></div>
        <div class="body"><div class="skel" style="height:120px"></div></div>
      </div>
      <div class="card ttcard" id="tt-card">
        <div class="tt-h"><span class="t">Heti órarend</span></div>
        <div class="tt-wrap"><div class="tt" id="tt"></div></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const SZAM = <?= json_encode($szam, JSON_UNESCAPED_UNICODE) ?>;
  const ORA = [['07:30','08:10'],['08:20','09:05'],['09:15','10:00'],['10:15','11:00'],['11:10','11:55'],['12:05','12:50'],['12:55','13:35'],['13:40','14:20']];
  const NAP = {1:'H',2:'K',3:'Sze',4:'Cs',5:'P'};

  /* ---- státusz kártya ---- */
  function renderStatus(d){
    const free = (d.allapot !== 'foglalt');
    document.body.className = free ? 't-szabad' : 't-foglalt';
    document.body.setAttribute('data-page','termek');

    let main, pillCls, pillTxt;
    if (d.uzenet && !d.aktualis){
      pillCls='idle'; pillTxt=(d.szunet?'Szünet':'Zárva');
      main = '<div class="free-block"><span class="ic">🌙</span><div class="ttl" style="color:var(--ink)">'+TK.esc(d.szunet||'Nincs tanítás')+'</div><div class="sub">'+TK.esc(d.uzenet)+'</div></div>';
    } else if (!free && d.aktualis){
      pillCls='busy'; pillTxt='Foglalt';
      const a = d.aktualis;
      const k = TK.hm(a.kezdes), v = TK.hm(a.vegzes);
      const tanar = TK.esc(a.tanar_nev || a.tanar || '—');
      const rem = (a.perc_maradt != null) ? a.perc_maradt : TK.left(v);
      const p = (k&&v) ? TK.pct(k,v) : 0;
      main = '<div class="kv"><div class="k">Tanár</div><div class="v">'+tanar+'</div></div>'
           + '<div class="kv2"><div class="kv" style="margin:0"><div class="k">Osztály</div><div class="v">'+TK.esc(a.osztaly||'—')+'</div></div>'
           + '<div class="kv" style="margin:0"><div class="k">Tantárgy</div><div class="v" style="font-size:18px">'+TK.esc(a.tantargy||'—')+'</div></div></div>'
           + '<div class="prog" style="margin-top:18px"><div class="track"><i style="width:'+p+'%"></i></div>'
           + '<div class="lbl"><span class="mono">'+k+'</span><span class="mid">még '+rem+' perc</span><span class="mono">'+v+'</span></div></div>';
    } else {
      pillCls='free'; pillTxt='Szabad';
      main = '<div class="free-block"><span class="ic">✓</span><div class="ttl">Szabad terem</div><div class="sub">Nincs aktív foglalás</div></div>';
    }

    let next = '';
    if (d.kovetkezo){
      const n = d.kovetkezo;
      const who = [n.tanar_nev || n.tanar, n.osztaly, n.tantargy].filter(Boolean).map(TK.esc).join(' · ');
      next = '<div class="next"><div class="k">Következő óra</div><div class="row"><span class="who">'+(who||'—')+'</span>'
           + '<span class="tm">'+TK.hm(n.kezdes)+'–'+TK.hm(n.vegzes)+'</span></div></div>';
    }

    const emelet = (d.emelet!=null && d.emelet!=='') ? ('<div class="rc-lab" style="margin-top:4px">'+TK.esc(d.emelet)+'. emelet</div>') : '';
    TK.$('#st-card').innerHTML =
        '<div class="head"><div><div class="rc-lab">Terem</div><div class="num">'+TK.esc(d.terem||SZAM)+'</div>'+emelet+'</div>'
      + '<span class="pill '+pillCls+'" style="padding:7px 13px;font-size:11px"><span class="pd"></span>'+pillTxt+'</span></div>'
      + '<div class="body">'+main+next+'</div>'
      + '<div class="foot"><a href="/termek">← Összes terem</a><span class="br">Ticky</span></div>';
  }

  /* ---- heti órarend ---- */
  function normalizeWeek(raw){
    const week = {1:[],2:[],3:[],4:[],5:[]};
    const push = (nap, orak) => {
      const n = Number(nap);
      if (!week[n] || !Array.isArray(orak)) return;
      orak.forEach(o => {
        const k = TK.hm(o.kezdes || o.kezdet || o.start), v = TK.hm(o.vegzes || o.vege || o.end);
        if (!k || !v) return;
        week[n].push({
          k, v,
          a: o.tanar_nev || o.tanar || o.teacher || '',
          b: [o.osztaly || o.class, o.tantargy || o.subject].filter(Boolean).join(' · ')
        });
      });
    };
    if (raw && Array.isArray(raw.het)) raw.het.forEach(d => push(d.nap, d.orak));
    else if (raw && raw.napok && typeof raw.napok === 'object') Object.keys(raw.napok).forEach(n => push(n, raw.napok[n]));
    else if (raw && Array.isArray(raw.orak) && raw.nap != null) push(raw.nap, raw.orak);
    const any = Object.values(week).some(a => a.length);
    return any ? week : null;
  }

  function renderWeek(week){
    const START=450, END=865, PPM=1.5, H=(END-START)*PPM;
    const today = TK.schoolDay();
    const now = TK.nowMin();
    let html = '<div class="tt-col-h"></div>';
    for (let n=1;n<=5;n++) html += '<div class="tt-col-h'+(n===today?' today':'')+'">'+NAP[n]+'</div>';

    let tc = '<div class="tt-time" style="height:'+H+'px">';
    for (let m=START;m<=END;m+=30) tc += '<span class="tlab" style="top:'+((m-START)*PPM)+'px">'+String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0')+'</span>';
    html += tc + '</div>';

    for (let n=1;n<=5;n++){
      const isToday = (n===today);
      let col = '<div class="tt-day'+(isToday?' today':'')+'" style="height:'+H+'px">';
      for (let m=START;m<=END;m+=30) col += '<div class="hl'+(m%60===0?' h':'')+'" style="top:'+((m-START)*PPM)+'px"></div>';
      if (isToday && now>=START && now<=END) col += '<div class="nowline" style="top:'+((now-START)*PPM)+'px"><div class="nd"></div></div>';
      (week[n]||[]).forEach(o => {
        const top=(TK.toMin(o.k)-START)*PPM, h=Math.max(22,(TK.toMin(o.v)-TK.toMin(o.k))*PPM);
        const ak = isToday && now>=TK.toMin(o.k) && now<=TK.toMin(o.v);
        const past = isToday && now>TK.toMin(o.v);
        col += '<div class="blk '+(ak?'now':past?'past':'')+'" style="top:'+top+'px;height:'+h+'px" title="'+TK.esc(o.a)+' · '+TK.esc(o.b)+' · '+o.k+'–'+o.v+'">'
             + '<div class="a">'+TK.esc(o.a||'Óra')+'</div>'+(h>30?'<div class="b">'+TK.esc(o.b)+'</div>':'')+'</div>';
      });
      html += col + '</div>';
    }
    TK.$('#tt').innerHTML = html;
  }

  function hideWeek(){
    const c = TK.$('#tt-card'); if (c) c.remove();
    const d = TK.$('#detail'); if (d) d.classList.add('solo');
  }

  (async function(){
    if (!SZAM){ hideWeek(); TK.$('#st-card').querySelector('.body').innerHTML='<div class="free-block"><span class="ic">⚠️</span><div class="ttl" style="color:var(--ink)">Hiányzó terem</div></div>'; return; }
    try{
      const d = await TK.api('/api/terem/'+encodeURIComponent(SZAM));
      renderStatus(d);
    }catch(e){
      TK.$('#st-card').querySelector('.body').innerHTML='<div class="free-block"><span class="ic">⚠️</span><div class="ttl" style="color:var(--ink)">Nem elérhető</div><div class="sub">Nem sikerült lekérni a terem állapotát.</div></div>';
    }
    try{
      const raw = await TK.api('/api/napirend/'+encodeURIComponent(SZAM)+'?nap=heten');
      const week = normalizeWeek(raw);
      if (week) renderWeek(week); else hideWeek();
    }catch(e){ hideWeek(); }
  })();

  setInterval(async () => {
    try{ renderStatus(await TK.api('/api/terem/'+encodeURIComponent(SZAM))); }catch(e){}
  }, 60000);
})();
</script>
<?php ticky_foot(); ?>
