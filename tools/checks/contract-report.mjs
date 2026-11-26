#!/usr/bin/env node
/**
 * VIRA CRM — the contract report (§9-4, REQ-09.04).
 *
 * «گزارش قراردادهای فعال، پایان‌یافته و نزدیک به انقضا»
 *
 * THE TRAP THIS SUITE EXISTS FOR
 * ------------------------------
 * `expiring` is a DERIVED state — ContractStatus::derived() says so, and no row
 * is ever stored with it. A report written the obvious way,
 * `where status = 'expiring'`, returns an empty set, renders a clean page with a
 * zero in it, and passes every smoke test. T-CR-04 asserts the opposite
 * direction: no contract is stored as expiring, and the near-expiry bucket is
 * nevertheless non-empty, because it is computed from the end date.
 *
 *   T-CR-00  the fixture covers every bucket, including the awkward ones
 *   T-CR-01  the report renders
 *   T-CR-02  each bucket's count matches an independent SQL count
 *   T-CR-03  the buckets are mutually exclusive and sum to the contract total
 *   T-CR-04  "expiring" comes from the end date, not from a stored status
 *   T-CR-05  a contract inside the warning window is not also counted as active
 *   T-CR-06  an active contract past its end date lands in "overdue", not in
 *            active and not silently dropped
 *   T-CR-07  EFFECT OF A SETTING: narrowing contracts.expiry_warning_days
 *            through the real settings screen moves a contract between buckets
 *   T-CR-08  the category filter really filters
 *   T-CR-09  an unknown category falls back instead of widening
 *   T-CR-10  a user without reports.view is refused in the backend
 *   T-CR-11  amounts are gated behind contracts.view_financials
 *   T-CR-12  days remaining read positive for a live contract, negative for one
 *            already past its end
 *   T-CR-13  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('contract-report');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('contractreport');
await flow.setup();

const PASSWORD = `Cr-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/reports/contracts') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();

/** Jalali-free: fixtures use ISO so the SQL and the report agree exactly. */
const dateOffset = (days) => `date('now', '${days >= 0 ? '+' : ''}${days} day')`;

/*
 * One contract per bucket, plus the two that decide whether the report is
 * honest: an active contract with no end date, and an active contract whose end
 * date has already passed. Explicit ids — see expert-workload.mjs for what
 * happens when a fixture's ids are computed instead of stated.
 */
const customerId = await q('select id from customers order by id limit 1');
const contractsBefore = await n('select count(*) from contracts');

const contract = (id, title, status, endOffset) => flow.exec(
  `insert into contracts (id, title, customer_id, amount, start_date, end_date, status,`
  + ` created_at, updated_at) values (${id}, '${title}', ${customerId}, 4000000,`
  + ` date('now'), ${endOffset === null ? 'null' : dateOffset(endOffset)}, '${status}',`
  + ` datetime('now'), datetime('now'))`,
);

await contract(9301, 'CR-درآستانه', 'active', 5);        // ends in 5 days  -> expiring
await contract(9302, 'CR-گذشته', 'active', -20);         // ended 20 days ago -> overdue
await contract(9303, 'CR-فعال', 'active', 200);          // far away        -> active
await contract(9304, 'CR-بی‌پایان', 'active', null);     // no end date     -> active
await contract(9305, 'CR-منقضی', 'expired', -400);       // -> finished
await contract(9306, 'CR-تمدیدشده', 'renewed', -300);    // -> finished
await contract(9307, 'CR-لغوشده', 'cancelled', -100);    // -> finished
await contract(9308, 'CR-پیش‌نویس', 'draft', 10);        // -> draft

check('T-CR-00', (await n("select count(*) from contracts where title like 'CR-%'")) === 8
  && (await n('select count(*) from contracts')) === contractsBefore + 8,
  `fixture: 8 CR contracts inserted, table ${contractsBefore} -> ${await n('select count(*) from contracts')}`);

/* ── T-CR-01: the page ────────────────────────────────────────────────── */
const page = await flow.render({ uri: '/reports/contracts' });
check('T-CR-01', page.status === 200 && page.body.includes('گزارش قراردادها'),
  `GET /reports/contracts -> ${page.status}`);

/*
 * The counts are read out of the page's stat tiles, and each is recomputed with
 * plain SQL that shares no code with the controller.
 */
/*
 * Parse the stat tiles structurally. Splitting on the label text and taking the
 * last occurrence looked simpler and was wrong: "فعال" and "در آستانه انقضا"
 * also appear in the page description and in the status badges of the contract
 * list, so the parse landed outside the tiles and read null.
 */
