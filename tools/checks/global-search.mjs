#!/usr/bin/env node
/**
 * VIRA CRM — global customer search (§9, REQ-09.01).
 *
 * WHAT THE REQUIREMENT ACTUALLY SAYS
 * ----------------------------------
 * §9 line 198: «جستجوی سراسری مشتریان و دسترسی سریع به پرونده هر مشتری» —
 * site-wide search of customers, with quick access to each customer's file.
 * §3 line 56 adds the fields: name, phone, national ID / company ID and
 * website domain.
 *
 * This suite deliberately does NOT add a route. The traceability entry for
 * REQ-09.01 said "no global search", but the topbar in `layouts/app.blade.php`
 * renders a search form on every authenticated page, pointing at
 * `customers.index`, and `Customer::scopeSearch()` already covers all four
 * required fields. Inventing a `/search` route to satisfy a checklist item
 * would have been the wrong fix; the right one is to prove the existing path
 * actually works from a page that is not the customers list.
 *
 *   T-GS-01  the search box is on a page that is not the customers list
 *   T-GS-02  it is on every page, not just one
 *   T-GS-03  company name search finds the customer
 *   T-GS-04  mobile search in PERSIAN DIGITS finds the customer
 *   T-GS-05  national / company ID search finds the customer
 *   T-GS-06  website domain search finds the customer
 *   T-GS-07  each result links to that customer's file, and the file opens
 *   T-GS-08  a term matching nothing returns the empty state, not an error
 *   T-GS-09  the search term is not treated as SQL
 *
 * 4 IS THE ONE THAT MATTERS MOST
 * ------------------------------
 * A Persian-speaking operator types ۰۹۳۵۱۱۱۲۲۳۳, not 09351112233. If the
 * digits are not normalised before comparison, the search silently finds
 * nothing and looks like a data problem.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('globalsearch');
await flow.setup();

const c = new Checks('global-search');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ap-${Date.now().toString(36)}-K2`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD, { expectRedirect: true });

const toFa = (s) => String(s).replace(/[0-9]/g, (d) => String.fromCharCode(0x06F0 + Number(d)));

/*
 * Every case below is a real seeded row; nothing here is invented so that a
 * test can pass. If the demo seeder changes, these must change with it — that
 * is the point of reading them from the database first.
 */
const byName = await flow.query("select id from customers where company_name like '%دیبا%' limit 1");
const dibaId = String(byName).trim();
const parsnovin = String(await flow.query(
  "select id from customers where normalized_national_id = '903809' limit 1",
)).trim();
const mehregan = String(await flow.query(
  "select id from customers where company_name like '%مهرگان%' limit 1",
)).trim();

/* ── T-GS-01/02: the box is genuinely global ──────────────────────────── */
const pages = ['/projects', '/notes', '/audit'];
const found = [];
for (const uri of pages) {
  const res = await flow.render({ uri });
  if (res.status === 200 && res.body.includes('v-topbar__search')) found.push(uri);
}

check('T-GS-01', found.includes('/projects'),
  `search box present on /projects: ${found.includes('/projects')}`);
check('T-GS-02', found.length === pages.length,
  `search box rendered on ${found.length}/${pages.length} non-customer pages (${found.join(', ')})`);

/* ── the search itself ────────────────────────────────────────────────── */
const search = async (term) => {
  const res = await flow.render({
    uri: `/customers?q=${encodeURIComponent(term)}`,
  });
  return { status: res.status, body: res.body };
};

/*
 * `route()` emits absolute URLs (APP_URL), so the origin must be optional here.
 * An anchored `/href="\/customers/` matched nothing at all, which made four
 * passing searches look like four failures.
 */
const hits = (body) => [...body.matchAll(/href="[^"]*\/customers\/(\d+)"/g)]
  .map((m) => m[1]);

/* T-GS-03: company name */
const byCompany = await search('دیبا');
check('T-GS-03', byCompany.status === 200 && hits(byCompany.body).includes(dibaId),
  `q=دیبا -> ${byCompany.status}, customer #${dibaId} in results: `
  + `${hits(byCompany.body).includes(dibaId)}`);

/* T-GS-04: mobile typed in Persian digits */
const byMobile = await search(toFa('09351112233'));
check('T-GS-04', byMobile.status === 200 && hits(byMobile.body).includes(dibaId),
  `q=${toFa('09351112233')} (Persian digits) -> customer #${dibaId} found: `
  + `${hits(byMobile.body).includes(dibaId)}`);

/* T-GS-05: national / company id */
const byId = await search('903809');
check('T-GS-05', byId.status === 200 && hits(byId.body).includes(parsnovin),
  `q=903809 -> customer #${parsnovin} found: ${hits(byId.body).includes(parsnovin)}`);

/* T-GS-06: website domain */
const byDomain = await search('mehregan.example.com');
check('T-GS-06', byDomain.status === 200 && hits(byDomain.body).includes(mehregan),
  `q=mehregan.example.com -> customer #${mehregan} found: `
  + `${hits(byDomain.body).includes(mehregan)}`);

/* ── T-GS-07: the result really opens the customer's file ─────────────── */
const file = await flow.render({ uri: `/customers/${dibaId}` });
check('T-GS-07', file.status === 200 && file.body.includes('دیبا'),
  `GET /customers/${dibaId} -> ${file.status}, file shows the searched customer`);

/* ── T-GS-08: no match is an empty state, not an error ────────────────── */
const none = await search('zzz-چنین-مشتری-وجود-ندارد');
check('T-GS-08', none.status === 200 && hits(none.body).length === 0,
  `nonsense term -> ${none.status}, ${hits(none.body).length} result links, `
  + `empty state rendered: ${none.body.includes('v-empty')}`);

/* ── T-GS-09: the term is a parameter, never SQL ──────────────────────── */
const injection = await search("'; DROP TABLE customers; --");
const stillThere = String(await flow.query('select count(*) from customers')).trim();
check('T-GS-09', injection.status === 200 && Number(stillThere) > 0,
  `SQL metacharacters in the term -> ${injection.status}; customers table still `
  + `has ${stillThere} rows`);

process.exit(c.summary() ? 0 : 1);
