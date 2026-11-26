# گزارش پیاده‌سازی امنیت

**شاخه:** `arena/3b43de84-viracrm`
**کامیت پیاده‌سازی:** `5dcd0c7bc9a11489f459f328bc5a02646ddd0301`
**کامیت پایه (پیش از هر تغییر):** `964efb74de9879324fd60e3463ca5c629f7b9055`

> **این سند ادعای «Production Ready» یا «Production Approved» نمی‌کند.**
> آنچه در پی می‌آید گزارشِ کارِ انجام‌شده و شواهدِ اندازه‌گیری‌شدهٔ آن است، همراه با
> محدودیت‌هایی که هنوز پابرجایند. تصمیم دربارهٔ آماده‌بودن برای تولید با کارفرماست.

---

## ۰. وضعیت Git پیش از هر تغییر

طبق قید اجرایی، پیش از دست‌زدن به هر پرونده ثبت شد:

| مورد | مقدار |
|---|---|
| HEAD | `964efb74de9879324fd60e3463ca5c629f7b9055` |
| شاخه | `arena/3b43de84-viracrm` |
| origin | یکسان با HEAD |
| `git status --porcelain` | **۰ پرونده** (درخت کاری تمیز) |

---

## ۱. تغییرات، پرونده به پرونده

### ۱.۱ پرونده‌های تازه

| پرونده | نقش |
|---|---|
| `app/Services/Backup/BackupCipher.php` | پاکت AES-256-GCM: HKDF-SHA256 با info=`vira-backup-v1`، salt ۱۶ بایتی برای هر فایل، IV ۱۲ بایتی، tag ۱۶ بایتی. `decrypt()` یا متن درست می‌دهد یا `false` — هرگز دادهٔ خراب. |
| `app/Services/Backup/BackupBuilder.php` | ساخت کانتینر `.vbk1`. نمونهٔ SQLite با `SQLite3::backup()` (read-only)، `integrity_check` پیش از ارسال، سقف ۶۴ مگابایت، نوشتن روی `.partial` سپس `rename()`. بدون هیچ shell-out. |
| `app/Services/Backup/BackupRestorer.php` | `verify()` (دروازه‌های ۱–۵، فقط خواندنی)، `verifyDeep()` (افزودن دروازهٔ ۶ و ۷)، `restore()` (انتقال DB موجود به `*.pre-restore-<ts>`، نصب، بررسی دوباره، بازگشت خودکار در شکست). استخراج، عضوِ دارای `..` یا مسیر مطلق را رد می‌کند. |
| `app/Services/Backup/RetentionPolicy.php` | منطق انتخابِ «چه بماند» به‌صورت تابع خالص، بدون لاراول — تا قابل آزمون باشد. |
| `app/Console/Commands/BackupDatabase.php` | `vira:backup` با `--path` و `--no-prune`. |
| `app/Console/Commands/RestoreBackup.php` | `vira:restore <file>` با `--verify` و `--force`. |
| `app/Console/Commands/GenerateBackupKey.php` | `vira:backup-key` — چاپ کلید، بدون نوشتن در `.env`. |
| `app/Http/Controllers/Audit/AuditLogController.php` | صفحهٔ گزارش رویدادها + `present()` به‌عنوان تنها مسیرِ redaction برای نمایش. |
| `resources/views/audit/index.blade.php` | صفحهٔ گزارش رویدادها (فیلتر، جست‌وجو، صفحه‌بندی). |
| `tests/Feature/BackupRestoreTest.php` | ۱۴ آزمون، ۹۶ assertion — روی پایگاه **جداسازی‌شده**. |
| `tests/Unit/AuditRedactionTest.php` | ۸ آزمون، ۳۶ assertion — لایهٔ دوم redaction. |
| `tools/checks/audit-log-page.mjs` | ۱۶ بررسی سطح HTTP. |
| `tools/checks/env-capabilities.php` | چاپ توانمندی‌های محیط؛ باید روی هاست واقعی دوباره اجرا شود. |
| `docs/BACKUP_ARCHITECTURE.md` | طراحی مرحلهٔ ۲ (۱۰ بخش). |

### ۱.۲ پرونده‌های تغییرکرده

