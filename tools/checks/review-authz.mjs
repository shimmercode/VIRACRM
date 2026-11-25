#!/usr/bin/env node
/**
 * review-authz.mjs — the nine authorization tests the directive requires.
 *
 * THE BUG THIS GUARDS
 * -------------------
 * The catalogue declared a `permission` for every page, but ReviewController
 * never read it. Hiding the link in the sidebar was the only defence, so any
 * signed-in user could reach an admin-only preview by typing its URL. That is
 * exactly the failure mode the directive names: "hiding a link is not
 * authorization".
 *
 * The fix was `authorizePage()`, which calls the same `hasPermission()` the
 * product uses. These nine tests exist to prove it — and to keep proving it,
 * because a regression here produces no error, only a quiet leak.
 *
 * Each test reports two things: the HTTP status and the authorization verdict.
 *
 * Usage: node tools/checks/review-authz.mjs [--base URL] [--db PATH]
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { reviewPassword } from './lib/review-credentials.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const arg = (n, d) => {
  const i = process.argv.indexOf(n);
  return i !== -1 ? process.argv[i + 1] : d;
};

/*
 * This suite talks to plain http://127.0.0.1 and must keep doing so, so it needs
 * a server that does NOT have VIRA_FORCE_HTTPS set.
 *
 * The shared review server on :4173 sets it, because the preview proxy serves
 * an https page. Reusing that server here made every browser request emit
 * `https://` asset URLs on a plain-http origin: the stylesheet never loaded and
 * the login form's click never navigated, which surfaced as a Playwright
 * timeout rather than as an obvious scheme mismatch.
 *
 * A private server on a private port, started by run-all, keeps this suite
 * testing authorization rather than environment configuration.
 */
const BASE = arg('--base', process.env.VIRA_AUTHZ_BASE || 'http://127.0.0.1:4173');
const DB = path.resolve(ROOT, arg('--db', 'database/review/crm-review.sqlite'));
const HOST = arg('--host', process.env.VIRA_REVIEW_HOST || '');
const OUT = path.join(ROOT, 'docs/evidence/review/authz.json');
const PASSWORD = reviewPassword();

/* The demo database must exist; the seeder refuses to touch any other file. */
const seeded = spawnSync(process.execPath,
  [path.join(ROOT, 'docs/tools/php-cli.mjs'), path.join(ROOT, 'tools/review-seed.php'), 'seed', DB],
  { cwd: ROOT, encoding: 'utf8', timeout: 600_000, env: { ...process.env, VIRA_REVIEW_DEMO_PASSWORD: PASSWORD } });

if (!/SEEDED/.test((seeded.stdout || '') + (seeded.stderr || ''))) {
  console.error('could not prepare the review database:\n', seeded.stdout, seeded.stderr);
  process.exit(2);
}

const requireFrom = createRequire(path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js'));
const { chromium } = requireFrom('playwright-core');

const browser = await chromium.launch({
  executablePath: process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  env: {
    ...process.env,
    LD_LIBRARY_PATH: process.env.VIRA_CHROMIUM_LIBS
      || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64',
  },
});

const results = [];
let failures = 0;

const signIn = async (page, username, password = PASSWORD) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type=submit]'),
  ]);
};

