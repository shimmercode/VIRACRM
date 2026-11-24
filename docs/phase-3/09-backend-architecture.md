# 09 — معماری Backend (Modular Monolith)

**Vira CRM** · Gate A · ۲ آذر ۱۴۰۵

---

## ۱. تصمیم معماری: Modular Monolith با مرزهای صریح

Laravel موجود **Monolith** است و باید بماند. اما بدون مرز، Monolith به گلولهٔ
گل تبدیل می‌شود. راه‌حل: **پوشه‌بندی دامنه‌محور داخل `app/`**، بدون تغییر
namespace ریشه و بدون بستهٔ خارجی.

```
app/
├── Models/              (۱۷ model موجود — نگه داشته می‌شود)
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Customers/
│   │   ├── Contracts/
│   │   ├── Projects/        ← جدید
│   │   ├── Credentials/     ← جدید
│   │   ├── Employees/       ← جدید
│   │   ├── Payroll/         ← جدید
│   │   ├── Reports/         ← جدید
│   │   ├── Admin/           ← جدید (users/roles/settings/audit)
│   │   └── Documents/       ← جدید (PDF)
│   ├── Requests/            (FormRequest به تفکیک دامنه)
│   └── Middleware/
├── Policies/            ← جدید (امروز فقط middleware است)
├── Enums/               (۳ موجود)
├── Services/
│   ├── Security/        (EnvelopeEncrypter، AuditRecorder، PermissionCatalog)
│   ├── Reminders/       (ReminderSchedule)
│   ├── Documents/       ← جدید (PDF)
│   ├── Payroll/         ← جدید (PayrollCalculator)
│   └── Contracts/       ← جدید (TemplateRenderer، VersionSnapshot)
├── Support/             (JalaliDate، Money، PersianNumber)
└── Jobs/                ← جدید (فقط جایی که async واقعاً لازم است)
```

**قاعدهٔ مرز:** یک Controller فقط سرویس دامنهٔ خودش را صدا می‌زند.
`PayrollController` هرگز مستقیم به `Project` کوئری نمی‌زند — از
`EmployeeAssignmentService` استفاده می‌کند.

**قاعدهٔ ضد-انتزاع:** Service فقط وقتی ساخته می‌شود که **منطق واقعی** داشته باشد.
CRUD سادهٔ `Contract` در Controller می‌ماند. `PayrollCalculator` و
`TemplateRenderer` و `CredentialVault` Service هستند چون منطق دارند.

---

## ۲. آنچه **اضافه نمی‌شود** (تصمیم صریح)

| الگو | تصمیم | چرا |
|------|-------|-----|
| Repository Pattern | ❌ **نه** | Eloquent خودش abstraction کافی است؛ Repository در این اندازه فقط لایهٔ تکراری است |
| Event Sourcing | ❌ **نه** | پیچیدگی بی‌دلیل؛ snapshot نسخه کافی است |
| CQRS | ❌ **نه** | حجم پروژه توجیه نمی‌کند |
| Microservices | ❌ **نه** | هاست اشتراکی |
| Livewire/Inertia | ❌ **نه** | جهت تأییدشده |
| Queue Worker دائمی | ⚠️ **مشروط** | فقط اگر هاست اجازه دهد (H-21) |
| DDD کامل (Aggregate/Value Object) | ⚠️ **جزئی** | فقط `Money` که موجود است |

---

## ۳. لایه‌های مسئولیت

```
Route (permission middleware)
   ↓
FormRequest (اعتبارسنجی + مجوز داده)
   ↓
Controller (هم‌آهنگی، بدون منطق کسب‌وکار)
   ↓
Policy (مالکیت/رکورد)  ←  Service (منطق واقعی)  ←  Model (رابطه/cast/scope)
   ↓
DB Transaction (برای عملیات چندمرحله‌ای)
```

**قاعدهٔ تراکنش:** هر عملیات چندمرحله‌ای که در نیمه‌کار شکست بخورد داده را
ناسازگار می‌کند، **باید** در `DB::transaction` باشد:

