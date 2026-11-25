# ماتریس نیازمندی‌های توسعه‌یافته — EXP-*

> **این سند از `REQUIREMENTS_TRACEABILITY.md` جداست.**
> آن سند ۹۶ نیازمندی `REQ-*` سند اصلی کارفرما را ردیابی می‌کند.
> این سند نیازمندی‌های **جدید** دستور ۳۶ بخشی را با شناسهٔ `EXP-*` ردیابی می‌کند.
> دو شمارش هرگز با هم جمع نمی‌شوند — یکی تاریخ مستند است، دیگری دامنهٔ توسعه.

**آخرین به‌روزرسانی:** ۱۴۰۵/۰۹/۰۱ · **کل نیازمندی‌ها:** ۱۲۴

## واژگان وضعیت

| وضعیت | معنا |
|---|---|
| `VERIFIED` | پیاده‌شده **و** با تست اجراشده اثبات شده |
| `PARTIAL` | بخشی پیاده‌شده؛ کمبود دقیقاً نوشته شده |
| `NOT_IMPLEMENTED` | هیچ کدی وجود ندارد |
| `BLOCKED` | منتظر تصمیم کارفرما یا منبع بیرونی؛ سؤال دقیق ثبت شده |

---

## خلاصه

| وضعیت | تعداد | درصد |
|---|---|---|
| VERIFIED | ۸ | ۶٪ |
| PARTIAL | ۰ | ۰٪ |
| NOT_IMPLEMENTED | ۱۱۲ | ۹۰٪ |
| BLOCKED | ۴ | ۳٪ |

---

## A. تکمیل ماژول‌های موجود

| شناسه | نیازمندی | وضعیت | شاهد |
|---|---|---|---|
| EXP-CORE-001 | کارتابل: فهرست تعهدهای دوره‌ای با شمارش کل | VERIFIED | `work-item-flow` T-WI-01/02 |
| EXP-CORE-002 | کارتابل: بستن مورد با ثبت شماره/مبلغ/تاریخ صورتحساب | VERIFIED | T-WI-05/06 |
| EXP-CORE-003 | کارتابل: لغو جدا از انجام، بازکردن مورد بسته | VERIFIED | `WorkItemAndNoteLifecycleTest` |
| EXP-CORE-004 | کارتابل: ایجاد مورد جدید با تاریخ جلالی | VERIFIED | `WorkItemController::store` |
| EXP-CORE-005 | یادداشت و وظیفه: CRUD کامل متصل به مشتری/قرارداد/پروژه | VERIFIED | `NoteController`, T-WI-08..10 |
| EXP-CORE-006 | یادداشت: انجام/بازکردن با ثبت انجام‌دهنده | VERIFIED | T-WI-10, `NoteController::complete` |
| EXP-CORE-007 | اعتبارسنجی تاریخ جلالی: تاریخ ناموجود خطا می‌دهد نه NULL | VERIFIED | T-WI-12/13 |
| EXP-CORE-008 | کارتابل و یادداشت: ۷ عرض بدون سرریز افقی | VERIFIED | `work-item-shots` ۲۸/۲۸ |
| EXP-CORE-009 | پرسنل (employees): CRUD + قرارداد پرسنل | NOT_IMPLEMENTED | جدول خالی، بدون مدل |
| EXP-CORE-010 | حقوق و دستمزد مطابق OD-16 | NOT_IMPLEMENTED | جدول‌ها آماده، بدون منطق |
| EXP-CORE-011 | کاربران و نقش‌ها: گرید مجوزها | NOT_IMPLEMENTED | ۴۳ مجوز seed شده، بدون UI |
| EXP-CORE-012 | تنظیمات: ویرایش با تاریخچه | NOT_IMPLEMENTED | جدول `settings` خوانده می‌شود، UI نیست |
| EXP-CORE-013 | گزارش رویدادها (Audit Log) قابل مرور و فیلتر | NOT_IMPLEMENTED | ثبت می‌شود، دیده نمی‌شود |
| EXP-CORE-014 | جست‌وجوی سراسری | NOT_IMPLEMENTED | فقط جست‌وجوی درون‌صفحه |
| EXP-CORE-015 | تولید PDF (قرارداد، فیش، گزارش) | NOT_IMPLEMENTED | بدون موتور PDF |
| EXP-CORE-016 | داشبورد: تکمیل KPIهای باقی‌مانده | PARTIAL | ۴ از ۶ گروه |

## B. ۱۸ ماژول جدید

