# WINDOWS_WEBDAV_COMPATIBILITY — سازگاری با Windows File Explorer

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> ## هشدار صریح پیش از هر چیز
> **«درایو شبکه‌ای ویندوز» آن چیزی نیست که کاربر فکر می‌کند.**
> ماکروسافت سرویس WebClient را **در نوامبر ۲۰۲۳ Deprecated** اعلام کرده و به‌صورت پیش‌فرض اجرا نمی‌شود. همچنین **پیش‌فرض `FileSizeLimitInBytes` برابر ۵۰ مگابایت** و **پیش‌فرض `FileAttributesLimitInBytes` برابر ۱ مگابایت** است.
>
> **دربارهٔ «۴ گیگابایت» هیچ ادعایی نمی‌کنیم.** کلید تنظیم از نوع `REG_DWORD` است، پس حداکثر مقدار *قابل تنظیم* `4,294,967,295` بایت (۴ GiB منهای یک بایت) است؛ ولی اینکه انتقال در آن اندازه واقعاً موفق شود، **در سند رسمی مایکروسافت نیامده** و تا آزمون `W`-series **`NOT TESTED`** است. صفحهٔ رسمی Win32 WebDAV هیچ سقف حجم فایلی مستند نمی‌کند.
>
> این سند **قبل از هر تست واقعی** حداکثر ادعای قابل قبول را این‌گونه ثبت می‌کند:
>
> | ادعا | وضعیت امروز |
> |------|-------------|
> | «با Windows File Explorer مثل یک درایو کار می‌کند» | `NOT TESTED` — و مشروط |
> | «بدون تغییر Registry کار می‌کند» | **`NOT SUPPORTED`** — سقف ۵۰MB پیش‌فرض |
> | «فایل‌های بالای ۴GB را پشتیبانی می‌کند» | **`IMPOSSIBLE`** — محدودیت کلاینت، نه سرور |
> | «روی همهٔ نسخه‌های ویندوز بی‌دردسر است» | `NOT TESTED` — و بسامد کاهشی دارد |

---

## ۱. آنچه واقعاً در ویندوز رخ می‌دهد

### ۱.۱. زنجیرهٔ فنی

```
Windows File Explorer
   → mrxdav.sys (redirector)
      → WebClient service (webclnt.dll)
         → HTTPS → WebDAV server (VPS)
```

اگر `WebClient` اجرا نشود: **خطای `67`** («Network name cannot be found») هنگام mount — که گمراه‌کننده است و کاربر فکر می‌کند سرور خراب است.

### ۱.۲. کاهش رسمی

| تاریخ | رخداد | منبع |
|-------|-------|------|
| نوامبر ۲۰۲۳ | «The Webclient (WebDAV) service is deprecated. The Webclient service isn't started by default in Windows.» | صفحهٔ رسمی Deprecated features ماکروسافت |

**ترجمهٔ عملی برای پروژه:** ما باید برای کاربر **راهنمای گام‌به‌گام** بدهیم که شامل **شروع دستی سرویس** است — نه یک «کلیک کن و وصل شو».

---

## ۲. تنظیمات Registry — واقعیت‌ها

کلید: `HKLM\SYSTEM\CurrentControlSet\Services\WebClient\Parameters`

| مقدار | نوع | پیش‌فرض | معنا | برای ما |
|-------|-----|---------|------|---------|
| `FileSizeLimitInBytes` | DWORD | **50,000,000** (~۴۷.۷MiB) | سقف انتقال هر فایل | **باید افزایش یابد** یا WebDAV برای فایل متوسط هم کار نمی‌کند |
| `FileAttributesLimitInBytes` | DWORD | **1,000,000** | سقف مجموع متادیتای یک پوشه (~۱۰۰۰ فایل) | پوشهٔ بزرگ = خالی نمایش داده می‌شود یا «Disk is not formatted» |
| `BasicAuthLevel` | DWORD | **1** | 0=خاموش، 1=Basic فقط SSL، 2=Basic روی HTTP | **1 را نگه دارید.** ۲ یعنی رمز به‌صورت متن روشن |
| `SupportLocking` | DWORD | **1** | پشتیبانی قفل | نگه دارید |
| `InternetServerTimeoutInSec` | DWORD | 30 | تایم‌اوت سرور اینترنتی | برای اینترنت ممکن است ۳۰ کافی نباشد |
| `SendReceiveTimeoutInSec` | DWORD | 60 | تایم‌اوت بعد از ارسال | آپلود بزرگ ممکن است قطع شود |
| `ServerNotFoundCacheLifeTimeInSec` | DWORD | 60 | کش «سرور WebDAV نیست» | اگر اول اشتباه شد، ۶۰ ثانیه قفل می‌ماند |

