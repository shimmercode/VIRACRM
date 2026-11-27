/**
 * Security headers and session cookie flags over real HTTP (§10-5).
 *
 * REQ-10.05 was carried as PARTIAL with the evidence "no security headers
 * test". The implementation was never the gap: `SecurityHeaders` is registered
 * at bootstrap/app.php:62 and sets six headers. What was missing was any
 * assertion that they actually reach a client — and a middleware that is
 * registered but never asserted can be dropped by a refactor with nothing
 * noticing, because the pages keep rendering fine.
 *
 * So this renders real pages and reads the real response headers. It also
 * covers the session cookie, which is the other half of §10-5 and the part
 * that silently misbehaves behind a TLS-terminating proxy: `Secure` is driven
 * by SESSION_SECURE_COOKIE, which has no default, so an HTTPS deployment that
 * forgets to set it ships a cookie over plain HTTP.
 *
 * Runs on a private flow database; the last check asserts the shared one was
 * not touched.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks, Flow } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('security headers');
const check = (id, ok, detail) => c.check(id, ok, detail);

const SHARED = path.join(ROOT, 'database/database.sqlite');
const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const sharedBefore = sha(SHARED);

const flow = new Flow('securityheaders');
await flow.setup();

const PASSWORD = `Sh-${Date.now().toString(36)}-K7`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/* ═══ Unauthenticated page: the headers must be there before login too ══ */

const login = await flow.render({ uri: '/login' });

const H = [
  ['x-content-type-options', 'nosniff'],
  ['x-frame-options', 'DENY'],
  ['referrer-policy', 'same-origin'],
  ['x-permitted-cross-domain-policies', 'none'],
  ['permissions-policy', 'geolocation=(), camera=(), microphone=(), payment=()'],
];

for (const [name, want] of H) {
  const got = login.headers[name];
  check(`SH-${name.slice(0, 6)}`, got === want,
    `/login sends ${name}: ${got === want ? want : `expected «${want}», got «${got ?? 'nothing'}»`}`);
}

check('SH-cache', (login.headers['cache-control'] ?? '').includes('no-store'),
  `/login is uncacheable: Cache-Control=${login.headers['cache-control'] ?? 'absent'}`
  + ` — pages carry customer and credential metadata`);

const csp = login.headers['content-security-policy'] ?? '';
const DIRECTIVES = ["default-src 'self'", "script-src 'self'", "font-src 'self'",
  "form-action 'self'", "frame-ancestors 'none'", "base-uri 'self'", "object-src 'none'"];
const missing = DIRECTIVES.filter((d) => !csp.includes(d));

check('SH-csp', missing.length === 0,
  missing.length === 0
    ? `CSP carries all ${DIRECTIVES.length} required directives, including frame-ancestors 'none'`
    : `CSP is missing: ${missing.join(', ')}`);

// A CSP that allows unsafe-inline for scripts would let an injected attribute
// run; style-src is the one deliberate exception (Blade component attributes).
check('SH-csp-strict', !/script-src[^;]*unsafe-inline/.test(csp),
  `script-src does not allow 'unsafe-inline' (style-src does, deliberately, for`
  + ` Blade component attributes)`);

check('SH-noleak', !('x-powered-by' in login.headers),
  `no X-Powered-By header — the stack version is not advertised`);

/* ═══ Authenticated page: same headers on a real data page ══════════════ */

await flow.login('admin', PASSWORD);
const home = await flow.render({ uri: '/' });

check('SH-auth', home.status === 200
  && home.headers['x-frame-options'] === 'DENY'
  && home.headers['x-content-type-options'] === 'nosniff'
  && (home.headers['content-security-policy'] ?? '').includes("default-src 'self'"),
  `an authenticated page (/) returns ${home.status} with the same headers —`
  + ` the middleware is global, not attached to one route group`);

/* ═══ Session cookie flags ══════════════════════════════════════════════ */

const loginFlow = new Flow('securityheaderscookie');
await loginFlow.setup();
await loginFlow.resetPassword({ password: PASSWORD, mustChange: false });

const before = await loginFlow.render({ uri: '/login' });
const token = Flow.token(before.body);
const posted = await loginFlow.render({
  method: 'POST',
  uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=admin&password=${encodeURIComponent(PASSWORD)}`,
});

const session = (posted.headers['set-cookie'] ?? '')
  .split(' | ')
  .find((s) => s.includes('vira_session') || s.toLowerCase().includes('session')) ?? '';

check('SH-ck-httponly', /HttpOnly/i.test(session),
  `the session cookie is HttpOnly — script cannot read it: ${session.slice(0, 70)}…`);

check('SH-ck-samesite', /SameSite=(Lax|Strict)/i.test(session),
  `the session cookie sets SameSite (${(session.match(/SameSite=\w+/i) || ['none'])[0]}),`
  + ` which is what stops a cross-site request carrying it`);

check('SH-ck-path', /Path=\//i.test(session),
  `the session cookie is scoped to Path=/`);

/*
 * The Secure flag is the one that depends on deployment configuration, so it
 * is asserted by setting the flag rather than by hoping: with
 * SESSION_SECURE_COOKIE=true the cookie MUST carry Secure, and without it the
 * cookie must not claim to.
 */
const secureOn = await loginFlow.render({
  uri: '/login',
  env: { SESSION_SECURE_COOKIE: 'true' },
});
const insecure = await loginFlow.render({ uri: '/login' });

const ckOf = (r) => (r.headers['set-cookie'] ?? '').split(' | ')
  .find((s) => s.toLowerCase().includes('session')) ?? '';

check('SH-ck-secure', /;\s*Secure/i.test(ckOf(secureOn)),
  `with SESSION_SECURE_COOKIE=true the session cookie carries Secure`);

check('SH-ck-secure-doc', !/;\s*Secure/i.test(ckOf(insecure)),
  `with the variable unset the cookie does NOT carry Secure — this is why an`
  + ` HTTPS deployment must set SESSION_SECURE_COOKIE=true, or the session`
  + ` crosses the network in plaintext`);

/* ═══ Isolation ═════════════════════════════════════════════════════════ */

check('SH-iso', sha(SHARED) === sharedBefore,
  `database/database.sqlite unchanged: ${sharedBefore.slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);
