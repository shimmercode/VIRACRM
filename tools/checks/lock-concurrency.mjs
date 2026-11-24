#!/usr/bin/env node
/**
 * VIRA CRM — regression execution lock: real concurrency, crash recovery, exit codes.
 *
 * The directive (§14.2, §14.3) is explicit that documenting a lock is not enough
 * and that a second process must be shown to be excluded. This suite proves it
 * by spawning genuinely concurrent child processes against the real lock module
 * — not by simulating one.
 *
 * WHAT IS PROVEN HERE
 *   L1  first acquirer succeeds
 *   L2  a second, concurrent acquirer is REFUSED (not queued, not silently run)
 *   L3  refusal carries exit code 3, distinct from 0/1/2
 *   L4  the holder pid appears in the refusal reason (operator can act on it)
 *   L5  release() lets the next run in
 *   L6  a stale lock (dead pid, same host) is recovered automatically
 *   L7  a live lock is NOT stolen, even if it is younger than the TTL
 *   L8  an unreadable/corrupt lock file is treated as stale, not a crash
 *   L9  a lock older than the TTL is recovered even without a pid check
 *   L10 lock release never deletes a lock that belongs to someone else
 *   L11 the runner exits 3 when another run holds the lock
 *   L12 the runner completes normally and leaves no lock behind
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

import { acquireRunLock, EXIT_LOCKED } from './lib/run-lock.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const here = fileURLToPath(import.meta.url);

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(26)} ${detail}`);
};

const LOCK = path.join(ROOT, '.arena', 'run.lock');
const clean = () => fs.rmSync(LOCK, { force: true });

// ── L1–L5: two real processes contend for the same lock ───────────────────
// Child A acquires and holds for 1.5s; child B starts 300ms later and must be
// refused while A is still alive.
const holderScript = `
import { acquireRunLock } from ${JSON.stringify(path.join(ROOT, 'tools/checks/lib/run-lock.mjs'))};
const l = acquireRunLock(${JSON.stringify(ROOT)});
if (!l.acquired) { console.log('REFUSED: ' + l.reason); process.exit(l.exitCode); }
console.log('ACQUIRED ' + l.holder.pid);
setTimeout(() => { l.release(); console.log('RELEASED'); process.exit(0); }, 1500);
`;
const contenderScript = holderScript; // same script — first past the post wins

const tmpA = path.join(ROOT, '.arena', `lock-child-${process.pid}.mjs`);
fs.mkdirSync(path.dirname(tmpA), { recursive: true });

const spawnChild = (code, name) => {
  const f = path.join(ROOT, '.arena', `${name}-${process.pid}.mjs`);
  fs.writeFileSync(f, code, 'utf8');
  const p = spawn(process.execPath, [f], { cwd: ROOT });
  let out = '';
  p.stdout.on('data', (d) => { out += d; });
  p.stderr.on('data', (d) => { out += d; });
  return { proc: p, done: new Promise((res) => p.on('close', (c) => { fs.rmSync(f, { force: true }); res({ code: c, out }); })) };
};

{
  clean();

  const a = spawnChild(holderScript, 'lock-a');
  await new Promise((r) => setTimeout(r, 350)); // let A take the lock

  const b = spawnChild(contenderScript, 'lock-b');
  const [ra, rb] = await Promise.all([a.done, b.done]);

  record('T-LOCK-first-acquires', ra.code === 0 && /ACQUIRED/.test(ra.out), `first process exit=${ra.code}`);
  record('T-LOCK-second-refused', rb.code === EXIT_LOCKED, `concurrent process exit=${rb.code} (expected ${EXIT_LOCKED})`);
  record('T-LOCK-distinct-code', EXIT_LOCKED === 3 && ![0, 1, 2].includes(rb.code), `exit ${rb.code} is distinguishable from 0/1/2`);
  // The point is that the REFUSED process tells the operator which pid holds the
  // lock, so they can act on it. Asserting on A's own output would prove nothing.
  const holderFromA = (ra.out.match(/ACQUIRED (\d+)/) || [])[1];
  const namedInB = holderFromA ? rb.out.includes(holderFromA) : false;
  record('T-LOCK-names-holder', !!holderFromA && namedInB,
    `refusal tells the operator which pid holds it (holder=${holderFromA}, named in refuser output=${namedInB})`);
  record('T-LOCK-released-after', !fs.existsSync(LOCK), 'lock file gone after the holder finished');
  fs.rmSync(tmpA, { force: true });
}

// ── L6: stale lock from a dead pid on this host is recovered ──────────────
{
  clean();
  fs.mkdirSync(path.dirname(LOCK), { recursive: true });
  // A pid that cannot be alive: > pid_max on Linux.
  fs.writeFileSync(LOCK, JSON.stringify({ pid: 999_999_999, host: os_host(), startedAt: new Date().toISOString() }));

  const l = acquireRunLock(ROOT);
  record('T-LOCK-stale-recovered', l.acquired === true, 'dead pid on this host was taken over');
  l.release();
}

// ── L7: a live lock is not stolen ─────────────────────────────────────────
{
  clean();
  fs.mkdirSync(path.dirname(LOCK), { recursive: true });
  fs.writeFileSync(LOCK, JSON.stringify({ pid: process.pid, host: os_host(), startedAt: new Date().toISOString() }));

  const l = acquireRunLock(ROOT);
  record('T-LOCK-live-not-stolen', l.acquired === false && l.exitCode === EXIT_LOCKED, 'a live pid on this host keeps the lock');
  clean();
}

// ── L8: corrupt lock file is stale, not fatal ─────────────────────────────
{
  clean();
  fs.mkdirSync(path.dirname(LOCK), { recursive: true });
  fs.writeFileSync(LOCK, '{ this is not json');

  const l = acquireRunLock(ROOT);
  record('T-LOCK-corrupt-recovered', l.acquired === true, 'unreadable lock treated as stale');
  l.release();
}

// ── L9: TTL recovers a lock whose pid looks alive ─────────────────────────
{
  clean();
  fs.mkdirSync(path.dirname(LOCK), { recursive: true });
  const old = new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(); // 4h > 3h TTL
  fs.writeFileSync(LOCK, JSON.stringify({ pid: process.pid, host: os_host(), startedAt: old }));

  const l = acquireRunLock(ROOT);
  record('T-LOCK-ttl-recovered', l.acquired === true, 'lock older than TTL was taken over');
  l.release();
}

// ── L10: release never deletes someone else's lock ────────────────────────
{
  clean();
  const mine = acquireRunLock(ROOT);
  // Simulate the lock being replaced by another process while we held it.
  fs.writeFileSync(LOCK, JSON.stringify({ pid: 999_999_999, host: 'other-host', startedAt: new Date().toISOString() }));
  const foreign = fs.readFileSync(LOCK, 'utf8');
  mine.release();
  const survived = fs.existsSync(LOCK) && fs.readFileSync(LOCK, 'utf8') === foreign;
  record('T-LOCK-no-foreign-release', survived, 'release left a foreign lock intact');
  clean();
}

// ── L11: the real runner refuses with exit 3 ──────────────────────────────
{
  clean();
  fs.mkdirSync(path.dirname(LOCK), { recursive: true });
  fs.writeFileSync(LOCK, JSON.stringify({ pid: process.pid, host: os_host(), startedAt: new Date().toISOString() }));

  const out = spawnSync(process.execPath, ['tools/checks/run-all.mjs', '--fast'], {
    cwd: ROOT, encoding: 'utf8', timeout: 60_000,
  });

  record('T-LOCK-runner-exit3', out.status === EXIT_LOCKED, `run-all under a held lock exited ${out.status}`);
  record('T-LOCK-runner-explains', /another run is in progress/i.test(out.stderr || ''), 'refusal message explains why');
  clean();
}

// ── L12: a normal runner completion leaves no lock behind ─────────────────
{
  clean();
  const out = spawnSync(process.execPath, ['tools/checks/run-all.mjs', '--fast'], {
    cwd: ROOT, encoding: 'utf8', timeout: 300_000,
  });
  record('T-LOCK-cleared-on-finish', !fs.existsSync(LOCK), `runner exit=${out.status}, lock removed afterwards`);
}

// ── L13: a SIGKILLed holder leaves a stale lock that the next run recovers ──
// This is the crash path the directive names (§14.2.5, §14.2.6): SIGKILL cannot
// be trapped, so no release handler runs and the lock file is left behind. The
// next run must recover rather than be blocked forever.
{
  clean();

  const victim = spawnChild(holderScript.replace('}, 1500)', '}, 60000)'), 'lock-crash');
  await new Promise((r) => setTimeout(r, 500)); // let it acquire, then hang

  const leftBehind = fs.existsSync(LOCK);
  const holder = leftBehind ? JSON.parse(fs.readFileSync(LOCK, 'utf8')) : null;

  victim.proc.kill('SIGKILL');          // no release handler can run
  await victim.done;

  const stillThere = fs.existsSync(LOCK);
  const recovered = acquireRunLock(ROOT);

  record('T-LOCK-crash-leaves-file', leftBehind && stillThere,
    `SIGKILL left the lock behind (pid ${holder?.pid}) — as expected, no handler ran`);
  record('T-LOCK-crash-recovered', recovered.acquired === true,
    'the next run recovered the dead holder instead of staying blocked');
  recovered.release();
}

function os_host() {
  return os.hostname();
}

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);
process.exit(passed === results.length ? 0 : 1);
