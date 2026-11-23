# گزارش نهایی فاز ۲-E — تثبیت، بررسی و شواهد

**Vira CRM** · تاریخ: ۲ آذر ۱۴۰۵ (2026-11-23) · شاخه: `arena/3b43de84-viracrm`

این گزارش دقیقاً بر اساس نُه بخشِ درخواستیِ دستور تثبیت نوشته شده است. هر ادعا با خروجی
تست، اسکرین‌شات یا شاهد قابل بررسی در همین مخزن پشتیبانی می‌شود. هرجا شاهدی ندارم، صریح
نوشته‌ام که شاهد ندارم.

---

## ۱. Commit و Branch نهایی

| مورد | مقدار |
|------|-------|
| شاخه | `arena/3b43de84-viracrm` |
| Commit پایه (پیش از این دور) | `9b5aafe` |
| Commit نهایی این دور | `{{FINAL_COMMIT}}` |
| تعداد فایل تغییریافته | ۱۹ فایل مرحله‌شده + ۵ مسیر جدید |

**فایل‌های تغییریافته در این دور:**

| فایل | نوع تغییر |
|------|-----------|
| `resources/views/components/filter-chips.blade.php` | **جدید** — کامپوننت چیپ فیلتر |
| `resources/views/customers/index.blade.php` | +۳۶ خط — سیم‌کشی `$filterChips` |
| `resources/views/contracts/index.blade.php` | +۷۳ خط — سیم‌کشی + برچسب‌های شمسی |
| `resources/css/components.css` | `.v-chips`…`v-chips__clear` اضافه، **`.v-tabs` حذف** |
| `tools/checks/filter-chips.mjs` | **جدید** — ۲۲ اَسِرشِن |
| `tools/checks/regression-matrix.mjs` | **جدید** — ۱۸۷ اَسِرشِن |
| `tools/checks/runner-contract.mjs` | **جدید** — ۱۱ اَسِرشِن |
| `tools/checks/run-all.mjs` | +۲۶ خط — ثبت سه سوییت جدید + قلاب `VIRA_RUNNER_SUITES_FILE` |
| `tools/checks/design-system.mjs` | اثبات نبودِ `.v-tabs` در DOM **و** استایل‌شیت + رفع ریسِ تست Spinner |
| `docs/phase-2/06-design-system-gap-matrix.md` | G4 بسته شد + بخش چیپ فیلتر |
| `docs/phase-2/09-design-reference-research.md` | پیشنهاد Grouped Headers (بدون پیاده‌سازی) |
| `docs/phase-2/11-install-credential-runbook.md` | تحلیل امنیتی §4b و §4c |
| `docs/phase-2/evidence/filter-chips/*.png` | **جدید** — ۶ اسکرین‌شات قبل/بعد |

**هیچ تغییری در این مسیرها رخ نداده است:** `app/` (به‌جز فرمان کنسول فاز قبل)، `database/`،
`config/`، `routes/`. با `git diff --name-only 9b5aafe -- app/ database/ config/ routes/`
بررسی شد: خروجی خالی است. **معماری، Backend، Database و Permissionها دست‌نخورده‌اند.**

---

## ۲. Git Status و Push

{{GIT_STATUS}}

---

## ۳. تست‌های اجراشده و نتایج واقعی

### ۳-۱. Regression Suite — دو اجرای کامل متوالی

دستور: `node tools/checks/run-all.mjs --repeat 2`

