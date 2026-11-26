#!/usr/bin/env node
/**
 * VIRA CRM — target keywords (§5-1, REQ-05.03).
 *
 * «فهرست کلمات کلیدی هدف و تعداد کلمات» and, in the catalogue, the explicit
 * condition: «تعداد کلمات از داده واقعی محاسبه شود، نه دستی».
 *
 * THE GAP
 * -------
 * The project page already printed the count with `$project->keywords->count()`
 * — computed from rows, correctly — but nothing in the product could create a
 * row, so the count was permanently zero and the requirement was being met by
 * an empty set. The project form separately offered a typed
 * «تعداد کلمات کلیدی» number, stored in seo_project_details.keyword_count, which
 * is exactly the manual count the requirement rules out.
 *
 *   T-KW-00  the fixture is what the assertions assume
 *   T-KW-01  the form appears on an SEO project and not on a design project
 *   T-KW-02  a keyword is stored, with its normalized form alongside
 *   T-KW-03  the count on the page rises with the rows
 *   T-KW-04  the typed keyword_count column has no effect on the count shown —
 *            this is the clause the requirement actually states
 *   T-KW-05  the same text twice is refused
 *   T-KW-06  text that normalizes to the same keyword is refused: ZWNJ, a
 *            double space, and Arabic ي for Persian ی all compare equal
 *   T-KW-07  a keyword moves through its statuses
 *   T-KW-08  a status outside ProjectKeyword::STATUSES is refused
 *   T-KW-09  the package's keyword_limit is enforced against real rows
 *   T-KW-10  a keyword reached through another project's URL is a 404
 *   T-KW-11  a user without projects.update cannot add one
 *   T-KW-12  deleting drops the row and the count falls with it
 *   T-KW-13  every mutation is audited
 *   T-KW-14  the shared development database is byte-identical afterwards
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('project-keyword');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('keyword');
await flow.setup();

const PASSWORD = `Kw-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const n = async (sql) => Number(await q(sql)) || 0;
const token = async (uri = '/projects/1') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();
const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());
const followRedirect = async (res) => {
  const loc = String(res.headers['location'] || '/projects/1').replace(/^https?:\/\/[^/]+/, '');

  return (await flow.render({ uri: loc })).body;
};

/**
 * The count as the page reports it, parsed out of the keywords card header.
 * Read from the page rather than the table, because the requirement is about
 * what the product shows.
 */
const shownCount = async (projectId) => {
  const html = (await flow.render({ uri: `/projects/${projectId}` })).body;
  const block = html.split('id="keywords"').pop().split('</span>')[0];
  const m = block.match(/v-card__meta[^>]*>\s*([۰-۹0-9]+)/);

  return m ? Number(m[1].replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))) : null;
};

const addKeyword = async (projectId, fields) => flow.render({
  method: 'POST',
  uri: `/projects/${projectId}/keywords`,
  body: form({ _token: await token(`/projects/${projectId}`), status: 'pending', ...fields }),
});

/* ── fixtures ─────────────────────────────────────────────────────────── */
const customerId = await q('select id from customers order by id limit 1');

// A package with a limit of 2, and an SEO project on it, for T-KW-09.
await flow.exec(
  `insert into seo_packages (id, code, title, keyword_limit, is_active, sort_order,`
  + ` created_at, updated_at) values (9401, 'KW-LIMIT', 'پکیج سقف‌دار', 2, 1, 99,`
  + ` datetime('now'), datetime('now'))`,
);
await flow.exec(
  `insert into projects (id, customer_id, type, title, status, amount, start_date,`
  + ` created_at, updated_at) values (9401, ${customerId}, 'seo', 'پروژهٔ سقف کلمات',`
  + ` 'active', 1000000, date('now'), datetime('now'), datetime('now'))`,
);
await flow.exec(
  `insert into seo_project_details (project_id, seo_package_id, keyword_count, recurrence,`
  + ` created_at, updated_at) values (9401, 9401, 0, 'monthly', datetime('now'), datetime('now'))`,
);

check('T-KW-00', (await n('select count(*) from seo_packages where id = 9401')) === 1
  && (await n('select count(*) from projects where id = 9401')) === 1
  && (await n('select count(*) from project_keywords')) === 0,
  `fixture: capped package and its SEO project in place, keywords table empty`);

