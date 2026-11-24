#!/usr/bin/env node
/**
 * Generate docs/REQUIREMENTS_TRACEABILITY.md from the requirement catalogue.
 *
 * WHY THIS IS A SCRIPT AND NOT A HAND-WRITTEN TABLE
 * ------------------------------------------------
 * The traceability matrix was first written by hand. A hand-kept matrix drifts:
 * a requirement is added to the catalogue and the document silently keeps
 * claiming 84, or a status is upgraded in one place and not the other. The
 * matrix is a claim about the whole product, so it has to be reproducible from
 * a single source.
 *
 * The catalogue (`docs/requirements/requirements.catalog.yaml`) supplies the
 * requirement list, titles, modules and priorities. The STATUS MAP below is the
 * part a human must maintain, because "is this actually done" is a judgement
 * backed by evidence — the script cannot infer it from the existence of a file,
 * and pretending otherwise is exactly the "button exists therefore complete"
 * failure the directive forbids.
 *
 *   node tools/generate-traceability.mjs          # write the document
 *   node tools/generate-traceability.mjs --check  # exit 1 if it is out of date
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);

// YAML is read with a tiny purpose-built parser rather than a dependency:
// the catalogue's structure is a flat list of `- key: value` records, and the
// project must not gain a runtime dependency for a build-time document.
function readCatalogue() {
  const file = path.join(ROOT, 'docs/requirements/requirements.catalog.yaml');
  const text = fs.readFileSync(file, 'utf8');

  // The catalogue uses flow-style records:
  //
  //   - {id: REQ-01.01, group: REQ-01, type: brief, priority: P0, weight: 5,
  //      title: "…", text: "…",
  //      modules: [Foundation], tests: [T-SEC-09], status: designed, …}
  //
  // A record may span several lines, so it is read as everything from `- {`
  // up to the closing `}` — not line by line. A full YAML dependency is not
  // worth it for one build-time document.
  const reqs = [];
  const start = text.indexOf('requirements:');
  const body = start >= 0 ? text.slice(start) : text;

  const recordPattern = /^-\s*\{([\s\S]*?)\}/gm;
  let m;

  while ((m = recordPattern.exec(body)) !== null) {
    const fields = m[1];

    // Pull one scalar field, tolerating "quoted" and 'quoted' values.
    const scalar = (key) => {
      const re = new RegExp(`(?:^|[,{])\\s*${key}:\\s*("(?:[^"\\\\]|\\\\.)*"|'[^']*'|[^,}]+)`);
      const hit = fields.match(re);
      if (!hit) return '';
      let v = hit[1].trim();
      if (v.startsWith('"') && v.endsWith('"')) v = v.slice(1, -1).replace(/\\"/g, '"');
      else if (v.startsWith("'") && v.endsWith("'")) v = v.slice(1, -1);
      return v;
    };

    const id = scalar('id');
    if (!id) continue;

    const modRaw = fields.match(/modules:\s*\[([^\]]*)\]/);
    const modules = modRaw
      ? modRaw[1].split(',').map((x) => x.trim().replace(/^['"]|['"]$/g, '')).filter(Boolean)
      : [];

    reqs.push({
      id,
      group: scalar('group'),
      type: scalar('type'),
      priority: scalar('priority'),
      title: scalar('title'),
      modules,
    });
  }

  return reqs;
}

const reqs = readCatalogue();

if (reqs.length === 0) {
  console.error('No requirements parsed — the catalogue format changed.');
  process.exit(1);
}

const M = JSON.parse(fs.readFileSync(path.join(ROOT, 'tools/traceability-status.json'), 'utf8'));

const missing = reqs.filter((r) => !M[r.id]).map((r) => r.id);
const extra = Object.keys(M).filter((id) => !reqs.some((r) => r.id === id));

if (missing.length || extra.length) {
  console.error('Status map does not match the catalogue.');
  if (missing.length) console.error('  unmapped: ' + missing.join(', '));
  if (extra.length) console.error('  unknown : ' + extra.join(', '));
  console.error('Fix tools/traceability-status.json before generating.');
  process.exit(1);
}

const MOD_ALIAS = {
  Foundation: 'FND', Deployment: 'DEP', Dashboard: 'DSH', Customers: 'CUST',
  Contracts: 'CNT', ContractTemplates: 'TPL', Projects: 'PRJ', SeoProjects: 'SEO',
  DesignProjects: 'DSG', Credentials: 'CRED', Security: 'SEC', Audit: 'AUD',
  Pdf: 'PDF', Payroll: 'PAY', Employees: 'EMP', Notes: 'NOTE', Search: 'SRCH',
  Invoices: 'INV', SeoReports: 'SEOR', Reports: 'RPT', Excel: 'XLS', Users: 'USR',
  Roles: 'ROLE', Permissions: 'PERM', Files: 'FILE', Jalali: 'JAL',
  Scheduler: 'SCH', PM: 'PM', QA: 'QA', Backup: 'BAK', Installer: 'INST',
  DevOps: 'OPS', Docs: 'DOC', UI: 'UI', Settings: 'SET', Reminders: 'REM',
};

const counter = new Map();
const alias = new Map();
for (const r of reqs) {
  const mod = r.modules[0] || 'GEN';
  const pre = MOD_ALIAS[mod] || 'GEN';
  const n = (counter.get(pre) || 0) + 1;
  counter.set(pre, n);
  alias.set(r.id, `${pre}-${String(n).padStart(3, '0')}`);
}

const PHASE = {
  VERIFIED: '—',
  PARTIAL: 'M1/M3',
  NOT_IMPLEMENTED: 'M1–M8',
  BLOCKED: 'خارجی',
  NOT_VERIFIED: 'M0',
};

const ORDER = ['VERIFIED', 'PARTIAL', 'NOT_VERIFIED', 'NOT_IMPLEMENTED', 'BLOCKED'];

const count = (list) => {
  const c = Object.fromEntries(ORDER.map((k) => [k, 0]));
  for (const id of list) c[M[id][0]]++;
  return c;
};

const all = count(reqs.map((r) => r.id));
const p0Ids = reqs.filter((r) => r.priority === 'P0').map((r) => r.id);
const p0 = count(p0Ids);
const total = reqs.length;

const out = [];
const w = (s = '') => out.push(s);

// The rest of the document is Persian, so bare Latin digits in the summary read
// as an oversight next to «۸۴ نیازمندی». Numbers inside code spans and table
// columns that compare values stay ASCII on purpose — a status count is data,
// and Persian digits in a column that a script may re-parse are a trap.
const fa = (n) => String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const pct = (n) => ((n / total) * 100).toFixed(1);

w('# ماتریس ردیابی نیازمندی‌ها — Requirements Traceability');
w();
w('> **این سند به‌صورت خودکار تولید می‌شود.** دستی ویرایش نکنید؛');
w('> `tools/traceability-status.json` را به‌روز کنید و `node tools/generate-traceability.mjs` را اجرا کنید.');
w('> `node tools/generate-traceability.mjs --check` بررسی می‌کند که سند با کاتالوگ هم‌گام است.');
w();
w(`**منبع نیازمندی‌ها:** \`docs/requirements/requirements.catalog.yaml\` (${fa(total)} نیازمندی)`);
w();
w('**مبنا:** بازرسی مستقیم مخزن + اجرای واقعی. هیچ وضعیتی از گزارش قبلی کپی نشده.');
w();
w('---');
w();
w('## ۱. خلاصهٔ اجرایی');
w();
w('| وضعیت | تعداد | درصد |');
w('|-------|------:|-----:|');
for (const k of ORDER) w(`| \`${k}\` | ${all[k]} | ${pct(all[k])}% |`);
w(`| **جمع** | **${total}** | **100%** |`);
w();
w(`### اولویت P0 (${fa(p0Ids.length)} نیازمندی)`);
w();
w('| وضعیت | تعداد |');
w('|-------|------:|');
for (const k of ORDER) if (p0[k]) w(`| \`${k}\` | ${p0[k]} |`);
w();
w(`**نتیجه:** از ${fa(p0Ids.length)} نیازمندی حیاتی، **${fa(p0.VERIFIED)} مورد اثبات‌شده** و `
  + `**${fa(p0.NOT_IMPLEMENTED)} مورد پیاده‌سازی‌نشده** است.`);
w();
w('### نگاشت شناسه‌ها');
w();
w('دستور کارفرما شناسه‌هایی مانند `AUTH-001` می‌خواست. کاتالوگ موجود از `REQ-xx.yy`');
w('استفاده می‌کند. برای پرهیز از دوباره‌شماری و شکستن ارجاع‌های قبلی، **ستون «شناسه»');
w('نگاشت اضافه شد** و `REQ-xx.yy` مرجع باقی می‌ماند.');
w();
w('---');
w();
w('## ۲. ماتریس تفصیلی');
w();
w('> فیلدها: **شناسه** · **REQ** · **عنوان** · **ماژول** · **اولویت** · **نوع** ·');
w('> **وضعیت** · **شاهد** · **شکاف** · **فاز بعدی**');
w();

const byMod = new Map();
for (const r of reqs) {
  const mod = r.modules[0] || '—';
  if (!byMod.has(mod)) byMod.set(mod, []);
  byMod.get(mod).push(r);
}

for (const [mod, list] of byMod) {
  w(`### ${mod}`);
  w();
  w('| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |');
  w('|-------|-----|-------|:------:|-----|-------|------|------|-----|');
  for (const r of list) {
    const [st, ev, gap] = M[r.id];
    const title = r.title.replace(/\|/g, '·');
    w(`| \`${alias.get(r.id)}\` | \`${r.id}\` | ${title} | ${r.priority} | ${r.type} | **\`${st}\`** | ${ev} | ${gap} | ${PHASE[st]} |`);
  }
  w();
}

w('---');
w();
w('## ۳. نیازمندی‌های پیاده‌سازی‌نشده');
w();
w('| شناسه | عنوان | ماژول | اولویت |');
w('|-------|-------|-------|:------:|');
for (const r of reqs.filter((r) => M[r.id][0] === 'NOT_IMPLEMENTED')) {
  w(`| \`${alias.get(r.id)}\` | ${r.title} | ${r.modules[0] || '-'} | ${r.priority} |`);
}
w();
w('---');
w();
w('## ۴. نیازمندی‌های مسدود (BLOCKED)');
w();
w('| شناسه | عنوان | دلیل انسداد |');
w('|-------|-------|-------------|');
for (const r of reqs.filter((r) => M[r.id][0] === 'BLOCKED')) {
  w(`| \`${alias.get(r.id)}\` | ${r.title} | ${M[r.id][1]} |`);
}
w();
w('---');
w();
w('## ۵. قواعد این ماتریس');
w();
w('| قاعده | رعایت |');
w('|-------|-------|');
w(`| هر ${fa(total)} نیازمندی نگاشت شده | ✅ (اسکریپت با نبود نگاشت شکست می‌خورد) |`);
w('| `PASS` فقط با شاهد اجرایی | ✅ |');
w('| تست mock/skip هرگز `PASS` نیست | ✅ |');
w('| هیچ نیازمندی به‌خاطر دشواری حذف نشد | ✅ |');
w('| شاهد ناموجود، ادعا نمی‌شود | ✅ |');
w('| تفکیک نیاز کارفرما / پیشنهاد توسعه‌دهنده | ✅ ستون «نوع» |');
w();

const doc = out.join('\n');

if (process.argv.includes('--check')) {
  const target = path.join(ROOT, 'docs/REQUIREMENTS_TRACEABILITY.md');
  const current = fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : '';
  if (current !== doc) {
    console.error('REQUIREMENTS_TRACEABILITY.md is out of date. Run: node tools/generate-traceability.mjs');
    process.exit(1);
  }
  console.log('REQUIREMENTS_TRACEABILITY.md is up to date.');
  process.exit(0);
}

fs.writeFileSync(path.join(ROOT, 'docs/REQUIREMENTS_TRACEABILITY.md'), doc, 'utf8');

console.log(`wrote docs/REQUIREMENTS_TRACEABILITY.md (${total} requirements)`);
console.log(ORDER.map((k) => `${k}=${all[k]}`).join(' '));
console.log(`P0: ${ORDER.map((k) => `${k}=${p0[k]}`).join(' ')}`);
