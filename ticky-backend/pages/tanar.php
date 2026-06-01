<?php
// tanar.php — Tanár kereső (/tanar)
require __DIR__ . '/utils/_layout.php';
ticky_head('Tanár kereső', 'tanar', 'Tanár kereső');
?>
<div class="wrap">
  <div class="page finder">
    <div class="card fcard">
      <div class="top"><div class="br"><span class="d"></span>Ticky</div><div class="lbl">Tanár kereső</div></div>
      <div class="sel-row">
        <div class="hdr"><span class="k">Válassz tanárt</span><span class="pill idle" id="tanar-pill"><span class="pd"></span>–</span></div>
        <select class="csel" id="tanar-sel"><option value="">Betöltés…</option></select>
      </div>
      <div class="fblock" id="tanar-akt">
        <div class="ph"><span class="ic">👤</span><p>Válassz tanárt a legördülő menüből</p></div>
      </div>
      <div class="flist"><div class="ttl">Mai napirend</div><div id="tanar-list"><p style="font-size:13px;color:var(--faint)">Nincs kiválasztva tanár</p></div></div>
      <div class="foot"><span class="ido" data-clock>—:—</span><span class="btn" style="padding:7px 12px" id="tanar-refresh"><svg class="ic" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>Frissít</span></div>
    </div>
  </div>
</div>