### ۲.۱. سقف مطلق ۴GB

مقدار از نوع DWORD **۳۲ بیتی** است → حداکثر `4,294,967,295` بایت ≈ **۴GiB**.
**هیچ Registry، Policy یا Hotfix این را بالا نمی‌برد.** برای فایل بزرگ‌تر، **کلاینت سوم‌شخص** یا **آپلود از مرورگر** لازم است.

> **این باید در قرارداد انتظارات نوشته شود**، نه در مستندات فنی که کسی نمی‌خواند.

---

## ۳. آنچه ما می‌توانیم کنترل کنیم

| مورد | آیا سرور می‌تواند حل کند؟ | توضیح |
|------|:---:|-------|
| سقف ۵۰MB | ❌ | در کلاینت است |
| سقف ۴GB | ❌ | در کلاینت است |
| سقف ~۱۰۰۰ فایل در پوشه | ❌ | در کلاینت است |
| شروع نشدن WebClient | ❌ | در ویندوز است |
| نام فارسی/Unicode | ✅ **بله** | با `Content-Disposition` و XML `multistatus` درست |
| قفل واقعی | ✅ **بله** | با sabre/dav |
| Permission Denied واضح | ✅ **بله** | `403` با بدنهٔ فارسی |
| Reconnect | ✅ **بله** | بدون session سمت سرور (Basic) |
| Timeout طولانی | ⚠️ **نیمه** | با `keepalive`/chunked، ولی کلاینت هم تنظیم دارد |

---

## ۴. راهنمای عملی برای کاربر ویندوز (پیش‌نویس)

> این راهنما **بخشی از محصول** است، نه ضمیمه. بدون آن، پشتیبانی overwhelmed می‌شود.

### ۴.۱. پیش‌نیاز

| # | کار | چرا |
|---|-----|-----|
| 1 | سرویس `WebClient` را Start و Startup را Automatic کنید | وگرنه mount خطای ۶۷ می‌دهد |
| 2 | `FileSizeLimitInBytes` را به مقدار مورد نیاز (مثلاً `5368709120` برای ۵GB — **ولی حداکثر ۴GB کار می‌کند**) تنظیم کنید | پیش‌فرض ۵۰MB است |
| 3 | `FileAttributesLimitInBytes` را به `20000000` افزایش دهید | پوشهٔ بزرگ |
| 4 | `WebClient` را restart کنید (`net stop WebClient && net start WebClient`) | تغییرات فقط در start خوانده می‌شوند |
| 5 | `BasicAuthLevel` را روی **1** نگه دارید | امنیت |
| 6 | آدرس را با **HTTPS** و با **FQDN کامل** map کنید | Basic بدون TLS رد می‌شود |

### ۴.۲. اسکریپت PowerShell (اختیاری، برای IT)

```powershell
# نیازمند اجرا به‌عنوان Administrator
$p = 'HKLM:\SYSTEM\CurrentControlSet\Services\WebClient\Parameters'

# 2 GiB سقف فایل — زیر سقف مطلق 4GiB
Set-ItemProperty -Path $p -Name FileSizeLimitInBytes -Value 2147483648 -Type DWord
# ~20,000 فایل در یک پوشه
Set-ItemProperty -Path $p -Name FileAttributesLimitInBytes -Value 20000000 -Type DWord
# Basic فقط روی TLS
Set-ItemProperty -Path $p -Name BasicAuthLevel -Value 1 -Type DWord

Set-Service WebClient -StartupType Automatic
Restart-Service WebClient
```

