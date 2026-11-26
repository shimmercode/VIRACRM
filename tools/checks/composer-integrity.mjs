#!/usr/bin/env node
/**
 * VIRA CRM — Composer integrity: what is declared, what is installed, and why
 * there is no lock file.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * `composer.lock` is absent from this repository. That fact was previously
 * carried as a one-line note in a report, which meant nothing failed if it
 * drifted, and nothing checked whether the installed tree actually matched
 * `composer.json`. A missing lock file is a real reproducibility risk — with
 * `minimum-stability: dev` and three floating constraints, two installs on two
 * days can resolve differently — so it is asserted here rather than described.
 *
 * This suite does NOT fabricate a lock file. Generating one needs Packagist,
 * which is unreachable from this environment; a hand-written lock with invented
 * hashes would be worse than none, because `composer install` on the client host
 * would either fail or silently resolve something else.
 *
 * WHAT IS ASSERTED
 *   K1  composer.json parses and declares what we think it declares
 *   K2  every direct require is actually installed
 *   K3  every installed direct require SATISFIES its declared constraint
 *   K4  no stray package sits in vendor/ outside the resolved graph
 *   K5  vendor/ is not tracked by git
 *   K6  composer.lock is absent AND that absence is documented, not silent
 *   K7  the PHP platform constraint matches the interpreter that runs the app
 *   K8  the resolver's own --verify agrees the tree is complete
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(30)} ${detail}`);
};

// ── constraint matching ────────────────────────────────────────────────────
// Deliberately narrow: it handles the shapes composer.json actually uses
// (exact, ^, ~, >=, and `|`/`||` unions) and refuses anything else rather than
// guessing. A constraint this cannot parse is reported as a failure, because
// silently treating an unknown constraint as satisfied is how a broken pin
// ships.
const parseVersion = (v) => String(v).replace(/^v/, '').split(/[.\-+]/).slice(0, 3)
  .map((n) => Number.parseInt(n, 10) || 0);

const cmp = (a, b) => {
  for (let i = 0; i < 3; i += 1) {
    if (a[i] !== b[i]) return a[i] < b[i] ? -1 : 1;
  }
  return 0;
};

const satisfiesOne = (version, constraint) => {
  const c = constraint.trim();
  if (c === '' || c === '*') return true;

  const v = parseVersion(version);

  if (c.startsWith('^')) {
    const base = parseVersion(c.slice(1));
    // ^1.2.3 := >=1.2.3 <2.0.0 ; ^0.2.3 := >=0.2.3 <0.3.0
    if (cmp(v, base) < 0) return false;
    const upper = base[0] > 0 ? [base[0] + 1, 0, 0] : [0, base[1] + 1, 0];
    return cmp(v, upper) < 0;
  }

  if (c.startsWith('~')) {
    const base = parseVersion(c.slice(1));
    if (cmp(v, base) < 0) return false;
    return cmp(v, [base[0], base[1] + 1, 0]) < 0;
  }

  if (c.startsWith('>=')) return cmp(v, parseVersion(c.slice(2))) >= 0;
  if (c.startsWith('<=')) return cmp(v, parseVersion(c.slice(2))) <= 0;
  if (c.startsWith('>')) return cmp(v, parseVersion(c.slice(1))) > 0;
  if (c.startsWith('<')) return cmp(v, parseVersion(c.slice(1))) < 0;
  if (c.startsWith('=')) return cmp(v, parseVersion(c.slice(1))) === 0;

  if (/^\d+(\.\d+){0,2}$/.test(c)) return cmp(v, parseVersion(c)) === 0;

  return null; // unparseable — caller must treat this as a failure
};

const satisfies = (version, constraint) => {
  // `^0.11|^0.12` and `^1.0 || ^2.0` are both unions.
  const parts = String(constraint).split(/\s*\|\|?\s*/);
  let sawUnknown = false;

  for (const part of parts) {
    const r = satisfiesOne(version, part);
    if (r === true) return true;
    if (r === null) sawUnknown = true;
  }

  return sawUnknown ? null : false;
};

// ── K1: composer.json ──────────────────────────────────────────────────────
const composerPath = path.join(ROOT, 'composer.json');
let composer = null;
try {
  composer = JSON.parse(fs.readFileSync(composerPath, 'utf8'));
  record('T-CMP-01-parse', true, 'composer.json parses');
} catch (e) {
  record('T-CMP-01-parse', false, `composer.json unreadable: ${e.message}`);
}

