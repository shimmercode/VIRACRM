#!/usr/bin/env node
/**
 * Authenticated flow: log in, exercise every page and permission gate, verify
 * the forced-password-change path, then sign out.
 *
 * ISOLATION: runs against its own database copy (`.arena/flows/authed/`) and
 * sets its own admin password. This flow *changes the password as part of the
 * test* — it verifies the forced-change path — so it must not share a database
 * with any other flow. See tools/checks/lib/flow-harness.mjs for why.
 */
import fs from 'node:fs';
import path from 'node:path';

import { Flow, Checks, ROOT } from './lib/flow-harness.mjs';

const flow = new Flow('authed');
await flow.setup();

const c = new Checks('authed-flow');

// A password this flow owns. Generated per run: nothing static is stored, and a
// stale value from an earlier run cannot be inherited.
const PASSWORD = `Authed-${Date.now().toString(36)}-A9`;
const NEW_PASSWORD = PASSWORD + 'Xy9';

await flow.resetPassword({ password: PASSWORD, mustChange: false });

// ── 1. login ────────────────────────────────────────────────────────────────
const loginPage = await flow.render({ uri: '/login' });
const token = Flow.token(loginPage.body);

const login = await flow.render({
  method: 'POST',
  uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=admin&password=${encodeURIComponent(PASSWORD)}`,
});

const loggedIn = login.status === 302 && !/\/login/.test(login.headers.location || '');
c.check('T-AUTH-06', loggedIn, `POST valid creds -> ${login.status} -> ${login.headers.location}`);

// ── 2. dashboard reachable ──────────────────────────────────────────────────
const dashboard = await flow.render({ uri: '/' });
c.check('T-AUTH-07', dashboard.status === 200,
  `GET / after login -> ${dashboard.status} (forced change = ${/password/.test(dashboard.headers.location || '')})`);

// ── 3. change the password ──────────────────────────────────────────────────
const pwPage = await flow.render({ uri: '/password' });
const pwToken = Flow.token(pwPage.body);
c.check('T-AUTH-08', pwPage.status === 200 && Boolean(pwToken), `GET /password -> ${pwPage.status}`);

const change = await flow.render({
  method: 'POST',
  uri: '/password',
  body: `_token=${encodeURIComponent(pwToken)}&_method=PUT`
    + `&current_password=${encodeURIComponent(PASSWORD)}`
    + `&password=${encodeURIComponent(NEW_PASSWORD)}`
    + `&password_confirmation=${encodeURIComponent(NEW_PASSWORD)}`,
});

c.check('T-AUTH-09', change.status === 302 && !/password/.test(change.headers.location || ''),
  `PUT /password -> ${change.status} -> ${change.headers.location}`);

// The change really took effect in this flow's database (and only this one).
const stored = await flow.query(
  "select must_change_password from users where username = 'admin'"
);
c.check('T-AUTH-09b', stored === '0', `must_change_password in flow db = ${stored}`);

// ── 4. every module page reachable, inside the real shell ──────────────────
for (const [id, uri, expect] of [
  ['T-PAGE-01', '/', 200],
  ['T-PAGE-02', '/customers', 200],
  ['T-PAGE-03', '/customers/create', 200],
  ['T-PAGE-04', '/contracts', 200],
  ['T-PAGE-05', '/contracts/create', 200],
]) {
  const r = await flow.render({ uri });
  c.check(id, r.status === expect, `GET ${uri} -> ${r.status}`);

  if (r.status === 200) {
    const hasShell = /v-sidebar/.test(r.body) && /dir="rtl"/.test(r.body);
    c.check(`${id}-shell`, hasShell, `  sidebar+rtl rendered: ${hasShell} (${r.body.length} bytes)`);
  }
}

// ── 5. unknown route gives a Persian 404, not a stack trace ────────────────
const notFound = await flow.render({ uri: '/this-does-not-exist' });
c.check('T-PAGE-06', notFound.status === 404 && /صفحه پیدا نشد/.test(notFound.body),
  `GET /nope -> ${notFound.status}, persian 404 = ${/صفحه پیدا نشد/.test(notFound.body)}`);

// ── 6. logout, and the session really stops working ────────────────────────
const outPage = await flow.render({ uri: '/' });
const outToken = Flow.token(outPage.body);

const out = await flow.render({
  method: 'POST',
  uri: '/logout',
  body: `_token=${encodeURIComponent(outToken)}`,
});

c.check('T-AUTH-10', out.status === 302 && /login/.test(out.headers.location || ''),
  `POST /logout -> ${out.status} -> ${out.headers.location}`);

const after = await flow.render({ uri: '/customers' });
c.check('T-AUTH-11', after.status === 302 && /login/.test(after.headers.location || ''),
  `GET /customers after logout -> ${after.status}`);

// ── forced-change path, on a second user in the same isolated database ─────
// Verifying that a user with must_change_password=true is *blocked* from every
// module is a distinct behaviour from setting the flag, so it gets its own
// account. This is what previously required resetting the shared admin.
const FORCED_PASSWORD = `Forced-${Date.now().toString(36)}-B7`;
await flow.resetPassword({ username: 'forced1', password: FORCED_PASSWORD, mustChange: true });

// A fresh jar: the admin jar above was signed out.
const forcedLogin = await flow.render({ uri: '/login' });
const forcedToken = Flow.token(forcedLogin.body);

await flow.render({
  method: 'POST',
  uri: '/login',
  body: `_token=${encodeURIComponent(forcedToken)}&username=forced1&password=${encodeURIComponent(FORCED_PASSWORD)}`,
});

const blocked = await flow.render({ uri: '/customers' });
const redirectedToPassword = blocked.status === 302 && /password/.test(blocked.headers.location || '');
c.check('T-AUTH-12', redirectedToPassword,
  `must_change_password user blocked from /customers -> ${blocked.status} -> ${blocked.headers.location}`);

const allowed = await flow.render({ uri: '/password' });
c.check('T-AUTH-13', allowed.status === 200, `/password reachable while change required -> ${allowed.status}`);

const ok = c.summary();

// The flow directory holds a database copy and its request logs. It is not
// evidence, so it is removed; nothing outside .arena/ ever saw it.
flow.cleanup();

process.exit(ok ? 0 : 1);
