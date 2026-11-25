#!/usr/bin/env node
/**
 * Screenshots of the credential module, at the widths the directive requires.
 *
 * A screenshot is not proof the feature works — credential-flow owns that. This
 * exists to prove something different, and something a passing HTTP test cannot
 * show: that the page renders in Vazirmatn, RTL, with the real design system,
 * and that **the password is not visible in the picture**.
 *
 * That last point is not a joke. The most likely way this page breaks is a
 * template edit that prints the secret into the table; every HTTP assertion
 * could still pass if it looked at the wrong cell. Here, the shot is taken with
 * a credential already stored, and the row is asserted to contain bullets.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_SHOTS_OUT || path.join(ROOT, 'docs/evidence/credentials');

fs.mkdirSync(OUT, { recursive: true });

/*
 * A key for this run only. It must be in the environment before the dev server
 * starts, because the server hands it to each PHP request.
 */
process.env.VIRA_CREDENTIAL_KEY = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 53 + 7) % 256)
).toString('base64');

const SECRET = 'Wp-Shot-' + Math.random().toString(36).slice(2, 10);

const flow = await startBrowserFlow('credential-shots', {
  viewport: { width: 1440, height: 960 },
});

const { browser, context, password, baseUrl } = flow;
const results = [];

try {
  // The context, not browser.newPage(): a new page on the browser gets a NEW
  // context, which has its own cookie jar, so the login would be lost and every
  // "credential" screenshot would silently be a picture of the login form.
  const page = await context.newPage();
  await uiLogin(page, baseUrl, 'admin', password);

  /*
   * Use a project that already exists in the seeded database rather than
   * fabricating one through in-page fetch().
   *
   * The fetch approach was tried and abandoned: driving the customer form
   * needs the full validation contract (mobile format, duplicate rules), and
   * synthesising it in a browser context made the seeding step itself the
   * most fragile part of the script — it failed with an empty result and the
   * screenshots silently captured whatever page it landed on. Discovering the
   * id from the list page is what ui-shots already does, for the same reason.
   */
  await page.goto(`${baseUrl}/projects`, { waitUntil: 'networkidle' });
  const projectHref = await page.evaluate(() => {
    const a = [...document.querySelectorAll('a[href]')]
      .find((el) => /\/projects\/\d+$/.test(el.getAttribute('href')));
    return a ? a.getAttribute('href') : null;
  });

  if (!projectHref) throw new Error('no project row on /projects to attach a credential to');

  const projectId = projectHref.match(/\/projects\/(\d+)$/)[1];

  // ── Store a credential through the real form ────────────────────────
  await page.goto(`${baseUrl}/projects/${projectId}/credentials/create`, { waitUntil: 'networkidle' });
  await page.selectOption('#credential_type_id', { label: 'وردپرس' });
  await page.fill('#label', 'ورود مدیر وردپرس');
  await page.fill('#username', 'admin_vira');
  await page.fill('#public_url', 'https://example.invalid/wp-admin');
  await page.fill('#secret', SECRET);
  /*
   * SCOPE THE SELECTOR TO THIS FORM.
   *
   * `page.click('button[type="submit"]')` takes the FIRST submit button in the
   * document, and the topbar carries a visible logout button. The click
   * therefore POSTed to /logout, the app correctly ended the session, and the
   * page landed on /login — which reads exactly like "the credential form is
   * broken" and cost real debugging time. The page was never at fault.
   */
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('form[action$="/credentials"] button[type="submit"]'),
  ]);

  const afterSave = new URL(page.url()).pathname;
  if (afterSave.includes('/login')) {
    throw new Error(
      'the page landed on /login after saving. Check which button the click hit: '
      + 'an unscoped [type=submit] selector picks the topbar logout button first.'
    );
  }

  // ── Shots at the required widths ────────────────────────────────────
  const widths = [390, 768, 1280, 1440];

  for (const width of widths) {
    await page.setViewportSize({ width, height: width < 600 ? 844 : 960 });
    await page.goto(`${baseUrl}/projects/${projectId}/credentials`, { waitUntil: 'networkidle' });

    const file = path.join(OUT, `credentials-${width}.png`);
    await page.screenshot({ path: file, fullPage: true });

    // The secret must not be in the rendered text at any width.
    const text = await page.evaluate(() => document.body.innerText);
    results.push({
      width,
      file: path.relative(ROOT, file),
      // For a user WITH credentials.reveal the cell shows a button, not
      // bullets — bullets are what a user WITHOUT the permission sees. Both
      // states hide the secret; asserting the wrong one made a correct page
      // look broken.
      showsButton: text.includes('مشاهدهٔ رمز'),
      leaked: text.includes(SECRET),
    });

    // Whatever the affordance, the secret itself must never be in the text.
    if (text.includes(SECRET)) {
      throw new Error(`the stored secret appeared in the list page at ${width}px`);
    }
  }

  // ── The create form, for the record ─────────────────────────────────
  await page.setViewportSize({ width: 1280, height: 960 });
  await page.goto(`${baseUrl}/projects/${projectId}/credentials/create`, { waitUntil: 'networkidle' });
  const createFile = path.join(OUT, 'credentials-create-1280.png');
  await page.screenshot({ path: createFile, fullPage: true });

  // ── Reveal, and prove it renders the real value on demand ───────────
  await page.goto(`${baseUrl}/projects/${projectId}/credentials`, { waitUntil: 'networkidle' });
  await page.click('[data-reveal]');
  await page.waitForSelector('.v-secret.is-visible', { timeout: 10_000 });
  const revealedText = await page.evaluate(() => document.querySelector('.v-secret')?.textContent || '');
  const revealFile = path.join(OUT, 'credentials-revealed-1280.png');
  await page.screenshot({ path: revealFile, fullPage: true });

  const ok = results.every((r) => r.showsButton && !r.leaked) && revealedText === SECRET;

  for (const r of results) {
    console.log(
      `${r.leaked ? 'FAIL' : 'PASS'}  ${r.width}px  revealButton=${r.showsButton} leaked=${r.leaked}  ${r.file}`
    );
  }
  console.log(`${revealedText === SECRET ? 'PASS' : 'FAIL'}  reveal populates the output element`);
  console.log(`${ok ? 'PASS' : 'FAIL'}  credential-shots: ${results.length} widths, secret never in the list DOM`);

  process.exitCode = ok ? 0 : 1;
} finally {
  // close() kills the private dev server and removes the flow directory.
  await flow.close();
}
