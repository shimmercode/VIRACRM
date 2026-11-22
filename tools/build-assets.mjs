#!/usr/bin/env node
/**
 * VIRA CRM — asset build.
 *
 * Vite is not installed (npm registry is reachable, but the client host cannot
 * run a Node build step and adding one would put a build toolchain between us
 * and a shared-hosting deployment). Instead this resolves the @import chain and
 * concatenates the CSS in order, which is all the stylesheets need.
 *
 * Output: public/build/css/app.css
 *
 * Usage: node tools/build-assets.mjs [--watch]
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SRC = path.join(ROOT, 'resources/css');
const OUT_DIR = path.join(ROOT, 'public/build/css');
const OUT = path.join(OUT_DIR, 'app.css');

const seen = new Set();

function inline(file, depth = 0) {
  if (depth > 10) throw new Error(`@import loop at ${file}`);

  const abs = path.isAbsolute(file) ? file : path.join(SRC, file);
  if (seen.has(abs)) return '';
  seen.add(abs);

  if (!fs.existsSync(abs)) throw new Error(`missing stylesheet: ${abs}`);

  const css = fs.readFileSync(abs, 'utf8');
  const dir = path.dirname(abs);

  // Resolve @import at the top of the file, preserving order.
  return css.replace(/@import\s+['"]([^'"]+)['"]\s*;/g, (_, target) => {
    const resolved = path.resolve(dir, target);
    return inline(resolved, depth + 1);
  });
}

function build() {
  seen.clear();
  const started = Date.now();

  const css = [
    '/* VIRA CRM — generated bundle. Do not edit; edit resources/css/*.css */',
    inline('app.css'),
  ].join('\n');

  fs.mkdirSync(OUT_DIR, { recursive: true });
  fs.writeFileSync(OUT, css, 'utf8');

  const kb = (Buffer.byteLength(css) / 1024).toFixed(1);
  console.log(`built public/build/css/app.css — ${kb} KB in ${Date.now() - started}ms`);
}

build();

if (process.argv.includes('--watch')) {
  let timer = null;
  fs.watch(SRC, { recursive: true }, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      try {
        build();
      } catch (err) {
        console.error('build failed:', err.message);
      }
    }, 120);
  });
  console.log(`watching ${SRC} …`);
}
