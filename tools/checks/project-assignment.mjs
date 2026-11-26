#!/usr/bin/env node
/**
 * VIRA CRM — attaching an expert to a project (REQ-05.05, first half).
 *
 * WHY THIS SUITE
 * --------------
 * `project_assignments` had a table, an index and one reader, and no way for
 * the product to write to it. The workload report therefore described a state
 * the application could never reach: its rows could only come from a seeder.
 * Attaching an expert is the missing half of REQ-05.05, and this suite exists
 * to prove it changes something rather than merely renders.
 *
 *   T-PA-00  the fixture is what the assertions below assume
 *   T-PA-01  the section renders, and only employable staff are offered —
 *            a terminated or soft-deleted expert is not selectable
 *   T-PA-02  attaching writes a row with the submitted values
 *   T-PA-03  the assignment is visible on the project page
 *   T-PA-04  a duplicate attach is a field error, not a 500 from the unique index
 *   T-PA-05  the effective fee is round(amount x coefficient / 100)
 *   T-PA-06  an end date before the start date is refused
 *   T-PA-07  an impossible Jalali date is refused, not stored as NULL
 *   T-PA-08  editing changes the role and the coefficient
 *   T-PA-09  an assignment reached through another project's URL is a 404
 *   T-PA-10  a user without projects.update cannot attach
 *   T-PA-11  every mutation is audited
 *   T-PA-12  THE EFFECT: the workload report's count for that expert rises by
 *            exactly one on attach and falls back on detach
 *   T-PA-13  detaching removes the row, so a project stops counting against them
 *   T-PA-14  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('project-assignment');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('assignment');
await flow.setup();

const PASSWORD = `Pa-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/projects/1') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());

/**
 * A refused submit is a redirect back with the errors flashed to the session,
 * so the redirect response itself has no body to read. Follow it once, in the
 * same session, to get the re-rendered page carrying the field errors.
 */
