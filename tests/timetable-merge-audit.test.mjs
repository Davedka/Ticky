import assert from 'node:assert/strict'
import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import {
  buildExpectedLessons,
  loadScheduleData,
} from '../scripts/import-source.mjs'
import {
  isValidClassCode,
  normalizeToken,
  splitCompoundValue,
} from '../scripts/osztaly-rules.mjs'

const ROOT = resolve(import.meta.dirname, '..')

function findTeachersFile(dir) {
  return readdirSync(dir).find((name) =>
    /tan.*rok.*\.js$/i.test(name.normalize('NFD').replace(/[^\x00-\x7F]/g, ''))
  )
}

function readRepoFile(path) {
  return readFileSync(resolve(ROOT, path), 'utf8')
}

const rootTeachersFile = findTeachersFile(ROOT)
const backendTeachersFile = findTeachersFile(resolve(ROOT, 'ticky-backend'))

assert.ok(rootTeachersFile, 'repo root tanárok.js should exist')
assert.ok(backendTeachersFile, 'backend tanárok.js should exist')

const rootTeachersSource = readFileSync(resolve(ROOT, rootTeachersFile), 'utf8')
const backendTeachersSource = readFileSync(resolve(ROOT, 'ticky-backend', backendTeachersFile), 'utf8')

assert.equal(
  rootTeachersSource,
  backendTeachersSource,
  'root and backend tanárok.js copies should stay identical'
)

const parsedEntries = loadScheduleData(resolve(ROOT, rootTeachersFile))
const builtLessons = buildExpectedLessons(parsedEntries)

const slot7Lesson = builtLessons.find(
  (lesson) =>
    lesson.teacher === 'ÁSZJ'
    && lesson.osztaly === '10.c'
    && lesson.het_napja === 2
    && lesson.kezdes === '12:55'
)

assert.ok(slot7Lesson, 'expected a parsed 12:55 lesson from tanárok.js')
assert.equal(slot7Lesson.ora_sorszam, 7, '12:55 lessons should map to the 7th period')

function collectRawSourceAnomalies(sourceText) {
  const timeStart = sourceText.indexOf('TIME_SLOTS')
  const timeArrayStart = sourceText.indexOf('[', timeStart)
  let depth = 0
  let timeArrayEnd = -1
  for (let index = timeArrayStart; index < sourceText.length; index++) {
    if (sourceText[index] === '[') depth++
    else if (sourceText[index] === ']' && --depth === 0) {
      timeArrayEnd = index
      break
    }
  }

  const timeBody = sourceText.slice(timeArrayStart, timeArrayEnd + 1)
  const validTimePairs = new Set(
    [...timeBody.matchAll(/\{\s*period:\s*\d+,\s*start:\s*'([^']+)',\s*end:\s*'([^']+)'\s*\}/g)]
      .map((match) => `${match[1]}-${match[2]}`)
  )

  const scheduleStart = sourceText.indexOf('SCHEDULE_DATA')
  const scheduleArrayStart = sourceText.indexOf('[', scheduleStart)
  depth = 0
  let scheduleArrayEnd = -1
  for (let index = scheduleArrayStart; index < sourceText.length; index++) {
    if (sourceText[index] === '[') depth++
    else if (sourceText[index] === ']' && --depth === 0) {
      scheduleArrayEnd = index
      break
    }
  }

  const anomalies = []
  const scheduleBody = sourceText.slice(scheduleArrayStart, scheduleArrayEnd + 1)
  const blocks = [...scheduleBody.matchAll(/\{([^}]*)\}/gs)]

  for (const [blockIndex, blockMatch] of blocks.entries()) {
    const props = [...blockMatch[1].matchAll(/(\w+)\s*:\s*['"]([^'"]*)['"]/g)]
    const keys = props.map((match) => match[1])
    const duplicates = [...new Set(keys.filter((key, index) => keys.indexOf(key) !== index))]
    const entry = Object.fromEntries(props.map((match) => [match[1], match[2]]))

    if (entry.start && entry.end && !validTimePairs.has(`${entry.start}-${entry.end}`)) {
      anomalies.push({
        type: 'invalid-time',
        index: blockIndex + 1,
        teacher: entry.teacher,
        class: entry.class,
        room: entry.room,
        start: entry.start,
        end: entry.end,
      })
    }

    if (duplicates.length > 0) {
      anomalies.push({
        type: 'duplicate-keys',
        index: blockIndex + 1,
        teacher: entry.teacher,
        class: entry.class,
        room: entry.room,
        duplicates,
      })
    }

    if (entry.class) {
      const invalidClassTokens = splitCompoundValue(entry.class)
        .map(normalizeToken)
        .filter(Boolean)
        .filter((token) => !isValidClassCode(token))

      if (invalidClassTokens.length > 0) {
        anomalies.push({
          type: 'invalid-class-token',
          index: blockIndex + 1,
          teacher: entry.teacher,
          class: entry.class,
          invalidClassTokens,
        })
      }
    }
  }

  return anomalies
}

