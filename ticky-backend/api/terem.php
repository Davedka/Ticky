<?php
// api/terem.php – egyedi terem, szünet-érzékeny

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';
require_once __DIR__ . '/../utils/tanarok_source.php';

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
$terem = $termek[0] ?? null;
$terem_id = $terem['id'] ?? null;
$emelet = $terem['emelet'] ?? null;

// Szünet → szabad, nincs óra
if ($sz !== null) {
    json_response([
        'terem'     => $szam,
        'emelet'    => $emelet,
        'allapot'   => 'szabad',
        'szunet'    => $sz['nev'],
        'uzenet'    => '🌙 '.$sz['nev'].' – nincs tanítás',
        'aktualis'  => null,
        'kovetkezo' => null,
    ]);
}

if ($nap === 0) {
    json_response(['terem'=>$szam,'emelet'=>$emelet,'allapot'=>'szabad','uzenet'=>'Hétvége','aktualis'=>null,'kovetkezo'=>null]);
}

$orak = [];
if ($terem_id !== null) {
    $orak = sb_get('orarendek', [
        'terem_id'  => 'eq.'.$terem_id,
        'het_napja' => 'eq.'.$nap,
        'aktiv'     => 'eq.true',
        'select'    => 'id,osztaly,tantargy,kezdes,vegzes,ora_sorszam,tanar_id',
        'order'     => 'kezdes.asc',
    ]);
}

$tanar_nevek = [];
if (!empty($orak)) {
    $source_teacher_names = function_exists('ticky_source_teacher_names') ? ticky_source_teacher_names() : [];
    $ids = array_unique(array_filter(array_column($orak,'tanar_id')));
    if (!empty($ids)) {
        foreach (sb_get('tanarok',['id'=>'in.('.implode(',',$ids).')','select'=>'id,rovid_nev,nev']) as $t) {
            $rovid = (string) ($t['rovid_nev'] ?? '?');
            $tanar_nevek[$t['id']] = [
                'rovid_nev' => $rovid,
                'nev' => $t['nev'] ?? ($source_teacher_names[$rovid] ?? null),
            ];
        }
    }
}

$grouped=[];
foreach ($orak as $o) {
    $t=$tanar_nevek[$o['tanar_id']]??null;
    $k=substr($o['kezdes'],0,5); $v=substr($o['vegzes'],0,5);
    $key=$k.'_'.$v;
    if (!isset($grouped[$key])) {
        $grouped[$key]=['ora_sorszam'=>$o['ora_sorszam'],'kezdes'=>$k,'vegzes'=>$v,'csoportok'=>[]];
    }
    $tanar_code=$t['rovid_nev']??'?';
    $tanar_nev=$t['nev']??null;
    $osztaly=$o['osztaly'];
    $tantargy=$o['tantargy'];
    $mar_van=false;
    foreach ($grouped[$key]['csoportok'] as $c) {
        if ($c['tanar']===$tanar_code && $c['osztaly']===$osztaly && $c['tantargy']===$tantargy) { $mar_van=true; break; }
    }
    if (!$mar_van) {
        $grouped[$key]['csoportok'][]=['tanar'=>$tanar_code,'tanar_nev'=>$tanar_nev,'osztaly'=>$osztaly,'tantargy'=>$tantargy];
    }
}

$slots=[];
foreach ($grouped as $slot) {
    $cs=$slot['csoportok'];
    $osztalyok=[]; $tanarok=[]; $tantargyak=[];
    foreach ($cs as $c) {
        if (!in_array($c['osztaly'],$osztalyok,true)) $osztalyok[]=$c['osztaly'];
        if (!in_array($c['tanar'],$tanarok,true)) $tanarok[]=$c['tanar'];
        if (!in_array($c['tantargy'],$tantargyak,true)) $tantargyak[]=$c['tantargy'];
    }
    $slots[]=[
        'ora_sorszam'=>$slot['ora_sorszam'],
        'tanar'=>implode(' / ',$tanarok),
        'tanar_nev'=>count($cs)===1?$cs[0]['tanar_nev']:null,
        'osztaly'=>implode('/',$osztalyok),
        'tantargy'=>implode(' / ',$tantargyak),
        'kezdes'=>$slot['kezdes'],
        'vegzes'=>$slot['vegzes'],
    ];
}
usort($slots, fn($a,$b)=>strcmp($a['kezdes'],$b['kezdes']));

$aktualis=$kovetkezo=null;
foreach ($slots as $ora) {
    $k=$ora['kezdes']; $v=$ora['vegzes'];
    if ($ido>=$k&&$ido<=$v) $aktualis=$ora;
    elseif ($ido<$k&&$kovetkezo===null) $kovetkezo=$ora;
}

if ($aktualis!==null) {
    $k=$aktualis['kezdes']; $v=$aktualis['vegzes'];
    $pm=(strtotime($v)-strtotime($ido))/60;
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'allapot'=>'foglalt',
        'aktualis'=>['ora_sorszam'=>$aktualis['ora_sorszam'],'tanar'=>$aktualis['tanar'],'tanar_nev'=>$aktualis['tanar_nev'],'osztaly'=>$aktualis['osztaly'],'tantargy'=>$aktualis['tantargy'],'kezdes'=>$k,'vegzes'=>$v,'perc_maradt'=>max(0,(int)$pm)],
        'kovetkezo'=>$kovetkezo?['ora_sorszam'=>$kovetkezo['ora_sorszam'],'tanar'=>$kovetkezo['tanar'],'osztaly'=>$kovetkezo['osztaly'],'tantargy'=>$kovetkezo['tantargy'],'kezdes'=>$kovetkezo['kezdes'],'vegzes'=>$kovetkezo['vegzes']]:null,
    ]);
} else {
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'allapot'=>'szabad','aktualis'=>null,
        'kovetkezo'=>$kovetkezo?['ora_sorszam'=>$kovetkezo['ora_sorszam'],'tanar'=>$kovetkezo['tanar'],'osztaly'=>$kovetkezo['osztaly'],'tantargy'=>$kovetkezo['tantargy'],'kezdes'=>$kovetkezo['kezdes'],'vegzes'=>$kovetkezo['vegzes']]:null,
    ]);
}