> **هشدار در متن اسکریپت:** مقادیر بالاتر از `4294967295` پذیرفته نمی‌شوند و رفتار نامشخص دارند. سقف واقعی ۴GiB است.

### ۴.۳. Mount

```
\\dav.example.com@SSL\DavWWWRoot\files\
```
یا در Explorer: `Map network drive` → آدرس بالا → credentials سازمانی.

**نکتهٔ فارسی:** نام پوشهٔ فارسی در Explorer نمایش درست دارد، ولی در URL باید percent-encoded شود — کلاینت این کار را خودکار می‌کند.

---

## ۵. ماتریس تست ویندوز (اجباری پیش از هر ادعا)

> **هیچ‌کدام از این ردیف‌ها امروز اجرا نشده است. همه `NOT TESTED`.**
> ستون «محیط» باید با نسخهٔ واقعی ویندوز پر شود.

| # | مورد | روش | انتظار | وضعیت |
|---|------|-----|--------|-------|
| W01 | اتصال و Mount | Map network drive | درایو ظاهر شود | `NOT TESTED` |
| W02 | احراز هویت | پسورد سازمانی | پذیرش | `NOT TESTED` |
| W03 | نمایش پوشه‌ها | باز کردن درایو | ساختار درست | `NOT TESTED` |
| W04 | ساخت پوشه | New Folder | ساخته شود | `NOT TESTED` |
| W05 | Upload کوچک (<10MB) | Drag & drop | موفق | `NOT TESTED` |
| W06 | Upload ۵۰MB | Drag & drop | **شکست پیش‌فرض** | `NOT TESTED` |
| W07 | Upload ۵۰MB پس از Registry | Drag & drop | موفق | `NOT TESTED` |
| W08 | Upload ۲GB | Drag & drop | موفق (اگر Registry) | `NOT TESTED` |
| W09 | Upload ۵GB | Drag & drop | **شکست قطعی** | `NOT TESTED` |
| W10 | Download | Copy به دسکتاپ | موفق | `NOT TESTED` |
| W11 | Rename | F2 | موفق | `NOT TESTED` |
| W12 | Move | Drag به پوشهٔ دیگر | موفق | `NOT TESTED` |
| W13 | Copy | Ctrl+C/V | موفق | `NOT TESTED` |
| W14 | Delete | Delete key | به Trash برود | `NOT TESTED` |
| W15 | Lock/Unlock | باز کردن فایل Word | قفل و آزادسازی | `NOT TESTED` |
| W16 | Reconnect | قطع و وصل مجدد | کار کند | `NOT TESTED` |
| W17 | نام فارسی | فایل `قرارداد.docx` | درست | `NOT TESTED` |
| W18 | نام طولانی (>۱۵۰ کاراکتر) | ساخت | رفتار مشخص | `NOT TESTED` |
| W19 | نام با فاصله و `#` و `%` | ساخت | درست یا خطای روشن | `NOT TESTED` |
| W20 | نام رزرو ویندوز (`CON.txt`) | ساخت | **رد** با پیام | `NOT TESTED` |
| W21 | Permission Denied | کاربر بدون مجوز | ۴۰۳ روشن | `NOT TESTED` |
| W22 | Revocation حین استفاده | لغو دسترسی | قطع در ≤۳۰s | `NOT TESTED` |
| W23 | قطع CRM | خاموش کردن CRM | ۵۰۳ + پیام | `NOT TESTED` |
| W24 | Token Expiration | انتظار > TTL | درخواست اعتبار مجدد | `NOT TESTED` |
| W25 | پوشه با ۱۰۰۰+ فایل | باز کردن | رفتار سقف متادیتا | `NOT TESTED` |
| W26 | WebClient خاموش | Stop service | خطای ۶۷ مستند | `NOT TESTED` |
| W27 | Cache کلاینت | تغییر نام سمت سرور | رفتار | `NOT TESTED` |
| W28 | Windows 10 | نسخهٔ هدف | — | `NOT TESTED` |
| W29 | Windows 11 | نسخهٔ هدف | — | `NOT TESTED` |
| W30 | Windows Server | اگر استفاده می‌شود | — | `NOT TESTED` |

