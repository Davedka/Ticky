import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const ROOT = resolve(import.meta.dirname, '..')
const read = (path) => readFileSync(resolve(ROOT, path), 'utf8')

const indexPhp = read('ticky-backend/index.php')
const adminPage = read('ticky-backend/pages/admin.php')
const importApi = read('ticky-backend/api/admin_orarend_import.php')
const legacyImportApi = read('ticky-backend/api/admin_import.php')
const publishApi = read('ticky-backend/api/admin_orarend_publish.php')
const versionApi = read('ticky-backend/api/admin_orarend_verzio.php')
const versionsApi = read('ticky-backend/api/admin_orarend_verziok.php')
const templateApi = read('ticky-backend/api/admin_orarend_sablon.php')
const repoPhp = read('ticky-backend/utils/timetable_repo.php')
const classesApi = read('ticky-backend/api/osztalyok.php')
const migration = read('ticky-backend/sql/001_orarend_verziok.sql')

// ── Jogosultság ──────────────────────────────────────────────────

const adminEndpoints = {
  'admin_orarend_import.php': importApi,
  'admin_orarend_verziok.php': versionsApi,
  'admin_orarend_verzio.php': versionApi,
  'admin_orarend_publish.php': publishApi,
  'admin_orarend_sablon.php': templateApi,
  'admin_import.php': legacyImportApi,
}

for (const [name, source] of Object.entries(adminEndpoints)) {
  assert.match(source, /admin_can_see_ui\(\)/, `${name}: hiányzik az admin UI ellenőrzés`)
  assert.match(source, /require_admin_api_request\(\[/, `${name}: hiányzik a require_admin_api_request`)
}

// Az adatot módosító végpontok friss bejelentkezést kérnek.
for (const [name, source] of Object.entries({
  'admin_orarend_import.php': importApi,
  'admin_orarend_publish.php': publishApi,
  'admin_import.php': legacyImportApi,
})) {
  assert.match(source, /ticky_require_fresh_admin_auth\(\)/, `${name}: hiányzik a friss admin auth`)
}

assert.match(
  versionApi,
  /REQUEST_METHOD'\] === 'DELETE'[\s\S]{0,200}ticky_require_fresh_admin_auth\(\)/,
  'a draft törléshez friss admin auth kell'
)

// ── A feltöltés nem írhatja felül azonnal az élő órarendet ───────

assert.match(
  repoPhp,
  /'aktiv'\s*=>\s*false,\s*\/\/ draft/,
  'a draft sorokat aktiv = false értékkel kell beszúrni'
)

assert.ok(
  !/sb_request\(\s*'DELETE'\s*,\s*'orarendek'/.test(legacyImportApi),
  'a tanárok.js import nem törölheti közvetlenül az orarendek táblát'
)
assert.ok(
  !/sb_request\(\s*'DELETE'\s*,\s*'(termek|tanarok)'/.test(legacyImportApi),
  'a tanárok.js import nem törölheti a termek/tanarok táblát'
)
assert.match(
  legacyImportApi,
  /ticky_repo_create_draft\(/,
  'a tanárok.js import is draft verziót hoz létre'
)

// ── A publikálás egyetlen tranzakció ─────────────────────────────

assert.match(
  repoPhp,
  /rpc\/ticky_publish_orarend_verzio/,
  'a publikálás az atomikus adatbázis-függvényt hívja'
)
assert.match(
  migration,
  /create or replace function public\.ticky_publish_orarend_verzio/,
  'a migrációnak tartalmaznia kell a publikáló függvényt'
)
assert.match(
  migration,
  /create unique index if not exists orarend_verziok_egy_aktiv[\s\S]*?where statusz = 'active'/,
  'adatbázis szinten kell kikényszeríteni az egy aktív verziót'
)

// Hibás import nem publikálható.
assert.match(
  publishApi,
  /hibak_szama[\s\S]{0,200}nem publikálható/,
  'a publikálás előtt újra kell ellenőrizni az import hibaszámát'
)

// ── Verzió-szivárgás a publikus API-n ────────────────────────────

assert.match(
  classesApi,
  /sb_get\('orarendek', \['select' => 'osztaly', 'aktiv' => 'eq\.true'\]\)/,
  'az osztálylista csak az aktív órarendet olvashatja'
)

// A publikus végpont nem fogadhat el kliens oldali verzió paramétert.
for (const file of ['api/termek.php', 'api/terem.php', 'api/napirend.php', 'api/osztaly_orarend.php', 'api/tanar_orarend.php']) {
  const source = read(`ticky-backend/${file}`)
  assert.ok(
    !/verzio_id/.test(source),
    `${file}: a publikus API nem hivatkozhat verzio_id-ra`
  )
}

// ── Útvonalak ────────────────────────────────────────────────────

for (const route of [
  "/api/admin/orarend/import",
  "/api/admin/orarend/verziok",
  "/api/admin/orarend/sablon",
  "/api/admin/orarend/{id}/publish",
  "/api/admin/orarend/{id}/rollback",
  "/api/admin/orarend/{id}",
]) {
  assert.ok(indexPhp.includes(route), `hiányzó útvonal: ${route}`)
}

// A konkrét útvonalaknak meg kell előzniük a {id} mintát, különben elnyelné őket.
const idRouteAt = indexPhp.indexOf("match_route('/api/admin/orarend/{id}'")
for (const literal of ['/api/admin/orarend/import', '/api/admin/orarend/verziok', '/api/admin/orarend/sablon']) {
  assert.ok(
    indexPhp.indexOf(literal) < idRouteAt,
    `${literal} útvonalát elnyeli a {id} minta`
  )
}
assert.ok(
  indexPhp.indexOf("'/api/admin/orarend/{id}/publish'") < idRouteAt,
  'a publish útvonalat elnyeli a {id} minta'
)

// A publish/rollback módot a router állítja be, nem a query string.
assert.match(indexPhp, /\$_GET\['mode'\] = 'publish';/, 'a publish módot a router állítja be')
assert.match(indexPhp, /\$_GET\['mode'\] = 'rollback';/, 'a rollback módot a router állítja be')

// ── Admin felület ────────────────────────────────────────────────

assert.match(adminPage, /data-section="orarend"/, 'hiányzik az Órarend menüpont')
assert.match(adminPage, /id="section-orarend"/, 'hiányzik az Órarend szekció')
assert.match(adminPage, /orarend:loadOrarendVersions/, 'az Órarend szekciónak nincs betöltője')

// FormData nem mehet át JSON.stringify-on, különben elveszne a fájl.
assert.match(
  adminPage,
  /init\.body instanceof FormData/,
  'az adminFetch nem kezeli a FormData törzset'
)

// ── Service key nem szivároghat kliensre ─────────────────────────

for (const [name, source] of Object.entries({ ...adminEndpoints, 'pages/admin.php': adminPage })) {
  assert.ok(
    !/SUPABASE_SERVICE_KEY/.test(source) || !/<script/.test(source),
    `${name}: a service key nem kerülhet kliensoldali kódba`
  )
}
assert.ok(
  !/SUPABASE_SERVICE_KEY/.test(adminPage),
  'az admin oldal nem hivatkozhat a service key-re'
)

console.log('orarend-import.test.mjs: minden szerkezeti ellenőrzés rendben');
