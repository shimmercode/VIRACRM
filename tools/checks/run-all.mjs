#!/usr/bin/env node
/**
 * VIRA CRM — one command, whole regression suite.
 *
 *   node tools/checks/run-all.mjs              # everything
 *   node tools/checks/run-all.mjs --fast       # skip the browser suites
 *   node tools/checks/run-all.mjs --repeat 2   # prove it is order-independent
 *
 * WHY THE FLOWS NO LONGER INTERFERE
 * ---------------------------------
 * Each flow used to share one database, so it was order dependent: `authed-flow`
 * rotated the admin password, sessions lived in the same table, and
 * `crud-flow`'s inserted rows changed counts other checks asserted on. Running
 * them in one loop gave a reproducible `1/13` for `crud-flow`.
 *
 * Now every flow builds a private database from the seeded baseline (see
 * `.arena/flows/<name>/`) and sets its own password. The runner executes them
 * sequentially in one process, each in its own child, and asserts at the end
 * that the shared database is byte-for-byte unchanged in row counts. `--repeat`
 * runs the whole suite twice and fails if any flow's totals differ.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

import { ROOT, php } from './lib/flow-harness.mjs';

const argv = process.argv.slice(2);
const fast = argv.includes('--fast');
const repeatIndex = argv.indexOf('--repeat');
const repeats = repeatIndex === -1 ? 1 : Math.max(1, Number(argv[repeatIndex + 1] || 2));

const NODE = process.execPath;

/**
 * The suite, in dependency order. Browser suites need the dev server on :8080;
 * they are skipped with a clear message rather than silently passing when it is
 * not running.
 */
const SUITES = [
  { id: 'unit', label: 'Unit + feature tests', kind: 'php', script: 'tests/run.php' },
  { id: 'views', label: 'Blade compilation', kind: 'php', script: 'tools/checks/views.php' },
  { id: 'authorization', label: 'Authorization (PHP)', kind: 'php', script: 'tools/checks/authz.php' },
  { id: 'flow-login', label: 'Flow: login', kind: 'node', script: 'tools/checks/login-flow.mjs' },
  { id: 'flow-authed', label: 'Flow: authenticated', kind: 'node', script: 'tools/checks/authed-flow.mjs' },
  { id: 'flow-authz', label: 'Flow: authorization', kind: 'node', script: 'tools/checks/authz-flow.mjs' },
  { id: 'flow-crud', label: 'Flow: CRUD', kind: 'node', script: 'tools/checks/crud-flow.mjs' },
  { id: 'css-audit', label: 'Token discipline', kind: 'node', script: 'tools/checks/css-audit.mjs' },
  { id: 'contrast', label: 'Contrast', kind: 'node', script: 'tools/checks/contrast.mjs' },
  { id: 'unstyled', label: 'Unstyled classes', kind: 'node', script: 'tools/checks/unstyled.mjs' },
  { id: 'tokens', label: 'Undefined tokens', kind: 'node', script: 'tools/checks/tokens.mjs' },
  { id: 'calendar', label: 'Jalali calendar', kind: 'browser', script: 'tools/checks/calendar.mjs' },
  { id: 'a11y', label: 'Accessibility', kind: 'browser', script: 'tools/checks/a11y.mjs' },
  { id: 'login-shots', label: 'Login page', kind: 'browser', script: 'tools/checks/ui-login-shots.mjs' },
  { id: 'ui-shots', label: 'Layout (7 pages x 6 widths)', kind: 'browser', script: 'tools/checks/ui-shots.mjs' },
  { id: 'table-cards', label: 'Responsive tables (cards on phones)', kind: 'browser', script: 'tools/checks/table-cards.mjs' },
];

