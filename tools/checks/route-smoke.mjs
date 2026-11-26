#!/usr/bin/env node
/**
 * VIRA CRM — every registered GET route, requested, with no 500 allowed.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * `StoreCustomerRequest` used `Rule::in` without importing the class. PHP
 * resolved it against the file's namespace, so every customer create threw a
 * fatal Error and returned HTTP 500. It went unnoticed for a whole round
 * because the tests that created customers asserted a row existed afterwards —
 * and seeded rows exist whether or not the POST worked.
 *
 * A missing import, a renamed accessor, a view referencing a method that does
 * not exist: all of these produce a 500 on a route that "works" in every
 * narrower test. The only thing that catches them broadly is asking every route
 * for a response and refusing to accept a server error.
 *
 * HOW THE LIST IS BUILT
 * ---------------------
 * From the live router (tools/checks/route-inventory.php), not from a
 * hand-written list. A maintained list goes stale the day a route is added, and
 * the routes that get forgotten are the new ones.
 *
 * HOW PARAMETERS ARE FILLED
 * -------------------------
 * With a real id where the fixture has a row, and with an id that cannot exist
 * where it does not. A 404 from a route whose record is missing still proves
 * the router matched, the middleware ran, and route-model binding resolved —
 * which is where fatals live. What is never acceptable is a 500.
 *
 *   T-RS-01  the inventory came from the live router and covers every GET route
 *   T-RS-02  no GET route returns 500
 *   T-RS-03  every GET route was actually requested — no silent skips
 *   T-RS-04  a 200 response renders the application shell, not a blank page
 *   T-RS-05  the superadmin is not locked out of a permission-gated page
 *   T-RS-06  the full status map, recorded as evidence
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Flow, Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const c = new Checks('route-smoke');
const check = (id, ok, detail) => c.check(id, ok, detail);

/* ── read the route table from the running application ─────────────────── */
const inv = spawnSync(process.execPath,
  [path.join(ROOT, 'docs/tools/php-cli.mjs'), path.join(ROOT, 'tools/checks/route-inventory.php')],
  { cwd: ROOT, encoding: 'utf8', timeout: 300_000 });

const raw = (inv.stdout || '') + (inv.stderr || '');
const block = raw.match(/VIRA_ROUTES_JSON_BEGIN\n([\s\S]*?)\nVIRA_ROUTES_JSON_END/);
const routes = block ? JSON.parse(block[1]) : [];

const getRoutes = routes.filter((r) => r.methods.includes('GET'));

check('T-RS-01', routes.length > 90 && getRoutes.length > 40,
  `the live router reports ${routes.length} routes, ${getRoutes.length} of them GET`);

/* ── sign in as the superadmin ─────────────────────────────────────────── */
const flow = new Flow('routesmoke');
await flow.setup();

const PASSWORD = `Rs-${Date.now().toString(36)}-K9`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const q = async (sql) => String(await flow.query(sql)).trim();

/*
 * Parameter name -> table. Where the fixture has a row the real id is used, so
 * the route renders a real record rather than an error page.
 */
const TABLES = {
  customer: 'customers',
  contract: 'contracts',
  template: 'contract_templates',
  project: 'projects',
  invoice: 'invoices',
  entry: 'journal_entries',
  period: 'payroll_periods',
  department: 'departments',
  costCenter: 'cost_centers',
  employee: 'employees',
  payslip: 'payslips',
  workItem: 'work_items',
  note: 'notes',
  credential: 'project_credentials',
};

const ids = {};
for (const [param, table] of Object.entries(TABLES)) {
  const v = await q(`select id from ${table} order by id limit 1`);
  if (/^\d+$/.test(v)) ids[param] = v;
}

/*
 * Two routes take a parameter that is not a record id: `storage/{path}` serves
 * a file and `review/preview/{key}` takes a catalogue key. They are still
 * requested — with a value that cannot resolve — because "no 500" has to hold
 * for them too.
 */
const LITERAL = { path: 'does-not-exist.png', key: 'no-such-preview-key' };

const results = [];