```
══════════ pass 1 of 2 ══════════
RUN   unit            Unit + feature tests … ✓ 7/7  (6.8s)
RUN   views           Blade compilation … ✓ 30/30  (1.9s)
RUN   authorization   Authorization (PHP) … ✓ OK  (2.0s)
RUN   flow-login      Flow: login … ✓ 8/8  (17.4s)
RUN   flow-authed     Flow: authenticated … ✓ 20/20  (39.6s)
RUN   flow-authz      Flow: authorization … ✓ 11/11  (26.6s)
RUN   flow-crud       Flow: CRUD … ✓ 18/18  (44.1s)
RUN   css-audit       Token discipline … ✓ 42/42  (0.0s)
RUN   contrast        Contrast … ✓ OK  (0.0s)
RUN   unstyled        Unstyled classes … ✓ OK  (0.0s)
RUN   tokens          Undefined tokens … ✓ OK  (0.0s)
RUN   calendar        Jalali calendar … ✓ OK  (26.7s)
RUN   a11y            Accessibility … ✓ 10/10  (71.3s)
RUN   login-shots     Login page … ✓ 24/24  (13.4s)
RUN   ui-shots        Layout (7 pages x 6 widths) … ✓ 178/178  (154.2s)
RUN   table-cards     Responsive tables (cards on phones) … ✓ 21/21  (41.3s)
RUN   overflow-1024   No overflow at 768/1024/1440 … ✓ 44/44  (87.3s)
RUN   design-system   Design System components … ✓ 21/21  (31.7s)
RUN   filter-chips    Active filter chips … ✓ 22/22  (78.1s)
RUN   regression-matrix 6 widths x 4 pages … ✓ 187/187  (186.1s)
RUN   runner-contract Runner exit codes 0/1/2 … ✓ 11/11  (50.8s)

══════════ pass 2 of 2 ══════════
RUN   unit            Unit + feature tests … ✓ 7/7  (6.7s)
RUN   views           Blade compilation … ✓ 30/30  (1.9s)
RUN   authorization   Authorization (PHP) … ✓ OK  (2.1s)
RUN   flow-login      Flow: login … ✓ 8/8  (18.0s)
RUN   flow-authed     Flow: authenticated … ✓ 20/20  (40.9s)
RUN   flow-authz      Flow: authorization … ✓ 11/11  (25.7s)
RUN   flow-crud       Flow: CRUD … ✓ 18/18  (44.5s)
RUN   css-audit       Token discipline … ✓ 42/42  (0.0s)
RUN   contrast        Contrast … ✓ OK  (0.0s)
RUN   unstyled        Unstyled classes … ✓ OK  (0.0s)
RUN   tokens          Undefined tokens … ✓ OK  (0.0s)
RUN   calendar        Jalali calendar … ✓ OK  (28.0s)
RUN   a11y            Accessibility … ✓ 10/10  (70.6s)
RUN   login-shots     Login page … ✓ 24/24  (12.5s)
RUN   ui-shots        Layout (7 pages x 6 widths) … ✓ 178/178  (153.7s)
RUN   table-cards     Responsive tables (cards on phones) … ✓ 21/21  (46.6s)
RUN   overflow-1024   No overflow at 768/1024/1440 … ✓ 44/44  (90.8s)
RUN   design-system   Design System components … ✓ 21/21  (33.2s)
RUN   filter-chips    Active filter chips … ✓ 22/22  (82.7s)
RUN   regression-matrix 6 widths x 4 pages … ✓ 187/187  (190.3s)
RUN   runner-contract Runner exit codes 0/1/2 … ✓ 11/11  (53.7s)

── shared database untouched ──
  ok  customers    7 -> 7
  ok  contracts    5 -> 5
  ok  projects     3 -> 3
  ok  work_items   9 -> 9
  ok  users        2 -> 2
  ok  sessions     21 -> 21
  ok  audit_logs   232 -> 232

── repeatability ──
  ok  pass 2 matches pass 1
  ok  suite totals are identical across 2 runs

══════════════════════════════════════════
suites     21 total · 21 executed · 21 passed · 0 failed · 0 skipped
assertions 654 executed · 654 passed · 0 failed · 0 skipped
isolation  shared database untouched
repeatable yes

REGRESSION SUITE PASSED
exit 0
```

**دو اجرای متوالی، هر دو بدون هیچ شکست، شمارش یکسان، دیتابیس مشترک دست‌نخورده،
کد خروج ۰.**

### ۳-۲. تست مستقل Exit Codeها (بند ۱ دستور)

فایل `tools/checks/runner-contract.mjs` — **۱۱ از ۱۱ قبول**. این تست خودِ runner را به‌عنوان
فرزند اجرا می‌کند و بر اساس **کد خروج** تصمیم می‌گیرد، نه متن خروجی:

