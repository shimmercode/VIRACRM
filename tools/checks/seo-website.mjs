#!/usr/bin/env node
/**
 * VIRA CRM — the SEO project's website address (REQ-05.02, §5-1).
 *
 * «اطلاعات هر پروژه سئو باید حداقل شامل موارد زیر باشد: نام پروژه، نام مشتری و
 * آدرس وب‌سایت.»
 *
 * The project name and the customer were there. The website address was not: the
 * only domain column in the schema was design_project_details.domain, which
 * belongs to the design fieldset, so an SEO project had nowhere to put it.
 *
 * This suite covers the four cases the field has to get right, and the one
 * mistake it would be easiest to make:
 *
 *   T-WU-01  the field is in the SEO fieldset and not the design one
 *   T-WU-02  a bare domain is accepted — the common way to write it here, and
 *            the form Laravel's own `url` rule would reject
 *   T-WU-03  a full URL with port, path, query and fragment is accepted
 *   T-WU-04  a Persian IDN written in Arabic script is accepted
 *   T-WU-05  an address containing a space is refused and nothing is stored
 *   T-WU-06  two schemes pasted together are refused
 *   T-WU-07  leaving it empty is allowed; the column is nullable
 *   T-WU-08  an EXISTING record created before the field existed still loads and
 *            still saves, with the address still empty
 *   T-WU-09  editing replaces the address and the old one is gone
 *   T-WU-10  the value lands in seo_project_details.website_url and NOT in
 *            design_project_details.domain
 *   T-WU-11  the project page renders it
 *   T-WU-12  a design project's domain is untouched by all of this
 *   T-WU-13  financial notes — §5-1 «توضیحات مالی مرتبط», which is not the same
 *            field as «توضیحات داخلی» — store and render separately from it
 *   T-WU-14  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('seo-website');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('seowebsite');
await flow.setup();

const PASSWORD = `Wu-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/projects/create') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());

/** Follow a redirect once so flashed field errors can be read. */
const after = async (res, fallback = '/projects/create') => {
  const loc = String(res.headers['location'] || fallback).replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

const customerId = await q('select id from customers order by id limit 1');

/**
 * Create a project through the real form. Returns { id, status, errors }.
 * `status: 'draft'` because §6 refuses to activate an SEO project until its
 * mandatory accesses exist — irrelevant to this field, and it would otherwise
 * be the only error on the page.
 */
const createProject = async (fields) => {
  const res = await flow.render({
    method: 'POST',
    uri: '/projects',
    body: form({
      _token: await token('/projects/create'),
      customer_id: customerId,
      title: 'پروژهٔ آدرس وب‌سایت',
      type: 'seo',
      status: 'draft',
      amount: '1000000',
      start_date: '۱۴۰۴/۰۱/۱۵',
      ...fields,
    }),
  });
  const id = String(res.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];

  return { id, status: res.status, errors: id ? [] : errorsOn(await after(res)) };
};

const storedUrl = (id) => flow.query(`select coalesce(website_url,'NULL') from seo_project_details where project_id = ${id}`);

/* ── T-WU-01: the field is in the right fieldset ──────────────────────── */
const createPage = await flow.render({ uri: '/projects/create' });
const seoBlock = createPage.body.split('data-type-section="seo"').pop().split('data-type-section="design"')[0];
const designBlock = createPage.body.split('data-type-section="design"').pop();

check('T-WU-01', createPage.status === 200 && seoBlock.includes('name="website_url"')
  && !designBlock.includes('name="website_url"') && designBlock.includes('name="domain"'),
  `website_url is in the SEO fieldset: ${seoBlock.includes('name="website_url"')}; `
  + `absent from the design fieldset, which keeps its own domain field: ${designBlock.includes('name="domain"')}`);

/* ── T-WU-02: a bare domain ───────────────────────────────────────────── */
const bare = await createProject({ website_url: 'example.ir' });
check('T-WU-02', bare.id !== undefined && (await storedUrl(bare.id)) === 'example.ir',
  `POST /projects with website_url=example.ir -> ${bare.status}, project ${bare.id},`
  + ` stored "${await storedUrl(bare.id)}" — no scheme required`);

/* ── T-WU-03: a full URL ──────────────────────────────────────────────── */
const full = 'https://shop.example.ir:8443/category/books?page=2#top';
const fullProject = await createProject({ website_url: full });
check('T-WU-03', fullProject.id !== undefined && (await storedUrl(fullProject.id)) === full,
  `a URL with port, path, query and fragment is stored as typed: "${await storedUrl(fullProject.id)}"`);

/* ── T-WU-04: a Persian IDN ───────────────────────────────────────────── */
const idn = 'https://مثال.ایران/بلاگ';
const idnProject = await createProject({ website_url: idn });
check('T-WU-04', idnProject.id !== undefined && (await storedUrl(idnProject.id)) === idn,
  `a Persian IDN in Arabic script is accepted: "${await storedUrl(idnProject.id)}"`);

/* ── T-WU-05: a space ─────────────────────────────────────────────────── */
const projectsBefore = await n('select count(*) from projects');
const spaced = await createProject({ website_url: 'example .ir' });
check('T-WU-05', spaced.id === undefined
  && spaced.errors.some((e) => e.includes('فاصله'))
  && (await n('select count(*) from projects')) === projectsBefore,
  `"example .ir" refused: ${spaced.errors.join(' / ') || '(none)'}; projects stayed at`
  + ` ${await n('select count(*) from projects')} — no partial row`);

/* ── T-WU-06: two schemes ─────────────────────────────────────────────── */
const doubled = await createProject({ website_url: 'https://https://example.ir' });
check('T-WU-06', doubled.id === undefined
  && doubled.errors.some((e) => e.includes('معتبر نیست')),
  `"https://https://example.ir" refused: ${doubled.errors.join(' / ') || '(none)'}`);

/* ── T-WU-07: empty is allowed ────────────────────────────────────────── */
const emptyProject = await createProject({ website_url: '' });
check('T-WU-07', emptyProject.id !== undefined && (await storedUrl(emptyProject.id)) === 'NULL',
  `an empty address still creates the project (${emptyProject.id}) and stores NULL,`
  + ` not an empty string`);

/*
 * ── T-WU-08: a record that predates the field ───────────────────────────
 *
 * The shared database was never migrated, so this is the shape every existing
 * SEO project has in production until the migration runs there: a
 * seo_project_details row with no address. It must still open and still save.
 */
const legacyId = 9601;
await flow.exec(
  `insert into projects (id, customer_id, type, title, status, amount, start_date,`
  + ` created_at, updated_at) values (${legacyId}, ${customerId}, 'seo', 'پروژهٔ قدیمی',`
  + ` 'draft', 500000, date('now'), datetime('now'), datetime('now'))`,
);
await flow.exec(
  `insert into seo_project_details (project_id, keyword_count, recurrence,`
  + ` reminder_offset_days, created_at, updated_at)`
  + ` values (${legacyId}, 7, 'monthly', 2, datetime('now'), datetime('now'))`,
);

const legacyEdit = await flow.render({ uri: `/projects/${legacyId}/edit` });
/*
 * Submitted the way the edit form submits, with every field the form renders.
 * A hand-built body that omits `keyword_count` would reset it to 0, because the
 * request reads `(int) ($this->input('keyword_count') ?? 0)` — that is
 * pre-existing behaviour of the form endpoint and not what this check is about,
 * but it is worth knowing: this endpoint replaces the SEO detail row rather
 * than patching it.
 */
const legacySave = await flow.render({
  method: 'POST',
  uri: `/projects/${legacyId}`,
  body: form({
    _token: await token(`/projects/${legacyId}/edit`),
    _method: 'PUT',
    customer_id: customerId,
    title: 'پروژهٔ قدیمی',
    type: 'seo',
    status: 'draft',
    amount: '500000',
    keyword_count: '7',
    recurrence: 'monthly',
    website_url: '',
  }),
});

check('T-WU-08', legacyEdit.status === 200 && legacySave.status === 302
  && (await q(`select keyword_count from seo_project_details where project_id = ${legacyId}`)) === '7',
  `a project recorded before the field existed still opens (${legacyEdit.status}) and still`
  + ` saves (${legacySave.status}); its keyword_count is intact at`
  + ` ${await q(`select keyword_count from seo_project_details where project_id = ${legacyId}`)}`);

/* ── T-WU-09: editing replaces the address ────────────────────────────── */
const beforeEdit = await storedUrl(bare.id);
const edited = await flow.render({
  method: 'POST',
  uri: `/projects/${bare.id}`,
  body: form({
    _token: await token(`/projects/${bare.id}/edit`),
    _method: 'PUT',
    customer_id: customerId,
    title: 'پروژهٔ آدرس وب‌سایت',
    type: 'seo',
    status: 'draft',
    amount: '1000000',
    website_url: 'https://new-address.ir',
  }),
});

check('T-WU-09', edited.status === 302 && beforeEdit === 'example.ir'
  && (await storedUrl(bare.id)) === 'https://new-address.ir',
  `the address went from "${beforeEdit}" to "${await storedUrl(bare.id)}" on edit`);

/* ── T-WU-10: the right column, not the design one ────────────────────── */
const designDomainRows = await n(
  `select count(*) from design_project_details where domain is not null and domain != ''`,
);
const seoRowsWithUrl = await n(
  `select count(*) from seo_project_details where website_url is not null`,
);

check('T-WU-10', seoRowsWithUrl >= 3 && designDomainRows === 0,
  `${seoRowsWithUrl} seo_project_details rows carry an address while`
  + ` ${designDomainRows} design_project_details rows carry a domain — the SEO address`
  + ` did not leak into the design table`);

/* ── T-WU-11: the project page renders it ─────────────────────────────── */
const shown = await flow.render({ uri: `/projects/${bare.id}` });
check('T-WU-11', shown.status === 200 && shown.body.includes('https://new-address.ir'),
  `the project page shows the stored address: ${shown.body.includes('https://new-address.ir')}`);

/* ── T-WU-12: design projects are unaffected ──────────────────────────── */
const designProject = await flow.render({
  method: 'POST',
  uri: '/projects',
  body: form({
    _token: await token('/projects/create'),
    customer_id: customerId,
    title: 'پروژهٔ طراحی',
    type: 'design',
    status: 'draft',
    amount: '1000000',
    domain: 'design-example.ir',
    site_type: 'فروشگاهی',
  }),
});
const designId = String(designProject.headers['location'] || '').match(/\/projects\/(\d+)/)?.[1];
const designDomain = designId
  ? await flow.query(`select coalesce(domain,'NULL') from design_project_details where project_id = ${designId}`)
  : 'NULL';

check('T-WU-12', designProject.status === 302 && designDomain === 'design-example.ir',
  `a design project still stores its domain in design_project_details: "${designDomain}"`);

/*
 * ── T-WU-13: financial notes are their own field ────────────────────────
 *
 * §5-1 names «مبلغ قرارداد و توضیحات مالی مرتبط» in one bullet and «توضیحات
 * داخلی» in another. Only `projects.notes` existed, labelled یادداشت داخلی, so
 * the financial note had nowhere to go and the two would have been merged.
 */
const fnProject = await createProject({ website_url: 'finance.example.ir' });
await flow.render({
  method: 'POST',
  uri: `/projects/${fnProject.id}`,
  body: form({
    _token: await token(`/projects/${fnProject.id}/edit`),
    _method: 'PUT',
    customer_id: customerId,
    title: 'پروژهٔ آدرس وب‌سایت',
    type: 'seo',
    status: 'draft',
    amount: '1000000',
    website_url: 'finance.example.ir',
    financial_notes: 'قسط اول ۵۰٪ پیش‌پرداخت، باقی در پایان ماه سوم.',
    notes: 'یادداشت داخلی دربارهٔ روند کار',
  }),
});

const financialStored = String(await flow.query(
  `select coalesce(financial_notes,'NULL') from seo_project_details where project_id = ${fnProject.id}`,
));
const internalStored = String(await flow.query(
  `select coalesce(notes,'NULL') from projects where id = ${fnProject.id}`,
));
const fnPage = await flow.render({ uri: `/projects/${fnProject.id}` });

check('T-WU-13', financialStored.includes('پیش‌پرداخت')
  && internalStored === 'یادداشت داخلی دربارهٔ روند کار'
  && financialStored !== internalStored
  && fnPage.body.includes('توضیحات مالی'),
  `the financial note and the internal note are stored in different places and both`
  + ` render: financial="${financialStored.slice(0, 30)}…", internal="${internalStored}"`);

/* ── T-WU-14: the shared database ─────────────────────────────────────── */
check('T-WU-14', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
