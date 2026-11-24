## [Decision Closure] — ۲ آذر ۱۴۰۵

**دامنه:** فقط اسناد و تصمیم‌ها. صفر کد عملیاتی، صفر Migration، صفر تغییر Schema، صفر خرید.

### افزوده
- `docs/FILE_WORKSPACE_DECISION_REGISTER.md` — ۳۶ تصمیم × ۱۵ فیلد، با قاعدهٔ قابل‌بازتولید برای «مسدودکننده».
- `docs/FILE_ORGANIZATIONAL_MODEL.md` — ممیزی واقعی CRM (کد + DB) و دو مدل سازمانی A/B با مقایسهٔ وزنی.
- `docs/FILE_WORKSPACE_ADR_EVIDENCE.md` — سطح‌بندی ۱۱ مؤلفهٔ ADR-FILE-001: ۴ `PROVEN`، ۵ `DESIGN-ONLY`، ۲ `NEEDS PoC`.
- `docs/FILE_WORKSPACE_CLIENT_DECISION_PACK.md` — فقط ۸ تصمیم کارفرما + ۵ زیرساخت، هر یک با پیش‌فرض مشخص.
- `tools/checks/docs-integrity.mjs`: گارد `T-DOC-referenced-paths` — هر مسیر مشخصِ نام‌برده‌شده در اسناد فایل باید وجود داشته باشد.

### اصلاح خطاهای F0
- **«۱۳ مسدودکننده» بی‌مبنا بود.** شمارش واقعی: **۳۶ تصمیم / ۱۶ مسدودکننده**. جدول خلاصه در
  `FILE_WORKSPACE_OPEN_DECISIONS.md` و ادعای `OPEN_DECISIONS.md` اصلاح شد؛ عدد ۱۳ حذف شد.
- **`FILE_WORKSPACE_ERD.md`** به `docs/database/file-erd.dot` ارجاع می‌داد که هرگز ساخته نشد — ارجاع حذف شد.
- **`FILE_COLLABORATION_RESEARCH.md` §۲.۴:** ادعای «فایل > ۴GB هرگز کار نمی‌کند» حذف شد.
  صفحهٔ رسمی Win32 هیچ سقف حجمی مستند نمی‌کند؛ ۴ GiB فقط **عرض `REG_DWORD`** است، نه محدودیت اثبات‌شده.
  سه ادعای MinIO / Seafile / Windows WebClient به‌تفکیک با منبع، محدودیت نسخه و سطح اطمینان ثبت شدند (§۲.۷).
- ارجاع نادرست «`composer.lock` = OD-118» به **OD-135** اصلاح شد.

### بازآرایی
- ۳۶ تصمیم فایل در شش دسته: **۸** کارفرما · **۲۳** فنی Arena · **۵** زیرساخت · **۰** بلوکه · **۰** Approved · **۰** Closed.
  هر شناسه دقیقاً یک‌بار؛ **هیچ حذف، ادغام یا شمارش مجددی رخ نداد** و **هیچ وضعیتی به Approved/Closed تغییر نکرد**.

### کیفیت
- `docs-integrity`: **۴۴/۴۴** PASS (قبلاً ۳۹/۳۹).
- Regression: **۲۵ سوییت · ۹۰۴ اَسِرشِن · ۰ شکست · exit 0**؛ دیتابیس مشترک دست‌نخورده.

---

# چرخهٔ تغییر — VIRA CRM

**فرمت:** Keep a Changelog · **تاریخ‌ها:** شمسی

---

## [Unreleased]

### F0 — امکان‌سنجی و معماری Vira Drive (ماژول فایل سازمانی)

**دامنه:** فقط مستندات. **هیچ کد Production، Migration یا تغییر Storage/DB انجام نشد.**

#### افزوده
- **۱۸ سند تحویلی** طبق دستور `MASTER ADDENDUM` §22:
  `FILE_COLLABORATION_RESEARCH`، `FILE_STORAGE_ARCHITECTURE`، `FILE_WORKSPACE_REQUIREMENTS`،
  `FILE_PERMISSION_MATRIX`، `FILE_WORKSPACE_ERD`، `FILE_VERSIONING_AND_LOCKING`،
  `WINDOWS_WEBDAV_COMPATIBILITY`، `WEBDAV_VPS_ARCHITECTURE`، `WEBDAV_CRM_AUTHORIZATION_BRIDGE`،
  `ONLINE_OFFICE_INTEGRATION`، `FILE_SECURITY_THREAT_MODEL`، `FILE_STORAGE_CAPACITY_PLAN`،
  `FILE_WORKSPACE_TEST_PLAN`، `FILE_WORKSPACE_DEPLOYMENT`، `FILE_BACKUP_AND_RECOVERY`،
  `FILE_WORKSPACE_OPEN_DECISIONS`، `FILE_WORKSPACE_ACCEPTANCE_CRITERIA`، `FILE_WORKSPACE_TRACEABILITY`.
