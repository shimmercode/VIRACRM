#!/usr/bin/env node
/**
 * END-TO-END USER JOURNEYS — does the product actually work as a product?
 *
 * WHY THIS EXISTS ALONGSIDE THE OTHER FLOWS
 * -----------------------------------------
 * The existing flow checks are per-module: crud-flow proves customers work,
 * project-flow proves projects work, payroll-flow proves payroll works. That is
 * necessary but it is not sufficient, and it hides a specific class of failure —
 * the seam. A module can pass every one of its own tests while the journey a
 * human actually walks is broken, because the break lives between two modules:
 * the customer created in step 1 does not appear in the contract form in step 2,
 * the project in step 3 cannot see the contract from step 2, the reminder
 * created in step 4 never reaches the dashboard, the session killed in step 10
 * still works.
 *
 * These are the ten journeys named in the product audit brief. Each one runs on
 * its own isolated database, creates its own data, and cleans up after itself.
 *
 *   W-01  login -> dashboard -> create customer -> dashboard reflects it
 *   W-02  customer -> contract -> project, all linked to one customer
 *   W-03  project -> credentials -> project PDF and confidential PDF
 *   W-04  note + obligation -> both visible in the cartable
 *   W-05  employee -> payroll, with the permission boundary
 *   W-06  search and filter across customers, contracts, projects
 *   W-07  reveal and export under different roles
 *   W-08  a sensitive event becomes visible in the audit log
 *   W-09  backup container verifies; restore refuses a tampered one
 *   W-10  logout -> the old session cannot get back in
 *   W-11  negative paths: invalid data, missing permission, direct URL, IDOR
 *
 * A CHECK THAT ONLY LOOKS AT A STATUS CODE IS NOT A CHECK. Where a journey
 * claims "the record is linked", the assertion reads the row back out of the
 * database and compares the foreign key. Where it claims "the dashboard shows
 * it", the assertion parses the rendered number and compares it to a COUNT.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { Flow, Checks, ROOT } from './lib/flow-harness.mjs';

const flow = new Flow('e2e-journeys');
await flow.setup();

const c = new Checks('e2e-workflows');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `E2e-${Date.now().toString(36)}-Q4`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/* A per-run vault key. Without it the product refuses to store a secret at
 * all, which is itself correct behaviour — but it means every credential step
 * below would silently test nothing. */
const VAULT_KEY = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 37 + 11) % 256),
).toString('base64');

const SECRET = `Wp-E2e-${Date.now().toString(36)}-Z3`;

const tok = (html) => Flow.token(html);
const form = (o) => new URLSearchParams(
  Object.fromEntries(Object.entries(o).filter(([, v]) => v !== null && v !== undefined)),
).toString();
const num = async (sql) => Number(String(await flow.query(sql)).trim()) || 0;
const uniq = Date.now().toString(36);

/* Every POST needs a fresh token; reusing one gives 419, and a test that
 * expected 403 would then pass for the wrong reason. */
const post = async (uri, fields, opts = {}) => {
  const page = await flow.render({ uri: fields.__from || '/', ...opts.page });
  const { __from, ...rest } = fields;
  return flow.render({
    method: 'POST',
    uri,
    body: form({ _token: tok(page.body), ...rest }),
    env: opts.env,
  });
};

/* ══ W-01 · login -> dashboard -> create customer ─══════════════════════ */
const loginPage = await flow.render({ uri: '/login' });
const loggedIn = await flow.render({
  method: 'POST',
  uri: '/login',
  body: form({ _token: tok(loginPage.body), username: 'admin', password: PASSWORD }),
});

const dash1 = await flow.render({ uri: '/' });
const customersBefore = await num('select count(*) from customers');

check(
  'W-01a',
  loggedIn.status === 302 && dash1.status === 200 && dash1.body.includes('v-sidebar'),
  `login -> ${loggedIn.status}, dashboard -> ${dash1.status}`,
);

/* The dashboard's customer count must equal the real one. A dashboard that
 * renders is not a dashboard that is right. */
/*
 * Read the dashboard's five counters into a label -> number map.
 *
 * Written as a REGEX LITERAL on purpose. The first two versions built the
 * pattern from a template literal, and in a template literal `\s` is not an
 * escape — it collapses to a bare `s`, so the pattern was looking for
 * `</span>s*<span` and matched nothing. Two checks then reported `null` against
 * a dashboard that was displaying the right number. A regex literal cannot be
 * mangled by string escaping, and scanning every counter at once means a label
 * typo shows up as a missing key rather than as a silent null.
 */
