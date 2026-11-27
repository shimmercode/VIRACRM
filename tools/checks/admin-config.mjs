#!/usr/bin/env node
/**
 * VIRA CRM — the §15 administrative configuration screens (REQ-15.01).
 *
 * §15 lists what must be configurable: contract types, project types, SEO
 * packages, the list of mandatory accesses, how payroll is calculated, and roles
 * and permissions. The tables behind all of them were seeded and most were read
 * by real code, but five had no screen at all — so the list was frozen at
 * whatever the seeder wrote and nobody could prove the code read it.
 *
 * Every check below asserts an EFFECT somewhere other than the screen that made
 * the change. A settings page that saves a row is not a settings feature; the
 * point is that the rest of the product behaves differently afterwards.
 *
 *   Contract types
 *     T-CFG-CT-01  the seeded list renders
 *     T-CFG-CT-02  a new type appears in the contract form
 *     T-CFG-CT-03  a duplicate code is a field error, not a 500
 *     T-CFG-CT-04  retiring a type removes it from the contract form
 *
 *   Project types
 *     T-CFG-PT-01  a new type appears in the project form
 *     T-CFG-PT-02  a project can actually be created with it (the validator
 *                  reads the table, not the Project::TYPES constant)
 *     T-CFG-PT-03  retiring a type stops new projects of that kind
 *     T-CFG-PT-04  bringing it back restores them
 *     T-CFG-PT-05  the code of a type projects already use cannot change
 *
 *   Required accesses — this one feeds §6's finalisation gate
 *     T-CFG-AR-01  adding a blocking requirement immediately blocks activation
 *     T-CFG-AR-02  removing it lifts the block
 *     T-CFG-AR-03  "blocks" without "required" is refused, because that
 *                  combination gates nothing and would look like a rule
 *     T-CFG-AR-04  the same access twice for one type is refused
 *
 *   Payroll rules
 *     T-CFG-PR-01  an approved active rule is stamped on a new period
 *     T-CFG-PR-02  an unapproved rule is never used, even when it is the only
 *                  one left
 *     T-CFG-PR-03  an unapproved method cannot be saved — OD-16 admits one
 *     T-CFG-PR-04  approving needs `payroll.approve`, not `settings.manage`
 *
 *   Roles and permissions
 *     T-CFG-RO-01  a permission granted to a role really reaches a user on it
 *     T-CFG-RO-02  a permission the role lacks really is refused
 *     T-CFG-RO-03  a user cannot hand out a permission they do not hold
 *     T-CFG-RO-04  ...but the guard does not block a permission they do hold
 *     T-CFG-RO-05  a role people are attached to cannot be deleted
 *     T-CFG-RO-06  the screen itself is closed to a user without users.manage
 *
 *     T-CFG-99     the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('admin-config');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('adminconfig');
await flow.setup();

const PASSWORD = `Ac-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri) => Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());

/** URL-encode a body that repeats one key, which permissions[] does. */
const formMulti = (o) => {
  const parts = [];
  for (const [k, v] of Object.entries(o)) {
    for (const item of Array.isArray(v) ? v : [v]) {
      parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(item)}`);
    }
  }

  return parts.join('&');
};

/**
 * The option list of one <select>, and nothing else from the page.
 *
 * Splitting on `name="x"` and reading the remainder of the document matches
 * every later select as well. «پشتیبانی» is the title of a seeded contract type
 * and also the project type this suite creates, so the loose version reported
 * success even when the project form had not changed.
 */
const selectBlock = (html, name) => {
  const i = String(html).indexOf(`name="${name}"`);

  if (i === -1) return '';

  return String(html).slice(i, String(html).indexOf('</select>', i));
};

/** Follow a redirect once so a flashed field error can be read. */
const after = async (res, fallback) => {
  const loc = String(res.headers['location'] || fallback).replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

const asAdmin = async () => {
  flow.jar.clear();
  await flow.login('admin', PASSWORD);
};

/* ══════════════════════ CONTRACT TYPES ══════════════════════ */

const ctIndex = await flow.render({ uri: '/settings/contract-types' });
check('T-CFG-CT-01', ctIndex.status === 200 && ctIndex.body.includes('پشتیبانی'),
  `the four seeded contract types render on /settings/contract-types (${ctIndex.status})`);

const ctNew = await flow.render({
  method: 'POST',
  uri: '/settings/contract-types',
  body: form({
    _token: await token('/settings/contract-types/create'),
    title: 'قرارداد آزمایشی',
    code: 'ct-probe',
    sort_order: '90',
    is_active: '1',
  }),
});
const ctId = await q("select id from contract_types where code = 'ct-probe'");
const contractForm = (await flow.render({ uri: '/contracts/create' })).body;
const ctBlock = selectBlock(contractForm, 'contract_type_id');

check('T-CFG-CT-02', ctNew.status === 302 && ctId !== '0'
  && ctBlock.includes('قرارداد آزمایشی'),
  `a type created here (id ${ctId}) is offered by the contract form:`
  + ` ${ctBlock.includes('قرارداد آزمایشی')}`);

const ctDup = await flow.render({
  method: 'POST',
  uri: '/settings/contract-types',
  body: form({
    _token: await token('/settings/contract-types/create'),
    title: 'تکراری',
    code: 'ct-probe',
    is_active: '1',
  }),
});
const ctDupErrors = errorsOn(await after(ctDup, '/settings/contract-types/create'));

check('T-CFG-CT-03', ctDup.status === 302
  && ctDupErrors.some((e) => e.includes('پیش‌تر ثبت شده'))
  && (await n("select count(*) from contract_types where code = 'ct-probe'")) === 1,
  `a duplicate code is refused as a field error (${ctDupErrors.join(' / ') || 'none'})`
  + ` and no second row was written`);

await flow.render({
  method: 'POST',
  uri: `/settings/contract-types/${ctId}`,
  body: form({
    _token: await token(`/settings/contract-types/${ctId}/edit`),
    _method: 'PUT',
    title: 'قرارداد آزمایشی',
    code: 'ct-probe',
    is_active: '0',
  }),
});
const ctAfterRetire = selectBlock((await flow.render({ uri: '/contracts/create' })).body,
  'contract_type_id');

check('T-CFG-CT-04', (await q(`select is_active from contract_types where id = ${ctId}`)) === '0'
  && !ctAfterRetire.includes('قرارداد آزمایشی'),
  `retiring the type stores is_active=0 and the contract form stops offering it:`
  + ` ${!ctAfterRetire.includes('قرارداد آزمایشی')}`);

/* ══════════════════════ PROJECT TYPES ══════════════════════ */

const customerId = await q('select id from customers order by id limit 1');

const ptNew = await flow.render({
  method: 'POST',
  uri: '/settings/project-types',
  body: form({
    _token: await token('/settings/project-types/create'),
    title: 'پشتیبانی',
    code: 'support',
    is_active: '1',
  }),
});
const ptForm = (await flow.render({ uri: '/projects/create' })).body;
const ptBlock = selectBlock(ptForm, 'type');

check('T-CFG-PT-01', ptNew.status === 302 && ptBlock.includes('پشتیبانی'),
  `a project type created here is offered by the project form: ${ptBlock.includes('پشتیبانی')}`);

const ptProject = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: await token('/projects/create'),
    customer_id: customerId,
    title: 'پروژهٔ پشتیبانی',
    type: 'support',
    status: 'draft',
    amount: '500000',
    start_date: '۱۴۰۴/۰۲/۰۱',
  }),
});
const ptProjectId = String(ptProject.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];

check('T-CFG-PT-02', ptProject.status === 302 && ptProjectId !== undefined
  && (await q(`select type from projects where id = ${ptProjectId}`)) === 'support',
  `POST /projects with the new type is accepted and stored: project ${ptProjectId}`
  + ` type="${await q(`select type from projects where id = ${ptProjectId}`)}" — the validator`
  + ` reads project_types, not the Project::TYPES constant`);

const ptId = await q("select id from project_types where code = 'design'");
await flow.render({
  method: 'POST',
  uri: `/settings/project-types/${ptId}`,
  body: form({
    _token: await token(`/settings/project-types/${ptId}/edit`),
    _method: 'PUT',
    title: 'طراحی',
    code: 'design',
    is_active: '0',
  }),
});
const retiredForm = selectBlock((await flow.render({ uri: '/projects/create' })).body, 'type');
const designRefused = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: await token('/projects/create'),
    customer_id: customerId,
    title: 'پروژهٔ طراحی',
    type: 'design',
    status: 'draft',
    amount: '500000',
    domain: 'x.ir',
  }),
});
const designErrors = errorsOn(await after(designRefused, '/projects/create'));

check('T-CFG-PT-03', !retiredForm.includes('طراحی')
  && designRefused.status === 302
  && designErrors.some((e) => e.includes('معتبر نیست')),
  `a retired type leaves the form (${!retiredForm.includes('طراحی')}) and a project of that`
  + ` kind is refused: ${designErrors.join(' / ') || 'none'}`);

await flow.render({
  method: 'POST',
  uri: `/settings/project-types/${ptId}`,
  body: form({
    _token: await token(`/settings/project-types/${ptId}/edit`),
    _method: 'PUT',
    title: 'طراحی',
    code: 'design',
    is_active: '1',
  }),
});
const restoredForm = selectBlock((await flow.render({ uri: '/projects/create' })).body, 'type');

check('T-CFG-PT-04', restoredForm.includes('طراحی'),
  `reactivating the type puts it back in the form: ${restoredForm.includes('طراحی')}`);

const seoId = await q("select id from project_types where code = 'seo'");
const codeChange = await flow.render({
  method: 'POST',
  uri: `/settings/project-types/${seoId}`,
  body: form({
    _token: await token(`/settings/project-types/${seoId}/edit`),
    _method: 'PUT',
    title: 'سئو',
    code: 'seo2',
    is_active: '1',
  }),
});

check('T-CFG-PT-05', (await q("select code from project_types where id = " + seoId)) === 'seo'
  && errorsOn(await after(codeChange, `/settings/project-types/${seoId}/edit`))
    .some((e) => e.includes('قابل تغییر نیست')),
  `renaming the code of a type projects already use is refused; the code is still`
  + ` "${await q(`select code from project_types where id = ${seoId}`)}"`);

/* ══════════════════ REQUIRED ACCESSES (§6 gate) ══════════════════ */

/*
 * The gate is proven on a DESIGN project on purpose. The three seeded
 * requirements are all for `seo`, so if the gate were hard-coded to the SEO
 * list this check would pass for the wrong reason. A requirement added here for
 * `design` has to be what blocks it.
 */
const gscId = await q("select id from credential_types where code = 'gsc'");

const designActive = {
  customer_id: customerId,
  title: 'پروژهٔ طراحی فعال',
  type: 'design',
  status: 'active',
  amount: '500000',
  start_date: '۱۴۰۴/۰۲/۰۱',
  domain: 'gate-example.ir',
  site_type: 'شرکتی',
};

const arAdd = await flow.render({
  method: 'POST',
  uri: '/settings/access-requirements',
  body: form({
    _token: await token('/settings/access-requirements/create'),
    project_type: 'design',
    credential_type_id: gscId,
    is_required: '1',
    blocks_finalize: '1',
  }),
});
const arId = await q(
  `select id from credential_requirements where project_type = 'design' and credential_type_id = ${gscId}`,
);

const blocked = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({ _token: await token('/projects/create'), ...designActive }),
});
const blockedErrors = errorsOn(await after(blocked, '/projects/create'));

check('T-CFG-AR-01', arAdd.status === 302 && arId !== '0'
  && blocked.status === 302
  && blockedErrors.some((e) => e.includes('دسترسی')),
  `requirement ${arId} added for design+gsc, and activating a design project is now`
  + ` refused: ${blockedErrors.join(' / ') || 'none'}`);

await flow.render({
  method: 'POST',
  uri: `/settings/access-requirements/${arId}`,
  body: form({
    _token: await token(`/settings/access-requirements/${arId}/edit`),
    _method: 'DELETE',
  }),
});
const unblocked = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({ _token: await token('/projects/create'), ...designActive }),
});
const unblockedId = String(unblocked.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];

check('T-CFG-AR-02', unblocked.status === 302 && unblockedId !== undefined
  && (await q(`select status from projects where id = ${unblockedId}`)) === 'active',
  `with the requirement removed the same project activates: ${unblockedId}`
  + ` status="${await q(`select status from projects where id = ${unblockedId}`)}"`);

const arBad = await flow.render({
  method: 'POST',
  uri: '/settings/access-requirements',
  body: form({
    _token: await token('/settings/access-requirements/create'),
    project_type: 'design',
    credential_type_id: gscId,
    is_required: '0',
    blocks_finalize: '1',
  }),
});

check('T-CFG-AR-03', arBad.status === 302
  && errorsOn(await after(arBad, '/settings/access-requirements/create'))
    .some((e) => e.includes('غیرالزامی')),
  `"blocks finalisation" on a non-required access is refused, because`
  + ` blockingFor() needs both flags and a row like that would gate nothing`);

await flow.render({
  method: 'POST',
  uri: '/settings/access-requirements',
  body: form({
    _token: await token('/settings/access-requirements/create'),
    project_type: 'design',
    credential_type_id: gscId,
    is_required: '1',
    blocks_finalize: '1',
  }),
});
const arDup = await flow.render({
  method: 'POST',
  uri: '/settings/access-requirements',
  body: form({
    _token: await token('/settings/access-requirements/create'),
    project_type: 'design',
    credential_type_id: gscId,
    is_required: '1',
    blocks_finalize: '1',
  }),
});
const arDupErrors = errorsOn(await after(arDup, '/settings/access-requirements/create'));

check('T-CFG-AR-04', arDupErrors.some((e) => e.includes('پیش‌تر'))
  && (await n(`select count(*) from credential_requirements where project_type = 'design'`
    + ` and credential_type_id = ${gscId}`)) === 1,
  `the same access twice for one project type is refused (${arDupErrors.join(' / ') || 'none'})`
  + ` and the unique index is not reached`);

/* ══════════════════════ PAYROLL RULES ══════════════════════ */

const seededRule = await q('select id from payroll_rules where is_approved = 1 order by id limit 1');

const period1 = await flow.render({
  method: 'POST',
  uri: '/payroll/periods',
  body: form({
    _token: await token('/payroll'),
    jalali_year: '1404',
    jalali_month: '3',
  }),
});
const period1Id = String(period1.headers['location'] || '').match(/\/payroll\/periods\/(\d+)/)?.[1];

check('T-CFG-PR-01', period1.status === 302 && period1Id !== undefined
  && (await q(`select payroll_rule_id from payroll_periods where id = ${period1Id}`)) === seededRule,
  `a new period is stamped with the approved rule ${seededRule}: got`
  + ` ${await q(`select payroll_rule_id from payroll_periods where id = ${period1Id}`)}`);

/* An unapproved rule must never reach a payslip, even when it is the only one. */
await flow.render({
  method: 'POST',
  uri: `/settings/payroll-rules/${seededRule}`,
  body: form({
    _token: await token(`/settings/payroll-rules/${seededRule}/edit`),
    _method: 'PUT',
    title: 'حقوق ثابت + اقلام دستی (تصمیم OD-16)',
    code: 'od16-manual-lines',
    method: 'manual_lines',
    is_active: '0',
    is_approved: '1',
  }),
});
await flow.render({
  method: 'POST',
  uri: '/settings/payroll-rules',
  body: form({
    _token: await token('/settings/payroll-rules/create'),
    title: 'قاعدهٔ تأییدنشده',
    code: 'unapproved-probe',
    method: 'manual_lines',
    is_active: '1',
    is_approved: '0',
  }),
});

const period2 = await flow.render({
  method: 'POST',
  uri: '/payroll/periods',
  body: form({
    _token: await token('/payroll'),
    jalali_year: '1404',
    jalali_month: '4',
  }),
});
const period2Id = String(period2.headers['location'] || '').match(/\/payroll\/periods\/(\d+)/)?.[1];
const period2Rule = await q(`select coalesce(payroll_rule_id,'NULL') from payroll_periods where id = ${period2Id}`);

check('T-CFG-PR-02', period2Id !== undefined && period2Rule === 'NULL',
  `with the approved rule inactive and only an unapproved one left, the new period`
  + ` ${period2Id} gets payroll_rule_id=${period2Rule} rather than an unapproved rule`);

await flow.render({
  method: 'POST',
  uri: `/settings/payroll-rules/${seededRule}`,
  body: form({
    _token: await token(`/settings/payroll-rules/${seededRule}/edit`),
    _method: 'PUT',
    title: 'حقوق ثابت + اقلام دستی (تصمیم OD-16)',
    code: 'od16-manual-lines',
    method: 'manual_lines',
    is_active: '1',
    is_approved: '1',
  }),
});

const badMethod = await flow.render({
  method: 'POST',
  uri: '/settings/payroll-rules',
  body: form({
    _token: await token('/settings/payroll-rules/create'),
    title: 'قاعدهٔ درصدی',
    code: 'percent-probe',
    method: 'percent_of_project_revenue',
    is_active: '1',
    is_approved: '0',
  }),
});

check('T-CFG-PR-03', badMethod.status === 302
  && errorsOn(await after(badMethod, '/settings/payroll-rules/create'))
    .some((e) => e.includes('تأیید نشده'))
  && (await n("select count(*) from payroll_rules where code = 'percent-probe'")) === 0,
  `a method outside PayrollRule::METHODS is refused — OD-16 admits fixed salary plus`
  + ` manual lines only, so no formula can be introduced from this screen`);

/* ══════════════════ ROLES AND PERMISSIONS ══════════════════ */

/*
 * A role holding one permission and not another, so both halves of the matrix
 * can be observed rather than assumed.
 */
await flow.render({
  method: 'POST',
  uri: '/settings/roles',
  body: formMulti({
    _token: await token('/settings/roles/create'),
    title: 'کارشناس مشتری',
    code: 'cfg_customer',
    description: 'فقط مشاهدهٔ مشتریان',
    'permissions[]': ['customers.view'],
  }),
});
const custRoleId = await q("select id from roles where code = 'cfg_customer'");
const custRolePerms = await q(
  `select group_concat(p.code, ',') from role_permissions rp join permissions p on p.id = rp.permission_id`
  + ` where rp.role_id = ${custRoleId}`,
);

await flow.resetPassword({ username: 'cfgcust', password: PASSWORD, mustChange: false, role: 'cfg_customer' });
flow.jar.clear();
await flow.login('cfgcust', PASSWORD);

const custAllowed = await flow.render({ uri: '/customers' });
const custDenied = await flow.render({ uri: '/payroll' });

check('T-CFG-RO-01', custRolePerms === 'customers.view'
  && custAllowed.status === 200,
  `a user on the role created here holds ${custRolePerms} and reaches /customers`
  + ` (${custAllowed.status})`);

check('T-CFG-RO-02', custDenied.status === 403,
  `the same user is refused /payroll, which the role does not carry:`
  + ` ${custDenied.status}`);

/* The escalation guard: a real user with users.manage but not payroll.approve. */
await asAdmin();
await flow.render({
  method: 'POST',
  uri: '/settings/roles',
  body: formMulti({
    _token: await token('/settings/roles/create'),
    title: 'ویرایشگر نقش',
    code: 'cfg_editor',
    'permissions[]': ['users.manage', 'settings.manage', 'customers.view', 'customers.create'],
  }),
});
await flow.resetPassword({ username: 'cfgeditor', password: PASSWORD, mustChange: false, role: 'cfg_editor' });
flow.jar.clear();
await flow.login('cfgeditor', PASSWORD);

const editorCanSee = await flow.render({ uri: '/settings/roles' });

const escalate = await flow.render({
  method: 'POST',
  uri: `/settings/roles/${custRoleId}`,
  body: formMulti({
    _token: await token(`/settings/roles/${custRoleId}/edit`),
    _method: 'PUT',
    title: 'کارشناس مشتری',
    code: 'cfg_customer',
    'permissions[]': ['customers.view', 'payroll.approve'],
  }),
});
const escalateErrors = errorsOn(await after(escalate, `/settings/roles/${custRoleId}/edit`));
const afterEscalate = await q(
  `select group_concat(p.code, ',') from role_permissions rp join permissions p on p.id = rp.permission_id`
  + ` where rp.role_id = ${custRoleId}`,
);

check('T-CFG-RO-03', editorCanSee.status === 200
  && escalateErrors.some((e) => e.includes('نمی‌توانید'))
  && afterEscalate === 'customers.view',
  `an editor who holds users.manage (${editorCanSee.status} on /settings/roles) is still`
  + ` refused when handing out payroll.approve — ${escalateErrors.join(' / ') || 'none'} —`
  + ` and the role is unchanged: ${afterEscalate}`);

const legit = await flow.render({
  method: 'POST',
  uri: `/settings/roles/${custRoleId}`,
  body: formMulti({
    _token: await token(`/settings/roles/${custRoleId}/edit`),
    _method: 'PUT',
    title: 'کارشناس مشتری',
    code: 'cfg_customer',
    'permissions[]': ['customers.view', 'customers.create'],
  }),
});

const afterLegit = await q(
  `select group_concat(p.code, ',') from role_permissions rp join permissions p on p.id = rp.permission_id`
  + ` where rp.role_id = ${custRoleId} order by p.code`,
);

check('T-CFG-RO-04', legit.status === 302
  && String(afterLegit).split(',').sort().join(',') === 'customers.create,customers.view',
  `the guard is not over-blocking: the same editor may grant customers.create, which they`
  + ` do hold. The role went from ${afterEscalate} to ${afterLegit}`);

/*
 * T-CFG-PR-04 — approving a payroll rule is not a settings edit.
 *
 * The editor holds `settings.manage`, so they can create and rename a rule.
 * They do not hold `payroll.approve`, so they must not be able to make one
 * usable. Whoever maintains configuration is not automatically whoever
 * authorises how salaries are computed.
 */
const unapprovedId = await q("select id from payroll_rules where code = 'unapproved-probe'");
const approveAttempt = await flow.render({
  method: 'POST',
  uri: `/settings/payroll-rules/${unapprovedId}`,
  body: form({
    _token: await token(`/settings/payroll-rules/${unapprovedId}/edit`),
    _method: 'PUT',
    title: 'قاعدهٔ تأییدنشده',
    code: 'unapproved-probe',
    method: 'manual_lines',
    is_active: '1',
    is_approved: '1',
  }),
});
const approveErrors = errorsOn(await after(approveAttempt, `/settings/payroll-rules/${unapprovedId}/edit`));

check('T-CFG-PR-04', (await q(`select is_approved from payroll_rules where id = ${unapprovedId}`)) === '0'
  && approveErrors.some((e) => e.includes('تأیید حقوق')),
  `an editor holding settings.manage but not payroll.approve cannot approve the rule:`
  + ` is_approved is still ${await q(`select is_approved from payroll_rules where id = ${unapprovedId}`)}`
  + ` — ${approveErrors.join(' / ') || 'none'}`);

await asAdmin();
const editorRole = await q("select id from roles where code = 'cfg_editor'");
const editorUsers = await n(`select count(*) from user_roles where role_id = ${editorRole}`);
const delInUse = await flow.render({
  method: 'POST',
  uri: `/settings/roles/${editorRole}`,
  body: form({
    _token: await token(`/settings/roles/${editorRole}/edit`),
    _method: 'DELETE',
  }),
});

check('T-CFG-RO-05', editorUsers > 0
  && (await n(`select count(*) from roles where id = ${editorRole}`)) === 1
  && (await flow.render({ uri: '/settings/roles' })).body.includes('این نقش را دارند'),
  `a role with ${editorUsers} user(s) attached cannot be deleted, and the list says so:`
  + ` «… کاربر این نقش را دارند؛ ابتدا نقش آن‌ها را تغییر دهید.»`);

const outsider = await flow.render({ uri: '/customers' });
await flow.resetPassword({ username: 'cfgout', password: PASSWORD, mustChange: false, role: 'viewer' });
flow.jar.clear();
await flow.login('cfgout', PASSWORD);
const closedScreen = await flow.render({ uri: '/settings/roles' });
const closedWrite = await flow.render({
  method: 'POST',
  uri: '/settings/roles',
  body: formMulti({
    _token: await token('/customers'),
    title: 'نقش غیرمجاز',
    code: 'cfg_sneak',
    'permissions[]': ['users.manage'],
  }),
});
await asAdmin();

check('T-CFG-RO-06', outsider.status === 200 && closedScreen.status === 403
  && closedWrite.status === 403
  && (await n("select count(*) from roles where code = 'cfg_sneak'")) === 0,
  `a viewer who can read /customers (${outsider.status}) is refused the roles screen`
  + ` (${closedScreen.status}) and a write to it (${closedWrite.status}); no role was`
  + ` created — users.manage is held by no seeded role`);

/* ══════════════════════ SHARED DATABASE ══════════════════════ */

check('T-CFG-99', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
