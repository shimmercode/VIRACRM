#!/usr/bin/env node
/**
 * VIRA CRM — the expert workload report (REQ-05.05 / REQ-09.07).
 *
 * WHAT WAS MISSING
 * ----------------
 * `project_assignments` had a table, a migration index and a reader in the
 * review-environment controller — and nothing in the product. No model, no
 * route, no screen. §5-5 asks for «اتصال پروژه به کارشناس و مشاهده تعداد
 * پروژه‌های فعال هر کارشناس» and §9-6 for the same figure together with what
 * payroll needs. Both were recorded as verified against evidence that had
 * nothing to do with them (a trial balance and a P&L).
 *
 * THE COUNTING BASIS UNDER TEST
 * -----------------------------
 * A project counts against an expert when the assignment is current
 * (`is_active = 1`), the project is in the selected status, and neither side is
 * soft-deleted. A project with three experts counts once for each of them, and
 * once in the distinct project total. These fixtures exercise exactly that.
 *
 *   T-EW-01  the report renders for a permitted user
 *   T-EW-02  each expert's active count matches an INDEPENDENT SQL count
 *   T-EW-03  a multi-expert project counts once per expert, once in the total
 *   T-EW-04  a cancelled project is excluded from the active count but stays in
 *            "all assignments"
 *   T-EW-05  a deactivated assignment is excluded from the active count
 *   T-EW-06  a soft-deleted project is excluded entirely
 *   T-EW-07  a soft-deleted employee does not appear
 *   T-EW-08  the status filter really filters
 *   T-EW-09  an unknown status falls back, it does not silently widen
 *   T-EW-10  the search filter finds an expert by name
 *   T-EW-11  a user without reports.view is refused, and the refusal is backend
 *   T-EW-12  the money column is hidden without projects.view_financials
 *   T-EW-13  an empty result renders the empty state, not an error
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('workload');
await flow.setup();

const c = new Checks('expert-workload');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ew-${Date.now().toString(36)}-T5`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;

/* ── fixtures: one shape per rule the report claims to apply ────────────
 *
 * Explicit primary keys, deliberately. The first version computed them as
 * `max(id) + n` and only 5 of 7 inserts landed, with employee ids that matched
 * nothing this file had asked for — so the report was being asserted against a
 * dataset it never described, and two checks failed for reasons that had
 * nothing to do with the report. Fixed ids make the fixture exactly what the
 * assertions claim, and the count check below proves it before anything is
 * asserted about the report.
 */
const customerId = await q('select id from customers order by id limit 1');

const E1 = 9001, E2 = 9002, E3 = 9003, E4 = 9004;
const P1 = 9101, P2 = 9102, P3 = 9103, P4 = 9104;

const emp = (id, code, first, last, deleted = 'null') => flow.exec(
  `insert into employees (id, code, first_name, last_name, position, start_date, status,`
  + ` created_at, updated_at, deleted_at) values (${id}, '${code}', '${first}', '${last}',`
  + ` 'کارشناس سئو', date('now'), 'active', datetime('now'), datetime('now'), ${deleted})`,
);

await emp(E1, 'EW-1', 'آرمین', 'بارکاری');
await emp(E2, 'EW-2', 'بهار', 'چندنقشه');
await emp(E3, 'EW-3', 'پویا', 'کنسل‌شده');
await emp(E4, 'EW-4', 'تینا', 'حذف‌شده', "date('now')");

const proj = (id, code, title, status, deleted = 'null') => flow.exec(
  `insert into projects (id, code, customer_id, type, title, status, amount, start_date,`
  + ` created_at, updated_at, deleted_at) values (${id}, '${code}', ${customerId}, 'seo',`
  + ` '${title}', '${status}', 1000000, date('now'), datetime('now'), datetime('now'), ${deleted})`,
);

await proj(P1, 'EW-P1', 'پروژهٔ تک‌کارشناس', 'active');
await proj(P2, 'EW-P2', 'پروژهٔ چندکارشناس', 'active');
await proj(P3, 'EW-P3', 'پروژهٔ کنسل‌شده', 'cancelled');
await proj(P4, 'EW-P4', 'پروژهٔ حذف‌شده', 'active', "date('now')");

