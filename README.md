# VIRA CRM

نرم‌افزار CRM اختصاصی آژانس دیجیتال مارکتینگ ویرا — **Standalone، مستقل از وردپرس**.

| مورد | مقدار |
|---|---|
| وضعیت فعلی | 🟡 **فاز ۰+۱ تحویل شد — منتظر تأیید کارفرما** |
| نسخه | `0.0.0-design` |
| پشته فنی (مصوب مشروط) | PHP 8.2+ · Laravel 12 · MySQL 8/MariaDB 10.6+ · Blade + Alpine.js |
| ساب‌دامین هدف | `crm.viraagency.co` |
| مستندات | [`docs/phase-1/README.md`](docs/phase-1/README.md) |

## شروع سریع مستندات

1. **گزارش فاز:** [`docs/phase-1/15-phase-report.md`](docs/phase-1/15-phase-report.md)
2. **بسته فاز ۱:** [`docs/phase-1/README.md`](docs/phase-1/README.md)
3. **ماتریس ردیابی:** [`docs/phase-1/05-traceability-matrix.md`](docs/phase-1/05-traceability-matrix.md)
4. **ابهامات/تصمیم‌ها:** [`docs/phase-1/06-open-questions.md`](docs/phase-1/06-open-questions.md)
5. **وضعیت واقعی:** [`docs/phase-1/07-status-dashboard.md`](docs/phase-1/07-status-dashboard.md)

## ساختار مخزن

```text
docs/
├── requirements/     کاتالوگ ماشین‌خوان نیازمندی‌ها و تست‌ها (منبع واحد حقیقت)
├── phase-0/          بررسی محیط، توپولوژی استقرار
├── phase-1/          تحلیل، معماری، دیتابیس، امنیت، UI/UX، تست، ابهامات، گزارش
├── database/         منبع DOT نمودار ERD
├── diagrams/         architecture.svg/png · erd.svg/png
├── src/              اسکریپت‌های بررسی اجراشده با PHP 8.3 واقعی
├── tools/            ابزار بررسی هاست، اجرای PHP در WASM، تولید مستندات
└── assets/fonts/     Vazirmatn (OFL) برای UI و PDF
```

## قواعد پروژه

- ❌ افزونه وردپرس، ❌ دیتابیس وردپرس، ❌ کاربران وردپرس — **ممنوع**.
- ✅ تاریخ شمسی در UI، ذخیره استاندارد در DB.
- ✅ هیچ ادعای تست بدون اجرای واقعی و شاهد.
- ✅ هیچ رمز/کلید واقعی در مخزن.

## بازتولید مستندات

```bash
# ۱) بازتولید RTM / داشبورد وضعیت / شمارش تست‌ها (نیازمند PyYAML)
python3 docs/tools/build_phase1_docs.py

# ۲) اجرای واقعی بررسی‌های PHP (نیازمند بسته @php-wasm/node — خارج از مخزن)
npm i @php-wasm/node@3.1.65
node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php
node docs/tools/php-cli.mjs docs/src/jalali-smoke-test.php
```

> خروجی این دو اجرا در `docs/phase-1/evidence/*.json` ذخیره می‌شود (شواهد فاز ۱).
