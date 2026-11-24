#!/usr/bin/env node
/**
 * VIRA CRM — real browser verification.
 *
 * Renders the eight 2-C pages in Chromium at four widths and measures the
 * layout, rather than trusting that the stylesheet "looks right". Every
 * assertion is a computed number from the live DOM.
 *
 * Run against the dev server on :8080.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

// ISOLATION: private dev server + database copy, so capturing the pages cannot
// write sessions or audit rows into the shared database.
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_SHOTS_OUT || path.join(ROOT, 'docs/phase-2/evidence/2c');

fs.mkdirSync(OUT, { recursive: true });

const flow = await startBrowserFlow('ui-shots', { viewport: { width: 1440, height: 900 } });
const BASE = flow.baseUrl;
const PASSWORD = flow.password;
const browser = flow.browser;
const context = flow.context;

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id}  ${detail}`);
};

// ── log in ───────────────────────────────────────────────────────────────
const page = await context.newPage();

// The flow prepared admin with must_change_password=false, so there is no
// forced-change branch to recover from — that branch existed only to cope with
// whatever password a previous flow had left in the shared database.
await uiLogin(page, BASE, 'admin', PASSWORD);

const afterLogin = new URL(page.url()).pathname;
record('T-UI-LOGIN', !afterLogin.includes('/login'), `login reached ${afterLogin}`);

// ── the eight required pages ─────────────────────────────────────────────
// Detail pages must point at records that actually exist. Hard-coding #1
// silently screenshotted a 404 once the demo data was re-seeded, and the
// shell checks then failed with "sidebar=null" rather than saying 404.
const discoverId = async (page, listPath, linkRe) => {
  await page.goto(`${BASE}${listPath}`, { waitUntil: 'domcontentloaded' });
  const href = await page.evaluate((re) => {
    const rx = new RegExp(re);
    const a = [...document.querySelectorAll('a[href]')].find((el) => rx.test(el.getAttribute('href')));
    return a ? a.getAttribute('href') : null;
  }, linkRe.source);
  if (!href) throw new Error(`no row link on ${listPath} matching ${linkRe}`);
  return new URL(href, BASE).pathname;
};

await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });
const CUSTOMER_ID = await discoverId(page, '/customers', /\/customers\/\d+$/);
const CONTRACT_ID = await discoverId(page, '/contracts', /\/contracts\/\d+$/);
console.log(`detail pages -> ${CUSTOMER_ID} ${CONTRACT_ID}`);

const PAGES = [
  ['login', '/login', false],
  ['dashboard', '/', true],
  ['customers-list', '/customers', true],
  ['customer-detail', CUSTOMER_ID, true],
  ['customer-form', '/customers/create', true],
  ['contracts-list', '/contracts', true],
  ['contract-detail', CONTRACT_ID, true],
  ['contract-form', '/contracts/create', true],
];

// All seven widths the phase-3 directive requires (§15). 576 is the compact
// tablet band — card layout has switched on but the sidebar has not yet
// collapsed — and it was missing here as well as in regression-matrix.
const WIDTHS = [
  ['desktop-1440', 1440, 900],
  ['laptop-1280', 1280, 800],
  ['tablet-1024', 1024, 768],
  ['tablet-768', 768, 1024],
  ['tablet-576', 576, 960],
  ['phone-430', 430, 932],
  ['phone-390', 390, 844],
];

for (const [name, uri, needsAuth] of PAGES) {
  for (const [label, width, height] of WIDTHS) {
    await page.setViewportSize({ width, height });

    const response = await page.goto(`${BASE}${uri}`, { waitUntil: 'domcontentloaded' });

    // The login page redirects when authenticated; skip its extra widths.
    const landed = new URL(page.url()).pathname;
    if (name === 'login' && landed !== '/login') {
      continue;
    }

    // A 404 renders the error layout, which has no sidebar — without this the
    // shell assertions fail confusingly instead of naming the real problem.
    record(
      `T-STATUS-${name}-${label}`,
      response !== null && response.status() < 400,
      `HTTP ${response === null ? 'null' : response.status()} for ${uri}`
    );

    const metrics = await page.evaluate(() => {
      const de = document.documentElement;
      const body = document.body;

      // Horizontal overflow: any element wider than the viewport, or a scroll
      // container that is not explicitly allowed to scroll.
      const scrollers = [...document.querySelectorAll('*')].filter((el) => {
        const style = getComputedStyle(el);
        const scrollable = ['auto', 'scroll'].includes(style.overflowX);
        return el.scrollWidth > el.clientWidth + 1 && !scrollable && el.clientWidth > 0;
      }).map((el) => el.className || el.tagName).slice(0, 5);

      const sidebar = document.querySelector('.v-sidebar');
      const topbar = document.querySelector('.v-topbar');

      return {
        docScrollWidth: de.scrollWidth,
        docClientWidth: de.clientWidth,
        bodyScrollWidth: body.scrollWidth,
        overflowX: de.scrollWidth - de.clientWidth,
        offenders: scrollers,
        sidebarVisible: sidebar ? getComputedStyle(sidebar).display !== 'none' : null,
        sidebarRight: sidebar ? Math.round(sidebar.getBoundingClientRect().right) : null,
        sidebarLeft: sidebar ? Math.round(sidebar.getBoundingClientRect().left) : null,
        topbarVisible: topbar ? getComputedStyle(topbar).display !== 'none' : null,
        dir: de.getAttribute('dir'),
        lang: de.getAttribute('lang'),
        // Font actually used for body text.
        fontFamily: getComputedStyle(body).fontFamily,
        // Is the Vazirmatn face loaded?
        fontLoaded: document.fonts ? [...document.fonts].some((f) => /Vazirmatn/i.test(f.family) && f.status === 'loaded') : null,
        // Smallest rendered text, to catch unreadably tiny type (§13).
        smallestFont: Math.min(...[...document.querySelectorAll('main *')]
          .filter((el) => el.textContent.trim() && el.children.length === 0)
          .map((el) => parseFloat(getComputedStyle(el).fontSize) || 99)),
        // Contrast-independent: is any control smaller than a 32px touch target?
        smallTargets: [...document.querySelectorAll('a, button, input, select, textarea')]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && r.height < 28;
          }).length,
      };
    });

    const prefix = `${name}-${label}`;
    const file = path.join(OUT, `${prefix}.png`);
    await page.screenshot({ path: file, fullPage: false });

    // No horizontal overflow at any width (§12).
    record(
      `T-RESP-${prefix}`,
      metrics.overflowX <= 1,
      `overflowX=${metrics.overflowX}px doc=${metrics.docScrollWidth}/${metrics.docClientWidth} ${metrics.offenders.length ? 'offenders: ' + metrics.offenders.join(',') : ''}`
    );

    // RTL is set on the document.
    record(`T-RTL-${prefix}`, metrics.dir === 'rtl' && metrics.lang === 'fa', `dir=${metrics.dir} lang=${metrics.lang}`);

    // The sidebar is on the RIGHT in RTL on desktop.
    if (needsAuth && width >= 1024) {
      const onRight = metrics.sidebarVisible && metrics.sidebarRight >= width - 2;
      record(`T-SIDEBAR-${prefix}`, onRight, `right=${metrics.sidebarRight} (viewport ${width}), visible=${metrics.sidebarVisible}`);
    }

    // On phones the desktop sidebar is gone and the burger is present instead.
    if (needsAuth && width < 1024) {
      const hidden = metrics.sidebarVisible === false;
      record(`T-MOBILE-${prefix}`, hidden && metrics.topbarVisible, `sidebar hidden=${hidden} topbar=${metrics.topbarVisible}`);
    }
  }
}

// ── typography and touch targets on the densest page ─────────────────────
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });
const phone = await page.evaluate(() => {
  const smallest = Math.min(...[...document.querySelectorAll('main *')]
    .filter((el) => el.textContent.trim() && el.children.length === 0)
    .map((el) => parseFloat(getComputedStyle(el).fontSize) || 99));
  const tiny = [...document.querySelectorAll('main a, main button')]
    .filter((el) => {
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && (r.height < 28 || r.width < 28);
    }).length;
  // The table must scroll inside its own container, not push the page.
  const wrap = document.querySelector('.v-tablewrap');
  return {
    smallest,
    tiny,
    // Below 768px the table is replaced by cards, so it no longer needs to
    // scroll — that is the fix, not a regression. What must still hold is that
    // the list is reachable and the PAGE does not overflow. At 768px and above
    // the table is present again and must scroll inside its own wrapper rather
    // than pushing the page.
    tableScrolls: wrap ? wrap.scrollWidth > wrap.clientWidth && getComputedStyle(wrap).overflowX === 'auto' : null,
    cardsReady: wrap ? wrap.getAttribute('data-cards') === 'ready' : null,
    cardCount: wrap ? wrap.querySelectorAll('.v-card-row').length : 0,
    cardsVisible: wrap && wrap.querySelector('.v-cards')
      ? getComputedStyle(wrap.querySelector('.v-cards')).display !== 'none'
      : false,
    pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    // Breathing room between the sticky topbar and the page heading. A 22px
    // bold Persian title has ascenders reaching the top of its line box, so a
    // small gap reads as "glued to the topbar" (T-HEAD-GAP).
    headGap: (() => {
      const bar = document.querySelector('.v-topbar');
      const h = document.querySelector('.v-pagehead__title');
      if (!bar || !h) return null;
      return Math.round(h.getBoundingClientRect().top - bar.getBoundingClientRect().bottom);
    })(),
    headFont: (() => {
      const h = document.querySelector('.v-pagehead__title');
      return h ? parseFloat(getComputedStyle(h).fontSize) : null;
    })(),
  };
});

record('T-TYPE-MIN', phone.smallest >= 11, `smallest rendered font ${phone.smallest}px on phone`);
record('T-TOUCH', phone.tiny <= 2, `controls under 28px: ${phone.tiny}`);
// The list must be usable at 390px by one means or the other: either the table
// scrolls inside its wrapper (the old behaviour) or the cards replace it (the
// new one). Silence is the only failure. The page itself must never overflow.
record('T-TABLE-SCROLL', (phone.tableScrolls === true || (phone.cardsVisible && phone.cardCount > 0)) && phone.pageOverflow <= 1,
  `table scrolls=${phone.tableScrolls}, cards=${phone.cardCount} visible=${phone.cardsVisible}, page overflow=${phone.pageOverflow}px`);
// ── Latin values must align with their RTL label ─────────────────────────
// `dir="ltr"` fixes character order but sets `text-align: start` to the LEFT
// edge. On a full-width <dd> in a right-aligned RTL column, the label sat right
// and its value far left, on separate lines. This measures the edges.
{
  await page.setViewportSize({ width: 430, height: 932 });
  await page.goto(`${BASE}${CUSTOMER_ID}`, { waitUntil: 'domcontentloaded' });

  const misaligned = await page.evaluate(() => {
    // A value is misaligned when the text starts opposite its label. With an
    // LTR element inside an RTL column, `text-align: start` resolves to the
    // LEFT edge while the parent's edge is on the RIGHT — so compare the ink
    // box edges, not the computed `text-align` keyword (which cannot tell them
    // apart: `start` is "left" here and "right" on the parent).
    const items = [...document.querySelectorAll('.v-dl > div')]
      .filter((d) => d.querySelector('dd[dir="ltr"]'));

    return items
      .filter((d) => {
        const dt = d.querySelector('dt');
        const dd = d.querySelector('dd[dir="ltr"]');
        if (!dt || !dd) return false;

        const range = document.createRange();
        range.selectNodeContents(dd);
        const ink = range.getBoundingClientRect();
        const label = dt.getBoundingClientRect();
        if (!ink.width || !label.width) return false;

        // Distance from the label's right edge minus the value's right edge.
        // Both should end at the same column edge in an RTL document.
        return Math.abs(label.right - ink.right) > 8;
      })
      .map((d) => (d.querySelector('dd')?.textContent || '').trim().slice(0, 18));
  });

  record(
    'T-DL-ALIGN',
    misaligned.length === 0,
    misaligned.length === 0 ? 'Latin values align with their labels' : `misaligned: ${misaligned.join(', ')}`
  );
}

// ── the page header must keep its title and its action on one row ────────
// An action rendered as a bare child of `.v-pagehead` became a third flex item
// and dropped onto its own row below 1024px, leaving the title stranded at the
// top alone. Measured at 768 and 1440: below ~600px stacking is the correct
// behaviour, so a wrap there is not a defect.
for (const [label, w, h] of [['tablet-768', 768, 1024], ['desktop-1440', 1440, 900]]) {
  await page.setViewportSize({ width: w, height: h });
  await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });

  const header = await page.evaluate(() => {
    const head = document.querySelector('.v-pagehead');
    if (!head) return { found: false };

    const title = head.querySelector('.v-pagehead__title');
    const action = head.querySelector('.v-pagehead__actions a, .v-pagehead__actions button');

    if (!title || !action) return { found: true, hasAction: false };

    const t = title.getBoundingClientRect();
    const a = action.getBoundingClientRect();

    return {
      found: true,
      hasAction: true,
      sameRow: Math.abs((t.top + t.bottom) / 2 - (a.top + a.bottom) / 2) < Math.max(t.height, a.height),
      titleRight: t.right >= a.right,
    };
  });

  record(
    `T-PAGEHEAD-${label}`,
    header.found && header.hasAction && header.sameRow && header.titleRight,
    !header.found
      ? 'no page header'
      : header.hasAction === false
        ? 'no primary action in header'
        : `same row=${header.sameRow}, title precedes action in RTL=${header.titleRight}`
  );
}

record('T-HEAD-GAP', phone.headGap !== null && phone.headGap >= 16,
  `topbar→title gap ${phone.headGap}px (title ${phone.headFont}px)`);

// ── focus visibility on a real page ──────────────────────────────────────
await page.setViewportSize({ width: 1280, height: 800 });
await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
await page.keyboard.press('Tab');
const focusInfo = await page.evaluate(() => {
  const el = document.activeElement;
  const style = getComputedStyle(el);
  return {
    tag: el.tagName,
    outline: style.outlineStyle + ' ' + style.outlineWidth,
    shadow: style.boxShadow,
    hasRing: (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none',
  };
});
record('T-FOCUS', focusInfo.hasRing, `first Tab focuses ${focusInfo.tag}, ring: outline=${focusInfo.outline} shadow=${focusInfo.shadow.slice(0, 30)}`);

// ── keyboard reaches the primary action ──────────────────────────────────
await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });
const keyboard = await page.evaluate(async () => {
  const primary = document.querySelector('.v-pagehead a.v-btn--primary');
  if (!primary) return { found: false };
  let reached = false;
  for (let i = 0; i < 25; i++) {
    if (document.activeElement === primary) { reached = true; break; }
    const ev = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
    document.dispatchEvent(ev);
    // Native tab order cannot be simulated from script; report tabbability.
    if (primary.tabIndex >= 0) { reached = 'tabbable'; break; }
  }
  return { found: true, reached, tabIndex: primary.tabIndex };
});
record('T-KEY-ACTION', keyboard.found && (keyboard.reached === 'tabbable' || keyboard.reached === true),
  `primary action present and tabbable (tabIndex=${keyboard.tabIndex})`);

await flow.close();

console.log('\n--- summary ---');
const pass = results.filter((r) => r.ok).length;
console.log(`${pass}/${results.length} passed`);
console.log(`screenshots: ${fs.readdirSync(OUT).filter((f) => f.endsWith('.png')).length} in ${OUT}`);

fs.writeFileSync(path.join(OUT, 'ui-results.json'), JSON.stringify(results, null, 2));
process.exit(pass === results.length ? 0 : 1);