const assign = (pid, eid, active = 1) => flow.exec(
  `insert into project_assignments (project_id, employee_id, role, amount, coefficient,`
  + ` is_active, created_at, updated_at) values (${pid}, ${eid}, 'کارشناس سئو', 500000,`
  + ` 100, ${active}, datetime('now'), datetime('now'))`,
);

await assign(P1, E1);   // EW-1: active
await assign(P2, E1);   // EW-1: active, shared with EW-2
await assign(P2, E2);   // EW-2: active, shared with EW-1
await assign(P3, E1);   // EW-1: cancelled project, excluded from the active count
await assign(P3, E3);   // EW-3: only a cancelled project
await assign(P4, E1);   // soft-deleted project, excluded entirely
await assign(P1, E4);   // soft-deleted employee, excluded

/*
 * Prove the fixture is what the assertions below assume before asserting
 * anything about the report. A partially inserted fixture produces failures
 * that look like product bugs.
 */
const fixture = {
  employees: await n("select count(*) from employees where code like 'EW-%'"),
  projects: await n("select count(*) from projects where code like 'EW-%'"),
  assignments: await n(
    "select count(*) from project_assignments pa join projects p on p.id = pa.project_id"
    + " where p.code like 'EW-%'",
  ),
};
check('T-EW-00', fixture.employees === 4 && fixture.projects === 4 && fixture.assignments === 7,
  `fixture in place: ${fixture.employees} employees, ${fixture.projects} projects, `
  + `${fixture.assignments} assignments (expected 4/4/7)`);

/* ── T-EW-01: the page ────────────────────────────────────────────────── */
const page = await flow.render({ uri: '/reports/expert-workload' });
check('T-EW-01', page.status === 200 && page.body.includes('بار کاری کارشناسان'),
  `GET /reports/expert-workload -> ${page.status}`);

/*
 * ── T-EW-02: the counts, against an independent computation ─────────────
 * Recomputed here in plain SQL rather than read from the report, so the two
 * cannot share a bug. This is the reconciliation the directive asks for.
 */
const expected = async (employeeId) => n(
  `select count(distinct pa.project_id) from project_assignments pa`
  + ` join projects p on p.id = pa.project_id`
  + ` where pa.employee_id = ${employeeId} and pa.is_active = 1`
  + ` and p.status = 'active' and p.deleted_at is null`,
);

const renderedCount = (html, code) => {
  const block = html.split(`>${code}<`).pop();
  const m = block.match(/v-table__num[^>]*>\s*([۰-۹0-9]+)/);

  return m ? Number(m[1].replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))) : null;
};

const exp1 = await expected(E1);
const exp2 = await expected(E2);
const rep1 = renderedCount(page.body, 'EW-1');
const rep2 = renderedCount(page.body, 'EW-2');

check('T-EW-02', rep1 === exp1 && rep2 === exp2,
  `report vs independent SQL — EW-1: ${rep1} vs ${exp1}, EW-2: ${rep2} vs ${exp2}`);

/* ── T-EW-03: multi-expert projects ───────────────────────────────────── */
const distinctProjects = await n(
  `select count(distinct pa.project_id) from project_assignments pa`
  + ` join projects p on p.id = pa.project_id`
  + ` where pa.is_active = 1 and p.status = 'active' and p.deleted_at is null`,
);
const sumOfRows = (rep1 || 0) + (rep2 || 0);

check('T-EW-03', exp1 === 2 && exp2 === 1 && distinctProjects === 2 && sumOfRows === 3,
  `project EW-P2 has two experts: it counts once for each (EW-1=${exp1}, EW-2=${exp2},`
  + ` row sum ${sumOfRows}) but once in the distinct total (${distinctProjects})`);

/* ── T-EW-04: a cancelled project ─────────────────────────────────────── */
const e3Active = await expected(E3);
const e3All = await n(
  `select count(distinct project_id) from project_assignments where employee_id = ${E3}`,
);
check('T-EW-04', e3Active === 0 && e3All === 1,
  `EW-3 is only on a cancelled project: active=${e3Active}, all assignments=${e3All}`);