if (composer) {
  const required = Object.keys(composer.require || {});
  record('T-CMP-01-declares', required.length >= 3,
    `require declares: ${required.join(', ')}`);

  // ── Resolve what is actually installed ─────────────────────────────────
  const cacheDir = path.join(ROOT, '.vendor-cache');
  const vendorDir = path.join(ROOT, 'vendor');
  const mangle = (name) => name.replace('/', '--');

  const installed = new Map();
  if (fs.existsSync(cacheDir)) {
    for (const f of fs.readdirSync(cacheDir).filter((x) => x.endsWith('.json'))) {
      try {
        const meta = JSON.parse(fs.readFileSync(path.join(cacheDir, f), 'utf8'));
        if (meta.name) installed.set(meta.name, meta);
      } catch { /* a corrupt cache entry is reported by the stray check */ }
    }
  }

  const isPlatform = (name) => name === 'php' || name.startsWith('ext-')
    || name.startsWith('composer-');

  const direct = required.filter((n) => !isPlatform(n));

  // ── K2 / K3: installed and constraint-satisfying ───────────────────────
  let missing = [];
  let violating = [];
  let unverifiable = [];

  for (const name of direct) {
    const meta = installed.get(name);
    if (!meta) { missing.push(name); continue; }

    const constraint = composer.require[name];
    const r = satisfies(meta.version, constraint);

    if (r === null) unverifiable.push(`${name} ${meta.version} vs "${constraint}"`);
    else if (!r) violating.push(`${name} ${meta.version} does not satisfy "${constraint}"`);
  }

  record('T-CMP-02-installed', missing.length === 0,
    missing.length ? `NOT installed: ${missing.join(', ')}`
      : `all ${direct.length} direct dependencies installed`);

  record('T-CMP-03-constraints', violating.length === 0 && unverifiable.length === 0,
    [...violating, ...unverifiable].join(' | ')
    || `all ${direct.length} installed versions satisfy their declared constraints`);

  for (const name of direct) {
    const meta = installed.get(name);
    if (meta) console.log(`        ${name.padEnd(22)} ${meta.version.padEnd(10)} (tag ${meta.tag})`);
  }

  // ── K4: no strays in vendor/ ───────────────────────────────────────────
  const vendorDirs = fs.existsSync(vendorDir)
    ? fs.readdirSync(vendorDir).filter((d) => d.includes('--'))
    : [];
  const resolvedNames = new Set([...installed.values()].map((m) => mangle(m.name)));
  const strays = vendorDirs.filter((d) => !resolvedNames.has(d));

  record('T-CMP-04-no-strays', strays.length === 0,
    strays.length ? `unresolved directories in vendor/: ${strays.slice(0, 5).join(', ')}`
      : `all ${vendorDirs.length} vendor directories come from the resolved graph`);

  // ── K5: vendor/ must not be tracked ────────────────────────────────────
  const tracked = spawnSync('git', ['ls-files', 'vendor/'], { cwd: ROOT, encoding: 'utf8' });
  const trackedCount = (tracked.stdout || '').trim() ? tracked.stdout.trim().split('\n').length : 0;

  record('T-CMP-05-vendor-untracked', trackedCount === 0,
    trackedCount ? `${trackedCount} vendor files are tracked by git` : 'vendor/ is gitignored, nothing tracked');

  // ── K6: lock file absence must be documented, not silent ───────────────
  const lockExists = fs.existsSync(path.join(ROOT, 'composer.lock'));
  const lockTracked = spawnSync('git', ['ls-files', '--error-unmatch', 'composer.lock'],
    { cwd: ROOT, encoding: 'utf8' }).status === 0;

  if (lockExists) {
    // If a lock file ever appears, it must be tracked and consistent.
    let lockOk = false;
    let detail = '';
    try {
      const lock = JSON.parse(fs.readFileSync(path.join(ROOT, 'composer.lock'), 'utf8'));
      const lockNames = new Set((lock.packages || []).map((p) => p.name));
      const absent = direct.filter((n) => !lockNames.has(n));
      lockOk = absent.length === 0;
      detail = lockOk
        ? `composer.lock present, tracked=${lockTracked}, covers all ${direct.length} direct deps`
        : `composer.lock missing entries: ${absent.join(', ')}`;
    } catch (e) {
      detail = `composer.lock unreadable: ${e.message}`;
    }
    record('T-CMP-06-lock', lockOk && lockTracked, detail);
  } else {
    /*
     * The lock file is absent. That is only acceptable while it is (a) explained
     * and (b) tracked as a known blocker — a silently missing lock file is how a
     * host ends up resolving different versions than the ones tested here.
     */
    const doc = path.join(ROOT, 'docs/PRODUCTION_READINESS.md');
    const explained = fs.existsSync(doc)
      && /composer\.lock/.test(fs.readFileSync(doc, 'utf8'));

    record('T-CMP-06-lock-absent-documented', explained,
      explained
        ? 'composer.lock absent; the reason and the host remediation are documented in docs/PRODUCTION_READINESS.md'
        : 'composer.lock absent and NOT documented — a reproducibility risk nobody would notice');
  }

  // ── K7: PHP platform constraint vs the interpreter ─────────────────────
  // Reads the existing capabilities probe rather than shelling out for
  // `php -v`: there is no native PHP here, only the WASM CLI wrapper, and that
  // wrapper takes a script path — not `-r`. Reusing env-capabilities.php also
  // means this check reports the same interpreter the rest of the suite uses.
  const phpConstraint = composer.require.php;
  const caps = spawnSync(process.execPath,
    ['docs/tools/php-cli.mjs', 'tools/checks/env-capabilities.php'],
    { cwd: ROOT, encoding: 'utf8', timeout: 120_000 });
  const phpVersion = ((caps.stdout || '') + (caps.stderr || ''))
    .match(/^PHP\s*:\s*(\d+\.\d+\.\d+)/m)?.[1];

  if (phpVersion && phpConstraint) {
    const okPhp = satisfies(phpVersion, phpConstraint);
    record('T-CMP-07-php-platform', okPhp === true,
      `PHP ${phpVersion} vs require.php "${phpConstraint}" -> ${okPhp === null ? 'UNPARSEABLE' : okPhp}`);
  } else {
    record('T-CMP-07-php-platform', false,
      `could not determine PHP version (got "${phpVersion}") for constraint "${phpConstraint}"`);
  }

  // ── K8: the resolver's own verification ────────────────────────────────
  const verify = spawnSync('python3', ['tools/resolve-vendor.py', '--verify'],
    { cwd: ROOT, encoding: 'utf8', timeout: 120_000 });
  const verifyOut = ((verify.stdout || '') + (verify.stderr || '')).trim().split('\n').pop();

  record('T-CMP-08-resolver-verify', verify.status === 0,
    `resolve-vendor.py --verify exit=${verify.status}: ${verifyOut}`);
}

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);
if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}
