#!/usr/bin/env node
/**
 * OD-16 — the payroll chain, through the real HTTP kernel.
 *
 * PayrollTest proves the formula. This proves the OPERATOR JOURNEY reaches it:
 *
 *   create an employee → create a payroll month → build payslips →
 *   open one → type base salary + manual lines → compute →
 *   publish → verify the ledger entry exists and is balanced
 *
 * WHY THE LAST STEP IS THE POINT
 * ------------------------------
 * A payroll system that computes a beautiful payslip and never reaches the books
 * understates wage expense for as long as nobody reconciles. The flow therefore
 * ends by reading the journal entry back out of the database and asserting
 * debit == credit == the gross payroll it just published.
 *
 * ISOLATION: copies the seeded DB into `.arena/flows/payroll/`.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('payroll');
await flow.setup();

const c = new Checks('payroll-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Pay-${Date.now().toString(36)}-P7`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const token = (html) => Flow.token(html);

/* ── T-PAY-01: the payroll index renders ─────────────────────────────────── */
const index = await flow.render({ uri: '/payroll' });
check(
  'T-PAY-01',
  index.status === 200
    && index.body.includes('حقوق و دستمزد')
    && index.body.includes('ایجاد دورهٔ حقوق'),
  `GET /payroll → ${index.status}; period-creation form present`,
);

/* ── T-PAY-02: the employee list renders ─────────────────────────────────── */
const employees = await flow.render({ uri: '/employees' });
check(
  'T-PAY-02',
  employees.status === 200 && employees.body.includes('پرسنل'),
  `GET /employees → ${employees.status}`,
);

/* ── T-PAY-03: create an employee through the real form ─────────────────── */
const empForm = await flow.render({ uri: '/employees/create' });
const empToken = token(empForm.body);
check(
  'T-PAY-03',
  empForm.status === 200
    && empForm.body.includes('کد ملی')
    && ! empForm.body.includes('حقوق پایه'),
  `GET /employees/create → ${empForm.status}; national id present, NO salary field (OD-16)`,
);

/*
 * A real Iranian national id with a valid check digit. 0499370899 is the
 * canonical test value; it passes the mod-11 rule the request enforces.
 */
/*
 * TWO employees on purpose. The first gets its payslip typed and computed; the
 * second is left uncomputed so the "publish a month with uncomputed figures is
 * refused" rule can be proved with real data instead of a fabricated state.
 */
const employeePayload = (first, last, nid, mobile) => new URLSearchParams({
  _token: empToken,
  first_name: first,
  last_name: last,
  national_id: nid,
  mobile,
  position: 'کارشناس حسابداری',
  status: 'active',
  start_date: '',
  end_date: '',
}).toString();

const created = await flow.render({
  method: 'POST',
  uri: '/employees',
  body: employeePayload('مریم', 'رستمی', '0499370899', '09121234567'),
});

await flow.render({
  method: 'POST',
  uri: '/employees',
  body: employeePayload('نیما', 'شریفی', '0084575948', '09351112233'),
});
check(
  'T-PAY-04',
  (created.status === 302 || created.status === 200) && created.status !== 422,
  `POST /employees → ${created.status}`,
);

const employeeId = await flow.query('select id from employees order by id desc limit 1');
check('T-PAY-05', Number(employeeId) > 0, `employee row created (id=${employeeId})`);

/* ── T-PAY-06: an invalid national id is refused, not stored ─────────────── */
const badEmp = await flow.render({
  method: 'POST',
  uri: '/employees',
  body: new URLSearchParams({
    _token: empToken,
    first_name: 'آزمون',
    last_name: 'نامعتبر',
    national_id: '1234567890',
    status: 'active',
  }).toString(),
});
const badCount = await flow.query("select count(*) from employees where last_name = 'نامعتبر'");
check(
  'T-PAY-06',
  Number(badCount) === 0,
  `invalid national id refused (rows with that surname: ${badCount})`,
);

/* ── T-PAY-07: create a payroll period ──────────────────────────────────── */
const periodToken = token((await flow.render({ uri: '/payroll' })).body);
const periodRes = await flow.render({
  method: 'POST',
  uri: '/payroll/periods',
  body: new URLSearchParams({
    _token: periodToken,
    jalali_year: '1405',
    jalali_month: '6',
  }).toString(),
});
const periodId = await flow.query('select id from payroll_periods order by id desc limit 1');
check(
  'T-PAY-07',
  Number(periodId) > 0,
  `POST /payroll/periods → ${periodRes.status}; period id=${periodId}`,
);

