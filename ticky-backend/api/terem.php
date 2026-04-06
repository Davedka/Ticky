<?php
// api/terem.php – egyedi terem, szünet-érzékeny

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';

handle_cors();

$szam = strtoupper(trim((string)($_GET['szam'] ?? '')));
if ($szam === '') {
    $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $params = match_route('/api/terem/{szam}', $uri);
    if ($params !== false) $szam = strtoupper(trim($params['szam']));
}
if ($szam === '') { json_error('Hiányzó terem szám', 400); }

$nap = mai_nap();
$ido = aktualis_ido();
$sz  = aktiv_szunet();

$termek = sb_get('termek', ['terem_szam'=>'eq.'.$szam,'select'=>'id,terem_szam,emelet']);
if (empty($termek)) { json_error('Terem nem található: '.$szam, 404); }
$terem = $termek[0];

// Szünet → szabad, nincs óra
if ($sz !== null) {
    json_response([
        'terem'     => $szam,
        'emelet'    => $terem['emelet'],
        'allapot'   => 'szabad',
        'szunet'    => $sz['nev'],
        'uzenet'    => '🌙 '.$sz['nev'].' – nincs tanítás',
        'aktualis'  => null,
        'kovetkezo' => null,
    ]);
}

if ($nap === 0) {
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'allapot'=>'szabad','uzenet'=>'Hétvége','aktualis'=>null,'kovetkezo'=>null]);
}

$orak = sb_get('orarendek', [
    'terem_id'  => 'eq.'.$terem['id'],
    'het_napja' => 'eq.'.$nap,
    'aktiv'     => 'eq.true',
    'select'    => 'id,osztaly,tantargy,kezdes,vegzes,ora_sorszam,tanar_id',
    'order'     => 'kezdes.asc',
]);

$tanar_nevek = [];
if (!empty($orak)) {
    $ids = array_unique(array_filter(array_column($orak,'tanar_id')));
    if (!empty($ids)) {
        foreach (sb_get('tanarok',['id'=>'in.('.implode(',',$ids).')','select'=>'id,rovid_nev,nev']) as $t) {
            $tanar_nevek[$t['id']] = $t;
        }
    }
}

foreach ($orak as &$o) {
    $t=$tanar_nevek[$o['tanar_id']]??null;
    $o['tanar']    =$t['rovid_nev']??'?';
    $o['tanar_nev']=$t['nev']??null;
    unset($o['tanar_id']);
}
unset($o);

$aktualis=$kovetkezo=null;
foreach ($orak as $ora) {
    $k=substr($ora['kezdes'],0,5); $v=substr($ora['vegzes'],0,5);
    if ($ido>=$k&&$ido<=$v) $aktualis=$ora;
    elseif ($ido<$k&&$kovetkezo===null) $kovetkezo=$ora;
}

if ($aktualis!==null) {
    $k=substr($aktualis['kezdes'],0,5); $v=substr($aktualis['vegzes'],0,5);
    $pm=(strtotime($v)-strtotime($ido))/60;
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'allapot'=>'foglalt',
        'aktualis'=>['ora_sorszam'=>$aktualis['ora_sorszam'],'tanar'=>$aktualis['tanar'],'tanar_nev'=>$aktualis['tanar_nev'],'osztaly'=>$aktualis['osztaly'],'tantargy'=>$aktualis['tantargy'],'kezdes'=>$k,'vegzes'=>$v,'perc_maradt'=>max(0,(int)$pm)],
        'kovetkezo'=>$kovetkezo?['ora_sorszam'=>$kovetkezo['ora_sorszam'],'tanar'=>$kovetkezo['tanar'],'osztaly'=>$kovetkezo['osztaly'],'tantargy'=>$kovetkezo['tantargy'],'kezdes'=>substr($kovetkezo['kezdes'],0,5),'vegzes'=>substr($kovetkezo['vegzes'],0,5)]:null,
    ]);
} else {
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'allapot'=>'szabad','aktualis'=>null,
        'kovetkezo'=>$kovetkezo?['ora_sorszam'=>$kovetkezo['ora_sorszam'],'tanar'=>$kovetkezo['tanar'],'osztaly'=>$kovetkezo['osztaly'],'tantargy'=>$kovetkezo['tantargy'],'kezdes'=>substr($kovetkezo['kezdes'],0,5),'vegzes'=>substr($kovetkezo['vegzes'],0,5)]:null,
    ]);
}
