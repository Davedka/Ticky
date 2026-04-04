// importer.js – Ticky Importer v7 (dependency-free)
// Futtatás: node importer.js
// Előfeltétel: setup.sql lefuttatva egyszer a Supabase SQL Editor-ban

import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import {
  buildExpectedLessons,
  collectExpectedClassCodes,
  loadMergedEnv,
  loadScheduleData,
} from './scripts/import-source.mjs'
import { normalizeToken } from './scripts/osztaly-rules.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname)
const SOURCE_FILE = resolve(ROOT, 'tanárok.js')
const fileEnv = loadMergedEnv(ROOT)

const SUPABASE_URL = process.env.SUPABASE_URL || fileEnv.SUPABASE_URL || ''
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_KEY || fileEnv.SUPABASE_SERVICE_KEY || ''

if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
  console.error('Hiányzó .env: SUPABASE_URL vagy SUPABASE_SERVICE_KEY')
  process.exit(1)
}

const sleep = ms => new Promise(resolvePromise => setTimeout(resolvePromise, ms))

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

async function countRows(table) {
  const response = await fetch(`${SUPABASE_URL}/rest/v1/${table}?select=*`, {
    method: 'HEAD',
    headers: {
      apikey: SUPABASE_SERVICE_KEY,
      Authorization: `Bearer ${SUPABASE_SERVICE_KEY}`,
      Prefer: 'count=exact',
    },
  })

  if (!response.ok) return 0
  return Number(response.headers.get('content-range')?.split('/')[1] ?? 0)
}

async function deleteAll() {
  console.log('┌─ TÖRLÉS ────────────────────────────────┐')

  let error = null
  try {
    await supabaseRequest('rpc/truncate_all_data', {
      method: 'POST',
      body: JSON.stringify({}),
    })
  } catch (rpcError) {
    error = rpcError
  }

  if (!error) {
    await sleep(600)
    const counts = await Promise.all(
      ['tanarok', 'termek', 'orarendek'].map(async table => ({ table, count: await countRows(table) }))
    )
    const allEmpty = counts.every(item => item.count === 0)
    counts.forEach(item => console.log(`│  ${item.table.padEnd(12)}: ${item.count === 0 ? '✓ üres' : `✗ ${item.count} maradt`}`))
    if (allEmpty) {
      console.log('└────────────────────────────────────────┘\n')
      return true
    }
  } else {
    console.log(`│  RPC hiba: ${error.message}`)
    console.log('│  → Futtasd le a setup.sql-t az SQL Editor-ban!')
  }

  console.log('│  Fallback: kézi törlés...')
  const tables = ['aktualis_orak', 'napi_orarend', 'orak_rendje', 'orarendek', 'termek', 'tanarok']
  for (const table of tables) {
    const count = await countRows(table)
    if (count === 0) continue
    await supabaseRequest(`${table}?id=not.is.null`, {
      method: 'DELETE',
      headers: { Prefer: 'return=minimal' },
    })
    await sleep(200)
    const remaining = await countRows(table)
    console.log(`│  ${table.padEnd(18)}: ${remaining === 0 ? '✓' : `✗ ${remaining} maradt`}`)
  }
  console.log('└────────────────────────────────────────┘\n')
  return true
}

async function bulkInsert(table, rows, batchSize = 200) {
  let inserted = 0
  for (let i = 0; i < rows.length; i += batchSize) {
    await supabaseRequest(table, {
      method: 'POST',
      headers: { Prefer: 'return=minimal' },
      body: JSON.stringify(rows.slice(i, i + batchSize)),
    })
    inserted += Math.min(batchSize, rows.length - i)
    process.stdout.write(`\r  → ${inserted}/${rows.length} (${table})   `)
  }
  console.log()
  return inserted
}

async function fetchTable(table, select) {
  return supabaseRequest(`${table}?select=${encodeURIComponent(select)}&limit=10000`)
}

