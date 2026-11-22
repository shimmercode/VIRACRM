#!/usr/bin/env node
/**
 * Login screenshots, captured without a session.
 *
 * The main harness authenticates once and reuses the context, so GET /login
 * redirects and the page can never be captured there. This runs in its own
 * browser with no cookies.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { startBrowserFlow } from './lib/browser-flow.mjs';

// ISOLATION: this suite runs against its own dev server and database copy, so
// it never writes a session row into the shared database. See
// tools/checks/lib/browser-flow.mjs.
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_SHOTS_OUT || path.join(ROOT, 'docs/phase-2/evidence/ui-audit');

fs.mkdirSync(OUT, { recursive: true });

const flow = await startBrowserFlow('ui-login', { viewport: { width: 1440, height: 900 } });
const BASE = flow.baseUrl;

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(24)} ${detail}`);
};

for (const [label, w, h] of [
  ['desktop-1440', 1440, 900],
  ['tablet-768', 768, 1024],
  ['phone-430', 430, 932],
  ['phone-390', 390, 844],
]) {
  const ctx = await flow.browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  const res = await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => document.fonts.ready);

  const m = await page.evaluate(() => ({
    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    dir: document.documentElement.getAttribute('dir'),
    lang: document.documentElement.getAttribute('lang'),
    hasUser: !!document.querySelector('#username'),
    hasPass: !!document.querySelector('#password'),
    // The auth card must be a single grid on the wrapper, not on <body>.
    bodyDisplay: getComputedStyle(document.body).display,
    submitInView: (() => {
      const b = document.querySelector('button[type=submit]');
      if (!b) return false;
      const r = b.getBoundingClientRect();
      return r.top >= 0 && r.bottom <= window.innerHeight && r.width > 0;
    })(),
    smallestFont: Math.min(...[...document.querySelectorAll('main *, .v-auth *, form *')]
      .filter((el) => el.textContent.trim() && el.children.length === 0)
      .map((el) => parseFloat(getComputedStyle(el).fontSize) || 99)),
  }));

  await page.screenshot({ path: path.join(OUT, `login-${label}.png`) });

  record(`T-LOGIN-STATUS-${label}`, res !== null && res.status() === 200, `HTTP ${res?.status()}`);
  record(`T-LOGIN-RESP-${label}`, m.overflowX <= 1, `overflowX=${m.overflowX}px`);
  record(`T-LOGIN-RTL-${label}`, m.dir === 'rtl' && m.lang === 'fa', `dir=${m.dir} lang=${m.lang}`);
  record(`T-LOGIN-FORM-${label}`, m.hasUser && m.hasPass && m.submitInView,
    `fields=${m.hasUser && m.hasPass}, submit visible=${m.submitInView}`);
  record(`T-LOGIN-TYPE-${label}`, m.smallestFont >= 11, `smallest font ${m.smallestFont}px`);
  record(`T-LOGIN-GRID-${label}`, m.bodyDisplay !== 'grid', `body display=${m.bodyDisplay}`);

  await ctx.close();
}

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

fs.writeFileSync(path.join(OUT, 'login-results.json'), JSON.stringify(results, null, 2));
process.exit(passed === results.length ? 0 : 1);
