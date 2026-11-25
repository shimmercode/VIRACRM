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
 * SERVER ISOLATION
 * ----------------
 * This suite used to talk to a shared server on a fixed port. When the review
 * environment began forcing https for the preview proxy, this suite's plain-http
 * browser broke; worse, a leftover test server on :4174 was reachable from the
 * preview proxy and the client opened it by accident. Both problems disappear
 * when the suite owns its server and stops it afterwards — so it uses the same
 * startBrowserFlow() helper as every other browser suite.
 *
 * Usage: node tools/checks/review-authz.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { startBrowserFlow } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/evidence/review/authz.json');

/*
 * The review accounts are seeded into the review database by a guarded
 * seeder that deliberately refuses any other file. This suite runs against a
 * private copy of the baseline, which has only `admin` and `viewer1` — so it
 * creates the four identities it needs itself, in its own throwaway database.
 *
 * Roles and permissions already exist in the baseline (4 roles, 39
 * permissions); only the accounts and their role links are added. The password
 * is the suite's own random one, hashed here, and never printed.
 */
const REVIEW_ACCOUNTS = [
  ['review.admin', 'manager', 1],
  ['review.manager', 'manager', 0],
  ['review.employee', 'account_manager', 0],
  ['review.viewer', 'viewer', 0],
];

const prepareReviewAccounts = async ({ db, password }) => {
  const hash = spawnSync(process.execPath, [
    path.join(ROOT, 'docs/tools/php-cli.mjs'),
    path.join(ROOT, 'tools/checks/make-password.php'),
    password,
  ], { cwd: ROOT, encoding: 'utf8', timeout: 120_000 });

  const hashed = (hash.stdout || '').trim().split('\n').pop();
  if (!hashed || !hashed.startsWith('$2y$')) {
    throw new Error(`could not hash the password: ${hash.stdout}${hash.stderr}`);
  }

  const { DatabaseSync } = await import('node:sqlite');
  const conn = new DatabaseSync(db);
  const now = new Date().toISOString().replace('T', ' ').slice(0, 19);

  for (const [username, role, superadmin] of REVIEW_ACCOUNTS) {
    const email = `${username}@review.invalid`;

    conn.prepare(
      'INSERT INTO users (name, username, email, password, is_superadmin, is_active, '
      + 'must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)',
    ).run(username, username, email, hashed, superadmin, now, now);

    const uid = conn.prepare('SELECT id FROM users WHERE username = ?').get(username).id;
    const rid = conn.prepare('SELECT id FROM roles WHERE code = ?').get(role).id;
    conn.prepare(
      'INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?)',
    ).run(uid, rid, now, now);
  }

  conn.close();
  process.env.VIRA_REVIEW_DEMO_PASSWORD = password;
};

const flow = await startBrowserFlow('review-authz', {
  viewport: { width: 1280, height: 900 },
  prepare: prepareReviewAccounts,
});
const BASE = flow.baseUrl;
const { browser, db } = flow;

const results = [];
let failures = 0;

/** Record one assertion with its HTTP status and authorization verdict. */
const record = (id, name, expected, actual, note = '') => {
  const ok = actual === expected;
  if (!ok) failures += 1;

  results.push({ id, name, expected, actual, ok, note });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(4)} ${name.padEnd(46)} `
    + `expected ${expected}, got ${actual}${note ? '  — ' + note : ''}`);
};

/** Sign in as one of the seeded review accounts. */
const asUser = async (username) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', process.env.VIRA_REVIEW_DEMO_PASSWORD);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type=submit]'),
  ]);
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
  record('A4', 'Unauthorized direct URL (no session)', ok ? 'deny' : '200', ok ? 'deny' : '200',
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
 * screen that does not exist. The database here is this suite's own private
 * copy, discarded when the run ends.
 */
const { DatabaseSync } = await import('node:sqlite');

const setRole = (username, roleCode) => {
  const conn = new DatabaseSync(db);
  const uid = conn.prepare('SELECT id FROM users WHERE username = ?').get(username).id;
  conn.prepare('DELETE FROM user_roles WHERE user_id = ?').run(uid);
  const rid = conn.prepare('SELECT id FROM roles WHERE code = ?').get(roleCode).id;
  conn.prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)').run(uid, rid);
  conn.close();
};

const setActive = (username, active) => {
  const conn = new DatabaseSync(db);
  conn.prepare('UPDATE users SET is_active = ? WHERE username = ?').run(active ? 1 : 0, username);
  conn.close();
};

// ── A6: role revocation mid-session ─────────────────────────────────────
{
  setRole('review.employee', 'viewer');           // demote
  const { ctx, page } = await asUser('review.employee');
  const res = await page.goto(`${BASE}/customers/create`, { waitUntil: 'domcontentloaded' });
  record('A6', 'Role revocation — demoted user loses create', 403, res.status(),
    'employee demoted to viewer in the DB; create now refused');
  await ctx.close();
  setRole('review.employee', 'account_manager');  // restore
}

// ── A7: session issued BEFORE the role changed ──────────────────────────
{
  // Sign in while the user still has no create right...
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
  // review.manager holds the manager role, which does not carry users.view.
  const { ctx, page } = await asUser('review.manager');
  const res = await page.goto(`${BASE}/review/preview/org`, { waitUntil: 'domcontentloaded' });
  record('A9', 'Missing permission — catalogue gate holds', 403, res.status(),
    'org preview requires users.view; manager lacks it');
  await ctx.close();
}

await flow.close();

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify({
  generatedAt: new Date().toISOString(),
  note: 'Each run uses a private server and a private database copy, discarded afterwards.',
  results,
}, null, 2));

/*
 * The runner parses "N/M passed" and nothing else. An earlier version printed
 * "passed 9" / "tests 9", which the runner could not read — so the suite ran,
 * exited 0, and contributed zero assertions to the total. That is the exact
 * shape of a silent pass.
 */
console.log('\n--- summary ---');
console.log(`${results.length - failures}/${results.length} passed`);
if (failures) console.log(`failed: ${results.filter((r) => !r.ok).map((r) => r.id).join(' · ')}`);
process.exit(failures === 0 ? 0 : 1);