| پرونده | تغییر |
|---|---|
| `database/seeders/RoleSeeder.php` | `credentials.export` از نقش manager **حذف** شد، با کامنتی که تصمیم را ثبت می‌کند. |
| `config/vira.php` | افزودن `backup_key` (از `VIRA_BACKUP_KEY`)، `backup_path`، `backup_retention`. استقلال دو کلید در همان‌جا توضیح داده شده. |
| `.env.example` | بخش «Backup encryption» + سه متغیر نگهداری، با دلیلِ جدا بودن از `VIRA_CREDENTIAL_KEY`. |
| `app/Models/AuditLog.php` | افزودن `scrub()` — لایهٔ دوم redaction برای متن آزاد. هیچ متد موجودی حذف یا ضعیف نشد. |
| `routes/web.php` | **یک** مسیر تازه: `GET /audit` با `permission:audit.view`. هیچ POST/PUT/PATCH/DELETE. |
| `config/navigation.php` | ورودی «گزارش رویدادها» در منوی ثانویه، مشروط به `audit.view`. |
| `tools/checks/credential-export-audit.mjs` | `T-CEA-05` از ۲۰۰ به **۴۰۳** وارونه شد (نه حذف) و `T-CEA-05b` افزوده شد. |
| `tools/checks/run-all.mjs` | ثبت سوئیت تازهٔ `audit-log-page`. |
| `tools/generate-rbac-doc.mjs` | تفکیک مجوزهای بلااستفاده به «عمدی — فقط super admin» و «غیرموجه — احتمالاً جای خالی در seed». |
| `docs/RBAC_PERMISSION_MATRIX.md`، `docs/RELEASE_MANIFEST.md`، `docs/DATABASE_SCHEMA.md` | بازتولید (هر سه توسط gate بررسی می‌شوند). |

**هیچ آزمونی حذف یا ضعیف نشد.** تنها تغییر در آزمون‌های موجود، وارونه‌کردن
`T-CEA-05` است که **ادعای بیشتری** می‌کند نه کمتر: از «manager می‌تواند export کند»
به «قوی‌ترین نقش عادی سامانه از export **رد می‌شود**، و همان کاربر هنوز می‌تواند
reveal کند».

---

## ۲. معماری نهایی Backup و Restore

شرح کامل در `docs/BACKUP_ARCHITECTURE.md`. خلاصهٔ آنچه **پیاده‌سازی و اجرا شد**:

```
.vbk1  =  "VIRABK1\n"  +  یک خط JSON header  +  AES-256-GCM( gzip( zip ) )

zip    =  manifest.json          (sha256 و اندازهٔ هر عضو)
       +  database.sqlite        (نمونهٔ آنلاین SQLite)
       +  files/<private|public>/…
```

| تصمیم | مقدار |
|---|---|
| رمزنگاری | AES-256-GCM — **جایگزین CBC شد** |
| KDF | HKDF-SHA256، info = `vira-backup-v1`، salt ۱۶ بایتی **برای هر فایل** |
| IV / tag | ۱۲ بایت / ۱۶ بایت |
| کلید | `VIRA_BACKUP_KEY`، base64ِ ۳۲ بایت، **مستقل از `VIRA_CREDENTIAL_KEY`** |
| محل ذخیره | `storage/app/backups/` — بیرون از document root و gitignore‌شده |
| نگهداری | ۷ روزانه / ۴ هفتگی / ۶ ماهانه، **یک کانتینر در هر دوره** |
| زمان حذف | فقط **پس از** موفقیت بکاپ تازه |
| دروازه‌های Restore | ۷، همه **پیش از** هر نوشتن روی مقصد |

### ۲.۱ چرا GCM و نه Encrypt-then-MAC

الزام ۱.۴ می‌گفت: GCM، و اگر محدودیت فنی بود Encrypt-then-MAC **با مستندات فنی**.
محدودیتی نبود، پس fallback ساخته نشد. آنچه در همین runtime اندازه‌گیری شد:

```
aes-256-gcm موجود: بله
رمزنگاری: ۱۴ بایت متن + ۱۶ بایت tag
دست‌کاری ciphertext  → decrypt = false
دست‌کاری tag         → decrypt = false
کلید اشتباه          → decrypt = false
hash_hkdf / hash_hmac / hash_equals / random_bytes: همه موجود
```

چون GCM **احراز اصالت درونی** دارد، دروازهٔ ۳ یک کنترل رمزنگاری است نه یک
مقایسهٔ checksum بیرونی. این دقیقاً همان چیزی است که CBC نداشت: در طرح پیشین،
فایل دست‌کاری‌شده با exit code صفر رمزگشایی می‌شد و تنها یک manifest جانبی
جلویش را می‌گرفت.