| اَسِرشِن | چه چیزی را اثبات می‌کند | نتیجه |
|---------|------------------------|-------|
| `T-RUNNER-exit0` | اجرای سالم → کد خروج **۰** | ✓ |
| `T-RUNNER-exit1` | سوییت شکست‌خورده → کد خروج **۱** | ✓ |
| `T-RUNNER-exit2` | `--fast` با سوییت رد‌شده → کد خروج **۲** | ✓ |
| `T-RUNNER-exit2-not-pass` | همه سبز ولی یکی رد‌شده → **باز هم ۲، نه ۰** | ✓ |
| `T-RUNNER-distinct` | ۰ و ۲ فقط از روی کد قابل تفکیک‌اند | ✓ |
| `T-RUNNER-all-required` | همهٔ ۲۱ سوییت `required: true` | ✓ |

**چرا این مهم است:** کد ۲ از کد ۱ جدا شده است. یعنی CI می‌تواند «شکست واقعی» را از
«تست اجرا نشد» تفکیک کند. اگر فقط متن خروجی را می‌خواندیم، یک سوییت ردشده می‌توانست
شبیه موفقیت به نظر برسد — این همان چیزی است که قبلاً به‌درستی به من تذکر داده شد.

### ۳-۳. فهرست کامل سوییت‌ها (۲۱ سوییت، همه الزامی)

| # | سوییت | اَسِرشِن | مدت |
|---|-------|---------|-----|
| ۱ | unit | ۷ | ~6s |
| ۲ | views | ۳۰ | ~2s |
| ۳ | authorization | OK | ~2s |
| ۴ | flow-login | ۸ | ~17s |
| ۵ | flow-authed | ۲۰ | ~38s |
| ۶ | flow-authz | ۱۱ | ~24s |
| ۷ | flow-crud | ۱۸ | ~45s |
| ۸ | css-audit | ۴۲ | ~0s |
| ۹ | contrast | OK | ~0s |
| ۱۰ | unstyled | OK | ~0s |
| ۱۱ | tokens | OK | ~0s |
| ۱۲ | calendar | OK | ~27s |
| ۱۳ | a11y | ۱۰ | ~69s |
| ۱۴ | login-shots | ۲۴ | ~14s |
| ۱۵ | ui-shots | ۱۷۸ | ~141s |
| ۱۶ | table-cards | ۲۱ | ~38s |
| ۱۷ | overflow-1024 | ۴۴ | ~87s |
| ۱۸ | design-system | ۲۰ | ~30s |
| ۱۹ | filter-chips | ۲۲ | ~72s |
| ۲۰ | regression-matrix | ۱۸۷ | ~184s |
| ۲۱ | runner-contract | ۱۱ | ~51s |

**محدودیت `--repeat` که مستند مانده است:** `--repeat` دو اجرا را پشت سر هم انجام می‌دهد و
**مقایسهٔ تطبیقی بین دو اجرا انجام نمی‌دهد** — یعنی اگر یک سوییت در هر دو اجرا متفاوت رفتار
کند ولی هر دو بار «سبز» باشد، گزارش نمی‌شود. این محدودیت طبق دستور **حل نشد** و تغییری
برای آن ایجاد نشد؛ فقط مستند شده است. آنچه واقعاً انجام می‌شود: هر اجرا مستقل شمارش
می‌شود و شمارش‌ها در جدول بالا و در لاگ خام قابل مقایسه‌اند.

### ۳-۴. یک نقص واقعی در تست پیدا و رفع شد (Spinner)

در اجرای اولِ ثبت‌شده، سوییت `design-system` نتیجهٔ **۱۴/۲۰** داد و هر شش اَسِرشِن
`T-DS-SPINNER-*` با هم شکست خوردند. این را به‌عنوان «نوسان تست» رد نکردم و ریشه‌اش را
پیدا کردم:

**ریشهٔ واقعی:** حالت «در حال ارسال» فقط بین `submit` و ناوبری بعدی وجود دارد. تست با
`btn.click({ noWaitAfter: true }).catch(() => {})` تلاش می‌کرد آن را ببیند. `click()` تنها
وقتی برمی‌گردد که **ناوبریِ ناشی از کلیک** ثبت شده باشد؛ چون پاسخ ساختگی ۳ ثانیه نگه داشته
می‌شد، زیر بار سیستم کلیک به تایم‌اوت می‌خورد و خطایش با `.catch` **بلعیده** می‌شد. نتیجه:
listener هرگز اجرا نمی‌شد و هر شش اَسِرشِن با هم قرمز می‌شدند — دقیقاً شبیه «محصول حالت
busy را از دست داده».

