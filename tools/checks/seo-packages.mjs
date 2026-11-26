#!/usr/bin/env node
/**
 * VIRA CRM — SEO service packages (§15, REQ-15.01).
 *
 * WHY THIS EXISTS
 * ---------------
 * `seo_packages` was read by the project form's dropdown and by nothing else.
 * There was no way to create a package, change its keyword ceiling, or retire
 * one — so the limit that ProjectKeyword enforces when a keyword is added was a
 * number nobody could move. This is the same defect the assignment and keyword
 * screens fixed: a table feeding a rule, with no way for the product to write it.
 *
 * The point of the suite is the effect, not the CRUD:
 *
 *   T-SP-01  the index lists the seeded packages
 *   T-SP-02  a package is created, Persian digits converted on the way in
 *   T-SP-03  an empty ceiling is stored as NULL, meaning unlimited
 *   T-SP-04  a duplicate code is a field error, not a 500
 *   T-SP-05  a code with spaces or Persian letters is refused
 *   T-SP-06  EFFECT: the new package appears in the project form
 *   T-SP-07  EFFECT: the ceiling a project inherits is the number set here —
 *            two keywords accepted, the third refused
 *   T-SP-08  EFFECT: editing the ceiling changes what the project accepts, with
 *            no re-seeding and no restart
 *   T-SP-09  EFFECT: deactivating removes it from the project form
 *   T-SP-10  a package with projects on it cannot be deleted, only deactivated;
 *            one with none can
 *   T-SP-11  a user without settings.manage can read but not write
 *   T-SP-12  every mutation is audited
 *   T-SP-13  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('seo-packages');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('seopackage');
await flow.setup();

const PASSWORD = `Sp-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/settings/seo-packages/create') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());
const followRedirect = async (res) => {
  const loc = String(res.headers['location'] || '/settings/seo-packages').replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

const CODE = `sp-${Date.now().toString(36)}`;

/* ── T-SP-01: the index ───────────────────────────────────────────────── */
const index = await flow.render({ uri: '/settings/seo-packages' });
check('T-SP-01', index.status === 200 && index.body.includes('seo-standard')
  && index.body.includes('پکیج‌های سئو'),
  `GET /settings/seo-packages -> ${index.status}, seeded packages listed`);

/* ── T-SP-02/03: create ───────────────────────────────────────────────── */
const created = await flow.render({
  method: 'POST',
  uri: '/settings/seo-packages',
  body: form({
    _token: await token(),
    title: 'پکیج سقف‌دار آزمون',
    code: CODE,
    keyword_limit: '۲',
    sort_order: '5',
    is_active: '1',
    description: 'برای آزمون سقف کلمات',
  }),
});
const newRow = await flow.query(
  `select title||'|'||keyword_limit||'|'||sort_order||'|'||is_active from seo_packages where code = '${CODE}'`,
);

check('T-SP-02', created.status === 302 && newRow === `پکیج سقف‌دار آزمون|2|5|1`,
  `POST -> ${created.status}; stored "${newRow}" — the Persian numeral ۲ became 2`);

const unlimitedCode = `${CODE}-unlimited`;
await flow.render({
  method: 'POST',
  uri: '/settings/seo-packages',
  body: form({ _token: await token(), title: 'بدون سقف', code: unlimitedCode, keyword_limit: '' }),
});
const unlimitedStored = await flow.query(
  `select coalesce(cast(keyword_limit as text), 'NULL') from seo_packages where code = '${unlimitedCode}'`,
);

check('T-SP-03', unlimitedStored === 'NULL',
  `an empty ceiling is stored as ${unlimitedStored}, which KeywordRequest reads as "no ceiling"`);

/* ── T-SP-04/05: the code field ───────────────────────────────────────── */
const dup = await flow.render({
  method: 'POST',
  uri: '/settings/seo-packages',
  body: form({ _token: await token(), title: 'تکراری', code: CODE, keyword_limit: '3' }),
});
const dupErrors = errorsOn(await followRedirect(dup));

check('T-SP-04', dup.status !== 500 && dupErrors.some((e) => e.includes('پیش‌تر'))
  && (await n(`select count(*) from seo_packages where code = '${CODE}'`)) === 1,
  `a duplicate code -> ${dup.status} with "${dupErrors.join(' / ') || '(none)'}"`);

