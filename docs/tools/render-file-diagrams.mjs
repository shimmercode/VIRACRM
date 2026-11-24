#!/usr/bin/env node
/**
 * VIRA CRM — file-module diagram renderer
 * ---------------------------------------
 * Renders every docs/diagrams/file-module/*.dot to .svg + .png using the same
 * toolchain as render-diagrams.mjs (@viz-js/viz + sharp), so no system Graphviz,
 * cairo or librsvg is required in the sandbox.
 *
 * These diagrams are plain flow/context graphs, not ERD tables, so they do not
 * carry the fixed-size cells that render-diagrams.mjs calibrates for. They do
 * keep the same 7/8 font-scale correction: the WASM rasteriser draws with a
 * fallback font that is ~12.5% wider than Graphviz's layout metrics, and
 * without the correction labels overlap their boxes in the PNG.
 *
 * Usage
 *   node docs/tools/render-file-diagrams.mjs          # render all
 *   node docs/tools/render-file-diagrams.mjs 04       # render one by prefix
 *
 * Requires: npm i  (package.json already declares @viz-js/viz and sharp)
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../..');
const dir = path.join(repoRoot, 'docs', 'diagrams', 'file-module');

const { instance } = await import('@viz-js/viz');
const sharp = (await import('sharp')).default;
const viz = await instance();

const FONT_SCALE = 7 / 8;
const scaleFontSizes = (svg) =>
  svg.replace(/font-size="([\d.]+)"/g, (_, s) => `font-size="${(Number(s) * FONT_SCALE).toFixed(2)}"`);

const only = process.argv[2];
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.dot') && (!only || f.startsWith(only))).sort();

if (!files.length) {
  console.error(`no .dot files matched${only ? ` prefix "${only}"` : ''} in ${path.relative(repoRoot, dir)}`);
  process.exit(1);
}

let failed = 0;
for (const f of files) {
  const base = f.replace(/\.dot$/, '');
  const src = fs.readFileSync(path.join(dir, f), 'utf8');
  let svg;
  try {
    svg = scaleFontSizes(viz.renderString(src, { format: 'svg' }));
  } catch (e) {
    console.error(`FAIL  ${base}: ${String(e.message || e).split('\n')[0]}`);
    failed++;
    continue;
  }

  const box = svg.match(/viewBox="[\d.]+ [\d.]+ ([\d.]+) ([\d.]+)"/);
  if (!box) {
    console.error(`FAIL  ${base}: rendered SVG has no viewBox`);
    failed++;
    continue;
  }
  const [, w] = box.map(Number);
  const maxWidth = Math.min(2400, Math.max(1200, Math.round(w)));
  const scale = maxWidth / w;

  fs.writeFileSync(path.join(dir, `${base}.svg`), svg);
  const png = await sharp(Buffer.from(svg), { density: Math.round(72 * Math.max(scale, 1)) })
    .resize({ width: maxWidth, withoutEnlargement: true })
    .png()
    .toFile(path.join(dir, `${base}.png`));

  console.log(`OK    ${base.padEnd(28)} ${png.width}x${png.height}  svg=${svg.length}B`);
}

if (failed) {
  console.error(`\nFAIL — ${failed} diagram(s) did not render.`);
  process.exit(1);
}
console.log(`\nPASS — ${files.length} diagrams rendered to docs/diagrams/file-module/{svg,png}.`);
