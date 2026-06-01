<?php
// osztaly.php — Osztály kereső (/osztaly)
require __DIR__ . '/utils/_layout.php';
ticky_head('Osztály kereső', 'osztaly', 'Osztály kereső');
?>
<div class="wrap">
  <div class="page finder">
    <div class="card fcard">
      <div class="top"><div class="br"><span class="d"></span>Ticky</div><div class="lbl">Osztály kereső</div></div>
      <div class="sel-row">
        <div class="hdr"><span class="k">Válassz osztályt</span><span class="pill idle" id="oszt-pill"><span class="pd"></span>–</span></div>
        <select class="csel" id="oszt-sel"><option value="">Betöltés…</option></select>
      </div>
      <div class="fblock" id="oszt-akt">
        <div class="ph"><span class="ic">🎒</span><p>Válassz osztályt a legördülő menüből</p></div>
      </div>
      <div class="flist"><div class="ttl">Mai napirend</div><div id="oszt-list"><p style="font-size:13px;color:var(--faint)">Nincs kiválasztva osztály</p></div></div>
      <div class="foot"><span class="ido" data-clock>—:—</span><span class="btn" style="padding:7px 12px" id="oszt-refresh"><svg class="ic" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>Frissít</span></div>
    </div>
  </div>
</div>

