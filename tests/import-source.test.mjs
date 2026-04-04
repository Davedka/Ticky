import assert from 'node:assert/strict'
import { resolve } from 'node:path'

import {
  buildExpectedLessons,
  collectExpectedClassCodes,
  loadScheduleData,
  normalizeScheduleTime,
  reconcileScheduleRows,
  scheduleRowSignature,
} from '../scripts/import-source.mjs'

const ROOT = resolve(import.meta.dirname, '..')
const SOURCE_FILE = resolve(ROOT, 'tanárok.js')

const entries = loadScheduleData(SOURCE_FILE)
const lessons = buildExpectedLessons(entries)
const classes = collectExpectedClassCodes(lessons)

for (const classCode of ['9.a', '9.b', '9.c', '9.d', '9.e']) {
  assert.ok(classes.includes(classCode), `missing expected class ${classCode}`)
}

assert.equal(classes.includes('101'), false)

assert.equal(normalizeScheduleTime('07:30:00'), '07:30')
assert.equal(
  scheduleRowSignature({
    terem_id: 'room-1',
    tanar_id: 'teacher-1',
    osztaly: '9.b',
    tantargy: 'mny',
    het_napja: 1,
    ora_sorszam: 1,
    kezdes: '07:30:00',
    vegzes: '08:10:00',
    aktiv: true,
  }),
  scheduleRowSignature({
    terem_id: 'room-1',
    tanar_id: 'teacher-1',
    osztaly: '9.b',
    tantargy: 'mny',
    het_napja: 1,
    ora_sorszam: 1,
    kezdes: '07:30',
    vegzes: '08:10',
    aktiv: true,
  })
)

const reconcileResult = reconcileScheduleRows(
  [
    {
      id: 'current-a',
      terem_id: 'room-1',
      tanar_id: 'teacher-1',
      osztaly: '9.b',
      tantargy: 'mny',
      het_napja: 1,
      ora_sorszam: 1,
      kezdes: '07:30:00',
      vegzes: '08:10:00',
      aktiv: true,
    },
    {
      id: 'current-b',
      terem_id: 'room-1',
      tanar_id: 'teacher-1',
      osztaly: '9.b',
      tantargy: 'mny',
      het_napja: 1,
      ora_sorszam: 1,
      kezdes: '07:30:00',
      vegzes: '08:10:00',
      aktiv: true,
    },
  ],
  [
    {
      terem_id: 'room-1',
      tanar_id: 'teacher-1',
      osztaly: '9.b',
      tantargy: 'mny',
      het_napja: 1,
      ora_sorszam: 1,
      kezdes: '07:30',
      vegzes: '08:10',
      aktiv: true,
    },
  ]
)
assert.equal(reconcileResult.missingRows.length, 0)
assert.equal(reconcileResult.extraRows.length, 1)
assert.equal(reconcileResult.extraRows[0].id, 'current-b')

console.log('import-source assertions passed')
