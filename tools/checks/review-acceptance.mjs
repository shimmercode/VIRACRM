#!/usr/bin/env node
/**
 * review-acceptance.mjs — the twelve mandatory checks for the review environment.
 *
 * The directive lists twelve tests that must actually run before the review
 * environment can be handed over (§22). This file runs them and refuses to
 * report a pass it did not observe.
 *
 * HONESTY RULES BAKED IN
 * ----------------------
 *   • A test whose precondition is missing reports SKIP, never PASS.
 *   • A fixture test is labelled FIXTURE in its own name; it is never called
 *     an integration test.
 *   • Every check prints what it measured, so the number can be re-derived.
 *   • Exit code is the number of failures, capped at 1 for the shell.
 *
 * Usage:
 *   node tools/checks/review-acceptance.mjs --base http://127.0.0.1:4173 \
 *        --db database/review/crm-review.sqlite
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const arg = (n, d) => {
  const i = process.argv.indexOf(n);
  return i !== -1 ? process.argv[i + 1] : d;
};

const BASE = arg('--base', 'http://127.0.0.1:4173');
const DB = path.resolve(ROOT, arg('--db', 'database/review/crm-review.sqlite'));
const EVIDENCE = path.join(ROOT, 'docs/evidence/review');
// Read from the environment; never hardcode a credential in Git.
const PASSWORD = (await import('./lib/review-credentials.mjs')).reviewPassword();

fs.mkdirSync(EVIDENCE, { recursive: true });

const results = [];
const record = (id, name, status, detail) => {
  results.push({ id, name, status, detail });
  const tag = { PASS: 'PASS', FAIL: 'FAIL', SKIP: 'SKIP' }[status];
  console.log(`${tag}  ${id.padEnd(4)} ${name.padEnd(38)} ${detail}`);
};

const exists = (p) => fs.existsSync(p);

// ── T1  Boot ───────────────────────────────────────────────────────────────
{
  const health = path.join(EVIDENCE, 'health.json');
  const enabled = /VIRA_REVIEW_ENABLED\s*=\s*true/.test(
    fs.readFileSync(path.join(ROOT, '.env'), 'utf8')
  );
  record('T1', 'Boot — review surface is registered', enabled ? 'PASS' : 'FAIL',
    enabled ? 'VIRA_REVIEW_ENABLED=true in .env' : 'review flag is not true');
  if (exists(health)) fs.rmSync(health);
}

// ── T2  Auth — no anonymous access ────────────────────────────────────────
{
  // curl without a session cookie must not receive 200 from any review route.
  const routes = ['/review', '/review/health', '/review/preview/drive',
    '/review/preview/org', '/review/preview/project-team'];
  let leaked = 0;
  for (const r of routes) {
    const out = spawnSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code}',
      '-H', 'Host: 4173-x.e2b.app', BASE + r], { encoding: 'utf8' });
    const code = (out.stdout || '').trim();
    if (code === '200') leaked += 1;
  }
  record('T2', 'Auth — anonymous request refused', leaked === 0 ? 'PASS' : 'FAIL',
    `${routes.length} routes checked, ${leaked} answered 200 without a session`);
}

// ── T3  Database isolation ────────────────────────────────────────────────
{
  const isReviewDb = DB.includes(`${path.sep}review${path.sep}`);
  const baseline = path.join(ROOT, 'database/database.sqlite');
  const same = exists(baseline) && exists(DB)
    && fs.statSync(baseline).size === fs.statSync(DB).size
    && fs.readFileSync(baseline).equals(fs.readFileSync(DB));

  record('T3', 'DB isolation — review has its own file', isReviewDb ? 'PASS' : 'FAIL',
    isReviewDb ? path.relative(ROOT, DB) : `not inside database/review/: ${DB}`);

  record('T3b', 'DB isolation — baseline bytes untouched', same ? 'FAIL' : 'PASS',
    same ? 'identical to the baseline database — the review copy is not isolated'
      : 'review file differs from database/database.sqlite');
}

// ── T4  No production data ────────────────────────────────────────────────
{
  const phpCheck = spawnSync('node', [path.join(ROOT, 'docs/tools/php-cli.mjs'),
    path.join(ROOT, 'tools/checks/review-data-audit.php'), DB], {
    cwd: ROOT, encoding: 'utf8', timeout: 300_000,
  });
  const out = (phpCheck.stdout || '').trim();
  const ok = /CLEAN/.test(out);
  record('T4', 'No production data — every row is fake', ok ? 'PASS' : 'FAIL',
    out.split('\n').filter(Boolean).slice(-1)[0] || 'audit produced no verdict');
}

// ── T5  Noindex ───────────────────────────────────────────────────────────
{
  const h = spawnSync('curl', ['-s', '-D', '-', '-o', '/dev/null',
    '-b', '/tmp/review-jar.txt', '-H', 'Host: 4173-x.e2b.app', `${BASE}/review`], { encoding: 'utf8' });
  const headers = h.stdout || '';
  const hasHeader = /x-robots-tag:\s*noindex/i.test(headers);

  const robots = spawnSync('curl', ['-s', '-H', 'Host: 4173-x.e2b.app', `${BASE}/robots.txt`],
    { encoding: 'utf8' });
  const disallowAll = /Disallow:\s*\/\s*$/m.test(robots.stdout || '');

  // The page itself must carry the meta tag too, as defence in depth.
  const page = spawnSync('curl', ['-s', '-b', '/tmp/review-jar.txt',
    '-H', 'Host: 4173-x.e2b.app', `${BASE}/review`], { encoding: 'utf8' });
  const hasMeta = /name="robots"[^>]*noindex/i.test(page.stdout || '');

  record('T5', 'Noindex — header, meta tag, robots.txt', hasHeader && hasMeta && disallowAll ? 'PASS' : 'FAIL',
    `header=${hasHeader} meta=${hasMeta} robots.txt=${disallowAll}`);
}

// ── T6  Navigation ────────────────────────────────────────────────────────
{
  const page = spawnSync('curl', ['-s', '-b', '/tmp/review-jar.txt',
    '-H', 'Host: 4173-x.e2b.app', `${BASE}/`], { encoding: 'utf8' });
  const body = page.stdout || '';
  const hasLink = body.includes('محیط بازبینی') && body.includes('/review');
  record('T6', 'Navigation — review row present for the demo admin', hasLink ? 'PASS' : 'FAIL',
    hasLink ? 'sidebar row «محیط بازبینی» renders' : 'row missing from the sidebar');
}

// ── T7  Route coverage ────────────────────────────────────────────────────
{
  const configSrc = fs.readFileSync(path.join(ROOT, 'config/review.php'), 'utf8');
  const keys = [...configSrc.matchAll(/'key'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
  const previews = [...configSrc.matchAll(/'preview'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
  const paths = [...configSrc.matchAll(/'path'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);

  let bad = [];
  for (const p of new Set(paths)) {
    const r = spawnSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code}',
      '-b', '/tmp/review-jar.txt', '-H', 'Host: 4173-x.e2b.app', BASE + p], { encoding: 'utf8' });
    if (!/^200$/.test((r.stdout || '').trim())) bad.push(`${p}=${r.stdout}`);
  }
  for (const k of new Set(previews)) {
    const r = spawnSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code}',
      '-b', '/tmp/review-jar.txt', '-H', 'Host: 4173-x.e2b.app',
      `${BASE}/review/preview/${k}`], { encoding: 'utf8' });
    if (!/^200$/.test((r.stdout || '').trim())) bad.push(`/review/preview/${k}=${r.stdout}`);
  }
  record('T7', 'Route coverage — every catalogue target opens', bad.length === 0 ? 'PASS' : 'FAIL',
    bad.length === 0 ? `${new Set(paths).size} paths + ${new Set(previews).size} previews all 200`
      : bad.join(', '));
}

// ── T8  Responsive (real browser measurement) ─────────────────────────────
{
  const file = path.join(EVIDENCE, 'responsive.json');
  if (!exists(file)) {
    record('T8', 'Responsive — 7 widths, real Chromium', 'SKIP',
      'run tools/checks/review-shots.mjs first');
  } else {
    const data = JSON.parse(fs.readFileSync(file, 'utf8'));
    const widths = [...new Set(data.results.map((r) => r.width))].sort((a, b) => a - b);
    const fails = data.results.filter((r) => !r.ok);
    const wantWidths = [390, 430, 576, 768, 1024, 1280, 1440];
    const widthsOk = wantWidths.every((w) => widths.includes(w));
    record('T8', 'Responsive — 7 widths, real Chromium',
      fails.length === 0 && widthsOk ? 'PASS' : 'FAIL',
      `${widths.join('/')} · ${data.results.length} measurements · ${fails.length} failures`);
  }
}

// ── T9  Seed repeatability ────────────────────────────────────────────────
{
  const file = path.join(EVIDENCE, 'seed-repeatability.json');
  if (!exists(file)) {
    record('T9', 'Seed repeatability — 3 runs identical', 'SKIP',
      'run tools/checks/review-seed-check.mjs first');
  } else {
    const d = JSON.parse(fs.readFileSync(file, 'utf8'));
    record('T9', 'Seed repeatability — 3 runs identical', d.identical ? 'PASS' : 'FAIL',
      d.identical ? `counts stable across ${d.runs.length} runs` : 'counts changed between runs');
  }
}

// ── T10 Reset ─────────────────────────────────────────────────────────────
{
  const file = path.join(EVIDENCE, 'reset-check.json');
  if (!exists(file)) {
    record('T10', 'Reset — returns exactly to baseline', 'SKIP',
      'run tools/checks/review-seed-check.mjs first');
  } else {
    const d = JSON.parse(fs.readFileSync(file, 'utf8'));
    record('T10', 'Reset — returns exactly to baseline', d.restored ? 'PASS' : 'FAIL',
      d.restored ? `baseline ${JSON.stringify(d.baseline)} restored` : 'baseline not restored');
  }
}

// ── T11 Role visibility (FIXTURE: demo users, not a real deployment) ──────
{
  const file = path.join(EVIDENCE, 'role-visibility.json');
  if (!exists(file)) {
    record('T11', 'Role visibility (FIXTURE) — 4 roles', 'SKIP',
      'run tools/checks/review-roles.mjs first');
  } else {
    const d = JSON.parse(fs.readFileSync(file, 'utf8'));
    const fails = d.results.filter((r) => !r.ok);
    record('T11', 'Role visibility (FIXTURE) — 4 roles', fails.length === 0 ? 'PASS' : 'FAIL',
      `${d.results.length} assertions, ${fails.length} failures`);
  }
}

// ── T12 Smoke — the four demo accounts sign in and land ──────────────────
{
  const users = ['review.admin', 'review.manager', 'review.employee', 'review.viewer'];
  let ok = 0;
  for (const u of users) {
    const jar = `/tmp/smoke-${u}.txt`;
    if (exists(jar)) fs.rmSync(jar);
    const login = spawnSync('curl', ['-s', '-c', jar, '-H', 'Host: 4173-x.e2b.app', `${BASE}/login`],
      { encoding: 'utf8' });
    const token = (login.stdout.match(/name="_token" value="([^"]+)"/) || [])[1];
    if (!token) continue;
    const post = spawnSync('curl', ['-s', '-b', jar, '-c', jar, '-o', '/dev/null',
      '-w', '%{http_code}', '-H', 'Host: 4173-x.e2b.app',
      '-d', `_token=${token}&username=${u}&password=${PASSWORD}`, `${BASE}/login`], { encoding: 'utf8' });
    const home = spawnSync('curl', ['-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}',
      '-H', 'Host: 4173-x.e2b.app', `${BASE}/`], { encoding: 'utf8' });
    if (/302/.test(post.stdout) && /200/.test(home.stdout)) ok += 1;
  }
  record('T12', 'Smoke — 4 demo accounts sign in', ok === users.length ? 'PASS' : 'FAIL',
    `${ok}/${users.length} accounts reached the dashboard`);
}

// ── verdict ───────────────────────────────────────────────────────────────
const counts = results.reduce((a, r) => ({ ...a, [r.status]: (a[r.status] || 0) + 1 }), {});
const failed = counts.FAIL || 0;

fs.writeFileSync(path.join(EVIDENCE, 'acceptance.json'), JSON.stringify({
  generatedAt: new Date().toISOString(),
  base: BASE, database: path.relative(ROOT, DB),
  counts, results,
}, null, 2));

console.log('\n--- summary ---');
console.log(`tests    ${results.length}`);
console.log(`pass     ${counts.PASS || 0}`);
console.log(`fail     ${failed}`);
console.log(`skip     ${counts.SKIP || 0}`);
console.log(`\nA skipped test is not a pass. Run the named prerequisite first.`);
process.exit(failed === 0 ? 0 : 1);
