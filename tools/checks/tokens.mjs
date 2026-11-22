#!/usr/bin/env node
/**
 * Verify every `var(--vira-*)` reference resolves to a declared token.
 *
 * An undefined custom property does not fall back to a default: the whole
 * declaration becomes invalid at computed-value time. A padding written as
 * `var(--vira-space-7) var(--vira-space-7) var(--vira-space-9)` therefore
 * silently collapses to 0, which is how the phone layout lost its gutters.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dir = path.join(ROOT, 'resources/css');
const css = fs.readdirSync(dir)
  .filter((f) => f.endsWith('.css'))
  .map((f) => fs.readFileSync(path.join(dir, f), 'utf8'))
  .join('\n');

const declared = new Set([...css.matchAll(/^\s*(--vira-[\w-]+)\s*:/gm)].map((m) => m[1]));

const used = new Map();
for (const m of css.matchAll(/var\(\s*(--vira-[\w-]+)/g)) {
  if (!used.has(m[1])) used.set(m[1], 0);
  used.set(m[1], used.get(m[1]) + 1);
}

const missing = [...used.entries()]
  .filter(([name]) => !declared.has(name))
  .sort((a, b) => b[1] - a[1]);

console.log(`declared: ${declared.size}  referenced: ${used.size}  undefined: ${missing.length}\n`);

for (const [name, count] of missing) {
  console.log(`  ${name.padEnd(28)} used ${count}×`);
}

const unused = [...declared].filter((d) => !used.has(d));
if (unused.length) {
  console.log(`\n  declared but never used: ${unused.length}`);
  for (const u of unused.slice(0, 12)) console.log(`    ${u}`);
}

process.exit(missing.length === 0 ? 0 : 1);
