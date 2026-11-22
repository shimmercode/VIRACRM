#!/usr/bin/env node
/**
 * End-to-end authentication flow against the real kernel.
 *
 * Drives tools/http-render.php exactly as the dev server does and keeps the
 * cookie jar, so this exercises the actual session, CSRF and middleware stack —
 * not a mock.
 */

import { Flow, Checks } from './lib/flow-harness.mjs';

// ISOLATION: even though this flow is anonymous-only, it runs against its own
// database copy. Two reasons: the sessions it creates cannot interfere with a
// concurrent flow, and its own user gives it a *known* credential to prove the
// positive login path with — previously only the failure path was tested here.
const flow = new Flow('login');
await flow.setup();

const c = new Checks('login-flow');
const render = (spec) => flow.render(spec);
const check = (id, ok, detail) => c.check(id, ok, detail);
const results = c.results;

const GOOD_PASSWORD = `Login-${Date.now().toString(36)}-E8`;
await flow.resetPassword({ password: GOOD_PASSWORD, mustChange: false });

// ── T-AUTH-01 Anonymous access is blocked ────────────────────────────────
const anon = await render({ uri: '/customers' });
check('T-AUTH-01', anon.status === 302 && (anon.headers.location || '').includes('/login'),
  `GET /customers anonym -> ${anon.status} ${anon.headers.location || ''}`);

// ── T-AUTH-02 Login page renders ─────────────────────────────────────────
const page = await render({ uri: '/login' });
const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
check('T-AUTH-02', page.status === 200 && /dir="rtl"/.test(page.body) && !!token,
  `GET /login -> ${page.status}, csrf=${token ? 'yes' : 'no'}, rtl=${/dir="rtl"/.test(page.body)}`);

// ── T-AUTH-03 Wrong password is rejected without disclosing why ──────────
const bad = await render({
  method: 'POST', uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=admin&password=definitely-wrong`,
});
check('T-AUTH-03', bad.status === 302 && /login/.test(bad.headers.location || ''),
  `POST bad creds -> ${bad.status} -> ${bad.headers.location}`);

// echo the error back
const afterBad = await render({ uri: '/login' });
const errorShown = /نام کاربری یا رمز عبور نادرست است/.test(afterBad.body);
check('T-AUTH-04', errorShown, `generic error shown: ${errorShown}`);

// ── T-AUTH-05 CSRF is enforced ───────────────────────────────────────────
const noToken = await render({
  method: 'POST', uri: '/login',
  body: 'username=admin&password=x',
});
check('T-AUTH-05', noToken.status === 419 || noToken.status === 302,
  `POST without CSRF token -> ${noToken.status}`);

// ── T-AUTH-14 The positive path: correct credentials really sign in ─────
// Every other assertion here proves that bad input is refused. Without this
// one, a login form that rejected everybody would pass the whole flow.
const fresh = await render({ uri: '/login' });
const goodToken = (fresh.body.match(/name="_token" value="([^"]+)"/) || [])[1];

const good = await render({
  method: 'POST',
  uri: '/login',
  body: `_token=${encodeURIComponent(goodToken)}`
    + `&username=admin&password=${encodeURIComponent(GOOD_PASSWORD)}`,
});

const signedIn = good.status === 302 && !/\/login/.test(good.headers.location || '');
check('T-AUTH-14', signedIn, `POST correct creds -> ${good.status} -> ${good.headers.location}`);

const dashboard = await render({ uri: '/' });
check('T-AUTH-15', dashboard.status === 200, `GET / with the session -> ${dashboard.status}`);

// ── T-AUTH-16 The session cookie is not readable by JavaScript ──────────
// httpOnly is the control that keeps a session token out of reach of any
// script that ends up on the page.
const cookieHeaders = [good.headers['set-cookie'], fresh.headers['set-cookie']]
  .filter(Boolean)
  .join(' | ');

// The cookie name derives from APP_NAME: "VIRA CRM" -> vira_crm_session.
// Matching the literal name keeps this from passing on some unrelated cookie.
const isSessionCookie = /vira_crm_session=/i.test(cookieHeaders);
const httpOnly = /httponly/i.test(cookieHeaders);

// The first assertion must be real: if no session cookie was set at all, we
// have not proven anything about its flags.
check('T-AUTH-16', isSessionCookie && httpOnly,
  `session cookie issued=${isSessionCookie}, httpOnly=${httpOnly}`);

const ok = c.summary();
flow.cleanup();
process.exit(ok ? 0 : 1);