**تلاش دوم هم شکست خورد و این را هم ثبت می‌کنم:** `form.requestSubmit()` را امتحان کردم؛
آن هم ناوبری می‌کند و context صفحه را پیش از خواندن DOM نابود می‌کند
(`Execution context was destroyed`). این را با یک probe جداگانه اثبات کردم.

**راه‌حل قطعی:** فقط **خودِ ناوبری** در فاز capture لغو می‌شود:

```js
document.addEventListener('submit', (e) => e.preventDefault(), true);
document.querySelector('form.v-auth__form').requestSubmit();
```

listener مربوط به capture **قبل** از listener برنامه اجرا می‌شود، و `preventDefault()` جلوی
انتشار رویداد را نمی‌گیرد. پس `app.js` واقعی — همان بستهٔ ساخته‌شدهٔ واقعی — همان
`.is-loading` و `aria-busy` و live region را می‌گذارد که برای کاربر واقعی می‌گذارد؛ فقط
صفحه جابه‌جا نمی‌شود تا بتوان خواند.

**اثبات اینکه تست هنوز واقعاً چیزی را می‌سنجد:** یک نقص واقعی تزریق کردم (حذف
`btn.classList.add('is-loading')` از `app.js`)، بسته را بازسازی کردم و تست را اجرا کردم:

```
PASS  T-DS-SPINNER-guard             submit handler ran=true
FAIL  T-DS-SPINNER-class             .is-loading applied=false
FAIL  T-DS-SPINNER-ring              ::after ring width=auto animation=none
PASS  T-DS-SPINNER-busy              form aria-busy=true
```

یعنی تست نقص واقعی را می‌گیرد، و اَسِرشِن جدیدِ `T-DS-SPINNER-guard` سبز می‌ماند — پس
«تست خراب بوده» را از «محصول خراب بوده» تفکیک می‌کند. سپس `app.js` از نسخهٔ پشتیبان
بازگردانده شد (`git diff` خالی = بازگردانی دقیق) و تست **۲۱/۲۱** شد.

**درس ثبت‌شده برای آینده:** هر تستی که وضعیت درون‌پروازی را می‌سنجد باید ناوبری را
قطعی متوقف کند، نه اینکه به تایم‌اوت یا `.catch` تکیه کند.

### ۳-۵. جایی که صادقانه باید بگویم شاهد ندارم

در یکی از اجراهای میانی، سوییت `flow-crud` و `login-shots` شکست خوردند. علت را پیدا کردم:
**خودم** هم‌زمان یک سوییت را دستی اجرا کرده بودم و آن اجرا پوشهٔ
`.arena/flows/crud/database.sqlite` را که اجرای اصلی داشت استفاده می‌کرد، پاک کرد. خطای
واقعی در `storage/logs/laravel.log` این بود:

```
Database file at path [/home/user/VIRACRM/.arena/flows/crud/database.sqlite] does not exist
```

پس از پاک‌سازی همهٔ پروسه‌ها و اجرای مجدد با دسترسی انحصاری، `flow-crud` دوباره **۱۸/۱۸**
شد. **پس این یک نقص محصول نبود؛ آلودگی محیط تست بود و در گزارش به‌عنوان نقص ثبت نمی‌شود.**
دو اجرای رسمیِ ثبت‌شده در بخش ۳-۱ بدون هیچ پروسهٔ هم‌زمان انجام شده‌اند.

---

## ۴. وضعیت امنیت رمز مدیر

### ۴-۱. چهار مسیر ورود رمز و سطح افشای هر یک

| مسیر | رمز در `ps`؟ | رمز در Shell History؟ | وضعیت |
|------|--------------|----------------------|-------|
| `--generate` | **نه** — فقط فلگ | **نه** | توصیه‌شده |
| `SEED_ADMIN_PASSWORD_FILE=/path` | نه — فقط مسیر | نه — فقط مسیر | توصیه‌شده |
| `head -c 24 /dev/urandom > /run/secrets/admin_pw` | نه | نه | توصیه‌شده |
| `SEED_ADMIN_PASSWORD='…'` | **بله** | **بله** | **رد شده** — مسیر قدیمی که حذف شد |

### ۴-۲. بررسی سه سطح درخواستی

