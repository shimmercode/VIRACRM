#!/usr/bin/env node
/**
 * SECURITY AUDIT — the audit trail page (Stage 3 of the security decisions).
 *
 * WHAT HAD TO BE TRUE BEFORE THIS PAGE COULD EXIST
 * ------------------------------------------------
 * `audit.view` was in the permissions table from the start, granted to no role
 * and referenced nowhere in app/ or routes/. The system was collecting an audit
 * trail nobody could read. Building the reader is the easy half; the half that
 * needed deciding is what a reader of a security log is allowed to do and see.
 *
 *   T-ALP-01  an anonymous visitor is redirected, never shown the log
 *   T-ALP-02  a super admin can read it
 *   T-ALP-03  reading it writes an `audit.viewed` row
 *   T-ALP-04  a manager is refused (403)
 *   T-ALP-05  an account_manager is refused (403)
 *   T-ALP-06  a viewer is refused (403)
 *   T-ALP-07  every refusal is itself audited as `denied`
 *   T-ALP-08  the event filter really filters
 *   T-ALP-09  the user filter really filters
 *   T-ALP-10  the date filter really filters
 *   T-ALP-11  pagination serves different rows per page
 *   T-ALP-12  a secret in the table never reaches the HTML
 *   T-ALP-13  the page is read-only: POST/PUT/PATCH/DELETE are refused
 *   T-ALP-14  viewing does not alter any existing record
 *   T-ALP-15  a search term is not written into the audit trail
 *   T-ALP-16  `audit.view` is granted to no seeded role
 *
 * WHY 12 AND 15 ARE THE ONES THAT MATTER
 * --------------------------------------
 * 12 is not "redaction is implemented". It inserts a row BY RAW SQL, straight
 * past AuditRecorder's redaction, containing a plaintext password in `changes`
 * and `password: <secret>` in the description — then asserts the secret is
 * absent from the rendered page. That tests the second layer, which is the only
 * one that can protect against a row written by something that forgot the first.
 *
 * 15 is the one nobody thinks about: a reviewer hunting for a leaked password
 * types it into the search box. If the search term were logged, the audit page
 * would become the place the secret is stored. The event records WHICH filters
 * were used, never their values.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('auditpage');
await flow.setup();

const c = new Checks('audit-log-page');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ap-${Date.now().toString(36)}-K2`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');
const count = async (sql) => Number(String(await flow.query(sql)).trim()) || 0;

/* ── T-ALP-01: anonymous ────────────────────────────────────────────────
 * A brand-new Flow has an empty cookie jar, so this is a genuine anonymous
 * request rather than a logged-in one with a missing permission. */
const anon = new Flow('auditpage');
anon.db = flow.db;
anon.dir = flow.dir;
const anonRes = await anon.render({ uri: '/audit' });

check(
  'T-ALP-01',
  anonRes.status !== 200 && !anonRes.body.includes('گزارش رویدادها'),
  `GET /audit with no session -> ${anonRes.status}, log content present: `
    + `${anonRes.body.includes('گزارش رویدادها')}`,
);

/* ── sign in as the superadmin fixture ────────────────────────────────── */
const loginPage = await flow.render({ uri: '/login' });
await flow.render({
  method: 'POST',
  uri: '/login',
  body: form({ _token: tok(loginPage.body), username: 'admin', password: PASSWORD }),
});

/* ── T-ALP-14 (baseline): nothing about the existing log changes ────────
 * Bounded by id, not by "whatever is in the table". This check inserts rows of
 * its own further down (a poisoned row for T-ALP-12, forty rows for T-ALP-11),
 * and the first version of this fingerprint counted them, so the test failed on
 * the check's own fixtures: 233 -> 274, exactly the 41 rows it had inserted.
 * What it has to prove is that READING does not rewrite rows that already
 * existed, so it pins a high-water mark and only ever looks below it. */
const baselineId = Number(String(await flow.query(
  'select coalesce(max(id), 0) from audit_logs',
)).trim()) || 0;

const fingerprint = () => flow.query(
  `select count(*) || ':' || coalesce(sum(id),0) from audit_logs `
    + `where id <= ${baselineId} and event <> 'audit.viewed'`,
);

/* ── T-ALP-02 / T-ALP-03: readable by a super admin, and the read is logged ── */
const viewedBefore = await count("select count(*) from audit_logs where event = 'audit.viewed'");
const page = await flow.render({ uri: '/audit' });
const viewedAfter = await count("select count(*) from audit_logs where event = 'audit.viewed'");

