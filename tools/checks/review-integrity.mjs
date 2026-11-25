#!/usr/bin/env node
/**
 * Review Environment integrity.
 *
 * Two classes of defect this suite exists to catch, both of which actually
 * happened while building the environment:
 *
 *   1. A catalogue entry whose `preview` points at a view that does not exist.
 *      The page then 404s and looks "not built" instead of "wired wrong".
 *   2. A `key` containing a dot. Laravel reads dots in route parameters as
 *      nested-array syntax, so `drive.permissions` produced a broken URL and a
 *      404 for a page that was perfectly fine. Eight keys had this defect.
 *
 * It also enforces the honesty rule: every catalogue row must carry one of the
 * four permitted statuses, and no page may claim "Functional" without a route
 * that really exists.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const results = [];
const check = (id, ok, detail) => {
  results.push({ id, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(34)} ${detail}`);
};

const config = fs.readFileSync(path.join(ROOT, 'config/review.php'), 'utf8');

// ── 1. every key is dot-free ────────────────────────────────────────────────
const keys = [...config.matchAll(/'key'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
const dotted = keys.filter((k) => k.includes('.'));
check('T-REV-keys-dotless', dotted.length === 0,
  dotted.length === 0 ? `${keys.length} catalogue keys, none contain a dot`
    : `${dotted.length} dotted keys: ${dotted.join(', ')}`);

// ── 2. every preview points at a real blade file ────────────────────────────
const previews = [...config.matchAll(/'preview'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
const missing = previews.filter(
  (p) => !fs.existsSync(path.join(ROOT, `resources/views/review/previews/${p}.blade.php`))
);
check('T-REV-previews-exist', missing.length === 0,
  missing.length === 0 ? `${previews.length} preview views resolve`
    : `missing views: ${missing.join(', ')}`);

// ── 3. statuses come only from the permitted four ──────────────────────────
const allowed = ['Functional', 'Partial', 'UI Preview', 'Not Implemented'];
const statuses = [...config.matchAll(/'status'\s*=>\s*'([^']+)'/g)].map((m) => m[1]);
const bad = statuses.filter((s) => !allowed.includes(s));
check('T-REV-status-vocabulary', bad.length === 0,
  bad.length === 0 ? `${statuses.length} rows use the four permitted statuses`
    : `unexpected: ${[...new Set(bad)].join(', ')}`);

// ── 4. no page claims Functional without a route that exists ───────────────
const rows = config.split("[\n").filter((b) => b.includes("'key'"));
const lying = rows.filter((b) => {
  const isFunctional = /'status'\s*=>\s*'Functional'/.test(b);
  const hasRoute = /'route'\s*=>\s*'(?!null')[^']+'/.test(b);
  return isFunctional && !hasRoute;
});
check('T-REV-functional-has-route', lying.length === 0,
  lying.length === 0 ? 'every Functional row names a real route'
    : `${lying.length} rows claim Functional with no route`);

// ── 5. the review surface cannot be mounted in production ──────────────────
const allowedEnv = /'allowed_environments'\s*=>\s*\[([^\]]+)\]/.exec(config)?.[1] ?? '';
check('T-REV-not-production',
  !/['"]production['"]/.test(allowedEnv) && /'enabled'\s*=>\s*env\(/.test(config),
  'enabled is env-driven and production is not an allowed environment');

// ── 6. routes are read-only (no POST/PUT/DELETE in the review route file) ──
const routes = fs.readFileSync(path.join(ROOT, 'routes/review.php'), 'utf8');
check('T-REV-read-only', !/Route::(post|put|patch|delete)/.test(routes),
  'the review route file registers GET routes only');

// ── 7. every catalogue row must carry the four §20 dimensions ──────────────
{
  // Each entry starts at its `'key' =>`; slice from there to the next key.
  const parts = config.split("'key' => '").slice(1);
  const missing = [];
  for (const part of parts) {
    const key = part.slice(0, part.indexOf("'"));
    for (const dim of ['design', 'responsive', 'interactive', 'issue']) {
      if (!new RegExp(`'${dim}'\\s*=>`).test(part)) missing.push(`${key}.${dim}`);
    }
  }
  check('T-REV-four-dimensions', missing.length === 0,
    missing.length === 0
      ? `${parts.length} pages carry design/responsive/interactive/issue`
      : `${missing.length} missing: ${missing.slice(0, 6).join(', ')}`);
}

// ── 8. no invisible bidi control characters in review markup ───────────────
// A U+200E (LRM) before a minus sign produced "syntax error, unexpected
// integer" in an array literal and a silent 500 on the page. The character is
// invisible in every editor, so a human reviewer cannot catch it — the check
// has to. ZWNJ (U+200C) is legitimate inside Persian words and is allowed.
{
  const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p2 = path.join(dir, e.name);
    return e.isDirectory() ? walk(p2) : p2.endsWith('.blade.php') ? [p2] : [];
  });

  const FORBIDDEN = /[\u200e\u200f\u202a-\u202e\ufeff]/;
  const offenders = [];

  for (const file of walk(path.join(ROOT, 'resources/views/review'))) {
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    lines.forEach((line, i) => {
      if (FORBIDDEN.test(line)) {
        offenders.push(`${path.relative(ROOT, file)}:${i + 1}`);
      }
    });
  }

  check('T-REV-no-bidi-controls', offenders.length === 0,
    offenders.length === 0
      ? 'no LRM/RLM/bidi overrides in review markup (ZWNJ allowed)'
      : `${offenders.length} lines: ${offenders.slice(0, 4).join(', ')}`);
}

/*
 * ── 9. no credential literal anywhere in the tree ─────────────────────────
 *
 * The demo password lived as a literal in a seeder, two check scripts and a
 * doc. Rotation removed the value but left the string in three explanatory
 * comments; a scanner still flags it and a reader learns nothing. Worse, the
 * original defect was invisible because nothing looked for it.
 *
 * This scans the tracked-suffix files for an assignment-shaped password and
 * for the specific retired literal, so neither can come back unnoticed.
 */