/* ── T-PAY-08: the period page renders in draft state ───────────────────── */
const periodPage = await flow.render({ uri: `/payroll/periods/${periodId}` });
check(
  'T-PAY-08',
  periodPage.status === 200
    && periodPage.body.includes('شهریور ۱۴۰۵')
    && periodPage.body.includes('ساخت فیش کارمندان فعال'),
  `GET /payroll/periods/${periodId} → ${periodPage.status}; correct Persian month name`,
);

/* ── T-PAY-09: build payslips for active employees ──────────────────────── */
const buildToken = token(periodPage.body);
const built = await flow.render({
  method: 'POST',
  uri: `/payroll/periods/${periodId}/build`,
  body: new URLSearchParams({ _token: buildToken }).toString(),
});
const slipCount = await flow.query(`select count(*) from payslips where payroll_period_id = ${periodId}`);
check(
  'T-PAY-09',
  Number(slipCount) > 0,
  `POST build → ${built.status}; ${slipCount} payslip(s) created`,
);

/* ── T-PAY-10: type a base salary and manual lines, then compute ────────── */
const slipId = await flow.query(`select id from payslips where payroll_period_id = ${periodId} order by id limit 1`);
const slipPage = await flow.render({ uri: `/payroll/payslips/${slipId}` });
check(
  'T-PAY-10',
  slipPage.status === 200 && slipPage.body.includes('اقلام دستی این ماه'),
  `GET /payroll/payslips/${slipId} → ${slipPage.status}`,
);

const slipToken = token(slipPage.body);
/*
 * Form method-spoofing: the browser sends POST with `_method=PUT`, and the
 * harness must do the same. A literal PUT has no parsed body, so the CSRF token
 * is never seen and every save returns 419 — which looks like a broken product
 * and is actually a broken test.
 */
const saved = await flow.render({
  method: 'POST',
  uri: `/payroll/payslips/${slipId}`,
  body: new URLSearchParams({
    _token: slipToken,
    _method: 'PUT',
    base_salary: '20000000',
    bonus: '1000000',
    overtime: '0',
    advance: '0',
    deductions: '0',
    worked_days: '31',
    'items[0][label]': 'حق مسکن',
    'items[0][direction]': 'earning',
    'items[0][amount]': '900000',
    'items[1][label]': 'قسط وام',
    'items[1][direction]': 'deduction',
    'items[1][amount]': '400000',
  }).toString(),
});
const net = await flow.query(`select net_payable from payslips where id = ${slipId}`);
const earnings = await flow.query(`select total_earnings from payslips where id = ${slipId}`);
const deductionsTotal = await flow.query(`select total_deductions from payslips where id = ${slipId}`);
check(
  'T-PAY-11',
  saved.status === 302 || saved.status === 200,
  `PUT payslip → ${saved.status}`,
);

/*
 * THE FORMULA, read back out of the database after a real form submission:
 *   earnings   = 20,000,000 + 1,000,000 + 900,000              = 21,900,000
 *   deductions = 400,000                                        =    400,000
 *   net        = 21,500,000
 */
check(
  'T-PAY-12',
  Number(earnings) === 21_900_000 && Number(deductionsTotal) === 400_000 && Number(net) === 21_500_000,
  `formula applied through HTTP: earnings=${earnings} deductions=${deductionsTotal} net=${net}`,
);

/* ── T-PAY-13: publishing a month with uncomputed slips is refused ──────── */
/* The second payslip (if any) was never computed, so publish must refuse. */
const beforePublish = await flow.query(`select state from payroll_periods where id = ${periodId}`);
const publishToken = token((await flow.render({ uri: `/payroll/periods/${periodId}` })).body);
let publishBlocked = false;
const uncomputed = await flow.query(`select count(*) from payslips where payroll_period_id = ${periodId} and computed_at is null`);
if (Number(uncomputed) > 0) {
  const refused = await flow.render({
    method: 'POST',
    uri: `/payroll/periods/${periodId}/publish`,
    body: new URLSearchParams({ _token: publishToken }).toString(),
  });
  const stateAfter = await flow.query(`select state from payroll_periods where id = ${periodId}`);
  publishBlocked = stateAfter !== 'published' && stateAfter === beforePublish;
  check('T-PAY-13', publishBlocked,
    `${uncomputed} uncomputed slip(s): publish refused → ${refused.status}, state stayed "${stateAfter}"`);
} else {
  check('T-PAY-13', false, 'expected at least one uncomputed payslip to prove publish is blocked');
}

