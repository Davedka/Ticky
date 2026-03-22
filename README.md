# Ticky – PHP Backend API

QR code-based classroom identification system for schools. Shows who is currently teaching, in which room, in real time.

## Stack

- **PHP 8.x** – Backend API (Render.com)
- **Supabase** – PostgreSQL database
- **Tailwind CSS** – Frontend
- **Node.js** – Timetable importer (run locally)

---

## API Endpoints

| Endpoint | Description |
|---|---|
| `GET /api/ping` | Health check |
| `GET /api/termek` | List of all rooms |
| `GET /api/termek?allapot=1` | Rooms with today's availability status |
| `GET /api/terem/{number}` | Current and next class for a given room |
| `GET /api/napirend/{number}` | Today's schedule for a given room |
| `GET /api/napirend/{number}?nap=1` | Schedule for a specific day (1=Mon … 5=Fri) |
| `GET /api/napirend/{number}?nap=heten` | Full weekly schedule for a room |
| `GET /api/tanarok` | All teacher codes and names |
| `GET /api/tanar/{code}/orarend` | Today's schedule for a given teacher |
| `GET /api/tanar/{code}/orarend?nap=heten` | Weekly schedule for a given teacher |

---

## Example Responses

### `GET /api/terem/204` – Occupied

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

### `GET /api/terem/204` – Free

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

## Frontend Pages

| URL | Description |
|---|---|
| `/` | Home page |
| `/termek` | All rooms — live status dashboard |
| `/terem/{number}` | Room QR page + weekly timeline |
| `/terem/{number}/nap` | Room's weekly schedule |
| `/tanar` | Teacher search |
| `/tanar/{code}` | Individual teacher view |
| `/kijelzo` | Hallway display mode (TV/tablet) |
| `/qr` | QR code generator + print |
| `/admin` | Admin panel (password protected) |

---

## File Structure

```
ticky-backend/
├── index.php              ← Router (all requests go here)
├── render.yaml            ← Render deploy config
├── composer.json
├── config/
│   └── supabase.php       ← Supabase connection + HTTP helpers
├── utils/
│   └── helpers.php        ← json_response, routing, CORS
├── api/
│   ├── terem.php          ← GET /api/terem/{number}
│   ├── termek.php         ← GET /api/termek
│   ├── napirend.php       ← GET /api/napirend/{number}
│   ├── tanarok.php        ← GET /api/tanarok
│   ├── tanar_orarend.php  ← GET /api/tanar/{code}/orarend
│   ├── admin_tanar.php    ← POST /api/admin/tanar
│   └── admin_terem.php    ← PATCH /api/admin/terem/{number}
└── pages/
    ├── terem.php          ← /terem/{number}
    ├── termek.php         ← /termek
    ├── napirend.php       ← /terem/{number}/nap
    ├── tanar.php          ← /tanar
    ├── kijelzo.php        ← /kijelzo
    ├── qr.php             ← /qr
    └── admin.php          ← /admin
```

---

## Deployment – Render.com

1. Push the `ticky-backend/` folder to a GitHub repository
2. Render → **New Web Service** → Connect repo
3. **Build command:** `echo "No build needed"`
4. **Start command:** `php -S 0.0.0.0:$PORT index.php`
5. Set the required environment variables (see below)
6. Deploy – takes about 1–2 minutes

### Required Environment Variables

| Variable | Description |
|---|---|
| `SUPABASE_URL` | Supabase project URL |
| `SUPABASE_ANON_KEY` | Public anon key |
| `SUPABASE_SERVICE_KEY` | Service role key (for admin operations) |
| `TIMEZONE` | `Europe/Budapest` |
| `ADMIN_PASSWORD` | Admin panel password |

> ⚠️ **Never commit these keys** to your repository. Set them via Render dashboard → Environment Variables.

---

## Importer (Node.js)

Timetable data is loaded into Supabase from the `tanárok.js` file.

```bash
cd importer
npm install
node importer.js
```

**Prerequisite:** A `.env` file must contain `SUPABASE_URL` and `SUPABASE_SERVICE_KEY`.

The importer deletes existing data and uploads the new set. Run it every time the timetable changes.

---

## Database Schema (Supabase)

| Table | Description |
|---|---|
| `termek` | Room number, floor |
| `tanarok` | Teacher code, full name |
| `orarendek` | Timetable entries (room, teacher, class, subject, day, time) |

The `het_napja` field in `orarendek`: `1` = Monday … `5` = Friday.

---

## School Bell Schedule

| Period | Start | End |
|---|---|---|
| 1st | 07:30 | 08:10 |
| 2nd | 08:20 | 09:05 |
| 3rd | 09:15 | 10:00 |
| 4th | 10:15 | 11:00 |
| 5th | 11:10 | 11:55 |
| 6th | 12:05 | 12:50 |
| 7th | 12:50 | 13:35 |
| 8th | 13:40 | 14:20 |
