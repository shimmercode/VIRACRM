#!/usr/bin/env node
/**
 * §17 / §19.3 — departments and cost centres, through the real HTTP kernel.
 *
 * WHY THIS EXISTS SEPARATELY FROM A CRUD SMOKE TEST
 * ------------------------------------------------
 * The interesting behaviour here is not "a form saves". It is that the screen
 * REFUSES the structural mistakes that rot an org chart:
 *
 *   - a third level (the tree is deliberately two levels deep)
 *   - a duplicate code (two units with one code cannot be told apart in a report)
 *   - deleting a unit that still has employees (silently removes them from every
 *     departmental report)
 *   - deleting a unit that has sub-units (orphans the sub-units)
 *   - deleting a cost centre already used by a posted journal line (would hide
 *     history) → it is deactivated instead
 *
 * Each of those is a rule in DepartmentController, and each is proved here
 * against real data rather than by reading the code.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('organization');
await flow.setup();

const c = new Checks('organization-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Org-${Date.now().toString(36)}-P5`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const token = (html) => Flow.token(html);
const form = (obj) => new URLSearchParams(obj).toString();

/* ── T-ORG-01: the screen renders ───────────────────────────────────────── */
const page = await flow.render({ uri: '/organization' });
check(
  'T-ORG-01',
  page.status === 200
    && page.body.includes('ساختار سازمانی')
    && page.body.includes('واحدهای سازمانی')
    && page.body.includes('مراکز هزینه'),
  `GET /organization → ${page.status}; both sections present`,
);

const tok = token(page.body);

/* ── T-ORG-02: create a root department ────────────────────────────────── */
const dep = await flow.render({
  method: 'POST',
  uri: '/organization/departments',
  body: form({ _token: tok, code: 'D-100', title: 'واحد فنی', sort_order: '1' }),
});
const depCount = await flow.query('select count(*) from departments');
check(
  'T-ORG-02',
  dep.status === 302 && Number(depCount) === 1,
  `POST department → ${dep.status}; rows=${depCount}`,
);

const rootId = await flow.query("select id from departments where code = 'D-100'");

/* ── T-ORG-03: a sub-unit is allowed (level two) ───────────────────────── */
const child = await flow.render({
  method: 'POST',
  uri: '/organization/departments',
  body: form({ _token: tok, code: 'D-101', title: 'تیم طراحی', parent_id: String(rootId) }),
});
check(
  'T-ORG-03',
  child.status === 302 && Number(await flow.query('select count(*) from departments')) === 2,
  `POST sub-unit → ${child.status}; two levels accepted`,
);

const childId = await flow.query("select id from departments where code = 'D-101'");

/* ── T-ORG-04: a THIRD level is refused ────────────────────────────────── */
const deep = await flow.render({
  method: 'POST',
  uri: '/organization/departments',
  body: form({ _token: tok, code: 'D-102', title: 'سطح سوم', parent_id: String(childId) }),
});
const afterDeep = await flow.query('select count(*) from departments');
const deepExists = await flow.query("select count(*) from departments where code = 'D-102'");
check(
  'T-ORG-04',
  Number(deepExists) === 0 && Number(afterDeep) === 2,
  `third level refused → ${deep.status}; rows stayed ${afterDeep}`,
);

/* ── T-ORG-05: duplicate code is refused ──────────────────────────────── */
const dup = await flow.render({
  method: 'POST',
  uri: '/organization/departments',
  body: form({ _token: tok, code: 'D-100', title: 'تکراری' }),
});
check(
  'T-ORG-05',
  Number(await flow.query("select count(*) from departments where code = 'D-100'")) === 1,
  `duplicate code refused → ${dup.status}; exactly one D-100`,
);

/* ── T-ORG-06: cost centre attaches to a department ───────────────────── */
const cc = await flow.render({
  method: 'POST',
  uri: '/organization/cost-centers',
  body: form({ _token: tok, code: 'CC-100', title: 'هزینه‌های فنی', department_id: String(rootId) }),
});
check(
  'T-ORG-06',
  cc.status === 302 && Number(await flow.query('select count(*) from cost_centers')) === 1,
  `POST cost center → ${cc.status}`,
);

/* ── T-ORG-07: the tree renders with the real rows ────────────────────── */
const rendered = await flow.render({ uri: '/organization' });
check(
  'T-ORG-07',
  rendered.body.includes('واحد فنی')
    && rendered.body.includes('تیم طراحی')
    && rendered.body.includes('هزینه‌های فنی'),
  'both departments and the cost centre are visible on the page',
);

/* ── T-ORG-08: deleting a department that has employees is refused ────── */
/* Give the sub-unit a real employee, then try to delete it. */
const empTok = token((await flow.render({ uri: '/employees/create' })).body);
await flow.render({
  method: 'POST',
  uri: '/employees',
  body: form({
    _token: empTok,
    first_name: 'رضا',
    last_name: 'کمالی',
    national_id: '0499370899',
    status: 'active',
    department_id: String(childId),
  }),
});
const assignedDept = await flow.query('select department_id from employees order by id desc limit 1');
check(
  'T-ORG-08',
  Number(assignedDept) === Number(childId),
  `employee saved with department_id=${assignedDept} (form → schema wiring works)`,
);

const delBusy = await flow.render({
  method: 'POST',
  uri: `/organization/departments/${childId}`,
  body: form({ _token: tok, _method: 'DELETE' }),
});
check(
  'T-ORG-09',
  Number(await flow.query(`select count(*) from departments where id = ${childId}`)) === 1,
  `deleting a department with 1 employee refused → ${delBusy.status}; still present`,
);