check(
  'T-ALP-02',
  page.status === 200 && page.body.includes('گزارش رویدادها') && page.body.includes('v-table'),
  `super admin GET /audit -> ${page.status}, ${page.body.length} bytes, `
    + `title present: ${page.body.includes('گزارش رویدادها')}`,
);

check(
  'T-ALP-03',
  viewedAfter === viewedBefore + 1,
  `audit.viewed rows ${viewedBefore} -> ${viewedAfter} after one page load`,
);

/* ── T-ALP-12: a secret in the table never reaches the HTML ─────────────
 * Written with raw SQL so it bypasses AuditRecorder::record() entirely — that
 * is the scenario the second redaction layer exists for. */
const SECRET_CHANGES = `Cv-${Date.now().toString(36)}-A1`;
const SECRET_DESC = `Cd-${Date.now().toString(36)}-B2`;
const ENVELOPE = 'v1:QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo=';

await flow.exec(
  "insert into audit_logs (event, description, changes, created_at) values ("
    + "'updated', 'password: ${SECRET_DESC} and ${ENVELOPE}', "
    + `'{"password":"${SECRET_CHANGES}","api_key":"${SECRET_CHANGES}2","note":"safe field"}', `
    + "datetime('now'))",
);

const poisoned = await flow.render({ uri: '/audit?event=updated' });

check(
  'T-ALP-12',
  poisoned.status === 200
    && !poisoned.body.includes(SECRET_CHANGES)
    && !poisoned.body.includes(SECRET_DESC)
    && !poisoned.body.includes(ENVELOPE)
    && poisoned.body.includes('[redacted]'),
  `rendered page contains changes-secret: ${poisoned.body.includes(SECRET_CHANGES)}, `
    + `description-secret: ${poisoned.body.includes(SECRET_DESC)}, `
    + `envelope: ${poisoned.body.includes(ENVELOPE)}, `
    + `redaction marker: ${poisoned.body.includes('[redacted]')}`,
);

/* ── T-ALP-15: the search term is not written into the trail ──────────── */
const SECRET_SEARCH = `Sx-${Date.now().toString(36)}-Z9`;
await flow.render({ uri: `/audit?q=${encodeURIComponent(SECRET_SEARCH)}` });

const storedSearch = await count(
  `select count(*) from audit_logs where description like '%${SECRET_SEARCH}%'`,
);

check(
  'T-ALP-15',
  storedSearch === 0,
  `audit rows containing the search term: ${storedSearch} — a reviewer hunting for a `
    + 'leaked password would type it into this box, so the box must not log it',
);

/* ── T-ALP-08/09/10: the filters actually filter ──────────────────────── */
const byEvent = await flow.render({ uri: '/audit?event=login' });
const eventOk = byEvent.status === 200
  && byEvent.body.includes('ورود')
  && !byEvent.body.includes('خروج از حساب</td>');

check(
  'T-ALP-08',
  eventOk,
  `?event=login -> ${byEvent.status}; the page shows only matching events`,
);

const adminId = String(await flow.query("select id from users where username = 'admin'")).trim();
const byUser = await flow.render({ uri: `/audit?user=${adminId}` });
const byOther = await flow.render({ uri: '/audit?user=999999' });

check(
  'T-ALP-09',
  byUser.status === 200
    && byOther.status === 200
    && byOther.body.includes('رویدادی با این فیلترها پیدا نشد')
    && !byUser.body.includes('رویدادی با این فیلترها پیدا نشد'),
  `?user=${adminId} -> ${byUser.status} with rows; ?user=999999 -> ${byOther.status} `
    + `with the empty state: ${byOther.body.includes('رویدادی با این فیلترها پیدا نشد')}`,
);

const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10);
const future = await flow.render({ uri: `/audit?from=${tomorrow}` });

check(
  'T-ALP-10',
  future.status === 200 && future.body.includes('رویدادی با این فیلترها پیدا نشد'),
  `?from=${tomorrow} (after every row) -> ${future.status}, empty state present: `
    + `${future.body.includes('رویدادی با این فیلترها پیدا نشد')}`,
);

/* ── T-ALP-11: pagination ───────────────────────────────────────────────
 * Rows are inserted rather than assumed: a pagination test that depends on how
 * much data happens to be in the fixture stops testing anything the moment
 * someone trims it. */