### ۲.۲ استقلال دو کلید (الزام ۱.۵)

`config/vira.php` دو متغیر محیطی **جدا** می‌خواند، و دلیلش همان‌جا نوشته شده:
کلید بکاپ همراه فایل به ذخیره‌سازی بیرون از سرور می‌رود، ولی کلید رمزنگاری
رمزها هرگز نباید از سرور بیرون برود. یکی‌کردنشان یعنی لو رفتن یک بکاپ = لو رفتن
همهٔ رمزهای سامانه، و چرخش هرکدام دیگری را باطل می‌کند.

این یک ادعای مستنداتی نیست؛ `BackupRestoreTest::testKeysAreIndependentByConfiguration`
روی خودِ `config/vira.php` و `.env.example` assertion دارد، پس یکی‌کردنشان در آینده
باعث **شکست آزمون** می‌شود.

---

## ۳. شواهد رمزنگاری و آزمون دست‌کاری

### ۳.۱ سوئیت PHP — `BackupRestoreTest`

اجرا روی کامیت `5dcd0c7`: **۱۴ آزمون، ۹۶ assertion، همه سبز.**

| آزمون | چه چیزی را ثابت می‌کند |
|---|---|
| `testGcmIsActuallyAvailableHere` | GCM در همین runtime کار می‌کند **و** احراز اصالت می‌کند: ciphertext دست‌خورده، tag دست‌خورده و کلید اشتباه هر سه `false` می‌دهند |
| `testKeysAreIndependentByConfiguration` | دو کلید از دو متغیر محیطی جدا می‌آیند |
| `testRoundTripRestoresEveryRow` | ساخت ← بازیابی در مقصدی دیگر؛ تعداد جدول، تعداد ردیف و **hash محتوای همهٔ جدول‌ها** یکسان |
| `testPreviousDatabaseIsMovedAsideNotOverwritten` | فایل `.pre-restore-*` دقیقاً همان پایگاه قبلی است |
| `testVerifyWritesNothing` | `verifyDeep()` نه پوشهٔ بکاپ را عوض می‌کند نه مقصد را |
| `testTamperedCiphertextIsRefused` | **دروازهٔ ۳** + restore استثنا می‌دهد |
| `testTruncatedFileIsRefused` | فایل ناقص → **دروازهٔ ۳** |
| `testWrongKeyIsRefused` | کلید اشتباه → **دروازهٔ ۳** |
| `testBadMagicIsRefused` | magic اشتباه → **دروازهٔ ۱** |
| `testInvalidManifestIsRefused` | manifest نامعتبر → **دروازهٔ ۵** |
| `testMemberChecksumMismatchIsRefused` | عضو ناسازگار → **دروازهٔ ۶**، و `verify()` سطحی آن را **نمی‌گیرد** (پس تفکیک دروازه‌ها واقعی است) |
| `testDestinationIsUnchangedAfterEveryRefusal` | در هر سه حالت رد، sha256 و mtime مقصد **و** نبودِ `.pre-restore-*` |
| `testKeyMaterialNeverAppearsInContainer` | کلید (base64 و بایت خام) و نامش هیچ‌جا در کانتینر نیست |
| `testTwoBuildsProduceDifferentContainers` | دو بکاپ از دادهٔ یکسان ≠ هم (IV تکراری تحت GCM فاجعه است) |
| چهار آزمون retention | انتخابِ درستِ «یک کانتینر در هر دوره» |

### ۳.۲ دو خطای واقعی که **فقط با اجرا** پیدا شدند

| # | خطا | چگونه آشکار شد |
|---|---|---|
| ۱ | manifest دو بار `json_encode` می‌شد — یک بار با `JSON_PRETTY_PRINT\|JSON_UNESCAPED_SLASHES` برای عضو آرشیو، یک بار بدون آن برای hash. دو رشتهٔ بایت متفاوت ⇒ **هر کانتینری در دروازهٔ ۵ رد می‌شد**. یعنی تا آن لحظه هیچ بکاپی قابل بازیابی نبود | نخستین اجرای `BackupRestoreTest` — پیام `restore refused at gate 5: manifest does not match the hash in the header` |
| ۲ | منطق retention «همهٔ فایل‌های N دورهٔ آخر» را نگه می‌داشت؛ دسته‌ها کاملاً روی هم می‌افتادند و pruning **هرگز چیزی حذف نمی‌کرد** | اجرای واقعی فرمان با ۲۰ کانتینر با mtime پخش‌شده: خروجی `pruned : 0` |