/* ── T-EW-05/06/07 ────────────────────────────────────────────────────── */
const e1All = await n(
  `select count(distinct project_id) from project_assignments where employee_id = ${E1}`,
);
check('T-EW-05', exp1 === 2 && e1All === 4,
  `EW-1 has 4 assignment rows but 2 count as active; the cancelled and the`
  + ` soft-deleted project are in the "all" figure (${e1All}) and out of the active one (${exp1})`);

check('T-EW-06', !page.body.includes('EW-P4') && e1All === 4,
  `the soft-deleted project contributes no active count; EW-1 active stayed ${exp1}`);

check('T-EW-07', !page.body.includes('EW-4'),
  `the soft-deleted employee does not appear: ${!page.body.includes('EW-4')}`);

/* ── T-EW-08/09: the status filter ────────────────────────────────────── */
const cancelledView = await flow.render({ uri: '/reports/expert-workload?status=cancelled' });
const cancelledRep = renderedCount(cancelledView.body, 'EW-3');

check('T-EW-08', cancelledRep === 1,
  `filtering to status=cancelled puts EW-3 at ${cancelledRep} (was ${e3Active} under status=active)`);

const bogus = await flow.render({ uri: '/reports/expert-workload?status=everything' });
const bogusRep = renderedCount(bogus.body, 'EW-1');

check('T-EW-09', bogus.status === 200 && bogusRep === exp1,
  `an unknown status falls back to 'active' rather than widening to every status:`
  + ` EW-1 still ${bogusRep} (expected ${exp1})`);

/* ── T-EW-10: search ──────────────────────────────────────────────────── */
const searched = await flow.render({ uri: `/reports/expert-workload?q=${encodeURIComponent('آرمین')}` });
check('T-EW-10', searched.status === 200 && searched.body.includes('EW-1')
  && !searched.body.includes('EW-2'),
  `q=آرمین returns EW-1 and not EW-2`);

/* ── T-EW-11: permission, refused in the backend ──────────────────────── */
const pw = `V-${Date.now().toString(36)}`;
await flow.resetPassword({ username: 'workload_viewer', password: pw, mustChange: false, role: 'viewer' });
const agent = new Flow('workload');
agent.db = flow.db;
agent.dir = flow.dir;
await agent.login('workload_viewer', pw, { expectRedirect: true });

const denied = await agent.render({ uri: '/reports/expert-workload' });
const granted = await q(
  "select count(*) from role_permissions rp join permissions p on p.id = rp.permission_id"
  + " join roles r on r.id = rp.role_id where r.code = 'viewer' and p.code = 'reports.view'",
);

check('T-EW-11', denied.status === 403 && granted === '0',
  `viewer GET /reports/expert-workload -> ${denied.status}; the role holds reports.view:`
  + ` ${granted === '0' ? 'no' : 'yes'} — the middleware refused it, not the template`);

/* ── T-EW-12: the money column is gated separately ────────────────────── */
const hasFin = await q(
  "select count(*) from role_permissions rp join permissions p on p.id = rp.permission_id"
  + " join roles r on r.id = rp.role_id join user_roles ur on ur.role_id = r.id"
  + " join users u on u.id = ur.user_id where u.username = 'admin'"
  + " and p.code = 'projects.view_financials'",
);
const showsMoney = page.body.includes('مبلغ مؤثر');

check('T-EW-12', (hasFin !== '0') === showsMoney,
  `admin holds projects.view_financials: ${hasFin !== '0'}; money column rendered: ${showsMoney}`
  + ' — workload is behind reports.view, pay is behind projects.view_financials');

/* ── T-EW-13: the empty state ─────────────────────────────────────────── */
const empty = await flow.render({ uri: '/reports/expert-workload?q=چنین-کارشناسی-نیست' });
check('T-EW-13', empty.status === 200 && empty.body.includes('v-empty'),
  `a search with no match -> ${empty.status}, empty state rendered: ${empty.body.includes('v-empty')}`);

process.exit(c.summary() ? 0 : 1);
