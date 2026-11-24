#!/usr/bin/env node
/**
 * generate-decision-matrix.mjs — the single machine-readable source of truth for
 * the 36 file-module decisions, and the reconciliation table built from it.
 *
 * WHY THIS EXISTS
 * ---------------
 * The 13-vs-16 blocker conflict happened because numbers were written by hand in
 * prose. A count that is typed is a claim; a count that is computed is a fact.
 * This script holds the classification once, then emits:
 *
 *   (a) the full 36-row matrix, one row per decision, eight columns;
 *   (b) a reconciliation grid that checks every headline number against the rows;
 *   (c) an explicit overlap report — the categories are NOT disjoint, and the
 *       client asked to see the overlap rather than have it hidden;
 *   (d) a pass/fail block, so a future edit that breaks arithmetic fails loudly.
 *
 * `--check` exits 1 when the document on disk is stale, which is wired into
 * docs-integrity. The generated document must never be hand-edited.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/FILE_WORKSPACE_DECISION_MATRIX.md');

// ── the classification ────────────────────────────────────────────────────────
// cat : C = Client Decision Required | I = Infrastructure Decision | A = Arena
//       | X = Blocked by External Environment
// blk : B1 schema/migration | B2 WebDAV auth|permission path | B3 purchase
//       | B4 deliverable scope | '' = does not block F1
// infra: does the decision depend on a component that does not exist yet?
const D = [
  // id      cat blk  infra  party        title (fa)
  ['OD-101', 'C', 'B1', 'yes', 'کارفرما',    'معماری نهایی: Build / Integrate / Hybrid'],
  ['OD-102', 'I', 'B1', 'yes', 'کارفرما',    'محل ذخیرهٔ بایت‌ها (Storage backend)'],
  ['OD-103', 'I', 'B2', 'yes', 'کارفرما+DNS', 'دامنهٔ WebDAV و گواهی TLS'],
  ['OD-104', 'C', 'B1', 'yes', 'کارفرما',    'منبع حقیقت دپارتمان'],
  ['OD-105', 'C', 'B1', 'yes', 'کارفرما',    'وجود و ماهیت Group'],
  ['OD-106', 'C', 'B1', '',    'کارفرما',    'ماتریس پیش‌فرض نقش × ناحیه'],
  ['OD-107', 'A', 'B2', 'yes', 'Arena',      'TTL کش مجوز WebDAV'],
  ['OD-108', 'A', '',   '',    'Arena',      'ساخت جدول file_shares در F1'],
  ['OD-109', 'A', '',   '',    'Arena',      'file_activity جدا یا ادغام با audit_logs'],
  ['OD-110', 'A', 'B1', '',    'Arena',      'سرنوشت جدول attachments موجود'],
  ['OD-111', 'A', '',   '',    'Arena',      'سقف عمق درخت'],
  ['OD-112', 'A', '',   '',    'Arena',      'هشدار محتوای یکسان'],
  ['OD-113', 'A', 'B1', 'yes', 'Arena',      'محل جدول قفل WebDAV'],
  ['OD-114', 'A', '',   '',    'Arena',      'max_versions پیش‌فرض'],
  ['OD-115', 'A', '',   '',    'Arena',      'قفل خودکار هنگام بازکردن'],
  ['OD-116', 'A', '',   'yes', 'Arena',      'مسیر جایگزین رسمی اگر ویندوز مشکل داشت'],
  ['OD-117', 'A', '',   'yes', 'Arena',      'رفتار پوشهٔ بزرگ در WebDAV'],
  ['OD-118', 'A', '',   '',    'Arena',      'دورهٔ چرخش shared_secret'],
  ['OD-119', 'I', '',   'yes', 'کارفرما',    'ارائه‌دهنده و منطقهٔ VPS'],
  ['OD-120', 'A', 'B2', 'yes', 'Arena',      'Basic یا App-Token پیش‌فرض WebDAV'],
  ['OD-121', 'A', 'B2', 'yes', 'Arena',      'mTLS اجباری یا توصیه'],
  ['OD-122', 'A', 'B2', 'yes', 'Arena',      'توزیع shared_secret'],
  ['OD-123', 'I', '',   'yes', 'کارفرما',    'ONLYOFFICE یا Collabora (F3)'],
  ['OD-124', 'A', '',   '',    'Arena',      'سیاست forcesave (F3)'],
  ['OD-125', 'I', '',   'yes', 'کارفرما',    'زیرساخت F3: همان VPS یا جدید'],
  ['OD-126', 'C', '',   '',    'کارفرما',    '2FA برای ادمین'],
  ['OD-127', 'C', 'B3', 'yes', 'کارفرما',    'ابزار و نقطهٔ اسکن بدافزار'],
  ['OD-128', 'C', 'B3', 'yes', 'کارفرما',    'تأیید اعداد رشد ذخیره‌سازی'],
  ['OD-129', 'A', '',   '',    'Arena',      'آرشیو سالانهٔ file_activity'],
  ['OD-130', 'A', '',   '',    'Arena',      'تخمین ۵۰ روز مهندسی F1'],
  ['OD-131', 'C', 'B3', 'yes', 'کارفرما',    'RPO/RTO نهایی'],
  ['OD-132', 'A', '',   'yes', 'Arena',      'Versioning یا Replication برای Storage'],
  ['OD-133', 'I', '',   'yes', 'کارفرما',    'محل بکاپ L3 خارج از سایت'],
  ['OD-134', 'C', '',   '',    'کارفرما',    'مسئول اجرای بازیابی'],
  ['OD-135', 'A', 'B1', '',    'Arena',      'افزودن composer.lock'],
  ['OD-136', 'A', '',   '',    'Arena',      'مجوز Hard Delete'],
];

const CAT_LABEL = {
  C: 'Client Decision Required',
  I: 'Infrastructure Decision',
  A: 'Technical Decision — Arena Responsible',
  X: 'Blocked by External Environment',
};
const BLK_LABEL = {
  B1: 'B1 — Schema / Migration',
  B2: 'B2 — WebDAV auth path',
  B3: 'B3 — Purchase / cost',
  B4: 'B4 — Deliverable scope',
  '': '—',
};

// ── derive everything from the rows; never type a total ──────────────────────
const rows = D.map(([id, cat, blk, infra, party, title]) => ({
  id, cat, blk, infra: infra === 'yes', party, title,
  blocker: blk !== '',
  clientApproval: cat === 'C',
  status: 'NEEDS APPROVAL',
}));

const n = (f) => rows.filter(f).length;
const T = {
  total: rows.length,
  blockers: n((r) => r.blocker),
  byCat: {
    C: n((r) => r.cat === 'C'),
    I: n((r) => r.cat === 'I'),
    A: n((r) => r.cat === 'A'),
    X: n((r) => r.cat === 'X'),
  },
  catBlockers: {
    C: n((r) => r.cat === 'C' && r.blocker),
    I: n((r) => r.cat === 'I' && r.blocker),
    A: n((r) => r.cat === 'A' && r.blocker),
    X: n((r) => r.cat === 'X' && r.blocker),
  },
  clientApproval: n((r) => r.clientApproval),
  infraDep: n((r) => r.infra),
  approved: n((r) => r.status === 'APPROVED'),
  closed: n((r) => r.status === 'CLOSED'),
};

// Cross-tabulation: category × blocker category. This is where overlaps show up.
const XTAB = {};
for (const r of rows) {
  const b = r.blk || '';
  XTAB[b] ??= { C: 0, I: 0, A: 0, X: 0, total: 0 };
  XTAB[b][r.cat] += 1;
  XTAB[b].total += 1;
}

// ── assertions: the reconciliation the client asked for ──────────────────────
const EXPECT = {
  total: 36,
  blockers: 16,
  client: 9,        // CD-1…CD-9, i.e. exactly the items in the client pack
  infrastructure: 6,
  arena: 21,
  arenaBlockers: 7,
};
const CHECKS = [
  ['۳۶ تصمیم', T.total, EXPECT.total],
  ['۱۶ مسدودکنندهٔ F1', T.blockers, EXPECT.blockers],
  ['۹ تصمیم کارفرما', T.byCat.C, EXPECT.client],
  ['۶ تصمیم زیرساخت', T.byCat.I, EXPECT.infrastructure],
  ['۲۱ تصمیم Arena', T.byCat.A, EXPECT.arena],
  ['۷ مسدودکنندهٔ Arena', T.catBlockers.A, EXPECT.arenaBlockers],
  ['۷ مسدودکنندهٔ کارفرما', T.catBlockers.C, 7],
  ['۲ مسدودکنندهٔ زیرساخت', T.catBlockers.I, 2],
  ['جمع سه دسته = ۳۶ (۹+۶+۲۱)', T.byCat.C + T.byCat.I + T.byCat.A, T.total],
  ['جمع مسدودکننده‌ها = ۱۶ (۷+۲+۷)', T.catBlockers.C + T.catBlockers.I + T.catBlockers.A, T.blockers],
  ['تأیید کارفرما = دستهٔ کارفرما', T.clientApproval, T.byCat.C],
  ['Approved = صفر', T.approved, 0],
  ['Closed = صفر', T.closed, 0],
  ['هیچ تصمیمی بلوکه با محیط بیرونی نیست', T.byCat.X, 0],
  ['B4 استفاده نشده', XTAB.B4?.total ?? 0, 0],
];

const pad = (s, w) => String(s).padEnd(w);
const fmt = (ok, label, got, want) =>
  `| ${ok ? '✅' : '❌'} | ${label} | ${got} | ${want} | ${ok ? 'PASS' : '**FAIL**'} |`;

// ── emit ──────────────────────────────────────────────────────────────────────
const L = [];
L.push('# FILE_WORKSPACE_DECISION_MATRIX — ماتریس و تطبیق ماشینی ۳۶ تصمیم');
L.push('');
L.push('> **این سند تولیدشده است — دستی ویرایش نکنید.**');
L.push('> منبع: `docs/tools/generate-decision-matrix.mjs` · بازتولید: `node docs/tools/generate-decision-matrix.mjs`');
L.push('> بررسی تازگی: `--check` (در `docs-integrity` اجرا می‌شود و در صورت کهنگی exit 1 می‌دهد).');
L.push('');
L.push('---');
L.push('');
L.push('## ۰. چرا این سند وجود دارد');
L.push('');
L.push('عدد «۱۳ مسدودکننده» در F0 **دستی نوشته شده بود** و با ردیف‌ها نمی‌خواند. یک عدد تایپ‌شده ادعاست؛');
L.push('یک عدد **محاسبه‌شده** واقعیت است. در این سند همهٔ اعداد از ردیف‌های §۱ مشتق می‌شوند و در §۳');
L.push('خودکار آزموده می‌شوند. اگر فردا ردیفی تغییر کند و جمع نخورد، `--check` شکست می‌دهد.');
L.push('');
L.push('---');
L.push('');
L.push('## ۱. ماتریس کامل ۳۶ تصمیم');
L.push('');
L.push('| OD ID | Status | F1 Blocker | Blocker Category | Responsible Party | Client Approval | Infra Dependency | Blocking Reason |');
L.push('|-------|--------|:----------:|------------------|-------------------|:---------------:|:----------------:|-----------------|');
for (const r of rows) {
  const blkCell = r.blocker ? '**Yes**' : 'No';
  const catCell = BLK_LABEL[r.blk];
  const caCell = r.clientApproval ? 'Yes' : 'No';
  const infCell = r.infra ? 'Yes' : 'No';
  // The reason is derived from the blocker category, so it can never contradict it.
  const reason = {
    B1: 'بدون آن Migration یا Schema فایل قابل نوشتن نیست',
    B2: 'بدون آن مسیر احراز/مجوز WebDAV قابل ساخت نیست',
    B3: 'بدون آن خرید یا تعهد هزینه ممکن نیست',
    B4: 'بدون آن دامنهٔ تحویل F1 نامشخص است',
    '': 'F1 را مسدود نمی‌کند',
  }[r.blk];
  L.push(`| **${r.id}** | \`${r.status}\` | ${blkCell} | ${catCell} | ${r.party} | ${caCell} | ${infCell} | ${reason} |`);
}
L.push('');
L.push('---');
L.push('');
L.push('## ۲. تطبیق اعداد (همه از ردیف‌های §۱ مشتق شده‌اند)');
L.push('');
L.push('| ادعا | مقدار محاسبه‌شده | مرجع |');
L.push('|------|-----------------:|------|');
L.push(`| ۳۶ تصمیم کل | **${T.total}** | تعداد ردیف‌های §۱ |`);
L.push(`| ۱۶ مسدودکنندهٔ F1 | **${T.blockers}** | ردیف‌هایی که Blocker Category ≠ — |`);
L.push(`| ${T.byCat.C} تصمیم کارفرما | **${T.byCat.C}** | \`Client Decision Required\` |`);
L.push(`| ${T.byCat.I} تصمیم زیرساخت | **${T.byCat.I}** | \`Infrastructure Decision\` |`);
L.push(`| ${T.byCat.A} تصمیم Arena | **${T.byCat.A}** | \`Technical Decision — Arena Responsible\` |`);
L.push(`| ${T.catBlockers.A} مسدودکنندهٔ Arena | **${T.catBlockers.A}** | Arena ∧ Blocker |`);
L.push(`| ۰ بلوکه با محیط بیرونی | **${T.byCat.X}** | \`Blocked by External Environment\` |`);
L.push(`| ۰ Approved | **${T.approved}** | هیچ وضعیتی Approved نیست |`);
L.push(`| ۰ Closed | **${T.closed}** | هیچ وضعیتی Closed نیست |`);
L.push('');
L.push('### ۲.۱. چرا دسته‌ها با اعداد مسدودکننده هم‌پوشانی دارند');
L.push('');
L.push('**این هم‌پوشانی یک نقص نیست؛ دو محور مستقل‌اند و باید صریح نشان داده شوند:**');
L.push('');
L.push('- **محور ۱ — مسئولیت:** چه کسی باید تصمیم بگیرد؟ (کارفرما / Arena / زیرساخت)');
L.push('- **محور ۲ — مسدودکنندگی:** آیا F1 بدون تصمیم متوقف می‌شود؟');
L.push('');
L.push('یک تصمیم می‌تواند «مسئولیت کارفرما» باشد ولی «مسدودکننده» نباشد. جمع این دو محور');
L.push(`**نباید** ۳۶ باشد — این همان خطایی است که عدد ۱۳ را ساخت. جدول متقاطع زیر این را روشن می‌کند:`);
L.push('');
L.push('| Blocker Category | Client | Infrastructure | Arena | مجموع |');
L.push('|------------------|-------:|---------------:|------:|-------:|');
for (const b of ['B1', 'B2', 'B3', 'B4', '']) {
  const x = XTAB[b];
  if (!x) continue;
  const lbl = b === '' ? '— (غیرمسدودکننده)' : BLK_LABEL[b];
  L.push(`| ${lbl} | ${x.C} | ${x.I} | ${x.A} | **${x.total}** |`);
}
L.push(`| **جمع** | **${T.byCat.C}** | **${T.byCat.I}** | **${T.byCat.A}** | **${T.total}** |`);
L.push('');
L.push('> **نکتهٔ کلیدی:** جدول بالا **۳۶** جمع می‌شود، چون هر تصمیم دقیقاً یک دستهٔ مسئولیت دارد.');
L.push('> اما ستون مسدودکننده **۱۶** جمع می‌شود، چون فقط ردیف‌های `B1/B2/B3/B4` مسدودکننده‌اند.');
L.push(`> اگر کسی «۸ + ۵ + ۲۳» را مسدودکننده حساب کند به ۳۶ می‌رسد — و اگر «۱۶» را با آن جمع بزند،`);
L.push('> عدد بی‌معنایی می‌سازد. این دو محور هرگز با هم جمع نمی‌شوند.');
L.push('');
L.push('---');
L.push('');
L.push('## ۳. آزمون‌های سازگاری (اجباری)');
L.push('');
L.push('| نتیجه | ادعا | محاسبه‌شده | انتظار | وضعیت |');
L.push('|:-----:|------|----------:|-------:|-------|');
let allPass = true;
for (const [label, got, want] of CHECKS) {
  const ok = got === want;
  if (!ok) allPass = false;
  L.push(fmt(ok, label, got, want));
}
L.push('');
if (allPass) {
  L.push('**نتیجه: تمام اعداد و دسته‌بندی‌ها با یکدیگر سازگارند.**');
} else {
  L.push('**نتیجه: ناسازگاری وجود دارد — این سند نباید منتشر شود.**');
}
L.push('');
L.push('---');
L.push('');
L.push('## ۴. تصمیم‌هایی که در دو محور «متفاوت‌اند» (هم‌پوشانی صریح)');
L.push('');
L.push('این‌ها تصمیم‌هایی هستند که ممکن است به‌اشتباه «مسئولیت کارفرما» تلقی شوند چون **هزینه** دارند،');
L.push('در حالی که Arena می‌تواند خودشان را با شواهد فنی ببندد:');
L.push('');
L.push('| OD ID | چرا شبیه کارفرماست | چرا در واقع Arena است |');
L.push('|-------|--------------------|------------------------|');
L.push('| OD-110 | به دادهٔ قرارداد (attachments) مربوط است | جدول **۰ ردیف** دارد؛ مهاجرت موازی در F1 بی‌ریسک است |');
L.push('| OD-120 | تجربهٔ ورود کاربر ویندوز را عوض می‌کند | `BasicAuthLevel 1` رسمی است و Basic روی TLS کافی است |');
L.push('| OD-127 | هزینهٔ RAM دارد | **Arena گزینه‌ها را می‌بندد؛ هزینه را کارفرما تأیید می‌کند** — پس در دستهٔ کارفرماست |');
L.push('| OD-121 | به امنیت پل مربوط است | شواهد فنی کافی است؛ اگر cPanel نپذیرد، تنزل **مستند** می‌شود |');
L.push('');
L.push('---');
L.push('');
L.push('## ۵. آنچه این سند **اثبات نمی‌کند**');
L.push('');
L.push('| ❌ | چرا |');
L.push('|----|-----|');
L.push('| اینکه تصمیم‌ها **درست** هستند | این سند فقط **سازگاری عددی** را اثبات می‌کند، نه صحت محتوا |');
L.push('| اینکه تصمیمی **بسته** شده | همه \`NEEDS APPROVAL\`؛ Approved = ۰ |');
L.push('| اینکه دسته‌بندی تغییرناپذیر است | اگر کارفرما تصمیمی را رد کند، ردیف عوض می‌شود و این سند بازتولید می‌گردد |');
L.push('');

const body = L.join('\n');
const prev = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';

if (process.argv.includes('--check')) {
  if (prev !== body) {
    console.error('FILE_WORKSPACE_DECISION_MATRIX.md is stale — run: node docs/tools/generate-decision-matrix.mjs');
    process.exit(1);
  }
  console.log(`decision matrix up to date (${T.total} decisions, ${T.blockers} blockers, all ${CHECKS.length} checks pass)`);
} else {
  fs.writeFileSync(OUT, body);
  console.log(`wrote ${path.relative(ROOT, OUT)} — ${T.total} decisions · ${T.blockers} blockers · ${CHECKS.length} checks`);
}

if (!allPass) {
  console.error('RECONCILIATION FAILED');
  process.exit(1);
}