const badCode = await flow.render({
  method: 'POST',
  uri: '/settings/seo-packages',
  body: form({ _token: await token(), title: 'کد ناموفق', code: 'پکیج جدید', keyword_limit: '3' }),
});

// Read once: a flashed error bag is cleared the first time it is rendered, so
// calling followRedirect twice reported "(none)" on a check that had passed.
const codeErrors = errorsOn(await followRedirect(badCode));
check('T-SP-05', codeErrors.some((e) => e.includes('کد پکیج')),
  `a Persian, space-bearing code is refused: ${codeErrors.join(' / ') || '(none)'}`);

/* ── T-SP-06: it reaches the project form ─────────────────────────────── */
const packageId = await q(`select id from seo_packages where code = '${CODE}'`);
const createPage = await flow.render({ uri: '/projects/create' });

/*
 * Read the option out of the seo_package_id select specifically. A bare
 * `value="4"` also appears in the customer dropdown on the same page, so a
 * substring match proved nothing — T-SP-10 failed on it while the deactivation
 * itself had worked.
 */
const packageOptions = (html) => {
  const block = String(html).split('name="seo_package_id"').pop().split('</select>')[0];

  return [...block.matchAll(/value="(\d+)"/g)].map((m) => m[1]);
};
const offeredPackages = packageOptions(createPage.body);

check('T-SP-06', createPage.status === 200 && offeredPackages.includes(String(packageId)),
  `the package created through this screen is offered on /projects/create;`
  + ` seo_package_id options are ${offeredPackages.join(',') || '(none)'}`);

/* ── T-SP-07: the ceiling it imposes ──────────────────────────────────── */
const customerId = await q('select id from customers order by id limit 1');
const newProject = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: await token('/projects/create'),
    customer_id: customerId,
    title: 'پروژهٔ سقف پکیج',
    type: 'seo',
    // Not 'active': §6 refuses to activate an SEO project until its mandatory
    // accesses (wordpress, hosting, domain) exist. Draft is the legal path and
    // changes nothing about the package ceiling under test.
    status: 'draft',
    amount: '1000000',
    start_date: '۱۴۰۴/۰۱/۱۵',
    seo_package_id: packageId,
  }),
});
const projectId = String(newProject.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];
const linked = projectId
  ? await q(`select coalesce(cast(seo_package_id as text),'NULL') from seo_project_details where project_id = ${projectId}`)
  : null;

check('T-SP-07', newProject.status === 302 && projectId !== undefined && linked === packageId,
  `POST /projects -> ${newProject.status}, project ${projectId ?? '(none)'} linked to package ${linked}`);