<script>
(function(){
  const sel = TK.$('#tanar-sel'), pill = TK.$('#tanar-pill');
  let current = '';

  function setPill(state){
    const map = { tanit:['busy','TANÍT'], szabad:['free','SZABAD'], idle:['idle','–'] };
    const [c,t] = map[state] || map.idle;
    pill.className = 'pill ' + c;
    pill.innerHTML = '<span class="pd"></span>' + t;
  }

  function lessonNo(o){
    const a = o.ora_sorszam, b = o.ora_sorszam_ig;
    if (a && b && b !== a) return a + '–' + b;
    return (a != null ? a : '·');
  }

  function render(d){
    const orak = Array.isArray(d.orak) ? d.orak : [];
    document.body.className = ''; document.body.setAttribute('data-page','tanar');

    if (d.uzenet && !orak.length){
      setPill('idle');
      TK.$('#tanar-akt').innerHTML = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">🌙</span><div><p style="font-weight:600">'+TK.esc(d.szunet?'Szünet':'Nincs tanítás')+'</p><p style="font-size:12px;color:var(--faint);margin-top:2px">'+TK.esc(d.uzenet)+'</p></div></div>';
      TK.$('#tanar-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">'+TK.esc(d.uzenet)+'</p>';
      return;
    }

    const now = TK.nowMin();
    const akt = orak.find(o => now>=TK.toMin(o.kezdes) && now<=TK.toMin(o.vegzes));
    const kov = orak.find(o => now<TK.toMin(o.kezdes));
    setPill(akt ? 'tanit' : 'szabad');
    document.body.className = akt ? 't-foglalt' : 't-szabad';

    let h;
    if (akt){
      const k=TK.hm(akt.kezdes), v=TK.hm(akt.vegzes), p=TK.pct(k,v);
      h = '<div class="kv"><div class="k">Most itt van</div><div class="v" style="font-size:26px">'+TK.esc(akt.terem||'—')+'. terem</div></div>'
        + '<div class="kv2"><div class="kv" style="margin:0"><div class="k">Osztály</div><div class="v" style="font-size:18px">'+TK.esc(akt.osztaly||'—')+'</div></div>'
        + '<div class="kv" style="margin:0"><div class="k">Tantárgy</div><div class="v" style="font-size:18px">'+TK.esc(akt.tantargy||'—')+'</div></div></div>'
        + '<div class="prog" style="margin-top:14px"><div class="track"><i style="width:'+p+'%"></i></div>'
        + '<div class="lbl"><span class="mono">'+k+'</span><span class="mid">'+v+'-ig</span><span class="mono">'+v+'</span></div></div>';
    } else if (kov){
      h = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">☕</span><div><p style="font-weight:600">Jelenleg szabad</p>'
        + '<p style="font-size:12px;color:var(--faint);margin-top:2px">Következő: <b style="color:var(--ink)">'+TK.esc(kov.terem||'—')+'. terem</b> · '+TK.hm(kov.kezdes)+'–'+TK.hm(kov.vegzes)+'</p></div></div>';
    } else {
      h = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">✓</span><div><p style="font-weight:600;color:var(--green)">Szabad</p><p style="font-size:12px;color:var(--faint);margin-top:2px">Ma már nincs több óra</p></div></div>';
    }
    TK.$('#tanar-akt').innerHTML = h;

    if (!orak.length){ TK.$('#tanar-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">Ma nincs órája</p>'; return; }

    TK.$('#tanar-list').innerHTML = orak.map(o => {
      const k=TK.hm(o.kezdes), v=TK.hm(o.vegzes);
      const ak = now>=TK.toMin(o.kezdes) && now<=TK.toMin(o.vegzes);
      const past = now>TK.toMin(o.vegzes);
      if (o.is_csoport && Array.isArray(o.csoportok) && o.csoportok.length){
        const rows = o.csoportok.map((c,i) =>
          '<div class="grow"><div class="lft"><span class="gbadge">'+(i+1)+'. csoport</span><span class="who">'+TK.esc(c.osztaly||'')+(c.tantargy?' · '+TK.esc(c.tantargy):'')+'</span></div><span class="rm">'+TK.esc(c.terem||'—')+'. terem</span></div>'
        ).join('');
        return '<div class="orow '+(past?'past':'')+'" style="background:rgba(243,239,230,.04);border:1px solid var(--line);border-radius:10px"><span class="n">'+lessonNo(o)+'</span><div class="ci">'
          + '<div class="r1" style="margin-bottom:2px"><span class="a">'+TK.esc(o.tantargy||'Óra')+'</span><span class="gbadge" style="font-size:9px">csoportbontás</span></div>'
          + rows + '<div class="r2" style="margin-top:4px">'+k+' – '+v+'</div></div></div>';
      }
      return '<div class="orow '+(ak?'now':past?'past':'')+'"><span class="n">'+lessonNo(o)+'</span><div class="ci">'
        + '<div class="r1"><span class="a">'+TK.esc(o.terem||'—')+'. terem</span><span class="b">'+[o.osztaly,o.tantargy].filter(Boolean).map(TK.esc).join(' · ')+'</span></div>'
        + '<div class="r2">'+k+' – '+v+'</div></div>'+(ak?'<span class="dotn"></span>':'')+'</div>';
    }).join('');
  }

  async function load(kod){
    current = kod;
    if (!kod){
      setPill('idle');
      document.body.className=''; document.body.setAttribute('data-page','tanar');
      TK.$('#tanar-akt').innerHTML = '<div class="ph"><span class="ic">👤</span><p>Válassz tanárt a legördülő menüből</p></div>';
      TK.$('#tanar-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">Nincs kiválasztva tanár</p>';
      return;
    }
    setPill('idle');
    try{ render(await TK.api('/api/tanar/'+encodeURIComponent(kod)+'/orarend')); }
    catch(e){ TK.$('#tanar-akt').innerHTML = '<div class="ph"><span class="ic">⚠️</span><p>Nem sikerült betölteni az órarendet</p></div>'; }
  }

  sel.addEventListener('change', e => load(e.target.value));
  TK.$('#tanar-refresh').addEventListener('click', () => { const i=TK.$('#tanar-refresh .ic'); i&&i.classList.add('spin'); setTimeout(()=>i&&i.classList.remove('spin'),600); load(current); });

  (async function(){
    try{
      const data = await TK.api('/api/tanarok');
      const list = Array.isArray(data.tanarok) ? data.tanarok : [];
      sel.innerHTML = '<option value="">— Válassz tanárt —</option>' + list.map(t => {
        const kod = TK.esc(t.rovid_nev);
        const label = t.nev ? (kod + ' – ' + TK.esc(t.nev)) : kod;
        return '<option value="'+kod+'">'+label+'</option>';
      }).join('');
    }catch(e){
      sel.innerHTML = '<option value="">Nem sikerült betölteni a tanárokat</option>';
    }
  })();
})();
</script>
<?php ticky_foot(); ?>