/* ── T-PAY-14: compute the period ──────────────────────────────────────── */
const computeRes = await flow.render({
  method: 'POST',
  uri: `/payroll/periods/${periodId}/compute`,
  body: new URLSearchParams({ _token: publishToken }).toString(),
});
const stillUncomputed = await flow.query(`select count(*) from payslips where payroll_period_id = ${periodId} and computed_at is null`);
check(
  'T-PAY-14',
  Number(stillUncomputed) === 0,
  `POST compute → ${computeRes.status}; uncomputed slips now ${stillUncomputed}`,
);

/* ── T-PAY-15: publish ─────────────────────────────────────────────────── */
const publishRes = await flow.render({
  method: 'POST',
  uri: `/payroll/periods/${periodId}/publish`,
  body: new URLSearchParams({ _token: publishToken }).toString(),
});
const state = await flow.query(`select state from payroll_periods where id = ${periodId}`);
check(
  'T-PAY-15',
  state === 'published',
  `POST publish → ${publishRes.status}; state="${state}"`,
);

/* ── T-PAY-16..18: the ledger entry is real and balanced ────────────────── */
const entryId = await flow.query(
  `select id from journal_entries where source_type = 'payroll' and source_id = ${periodId} limit 1`,
);
check('T-PAY-16', Number(entryId) > 0, `journal entry exists for the payroll (id=${entryId})`);

const debit = await flow.query(`select coalesce(sum(debit),0) from journal_lines where journal_entry_id = ${entryId}`);
const credit = await flow.query(`select coalesce(sum(credit),0) from journal_lines where journal_entry_id = ${entryId}`);
check(
  'T-PAY-17',
  Number(debit) === Number(credit) && Number(debit) > 0,
  `entry balanced: debit=${debit} credit=${credit}`,
);

const salaryLine = await flow.query(
  `select coalesce(sum(l.debit),0) from journal_lines l
   join ledger_accounts a on a.id = l.ledger_account_id
   where l.journal_entry_id = ${entryId} and a.code = '5-2-01'`,
);
const grossAll = await flow.query(`select coalesce(sum(total_earnings),0) from payslips where payroll_period_id = ${periodId}`);
check(
  'T-PAY-18',
  Number(salaryLine) === Number(grossAll),
  `Dr 5-2-01 = total gross payroll: salary=${salaryLine}, payslips total=${grossAll}`,
);

/* ── T-PAY-19: publishing twice is refused ─────────────────────────────── */
const publishAgain = await flow.render({
  method: 'POST',
  uri: `/payroll/periods/${periodId}/publish`,
  body: new URLSearchParams({ _token: publishToken }).toString(),
});
const entryCount = await flow.query(
  `select count(*) from journal_entries where source_type = 'payroll' and source_id = ${periodId}`,
);
check(
  'T-PAY-19',
  Number(entryCount) === 1,
  `second publish → ${publishAgain.status}; journal entries for this period = ${entryCount} (must be 1)`,
);

/* ── T-PAY-20: a published payslip cannot be silently edited ───────────── */
const afterPublishPage = await flow.render({ uri: `/payroll/payslips/${slipId}` });
const lockedFlag = afterPublishPage.body.includes('دلیل اصلاح');
check(
  'T-PAY-20',
  lockedFlag,
  'published payslip shows the amend-reason field, not a plain edit form',
);

/* ── T-PAY-21: the employee page shows the payslip history ─────────────── */
const empShow = await flow.render({ uri: `/employees/${employeeId}` });
check(
  'T-PAY-21',
  empShow.status === 200 && empShow.body.includes('سابقهٔ فیش حقوقی'),
  `GET /employees/${employeeId} → ${empShow.status}; salary history visible to payroll readers`,
);

/* ── T-PAY-22..24: role gating is real, proven with a second session ────── */
/*
 * `viewer` has `personnel.view` but NOT `payroll.view` (see RoleSeeder). A
 * dedicated session therefore proves two different things at once:
 *
 *   - the employee list is reachable for a non-privileged staff member
 *   - payroll is refused with 403, not merely hidden from the sidebar
 *
 * If this check is ever removed, "permission" becomes a CSS class again.
 */
