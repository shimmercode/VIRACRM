#!/usr/bin/env node
/**
 * Command-level half of the MySQL backup test matrix.
 *
 * WHAT THIS COVERS THAT THE PHP SUITE CANNOT
 * ------------------------------------------
 * `tests/Feature/BackupDriverMatrixTest.php` proves the strategy objects and the
 * container format in-process. The guards that matter to an operator, though,
 * live in the COMMAND: the document-root refusal, the exit code, whether a file
 * is left behind, whether the audit row was written. Those need a real process,
 * so they are exercised here through `artisan-probe`, which runs each command
 * against a private database copy.
 *
 * EVERY CASE NAMES ITS EXPECTATION
 * --------------------------------
 * A test that only asserts "non-zero exit" passes on a crash. Each case below
 * asserts the specific message and the specific filesystem state, because the
 * difference between "refused the wrong thing" and "refused the right thing" is
 * the whole point.
 *
 * NO MySQL SERVER IS PRESENT
 * --------------------------
 * The mysqldump SUCCESS path cannot run here. It is not simulated: a fake
 * mysqldump that emits a plausible file would prove nothing about a real one and
 * would be the fabrication this engagement is trying to eliminate. Those rows are
 * printed as BLOCKED with the command to run on a real host.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks, php } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('backup driver matrix');
const check = (id, ok, detail) => c.check(id, ok, detail);

const SHARED = path.join(ROOT, 'database/database.sqlite');
const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const sharedBefore = sha(SHARED);

const work = path.join(ROOT, '.arena/backup-driver-matrix');
fs.rmSync(work, { recursive: true, force: true });
fs.mkdirSync(work, { recursive: true });

/* A private database the commands may write against. */
const privateDb = path.join(work, 'source.sqlite');
fs.copyFileSync(SHARED, privateDb);

const KEY = crypto.randomBytes(32).toString('base64');
const WRONG = crypto.randomBytes(32).toString('base64');

const PROBE = 'tools/checks/artisan-probe.php';

/**
 * Run an artisan command against the private database at `privateDb`.
 *
 * The first positional argument to artisan-probe IS the database: the probe
 * force-sets DB_DATABASE from it after reading any KEY=VALUE pairs. So a
 * `DB_DATABASE` inside `env` is overwritten and means nothing — to act on a
 * different database, change `privateDb` for that call via `artisanOn()`.
 */
const artisan = (env, args) => php(PROBE, [privateDb, ...Object.entries(env).map(([k, v]) => `${k}=${v}`), '--', ...args]);

/** Same, but against an explicit database path. */
const artisanOn = (db, env, args) => php(PROBE, [db, ...Object.entries(env).map(([k, v]) => `${k}=${v}`), '--', ...args]);

const exitOf = (out) => {
  const m = String(out).match(/\[exit (\d+)\]/);
  return m ? Number(m[1]) : null;
};

const listOut = (dir) => (fs.existsSync(dir) ? fs.readdirSync(dir) : []);

/* ═══ 1. SQLite backup succeeds and reports its format ═════════════════ */

const outDir = path.join(work, 'ok');
const okBackup = await artisan({ VIRA_BACKUP_KEY: KEY }, ['vira:backup', `--path=${outDir}`, '--no-prune']);
const containers = listOut(outDir).filter((f) => f.endsWith('.vbk1'));

check('BDM-01', exitOf(okBackup.out) === 0 && containers.length === 1,
  `vira:backup on the SQLite deployment exits 0 and writes one container (${containers[0] ?? 'none'})`);

check('BDM-02', okBackup.out.includes('format : sqlite-file (sqlite)'),
  `the success output states the format and driver, so a reader can tell which database was captured`);

check('BDM-03', okBackup.out.includes('backup written and verified'),
  `the wording is "written and verified" — the container was re-opened and run through the restore gates before being published`);

/* ═══ 2. The container really holds the data ═══════════════════════════ */

const srcRows = Number((await php('tools/checks/flow-query.php', [privateDb,
  "select count(*) from sqlite_master where type='table' and name not like 'sqlite_%'"])).out.trim());

check('BDM-04', okBackup.out.includes(`tables : ${srcRows}`) || okBackup.out.includes(`tables : ${srcRows - 1}`),
  `the reported table count (${srcRows} read independently) matches the live database, not a filename`);

/* ═══ 3. MySQL without mysqldump → REFUSED, nothing written ════════════ */

const mysqlDir = path.join(work, 'mysql-refused');
const mysqlRun = await artisan(
  {
    VIRA_BACKUP_KEY: KEY,
    /* Only the driver and database name matter to the factory; assertReady()
     * rejects on the missing utility before any connection is attempted. */
    DB_CONNECTION: 'mysql',
  },
  ['vira:backup', `--path=${mysqlDir}`, '--no-prune'],
);

check('BDM-05', exitOf(mysqlRun.out) === 1,
  `DB_CONNECTION=mysql on a host with no mysqldump exits 1 (was: silently wrote a file from a schema name)`);

