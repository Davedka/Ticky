// tests/php-suite.test.mjs
// A PHP oldali tesztek futtatása a Node teszt-sorozatból.
//
// Ha a gépen nincs PHP, a suite kihagyja magát (nem buktatja el a buildet),
// de a kihagyást hangosan kiírja, hogy ne tűnjön zöldnek egy meg nem futott teszt.

import { spawnSync } from 'node:child_process'
import { resolve } from 'node:path'

const ROOT = resolve(import.meta.dirname, '..')
const SUITES = [
  'tests/php/timetable_import.test.php',
  'tests/php/orarend_view.test.php',
  'tests/php/supabase_paging.test.php',
  'tests/php/mascot.test.php',
  'tests/php/valasz_cache.test.php',
  'tests/php/terem_allapot.test.php',
  'tests/php/orarend_nap_cache.test.php',
  'tests/php/support_repo.test.php',
]

const probe = spawnSync('php', ['--version'], { encoding: 'utf8' })

if (probe.error || probe.status !== 0) {
  console.warn('FIGYELEM: nincs elérhető php parancs, a PHP tesztek KIMARADTAK.')
  process.exit(0)
}

for (const suite of SUITES) {
  const result = spawnSync('php', [suite], { cwd: ROOT, stdio: 'inherit' })

  if (result.status !== 0) {
    console.error(`PHP teszt elbukott: ${suite}`)
    process.exit(result.status ?? 1)
  }
}
