/**
 * Backup hardening — the parts of §14 the other suites did not cover.
 *
 * `e2e-workflows.mjs` (W-09) already proves a container verifies and a tampered
 * one is refused, and `phase4-integration.mjs` (scenario I) drives a real
 * backup → restore → reconciliation. This suite does not repeat them. It covers
 * what was left unproven:
 *
 *   · the container is actually *encrypted at rest*, measured rather than
 *     asserted from the code (entropy of the payload, absence of plaintext
 *     markers) — a command can declare "AES-256-GCM" in its description and
 *     still write a zip;
 *   · the destination can never land inside the document root, so a backup is
 *     not fetchable over HTTP;
 *   · the key gates are real: no key, and a malformed key, both refuse.
 *
 * Everything runs against a private copy of the database in `.arena/`, through
 * `artisan-probe.php`, which refuses the shared database by construction.
 * The last check asserts that held.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks, php } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('backup hardening');
const check = (id, ok, detail) => c.check(id, ok, detail);

const SHARED = path.join(ROOT, 'database/database.sqlite');
const PROBE = 'tools/checks/artisan-probe.php';
const COUNT = 'tools/checks/scratch-count.php';

const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const sharedBefore = sha(SHARED);

const work = path.join(ROOT, '.arena/backup-hardening');
fs.rmSync(work, { recursive: true, force: true });
fs.mkdirSync(work, { recursive: true });

const dbA = path.join(work, 'a.sqlite');
const dbB = path.join(work, 'b.sqlite');
fs.copyFileSync(SHARED, dbA);

const KEY = crypto.randomBytes(32).toString('base64');
const WRONG = crypto.randomBytes(32).toString('base64');

/** Run artisan against a private database. Env pairs go before the `--`. */
const artisan = (db, env, args) => php(PROBE, [db, ...env, '--', ...args]);

const exitOf = (out) => {
  const m = out.match(/\[exit (-?\d+)\]/);
  return m ? Number(m[1]) : null;
};

const containersIn = (dir) => (fs.existsSync(dir)
  ? fs.readdirSync(dir).filter((f) => f.endsWith('.vbk1')).sort()
  : []);

/* ═══ Key gates: no key, and a malformed key, must both refuse ═══════════ */

const outDir1 = path.join(work, 'no-key');
const noKey = await artisan(dbA, [], ['vira:backup', `--path=${outDir1}`]);
check('BK-01', exitOf(noKey.out) !== 0 && containersIn(outDir1).length === 0,
  `vira:backup with no VIRA_BACKUP_KEY exits ${exitOf(noKey.out)} and writes nothing`
  + ` — it must never emit an unencrypted dump`);

const outDir2 = path.join(work, 'bad-key');
const badKey = await artisan(dbA, ['VIRA_BACKUP_KEY=not-base64-32-bytes'],
  ['vira:backup', `--path=${outDir2}`]);
check('BK-02', exitOf(badKey.out) !== 0 && containersIn(outDir2).length === 0,
  `a malformed VIRA_BACKUP_KEY exits ${exitOf(badKey.out)} and writes nothing`
  + ` (the key must be base64-encoded 32 bytes)`);

/* ═══ The destination must never be web-served ══════════════════════════ */

const publicRoot = path.join(ROOT, 'public');
const webPaths = ['public/backups', 'public/a/b/c', 'public'];
const webResults = [];

for (const p of webPaths) {
  const r = await artisan(dbA, [`VIRA_BACKUP_KEY=${KEY}`], ['vira:backup', `--path=${p}`]);
  const refused = exitOf(r.out) !== 0 && r.out.includes('document root');
  webResults.push({ p, refused, exit: exitOf(r.out) });
}

check('BK-03', webResults.every((r) => r.refused),
  `every --path inside the document root is refused: `
  + webResults.map((r) => `${r.p}=${r.refused ? 'refused' : 'WRITTEN'}`).join(', ')
  + ` — a container is a dump of the whole database and must not be fetchable`);

check('BK-04', fs.readdirSync(publicRoot).every((f) => !f.startsWith('vira-backup')),
  `public/ still holds only ${fs.readdirSync(publicRoot).join(', ')} — no container was written there`);

const defaultDir = path.join(ROOT, 'storage/app/backups');
check('BK-05', !defaultDir.startsWith(publicRoot + path.sep),
  `the default destination ${path.relative(ROOT, defaultDir)} is outside the document root`);

/* ═══ A legitimate path still works, and the container is encrypted ═════ */

const goodDir = path.join(work, 'good');
const good = await artisan(dbA, [`VIRA_BACKUP_KEY=${KEY}`], ['vira:backup', `--path=${goodDir}`]);
const made = containersIn(goodDir);

check('BK-06', exitOf(good.out) === 0 && made.length === 1,
  `a --path outside the document root is accepted: exit ${exitOf(good.out)},`
  + ` produced ${made[0] ?? 'nothing'}`);

