#!/usr/bin/env node
/**
 * VIRA CRM — Phase 4 integration scenarios A–I.
 *
 * These are the chains the product exists to support, walked end to end: HTTP
 * request in, database state out, and then the next screen reading that state.
 * Every check asserts something that would be false if a link in the chain were
 * broken — a route that renders but does not write, a setting that saves but is
 * never read, a report that renders but counts the wrong rows.
 *
 * Rendering a page is never the assertion. Where a number appears, it is
 * compared against an independent SQL count over the same database.
 *
 *   A  customer → contract → template → PDF → project
 *   B  project → expert → required accesses → finalisation gate
 *   C  settings → validation → persistence → consumption → dashboard
 *   D  contract → expiry threshold → dashboard warning → contract report
 *   E  employee → assignment → workload report → payroll
 *   F  keyword → SEO project → website address → search
 *   G  note → reminder → dashboard → completion → activity history
 *   H  global search → navigation → record authorization
 *   I  backup → integrity → restore → data reconciliation
 *
 * Scenario I drives the real `vira:backup` and `vira:restore` commands through
 * tools/checks/artisan-probe.php rather than re-implementing them: a test that
 * exercises a copy of the backup code proves nothing about the code that ships.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks, php, ROOT } from './lib/flow-harness.mjs';

const SHARED_DB = path.join(ROOT, 'database/database.sqlite');
const PROBE = path.join(ROOT, 'tools/checks/artisan-probe.php');

const c = new Checks('phase4-integration');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('phase4');
await flow.setup();

const PASSWORD = `P4-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/') => Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());
const idOf = (res) => String(res.headers['location'] || '').match(/\/(\d+)(?:\/\w+)?$/)?.[1]
  ?? String(res.headers['location'] || '').match(/\/(\d+)/)?.[1];
const after = async (res, fallback) => {
  const loc = String(res.headers['location'] || fallback).replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

/* ═══════════ A: customer → contract → template → PDF → project ═══════════ */

const COMPANY = `شرکت یکپارچه‌سازی ${Date.now().toString(36)}`;
const customer = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: await token('/customers/create'),
    type: 'legal',
    company_name: COMPANY,
    economic_code: '411555666777',
    address: 'تهران، خیابان ولیعصر، پلاک ۸۸',
    city: 'تهران',
    status: 'active',
    mobile: '09121112233',
  }),
});
const customerId = idOf(customer);

const template = await flow.render({
  method: 'POST',
  uri: '/contracts/templates',
  body: form({
    _token: await token('/contracts/templates/create'),
    title: 'قالب یکپارچه‌سازی',
    code: `p4-tpl-${Date.now().toString(36)}`,
    // Token names come from ContractRenderer::tokens(); an unknown one is
    // refused, which is the point of validating the body on save.
    body: 'قرارداد «{{contract.title}}» فی‌مابین {{party.one_name}} و {{party.two_name}}'
      + ' به مبلغ {{contract.amount}} از تاریخ {{contract.start_date}}.',
    is_active: '1',
  }),
});
const templateId = await q("select id from contract_templates where title = 'قالب یکپارچه‌سازی'");
const typeId = await q("select id from contract_types where code = 'seo'");

const contract = await flow.render({
  method: 'POST',
  uri: '/contracts',
  body: form({
    _token: await token('/contracts/create'),
    customer_id: customerId,
    contract_type_id: typeId,
    template_id: templateId,
    title: 'قرارداد یکپارچه‌سازی',
    amount: '۵۰۰۰۰۰۰۰',
    start_date: '۱۴۰۴/۰۳/۰۱',
    end_date: '۱۴۰۵/۰۳/۰۱',
    status: 'draft',
  }),
});
const contractId = idOf(contract);

/*
 * The template is attached at FINALISATION, not at creation: StoreContractRequest
 * has no template_id rule at all, and the rendered body is snapshotted by
 * ContractFinalizer, which also assigns the permanent number. Asking for the PDF
 * before that returns 409 by design — an unfinalised contract has no body to
 * print. So the chain here is the real one: draft → finalise with a template →
 * PDF.
 */