| شناسه | ماژول | وضعیت | وابستگی |
|---|---|---|---|
| EXP-SALES-001 | قیف فروش ۸ مرحله‌ای + کانبان + drag&drop | NOT_IMPLEMENTED | فاز ۱ |
| EXP-SALES-002 | منبع سرنخ، کمپین، رقیب، دلیل باخت | NOT_IMPLEMENTED | EXP-SALES-001 |
| EXP-SALES-003 | تبدیل کنترل‌شده Won→مشتری/قرارداد/پروژه بدون تکرار | NOT_IMPLEMENTED | EXP-SALES-001 |
| EXP-C360-001 | Customer 360: تجمیع داده واقعی همهٔ ماژول‌ها | NOT_IMPLEMENTED | فاز ۲–۵ |
| EXP-C360-002 | فرمول امتیاز سلامت **قابل تنظیم و شفاف** + نمایش منبع | NOT_IMPLEMENTED | EXP-C360-001 |
| EXP-C360-003 | LTV، شاخص ریسک، اقدام بعدی پیشنهادی | NOT_IMPLEMENTED | EXP-C360-002 |
| EXP-RESOURCE-001 | ظرفیت هفتگی/ماهانه، ساعت تخصیص‌یافته vs آزاد | NOT_IMPLEMENTED | پرسنل |
| EXP-RESOURCE-002 | اضافه‌بار/کم‌باری، تقویم مرخصی، گزارش ظرفیت دپارتمان | NOT_IMPLEMENTED | EXP-RESOURCE-001 |
| EXP-HELPDESK-001 | تیکت با شمارهٔ پیگیری، ۶ وضعیت، SLA، تشدید | NOT_IMPLEMENTED | فاز ۴ |
| EXP-HELPDESK-002 | یادداشت داخلی vs پاسخ عمومی، گزارش عملکرد | NOT_IMPLEMENTED | EXP-HELPDESK-001 |
| EXP-WORKFLOW-001 | موتور Trigger→Condition→Action بدون کد/SQL دلخواه | NOT_IMPLEMENTED | فاز ۶ |
| EXP-WORKFLOW-002 | فعال/غیرفعال، اجرای آزمایشی، تاریخچه، ضدحلقه، retry | NOT_IMPLEMENTED | EXP-WORKFLOW-001 |
| EXP-BI-001 | مرکز KPI: درآمد، هزینه، سود خالص، حاشیه، تبدیل، ریزش | NOT_IMPLEMENTED | **حسابداری (OD-41)** |
| EXP-BI-002 | مقایسه دوره، drill-down، فیلتر، خروجی Excel+PDF | NOT_IMPLEMENTED | EXP-BI-001 |
| EXP-BI-003 | «در دسترس نیست» به‌جای صفر ساختگی | NOT_IMPLEMENTED | EXP-BI-001 |
| EXP-MEETING-001 | جلسات، حاضران، صورت‌جلسه، مصوبات→وظیفه | NOT_IMPLEMENTED | فاز ۵ |
| EXP-MEETING-002 | یادآور، تقویم یکپارچه با وظایف، PDF | NOT_IMPLEMENTED | EXP-MEETING-001 |
| EXP-PROPOSAL-001 | قالب پیشنهاد به تفکیک سرویس + تحویل‌شدنی‌ها + زمان‌بندی | NOT_IMPLEMENTED | فاز ۴ |
| EXP-PROPOSAL-002 | قیمت، شرایط پرداخت، تخفیف، نسخه‌ها، تأیید، PDF | NOT_IMPLEMENTED | EXP-PROPOSAL-001 |
| EXP-PROPOSAL-003 | پیشنهاد تأییدشده → قرارداد | NOT_IMPLEMENTED | EXP-PROPOSAL-002 |
| EXP-OKR-001 | اهداف سالانه/فصلی، نتایج کلیدی، پیشرفت، تاریخچه | NOT_IMPLEMENTED | فاز ۵ |
| EXP-OKR-002 | ارزیابی نه فقط بر اساس تعداد وظیفه | NOT_IMPLEMENTED | EXP-OKR-001 |
| EXP-KNOWLEDGE-001 | مقالات/SOP با نسخه‌بندی، نویسنده، تأییدکننده | NOT_IMPLEMENTED | فاز ۵ |
| EXP-KNOWLEDGE-002 | Draft/Published/Archived، برچسب، سطح محرمانگی، تاریخ بازبینی | NOT_IMPLEMENTED | EXP-KNOWLEDGE-001 |
| EXP-QA-001 | چک‌لیست تحویل به تفکیک سرویس، بازبینی داخلی، تأیید PM+مشتری | NOT_IMPLEMENTED | فاز ۵ |
| EXP-QA-002 | نقص، اصلاح، وضعیت QA؛ پروژه بدون تأیید نهایی نمی‌شود | NOT_IMPLEMENTED | EXP-QA-001 |
| EXP-RENEWAL-001 | تاریخ پایان قرارداد، هشدار تمدید، نتیجهٔ مذاکره | NOT_IMPLEMENTED | فاز ۴ |
| EXP-RENEWAL-002 | گزارش منقضی/تمدیدشده/ازدست‌رفته | NOT_IMPLEMENTED | EXP-RENEWAL-001 |
| EXP-VENDOR-001 | تأمین‌کننده/پیمانکار، قرارداد، هزینه، فاکتور، پرداخت | NOT_IMPLEMENTED | فاز ۶ |
| EXP-VENDOR-002 | امتیازدهی و اتصال به پروژه‌ها | NOT_IMPLEMENTED | EXP-VENDOR-001 |
| EXP-ASSET-001 | تجهیزات/لایسنس نرم‌افزار، سریال، گارانتی | NOT_IMPLEMENTED | فاز ۶ |
| EXP-ASSET-002 | تخصیص به پرسنل، رسید تحویل/برگشت، تاریخچهٔ تعمیر | NOT_IMPLEMENTED | EXP-ASSET-001 |
| EXP-PORTAL-001 | پورتال مشتری با احراز هویت **جداسازی‌شده** | NOT_IMPLEMENTED | فاز ۶ |
| EXP-PORTAL-002 | پیشرفت پروژه، فایل، قرارداد، فاکتور، تیکت برای مشتری | NOT_IMPLEMENTED | EXP-PORTAL-001 |
| EXP-PORTAL-003 | IDOR-safe، noindex، بدون یادداشت داخلی | NOT_IMPLEMENTED | EXP-PORTAL-001 |
| EXP-CSAT-001 | نظرسنجی پس از تحویل، CSAT/NPS، شکایت | NOT_IMPLEMENTED | فاز ۵ |
| EXP-CSAT-002 | روند، گزارش به تفکیک سرویس/PM، تغذیهٔ Customer 360 | NOT_IMPLEMENTED | EXP-CSAT-001 |
| EXP-NOTIFICATION-001 | اطلاع‌رسانی یکپارچهٔ درون‌برنامه‌ای/ایمیل | NOT_IMPLEMENTED | فاز ۶ |
| EXP-NOTIFICATION-002 | ترجیح کاربر، علامت خوانده‌شده، گروه‌بندی، پیوند عمیق | NOT_IMPLEMENTED | EXP-NOTIFICATION-001 |
| EXP-NOTIFICATION-003 | پیامک (در صورت تأیید سرویس — OD-42) | **BLOCKED** | OD-42 |
| EXP-INTELLIGENCE-001 | OpenAI Responses API، کلید فقط سمت سرور در Vault | NOT_IMPLEMENTED | فاز ۷ |
| EXP-INTELLIGENCE-002 | انتخاب مدل در بک‌اند، بدون مدل hardcode | NOT_IMPLEMENTED | EXP-INTELLIGENCE-001 |
| EXP-INTELLIGENCE-003 | چت فارسی + function calling روی سرویس واقعی | NOT_IMPLEMENTED | فاز ۷ |
| EXP-INTELLIGENCE-004 | هر فراخوانی تابع، مجوز کاربر جاری را دوباره چک می‌کند | NOT_IMPLEMENTED | EXP-INTELLIGENCE-003 |
| EXP-INTELLIGENCE-005 | تحلیل‌گر اجرایی/پروژه/مالی/فروش + گزارش‌نویس با تأیید انسان | NOT_IMPLEMENTED | EXP-INTELLIGENCE-003 |
| EXP-INTELLIGENCE-006 | دستیار دانش با ارجاع به منبع | NOT_IMPLEMENTED | EXP-KNOWLEDGE-001 |
| EXP-INTELLIGENCE-007 | تشخیص ناهنجاری: ابتدا قاعده و آمار | NOT_IMPLEMENTED | EXP-BI-001 |
| EXP-INTELLIGENCE-008 | v1 فقط‌خواندنی + لایهٔ تأیید ۷ مرحله‌ای برای تغییر | NOT_IMPLEMENTED | EXP-INTELLIGENCE-001 |
| EXP-INTELLIGENCE-009 | سقف مصرف روزانه/ماهانه، کش، timeout، kill switch | NOT_IMPLEMENTED | EXP-INTELLIGENCE-001 |
| EXP-INTELLIGENCE-010 | هر پاسخ منبع داده/دوره/روش و «قطعی vs تخمین» را می‌گوید | NOT_IMPLEMENTED | EXP-INTELLIGENCE-003 |
| EXP-INTELLIGENCE-011 | کلید هرگز در فرانت/Git/لاگ/plaintext | NOT_IMPLEMENTED | EXP-INTELLIGENCE-001 |