- `docs/adr/ADR-FILE-001-file-workspace-architecture.md` — تصمیم Hybrid با گزینه‌های ردشده.
- `docs/phase-4/00-F0-FILE-WORKSPACE-REPORT.md` — گزارش نهایی A–T با برچسب هر نتیجه.
- **۱۰ نمودار** (`.dot` + `.svg` + `.png`) در `docs/diagrams/file-module/`:
  System Context، Component، Deployment، WebDAV Auth Sequence، WebDAV File Operation،
  Permission Resolution، Upload Lifecycle، Version Lifecycle، Locking Lifecycle، Backup/Restore.
- `docs/tools/render-file-diagrams.mjs` — رندر با همان زنجیرهٔ `@viz-js/viz` + `sharp` پروژه.
- `docs/tools/generate-file-traceability.mjs` — تولید و `--check` برای ۹۹ نیازمندی.

#### تغییر
- `tools/checks/docs-integrity.mjs` — از **۱۹ به ۳۹ اَسِرشِن**: ۲۰ سند جدید الزامی شدند
  و هم‌گامی `FILE_WORKSPACE_TRACEABILITY.md` با منبعش بررسی می‌شود.
- `docs/requirements/requirements.catalog.yaml` — گروه `REQ-16` و **۱۲ نیازمندی** (REQ-16.01…12).
- `tools/traceability-status.json` — ۱۲ وضعیت جدید؛ کاتالوگ از ۸۴ به **۹۶** نیازمندی رسید.
- `docs/REQUIREMENTS_TRACEABILITY.md` — بازتولید شد (VERIFIED=26 · PARTIAL=20 · NOT_VERIFIED=2 ·
  NOT_IMPLEMENTED=41 · BLOCKED=7).
- `docs/OPEN_DECISIONS.md` — **۳۶ تصمیم جدید OD-101…OD-136** (بدون برخورد با OD-01…47)؛
  حالا **۸۱ تصمیم** ثبت‌شده.

#### اثبات‌شده در همین مخزن (اجرا، نه ادعا)
- `Storage::disk('local')->temporaryUrl()` با `serve => true` کار می‌کند و امضاشده است.
- امضای دست‌کاری‌شده رد می‌شود (۳۰۲ به login، بدون داده).
- **`storage.local` از Range Request پشتیبانی نمی‌کند** → مسیر دانلود اختصاصی الزامی است.
- **Content-Type از محتوا تعیین می‌شود، نه پسوند** (`photo.png` با بدنهٔ HTML → `text/html`).
- `League\Flysystem\PathTraversalDetected` سه الگوی حمله را رد می‌کند و مسیر مطلق بیرون root نمی‌رود.
- SHA-256 جریانی: **۱۰۷ MB/s** با حافظهٔ اوج **۲۰ MB** (مستقل از حجم).

#### تصحیح یک فرض قبلی
- ادعای «`temporaryUrl` روی دیسک local پشتیبانی نمی‌شود» **نادرست بود**؛ در Laravel 12 با
  `serve => true` پشتیبانی می‌شود. محدودیت واقعی چیز دیگری است (Range و عدم ابطال‌پذیری).

#### رفع رخوت مستندسازی (پیدا‌شده در بازبینی F0)
- `TEST_STRATEGY.md` جدول سوییت `ui-shots` را **۲۰۶** می‌نوشت، ولی از commit `96b29ed`
  (افزودن صفحات پروژه) واقعاً **۲۹۰** اَسِرشِن اجرا می‌شد؛ فقط برچسب `run-all` به‌روز شده بود.
  عدد اصلاح شد و اختلاف شمارش (۷۹۵ → ۸۹۹) در همان سند توضیح داده شد: +۲۰ از `docs-integrity`
  و +۸۴ از این رخوت قدیمی که اکنون آشکار و اصلاح شده است.

#### آنچه انجام نشد (عمداً)
- هیچ Migration، هیچ کد، هیچ تغییر DB/Storage — طبق دستور §25/§26.
- هیچ ادعای سازگاری Windows (۳۰ تست `NOT TESTED`) و هیچ ادعای Production-Ready.


