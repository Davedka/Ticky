import { spawnSync } from 'node:child_process'
import { resolve } from 'node:path'

const ROOT = resolve(import.meta.dirname, '..')
const SUITES = ['tests/php/timetable_import.test.php']

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