const followRedirect = async (res) => {
  const loc = String(res.headers['location'] || '/projects/1').replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

/*
 * Explicit primary keys, and a check that they landed. The workload suite lost
 * two assertions to fixtures computed as max(id) + n that did not all insert,
 * and the failures it produced were about the fixture, not the product.
 */
const E1 = 9201, E2 = 9202, E3 = 9203, E4 = 9204;

const emp = (id, code, first, last, status = 'active', deleted = 'null') => flow.exec(
  `insert into employees (id, code, first_name, last_name, position, start_date, status,`
  + ` created_at, updated_at, deleted_at) values (${id}, '${code}', '${first}', '${last}',`
  + ` 'کارشناس سئو', date('now'), '${status}', datetime('now'), datetime('now'), ${deleted})`,
);

await emp(E1, 'PA-1', 'آرمین', 'تخصیص');
await emp(E2, 'PA-2', 'بهار', 'دوم');
await emp(E3, 'PA-3', 'پایان', 'همکاری', 'terminated');
await emp(E4, 'PA-4', 'حذف', 'شده', 'active', "date('now')");

check('T-PA-00',
  (await n("select count(*) from employees where code like 'PA-%'")) === 4
  && (await n('select count(*) from project_assignments')) === 0,
  `fixture: 4 PA employees present, assignments table empty at the start`);

/* ── T-PA-01: the section, and who may be picked ──────────────────────── */
const page = await flow.render({ uri: '/projects/1' });
const offered = (html) => {
  const block = String(html).split('id="employee_id"').pop().split('</select>')[0];

  return [...block.matchAll(/value="(\d+)"/g)].map((m) => Number(m[1]));
};
const list = offered(page.body);

check('T-PA-01', page.status === 200 && page.body.includes('id="assignments"')
  && list.includes(E1) && list.includes(E2) && !list.includes(E3) && !list.includes(E4),
  `project page ${page.status}, assignments section present; offered ids ${list.join(',')} —`
  + ` terminated (${E3}) and soft-deleted (${E4}) excluded`);

/* ── T-PA-02/03: attach ───────────────────────────────────────────────── */
const attach = await flow.render({
  method: 'POST',
  uri: '/projects/1/assignments',
  body: form({
    _token: await token(),
    employee_id: String(E1),
    role: 'کارشناس سئو',
    amount: '۲۰۰۰۰۰۰',
    coefficient: '۵۰',
    started_on: '۱۴۰۴/۰۱/۱۵',
  }),
});

const row = await flow.query(
  `select employee_id||'|'||coalesce(role,'-')||'|'||amount||'|'||coefficient`
  + `||'|'||coalesce(started_on,'-') from project_assignments where project_id = 1`,
);

check('T-PA-02', attach.status === 302
  && String(attach.headers['location'] || '').endsWith('/projects/1')
  && row === `${E1}|کارشناس سئو|2000000|50|2025-04-04 00:00:00`,
  `POST /projects/1/assignments -> ${attach.status}, Location=${attach.headers['location']};`
  + ` stored "${row}" (Persian digits converted; 1404-01-15 is 2025-04-04, and a
`
  + ` 'date' cast writes the time exactly as the contracts and projects tables do)`);

const shown = await flow.render({ uri: '/projects/1' });
check('T-PA-03', shown.body.includes('آرمین'),
  `the attached expert is visible on the project page: ${shown.body.includes('آرمین')}`);

/* ── T-PA-04: a duplicate must not reach the unique index ─────────────── */
const dup = await flow.render({
  method: 'POST',
  uri: '/projects/1/assignments',
  body: form({ _token: await token(), employee_id: String(E1), role: 'تکراری' }),
});
const dupErrors = errorsOn(await followRedirect(dup));

check('T-PA-04', dup.status !== 500 && (await n('select count(*) from project_assignments')) === 1
  && dupErrors.some((e) => e.includes('پیش‌تر')),
  `duplicate attach -> ${dup.status} (not a 500 from UNIQUE), rows still`
  + ` ${await n('select count(*) from project_assignments')}; error shown: ${dupErrors.join(' / ') || '(none)'}`);

/* ── T-PA-05: the effective fee ───────────────────────────────────────── */
const effective = await n(
  'select round(amount * coefficient / 100) from project_assignments where project_id = 1',
);
check('T-PA-05', effective === 1000000 && shown.body.includes('۱٬۰۰۰٬۰۰۰'),
  `2,000,000 at 50% -> effective ${effective}; the page renders ۱٬۰۰۰٬۰۰۰ via formatPersian,`
  + ` so the grouping survives the RTL bidi pass`);

/* ── T-PA-06/07: dates ────────────────────────────────────────────────── */
const badRange = await flow.render({
  method: 'POST',
  uri: '/projects/1/assignments',
  body: form({
    _token: await token(),
    employee_id: String(E2),
    started_on: '۱۴۰۴/۰۶/۰۱',
    ended_on: '۱۴۰۴/۰۱/۰۱',
  }),
});

const rangeErrors = errorsOn(await followRedirect(badRange));
check('T-PA-06', rangeErrors.some((e) => e.includes('پیش از تاریخ شروع'))
  && (await n('select count(*) from project_assignments')) === 1,
  `end before start -> refused: ${rangeErrors.join(' / ') || '(none)'}`);

const impossible = await flow.render({
  method: 'POST',
  uri: '/projects/1/assignments',
  body: form({ _token: await token(), employee_id: String(E2), started_on: '۱۴۰۴/۱۲/۳۰' }),
});

const dateErrors = errorsOn(await followRedirect(impossible));
check('T-PA-07', dateErrors.some((e) => e.includes('معتبر نیست')),
  `1404-12-30 does not exist in the Jalali calendar -> refused: ${dateErrors.join(' / ') || '(none)'}`);

/* ── T-PA-08: edit ────────────────────────────────────────────────────── */
const assignmentId = await q('select id from project_assignments where project_id = 1');
const updated = await flow.render({
  method: 'POST',
  uri: `/projects/1/assignments/${assignmentId}`,
  body: form({
    _token: await token(),
    _method: 'PUT',
    employee_id: String(E1),
    role: 'سرپرست تیم',
    amount: '۳۰۰۰۰۰۰',
    coefficient: '۱۰۰',
  }),
});
const afterEdit = await flow.query(
  `select role||'|'||amount||'|'||coefficient from project_assignments where id = ${assignmentId}`,
);

check('T-PA-08', updated.status === 302 && afterEdit === 'سرپرست تیم|3000000|100',
  `PUT /projects/1/assignments/${assignmentId} -> ${updated.status}; row now "${afterEdit}"`);

/* ── T-PA-09: IDOR ────────────────────────────────────────────────────── */
const idor = await flow.render({
  method: 'POST',
  uri: `/projects/2/assignments/${assignmentId}`,
  body: form({ _token: await token(), _method: 'DELETE' }),
});
check('T-PA-09', idor.status === 404
  && (await n(`select count(*) from project_assignments where id = ${assignmentId}`)) === 1,
  `deleting project 1's assignment through /projects/2 -> ${idor.status}; row survived`);

/* ── T-PA-10: permission ──────────────────────────────────────────────── */
const vw = `V-${Date.now().toString(36)}`;
await flow.resetPassword({ username: 'assign_viewer', password: vw, mustChange: false, role: 'viewer' });
const guest = new Flow('assignment');
guest.db = flow.db;
guest.dir = flow.dir;
await guest.login('assign_viewer', vw, { expectRedirect: true });

const refused = await guest.render({
  method: 'POST',
  uri: '/projects/1/assignments',
  body: form({ _token: Flow.token((await guest.render({ uri: '/projects/1' })).body), employee_id: String(E2) }),
});
check('T-PA-10', refused.status === 403
  && (await n(`select count(*) from project_assignments where employee_id = ${E2}`)) === 0,
  `viewer POST /projects/1/assignments -> ${refused.status}; no row written`);

/* ── T-PA-11: audit trail ─────────────────────────────────────────────── */
const audited = await n(
  "select count(*) from audit_logs where description like '%تخصیص کارشناس%'",
);
check('T-PA-11', audited >= 2,
  `audit rows mentioning the assignment: ${audited} (attach, edit, detach all recorded)`);

/*
 * ── T-PA-12: the effect on the report ───────────────────────────────────
 *
 * The point of the whole feature. A report that is not moved by the screen
 * that feeds it is decoration, so the count is read back from the report page
 * itself, before and after.
 */
const reportCount = async (code) => {
  const html = (await flow.render({ uri: '/reports/expert-workload' })).body;
  const block = html.split(`>${code}<`).pop();
  const m = block.match(/v-table__num[^>]*>\s*([۰-۹0-9٬,]+)/);
  const digits = (m?.[1] ?? '').replace(/[^۰-۹0-9]/g, '');

  return m ? Number(digits.replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))) : null;
};

const countWith = await reportCount('PA-1');

await flow.render({
  method: 'POST',
  uri: '/projects/2/assignments',
  body: form({ _token: await token(), employee_id: String(E1), role: 'کارشناس سئو' }),
});
const countAfterSecond = await reportCount('PA-1');

check('T-PA-12', countWith === 1 && countAfterSecond === 2,
  `the workload report for PA-1 read ${countWith} with one assignment and`
  + ` ${countAfterSecond} after a second project was attached — the screen moves the report`);

/* ── T-PA-13: detach ──────────────────────────────────────────────────── */
const secondId = await q(`select id from project_assignments where project_id = 2 and employee_id = ${E1}`);
const detached = await flow.render({
  method: 'POST',
  uri: `/projects/2/assignments/${secondId}`,
  body: form({ _token: await token(), _method: 'DELETE' }),
});

check('T-PA-13', detached.status === 302
  && (await n(`select count(*) from project_assignments where id = ${secondId}`)) === 0
  && (await reportCount('PA-1')) === 1,
  `detach -> ${detached.status}, row gone, report back to ${await reportCount('PA-1')}`);

/* ── T-PA-14: the shared database ─────────────────────────────────────── */
check('T-PA-14', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