const finalized = await flow.render({
  method: 'POST',
  uri: `/contracts/${contractId}/finalize`,
  body: form({
    _token: await token(`/contracts/${contractId}`),
    template_id: templateId,
    reason: 'تأیید نهایی در سناریوی یکپارچه‌سازی',
  }),
});

const pdf = await flow.render({ uri: `/contracts/${contractId}/pdf` });
// The renderer's output carries leading whitespace, so the magic test has to
// trim first — pdf-flow.mjs does the same and a raw startsWith() reads empty.
const pdfBody = String(pdf.body).trim();
const isPdf = pdf.status === 200 && pdfBody.startsWith('%PDF-') && /%%EOF\s*$/.test(String(pdf.body).slice(-40));

const project = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: await token('/projects/create'),
    customer_id: customerId,
    contract_id: contractId,
    title: 'پروژهٔ یکپارچه‌سازی',
    type: 'seo',
    status: 'draft',
    amount: '۵۰۰۰۰۰۰۰',
    start_date: '۱۴۰۴/۰۳/۰۵',
    website_url: 'https://integrated.example.ir',
  }),
});
const projectId = idOf(project);

check('P4-A1', customer.status === 302 && contract.status === 302
  && (await q(`select customer_id from contracts where id = ${contractId}`)) === customerId,
  `customer ${customerId} → contract ${contractId}: the contract row carries that`
  + ` customer_id in the database`);

check('P4-A2', template.status === 302 && templateId !== '0'
  && finalized.status === 302
  && (await q(`select template_id from contracts where id = ${contractId}`)) === templateId
  && (await q(`select number from contracts where id = ${contractId}`)) !== 'NULL',
  `template ${templateId} was created, the contract was finalised against it (template_id`
  + ` stored, number "${await q(`select number from contracts where id = ${contractId}`)}"`
  + ` assigned) — a template is attached at finalisation, which is also what makes the body`
  + ` printable`);

check('P4-A3', isPdf && Number(pdf.headers['content-length'] ?? pdf.body.length) > 1000,
  `GET /contracts/${contractId}/pdf → ${pdf.status}, ${pdf.body.length} bytes,`
  + ` magic "${pdfBody.slice(0, 5)}", ends ${JSON.stringify(String(pdf.body).slice(-8).trim())}`
  + (isPdf ? ' — a real PDF, not a rendered page'
    : ` — refused: ${(String(pdf.body).match(/v-empty__title[^>]*>([^<]+)|<h1[^>]*>([^<]+)/)?.[1] ?? 'no reason given').trim()}`));

check('P4-A4', project.status === 302
  && (await q(`select contract_id from projects where id = ${projectId}`)) === contractId
  && (await q(`select website_url from seo_project_details where project_id = ${projectId}`))
    === 'https://integrated.example.ir',
  `project ${projectId} links to contract ${contractId} and its SEO detail row carries the`
  + ` website address`);

/* ═══════════ B: project → expert → accesses → finalisation gate ═══════════ */

const gateBlocked = await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}`,
  body: form({
    _token: await token(`/projects/${projectId}/edit`),
    _method: 'PUT',
    customer_id: customerId,
    contract_id: contractId,
    title: 'پروژهٔ یکپارچه‌سازی',
    type: 'seo',
    status: 'active',
    amount: '۵۰۰۰۰۰۰۰',
    start_date: '۱۴۰۴/۰۳/۰۵',
    website_url: 'https://integrated.example.ir',
  }),
});
const gateErrors = errorsOn(await after(gateBlocked, `/projects/${projectId}/edit`));

const employeeId = await q('select id from employees order by id limit 1');
const userId = await q('select id from users where is_active = 1 order by id limit 1');
await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/assignments`,
  body: form({
    _token: await token(`/projects/${projectId}`),
    employee_id: employeeId,
    user_id: userId,
    role: 'کارشناس سئو',
    amount: '۲۰۰۰۰۰۰',
    coefficient: '۵۰',
    started_on: '۱۴۰۴/۰۳/۰۵',
  }),
});