### امنیت — لایهٔ Policy

#### افزوده
- `ContractPolicy` — تفکیک `viewFinancials` از `view`، ثبت صریح در `AppServiceProvider`.
- `ProjectPolicy` — مالک پروژه بدون مجوز سراسری می‌تواند باز کند، اما مبلغ را نه.
- `policies.php` — ۱۴ اَسِرشِن روی `Gate::allows()` واقعی.
- `errors/500.blade.php` — بدون افشای جزئیات، با شناسهٔ همبستگی مستقل.

#### رفع
- تست policy در ابتدا روی دیتابیس مشترک می‌نوشت؛ چک جداسازی آن را گرفت
  (`SHARED DATABASE WAS MODIFIED`, users 2→6). اکنون دیتابیس خصوصی دارد.

### M3 — ماژول پروژه‌ها

#### افزوده
- `ProjectController` + ۷ مسیر + ۴ صفحه (فهرست، جزئیات، ایجاد، ویرایش).
- مدل‌های `SeoProjectDetail`, `DesignProjectDetail`, `SeoPackage`,
  `ProjectKeyword`, `ProjectType` — جدول‌ها بودند، مدل نبودند.
- `project-flow.mjs` — ۲۸ اَسِرشِن، دیتابیس جدا، HTTP واقعی.
- `ProjectDictionarySeeder` — ۲ نوع و ۳ پکیج سئو.
- پوشش اسکرین‌شات: ۳ صفحهٔ پروژه در ۷ عرض (۱۱ صفحه × ۷ عرض = ۲۹۰ اَسِرشِن).

#### امنیت
- گارد «قرارداد باید متعلق به همان مشتری باشد» — جلوگیری از انتساب مالی غلط.
- تغییر نوع پروژه، مشخصات نوع قبلی را حذف می‌کند (تست‌شده در سطح دیتابیس).

### M0 — زیرساخت کیفیت

#### نتیجهٔ اجرا
```
25 suites · 795 assertions · 0 failed · 0 skipped
isolation: shared database untouched (every pass)
repeatable: yes · exit 0
```

#### افزوده
- `tools/generate-traceability.mjs` — ماتریس ردیابی از کاتالوگ تولید می‌شود.
- `docs-integrity.mjs` — ۱۹ اَسِرشِن روی وجود و تازگی اسناد.
- **قفل اجرای رگرسیون** (`tools/checks/lib/run-lock.mjs`) — `O_CREAT|O_EXCL`،
  exit code ۳، بازیابی از pid مرده/فایل خراب/TTL/SIGKILL.
- **`lock-concurrency.mjs`** — ۱۵ اَسِرشِن با فرآیندهای واقعی، شامل
  کشتن با SIGKILL و اثبات بازیابی.
- **۷ اَسِرشِن عرض ۵۷۶px** در `regression-matrix` و `ui-shots`.
- **گارد بازگشت** در `lock-concurrency` تا اجرای بازگشتی دوباره رخ ندهد.

#### تغییر
- `run-all.mjs`: مقایسهٔ ۵ بُعدی در `--repeat`؛ گزارش جداسازی **هر پاس**؛
  `--force-lock`؛ `lock.release()` پیش از exit.
- `runner-contract.mjs`: `--force-lock` به فرزندان (سوییت‌ها stub‌اند).

#### رفع
- اجرای بازگشتی `run-all` از درون `lock-concurrency` (۲۱۰s → ۱۲.۶s).
- شمارش اشتباه «۱۳ عرض» → ۷ عرض واقعی.

---

## [0.3.0] — Gate A

### افزوده
- ۱۷ سند طراحی (`docs/phase-3/01`…`17`) + گزارش Gate A.
- ۴ اسکرین‌شات شاهد.

---

## [0.2.0] — فاز ۲-E

### افزوده
- ۲۱ سوییت رگرسیون، ۶۵۴ اَسِرشِن.
- طراحی واکنش‌گرا، تم‌های تیره، اجزای DS.

---

## [0.1.0] — پایه

### افزوده
- Laravel 12، احراز هویت، RBAC، مشتریان، قراردادها، Audit.
- تاریخ شمسی، `Money` با ریال صحیح.

---

## قالب

| بخش | استفاده |
|-----|---------|
| `افزوده` | قابلیت جدید |
| `تغییر` | تغییر رفتار موجود |
| `رفع` | باگ رفع‌شده |
| `امنیت` | تغییر امنیتی |
| `حذف` | قابلیت برداشته‌شده |