/** Run a script and capture its output plus a parsed "passed/total". */
function run(suite) {
  return new Promise((resolve) => {
    const child = spawn(NODE, [suite.kind === 'php' ? 'docs/tools/php-cli.mjs' : suite.script,
      ...(suite.kind === 'php' ? [suite.script] : [])], {
      cwd: ROOT,
      env: { ...process.env, PHP_WASM_PATH: process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules' },
    });

    let out = '';
    child.stdout.on('data', (d) => { out += d; });
    child.stderr.on('data', (d) => { out += d; });

    const timer = setTimeout(() => child.kill('SIGKILL'), 900_000);
    child.on('close', (code) => {
      clearTimeout(timer);

      // "12/12 passed", "tests: 7 | passed: 7", "compiled: 29, failed: 0"
      const fraction = out.match(/(\d+)\/(\d+)\s+passed/);
      const failed = out.match(/failed:\s*(\d+)/);
      const compiled = out.match(/compiled:\s*(\d+)/);
      const phpTests = out.match(/tests:\s*(\d+)\s*\|\s*passed:\s*(\d+)\s*\|\s*failed:\s*(\d+)/);

      let passed = null;
      let total = null;

      if (phpTests) {
        passed = Number(phpTests[2]);
        total = Number(phpTests[1]);
      } else if (fraction) {
        passed = Number(fraction[1]);
        total = Number(fraction[2]);
      } else if (compiled && failed) {
        passed = Number(compiled[1]);
        total = Number(compiled[1]) + Number(failed[1]);
      }

      resolve({ code, out, passed, total, ok: code === 0 });
    });
  });
}

/** Row counts of the shared database, to prove the flows did not touch it. */
async function sharedCounts() {
  const tables = ['customers', 'contracts', 'projects', 'work_items', 'users', 'sessions', 'audit_logs'];
  const counts = {};

  for (const table of tables) {
    const result = await php('tools/checks/flow-query.php', [
      path.join(ROOT, 'database/database.sqlite'),
      `select count(*) from ${table}`,
    ]);
    counts[table] = result.out.trim();
  }

  return counts;
}

/** Is the dev server listening? Browser suites need it. */
async function serverUp() {
  try {
    const res = await fetch('http://127.0.0.1:8080/login', { signal: AbortSignal.timeout(5000) });

    return res.ok;
  } catch {
    return false;
  }
}

const browserAvailable = await serverUp();
const before = await sharedCounts();

const runs = [];
let allOk = true;

for (let pass = 1; pass <= repeats; pass++) {
  if (repeats > 1) console.log(`\n══════════ pass ${pass} of ${repeats} ══════════`);

  const passResults = [];

  for (const suite of SUITES) {
    if (suite.kind === 'browser' && (!browserAvailable || fast)) {
      const why = fast ? '--fast' : 'dev server not running on :8080';
      console.log(`SKIP  ${suite.id.padEnd(15)} ${suite.label}  (${why})`);
      passResults.push({ ...suite, skipped: true });

      continue;
    }

    process.stdout.write(`RUN   ${suite.id.padEnd(15)} ${suite.label} … `);
    const started = Date.now();
    const result = await run(suite);
    const seconds = ((Date.now() - started) / 1000).toFixed(1);

    const rating = result.passed === null ? (result.ok ? 'OK' : 'FAIL') : `${result.passed}/${result.total}`;
    console.log(`${result.ok ? '✓' : '✗'} ${rating}  (${seconds}s)`);

    if (!result.ok) {
      allOk = false;
      // Show only the failing lines, so a failure is visible without scrolling
      // through 178 passing assertions.
      for (const line of result.out.split('\n').filter((l) => /^\s*FAIL/.test(l))) {
        console.log(`        ${line.trim()}`);
      }
    }

    passResults.push({ ...suite, ...result, skipped: false });
  }

  runs.push(passResults);
}

const after = await sharedCounts();

// ── The isolation assertion: the shared database must be untouched ─────────
console.log('\n── shared database untouched ──');
let isolationOk = true;

for (const table of Object.keys(before)) {
  const same = before[table] === after[table];
  if (!same) isolationOk = false;
  console.log(`${same ? '  ok ' : '  BAD'} ${table.padEnd(12)} ${before[table]} -> ${after[table]}`);
}

// ── Repeatability: every pass must produce the same totals per suite ──────
let repeatOk = true;

if (repeats > 1) {
  console.log('\n── repeatability ──');

  const signature = (passResults) => passResults
    .map((r) => (r.skipped ? `${r.id}:skipped` : `${r.id}:${r.passed}/${r.total}`))
    .join('|');

  const first = signature(runs[0]);

  for (let i = 1; i < runs.length; i++) {
    const same = signature(runs[i]) === first;
    if (!same) repeatOk = false;
    console.log(`${same ? '  ok ' : '  BAD'} pass ${i + 1} matches pass 1`);
  }

  console.log(`  ${repeatOk ? 'ok ' : 'BAD'} suite totals are identical across ${repeats} runs`);
}

// ── Summary ────────────────────────────────────────────────────────────────
const executed = runs[0].filter((r) => !r.skipped);
const skipped = runs[0].filter((r) => r.skipped);
const totalAssertions = executed.reduce((sum, r) => sum + (r.total || 0), 0);
const passedAssertions = executed.reduce((sum, r) => sum + (r.passed || 0), 0);
const failedSuites = executed.filter((r) => !r.ok);

console.log('\n══════════════════════════════════════════');
console.log(`suites:     ${executed.length} executed, ${skipped.length} skipped`);
console.log(`assertions: ${passedAssertions}/${totalAssertions}`);
console.log(`failed:     ${failedSuites.length ? failedSuites.map((r) => r.id).join(', ') : 'none'}`);
console.log(`isolation:  ${isolationOk ? 'shared database untouched' : 'SHARED DATABASE WAS MODIFIED'}`);
if (repeats > 1) console.log(`repeatable: ${repeatOk ? 'yes' : 'NO'}`);

const success = allOk && isolationOk && repeatOk;

console.log(`\n${success ? 'REGRESSION SUITE PASSED' : 'REGRESSION SUITE FAILED'}`);

process.exit(success ? 0 : 1);
