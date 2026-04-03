import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import {
  isValidClassCode,
  isRoomToken,
  normalizeToken,
  sortClassCodes,
  splitCompoundValue,
  uniqueNormalized,
} from './osztaly-rules.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname, '..')
const SOURCE_FILE = resolve(ROOT, 'tanárok.js')
const ENV_FILE = resolve(ROOT, '.env')
const APPLY = process.argv.includes('--apply')

const NAP = { 'Hétfő': 1, 'Kedd': 2, 'Szerda': 3, 'Csütörtök': 4, 'Péntek': 5 }
const ORA = { '07:30': 1, '08:20': 2, '09:15': 3, '10:15': 4, '11:10': 5, '12:05': 6, '12:50': 7, '13:40': 8 }

function loadEnvFile(file) {
  if (!existsSync(file)) return {}

  const env = {}
  const lines = readFileSync(file, 'utf8').split(/\r?\n/)
  for (const line of lines) {
    const trimmed = line.trim()
    if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) continue
    const idx = trimmed.indexOf('=')
    const key = trimmed.slice(0, idx).trim()
    const value = trimmed.slice(idx + 1).trim().replace(/^['"]|['"]$/g, '')
    env[key] = value
  }
  return env
}

function loadScheduleData(file) {
  const txt = readFileSync(file, 'utf8')
  const start = txt.indexOf('SCHEDULE_DATA')
  if (start < 0) throw new Error('SCHEDULE_DATA nem található a tanárok.js fájlban')

  const arrayStart = txt.indexOf('[', start)
  let depth = 0
  let arrayEnd = -1
  for (let i = arrayStart; i < txt.length; i++) {
    if (txt[i] === '[') depth++
    else if (txt[i] === ']' && --depth === 0) {
      arrayEnd = i
      break
    }
  }

  const entries = []
  const blockRe = /\{([^}]+)\}/g
  const kvRe = /(\w+)\s*:\s*['"]([^'"]*)['"]/g
  let block
  while ((block = blockRe.exec(txt.slice(arrayStart, arrayEnd + 1))) !== null) {
    const entry = {}
    let kv
    kvRe.lastIndex = 0
    while ((kv = kvRe.exec(block[1])) !== null) {
      entry[kv[1]] = kv[2]
    }
    if (entry.teacher && entry.room && entry.class && entry.day && entry.start && entry.end) {
      entries.push(entry)
    }
  }

  return entries
}

function buildExpectedLessons(entries) {
  const expected = []

  for (const entry of entries) {
    const day = NAP[entry.day]
    if (!day) continue

    const rooms = splitCompoundValue(entry.room).map(normalizeToken).filter(isRoomToken)
    const classes = splitCompoundValue(entry.class).map(normalizeToken).filter(isValidClassCode)

    for (const room of rooms) {
      for (const classCode of classes) {
        expected.push({
          teacher: normalizeToken(entry.teacher),
          room,
          osztaly: classCode,
          tantargy: normalizeToken(entry.subject),
          het_napja: day,
          ora_sorszam: ORA[entry.start] ?? null,
          kezdes: entry.start,
          vegzes: entry.end,
          aktiv: true,
        })
      }
    }
  }

  return expected
}

function rowSignature(row) {
  return [
    row.terem_id,
    row.tanar_id,
    normalizeToken(row.osztaly),
    normalizeToken(row.tantargy),
    row.het_napja,
    row.ora_sorszam ?? '',
    row.kezdes,
    row.vegzes,
    row.aktiv === false ? '0' : '1',
  ].join('|')
}

function chunk(values, size) {
  const result = []
  for (let i = 0; i < values.length; i += size) {
    result.push(values.slice(i, i + size))
  }
  return result
}

const fileEnv = loadEnvFile(ENV_FILE)
const SUPABASE_URL = process.env.SUPABASE_URL || fileEnv.SUPABASE_URL || ''
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_KEY || fileEnv.SUPABASE_SERVICE_KEY || ''

async function supabaseRequest(path, options = {}) {
  const response = await fetch(`${SUPABASE_URL}/rest/v1/${path}`, {
    ...options,
    headers: {
      apikey: SUPABASE_SERVICE_KEY,
      Authorization: `Bearer ${SUPABASE_SERVICE_KEY}`,
      'Content-Type': 'application/json',
      ...(options.headers ?? {}),
    },
  })

  const text = await response.text()
  if (!response.ok) {
    throw new Error(`Supabase hiba (${response.status}): ${text.slice(0, 300)}`)
  }

  return text ? JSON.parse(text) : null
}

async function fetchTable(table, params) {
  const search = new URLSearchParams(params).toString()
  return supabaseRequest(`${table}?${search}`)
}

async function insertRows(table, rows) {
  if (!rows.length) return
  await supabaseRequest(table, {
    method: 'POST',
    headers: { Prefer: 'return=minimal' },
    body: JSON.stringify(rows),
  })
}