## C. Vira Workspace سازمانی

| شناسه | نیازمندی | وضعیت | شاهد |
|---|---|---|---|
| EXP-WS-001 | ورک‌اسپیس به تفکیک دپارتمان/پروژه/مشتری/قرارداد/HR/حسابداری | NOT_IMPLEMENTED | سند طراحی موجود |
| EXP-WS-002 | درخت پوشه، نمای شبکه‌ای/فهرست، drag&drop | NOT_IMPLEMENTED | — |
| EXP-WS-003 | آپلود تکه‌ای و قابل ادامه (chunked+resumable) | NOT_IMPLEMENTED | — |
| EXP-WS-004 | نسخه‌بندی و بازگردانی | NOT_IMPLEMENTED | — |
| EXP-WS-005 | قفل + حل تعارض | NOT_IMPLEMENTED | — |
| EXP-WS-006 | ۱۸ مجوز دانه‌ریز فایل | NOT_IMPLEMENTED | — |
| EXP-WS-007 | اشتراک با کاربر/گروه/نقش/دپارتمان/پروژه/مشتری | NOT_IMPLEMENTED | — |
| EXP-WS-008 | ارث‌بری مجوز + شکستن ارث | NOT_IMPLEMENTED | — |
| EXP-WS-009 | نگهداری (retention)، سهمیه (quota)، Audit | NOT_IMPLEMENTED | — |
| EXP-WS-010 | لینک عمومی پیش‌فرض خاموش؛ انقضا+رمز+سقف دانلود+لغو | NOT_IMPLEMENTED | — |
| EXP-WS-011 | تست امنیتی: path traversal، IDOR، MIME spoofing، zip bomb | NOT_IMPLEMENTED | — |
| EXP-WS-012 | قلاب ONLYOFFICE/Collabora برای F3 (رزرو، پیاده‌سازی نشده) | **BLOCKED** | تصمیم F3 |

