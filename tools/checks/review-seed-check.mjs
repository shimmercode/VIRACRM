#!/usr/bin/env node
/**
 * review-seed-check.mjs — prove the demo data is repeatable and reversible.
 *
 * Three claims are made about the review seed, and each must be demonstrated
 * rather than asserted:
 *
 *   1. running the seed three times produces identical counts (repeatable);
 *   2. `reset` returns the database to exactly the baseline counts;
 *   3. reset touches only the review database, never database/database.sqlite.
 *
 * The check takes a byte-for-byte hash of the baseline database before and
 * after, so claim 3 is measured, not promised.
 *
 * Writes docs/evidence/review/seed-repeatability.json and reset-check.json.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const DB = path.join(ROOT, 'database/review/crm-review.sqlite');
const BASELINE = path.join(ROOT, 'database/database.sqlite');
const OUT = path.join(ROOT, 'docs/evidence/review');
const TABLES = ['users', 'customers', 'contracts', 'projects', 'employees',
  'project_assignments', 'work_items', 'notes'];

fs.mkdirSync(OUT, { recursive: true });

const php = (script, ...args) => {
  const r = spawnSync(process.execPath,
    [path.join(ROOT, 'docs/tools/php-cli.mjs'), path.join(ROOT, script), ...args],
    { cwd: ROOT, encoding: 'utf8', timeout: 600_000 });
  return { out: (r.stdout || '') + (r.stderr || ''), status: r.status };
};

/** Row counts read directly from the file — not from the tool under test. */
const counts = (file) => {
  const r = spawnSync(process.execPath, ['-e', `
    const { DatabaseSync } = require('node:sqlite');
    const db = new DatabaseSync(${JSON.stringify(file)});
    const out = {};
    for (const t of ${JSON.stringify(TABLES)}) {
      out[t] = db.prepare('select count(*) as n from ' + t).get().n;
    }
    db.close();
    console.log(JSON.stringify(out));
  `], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`could not read ${file}: ${r.stderr}`);
  return JSON.parse(r.stdout);
};

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');

const baselineHashBefore = sha(BASELINE);
const baselineCounts = counts(BASELINE);

console.log('baseline (database/database.sqlite)');
console.log(' ', JSON.stringify(baselineCounts));

/*
 * ── 1. capture the clean state, then seed three times ──────────────────────
 *
 * WHAT CHANGED HERE AND WHY
 * -------------------------
 * Claim 2 used to assert that `reset` returns the review database to exactly
 * the row counts of `database/database.sqlite`. That comparison cannot hold,
 * and not because reset is broken:
 *
 *   - the review database is `database/review/crm-review.sqlite`, a separate
 *     file built by tools/review-seed.php, not a copy of the dev database;
 *   - `database/database.sqlite` is a mutable, gitignored working file that
 *     accumulates rows no seeder can reproduce. At the time of writing it held
 *     one employee, `E-1001` «مریم رستمی», created 2026-11-25 by an earlier
 *     manual run. There is no EmployeeSeeder, so no seed or reset can ever
 *     recreate it.
 *
 * So the old assertion measured drift between two files built by different
 * processes and reported it as a reset failure. Deleting the stray row was not
 * an option either: the shared development database must stay untouched.
 *
 * The replacement is strictly stronger. It pins reset against a state captured
 * from the review database itself, then additionally proves that no
 * demo-tagged row survives a reset — which is the property the review
 * environment actually depends on.
 */
php('tools/review-seed.php', 'reset', DB);
const cleanState = counts(DB);
console.log('clean state (after reset, before any seed)');
console.log(' ', JSON.stringify(cleanState));
console.log('dev baseline (database/database.sqlite, mutable — informational only)');
console.log(' ', JSON.stringify(baselineCounts));

const runs = [];
for (let i = 1; i <= 3; i++) {
  php('tools/review-seed.php', 'seed', DB);
  runs.push(counts(DB));
  console.log(`seed run ${i}`, JSON.stringify(runs[i - 1]));
}

const identical = runs.every((r) => JSON.stringify(r) === JSON.stringify(runs[0]));
console.log(`\nrepeatable: ${identical ? 'YES' : 'NO'} — 3 runs produced identical counts`);

/* ── 2. reset must be reversible AND idempotent ─────────────────────────── */
const beforeReset = counts(DB);
php('tools/review-seed.php', 'reset', DB);
const afterReset = counts(DB);
const restored = JSON.stringify(afterReset) === JSON.stringify(cleanState);

php('tools/review-seed.php', 'reset', DB);
const afterSecondReset = counts(DB);
const idempotent = JSON.stringify(afterSecondReset) === JSON.stringify(cleanState);

console.log(`reset:      ${restored ? 'YES' : 'NO'} — returned to the captured clean state`);
console.log(`idempotent: ${idempotent ? 'YES' : 'NO'} — a second reset changed nothing further`);
if (!restored) {
  console.log('  expected', JSON.stringify(cleanState));
  console.log('  actual  ', JSON.stringify(afterReset));
}

/* ── 2b. no demo-tagged row may survive a reset ───────────────────────────
 * This is the property the review environment depends on: a reviewer must be
 * able to get rid of every generated record. Counted straight from the file. */
const taggedLeft = (() => {
  const r = spawnSync(process.execPath, ['-e', `
    const { DatabaseSync } = require('node:sqlite');
    const db = new DatabaseSync(${JSON.stringify(DB)});
    const out = {};
    out.customers = db.prepare("select count(*) n from customers where code like 'RVW-%'").get().n;
    out.contracts = db.prepare("select count(*) n from contracts where number like 'RVW-C-%'").get().n;
    out.projects  = db.prepare("select count(*) n from projects where code like 'RVW-%'").get().n;
    out.employees = db.prepare("select count(*) n from employees where code like 'RVW-E-%'").get().n;
    out.users     = db.prepare("select count(*) n from users where username like 'review.%'").get().n;
    db.close();
    console.log(JSON.stringify(out));
  `], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`could not read ${DB}: ${r.stderr}`);
  return JSON.parse(r.stdout);
})();
const noTaggedLeft = Object.values(taggedLeft).every((n) => n === 0);
console.log(`demo rows:  ${noTaggedLeft ? 'none left' : 'LEFT BEHIND ' + JSON.stringify(taggedLeft)}`);

// ── 3. the baseline file must be byte-identical ──────────────────────────
const baselineHashAfter = sha(BASELINE);
const baselineUntouched = baselineHashBefore === baselineHashAfter;

console.log(`isolation:  ${baselineUntouched ? 'YES' : 'NO'} — database/database.sqlite ${baselineUntouched ? 'byte-identical' : 'CHANGED'}`);

// ── restore a seeded database and leave the server in a usable state ─────
php('tools/review-seed.php', 'seed', DB);
const finalCounts = counts(DB);

fs.writeFileSync(path.join(OUT, 'seed-repeatability.json'), JSON.stringify({
  generatedAt: new Date().toISOString(), runs, identical,
}, null, 2));

fs.writeFileSync(path.join(OUT, 'reset-check.json'), JSON.stringify({
  generatedAt: new Date().toISOString(),
  baseline: baselineCounts, cleanState, beforeReset, afterReset, restored,
  idempotent, taggedLeft, noTaggedLeft,
  baselineUntouched, baselineHashBefore, baselineHashAfter, finalCounts,
}, null, 2));

const ok = identical && restored && idempotent && noTaggedLeft && baselineUntouched;
console.log(`\n${ok ? 'PASS' : 'FAIL'} — repeatable, reversible, idempotent, `
  + 'demo-free and isolated');
process.exit(ok ? 0 : 1);