| عملیات | چرا |
|--------|-----|
| نهایی‌سازی قرارداد + snapshot + فایل | اگر snapshot ساخته نشود ولی وضعیت عوض شود |
| محاسبهٔ payroll + Payslip + items | مالی |
| ساخت رویداد فاکتور + یادآور | اگر یادآور ساخته نشود رویداد یتیم می‌ماند |
| تأیید فیش + قفل دوره | پول |
| دوباره محاسبهٔ یادآورها | باید اتمی باشد |

---

## ۴. ماژول‌ها و مسئولیت (۱۷ ماژول)

| # | ماژول | مسئولیت | وضعیت |
|---|-------|---------|-------|
| ۱ | Auth | ورود، خروج، تغییر رمز، throttle | ✅ موجود |
| ۲ | Dashboard | KPI، کارتل، لینک به رکورد | ⚠️ نیمه |
| ۳ | Customers | CRUD + search + dedupe | ✅ موجود |
| ۴ | Contracts | CRUD + status + version | ⚠️ نیمه |
| ۵ | ContractTemplates | قالب + placeholder + نسخه‌بندی | ❌ |
| ۶ | Projects | header + نوع‌محور | ❌ |
| ۷ | SeoProjects | فیلد سئو + keyword + event | ❌ |
| ۸ | DesignProjects | فیلد طراحی | ❌ |
| ۹ | Credentials | vault + reveal + audit | ❌ |
| ۱۰ | Documents | PDF (۴ نوع) | ❌ |
| ۱۱ | Employees | پرونده + تخصیص | ❌ |
| ۱۲ | Payroll | قواعد + دوره + فیش | ❌ |
| ۱۳ | Reminders | engine + scheduler + کارتل | ⚠️ هسته هست |
| ۱۴ | Notes | یادداشت + کار | ❌ |
| ۱۵ | Reports | گزارش‌ها | ❌ |
| ۱۶ | AccessControl | users/roles/permissions | ⚠️ فقط کاتالوگ |
| ۱۷ | Settings + Audit | تنظیمات + رویدادها | ⚠️ فقط خواندن |

---

## ۵. `CredentialVault` — طراحی امنیتی (حساس‌ترین سرویس)

```php
final class CredentialVault
{
    public function __construct(private EnvelopeEncrypter $cipher) {}

    /** ذخیرهٔ رمز: فقط ciphertext در DB */
    public function store(Project $project, string $type, array $secret, User $actor): Credential

    /** افشا: هر بار از سرور، با مجوز، با Audit */
    public function reveal(Credential $credential, User $actor): string
    {
        Gate::authorize('reveal', $credential);          // 1. مجوز
        $plain = $this->cipher->decrypt($credential->secret);  // 2. رمزگشا
        AuditRecorder::credentialRevealed($credential, $actor); // 3. audit — بدون مقدار
        return $plain;
    }

    /** هرگز لیست را با رمز برنگردان */
    public function listing(Project $project): Collection; // فقط mask + fingerprint
}
```

**قواعد سخت:**

1. رمز **هرگز** در payload فهرست نیست. یک Route جدا `POST /credentials/{id}/reveal` است.
2. `reveal` با `POST` (نه `GET`) — تا در history مرورگر و proxy log ننشیند.
3. پاسخ `Cache-Control: no-store` + `Pragma: no-cache`.
4. Audit **مقدار** را ثبت نمی‌کند، فقط «چه کسی چه رکوردی را چه زمانی».
5. `key fingerprint` برای تشخیص تغییر رمز بدون افشای آن.
6. Re-auth: برای `reveal` اگر بیش از N دقیقه از ورود گذشته، رمز فعلی دوباره خواسته می‌شود (**تصمیم OD-26**).

---

## ۶. `PayrollCalculator` — چارچوب بدون اختراع فرمول

**این نکتهٔ حیاتی است:** سند فرمول حقوق نمی‌دهد (فقط «قابل تنظیم» می‌گوید).
پس معماری باید **قاعده‌پذیر** باشد، نه فرمول‌محور:

```php
final class PayrollCalculator
{
    /**
     * قواعد از payroll_rules خوانده می‌شوند (قابل تنظیم در UI).
     * هر قاعده یک strategy است: fixed | per_project | coefficient | percent.
     */
    public function calculate(PayrollPeriod $period, Employee $employee): PayrollResult
    {
        $inputs = $this->snapshotInputs($employee);   // قفل ورودی‌ها
        $rules  = $this->activeRules($period);        // از DB
        $lines  = [];
        foreach ($rules as $rule) {
            $lines[] = $rule->strategy()->apply($inputs, $rule->params);
        }
        return new PayrollResult($lines, $inputs->hash());
    }
}
```

