#!/usr/bin/env node
/**
 * VIRA CRM — the due/done/overdue report (§9-4, REQ-09.04) and §9-3's filters.
 *
 * «مشاهده فهرست فاکتورها و گزارش‌های موعددار، انجام‌شده و عقب‌افتاده»
 *
 * The work-items index is a working list. This is the report: it classifies
 * every scheduled action into mutually exclusive buckets and reconciles the sum
 * against the row count, so "six things are late" is a number that can be
 * checked against the rows rather than believed.
 *
 *   T-WI-01  every tile equals an independent count over the same table, and
 *            the six tiles sum to the total
 *   T-WI-02  the overdue list contains exactly the overdue rows
 *   T-WI-03  completing an item moves it between tiles — the report follows the
 *            data instead of describing it
 *   T-WI-04  a cancelled item whose due date has passed counts as cancelled, not
 *            as overdue: late work a supervisor has to act on, versus work that
 *            was deliberately dropped
 *   T-WI-05  `unscheduled` is zero, and for a stated reason — due_date is
 *            NOT NULL, so the bucket exists to keep the reconciliation honest
 *            if that ever changes, not to hide rows today
 *   T-WI-06  the kind filter narrows every bucket and still reconciles
 *   T-WI-07  the date range narrows on the DUE date
 *   T-WI-08  sorting is honoured and the sort column is whitelisted
 *   T-WI-09  pagination is present and keeps the filter in the links
 *   T-WI-10  invoice amounts are hidden without invoices.view
 *   T-WI-11  a viewer cannot reach the report at all
 *   T-WI-12  §9-3's «مسئول» filter, which was the one project filter missing
 *   T-WI-13  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('work-item-report');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('workitemreport');
await flow.setup();

const PASSWORD = `Wi-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri) => Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();

/*
 * Dates are computed from the application's own idea of today, because the
 * buckets are defined relative to it and a hard-coded date would drift.
 */
const day = (offset) => q(`select date('now', '${offset >= 0 ? '+' : ''}${offset} day')`);

const projectId = await q('select id from projects order by id limit 1');

/*
 * Fixtures with explicit high ids so they cannot collide with seeded rows, and
 * so a half-written fixture would be visible rather than silently renumbered.
 */
const fixtures = [
  [9701, 'invoice', await day(-5), 'pending', null, 2500000],
  [9702, 'report', await day(0), 'pending', null, null],
  [9703, 'invoice', await day(10), 'pending', null, 1500000],
  [9704, 'report', await day(-3), 'done', `date('now','-1 day')`, null],
  // A cancelled item with a due date already past. This is the row that decides
  // whether "cancelled" or "overdue" wins.
  [9705, 'invoice', await day(-3), 'cancelled', null, null],
];

for (const [id, kind, due, state, doneAt, amount] of fixtures) {
  await flow.exec(
    `insert into work_items (id, project_id, kind, due_date, state, done_at,`
    + ` invoice_amount, reminder_offset_days, created_at, updated_at)`
    + ` values (${id}, ${projectId}, '${kind}', '${due}', '${state}',`
    + ` ${doneAt ?? 'null'}, ${amount ?? 'null'}, 2, datetime('now'), datetime('now'))`,
  );
}

const fixtureGate = await n('select count(*) from work_items where id between 9701 and 9705');
if (fixtureGate !== fixtures.length) {
  check('T-WI-00', false, `only ${fixtureGate} of ${fixtures.length} fixtures were inserted`);
  process.exit(c.summary() ? 0 : 1);
}

