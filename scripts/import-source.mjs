import { existsSync, readFileSync } from 'node:fs'
import { homedir } from 'node:os'
import { resolve } from 'node:path'

import {
  isRoomToken,
  isValidClassCode,
  normalizeToken,
  sortClassCodes,
  splitCompoundValue,
  uniqueNormalized,
} from './osztaly-rules.mjs'

export const NAP = { 'Hétfő': 1, 'Kedd': 2, 'Szerda': 3, 'Csütörtök': 4, 'Péntek': 5 }
export const ORA = { '07:30': 1, '08:20': 2, '09:15': 3, '10:15': 4, '11:10': 5, '12:05': 6, '12:50': 7, '12:55': 7, '13:40': 8 }

export function loadEnvFile(file) {
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

export function envCandidateFiles(root) {
  return [
    resolve(root, '.env'),
    resolve(homedir(), 'ticky', '.env'),
  ]
}

export function loadMergedEnv(root) {
  const merged = {}
  for (const file of envCandidateFiles(root)) {
    Object.assign(merged, loadEnvFile(file))
  }
  return merged
}

export function loadScheduleData(file) {
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

export function buildExpectedLessons(entries) {
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

export function collectExpectedClassCodes(lessons) {
  return uniqueNormalized(lessons.map(item => item.osztaly)).sort(sortClassCodes)
}

export function normalizeScheduleTime(value) {
  return normalizeToken(value).slice(0, 5)
}

export function scheduleRowSignature(row) {
  return [
    row.terem_id,
    row.tanar_id,
    normalizeToken(row.osztaly),
    normalizeToken(row.tantargy),
    row.het_napja,
    row.ora_sorszam ?? '',
    normalizeScheduleTime(row.kezdes),
    normalizeScheduleTime(row.vegzes),
    row.aktiv === false ? '0' : '1',
  ].join('|')
}

export function reconcileScheduleRows(currentRows, expectedRows) {
  const buckets = new Map()

  for (const row of currentRows) {
    const signature = scheduleRowSignature(row)
    const bucket = buckets.get(signature) ?? []
    bucket.push(row)
    buckets.set(signature, bucket)
  }

  const missingRows = []
  for (const row of expectedRows) {
    const signature = scheduleRowSignature(row)
    const bucket = buckets.get(signature)
    if (bucket && bucket.length > 0) {
      bucket.shift()
      continue
    }
    missingRows.push(row)
  }

  const extraRows = []
  for (const bucket of buckets.values()) {
    extraRows.push(...bucket)
  }

  return { missingRows, extraRows }
}
