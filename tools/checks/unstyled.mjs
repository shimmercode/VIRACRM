#!/usr/bin/env node
/**
 * Find classes used in Blade markup that no stylesheet defines.
 *
 * A class with no rule is a silent visual bug: the markup looks intentional but
 * renders as an unstyled block. This lists every occurrence so the gap is
 * quantified rather than discovered by squinting at a screenshot.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

// ── classes defined in CSS ───────────────────────────────────────────────
/*
 * Follow app.css's real @import graph instead of a hardcoded file list.
 *
 * The list used to be ['tokens','app','components','layout','pages'], so a new
 * stylesheet was invisible to this check even when the build included it — the
 * review surface's rules were reported as unstyled while rendering correctly.
 * A check that disagrees with the build is worse than no check: it trains you to
 * ignore it.
 */
function loadCss(file, seen = new Set()) {
  const abs = path.join(ROOT, 'resources/css', file);
  if (seen.has(abs) || !fs.existsSync(abs)) return '';
  seen.add(abs);

  const source = fs.readFileSync(abs, 'utf8');

  return source.replace(/@import\s+['"]([^'"]+)['"]\s*;/g, (_, target) => loadCss(target, seen));
}

const css = loadCss('app.css');

const defined = new Set();
for (const m of css.matchAll(/\.([a-zA-Z][\w-]*)/g)) defined.add(m[1]);

// ── classes used in Blade ────────────────────────────────────────────────
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = path.join(dir, e.name);
    return e.isDirectory() ? walk(p) : p.endsWith('.blade.php') ? [p] : [];
  });
}

const used = new Map(); // class -> Set(files)

for (const file of walk(path.join(ROOT, 'resources/views'))) {
  const source = fs.readFileSync(file, 'utf8');
  const rel = path.relative(ROOT, file);

  // class="a b c" and :class / @class arrays
  for (const m of source.matchAll(/class="([^"]+)"/g)) {
    /*
     * Strip Blade expressions from the whole attribute BEFORE tokenising.
     *
     * Doing it per token, as this used to, shredded any expression containing a
     * space: `class="v-input{{ $errors->has('x') ? ' v-input--invalid' : '' }}"`
     * split into the fragments `'`, `v-input--invalid'` and `''`, and the
     * middle one was reported as an unstyled class with a trailing quote. That
     * was a false positive on a class the stylesheet does define — which is the
     * worst kind of finding, because it trains you to ignore this check.
     */
    const literal = m[1].replace(/\{\{.*?\}\}/g, ' ');
    for (const token of literal.split(/\s+/)) {
      const clean = token.trim();
      if (!clean || !/^[a-zA-Z]/.test(clean)) continue;
      // A leftover quote, brace or colon means the token came from an
      // expression this scanner does not evaluate, not from markup.
      if (/['"{}$@:]/.test(clean)) continue;
      /*
       * A token ending in `-` or `_` is a modifier whose name was inside the
       * expression we just removed — `class="v-badge--{{ $tone }}"` leaves
       * `v-badge--`. That is not a class name, it is the shape of one, so it
       * must not be reported as unstyled.
       */
      if (/[-_]$/.test(clean)) continue;
      if (!used.has(clean)) used.set(clean, new Set());
      used.get(clean).add(rel);
    }
  }
}

// Classes that are intentionally style-free: JS hooks, helpers provided by
// other systems, and Laravel's own error bag output.
const IGNORE = new Set([
  'is-active', 'is-invalid', 'is-disabled', 'is-loading', 'is-flipped', 'is-locked',
  'rtl-mirror', 'vira-no-scroll',
  // Invoice line rows: selectors for resources/js/app.js, which clones rows,
  // renumbers their inputs and writes the computed amount into the cell. They
  // carry no styles because they are not styling hooks (app.js:412, 438).
  'item-row', 'line-total',
]);

const missing = [...used.entries()]
  .filter(([cls]) => !defined.has(cls) && !IGNORE.has(cls))
  .sort((a, b) => a[0].localeCompare(b[0]));

console.log(`defined: ${defined.size}  used: ${used.size}  unstyled: ${missing.length}\n`);

for (const [cls, files] of missing) {
  console.log(`  .${cls.padEnd(26)} ${[...files].slice(0, 2).join(', ')}`);
}

process.exit(missing.length === 0 ? 0 : 1);
