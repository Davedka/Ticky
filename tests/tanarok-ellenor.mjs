#!/usr/bin/env node
// tanarok-ellenor.mjs — Ticky órarend adat-ellenőrző
// Használat:  node tanarok-ellenor.mjs [útvonal/tanárok.js]
// Nincs külső függőség. A tanárok.js-t ESM-ként importálja (export const ...).

import { pathToFileURL } from 'node:url';
import { existsSync } from 'node:fs';
import path from 'node:path';

const MAX_PER_KIND = 40; // hosszú listák levágása

// ── fájl megkeresése ────────────────────────────────────────────────
const candidates = process.argv[2]
  ? [process.argv[2]]
  : ['tanárok.js', 'tanarok.js', 'tana\u0301rok.js'];
let file = null;
for (const c of candidates) {
  const abs = path.resolve(process.cwd(), c);
  if (existsSync(abs)) { file = abs; break; }
}
if (!file) {
  console.error('Nem találom a tanárok.js-t. Add meg az útvonalat:');
  console.error('  node tanarok-ellenor.mjs ./valami/tanárok.js');
  process.exit(1);
}
const mod = await import(pathToFileURL(file).href);
const SCHEDULE_DATA = mod.SCHEDULE_DATA ?? [];
const TEACHER_NAMES = mod.TEACHER_NAMES ?? {};
const TEACHERS = mod.TEACHERS ?? [];

// ── kanonikus idősávok ──────────────────────────────────────────────
const SLOTS = [
  ['07:30','08:10'],['08:20','09:05'],['09:15','10:00'],['10:15','11:00'],
  ['11:10','11:55'],['12:05','12:50'],['12:55','13:35'],['13:40','14:20'],
];
const STARTS = new Map(SLOTS.map(([s], i) => [s, i + 1]));
const ENDS   = new Map(SLOTS.map(([, e], i) => [e, i + 1]));
const DAYS_OK = new Set(['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek']);

const problems = [];
const add = (kind, msg) => problems.push({ kind, msg });
const push = (map, k, v) => { if (!map.has(k)) map.set(k, []); map.get(k).push(v); };

// ── mezők + idősáv ──────────────────────────────────────────────────
SCHEDULE_DATA.forEach((e, i) => {
  const ref = `#${i} ${e.teacher}/${e.class}/${e.day} ${e.start}`;
  for (const k of ['teacher', 'room', 'class', 'subject', 'day', 'start', 'end'])
    if (e[k] === undefined || e[k] === '') add('hiányzó mező', `${ref}: üres "${k}"`);
  if (e.day && !DAYS_OK.has(e.day)) add('rossz nap', `${ref}: ismeretlen nap "${e.day}"`);
  const si = STARTS.get(String(e.start || '').slice(0, 5));
  const ei = ENDS.get(String(e.end || '').slice(0, 5));
  if (e.start && si === undefined) add('rossz idősáv', `${ref}: nem szabványos kezdés "${e.start}"`);
  if (e.end && ei === undefined) add('rossz idősáv', `${ref}: nem szabványos vég "${e.end}"`);
  if (si !== undefined && ei !== undefined && ei < si) add('fordított idő', `${ref}: vég < kezdés`);
});

// ── pontos duplikátumok ─────────────────────────────────────────────
const seen = new Map();
SCHEDULE_DATA.forEach((e, i) => {
  const key = [e.teacher, e.room, e.class, e.subject, e.day, e.start, e.end].join('|');
  if (seen.has(key)) add('duplikátum', `#${i} = #${seen.get(key)}  (${key})`);
  else seen.set(key, i);
});