هیچ‌کدام با خواندن کد پیدا نمی‌شدند. این دو، دلیلِ وجودِ این سوئیت‌اند.

---

## ۴. شواهد بازیابی واقعی در محیط جداسازی‌شده

**الزام:** هرگز به پایگاه دادهٔ مشترک یا دادهٔ واقعی دست نزن.

روش: پایگاه مقصد از `artisan migrate --force` با `DB_DATABASE` اشاره‌شده به یک
مسیر در `/tmp` ساخته شد. `database/database.sqlite` **هرگز باز نشد**.

### ۴.۱ دست‌نخوردن پایگاه مشترک — و یک ادعای اصلاح‌شده

**آنچه اندازه‌گیری شد، در دو مرحلهٔ جدا:**

```
sha256 پیش از هر اجرای backup/restore : d9ebb0894f814c362e14d4a58f6769d2aeac74735ee6d869be2179466f49b317
sha256 پس از همهٔ اجرای backup/restore : d9ebb0894f814c362e14d4a58f6769d2aeac74735ee6d869be2179466f49b317
```
یکسان — بایت‌به‌بایت. **کار backup/restore به پایگاه مشترک دست نزد.**

اما بعد از اجرای Regression Suite (بند ۷) هش عوض شده بود:

```
sha256 پس از Regression Suite : 0767a2292cbd00f166ac80af7173af458d331d1cbf33da6dbb15d62877cad567
mtime پرونده                   : 07:14  (درون بازهٔ اجرای سوئیت، نه بازهٔ backup/restore)
```

نسخهٔ نخست این بند نوشته بود «sha256 پس از همهٔ اجراها یکسان» — و آن **نادرست
بود**، چون آن اندازه‌گیری پیش از شروع Regression Suite گرفته شده بود. اصلاح شد.

**آنچه دربارهٔ خودِ سوئیت اندازه‌گیری شد** (نه حدس):

```
customers 7 · contracts 5 · projects 3 · work_items 9
users 2 · sessions 1 · audit_logs 232 · role_permissions 80
integrity_check: ok        journal_mode: delete      بدون فایل -wal/-shm
```

همهٔ این شمارش‌ها با مقدار ثبت‌شدهٔ پایه یکسان‌اند و خودِ سوئیت نیز همین را
گزارش می‌کند (`isolation shared database untouched (every pass)`). تنها جدولی که
محتوایش ذاتاً متغیر است `sessions` است (یک ردیف، payload ۲۸۰ بایتی) — و بررسیِ
جداسازیِ سوئیت **تعداد ردیف** را مقایسه می‌کند نه هش محتوا را.

**نتیجهٔ صادقانه:** کار backup/restore پایگاه مشترک را تغییر نداد (هش، دو بار
اندازه‌گیری شد). Regression Suite پس از آن پرونده را بازنویسی کرد و من **نمی‌توانم
اثبات کنم** که محتوای غیر `sessions` بایت‌به‌بایت همان است، چون هش محتوای «پیش از»
را نگه نداشتم. آنچه می‌توانم بگویم این است: همهٔ شمارش‌های ردیف یکسان‌اند و
`integrity_check` سالم است. اگر این تمایز برای کارفرما مهم است، در اجرای بعدی هش
محتوای «پیش از» هم ثبت می‌شود.

### ۴.۲ بکاپ واقعی (`vira:backup`)

```
database : /tmp/vira-ops2/iso.sqlite
storage  : /home/user/VIRACRM/storage/app
target   : /tmp/vira-ops2/backups
backup written
  file   : vira-backup-2026-11-26-064549-b7fd20.vbk1
  bytes  : 15,521
  tables : 58
  rows   : 15
  files  : 2
  sha256 : 712753449e0d50aebaf5751f9ba80e85457ff804c05e985c2c3258477def2fdf
  pruned : 0 old container(s)
exit=0
```

ردیف audit نوشته‌شده در همان پایگاه جداسازی‌شده:

```
[2026-11-26 06:43:14] backup: بکاپ موفق: vira-backup-…-a8fd7a.vbk1
                      (15,522 بایت، 58 جدول، 15 ردیف، 2 فایل)
```