/*
 * A SECOND cookie jar over the SAME flow database. Calling setup() here would
 * re-copy the seeded database and wipe everything the admin just did, so the
 * jar is reused deliberately — the two sessions must see one set of data for the
 * comparison to mean anything.
 */
const viewer = new Flow('payroll');
viewer.db = flow.db;
viewer.dir = flow.dir;
viewer.requests = 0;
const VIEWER_PASSWORD = `Vw-${Date.now().toString(36)}-P4`;
/*
 * `manager` is the honest probe: RoleSeeder grants it `personnel.view` and
 * deliberately withholds `payroll.view` ("دسترسی … بدون دسترسی به حقوق").
 * Using the same account for both checks means the 403 cannot be explained by
 * "the account is wrong" — it is the permission doing the work.
 */
await viewer.resetPassword({
  username: 'payroll_manager',
  password: VIEWER_PASSWORD,
  mustChange: false,
  role: 'manager',
});
await viewer.login('payroll_manager', VIEWER_PASSWORD, { expectRedirect: true });

const viewerEmployees = await viewer.render({ uri: '/employees' });
const viewerPayroll = await viewer.render({ uri: '/payroll' });
const viewerPayslip = await viewer.render({ uri: `/payroll/payslips/${slipId}` });

check(
  'T-PAY-22',
  viewerEmployees.status === 200,
  `manager CAN read the personnel list: GET /employees → ${viewerEmployees.status}`,
);

check(
  'T-PAY-23',
  viewerPayroll.status === 403,
  `limited user is REFUSED payroll: GET /payroll → ${viewerPayroll.status} (expected 403)`,
);

check(
  'T-PAY-24',
  viewerPayslip.status === 403,
  `a payslip URL is refused too, not just the index: GET /payroll/payslips/{id} → ${viewerPayslip.status}`,
);

/*
 * Amending a PUBLISHED payslip must leave a restorable snapshot and demand a
 * reason. Publishing above means the month is now frozen, so this is the real
 * post-publication correction path, driven through the form.
 */
const afterPublishToken = Flow.token(afterPublishPage.body);
const amended = await flow.render({
  method: 'POST',
  uri: `/payroll/payslips/${slipId}`,
  body: new URLSearchParams({
    _token: afterPublishToken,
    _method: 'PUT',
    amend: '1',
    base_salary: '20000000',
    bonus: '1000000',
    overtime: '0',
    advance: '0',
    deductions: '0',
    worked_days: '31',
    'items[0][label]': 'حق مسکن',
    'items[0][direction]': 'earning',
    'items[0][amount]': '900000',
    'items[1][label]': 'قسط وام',
    'items[1][direction]': 'deduction',
    'items[1][amount]': '400000',
    amend_reason: 'اصلاح روزهای کارکرد پس از انتشار',
  }).toString(),
});
const versionCount = await flow.query(
  `select count(*) from payslip_versions where payslip_id = ${slipId}`,
);
const snapshotNet = await flow.query(
  `select json_extract(snapshot, '$.net_payable') from payslip_versions where payslip_id = ${slipId} order by version limit 1`,
);
check(
  'T-PAY-25',
  (amended.status === 302 || amended.status === 200) && Number(versionCount) === 1,
  `amending a published payslip → ${amended.status}; ${versionCount} version snapshot(s)`,
);
check(
  'T-PAY-26',
  Number(snapshotNet) === 21_500_000,
  `the snapshot holds the PRE-amendment net (${snapshotNet}), so the original figure is reconstructible`,
);

/* Amending without a reason must be refused — the trail is not optional. */
const noReason = await flow.render({
  method: 'POST',
  uri: `/payroll/payslips/${slipId}`,
  body: new URLSearchParams({
    _token: afterPublishToken,
    _method: 'PUT',
    amend: '1',
    base_salary: '21000000',
    amend_reason: '',
  }).toString(),
});
const versionCountAfter = await flow.query(
  `select count(*) from payslip_versions where payslip_id = ${slipId}`,
);
const baseAfter = await flow.query(`select base_salary from payslips where id = ${slipId}`);
check(
  'T-PAY-27',
  Number(baseAfter) === 20_000_000 && Number(versionCountAfter) === 1,
  `amendment without a reason refused → ${noReason.status}; base stayed ${baseAfter}`,
);

const ok = c.summary();
process.exit(ok ? 0 : 1);
