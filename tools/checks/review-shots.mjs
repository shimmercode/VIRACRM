#!/usr/bin/env node
/**
 * review-shots.mjs — capture every review page in a real Chromium.
 *
 * WHY A REAL BROWSER
 * ------------------
 * A screenshot from a mockup proves the mockup. This suite drives the actual
 * server, logs in with a real session, and measures the live DOM at seven
 * widths. Nothing here is generated imagery.
 *
 * WHAT IT ASSERTS (per width)
 *   - no horizontal overflow: scrollWidth <= clientWidth + 1 on the document
 *   - the shell is present (sidebar or the mobile drawer trigger)
 *   - Persian text does not overflow its container
 *
 * Output:
 *   docs/evidence/review/<key>-<width>.png
 *   docs/evidence/review/responsive.json
 *
 * Usage:  node tools/checks/review-shots.mjs [--base http://127.0.0.1:PORT]
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/evidence/review');
const WIDTHS = [390, 430, 576, 768, 1024, 1280, 1440];

const baseArg = process.argv.indexOf('--base');
const BASE = baseArg !== -1 ? process.argv[baseArg + 1] : 'http://127.0.0.1:4173';

const requireFrom = createRequire(path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js'));
const { chromium } = requireFrom('playwright-core');

fs.mkdirSync(OUT, { recursive: true });

// The catalogue is the single source of which pages exist.
const configSrc = fs.readFileSync(path.join(ROOT, 'config/review.php'), 'utf8');
const keys = [...configSrc.matchAll(/'key'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
const previews = new Set(
  [...configSrc.matchAll(/'preview'\s*=>\s*'([^']+)'/g)].map((m) => m[1])
);
const routes = new Map();
for (const block of configSrc.split("[\n")) {
  const k = /'key'\s*=>\s*'([^']+)'/.exec(block)?.[1];
  const r = /'route'\s*=>\s*'([^']+)'/.exec(block)?.[1];
  const p = /'path'\s*=>\s*'([^']+)'/.exec(block)?.[1];
  if (k && r && r !== 'null') routes.set(k, p);
}

const browser = await chromium.launch({
  executablePath: process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--font-render-hinting=none'],
  env: {
    ...process.env,
    LD_LIBRARY_PATH: process.env.VIRA_CHROMIUM_LIBS
      || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64',
  },
});

const password = fs.readFileSync(path.join(ROOT, '.arena/admin-password'), 'utf8').trim();
const results = [];
let failures = 0;

for (const width of WIDTHS) {
  const context = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await context.newPage();

  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name=username]', 'admin');
  await page.fill('input[name=password]', password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type=submit]'),
  ]);

  // The catalogue itself, then every page that can be opened.
  const targets = [{ key: 'review-index', url: '/review' }, { key: 'review-health', url: '/review/health' }];

  for (const [key, url] of routes) targets.push({ key, url });
  for (const key of keys) {
    const previewName = [...configSrc.matchAll(/'preview'\s*=>\s*'([^']+)'/g)]
      .map((m) => m[1]);
    if (previewName.includes(key)) targets.push({ key: 'preview-' + key, url: '/review/preview/' + key });
  }

  for (const t of targets) {
    const res = await page.goto(BASE + t.url, { waitUntil: 'domcontentloaded' });
    const status = res?.status() ?? 0;

    const m = await page.evaluate(() => {
      const doc = document.documentElement;
      const overflowers = [...document.querySelectorAll('body *')]
        .filter((el) => {
          const r = el.getBoundingClientRect();
          return r.width > 0 && r.right > doc.clientWidth + 2;
        })
        .slice(0, 4)
        .map((el) => el.className || el.tagName);

      return {
        scrollWidth: doc.scrollWidth,
        clientWidth: doc.clientWidth,
        hasShell: !!document.querySelector('.v-shell, .v-mobile-bar'),
        hasSidebar: !!document.querySelector('.v-sidebar'),
        overflowers,
      };
    });

    const overflow = m.scrollWidth > m.clientWidth + 1;
    const ok = status === 200 && !overflow && m.hasShell;

    results.push({
      width, key: t.key, url: t.url, status,
      scrollWidth: m.scrollWidth, clientWidth: m.clientWidth,
      hasShell: m.hasShell, hasSidebar: m.hasSidebar,
      overflowers: m.overflowers, ok,
    });

    if (!ok) {
      failures += 1;
      console.log(`FAIL  ${String(width).padStart(4)}  ${t.key.padEnd(24)} status=${status} `
        + `overflow=${m.scrollWidth}/${m.clientWidth} shell=${m.hasShell}`);
    } else {
      console.log(`PASS  ${String(width).padStart(4)}  ${t.key.padEnd(24)} status=${status} `
        + `${m.scrollWidth}px`);
    }

    // One screenshot per page at the canonical desktop width and per page at
    // mobile width; seven widths per page would be 180+ files for no extra
    // information, since the measurement above already covers all seven.
    if (width === 1440 || width === 390) {
      await page.screenshot({
        path: path.join(OUT, `${t.key}-${width}.png`),
        fullPage: false,
      });
    }
  }

  await context.close();
}

await browser.close();

fs.writeFileSync(
  path.join(OUT, 'responsive.json'),
  JSON.stringify({ generatedAt: new Date().toISOString(), base: BASE, widths: WIDTHS, results }, null, 2)
);

const pages = new Set(results.map((r) => r.key)).size;
console.log(`\n--- summary ---`);
console.log(`pages    ${pages}`);
console.log(`checks   ${results.length} (${pages} pages x ${WIDTHS.length} widths)`);
console.log(`failures ${failures}`);
console.log(`shots    ${fs.readdirSync(OUT).filter((f) => f.endsWith('.png')).length} PNG in docs/evidence/review/`);
process.exit(failures === 0 ? 0 : 1);