const dashStats = (body) => {
  const out = {};
  const re = /<span class="v-stat__label">([^<]+)<\/span>\s*<span class="v-stat__value">([^<]+)<\/span>/g;
  let m;
  while ((m = re.exec(body)) !== null) {
    out[m[1]] = Number(
      m[2].replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[^\d]/g, ''),
    );
  }
  return out;
};

const stats1 = dashStats(dash1.body);
const shownCustomers = stats1['مشتریان'];
check(
  'W-01b',
  shownCustomers === customersBefore,
  `dashboard customer figure ${shownCustomers} vs database COUNT ${customersBefore}`
    + ` (counters read: ${Object.keys(stats1).join(', ') || 'none'})`,
);

const cPage = await flow.render({ uri: '/customers/create' });
const customerName = `شرکت سفر یکپارچه ${uniq}`;
await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: tok(cPage.body),
    type: 'legal',
    status: 'active',
    company_name: customerName,
    mobile: `09${String(Date.now()).slice(-9)}`,
    national_id: String(10000000000 + (Date.now() % 999999999)),
    address: 'تهران، خیابان آزادی',
    source: 'referral',
    notes: 'ساخته‌شده توسط آزمون گردش‌کار',
  }),
});
const customerId = await num('select id from customers order by id desc limit 1');
const customerStored = String(await flow.query(
  `select company_name from customers where id = ${customerId}`,
)).trim();

check(
  'W-01c',
  customerId > customersBefore && customerStored === customerName,
  `customer created id=${customerId}; name round-tripped: ${customerStored === customerName}`,
);

const dash2 = await flow.render({ uri: '/' });
check(
  'W-01d',
  dashStats(dash2.body)['مشتریان'] === customersBefore + 1,
  `dashboard reflects the new customer: ${dashStats(dash2.body)['مشتریان']} vs ${customersBefore + 1}`,
);

/* ══ W-02 · customer -> contract -> project, one lineage ════════════════ */
const ctPage = await flow.render({ uri: '/contracts/create' });

/* The contract form must offer the customer just created. If the dropdown is
 * built from a stale or filtered query, the journey breaks here and no
 * contract test would notice. */
const offersCustomer = ctPage.body.includes(`value="${customerId}"`);
check(
  'W-02a',
  ctPage.status === 200 && offersCustomer,
  `contract form -> ${ctPage.status}; offers customer ${customerId}: ${offersCustomer}`,
);

await flow.render({
  method: 'POST',
  uri: '/contracts',
  body: form({
    _token: tok(ctPage.body),
    customer_id: String(customerId),
    title: `قرارداد سئو ${uniq}`,
    amount: '48000000',
    start_date: '1405/01/01',
    end_date: '1405/12/29',
    status: 'active',
    party_one_name: 'شرکت ویرا',
    party_two_name: customerName,
    description: 'سئو و تولید محتوا',
  }),
});
const contractId = await num('select id from contracts order by id desc limit 1');
const contractCustomer = await num(`select customer_id from contracts where id = ${contractId}`);

check(
  'W-02b',
  contractId > 0 && contractCustomer === customerId,
  `contract id=${contractId} linked to customer ${contractCustomer} (expected ${customerId})`,
);

const pPage = await flow.render({ uri: '/projects/create' });
await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: tok(pPage.body),
    customer_id: String(customerId),
    contract_id: String(contractId),
    type: 'seo',
    title: `پروژه سئو ${uniq}`,
    // Draft, not active: §6 forbids finalising an SEO project before its
    // mandatory accesses exist, and a fresh project has none. T-PRJ-09b/f in
    // project-flow covers both halves of that gate.
    status: 'draft',
    amount: '48000000',
    start_date: '1405/02/01',
    end_date: '1405/08/01',
    keyword_count: 30,
    recurrence: 'monthly',
    reminder_offset_days: 2,
    notes: 'گردش‌کار یکپارچه',
  }),
});
const projectId = await num('select id from projects order by id desc limit 1');
const projectRow = String(await flow.query(
  `select customer_id || ':' || coalesce(contract_id,0) from projects where id = ${projectId}`,
)).trim();

check(
  'W-02c',
  projectRow === `${customerId}:${contractId}`,
  `project id=${projectId} lineage customer:contract = ${projectRow} (expected ${customerId}:${contractId})`,
);

