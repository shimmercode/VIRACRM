#!/usr/bin/env node
/**
 * generate-page-inventory.mjs — the page inventory, derived from the catalogue.
 *
 * WHY THIS EXISTS
 * ---------------
 * Two inventory documents disagreed. One said "4 preview / 15 not implemented",
 * the other "19 preview / 0 not implemented", and both sat in docs/ where a
 * reader cannot tell which is current. The directive calls this report drift: a
 * number typed by hand on Tuesday contradicts the truth on Friday and nothing
 * fails.
 *
 * So the number is no longer typed. `config/review.php` is the single source of
 * truth; tools/checks/dump-review-catalogue.php asks PHP to serialise it; this
 * script renders the document. `--check` regenerates and compares bytes, so a
 * stale document fails the suite.
 *
 * Usage:
 *   node tools/generate-page-inventory.mjs           # write
 *   node tools/generate-page-inventory.mjs --check    # exit 1 if stale
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs/CRM_REVIEW_PAGE_INVENTORY.md');
const CHECK = process.argv.includes('--check');

const dump = spawnSync(process.execPath, [
  path.join(ROOT, 'docs/tools/php-cli.mjs'),
  path.join(ROOT, 'tools/checks/dump-review-catalogue.php'),
], { cwd: ROOT, encoding: 'utf8', timeout: 300_000 });

if (dump.status !== 0) {
  console.error('could not read the catalogue:\n', dump.stderr || dump.stdout);
  process.exit(2);
}

// php-cli prints a trailing timing line; take the JSON that ends at the first
// line containing only "]".
const raw = dump.stdout;
const end = raw.indexOf('\n]');
const catalogue = JSON.parse(raw.slice(0, end === -1 ? raw.length : end + 2));

if (!Array.isArray(catalogue) || catalogue.length === 0) {
  console.error('catalogue came back empty');
  process.exit(2);
}

const count = (s) => catalogue.filter((p) => p.status === s).length;
const F = count('Functional');
const P = count('Partial');
const U = count('UI Preview');
const N = count('Not Implemented');

const urlOf = (p) => (p.route && p.path ? p.path : (p.preview ? `/review/preview/${p.key}` : null));

const GROUPS = [
  ['core', 'هسته'],
  ['operations', 'عملیات'],
  ['finance', 'مالی'],
  ['admin', 'مدیریت و دسترسی'],
  ['drive', 'Vira Drive'],
];

const lines = [];
const push = (...x) => lines.push(...x);

push(
  '# CRM_REVIEW_PAGE_INVENTORY — فهرست کامل صفحه‌ها',
  '',
  '> **این سند تولیدشده است — دستی ویرایش نکنید.**',
  '> منبع: `config/review.php` · تولید: `node tools/generate-page-inventory.mjs`',
  '> اعتبارسنجی: `--check` که در `run-all` اجرا می‌شود.',
  '',
  '## خلاصهٔ وضعیت',
  '',
  '| وضعیت | تعداد | معنا |',
  '|---|:-:|---|',
  `| Functional | ${F} | مسیر واقعی، کوئری واقعی، تعامل واقعی |`,
  `| Partial | ${P} | داده واقعی هست، ولی منطق مجوز/کارکرد کامل نیست |`,
  `| UI Preview | ${U} | طراحی کامل در همان Shell؛ هیچ بک‌اندی ندارد |`,
  `| Not Implemented | ${N} | هیچ صفحه‌ای وجود ندارد |`,
  `| **جمع** | **${catalogue.length}** | |`,
  '',
);

if (N > 0) {
  push(
    `> **${N} صفحه هنوز هیچ UI ندارد.** تا این عدد صفر نشود، محیط بازبینی`,
    '> بازبینیِ فهرست است نه بازبینیِ محصول.',
    '',
  );
} else {
  push(
    '**۰ صفحه بدون UI.** هر صفحهٔ کاتالوگ یک صفحهٔ واقعی دارد. آن‌هایی که بک‌اند',
    'ندارند صریحاً `UI Preview` برچسب خورده‌اند و در همان صفحه می‌نویسند که هیچ',
    'چیزی ذخیره، ارسال یا اجرا نمی‌شود.',
    '',
  );
}

for (const [g, label] of GROUPS) {
  const rows = catalogue.filter((p) => p.group === g);
  if (rows.length === 0) continue;

  push(
    `## ${label} — ${rows.length} صفحه`,
    '',
    '| صفحه | وضعیت | URL |',
    '|---|---|:-:|',
  );

  for (const r of rows) {
    const u = urlOf(r);
    push(`| ${r.title || r.key} | \`${r.status}\` | ${u ? `\`${u}\`` : '—'} |`);
  }
  push('');
}

const previewed = catalogue.filter((p) => p.status === 'UI Preview' || p.status === 'Partial');
if (previewed.length) {
  push(
    '## بُعد چهارم: چه کاری می‌کند و چه مشکلی دارد',
    '',
    '| صفحه | تعامل واقعی | مشکل شناخته‌شده |',
    '|---|---|---|',
  );
  for (const p of previewed) {
    push(`| ${p.title || p.key} | ${p.interactive || '—'} | ${p.issue || '—'} |`);
  }
  push('');
}

push(
  '## قاعده‌ای که این جدول را قابل‌اتکا می‌کند',
  '',
  'هر ردیف `Functional` باید به مسیری اشاره کند که واقعاً وجود دارد. هر ردیف',
  '`UI Preview` باید `preview` داشته باشد و آن فایل Blade موجود باشد. اگر یکی',
  'از این‌ها نباشد، `tools/checks/review-integrity.mjs` شکست می‌خورد.',
  '',
  'و خودِ این سند از همان کاتالوگ ساخته می‌شود — پس عددی که می‌خوانید نمی‌تواند',
  'با کاتالوگ اختلاف داشته باشد.',
  '',
);

const generated = lines.join('\n');

if (CHECK) {
  const existing = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';
  if (existing !== generated) {
    console.error('STALE — docs/CRM_REVIEW_PAGE_INVENTORY.md does not match the catalogue');
    console.error('run: node tools/generate-page-inventory.mjs');
    process.exit(1);
  }
  console.log(`${catalogue.length} pages — document is current`);
  process.exit(0);
}

fs.writeFileSync(OUT, generated);
console.log(`${catalogue.length} pages → ${path.relative(ROOT, OUT)}`);