const fa = (str) => Number(String(str).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0)));
const tiles = (html) => {
  const out = {};
  for (const block of String(html).split('<a class="v-stat').slice(1)) {
    const seg = block.split('</a>')[0];
    const label = (seg.match(/v-stat__label[^>]*>\s*([^<]+)/) || [])[1]?.trim();
    const value = (seg.match(/v-stat__value[^>]*>\s*([۰-۹0-9]+)/) || [])[1];
    if (label && value !== undefined) out[label] = fa(value);
  }

  return out;
};

const sqlCounts = {
  expiring: await n(`select count(*) from contracts where deleted_at is null`
    + ` and status in ('active','expiring') and end_date is not null`
    + ` and date(end_date) >= date('now') and date(end_date) <= date('now','+30 day')`),
  overdue: await n(`select count(*) from contracts where deleted_at is null`
    + ` and status in ('active','expiring') and end_date is not null and date(end_date) < date('now')`),
  active: await n(`select count(*) from contracts where deleted_at is null`
    + ` and status in ('active','expiring') and (end_date is null or date(end_date) > date('now','+30 day'))`),
  finished: await n(`select count(*) from contracts where deleted_at is null`
    + ` and status in ('expired','renewed','cancelled')`),
  draft: await n(`select count(*) from contracts where deleted_at is null and status = 'draft'`),
};
const sqlTotal = await n('select count(*) from contracts where deleted_at is null');

const renderedTiles = tiles(page.body);
const rendered = {};
for (const [key, label] of Object.entries({
  expiring: 'در آستانه انقضا', overdue: 'گذشته از موعد', active: 'فعال',
  finished: 'پایان‌یافته', draft: 'پیش‌نویس',
})) {
  rendered[key] = renderedTiles[label] ?? null;
}

const mismatches = Object.keys(sqlCounts)
  .filter((k) => rendered[k] !== sqlCounts[k])
  .map((k) => `${k}: page ${rendered[k]} vs SQL ${sqlCounts[k]}`);

check('T-CR-02', mismatches.length === 0,
  mismatches.length === 0
    ? `page vs independent SQL — ${Object.entries(sqlCounts).map(([k, v]) => `${k}=${v}`).join(', ')}`
    : `MISMATCH ${mismatches.join('; ')}`);

/* ── T-CR-03: mutual exclusivity + reconciliation ─────────────────────── */
const sum = Object.values(sqlCounts).reduce((a, b) => a + b, 0);

check('T-CR-03', sum === sqlTotal && !page.body.includes('قابل اتکا نیست'),
  `buckets sum to ${sum}, contract total is ${sqlTotal}; the page shows no`
  + ` reconciliation warning: ${!page.body.includes('قابل اتکا نیست')}`);

/*
 * ── T-CR-04: the derived state ──────────────────────────────────────────
 *
 * The check that a naive implementation would pass and then be wrong about.
 */
const storedExpiring = await n("select count(*) from contracts where status = 'expiring'");

/*
 * Asserted against the PAGE, not the SQL. An earlier wording of this check
 * compared the independent count with itself and therefore still passed when
 * the controller was changed to read the stored status — the one bug it exists
 * to catch. The page's own tile is what has to disagree with the stored column.
 */
check('T-CR-04', storedExpiring === 0 && rendered.expiring === sqlCounts.expiring
  && rendered.expiring >= 1,
  `${storedExpiring} contracts are stored with status='expiring', yet the page's`
  + ` near-expiry tile reads ${rendered.expiring} (SQL ${sqlCounts.expiring}) — the`
  + ` bucket is computed from end_date inside the ${await q("select value from settings where key='contracts.expiry_warning_days'")}-day window`);

/* ── T-CR-05: not double counted ──────────────────────────────────────── */
const cr1 = await q("select title from contracts where id = 9301");
const listedExpiring = (await flow.render({ uri: '/reports/contracts?category=expiring' })).body;
const listedActive = (await flow.render({ uri: '/reports/contracts?category=active' })).body;

check('T-CR-05', listedExpiring.includes('CR-درآستانه') && !listedActive.includes('CR-درآستانه'),
  `"${cr1}" (ends in 5 days) appears under near-expiry and is absent from the`
  + ` active list — a contract is in one bucket, not two`);

/* ── T-CR-06: the overdue bucket ──────────────────────────────────────── */
const listedOverdue = (await flow.render({ uri: '/reports/contracts?category=overdue' })).body;
check('T-CR-06', sqlCounts.overdue >= 1 && listedOverdue.includes('CR-گذشته')
  && !listedActive.includes('CR-گذشته'),
  `an active contract whose end date passed 20 days ago is reported as overdue`
  + ` (${sqlCounts.overdue} in the bucket), not counted as active and not dropped`);

/*
 * ── T-CR-07: the setting actually drives the report ─────────────────────
 *
 * Changed through the real settings screen, not by writing the row, so this is
 * a test of the setting's effect rather than of the report reading a number.
 * With the window at 30 days a contract ending in 10 days is near expiry;
 * narrowing it to 2 days must move it out.
 */