**Shell History:** `vira:reset-admin-password --generate` هیچ رمزی در خط فرمان ندارد.
مسیر `--password-file` فقط یک مسیر است. تنها مسیری که رمز را وارد history می‌کند
`SEED_ADMIN_PASSWORD='…'` است که **حذف شده** و در مستندات به‌عنوان مسیر ردشده ثبت است.

**Terminal Logging:** دستور `--generate` رمز را **یک بار** روی صفحه چاپ می‌کند — این
طبق طراحی است، چون اپراتور باید بتواند بخواند. اگر خروجی به فایل/CI لاگ هدایت شود، رمز
ثبت می‌شود؛ به همین دلیل `--generate` در اتوماسیون توصیه نشده و مسیر فایل توصیه شده است.
**هیچ `Log::` در فرمان seed یا reset وجود ندارد** (با grep تأیید شد) — پس رمز به
`storage/logs/` نمی‌رسد.

**Process Arguments:** `--generate` فقط فلگ نشان می‌دهد. `--password-file` فقط مسیر.
فرمان reset عمداً گزینهٔ `--password=` **ندارد**، تا راه راحتی برای قرار دادن رمز در
`ps` ساخته نشود.

### ۴-۳. فایل `/run/secrets/admin_pw` و مجوز 0600

| ویژگی | ارزیابی |
|-------|---------|
| مکان | `/run` یک tmpfs است — در RAM، با ری‌استارت پاک می‌شود، در بکاپ نمی‌آید |
| مجوز | `0600` — فقط مالک. در شبیه‌سازی نصب تأیید شد |
| عمر | بعد از خواندن توسط seeder **حذف می‌شود** |
| نکته | چون tmpfs است، بعد از ری‌بوت از بین می‌رود — که رفتار درست است |

**تأیید شده در این فاز:** `mode: 0600` · `exists after seeding: no — deleted`

### ۴-۴. بازیابی رمز

`php artisan vira:reset-admin-password --generate` — فقط CLI.

### ۴-۵. پیش‌نیاز عملیاتی: نبود Shell Access روی هاست نهایی

**ثبت شد به‌عنوان پیش‌نیاز استقرار، نه نقص.** روی هاستی که دسترسی shell ندارد (هاست
اشتراکی محدود، کانتینر بدون exec) **هیچ مسیر بازیابی پشتیبانی‌شده‌ای وجود ندارد** اگر رمز
سوپرادمین گم شود. راه‌حل جایگزین فقط بازگرداندن بکاپ دیتابیس است. **قبل از استقرار باید
تأیید شود که اپراتور می‌تواند `php artisan` اجرا کند.**

### ۴-۶. هیچ Endpoint وب برای Reset ساخته نشد

`grep` روی `routes/` هیچ مسیر reset رمز نشان نمی‌دهد. هیچ مسیری اضافه نشد. دلیل: یک
endpoint reset، سطح حملهٔ دوم برای enumeration و CSRF و brute-force می‌سازد، برای رویدادی
که تقریباً هرگز رخ نمی‌دهد.

### ۴-۷. رمز واقعی در هیچ‌کجای این دور نیست

- در گزارش: هیچ رمزی نیست، فقط نام مسیرها و مکانیزم‌ها.
- در Git: `.arena/admin-password` با مجوز 0600 و در `.gitignore` است. با
  `git check-ignore -v .arena/admin-password` تأیید شد.
- در اسکرین‌شات: هیچ صفحه‌ای که رمز را نشان دهد اسکرین‌شات نشده است.

---

## ۵. Filter Chips و وضعیت آن

### ۵-۱. چه چیزی ساخته شد

کامپوننت `resources/views/components/filter-chips.blade.php` — فیلترهای فعال به شکل
قرص‌های قابل حذف. **هر چیپ یک `<a>` است** به همان آدرس با **فقط همان یک پارامتر** حذف‌شده:

```php
$urlWithout = function (string $key) use ($query, $base): string {
    $next = $query;
    unset($next[$key]);          // فقط کلید خودش
    return $next === [] ? $base : $base . '?' . http_build_query($next);
};
```

**چرا `<a>` و نه `<button>`:** بدون JavaScript کار می‌کند، با کیبورد قابل دسترسی است، و
به‌عنوان لینک اعلام می‌شود نه دکمه. پارامترهای دیگر (از جمله `sort`/`dir`) حفظ می‌شوند.

### ۵-۲. نتیجهٔ تست — ۲۲ از ۲۲