**بدون OD-16 (تصمیم شما دربارهٔ فرمول) این کلاس ساخته نمی‌شود.** فقط اینترفیس
و جدول `payroll_rules` طراحی می‌شود تا فرمول بعداً به‌عنوان «rule» وارد شود، نه
به‌عنوان کد ثابت.

---

## ۷. `ReminderEngine` — idempotency

```php
// unique_key = "{$eventType}:{$subjectId}:{$kind}:{$fireDate}"
DB::transaction(function () use ($event) {
    ScheduledEvent::updateOrCreate(
        ['unique_key' => $key],        // ← idempotent
        ['event_type' => ..., 'kind' => ..., 'fire_date' => ...]
    );
});
```

- اجرای مکرر → همان ردیف، نه ردیف دوم. `unique_key` در schema موجود است ✅.
- تغییر تاریخ رویداد → `delete` رویدادهای آن `subject` که `fire_date` قدیمی دارند،
  سپس `insert` جدید — در همان تراکنش.
- **استقلال:** فاکتور و گزارش `subject_type` متفاوت دارند → کاملاً جدا.
- `job_runs` هر اجرا را ثبت می‌کند (موجود در schema) → «قابل مشاهده».

---

## ۸. زمان‌بندی — سه سناریو بر اساس واقعیت هاست

| سناریو | پیش‌نیاز | پیاده‌سازی |
|--------|----------|------------|
| A — cron واقعی | H-15 ✅ | `Schedule::command('vira:reminders:run')->dailyAt('08:00')` |
| B — cron با حداقل ۵ دقیقه | H-15 ✅ | `->everyFiveMinutes()` + بررسی «قبلاً اجرا شده؟» با `job_runs` |
| C — بدون cron | H-15 ❌ | **Fallback:** اجرای «کشف‌شده هنگام درخواست» — یک middleware سبک که اگر بیش از ۱ ساعت از آخرین اجرا گذشته، بعد از پاسخ (terminating) Job را اجرا می‌کند |

**سناریو C ریسک دارد** و باید صریح مستند شود: اگر هیچ‌کس وارد سیستم نشود،
یادآوری ساخته نمی‌شود. **تصمیم نهایی منوط به H-15 است.**

---

## ۹. آنچه از Backend موجود حفظ می‌شود

- `permission:` middleware روی هر Route — بدون استثنا.
- Whitelist ستون sort.
- `search_key` نرمال‌شده برای جستجوی فارسی.
- `expiring` به‌عنوان وضعیت مشتق.
- `AuditRecorder` برای هر رویداد حساس.
- `Money` با ریال صحیح (هرگز float).
- `JalaliDate` به‌عنوان تنها لایهٔ تبدیل.

---

## ۱۰. حجم تقریبی کد (برآورد صادقانه)

| ماژول | فایل | خط تقریبی | پیچیدگی |
|-------|------|-----------|---------|
| ContractTemplates + renderer | ۸ | ۹۰۰ | بالا |
| Projects (۲ نوع) | ۱۴ | ۱٬۸۰۰ | بالا |
| Credentials | ۹ | ۱٬۱۰۰ | **بحرانی** |
| Documents (PDF×۴) | ۱۰ | ۱٬۴۰۰ | **بحرانی** |
| Employees | ۷ | ۸۰۰ | متوسط |
| Payroll | ۱۲ | ۱٬۶۰۰ | **بحرانی** |
| Reminders (Job+Command) | ۵ | ۶۰۰ | متوسط |
| Notes | ۴ | ۴۰۰ | پایین |
| Reports | ۸ | ۱٬۰۰۰ | متوسط |
| Admin (users/roles/settings/audit) | ۱۲ | ۱٬۳۰۰ | متوسط |
| Backup | ۳ | ۳۰۰ | متوسط |
| **جمع** | **۹۲** | **~۱۱٬۲۰۰** | — |

به‌علاوه ~۱۲٬۰۰۰ خط View/CSS/JS و ~۵٬۰۰۰ خط تست.
**این حجم را نباید در یک مرحله نوشت.**