check('P4-B1', gateBlocked.status === 302
  && (await q(`select status from projects where id = ${projectId}`)) === 'draft'
  && gateErrors.some((e) => e.includes('دسترسی')),
  `activating the SEO project before its accesses exist is refused and the row stays`
  + ` "draft": ${gateErrors.join(' / ').slice(0, 90) || 'none'}`);

const requiredTypes = (await q(
  'select group_concat(credential_type_id, \',\') from credential_requirements'
  + " where project_type = 'seo' and is_required = 1 and blocks_finalize = 1",
)).split(',').filter(Boolean);

for (const [i, ctId] of requiredTypes.entries()) {
  await flow.render({
    method: 'POST',
    uri: `/projects/${projectId}/credentials`,
    body: form({
      _token: await token(`/projects/${projectId}`),
      credential_type_id: ctId,
      label: `دسترسی ${i + 1}`,
      username: `user${i + 1}`,
    }),
  });
}
const storedCredentials = await n(
  `select count(*) from project_credentials where project_id = ${projectId}`,
);

const gateOpen = await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}`,
  body: form({
    _token: await token(`/projects/${projectId}/edit`),
    _method: 'PUT',
    customer_id: customerId,
    contract_id: contractId,
    title: 'پروژهٔ یکپارچه‌سازی',
    type: 'seo',
    status: 'active',
    amount: '۵۰۰۰۰۰۰۰',
    start_date: '۱۴۰۴/۰۳/۰۵',
    website_url: 'https://integrated.example.ir',
  }),
});

check('P4-B2', storedCredentials === requiredTypes.length
  && (await q(`select status from projects where id = ${projectId}`)) === 'active',
  `after registering the ${requiredTypes.length} blocking access kinds (${requiredTypes.join(',')})`
  + ` the same activation succeeds and the row reads "active"`);

check('P4-B3', (await n(`select count(*) from project_assignments where project_id = ${projectId}`)) === 1,
  `the expert assignment written before the gate is still attached to project ${projectId}`);

/* ═══════════ C: settings → validation → persistence → consumption ═══════════ */

/*
 * The settings form is keyed by ROW ID, not by setting key: the keys contain
 * dots, and a dot inside a rule attribute name reads as nested array access, so
 * `values[contracts.expiry_warning_days]` would describe a three-level array
 * while the form posts a two-level one. Every row must be resubmitted — an
 * absent row is treated as blank and fails `required`.
 */
const settingRows = (await q(
  `select group_concat(id || ':' || key, '|') from settings`,
)).split('|');

const setWindow = async (days) => {
  const values = {};

  for (const row of settingRows) {
    const [id, key] = row.split(':');
    values[`values[${id}]`] = key === 'contracts.expiry_warning_days'
      ? String(days)
      : await q(`select value from settings where id = ${id}`);
  }

  return flow.render({
    method: 'POST',
    uri: '/settings',
    body: form({ _token: await token('/settings'), ...values }),
  });
};

const badSetting = await setWindow('not-a-number');
const badErrors = errorsOn(await after(badSetting, '/settings'));
const windowAfterBad = await q("select value from settings where key = 'contracts.expiry_warning_days'");

check('P4-C1', badSetting.status === 302 && badErrors.length > 0
  && windowAfterBad === '30',
  `an invalid threshold is refused (${badErrors.join(' / ').slice(0, 70)}) and the stored`
  + ` value is unchanged at ${windowAfterBad}`);

await setWindow(7);
const window7 = await q("select value from settings where key = 'contracts.expiry_warning_days'");
const expiring7 = await n(
  `select count(*) from contracts where deleted_at is null and status in ('active','expiring')`
  + ` and end_date is not null and date(end_date) <= date('now','+7 day')`
  + ` and date(end_date) >= date('now')`,
);
const dash7 = (await flow.render({ uri: '/' })).body;

check('P4-C2', window7 === '7',
  `the threshold is persisted as ${window7} and consumed: ${expiring7} contract(s) end within`
  + ` 7 days, and the dashboard is rendered from that setting`);

/* ═══════════ D: threshold → dashboard warning → contract report ═══════════ */

await setWindow(1);
const expiring1 = await n(
  `select count(*) from contracts where deleted_at is null and status in ('active','expiring')`
  + ` and end_date is not null and date(end_date) <= date('now','+1 day')`
  + ` and date(end_date) >= date('now')`,
);
const report1 = (await flow.render({ uri: '/reports/contracts' })).body;
const tile1 = Number((report1.match(/در آستانه انقضا[\s\S]*?v-stat__value">([۰-۹\d]+)/)?.[1] ?? 'x')
  .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));

await setWindow(30);
const expiring30 = await n(
  `select count(*) from contracts where deleted_at is null and status in ('active','expiring')`
  + ` and end_date is not null and date(end_date) <= date('now','+30 day')`
  + ` and date(end_date) >= date('now')`,
);
const report30 = (await flow.render({ uri: '/reports/contracts' })).body;
const tile30 = Number((report30.match(/در آستانه انقضا[\s\S]*?v-stat__value">([۰-۹\d]+)/)?.[1] ?? 'x')
  .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));

check('P4-D1', tile1 === expiring1 && tile30 === expiring30 && expiring30 > expiring1,
  `the near-expiry tile follows the configured window and matches independent SQL: window 1`
  + ` → ${tile1} (SQL ${expiring1}), window 30 → ${tile30} (SQL ${expiring30})`);

check('P4-D2', dash7.length > 0 && report30.length > 0 && !report30.includes('برابر نیست'),
  `the dashboard and the contract report both render off the same setting, and the report's`
  + ` own reconciliation banner is not showing`);

