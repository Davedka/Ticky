# Ticky API – PHP Backend

## Endpointok

| Endpoint | Leírás |
|---|---|
| `GET /api/ping` | Health check |
| `GET /api/termek` | Összes terem |
| `GET /api/termek?allapot=1` | Termek + mai foglaltság |
| `GET /api/terem/204` | Terem 204 aktuális órája |
| `GET /api/napirend/204` | Terem 204 mai napirendje |
| `GET /api/napirend/204?nap=3` | Szerdai órarend |
| `GET /api/napirend/204?nap=heten` | Teljes heti órarend |

## Példa válasz – `GET /api/terem/204`

**Foglalt:**
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
    "kezdes": "10:15"
  }
}
```

**Szabad:**
```json
{
  "terem": "204",
  "allapot": "szabad",
  "aktualis": null,
  "kovetkezo": {
    "tanar": "ÁSZJ",
    "kezdes": "10:15"
  }
}
```

## Telepítés

### Lokálisan
```bash
cp .env.example .env
# Töltsd ki a .env-t
php -S localhost:8000 index.php
```

### Render.com
1. GitHub repóba töltsd fel ezt a mappát
2. Render → New Web Service → Connect repo
3. Environment variables beállítása (SUPABASE_URL, stb.)
4. Deploy – kb. 1 perc

## Fájlstruktúra
```
backend/
├── index.php          ← Router (minden kérés ide jön)
├── .htaccess          ← URL rewriting
├── render.yaml        ← Render deploy konfig
├── composer.json
├── .env.example
├── config/
│   └── supabase.php   ← Supabase kapcsolat + HTTP helpers
├── utils/
│   └── helpers.php    ← json_response, routing, CORS
└── api/
    ├── terem.php      ← GET /api/terem/{szam}
    ├── termek.php     ← GET /api/termek
    └── napirend.php   ← GET /api/napirend/{szam}
```
