#!/usr/bin/env node
/**
 * Generate docs/DATABASE_SCHEMA.md from the live SQLite schema.
 *
 * WHY GENERATED, NOT WRITTEN
 * -------------------------
 * A hand-written schema document is wrong within a week and nobody notices,
 * because the code keeps working. This reads PRAGMA table_info the same way the
 * application does, so the document cannot describe a column that does not
 * exist — the exact failure this project already had once with
 * `employees.department_id`, where the model declared a column the migration
 * never created.
 *
 * It also states what SQLite cannot express: there are no enum types, no
 * CHECK constraints from the schema, and several NOT NULL rules live in the
 * application instead. That gap is the single most important thing a reader of
 * this document needs to know before writing SQL against it.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DB = path.join(ROOT, 'database/database.sqlite');
const OUT = path.join(ROOT, 'docs/DATABASE_SCHEMA.md');

/* Group tables by module. Anything unlisted lands in "Other" and is visible as
   such — a new table must not silently belong to nothing. */
const GROUPS = {
  'هویت و دسترسی': ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'password_reset_tokens', 'sessions'],
  'مشتریان': ['customers', 'customer_contacts', 'customer_websites'],
  'پروژه‌ها': ['projects', 'project_types', 'project_assignments', 'project_keywords', 'seo_packages', 'seo_project_details', 'design_project_details'],
  'قراردادها': ['contracts', 'contract_types', 'contract_templates', 'contract_versions', 'contract_files'],
  'دسترسی‌های فنی': ['project_credentials', 'credential_types', 'credential_values', 'credential_requirements'],
  'حسابداری': ['ledger_accounts', 'fiscal_periods', 'journal_entries', 'journal_lines', 'invoices', 'invoice_items', 'payments', 'payment_allocations', 'cost_centers'],
  'منابع انسانی': ['employees', 'departments', 'employee_contracts', 'payroll_periods', 'payroll_rules', 'payslips', 'payroll_items', 'payslip_versions'],
  'اسناد و پیوست‌ها': ['attachments'],
  'عملیات و گزارش': ['work_items', 'notes', 'audit_logs', 'scheduled_events', 'job_runs', 'settings', 'settings_history'],
  'زیرساخت': ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
};

const sql = (q) => execFileSync(
  process.execPath,
  [
    path.join(ROOT, 'docs/tools/php-cli.mjs'),
    path.join(ROOT, 'tools/checks/schema-dump.php'),
    DB,
    q,
  ],
  { cwd: ROOT, encoding: 'utf8', maxBuffer: 40 * 1024 * 1024 },
).replace(/^\[php-cli\][^\n]*\n?/gm, '').trim();

const tables = sql("select name from sqlite_master where type='table' and name not like 'sqlite_%' order by name")
  .split('\n').filter(Boolean);

const rowsOf = (t) => {
  const out = sql(`select count(*) from "${t}"`);
  return Number(out) || 0;
};

const columnsOf = (t) => {
  const raw = sql(`pragma table_info("${t}")`);
  return raw.split('\n').filter(Boolean).map((line) => {
    const [cid, name, type, notnull, dflt, pk] = line.split('\t');
    return { name, type: type || '—', notNull: notnull === '1', dflt: dflt || '', pk: pk === '1' };
  });
};

const fksOf = (t) => {
  const raw = sql(`pragma foreign_key_list("${t}")`);
  return raw.split('\n').filter(Boolean).map((line) => {
    const parts = line.split('\t');
    return `${parts[3]}.${parts[4]} → ${parts[2]}.${parts[5] ?? 'id'}`;
  });
};

const indexesOf = (t) => {
  const raw = sql(`select name, sql from sqlite_master where type='index' and tbl_name='${t}' and sql is not null`);
  return raw.split('\n').filter(Boolean);
};

const known = new Set(Object.values(GROUPS).flat());
const other = tables.filter((t) => !known.has(t));

