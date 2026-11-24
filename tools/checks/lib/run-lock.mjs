/**
 * VIRA CRM — regression execution lock.
 *
 * WHY THIS EXISTS
 * ---------------
 * The runner is not parallel-safe. Suites cannot touch the shared database, but
 * they do share the *filesystem*: `lib/browser-flow.mjs` builds each suite's
 * private database at `.arena/flows/<suite-name>/`, and it begins by
 * `rmSync(dir)` then re-creating it. The directory name is derived from the
 * suite name, so it is identical in two concurrent runs.
 *
 * That is not a theoretical race. During phase 2-E it produced exactly this:
 *
 *     Database file at path [.arena/flows/crud/database.sqlite] does not exist
 *
 * …because run A deleted the directory that run B was still serving from. The
 * failure looked like a product bug in `flow-crud` and cost time to diagnose.
 * The directive (§14.2) requires a real process-level lock, not a note in a doc.
 *
 * MECHANISM
 * ---------
 * `fs.openSync(path, 'wx')` fails atomically if the file already exists — this is
 * O_CREAT|O_EXCL, which is atomic on POSIX and does not need `flock`. Deliberately
 * avoided `flock(1)`: cPanel hosts cannot be assumed to have it, and Node has no
 * portable advisory-lock API. Exclusive-create is the portable primitive.
 *
 * STALE-LOCK RECOVERY (§14.2.5, §14.2.6)
 * --------------------------------------
 * A crash must not strand the lock forever. The lock file records `{pid, host,
 * startedAt}`. On acquisition failure we check whether that pid is still alive:
 *
 *   • same host, pid gone        → stale → take over
 *   • same host, pid alive       → genuine contention → refuse
 *   • different host             → cannot verify → honour a TTL, then take over
 *   • old enough (TTL exceeded)  → assume stale even if pid appears alive
 *
 * PID reuse is the one hole: a recycled pid can make a dead lock look live. The
 * TTL bounds that; a full run is ~13 minutes, so the default TTL is 3 hours.
 *
 * The exit code for contention is 3, distinct from 0/1/2 so CI can tell
 * "someone else is running" apart from "the code is broken".
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

export const EXIT_LOCKED = 3;

/** Max age before a lock is considered abandoned even if its pid looks alive. */
const DEFAULT_TTL_MS = 3 * 60 * 60 * 1000; // 3h — a full run is ~13min

function isPidAlive(pid) {
  // Signal 0 performs the permission/existence check without delivering a signal.
  try {
    process.kill(pid, 0);
    return true;
  } catch (err) {
    // EPERM means the process exists but belongs to another user.
    return err.code === 'EPERM';
  }
}

function readLock(file) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    // A partially written file (crash between open and write) is treated as
    // stale rather than crashing the runner.
    return null;
  }
}

/**
 * Try to take the lock. Returns `{ acquired, release, holder, reason, exitCode }`.
 * Never throws for contention — the caller decides what to print.
 */
export function acquireRunLock(root, { ttlMs = DEFAULT_TTL_MS, force = false } = {}) {
  const dir = path.join(root, '.arena');
  const file = path.join(dir, 'run.lock');

  fs.mkdirSync(dir, { recursive: true });

  const payload = {
    pid: process.pid,
    host: os.hostname(),
    startedAt: new Date().toISOString(),
  };

  const tryCreate = () => {
    const fd = fs.openSync(file, 'wx'); // atomic: fails if it exists
    try {
      fs.writeSync(fd, JSON.stringify(payload, null, 2));
    } finally {
      fs.closeSync(fd);
    }
  };

  if (force) {
    fs.rmSync(file, { force: true });
  }

  try {
    tryCreate();
  } catch (err) {
    if (err.code !== 'EEXIST') throw err;

    const holder = readLock(file);
    const age = holder?.startedAt ? Date.now() - Date.parse(holder.startedAt) : Infinity;
    const sameHost = holder?.host === payload.host;
    const alive = sameHost && typeof holder?.pid === 'number' ? isPidAlive(holder.pid) : null;

    // Decide whether the existing lock is genuinely live.
    const stale =
      !holder ||                       // unreadable/corrupt
      age > ttlMs ||                   // past TTL
      (sameHost && alive === false);   // same host, process is gone

    if (!stale) {
      return {
        acquired: false,
        exitCode: EXIT_LOCKED,
        holder,
        reason: sameHost
          ? `another run is in progress (pid ${holder.pid}, started ${holder.startedAt})`
          : `another run appears active on host "${holder.host}" (cannot verify its pid from here)`,
        release: () => {},
      };
    }

    // Take over the stale lock, but re-create atomically so two processes racing
    // to recover a stale lock cannot both win.
    fs.rmSync(file, { force: true });
    try {
      tryCreate();
    } catch (err2) {
      if (err2.code === 'EEXIST') {
        return {
          acquired: false,
          exitCode: EXIT_LOCKED,
          holder: readLock(file),
          reason: 'lost the race to recover a stale lock',
          release: () => {},
        };
      }
      throw err2;
    }
  }

  let released = false;
  const release = () => {
    if (released) return;
    released = true;
    // Only remove the lock if it is still OURS — never delete the next run's lock.
    const cur = readLock(file);
    if (cur && cur.pid === payload.pid && cur.startedAt === payload.startedAt) {
      fs.rmSync(file, { force: true });
    }
  };

  // Safety nets so a thrown exception or Ctrl-C does not strand the lock.
  process.once('exit', release);
  for (const sig of ['SIGINT', 'SIGTERM']) {
    process.once(sig, () => {
      release();
      // Re-raise so the conventional exit status is preserved.
      process.kill(process.pid, sig);
    });
  }

  return { acquired: true, exitCode: 0, holder: payload, reason: null, release };
}