/* ── T-KW-01: the form, and only where it belongs ─────────────────────── */
const seoPage = await flow.render({ uri: '/projects/1' });
const designPage = await flow.render({ uri: '/projects/2' });

check('T-KW-01', seoPage.status === 200 && seoPage.body.includes('id="keywords"')
  && seoPage.body.includes('keyword_text') && !designPage.body.includes('keyword_text'),
  `SEO project shows the keyword form: ${seoPage.body.includes('keyword_text')};`
  + ` design project does not: ${!designPage.body.includes('keyword_text')}`);

/* ── T-KW-02/03: add, and the count follows ───────────────────────────── */
const countBefore = await shownCount(1);

/*
 * Seeded WITH a ZWNJ (U+200C) on purpose. «طراحی‌سایت» and «طراحی سایت» look
 * identical on screen but are different strings, so the stored `normalized`
 * column can only match the plain-space form if normalize() actually ran.
 * An earlier fixture used the plain-space spelling, which normalizes to
 * itself — a store() that skipped normalization passed every check.
 */
const BASE = 'طراحی‌سایت';
const added = await addKeyword(1, { keyword: BASE });
const stored = await flow.query("select keyword||'|'||normalized||'|'||status from project_keywords");

check('T-KW-02', added.status === 302 && stored === 'طراحی‌سایت|طراحی سایت|pending',
  `POST /projects/1/keywords -> ${added.status}; stored "${stored}" — the raw text`
  + ` is kept and the folded form is what gets compared`);

const countAfterOne = await shownCount(1);
check('T-KW-03', countBefore === 0 && countAfterOne === 1,
  `the count on the page went ${countBefore} -> ${countAfterOne} with the rows`);

/*
 * ── T-KW-04: the typed count is not the count ───────────────────────────
 *
 * seo_project_details.keyword_count is a number someone types. Setting it to
 * 99 must not change what the page reports. This is the clause the requirement
 * states outright, and it is the one a manual-count implementation fails.
 */
await flow.exec(
  `insert into seo_project_details (project_id, seo_package_id, keyword_count, recurrence,`
  + ` created_at, updated_at) values (1, 1, 99, 'monthly', datetime('now'), datetime('now'))`,
);
const countWithTyped99 = await shownCount(1);

check('T-KW-04', countWithTyped99 === 1,
  `seo_project_details.keyword_count set to 99, the page still reports`
  + ` ${countWithTyped99} — the count comes from project_keywords, not from the typed field`);

/* ── T-KW-05/06: duplicates, including after normalization ────────────── */
const dup = await addKeyword(1, { keyword: BASE });
const dupErrors = errorsOn(await followRedirect(dup));

check('T-KW-05', dupErrors.some((e) => e.includes('پیش‌تر'))
  && (await n('select count(*) from project_keywords')) === 1,
  `the identical text is refused: ${dupErrors.join(' / ') || '(none)'}`);

/*
 * Three spellings that look different and are the same keyword: a ZWNJ where
 * the space was, two spaces, and the Arabic ي for the Persian ی.
 */
const variants = ['طراحی سایت', 'طراحی  سایت', 'طراحی سايت'];
const variantResults = [];
for (const variant of variants) {
  const res = await addKeyword(1, { keyword: variant });
  variantResults.push(errorsOn(await followRedirect(res)).some((e) => e.includes('پیش‌تر')));
}
const rowsAfterVariants = await n('select count(*) from project_keywords');

check('T-KW-06', variantResults.every(Boolean) && rowsAfterVariants === 1,
  `plain space, double space and Arabic ي were all refused (${variantResults.join(', ')});`
  + ` the table still holds ${rowsAfterVariants} row`);

/* ── T-KW-07/08: status ───────────────────────────────────────────────── */
const keywordId = await q('select id from project_keywords limit 1');
const statusChange = await flow.render({
  method: 'POST',
  uri: `/projects/1/keywords/${keywordId}`,
  body: form({ _token: await token(), _method: 'PUT', keyword: BASE, status: 'working' }),
});

