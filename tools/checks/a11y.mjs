#!/usr/bin/env node
/**
 * VIRA CRM — accessibility and interaction-state checks.
 *
 * These cover the failures that a "does it render" check cannot see: whether a
 * modal actually contains focus, whether a control exists for every viewport,
 * and whether the flash/error messages are announced rather than merely drawn.
 *
 * Every finding here was first observed by driving the real pages, not inferred
 * from markup.
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

const flow = await startBrowserFlow('a11y', { viewport: { width: 1440, height: 900 } });
const BASE = flow.baseUrl;
const PASSWORD = flow.password;
const browser = flow.browser;

const results = [];

const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(26)} ${detail}`);
};

const login = async (page) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('#username', 'admin');
  await page.fill('#password', PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type=submit]'),
  ]);
};

// ── 1. Sign-out must exist at every viewport ─────────────────────────────
// Below 1024px the sidebar rail is display:none, so the drawer is the only
// navigation. When the drawer omitted the footer there was no way to sign out
// on a phone at all.
for (const [label, width, height] of [
  ['phone-390', 390, 844],
  ['tablet-768', 768, 1024],
  ['desktop-1440', 1440, 900],
]) {
  const ctx = await flow.browser.newContext({ viewport: { width, height } });
  const page = await ctx.newPage();
  await login(page);
  await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });

  const burger = await page.$('[data-drawer-open]');
  if (burger && (await burger.isVisible())) {
    await burger.click();
    await page.waitForTimeout(300);
  }

  const reachable = await page.evaluate(() =>
    [...document.querySelectorAll('form[action*="logout"]')].some((form) => {
      const r = form.getBoundingClientRect();
      const cs = getComputedStyle(form);

      return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
    })
  );

  record(`T-LOGOUT-${label}`, reachable, reachable ? 'sign-out reachable' : 'NO visible sign-out control');
  await ctx.close();
}

// ── 2. The mobile drawer is a modal dialog, so it must trap focus ────────
{
  const ctx = await flow.browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();
  await login(page);
  await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });

  const burger = await page.$('[data-drawer-open]');
  await burger.click();
  await page.waitForTimeout(300);

  const opened = await page.evaluate(() => {
    const panel = document.querySelector('.v-drawer__panel');

    return {
      open: document.body.classList.contains('v-app--drawer-open'),
      inPanel: panel ? panel.contains(document.activeElement) : false,
      onScrim: document.activeElement
        ? document.activeElement.classList.contains('v-drawer__scrim')
        : false,
    };
  });

  record(
    'T-DRAWER-FOCUS',
    opened.open && opened.inPanel && !opened.onScrim,
    `focus inside panel=${opened.inPanel}, on scrim=${opened.onScrim}`
  );

  // Tab twice the number of controls; focus must never leave the dialog.
  let escaped = 0;

  for (let i = 0; i < 24; i++) {
    await page.keyboard.press('Tab');
    const outside = await page.evaluate(() => {
      const panel = document.querySelector('.v-drawer__panel');

      return panel ? !panel.contains(document.activeElement) : true;
    });

    if (outside) escaped++;
  }

  record('T-DRAWER-TRAP', escaped === 0, `${escaped} of 24 Tab presses escaped the dialog`);

  await page.keyboard.press('Escape');
  await page.waitForTimeout(250);
  const closed = await page.evaluate(() => ({
    open: document.body.classList.contains('v-app--drawer-open'),
    // Focus must return to the control that opened it, not to <body>.
    onTrigger: document.activeElement
      ? document.activeElement.hasAttribute('data-drawer-open')
      : false,
  }));

  record(
    'T-DRAWER-ESCAPE',
    !closed.open && closed.onTrigger,
    `closed=${!closed.open}, focus restored to trigger=${closed.onTrigger}`
  );

  await ctx.close();
}

// ── 3. Flash and error messages are announced ───────────────────────────
// role="status" implies aria-live="polite"; role="alert" is assertive. What
// matters is that the container carries a live region role at all.
{
  const ctx = await flow.browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();

  // Force a validation failure by submitting an empty customer form.
  // The submit button must be scoped to the form: every authenticated page also
  // renders a sign-out <button type=submit>, and a bare selector clicked that
  // instead — which silently logged the session out and made this check report
  // "no error summary" for the wrong reason.
  await login(page);
  await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('form[action*="/customers"] button[type=submit]'),
  ]);

  const alerts = await page.evaluate(() =>
    [...document.querySelectorAll('[role="alert"], [role="status"], [aria-live]')].map((el) => ({
      role: el.getAttribute('role'),
      live: el.getAttribute('aria-live'),
      hasText: (el.textContent || '').trim().length > 0,
    }))
  );

  record(
    'T-ALERT-LIVE',
    alerts.length > 0 && alerts.some((a) => a.hasText),
    `${alerts.length} live region(s); roles: ${[...new Set(alerts.map((a) => a.role || a.live))].join(',')}`
  );

  // The error summary must be present, focusable, and actually receive focus.
  const summary = await page.evaluate(() => {
    const el = document.getElementById('error-summary');

    return el
      ? {
          found: true,
          tabindex: el.getAttribute('tabindex'),
          focused: document.activeElement === el,
          invalidFields: document.querySelectorAll('[aria-invalid="true"]').length,
          described: [...document.querySelectorAll('[aria-invalid="true"]')]
            .every((f) => (f.getAttribute('aria-describedby') || '').includes('error')),
        }
      : { found: false };
  });

  record(
    'T-ERROR-SUMMARY',
    summary.found && summary.tabindex === '-1' && summary.focused,
    summary.found
      ? `summary present, tabindex=${summary.tabindex}, focused=${summary.focused}`
      : 'no #error-summary element after a failed submit'
  );

  record(
    'T-FIELD-INVALID',
    summary.found && summary.invalidFields > 0 && summary.described,
    summary.found
      ? `${summary.invalidFields} field(s) aria-invalid, all referencing their error text=${summary.described}`
      : 'not reached'
  );

  await ctx.close();
}

// ── 4. Every control has an accessible name ─────────────────────────────
{
  const ctx = await flow.browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await login(page);

  const unnamed = [];

  for (const uri of ['/', '/customers', '/contracts', '/customers/create', '/contracts/create']) {
    await page.goto(`${BASE}${uri}`, { waitUntil: 'domcontentloaded' });

    const found = await page.evaluate(() => {
      const nameOf = (el) => (
        el.getAttribute('aria-label') ||
        (el.getAttribute('aria-labelledby') || '').split(' ').map((id) => {
          const t = document.getElementById(id);

          return t ? t.textContent : '';
        }).join(' ') ||
        (el.labels ? [...el.labels].map((l) => l.textContent).join(' ') : '') ||
        el.getAttribute('title') ||
        (el.tagName === 'BUTTON' || el.tagName === 'A' ? el.textContent : '') ||
        ''
      ).trim();

      return [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea')]
        .filter((el) => {
          const r = el.getBoundingClientRect();

          return r.width > 0 && r.height > 0 && !nameOf(el);
        })
        .map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`);
    });

    unnamed.push(...found.map((f) => `${uri} → ${f}`));
  }

  record(
    'T-ACCESS-NAME',
    unnamed.length === 0,
    unnamed.length === 0 ? 'all visible controls have accessible names' : unnamed.slice(0, 6).join('; ')
  );

  await ctx.close();
}

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

const out = path.join(ROOT, 'docs/phase-2/evidence/ui-audit');
fs.mkdirSync(out, { recursive: true });
fs.writeFileSync(path.join(out, 'a11y-results.json'), JSON.stringify(results, null, 2));

process.exit(passed === results.length ? 0 : 1);
