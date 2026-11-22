#!/usr/bin/env node
/**
 * WCAG contrast check on the real token pairs the interface uses.
 *
 * §12 requires adequate contrast. These are the actual foreground/background
 * pairs in the stylesheet, not a generic palette review.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const tokensCss = fs.readFileSync(path.join(ROOT, 'resources/css/tokens.css'), 'utf8');

function token(name) {
  const m = tokensCss.match(new RegExp(`${name}\\s*:\\s*(#[0-9a-f]{3,8})`, 'i'));
  return m ? m[1] : null;
}

function srgb(c) {
  const v = c / 255;
  return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
}

function luminance(hex) {
  let h = hex.replace('#', '');
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  const r = parseInt(h.slice(0, 2), 16);
  const g = parseInt(h.slice(2, 4), 16);
  const b = parseInt(h.slice(4, 6), 16);
  return 0.2126 * srgb(r) + 0.7152 * srgb(g) + 0.0722 * srgb(b);
}

function ratio(fg, bg) {
  const l1 = luminance(fg);
  const l2 = luminance(bg);
  const [a, b] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (a + 0.05) / (b + 0.05);
}

const pairs = [
  ['body text on page', '--vira-ink', '--vira-bg', 4.5],
  ['body text on surface', '--vira-ink', '--vira-surface', 4.5],
  ['secondary text on surface', '--vira-text-secondary', '--vira-surface', 4.5],
  ['muted text on surface', '--vira-text-muted', '--vira-surface', 4.5],
  ['primary on surface', '--vira-primary', '--vira-surface', 4.5],
  ['white on primary button', '#ffffff', '--vira-primary', 4.5],
  ['danger text on surface', '--vira-danger', '--vira-surface', 4.5],
  ['gold ink on gold soft', '--vira-gold-ink', '--vira-gold-soft', 4.5],
];

const results = [];
for (const [label, fgName, bgName, min] of pairs) {
  const fg = fgName.startsWith('#') ? fgName : token(fgName);
  const bg = bgName.startsWith('#') ? bgName : token(bgName);

  if (!fg || !bg) {
    results.push({ label, ok: false, detail: 'token missing' });
    console.log(`FAIL  ${label}  (token missing: ${!fg ? fgName : bgName})`);
    continue;
  }

  const r = ratio(fg, bg);
  const ok = r >= min;
  results.push({ label, ok, ratio: Number(r.toFixed(2)), fg, bg });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label.padEnd(28)} ${r.toFixed(2)}:1  (needs ${min}:1)  ${fg} on ${bg}`);
}

const pass = results.filter((r) => r.ok).length;
console.log(`\n${pass}/${results.length} pairs meet WCAG AA`);
process.exit(pass === results.length ? 0 : 1);
