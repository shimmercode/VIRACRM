#!/usr/bin/env node
/**
 * work-item-shots.mjs — the two new pages, in a real Chromium, at all seven
 * required widths (390 / 430 / 576 / 768 / 1024 / 1280 / 1440).
 *
 * WHY THIS IS A SEPARATE SUITE FROM review-shots.mjs
 * --------------------------------------------------
 * That suite covers config/review.php. The کارتابل and the notes module are
 * operator pages; adding them to that catalogue would push internal operational
 * screens into the client-review surface. They still need the same proof.
 *
 * Uses startBrowserFlow(), which spawns its OWN server and its OWN database copy
 * on a free port. That matters: the shared preview server runs with
 * VIRA_FORCE_HTTPS=true, so every response is https and the session cookie is
 * Secure — a plain-http Chromium silently drops it and every screenshot becomes
 * a picture of the login form. The isolated harness is plain http by design.
 *
 * WHAT IT ASSERTS, per width, per page:
 *   1. HTTP 200 — a 500 renders as a blank page that looks acceptable at a glance
 *   2. no horizontal document overflow
 *   3. the expected Persian label is present (proof the real view rendered)
 *   4. the expected component class is present (proof it is styled, not raw HTML)
 *   5. at least one tappable control — no key action unreachable on a phone
 *
 * Output: docs/evidence/work-items/<key>-<width>.png  and  responsive.json
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/evidence/work-items');
const WIDTHS = [390, 430, 576, 768, 1024, 1280, 1440];

fs.mkdirSync(OUT, { recursive: true });

/*
 * `classes` are CSS selectors and `text` are literal strings. They were
 * originally one list, which silently made 'سررسید' a class selector — an
 * invalid selector that matches nothing and reports a false failure. Keeping
 * them apart is the difference between a suite that measures the page and one
 * that measures itself.
 *
 * `classes` is optional for pages that legitimately render an empty state: the
 * notes list has no rows in a fresh seed, so it must show v-empty-state, not a
 * table. Asserting a table there would force a fake row into the fixture.
 */
const PAGES = [
  {
    key: 'cartable',
    url: '/work-items',
    text: ['کارتابل', 'سررسید', 'صورتحساب'],
    classes: ['v-table', 'v-stats', 'v-filterbar'],
  },
  {
    key: 'cartable-detail',
    url: '/work-items/1',
    text: ['مشخصات', 'سررسید'],
    classes: ['v-dl', 'v-pagehead'],
  },
  {
    key: 'notes',
    url: '/notes',
    text: ['یادداشت و وظیفه'],
    classes: ['v-filterbar'],
  },
  {
    key: 'notes-create',
    url: '/notes/create',
    text: ['یادداشت جدید', 'عنوان', 'اولویت'],
    classes: ['v-form'],
  },
  // OD-41 — the accounting surfaces. The trial balance is the widest table in
  // the product (five money columns), so it is the real test of the mobile
  // card-collapse rule rather than a page that happens to be narrow.
  {
    key: 'accounting-chart',
    url: '/accounting/chart',
    text: ['نمودار حساب‌ها', 'قابل ثبت'],
    classes: ['v-table', 'v-tabs'],
  },
  {
    key: 'accounting-trial',
    url: '/accounting/trial-balance',
    text: ['تراز آزمایشی'],
    classes: ['v-tabs'],
  },
  {
    key: 'invoices',
    url: '/invoices',
    text: ['فاکتورها'],
    classes: ['v-filterbar'],
  },
  // OD-16 — the HR surfaces. The payroll period page is the densest new screen
  // (four money columns plus action forms), and the payslip editor carries a
  // definition list beside a full form, which is where the two-column detail
  // grid has to collapse cleanly at 390px.
  {
    key: 'employees',
    url: '/employees',
    text: ['پرسنل'],
    classes: ['v-filterbar'],
  },
  // §17 — the organisational tree. The two-level rows with an inline edit
  // <details> are the hardest thing on the site to keep inside 390px.
  // REQ-04.04 — the template editor is the longest text surface in the product
  // (a 16-row textarea plus a wrapped placeholder palette), so it is the real
  // test of horizontal containment on a narrow screen.
  // REQ-09.05 — the export buttons sit in the page head, the tightest horizontal
  // space in the layout, and a page head with two buttons plus a title is where
  // a narrow screen breaks first.
  {
    key: 'payslip',
    url: '/payroll/payslips/1',
    text: ['فیش حقوقی'],
    classes: ['v-btn'],
  },
  {
    key: 'credentials',
    url: '/projects/1/credentials',
    text: ['دسترسی‌های پروژه'],
    classes: ['v-btn'],
  },
  {
    key: 'contract-templates',
    url: '/contracts/templates',
    text: ['قالب‌های قرارداد'],
    classes: ['v-card'],
  },
  {
    key: 'contract-template-form',
    url: '/contracts/templates/create',
    text: ['ثبت قالب قرارداد', 'متن قالب'],
    classes: ['v-form', 'v-tokenlist'],
  },
  {
    key: 'organization',
    url: '/organization',
    text: ['ساختار سازمانی', 'مراکز هزینه'],
    // NOT `v-tree`: a fresh install has no departments, because a client's org
    // structure is their own data and seeding an invented one would be fake
    // content. On an empty install the page correctly shows its empty state, so
    // the assertion is the create form, which exists either way.
    classes: ['v-inlineform'],
  },
  {
    key: 'payroll',
    url: '/payroll',
    text: ['حقوق و دستمزد'],
    // `v-inlineform` is the side-by-side year/month/button row, which is the one
    // construct on this page that can overflow a 390px viewport.
    classes: ['v-inlineform'],
  },
];