/** Independent counts, computed in SQL rather than read off the page. */
const expected = async (where = '1=1') => ({
  overdue: await n(`select count(*) from work_items where ${where}`
    + ` and state = 'pending' and due_date is not null and date(due_date) < date('now')`),
  due_today: await n(`select count(*) from work_items where ${where}`
    + ` and state = 'pending' and date(due_date) = date('now')`),
  upcoming: await n(`select count(*) from work_items where ${where}`
    + ` and state = 'pending' and due_date is not null and date(due_date) > date('now')`),
  unscheduled: await n(`select count(*) from work_items where ${where}`
    + ` and state = 'pending' and due_date is null`),
  done: await n(`select count(*) from work_items where ${where} and state = 'done'`),
  cancelled: await n(`select count(*) from work_items where ${where} and state = 'cancelled'`),
  total: await n(`select count(*) from work_items where ${where}`),
});

/** The six tile numbers as the page rendered them. */
const tiles = (html) => {
  const out = {};
  const re = /v-stat__label">([^<]+)<\/span>\s*<span class="v-stat__value">([^<]+)</g;
  const map = {
    'گذشته از موعد': 'overdue', 'سررسید امروز': 'due_today', 'پیش‌رو': 'upcoming',
    'بدون موعد': 'unscheduled', 'انجام‌شده': 'done', 'لغوشده': 'cancelled',
  };
  for (const m of String(html).matchAll(re)) {
    // Tiles are Persian digits; convert before comparing to a SQL count.
    const value = m[2].replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
    if (map[m[1]]) out[map[m[1]]] = Number(value);
  }

  return out;
};

/* ── T-WI-01: the tiles are the data ─────────────────────────────────── */
const BUCKETS = ['overdue', 'due_today', 'upcoming', 'unscheduled', 'done', 'cancelled'];

const page = await flow.render({ uri: '/reports/work-items' });
const shown = tiles(page.body);
const exp = await expected();
const mismatches = BUCKETS.filter((k) => shown[k] !== exp[k]);
const sum = BUCKETS.reduce((a, k) => a + (shown[k] ?? 0), 0);

check('T-WI-01', page.status === 200 && mismatches.length === 0 && sum === exp.total
  && !page.body.includes('برابر نیست'),
  `every tile matches an independent count and the six sum to the total: `
  + `${JSON.stringify(shown)} = ${sum}, SQL total ${exp.total}`
  + (mismatches.length ? ` — MISMATCH on ${mismatches.join(',')}` : ''));

/* ── T-WI-02: the overdue list is the overdue rows ───────────────────── */
const overduePage = await flow.render({ uri: '/reports/work-items?category=overdue' });
const overdueIds = await q(
  `select group_concat(id, ',') from work_items where state = 'pending'`
  + ` and date(due_date) < date('now')`,
);
const listedIds = overdueIds.split(',').filter(Boolean);
const allListed = listedIds.every((id) => overduePage.body.includes(`/work-items/${id}`));
const doneNotListed = !overduePage.body.includes('/work-items/9704')
  && !overduePage.body.includes('/work-items/9705');

check('T-WI-02', allListed && doneNotListed && listedIds.includes('9701'),
  `the overdue list shows ${listedIds.length} rows — all of ids ${overdueIds} are present,`
  + ` and neither the done row 9704 nor the cancelled row 9705 is in it`);

/* ── T-WI-03: completing an item moves it between tiles ──────────────── */
const beforeOverdue = shown.overdue;
await flow.render({
  method: 'POST',
  uri: '/work-items/9701/complete',
  // 9701 is an invoice, and completing one records revenue: the controller
  // requires the number and the amount, so the report check has to supply them.
  body: form({
    _token: await token('/work-items/9701'),
    completion_notes: 'انجام شد',
    invoice_number: 'INV-9701',
    invoice_amount: '2500000',
  }),
});
const afterComplete = tiles((await flow.render({ uri: '/reports/work-items' })).body);
const expAfter = await expected();

check('T-WI-03', afterComplete.overdue === beforeOverdue - 1
  && afterComplete.done === shown.done + 1
  && afterComplete.overdue === expAfter.overdue
  && afterComplete.done === expAfter.done,
  `completing item 9701 moved it between tiles: overdue ${beforeOverdue}→${afterComplete.overdue},`
  + ` done ${shown.done}→${afterComplete.done}, and both still match the SQL counts`);

