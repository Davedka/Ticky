# Changelog

A projekt összes jelentős változása ebben a fájlban lesz dokumentálva.


---

## [1.2.4] – 2026-05-28

### 📝Frissitve
- Frissitve lett a dokumentáció mivel már elavult lett egy picit a readme teljesen átlett irva.
- A legutobbi változtatásokat illetve javitásokat és fejlesztések belelettek irva hogy uptodate legyen a readme.

### 🐛 Javítva
- `Tanárok órája egybecsuszott`bug: a(z) `Osztálynézet` & `Termek` & `Kijelző` - mindegyiknél egybecsuszott a tanárok oróji ha egymás után van órája ugyanabban a teremben ugyanazzal az osztályal ugyanazon a napon, mostmár jól mutatja az órákat mindenhol.
- `Napirend nézet`: `Termek-> bármelyik terem-> és ott Napirend nézet`: - ha véletlen két vagy több osztály van azon az órán vagy terembe akkor nem rendes modon irta ki ergo xy osztály - másik xy osztály na mostmár ugy nézki hogy xy osztály/xy osztály.
- `Qr generátor`bug: az utobbi patchek illetve updatek után a qr-kódók nem töltöttek be átkerületek cloudbase-re tehát mostantol látszódnak az összes termeknek a qr-kódjai.
- `Osztály nézet`bug: Miután meglett csinálva hogy az egymás utáni órákat külön órának vegye és ne egy óraként ergo nem váltott át ha pl 2 óra volt egymás után a 3.órára stb stb ezután azis felkerült hogy nagy bug volt végig az hogy a csoportbontásnak a logikája elcsuszott valahol mert a csoportbontásnál ha egyszerre két óra volt egymás után szintén egynek vette és itt születtek ilyen szemrelátható dolgok hogy ha volt egy ilyen 3-4.órába csoportbontás dupla óra egymás után akkor hirtelen 6-7 óra elcsuszott lett két 6.óra stb stb sikerült kiküszübölni a problémát illetve javitva lett.
- `Összes terem` & `Kijelző`: Iskola bejárása szerint lett organizálva tehát ahogy megy az iskola ugy vannak benne a termek.
- 'Új feature' mostantol az 'Osztály nézetbwn' van egy outline ami mutatja a csoportbontásos órát & az órának a tetején kiirja hogy hány óra csoportbontás van.

### ⚙️ WIP [2026-09-01 - 2026-11-01]
- Nos mivel az iskola átesett nem régiben érettségi előtt egy épület bövitésen következő tanévben várhatóak az uj termek is.
- Szeretnék pár tester-t szerezni aki tud minden ujdonságot tesztelni fejlesztés alatt van egy olyan tesztelési felület amivel tudnak tesztelni könnyen ugy hogy ne törjön el semmi.


### 💡 Tervek
- `Lyukas óra`: Következő tanévre szeretném megcsinálni a lyukasórát is mivel egy nagyon fontos dolog lehetne mind a tanároknak illetve diákoknak is.
- `Tanár kereső`: A tulsok tanár miatt ugyérzem hogy mind telefonon mind számitógépen egy fokkal praktikusabb lenne az ha egy kereső lenne felül nem egy dropdown menü igy beirva a monogramot vagy a tanár teljes nevét könyebb keresési lehetőség.


## [1.2.0] – 2026-04-19

### 🐛 Javítva
- **Időzóna bug:** a tanár nézetben az aktuális óra elcsúszva jelent meg, mert a szerver és a kliens idő nem volt szinkronban. A backend most már `DateTime` objektumokkal dolgozik az `Europe/Budapest` időzóna alapján.
- **Dupla óra határeset:** ha két óra közvetlenül egymás után következett (pl. 08:00–08:45 és 08:45–09:30), a 08:45-ös pillanatban az algoritmus már a következő órát mutatta. A fix: ha `ido === vegzes` és van következő óra amelynek `kezdes === vegzes`, az előző óra marad aktív a záró pillanatban.
- **`13.c_du` és hasonló kódok** mostantól az „Egyéb" kategóriába kerülnek, nem pedig a 13. évfolyamba.
- **Admin link a navbarban:** korábban mindenki látta, most csak az jelentkezett admin számára jelenik meg.

### ✨ Hozzáadva
- **Szünetek Supabase-ben:** hardcoded lista helyett `szunetek` tábla, teljes CRUD az admin panelen.
- **Szünet banner minden oldalon:** tanár, osztály, terem, termek, napirend és kijelző oldal is mutatja ha aktív szünet van.
- **Osztály oldal teljesen újraírva:** kártyás layout, élő státusz pill, csoportbontás megjelenítése.
- **Tanár oldal újraírva:** ugyanolyan kártyás UI mint az osztály, heti napirend lista, progress bar az aktuális óránál.
- **Felhasználók tab admin panelen:** bcrypt jelszó, szerep (admin/user), aktív toggle, jelszó csere modal.
- **Mobil hamburger menü** a navbarban.

### 🚀 Teljesítmény
- **Lighthouse Desktop:** Performance 99, Accessibility 96, Best Practices 100, SEO 90
- **Lighthouse Mobile:** Performance 83, Accessibility 96, Best Practices 100, SEO 90

---

## [1.1.0] – 2026-04-05

### ✨ Hozzáadva
- **Admin panel:** Dashboard (élő foglalt termek), Tanárok kezelése (teljes név hozzáadása rövid kódhoz).
- **QR generátor:** nyomtatható QR kódok az összes teremhez, tömeges kiválasztás.
- **Kijelző mód (`/kijelzo`):** folyosói monitorra tervezett teljes képernyős nézet az összes terem élő állapotával.
- **Support oldal** egyszerű űrlappal + GitHub issue link.

### 🎨 Változott
- Glassmorphism design rendszer: sötét navy háttér, arany accent, Playfair Display + DM Sans fontok.
- Floating oldal-sidebar + mobilon bottom bar.

---

## [1.0.0] – 2026-03-20

### ✨ Első kiadás
- **QR-alapú teremnézet:** `/terem/{szám}` – élő foglaltság, aktuális óra, következő óra, progress bar.
- **Teljes heti órarend nézet:** `/terem/{szám}/nap` – desktop grid + mobil tabs.
- **Termek lista:** `/termek` – élő foglalt/szabad státusz minden teremnél, szűrés + kereső.
- **Tanár kereső** és **osztály kereső** oldalak.
- **PHP 8 + Supabase PostgreSQL** backend Render.com-on.
- **Órarendek, tanárok, termek, osztályok** Supabase táblákban.

---

## Jelmagyarázat

- `✨ Hozzáadva` – új funkciók
- `⚙️ WIP` – Dolgozás van rajta
- `💡 Ötletek` – Ötletek
- `🎨 Változott` – meglévő funkciók módosítása
- `🐛 Javítva` – hibajavítások
- `🗑️ Eltávolítva` – törölt funkciók
- `🔒 Biztonság` – biztonsági javítások
- `🚀 Teljesítmény` – gyorsítás, méret-csökkentés
- `📝 Dokumentáció` – README, docs változás

---

_Az aktuális fejlesztést a [`main`](https://github.com/Davedka/Ticky) branchen követheted._