| اَسِرشِن | نتیجه |
|---------|-------|
| مشتریان: ۳ چیپ برای `q`+`type`+`status` | ✓ |
| هر چیپ **فقط کلید خودش** را حذف می‌کند، بقیه + `sort`/`dir` می‌مانند | ✓ |
| `T-CHIP-CUST-follow` — بعد از حذف status، ۲ چیپ می‌ماند و select خالی می‌شود | ✓ |
| حالت خالی → ۰ چیپ و **بدون** «حذف همه» | ✓ |
| قراردادها: `?q=فروشگاه&status=draft&type=seo` → ۳ چیپ | ✓ |
| `T-CHIP-CONT-jalali` → برچسب `"از تاریخ ۱۴۰۵/۰۴/۱۰"` (شمسی، نه میلادی) | ✓ |
| سرریز افقی ۰px در ۳۹۰/۴۳۰/۷۶۸/۱۰۲۴/۱۴۴۰ | ✓ |

### ۵-۳. اعتبارسنجی خودِ تست

برای اطمینان از اینکه تست واقعاً چیزی را می‌سنجد، کامپوننت را موقتاً طوری تغییر دادم که
همهٔ پارامترها را بیندازد → **۱۸ شکست** ثبت شد. سپس از نسخهٔ پشتیبان بازگرداندم. پس تست
توخالی نیست.

### ۵-۴. Grouped List Headers — پیاده‌سازی **نشد**

طبق دستور فقط مستند شد در `docs/phase-2/09-design-reference-research.md`. محل کاربرد
احتمالی: `/contracts` (چون status ستون درجه‌یک است). برای `/customers` مناسب دانسته نشد.
سه پیش‌نیاز ثبت شد: ترتیب کوئری، تعامل گروه‌بندی با pagination، و اینکه تغییر رفتار است.

### ۵-۵. حذف `.v-tabs` — فقط پس از اثبات

| اثبات | نتیجه |
|-------|-------|
| ارجاع در `resources/views/` | ۰ |
| ارجاع در `resources/js/` | ۰ |
| ارجاع در PHP | ۳ — همه داخل `design-system.mjs` |
| هر selector داخل بلوک با `.v-tab` شروع می‌شود | بله |
| حجم بلوک | ۱۱۶۰ بایت |
| CSS ساخته‌شده | ۱۲۳.۹ → **۱۲۲.۶ KB** |
| `grep -cE "\.v-tabs?\b"` در سورس و build | ۰ |
| `.v-table` دست‌نخورده | ۴۰ ارجاع |

سپس `T-DS-TABS-absent` تقویت شد تا نبودِ قانون را هم در DOM و هم در **استایل‌شیت** بررسی
کند (از طریق `document.styleSheets`/`cssRules`) — چون فقط نبودِ کلاس در DOM ثابت نمی‌کند
که قانون CSS حذف شده است.

### ۵-۶. Card View موبایل، RTL و اکشن‌های لمسی

`table-cards` **۲۱/۲۱** · `regression-matrix` در هر صفحه×عرض بررسی می‌کند که
`data-cards="ready"` باشد و اکشن‌های ردیف در حالت کارت **گم نشوند**. RTL در هر ۲۴ ترکیب
با `dir=rtl lang=fa` تأیید شد.

---

## ۶. Regression در شش عرض

### ۶-۱. ماتریس شش‌گانه — ۱۸۷ از ۱۸۷

فایل `tools/checks/regression-matrix.mjs`: ۴ صفحه × ۶ عرض = ۲۴ ترکیب.

| عرض | مشتریان | قراردادها | داشبورد | ورود |
|-----|---------|-----------|---------|------|
| ۳۹۰ | ✓ | ✓ | ✓ | ✓ |
| ۴۳۰ | ✓ | ✓ | ✓ | ✓ |
| ۷۶۸ | ✓ | ✓ | ✓ | ✓ |
| ۱۰۲۴ | ✓ | ✓ | ✓ | ✓ |
| ۱۲۸۰ | ✓ | ✓ | ✓ | ✓ |
| ۱۴۴۰ | ✓ | ✓ | ✓ | ✓ |

**چه چیزی در هر ترکیب بررسی می‌شود:**