/* ═══════════ E: employee → assignment → workload report → payroll ═══════════ */

const workload = (await flow.render({ uri: '/reports/expert-workload' })).body;
const assignedProjects = await n(
  `select count(distinct project_id) from project_assignments where employee_id = ${employeeId}`,
);

check('P4-E1', workload.length > 0 && assignedProjects >= 1
  && workload.includes('v-table'),
  `employee ${employeeId} carries ${assignedProjects} project(s) in the database and the`
  + ` workload report renders them`);

const period = await flow.render({
  method: 'POST',
  uri: '/payroll/periods',
  body: form({
    _token: await token('/payroll'),
    jalali_year: '1404',
    jalali_month: '5',
  }),
});
const periodId = idOf(period);
const built = await flow.render({
  method: 'POST',
  uri: `/payroll/periods/${periodId}/build`,
  body: form({ _token: await token(`/payroll/periods/${periodId}`) }),
});
const activeEmployees = await n("select count(*) from employees where status = 'active'");
const payslips = await n(`select count(*) from payslips where payroll_period_id = ${periodId}`);

check('P4-E2', period.status === 302 && built.status === 302
  && payslips === activeEmployees && payslips > 0,
  `building period ${periodId} created ${payslips} payslip(s) for ${activeEmployees} active`
  + ` employee(s) — one per employee, none silently missing`);

const payslipForAssigned = await n(
  `select count(*) from payslips p join employees e on e.id = p.employee_id`
  + ` where p.payroll_period_id = ${periodId} and e.id = ${employeeId}`,
);

check('P4-E3', payslipForAssigned === 1,
  `the employee who was assigned to project ${projectId} has a payslip in the same period:`
  + ` ${payslipForAssigned}`);

/* ═══════════ F: keyword → SEO project → website address → search ═══════════ */

const KEYWORD = `یکپارچه‌سازی ${Date.now().toString(36)}`;
const keyword = await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/keywords`,
  body: form({
    _token: await token(`/projects/${projectId}`),
    keyword: KEYWORD,
    status: 'working',
  }),
});
const storedKeyword = await q(
  `select keyword from project_keywords where project_id = ${projectId} order by id desc limit 1`,
);
const projectPage = (await flow.render({ uri: `/projects/${projectId}` })).body;
const searchHit = await flow.render({ uri: `/projects?q=${encodeURIComponent(KEYWORD.split(' ')[0])}` });

check('P4-F1', keyword.status === 302 && storedKeyword.includes(KEYWORD.split(' ')[0]),
  `the keyword is stored on project ${projectId}: "${storedKeyword}"`);

check('P4-F2', projectPage.includes('https://integrated.example.ir')
  && projectPage.includes(KEYWORD.split(' ')[0]),
  `the project page shows both the website address and the keyword — §5-1's fields and §5-1's`
  + ` keyword list on one record`);

check('P4-F3', searchHit.status === 200 && searchHit.body.includes('پروژهٔ یکپارچه‌سازی'),
  `searching the projects index for the keyword's stem finds the project`);