**قاعدهٔ گزارش:** تا وقتی ردیف W01 با محیط مشخص پر نشده، هیچ‌جا نمی‌توان نوشت «سازگار با Windows Explorer». فقط می‌توان نوشت: **«پیاده‌سازی بر پایهٔ استاندارد انجام شده؛ آزمون ویندوز در انتظار است.»**

---

## ۶. گزینه‌های جایگزین که باید به کارفرما ارائه شود

| گزینه | مزیت | عیب | هزینه |
|-------|------|-----|-------|
| **WebDAV + WebClient** | بدون نصب، بومی ویندوز | Deprecated، سقف ۵۰MB/۴GB، Registry لازم | صفر |
| **کلاینت WebDAV سوم‌شخص** (مثلاً Cyberduck/WinSCP) | سقف ویندوز ندارد، پایدار | نصب لازم، UX متفاوت | صفر تا کم |
| **کلاینت همگام‌سازی اختصاصی** | تجربهٔ OneDrive-مانند | ساخت و نگهداری سنگین | بالا |
| **SMB داخلی + VPN** | تجربهٔ واقعی درایو | ممنوعیت اینترنت، نیاز VPN | متوسط |
| **فقط مرورگر** | امن‌ترین، ساده‌ترین | بدون Explorer | صفر |

**پیشنهاد مستند:** F1 = **WebDAV + راهنمای Registry**، و **هم‌زمان** کلاینت سوم‌شخص به‌عنوان مسیر پشتیبانی‌شده معرفی شود. **تصمیم در OD-116.**

---

## ۷. SMB — چرا حتی برای «درایو واقعی» پیشنهاد نمی‌شود

| مورد | واقعیت |
|------|---------|
| پورت‌های SMB | ۱۳۹ و ۴۴۵ — هدف اصلی باج‌گیرها (WannaCry از همین) |
| باز کردن روی اینترنت | **ممنوعیت صریح کارفرما** و توصیهٔ امنیتی جهانی |
| جایگزین | VPN + SMB داخلی، یا NAS در شبکهٔ داخلی |
| cPanel | SMB روی cPanel قابل استقرار پایدار نیست |

**SMB و WebDAV دو پروتکل متفاوت‌اند و هرگز به‌جای هم معرفی نمی‌شوند.**

---

## ۸. تعداد فایل در پوشه — محدودیت واقعی و راه‌حل

| محدودیت | منبع | راه‌حل ما |
|---------|------|-----------|
| ~۱۰۰۰ فایل در Explorer | `FileAttributesLimitInBytes` = 1,000,000 | راهنمای Registry + هشدار در UI برای پوشه‌های بزرگ |
| همهٔ آیتم‌ها در یک PROPFIND | sabre/dav | paging در سرور با `Depth: 1` و `allprop` محدود |
| حافظهٔ سرور | PHP | `memory_limit` مستند شود |

**پیشنهاد طراحی:** در WebDAV، برای پوشه‌های بیش از ۵۰۰ آیتم، سرور paging نکند (چون WebDAV paging ندارد) ولی **UI** هشدار دهد «این پوشه در Explorer ممکن است ناقص نمایش داده شود». **تصمیم در OD-117.**

---

## ۹. آنچه هنوز اثبات نشده

| مورد | وضعیت |
|------|-------|
| هر ۳۰ ردیف W01–W30 | `NOT TESTED` |
| رفتار واقعی با نام فارسی روی ویندوز ۱۱ | `NOT TESTED` |
| اثر Deprecation در به‌روزرسانی‌های آیندهٔ ویندوز | **نامشخص** — ریسک باز |
| کارایی با ۵۰۰۰ فایل در یک پوشه | `NOT TESTED` |