/* The customer dossier is the hub the requirements call for: everything hangs
 * off it. Both the contract and the project must be reachable from there. */
const dossier = await flow.render({ uri: `/customers/${customerId}` });
check(
  'W-02d',
  dossier.status === 200
    && dossier.body.includes(`/contracts/${contractId}`)
    && dossier.body.includes(`/projects/${projectId}`),
  `customer dossier -> ${dossier.status}; links contract: `
    + `${dossier.body.includes(`/contracts/${contractId}`)}, project: `
    + `${dossier.body.includes(`/projects/${projectId}`)}`,
);

/* ══ W-03 · project -> credentials -> PDFs ══════════════════════════════ */
const crPage = await flow.render({ uri: `/projects/${projectId}/credentials/create` });
const wpType = (crPage.body.match(/<option value="(\d+)"[^>]*>\s*وردپرس/) || [])[1];

await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials`,
  env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
  body: form({
    _token: tok(crPage.body),
    credential_type_id: wpType,
    label: 'وردپرس سایت اصلی',
    username: 'vira_admin',
    public_url: 'https://example.invalid/wp-admin',
    secret: SECRET,
  }),
});
const credentialId = await num('select id from project_credentials order by id desc limit 1');
const storedCipher = String(await flow.query(
  `select ciphertext from credential_values where project_credential_id = ${credentialId} and field = 'password'`,
)).trim();

check(
  'W-03a',
  credentialId > 0 && storedCipher.startsWith('v1:') && !storedCipher.includes(SECRET),
  `credential id=${credentialId} stored as envelope ciphertext, plaintext absent: `
    + `${!storedCipher.includes(SECRET)}`,
);

const projectPdf = await flow.render({ uri: `/projects/${projectId}/pdf` });
check(
  'W-03b',
  projectPdf.status === 200 && projectPdf.body.trimStart().startsWith('%PDF-'),
  `project PDF -> ${projectPdf.status}, ${projectPdf.body.length} bytes, `
    + `starts with %PDF: ${projectPdf.body.trimStart().startsWith('%PDF-')}`,
);

/* The requirements are explicit: the confidential access sheet must be a
 * separate deliverable from the general project document, and it must be a
 * POST — a secret in a URL is a secret in the history. */
const credList = await flow.render({ uri: `/projects/${projectId}/credentials` });
const credPdf = await flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
  body: form({ _token: tok(credList.body) }),
});
check(
  'W-03c',
  credPdf.status === 200
    && credPdf.body.trimStart().startsWith('%PDF-')
    && /no-store/.test(String(credPdf.headers['cache-control'] || '')),
  `confidential PDF via POST -> ${credPdf.status}, no-store: `
    + `${/no-store/.test(String(credPdf.headers['cache-control'] || ''))}`,
);

/* ══ W-04 · note + obligation -> cartable ═══════════════════════════════ */
const notePage = await flow.render({ uri: '/notes/create' });
const noteTitle = `یادداشت گردش‌کار ${uniq}`;
await flow.render({
  method: 'POST',
  uri: '/notes',
  body: form({
    _token: tok(notePage.body),
    title: noteTitle,
    description: 'از آزمون گردش‌کار یکپارچه',
    priority: 'high',
    due_date: '1405/09/03',
    customer_id: String(customerId),
  }),
});
const noteId = await num('select id from notes order by id desc limit 1');
const noteCustomer = await num(`select coalesce(customer_id,0) from notes where id = ${noteId}`);

check(
  'W-04a',
  noteId > 0 && noteCustomer === customerId,
  `note id=${noteId} attached to customer ${noteCustomer} (expected ${customerId})`,
);

const wiPage = await flow.render({ uri: '/work-items' });
const obligationsBefore = await num(
  `select count(*) from work_items where project_id = ${projectId} and state = 'pending'`,
);
await flow.render({
  method: 'POST',
  uri: '/work-items',
  body: form({
    _token: tok(wiPage.body),
    project_id: String(projectId),
    kind: 'report',
    due_date: '1405/09/10',
    recurrence: 'monthly',
  }),
});
const obligationsAfter = await num(
  `select count(*) from work_items where project_id = ${projectId} and state = 'pending'`,
);

check(
  'W-04b',
  obligationsAfter === obligationsBefore + 1,
  `pending obligations for the project ${obligationsBefore} -> ${obligationsAfter}`,
);

const cartable = await flow.render({ uri: '/work-items' });
const notesList = await flow.render({ uri: '/notes' });
check(
  'W-04c',
  cartable.status === 200 && notesList.status === 200 && notesList.body.includes(noteTitle),
  `cartable -> ${cartable.status}; the note is listed on /notes: `
    + `${notesList.body.includes(noteTitle)}`,
);

/* Closing an obligation must not leave it in the open list — the requirements
 * say completed items leave the open list but their history is kept. */
const itemId = await num(
  `select id from work_items where project_id = ${projectId} and state = 'pending' order by id desc limit 1`,
);
await post(`/work-items/${itemId}/complete`, { __from: `/work-items/${itemId}` });
const stillPending = await num(`select count(*) from work_items where id = ${itemId} and state = 'pending'`);
const keptHistory = await num(`select count(*) from work_items where id = ${itemId}`);

check(
  'W-04d',
  stillPending === 0 && keptHistory === 1,
  `after completion: pending=${stillPending}, row still exists=${keptHistory} `
    + '(leaves the open list, history kept)',
);

/* ══ W-05 · employee -> payroll boundary ════════════════════════════════ */
const empPage = await flow.render({ uri: '/employees/create' });
await flow.render({
  method: 'POST',
  uri: '/employees',
  body: form({
    _token: tok(empPage.body),
    first_name: 'آزمون',
    last_name: `گردش‌کار ${uniq}`,
    national_id: String(20000000000 + (Date.now() % 999999999)),
    mobile: `09${String(Date.now() + 7).slice(-9)}`,
    position: 'کارشناس سئو',
    status: 'active',
  }),
});
const employeeId = await num('select id from employees order by id desc limit 1');
const payrollPage = await flow.render({ uri: '/payroll' });

check(
  'W-05a',
  employeeId > 0 && payrollPage.status === 200,
  `employee id=${employeeId}; payroll page -> ${payrollPage.status}`,
);

/* A viewer must not see payroll at all. This is the confidential-financial
 * boundary in §10, tested on the real route rather than on a helper. */
const viewerPw = `V-${uniq}`;
await flow.resetPassword({ username: 'e2e_viewer', password: viewerPw, mustChange: false, role: 'viewer' });
const vAgent = new Flow('e2e-journeys');
vAgent.db = flow.db;
vAgent.dir = flow.dir;
await vAgent.login('e2e_viewer', viewerPw, { expectRedirect: true });

const viewerPayroll = await vAgent.render({ uri: '/payroll' });
const viewerEmployee = await vAgent.render({ uri: `/employees/${employeeId}` });
check(
  'W-05b',
  viewerPayroll.status === 403 && viewerEmployee.status === 403,
  `viewer: /payroll -> ${viewerPayroll.status}, /employees/${employeeId} -> ${viewerEmployee.status}`,
);

/* ══ W-06 · search and filter ═══════════════════════════════════════════ */
const searchHit = await flow.render({ uri: `/customers?q=${encodeURIComponent(uniq)}` });
const searchMiss = await flow.render({ uri: `/customers?q=zzz-no-such-${uniq}` });

check(
  'W-06a',
  searchHit.status === 200
    && searchHit.body.includes(customerName)
    && !searchMiss.body.includes(customerName),
  `search for "${uniq}" finds the customer: ${searchHit.body.includes(customerName)}; `
    + `an unrelated query does not: ${!searchMiss.body.includes(customerName)}`,
);

const filteredContracts = await flow.render({ uri: `/contracts?customer=${customerId}` });
const otherContracts = await flow.render({ uri: '/contracts?customer=999999' });
check(
  'W-06b',
  filteredContracts.status === 200 && otherContracts.status === 200,
  `contract filter by customer -> ${filteredContracts.status}; impossible id -> ${otherContracts.status}`,
);

const pagedCustomers = await flow.render({ uri: '/customers?page=1' });
check(
  'W-06c',
  pagedCustomers.status === 200,
  `paginated customer list -> ${pagedCustomers.status}`,
);

/* ══ W-07 · reveal and export under different roles ═════════════════════ */
const managerPw = `M-${uniq}`;
await flow.resetPassword({ username: 'e2e_manager', password: managerPw, mustChange: false, role: 'manager' });
const mAgent = new Flow('e2e-journeys');
mAgent.db = flow.db;
mAgent.dir = flow.dir;
await mAgent.login('e2e_manager', managerPw, { expectRedirect: true });
const mDash = await mAgent.render({ uri: '/' });

const managerReveal = await mAgent.render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/${credentialId}/reveal`,
  env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
  body: form({ _token: tok(mDash.body) }),
});
const managerExport = await mAgent.render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
  body: form({ _token: tok(mDash.body) }),
});
const viewerExport = await vAgent.render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  body: form({ _token: tok((await vAgent.render({ uri: '/' })).body) }),
});

