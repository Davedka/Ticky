# Ticky – PHP Backend API

QR-kód alapú iskolai terem-azonosító rendszer. Megmutatja ki tart éppen órát, melyik teremben, valós időben.

## Stack

- **PHP 8.x** – Backend API (Render.com)
- **Supabase** – PostgreSQL adatbázis
- **Tailwind CSS** – Frontend
- **Node.js** – Órarend importer (lokálisan futtatva)

---

## API Endpointok

| Endpoint | Leírás |
|---|---|
| `GET /api/ping` | Health check |
| `GET /api/termek` | Összes terem listája |
| `GET /api/termek?allapot=1` | Termek + mai foglaltsági státusz |
| `GET /api/terem/{szam}` | Adott terem aktuális és következő órája |
| `GET /api/napirend/{szam}` | Adott terem mai napirendje |
| `GET /api/napirend/{szam}?nap=1` | Adott nap napirendje (1=H … 5=P) |
| `GET /api/napirend/{szam}?nap=heten` | Teljes heti napirend |
| `GET /api/tanarok` | Összes tanár kód + nevek |
| `GET /api/tanar/{kod}/orarend` | Adott tanár mai napirendje |
| `GET /api/tanar/{kod}/orarend?nap=heten` | Adott tanár heti napirendje |

---

## Példa válaszok

### `GET /api/terem/204` – Foglalt

```json
{
  "terem": "204",
  "allapot": "foglalt",
  "aktualis": {
    "tanar": "ÁSZJ",
    "osztaly": "9.b",
    "tantargy": "mny",
    "kezdes": "09:15",
    "vegzes": "10:00",
    "perc_maradt": 23
  },
  "kovetkezo": {
    "tanar": "ÁSZJ",
    "osztaly": "10.c",
    "kezdes": "10:15",
    "vegzes": "11:00"
  }
}
```

### `GET /api/terem/204` – Szabad

```json
{
  "terem": "204",
  "allapot": "szabad",
  "aktualis": null,
  "kovetkezo": {
    "tanar": "ÁSZJ",
    "kezdes": "10:15",
    "vegzes": "11:00"
  }
}
```

### `GET /api/tanar/ÁSZJ/orarend`

```json
{
  "tanar": "ÁSZJ",
  "tanar_nev": "Ásványi-Szabó Judit",
  "nap": 3,
  "nap_neve": "Szerda",
  "orak": [
    {
      "ora_sorszam": 2,
      "terem": "204",
      "osztaly": "9.b",
      "tantargy": "mny",
      "kezdes": "08:20",
      "vegzes": "09:05",
      "folyamatban": false
    }
  ]
}
```

---

## Frontend Oldalak

| URL | Leírás |
|---|---|
| `/` | Főoldal |
| `/termek` | Összes terem élő státusz dashboard |
| `/terem/{szam}` | Terem QR oldal + heti időtengel |
| `/terem/{szam}/nap` | Terem heti napirendje |
| `/tanar` | Tanár kereső |
| `/tanar/{kod}` | Adott tanár nézete |
| `/kijelzo` | Folyosói kijelző mód (TV/tablet) |
| `/qr` | QR kód generáló + nyomtatás |
| `/admin` | Admin panel (jelszóval védett) |

---

## Fájlstruktúra

```
ticky-backend/
├── index.php              ← Router (minden kérés ide jön)
├── render.yaml            ← Render deploy konfig
├── composer.json
├── config/
│   └── supabase.php       ← Supabase kapcsolat + HTTP helpers
├── utils/
│   └── helpers.php        ← json_response, routing, CORS
├── api/
│   ├── terem.php          ← GET /api/terem/{szam}
│   ├── termek.php         ← GET /api/termek
│   ├── napirend.php       ← GET /api/napirend/{szam}
│   ├── tanarok.php        ← GET /api/tanarok
│   ├── tanar_orarend.php  ← GET /api/tanar/{kod}/orarend
│   ├── admin_tanar.php    ← POST /api/admin/tanar
│   └── admin_terem.php    ← PATCH /api/admin/terem/{szam}
└── pages/
    ├── terem.php          ← /terem/{szam}
    ├── termek.php         ← /termek
    ├── napirend.php       ← /terem/{szam}/nap
    ├── tanar.php          ← /tanar
    ├── kijelzo.php        ← /kijelzo
    ├── qr.php             ← /qr
    └── admin.php          ← /admin
```

---

## Telepítés – Render.com

1. GitHub repóba töltsd fel a `ticky-backend/` mappát
2. Render → **New Web Service** → Connect repo
3. **Build command:** `echo "No build needed"`
4. **Start command:** `php -S 0.0.0.0:$PORT index.php`
5. Environment variables beállítása (lásd lent)
6. Deploy – kb. 1–2 perc

### Szükséges Environment Variables

| Változó | Leírás |
|---|---|
| `SUPABASE_URL` | Supabase projekt URL |
| `SUPABASE_ANON_KEY` | Publikus anon kulcs |
| `SUPABASE_SERVICE_KEY` | Service role kulcs (admin műveletekhez) |
| `TIMEZONE` | `Europe/Budapest` |
| `ADMIN_PASSWORD` | Admin panel jelszó |

> ⚠️ A kulcsokat **soha ne commitold** a repóba. Render dashboard → Environment Variables menüben add meg őket.

---

## Importer (Node.js)

Az órarendadatok a `tanárok.js` fájlból kerülnek a Supabase adatbázisba.

```bash
cd importer
npm install
node importer.js
```

**Előfeltétel:** A `.env` fájlban legyen megadva `SUPABASE_URL` és `SUPABASE_SERVICE_KEY`.

Az importer törli a régi adatokat és feltölti az újakat. Futtasd minden alkalommal amikor megváltozik az órarend.

---

## Adatbázis sémája (Supabase)

| Tábla | Leírás |
|---|---|
| `termek` | Terem szám, emelet |
| `tanarok` | Tanár kód, teljes név |
| `orarendek` | Órarend bejegyzések (terem, tanár, osztály, tantárgy, nap, idő) |

Az `orarendek` tábla `het_napja` mezője: `1`=Hétfő … `5`=Péntek.

---

## Iskolai időbeosztás

| Óra | Kezdés | Vége |
|---|---|---|
| 1. | 07:30 | 08:10 |
| 2. | 08:20 | 09:05 |
| 3. | 09:15 | 10:00 |
| 4. | 10:15 | 11:00 |
| 5. | 11:10 | 11:55 |
| 6. | 12:05 | 12:50 |
| 7. | 12:50 | 13:35 |
| 8. | 13:40 | 14:20 |
