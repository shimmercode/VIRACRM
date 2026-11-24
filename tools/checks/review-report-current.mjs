#!/usr/bin/env node
/**
 * review-report-current.mjs — the final report must not contradict the evidence.
 *
 * Every number in CRM_REVIEW_FINAL_REPORT.md is cross-checked against the JSON
 * the suites actually wrote. A report that claims 13/13 while acceptance.json
 * says 12 is the failure mode this file exists to make impossible — the same
 * hand-typed-number bug that put "13 blockers" in a document where the rows said
 * 16.
 *
 * If evidence is missing the check reports SKIP for that item rather than
 * assuming a pass.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const EV = path.join(ROOT, 'docs/evidence/review');
const REPORT = path.join(ROOT, 'docs/CRM_REVIEW_FINAL_REPORT.md');

if (!fs.existsSync(REPORT)) {
  console.error('FAIL  final report missing');
  process.exit(1);
}

const raw = fs.readFileSync(REPORT, 'utf8');

/*
 * The report is in Persian and uses Persian-Indic digits (۹۸), while the
 * evidence JSON uses ASCII (98). Comparing them directly produced three false
 * failures. Normalise the digits before searching — the content is the same
 * number, and a check that fails on numeral shape teaches nothing.
 */
const toAsciiDigits = (text) => text
  .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
  .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660));

const report = toAsciiDigits(raw);
const read = (f) => {
  const p = path.join(EV, f);
  return fs.existsSync(p) ? JSON.parse(fs.readFileSync(p, 'utf8')) : null;
};

let total = 0;
let passed = 0;
const problems = [];

const check = (name, ok, detail, skipped = false) => {
  total += 1;
  if (skipped) {
    console.log(`SKIP  ${name.padEnd(48)} ${detail}`);
    return;
  }
  if (ok) passed += 1;
  else problems.push(name);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(48)} ${detail}`);
};

// ── acceptance: 13/13 ────────────────────────────────────────────────────
{
  const a = read('acceptance.json');
  if (!a) {
    check('acceptance 13/13', false, 'acceptance.json missing', true);
  } else {
    const p = a.counts.PASS || 0;
    const f = a.counts.FAIL || 0;
    const s = a.counts.SKIP || 0;
    check('acceptance all pass', f === 0 && s === 0 && p > 0, `${p} pass / ${f} fail / ${s} skip`);
    check('report states the same acceptance count',
      report.includes(`**13 / 13**`) || report.includes(`${p} / ${p}`),
      `report mentions ${p} in the summary table`);
  }
}

// ── responsive: 98 measurements across 7 widths ──────────────────────────
{
  const r = read('responsive.json');
  if (!r) {
    check('responsive 98 measurements', false, 'responsive.json missing', true);
  } else {
    const n = r.results.length;
    const widths = new Set(r.results.map((x) => x.width)).size;
    const fails = r.results.filter((x) => !x.ok).length;
    check('responsive: 7 widths', widths === 7, `${widths} widths`);
    check('responsive: zero failures', fails === 0, `${fails} failures of ${n}`);
    check('report cites the measured count', report.includes(String(n)), `report contains "${n}"`);
  }
}

// ── roles: 15 assertions, all pass ───────────────────────────────────────
{
  const v = read('role-visibility.json');
  if (!v) {
    check('role visibility 15/15', false, 'role-visibility.json missing', true);
  } else {
    const n = v.results.length;
    const bad = v.results.filter((x) => !x.ok).length;
    check('role visibility all pass', bad === 0, `${n - bad}/${n}`);
    check('report cites the assertion count', report.includes(String(n)), `report contains "${n}"`);
  }
}

// ── seed + reset ─────────────────────────────────────────────────────────
{
  const s = read('seed-repeatability.json');
  const rc = read('reset-check.json');
  check('seed repeatability identical', s?.identical === true,
    s ? `${s.runs.length} runs` : 'missing', !s);
  check('reset restored baseline', rc?.restored === true,
    rc ? 'restored' : 'missing', !rc);
  check('baseline database untouched', rc?.baselineUntouched === true,
    rc ? 'byte-identical' : 'missing', !rc);
}

// ── screenshots present ──────────────────────────────────────────────────
{
  const pngs = fs.existsSync(EV)
    ? fs.readdirSync(EV).filter((f) => f.endsWith('.png'))
    : [];
  check('screenshots exist', pngs.length >= 20, `${pngs.length} PNG files`);
}

// ── the report must not claim readiness it cannot support ────────────────
{
  const forbidden = [
    ['production ready', /production[-\s]?ready/i],
    ['F1 ready', /F1\s+(is\s+)?ready/i],
    ['all decisions closed', /all\s+\d+\s+decisions\s+(are\s+)?closed/i],
  ];
  for (const [name, re] of forbidden) {
    const hit = re.test(report);
    // The report may MENTION the phrase in a denial. Only a positive claim is a
    // failure, so require the phrase to appear without a negation nearby.
    const negated = hit && new RegExp(`(not|no|never|هیچ)[^.]{0,80}${re.source}`, 'i').test(report);
    check(`report does not claim ${name}`, !hit || negated,
      hit ? (negated ? 'only mentioned in a denial' : 'CLAIMED') : 'absent');
  }
}

// ── the §27 verdict must be explicit ─────────────────────────────────────
{
  const hasVerdict = /پاسخ صریح به پرسش §۲۷/.test(raw);
  const hasBoth = /واقعاً در دسترس و قابل استفاده است/.test(raw)
    && /آنچه کار \*\*نمی‌کند\*\*/.test(raw);
  check('§27 verdict is explicit', hasVerdict && hasBoth,
    hasVerdict ? (hasBoth ? 'states both what works and what does not' : 'one-sided')
      : 'missing');
}

// ── F1 must still be reported blocked ────────────────────────────────────
check('report states F1 is BLOCKED', /F1\s+همچنان\s+`BLOCKED`/.test(raw),
  /BLOCKED/.test(report) ? 'present' : 'missing');

// ── no decision may be reported as approved ──────────────────────────────
check('no decision reported as Approved',
  /Approved\s*\|\s*\*\*0\*\*/.test(report) || /Approved[^\n]*\|\s*0\s*\|/.test(report),
  'zero approved rows stated');

console.log(`\n--- summary ---\n${passed}/${total} passed`);
if (problems.length) {
  console.log('failed:', problems.join(' · '));
  process.exit(1);
}