### ۴.۳ بازیابی واقعی — با شاهدِ داده، نه فقط «integrity ok»

یک ردیف نشانه (`RESTORE-CANARY-7f3a`) در پایگاه جداسازی‌شده درج شد، بکاپ گرفته
شد، و سپس بازیابی در **مسیری متفاوت**:

```
verification passed — all seven gates
  created : 2026-11-26T06:45:49+00:00
  tables  : 58    rows : 15    files : 2
restore complete
  database       : /tmp/vira-ops2/restored.sqlite
  files restored : 2
exit=0

بررسی پایگاه بازیابی‌شده:
  tables: 58        integrity: ok
  marker table present: YES
  marker rows: 1
  marker value: RESTORE-CANARY-7f3a      ← داده واقعاً برگشت
```

«integrity ok» به‌تنهایی ثابت نمی‌کند داده برگشته است؛ بازگشتِ ردیف نشانه ثابت
می‌کند.

### ۴.۴ ردِ پیش از بازیابی — اندازه‌گیری‌شده

| حالت | دروازه | exit | دادهٔ مقصد |
|---|---|---|---|
| بایت دست‌کاری‌شده | ۳ | **۱** | یکسان |
| فایل بریده (۴۰۰۰ بایت از ۱۵۵۲۱) | ۳ | **۱** | یکسان |
| کلید اشتباه | ۳ | **۱** | یکسان |
| کلید نادرست‌قالب (`WRONG`) | — | **۱** | یکسان |
| کلید خالی (restore) | — | **۱** | یکسان |
| کلید خالی (backup) | — | **۱** | **هیچ فایلی نوشته نشد** |
| فقط تأیید، بدون `--force` | — | **۰** | **مقصد اصلاً ساخته نشد** |
| بکاپ و بازیابی سالم | — | **۰** | — |

«دادهٔ مقصد یکسان» یعنی: `tables=58 rows_excl_audit=15` و hash محتوای همهٔ
جدول‌های غیر `audit_logs` پیش و پس از تلاشِ ردشده **دقیقاً یکسان**.

### ۴.۵ یک نکته که باید صریح گفته شود

هنگام اندازه‌گیری، sha256 *فایل* مقصد پس از یک بازیابیِ ردشده عوض شده بود.
علتش پیدا شد: فرمان یک ردیف `restore_failed` در `audit_logs` می‌نویسد و آن جدول
در همان پایگاه مقصد است.

* **هیچ داده‌ای از کانتینر نصب نمی‌شود** (جدول بالا).
* **یک ردیف audit افزوده می‌شود.**

پیام فرمان در ابتدا «مقصد تغییر نکرد» بود که با این اندازه‌گیری سازگار نبود، پس
**اصلاح شد**. اگر خود پایگاه خراب باشد، نوشتن همان ردیف ممکن است خطا بدهد؛ اکنون
رویداد همیشه در فایل لاگ هم نوشته می‌شود و خطای ثبت، خطای اصلی را نمی‌پوشاند.

### ۴.۶ انتقال پایگاه موجود به کنار (نه بازنویسی)

```
.pre-restore files: 3
  restored.sqlite.pre-restore-20261126064622  sha=6528c5619e64aef2
original pre-restore sha was                    6528c5619e64aef2
```
کپیِ کنارگذاشته‌شده **دقیقاً** همان پایگاهی است که پیش از بازیابی آنجا بود.

### ۴.۷ Pruning واقعی

۲۰ کانتینر با تاریخ‌های پخش‌شده در ۲۰ روز + ۱ بکاپ تازه، با سیاست ۷/۴/۶:

```
before: 20 containers, 20 sidecars
  pruned : 12 old container(s)
after : 9 containers, 9 sidecars
kept: 11-08, 11-15, 11-20, 11-21, 11-22, 11-23, 11-24, 11-25, 11-26
orphan sidecars: 0
```
۹ بازمانده = ۷ روزانهٔ آخر + ۲ انتخاب هفتگی. دقیقاً همان سیاست.

---

## ۵. وضعیت صفحهٔ Audit Log (مرحلهٔ ۳)