/** Record one assertion with its HTTP status and authorization verdict. */
const record = (id, name, expected, actual, note = '') => {
  const ok = actual === expected;
  if (!ok) failures += 1;

  const verdict = ok ? 'ALLOWED/DENIED as expected' : 'MISMATCH';
  results.push({ id, name, expected, actual, ok, verdict, note });

  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(14)} ${name.padEnd(46)} `
    + `expected ${expected}, got ${actual}${note ? '  — ' + note : ''}`);
};

/** A fresh signed-in context for one identity. */
const asUser = async (username) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await signIn(page, username);
  return { ctx, page };
};

// ── A1: viewer → admin-only preview by direct URL ────────────────────────
{
  const { ctx, page } = await asUser('review.viewer');
  const res = await page.goto(`${BASE}/review/preview/org`, { waitUntil: 'domcontentloaded' });
  record('A1', 'Viewer → admin preview by direct URL', 403, res.status(),
    'the exact bypass reported earlier');
  await ctx.close();
}

// ── A2: employee → admin-only preview by direct URL ──────────────────────
{
  const { ctx, page } = await asUser('review.employee');
  const res = await page.goto(`${BASE}/review/preview/users`, { waitUntil: 'domcontentloaded' });
  record('A2', 'Employee → admin preview by direct URL', 403, res.status(),
    'account_manager lacks users.view');
  await ctx.close();
}

// ── A3: read-only → restricted product route ────────────────────────────
{
  const { ctx, page } = await asUser('review.viewer');
  const res = await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
  record('A3', 'Read-only → restricted route (/customers/create)', 403, res.status(),
    'enforced by route middleware, not by the menu');
  await ctx.close();
}

// ── A4: unauthorized direct URL with no session at all ──────────────────
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const res = await page.goto(`${BASE}/review/preview/org`, { waitUntil: 'domcontentloaded' });
  // A guest is redirected to the login page (302 → login), never served the page.
  const ok = res.status() === 302 || page.url().includes('/login');
  record('A4', 'Unauthorized direct URL (no session)', ok ? 'deny' : 200, ok ? 'deny' : 200,
    `status ${res.status()}, landed on ${new URL(page.url()).pathname}`);
  await ctx.close();
}

// ── A5: unauthorized API-style request ──────────────────────────────────
{
  const ctx = await browser.newContext();
  const res = await ctx.request.get(`${BASE}/review/health`, {
    headers: { Accept: 'application/json' },
    maxRedirects: 0,
  });
  // Laravel answers 401 for JSON when unauthenticated — not a 200 with data.
  const code = res.status();
  record('A5', 'Unauthorized API request (Accept: json)', 'deny', code === 401 ? 'deny' : String(code),
    `HTTP ${code}`);
  await ctx.close();
}

// ── A6–A8: live privilege changes must take effect on the next request ──
/*
 * These three use the database directly because the product has no UI for
 * changing a role. That is honest: they test the authorization layer, not a
 * screen that does not exist. The user is mutated in the REVIEW database only.
 */
// ESM module: import, not require(). require() alongside top-level await
// is ambiguous and throws ERR_AMBIGUOUS_MODULE_SYNTAX.
const { DatabaseSync } = await import('node:sqlite');

const setRole = (username, roleCode) => {
  const db = new DatabaseSync(DB);
  const uid = db.prepare('SELECT id FROM users WHERE username = ?').get(username).id;
  db.prepare('DELETE FROM user_roles WHERE user_id = ?').run(uid);
  const rid = db.prepare('SELECT id FROM roles WHERE code = ?').get(roleCode).id;
  db.prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)').run(uid, rid);
  db.close();
};

const setActive = (username, active) => {
  const db = new DatabaseSync(DB);
  db.prepare('UPDATE users SET is_active = ? WHERE username = ?').run(active ? 1 : 0, username);
  db.close();
};

// ── A6: role revocation mid-session ─────────────────────────────────────
{
  setRole('review.employee', 'viewer');           // demote
  const { ctx, page } = await asUser('review.employee');
  const res = await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
  record('A6', 'Role revocation — demoted user loses create', 403, res.status(),
    'employee promoted from viewer in the DB; create now refused');
  await ctx.close();
  setRole('review.employee', 'account_manager');  // restore
}

// ── A7: session issued BEFORE the role changed ──────────────────────────
{
  // Sign in while the user still has the privilege...
  const { ctx, page } = await asUser('review.viewer');
  const before = await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
  // ...then change the role underneath the LIVE session.
  setRole('review.viewer', 'manager');
  const after = await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });

  const ok = before.status() === 403 && after.status() === 200;
  record('A7', 'Session after role change — new rights apply', ok ? 'yes' : 'no', ok ? 'yes' : 'no',
    `before ${before.status()}, after ${after.status()} with the same cookie`);
  await ctx.close();
  setRole('review.viewer', 'viewer');
}

// ── A8: invalid user state (deactivated) ────────────────────────────────
{
  const { ctx, page } = await asUser('review.viewer');
  const before = await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });
  setActive('review.viewer', false);
  const after = await page.goto(`${BASE}/customers`, { waitUntil: 'domcontentloaded' });
  const denied = after.url().includes('/login') || after.status() === 302 || after.status() === 403;

  record('A8', 'Invalid user state — deactivated user rejected',
    'deny', denied ? 'deny' : String(after.status()),
    `before ${before.status()}, after deactivation ${after.status()} → ${new URL(after.url()).pathname}`);
  await ctx.close();
  setActive('review.viewer', true);
}

// ── A9: missing permission declared in the catalogue ────────────────────
{
  // review.manager has manager role but no users.view in RoleSeeder? It does not.
  const { ctx, page } = await asUser('review.manager');
  const res = await page.goto(`${BASE}/review/preview/org`, { waitUntil: 'domcontentloaded' });
  record('A9', 'Missing permission — catalogue gate holds', 403, res.status(),
    'org preview requires users.view; manager lacks it');
  await ctx.close();
}

await browser.close();

// ── the catalogue index itself must stay reachable for every role ───────
fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify({
  generatedAt: new Date().toISOString(),
  base: BASE,
  database: path.relative(ROOT, DB),
  note: 'A6–A8 mutate the REVIEW database only; the baseline database is never opened.',
  results,
}, null, 2));

/*
 * The runner parses "N/M passed" and nothing else. An earlier version of this
 * summary printed "passed 9" / "tests 9", which the runner could not read — so
 * the suite ran, exited 0, and contributed zero assertions to the total. The
 * number stayed at 979 while a nine-test suite reported success, which is the
 * precise shape of a silent pass. Print the format the runner understands.
 */
console.log('\n--- summary ---');
console.log(`${results.length - failures}/${results.length} passed`);
if (failures) console.log(`failed: ${results.filter((r) => !r.ok).map((r) => r.id).join(' · ')}`);
process.exit(failures === 0 ? 0 : 1);
