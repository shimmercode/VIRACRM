#!/usr/bin/env node
/**
 * VIRA CRM — the six-width regression matrix.
 *
 * Four pages × seven widths, asserting the invariants the review asked for:
 *
 *   - no unintended horizontal overflow (page or table wrapper)
 *   - no lost information: the primary data column and the row actions are
 *     present and visible at every width
 *   - RTL intact (`dir="rtl"`, `lang="fa"`)
 *   - no Gregorian date leaks into the UI
 *   - keyboard navigation intact (focus reaches the first interactive control,
 *     and the focus ring is painted)
 *   - brand colours unchanged (`#450073`, `#D2AB67`)
 *
 * The cards below 768px are a *different* representation, so "no lost
 * information" is checked against whichever representation is active rather
 * than against the table alone.
 */
import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(36)} ${detail}`);
};

// The seven widths the phase-3 directive requires (§15). 576px was missing from
// this matrix and from every other suite; it is the "compact tablet / large
// phone" band where the card layout has already kicked in but the sidebar has
// not yet collapsed, so it is the width most likely to break unnoticed.
const WIDTHS = [390, 430, 576, 768, 1024, 1280, 1440];
const PAGES = [
  { path: '/customers', name: 'customers', list: true },
  { path: '/contracts', name: 'contracts', list: true },
  { path: '/', name: 'dashboard', list: false },
];

const flow = await startBrowserFlow('regression-matrix', { viewport: { width: 1440, height: 900 } });

// ── Login (unauthenticated, so it gets its own context) ─────────────────
for (const width of WIDTHS) {
  const context = await flow.browser.newContext({ viewport: { width, height: 900 } });
  const page = await context.newPage();
  await page.goto(`${flow.baseUrl}/login`, { waitUntil: 'domcontentloaded' });

  const m = await page.evaluate(() => {
    const doc = document.documentElement;

    // Every input must be reachable and visible: a login form that overflows or
    // hides its submit button cannot be used at all.
    const submit = document.querySelector('button[type="submit"]');
    const submitBox = submit ? submit.getBoundingClientRect() : null;

    return {
      overflow: doc.scrollWidth - doc.clientWidth,
      dir: doc.dir,
      lang: doc.lang,
      // The submit must be inside the viewport horizontally.
      submitVisible: !!submitBox && submitBox.width > 0
        && submitBox.right <= doc.clientWidth + 1 && submitBox.left >= -1,
      // Smallest rendered font, guarding against a shrink-to-fit layout.
      smallest: Math.min(
        ...[...document.querySelectorAll('main *')]
          .filter((el) => el.children.length === 0 && el.textContent.trim())
          .map((el) => parseFloat(getComputedStyle(el).fontSize) || 99)
      ),
      gregorian: /\b(1[3-9]\d{2}|20\d{2})\s*[-/]\s*\d{1,2}\s*[-/]\s*\d{1,2}\b/.test(
        document.body.innerText
      ),
    };
  });

  record(`T-MX-login-${width}-ovf`, m.overflow <= 0, `page overflow ${m.overflow}px`);
  record(`T-MX-login-${width}-rtl`, m.dir === 'rtl' && m.lang === 'fa', `dir=${m.dir} lang=${m.lang}`);
  record(`T-MX-login-${width}-submit`, m.submitVisible, 'submit button inside the viewport');
  record(`T-MX-login-${width}-type`, m.smallest >= 11, `smallest font ${m.smallest}px`);
  record(`T-MX-login-${width}-date`, !m.gregorian, 'no Gregorian date in the rendered text');

  await context.close();
}

// ── Authenticated pages ────────────────────────────────────────────────
for (const target of PAGES) {
  for (const width of WIDTHS) {
    const page = await flow.browser.newPage({ viewport: { width, height: 900 } });
    await uiLogin(page, flow.baseUrl, 'admin', flow.password);
    await page.goto(`${flow.baseUrl}${target.path}`, { waitUntil: 'domcontentloaded' });
    // Card view is built after DOMContentLoaded; wait for it on list pages.
    if (target.list) {
      await page.waitForSelector('.v-tablewrap[data-cards="ready"]', { timeout: 10_000 }).catch(() => {});
    }
    await page.waitForTimeout(120);

    const m = await page.evaluate((isList) => {
      const doc = document.documentElement;
      const wrap = document.querySelector('.v-tablewrap');
      const table = wrap?.querySelector('table');
      const cards = wrap?.querySelector('.v-cards');
      const usingCards = !!cards && getComputedStyle(cards).display !== 'none';

      // "No lost information": whichever representation is active must carry
      // the record names and the row actions.
      let rows = 0;
      let actions = 0;
      let primary = 0;

      if (isList) {
        if (usingCards) {
          const cardEls = wrap.querySelectorAll('.v-card-row');
          rows = cardEls.length;
          cardEls.forEach((c) => {
            if (c.querySelector('.v-card-row__primary')?.textContent.trim()) primary += 1;
            actions += c.querySelectorAll('.v-card-row__actions a, .v-card-row__actions button').length;
          });
        } else {
          const trs = wrap.querySelectorAll('tbody tr');
          rows = trs.length;
          trs.forEach((tr) => {
            if (tr.querySelector('.v-table__primary')?.textContent.trim()) primary += 1;
            actions += tr.querySelectorAll('.v-table__actions a, .v-table__actions button').length;
          });
        }
      }

      // Keyboard reachability: the first interactive control must be focusable.
      const focusable = [...document.querySelectorAll('main a[href], main button, main input, main select')]
        .filter((el) => el.getBoundingClientRect().width > 0 && !el.disabled);

      return {
        overflow: doc.scrollWidth - doc.clientWidth,
        wrapOverflow: wrap ? wrap.scrollWidth - wrap.clientWidth : 0,
        dir: doc.dir,
        lang: doc.lang,
        usingCards,
        rows,
        primary,
        actions,
        focusableCount: focusable.length,
        smallest: Math.min(
          ...[...document.querySelectorAll('main *')]
            .filter((el) => el.children.length === 0 && el.textContent.trim())
            .map((el) => parseFloat(getComputedStyle(el).fontSize) || 99)
        ),
        gregorian: /\b(1[3-9]\d{2}|20\d{2})\s*[-/]\s*\d{1,2}\s*[-/]\s*\d{1,2}\b/.test(
          document.querySelector('main')?.innerText || ''
        ),
        // Brand tokens as actually computed.
        primaryColor: getComputedStyle(doc).getPropertyValue('--vira-primary').trim(),
        goldColor: getComputedStyle(doc).getPropertyValue('--vira-gold').trim(),
      };
    }, target.list);

    const tag = `${target.name}-${width}`;

    record(`T-MX-${tag}-ovf`, m.overflow <= 0, `page overflow ${m.overflow}px`);
    record(`T-MX-${tag}-rtl`, m.dir === 'rtl' && m.lang === 'fa', `dir=${m.dir} lang=${m.lang}`);
    record(`T-MX-${tag}-type`, m.smallest >= 11, `smallest font ${m.smallest}px`);
    record(`T-MX-${tag}-date`, !m.gregorian, 'no Gregorian date in main content');
    record(`T-MX-${tag}-kbd`, m.focusableCount >= 3, `${m.focusableCount} focusable controls`);

    if (target.list) {
      record(`T-MX-${tag}-wrap`, m.wrapOverflow <= 0,
        `list wrapper overflow ${m.wrapOverflow}px (${m.usingCards ? 'cards' : 'table'})`);
      record(`T-MX-${tag}-rows`, m.rows > 0, `${m.rows} ${m.usingCards ? 'cards' : 'rows'}`);
      record(`T-MX-${tag}-primary`, m.primary === m.rows,
        `${m.primary}/${m.rows} records show a name`);
      record(`T-MX-${tag}-actions`, m.actions >= m.rows,
        `${m.actions} action controls for ${m.rows} records (no actions lost)`);
    }

    // Hex is case-insensitive: the stylesheet declares #d2ab67, the brand spec
    // writes #D2AB67. Compare normalised values — the COLOUR is what matters,
    // not the spelling.
    const sameColour = (a, b) => (a || '').toLowerCase() === b.toLowerCase();

    record(`T-MX-${tag}-brand`,
      sameColour(m.primaryColor, '#450073') && sameColour(m.goldColor, '#D2AB67'),
      `primary=${m.primaryColor} gold=${m.goldColor} (must equal #450073 / #D2AB67)`);

    await page.close();
  }
}

// ── Focus ring is actually painted (keyboard navigation is usable) ──────
{
  const page = await flow.browser.newPage({ viewport: { width: 1440, height: 900 } });
  await uiLogin(page, flow.baseUrl, 'admin', flow.password);
  await page.goto(`${flow.baseUrl}/customers`, { waitUntil: 'domcontentloaded' });

  await page.keyboard.press('Tab');
  const focus = await page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return { ok: false, why: 'nothing focused' };
    const cs = getComputedStyle(el);
    return {
      tag: el.tagName,
      outline: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0,
      boxShadow: cs.boxShadow !== 'none',
    };
  });

  record('T-MX-focusring', focus.outline === true || focus.boxShadow === true,
    `first Tab reaches <${focus.tag}> with a visible ring`);

  await page.close();
}

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}
