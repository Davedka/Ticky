# Osztálynézet Follow-up Design

Dátum: 2026-04-04
Állapot: jóváhagyott tervezés

## Kontextus

Az osztálynézet első verziója a PR26 logikájára épült, de a jelenlegi Ticky UI-stílusával lett beillesztve. A follow-up célja nem új dizájn létrehozása, hanem a most már merge-ölt osztálynézet adatminőségi és navigációs hiányosságainak javítása:

- az osztály belépési pont vizuálisan is kerüljön a tanár és a QR közé
- a support oldalon lehessen kifejezetten osztályproblémát jelenteni
- a FAQ kapjon osztály-specifikus válaszokat
- a hibás `101` rekord ne jelenjen meg lehetséges osztályként
- a 9. évfolyam teljesen kerüljön vissza az osztálylistába
- a párhuzamos órák jelenlegi logikája maradjon meg
- a jelenlegi, commitolt osztályoldal vizuális nyelve maradjon az alap

## Célok

1. Az osztálylista és az osztály órarend API csak valódi osztályokat szolgáljon ki.
2. A 9. évfolyam osztályai újra megjelenjenek.
3. A hibásan bekerült teremszámok, különösen a `101`, ne jelenjenek meg osztályként.
4. A jövőbeli importoknál se tudjon visszacsúszni ugyanilyen hiba.
5. Az osztálynézet belépési pontjai és a support tartalom tükrözzék az új funkciót.

## Nem célok

- az osztálynézet teljes újratervezése
- a párhuzamos órák UX-logikájának átírása
- új admin UI építése osztálykezeléshez
- a tanár-, terem- vagy kijelzőnézet vizuális átdolgozása

## Megfigyelt probléma

### Jelenlegi állapot

- `ticky-backend/api/osztalyok.php` a `orarendek.osztaly` mező minden distinct értékét nyersen visszaadja.
- `ticky-backend/api/osztaly_orarend.php` a kért osztályt a nyers adatkészletből oldja fel.
- A rendszerben nincs központi szabály arra, mi számít osztálynak és mi teremnek.
- A support oldal jelenleg nem kezeli külön az osztálynézettel kapcsolatos hibákat.
- A landing oldalon az osztálynézet kártya még nincs a tanár és a QR közé emelve.

### Következmény

- hibás rekordok, például `101`, osztályként megjelenhetnek
- a 9. évfolyam megjelenése nem megbízható
- a jövőbeli import ismét előállíthatja ugyanazt az állapotot
- a support és FAQ nem ad elég konkrét segítséget az osztálynézethez

## Ajánlott megoldás

Az ajánlott megoldás három részből áll:

1. futásidejű védelmi réteg az osztály API-kban
2. importlogikai javítás, hogy a későbbi adatok se legyenek hibásak
3. egyszeri adattisztítás / újraimport, hogy a mostani Supabase-állapot is rendbe kerüljön

Ez ad azonnali javulást a felületen, és egyben megszünteti a hiba visszatérésének fő okát is.

## Tervezett architektúra

### 1. Közös osztály-segédlogika

Bevezetünk egy közös, újrahasználható osztályos segédréteget PHP oldalon. Ennek felelőssége:

- osztálykód normalizálása
- érvényesség eldöntése
- teremszám jellegű értékek kizárása
- rendezési szabály biztosítása

Javasolt helye:

- új helper fájl, például `ticky-backend/utils/osztaly.php`, vagy
- a meglévő `helpers.php` bővítése, ha a függvénykészlet kicsi marad

Mivel ez egy jól elkülöníthető doménszabály, a preferált irány egy külön `utils/osztaly.php`.

### 2. Érvényességi szabály

Az osztálylista és az osztályfeloldás nem csak a standard `9.a`, `10.b` típusú kódokat fogadhatja el, mert a forrásban vannak speciálisabb alakok is. Ezért a szabály nem lehet szűken "szám+pont+betű".

Az osztályjelölt akkor maradjon meg, ha:

- tartalmaz osztályjellegű mintát vagy betűs azonosítót
- nem tisztán teremszám jellegű
- nem egy ismert terem-token

Első körben kizárandó:

- tisztán numerikus értékek, például `101`
- egyértelmű teremazonosítók, ha csak terem-formában szerepelnek

Megőrzendő:

- `9.a`, `9.b`, `9.c`, stb.
- speciális, betűs vagy vegyes alakok, ha azok osztálymezőből származnak és nem teremszámok

Az osztálylista forrása továbbra is a Supabase `orarendek.osztaly`, de a központi segédlogika szűri meg a végeredményt.

### 3. Osztálylista API

Az `api/osztalyok.php` változásai:

- nyers distinct lista betöltése marad
- minden elem a központi osztály-validáló logikán megy át
- a hibás `101` és hasonló értékek kiesnek
- a rendezés továbbra is osztálybarát marad
- a 9. évfolyam visszajön, ha a Supabase-ban már helyesen benne van

### 4. Osztály órarend API

Az `api/osztaly_orarend.php` változásai:

- a feloldás ugyanazt a validációs szabályt használja, mint a lista
- a nyers Supabase-találatokból csak a valid osztálykérés maradhat
- a mostani párhuzamosóra-csoportosítás változatlan marad
- a response shape nem változik érdemben, hogy a jelenlegi UI tovább működjön

### 5. Importlogika

Az importlogikában a jelenlegi osztály/terem szétválasztás közös szabályra kerül. A cél:

