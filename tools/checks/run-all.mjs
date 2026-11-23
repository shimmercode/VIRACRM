#!/usr/bin/env node
/**
 * VIRA CRM — one command, whole regression suite.
 *
 *   node tools/checks/run-all.mjs                # FULL run (authoritative)
 *   node tools/checks/run-all.mjs --repeat 2     # full run, twice, must match
 *   node tools/checks/run-all.mjs --fast         # skips browser suites — NEVER authoritative
 *
 * EXIT CODES
 * ----------
 *   0  PASSED      — every required suite ran, every assertion passed
 *   1  FAILED      — a suite ran and failed, or isolation/repeatability broke
 *   2  INCOMPLETE  — a required suite did not run (e.g. `--fast`)
 *
 * The distinction matters. An incomplete run is not a passing run: `--fast` used
 * to print PASSED with exit 0 while silently skipping five suites, which is the
 * failure mode this file now exists to prevent. Any skip of a required suite
 * makes the verdict INCOMPLETE and the exit code non-zero, no matter how many
 * assertions passed.
 *
 * WHAT IS REQUIRED
 * ----------------
 * Every suite in SUITES is `required: true`. There is no per-suite opt-out; the
 * only way to skip is the global `--fast` flag, which is itself an explicit,
 * visible declaration whose result can never be reported as PASSED.
 *
 * WHY THE FLOWS NO LONGER INTERFERE
 * ---------------------------------
 * Each flow used to share one database, so it was order dependent: `authed-flow`
 * rotated the admin password, sessions lived in the same table, and
 * `crud-flow`'s inserted rows changed counts other checks asserted on.
 *
 * Now every flow builds a private database from the seeded baseline (see
 * `.arena/flows/<name>/`) and sets its own password. Browser suites go further
 * and start their own dev server on an OS-assigned port (lib/browser-flow.mjs),
 * so they no longer depend on a server already running on :8080 — that
 * dependency used to make them "unavailable" for reasons unrelated to the code.
 *
 * The runner asserts at the end that the shared database is unchanged in row
 * counts. `--repeat` runs the whole suite twice and fails if any suite's totals
 * differ.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

import { ROOT, php } from './lib/flow-harness.mjs';

const EXIT = { PASSED: 0, FAILED: 1, INCOMPLETE: 2 };

const argv = process.argv.slice(2);

if (argv.includes('--help') || argv.includes('-h')) {
  console.log(`VIRA CRM regression runner

  node tools/checks/run-all.mjs              full run — authoritative
  node tools/checks/run-all.mjs --repeat 2   full run twice, totals must match
  node tools/checks/run-all.mjs --fast       skip browser suites (exit 2, INCOMPLETE)

Exit codes: 0 passed, 1 failed, 2 incomplete (a required suite did not run).`);
  process.exit(0);
}

const fast = argv.includes('--fast');
const repeatIndex = argv.indexOf('--repeat');
const repeats = repeatIndex === -1 ? 1 : Math.max(1, Number(argv[repeatIndex + 1] || 2));

const NODE = process.execPath;

/**
 * The suite, in dependency order.
 *
 * `required: true` on every entry. The list is not order-dependent any more —
 * `--repeat` proves it — but a stable order keeps output readable.
 */