check('T-KW-07', statusChange.status === 302
  && (await q(`select status from project_keywords where id = ${keywordId}`)) === 'working',
  `PUT -> ${statusChange.status}; status is now "${await q(`select status from project_keywords where id = ${keywordId}`)}"`);

const badStatus = await flow.render({
  method: 'POST',
  uri: `/projects/1/keywords/${keywordId}`,
  body: form({ _token: await token(), _method: 'PUT', keyword: BASE, status: 'not-a-status' }),
});

check('T-KW-08', (await q(`select status from project_keywords where id = ${keywordId}`)) === 'working'
  && errorsOn(await followRedirect(badStatus)).length > 0,
  `status "not-a-status" refused; stored status unchanged: "${await q(`select status from project_keywords where id = ${keywordId}`)}"`);

/* ── T-KW-09: the package limit ───────────────────────────────────────── */
const first = await addKeyword(9401, { keyword: 'کلمهٔ اول' });
const second = await addKeyword(9401, { keyword: 'کلمهٔ دوم' });
const third = await addKeyword(9401, { keyword: 'کلمهٔ سوم' });
const cappedRows = await n('select count(*) from project_keywords where project_id = 9401');

check('T-KW-09', first.status === 302 && second.status === 302 && cappedRows === 2
  && errorsOn(await followRedirect(third)).some((e) => e.includes('سقف')),
  `package keyword_limit is 2: two accepted, the third refused with`
  + ` "${errorsOn(await followRedirect(third)).join(' / ') || '(none)'}"; rows ${cappedRows}`);

/* ── T-KW-10: IDOR ────────────────────────────────────────────────────── */
const idor = await flow.render({
  method: 'POST',
  uri: `/projects/2/keywords/${keywordId}`,
  body: form({ _token: await token('/projects/2'), _method: 'DELETE' }),
});

check('T-KW-10', idor.status === 404
  && (await n(`select count(*) from project_keywords where id = ${keywordId}`)) === 1,
  `deleting project 1's keyword through /projects/2 -> ${idor.status}; row survived`);

/* ── T-KW-11: permission ──────────────────────────────────────────────── */
const vw = `V-${Date.now().toString(36)}`;
await flow.resetPassword({ username: 'kw_viewer', password: vw, mustChange: false, role: 'viewer' });
const guest = new Flow('keyword');
guest.db = flow.db;
guest.dir = flow.dir;
await guest.login('kw_viewer', vw, { expectRedirect: true });
const rowsBeforeViewer = await n('select count(*) from project_keywords');
const refused = await guest.render({
  method: 'POST',
  uri: '/projects/1/keywords',
  body: form({
    _token: Flow.token((await guest.render({ uri: '/projects/1' })).body),
    keyword: 'کلمهٔ غیرمجاز',
    status: 'pending',
  }),
});

check('T-KW-11', refused.status === 403
  && (await n('select count(*) from project_keywords')) === rowsBeforeViewer,
  `viewer POST -> ${refused.status}; rows unchanged at ${rowsBeforeViewer}`);

/* ── T-KW-12: delete, and the count falls ─────────────────────────────── */
const countBeforeDelete = await shownCount(9401);
const cappedId = await q('select id from project_keywords where project_id = 9401 order by id limit 1');
const deleted = await flow.render({
  method: 'POST',
  uri: `/projects/9401/keywords/${cappedId}`,
  body: form({ _token: await token('/projects/9401'), _method: 'DELETE' }),
});

check('T-KW-12', deleted.status === 302
  && (await n(`select count(*) from project_keywords where id = ${cappedId}`)) === 0
  && (await shownCount(9401)) === countBeforeDelete - 1,
  `delete -> ${deleted.status}; the page count fell ${countBeforeDelete} -> ${await shownCount(9401)}`
  + ' because it is computed, and a slot on the capped package is free again');

/* ── T-KW-13: audit ───────────────────────────────────────────────────── */
check('T-KW-13', (await n("select count(*) from audit_logs where description like '%کلمهٔ کلیدی%'")) >= 3,
  `audit rows about keywords: ${await n("select count(*) from audit_logs where description like '%کلمهٔ کلیدی%'")}`);

/* ── T-KW-14: the shared database ─────────────────────────────────────── */
check('T-KW-14', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
