# چرخهٔ تغییر — VIRA CRM

**فرمت:** Keep a Changelog · **تاریخ‌ها:** شمسی

---

## [Unreleased]

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