for (const route of getRoutes) {
  // The root route's uri is already '/', so a naive '/' + uri yields '//'.
  let uri = route.uri === '/' ? '/' : '/' + route.uri.replace(/^\//, '');
  let unfillable = null;

  for (const param of route.parameters) {
    /*
     * A real id where the fixture has one. Where the table is empty, an id that
     * cannot exist: the resulting 404 still proves the router matched, the
     * middleware ran and route-model binding resolved, which is where fatals
     * live. Skipping the route would leave the gap this suite exists to close.
     */
    const value = ids[param] ?? (TABLES[param] ? '999999' : LITERAL[param]);

    if (value === undefined) {
      unfillable = param;

      break;
    }

    uri = uri.replace(`{${param}}`, encodeURIComponent(value));
  }

  if (unfillable) {
    results.push({ name: route.name ?? route.uri, uri, status: null, note: `unfillable {${unfillable}}` });

    continue;
  }

  let res;
  try {
    res = await flow.render({ uri });
  } catch (e) {
    results.push({ name: route.name ?? route.uri, uri, status: 'THREW', note: String(e.message).slice(0, 120) });

    continue;
  }

  const type = String(res.headers['content-type'] || '');
  results.push({
    name: route.name ?? route.uri,
    uri,
    status: res.status,
    /*
     * A PDF or the health endpoint is a 200 with no application shell, and that
     * is correct. Judging them as hollow pages would be testing the wrong thing.
     */
    isHtml: type.includes('text/html'),
    shell: res.body.includes('v-shell') || res.body.includes('v-plain'),
    bytes: res.body.length,
  });
}

/* ── T-RS-03: nothing was silently skipped ─────────────────────────────── */
const skipped = results.filter((r) => r.status === null || r.status === 'THREW');
check('T-RS-03', skipped.length === 0,
  skipped.length === 0
    ? `all ${results.length} GET routes were requested`
    : `${skipped.length} not requested: ${skipped.map((r) => `${r.uri} (${r.note})`).join(', ')}`);

/* ── T-RS-02: the claim that matters ───────────────────────────────────── */
const serverErrors = results.filter((r) => Number(r.status) >= 500);
check('T-RS-02', serverErrors.length === 0,
  serverErrors.length === 0
    ? `no GET route returned a server error (${results.length} requested)`
    : `500s: ${serverErrors.map((r) => `${r.uri} -> ${r.status}`).join(', ')}`);

/* ── T-RS-04: a 200 is a page, not an empty body ───────────────────────── */
/*
 * `/up` is the framework health endpoint declared at bootstrap/app.php:11. It
 * answers a load balancer, not a person, and deliberately renders no shell.
 */
const NOT_A_PAGE = ['/up'];
const hollow = results.filter((r) => Number(r.status) === 200 && r.isHtml
  && !r.shell && !NOT_A_PAGE.includes(r.uri));
const binary = results.filter((r) => Number(r.status) === 200 && !r.isHtml);
check('T-RS-04', hollow.length === 0,
  hollow.length === 0
    ? `every 200 HTML page rendered the application shell; `
      + `${binary.length} 200s are non-HTML by design (${binary.map((r) => r.uri).join(', ')}); `
      + `${NOT_A_PAGE.join(', ')} excluded as the health endpoint`
    : `200 HTML pages without a shell: ${hollow.map((r) => r.uri).join(', ')}`);

/* ── T-RS-05: the superadmin reaches the gated pages ───────────────────── */
const gated = getRoutes.filter((r) => (r.middleware || []).some((m) => String(m).startsWith('permission:')));
const gatedResults = results.filter((r) => gated.some((g) => ('/' + g.uri) === r.uri || r.uri.startsWith('/' + g.uri)));
const lockedOut = gatedResults.filter((r) => r.status === 403);

check('T-RS-05', gated.length > 0 && lockedOut.length === 0,
  `${gated.length} GET routes are permission-gated; superadmin refused on `
  + `${lockedOut.length}${lockedOut.length ? ': ' + lockedOut.map((r) => r.uri).join(', ') : ''}`);

/* ── T-RS-06: the whole map, as evidence ───────────────────────────────── */
const tally = results.reduce((acc, r) => {
  const k = String(r.status);
  acc[k] = (acc[k] || 0) + 1;

  return acc;
}, {});
const unexpected = results.filter((r) => ![200, 302, 403, 404, 405].includes(Number(r.status)));

check('T-RS-06', unexpected.length === 0,
  `statuses ${JSON.stringify(tally)}`
  + (unexpected.length ? `; unexpected: ${unexpected.map((r) => `${r.uri}->${r.status}`).join(', ')}` : ''));

/* ── POST routes: an empty submit must fail validation, never fatally ────
 *
 * The defect that prompted this suite lived in a FormRequest, and a FormRequest
 * is only instantiated on the POST. `GET /customers/create` renders the form
 * happily while every `POST /customers` throws, so a GET-only sweep cannot see
 * it. Submitting each POST route with nothing but a CSRF token runs the real
 * validation rules and the real controller entry point; the expected outcome is
 * a 302 back with errors, and the only unacceptable outcome is a 500.
 *
 * `logout` is excluded because it would end the session under test.
 */
const postRoutes = routes.filter((r) => r.methods.includes('POST') && r.uri !== 'logout');
const postResults = [];

for (const route of postRoutes) {
  let uri = route.uri === '/' ? '/' : '/' + route.uri.replace(/^\//, '');
  let unfillable = null;

  for (const param of route.parameters) {
    const value = ids[param] ?? (TABLES[param] ? '999999' : LITERAL[param]);

    if (value === undefined) {
      unfillable = param;

      break;
    }

    uri = uri.replace(`{${param}}`, encodeURIComponent(value));
  }

  if (unfillable) {
    postResults.push({ uri, status: null, note: `unfillable {${unfillable}}` });

    continue;
  }

  // A fresh token per POST: the previous response rotates the session.
  const csrf = Flow.token((await flow.render({ uri: '/' })).body);

  let res;
  try {
    res = await flow.render({
      method: 'POST',
      uri,
      body: new URLSearchParams({ _token: csrf }).toString(),
    });
  } catch (e) {
    postResults.push({ uri, status: 'THREW', note: String(e.message).slice(0, 120) });

    continue;
  }

  // A route that signed the session out would make every later result a redirect.
  if (String(res.headers['location'] || '').includes('/login')) {
    await flow.login('admin', PASSWORD);
  }

  postResults.push({
    uri,
    status: res.status,
    type: String(res.headers['content-type'] || ''),
    // Full body, not a slice: truncating it broke JSON.parse and made a
    // legitimate refusal look malformed.
    body: Number(res.status) >= 500 ? String(res.body || '') : '',
  });
}

/*
 * ── WHICH 5xx RESPONSES ARE ACCEPTABLE, AND WHY THAT LIST IS SHORT ────────
 *
 * A 5xx is a failure unless it is a response the code deliberately produces.
 * An earlier version of this check accepted any 5xx served as
 * `application/json` on the theory that a designed refusal is JSON and an
 * uncaught fatal is HTML. That is far too loose: content type says nothing
 * about whether the *particular* error was intended, and a new endpoint that
 * leaks a JSON exception page would have been waved through.
 *
 * So the tolerance is an explicit allowlist. Each entry names the route, the
 * exact status, and the shape of the body, and each is tied to the line of code
 * that produces it. A 5xx that is not on this list fails, and adding to the
 * list means naming the code that justifies it.
 */
const DOCUMENTED_5XX = [
  {
    label: 'credential reveal with no encryption key configured',
    // CredentialController.php:201 — `if (! $this->vault->isReady())`. The
    // endpoint refuses to guess a plaintext rather than returning a wrong one.
    matches: (uri) => /^\/projects\/\d+\/credentials\/\d+\/reveal$/.test(uri),
    status: 503,
    body: (text) => {
      const parsed = JSON.parse(text);

      return parsed.ok === false
        && typeof parsed.message === 'string'
        && parsed.message.length > 0;
    },
  },
];

const classify5xx = (r) => {
  for (const entry of DOCUMENTED_5XX) {
    if (!entry.matches(r.uri)) continue;
    if (Number(r.status) !== entry.status) continue;

    try {
      // The renderer prefixes the body with a newline; JSON.parse tolerates
      // leading whitespace, but the earlier truncation at 200 characters cut
      // the payload mid-string and made a valid refusal look malformed.
      return entry.body(String(r.body || '').trim())
        ? { ok: true, why: entry.label }
        : { ok: false, why: `${entry.label}: body did not match the documented shape` };
    } catch (e) {
      return { ok: false, why: `${entry.label}: body was not parseable (${e.message})` };
    }
  }

  return { ok: false, why: 'not a documented error response' };
};

const postSkipped = postResults.filter((r) => r.status === null);

check('T-RS-07', postSkipped.length === 0 && postResults.length === postRoutes.length,
  `all ${postRoutes.length} POST routes submitted (logout excluded, it would end the `
  + `session under test); ${postSkipped.length} skipped`);

const postErrors = [];
const tolerated = [];

for (const r of postResults) {
  if (r.status === 'THREW') {
    postErrors.push(`${r.uri} threw: ${r.note}`);

    continue;
  }

  if (Number(r.status) < 500) continue;

  const verdict = classify5xx(r);
  if (verdict.ok) tolerated.push(`${r.uri} -> ${r.status} (${verdict.why})`);
  else postErrors.push(`${r.uri} -> ${r.status} [${r.type}] ${verdict.why}`);
}

check('T-RS-08', postErrors.length === 0,
  postErrors.length === 0
    ? `no POST route failed fatally on an empty submit (${postResults.length} submitted)`
      + (tolerated.length
        ? `; ${tolerated.length} tolerated 5xx, each matched to the code that produces it: ${tolerated.join('; ')}`
        : '; no 5xx at all')
    : `unacceptable 5xx: ${postErrors.join(' | ')}`);

console.log('\nstatus map:');
for (const r of results) {
  console.log(`  GET  ${String(r.status).padEnd(5)} ${r.uri}`);
}
console.log('\nPOST map (empty submit):');
for (const r of postResults) {
  console.log(`  POST ${String(r.status).padEnd(5)} ${r.uri}`);
}

process.exit(c.summary() ? 0 : 1);