const CREDENTIAL_PATTERNS = [
  [/ReviewDemo!/, 'the retired demo password literal'],
  [/['"]password['"]\s*=>\s*['"][^'"$]{8,}['"]/, 'a hardcoded password value'],
  [/const\s+DEMO_PASSWORD\s*=/, 'a password constant'],
];

const SCAN_DIRS = ['database/seeders', 'tools', 'docs', 'resources/views/review'];
const SCAN_EXT = /\.(php|mjs|js|md|blade\.php)$/;
const offenders = [];

const walk = (dir) => {
  const abs = path.join(ROOT, dir);
  if (!fs.existsSync(abs)) return;
  for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
    const rel = path.join(dir, entry.name);
    if (entry.isDirectory()) { walk(rel); continue; }
    if (!SCAN_EXT.test(entry.name)) continue;
    if (rel.includes('node_modules')) continue;
    // This file defines the patterns; scanning it would always match.
    if (rel === 'tools/checks/review-integrity.mjs') continue;
    const text = fs.readFileSync(path.join(ROOT, rel), 'utf8');
    for (const [re, what] of CREDENTIAL_PATTERNS) {
      if (re.test(text)) offenders.push(`${rel}: ${what}`);
    }
  }
};
SCAN_DIRS.forEach(walk);

check('T-REV-no-credential-literal', offenders.length === 0,
  offenders.length === 0
    ? `${SCAN_DIRS.length} directories scanned, no credential literal`
    : `${offenders.length}: ${offenders.slice(0, 3).join(' · ')}`);

// Counted here, after every check has run. An earlier version computed this
// above check 9, so the new check printed PASS while the summary said 8/9 —
// a check that cannot fail its own suite.
const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);
process.exit(passed === results.length ? 0 : 1);
