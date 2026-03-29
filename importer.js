// importer.js – Ticky Importer v6 (gyors, bulk insert)
// Futtatás: node importer.js
// Előfeltétel: setup.sql lefuttatva egyszer a Supabase SQL Editor-ban

import { createClient } from '@supabase/supabase-js'
import { readFileSync } from 'fs'
import { config } from 'dotenv'

config()

const { SUPABASE_URL, SUPABASE_SERVICE_KEY } = process.env
if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
  console.error('Hiányzó .env: SUPABASE_URL vagy SUPABASE_SERVICE_KEY')
  process.exit(1)
}

const sb = createClient(SUPABASE_URL, SUPABASE_SERVICE_KEY, {
  auth: { autoRefreshToken: false, persistSession: false }
})

const NAP = { 'Hétfő':1, 'Kedd':2, 'Szerda':3, 'Csütörtök':4, 'Péntek':5 }
const ORA = { '07:30':1,'08:20':2,'09:15':3,'10:15':4,'11:10':5,'12:05':6,'12:50':7,'13:40':8 }
const CLASS_RE = /^\d+\.[a-zA-Z]/

// ─── Segédfüggvények ─────────────────────────────────────

const sleep = ms => new Promise(r => setTimeout(r, ms))

async function countRows(table) {
  const { count } = await sb.from(table).select('*', { count: 'exact', head: true })
  return count ?? 0
}

// ─── TÖRLÉS ──────────────────────────────────────────────

async function deleteAll() {
  console.log('┌─ TÖRLÉS ───────────────────────────────┐')
  
  // 1. kísérlet: RPC TRUNCATE CASCADE
  const { data, error } = await sb.rpc('truncate_all_data')
  
  if (!error) {
    await sleep(600)
    const counts = await Promise.all(
      ['tanarok','termek','orarendek'].map(async t => ({ t, n: await countRows(t) }))
    )
    const mind0 = counts.every(x => x.n === 0)
    counts.forEach(x => console.log(`│  ${x.t.padEnd(12)}: ${x.n === 0 ? '✓ üres' : `✗ ${x.n} maradt`}`))
    if (mind0) { console.log('└────────────────────────────────────────┘\n'); return true }
  } else {
    console.log(`│  RPC hiba: ${error.message}`)
    console.log('│  → Futtasd le a setup.sql-t az SQL Editor-ban!')
  }

  // 2. kísérlet: kézi törlés helyes sorrendben
  console.log('│  Fallback: kézi törlés...')
  const tablak = ['aktualis_orak','napi_orarend','orak_rendje','orarendek','termek','tanarok']
  for (const t of tablak) {
    const n = await countRows(t)
    if (n === 0) continue
    await sb.from(t).delete().not('id', 'is', null)
    await sleep(200)
    const maradt = await countRows(t)
    console.log(`│  ${t.padEnd(18)}: ${maradt === 0 ? '✓' : `✗ ${maradt} maradt`}`)
  }
  console.log('└────────────────────────────────────────┘\n')
  return true
}

// ─── JS FÁJL BEOLVASÁS ───────────────────────────────────

function loadData(file) {
  const txt = readFileSync(file, 'utf-8')
  const start = txt.indexOf('SCHEDULE_DATA')
  if (start < 0) throw new Error('SCHEDULE_DATA nem található')
  
  const a = txt.indexOf('[', start)
  let d = 0, e = -1
  for (let i = a; i < txt.length; i++) {
    if (txt[i] === '[') d++
    else if (txt[i] === ']' && --d === 0) { e = i; break }
  }
  
  const entries = []
  const bRe = /\{([^}]+)\}/g
  const kRe = /(\w+)\s*:\s*['"]([^'"]*)['"]/g
  let b
  while ((b = bRe.exec(txt.slice(a, e+1))) !== null) {
    const o = {}; let k
    kRe.lastIndex = 0
    while ((k = kRe.exec(b[1])) !== null) o[k[1]] = k[2]
    if (o.teacher && o.room && o.day) entries.push(o)
  }
  return entries
}

// ─── SLASH SZÉTBONTÁS ────────────────────────────────────

const split = v => v.includes(' ') ? [v.trim()] : v.split('/').map(x=>x.trim()).filter(Boolean)
const isRoom = v => !CLASS_RE.test(v)

// ─── BULK INSERT ─────────────────────────────────────────

async function bulkInsert(table, rows, batchSize = 200) {
  let ok = 0
  for (let i = 0; i < rows.length; i += batchSize) {
    const { error } = await sb.from(table).insert(rows.slice(i, i + batchSize))
    if (error) console.log(`\n  ! ${table} insert hiba (batch ${i}): ${error.message}`)
    else ok += Math.min(batchSize, rows.length - i)
    process.stdout.write(`\r  → ${ok}/${rows.length} (${table})   `)
  }
  console.log()
  return ok
}

// ─── FŐ FOLYAMAT ─────────────────────────────────────────

