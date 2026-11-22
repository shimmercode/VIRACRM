#!/usr/bin/env node
/**
 * Design-system and RTL audit of the built stylesheet.
 *
 * This checks the CSS the browser will actually receive, not the sources:
 * missing tokens, hard-coded colours that bypass the token system, logical
 * (RTL-aware) properties, focus visibility, and reduced-motion handling.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const CSS = path.join(ROOT, 'public/build/css/app.css');
const css = fs.readFileSync(CSS, 'utf8');

const results = [];
const check = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id}  ${detail}`);
};

// ── Required tokens from the design direction ────────────────────────────
const tokens = {
  '--vira-primary': '#450073',
  '--vira-ink': '#211B2B',
  '--vira-bg': '#F6F5F8',
  '--vira-surface': '#FFFFFF',
  '--vira-gold': '#D2AB67',
};

for (const [name, value] of Object.entries(tokens)) {
  const re = new RegExp(`${name}\\s*:\\s*${value}`, 'i');
  check(`T-TOK-${name.replace('--vira-', '').toUpperCase()}`, re.test(css), `${name} = ${value}`);
}

// ── Font is local and declared ───────────────────────────────────────────
check('T-TOK-FONT', /font-family\s*:\s*[^;]*Vazirmatn/i.test(css), 'Vazirmatn in font stack');
check('T-TOK-FONTFILE', /@font-face/.test(css) && /Vazirmatn-Regular\.woff2/.test(css),
  '@font-face points at the local woff2');

// ── No remote resources (host must not call out) ─────────────────────────
const remote = css.match(/url\(\s*['"]?(https?:)?\/\//g) || [];
check('T-TOK-NOREMOTE', remote.length === 0, `remote url() references: ${remote.length}`);

// ── RTL: prefers logical properties ──────────────────────────────────────
const logical = (css.match(/\b(inset-inline-(start|end)|margin-inline|padding-inline|border-inline|text-align:\s*(start|end))/g) || []).length;
check('T-RTL-01', logical >= 20, `logical (inline-start/end) declarations: ${logical}`);

const physical = (css.match(/\b(margin|padding)-(left|right)\s*:/g) || []);
check('T-RTL-02', physical.length <= 4, `physical left/right margin/padding: ${physical.length} (want ≤4)`);

// ── Focus visibility is never removed without a replacement ──────────────
//
// `outline: none` is acceptable ONLY when the same rule block supplies a
// visible alternative (a focus ring shadow or an explicit outline width).
// Counting bare occurrences would flag correct code; this checks pairs.
const rules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)];

let bareOutlineNone = 0;
let replacedFocus = 0;

for (const [, selector, body] of rules) {
  if (!/outline\s*:\s*none/.test(body)) continue;

  const replaced = /box-shadow\s*:\s*var\(--vira-focus-ring/.test(body)
    || /outline\s*:\s*[1-9]px/.test(body);

  if (replaced) replacedFocus++;
  else {
    bareOutlineNone++;
    console.log(`      bare outline:none -> ${selector.trim().slice(0, 70)}`);
  }
}

const focusVisible = (css.match(/:focus-visible/g) || []).length;

check('T-A11Y-01', bareOutlineNone === 0,
  `outline:none always paired with a focus ring: ${replacedFocus} replaced, ${bareOutlineNone} bare`);
check('T-A11Y-02', focusVisible >= 8, `:focus-visible rules: ${focusVisible}`);

// ── Reduced motion honoured ──────────────────────────────────────────────
check('T-A11Y-03', /prefers-reduced-motion/.test(css), 'prefers-reduced-motion block present');

// ── Breakpoints required by §12 ──────────────────────────────────────────
//
// §12 asks for the layout to be verified at 1280+, 1024, 768 and ≤480. The
// stylesheet expresses those as desktop-first max-width queries at 1023, 767
// and 479 — each one is the boundary *below* the listed size.
const mediaQueries = [...css.matchAll(/@media[^{]+/g)].map((m) => m[0].trim());

for (const [id, bp, why] of [
  ['T-BP-1024', '1023px', 'tablet / small laptop'],
  ['T-BP-768', '767px', 'phone landscape'],
  ['T-BP-480', '479px', 'phone portrait'],
]) {
  check(id, css.includes(bp), `max-width ${bp} (${why})`);
}

check('T-BP-COUNT', mediaQueries.length >= 3, `media queries: ${mediaQueries.length}`);

// ── Component coverage required by §7 ────────────────────────────────────
//
// Each entry is a class the Blade views emit. Checking the emitted name (not a
// convenient near-name) is the point: an audit that passes with `.v-icon-btn`
// while the markup says `.v-iconbtn` proves nothing.
const components = [
  ['button', '.v-btn'],
  ['icon button', '.v-iconbtn'],
  ['input', '.v-input'],
  ['textarea', '.v-textarea'],
  ['select', '.v-select'],
  ['checkbox', '.v-check'],
  ['badge/status', '.v-badge'],
  ['card', '.v-card'],
  ['data table', '.v-table'],
  ['filter bar', '.v-filterbar'],
  ['breadcrumb', '.v-breadcrumb'],
  ['pagination', '.v-pagination'],
  ['menu/dropdown', '.v-menu'],
  ['toast/alert', '.v-alert'],
  ['avatar', '.v-avatar'],
  ['sidebar', '.v-sidebar'],
  ['topbar', '.v-topbar'],
  ['form section', '.v-fsection'],
  ['activity timeline', '.v-timeline'],
  ['empty state', '.v-empty'],
  ['stats', '.v-stat'],
  ['detail grid', '.v-detail-grid'],
  ['mobile drawer', '.v-drawer'],
  ['definition list', '.v-dl'],
];

for (const [name, sel] of components) {
  check(`T-COMP-${name.replace(/[^a-z]/gi, '').toUpperCase()}`, css.includes(sel), `selector ${sel}`);
}

// ── No hard-coded hex outside the token declarations ─────────────────────
//
// A colour written directly into a component will not respond to a theme or a
// brand change, and is the usual way a design system rots. Only the token
// block and pure black/white transparency fallbacks are exempt.
// Comments record why a colour changed (e.g. "darkened from #8b8494"), and a
// hex in a comment is documentation, not a style. Strip comments first so the
// check tests the cascade rather than the prose.
const cssCode = css.replace(/\/\*[\s\S]*?\*\//g, '');

const strayHex = [...cssCode.matchAll(/#[0-9a-f]{3,8}\b/gi)]
  .map((m) => m[0])
  .filter((h) => !/^#(fff|ffffff|000|000000)$/i.test(h))
  // Ignore anything inside the :root token block.
  .filter((h) => !new RegExp(`--vira-[a-z-]+\\s*:\\s*${h}`, 'i').test(css));

check('T-TOK-NOSTRAY', strayHex.length === 0,
  strayHex.length === 0
    ? 'no stray hex colours outside tokens'
    : `stray hex: ${[...new Set(strayHex)].slice(0, 8).join(', ')}`);

console.log('\n--- summary ---');
const pass = results.filter((r) => r.ok).length;
console.log(`${pass}/${results.length} passed  (${(css.length / 1024).toFixed(1)} KB stylesheet)`);

fs.writeFileSync(
  path.join(ROOT, '.arena', 'css-audit.json'),
  JSON.stringify(results, null, 2)
);

process.exit(pass === results.length ? 0 : 1);