await contract(9309, 'CR-مرزی', 'active', 10);

const settingId = await q("select id from settings where key = 'contracts.expiry_warning_days'");

/*
 * `flow.query()` returns one column of one row, so the whole settings table is
 * aggregated into a single string with separators that cannot appear in the
 * data — a unit separator between id and value, a record separator between
 * rows. Every row must be submitted: the settings screen treats an absent row
 * as a deletion.
 */
const allValues = async (override) => {
  const dump = await flow.query(
    "select group_concat(id || char(31) || coalesce(value, ''), char(30)) from settings",
  );
  const payload = {};
  for (const row of dump.split('\x1e').filter(Boolean)) {
    const [id, value] = row.split('\x1f');
    payload[`values[${id}]`] = id === settingId ? override : value;
  }

  return payload;
};
const setWindow = async (days) => flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({ _token: Flow.token((await flow.render({ uri: '/settings' })).body), ...(await allValues(String(days))) }),
});

const inWindow30 = (await flow.render({ uri: '/reports/contracts?category=expiring' })).body.includes('CR-مرزی');
const savedNarrow = await setWindow(2);
const storedWindow = await q("select value from settings where key='contracts.expiry_warning_days'");
const narrowPage = await flow.render({ uri: '/reports/contracts?category=expiring' });
const inWindow2 = narrowPage.body.includes('CR-مرزی');
await setWindow(30);

check('T-CR-07', inWindow30 === true && savedNarrow.status === 302 && storedWindow === '2' && inWindow2 === false,
  `CR-مرزی ends in 10 days: inside the 30-day window ${inWindow30}; after saving`
  + ` the window as 2 through POST /settings (${savedNarrow.status}, stored ${storedWindow})`
  + ` it is in the near-expiry list: ${inWindow2} — then restored to 30`);

/* ── T-CR-08/09: the filter ───────────────────────────────────────────── */
const finishedPage = await flow.render({ uri: '/reports/contracts?category=finished' });
check('T-CR-08', finishedPage.status === 200 && ['CR-منقضی', 'CR-تمدیدشده', 'CR-لغوشده']
  .every((t) => finishedPage.body.includes(t)) && !finishedPage.body.includes('CR-فعال'),
  `category=finished lists all three terminal contracts and none of the active ones`);

const bogus = await flow.render({ uri: '/reports/contracts?category=everything' });
const bogusExpiring = bogus.body.includes('CR-درآستانه');
const bogusWidened = bogus.body.includes('CR-فعال') || bogus.body.includes('CR-منقضی');
check('T-CR-09', bogus.status === 200 && bogusExpiring && !bogusWidened,
  `category=everything falls back to the near-expiry bucket: its contract is`
  + ` listed (${bogusExpiring}) while an active and a finished one are not`
  + ` (${!bogusWidened}) — the fallback narrows, it does not widen`);

/* ── T-CR-10: permission ──────────────────────────────────────────────── */
const vw = `V-${Date.now().toString(36)}`;
await flow.resetPassword({ username: 'cr_viewer', password: vw, mustChange: false, role: 'viewer' });
const guest = new Flow('contractreport');
guest.db = flow.db;
guest.dir = flow.dir;
await guest.login('cr_viewer', vw, { expectRedirect: true });
const denied = await guest.render({ uri: '/reports/contracts' });

check('T-CR-10', denied.status === 403,
  `viewer GET /reports/contracts -> ${denied.status}`);

/* ── T-CR-11: amounts ─────────────────────────────────────────────────── */
const adminHasFin = (await q(
  "select count(*) from role_permissions rp join permissions p on p.id = rp.permission_id"
  + " join roles r on r.id = rp.role_id join user_roles ur on ur.role_id = r.id"
  + " join users u on u.id = ur.user_id where u.username = 'admin' and p.code = 'contracts.view_financials'",
)) !== '0';
const pageShowsAmount = (await flow.render({ uri: '/reports/contracts?category=active' })).body.includes('۴٬۰۰۰٬۰۰۰');

check('T-CR-11', adminHasFin === pageShowsAmount,
  `admin holds contracts.view_financials: ${adminHasFin}; amount column rendered: ${pageShowsAmount}`);

/* ── T-CR-12: the sign of "days remaining" ────────────────────────────── */
const overdueBody = (await flow.render({ uri: '/reports/contracts?category=overdue' })).body;
const activeBody = (await flow.render({ uri: '/reports/contracts?category=active' })).body;

check('T-CR-12', overdueBody.includes('v-money--negative') && !activeBody.includes('v-money--negative'),
  `the overdue list marks its rows negative and the active list does not —`
  + ` days remaining are end minus today, not the other way round`);

/* ── T-CR-13: the shared database ─────────────────────────────────────── */
check('T-CR-13', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
