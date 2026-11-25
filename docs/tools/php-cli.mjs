#!/usr/bin/env node
/**
 * VIRA CRM — PHP-WASM CLI harness (development tool, not deployed)
 * ---------------------------------------------------------------
 * The development sandbox for this repository has no native PHP. This tool runs a real
 * PHP 8.3 interpreter (official WebAssembly build of php-src, npm package @php-wasm/node)
 * via Node and executes a PHP script with SAPI=cli.
 *
 * It is used to verify real logic (Jalali dates, reminder day-math, AES-GCM, money math)
 * with actual executions and JSON evidence under docs/phase-1/evidence/.
 * It is NOT a substitute for deploying on the customer host.
 *
 * Setup (once, outside the repo):  npm i @php-wasm/node@3.1.65
 * Usage:
 *   node docs/tools/php-cli.mjs <script.php> [args...]
 * Env:
 *   PHP_WASM_PATH  folder containing node_modules/@php-wasm (default /tmp/phpwasm/node_modules)
 *   NO_MOUNT=1     copy the script into the WASM FS instead of mounting the host FS
 */
import fs from 'node:fs';
import path from 'node:path';

const phpWasmPath = process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules';
const script = path.resolve(process.argv[2] || '');
const args = process.argv.slice(3);

if (!fs.existsSync(script)) {
  console.error('script not found:', script);
  process.exit(2);
}
if (!fs.existsSync(path.join(phpWasmPath, '@php-wasm/node'))) {
  console.error(`@php-wasm/node not found at ${phpWasmPath}.`);
  console.error('Run: npm i @php-wasm/node@3.1.65 in a separate folder and set PHP_WASM_PATH.');
  process.exit(2);
}

const { useHostFilesystem, loadNodeRuntime } = await import(path.join(phpWasmPath, '@php-wasm/node/index.js'));
const { PHP } = await import(path.join(phpWasmPath, '@php-wasm/universal/index.js'));

const processId = 200 + Math.floor(Math.random() * 700);
const rt = await loadNodeRuntime('8.3', { emscriptenOptions: { processId } });
const php = new PHP(rt);

if (process.env.NO_MOUNT !== '1') {
  useHostFilesystem(php);
} else {
  php.mkdir('/work');
  php.writeFile('/work/script.php', fs.readFileSync(script));
}

const started = Date.now();
const target = process.env.NO_MOUNT === '1' ? '/work/script.php' : script;
/*
 * Pass through the caller's environment.
 *
 * This used to hand the runtime a fresh object containing only
 * PHP_ERROR_REPORTING, which silently dropped everything the parent passed in.
 * That made VIRA_PREVIEW_EMBED (and any other VIRA_* switch) invisible to PHP
 * while `VIRA_PREVIEW_EMBED=... node docs/tools/php-cli.mjs ...` looked like it
 * should work. A configuration variable that is quietly discarded is worse than
 * one that is missing: the command appears to succeed and the setting has no
 * effect.
 */
const response = await php.cli(['php', target, ...args], {
  env: { ...process.env, PHP_ERROR_REPORTING: 'E_ALL' },
});
const [stdout, stderr] = await Promise.all([response.stdoutText, response.stderrText]);
process.stdout.write(stdout || '');
if (stderr) process.stderr.write(stderr);
const exitCode = await response.exitCode;
console.error(`\n[php-cli] exit=${exitCode} time=${Date.now() - started}ms runtime=php-wasm 8.3`);

/*
 * Propagate the script's real exit code.
 *
 * This used to be `process.exit(0)` unconditionally, which quietly made every
 * PHP check unfailable: tools/checks/run-all.mjs decides a suite passed with
 * `ok: code === 0`, so a PHP suite that exited 1 was still reported as ✓. The
 * log line above said `exit=1` while the process said 0 — the gate believed the
 * process, not the log.
 *
 * 2 is preserved as the "precondition unavailable" signal that run-all.mjs
 * already interprets, so a missing runtime stays distinguishable from a failed
 * check rather than collapsing into the same bucket.
 */
process.exit(exitCode);
