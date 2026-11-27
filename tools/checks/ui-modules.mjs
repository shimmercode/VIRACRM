#!/usr/bin/env node
/**
 * UI audit for the modules added after the phase-2/3 audit.
 *
 * `ui-shots.mjs` captures eleven pages: login, dashboard, and the customer /
 * contract / project lists, details and forms. Those are the pages that
 * existed when the earlier audit ran. Payroll, the three reports, the
 * organisation chart and the six §15 settings screens came later and had
 * **no** screenshot and no measured layout at all — a route that renders is
 * not a page that has been audited, and §15 asks for the responsive layout to
 * hold on every screen.
 *
 * So this does to the new modules exactly what ui-shots does to the old ones:
 * real Chromium, the same eight widths, and every assertion a computed number
 * from the live DOM rather than a claim about the stylesheet.
 *
 * Screenshots land in docs/evidence/ui-modules/ at the widest and narrowest
 * widths, so there is a real before/after artefact per page.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_MODULES_OUT || path.join(ROOT, 'docs/evidence/ui-modules');

fs.mkdirSync(OUT, { recursive: true });

const flow = await startBrowserFlow('ui-modules', { viewport: { width: 1440, height: 900 } });
const BASE = flow.baseUrl;
const PASSWORD = flow.password;
const context = flow.context;

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(34)} ${detail}`);
};

/* ── The modules the earlier audit never saw ──────────────────────────── */
const PAGES = [
  ['payroll', '/payroll'],
  ['organization', '/organization'],
  ['report-expert-workload', '/reports/expert-workload'],
  ['report-contracts', '/reports/contracts'],
  ['report-work-items', '/reports/work-items'],
  ['settings-index', '/settings'],
  ['settings-roles', '/settings/roles'],
  ['settings-seo-packages', '/settings/seo-packages'],
  ['settings-payroll-rules', '/settings/payroll-rules'],
  ['settings-contract-types', '/settings/contract-types'],
  ['settings-project-types', '/settings/project-types'],
  ['settings-access-reqs', '/settings/access-requirements'],
];

/* The same eight widths ui-shots uses, so the two audits are comparable. */
const WIDTHS = [
  ['desktop-1440', 1440, 900],
  ['laptop-1280', 1280, 800],
  ['tablet-1024', 1024, 768],
  ['tablet-768', 768, 1024],
  ['tablet-576', 576, 960],
  ['phone-430', 430, 932],
  ['phone-390', 390, 844],
  ['phone-360', 360, 800],
];

// Capture a real artefact only at the extremes; eight widths of twelve pages
// would be 96 images of mostly the same layout.
const SHOT_WIDTHS = new Set(['desktop-1440', 'phone-360']);

const page2 = await context.newPage();
await uiLogin(page2, BASE, 'admin', PASSWORD);

const perPage = new Map();