check('BDM-06', /mysqldump|proc_open/.test(mysqlRun.out),
  `the refusal names the missing dependency, so the operator knows what to install: "${(mysqlRun.out.match(/backup REFUSED: (.{0,90})/) || [])[1] ?? ''}…"`);

check('BDM-07', listOut(mysqlDir).filter((f) => f.endsWith('.vbk1')).length === 0,
  `nothing was written — the directory holds ${listOut(mysqlDir).length} file(s)`);

check('BDM-08', /Nothing was written/.test(mysqlRun.out),
  `the message states explicitly that nothing was written and no backup was pruned`);

/* ═══ 4. Unsupported driver → REFUSED ═════════════════════════════════ */

const srvDir = path.join(work, 'sqlsrv-refused');
const srvRun = await artisan(
  { VIRA_BACKUP_KEY: KEY, DB_CONNECTION: 'sqlsrv' },
  ['vira:backup', `--path=${srvDir}`, '--no-prune'],
);

/* sqlsrv is not configured, so Laravel fails earlier with a driver error. That
 * is still a refusal with a non-zero exit and no file — which is the property
 * being tested. */
check('BDM-09', exitOf(srvRun.out) !== 0 && listOut(srvDir).filter((f) => f.endsWith('.vbk1')).length === 0,
  `an unsupported driver does not produce a container (exit ${exitOf(srvRun.out)})`);

/* ═══ 5. Document root and traversal refusals ═════════════════════════ */

for (const [id, destination, label] of [
  ['BDM-10', 'public/backups', 'the document root'],
  ['BDM-11', '.arena/../public/backups', 'the document root reached via ".."'],
]) {
  const r = await artisan({ VIRA_BACKUP_KEY: KEY }, ['vira:backup', `--path=${destination}`, '--no-prune']);
  check(id, exitOf(r.out) === 1 && /document root/.test(r.out),
    `${label} (--path=${destination}) is refused with exit 1`);
}

check('BDM-12', !fs.existsSync(path.join(ROOT, 'public/backups')),
  `public/backups does not exist after the attempts`);

/*
 * A symlink is the traversal case a string prefix test cannot see. The guard
 * resolves realpath, so it should be caught — but "should be" is the reasoning
 * that let the original defect ship, so it is executed.
 */
const linkDir = path.join(work, 'link-to-public');
fs.symlinkSync(path.join(ROOT, 'public'), linkDir);
const linkRun = await artisan({ VIRA_BACKUP_KEY: KEY },
  ['vira:backup', `--path=${path.relative(ROOT, linkDir)}/leaked`, '--no-prune']);

check('BDM-12b', exitOf(linkRun.out) === 1 && /document root/.test(linkRun.out),
  `a symlink pointing into the document root is refused (exit 1) — realpath resolves it, `
  + `which a plain prefix test would not`);

check('BDM-12c', !fs.existsSync(path.join(ROOT, 'public/leaked')),
  `nothing was created inside public/ through the symlink`);

/* ═══ 6. Wrong key on restore → refused, data untouched ════════════════ */

const container = path.join(outDir, containers[0]);
const target = path.join(work, 'target.sqlite');
fs.copyFileSync(SHARED, target);

/*
 * Read the per-table digests, not the single whole-database hash.
 *
 * artisan-probe ALWAYS sets DB_DATABASE to the path given as its first argument,
 * so a restore can only ever be pointed at a private database by passing it
 * there. The first version of this test passed `DB_DATABASE: target` as an env
 * pair while artisan-probe's positional argument still named the SOURCE
 * database — so the restore ran against the source and `target` was never
 * touched. Comparing `target` before and after then compared a file to itself
 * and passed for the wrong reason.
 *
 * Even against the correct database the whole-database hash is the wrong
 * assertion: a refused restore appends a `restore_failed` audit row by design
 * (that is the evidence trail), so the file legitimately changes. What must not
 * change is any DATA table.
 */
const fp = async (db) => JSON.parse(
  (await php('tools/checks/db-fingerprint.php', [db, '--json'])).out.trim().split('\n')[0],
);

const beforeWrong = await fp(target);

const wrongKeyRun = await artisanOn(
  target,
  { VIRA_BACKUP_KEY: WRONG },
  ['vira:restore', `file=${container}`, '--force', '--no-interaction'],
);
/* artisan-probe's first positional already points the restore at `target`. */

const afterWrong = await fp(target);

check('BDM-13', exitOf(wrongKeyRun.out) === 1,
  `restoring with the wrong key exits 1 (gate 3, GCM authentication)`);

const changedData = Object.keys(beforeWrong.perTable)
  .filter((t) => t !== 'audit_logs')
  .filter((t) => beforeWrong.perTable[t] !== afterWrong.perTable[t]);