| الزام | وضعیت |
|---|---|
| دسترسی فقط با `audit.view` | ✅ `permission:audit.view` روی مسیر؛ middleware سمت سرور |
| فیلتر کاربر | ✅ `?user=<id>` |
| فیلتر نوع رویداد | ✅ `?event=<code>` |
| فیلتر موجودیت | ✅ `?subject=<Model>` |
| فیلتر تاریخ | ✅ `?from=` و `?to=` |
| جست‌وجو | ✅ `?q=` روی `description` |
| صفحه‌بندی | ✅ ۳۰ ردیف، `<x-pagination>` |
| نمایش جزئیات تغییرات | ✅ نام فیلدهای تغییرکرده |
| **هرگز** نمایش رمز/کلید/token | ✅ دو لایهٔ redaction (بند ۵.۲) |
| ثبت `audit.viewed` | ✅ در هر بارخوانی |
| **بدون** ویرایش/حذف از UI | ✅ هیچ مسیر نوشتنی وجود ندارد |
| رویدادهای حساس قابل بازبینی، بدون افشای داده | ✅ رویداد و نام فیلد دیده می‌شود، مقدار نه |

### ۵.۱ نتایج `audit-log-page` — **۱۶ از ۱۶**

```
PASS  T-ALP-01  GET /audit با session هیچ -> 302، محتوای لاگ: false
PASS  T-ALP-02  super admin -> 200، 79900 بایت
PASS  T-ALP-03  audit.viewed: 0 -> 1 پس از یک بارخوانی
PASS  T-ALP-04  manager -> 403
PASS  T-ALP-05  account_manager -> 403
PASS  T-ALP-06  viewer -> 403
PASS  T-ALP-07  denied: 27 -> 30 (سه رد، سه ردیف)
PASS  T-ALP-08  ?event=login -> 200، فقط رویدادهای منطبق
PASS  T-ALP-09  ?user=1 -> 200 با ردیف؛ ?user=999999 -> 200 با حالت خالی
PASS  T-ALP-10  ?from=<فردا> -> 200 با حالت خالی
PASS  T-ALP-11  صفحهٔ ۱ (rel=next) و صفحهٔ ۲ (rel=prev)، بدنه‌ها متفاوت
PASS  T-ALP-12  رمز در HTML نیست؛ نشانگر [redacted] هست
PASS  T-ALP-13  POST/PUT/PATCH/DELETE -> 405/405/405/405
PASS  T-ALP-14  ردیف‌های موجود پس از بارخوانی عوض نشدند
PASS  T-ALP-15  عبارت جست‌وجو در audit_logs ثبت نشد
PASS  T-ALP-16  audit.view وجود دارد؛ grant به هیچ نقش: ۰
```

### ۵.۲ دو تصمیم طراحی که آزمون‌شان هم نوشته شد

**الف — چرا redaction دوباره، وقتی `AuditRecorder` از قبل redact می‌کند؟**
`T-ALP-12` یک ردیف را با **SQL خام** درج می‌کند، کاملاً بیرون از `AuditRecorder`،
با رمزِ plaintext در `changes` و `password: <secret>` در description. سپس بررسی
می‌کند هیچ‌کدام در HTML نیست. این لایهٔ دوم است که آزموده می‌شود — همان لایه‌ای
که در برابر ردیفی ایستاده که مسیر لایهٔ اول را نرفته.

`AuditLog::scrub()` فقط شکل‌های `key: value` و `key = value` و پیشوند `v1:` را
پنهان می‌کند. نسخهٔ نخست هر واژهٔ پس از کلیدواژه را پنهان می‌کرد و
«password changed for user 4» را به «password [redacted] for user 4» تبدیل می‌کرد —
یعنی **دقیقاً همان جمله‌ای را از بین می‌برد که بازجو به آن نیاز دارد**.
`testProseIsNotRedacted` جلوی بازگشت این اشتباه را می‌گیرد.

**ب — چرا عبارت جست‌وجو ثبت نمی‌شود؟**
کسی که دنبال یک رمزِ لو رفته می‌گردد، آن را در خانهٔ جست‌وجو **تایپ می‌کند**. اگر
عبارت جست‌وجو در `audit_logs` ثبت شود، صفحهٔ گزارش رویدادها به جایی تبدیل می‌شود
که رمز در آن ذخیره شده. پس `audit.viewed` فقط **نام** فیلترهای فعال را ثبت می‌کند،
نه مقدارشان. `T-ALP-15` همین را اندازه می‌گیرد.

---

## ۶. آزمون‌های دسترسی و امنیت