// ── ütközések (tanár / terem / osztály egy idősávban) ───────────────
const slot = e => `${e.day} ${String(e.start || '').slice(0, 5)}`;
const byTeacher = new Map(), byRoom = new Map(), byClass = new Map();
SCHEDULE_DATA.forEach(e => {
  push(byTeacher, `${e.teacher}@@${slot(e)}`, e);
  for (const r of String(e.room || '').split('/').map(s => s.trim()).filter(Boolean))
    push(byRoom, `${r}@@${slot(e)}`, e);
  for (const c of String(e.class || '').split('/').map(s => s.trim()).filter(Boolean))
    push(byClass, `${c}@@${slot(e)}`, e);
});
for (const [k, arr] of byTeacher) {
  const rooms = new Set(arr.map(e => e.room));
  if (rooms.size > 1)
    add('tanár ütközés', `${k.split('@@')[0]} egyszerre több teremben (${k.split('@@')[1]}): ${[...rooms].join(', ')}`);
}
for (const [k, arr] of byRoom) {
  const t = new Set(arr.map(e => e.teacher));
  if (t.size > 1)
    add('terem ütközés', `${k.split('@@')[0]} terem párhuzamosan foglalt (${k.split('@@')[1]}): ${[...t].join(', ')}`);
}
for (const [k, arr] of byClass) {
  const v = new Set(arr.map(e => `${e.teacher}/${e.room}/${e.subject}`));
  if (v.size > 3)
    add('osztály ütközés', `${k.split('@@')[0]} – ${v.size} eltérő óra egy idősávban (${k.split('@@')[1]})`);
}

// ── tantárgyak ──────────────────────────────────────────────────────
const freq = new Map();
SCHEDULE_DATA.forEach(e => freq.set(e.subject, (freq.get(e.subject) || 0) + 1));
// sérült jelek: "csoport" beégve, kis betű után nagy "I" (OCR l→I), gyanúsan hosszú
const garbled = s => !!s && (/csoport/i.test(s) || /[a-zà-ÿ]I/.test(s) || s.length > 22);
for (const [s, n] of freq) if (garbled(s)) add('gyanús tantárgy', `"${s}" (${n}×) – sérült/elgépelt?`);

// ugyanaz a tantárgy többféleképp írva (pl. "tn" / "tn." / "hi" / "hI")
const formGroups = new Map();
for (const s of freq.keys()) push(formGroups, s.toLowerCase().replace(/\.+$/, '').trim(), s);
for (const [, set] of formGroups) {
  const u = [...new Set(set)];
  if (u.length > 1) add('inkonzisztens tantárgy', u.map(x => `"${x}"`).join(' / '));
}
const rare = [...freq].filter(([, n]) => n === 1).map(([s]) => s).filter(s => !garbled(s)).sort();

// ── tanárkódok kontra TEACHER_NAMES / TEACHERS ──────────────────────
for (const t of new Set(SCHEDULE_DATA.map(e => e.teacher))) {
  if (!(t in TEACHER_NAMES)) add('hiányzó tanárnév', `"${t}" – van órája, de nincs TEACHER_NAMES-ben`);
  if (TEACHERS.length && !TEACHERS.includes(t)) add('hiányzó a TEACHERS-ből', `"${t}"`);
}

// ── kimenet ─────────────────────────────────────────────────────────
const byKind = {};
for (const p of problems) (byKind[p.kind] ??= []).push(p.msg);

console.log(`\n=== Ticky órarend-ellenőrzés ===`);
console.log(`Fájl: ${file}`);
console.log(`Bejegyzés: ${SCHEDULE_DATA.length}  |  Talált probléma: ${problems.length}\n`);
for (const kind of Object.keys(byKind).sort()) {
  const list = byKind[kind];
  console.log(`■ ${kind} (${list.length})`);
  for (const m of list.slice(0, MAX_PER_KIND)) console.log(`   - ${m}`);
  if (list.length > MAX_PER_KIND) console.log(`   … és még ${list.length - MAX_PER_KIND}`);
  console.log('');
}
if (rare.length) {
  console.log(`■ ritka tantárgy – 1×, csak kézi ránézésre (${rare.length})`);
  console.log('   ' + rare.join(', ') + '\n');
}
if (!problems.length) console.log('Nincs automatikusan észlelt hiba. 🎉');