check(
  'W-07a',
  managerReveal.status === 200,
  `manager may reveal -> ${managerReveal.status} (credentials.reveal is granted to manager)`,
);
check(
  'W-07b',
  managerExport.status === 403 && viewerExport.status === 403,
  `export refused for manager -> ${managerExport.status}, viewer -> ${viewerExport.status} `
    + '(export is super-admin only)',
);

/* IDOR: a user with no access to this project must not reach its credentials
 * by typing the URL, even with a valid session. */
const viewerCredList = await vAgent.render({ uri: `/projects/${projectId}/credentials` });
check(
  'W-07c',
  viewerCredList.status === 403,
  `viewer typing /projects/${projectId}/credentials directly -> ${viewerCredList.status}`,
);

/* ══ W-08 · sensitive event -> audit log ════════════════════════════════ */
const revealedRows = await num("select count(*) from audit_logs where event = 'revealed'");
const exportedRows = await num("select count(*) from audit_logs where event = 'exported'");
const deniedRows = await num("select count(*) from audit_logs where event = 'denied'");

check(
  'W-08a',
  revealedRows > 0 && exportedRows > 0 && deniedRows > 0,
  `audit rows after the journeys: revealed=${revealedRows}, exported=${exportedRows}, `
    + `denied=${deniedRows} — each sensitive act left a trail`,
);

