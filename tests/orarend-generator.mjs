
// orarend-generator.mjs — orarend_csoportonkent.xlsx  ->  tanárok.js (SCHEDULE_DATA, csoport-id-vel)
//
// Telepítés:  npm i xlsx
// Használat:  node orarend-generator.mjs orarend_csoportonkent.xlsx [kimenet.js]
//
// MIT VÁR a füllektől (a képeid layoutja alapján):
//   - A1 cella:  "11.e – 1. csoport"        -> osztály = "11.e", csoport = "1"
//   - 2. sor:    fejléc (Nap | 1. | ... | 8.) -> figyelmen kívül, fix idősávok alább
//   - A oszlop:  Hé / Ke / Sz / Cs / Pé
//   - B..I:      1..8. óra
//   - cella:     tantárgy (+ esetleg "-X. csoport" sor), KÜLÖN sorban "TEREM TANÁR"
//
// Amit nem tud tisztán parse-olni (cellán belüli nyelvi a1/a2/a3 bontás, sérült cella):
//   -> WARNING stderr-re + "// ELLENŐRIZD" a generált sorban (vagy kihagyás).

import * as XLSX from 'xlsx';
import { writeFileSync } from 'node:fs';

const SRC = process.argv[2];
const OUT = process.argv[3] ?? 'tanarok.generated.js';
if (!SRC) {
  console.error('Használat: node orarend-generator.mjs <xlsx> [kimenet.js]');
  process.exit(1);
}

const SLOTS = {
  1: ['07:30', '08:10'], 2: ['08:20', '09:05'], 3: ['09:15', '10:00'], 4: ['10:15', '11:00'],
  5: ['11:10', '11:55'], 6: ['12:05', '12:50'], 7: ['12:55', '13:35'], 8: ['13:40', '14:20'],
};
const DAY = {
  'Hé': 'Hétfő', 'Ke': 'Kedd', 'Sz': 'Szerda', 'Cs': 'Csütörtök', 'Pé': 'Péntek',
  'Hétfő': 'Hétfő', 'Kedd': 'Kedd', 'Szerda': 'Szerda', 'Csütörtök': 'Csütörtök', 'Péntek': 'Péntek',
};
const TITLE_RE = /^(.+?)\s*[–-]\s*(\d+)\.?\s*csoport/i;

const warnings = [];
const warn = m => warnings.push(m);

// "TEREM TANÁR" sor: van benne számjegy ÉS nagybetűs token
const isLocationLine = l => /\d/.test(l) && /[A-ZÁÉÍÓÖŐÚÜŰ]/.test(l);

function parseCell(text, ref) {
  const lines = String(text).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
  if (!lines.length) return null;

  const rest = [];
  let locLine = null;
  for (const l of lines) {
    if (/csoport/i.test(l) && !isLocationLine(l)) continue; // tiszta csoport-marker sor
    if (isLocationLine(l)) locLine = l;                     // az utolsó ilyen a lokáció
    else rest.push(l);
  }
  if (!locLine) {
    warn(`${ref}: nincs felismerhető "TEREM TANÁR" sor -> "${lines.join(' | ')}"`);
    return null;
  }

  const complex = /\//.test(locLine);
  const tok = locLine.split(/\s+/).filter(Boolean);
  const teacher = tok[tok.length - 1] ?? '';
  const room = tok.slice(0, -1).join(' ').trim() || '?';
  if (complex) warn(`${ref}: összetett cella (nyelvi/al-bontás?), kézi ellenőrzés kell -> "${locLine}"`);

  const subject = rest.join(' ').replace(/^-+|-+$/g, '').replace(/\s+/g, ' ').trim();
  if (!subject) warn(`${ref}: üres tantárgy -> "${lines.join(' | ')}"`);

  return { teacher, room, subject, complex };
}

const wb = XLSX.readFile(SRC);
const out = [];
let sheetsDone = 0, sheetsSkipped = 0;

for (const name of wb.SheetNames) {
  const ws = wb.Sheets[name];
  const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: false });
  if (!rows.length) { sheetsSkipped++; continue; }

  const title = String(rows[0]?.[0] ?? '').trim();
  const m = title.match(TITLE_RE);
  if (!m) { sheetsSkipped++; continue; } // nem órarend-fül

  const klass = m[1].trim();
  const group = m[2];
  sheetsDone++;

  for (let r = 1; r < rows.length; r++) {
    const day = DAY[String(rows[r]?.[0] ?? '').trim()];
    if (!day) continue;
    for (let p = 1; p <= 8; p++) {
      const cell = rows[r][p];
      if (cell === undefined || String(cell).trim() === '') continue;
      const ref = `[${name}] ${day} ${p}.óra`;
      const parsed = parseCell(cell, ref);
      if (!parsed) continue;
      const [start, end] = SLOTS[p];
      out.push({
        teacher: parsed.teacher, room: parsed.room, class: klass,
        subject: parsed.subject, group, day, start, end,
        _check: parsed.complex,
      });
    }
  }
}

// kiírás a tanárok.js stílusában (egyszeres idézőjel)
const q = s => `'${String(s).replace(/'/g, "\\'")}'`;
const body = out.map(e => {
  const flag = e._check ? '  // ELLENŐRIZD' : '';
  return `  { teacher: ${q(e.teacher)}, room: ${q(e.room)}, class: ${q(e.class)}, `
       + `subject: ${q(e.subject)}, group: ${q(e.group)}, day: ${q(e.day)}, `
       + `start: ${q(e.start)}, end: ${q(e.end)} },${flag}`;
}).join('\n');

const file =
  `// Automatikusan generálva ebből: ${SRC}\n` +
  `// Generálás: ${new Date().toISOString()}\n` +
  `// FIGYELEM: az "// ELLENŐRIZD" sorok kézi átnézést igényelnek.\n\n` +
  `export const SCHEDULE_DATA = [\n${body}\n];\n`;
writeFileSync(OUT, file, 'utf8');

console.error(`\n=== orarend-generator ===`);
console.error(`Feldolgozott fül: ${sheetsDone}  |  kihagyott fül: ${sheetsSkipped}`);
console.error(`Generált sor: ${out.length}  (ebből ellenőrzendő: ${out.filter(e => e._check).length})`);
console.error(`Figyelmeztetés: ${warnings.length}`);
for (const w of warnings.slice(0, 60)) console.error('  ! ' + w);
if (warnings.length > 60) console.error(`  … és még ${warnings.length - 60}`);
console.error(`\nKimenet: ${OUT}`);