| سوئیت | نتیجه |
|---|---|
| `credential-export-audit` | **۱۳ از ۱۳** (پیش از این ۱۲؛ `T-CEA-05b` افزوده شد) |
| `audit-log-page` | **۱۶ از ۱۶** (تازه) |
| `BackupRestoreTest` | ۱۴ آزمون / ۹۶ assertion |
| `AuditRedactionTest` | ۸ آزمون / ۳۶ assertion |
| `route-permissions` | **۴ از ۴** — ۱۰۶ مسیر، ۱۰۲ نیازمند احراز، **۰ بدون مجوز**، ۰ مجوز ناشناخته |
| `docs-integrity` | **۵۲ از ۵۲** |

نقش‌ها پس از تغییر (از پایگاه داده، نه از مستندات):

| مجوز | نقش‌ها |
|---|---|
| `credentials.export` | **هیچ** (فقط super admin) |
| `credentials.reveal` | manager |
| `credentials.manage` | manager, account_manager |
| `credentials.view` | manager, account_manager |
| `audit.view` | **هیچ** (فقط super admin) |

`role_permissions` = ۸۰ ردیف؛ سهم manager = ۳۶.

---

## ۷. خروجی Regression Suite

اجرا روی کامیت **`5dcd0c7bc9a11489f459f328bc5a02646ddd0301`** با درخت کاری **تمیز**
(۰ پرونده در `git status --porcelain`)، پس از commit و نه پیش از آن.
لاگ کامل: `/tmp/regression-5dcd0c7.log`.

```
suites     38 total · 38 executed · 38 passed · 0 failed · 0 skipped
assertions 1108 executed · 1108 passed · 0 failed · 0 skipped
isolation  shared database untouched (every pass)

REGRESSION SUITE PASSED
exit 0
```

### ۷.۱ پایگاه دادهٔ مشترک پس از اجرا

```
  ok  customers    7 -> 7
  ok  contracts    5 -> 5
  ok  projects     3 -> 3
  ok  work_items   9 -> 9
  ok  users        2 -> 2
  ok  sessions     1 -> 1
  ok  audit_logs   232 -> 232
```

### ۷.۲ تطبیق با اجرای پیشین — بدون حدس

مقایسهٔ سوئیت‌به‌سوئیت با لاگ `701e256` (۳۷ سوئیت / ۱۰۸۸ assertion):

| سوئیت | پیش | اکنون | علت |
|---|---|---|---|
| `audit-log-page` | — | **۱۶** | سوئیت تازهٔ مرحلهٔ ۳ |
| `credential-export` | ۱۲ | **۱۳** | افزودن `T-CEA-05b` |
| `unit` | ۱۳ | **۱۵** | دو کلاس تازه: `BackupRestoreTest`، `AuditRedactionTest` |
| `views` | ۹۳ | **۹۴** | یک Blade تازه: `audit/index.blade.php` |
| ۳۴ سوئیت دیگر | بدون تغییر | بدون تغییر | — |

`۱۰۸۸ + ۱۶ + ۱ + ۲ + ۱ = ۱۱۰۸` — و جمع اعدادِ تک‌تک سوئیت‌ها در همین اجرا نیز
دقیقاً ۱۱۰۸ می‌شود. هیچ assertion بی‌توضیحی باقی نمانده است.

> **یادآوری یک قید:** سوئیت `unit` تعداد **کلاس‌ها** را گزارش می‌کند، نه
> assertionها. پس ۹۶ assertionِ `BackupRestoreTest` و ۳۶ assertionِ
> `AuditRedactionTest` در عدد ۱۱۰۸ ظاهر نمی‌شوند؛ سهمشان در این جدول فقط
> «+۲ کلاس» است. این یک خاموشی در گزارش‌دهی runner است، نه نبودِ آزمون —
> اجرای مستقیم `tests/run.php` روی همین کامیت هر دو را با تعداد assertion نشان
> می‌دهد: **۱۵ کلاس / ۷۸۱ assertion / ۰ شکست** — Feature = ۱۰ کلاس / ۵۶۸
> assertion، Unit = ۵ کلاس / ۲۱۳ assertion.

### ۷.۳ آنچه این خروجی **نیست**

خروجی `docs-integrity` (۵۲/۵۲) بخشی از همین جدول است، ولی **شاهدِ اجرای عملیاتی
نیست**. آن سوئیت بررسی می‌کند مستندات با کد هم‌خوان است؛ نه اینکه بکاپی گرفته یا
بازیابی‌ای انجام شده باشد. شواهد عملیاتی فقط در بندهای ۳ و ۴ آمده‌اند.

