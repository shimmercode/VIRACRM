#!/usr/bin/env node
/**
 * VIRA CRM — no horizontal overflow at the desktop/tablet widths.
 *
 * THE DEFECT THIS GUARDS
 * ----------------------
 * At exactly 1024px, `/customers` and `/contracts` scrolled 33px inside their
 * table wrapper. The cause was `min-width: 720px` on `.v-table`: at 1024px the
 * sidebar takes 264px and `.v-content` has 28px padding a side, so the table had
 * 687px — 720 − 687 = 33. The table's natural (fit-content) width is 687px, so
 * the floor, not the content, caused the overflow.
 *
 * It survived because nothing asserted it. `ui-shots` checks the *page* overflow,
 * and this never reached the page — it stayed inside the wrapper. A scrollbar
 * inside a box is easy to miss and impossible to notice in a screenshot.
 *
 * Fix: `min-width: min(720px, 100%)` — the floor yields to the space that
 * exists. This suite asserts both halves of that: no overflow anywhere, and the
 * table has not been collapsed to something unreadable to achieve it.
 *
 * The 768 and 1440 widths are included because the fix must not trade one width
 * for another, and the 390 width proves the card view below 768px is untouched.
 */
import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(28)} ${detail}`);
};

// The exact viewport that used to overflow, plus its neighbours.
const WIDTHS = [768, 1024, 1280, 1440];
const PAGES = ['/customers', '/contracts'];

// Below this a data table stops being readable; above it we are merely wasting
// space. 620px is the floor the phone breakpoint already used.
const MIN_READABLE = 620;

const flow = await startBrowserFlow('overflow', { viewport: { width: 1024, height: 900 } });

for (const width of WIDTHS) {
  for (const path of PAGES) {
    const page = await flow.browser.newPage({ viewport: { width, height: 900 } });
    await uiLogin(page, flow.baseUrl, 'admin', flow.password);
    await page.goto(`${flow.baseUrl}${path}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(250);

    const m = await page.evaluate((minReadable) => {
      const doc = document.documentElement;
      const wrap = document.querySelector('.v-tablewrap');
      const table = wrap?.querySelector('table');

      // Anything wider than the viewport, so a future overflow is named rather
      // than merely counted.
      const offenders = [];
      if (wrap) {
        wrap.querySelectorAll('*').forEach((el) => {
          const b = el.getBoundingClientRect();
          if (b.width > 0 && (b.right > doc.clientWidth + 1 || b.left < -1)) {
            offenders.push(`${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`);
          }
        });
      }

      return {
        docOverflow: doc.scrollWidth - doc.clientWidth,
        wrapOverflow: wrap ? wrap.scrollWidth - wrap.clientWidth : null,
        wrapWidth: wrap ? Math.round(wrap.clientWidth) : null,
        tableWidth: table ? Math.round(table.getBoundingClientRect().width) : null,
        tableDisplay: table ? getComputedStyle(table).display : null,
        minReadable,
        offenders: [...new Set(offenders)].slice(0, 5),
      };
    }, MIN_READABLE);

    const tag = `${path.slice(1)}-${width}`;

    record(`T-OVF-${tag}-doc`, m.docOverflow <= 0,
      `page overflow ${m.docOverflow}px at ${width}px`);

    record(`T-OVF-${tag}-wrap`, m.wrapOverflow === 0,
      `table wrapper overflow ${m.wrapOverflow}px (was 33 at 1024)`);

    record(`T-OVF-${tag}-readable`, m.tableWidth >= Math.min(MIN_READABLE, m.wrapWidth ?? 0),
      `table ${m.tableWidth}px in ${m.wrapWidth}px wrapper (min readable ${MIN_READABLE})`);

    record(`T-OVF-${tag}-table`, m.tableDisplay === 'table',
      `table display=${m.tableDisplay} (must stay a table at ${width}px)`);

    if (m.offenders.length) {
      record(`T-OVF-${tag}-offenders`, false, `overflowing: ${m.offenders.join(', ')}`);
    } else {
      record(`T-OVF-${tag}-offenders`, true, 'nothing extends past the viewport');
    }

    await page.close();
  }
}

// ── The fix must not have broken card view below 768px ────────────────────
/*
 * 360 joins 390 here rather than in WIDTHS above, because these widths are
 * card-mode: the table is intentionally display:none and replaced by
 * .v-card-row elements. Running the table assertions at 360 would assert
 * against a layout the design system deliberately does not use, and "fail"
 * on correct behaviour.
 */
const CARD_WIDTHS = [[390, 844], [360, 800]];

for (const [cardWidth, cardHeight] of CARD_WIDTHS) {
  const page = await flow.browser.newPage({ viewport: { width: cardWidth, height: cardHeight } });
  await uiLogin(page, flow.baseUrl, 'admin', flow.password);

  for (const path of PAGES) {
    await page.goto(`${flow.baseUrl}${path}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.v-tablewrap[data-cards="ready"]', { timeout: 10000 }).catch(() => {});

    const m = await page.evaluate(() => {
      const wrap = document.querySelector('.v-tablewrap');
      return {
        cards: wrap.querySelectorAll('.v-card-row').length,
        tableDisplay: getComputedStyle(wrap.querySelector('table')).display,
        wrapOverflow: wrap.scrollWidth - wrap.clientWidth,
        docOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    });

    const tag = path.slice(1);
    record(`T-OVF-${tag}-${cardWidth}-cards`, m.cards > 0 && m.tableDisplay === 'none',
      `${m.cards} cards, table display=${m.tableDisplay} at ${cardWidth}px`);
    record(`T-OVF-${tag}-${cardWidth}-overflow`, m.wrapOverflow <= 0 && m.docOverflow <= 0,
      `wrapper ${m.wrapOverflow}px, page ${m.docOverflow}px`);
  }

  await page.close();
}

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}
