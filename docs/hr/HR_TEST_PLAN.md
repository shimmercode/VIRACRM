# برنامهٔ آزمون ماژول پرسنل و حقوق

## سه لایه

| لایه | ابزار | چه چیزی را ثابت می‌کند |
|---|---|---|
| واحد | `tests/Feature/PayrollTest.php` | فرمول و حالت‌ها دقیقاً همان‌اند |
| سرتاسری | `tools/checks/payroll-flow.mjs` | مسیر واقعی اپراتور به همان سرویس‌ها می‌رسد |
| بصری | `tools/checks/work-item-shots.mjs` | ۹ صفحه در ۷ عرض بدون سرریز |

## `PayrollTest` — ۱۱ سناریو

| سناریو | ادعا |
|---|---|
| `formulaIsFixedSalaryPlusManualLines` | ۲۰+۲+۱.۵+۰.۹+۰.۶ = ۲۵ میلیون دریافتی؛ ۴.۵ میلیون کسورات؛ ۲۰.۵ خالص |
| `negativeItemReducesNetAndDoesNotCancel` | +۱ و −۱ میلیون، ۲ میلیون اثر دارند نه صفر |
| `projectsTotalNeverEntersTheFormula` | ۹۰۰ میلیون پروژه خالص را تغییر نمی‌دهد |
| `negativeNetIsRefused` | خالص منفی رد می‌شود |
| `publishedPeriodCannotBeRecomputed` | دورهٔ منتشرشده محاسبهٔ مجدد نمی‌شود |
| `amendWritesRestorableSnapshot` | snapshot مقدار **قبل** را دارد |
| `buildPeriodSkipsExistingPayslips` | بازسازی، اقلام تایپ‌شده را پاک نمی‌کند |
| `negativeStoredAmountIsRefused` | مقدار منفی ذخیره‌شده رد می‌شود (ستون unsigned) |
| `postingSplitsGrossIntoNetAndWithheld` | سند: بدهکار ۲۱.۹ = بستانکار ۲۱.۵ + ۰.۴ |
| `postingRefusesADraftMonth` | پیش‌نویس به دفتر کل نمی‌رود |
| `postingRefusesAnInconsistentPayslip` | فیش ناسازگار پیش از سند رد می‌شود |

## `payroll-flow.mjs` — ۲۷ بررسی

مسیر: ساخت کارمند → ساخت دوره → ساخت فیش → ثبت حقوق پایه و دو قلم دستی →
محاسبه → تلاش برای انتشار با فیش محاسبه‌نشده (باید رد شود) → محاسبهٔ دوره →
انتشار → خواندن سند از دیتابیس → انتشار دوباره (باید یک سند بماند) →
اصلاح فیش منتشرشده با دلیل → اصلاح بدون دلیل (باید رد شود).

نکات مهم:

- `T-PAY-12` عدد فرمول را **از دیتابیس پس از ارسال فرم واقعی** می‌خواند:
  `earnings=21900000 deductions=400000 net=21500000`.
- `T-PAY-13` با دو کارمند واقعی کار می‌کند تا یک فیش عمداً محاسبه‌نشده بماند؛
  انتشار ماه نیمه‌محاسبه‌شده باید رد شود.
- `T-PAY-17/18` سند را از `journal_lines` می‌خوانند: بدهکار = بستانکار = ۲۱.۹
  میلیون، و `Dr 5-2-01` دقیقاً برابر جمع ناخالص فیش‌ها.
- `T-PAY-22..24` با نشست دوم و نقش `manager` اجرا می‌شوند.

## نتیجهٔ ثبت‌شده

```
PayrollTest          27 assertions  ok
payroll-flow        27/27 passed
work-item-shots     63/63 passed   (9 pages × 7 widths)
tests/run.php       11 suites / 565 assertions / 0 failed
```