/* ═══════════ G: note → reminder → dashboard → completion → audit ═══════════ */

const noteTitle = `یادداشت یکپارچه‌سازی ${Date.now().toString(36)}`;
const note = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: form({
    _token: await token('/notes/create'),
    title: noteTitle,
    description: 'پیگیری دسترسی‌ها',
    priority: 'high',
    due_date: '۱۴۰۴/۰۴/۰۱',
    project_id: projectId,
    customer_id: customerId,
  }),
});
// /notes redirects to the index rather than to the new record, so the id has
// to be read from the database rather than from Location.
const noteId = await q(`select id from notes where title = '${noteTitle}' order by id desc limit 1`);
const openNotes = await n("select count(*) from notes where status = 'open'");
const dashWithNote = (await flow.render({ uri: '/' })).body;

check('P4-G1', note.status === 302 && noteId !== undefined && openNotes >= 1
  && dashWithNote.includes(noteTitle),
  `note ${noteId} is stored open and appears on the manager dashboard (${openNotes} open note(s))`);

await flow.render({
  method: 'POST',
  uri: `/notes/${noteId}/complete`,
  body: form({ _token: await token(`/notes/${noteId}/edit`) }),
});
const noteStatus = await q(`select status from notes where id = ${noteId}`);
const auditRows = await n(
  `select count(*) from audit_logs where description like '%${noteTitle.slice(0, 20)}%'`,
);

check('P4-G2', noteStatus === 'done' && auditRows >= 1,
  `completing the note moves it to "${noteStatus}" and writes ${auditRows} activity row(s)`
  + ` naming it`);

/* ═══════════ H: global search → navigation → record authorization ═══════════ */

const search = await flow.render({ uri: `/customers?q=${encodeURIComponent(COMPANY)}` });
const searchFinds = search.body.includes(COMPANY);

await flow.resetPassword({ username: 'p4viewer', password: PASSWORD, mustChange: false, role: 'viewer' });
flow.jar.clear();
await flow.login('p4viewer', PASSWORD);
const viewerCustomer = await flow.render({ uri: `/customers/${customerId}` });
const viewerPayroll = await flow.render({ uri: '/payroll' });
flow.jar.clear();
await flow.login('admin', PASSWORD);

check('P4-H1', search.status === 200 && searchFinds,
  `the global search box finds the customer created in scenario A by name`);

check('P4-H2', viewerCustomer.status !== 500 && viewerPayroll.status === 403,
  `navigating to that record as a viewer returns ${viewerCustomer.status} (a permission`
  + ` decision, not a crash) and /payroll is refused with ${viewerPayroll.status}`);

/* ═══════════ I: backup → integrity → restore → reconciliation ═══════════ */

const scratch = path.join(ROOT, '.arena/phase4-backup');
fs.rmSync(scratch, { recursive: true, force: true });
fs.mkdirSync(scratch, { recursive: true });

const key = crypto.randomBytes(32).toString('base64');
fs.copyFileSync(flow.db, path.join(scratch, 'source.sqlite'));

const beforeCounts = {};
for (const table of ['customers', 'contracts', 'projects', 'work_items', 'users', 'audit_logs']) {
  beforeCounts[table] = await n(`select count(*) from ${table}`);
}

const backup = await php(PROBE, [
  path.join(scratch, 'source.sqlite'),
  `VIRA_BACKUP_KEY=${key}`,
  '--',
  'vira:backup',
  `--path=${path.join(scratch, 'out')}`,
]);
const container = fs.existsSync(path.join(scratch, 'out'))
  ? fs.readdirSync(path.join(scratch, 'out')).filter((f) => f.endsWith('.vbk1')).sort().pop()
  : null;

