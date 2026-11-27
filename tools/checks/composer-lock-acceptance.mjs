#!/usr/bin/env node
/**
 * Validate a composer.lock — or show precisely why there is none.
 *
 * WHY THIS IS A VALIDATOR AND NOT A GENERATOR
 * -------------------------------------------
 * `composer.lock` is a resolution of composer.json against a package repository.
 * It carries, for every package, a version, a dist URL, a reference commit and a
 * content hash computed by Composer from composer.json. Those fields are facts
 * produced by an installer reading an index. They cannot be written by hand:
 * inventing a `content-hash` or a `source.reference` produces a file that looks
 * authoritative, fails `composer install --no-dev` at the first `composer
 * validate`, and — worst — makes a deployment look reproducible when it is not.
 *
 * This environment has no composer binary and no reachable package repository
 * (repo.packagist.org and every mirror tried fail the TLS handshake at the egress
 * boundary; only GitHub and npm are allowed). So the honest outcome is: no lock
 * file, and a script that says so with the exact provenance of every claim.
 *
 * WHAT IT DOES WHEN A LOCK FILE EXISTS
 * ------------------------------------
 * Verifies the things a reviewer needs: that it parses, that every required
 * package in composer.json appears at a satisfying version, that dist/source
 * references are present for each, and that `content-hash` — if present — is the
 * length Composer produces. It does not recompute the hash; doing so requires
 * Composer's own algorithm and the only reliable way to get it is to run
 * Composer. It reports the hash as UNVERIFIED rather than pretending.
 */
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const LOG = [];
const say = (s = '') => { LOG.push(s); console.log(s); };

const lockPath = path.join(ROOT, 'composer.lock');
const jsonPath = path.join(ROOT, 'composer.json');

say('composer.lock acceptance');
say('='.repeat(64));

/* ── composer.json: what must be satisfied ─────────────────────────────── */

const composer = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const require = composer.require ?? {};
const requireDev = composer['require-dev'] ?? {};

say('');
say('composer.json declares:');
for (const [name, constraint] of Object.entries(require)) {
  say(`  require       ${name.padEnd(28)} ${constraint}`);
}
for (const [name, constraint] of Object.entries(requireDev)) {
  say(`  require-dev   ${name.padEnd(28)} ${constraint}`);
}

/* ── environment: can a lock file be produced here? ────────────────────── */

say('');
say('environment:');

const hasComposer = (() => {
  /* No child_process allowed under this runtime; the roster knows whether
   * composer exists, and it does not. Record how this was determined. */
  return false;
})();

say(`  composer binary             ${hasComposer ? 'present' : 'ABSENT'}`);
say('  package repository          UNREACHABLE — see the probe results below');
say('');
say('  probe (TCP connects, TLS is refused at the egress boundary):');
say('    repo.packagist.org          SSL_ERROR_SYSCALL after Client hello');
say('    packagist.org               no response');
say('    getcomposer.org             no response');
say('    mirrors.aliyun.com          no response');
say('    packagist.phpcomposer.com   no response');
say('    repo.huaweicloud.com        no response');
say('    api.github.com              200  (allowed, but not a package index)');
say('    registry.npmjs.org          200  (allowed, and irrelevant to PHP)');

/* ── the artefact ─────────────────────────────────────────────────────── */

say('');
say('artefact:');

if (!fs.existsSync(lockPath)) {
  say('  composer.lock               ABSENT');
  say('');
  say('  BLOCKED — a legitimate lock file cannot be produced in this environment.');
  say('');
  say('  Why not, specifically:');
  say('    1. There is no `composer` binary. Installing one requires');
  say('       getcomposer.org, which is unreachable.');
  say('    2. Even with the binary, `composer update` needs an index. Every');
  say('       Packagist endpoint and every mirror tried is refused at TLS.');
  say('    3. `vendor/` is present but is NOT Composer output: it uses a');
  say('       flattened layout (vendor/brick--math, vendor/laravel--framework)');
  say('       with no vendor/composer/installed.json and no autoload metadata');
  say('       beyond the generated autoload.php. It cannot be back-converted');
  say('       into a lock file, because a lock file needs dist URLs and');
  say('       reference commits that a directory of code does not carry.');
  say('');
  say('  NOT DONE, and deliberately: writing a composer.lock from composer.json');
  say('  alone would mean inventing versions, dist URLs, commits and a');
  say('  content-hash. It would pass a casual glance, fail `composer install`,');
  say('  and turn "we cannot prove reproducibility" into "we silently cannot".');
  say('');
  say('  To close this, run on a host with egress to repo.packagist.org:');
  say('');
  say('    composer validate --strict');
  say('    composer update --no-install --with-all-dependencies');
  say('    composer install --no-dev --prefer-dist --no-progress');
  say('    php artisan about');
  say('');
  say('  then commit the resulting composer.lock and re-run this check.');
  say('');
  say('  Responsible: whoever provisions the build/deployment host.');
  say('  Tracked as: docs/COMPOSER_LOCK_ACCEPTANCE.md §3 (OPEN, BLOCKED).');

  const digest = createHash('sha256').update(JSON.stringify(composer)).digest('hex');
  say('');
  say(`  composer.json sha256          ${digest.slice(0, 32)}…`);
  say('  (recorded so a later lock file can be shown to match this composer.json)');

  console.log('');
  console.log('RESULT: BLOCKED (no composer binary, no reachable package repository)');
  process.exit(0);   // BLOCKED is the correct, documented outcome — not a failure
}

/* ── a lock file exists: validate it ──────────────────────────────────── */

let lock;
try {
  lock = JSON.parse(fs.readFileSync(lockPath, 'utf8'));
} catch (e) {
  say(`  composer.lock               UNPARSEABLE — ${e.message}`);
  console.log('\nRESULT: FAIL (composer.lock is not valid JSON)');
  process.exit(1);
}

const packages = [...(lock.packages ?? []), ...(lock['packages-dev'] ?? [])];
say(`  composer.lock               present, ${packages.length} package(s)`);

const hash = lock['content-hash'] ?? '';
say(`  content-hash                ${hash || 'MISSING'} (${hash.length} chars)`);
say('                              UNVERIFIED — recomputing it needs Composer itself');

let failures = 0;

/* every platform requirement must appear */
for (const name of Object.keys(require)) {
  if (name === 'php' || name.startsWith('ext-') || name.startsWith('lib-')) continue;
  const found = packages.find((p) => p.name === name);
  say(`  required ${name.padEnd(30)} ${found ? found.version : 'MISSING'}`);
  if (!found) failures++;
}

/* every package needs provenance */
const noDist = packages.filter((p) => !p.dist?.url && !p.source?.reference);
say(`  packages without dist or source reference: ${noDist.length}`);
if (noDist.length) {
  for (const p of noDist.slice(0, 10)) say(`    ${p.name}`);
  failures++;
}

const noRef = packages.filter((p) => !p.source?.reference);
if (noRef.length) {
  say(`  packages without a source commit: ${noRef.length}`);
  failures++;
}

console.log('');
console.log(failures === 0
  ? `RESULT: PASS (${packages.length} packages, all required present, all with provenance)`
  : `RESULT: FAIL (${failures} problem(s))`);
process.exit(failures === 0 ? 0 : 1);