---

## ۸. محدودیت‌های باقی‌مانده

۱. **زمان‌بندی خودکار ساخته نشد.** cron و SSH روی هاست مقصد `BLOCKED`اند. برای
   جلوگیری از ساختن سطح حملهٔ تازه، هیچ route خودراه‌اندازی ساخته نشد. این یک
   شکاف عملیاتی واقعی است، نه یک نکتهٔ فنی.

۲. **این محیط php-wasm است، نه هاست مقصد.** همهٔ توانمندی‌ها در همین runtime
   تأیید شدند (OpenSSL 1.1.1t، GCM فعال و احرازکننده، SQLite 3.51.0،
   `SQLite3::backup()`). `tools/checks/env-capabilities.php` باید روی هاست واقعی
   اجرا شود؛ تا آن زمان هیچ‌یک از این فرض‌ها برای آن هاست تأیید نیست.

۳. **`artisan list` در این runtime crash می‌کند** (برخورد polyfill مربوط به
   `ext-intl`). خودِ فرمان‌ها سالم‌اند — هر سه exit=0. به همین دلیل در فرمان‌ها
   از `$this->table()` استفاده نمی‌شود: همان crash یک بار باعث شد ثبت audit اجرا
   نشود.

۴. **کل payload در حافظه رمزنگاری می‌شود.** با `memory_limit=256M` و سقف
   ۶۴ مگابایت، بکاپ بزرگ‌تر **رد می‌شود** نه اینکه حافظه را تمام کند.

۵. **Off-site واقعی آزموده نشد** — مقصد خارجی در دسترس نبود.

۶. **بازیابی زیر بار آزموده نشد.**

۷. **`storage/app` عملاً خالی است.** بکاپ واقعی «۲ فایل» گزارش کرد، ولی آن دو فقط
   `private/.gitignore` و `public/.gitignore` هستند. مسیر فایل‌ها در کانتینر
   **اجرا و بایت‌به‌بایت مقایسه شد**، ولی با پیوست واقعی مشتری آزموده نشده.

۸. **همهٔ آزمون‌ها روی SQLite‌اند.** اجرای سوئیت روی MariaDB/MySQL همچنان باز است
   (B4 در گزارش ممیزی).

۹. **`composer.lock` هنوز commit نشده** (B2). بدون آن، نصب روی هاست مقصد ممکن است
   نسخه‌های متفاوتی بگیرد.

۱۰. **محتوای `docs/workspace/` (۱۳ پرونده) و `docs/ai/` (۷ پرونده) هنوز تعریف
    نشده.** این مورد عمداً دست‌نخورده ماند، چون هیچ‌جای مخزن مشخص نمی‌کند آن
    پرونده‌ها باید چه باشند و ساختنشان با حدس، خلافِ قیدِ «بدون فرض» است.

---

## ۹. وضعیت نهایی Git و درخت کاری

| مورد | مقدار |
|---|---|
| کامیت پایه | `964efb74de9879324fd60e3463ca5c629f7b9055` |
| کامیت پیاده‌سازی | `5dcd0c7bc9a11489f459f328bc5a02646ddd0301` |
| درخت کاری هنگام اجرای Regression | **تمیز** (۰ پرونده در `git status --porcelain`) |
| تغییرات | ۲۶ پرونده، ۳۶۴۶ افزوده، ۲۸ حذف‌شده |
| `database/database.sqlite` | gitignore‌شده؛ **توسط کار backup/restore دست‌نخورده** (sha256 یکسان در دو اندازه‌گیری). Regression Suite پس از آن پرونده را بازنویسی کرد — جزئیات و مرزِ آنچه اثبات شده در بند ۴.۱ |
| WordPress / theme / پلاگین‌های سایت اصلی | **دست نخورد** |
| API | **پیاده‌سازی نشد** — فقط معماری مستند شد |

این گزارش به‌عنوان کامیتِ جدا ثبت می‌شود، تا شواهدِ بالا به همان کامیتی که
آزمایش‌ها رویش اجرا شد اشاره کنند و نه به کامیتی که هنوز وجود نداشت.

---

**پایان گزارش. ادعای Production Ready یا Production Approved در این سند نیست.**