assert.deepEqual(
  collectRawSourceAnomalies(rootTeachersSource),
  [],
  'tanárok.js should not contain duplicate keys, invalid class tokens, or invalid slot times'
)

const osztalyApiPhp = readRepoFile('ticky-backend/api/osztaly_orarend.php')
const tanarApiPhp = readRepoFile('ticky-backend/api/tanar_orarend.php')
const sourceHelperPhp = readRepoFile('ticky-backend/utils/tanarok_source.php')
const teremApiPhp = readRepoFile('ticky-backend/api/terem.php')
const kijelzoPagePhp = readRepoFile('ticky-backend/pages/kijelzo.php')
const helpersPhp = readRepoFile('ticky-backend/utils/helpers.php')
const tanarPagePhp = readRepoFile('ticky-backend/pages/tanar.php')
const importSourceScript = readRepoFile('scripts/import-source.mjs')

assert.match(
  osztalyApiPhp,
  /json_response\(\['osztaly'\s*=>\s*\$result\['osztaly'\],\s*'orak'\s*=>\s*merge_consecutive_orak\(\$orak\)\]\);/s,
  'class source fallback should merge consecutive lessons before returning'
)

assert.match(
  sourceHelperPhp,
  /function\s+ticky_source_group_teacher_lessons\s*\([\s\S]*?\$grouped\s*=\s*\[\];[\s\S]*?'csoportok'/,
  'teacher source fallback should group same-time parallel lessons into csoportok'
)

assert.match(
  sourceHelperPhp,
  /function\s+ticky_source_teacher_day_schedule\s*\([\s\S]*?ticky_source_group_teacher_lessons\(\$lessons\)/,
  'teacher source fallback should delegate grouped lesson building through the shared helper'
)

assert.match(
  tanarApiPhp,
  /merge_consecutive_orak\(\$source_schedule\['orak'\]\s*\?\?\s*\[\]\)/,
  'teacher fallback should still merge grouped source lessons into double-hours'
)

assert.match(
  teremApiPhp,
  /\$orak\s*=\s*merge_consecutive_orak\(\$orak\);/,
  'single-room API should merge consecutive identical lessons before current/next selection'
)

assert.match(
  helpersPhp,
  /function\s+ticky_group_merge_signature\s*\(/,
  'merge helper should expose a canonical signature for grouped lessons'
)

assert.match(
  helpersPhp,
  /\$last_group_signature\s*===\s*\$curr_group_signature/,
  'merge helper should be able to merge consecutive grouped lessons when the groups actually match'
)

assert.match(
  kijelzoPagePhp,
  /\{kezdes:'12:55',vegzes:'13:35'\}/,
  'hallway display should use the updated 12:55 seventh-period slot'
)

assert.match(
  tanarPagePhp,
  /if\s*\(a\.is_csoport\)/,
  'teacher page should render grouped current lessons explicitly'
)

assert.match(
  tanarPagePhp,
  /o\.csoportok\.map\(/,
  'teacher page should render grouped lesson rows in the daily list'
)

assert.match(
  importSourceScript,
  /'12:55':\s*7/,
  'import-source period map should recognize the updated 12:55 slot'
)

console.log('timetable-merge-audit assertions passed')
