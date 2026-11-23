#!/usr/bin/env node
/**
 * VIRA CRM — active filter chips.
 *
 * The requirement is specific: each chip must remove ITS OWN filter and leave
 * every other filter intact. That is the whole reason the component exists, so
 * it is what this suite proves — by building a URL with several filters active
 * and following each chip.
 *
 * Assertions per page:
 *   1. one chip per active filter, no more, no fewer
 *   2. each chip's href drops exactly one parameter
 *   3. every other parameter survives in that href
 *   4. following a chip really removes only that filter (server round trip)
 *   5. "clear all" removes everything
 *   6. no chips when nothing is filtered
 *   7. chips are keyboard reachable and have an accessible name
 *
 * Runs against its own database copy and dev server, like every other suite.
 */
import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(34)} ${detail}`);
};

const flow = await startBrowserFlow('filter-chips', { viewport: { width: 1280, height: 900 } });

/** Count chips and read their hrefs, plus the chip labels. */
const readChips = (page) => page.evaluate(() => {
  const chips = [...document.querySelectorAll('.v-chip')];
  return {
    count: chips.length,
    clear: document.querySelector('.v-chips__clear')?.getAttribute('href') || null,
    items: chips.map((a) => ({
      href: a.getAttribute('href'),
      label: a.getAttribute('aria-label'),
      text: a.textContent.replace(/\s+/g, ' ').trim(),
      // A chip must be a link so it is keyboard reachable and works without JS.
      isLink: a.tagName === 'A',
      tabbable: a.tabIndex >= 0,
    })),
  };
});

/** Parse a URL's query into a plain object. */
const qs = (href) => {
  const u = new URL(href, 'http://x');
  return Object.fromEntries(u.searchParams.entries());
};

// ── customers: q + type + status ─────────────────────────────────────────
{
  const page = await flow.browser.newPage({ viewport: { width: 1280, height: 900 } });
  await uiLogin(page, flow.baseUrl, 'admin', flow.password);

  const url = `${flow.baseUrl}/customers?q=آرمان&type=legal&status=active&sort=company_name&dir=asc`;
  await page.goto(url, { waitUntil: 'domcontentloaded' });

  const { count, items, clear } = await readChips(page);

  record('T-CHIP-CUST-count', count === 3, `${count} chips for 3 active filters (q, type, status)`);

  const byKey = (needle) => items.find((i) => i.href.includes(needle));

  // Each chip must drop exactly its own parameter.
  const cases = [
    { key: 'q', label: 'search' },
    { key: 'type', label: 'type' },
    { key: 'status', label: 'status' },
  ];

  for (const c of cases) {
    const chip = items.find((i) => {
      const q = qs(i.href);
      return q[c.key] === undefined;
    });

    if (!chip) {
      record(`T-CHIP-CUST-${c.key}`, false, `no chip removes "${c.key}"`);
      continue;
    }

    const q = qs(chip.href);
    const others = Object.keys(qs(url)).filter((k) => k !== c.key && k !== 'sort' && k !== 'dir');

    // Its own key gone, all other filters still present.
    const removedOwn = q[c.key] === undefined;
    const keptOthers = others.every((k) => q[k] !== undefined);
    // Sort is a view preference, not a filter — it must survive too.
    const keptSort = q.sort === 'company_name' && q.dir === 'asc';

    record(`T-CHIP-CUST-${c.key}-own`, removedOwn && keptOthers,
      `drops ${c.key}; keeps ${others.join(',')}${keptSort ? ' + sort' : ''}`);

    record(`T-CHIP-CUST-${c.key}-sort`, keptSort,
      `sort/dir preserved (${q.sort ?? '—'}/${q.dir ?? '—'})`);
  }

  record('T-CHIP-CUST-clear', clear !== null && qs(clear).q === undefined && qs(clear).status === undefined,
    `clear-all href drops all filters`);

  record('T-CHIP-CUST-links', items.every((i) => i.isLink && i.tabbable),
    `all chips are focusable <a> elements`);

  record('T-CHIP-CUST-names', items.every((i) => (i.label || '').length > 5),
    `every chip has an aria-label (e.g. "${items[0]?.label}")`);

  // ── following a chip really removes only that filter ──────────────────
  const statusChip = items.find((i) => {
    const q = qs(i.href);
    return q.status === undefined;
  });

  if (statusChip) {
    await page.goto(statusChip.href, { waitUntil: 'domcontentloaded' });
    const after = await page.evaluate(() => ({
      chips: [...document.querySelectorAll('.v-chip')].length,
      statusSelect: document.querySelector('#f-status')?.value ?? null,
      typeSelect: document.querySelector('#f-type')?.value ?? null,
      // NB: the topbar has its own name="q" search. Every read here must be
      // scoped to .v-filterbar or it measures the wrong control.
      qInput: document.querySelector('.v-filterbar input[name="q"]')?.value ?? null,
    }));

    record('T-CHIP-CUST-follow', after.chips === 2 && after.statusSelect === '',
      `after following: ${after.chips} chips, status select="${after.statusSelect}"`);
    record('T-CHIP-CUST-keep', after.typeSelect === 'legal' && after.qInput === 'آرمان',
      `other filters survived: type="${after.typeSelect}" q="${after.qInput}"`);
  }

  await page.close();
}

// ── customers: no chips when nothing is filtered ─────────────────────────
{
  const page = await flow.browser.newPage({ viewport: { width: 1280, height: 900 } });
  await uiLogin(page, flow.baseUrl, 'admin', flow.password);
  await page.goto(`${flow.baseUrl}/customers`, { waitUntil: 'domcontentloaded' });

  const { count, clear } = await readChips(page);
  record('T-CHIP-EMPTY-none', count === 0, `${count} chips with no active filter`);
  record('T-CHIP-EMPTY-clear', clear === null, `no clear-all link when nothing is filtered`);

  await page.close();
}

// ── contracts: q + status + type ─────────────────────────────────────────
{
  const page = await flow.browser.newPage({ viewport: { width: 1280, height: 900 } });
  await uiLogin(page, flow.baseUrl, 'admin', flow.password);

  const url = `${flow.baseUrl}/contracts?q=فروشگاه&status=draft&type=seo`;
  await page.goto(url, { waitUntil: 'domcontentloaded' });

  const { count, items } = await readChips(page);
  record('T-CHIP-CONT-count', count === 3, `${count} chips for 3 active filters`);

  // Each chip drops exactly one key and keeps the other two.
  const allKeys = ['q', 'status', 'type'];
  let perChipOk = true;
  const detail = [];

  for (const key of allKeys) {
    const chip = items.find((i) => qs(i.href)[key] === undefined);
    if (!chip) {
      perChipOk = false;
      detail.push(`${key}:missing`);
      continue;
    }
    const q = qs(chip.href);
    const kept = allKeys.filter((k) => k !== key).every((k) => q[k] !== undefined);
    const own = q[key] === undefined;
    // And it must not drop a key it does not own.
    const extraDropped = allKeys.filter((k) => k !== key && q[k] === undefined);
    if (!(kept && own) || extraDropped.length) {
      perChipOk = false;
      detail.push(`${key}:${extraDropped.join('+')} lost`);
    } else {
      detail.push(`${key}:ok`);
    }
  }

  record('T-CHIP-CONT-independent', perChipOk, detail.join(' '));

  // Jalali, not Gregorian: a `from` date must not render as 2026-07-01.
  const withDate = `${flow.baseUrl}/contracts?from=2026-07-01`;
  await page.goto(withDate, { waitUntil: 'domcontentloaded' });
  const dateChip = await page.evaluate(() => {
    const a = document.querySelector('.v-chip');
    return a ? a.textContent.replace(/\s+/g, ' ').trim() : null;
  });

  const looksGregorian = dateChip !== null && /20\d{2}-\d{2}-\d{2}/.test(dateChip);
  const hasPersianDigits = dateChip !== null && /[۰-۹]/.test(dateChip);

  record('T-CHIP-CONT-jalali', dateChip !== null && !looksGregorian && hasPersianDigits,
    `date chip "${dateChip}" is Jalali, not Gregorian`);

  await page.close();
}

// ── layout: chips must not cause overflow at any width ───────────────────
for (const width of [390, 430, 768, 1024, 1440]) {
  const page = await flow.browser.newPage({ viewport: { width, height: 900 } });
  await uiLogin(page, flow.baseUrl, "admin", flow.password);
  await page.goto(
    `${flow.baseUrl}/customers?q=آرمان&type=legal&status=active`,
    { waitUntil: 'domcontentloaded' }
  );

  const m = await page.evaluate(() => {
    const doc = document.documentElement;
    const chips = document.querySelector('.v-chips');
    return {
      overflow: doc.scrollWidth - doc.clientWidth,
      chipRowOverflow: chips ? chips.scrollWidth - chips.clientWidth : 0,
      count: document.querySelectorAll('.v-chip').length,
    };
  });

  record(`T-CHIP-OVF-${width}`, m.overflow <= 0 && m.chipRowOverflow <= 0,
    `${m.count} chips, page ${m.overflow}px, chip row ${m.chipRowOverflow}px`);

  await page.close();
}

await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}
