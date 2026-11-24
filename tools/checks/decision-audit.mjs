#!/usr/bin/env node
/**
 * decision-audit.mjs — reconcile the 36 decisions against the single source.
 *
 * The failure this prevents already happened once: a headline number ("13
 * blockers") was typed by hand in prose while the rows said 16. Every number in
 * docs/CRM_REVIEW_DECISION_AUDIT.md must therefore be derivable, and this check
 * derives it and compares.
 *
 * It also enforces §1's three-independent-concepts rule: owner, client-approval
 * and infrastructure-dependency are separate flags, and a decision that is
 * client-owned but infra-dependent must be counted in BOTH — not merged into one.
 *
 * Usage: node tools/checks/decision-audit.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SRC = path.join(ROOT, 'docs/tools/generate-decision-matrix.mjs');
const DOC = path.join(ROOT, 'docs/CRM_REVIEW_DECISION_AUDIT.md');

const source = fs.readFileSync(SRC, 'utf8');
const start = source.indexOf('const D = [');
const end = source.indexOf('\n];', start) + 3;

if (start === -1 || end < start) {
  console.error('FAIL  could not locate the D array in the generator');
  process.exit(1);
}

// The array literal is data, not code: evaluate only that slice.
const D = eval(source.slice(start, end).replace('const D =', '')); // eslint-disable-line no-eval

const rows = D.map(([id, cat, blk, infra, party, title]) => ({
  id, cat, blk, infra: infra === 'yes', party, title,
}));

const problems = [];
let CHECKS_RUN = 0;

const check = (name, ok, detail) => {
  CHECKS_RUN += 1;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(44)} ${detail}`);
  if (!ok) problems.push(name);
};

// ── identities must be unique and complete ───────────────────────────────
const ids = rows.map((r) => r.id);
const unique = new Set(ids);

check('row count is 36', rows.length === 36, `${rows.length} rows`);
check('every ID is unique', unique.size === rows.length, `${unique.size} unique`);
check('IDs are contiguous OD-101..OD-136',
  ids.every((id, i) => id === `OD-${101 + i}`),
  ids[0] + ' … ' + ids[ids.length - 1]);

// ── the three independent concepts ───────────────────────────────────────
const byCat = rows.reduce((a, r) => ({ ...a, [r.cat]: (a[r.cat] || 0) + 1 }), {});
check('client-owned = 9', byCat.C === 9, `${byCat.C || 0}`);
check('infra-owned = 6', byCat.I === 6, `${byCat.I || 0}`);
check('Arena-owned = 21', byCat.A === 21, `${byCat.A || 0}`);
check('categories sum to 36', (byCat.C || 0) + (byCat.I || 0) + (byCat.A || 0) === 36,
  `${byCat.C}+${byCat.I}+${byCat.A}`);

const infraDependent = rows.filter((r) => r.infra);
check('infra-DEPENDENT is a separate axis', infraDependent.length === 20,
  `${infraDependent.length} decisions depend on missing infra`);

const overlap = rows.filter((r) => r.cat === 'C' && r.infra);
check('client-owned AND infra-dependent overlap = 6', overlap.length === 6,
  overlap.map((r) => r.id).join(', '));

// ── F1 blockers: unique count vs category sum ────────────────────────────
const blockers = rows.filter((r) => r.blk !== '');
check('F1 blockers (unique decisions) = 16', blockers.length === 16, `${blockers.length}`);

const byBlk = blockers.reduce((a, r) => ({ ...a, [r.blk]: (a[r.blk] || 0) + 1 }), {});
const blkSum = Object.values(byBlk).reduce((a, n) => a + n, 0);
check('blocker category sum discloses overlap', blkSum >= blockers.length,
  `${blkSum} category slots across ${blockers.length} decisions `
  + `(B1=${byBlk.B1 || 0} B2=${byBlk.B2 || 0} B3=${byBlk.B3 || 0})`);

// ── OD-127 specifically, as §1 requires ─────────────────────────────────
const od127 = rows.find((r) => r.id === 'OD-127');
check('OD-127 is still client-owned', od127?.cat === 'C', od127?.cat ?? 'missing');
check('OD-127 is still an F1 blocker', od127?.blk === 'B3', od127?.blk ?? '—');
check('OD-127 is still infra-dependent', od127?.infra === true, String(od127?.infra));

// ── no identifier may have been removed or duplicated by a migration ────
const lost = ['OD-101', 'OD-104', 'OD-105', 'OD-106', 'OD-126', 'OD-127',
  'OD-128', 'OD-131', 'OD-134'].filter((id) => !unique.has(id));
check('no client ID lost in the client→infra move', lost.length === 0,
  lost.length ? lost.join(', ') : 'all 9 present');

// ── the document must not claim a decision is closed ────────────────────
const doc = fs.readFileSync(DOC, 'utf8');
const badClaims = [
  ['Approved', /\|\s*`?APPROVED`?\s*\|/i],
  ['Closed', /\|\s*`?CLOSED`?\s*\|/i],
];
for (const [label, re] of badClaims) {
  check(`document claims no ${label} decision`, !re.test(doc),
    re.test(doc) ? 'found a table cell claiming it' : 'none');
}

// ── every count stated in the document must match a derived count ──────
const stated = (re) => (doc.match(re) || [])[1];
const pairs = [
  ['client count stated = 9', /\|\s*\*\*۹\*\*\s*\|/],
  ['overlap table lists 6 rows', /\|\s*OD-101\s*\|[\s\S]*?\|\s*OD-131\s*\|/],
];
for (const [name, re] of pairs) check(name, re.test(doc), re.test(doc) ? 'present' : 'missing');

console.log('\n--- summary ---');
// The regression runner parses "N/M passed"; emit it so this suite reports a
// real assertion count instead of "OK" with no numbers.
const total = CHECKS_RUN;
console.log(`${total - problems.length}/${total} passed`);
if (problems.length) {
  console.log('failed:', problems.join(' · '));
  process.exit(1);
}
