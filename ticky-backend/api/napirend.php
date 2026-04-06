<?php
// api/napirend.php – szünet-érzékeny

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/szunet.php';

handle_cors();

$szam = strtoupper(trim((string)($_GET['szam'] ?? '')));
if ($szam === '') {
    $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $params = match_route('/api/napirend/{szam}', $uri);
    if ($params !== false) $szam = strtoupper(trim($params['szam']));
}
if ($szam === '') { json_error('Hiányzó terem szám', 400); }

$nap_param    = $_GET['nap'] ?? null;
$het_egeszben = ($nap_param === 'heten');
$sz           = aktiv_szunet();

$termek = sb_get('termek', ['terem_szam'=>'eq.'.$szam,'select'=>'id,terem_szam,emelet']);
if (empty($termek)) { json_error('Terem nem található: '.$szam, 404); }
$terem = $termek[0];

// Hétvége
if (!$het_egeszben) {
    $nap = $nap_param !== null ? (int)$nap_param : mai_nap();
    if ($nap < 1 || $nap > 5) {
        json_response(['terem'=>$szam,'nap'=>$nap,'uzenet'=>'Nincs tanítás (hétvége)','orak'=>[]]);
    }
}

// Szünet – heti nézetnél is visszaadjuk de flag-eljük
$orarendek_params = [
    'terem_id' => 'eq.'.$terem['id'],
    'aktiv'    => 'eq.true',
    'select'   => 'osztaly,tantargy,kezdes,vegzes,ora_sorszam,het_napja,tanar_id',
    'order'    => 'het_napja.asc,kezdes.asc',
];
if ($het_egeszben) {
    $orarendek_params['het_napja'] = 'in.(1,2,3,4,5)';
} else {
    $orarendek_params['het_napja'] = 'eq.'.$nap;
}

$orak = sb_get('orarendek', $orarendek_params);

$tanar_map = [];
if (!empty($orak)) {
    $ids = array_unique(array_filter(array_column($orak,'tanar_id')));
    if (!empty($ids)) {
        foreach (sb_get('tanarok',['id'=>'in.('.implode(',',$ids).')','select'=>'id,rovid_nev,nev']) as $t) {
            $tanar_map[$t['id']]=$t;
        }
    }
}

$NAP_NEVEK=[1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
$ido=aktualis_ido();

if ($het_egeszben) {
    $het=[];
    foreach ($orak as $o) {
        $d=$o['het_napja']; $tr=$tanar_map[$o['tanar_id']]??null;
        $het[$d][]=['ora_sorszam'=>$o['ora_sorszam'],'tanar'=>$tr['rovid_nev']??'?','tanar_nev'=>$tr['nev']??null,'osztaly'=>$o['osztaly'],'tantargy'=>$o['tantargy'],'kezdes'=>substr($o['kezdes'],0,5),'vegzes'=>substr($o['vegzes'],0,5)];
    }
    $napok=[];
    for ($d=1;$d<=5;$d++) $napok[]=['nap'=>$d,'nap_neve'=>$NAP_NEVEK[$d],'orak'=>$het[$d]??[]];
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'het'=>$napok,'szunet'=>$sz?$sz['nev']:null]);
} else {
    $result=[];
    foreach ($orak as $o) {
        $tr=$tanar_map[$o['tanar_id']]??null;
        $k=substr($o['kezdes'],0,5); $v=substr($o['vegzes'],0,5);
        $result[]=['ora_sorszam'=>$o['ora_sorszam'],'tanar'=>$tr['rovid_nev']??'?','tanar_nev'=>$tr['nev']??null,'osztaly'=>$o['osztaly'],'tantargy'=>$o['tantargy'],'kezdes'=>$k,'vegzes'=>$v,'folyamatban'=>($ido>=$k&&$ido<=$v)];
    }
    json_response(['terem'=>$szam,'emelet'=>$terem['emelet'],'nap'=>$nap??mai_nap(),'nap_neve'=>$NAP_NEVEK[$nap??mai_nap()]??'','ido'=>$ido,'orak'=>$result,'szunet'=>$sz?$sz['nev']:null]);
}