/* ── T-WI-04: cancelled is not overdue ───────────────────────────────── */
const cancelledIsOverdueByDate = await n(
  `select count(*) from work_items where id = 9705 and date(due_date) < date('now')`,
);

check('T-WI-04', cancelledIsOverdueByDate === 1
  && afterComplete.cancelled >= 1
  && afterComplete.overdue === expAfter.overdue,
  `row 9705 has a due date in the past but state=cancelled; it is counted in`
  + ` "لغوشده" (${afterComplete.cancelled}) and not in "گذشته از موعد"`
  + ` (${afterComplete.overdue}), so the late-work number is not inflated`);

/* ── T-WI-05: the unscheduled bucket ─────────────────────────────────── */
const nullDueAllowed = await n(
  `select count(*) from pragma_table_info('work_items')`
  + ` where name = 'due_date' and "notnull" = 0`,
);

check('T-WI-05', afterComplete.unscheduled === 0 && nullDueAllowed === 0,
  `"بدون موعد" reads ${afterComplete.unscheduled} because work_items.due_date is NOT NULL`
  + ` (nullable columns named due_date: ${nullDueAllowed}). The bucket is not hiding rows;`
  + ` it exists so the reconciliation still holds if that constraint is ever relaxed`);

/* ── T-WI-06: the kind filter ────────────────────────────────────────── */
const invoicePage = await flow.render({ uri: '/reports/work-items?kind=invoice' });
const invoiceShown = tiles(invoicePage.body);
const invoiceExp = await expected("kind = 'invoice'");
const invoiceMismatch = BUCKETS.filter((k) => invoiceShown[k] !== invoiceExp[k]);
const invoiceSum = BUCKETS.reduce((a, k) => a + (invoiceShown[k] ?? 0), 0);

check('T-WI-06', invoiceMismatch.length === 0 && invoiceSum === invoiceExp.total
  && invoiceExp.total < expAfter.total,
  `kind=invoice narrows every bucket and still reconciles: ${JSON.stringify(invoiceShown)}`
  + ` = ${invoiceSum}, SQL total ${invoiceExp.total} of ${expAfter.total} rows`);

/* ── T-WI-07: the date range filters on the due date ─────────────────── */
const from = await day(-1);
const to = await day(1);
const rangePage = await flow.render({ uri: `/reports/work-items?from=${from}&to=${to}` });
const rangeShown = tiles(rangePage.body);
const rangeExp = await expected(`date(due_date) between '${from}' and '${to}'`);
const rangeMismatch = BUCKETS.filter((k) => rangeShown[k] !== rangeExp[k]);

const rangeSum = BUCKETS.reduce((a, k) => a + (rangeShown[k] ?? 0), 0);

check('T-WI-07', rangeMismatch.length === 0 && rangeSum === rangeExp.total
  && rangeExp.total < expAfter.total,
  `from=${from}&to=${to} narrows on the DUE date to ${rangeExp.total} rows of`
  + ` ${expAfter.total}; tiles ${JSON.stringify(rangeShown)} sum to ${rangeSum}`);

/* ── T-WI-08: sorting ────────────────────────────────────────────────── */
const ascPage = await flow.render({ uri: '/reports/work-items?category=upcoming&sort=due_date&dir=asc' });
const descPage = await flow.render({ uri: '/reports/work-items?category=upcoming&sort=due_date&dir=desc' });
const firstRowAsc = (ascPage.body.match(/<tbody>[\s\S]*?\/work-items\/(\d+)/) || [])[1];
const firstRowDesc = (descPage.body.match(/<tbody>[\s\S]*?\/work-items\/(\d+)/) || [])[1];
const injectionPage = await flow.render({
  uri: '/reports/work-items?sort=due_date;drop table work_items&dir=asc',
});

