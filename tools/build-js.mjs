#!/usr/bin/env node
/**
 * VIRA CRM — JS concatenation.
 *
 * The client host cannot run a Node build. Alpine is already a self-contained
 * build, so "bundling" here means writing the vendor file followed by the
 * application file into one script in the order they must execute.
 *
 * Output: public/build/js/app.js
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const PARTS = [
  'public/build/js/vendor/alpine/alpine-csp.min.js',
  'resources/js/app.js',
  // Responsive list tables: turns a wide table into cards below 768px. Kept in
  // its own file because it is a self-contained progressive enhancement that
  // runs independently of the interface behaviours above.
  'resources/js/table-cards.js',
];

const OUT = path.join(ROOT, 'public/build/js/app.js');

const chunks = PARTS.map((rel) => {
  const abs = path.join(ROOT, rel);
  if (!fs.existsSync(abs)) throw new Error(`missing script: ${rel}`);
  return `/* ==== ${rel} ==== */\n` + fs.readFileSync(abs, 'utf8');
});

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, chunks.join('\n;\n') + '\n', 'utf8');

const kb = (fs.statSync(OUT).size / 1024).toFixed(1);
console.log(`built public/build/js/app.js — ${kb} KB from ${PARTS.length} parts`);