const flow = await startBrowserFlow('work-item-shots');
const results = [];
let failures = 0;

try {
  for (const width of WIDTHS) {
    const context = await flow.browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();

    await uiLogin(page, flow.baseUrl, 'admin', flow.password);

    for (const p of PAGES) {
      const res = await page.goto(flow.baseUrl + p.url, { waitUntil: 'networkidle' });
      const status = res?.status() ?? 0;

      const measured = await page.evaluate(({ classes, text }) => {
        const doc = document.documentElement;
        const overflowers = [...document.querySelectorAll('body *')]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.right > doc.clientWidth + 2;
          })
          .slice(0, 4)
          .map((el) => el.tagName.toLowerCase()
            + (el.className ? '.' + String(el.className).split(' ')[0] : ''));

        const bodyText = document.body.innerText.slice(0, 6000);

        return {
          overflow: doc.scrollWidth - doc.clientWidth,
          overflowers,
          missingClasses: classes.filter((n) => !document.querySelector('.' + n)),
          missingText: text.filter((t) => !bodyText.includes(t)),
          text: bodyText,
          tappable: [...document.querySelectorAll('button, .v-btn')]
            .filter((el) => {
              const r = el.getBoundingClientRect();
              return r.width > 0 && r.height >= 28;
            }).length,
        };
      }, { classes: p.classes, text: p.text });

      const ok = status === 200
        && measured.overflow <= 1
        && measured.missingClasses.length === 0
        && measured.missingText.length === 0
        && measured.tappable >= 1;

      if (!ok) failures += 1;

      console.log(
        `${ok ? 'PASS' : 'FAIL'}  ${p.key.padEnd(18)} @${String(width).padEnd(5)} `
        + `status=${status} overflow=${measured.overflow} `
        + `missingClass=${measured.missingClasses.join(',') || '-'} `
        + `missingText=${measured.missingText.join(',') || '-'} tappable=${measured.tappable}`
        + (measured.overflowers.length ? ` offenders=${measured.overflowers.join(' ')}` : ''),
      );

      results.push({
        key: p.key, width, status, ok,
        overflow: measured.overflow,
        overflowers: measured.overflowers,
        tappable: measured.tappable,
      });

      await page.screenshot({ path: path.join(OUT, `${p.key}-${width}.png`) });
    }

    await context.close();
  }
} finally {
  await flow.close();
}

fs.writeFileSync(
  path.join(OUT, 'responsive.json'),
  JSON.stringify({ generatedAt: new Date().toISOString(), widths: WIDTHS, results }, null, 2),
);

console.log(`\n--- work-item-shots: ${results.length - failures}/${results.length} passed ---`);
process.exit(failures === 0 ? 0 : 1);