let md = `# ساختار پایگاه داده

> **این فایل تولید می‌شود.** با \`node tools/generate-schema-doc.mjs\` از
> \`database/database.sqlite\` بازتولید می‌شود. دستی ویرایش نکنید؛ تغییر در
> مهاجرت‌ها بدهید و دوباره تولید کنید.

منبع حقیقت، مهاجرت‌های \`database/migrations/\` هستند. این سند آن‌ها را همان‌طور
می‌خواند که اپلیکیشن می‌خواند.

## آنچه SQLite بیان نمی‌کند

این نکته را قبل از نوشتن هر کوئری بخوانید:

1. **نوع ستون تزئینی است.** SQLite هر ستون را می‌پذیرد؛ \`integer\` فقط یک
   affinity است. اعتبارسنجی واقعی در لاراول انجام می‌شود.
2. **هیچ \`ENUM\` وجود ندارد.** مقادیری مثل \`contracts.status\` یا
   \`payslips.state\` در PHP به enum تبدیل می‌شوند (\`App\\Enums\\*\`) و همان‌جا
   فهرست مجاز تعریف می‌شود.
3. **\`CHECK\` تقریباً هیچ‌جا نیست.** قواعدی مثل «مبلغ منفی نباشد» در سرویس‌ها
   هستند، نه در پایگاه داده.
4. **\`UNSIGNED\` معنا ندارد.** این یک تلهٔ واقعی بوده: \`payroll_items.amount\`
   در MySQL علامت‌دار نیست، پس جهت (دریافتی/کسورات) یک **ستون** است
   (\`direction\`)، نه علامت عدد. روی SQLite عدد منفی کار می‌کند و روی
   MariaDB خطای out-of-range می‌دهد.
5. **حذف نرم.** جدول‌هایی با ستون \`deleted_at\` هرگز واقعاً حذف نمی‌شوند.
   شماره‌گذاری قرارداد عمداً ردیف‌های حذف‌شده را هم می‌شمارد تا شمارهٔ تکراری
   صادر نشود.

## فهرست جدول‌ها

`;

const emojiEmpty = (n) => (n === 0 ? '۰' : String(n));

const dumpTable = (t) => {
  const cols = columnsOf(t);
  const fks = fksOf(t);
  const idx = indexesOf(t);
  const count = rowsOf(t);

  let out = `### \`${t}\`\n\n`;
  out += `ردیف‌های موجود در پایگاه نمونه: **${emojiEmpty(count)}**\n\n`;
  out += '| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |\n|---|---|---|---|---|\n';
  for (const c of cols) {
    out += `| \`${c.name}\` | ${c.type} | ${c.notNull ? 'خیر' : 'بله'} | ${c.dflt ? '`' + c.dflt + '`' : '—'} | ${c.pk ? 'PK' : ''} |\n`;
  }
  if (fks.length) {
    out += `\n**کلیدهای خارجی:** ${fks.map((f) => '`' + f + '`').join('، ')}\n`;
  }
  if (idx.length) {
    out += '\n**ایندکس‌ها:**\n\n';
    for (const line of idx) out += '```sql\n' + line + '\n```\n';
  }
  return out + '\n';
};

for (const [group, list] of Object.entries(GROUPS)) {
  const present = list.filter((t) => tables.includes(t));
  if (!present.length) continue;
  md += `## ${group}\n\n`;
  for (const t of present) md += dumpTable(t);
}

if (other.length) {
  md += `## طبقه‌بندی‌نشده\n\n`;
  md += `این جدول‌ها در گروه‌های بالا نیستند. اگر جدولی اینجاست، یا جدید است و\n`;
  md += `باید در \`GROUPS\` بایند شود، یا باقی‌ماندهٔ یک ماژول حذف‌شده است.\n\n`;
  for (const t of other) md += dumpTable(t);
}

/* A drift check the reader can run — and that tools/checks/docs-integrity can
   call later. */
md += `## بررسی هم‌خوانی

\`\`\`bash
node tools/generate-schema-doc.mjs --check
\`\`\`

اگر این سند با پایگاه داده هم‌خوان نباشد، کد خروج ۱ است. مقایسه روی
پایگاهِ نمونه انجام می‌شود؛ روی سرور مشتری، \`php artisan migrate\` منبع است.
`;

if (process.argv.includes('--check')) {
  const current = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';
  if (current === md) {
    console.log('OK: docs/DATABASE_SCHEMA.md is current');
    process.exit(0);
  }
  console.log('STALE: docs/DATABASE_SCHEMA.md does not match the database');
  process.exit(1);
}

fs.writeFileSync(OUT, md, 'utf8');
console.log(`wrote docs/DATABASE_SCHEMA.md — ${tables.length} tables`);