async function run() {
  console.log('╔══════════════════════════════════════════╗')
  console.log('║    Ticky Importer v6 – Bulk & Fast       ║')
  console.log('╚══════════════════════════════════════════╝\n')
  
  // 1. Törlés
  await deleteAll()
  
  // 1.5 Várakozás – ellenőrizheted Supabase-ben a törlést
  console.log('⏳ Várakozás 10 másodpercet (ellenőrizheted Supabase-ben)...')
  for (let i = 10; i > 0; i--) {
    process.stdout.write(`\r   Folytatás ${i} másodperc múlva... `)
    await sleep(1000)
  }
  console.log('\r   Indítás!                          \n')

  // 2. Adatok beolvasása
  console.log('┌─ ADATOK ───────────────────────────────────')
  const entries = loadData('./tanárok.js')
  console.log(`│  ${entries.length} bejegyzés beolvasva`)
  
  // 3. Összes egyedi tanár és terem kinyerése
  const tanarSet = new Set()
  const teremSet = new Set()
  
  for (const e of entries) {
    tanarSet.add(e.teacher)
    split(e.room).filter(isRoom).forEach(r => teremSet.add(r))
  }
  
  console.log(`│  ${tanarSet.size} egyedi tanár`)
  console.log(`│  ${teremSet.size} egyedi terem`)
  console.log('└────────────────────────────────────────────\n')
  
  // 4. Tanárok bulk insert
  console.log('┌─ TANÁROK INSERT ───────────────────────────')
  const tanarRows = [...tanarSet].map(nev => ({ rovid_nev: nev }))
  await bulkInsert('tanarok', tanarRows, 100)
  
  // 5. Termek bulk insert
  console.log('┌─ TERMEK INSERT ────────────────────────────')
  const teremRows = [...teremSet].map(szam => ({ terem_szam: szam }))
  await bulkInsert('termek', teremRows, 100)
  
  // 6. ID-k lekérése (egyszer, nem egyenként)
  console.log('┌─ ID-K LEKÉRÉSE ────────────────────────────')
  
  const { data: tanarData } = await sb.from('tanarok').select('id, rovid_nev').limit(10000)
  const { data: teremData } = await sb.from('termek').select('id, terem_szam').limit(10000)
  
  const tanarMap = Object.fromEntries(tanarData.map(x => [x.rovid_nev, x.id]))
  const teremMap = Object.fromEntries(teremData.map(x => [x.terem_szam, x.id]))
  
  console.log(`│  ${Object.keys(tanarMap).length} tanár ID betöltve`)
  console.log(`│  ${Object.keys(teremMap).length} terem ID betöltve`)
  console.log('└────────────────────────────────────────────\n')
  
  // 7. Órarend sorok összeállítása
  console.log('┌─ ÓRAREND SOROK ÖSSZEÁLLÍTÁSA ──────────────')
  const oraRows = []
  const hibak = []
  
  for (const e of entries) {
    const nap = NAP[e.day]
    if (!nap) { hibak.push(`Ismeretlen nap: ${e.day}`); continue }
    
    const tanarId = tanarMap[e.teacher]
    if (!tanarId) { hibak.push(`Nem találom: ${e.teacher}`); continue }
    
    const termek = split(e.room).filter(isRoom)
    const osztalyok = split(e.class)
    
    for (const teremSzam of termek) {
      const teremId = teremMap[teremSzam]
      if (!teremId) { hibak.push(`Nincs terem ID: ${teremSzam}`); continue }
      
      for (const osztaly of osztalyok) {
        oraRows.push({
          terem_id:    teremId,
          tanar_id:    tanarId,
          osztaly,
          tantargy:    e.subject,
          het_napja:   nap,
          ora_sorszam: ORA[e.start] ?? null,
          kezdes:      e.start,
          vegzes:      e.end,
          aktiv:       true,
        })
      }
    }
  }
  
  console.log(`│  ${oraRows.length} órarend sor előkészítve`)
  console.log('└────────────────────────────────────────────\n')
  
  // 8. Órarend bulk insert
  console.log('┌─ ÓRARENDEK INSERT ─────────────────────────')
  const feltoltott = await bulkInsert('orarendek', oraRows, 200)
  console.log('└────────────────────────────────────────────\n')
  
  // 9. Összefoglaló
  console.log('╔══════════════════════════════════════════╗')
  console.log('║              IMPORT KÉSZ ✓               ║')
  console.log('╠══════════════════════════════════════════╣')
  console.log(`║  Tanárok       : ${String(tanarSet.size).padEnd(22)}║`)
  console.log(`║  Termek        : ${String(teremSet.size).padEnd(22)}║`)
  console.log(`║  Órarend sorok : ${String(feltoltott).padEnd(22)}║`)
  console.log(`║  Hibák         : ${String(hibak.length).padEnd(22)}║`)
  console.log('╚══════════════════════════════════════════╝')
  
  if (hibak.length > 0) {
    console.log('\nHibák:')
    hibak.slice(0, 15).forEach(h => console.log(`  ! ${h}`))
  }
}

run().catch(e => { console.error('KRITIKUS HIBA:', e.message); process.exit(1) })