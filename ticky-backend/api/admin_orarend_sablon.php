<?php
// api/admin_orarend_sablon.php
// GET /api/admin/orarend/sablon – "Ticky Import Format v1" sablon letöltése.
//
// Az iskola ezt tölti ki évente, így az import bemenete kiszámítható marad.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/xlsx_writer.php';
require_once __DIR__ . '/../utils/timetable_import.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['GET']);

$rows = [
    ['Tanár', 'Terem', 'Osztály', 'Tantárgy', 'Nap', 'Kezdés', 'Vége', 'Csoport'],
];

// Néhány kitöltött példasor, hogy a formátum egyértelmű legyen.
$examples = [
    ['ÁSZJ', '204', '9.b', 'matematika', 'Hétfő',     1, ''],
    ['BUP',  '202', '9.a', 'irodalom',   'Kedd',      2, '1'],
    ['NYMK', 'K2',  '9.a', 'angol',      'Szerda',    3, '2'],
    ['FL',   'T1',  '10.c', 'testnevelés', 'Csütörtök', 4, ''],
    ['SZBI', 'm17', '11.f', 'gyakorlat',  'Péntek',   5, ''],
];

$slots = ticky_timetable_period_slots();
foreach ($examples as [$teacher, $room, $class, $subject, $day, $period, $group]) {
    [$start, $end] = $slots[$period];
    $rows[] = [$teacher, $room, $class, $subject, $day, $start, $end, $group];
}

// Súgó munkalap: az elfogadott értékek felsorolása.
$help = [
    ['Ticky Import Format v1 – kitöltési útmutató'],
    [''],
    ['Oszlop', 'Kötelező', 'Elfogadott érték'],
    ['Tanár', 'igen', 'Tanár rövid kódja, pl. ÁSZJ, BUP, NYMK'],
    ['Terem', 'igen', 'Teremkód, pl. 204, K101, m17, T1, Kt'],
    ['Osztály', 'igen', 'Osztálykód, pl. 9.b, 13.c_du, HT_13.ir'],
    ['Tantárgy', 'nem', 'Tantárgy neve vagy kódja'],
    ['Nap', 'igen', 'Hétfő, Kedd, Szerda, Csütörtök, Péntek'],
    ['Kezdés', 'igen', 'A csengetési rend szerinti kezdés (lásd lent)'],
    ['Vége', 'igen', 'A csengetési rend szerinti vége'],
    ['Csoport', 'nem', 'Csoportbontás száma (1, 2, ...). Üres = az egész osztály.'],
    [''],
    ['Csengetési rend'],
    ['Óra', 'Kezdés', 'Vége'],
];

foreach ($slots as $number => [$start, $end]) {
    $help[] = [$number . '.', $start, $end];
}

$help[] = [''];
$help[] = ['Egy sor = egy tanóra egy tanárral, egy teremben, egy osztálynak.'];
$help[] = ['Csoportbontásnál csoportonként külön sort kell felvenni.'];

$bytes = ticky_xlsx_write([
    ['nev' => 'Órarend', 'sorok' => $rows],
    ['nev' => 'Útmutató', 'sorok' => $help],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Ticky_Import_Template.xlsx"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