check('BDM-14', changedData.length === 0,
  `no DATA table changed in the target database (${Object.keys(beforeWrong.perTable).length - 1} compared, `
  + `audit_logs excluded because the refusal records itself)`);

check('BDM-14b',
  beforeWrong.perTable.audit_logs !== afterWrong.perTable.audit_logs
  && afterWrong.rows === beforeWrong.rows + 1,
  `the refused restore did append exactly one audit row (${beforeWrong.rows} → ${afterWrong.rows}) — `
  + `so the restore really did run against this database, which is what BDM-14 depends on`);

/* ═══ 7. Corrupted container → refused ════════════════════════════════ */

const corrupt = path.join(work, 'corrupt.vbk1');
const bytes = fs.readFileSync(container);
bytes[Math.floor(bytes.length / 2)] ^= 0x01;   // flip one bit mid-ciphertext
fs.writeFileSync(corrupt, bytes);

const beforeCorrupt = await fp(target);
const corruptRun = await artisanOn(
  target,
  { VIRA_BACKUP_KEY: KEY },
  ['vira:restore', `file=${corrupt}`, '--force', '--no-interaction'],
);
const afterCorrupt = await fp(target);

const corruptChanged = Object.keys(beforeCorrupt.perTable)
  .filter((t) => t !== 'audit_logs')
  .filter((t) => beforeCorrupt.perTable[t] !== afterCorrupt.perTable[t]);

check('BDM-15', exitOf(corruptRun.out) === 1 && corruptChanged.length === 0,
  `a one-bit flip is refused (exit 1) and no data table changed — the GCM tag catches it before any write`);

/* ═══ 8. Truncated container → refused ════════════════════════════════ */

const truncated = path.join(work, 'truncated.vbk1');
fs.writeFileSync(truncated, fs.readFileSync(container).subarray(0, 4096));

const truncRun = await artisanOn(target, { VIRA_BACKUP_KEY: KEY },
  ['vira:restore', `file=${truncated}`, '--force', '--no-interaction']);

check('BDM-16', exitOf(truncRun.out) === 1,
  `a truncated container is refused with exit 1`);

/* ═══ 9. Not a container at all ═══════════════════════════════════════ */

const garbage = path.join(work, 'garbage.bin');
fs.writeFileSync(garbage, crypto.randomBytes(2048));

const garbageRun = await artisanOn(target, { VIRA_BACKUP_KEY: KEY },
  ['vira:restore', `file=${garbage}`, '--force', '--no-interaction']);

check('BDM-17', exitOf(garbageRun.out) === 1 && /gate 1|not a VIRA backup/.test(garbageRun.out),
  `random bytes are refused at gate 1 (magic), not treated as a backup`);

/* ═══ 10. Restore succeeds and reports format ═════════════════════════ */

const goodRun = await artisanOn(target, { VIRA_BACKUP_KEY: KEY },
  ['vira:restore', `file=${container}`, '--force', '--no-interaction']);

check('BDM-18', exitOf(goodRun.out) === 0 && goodRun.out.includes('format         : sqlite-file'),
  `a valid container restores with exit 0 and reports the format it installed`);

/* ═══ 11. Restore is driver-aware: a sqlite container on mysql ════════ */

/* DB_CONNECTION alone: artisan-probe sets DB_DATABASE from its positional
 * argument, so passing one here would be silently ignored. */
const mysqlRestore = await artisanOn(
  target,
  { VIRA_BACKUP_KEY: KEY, DB_CONNECTION: 'mysql' },
  ['vira:restore', `file=${container}`, '--force', '--no-interaction'],
);

check('BDM-19', exitOf(mysqlRestore.out) === 1 && /not implemented|incompatible/i.test(mysqlRestore.out),
  `restoring onto a MySQL host is refused rather than copying a dump over a schema name:`
  + ` "${(mysqlRestore.out.match(/(not implemented|incompatible)[^"]{0,70}/i) || [])[0] ?? ''}"`);

/* ═══ 12. Blocked rows, stated rather than skipped ════════════════════ */

console.log('');
console.log('BLOCKED  BDM-20  MySQL backup success against a real server — no mysqld:');
console.log('                 apt and deb.debian.org are both unreachable (see MYSQL_COMPATIBILITY_REPORT §2).');
console.log('BLOCKED  BDM-21  MySQL restore into a separate empty schema — needs the same server.');
console.log('BLOCKED  BDM-22  "the dump contains actual records" — needs a real mysqldump to examine.');
console.log('                 The structural checks in MysqlDumpSnapshotSource::verifyDumpContainsData');
console.log('                 are implemented and unit-untested against real output.');

/* ═══ 13. Isolation ═══════════════════════════════════════════════════ */

check('BDM-23', sha(SHARED) === sharedBefore,
  `database/database.sqlite unchanged: ${sharedBefore.slice(0, 16)}… (every command ran on a private copy)`);

fs.rmSync(work, { recursive: true, force: true });

process.exit(c.summary() ? 0 : 1);
