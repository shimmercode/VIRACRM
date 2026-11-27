/**
 * Screenshots for the gate-6 areas the existing suites miss.
 *
 * `ui-shots.mjs` writes 80 images at eight widths but covers only the core
 * entities — dashboard, customers, contracts, projects — and never the login
 * page. `ui-modules.mjs` covers the newly-audited modules but only at two
 * widths. Gate 6 explicitly names the login and authentication flow, and
 * employees, so those are captured here at the same eight widths and written
 * to the same evidence directory.
 *
 * This suite only takes pictures and asserts the invariants that can be read
 * from the DOM at each width. The judgement about how they look belongs to the
 * human review recorded in the acceptance report.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks } from './lib/flow-harness.mjs';
import { startBrowserFlow } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/phase-2/evidence/2c');
fs.mkdirSync(OUT, { recursive: true });

const WIDTHS = [
  ['desktop', 1440], ['laptop', 1280], ['tablet', 1024], ['tablet', 768],
  ['tablet', 576], ['phone', 430], ['phone', 390], ['phone', 360],
];

const c = new Checks('auth and employee screenshots');

/*
 * The login page is captured logged OUT, so it gets its own context rather than
 * reusing an authenticated session. startBrowserFlow gives a fresh one.
 */
const anon = await startBrowserFlow('shots-login');
// startBrowserFlow already builds the context at the requested viewport; it is
// a property, not a factory. Calling it as a function would throw.
const page = await anon.context.newPage();

const overflowAt = async () => page.evaluate(() => {
  const de = document.documentElement;
  return { scroll: de.scrollWidth, client: de.clientWidth };
});

let loginOk = true;
const details = [];

for (const [label, width] of WIDTHS) {
  await page.setViewportSize({ width, height: 900 });
  await page.goto(`${anon.baseUrl}/login`, { waitUntil: 'networkidle' });

  const file = path.join(OUT, `login-${label}-${width}.png`);
  await page.screenshot({ path: file, fullPage: false });

  const o = await overflowAt();
  const rtl = await page.getAttribute('html', 'dir');
  const font = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
  const hasUser = await page.locator('#username').count();
  const hasPass = await page.locator('#password').count();
  const labelled = await page.evaluate(() => {
    const fields = [...document.querySelectorAll('input:not([type=hidden])')];
    return fields.every((f) => f.labels?.length || f.getAttribute('aria-label') || f.getAttribute('placeholder'));
  });

  const ok = o.scroll <= o.client && rtl === 'rtl' && /Vazirmatn/.test(font)
    && hasUser === 1 && hasPass === 1 && labelled;
  if (!ok) loginOk = false;
  details.push(`${width}px:${ok ? 'ok' : 'FAIL'}`);
}

c.check('SHOT-LOGIN', loginOk,
  `login renders at all eight widths with dir=rtl, Vazirmatn, both credential`
  + ` fields labelled and no horizontal overflow (${details.join(' ')})`);

/* Employees needs a session. */
await page.goto(`${anon.baseUrl}/login`, { waitUntil: 'networkidle' });
await page.fill('#username', 'admin');
await page.fill('#password', anon.password);
await page.click('button[type="submit"]');
await page.waitForLoadState('networkidle');

let empOk = true;
const empDetails = [];

for (const [label, width] of WIDTHS) {
  await page.setViewportSize({ width, height: 900 });
  const res = await page.goto(`${anon.baseUrl}/employees`, { waitUntil: 'networkidle' });

  const file = path.join(OUT, `employees-list-${label}-${width}.png`);
  await page.screenshot({ path: file, fullPage: false });

  const o = await overflowAt();
  const ok = res.status() === 200 && o.scroll <= o.client;
  if (!ok) empOk = false;
  empDetails.push(`${width}px:${res.status()}${o.scroll > o.client ? ' OVERFLOW' : ''}`);
}

c.check('SHOT-EMPLOYEES', empOk,
  `employees renders 200 at all eight widths with no horizontal overflow`
  + ` (${empDetails.join(' ')})`);

const shots = fs.readdirSync(OUT).filter((f) => /^(login|employees-list)-/.test(f));
c.check('SHOT-COUNT', shots.length === 16,
  `${shots.length} images written to docs/phase-2/evidence/2c`
  + ` (login + employees, eight widths each)`);

await anon.close();
process.exit(c.summary() ? 0 : 1);