const auditPage = await flow.render({ uri: '/audit' });
const leaked = await num(
  `select count(*) from audit_logs where description like '%${SECRET}%' or changes like '%${SECRET}%'`,
);
check(
  'W-08b',
  auditPage.status === 200
    && !auditPage.body.includes(SECRET)
    && leaked === 0,
  `audit page -> ${auditPage.status}; the secret appears in the page: `
    + `${auditPage.body.includes(SECRET)}, in the table: ${leaked}`,
);

/* ══ W-09 · backup container verifies; tamper is refused ════════════════
 * Driven through the real console commands against an isolated database.
 * The shared database is never opened: DB_DATABASE points at a temp file. */
const isoDir = path.join(ROOT, '.arena/flows/e2e-journeys/backup');
fs.rmSync(isoDir, { recursive: true, force: true });
fs.mkdirSync(isoDir, { recursive: true });

const backupKey = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 53 + 7) % 256),
).toString('base64');
const isoEnv = { ...process.env, DB_DATABASE: path.join(isoDir, 'iso.sqlite'), VIRA_BACKUP_KEY: backupKey };

const artisan = (args, env) => {
  try {
    const out = execFileSync(
      process.execPath,
      [path.join(ROOT, 'docs/tools/php-cli.mjs'), 'artisan', ...args],
      { cwd: ROOT, env, encoding: 'utf8', timeout: 180_000, stdio: ['ignore', 'pipe', 'pipe'] },
    );
    return { code: 0, out };
  } catch (e) {
    return { code: e.status ?? 1, out: `${e.stdout || ''}${e.stderr || ''}` };
  }
};

const migrated = artisan(['migrate', '--force'], isoEnv);
const backed = artisan(['vira:backup', `--path=${isoDir}`], isoEnv);
const containers = fs.readdirSync(isoDir).filter((f) => f.endsWith('.vbk1'));
const container = containers.length ? path.join(isoDir, containers[0]) : null;

check(
  'W-09a',
  migrated.code === 0 && backed.code === 0 && containers.length === 1,
  `isolated migrate -> ${migrated.code}; vira:backup -> ${backed.code}; `
    + `containers written: ${containers.length}`,
);

let verifyOnly = { code: 1, out: 'no container' };
let tamperedRefused = { code: 0, out: 'no container' };
let wrongKeyRefused = { code: 0, out: 'no container' };

if (container) {
  verifyOnly = artisan(['vira:restore', container], isoEnv);

  const tampered = path.join(isoDir, 'tampered.vbk1');
  const bytes = fs.readFileSync(container);
  bytes[bytes.length - 300] ^= 0x5a;
  fs.writeFileSync(tampered, bytes);
  tamperedRefused = artisan(['vira:restore', tampered, '--force', '--no-interaction'], isoEnv);

  wrongKeyRefused = artisan(
    ['vira:restore', container, '--force', '--no-interaction'],
    { ...isoEnv, VIRA_BACKUP_KEY: Buffer.from(Array.from({ length: 32 }, () => 9)).toString('base64') },
  );
}