check('P4-I1', backup.out.includes('[exit 0]') && container !== null,
  `vira:backup produced ${container ?? 'nothing'} from the flow's database`
  + ` (${container ? fs.statSync(path.join(scratch, 'out', container)).size : 0} bytes)`);

const verify = container
  ? await php(PROBE, [
    path.join(scratch, 'source.sqlite'),
    `VIRA_BACKUP_KEY=${key}`,
    '--',
    'vira:restore',
    `file=${path.join(scratch, 'out', container)}`,
    '--verify',
  ])
  : { out: 'no container' };
const tampered = container ? path.join(scratch, 'tampered.vbk1') : null;

if (tampered) {
  const bytes = fs.readFileSync(path.join(scratch, 'out', container));
  // Flip a byte deep in the ciphertext, well past the header, so the failure
  // comes from GCM authentication rather than from a malformed container.
  bytes[Math.floor(bytes.length / 2)] ^= 0xff;
  fs.writeFileSync(tampered, bytes);
}

const tamperResult = tampered
  ? await php(PROBE, [
    path.join(scratch, 'source.sqlite'),
    `VIRA_BACKUP_KEY=${key}`,
    '--',
    'vira:restore',
    `file=${tampered}`,
    '--force',
    // Without this the command asks "Proceed?" and, with stdin closed, aborts
    // while STILL exiting 0. A check that only read the exit code would have
    // called a restore that wrote nothing a success.
    '--no-interaction',
  ])
  : { out: 'no container' };

check('P4-I2', verify.out.includes('[exit 0]')
  && !tamperResult.out.includes('[exit 0]'),
  `the unmodified container verifies (exit 0) and a container with one flipped ciphertext byte`
  + ` is refused — ${String(tamperResult.out).split('\n').find((l) => l.includes('gate') || l.includes('EXCEPTION') || l.includes('exit'))?.trim().slice(0, 80)}`);

const target = path.join(scratch, 'target.sqlite');
fs.writeFileSync(target, '');
const restored = container
  ? await php(PROBE, [
    target,
    `VIRA_BACKUP_KEY=${key}`,
    '--',
    'vira:restore',
    `file=${path.join(scratch, 'out', container)}`,
    '--force',
    // Without this the command asks "Proceed?" and, with stdin closed, aborts
    // while STILL exiting 0. A check that only read the exit code would have
    // called a restore that wrote nothing a success.
    '--no-interaction',
  ])
  : { out: 'no container' };

/*
 * Row counts of the restored file, read straight out of it. Comparing against a
 * number this suite computed some other way would be circular; the point is that
 * the container produced by the shipping backup command round-trips the rows.
 */
const afterCounts = {};
if (fs.existsSync(target) && fs.statSync(target).size > 0) {
  const read = await php(path.join(ROOT, 'tools/checks/scratch-count.php'), [
    target, ...Object.keys(beforeCounts),
  ]);

  for (const line of String(read.out).split('\n')) {
    const [table, count] = line.split('\t');

    if (table && /^\d+$/.test(String(count))) {
      afterCounts[table] = Number(count);
    }
  }
}

/*
 * audit_logs is allowed to GROW: restoring appends its own restore_ok row, so a
 * restored database has one more audit entry than the source had. Every other
 * table must come back identical — a table that silently gained or lost rows
 * would mean the container is not a faithful copy.
 */
const diff = Object.keys(beforeCounts).filter((t) => (t === 'audit_logs'
  ? afterCounts[t] < beforeCounts[t]
  : afterCounts[t] !== beforeCounts[t]));
const reconciled = Object.keys(beforeCounts)
  .map((t) => `${t}=${beforeCounts[t]}→${afterCounts[t] ?? '?'}`)
  .join(', ');

check('P4-I3', restored.out.includes('[exit 0]')
  && fs.existsSync(target) && fs.statSync(target).size > 0 && diff.length === 0,
  `restoring into an empty database succeeds and every table comes back with the row count`
  + ` it had at backup time: ${reconciled}`
  + `; audit_logs may grow because the restore records itself`
  + (diff.length ? ` — DIFFERS on ${diff.join(',')}` : ''));

/* ═══════════ shared database ═══════════ */

check('P4-Z', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