async function deleteRowsByIds(table, ids) {
  if (!ids.length) return
  const search = new URLSearchParams({
    id: `in.(${ids.join(',')})`,
  }).toString()

  await supabaseRequest(`${table}?${search}`, {
    method: 'DELETE',
    headers: { Prefer: 'return=minimal' },
  })
}

function printSummary(label, values) {
  console.log(`${label}: ${values.length}`)
  if (values.length) {
    console.log(values.join(', '))
  }
}

async function run() {
  const entries = loadScheduleData(SOURCE_FILE)
  const expectedLessons = buildExpectedLessons(entries)
  const expectedClasses = uniqueNormalized(expectedLessons.map(item => item.osztaly)).sort(sortClassCodes)

  console.log(`Forrásbejegyzések: ${entries.length}`)
  console.log(`Várt órarend sorok: ${expectedLessons.length}`)
  console.log(`Várt osztályok: ${expectedClasses.length}`)
  console.log(`Tartalmaz 101-et: ${expectedClasses.includes('101') ? 'igen' : 'nem'}`)
  console.log(`9. évfolyam: ${expectedClasses.filter(code => code.startsWith('9.')).sort(sortClassCodes).join(', ')}`)

  if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
    console.log('Nincs SUPABASE_URL vagy SUPABASE_SERVICE_KEY beállítva, ezért csak forrásösszegzés készült.')
    return
  }

  const [teachers, rooms, currentRows] = await Promise.all([
    fetchTable('tanarok', { select: 'id,rovid_nev', limit: '10000' }),
    fetchTable('termek', { select: 'id,terem_szam', limit: '10000' }),
    fetchTable('orarendek', { select: 'id,terem_id,tanar_id,osztaly,tantargy,het_napja,ora_sorszam,kezdes,vegzes,aktiv', limit: '20000' }),
  ])

  const teacherMap = new Map(teachers.map(item => [normalizeToken(item.rovid_nev), item.id]))
  const roomMap = new Map(rooms.map(item => [normalizeToken(item.terem_szam), item.id]))

  const missingTeacherRefs = new Set()
  const missingRoomRefs = new Set()
  const expectedRows = []

  for (const row of expectedLessons) {
    const tanar_id = teacherMap.get(row.teacher)
    const terem_id = roomMap.get(row.room)

    if (!tanar_id) {
      missingTeacherRefs.add(row.teacher)
      continue
    }

    if (!terem_id) {
      missingRoomRefs.add(row.room)
      continue
    }

    expectedRows.push({
      terem_id,
      tanar_id,
      osztaly: row.osztaly,
      tantargy: row.tantargy,
      het_napja: row.het_napja,
      ora_sorszam: row.ora_sorszam,
      kezdes: row.kezdes,
      vegzes: row.vegzes,
      aktiv: true,
    })
  }

  const invalidRows = currentRows.filter(row => !isValidClassCode(row.osztaly))
  const validCurrentRows = currentRows.filter(row => isValidClassCode(row.osztaly))
  const currentClassList = uniqueNormalized(validCurrentRows.map(row => row.osztaly)).sort(sortClassCodes)
  const missingClasses = expectedClasses.filter(code => !currentClassList.some(existing => existing.toLowerCase() === code.toLowerCase()))

  const currentSignatures = new Set(validCurrentRows.map(rowSignature))
  const missingRows = expectedRows.filter(row => !currentSignatures.has(rowSignature(row)))

  console.log(`Supabase jelenlegi valid osztályok: ${currentClassList.length}`)
  console.log(`Hibás osztály-sorok törlésre: ${invalidRows.length}`)
  console.log(`Hiányzó osztálykódok: ${missingClasses.length}`)
  console.log(`Hiányzó órarend sorok: ${missingRows.length}`)

  if (missingTeacherRefs.size) {
    printSummary('Hiányzó tanár referenciák', [...missingTeacherRefs].sort())
  }
  if (missingRoomRefs.size) {
    printSummary('Hiányzó terem referenciák', [...missingRoomRefs].sort())
  }
  if (missingClasses.length) {
    printSummary('Hiányzó osztályok', missingClasses.sort(sortClassCodes))
  }

  if (!APPLY) {
    console.log('Dry run kész. Használj --apply kapcsolót a javításhoz.')
    return
  }

  for (const ids of chunk(invalidRows.map(row => row.id), 200)) {
    await deleteRowsByIds('orarendek', ids)
  }

  for (const rowsChunk of chunk(missingRows, 500)) {
    await insertRows('orarendek', rowsChunk)
  }

  console.log(`Törölt hibás sorok: ${invalidRows.length}`)
  console.log(`Beszúrt hiányzó sorok: ${missingRows.length}`)
}

run().catch(error => {
  console.error(error.message)
  process.exit(1)
})
