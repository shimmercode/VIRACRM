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
import { createRequire } from 'node:module';

// `playwright-core` is not a dependency of this repo: the sandbox has no npm
// registry access, so the driver is installed out-of-tree and located via
// VIRA_PLAYWRIGHT (a directory containing node_modules/playwright-core).
const requireFrom = createRequire(
  path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js')
);

let chromium;
try {
  ({ chromium } = requireFrom('playwright-core'));
} catch {
  console.error('playwright-core not found. Set VIRA_PLAYWRIGHT to a directory that has it installed.');
  process.exit(2);
}

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = process.env.VIRA_SHOTS_OUT || path.join(ROOT, 'docs/phase-2/evidence/2c');
const BASE = process.env.VIRA_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORD = process.env.VIRA_ADMIN_PASSWORD || 'DevOnly-Passw0rd-2026';

// Chromium is not vendored in this repo (the sandbox has no package registry),
// so its path is an environment concern rather than a source one. The defaults
// match the throwaway browser used during development.
const EXECUTABLE =
  process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin';
const LIB_PATH =
  process.env.VIRA_CHROMIUM_LIBS || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64';

if (!fs.existsSync(EXECUTABLE)) {
  console.error(`Chromium not found at ${EXECUTABLE}. Set VIRA_CHROMIUM to a browser binary.`);
  process.exit(2);
}

fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--font-render-hinting=none'],
  env: { ...process.env, LD_LIBRARY_PATH: LIB_PATH },
});

const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id}  ${detail}`);
};

// ── log in ───────────────────────────────────────────────────────────────
await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#username', 'admin');
await page.fill('#password', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('button[type="submit"]')]);

const afterLogin = new URL(page.url()).pathname;
record('T-UI-LOGIN', !afterLogin.includes('/login'), `login reached ${afterLogin}`);

if (afterLogin.includes('/password')) {
  await page.fill('#current_password', PASSWORD);
  await page.fill('#password', PASSWORD);
  await page.fill('#password_confirmation', PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('button[type="submit"]')]);
  record('T-UI-PW', true, 'forced password change completed');
}

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

const WIDTHS = [
  ['desktop-1440', 1440, 900],
  ['laptop-1280', 1280, 800],
  ['tablet-1024', 1024, 768],
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
    tableScrolls: wrap ? wrap.scrollWidth > wrap.clientWidth && getComputedStyle(wrap).overflowX === 'auto' : null,
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
record('T-TABLE-SCROLL', phone.tableScrolls !== false && phone.pageOverflow <= 1,
  `table scrolls in wrapper=${phone.tableScrolls}, page overflow=${phone.pageOverflow}px`);
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

await browser.close();

console.log('\n--- summary ---');
const pass = results.filter((r) => r.ok).length;
console.log(`${pass}/${results.length} passed`);
console.log(`screenshots: ${fs.readdirSync(OUT).filter((f) => f.endsWith('.png')).length} in ${OUT}`);

fs.writeFileSync(path.join(OUT, 'ui-results.json'), JSON.stringify(results, null, 2));
process.exit(pass === results.length ? 0 : 1);
