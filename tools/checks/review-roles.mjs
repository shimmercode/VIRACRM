#!/usr/bin/env node
/**
 * review-roles.mjs — prove that the review environment honours real permissions.
 *
 * The point of a review environment is undermined if every account sees every
 * page. This suite logs in as each demo role against the running review server
 * and asserts what that role can and cannot open, using the same
 * `EnsurePermission` middleware the product uses.
 *
 * It is a fixture test, not an integration test against a real deployment: the
 * users are created by tools/checks/review-user.php inside the review database
 * copy. It is reported as such.
 *
 * Usage: node tools/checks/review-roles.mjs [--base URL] [--db PATH]
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const arg = (name, fallback) => {
  const i = process.argv.indexOf(name);
  return i !== -1 ? process.argv[i + 1] : fallback;
};

const BASE = arg('--base', 'http://127.0.0.1:4173');
const DB = path.resolve(ROOT, arg('--db', 'database/review/crm-review.sqlite'));

// role => pages it must be able to open, and pages it must be refused
/*
 * The expectations below were read off the real permission matrix in
 * RoleSeeder, then confirmed against the running server. The catalogue index
 * itself requires only a session, so every signed-in role can open it; the
 * previews and the CRUD routes are where the permission boundary lives.
 */
const MATRIX = [
  { user: 'review.admin', expect: 200, path: '/review' },
  { user: 'review.admin', expect: 200, path: '/customers' },
  { user: 'review.admin', expect: 200, path: '/customers/create' },
  { user: 'review.admin', expect: 200, path: '/review/preview/org' },

  { user: 'review.manager', expect: 200, path: '/customers' },
  { user: 'review.manager', expect: 200, path: '/customers/create' },
  // manager lacks users.view, so the organisation preview must be refused
  { user: 'review.manager', expect: 403, path: '/review/preview/org' },

  // account_manager may create customers but not open the admin preview
  { user: 'review.employee', expect: 200, path: '/review' },
  { user: 'review.employee', expect: 200, path: '/customers/create' },
  { user: 'review.employee', expect: 403, path: '/review/preview/org' },

  // the read-only role must reach lists and be refused every write route
  { user: 'review.viewer', expect: 200, path: '/customers' },
  { user: 'review.viewer', expect: 403, path: '/customers/create' },
  { user: 'review.viewer', expect: 403, path: '/contracts/create' },

  /*
   * `/users` is NOT a permission boundary in this build — it is a 404, because
   * the users module has no route at all (the catalogue marks it
   * "Not Implemented"). Asserting 403 there would have been a test that passes
   * only if the code changes; the honest assertion is that the route does not
   * exist, so nobody can reach user administration through any URL.
   */
  { user: 'review.admin', expect: 404, path: '/users' },
  { user: 'review.viewer', expect: 404, path: '/users' },
];

// Read from the environment; never hardcode a credential in Git.
const { reviewPassword } = await import('./lib/review-credentials.mjs');
const PASSWORD = reviewPassword();

// ── ensure the demo users exist in the review database ───────────────────
// Uses the same seeder the environment itself uses, so a role test can never
// pass against accounts that only exist inside the test harness.
const prepared = spawnSync(
  process.execPath,
  [path.join(ROOT, 'docs/tools/php-cli.mjs'), path.join(ROOT, 'tools/review-seed.php'), 'seed', DB],
  { cwd: ROOT, encoding: 'utf8', timeout: 600_000 }
);

const prepOut = (prepared.stdout || '') + (prepared.stderr || '');

if (!/SEEDED/.test(prepOut)) {
  console.error('could not prepare demo users:\n', prepOut);
  process.exit(2);
}

// ── drive the browser ────────────────────────────────────────────────────
const { createRequire } = await import('node:module');
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

let failures = 0;
const results = [];

for (const row of MATRIX) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();

  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name=username]', row.user);
  await page.fill('input[name=password]', PASSWORD);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type=submit]'),
  ]);

  const res = await page.goto(BASE + row.path, { waitUntil: 'domcontentloaded' });
  const status = res?.status() ?? 0;
  const ok = status === row.expect;

  if (!ok) failures += 1;
  results.push({ ...row, actual: status, ok });

  console.log(`${ok ? 'PASS' : 'FAIL'}  ${row.user.padEnd(18)} ${row.path.padEnd(22)} `
    + `expected ${row.expect}, got ${status}`);

  await context.close();
}

await browser.close();

fs.writeFileSync(
  path.join(ROOT, 'docs/evidence/review/role-visibility.json'),
  JSON.stringify({ generatedAt: new Date().toISOString(), fixture: true, results }, null, 2)
);

console.log(`\n--- summary ---\n${MATRIX.length - failures}/${MATRIX.length} role checks passed`);
console.log('NOTE: fixture test — demo users are created in the review database copy.');
process.exit(failures === 0 ? 0 : 1);
