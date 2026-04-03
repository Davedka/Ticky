# Osztálynézet Follow-up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stabilizálni az osztálynézet adatait, visszahozni a 9. évfolyamot, kiszűrni a hibás `101` osztályt, és a mostani UI-hoz igazítani a nav/support bekötéseket.

**Architecture:** Közös osztály-helper dönti el, mi számít valós osztálynak, ezt használja az osztálylista API, az osztály órarend API és az importlogika is. A felületi munka csak a meglévő layoutok sorrendjét és tartalmát igazítja, a párhuzamos órák jelenlegi renderelése változatlan marad.

**Tech Stack:** PHP, vanilla JS, PowerShell, Node.js importer, Supabase REST helpers

---

### Task 1: Közös osztály-helper bevezetése

**Files:**
- Create: `C:\Users\david\Documents\New project\Ticky\ticky-backend\utils\osztaly.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\osztalyok.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\osztaly_orarend.php`
- Test: `git diff --check`

- [ ] **Step 1: Írd le a helper API-t és a kizárási szabályokat**

```php
function osztaly_normalize_code(string $value): string
function osztaly_is_numeric_room_like(string $value): bool
function osztaly_is_valid_code(string $value): bool
function osztaly_sort_compare(string $left, string $right): int
function osztaly_unique_list(array $values): array
function osztaly_resolve_code_from_values(string $requested_code, array $values): ?string
```

- [ ] **Step 2: Implementáld a helper minimális változatát**

```php
<?php

function osztaly_normalize_code(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function osztaly_is_numeric_room_like(string $value): bool
{
    return preg_match('/^\d{1,4}$/', $value) === 1;
}

function osztaly_is_valid_code(string $value): bool
{
    $value = osztaly_normalize_code($value);
    if ($value === '' || osztaly_is_numeric_room_like($value)) {
        return false;
    }

    return preg_match('/[\p{L}]/u', $value) === 1;
}
```

- [ ] **Step 3: Állítsd át az osztálylista API-t a helperre**

```php
require_once __DIR__ . '/../utils/osztaly.php';

$class_list = osztaly_unique_list(array_column($rows, 'osztaly'));
usort($class_list, 'osztaly_sort_compare');
```

- [ ] **Step 4: Állítsd át az osztály órarend API-t ugyanarra a feloldásra**

```php
require_once __DIR__ . '/../utils/osztaly.php';

$raw_values = array_column($rows, 'osztaly');
$class_code = osztaly_resolve_code_from_values($requested_code, $raw_values);
```

- [ ] **Step 5: Futtasd a whitespace/patch ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 6: Commit**

```bash
git add ticky-backend/utils/osztaly.php ticky-backend/api/osztalyok.php ticky-backend/api/osztaly_orarend.php
git commit -m "Add shared class filtering rules"
```

### Task 2: Nav és landing sorrend javítása

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\utils\_nav.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\index.php`
- Test: `git diff --check`

- [ ] **Step 1: Tedd be az Osztály linket a shared navba a Tanár és QR közé**

```php
$links = [
    ['href'=>'/termek',  'label'=>'Termek',  'key'=>'termek'],
    ['href'=>'/tanar',   'label'=>'Tanár',   'key'=>'tanar'],
    ['href'=>'/osztaly', 'label'=>'Osztály', 'key'=>'osztaly'],
    ['href'=>'/qr',      'label'=>'QR',      'key'=>'qr'],
    ['href'=>'/kijelzo', 'label'=>'Kijelző', 'key'=>'kijelzo'],
];
```

- [ ] **Step 2: A landing secondary kártyákat rendezd át három blokkra**

```html
<a href="/tanar" class="glass card-hover card-sm">...</a>
<a href="/osztaly" class="glass card-hover card-sm">...</a>
<a href="/qr" class="glass card-hover card-sm">...</a>
```

- [ ] **Step 3: Az index mobilmenü és desktop nav sorrendje maradjon konzisztens**

```html
<a href="/tanar">👩‍🏫 Tanár kereső</a>
<a href="/osztaly">🎓 Osztály nézet</a>
<a href="/qr">🖨️ QR Generátor</a>
```

- [ ] **Step 4: Futtasd a whitespace/patch ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 5: Commit**

```bash
git add ticky-backend/utils/_nav.php ticky-backend/index.php
git commit -m "Reorder class navigation entry"
```

### Task 3: Support kategória és FAQ bővítése

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\pages\support.php`
- Test: `git diff --check`

- [ ] **Step 1: Adj hozzá osztály-specifikus support kategóriát a selecthez**

```html
<option value="osztaly" style="background:#0b2e59;">🎓 Osztály / osztálynézet probléma</option>
```

- [ ] **Step 2: Frissítsd a JS tárgy-label mapet**

```js
const targyNevek = {
  hiba:'🐛 Hibajelentés',
  kerdes:'❓ Általános kérdés',
  terem:'🏫 Terem / órarend',
  tanar:'👩‍🏫 Tanár adat',
  osztaly:'🎓 Osztály / osztálynézet probléma',
  javaslat:'💡 Fejlesztési javaslat',
  egyeb:'📋 Egyéb'
}
```