async function run() {
  console.log('╔══════════════════════════════════════════╗')
  console.log('║    Ticky Importer v7 – Dependency-free  ║')
  console.log('╚══════════════════════════════════════════╝\n')

  await deleteAll()

  console.log('⏳ Várakozás 10 másodpercet (ellenőrizheted Supabase-ben)...')
  for (let i = 10; i > 0; i--) {
    process.stdout.write(`\r   Folytatás ${i} másodperc múlva... `)
    await sleep(1000)
  }
  console.log('\r   Indítás!                          \n')

  console.log('┌─ ADATOK ─────────────────────────────────')
  const entries = loadScheduleData(SOURCE_FILE)
  const expectedLessons = buildExpectedLessons(entries)
  const expectedClasses = collectExpectedClassCodes(expectedLessons)
  console.log(`│  ${entries.length} bejegyzés beolvasva`)
  console.log(`│  ${expectedClasses.length} várt osztály`)

  const teacherSet = new Set(expectedLessons.map(item => item.teacher))
  const roomSet = new Set(expectedLessons.map(item => item.room))

  console.log(`│  ${teacherSet.size} egyedi tanár`)
  console.log(`│  ${roomSet.size} egyedi terem`)
  console.log('└──────────────────────────────────────────\n')

  console.log('┌─ TANÁROK INSERT ─────────────────────────')
  const teacherRows = [...teacherSet].map(name => ({ rovid_nev: name }))
  await bulkInsert('tanarok', teacherRows, 100)

  console.log('┌─ TERMEK INSERT ──────────────────────────')
  const roomRows = [...roomSet].map(roomNumber => ({ terem_szam: roomNumber }))
  await bulkInsert('termek', roomRows, 100)

  console.log('┌─ ID-K LEKÉRÉSE ──────────────────────────')
  const [teacherData, roomData] = await Promise.all([
    fetchTable('tanarok', 'id,rovid_nev'),
    fetchTable('termek', 'id,terem_szam'),
  ])

  const teacherMap = Object.fromEntries(teacherData.map(item => [normalizeToken(item.rovid_nev), item.id]))
  const roomMap = Object.fromEntries(roomData.map(item => [normalizeToken(item.terem_szam), item.id]))

  console.log(`│  ${Object.keys(teacherMap).length} tanár ID betöltve`)
  console.log(`│  ${Object.keys(roomMap).length} terem ID betöltve`)
  console.log('└──────────────────────────────────────────\n')

  console.log('┌─ ÓRAREND SOROK ÖSSZEÁLLÍTÁSA ────────────')
  const lessonRows = []
  const errors = []

  for (const lesson of expectedLessons) {
    const teacherId = teacherMap[normalizeToken(lesson.teacher)]
    if (!teacherId) {
      errors.push(`Nem találom: ${lesson.teacher}`)
      continue
    }

    const roomId = roomMap[normalizeToken(lesson.room)]
    if (!roomId) {
      errors.push(`Nincs terem ID: ${lesson.room}`)
      continue
    }

    lessonRows.push({
      terem_id: roomId,
      tanar_id: teacherId,
      osztaly: lesson.osztaly,
      tantargy: lesson.tantargy,
      het_napja: lesson.het_napja,
      ora_sorszam: lesson.ora_sorszam,
      kezdes: lesson.kezdes,
      vegzes: lesson.vegzes,
      aktiv: true,
    })
  }

  console.log(`│  ${lessonRows.length} órarend sor előkészítve`)
  console.log('└──────────────────────────────────────────\n')

  console.log('┌─ ÓRARENDEK INSERT ───────────────────────')
  const insertedLessons = await bulkInsert('orarendek', lessonRows, 200)
  console.log('└──────────────────────────────────────────\n')

  console.log('╔══════════════════════════════════════════╗')
  console.log('║              IMPORT KÉSZ ✓              ║')
  console.log('╠══════════════════════════════════════════╣')
  console.log(`║  Tanárok       : ${String(teacherSet.size).padEnd(22)}║`)
  console.log(`║  Termek        : ${String(roomSet.size).padEnd(22)}║`)
  console.log(`║  Órarend sorok : ${String(insertedLessons).padEnd(22)}║`)
  console.log(`║  Hibák         : ${String(errors.length).padEnd(22)}║`)
  console.log('╚══════════════════════════════════════════╝')

  if (errors.length > 0) {
    console.log('\nHibák:')
    errors.slice(0, 15).forEach(error => console.log(`  ! ${error}`))
  }
}

run().catch(error => {
  console.error('KRITIKUS HIBA:', error.message)
  process.exit(1)
})
