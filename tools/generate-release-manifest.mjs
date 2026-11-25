#!/usr/bin/env node
/**
 * Generate docs/RELEASE_MANIFEST.md from the working tree.
 *
 * WHY GENERATED
 * -------------
 * A hand-written manifest is a document that claims "this release contains X"
 * and is checked by nobody. Every count in it is derivable from the tree, so it
 * is derived: file counts, checksums of the shipped assets, the dependency list,
 * and the checklist of documents a reviewer expects to find.
 *
 * Deliberately EXCLUDED from the output: timestamps and the git HEAD. Including
 * them would make the file differ on every commit, so a --check could never pass
 * and the document would go back to being decoration. The commit that a release
 * was cut from is recorded by git itself, which does not need a duplicate.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs/RELEASE_MANIFEST.md');

const git = (args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();

/**
 * Everything that would ship: tracked files plus untracked ones that are not
 * gitignored.
 *
 * `git ls-files` alone was the first version of this and it was wrong in a way
 * that is easy to miss: a newly written document is not tracked yet, so the
 * manifest reported documents that were sitting on disk as MISSING, and the same
 * file produced different output before and after `git add`. Basing the count on
 * the working tree instead makes the manifest describe the release as it stands,
 * stable across the commit boundary. Ignored paths (vendor/, node_modules/,
 * .arena/) stay excluded, so generated junk is still not counted.
 */
const tracked = git(['ls-files', '--cached', '--others', '--exclude-standard'])
  .split('\n').filter(Boolean);

const sha = (rel) => {
  try {
    return crypto.createHash('sha256')
      .update(fs.readFileSync(path.join(ROOT, rel)))
      .digest('hex')
      .slice(0, 16);
  } catch {
    return 'MISSING';
  }
};

const bytes = (rel) => {
  try {
    return fs.statSync(path.join(ROOT, rel)).size;
  } catch {
    return 0;
  }
};

/* ── inventory by extension ─────────────────────────────────────────────── */
const byExt = new Map();
for (const f of tracked) {
  const ext = path.extname(f).replace('.', '') || '(none)';
  byExt.set(ext, (byExt.get(ext) ?? 0) + 1);
}

/* ── tracked artefacts, with checksums ──────────────────────────────────── */
/*
 * database/database.sqlite is deliberately NOT checksummed.
 *
 * It was, and the gate failed on the next run: the file's bytes change whenever
 * the app touches it, even when every row count the isolation check compares is
 * identical. A "release manifest" whose checksum moves on a login is not
 * identifying a release, and a check that goes stale by itself trains people to
 * ignore it. What actually defines the schema and the demo data is the
 * migrations and the seeders, and those are stable — so those are hashed.
 */
const SHIPPED = [
  ['composer.json', 'وابستگی‌های PHP'],
  ['.env.example', 'الگوی متغیرهای محیط'],
  ['tools/build-assets.mjs', 'سازندهٔ دارایی‌ها — در مقصد اجرا می‌شود'],
];

/**
 * A single combined hash over the migrations and seeders, in sorted path order
 * so the result does not depend on filesystem ordering.
 */
const seedHash = (() => {
  const files = tracked
    .filter((f) => (f.startsWith('database/migrations/') || f.startsWith('database/seeders/'))
      && f.endsWith('.php'))
    .sort();
  const h = crypto.createHash('sha256');
  for (const f of files) {
    h.update(f);
    h.update(fs.readFileSync(path.join(ROOT, f)));
  }
  return { digest: h.digest('hex').slice(0, 16), count: files.length };
})();

/*
 * Build output is gitignored (/public/build, .gitignore:13), so it is NOT part
 * of the inventory above and does not travel with the repository. DEPLOYMENT_GUIDE
 * runs `node tools/build-assets.mjs` on the target instead. Listing these without
 * saying so would imply the release carries a stylesheet that git does not.
 */
const BUILT = [
  ['public/build/css/app.css', 'CSS کامپایل‌شده — تنها برگهٔ استایلی که بارگذاری می‌شود'],
  ['public/build/js/app.js', 'JavaScript کامپایل‌شده'],
];

/* ── dependencies, read from composer.json rather than remembered ───────── */
const composer = JSON.parse(fs.readFileSync(path.join(ROOT, 'composer.json'), 'utf8'));
const requires = Object.entries(composer.require ?? {})
  .filter(([name]) => name !== 'php')
  .sort(([a], [b]) => a.localeCompare(b));
const phpConstraint = composer.require?.php ?? '?';