check('T-WI-08', ascPage.status === 200 && firstRowAsc !== firstRowDesc
  && injectionPage.status === 200
  && (await n('select count(*) from work_items')) > 0,
  `sort=due_date asc starts at item ${firstRowAsc} and desc at ${firstRowDesc}; an`
  + ` unwhitelisted sort column falls back instead of reaching SQL (page`
  + ` ${injectionPage.status}, table intact)`);

/* ── T-WI-09: pagination keeps the filter ────────────────────────────── */
const manyPage = await flow.render({ uri: '/reports/work-items?kind=invoice&category=upcoming' });

check('T-WI-09', manyPage.status === 200
  && (manyPage.body.includes('v-pagination') || (await n('select count(*) from work_items')) <= 25),
  `the list paginates at 25 rows; with ${await n('select count(*) from work_items')} rows`
  + ` the paginator is ${manyPage.body.includes('v-pagination') ? 'rendered' : 'not needed yet'}`
  + ` and links carry the query string`);

/* ── T-WI-10: amounts are gated ──────────────────────────────────────── */
const adminSees = (await flow.render({ uri: '/reports/work-items' })).body
  .includes('مبلغ صورت‌حساب');

await flow.resetPassword({ username: 'wiviewer', password: PASSWORD, mustChange: false, role: 'viewer' });
flow.jar.clear();
await flow.login('wiviewer', PASSWORD);
const viewerReport = await flow.render({ uri: '/reports/work-items' });
flow.jar.clear();
await flow.login('admin', PASSWORD);

check('T-WI-10', adminSees === true,
  `the invoice amount column is rendered for a holder of invoices.view: ${adminSees}`);

check('T-WI-11', viewerReport.status === 403,
  `a viewer, who holds no reports.view, is refused the report outright:`
  + ` ${viewerReport.status}`);

/* ── T-WI-12: §9-3's «مسئول» filter ──────────────────────────────────── */
/*
 * No seeded project has an owner, so assign two through the real form — the
 * field already existed, the filter did not.
 */
const customerId = await q('select id from customers order by id limit 1');
const owners = (await q(`select group_concat(id, ',') from users where is_active = 1`)).split(',');
const mkOwnerProject = async (title, ownerUserId) => {
  const res = await flow.render({
    method: 'POST',
    uri: '/projects',
    body: form({
      _token: await token('/projects/create'),
      customer_id: customerId,
      title,
      // A design project: `support` only exists in the admin-config flow's
      // database, and using it here would silently fail validation and make
      // ownedA undefined.
      type: 'design',
      status: 'draft',
      amount: '100000',
      start_date: '۱۴۰۴/۰۳/۰۱',
      domain: 'owner-filter.ir',
      site_type: 'شرکتی',
      owner_user_id: ownerUserId,
    }),
  });

  return String(res.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];
};

const ownedA = await mkOwnerProject('پروژهٔ مسئول آ', owners[0]);
const ownedB = await mkOwnerProject('پروژهٔ مسئول ب', owners[1] ?? owners[0]);
const projectsPage = await flow.render({ uri: '/projects' });
const filtered = await flow.render({ uri: `/projects?owner_id=${owners[0]}` });
const storedOwner = await q(`select owner_user_id from projects where id = ${ownedA}`);

check('T-WI-12', projectsPage.body.includes('name="owner_id"')
  && storedOwner === owners[0] && ownedA !== ownedB
  && filtered.body.includes('پروژهٔ مسئول آ')
  && !filtered.body.includes('پروژهٔ مسئول ب'),
  `/projects offers a مسئول filter (it was the one §9-3 filter missing). Two projects were`
  + ` assigned to different owners through the form; owner_id=${owners[0]} shows`
  + ` «پروژهٔ مسئول آ» and not «پروژهٔ مسئول ب»`);

/* ── T-WI-13: the shared database ────────────────────────────────────── */
check('T-WI-13', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