for (const [name, uri] of PAGES) {
  const seen = { overflow: [], status: null, dir: null, font: null, tables: null, labels: null, inputs: null, unlabelled: null, shot: 0 };

  for (const [label, width, height] of WIDTHS) {
    await page2.setViewportSize({ width, height });
    const response = await page2.goto(`${BASE}${uri}`, { waitUntil: 'domcontentloaded' });

    seen.status = response === null ? null : response.status();
    if (seen.status === null || seen.status >= 400) continue;

    const m = await page2.evaluate(() => {
      const de = document.documentElement;
      const offenders = [...document.querySelectorAll('*')].filter((el) => {
        const s = getComputedStyle(el);
        return el.scrollWidth > el.clientWidth + 1
          && !['auto', 'scroll'].includes(s.overflowX)
          && el.clientWidth > 0;
      }).map((el) => (el.className || el.tagName).toString().slice(0, 40)).slice(0, 4);

      // Every input must have a reachable label; an unlabelled field is the
      // most common accessibility defect in a generated form.
      const fields = [...document.querySelectorAll('input:not([type=hidden]), select, textarea')];
      const unlabelled = fields.filter((f) => {
        if (f.id && document.querySelector(`label[for="${f.id}"]`)) return false;
        if (f.closest('label')) return false;
        if (f.getAttribute('aria-label') || f.getAttribute('aria-labelledby')) return false;
        return true;
      }).map((f) => f.name || f.id || f.tagName).slice(0, 4);

      const tables = [...document.querySelectorAll('table')];

      return {
        overflowX: de.scrollWidth - de.clientWidth,
        offenders,
        dir: de.getAttribute('dir'),
        lang: de.getAttribute('lang'),
        font: getComputedStyle(document.body).fontFamily,
        tableCount: tables.length,
        tablesWithHead: tables.filter((t) => t.querySelector('thead, th')).length,
        fieldCount: fields.length,
        unlabelled,
      };
    });

    if (m.overflowX > 1) seen.overflow.push(`${label} +${m.overflowX}px (${m.offenders.join(', ') || '?'})`);
    seen.dir = m.dir;
    seen.lang = m.lang;
    seen.font = m.font;
    seen.tables = { count: m.tableCount, withHead: m.tablesWithHead };
    seen.inputs = m.fieldCount;
    seen.unlabelled = m.unlabelled;

    if (SHOT_WIDTHS.has(label)) {
      const file = path.join(OUT, `${name}-${label}.png`);
      await page2.screenshot({ path: file, fullPage: false });
      if (fs.existsSync(file) && fs.statSync(file).size > 4000) seen.shot++;
    }
  }

  perPage.set(name, seen);

  record(`MOD-STATUS-${name}`, seen.status !== null && seen.status < 400,
    `${uri} → HTTP ${seen.status}`);
  record(`MOD-RTL-${name}`, seen.dir === 'rtl' && seen.lang === 'fa',
    `${uri} renders dir=${seen.dir} lang=${seen.lang}`);
  record(`MOD-FONT-${name}`, (seen.font ?? '').includes('Vazirmatn'),
    `${uri} body font-family: ${(seen.font ?? 'none').slice(0, 46)}`);
  record(`MOD-FIT-${name}`, seen.overflow.length === 0,
    seen.overflow.length === 0
      ? `${uri} fits all ${WIDTHS.length} widths down to 360px with no horizontal overflow`
      : `${uri} overflows: ${seen.overflow.join('; ')}`);
  record(`MOD-SHOT-${name}`, seen.shot === SHOT_WIDTHS.size,
    `${seen.shot}/${SHOT_WIDTHS.size} screenshots captured for ${uri}`);

  if ((seen.inputs ?? 0) > 0) {
    record(`MOD-LABEL-${name}`, (seen.unlabelled ?? []).length === 0,
      (seen.unlabelled ?? []).length === 0
        ? `all ${seen.inputs} form fields on ${uri} have a reachable label`
        : `${uri} has unlabelled fields: ${seen.unlabelled.join(', ')}`);
  }

  if ((seen.tables?.count ?? 0) > 0) {
    record(`MOD-THEAD-${name}`, seen.tables.count === seen.tables.withHead,
      `${seen.tables.withHead}/${seen.tables.count} tables on ${uri} have a header row`);
  }
}

/* ── The shell must survive the new pages too ─────────────────────────── */
await page2.setViewportSize({ width: 1440, height: 900 });
await page2.goto(`${BASE}/payroll`, { waitUntil: 'domcontentloaded' });
const shell = await page2.evaluate(() => {
  const s = document.querySelector('.v-sidebar');
  const t = document.querySelector('.v-topbar');
  return {
    sidebar: s ? getComputedStyle(s).display !== 'none' : false,
    topbar: t ? getComputedStyle(t).display !== 'none' : false,
    navLinks: document.querySelectorAll('.v-sidebar a').length,
  };
});
record('MOD-SHELL', shell.sidebar && shell.topbar && shell.navLinks > 0,
  `the module pages render inside the real shell: sidebar=${shell.sidebar},`
  + ` topbar=${shell.topbar}, ${shell.navLinks} nav links`);

/* ── Narrow band: the sidebar must collapse, not overlap ──────────────── */
await page2.setViewportSize({ width: 360, height: 800 });
await page2.goto(`${BASE}/payroll`, { waitUntil: 'domcontentloaded' });
const narrow = await page2.evaluate(() => {
  const s = document.querySelector('.v-sidebar');
  const r = s ? s.getBoundingClientRect() : null;
  return {
    hidden: s ? getComputedStyle(s).display === 'none' : true,
    offscreen: r ? r.right <= 0 || r.left >= window.innerWidth : true,
    width: window.innerWidth,
  };
});
record('MOD-SHELL-360', narrow.hidden || narrow.offscreen,
  `at 360px the sidebar is ${narrow.hidden ? 'hidden' : 'off-screen'} rather than`
  + ` covering the ${narrow.width}px viewport`);

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- ui-modules: ${passed}/${results.length} passed ---`);
console.log(`screenshots in ${path.relative(ROOT, OUT)}`);
process.exit(passed === results.length ? 0 : 1);