- a jövőbeli import során a `101` ne mehessen be osztályként
- a 9. évfolyam osztályai ne essenek ki hibás felismerés miatt
- a jelenlegi PR26-os párhuzamos csoportkezelési elv sértetlen maradjon

Az import oldalán a room/class felismerés ne két, lazán összefüggő regexp-alapú mellékág legyen, hanem ugyanarra a szabályrendszerre épüljön, mint a PHP oldali osztályszűrés.

### 6. Egyszeri adatjavítás

Mivel a hiba már bekerült a Supabase-ba, a futásidejű szűrés önmagában nem elég. Kell egy egyszeri helyreállítás is.

Az egyszeri adatjavítás célja:

- a 9. évfolyam teljes visszatöltése
- a hibás osztály-értékek eltávolítása vagy felülírása

Preferált irány:

- az import javítása után ugyanazzal a forrással újraimportálni az érintett osztályadatokat

Fallback:

- célzott, egyszeri adatjavító script a hibás rekordokra

## UI és navigációs változások

### 1. Landing oldal

A landing oldalon az osztálynézet belépési pontja a tanár és a QR közé kerül olvasási sorrendben, a jelenlegi dizájnnyelv megtartásával.

Preferált elrendezés:

- a másodlagos kártyák sorrendje: `Tanár kereső` -> `Osztály nézet` -> `QR Generátor`
- az osztálynézet ne külön, későbbi blokkban legyen
- a meglévő glass/gold-line/Playfair megjelenés maradjon

Ez nem PR26-layout átvétel, hanem a mostani landing rendszer újrarendezése.

### 2. Shared nav

A shared navbarban az `Osztály` link a `Tanár` és a `QR` között szerepeljen minden olyan oldalon, amely a `utils/_nav.php`-t használja.

### 3. Support oldal

Új support kategória:

- `Osztály / osztálynézet probléma`

Ezt a formban és a generált mail tárgy/label mappingben is be kell vezetni.

### 4. FAQ bővítés

Új FAQ témák:

- miért nem látok egy osztályt a listában
- mit jelent a párhuzamos jelzés
- miért látok több tanárt vagy több termet ugyanabban az időablakban
- mit tegyek, ha rossz terem vagy hiányzó 9-es osztály jelenik meg

Az FAQ a support oldalon marad, a jelenlegi accordion-megoldás bővítésével.

## Adatfolyam

### Osztálylista

1. Supabase `orarendek.osztaly` distinct lekérés
2. közös validáció és kizárási szabály
3. rendezés
4. frontend select feltöltése

### Osztály órarend

1. kérés érkezik egy osztálykóddal
2. közös osztályfeloldás
3. napi órák lekérése a Supabase-ból
4. jelenlegi párhuzamosóra-csoportosítás
5. frontend render

### Import / helyreállítás

1. forrásadat beolvasása
2. közös class-vs-room felismerés
3. helyes osztálymezők létrehozása
4. Supabase frissítés / újraimport

## Hibakezelés

- ha egy osztálykód érvénytelen, az API továbbra is 400/404 választ ad, de csak validáció után
- ha a 9-es osztályok helyreállítása valamilyen okból nem teljes, a runtime-szűrés akkor is megakadályozza a nyilvánvaló hibás rekordok kijutását
- ha az importhelyreállítás nem fut le, a UI még akkor is stabil marad, csak a hiányzó osztályok maradnak vissza

## Tesztelési stratégia

### Funkcionális ellenőrzés

- az osztálylista tartalmazza a 9. évfolyam osztályait
- az osztálylista nem tartalmazza a `101`-et
- egy kiválasztott 9-es osztály megnyitható
- a párhuzamos órák ugyanúgy jelennek meg, mint a mostani implementációban
- a landing oldalon az osztálynézet a tanár és a QR közé kerül
- a support formban van osztály-specifikus kategória
- a FAQ-ban vannak osztály-specifikus kérdések

### Regresszió

- a tanárnézet változatlanul működik
- a QR oldalra mutató linkek nem sérülnek
- a shared nav meglévő oldalaknál nem törik meg

### Technikai ellenőrzés

- `git diff --check`
- elérhető esetben PHP lint az érintett fájlokra
- ha készül importscript vagy adatjavító script, annak száraz futása vagy ellenőrzött outputja

## Érintett fájlok

Biztosan érintett:

- `ticky-backend/index.php`
- `ticky-backend/utils/_nav.php`
- `ticky-backend/pages/support.php`
- `ticky-backend/api/osztalyok.php`
- `ticky-backend/api/osztaly_orarend.php`
- `ticky-backend/pages/osztaly.php`
- `importer.js`

Új fájl csak akkor kell, ha a helper külön fájlba kerül:

- `ticky-backend/utils/osztaly.php` vagy ennek megfelelő közös helper

Opcionális új fájl:

- egyszeri adatjavító script, ha az újraimport önmagában nem elég

## Nyitott döntések, amelyekre a mostani terv választ ad

- Csak UI-fix legyen, vagy adatjavítás is?
  - válasz: adatjavítás is
- PR26 dizájnt kell követni?
  - válasz: nem, a jelenlegi commitolt UI marad az alap
- A párhuzamos órák logikája változzon?
  - válasz: nem, marad a mostani osztálynézet logikája

## Implementációs sorrend

1. közös osztály-validációs helper
2. osztály API-k átállítása erre a helperre
3. landing és shared nav sorrend javítása
4. support kategória és FAQ bővítése
5. importlogika javítása
6. egyszeri adatkorrekció vagy újraimport
7. végső regresszió-ellenőrzés
