#!/usr/bin/env node
/**
 * Generate docs/RBAC_PERMISSION_MATRIX.md from the seeded roles/permissions.
 *
 * WHY GENERATED
 * -------------
 * An access-control document that is maintained by hand is an access-control
 * document that is wrong. The matrix below is read out of `permissions`,
 * `roles` and `role_permissions`, so it describes what the running system will
 * actually allow — not what someone remembered to type.
 *
 * `tools/checks/docs-integrity.mjs` calls this with --check, so the document
 * going stale is a build failure rather than a surprise during an audit.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DB = path.join(ROOT, 'database/database.sqlite');
const OUT = path.join(ROOT, 'docs/RBAC_PERMISSION_MATRIX.md');

const sql = (q) => execFileSync(
  process.execPath,
  [path.join(ROOT, 'docs/tools/php-cli.mjs'), path.join(ROOT, 'tools/checks/schema-dump.php'), DB, q],
  { cwd: ROOT, encoding: 'utf8', maxBuffer: 40 * 1024 * 1024 },
).replace(/^\[php-cli\][^\n]*\n?/gm, '').trim();

const lines = (q) => sql(q).split('\n').filter(Boolean);

/* roles: id \t code \t title */
const roles = lines('select id, code, title from roles order by id').map((l) => {
  const [id, code, title] = l.split('\t');
  return { id, code, title };
});

/* permissions: id \t code \t group */
/* The column is `area` (customers / contracts / credentials / ...) and there is
 * an `is_sensitive` flag, which is more useful than it looks: a matrix that
 * cannot distinguish "may list credentials" from "may export every secret" is
 * the matrix that gets an audit finding. */
const perms = lines('select id, code, coalesce(area, \'\'), is_sensitive from permissions order by code').map((l) => {
  const [id, code, area, sensitive] = l.split('\t');
  return { id, code, area, sensitive: sensitive === '1' };
});

/* role_permissions as a map: permission_id -> Set(role_id) */
const assignment = new Map();
for (const l of lines('select role_id, permission_id from role_permissions')) {
  const [roleId, permId] = l.split('\t');
  if (!assignment.has(permId)) assignment.set(permId, new Set());
  assignment.get(permId).add(roleId);
}

/* Which permissions does a role hold, for the summary counts. */
const countByRole = new Map(roles.map((r) => [r.id, 0]));
for (const [, set] of assignment) for (const r of set) countByRole.set(r, (countByRole.get(r) ?? 0) + 1);

/* Group permissions by their group field, falling back to the code prefix. */
const grouped = new Map();
for (const p of perms) {
  const key = p.area || p.code.split('.')[0];
  if (!grouped.has(key)) grouped.set(key, []);
  grouped.get(key).push(p);
}

const users = Number(sql('select count(*) from users')) || 0;
const superadmins = Number(sql('select count(*) from users where is_superadmin = 1')) || 0;

let md = `# ماتریس نقش و مجوز

> **این فایل تولید می‌شود.** با \`node tools/generate-rbac-doc.mjs\` از پایگاه
> داده بازتولید می‌شود و \`docs-integrity\` هم‌خوانی‌اش را بررسی می‌کند.
> دستی ویرایش نکنید.

${roles.length} نقش، **${perms.length}** مجوز، ${users} کاربر (${superadmins} ابرمدیر).

## مدل

- **ابرمدیر** (\`users.is_superadmin\`) از همهٔ بررسی‌ها عبور می‌کند و وجودش در
  ماتریس معنا ندارد. عمداً ستون جدا دارد تا «همه‌کاره» یک تصمیم صریح باشد، نه
  مجموعه‌ای از تیک‌ها که کسی یادش رفته بردارد.
- **نقش‌ها** از طریق \`user_roles\` به کاربر وصل می‌شوند. یک کاربر می‌تواند چند
  نقش داشته باشد و مجوزهایشان **جمع** می‌شود.
- **مجوزها** با \`permission:*\` روی مسیرها اعمال می‌شوند. یک بررسی در میدل‌ور
  کافی نیست که دکمه را مخفی کند — همان مسیر باید مستقل هم بررسی شود، وگرنه
  یک POST مستقیم از آن عبور می‌کند.
- **جداسازی عمدی:** \`contracts.finalize\` از \`contracts.create\` جداست؛
  \`credentials.reveal\` و \`credentials.export\` از \`credentials.view\` جدا هستند؛
  \`payroll.manage\` از \`payroll.approve\` جدا است. کارِ آماده‌کردن و کارِ تأیید
  کردن دو قدرت متفاوت‌اند.

## ماتریس

مقدار هر خانه: ✅ مجوز دارد، — ندارد. ⚠️ یعنی مجوز در \`permissions.is_sensitive\`
حساس علامت خورده است — خواندن یا بیرون‌بردن دادهٔ محرمانه، نه فقط دیدن فهرست.

| مجوز | حساس | ${roles.map((r) => r.title).join(' | ')} |
|---|---|${roles.map(() => '---').join('|')}|
`;

