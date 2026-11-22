# فاز ۱ — بسته تحلیل و طراحی VIRA CRM

> **وضعیت:** 🟡 خروجی فاز ۱ آماده است — **توقف برای تأیید کارفرما** (گیت فاز ۱).
> تا تأیید صریح، توسعه اصلی آغاز نمی‌شود (بخش ۲۹ بریف).

## اقلام تحویلی فاز ۱ (آنچه در این پوشه است)

| # | قلم | فایل |
|---|---|---|
| 1 | گزارش کامل تحلیل سند | [`14-analysis-report.md`](14-analysis-report.md) |
| 2 | فهرست نیازمندی‌های شناسه‌گذاری‌شده (۸۱ مورد) | [`../requirements/requirements.catalog.yaml`](../requirements/requirements.catalog.yaml) |
| 3 | Requirements Traceability Matrix (اولیه) | [`05-traceability-matrix.md`](05-traceability-matrix.md) + [CSV](05-traceability-matrix.csv) |
| 4 | گزارش سازگاری هاست | [`01-host-compliance-report.md`](01-host-compliance-report.md) |
| 5 | گزارش محیط توسعه (اجرای واقعی) | [`00-dev-environment-validation.md`](00-dev-environment-validation.md) |
| 6 | معماری پیشنهادی + نمودار | [`02-architecture.md`](02-architecture.md) · [`../diagrams/architecture.png`](../diagrams/architecture.png) |
| 7 | ERD دیتابیس | [`../diagrams/erd.png`](../diagrams/erd.png) · منبع [`../database/erd.dot`](../database/erd.dot) |
| 8 | فهرست جداول و فیلدها | [`03-database-design.md`](03-database-design.md) |
| 9 | گردش‌کارهای اصلی (W1–W10) | [`04-workflows.md`](04-workflows.md) |
| 10 | ساختار ماژول‌ها | [`02-architecture.md` §3](02-architecture.md) |
| 11 | طرح UI/UX + Design System | [`09-uiux-design.md`](09-uiux-design.md) |
| 12 | وایرفریم صفحات کلیدی | [`10-ui-wireframes.md`](10-ui-wireframes.md) |
| 13 | برنامه امنیت | [`08-security-plan.md`](08-security-plan.md) |
| 14 | برنامه تست | [`12-test-strategy.md`](12-test-strategy.md) · [`11-test-plan-counts.md`](11-test-plan-counts.md) |
| 15 | کاتالوگ تست (۱۱۹ سناریو) | [`../requirements/tests.catalog.yaml`](../requirements/tests.catalog.yaml) |
| 16 | فهرست ابهامات و تصمیم‌های کارفرما | [`06-open-questions.md`](06-open-questions.md) |
| 17 | برنامه اجرایی فازها | [`13-roadmap.md`](13-roadmap.md) |
| 18 | داشبورد وضعیت واقعی | [`07-status-dashboard.md`](07-status-dashboard.md) |
| 19 | ADR معماری | [`01-architecture-decision-record.md`](01-architecture-decision-record.md) |
| 20 | گزارش نهایی فاز ۰+۱ | [`15-phase-report.md`](15-phase-report.md) |
| 21 | شواهد اجرایی | [`evidence/`](evidence/) |
| 22 | ابزار بررسی هاست و اجرای PHP | [`../tools/`](../tools/) |
| 23 | گزارش اعتبارسنجی زنجیره ابزار و مستندات | [`../qa-toolchain-validation.md`](../qa-toolchain-validation.md) |

## وضعیت شفاف

| مورد | وضعیت |
|---|---|
| تحلیل بریف و استخراج الزامات | ✅ انجام شد (۸۱ الزام) |
| طراحی معماری/دیتابیس/امنیت/UI/تست | ✅ انجام شد |
| بررسی واقعی هاست | ⛔ **BLOCKED** — نیازمند اجرای اسکریپت توسط کارفرما |
| نصب/اجرای Laravel | ⛔ **انجام نشده** — sandbox بدون Composer/PHP بومی |
| دریافت سند اصلی ۱۵ بخشی | ⛔ **در دسترس نیست** — تحلیل بر مبنای بریف |
| پیاده‌سازی ماژول‌ها | ⛔ **شروع نشده** — گیت تأیید |

## اجرای مجدد تولید مستندات

```bash
# بازتولید RTM / داشبورد وضعیت / شمارش تست‌ها از کاتالوگ‌ها
python3 docs/tools/build_phase1_docs.py

# اجرای واقعی بررسی‌های PHP (نیازمند @php-wasm/node)
node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php
node docs/tools/php-cli.mjs docs/src/jalali-smoke-test.php
```