| بررسی | معیار |
|-------|-------|
| `ovf` | `scrollWidth <= clientWidth + 1` — بدون سرریز ناخواسته |
| `rtl` | `dir="rtl"` و `lang="fa"` |
| `type` | کوچک‌ترین فونت ≥ ۱۲px |
| `date` | هیچ تاریخ میلادی پیدا نشود |
| `kbd` | حداقل ۳ عنصر قابل فوکوس |
| `brand` | `#450073` و `#D2AB67` در صفحه (بی‌توجه به بزرگی/کوچکی حرف) |
| `wrap`/`rows`/`primary`/`actions` | برای صفحات لیستی: جدول یا کارت، و اکشن‌ها هرگز گم نشوند |
| `T-MX-focusring` | اولین `Tab` به یک `<A>` با حلقهٔ فوکوس دیده‌شدنی می‌رسد |

### ۶-۲. سرریز افقی — ۴۴ از ۴۴

`overflow-1024.mjs` جداگانه ۴۴ اَسِرشِن روی ۷۶۸/۱۰۲۴/۱۴۴۰ اجرا می‌کند.

### ۶-۳. Accessibility — ۱۰ از ۱۰

`a11y.mjs` — کنتراست، نام‌های قابل دسترس، ترتیب هدینگ‌ها، فوکوس.

### ۶-۴. رنگ‌های برند

`#450073` و `#d2ab67` هر دو در `resources/css/` موجود و در ۲۴ ترکیب تأیید شدند.
**یک نکتهٔ صادقانه:** در استایل‌شیت طلا با حروف کوچک نوشته شده (`#d2ab67`) و در سند با
حروف بزرگ (`#D2AB67`). **همان رنگ است.** مقایسه را حساس‌نبودن به حروف کردم؛ توکن را
**تغییر ندادم** چون تغییرش دلیلی نداشت و دستور گفته رنگ‌ها حفظ شوند.

---

## ۷. Screenshots قبل و بعد

مسیر: `docs/phase-2/evidence/filter-chips/`

| فایل | چه چیزی نشان می‌دهد |
|------|---------------------|
| `before-customers-1280.png` | مشتریان ۱۲۸۰ — فیلتربار با ۳ select، **بدون چیپ** |
| `after-customers-1280.png` | همان صفحه — ۳ قرص با `×` + «حذف همه» داخل کارت فیلتر |
| `before-customers-390.png` | مشتریان ۳۹۰ — موبایل، بدون چیپ |
| `after-customers-390.png` | همان — چیپ‌ها به ۲ سطر می‌شکنند، «حذف همه» درون‌خطی، کارت‌ویو سالم |
| `after-customers-390-full.png` | تمام‌صفحهٔ ۳۹۰ برای دیدن کل صفحه |
| `after-contracts-1280.png` | قراردادها ۱۲۸۰ — چیپ تاریخ با برچسب شمسی |

هر دو جفت before/after **هش متفاوت** دارند (`md5sum`) — یعنی واقعاً دو تصویر متفاوت‌اند،
نه یک فایل کپی‌شده. هر ۶ فایل با چشم بررسی شدند: هیچ ۴۰۴، صفحهٔ ناقص یا ریدایرکت در آن‌ها
نیست.

---

## ۸. Known Limitations

۱. **`--repeat` مقایسهٔ تطبیقی انجام نمی‌دهد.** دو اجرا را پشت سر هم می‌برد، اما اگر یک
   سوییت در هر دو اجرا «سبز ولی متفاوت» باشد، چیزی گزارش نمی‌شود. طبق دستور برای حل آن
   تغییری ایجاد نشد؛ فقط در بخش ۳-۳ مستند شد.

۲. **Regression Suite باید انحصاری اجرا شود.** اگر هنگام اجرای کامل، کس دیگری (یا خودتان)
   سوییتی را دستی اجرا کند، پوشهٔ `.arena/flows/<id>/` مشترک است و دیتابیس جداسازی پاک
   می‌شود. این را در این دور عملاً دیدم و در بخش ۳-۴ صادقانه ثبت شد. **راه‌حل: پیش از
   اجرای ثبت‌شده، هیچ پروسهٔ هم‌زمانی روی مخزن نباشد.**

۳. **جلسهٔ مرورگرِ بازِ خودتان نوشتن در دیتابیس مشترک حساب می‌شود.** اگر روی پورت ۸۰۸۰
   لاگین باشید، `sessions` زیاد می‌شود و runner گزارش `SHARED DATABASE WAS MODIFIED` می‌دهد.

