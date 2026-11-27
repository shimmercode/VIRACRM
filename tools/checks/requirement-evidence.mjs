#!/usr/bin/env node
/**
 * Audit the requirement-closure evidence pointers.
 *
 * WHY THIS EXISTS
 * ───────────────
 * The closure document is only as good as its citations. A status is worth
 * nothing if the artefact it names does not exist, or if it names nothing at
 * all — and both failures have already happened here: one VERIFIED item cited
 * a suite (`work-items-flow`) that was never written, and most VERIFIED items
 * carried `—` where the evidence belongs.
 *
 * This tool joins the three sources and reports, per requirement:
 *
 *   · whether the evidence field is populated;
 *   · whether every path / suite / test it names actually exists;
 *   · whether a VERIFIED status rests on anything at all.
 *
 * It does not judge whether the evidence is *sufficient* — only whether it is
 * real. Sufficiency is a human judgement recorded in the closure document.
 *
 * USAGE
 *   node tools/checks/requirement-evidence.mjs           # summary
 *   node tools/checks/requirement-evidence.mjs --verbose # one line per req
 *
 * EXIT
 *   0  every VERIFIED requirement cites at least one artefact that exists
 *   1  at least one VERIFIED requirement does not
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const verbose = process.argv.includes('--verbose');

const status = JSON.parse(fs.readFileSync(path.join(ROOT, 'tools/traceability-status.json'), 'utf8'));

/* The universe of artefacts a citation may legitimately point at. */
const roster = fs.readFileSync(path.join(ROOT, 'tools/checks/run-all.mjs'), 'utf8');
const suiteIds = new Set([...roster.matchAll(/id: '([a-z0-9-]+)'/g)].map((m) => m[1]));
const checkScripts = new Set(
  fs.readdirSync(path.join(ROOT, 'tools/checks'))
    .filter((f) => f.endsWith('.mjs') || f.endsWith('.php'))
    .map((f) => f.slice(0, -4)),
);
const testFiles = fs.existsSync(path.join(ROOT, 'tests'))
  ? new Set(fs.readdirSync(path.join(ROOT, 'tests')).filter((f) => f.endsWith('.php')).map((f) => f.slice(0, -4)))
  : new Set();

const knownName = (tok) => suiteIds.has(tok) || checkScripts.has(tok) || testFiles.has(tok);

/* A citation is "real" if it names an existing repo path or a known artefact. */
const isReal = (citation) => {
  const c = citation.trim().replace(/^[`'"[]|[`'"\]]$/g, '');
  if (c === '' || c === '—' || c === '-') return null; // empty, not a failure

  /*
   * Only a token that LOOKS like a path is treated as one. Persian prose
   * contains slashes rarely, but an earlier version of this check matched on
   * `includes('/')` alone and reported sixteen sentences as broken citations.
   * A real path here starts with a known top-level directory and has no spaces.
   */
  const first = c.split(/\s+/)[0].replace(/[:;,.]+$/, '');

  if (/^(app|config|routes|database|resources|tests|tools|docs|bootstrap|public|storage)\//.test(first)) {
    return fs.existsSync(path.join(ROOT, first));
  }

  /* bare name: a suite id, a script basename, or a test class */
  const base = first.replace(/\.(mjs|php)$/, '');
  if (knownName(base)) return true;

  /* anything else is prose, not a citation */
  return null;
};

const problems = [];
const verifiedNoEvidence = [];

for (const [id, [st, summary, evidence]] of Object.entries(status)) {
  const raw = (evidence ?? '').trim();
  const parts = raw.split(/[;,]/).map((s) => s.trim()).filter(Boolean);
  const assessed = parts.map((p) => ({ p, ok: isReal(p) })).filter((x) => x.ok !== null);
  const anyReal = assessed.some((x) => x.ok);
  const broken = assessed.filter((x) => x.ok === false);

  if (broken.length) {
    problems.push({ id, broken: broken.map((b) => b.p), raw });
  }

  if (st === 'VERIFIED' && !anyReal) {
    verifiedNoEvidence.push({ id, summary, raw });
  }

  if (verbose) {
    const mark = broken.length ? 'BROKEN' : anyReal ? 'ok' : 'none';
    console.log(`${mark.padEnd(6)} ${id.padEnd(12)} ${st.padEnd(16)} ${raw.slice(0, 60)}`);
  }
}

console.log(`\nrequirements audited          : ${Object.keys(status).length}`);
console.log(`VERIFIED with no real evidence: ${verifiedNoEvidence.length}`);

if (verifiedNoEvidence.length) {
  const byPrefix = {};
  for (const v of verifiedNoEvidence) {
    const pre = v.id.split('.')[0];
    byPrefix[pre] = (byPrefix[pre] ?? 0) + 1;
  }
  console.log('  by group: ' + Object.entries(byPrefix).map(([k, n]) => `${k}:${n}`).join(' '));
}

console.log(`citations naming a missing file: ${problems.length}`);
for (const p of problems) {
  console.log(`  ${p.id}: ${p.broken.join(', ')}`);
}

/*
 * The exit code keys on VERIFIED-without-evidence, because that is the claim
 * the directive forbids. A broken citation is printed but does not fail the
 * run: some requirements legitimately cite a suite by a name the roster
 * spells differently, and that is a wording fix, not a false claim.
 */
process.exit(verifiedNoEvidence.length === 0 && problems.length === 0 ? 0 : 1);