## D. تحویل

| شناسه | نیازمندی | وضعیت |
|---|---|---|
| EXP-DEL-001 | ZIP سورس + SHA-256 + manifest | VERIFIED |
| EXP-DEL-002 | ZIP تولیدی با `vendor/` + دارایی‌های ساخته‌شده | VERIFIED |
| EXP-DEL-003 | تأیید ZIP استخراج‌شده (نه پوشهٔ کار) | VERIFIED |
| EXP-DEL-004 | ماتریس نیازمندی نهایی | VERIFIED |
| EXP-DEL-005 | گزارش تست با تفکیک Total/Executed/Passed/Failed/Skipped | VERIFIED |
| EXP-DEL-006 | گزارش امنیتی | PARTIAL |
| EXP-DEL-007 | ERD توسعه‌یافته | NOT_IMPLEMENTED |
| EXP-DEL-008 | سند معماری | NOT_IMPLEMENTED |
| EXP-DEL-009 | راهنمای استقرار و cPanel | PARTIAL |
| EXP-DEL-010 | راهنمای پشتیبان‌گیری/بازیابی | NOT_IMPLEMENTED |
| EXP-DEL-011 | راهنمای پیکربندی AI | NOT_IMPLEMENTED |
| EXP-DEL-012 | راهنمای کاربر و مدیر | NOT_IMPLEMENTED |

---

## تغییرات این دوره

**۱۴۰۵/۰۹/۰۱ — فاز ۰**
- ۸ نیازمندی `EXP-CORE-001..008` به VERIFIED رسید.
- نقص DEF-01 (کارتابل غیرقابل بستن) و DEF-02 (یادداشت بدون UI) رفع شد.
- نقص DEF-03 (تاریخ جلالی نامعتبر → NULL بی‌صدا) کشف و رفع شد.
- ۴ نیازمندی BLOCKED با شناسهٔ تصمیم: OD-41 (حسابداری)، OD-42 (پیامک)، OD-43 (مدل AI)، F3.

**هیچ نیازمندی‌ای حذف نشد.** دامنهٔ کار کوچک نشده است.