const SUITES = [
  { id: 'unit', label: 'Unit + feature tests', kind: 'php', script: 'tests/run.php', required: true },
  { id: 'views', label: 'Blade compilation', kind: 'php', script: 'tools/checks/views.php', required: true },
  { id: 'authorization', label: 'Authorization (PHP)', kind: 'php', script: 'tools/checks/authz.php', required: true },
  { id: 'flow-login', label: 'Flow: login', kind: 'node', script: 'tools/checks/login-flow.mjs', required: true },
  { id: 'flow-authed', label: 'Flow: authenticated', kind: 'node', script: 'tools/checks/authed-flow.mjs', required: true },
  { id: 'flow-authz', label: 'Flow: authorization', kind: 'node', script: 'tools/checks/authz-flow.mjs', required: true },
  { id: 'flow-crud', label: 'Flow: CRUD', kind: 'node', script: 'tools/checks/crud-flow.mjs', required: true },
  { id: 'css-audit', label: 'Token discipline', kind: 'node', script: 'tools/checks/css-audit.mjs', required: true },
  { id: 'contrast', label: 'Contrast', kind: 'node', script: 'tools/checks/contrast.mjs', required: true },
  { id: 'unstyled', label: 'Unstyled classes', kind: 'node', script: 'tools/checks/unstyled.mjs', required: true },
  { id: 'tokens', label: 'Undefined tokens', kind: 'node', script: 'tools/checks/tokens.mjs', required: true },
  { id: 'calendar', label: 'Jalali calendar', kind: 'browser', script: 'tools/checks/calendar.mjs', required: true },
  { id: 'a11y', label: 'Accessibility', kind: 'browser', script: 'tools/checks/a11y.mjs', required: true },
  { id: 'login-shots', label: 'Login page', kind: 'browser', script: 'tools/checks/ui-login-shots.mjs', required: true },
  { id: 'ui-shots', label: 'Layout (7 pages x 6 widths)', kind: 'browser', script: 'tools/checks/ui-shots.mjs', required: true },
  { id: 'table-cards', label: 'Responsive tables (cards on phones)', kind: 'browser', script: 'tools/checks/table-cards.mjs', required: true },
  { id: 'overflow-1024', label: 'No overflow at 768/1024/1440', kind: 'browser', script: 'tools/checks/overflow.mjs', required: true },
  { id: 'design-system', label: 'Design System components', kind: 'browser', script: 'tools/checks/design-system.mjs', required: true },
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

      // Exit code 2 from a check means "precondition unavailable" (for example
      // Playwright is not installed). That is a failure of the run, not a skip:
      // an unavailable suite has proved nothing about the code.
      resolve({ code, out, passed, total, ok: code === 0, unavailable: code === 2 });
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

const before = await sharedCounts();

const runs = [];

for (let pass = 1; pass <= repeats; pass++) {
  if (repeats > 1) console.log(`\n══════════ pass ${pass} of ${repeats} ══════════`);

  const passResults = [];

  for (const suite of SUITES) {
    // `--fast` is the ONLY way a suite is skipped, it is global, and it is
    // reported loudly here and in the verdict.
    if (fast && suite.kind === 'browser') {
      console.log(`SKIP  ${suite.id.padEnd(15)} ${suite.label}  (--fast: explicitly skipped)`);
      passResults.push({ ...suite, skipped: true, ok: null, passed: null, total: null });

      continue;
    }

    process.stdout.write(`RUN   ${suite.id.padEnd(15)} ${suite.label} … `);
    const started = Date.now();
    const result = await run(suite);
    const seconds = ((Date.now() - started) / 1000).toFixed(1);

    const rating = result.passed === null ? (result.ok ? 'OK' : 'FAIL') : `${result.passed}/${result.total}`;
    console.log(`${result.ok ? '✓' : '✗'} ${rating}  (${seconds}s)`);

    if (!result.ok) {
      if (result.unavailable) {
        console.log('        UNAVAILABLE — a precondition is missing (not a pass, not a skip)');
      }
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

// ── Counts: executed / passed / failed / skipped, always all four ─────────
const firstPass = runs[0];

const executedSuites = firstPass.filter((r) => !r.skipped);
const passedSuites = executedSuites.filter((r) => r.ok);
const failedSuites = executedSuites.filter((r) => !r.ok);
const skippedSuites = firstPass.filter((r) => r.skipped);
const skippedRequired = skippedSuites.filter((r) => r.required);

const totalAssertions = executedSuites.reduce((sum, r) => sum + (r.total || 0), 0);
const passedAssertions = executedSuites.reduce((sum, r) => sum + (r.passed || 0), 0);
const failedAssertions = totalAssertions - passedAssertions;

const allOk = failedSuites.length === 0;

// A required suite that did not run makes the run INCOMPLETE, never PASSED.
const complete = skippedRequired.length === 0;

console.log('\n══════════════════════════════════════════');
console.log(`suites     ${SUITES.length} total · ${executedSuites.length} executed · ${passedSuites.length} passed · ${failedSuites.length} failed · ${skippedSuites.length} skipped`);
console.log(`assertions ${totalAssertions} executed · ${passedAssertions} passed · ${failedAssertions} failed · 0 skipped`);

if (failedSuites.length) {
  console.log(`failed     ${failedSuites.map((r) => r.id).join(', ')}`);
}

if (skippedRequired.length) {
  console.log(`skipped    ${skippedRequired.map((r) => r.id).join(', ')} — REQUIRED, so this run proves nothing about them`);
}

console.log(`isolation  ${isolationOk ? 'shared database untouched' : 'SHARED DATABASE WAS MODIFIED'}`);
if (repeats > 1) console.log(`repeatable ${repeatOk ? 'yes' : 'NO'}`);

let verdict;
let exitCode;

if (!complete) {
  verdict = 'REGRESSION SUITE INCOMPLETE — required suites were skipped';
  exitCode = EXIT.INCOMPLETE;
} else if (!allOk || !isolationOk || !repeatOk) {
  verdict = 'REGRESSION SUITE FAILED';
  exitCode = EXIT.FAILED;
} else {
  verdict = 'REGRESSION SUITE PASSED';
  exitCode = EXIT.PASSED;
}

console.log(`\n${verdict}`);
console.log(`exit ${exitCode}`);

process.exit(exitCode);