const container = made.length ? path.join(goodDir, made[0]) : null;
let header = null;
let body = Buffer.alloc(0);

if (container) {
  const raw = fs.readFileSync(container);
  if (raw.subarray(0, 8).toString() === 'VIRABK1\n') {
    const lineEnd = raw.indexOf(0x0a, 8);
    try { header = JSON.parse(raw.subarray(8, lineEnd).toString()); } catch { header = null; }
    body = raw.subarray(lineEnd + 1);
  }
}

check('BK-07', Boolean(container) && header?.cipher === 'aes-256-gcm' && header?.kdf === 'hkdf-sha256'
  && Boolean(header?.iv) && Boolean(header?.tag) && Boolean(header?.salt),
  header
    ? `container is VIRABK1 with cipher=${header.cipher}, kdf=${header.kdf}, and a`
      + ` salt/iv/GCM tag — authenticated encryption, not a password-less zip`
    : `container magic or header could not be read`);

// Entropy of the payload. AES-256-GCM output is indistinguishable from random,
// so it must sit at ~8 bits per byte. A zip of a SQLite file sits far lower and
// is trivially recognisable; this is the measurement that catches a command
// which merely *claims* to encrypt.
const bitsPerByte = (buf) => {
  if (!buf.length) return 0;
  const freq = new Array(256).fill(0);
  for (const b of buf) freq[b]++;
  let h = 0;
  for (const n of freq) {
    if (n) {
      const p = n / buf.length;
      h -= p * Math.log2(p);
    }
  }
  return h;
};

const sample = body.subarray(0, Math.min(body.length, 20000));
const entropy = bitsPerByte(sample);
const leaks = body.includes(Buffer.from('SQLite format 3'))
  || body.includes(Buffer.from('کاربر', 'utf8'));

check('BK-08', body.length > 1000 && entropy > 7.9 && !leaks,
  `${body.length} bytes of payload at ${entropy.toFixed(3)} bits/byte (random = 8),`
  + ` with no SQLite magic and no plaintext Persian — the dump is encrypted at rest`);

check('BK-09', fs.existsSync(`${container}.sha256`),
  `a detached ${made[0]}.sha256 checksum ships beside the container`);

/* ═══ A wrong key and a flipped bit must both be refused ════════════════ */

fs.copyFileSync(SHARED, dbB);

const wrongKey = await artisan(dbB, [`VIRA_BACKUP_KEY=${WRONG}`],
  ['vira:restore', `file=${container}`, '--verify', '--no-interaction']);

check('BK-10', exitOf(wrongKey.out) !== 0 && wrongKey.out.includes('restore_failed'),
  `restore with the wrong key exits ${exitOf(wrongKey.out)} and appends a`
  + ` restore_failed audit row — the refusal is recorded, not silent`);

const tampered = path.join(work, 'tampered.vbk1');
fs.copyFileSync(container, tampered);
{
  const buf = fs.readFileSync(tampered);
  const at = Math.floor(buf.length / 2);
  buf[at] ^= 0x01; // one bit in the ciphertext body
  fs.writeFileSync(tampered, buf);
}

const tamperRun = await artisan(dbB, [`VIRA_BACKUP_KEY=${KEY}`],
  ['vira:restore', `file=${tampered}`, '--force', '--no-interaction']);

check('BK-11', exitOf(tamperRun.out) !== 0 && tamperRun.out.includes('restore_failed'),
  `a container with one flipped bit exits ${exitOf(tamperRun.out)} and is audited`
  + ` — the GCM tag detects tampering before anything is written`);

/* ═══ The positive path: a real restore reconciles ══════════════════════ */

fs.copyFileSync(SHARED, dbB);
const restored = await artisan(dbB, [`VIRA_BACKUP_KEY=${KEY}`],
  ['vira:restore', `file=${container}`, '--force', '--no-interaction']);

const TABLES = ['customers', 'contracts', 'projects', 'users', 'work_items'];
const counts = async (db) => {
  const out = {};
  for (const t of TABLES) {
    const r = await php(COUNT, [db, t]);
    out[t] = (r.out.match(/(\d+)\s*$/) || [])[1] ?? 'ERR';
  }
  return out;
};

const a = await counts(dbA);
const b = await counts(dbB);
const same = TABLES.every((t) => a[t] === b[t]);

check('BK-12', exitOf(restored.out) === 0 && same,
  `restore into a fresh database exits ${exitOf(restored.out)} and reconciles:`
  + ` ${TABLES.map((t) => `${t} ${a[t]}=${b[t]}`).join(', ')}`);

/* ═══ None of this touched the shared database ══════════════════════════ */

check('BK-13', sha(SHARED) === sharedBefore,
  `database/database.sqlite unchanged: ${sharedBefore.slice(0, 16)}…`
  + ` (every command ran through artisan-probe against a private copy)`);

fs.rmSync(work, { recursive: true, force: true });

process.exit(c.summary() ? 0 : 1);
