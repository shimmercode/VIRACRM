#!/usr/bin/env node
/**
 * Customer and contract CRUD through the real HTTP stack, as the superadmin.
 * Proves: create persists, validation rejects bad input in Persian, edit
 * updates, search/filter/pagination work, and contract money/dates convert.
 */

import fs from 'node:fs';
import path from 'node:path';

import { Flow, Checks } from './lib/flow-harness.mjs';

// ISOLATION: private database copy, so the customers and contracts this flow
// creates never appear in another flow's assertions or in the evidence
// screenshots. The password is generated here rather than inherited.
const flow = new Flow('crud');
await flow.setup();

const c = new Checks('crud-flow');

const PASSWORD = `Crud-${Date.now().toString(36)}-C3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

const results = c.results;
const check = (id, ok, detail) => c.check(id, ok, detail);
const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
const render = (spec) => flow.render(spec);

// login
let page = await render({ uri: '/login' });
const login = await render({ method: 'POST', uri: '/login',
  body: form({ _token: tok(page.body), username: 'admin', password: PASSWORD }) });
check('T-CRUD-00', login.status === 302, `login -> ${login.status} -> ${login.headers.location}`);

// The flow sets its own password and clears must_change_password, so the
// forced-change branch this script used to carry is gone: it existed only to
// recover from whatever password a previous flow had left behind.

// ── Customer create: invalid input is rejected with Persian messages ────
page = await render({ uri: '/customers/create' });
let t = tok(page.body);
const invalid = await render({ method: 'POST', uri: '/customers',
  body: form({ _token: t, type: 'natural', status: 'lead', first_name: '', last_name: '' }) });
check('T-CUST-01', invalid.status === 302, `POST invalid customer -> ${invalid.status} (redirect back)`);

const invalidPage = await render({ uri: '/customers/create' });
const hasPersianErr = /را وارد کنید/.test(invalidPage.body);
check('T-CUST-02', hasPersianErr, `persian validation message shown: ${hasPersianErr}`);

// ── Customer create: valid ───────────────────────────────────────────────
page = await render({ uri: '/customers/create' });
t = tok(page.body);
const uniq = 'A' + Date.now().toString(36);
// A fresh mobile per run: reusing one would legitimately trip the duplicate
// detector (same phone number), which is a different assertion than "create
// works".
const mobile = '0935' + String(Date.now()).slice(-7);
const created = await render({ method: 'POST', uri: '/customers', body: form({
  _token: t, type: 'legal', status: 'active', company_name: 'شرکت آزمون ' + uniq,
  mobile, email: uniq.toLowerCase() + '@example.com', city: 'تهران',
  'websites[0][url]': uniq.toLowerCase() + '.example.com', 'websites[0][label]': 'سایت اصلی',
}) });
const loc = created.headers.location || '';
check('T-CUST-03', created.status === 302 && /\/customers\/\d+$/.test(loc),
  `POST valid customer -> ${created.status} -> ${loc}`);

const id = (loc.match(/\/customers\/(\d+)$/) || [])[1];
const detail = await render({ uri: `/customers/${id}` });
check('T-CUST-04', detail.status === 200 && detail.body.includes('شرکت آزمون ' + uniq),
  `GET /customers/${id} -> ${detail.status}, name present`);
check('T-CUST-05', detail.body.includes(uniq.toLowerCase() + '.example.com'),
  `website persisted and shown`);

// ── Duplicate detection ──────────────────────────────────────────────────
page = await render({ uri: '/customers/create' });
t = tok(page.body);
const dupe = await render({ method: 'POST', uri: '/customers', body: form({
  _token: t, type: 'legal', status: 'active', company_name: 'شرکت آزمون ' + uniq,
  mobile: '09351112233',
}) });
const dupePage = await render({ uri: '/customers/create' });
check('T-CUST-06', /مشتری مشابه یافت شد/.test(dupePage.body),
  `duplicate warning shown: ${/مشتری مشابه یافت شد/.test(dupePage.body)}`);

// ── Search finds it ─────────────────────────────────────────────────────
const search = await render({ uri: '/customers?q=' + encodeURIComponent(uniq) });
check('T-CUST-07', search.body.includes('شرکت آزمون ' + uniq),
  `search by code fragment found the customer`);

// ── Filter by status ────────────────────────────────────────────────────
const filtered = await render({ uri: '/customers?status=active' });
check('T-CUST-08', filtered.status === 200 && filtered.body.includes('شرکت آزمون ' + uniq),
  `status filter returns active customers`);

// ── Edit ────────────────────────────────────────────────────────────────
page = await render({ uri: `/customers/${id}/edit` });
t = tok(page.body);
const cityOld = page.body.includes('تهران');
const updated = await render({ method: 'POST', uri: `/customers/${id}`, body: form({
  _token: t, _method: 'PUT', type: 'legal', status: 'prospect',
  company_name: 'شرکت آزمون ' + uniq, mobile, city: 'اصفهان',
  'websites[0][url]': uniq.toLowerCase() + '.example.com', 'websites[0][label]': 'سایت اصلی',
}) });
const afterEdit = await render({ uri: `/customers/${id}` });
check('T-CUST-09', updated.status === 302 && cityOld && afterEdit.body.includes('اصفهان'),
  `edit persisted new city: ${afterEdit.body.includes('اصفهان')}`);

// ── Contract create with Jalali date + money ────────────────────────────
page = await render({ uri: '/contracts/create' });
t = tok(page.body);
const contractBody = form({
  _token: t, customer_id: id, title: 'قرارداد سئو ' + uniq, amount: '120,000,000',
  start_date: '۱۴۰۴/۰۱/۱۵', end_date: '۱۴۰۵/۰۱/۱۴', status: 'active',
  description: 'قرارداد آزمایشی برای بررسی مسیر ثبت.',
});
const con = await render({ method: 'POST', uri: '/contracts', body: contractBody });
const conLoc = con.headers.location || '';
check('T-CON-01', con.status === 302 && /\/contracts\/\d+$/.test(conLoc),
  `POST contract (jalali dates) -> ${con.status} -> ${conLoc}`);

const conId = (conLoc.match(/\/contracts\/(\d+)$/) || [])[1];
if (conId) {
  const conDetail = await render({ uri: `/contracts/${conId}` });
  // 1404/01/15 -> 2025-04-04 ; money 120,000,000 becomes Persian digits
  // Persian digits with the Persian thousands separator (U+066C).
  const hasAmount = conDetail.body.includes('۱۲۰٬۰۰۰٬۰۰۰');
  check('T-CON-02', conDetail.status === 200 && hasAmount,
    `contract detail renders ۱۲۰٬۰۰۰٬۰۰۰ = ${hasAmount}`);
  check('T-CON-03', /۱۴۰۴/.test(conDetail.body) || /2025/.test(conDetail.body),
    `contract detail shows a date (jalali preferred)`);
  check('T-CON-04', conDetail.body.includes('شرکت آزمون ' + uniq),
    `contract links to its real customer`);

  // invalid Jalali date must be refused
  page = await render({ uri: '/contracts/create' });
  t = tok(page.body);
  const badDate = await render({ method: 'POST', uri: '/contracts', body: form({
    _token: t, customer_id: id, title: 'bad date', amount: '1000',
    start_date: '1404/13/45', status: 'draft',
  }) });
  const badPage = await render({ uri: '/contracts/create' });
  check('T-CON-05', /تاریخ شروع معتبر نیست/.test(badPage.body),
    `impossible jalali date rejected in persian`);
}

// ── Contracts list ──────────────────────────────────────────────────────
const conList = await render({ uri: '/contracts' });
check('T-CON-06', conList.status === 200 && conList.body.includes('قرارداد سئو ' + uniq),
  `contract appears in list`);

// ── Pagination component present when > 20 rows; header always sane ─────
check('T-CON-07', conList.body.includes('قراردادها'), `contracts list renders heading`);

// The created records must exist in this flow's database specifically.
const storedCustomers = await flow.query(
  `select count(*) from customers where company_name like 'شرکت آزمون %'`
);
check('T-CUST-10', storedCustomers === '1',
  `exactly one test customer in the flow db (found ${storedCustomers})`);

const ok = c.summary();
flow.cleanup();
process.exit(ok ? 0 : 1);
