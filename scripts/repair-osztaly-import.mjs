import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import {
  buildExpectedLessons,
  collectExpectedClassCodes,
  loadMergedEnv,
  loadScheduleData,
  reconcileScheduleRows,
  scheduleRowSignature,
} from './import-source.mjs'
import { isValidClassCode, normalizeToken, sortClassCodes, uniqueNormalized } from './osztaly-rules.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname, '..')
const SOURCE_FILE = resolve(ROOT, 'tanárok.js')
const APPLY = process.argv.includes('--apply')

function chunk(values, size) {
  const result = []
  for (let i = 0; i < values.length; i += size) {
    result.push(values.slice(i, i + size))
  }
  return result
}

const fileEnv = loadMergedEnv(ROOT)
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

async function fetchAllTableRows(table, select, pageSize = 1000) {
  const rows = []
  for (let offset = 0; ; offset += pageSize) {
    const batch = await fetchTable(table, {
      select,
      limit: String(pageSize),
      offset: String(offset),
    })
    rows.push(...batch)
    if (batch.length < pageSize) {
      return rows
    }
  }
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
  const expectedClasses = collectExpectedClassCodes(expectedLessons)

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
    fetchAllTableRows('orarendek', 'id,terem_id,tanar_id,osztaly,tantargy,het_napja,ora_sorszam,kezdes,vegzes,aktiv'),
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
  const dedupedExpectedRows = []
  const expectedSignatures = new Set()
  for (const row of expectedRows) {
    const signature = scheduleRowSignature(row)
    if (expectedSignatures.has(signature)) continue
    expectedSignatures.add(signature)
    dedupedExpectedRows.push(row)
  }
  const { missingRows, extraRows } = reconcileScheduleRows(validCurrentRows, dedupedExpectedRows)

  console.log(`Supabase jelenlegi valid osztályok: ${currentClassList.length}`)
  console.log(`Hibás osztály-sorok törlésre: ${invalidRows.length}`)
  console.log(`Hiányzó osztálykódok: ${missingClasses.length}`)
  console.log(`Hiányzó órarend sorok: ${missingRows.length}`)
  console.log(`Többlet / duplikált órarend sorok törlésre: ${extraRows.length}`)

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
  for (const ids of chunk(extraRows.map(row => row.id), 200)) {
    await deleteRowsByIds('orarendek', ids)
  }

  for (const rowsChunk of chunk(missingRows, 500)) {
    await insertRows('orarendek', rowsChunk)
  }

  console.log(`Törölt hibás sorok: ${invalidRows.length}`)
  console.log(`Törölt többlet / duplikált sorok: ${extraRows.length}`)
  console.log(`Beszúrt hiányzó sorok: ${missingRows.length}`)
}

run().catch(error => {
  console.error(error.message)
  process.exit(1)
})