for (const [group, list] of [...grouped].sort(([a], [b]) => a.localeCompare(b))) {
  md += `| **${group}** | |${roles.map(() => ' |').join('')}\n`;
  for (const p of list) {
    const held = assignment.get(p.id) ?? new Set();
    const cells = roles.map((r) => (held.has(r.id) ? '✅' : '—'));
    md += `| \`${p.code}\` | ${p.sensitive ? '⚠️' : ''} | ${cells.join(' | ')} |\n`;
  }
}

md += `\n## جمع هر نقش\n\n| نقش | کد | تعداد مجوز |\n|---|---|---|\n`;
for (const r of roles) {
  md += `| ${r.title} | \`${r.code}\` | ${countByRole.get(r.id) ?? 0} |\n`;
}

/* Permissions that no role holds are a real finding: either dead, or a role is
   missing from the seed. Surface them rather than letting the matrix hide them. */
const orphans = perms.filter((p) => !assignment.has(p.id));
/*
 * A permission held by no role is reachable only by a super admin, because
 * User::hasPermission() short-circuits on is_superadmin. That is sometimes the
 * intent and sometimes a seed gap, and telling them apart is the whole point of
 * this section — an unlabelled list would let a real gap hide inside a
 * deliberate decision.
 */
const SUPERADMIN_ONLY = {
  // SECURITY & RELEASE READINESS AUDIT, stage 1.1 — deliberate. Export gathers
  // every password on a project into one portable file; once saved, no
  // permission, audit row or no-store header can reach it. The route is also
  // not scoped to owned projects, so any role holding it could export every
  // project's secrets.
  'credentials.export': 'عمدی — تصمیم ممیزی امنیت، مرحلهٔ ۱.۱',
  // The audit log records every user's logins, IPs and user agents — including
  // the super admin's. Whoever can read it can profile everyone, and reading it
  // is itself logged (audit.viewed), so a role that both reads the log and
  // administers users could cover its own tracks by reading first. Left with no
  // role until there is an explicit decision about who should see it.
  'audit.view': 'عمدی — لاگ شامل IP و user-agent همهٔ کاربران است؛ تا تصمیم صریح، فقط ابرمدیر',
};

md += `\n## مجوزهای بدون نقش\n\n`;
if (orphans.length === 0) {
  md += 'هیچ — هر مجوز حداقل به یک نقش داده شده است.\n';
} else {
  const deliberate = orphans.filter((p) => SUPERADMIN_ONLY[p.code]);
  const gaps = orphans.filter((p) => !SUPERADMIN_ONLY[p.code]);

  if (deliberate.length) {
    md += `### عمدی — فقط ابرمدیر\n\n`;
    for (const p of deliberate) md += `- \`${p.code}\` — ${SUPERADMIN_ONLY[p.code]}\n`;
    md += '\n';
  }
  if (gaps.length) {
    md += `### بدون توجیه — احتمالاً seed ناقص\n\n`;
    md += `این‌ها به هیچ نقشی داده نشده‌اند و دلیل ثبت‌شده‌ای هم ندارند. یا باید به
نقشی داده شوند یا دلیل ابرمدیر-only بودنشان مستند شود.\n\n`;
    for (const p of gaps) md += `- \`${p.code}\`\n`;
  }
}

md += `
## آنچه این ماتریس نشان نمی‌دهد

1. **مجوزهای سطح ردیف.** این ماتریس می‌گوید چه کسی *چه کاری* می‌تواند بکند، نه
   *روی کدام ردیف*. محدودسازی ردیف (مثلاً فقط پروژه‌های خودم) در کوئری‌ها است.
2. **مالکیت.** برخی مسیرها علاوه بر مجوز، مالکیت رکورد را هم می‌سنجند.
3. **مسیرهایی که مجوز ندارند** (ورود، خروج، صفحهٔ اصلی). این‌ها در ماتریس نیستند.

## آزمون

| چه چیزی | کجا |
|---|---|
| هر مسیر محافظت‌شده مجوز دارد | \`tools/checks/routes.php\` |
| مجوز نبودن → ۴۰۳ (نه ۴۰۴ خاموش) | \`tools/checks/permission-flow.mjs\` |
| ماتریس با پایگاه هم‌خوان است | \`tools/checks/docs-integrity.mjs\` |
`;

if (process.argv.includes('--check')) {
  const current = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';
  if (current === md) {
    console.log('OK: docs/RBAC_PERMISSION_MATRIX.md is current');
    process.exit(0);
  }
  console.log('STALE: docs/RBAC_PERMISSION_MATRIX.md does not match the database');
  process.exit(1);
}

fs.writeFileSync(OUT, md, 'utf8');
console.log(`wrote docs/RBAC_PERMISSION_MATRIX.md — ${roles.length} roles, ${perms.length} permissions`);
