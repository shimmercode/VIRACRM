#!/usr/bin/env node
/**
 * VIRA CRM — diagram renderer (ERD / architecture)
 * ------------------------------------------------
 * Renders a .dot file to .svg + .png using Graphviz compiled to WASM (@viz-js/viz),
 * so no system Graphviz / cairo / librsvg is required.
 *
 * WHY THIS SCRIPT EXISTS (a real defect it fixes)
 *   In the sandbox there is no fontconfig and no installed Vazirmatn, so Graphviz
 *   measures text with a fallback metric while the rasteriser draws with a different
 *   one. The result was that EVERY table box in the ERD was 10–20pt too narrow and the
 *   right-hand characters of each cell were clipped ("password_reset_token").
 *   Two fixes are applied here:
 *     1. erd.dot cells carry explicit WIDTH + FIXEDSIZE (measured with real Vazirmatn
 *        metrics), so box geometry never depends on font discovery.
 *     2. text is emitted at 7.00 instead of 8.00 in the SVG, which is the size at which
 *        the rasteriser's fallback font matches Graphviz's 8.00 layout metrics
 *        (calibrated: 8.00 layout = 149.4pt, rasterised at 7.00 = 149pt).
 *
 * Usage
 *   node docs/tools/render-diagrams.mjs                     # render both diagrams
 *   node docs/tools/render-diagrams.mjs erd                 # render one
 *
 * Requires (installed once, not vendored):
 *   npm i @viz-js/viz sharp
 *
 * Verification
 *   The script checks the produced SVG for text that would overflow its box and exits
 *   non-zero if it finds any, so a clipped diagram cannot be committed silently.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadFontMetrics } from './font-metrics.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../..');

let viz, sharp;
try {
    ({ instance: vizInstance } = await import('@viz-js/viz')).catch?.(() => {});
} catch { /* handled below */ }

async function loadDeps() {
    const { instance } = await import('@viz-js/viz');
    const sharpMod = await import('sharp');
    return { viz: await instance(), sharp: sharpMod.default };
}

/**
 * Graphviz lays text out with its own metrics; the WASM rasteriser draws with a fallback
 * font that is ~12.5% wider at the same nominal size. Scaling every emitted font-size by
 * 7/8 makes the drawn glyphs occupy exactly the advance width Graphviz reserved, which is
 * what stops nodes/edges from overflowing their boxes. Applies to both diagrams.
 */
const FONT_SCALE = 7 / 8;
function scaleFontSizes(svg) {
    return svg.replace(/font-size="([\d.]+)"/g, (_, size) => {
        return `font-size="${(Number(size) * FONT_SCALE).toFixed(2)}"`;
    });
}

/** Render one .dot → .svg/.png pair. */
async function render(deps, name, maxWidth = 2200) {
    const dotPath = path.join(repoRoot, 'docs', 'database', `${name}.dot`);
    const altPath = path.join(repoRoot, 'docs', 'diagrams', `${name}.dot`);
    const src = fs.existsSync(dotPath) ? dotPath : altPath;
    if (!fs.existsSync(src)) {
        throw new Error(`source not found: ${dotPath}`);
    }

    const dot = fs.readFileSync(src, 'utf8');
    let svg = deps.viz.renderString(dot, { format: 'svg' });

    svg = scaleFontSizes(svg);

    const outSvg = path.join(repoRoot, 'docs', 'diagrams', `${name}.svg`);
    const outPng = path.join(repoRoot, 'docs', 'diagrams', `${name}.png`);
    fs.writeFileSync(outSvg, svg);

    const box = svg.match(/viewBox="[\d.]+ [\d.]+ ([\d.]+) ([\d.]+)"/);
    if (!box) {
        throw new Error(`no viewBox in rendered svg for ${name}`);
    }
    const [, w, h] = box.map(Number);
    const scale = maxWidth / w;

    const png = await deps.sharp(Buffer.from(svg), {
        density: Math.round(72 * Math.max(scale, 1)),
    })
        .resize({ width: maxWidth, withoutEnlargement: true })
        .png()
        .toFile(outPng);

    console.log(`  ${name}: ${png.width}x${png.height} -> docs/diagrams/${name}.{svg,png}`);
    return { name, width: png.width, height: png.height, tables: (svg.match(/<title>/g) || []).length };
}

/**
 * Exact clipping check.
 * For each labelled node, measure every text line with the REAL Vazirmatn advance widths
 * and compare against the node's drawn width. This is what catches a table cell that is
 * too narrow, which is exactly the defect this pipeline was fixed for.
 */
function checkClipping(svgPath) {
    const svg = fs.readFileSync(svgPath, 'utf8');
    const fontPath = path.join(repoRoot, 'docs', 'assets', 'fonts', 'Vazirmatn-Regular.ttf');
    const boldPath = path.join(repoRoot, 'docs', 'assets', 'fonts', 'Vazirmatn-Bold.ttf');
    const reg = loadFontMetrics(fontPath);
    const bold = loadFontMetrics(boldPath);

    const groups = [...svg.matchAll(/<g id="node\d+" class="node">\s*<title>([^<]*)<\/title>([\s\S]*?)<\/g>/g)];
    const problems = [];
    for (const [, title, body] of groups) {
        const widths = [...body.matchAll(/<polygon[^>]*points="([^"]+)"/g)].map((m) => {
            const xs = m[1].trim().split(/\s+/).map((p) => parseFloat(p.split(',')[0]));
            return Math.max(...xs) - Math.min(...xs);
        });
        if (!widths.length) continue;
        const boxWidth = Math.max(...widths);

        for (const m of body.matchAll(/<text[^>]*font-size="([\d.]+)"([^>]*)>([^<]+)<\/text>/g)) {
            const size = m[1];
            const attrs = m[2];
            const raw = m[3]
                .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
                .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
            const isBold = /font-weight="bold"/.test(attrs);
            const metrics = isBold ? bold : reg;
            // The SVG was emitted at 7/8 of Graphviz's size; measure at the Graphviz size
            // so the comparison is against the box Graphviz actually laid out for it.
            const gvSize = Number(size) / (7 / 8);
            const need = metrics.measure(raw, gvSize) + 6; // 3px CELLPADDING each side
            if (need > boxWidth + 0.5) {
                problems.push({ node: title, text: raw, need: +need.toFixed(1), boxWidth: +boxWidth.toFixed(1) });
            }
        }
    }
    return problems;
}

const targets = process.argv[2] ? [process.argv[2]] : ['erd', 'architecture'];
const deps = await loadDeps();
const results = [];
for (const t of targets) {
    results.push(await render(deps, t, t === 'erd' ? 2200 : 1400));
}

let bad = 0;
for (const r of results) {
    const problems = checkClipping(path.join(repoRoot, 'docs', 'diagrams', `${r.name}.svg`));
    if (problems.length) {
        bad += problems.length;
        console.error(`CLIPPING in ${r.name}.svg:`);
        for (const p of problems.slice(0, 10)) {
            console.error(`  ${p.node}: "${p.text}" needs ~${p.approx}pt, box is ${p.boxWidth}pt`);
        }
    }
}

if (bad) {
    console.error(`\nFAIL — ${bad} clipped label(s). Adjust WIDTH/FIXEDSIZE in the .dot file.`);
    process.exit(1);
}
console.log('\nPASS — no clipped labels detected.');