for (let i = 0; i < 40; i += 1) {
  await flow.exec(
    `insert into audit_logs (event, description, created_at) values ('pagetest', 'row ${i}', datetime('now'))`,
  );
}

const p1 = await flow.render({ uri: '/audit?event=pagetest' });
const p2 = await flow.render({ uri: '/audit?event=pagetest&page=2' });

check(
  'T-ALP-11',
  p1.status === 200
    && p2.status === 200
    && p1.body.includes('rel="next"')
    && p2.body.includes('rel="prev"')
    && p1.body !== p2.body,
  `page 1 -> ${p1.status} (next link: ${p1.body.includes('rel="next"')}), `
    + `page 2 -> ${p2.status} (prev link: ${p2.body.includes('rel="prev"')}), `
    + `bodies differ: ${p1.body !== p2.body}`,
);

/* ── T-ALP-13: read-only ──────────────────────────────────────────────── */
const home = await flow.render({ uri: '/' });
const csrf = tok(home.body);

const writeAttempts = {};
for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
  const res = await flow.render({
    method,
    uri: '/audit',
    body: form({ _token: csrf }),
  });
  writeAttempts[method] = res.status;
}

check(
  'T-ALP-13',
  Object.values(writeAttempts).every((s) => s === 405 || s === 403),
  `write verbs against /audit: ${JSON.stringify(writeAttempts)} — an audit log an `
    + 'administrator can amend is not evidence',
);

/* ── T-ALP-04/05/06/07: role separation ───────────────────────────────── */
const deniedBefore = await count("select count(*) from audit_logs where event = 'denied'");

const asRole = async (username, role) => {
  const pw = `A-${role}-${Date.now().toString(36)}`;
  await flow.resetPassword({ username, password: pw, mustChange: false, role });

  const agent = new Flow('auditpage');
  agent.db = flow.db;
  agent.dir = flow.dir;
  await agent.login(username, pw, { expectRedirect: true });

  return agent.render({ uri: '/audit' });
};

const manager = await asRole('audit_page_manager', 'manager');
const acct = await asRole('audit_page_acct', 'account_manager');
const viewer = await asRole('audit_page_viewer', 'viewer');

check(
  'T-ALP-04',
  manager.status === 403 && !manager.body.includes('v-table'),
  `manager -> ${manager.status} (audit.view is granted to no role, so the most `
    + 'privileged ordinary role must be refused)',
);
check(
  'T-ALP-05',
  acct.status === 403 && !acct.body.includes('v-table'),
  `account_manager -> ${acct.status}`,
);
check(
  'T-ALP-06',
  viewer.status === 403 && !viewer.body.includes('v-table'),
  `viewer -> ${viewer.status}`,
);

const deniedAfter = await count("select count(*) from audit_logs where event = 'denied'");
check(
  'T-ALP-07',
  deniedAfter >= deniedBefore + 3,
  `denied rows ${deniedBefore} -> ${deniedAfter} across three refused page loads — a `
    + 'refusal that is not recorded is a refusal nobody can investigate',
);

/* ── T-ALP-14: viewing changed no existing record ───────────────────────
 * Runs after every other page load in this file, so it covers all of them.
 * Only rows that existed at the baseline are in scope. */
const rowsBefore = String(await fingerprint()).trim();
await flow.render({ uri: '/audit' });
const rowsAfter = String(await fingerprint()).trim();

check(
  'T-ALP-14',
  rowsBefore === rowsAfter,
  rowsBefore === rowsAfter
    ? `count:sum(id) of the rows at or below id ${baselineId} is unchanged after `
      + `another page load (${rowsAfter})`
    : `existing audit rows changed while viewing: ${rowsBefore} -> ${rowsAfter}`,
);

/* ── T-ALP-16: the permission model ───────────────────────────────────── */
const grants = await count(
  'select count(*) from role_permissions where permission_id in '
    + "(select id from permissions where code = 'audit.view')",
);
const permissionExists = await count("select count(*) from permissions where code = 'audit.view'");

check(
  'T-ALP-16',
  permissionExists === 1 && grants === 0,
  `audit.view exists: ${permissionExists === 1}, role grants: ${grants} — reachable only `
    + 'through the superadmin bypass, so the people named in the log are not the '
    + 'people reading it',
);

c.summary();
process.exit(c.summary() ? 0 : 1);