/* ── the documents a reviewer looks for ─────────────────────────────────── */
const EXPECTED_DOCS = [
  'docs/ARCHITECTURE.md',
  'docs/DATABASE_SCHEMA.md',
  'docs/DATABASE_MAP.md',
  'docs/ERD.md',
  'docs/API_DOCUMENTATION.md',
  'docs/SECURITY_ARCHITECTURE.md',
  'docs/SECURITY_REVIEW.md',
  'docs/RBAC_PERMISSION_MATRIX.md',
  'docs/AUDIT_LOG_SPECIFICATION.md',
  'docs/BACKUP_RESTORE_GUIDE.md',
  'docs/BACKUP_RECOVERY.md',
  'docs/DEPLOYMENT_GUIDE.md',
  'docs/TEST_STRATEGY.md',
  'docs/REGRESSION_TEST_REPORT.md',
  'docs/DESIGN_SYSTEM.md',
  'docs/DOCUMENTATION_INDEX.md',
  'docs/CHANGELOG.md',
  'docs/CREDENTIAL_MODULE.md',
  'docs/pdf/PDF_ENGINE.md',
  'docs/pdf/PDF_TEST_REPORT.md',
];

const missingDocs = EXPECTED_DOCS.filter((d) => !tracked.includes(d));

const docCount = tracked.filter((f) => f.startsWith('docs/') && f.endsWith('.md')).length;
const phpCount = tracked.filter((f) => f.endsWith('.php')).length;
const viewCount = tracked.filter((f) => f.endsWith('.blade.php')).length;
const migrationCount = tracked.filter((f) => f.startsWith('database/migrations/') && f.endsWith('.php')).length;
const checkCount = tracked.filter((f) => f.startsWith('tools/checks/')).length;
const testCount = tracked.filter((f) => f.startsWith('tests/')).length;

let md = `# فهرست اقلام انتشار (Release Manifest)

> **تولیدشده.** با \`node tools/generate-release-manifest.mjs\` از درخت کاری ساخته
> می‌شود و \`docs-integrity\` هم‌خوانی‌اش را می‌سنجد. دستی ویرایش نکنید.
>
> عمداً **بدون مهر زمانی و بدون هش commit** است: آن‌ها باعث می‌شدند فایل در هر
> commit متفاوت شود، \`--check\` هرگز پاس نشود، و سند دوباره به تزئین تبدیل شود.
> اینکه انتشار از کدام commit بریده شده را خودِ git نگه می‌دارد و نیازی به نسخهٔ
> دومش نیست.

## موجودی

| مورد | تعداد |
|---|---:|
| فایل‌های انتشار (tracked + غیر‌ignore) | **${tracked.length}** |
| فایل PHP | ${phpCount} |
| قالب Blade | ${viewCount} |
| مهاجرت پایگاه داده | ${migrationCount} |
| سند Markdown زیر \`docs/\` | ${docCount} |
| بررسی در \`tools/checks/\` | ${checkCount} |
| فایل آزمون | ${testCount} |

### بر پایهٔ پسوند

| پسوند | تعداد |
|---|---:|
`;

for (const [ext, n] of [...byExt].sort((a, b) => b[1] - a[1]).slice(0, 10)) {
  md += `| \`.${ext}\` | ${n} |\n`;
}

md += `\n## اقلام همراه انتشار

هش‌ها SHA-256 و ۱۶ رقم اول‌اند. اگر فایلی در این جدول \`MISSING\` شد، یعنی وجود
ندارد — یعنی منتشر نمی‌شود.

| فایل | اندازه | SHA-256 (۱۶ رقم) | چیست |
|---|---:|---|---|
`;

for (const [rel, what] of SHIPPED) {
  const size = bytes(rel);
  const kb = size >= 1024 ? `${(size / 1024).toFixed(1)} KB` : `${size} B`;
  md += `| \`${rel}\` | ${kb} | \`${sha(rel)}\` | ${what} |\n`;
}

