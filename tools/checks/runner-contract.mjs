#!/usr/bin/env node
/**
 * VIRA CRM — the regression runner's own contract.
 *
 * `run-all.mjs` is the thing CI will trust, so its exit codes are tested the
 * same way the product is: by making it fail on purpose and observing the code,
 * never by reading its output text.
 *
 * Contract under test:
 *
 *   0  PASSED      every required suite ran and passed
 *   1  FAILED      a suite ran and failed
 *   2  INCOMPLETE  a required suite did not run (--fast)
 *
 * Each case runs the real runner as a child process with its suite list
 * injected, so this is fast and does not depend on the full 18-suite runtime.
 *
 * WHY A CHILD PROCESS AND NOT A FUNCTION CALL
 * -------------------------------------------
 * The contract is about `process.exit(code)`. Anything that imported the runner
 * and inspected a returned value would not be testing what CI sees.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(34)} ${detail}`);
};

/**
 * Run the real runner with a stubbed suite list.
 *
 * `VIRA_RUNNER_SUITES_FILE` lets this test replace the suite list without
 * editing run-all.mjs, so the production path is exercised unchanged.
 */
function runRunner(args, suites) {
  const suiteFile = path.join(ROOT, '.arena', `runner-suites-${process.pid}.json`);
  fs.mkdirSync(path.dirname(suiteFile), { recursive: true });
  fs.writeFileSync(suiteFile, JSON.stringify(suites), 'utf8');

  // `--force-lock` is deliberate. This suite exists to drive the runner as a
  // child process and inspect its exit code, so it would otherwise deadlock:
  // the parent `run-all` already holds `.arena/run.lock`, and every child here
  // would be refused with exit 3. The children are stubs that never touch the
  // database, and they run strictly one at a time (spawnSync), so there is no
  // real concurrency to guard against. The lock's own behaviour is tested
  // separately and for real by tools/checks/lock-concurrency.mjs, which spawns
  // genuinely concurrent processes.
  const out = spawnSync(
    process.execPath,
    ['tools/checks/run-all.mjs', '--force-lock', ...args],
    {
      cwd: ROOT,
      encoding: 'utf8',
      env: {
        ...process.env,
        VIRA_RUNNER_SUITES_FILE: suiteFile,
        PHP_WASM_PATH: process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules',
      },
      timeout: 300_000,
    }
  );

  fs.rmSync(suiteFile, { force: true });

  return { code: out.status, stdout: out.stdout || '', stderr: out.stderr || '' };
}

// A suite that always passes, and one that always fails. Written to .arena so
// they never enter the repository.
const ARENA = path.join(ROOT, '.arena');
fs.mkdirSync(ARENA, { recursive: true });

const OK_SUITE = path.join(ARENA, 'runner-ok.mjs');
const FAIL_SUITE = path.join(ARENA, 'runner-fail.mjs');

fs.writeFileSync(OK_SUITE, [
  'console.log("PASS  T-STUB-1  stub");',
  'console.log("");',
  'console.log("--- summary ---");',
  'console.log("1/1 passed");',
  'process.exit(0);',
].join('\n'));

fs.writeFileSync(FAIL_SUITE, [
  'console.log("FAIL  T-STUB-1  stub");',
  'console.log("");',
  'console.log("--- summary ---");',
  'console.log("0/1 passed");',
  'process.exit(1);',
].join('\n'));

const rel = (p) => path.relative(ROOT, p);

// ── Case 1: all required suites pass → 0 ────────────────────────────────
{
  const r = runRunner([], [
    { id: 'stub-ok', label: 'stub passing suite', kind: 'node', script: rel(OK_SUITE), required: true },
  ]);

  // Exit code is the assertion. Output text is reported only for diagnosis.
  record('T-RUNNER-exit0', r.code === 0, `exit=${r.code} (expected 0 = PASSED)`);

  // The verdict word must agree with the code, but the CODE decides.
  record('T-RUNNER-exit0-word', /REGRESSION SUITE PASSED/.test(r.stdout),
    'verdict text says PASSED (informational only)');
}

// ── Case 2: a required suite fails → 1 ──────────────────────────────────
{
  const r = runRunner([], [
    { id: 'stub-ok', label: 'stub passing suite', kind: 'node', script: rel(OK_SUITE), required: true },
    { id: 'stub-fail', label: 'stub failing suite', kind: 'node', script: rel(FAIL_SUITE), required: true },
  ]);

  record('T-RUNNER-exit1', r.code === 1, `exit=${r.code} (expected 1 = FAILED)`);
  record('T-RUNNER-exit1-word', /REGRESSION SUITE FAILED/.test(r.stdout),
    'verdict text says FAILED (informational only)');

  // A failing suite must be named, or the operator has to grep.
  record('T-RUNNER-exit1-names', /stub-fail/.test(r.stdout), 'failing suite is named in the output');
}

// ── Case 3: a required browser suite skipped by --fast → 2 ──────────────
{
  const r = runRunner(['--fast'], [
    { id: 'stub-ok', label: 'stub passing suite', kind: 'node', script: rel(OK_SUITE), required: true },
    { id: 'stub-browser', label: 'stub browser suite', kind: 'browser', script: rel(OK_SUITE), required: true },
  ]);

  record('T-RUNNER-exit2', r.code === 2, `exit=${r.code} (expected 2 = INCOMPLETE)`);
  record('T-RUNNER-exit2-word', /INCOMPLETE/.test(r.stdout),
    'verdict text says INCOMPLETE (informational only)');

  // The skipped suite must be named as required, so it cannot be dismissed as
  // "just the browser tests".
  record('T-RUNNER-exit2-names', /stub-browser/.test(r.stdout), 'skipped required suite is named');

  // Crucially: the passing suite must NOT turn this into a pass. Everything
  // that ran, passed — and the verdict is still INCOMPLETE.
  record('T-RUNNER-exit2-not-pass', !/REGRESSION SUITE PASSED/.test(r.stdout),
    'a clean pass on everything that ran does not produce PASSED');
}

// ── Case 4: exit code is what CI reads, not the output ──────────────────
{
  // Same suites, opposite verdicts. If CI keyed off text, these would be
  // indistinguishable from the codes alone.
  const pass = runRunner([], [
    { id: 'stub-ok', label: 'ok', kind: 'node', script: rel(OK_SUITE), required: true },
  ]);
  const fail = runRunner(['--fast'], [
    { id: 'stub-ok', label: 'ok', kind: 'node', script: rel(OK_SUITE), required: true },
    { id: 'stub-b', label: 'b', kind: 'browser', script: rel(OK_SUITE), required: true },
  ]);

  const distinct = pass.code === 0 && fail.code === 2;
  record('T-RUNNER-distinct', distinct,
    `exit codes distinguish outcomes without reading text (${pass.code} vs ${fail.code})`);
}

// ── Case 5: the real suite list is all-required ────────────────────────
{
  // Read the production list out of run-all.mjs and confirm no suite has been
  // quietly marked optional.
  const src = fs.readFileSync(path.join(ROOT, 'tools/checks/run-all.mjs'), 'utf8');
  const entries = src.match(/\{ id: '[^']+',[^}]*\}/g) || [];
  const withoutRequired = entries.filter((e) => !/required: true/.test(e));

  record('T-RUNNER-all-required', entries.length >= 18 && withoutRequired.length === 0,
    `${entries.length} suites in run-all.mjs, ${withoutRequired.length} missing required:true`);
}

fs.rmSync(OK_SUITE, { force: true });
fs.rmSync(FAIL_SUITE, { force: true });

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);

if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}
