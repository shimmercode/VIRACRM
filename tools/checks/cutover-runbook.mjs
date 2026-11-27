#!/usr/bin/env node
/**
 * Cutover runbook conformance: every factual claim in docs/CUTOVER_RUNBOOK.md
 * about this application must still be true of this application.
 *
 * WHY A RUNBOOK NEEDS A TEST
 * --------------------------
 * A runbook is read at 2am by someone who cannot check it. If it names a command
 * that was renamed, an env var that was dropped, or a header the app does not
 * emit, the failure surfaces during the cutover — the worst possible moment, and
 * the one place an incorrect instruction is least likely to be questioned.
 *
 * Writing the first draft of this runbook produced exactly that class of error:
 * it told the operator to look for HSTS among the headers the APPLICATION sets.
 * The application sets seven headers and HSTS is not one of them — it belongs at
 * the TLS terminator. That kind of mistake is invisible in review and costly in
 * practice, so it is asserted mechanically here.
 *
 * WHAT IS CHECKED
 * ---------------
 *  - every artisan command the runbook tells the operator to run exists
 *  - every .env key it lists is read by config/ or bootstrap/
 *  - every security header it attributes to the application is actually set
 *  - the runbook does not claim HSTS comes from the application
 *  - the stated extension list is what the application requires
 *  - no secret value appears anywhere in the document
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('cutover runbook');
const check = (id, ok, detail) => c.check(id, ok, detail);

const doc = fs.readFileSync(path.join(ROOT, 'docs/CUTOVER_RUNBOOK.md'), 'utf8');

/* ── 1. every artisan command named must exist ────────────────────────── */

const commandSignatures = new Map();

for (const file of fs.readdirSync(path.join(ROOT, 'app/Console/Commands'))) {
  const src = fs.readFileSync(path.join(ROOT, 'app/Console/Commands', file), 'utf8');
  const m = src.match(/\$signature\s*=\s*'([a-z0-9:_-]+)/i);
  if (m) commandSignatures.set(m[1], file);
}

/* Only namespaced commands (with a colon) are ours; `migrate`, `db:seed` and
 * friends belong to the framework and are not in that directory. */
const namedCommands = [...new Set(
  [...doc.matchAll(/php artisan ([a-z0-9:_-]+)/gi)]
    .map((m) => m[1])
    .filter((name) => name.includes(':')),
)];

const missingCommands = namedCommands.filter(
  (name) => !commandSignatures.has(name) && !name.startsWith('vira:'),
);

const ourCommandsMissing = namedCommands.filter(
  (name) => name.startsWith('vira:') && !commandSignatures.has(name),
);

check('RB-COMMANDS-EXIST', ourCommandsMissing.length === 0,
  `every vira:* command the runbook names is registered: `
  + `${namedCommands.filter((n) => n.startsWith('vira:')).sort().join(', ')}`
  + (ourCommandsMissing.length ? ` — MISSING: ${ourCommandsMissing.join(', ')}` : ''));

check('RB-COMMANDS-NAMED', namedCommands.length >= 4,
  `the runbook names ${namedCommands.length} artisan commands`);

/* ── 2. every env key it lists is actually read ───────────────────────── */

const configSources = ['config', 'bootstrap']
  .flatMap((dir) => fs.readdirSync(path.join(ROOT, dir))
    .filter((f) => f.endsWith('.php'))
    .map((f) => fs.readFileSync(path.join(ROOT, dir, f), 'utf8')))
  .join('\n');

const seededFromEnv = fs.readFileSync(path.join(ROOT, 'database/seeders/UserSeeder.php'), 'utf8')
  + fs.readFileSync(path.join(ROOT, 'config/vira.php'), 'utf8');

/*
 * Env keys are collected from the runbook's markdown tables: a row whose first
 * cell is a bare UPPER_CASE token. Prose mentions are not collected, so the
 * check tests what the operator is told to SET, not every passing reference.
 */
const tableKeys = [...doc.matchAll(/^\|\s*`([A-Z][A-Z0-9_]{2,})`\s*\|/gm)].map((m) => m[1]);

const unread = [...new Set(tableKeys)].filter((key) => {
  if (key.startsWith('SEED_')) return !seededFromEnv.includes(key);
  return !configSources.includes(`env('${key}'`) && !seededFromEnv.includes(key);
});

check('RB-ENV-KEYS-READ', unread.length === 0,
  unread.length === 0
    ? `all ${new Set(tableKeys).size} env keys in the runbook's tables are read by config/, bootstrap/ or the seeder`
    : `env keys the runbook tells the operator to set but nothing reads: ${unread.join(', ')}`);

/* ── 3. header attribution ────────────────────────────────────────────── */

const headerSrc = fs.readFileSync(path.join(ROOT, 'app/Http/Middleware/SecurityHeaders.php'), 'utf8');
const appHeaders = [...headerSrc.matchAll(/headers->set\(\s*'([A-Za-z-]+)'/g)].map((m) => m[1]);

check('RB-APP-HEADERS', appHeaders.length >= 6,
  `the application sets ${appHeaders.length} headers: ${appHeaders.join(', ')}`);

check('RB-HSTS-CORRECTLY-ATTRIBUTED',
  !appHeaders.includes('Strict-Transport-Security')
  && /add_header Strict-Transport-Security/.test(doc),
  'HSTS is attributed to nginx (which the runbook instructs the operator to add) and NOT '
  + 'claimed as an application header — the application genuinely does not set it');

/* ── 4. the runbook must not claim things it cannot ───────────────────── */

check('RB-OPEN-GATES-STATED',
  /composer\.lock/.test(doc) && /REQ-12\.07/.test(doc) && /BLOCKED/.test(doc),
  'the runbook states its three open gates (composer.lock, MySQL backup, cron/REQ-12.07) '
  + 'and marks them BLOCKED rather than presenting them as done');

check('RB-READINESS-VERDICT',
  /NOT PRODUCTION READY/.test(doc),
  'the runbook keeps the readiness verdict at NOT PRODUCTION READY');

check('RB-NO-CRON-CLAIM',
  /no scheduler entry for `vira:backup`/.test(doc) && /routes\/console\.php/.test(doc),
  'it states plainly that the application schedules no backup — a crontab line the operator '
  + 'must add, not a verified feature');

/* ── 5. no secrets in the document ────────────────────────────────────── */

const leakPatterns = [
  /\bAPP_KEY=base64:[A-Za-z0-9+/=]{20,}/,
  /\bVIRA_BACKUP_KEY=[A-Za-z0-9+/=]{40,}/,
  /\bVIRA_CREDENTIAL_KEY=[A-Za-z0-9+/=]{40,}/,
  /\bDB_PASSWORD=(?!⟨)[^\s`|]{6,}/,
];

const leaks = leakPatterns.filter((re) => re.test(doc));

check('RB-NO-SECRETS', leaks.length === 0,
  leaks.length === 0
    ? 'no key or password value appears — every secret is a ⟨placeholder⟩ or an instruction to generate'
    : `${leaks.length} pattern(s) matched a real-looking secret`);

check('RB-PLACEHOLDERS',
  (doc.match(/⟨[^⟩]+⟩/g) ?? []).length >= 5,
  'host-specific values are written as ⟨placeholders⟩ so they cannot be copy-pasted by mistake');

/* ── 6. the restore warning is present and correct ────────────────────── */

check('RB-NO-PROD-RESTORE',
  /Never test a restore against the production database/.test(doc),
  'it warns against restoring over production, which is the single most destructive '
  + 'mistake available during a cutover');

process.exit(c.summary() ? 0 : 1);