<script>
(function(){
  const sel = TK.$('#oszt-sel'), pill = TK.$('#oszt-pill');
  let current = '';

  function setPill(state){
    const map = { tanul:['busy','TANUL'], szabad:['free','SZABAD'], idle:['idle','–'] };
    const [c,t] = map[state] || map.idle;
    pill.className = 'pill ' + c; pill.innerHTML = '<span class="pd"></span>' + t;
  }
  function lessonNo(o){ const a=o.ora_sorszam,b=o.ora_sorszam_ig; if(a&&b&&b!==a) return a+'–'+b; return (a!=null?a:'·'); }
  function roomLink(terem){
    const t = String(terem||'').trim();
    if (!t || /[\/\s]/.test(t)) return TK.esc(terem||'—')+'. terem';
    return '<a href="/terem/'+encodeURIComponent(t)+'" style="color:var(--ink)">'+TK.esc(t)+'. terem <span style="color:var(--gold-2);font-size:.85em">→</span></a>';
  }

  function render(d){
    const orak = Array.isArray(d.orak) ? d.orak : [];
    document.body.className=''; document.body.setAttribute('data-page','osztaly');

    if (d.uzenet && !orak.length){
      setPill('idle');
      TK.$('#oszt-akt').innerHTML = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">🌙</span><div><p style="font-weight:600">'+TK.esc(d.szunet?'Szünet':'Nincs tanítás')+'</p><p style="font-size:12px;color:var(--faint);margin-top:2px">'+TK.esc(d.uzenet)+'</p></div></div>';
      TK.$('#oszt-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">'+TK.esc(d.uzenet)+'</p>';
      return;
    }

    const now = TK.nowMin();
    const akt = orak.find(o => now>=TK.toMin(o.kezdes) && now<=TK.toMin(o.vegzes));
    const kov = orak.find(o => now<TK.toMin(o.kezdes));
    setPill(akt ? 'tanul' : 'szabad');
    document.body.className = akt ? 't-foglalt' : 't-szabad';

    let h;
    if (akt){
      const k=TK.hm(akt.kezdes), v=TK.hm(akt.vegzes), p=TK.pct(k,v);
      const tanar = akt.tanar_nev || akt.tanar || '—';
      h = '<div class="kv"><div class="k">Most itt van</div><div class="v" style="font-size:26px">'+roomLink(akt.terem)+'</div></div>'
        + '<div class="kv2"><div class="kv" style="margin:0"><div class="k">Tanár</div><div class="v" style="font-size:17px">'+TK.esc(tanar)+'</div></div>'
        + '<div class="kv" style="margin:0"><div class="k">Tantárgy</div><div class="v" style="font-size:17px">'+TK.esc(akt.tantargy||'—')+'</div></div></div>'
        + '<div class="prog" style="margin-top:14px"><div class="track"><i style="width:'+p+'%"></i></div>'
        + '<div class="lbl"><span class="mono">'+k+'</span><span class="mid">még '+TK.left(v)+' perc</span><span class="mono">'+v+'</span></div></div>';
    } else if (kov){
      h = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">☕</span><div><p style="font-weight:600">Jelenleg szabad</p>'
        + '<p style="font-size:12px;color:var(--faint);margin-top:2px">Következő: <b style="color:var(--ink)">'+TK.esc(kov.terem||'—')+'. terem</b>'+(kov.tantargy?' · '+TK.esc(kov.tantargy):'')+' · '+TK.hm(kov.kezdes)+'–'+TK.hm(kov.vegzes)+'</p></div></div>';
    } else {
      h = '<div style="display:flex;align-items:center;gap:13px"><span style="font-size:26px">✓</span><div><p style="font-weight:600;color:var(--green)">Szabad</p><p style="font-size:12px;color:var(--faint);margin-top:2px">Ma már nincs több óra</p></div></div>';
    }
    TK.$('#oszt-akt').innerHTML = h;

    if (!orak.length){ TK.$('#oszt-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">Ma nincs órája</p>'; return; }

    TK.$('#oszt-list').innerHTML = orak.map(o => {
      const k=TK.hm(o.kezdes), v=TK.hm(o.vegzes);
      const ak = now>=TK.toMin(o.kezdes) && now<=TK.toMin(o.vegzes);
      const past = now>TK.toMin(o.vegzes);
      if (o.is_csoport && Array.isArray(o.csoportok) && o.csoportok.length){
        const rows = o.csoportok.map((c,i) =>
          '<div class="grow"><div class="lft"><span class="gbadge">'+(i+1)+'. csoport</span><span class="who">'+[c.tantargy,(c.tanar_nev||c.tanar)].filter(Boolean).map(TK.esc).join(' · ')+'</span></div><span class="rm">'+TK.esc(c.terem||'—')+'. terem</span></div>'
        ).join('');
        return '<div class="orow '+(ak?'now':past?'past':'')+'" style="background:rgba(243,239,230,.04);border:1px solid var(--line);border-radius:10px"><span class="n">'+lessonNo(o)+'</span><div class="ci">'
          + '<div class="r1" style="margin-bottom:2px"><span class="a">'+TK.esc(o.tantargy||'Óra')+'</span><span class="gbadge" style="font-size:9px">csoportbontás</span></div>'
          + rows + '<div class="r2" style="margin-top:4px">'+k+' – '+v+'</div></div>'+(ak?'<span class="dotn"></span>':'')+'</div>';
      }
      const tanar = o.tanar_nev || o.tanar || '';
      return '<div class="orow '+(ak?'now':past?'past':'')+'"><span class="n">'+lessonNo(o)+'</span><div class="ci">'
        + '<div class="r1"><span class="a">'+TK.esc(o.terem||'—')+'. terem</span><span class="b">'+[o.tantargy,tanar].filter(Boolean).map(TK.esc).join(' · ')+'</span></div>'
        + '<div class="r2">'+k+' – '+v+'</div></div>'+(ak?'<span class="dotn"></span>':'')+'</div>';
    }).join('');
  }

  async function load(kod){
    current = kod;
    if (!kod){
      setPill('idle'); document.body.className=''; document.body.setAttribute('data-page','osztaly');
      TK.$('#oszt-akt').innerHTML = '<div class="ph"><span class="ic">🎒</span><p>Válassz osztályt a legördülő menüből</p></div>';
      TK.$('#oszt-list').innerHTML = '<p style="font-size:13px;color:var(--faint)">Nincs kiválasztva osztály</p>';
      return;
    }
    setPill('idle');
    try{ render(await TK.api('/api/osztaly/'+encodeURIComponent(kod)+'/orarend')); }
    catch(e){ TK.$('#oszt-akt').innerHTML = '<div class="ph"><span class="ic">⚠️</span><p>Nem sikerült betölteni az órarendet</p></div>'; }
  }

  sel.addEventListener('change', e => load(e.target.value));
  TK.$('#oszt-refresh').addEventListener('click', () => { const i=TK.$('#oszt-refresh .ic'); i&&i.classList.add('spin'); setTimeout(()=>i&&i.classList.remove('spin'),600); load(current); });

  (async function(){
    try{
      const data = await TK.api('/api/osztalyok');
      const list = Array.isArray(data.osztalyok) ? data.osztalyok : [];
      const groups = {};
      list.forEach(code => {
        const m = String(code).match(/^(\d+)\./) || String(code).match(/^(\d+)/);
        const key = m ? (m[1] + '. évfolyam') : 'Egyéb';
        (groups[key] = groups[key] || []).push(code);
      });
      const orderKeys = Object.keys(groups).sort((a,b) => {
        const na = parseInt(a), nb = parseInt(b);
        if (isNaN(na) && isNaN(nb)) return a.localeCompare(b,'hu');
        if (isNaN(na)) return 1; if (isNaN(nb)) return -1;
        return na - nb;
      });
      sel.innerHTML = '<option value="">— Válassz osztályt —</option>' + orderKeys.map(key =>
        '<optgroup label="'+TK.esc(key)+'">' + groups[key].map(c => '<option value="'+TK.esc(c)+'">'+TK.esc(c)+'</option>').join('') + '</optgroup>'
      ).join('');
    }catch(e){
      sel.innerHTML = '<option value="">Nem sikerült betölteni az osztályokat</option>';
    }
  })();
})();
</script>
<?php ticky_foot(); ?>