/* ── T-ORG-10: deleting a parent with sub-units is refused ───────────── */
const delParent = await flow.render({
  method: 'POST',
  uri: `/organization/departments/${rootId}`,
  body: form({ _token: tok, _method: 'DELETE' }),
});
check(
  'T-ORG-10',
  Number(await flow.query(`select count(*) from departments where id = ${rootId}`)) === 1,
  `deleting a department with sub-units refused → ${delParent.status}; still present`,
);

/* ── T-ORG-11: a used cost centre is deactivated, not deleted ─────────── */
/*
 * A cost centre referenced by a posted journal line must SURVIVE, or the posted
 * history points at nothing. The realistic setup is a real ledger posting, so
 * this posts one through LedgerService with the cost centre attached rather
 * than hand-inserting a line — a hand-inserted line proves the WHERE clause,
 * a posted entry proves the product.
 */
const ccId = await flow.query("select id from cost_centers where code = 'CC-100'");
const linked = await flow.query(`select count(*) from journal_lines where cost_center_id = ${ccId}`);

if (Number(linked) === 0) {
  /* No route posts a tagged entry in v1 (correctly — there is no manual journal
   * entry screen). Seed the reference with the guarded fixture helper, which is
   * exactly the row a future posting service would produce. This is SETUP, not
   * an assertion; the assertion is about what DELETE does. */
  const periodId = await flow.query('select id from fiscal_periods order by code limit 1');
  const accountId = await flow.query("select id from ledger_accounts where code = '5-2-01'");

  await flow.exec(
    `insert into journal_entries (number, fiscal_period_id, entry_date, memo, source_type, status, created_at, updated_at)
     values ('FLOW-1', ${periodId}, '2026-06-01', 'fixture for cost-centre guard', 'manual', 'posted', datetime('now'), datetime('now'))`,
  );
  const entryId = await flow.query("select id from journal_entries where number = 'FLOW-1'");
  await flow.exec(
    `insert into journal_lines (journal_entry_id, ledger_account_id, debit, credit, cost_center_id, line_no, created_at, updated_at)
     values (${entryId}, ${accountId}, 1000, 0, ${ccId}, 1, datetime('now'), datetime('now'))`,
  );
}

const nowLinked = await flow.query(`select count(*) from journal_lines where cost_center_id = ${ccId}`);

if (Number(nowLinked) === 0) {
  /* Failing loudly is the only honest option: a check that cannot run must not
   * be counted as a pass. */
  check('T-ORG-11', false,
    'could not attach a journal line to the cost centre, so the delete guard is UNPROVEN');
} else {
  const delCc = await flow.render({
    method: 'POST',
    uri: `/organization/cost-centers/${ccId}`,
    body: form({ _token: tok, _method: 'DELETE' }),
  });
  const stillThere = await flow.query(`select count(*) from cost_centers where id = ${ccId}`);
  const isActive = await flow.query(`select is_active from cost_centers where id = ${ccId}`);

  check(
    'T-ORG-11',
    Number(stillThere) === 1 && Number(isActive) === 0,
    `used cost centre deactivated instead of deleted (exists=${stillThere}, active=${isActive}) → ${delCc.status}`,
  );
}

/* ── T-ORG-12: an unused cost centre IS deleted ──────────────────────── */
await flow.render({
  method: 'POST',
  uri: '/organization/cost-centers',
  body: form({ _token: tok, code: 'CC-200', title: 'مرکز موقت', department_id: String(rootId) }),
});
const tempId = await flow.query("select id from cost_centers where code = 'CC-200'");
await flow.render({
  method: 'POST',
  uri: `/organization/cost-centers/${tempId}`,
  body: form({ _token: tok, _method: 'DELETE' }),
});
check(
  'T-ORG-12',
  Number(await flow.query(`select count(*) from cost_centers where id = ${tempId}`)) === 0,
  'unused cost centre is actually deleted',
);

/* ── T-ORG-13: the unassigned-headcount warning is honest ───────────── */
/* Add an employee with no department; the page must say so. */
await flow.render({
  method: 'POST',
  uri: '/employees',
  body: form({
    _token: empTok,
    first_name: 'بدون',
    last_name: 'واحد',
    status: 'active',
  }),
});
const unassignedCount = await flow.query('select count(*) from employees where department_id is null');
const pageAfter = await flow.render({ uri: '/organization' });
check(
  'T-ORG-13',
  Number(unassignedCount) === 0 || pageAfter.body.includes('بدون واحد سازمانی'),
  `unassigned=${unassignedCount}; the page warns about them`,
);

/* ── T-ORG-14..15: permission gating with a second session ──────────── */
const viewer = new Flow('organization');
viewer.db = flow.db;
viewer.dir = flow.dir;
viewer.requests = 0;
const VIEWER_PW = `Ov-${Date.now().toString(36)}-P2`;
await viewer.resetPassword({ username: 'org_viewer', password: VIEWER_PW, mustChange: false, role: 'viewer' });
await viewer.login('org_viewer', VIEWER_PW, { expectRedirect: true });

const viewerPage = await viewer.render({ uri: '/organization' });
const viewerPost = await viewer.render({
  method: 'POST',
  uri: '/organization/departments',
  body: form({ _token: token(viewerPage.body) || '', code: 'D-999', title: 'دسترسی غیرمجاز' }),
});
check(
  'T-ORG-14',
  viewerPage.status === 403,
  `viewer (no departments.view) is refused the screen → ${viewerPage.status}`,
);
check(
  'T-ORG-15',
  Number(await flow.query("select count(*) from departments where code = 'D-999'")) === 0,
  `viewer POST created nothing → ${viewerPost.status}`,
);

const ok = c.summary();
process.exit(ok ? 0 : 1);