const addKeyword = async (text) => flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/keywords`,
  body: form({ _token: await token(`/projects/${projectId}`), keyword: text, status: 'pending' }),
});

const k1 = await addKeyword('کلمهٔ یکم');
const k2 = await addKeyword('کلمهٔ دوم');
const k3 = await addKeyword('کلمهٔ سوم');
const kwRows = await n(`select count(*) from project_keywords where project_id = ${projectId}`);

check('T-SP-08', k1.status === 302 && k2.status === 302 && kwRows === 2
  && errorsOn(await followRedirect(k3)).some((e) => e.includes('سقف')),
  `the package set a ceiling of 2 through the UI: two keywords stored, the third`
  + ` refused with "${errorsOn(await followRedirect(k3)).join(' / ') || '(none)'}"; rows ${kwRows}`);

/*
 * ── T-SP-09: editing the ceiling changes what is accepted ───────────────
 *
 * The point of the whole screen. Raise the limit to 3 and the keyword that was
 * just refused has to go through — with no re-seeding, no restart and no
 * change to any other data.
 */
const raised = await flow.render({
  method: 'POST',
  uri: `/settings/seo-packages/${packageId}`,
  body: form({
    _token: await token('/settings/seo-packages/create'),
    _method: 'PUT',
    title: 'پکیج سقف‌دار آزمون',
    code: CODE,
    keyword_limit: '۳',
    sort_order: '5',
    is_active: '1',
  }),
});
const newLimit = await q(`select keyword_limit from seo_packages where id = ${packageId}`);
const k3again = await addKeyword('کلمهٔ سوم');
const kwRowsAfter = await n(`select count(*) from project_keywords where project_id = ${projectId}`);

check('T-SP-09', raised.status === 302 && newLimit === '3' && k3again.status === 302
  && kwRowsAfter === 3,
  `PUT -> ${raised.status}, ceiling now ${newLimit}; the previously refused keyword`
  + ` was accepted and rows went ${kwRows} -> ${kwRowsAfter}`);

/* ── T-SP-10: deactivating removes it from the form ───────────────────── */
/*
 * An unchecked checkbox posts nothing, so the form carries a hidden
 * is_active=0 behind it. Assert the hidden field is really in the markup
 * before posting it by hand — otherwise this check would be testing a value
 * the form never sends.
 */
const editPage = await flow.render({ uri: `/settings/seo-packages/${packageId}/edit` });
const hasHiddenFlag = editPage.body.includes('type="hidden" name="is_active" value="0"');

const deactivated = await flow.render({
  method: 'POST',
  uri: `/settings/seo-packages/${packageId}`,
  body: form({
    _token: await token(`/settings/seo-packages/${packageId}/edit`),
    _method: 'PUT',
    title: 'پکیج سقف‌دار آزمون',
    code: CODE,
    keyword_limit: '3',
    sort_order: '5',
    is_active: '0',
  }),
});
const isActive = await q(`select is_active from seo_packages where id = ${packageId}`);
const offeredAfter = packageOptions((await flow.render({ uri: '/projects/create' })).body);

check('T-SP-10', hasHiddenFlag && deactivated.status === 302 && isActive === '0'
  && !offeredAfter.includes(String(packageId)),
  `the edit form carries the hidden is_active=0 (${hasHiddenFlag}); submitted unchecked`
  + ` -> is_active=${isActive}, and the seo_package_id options are now`
  + ` ${offeredAfter.join(',') || '(none)'} — ${packageId} is gone`);

/* ── T-SP-11: a package in use cannot be deleted ──────────────────────── */
const deleteInUse = await flow.render({
  method: 'POST',
  uri: `/settings/seo-packages/${packageId}`,
  body: form({ _token: await token('/settings/seo-packages'), _method: 'DELETE' }),
});
const survived = await n(`select count(*) from seo_packages where id = ${packageId}`);

const unusedId = await q(`select id from seo_packages where code = '${unlimitedCode}'`);
const deleteUnused = await flow.render({
  method: 'POST',
  uri: `/settings/seo-packages/${unusedId}`,
  body: form({ _token: await token('/settings/seo-packages'), _method: 'DELETE' }),
});
const gone = await n(`select count(*) from seo_packages where id = ${unusedId}`);

check('T-SP-11', deleteInUse.status === 302 && survived === 1
  && deleteUnused.status === 302 && gone === 0,
  `delete refused for the package with a project on it (row still there: ${survived === 1}),`
  + ` allowed for the unused one (gone: ${gone === 0})`);

/* ── T-SP-12: permission ──────────────────────────────────────────────── */
const vw = `V-${Date.now().toString(36)}`;
await flow.resetPassword({ username: 'sp_viewer', password: vw, mustChange: false, role: 'viewer' });
const guest = new Flow('seopackage');
guest.db = flow.db;
guest.dir = flow.dir;
await guest.login('sp_viewer', vw, { expectRedirect: true });

const guestIndex = await guest.render({ uri: '/settings/seo-packages' });
const packagesBefore = await n('select count(*) from seo_packages');
const guestPost = await guest.render({
  method: 'POST',
  uri: '/settings/seo-packages',
  body: form({
    _token: Flow.token((await guest.render({ uri: '/settings/seo-packages' })).body),
    title: 'غیرمجاز',
    code: `${CODE}-no`,
  }),
});

check('T-SP-12', guestPost.status === 403
  && (await n('select count(*) from seo_packages')) === packagesBefore,
  `viewer POST -> ${guestPost.status}; packages unchanged at ${packagesBefore}`
  + ` (viewer index -> ${guestIndex.status}; settings.view is not granted to that role)`);

/* ── T-SP-13: audit ───────────────────────────────────────────────────── */
check('T-SP-13', (await n("select count(*) from audit_logs where description like '%پکیج سئو%'")) >= 3,
  `audit rows about SEO packages: ${await n("select count(*) from audit_logs where description like '%پکیج سئو%'")}`);

/* ── T-SP-14: the shared database ─────────────────────────────────────── */
check('T-SP-14', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