- [ ] **Step 3: Bővítsd az FAQ tömböt osztálynézetes elemekkel**

```php
['q'=>'Miért nem látom az osztályomat a listában?','a'=>'...'],
['q'=>'Mit jelent a párhuzamos jelzés az osztálynézetben?','a'=>'...'],
['q'=>'Miért látok egyszerre több tanárt vagy termet?','a'=>'...'],
```

- [ ] **Step 4: Futtasd a whitespace/patch ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 5: Commit**

```bash
git add ticky-backend/pages/support.php
git commit -m "Add class support and FAQ entries"
```

### Task 4: Importlogika javítása a hibás osztályok ellen

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\importer.js`
- Test: `node --check importer.js`
- Test: `git diff --check`

- [ ] **Step 1: Emeld ki az osztály-vs-terem felismerést külön függvényekbe**

```js
function normalizeToken(value) { ... }
function isNumericRoomLike(value) { ... }
function isValidClassCode(value) { ... }
function isRoomToken(value) { ... }
```

- [ ] **Step 2: Az osztályok szűrését tedd explicit validációra**

```js
const osztalyok = split(e.class).map(normalizeToken).filter(isValidClassCode)
const termek = split(e.room).map(normalizeToken).filter(isRoomToken)
```

- [ ] **Step 3: Védd le az üres vagy hibás osztályokat az insert előtt**

```js
if (!osztalyok.length) {
  hibak.push(`Nincs érvényes osztály: ${e.class}`)
  continue
}
```

- [ ] **Step 4: Futtasd a JS syntax checket**

Run: `node --check importer.js`
Expected: no syntax errors, exit code 0

- [ ] **Step 5: Futtasd a whitespace/patch ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 6: Commit**

```bash
git add importer.js
git commit -m "Harden class detection in importer"
```

### Task 5: Egyszeri adatkorrekció a jelenlegi Supabase-állapotra

**Files:**
- Create: `C:\Users\david\Documents\New project\Ticky\scripts\repair-osztaly-import.mjs`
- Test: `node --check scripts/repair-osztaly-import.mjs`
- Test: `node scripts/repair-osztaly-import.mjs --dry-run`
- Test: `git diff --check`

- [ ] **Step 1: Írj egy dry-run scriptet, ami újraépíti a várt osztálylistát a forrásból**

```js
const dryRun = process.argv.includes('--dry-run')
const expectedClasses = buildExpectedClassesFromSource(entries)
console.log({ total: expectedClasses.length, has101: expectedClasses.includes('101') })
```

- [ ] **Step 2: A script kérdezze le a Supabase jelenlegi osztályértékeit és listázza az eltéréseket**

```js
const { data } = await sb.from('orarendek').select('osztaly')
const currentClasses = uniqueValidClasses(data.map(row => row.osztaly))
```

- [ ] **Step 3: Implementáld úgy, hogy alapból dry-run legyen, és csak explicit kapcsolóval írjon**

```js
if (!process.argv.includes('--apply')) {
  console.log('Dry run only')
  process.exit(0)
}
```

- [ ] **Step 4: Futtasd a JS syntax checket**

Run: `node --check scripts/repair-osztaly-import.mjs`
Expected: no syntax errors, exit code 0

- [ ] **Step 5: Futtasd a dry runt**

Run: `node scripts/repair-osztaly-import.mjs --dry-run`
Expected: prints current vs expected class summary, and `101` is excluded from expected list

- [ ] **Step 6: Futtasd a whitespace/patch ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 7: Commit**

```bash
git add scripts/repair-osztaly-import.mjs
git commit -m "Add class repair dry-run script"
```

### Task 6: Végső integrációs ellenőrzés

**Files:**
- Verify only

- [ ] **Step 1: Futtasd a teljes whitespace ellenőrzést**

Run: `git diff --check`
Expected: no output, exit code 0

- [ ] **Step 2: Futtasd újra a JS syntax ellenőrzéseket**

Run: `node --check importer.js`
Expected: no syntax errors

Run: `node --check scripts/repair-osztaly-import.mjs`
Expected: no syntax errors

- [ ] **Step 3: Futtasd a javító script dry-runját friss állapotban**

Run: `node scripts/repair-osztaly-import.mjs --dry-run`
Expected: összegzés a javítandó osztályokról, `101` nem várt osztályként jelenik meg

- [ ] **Step 4: Ha elérhető a PHP futtató, linteld az érintett fájlokat**

Run: `php -l ticky-backend/api/osztalyok.php`
Expected: `No syntax errors detected`

Run: `php -l ticky-backend/api/osztaly_orarend.php`
Expected: `No syntax errors detected`

Run: `php -l ticky-backend/pages/support.php`
Expected: `No syntax errors detected`

If `php` not found: document that limitation in the final report instead of claiming lint success.

- [ ] **Step 5: Commit az utolsó integrált állapotra, ha még van nem commitolt változás**

```bash
git add .
git commit -m "Finalize osztaly follow-up fixes"
```