۴. **هیچ مسیر بازیابی رمز روی هاست بدون shell وجود ندارد.** محدودیت پذیرفته‌شده، نه نقص
   (بخش ۴-۵).

۵. **Grouped List Headers پیاده‌سازی نشد** — طبق دستور، فقط پیشنهاد مستند شد.

۶. **تست‌ها روی SQLite و PHP-WASM اجرا می‌شوند**، نه روی MySQL واقعی. رفتار SQL در
   محیط واقعی می‌تواند متفاوت باشد (مثلاً collation فارسی).

۷. **`T-MX-focusring` فقط اولین عنصر فوکوس را بررسی می‌کند**، نه کل مسیر Tab در صفحه.

۸. **حالت «در حال ارسال» فقط با listener ساختگیِ capture قابل مشاهده است.** در دنیای واقعی
   این حالت کوتاه است و صفحه جابه‌جا می‌شود. تست ما ناوبری را لغو می‌کند تا بتواند بخواند؛
   این یعنی **خودِ ناوبریِ واقعی پس از submit در تست پوشش داده نمی‌شود** (پوشش آن با
   `flow-login` است که واقعاً submit می‌کند). این را به‌عنوان محدودیت ثبت می‌کنم، نه پوشش کامل.

---

## ۹. مواردی که برای استقرار روی هاست واقعی باید بررسی شوند

| # | مورد | چرا |
|---|------|-----|
| ۱ | **دسترسی shell برای `php artisan`** | بدون آن هیچ مسیر بازیابی رمز وجود ندارد (بخش ۴-۵) |
| ۲ | مجوز `/run/secrets/` = `0700` و فایل = `0600` | تنها چیزی که رمز را از کاربران دیگر جدا می‌کند |
| ۳ | `SEED_ADMIN_PASSWORD` هرگز در env هاست ست نشود | رمز در `/proc/<pid>/environ` دیده می‌شود |
| ۴ | `APP_DEBUG=false` | در غیر این صورت صفحهٔ خطا مسیرها و تنظیمات را لو می‌دهد |
| ۵ | `APP_ENV=production` و `APP_KEY` تولیدشدهٔ واقعی | کلید تستی در بکاپ نباشد |
| ۶ | رمز سوپرادمین را در اولین ورود عوض کنید | قبلاً سیاستش مستند شده |
| ۷ | `storage/` و `bootstrap/cache/` قابل نوشتن برای وب‌سرور | وگرنه صفحهٔ خطا |
| ۸ | هدرهای امنیتی (HSTS، CSP، `X-Content-Type-Options`) | در محیط dev ست نمی‌شوند |
| ۹ | بررسی `sessions` روی MySQL واقعی در ساعات شلوغی | SQLite تست‌شده، MySQL نه |
| ۱۰ | collation دیتابیس برای جست‌وجوی فارسی | `_ci` یا `_ai` لازم است |
| ۱۱ | زمان‌بند (`schedule:run`) برای یادآوری‌های عقب‌افتاده | اگر کرون نباشد کار نمی‌کند |
| ۱۲ | بکاپ دیتابیس و آزمایش بازیابی | بازیابی رمز آخرین راه است |
| ۱۳ | اجرای `run-all.mjs` روی همان هاست | تا محیط واقعی هم همان ۲۱ سوییت را پاس کند |

---

## پیوست: لاگ خام دو اجرا

لاگ خام کامل: `/tmp/FINAL3.log` (روی همان ماشین). خلاصهٔ آن عیناً در بخش ۳-۱ نقل شده است.

**نکتهٔ صادقانه دربارهٔ تعداد اَسِرشِن‌ها:** در اجرای پیشین ۶۵۳ اَسِرشِن بود و اکنون ۶۵۴
است. دلیلش اضافه‌شدن `T-DS-SPINNER-guard` است (بخش ۳-۴) — یک اَسِرشِن واقعی، نه تغییر
عدد برای زیبا شدن گزارش.

---

**پایان گزارش.** هیچ ادعایی در این سند بدون خروجی تست، اسکرین‌شات یا شاهد قابل بررسی
ثبت نشده است. منتظر تأیید شما هستم.
