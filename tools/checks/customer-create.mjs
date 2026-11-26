#!/usr/bin/env node
/**
 * VIRA CRM — creating a customer, asserted properly.
 *
 * WHY A SUITE EXISTS FOR ONE FORM
 * -------------------------------
 * `StoreCustomerRequest` used `Rule::in` without importing the class. PHP
 * resolved it against the file's namespace, so every `POST /customers` threw a
 * fatal Error and returned 500. The suite stayed green, because the checks that
 * created customers did this:
 *
 *     await flow.render({ method: 'POST', uri: '/customers', body: ... });
 *     const id = await flow.query('select id from customers order by id desc limit 1');
 *     check('...', Number(id) > 0, ...);
 *
 * Every one of those assertions holds whether or not the POST worked: the
 * request's status was never read, and the "new" id was just the newest seeded
 * row. A module that was completely down looked healthy.
 *
 * So this suite asserts the four things that were missing:
 *
 *   T-CC-01  the response is a success redirect, and its Location names the new
 *            record — the id comes from the RESPONSE, not from a table scan
 *   T-CC-02  the isolated database gained exactly one customer
 *   T-CC-03  the stored row is what was submitted, including the derived
 *            normalised columns
 *   T-CC-04  an individual (حقیقی) customer works on its own field set
 *   T-CC-05  a status outside CustomerStatus is refused and writes nothing —
 *            this is the rule whose rewrite broke the form
 *   T-CC-06  a national ID with a bad check digit is refused
 *   T-CC-07  a refused submit leaves no partial row behind
 *   T-CC-08  the shared development database is byte-identical afterwards
 *
 * T-CC-01 is the one that matters. Reading the new id out of the Location
 * header is the only way to know the request produced a record; anything read
 * back from the table afterwards could have been there already.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');

const c = new Checks('customer-create');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const flow = new Flow('customercreate');
await flow.setup();

const PASSWORD = `Cc-${Date.now().toString(36)}-M3`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();
const token = async (uri = '/customers/create') =>
  Flow.token((await flow.render({ uri })).body);
const form = (o) => new URLSearchParams(o).toString();

/** The record id a redirect points at, or null. */
const idFromLocation = (res) => {
  const m = /\/customers\/(\d+)(?:\/edit)?$/.test(String(res.headers['location'] || ''))
    ? String(res.headers['location']).match(/\/customers\/(\d+)/)
    : null;

  return m ? m[1] : null;
};

const errorsOn = (html) => [...String(html).matchAll(/class="v-error"[^>]*>([^<]+)</g)]
  .map((m) => m[1].trim());

const COMPANY = 'شرکت آزمون ثبت مشتری';
const countBefore = Number(await q('select count(*) from customers'));

/* ── T-CC-01/02/03: a legal customer, asserted from the response ──────── */
const res = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: await token(),
    type: 'legal',
    company_name: COMPANY,
    economic_code: '411555666777',
    address: 'تهران، خیابان ولیعصر، پلاک ۸۸',
    city: 'تهران',
    status: 'active',
    mobile: '09121112233',
  }),
});

const newId = idFromLocation(res);

check('T-CC-01', res.status === 302 && newId !== null,
  `POST /customers -> ${res.status}; Location=${res.headers['location']}; `
  + `new record id read from the response: ${newId ?? '(none)'}`);

const countAfter = Number(await q('select count(*) from customers'));
check('T-CC-02', newId !== null && countAfter === countBefore + 1
  && (await q(`select count(*) from customers where id = ${newId ?? 0}`)) === '1',
  `customers in the isolated flow database ${countBefore} -> ${countAfter}; `
  + `a row with the id from the response exists`);

if (newId !== null) {
  const row = await q(
    `select company_name || '|' || status || '|' || type || '|' || coalesce(normalized_mobile,'') `
    + `from customers where id = ${newId}`,
  );
  const [storedName, storedStatus, storedType, normalizedMobile] = row.split('|');

  check('T-CC-03',
    storedName === COMPANY && storedStatus === 'active' && storedType === 'legal'
    && normalizedMobile === '09121112233',
    `stored row: company=${storedName}, status=${storedStatus}, type=${storedType}, `
    + `normalized_mobile=${normalizedMobile} — including the column the model derives, `
    + 'not just the ones the form posted');
} else {
  check('T-CC-03', false, 'no new record, so nothing to compare');
}

/* ── T-CC-04: the other branch of the type-specific rules ─────────────── */
const resPerson = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: await token(),
    type: 'natural',
    first_name: 'نگار',
    last_name: 'آزمون‌پور',
    // A structurally valid Iranian national ID (check digit verified).
    national_id: '0084575948',
    status: 'lead',
  }),
});
const personId = idFromLocation(resPerson);

check('T-CC-04', resPerson.status === 302 && personId !== null,
  `POST an individual customer -> ${resPerson.status}; id from response: ${personId ?? '(none)'}`);

/* ── T-CC-05: the status rule that broke the form ─────────────────────── */
const beforeBad = Number(await q('select count(*) from customers'));
const resBad = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: await token(),
    type: 'legal',
    company_name: 'شرکت با وضعیت نامعتبر',
    status: 'not-a-real-status',
  }),
});
const afterBad = Number(await q('select count(*) from customers'));
const badPage = await flow.render({ uri: '/customers/create' });

check('T-CC-05', resBad.status === 302 && afterBad === beforeBad
  && idFromLocation(resBad) === null,
  `status='not-a-real-status' -> ${resBad.status}; customers ${beforeBad} -> ${afterBad}; `
  + `no record id in the response. This is the rule whose rewrite (Rule::in derived `
  + `from CustomerStatus::cases()) once threw a fatal for want of an import.`
  + (badPage.status === 200 ? ' The form still renders afterwards.' : ''));

/* ── T-CC-06/07: checksum refusal leaves nothing behind ───────────────── */
const beforeCk = Number(await q('select count(*) from customers'));
const resCk = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: await token(),
    type: 'natural',
    first_name: 'رضا',
    last_name: 'رقم‌کنترلی',
    national_id: '1234567890', // sum%11 == 1, so the check digit must be 1, not 0
    status: 'lead',
  }),
});
const afterCk = Number(await q('select count(*) from customers'));
const redirectedTo = String(resCk.headers['location'] || '');

check('T-CC-06', resCk.status === 302 && !/\/customers\/\d+$/.test(redirectedTo),
  `a national ID with a bad check digit -> ${resCk.status}, Location=${redirectedTo}`);

check('T-CC-07', afterCk === beforeCk,
  `a refused submit wrote nothing: customers ${beforeCk} -> ${afterCk}`);

/* ── T-CC-08: the shared development database ─────────────────────────── */
const sharedAfter = sha(SHARED_DB);
check('T-CC-08', sharedAfter === sharedBefore,
  sharedAfter === sharedBefore
    ? `database/database.sqlite byte-identical (sha256 ${sharedBefore.slice(0, 16)}…)`
    : `SHARED DATABASE CHANGED: ${sharedBefore.slice(0, 16)}… -> ${sharedAfter.slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