md += `
### تعریف پایگاه داده — خودِ فایل منتشر نمی‌شود

\`database/database.sqlite\` در \`database/.gitignore\` است (\`*.sqlite*\`)، پس
**با مخزن سفر نمی‌کند** و در شمارش بالا هم نیست. در مقصد باید با
\`migrate\` و \`db:seed\` ساخته شود — طبق \`docs/DEPLOYMENT_GUIDE.md\`.

هش هم عمداً **ندارد**، و دو دلیل دارد: اول اینکه اصلاً در انتشار نیست؛ دوم اینکه
بایت‌هایش هر بار که برنامه لمسش کند عوض می‌شود، حتی وقتی تعداد ردیف‌ها یکسان
بماند. هشی که با یک ورود به سیستم تغییر کند انتشار را شناسایی نمی‌کند و فقط
gate را الکی قرمز می‌کند. آنچه طرح و دادهٔ نمونه را واقعاً تعریف می‌کند
مهاجرت‌ها و seederها هستند:

| مورد | مقدار |
|---|---|
| فایل مهاجرت + seeder | ${seedHash.count} |
| SHA-256 ترکیبی (۱۶ رقم) | \`${seedHash.digest}\` |

اگر این هش با آنچه در مقصد می‌بینید فرق داشت، طرح یا دادهٔ نمونه فرق کرده است.

## ساخته‌شده در مقصد — در git نیست

\`/public/build\` در \`.gitignore\` است، پس این فایل‌ها **با مخزن سفر نمی‌کنند** و
جزء شمارش بالا نیستند. \`docs/DEPLOYMENT_GUIDE.md\` به‌جای آن
\`node tools/build-assets.mjs\` را روی مقصد اجرا می‌کند.

هش زیر مربوط به ساختِ همین ماشین است و برای **مقایسه** آمده: اگر خروجی مقصد این
هش را نداد، یعنی دارایی‌ها درست ساخته نشده‌اند.

| فایل | اندازه | SHA-256 (۱۶ رقم) |
|---|---:|---|
`;

for (const [rel] of BUILT) {
  const size = bytes(rel);
  const kb = size >= 1024 ? `${(size / 1024).toFixed(1)} KB` : `${size} B`;
  md += `| \`${rel}\` | ${kb} | \`${sha(rel)}\` |\n`;
}

md += `\n## وابستگی‌ها

PHP: \`${phpConstraint}\`

| بسته | قید نسخه |
|---|---|
`;

for (const [name, constraint] of requires) {
  md += `| \`${name}\` | \`${constraint}\` |\n`;
}

md += `
\`composer.lock\` در مخزن commit نشده است (چون Packagist در محیط توسعه در دسترس
نبود). در عوض \`tools/resolve-vendor.py\` وابستگی‌ها را با هش ثابت حل می‌کند و
\`--verify\` تعداد بسته‌های نصب‌شده را تأیید می‌کند.

**این یک ریسک واقعی انتشار است:** بدون \`composer.lock\`، نسخهٔ دقیقِ
وابستگی‌های غیرمستقیم روی ماشین مقصد ممکن است با آنچه اینجا آزموده شد فرق کند.

## فهرست اسناد مورد انتظار

`;

if (missingDocs.length === 0) {
  md += `هر ${EXPECTED_DOCS.length} سندِ مورد انتظار وجود دارد:\n\n`;
  for (const d of EXPECTED_DOCS) md += `- \`${d}\`\n`;
} else {
  md += `**${missingDocs.length} سند از فهرست مورد انتظار موجود نیست:**\n\n`;
  for (const d of missingDocs) md += `- \`${d}\`\n`;
  md += '\n';
}

md += `
## آنچه در این انتشار نیست

صادقانه، چون فهرستِ بدون این بخش تصویر ناقصی می‌دهد:

| نبود | وضعیت |
|---|---|
| کد بکاپ | **صفر** — جدول \`backup_runs\` ساخته نشده، فرمان کنسولی نیست |
| API عمومی | **صفر** — \`routes/api.php\` نیست، Sanctum/Passport نصب نیست |
| رابط خواندن گزارش رویدادها | **نیست** — داده نوشته می‌شود ولی هیچ صفحه‌ای ندارد |
| \`composer.lock\` | commit نشده |
| Vira Drive (بستر فایل) | طراحی شده، **صفر کد** |
| آزمون روی MariaDB/MySQL | نیست — همهٔ آزمون‌ها روی SQLite |

## دروازه‌های کیفیت

پیش از انتشار باید همه سبز باشند:

\`\`\`bash
node tools/checks/run-all.mjs          # ۳۶ سوئیت
\`\`\`

جزئیات آخرین اجرا در [\`REGRESSION_TEST_REPORT.md\`](REGRESSION_TEST_REPORT.md).
`;

if (process.argv.includes('--check')) {
  const current = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';
  if (current === md) {
    console.log('OK: docs/RELEASE_MANIFEST.md is current');
    process.exit(0);
  }
  console.log('STALE: docs/RELEASE_MANIFEST.md does not match the working tree');
  process.exit(1);
}

fs.writeFileSync(OUT, md, 'utf8');
console.log(`wrote docs/RELEASE_MANIFEST.md — ${tracked.length} tracked files`);