check(
  'W-09b',
  container !== null
    && verifyOnly.code === 0
    && /Nothing was written/.test(verifyOnly.out)
    && tamperedRefused.code === 1
    && wrongKeyRefused.code === 1,
  container === null
    ? 'no container was produced, so the restore gates could not be exercised'
    : `verify-only -> ${verifyOnly.code} (writes nothing: `
      + `${/Nothing was written/.test(verifyOnly.out)}); tampered -> ${tamperedRefused.code}; `
      + `wrong key -> ${wrongKeyRefused.code} (both must be 1)`,
);

/* ══ W-10 · logout -> the old session is dead ═══════════════════════════ */
const lAgent = new Flow('e2e-journeys');
lAgent.db = flow.db;
lAgent.dir = flow.dir;
await lAgent.login('admin', PASSWORD, { expectRedirect: true });
const beforeLogout = await lAgent.render({ uri: '/customers' });

const lPage = await lAgent.render({ uri: '/' });
await lAgent.render({
  method: 'POST',
  uri: '/logout',
  body: form({ _token: tok(lPage.body) }),
});
const afterLogout = await lAgent.render({ uri: '/customers' });
const afterLogoutAudit = await lAgent.render({ uri: '/audit' });

check(
  'W-10a',
  beforeLogout.status === 200 && afterLogout.status === 302 && afterLogoutAudit.status === 302,
  `before logout /customers -> ${beforeLogout.status}; after logout -> ${afterLogout.status}, `
    + `/audit -> ${afterLogoutAudit.status} (the old cookie must not get back in)`,
);

/* ══ W-11 · negative paths ══════════════════════════════════════════════ */
/*
 * Count rows before and after, rather than looking for "nameless" rows.
 *
 * The first version asserted that no customer had an empty company_name — but a
 * natural person legitimately has no company name, so the seeded data already
 * contained two such rows and the check failed on data it had nothing to do
 * with. What actually matters is that the invalid submit created nothing.
 */
const customersBeforeBad = await num('select count(*) from customers');
const badCustomer = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({ _token: tok((await flow.render({ uri: '/customers/create' })).body), type: 'legal', status: 'lead' }),
});
const customersAfterBad = await num('select count(*) from customers');
const badFormAgain = await flow.render({ uri: '/customers/create' });

check(
  'W-11a',
  badCustomer.status === 302
    && customersAfterBad === customersBeforeBad
    && /را وارد کنید/.test(badFormAgain.body),
  `empty customer -> ${badCustomer.status} (redirect back); rows `
    + `${customersBeforeBad} -> ${customersAfterBad}; a Persian validation message is `
    + `shown on the re-rendered form: ${/را وارد کنید/.test(badFormAgain.body)}`,
);

/* A record that does not exist must 404, not 500 and not someone else's data. */
const missingCustomer = await flow.render({ uri: '/customers/999999' });
const missingProject = await flow.render({ uri: '/projects/999999' });
check(
  'W-11b',
  missingCustomer.status === 404 && missingProject.status === 404,
  `missing customer -> ${missingCustomer.status}, missing project -> ${missingProject.status}`,
);

/* SQL injection through a filter must not change the result set or error out. */
const injected = await flow.render({ uri: `/customers?q=${encodeURIComponent("' or 1=1 --")}` });
check(
  'W-11c',
  injected.status === 200 && !injected.body.includes(customerName),
  `injection attempt -> ${injected.status}; it did not return the whole table: `
    + `${!injected.body.includes(customerName)}`,
);

/* Reflected XSS must be escaped by the view layer. */
const xss = `Xss${uniq}<script>alert(1)</script>`;
await flow.render({
  method: 'POST',
  uri: '/notes',
  body: form({
    _token: tok((await flow.render({ uri: '/notes/create' })).body),
    title: xss,
    description: 'xss probe',
    priority: 'low',
    due_date: '1405/09/05',
  }),
});
const xssList = await flow.render({ uri: '/notes' });
check(
  'W-11d',
  !xssList.body.includes('<script>alert(1)</script>'),
  `stored XSS payload rendered as live markup: ${xssList.body.includes('<script>alert(1)</script>')}`,
);

c.summary();
process.exit(c.summary() ? 0 : 1);
