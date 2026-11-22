#!/usr/bin/env node
/**
 * VIRA CRM — calendar regression guard.
 *
 * The product shows Jalali (Persian) dates everywhere. A Gregorian date is not
 * a styling nit: a Persian speaker reading ۲۰۲۶‑۱۱‑۲۲ sees a wrong year, and
 * the phase brief treats the calendar as a correctness requirement. The bug has
 * recurred three times (dashboard task list, customer detail panel, contracts
 * table) because `->format('Y-m-d')` is the path of least resistance in Blade.
 *
 * This check renders the real pages in Chromium and fails if any ASCII date of
 * the form 19xx/20xx-MM-DD appears in the visible text. Persian digits are never
 * matched, so correct output cannot trip it.
 */
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

// ISOLATION: private dev server + database copy. Previously this suite drove
// the shared server, so simply rendering pages wrote session and audit rows
// into the shared database.
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_SHOTS_OUT || path.join(ROOT, 'docs/phase-2/evidence/ui-audit');

fs.mkdirSync(OUT, { recursive: true });

const flow = await startBrowserFlow('calendar', { viewport: { width: 1440, height: 900 } });
const BASE = flow.baseUrl;
const PASSWORD = flow.password;
const browser = flow.browser;

// A year above 1600 can only be Gregorian: the Jalali year is in the 1400s.
const GREGORIAN = /\b(1[6-9]\d{2}|20\d{2})\s*[-/]\s*\d{1,2}\s*[-/]\s*\d{1,2}\b/g;


const page = await flow.context.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#username', 'admin');
await page.fill('#password', PASSWORD);
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('button[type=submit]'),
]);

// Detail pages are discovered from the list pages, so a re-seed cannot make
// this check silently skip a page (it did once, with a hard-coded id).
const discover = async (listPath) => {
  await page.goto(`${BASE}${listPath}`, { waitUntil: 'domcontentloaded' });
  const href = await page.evaluate(() => {
    const a = [...document.querySelectorAll('a[href]')].find((el) =>
      /\/\d+$/.test(el.getAttribute('href') || '')
    );

    return a ? a.getAttribute('href') : null;
  });

  return href ? new URL(href, BASE).pathname : null;
};

const customerDetail = await discover('/customers');
const contractDetail = await discover('/contracts');

const pages = ['/', '/customers', '/contracts', customerDetail, contractDetail].filter(Boolean);

let failures = 0;

for (const uri of pages) {
  await page.goto(`${BASE}${uri}`, { waitUntil: 'domcontentloaded' });

  const findings = await page.evaluate((pattern) => {
    const rx = new RegExp(pattern.source, pattern.flags);
    const text = document.body.innerText;

    return [...text.matchAll(rx)].map((m) => {
      const at = m.index ?? 0;
      // Include the surrounding line so the report says which field is wrong.
      const lineStart = text.lastIndexOf('\n', at) + 1;
      const lineEnd = text.indexOf('\n', at);
      const line = text.slice(lineStart, lineEnd === -1 ? undefined : lineEnd).trim();

      return { date: m[0], context: line.slice(0, 90) };
    });
  }, GREGORIAN);

  if (findings.length === 0) {
    console.log(`PASS  T-FA-DATE  ${uri.padEnd(18)} Jalali only`);

    continue;
  }

  failures += findings.length;
  for (const f of findings) {
    console.log(`FAIL  T-FA-DATE  ${uri.padEnd(18)} "${f.date}" in: ${f.context}`);
  }
}

await flow.close();

console.log(failures === 0 ? '\ncalendar: OK' : `\ncalendar: ${failures} Gregorian date(s) leaked`);
process.exit(failures === 0 ? 0 : 1);
