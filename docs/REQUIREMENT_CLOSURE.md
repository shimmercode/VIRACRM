# Vira CRM — Requirement Closure (بستن الزامات)

> **Generated.** `node tools/generate-requirement-closure.mjs` joins
> `docs/requirements/requirements.catalog.yaml` (the requirement text) with
> `tools/traceability-status.json` (status and evidence). Do not edit by hand.
>
> Every requirement below is listed with its **actual text from the binding
> 15-section document**, not a paraphrase. Requirements that are not VERIFIED
> carry an explicit gap and a concrete remedy.
>
> **Nothing here is claimed as done that was not actually executed.** Where an
> environment could not prove something, the status says BLOCKED and names what
> was missing.

## Summary

| Status | Count |
|---|---:|
| `VERIFIED` | 63 |
| `PARTIAL` | 13 |
| `NOT_VERIFIED` | 1 |
| `NOT_IMPLEMENTED` | 15 |
| `BLOCKED` | 4 |
| **Total tracked** | **96** |

In scope of the binding 15-section document: **84**.
Outside it (the §16 file-workspace set, which the client document does not
contain): **12** — tracked, but see the last section.

Weighted completion (catalog weights): **259/393** =
**65.9%** of contractual weight
is VERIFIED.

---

## 1. Requirements not yet VERIFIED, ordered by the document's sections

These are the ones that matter for acceptance. Each entry states what exists,
what is missing, and what to do next.

### REQ-01.03 — فایل‌های حساس خارج از Document Root

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 4 · type `` |
| **Source** | §۱ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> فایل‌های برنامه و داده‌های حساس خارج از مسیر عمومی وب؛ فقط public قابل دسترس. در صورت محدودیت هاست، امن‌ترین معماری سازگار.

**Where it stands:** storage/ outside root

**Evidence:** public/ uploads not yet used

**Remaining gap:** storage/ and .arena/ sit outside public/, and vira:backup now refuses any --path under the document root (backup-hardening BK-03/05).

**Remedy:** Nothing code-side. Confirm on the real host that the web server's Document Root is public/ and not the repository root; that is a host setting, not a Laravel one.

### REQ-01.04 — تاب‌آوری CRM در قطعی سایت اصلی

| | |
|---|---|
| **Status** | `NOT_VERIFIED` |
| **Priority** | P1 · weight 3 · type `` |
| **Source** | §۱ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> قطعی/به‌روزرسانی وب‌سایت وردپرسی نباید CRM را مختل کند؛ اتصال آینده فقط از طریق API مستقل.

**Where it stands:** no dependency on main site found

**Evidence:** not exercised

**Remaining gap:** No code path calls the WordPress site: grep finds no outbound HTTP to the main domain, and the CRM has its own database and session.

**Remedy:** Add a check that fails if any config value or route points at the WordPress host, then simulate a WordPress outage. Until a test exists this stays NOT_VERIFIED rather than being claimed.

### REQ-02.01 — شاخص‌های داشبورد

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P1 · weight 4 · type `` |
| **Source** | §۲ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> تعداد مشتریان، قراردادهای فعال، قراردادهای نزدیک انقضا، پروژه‌های فعال، کارهای سررسیدشده، فاکتورهای موعددار، گزارش‌های موعددار، یادداشت‌های باز، موارد عقب‌افتاده، اقدامات انجام‌شده.

**Where it stands:** DashboardController + dashboard.blade

**Evidence:** 4 of 6 KPI groups

**Remaining gap:** The dashboard renders count tiles for customers, active contracts and expiring contracts, each linking to its list, plus the کارتابل groups.

**Remedy:** §2-1 names ten indicators. Add the missing ones (invoiced items, due reports, open notes, overdue items, completed actions) as tiles or as کارتابل sections, each reconciled against SQL the way work-item-report.mjs does.

### REQ-03.05 — جلوگیری از ثبت مشتری تکراری

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 4 · type `` |
| **Source** | §۳ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> تشخیص تکراری بر پایه کد ملی/شناسه ملی، شماره تماس، دامنه وب‌سایت با هشدار پیش از ثبت.

**Where it stands:** duplicate detection partial

**Evidence:** no domain-level uniqueness proof

**Remaining gap:** StoreCustomerRequest validates national_id for length (10) and checksum digit, and rejects an invalid one.

**Remedy:** There is no uniqueness rule, so two customers can share a national_id. Add `unique:customers,national_id` (ignoring soft-deleted rows) plus the Persian error message, and a check that a second create with the same national_id is refused with a 302 + flashed error.

### REQ-03.08 — یک‌بار ثبت اطلاعات مشتری

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 4 · type `` |
| **Source** | §۳ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> اطلاعات مشتری فقط یک بار ثبت و در قرارداد/پروژه/فاکتور ارجاع داده شود (Single Source of Truth).

**Where it stands:** single customer record

**Evidence:** credentials not joined

**Remaining gap:** One customers row per customer; the detail page joins contracts, projects, notes and credentials.

**Remedy:** Prove it end to end: create a customer once, attach a contract, a project, a note and a credential, then assert all five surfaces show the same customer_id and that no second row was created.

### REQ-06.07 — هشدار محرمانگی روی فایل حاوی رمز

| | |
|---|---|
| **Status** | `NOT_IMPLEMENTED` |
| **Priority** | P1 · weight 3 · type `` |
| **Source** | §۶ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> فایل حاوی رمزها باید با هشدار محرمانگی و ترجیحاً رمز PDF یا روش امن تحویل ارائه شود.

**Where it stands:** no files

**Evidence:** —

**Remaining gap:** No file download exists, so there is no file to warn about.

**Remedy:** Depends on REQ-10.06 (uploads). When downloads land, add a confidentiality banner on any credential-bearing document and refuse direct URL access without a permission check.

### REQ-10.05 — الزامات امنیتی پایه

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 5 · type `` |
| **Source** | §۱۰ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> Password Hashing، Session امن، CSRF، XSS، SQLi، Validation سمت سرور، Authorization همه عملیات حساس، Rate Limiting ورود، مدیریت خطا بدون Stack Trace، Secure Cookie، HTTPS.

**Where it stands:** all code-side controls implemented and asserted; HTTPS itself is deployment-side

**Evidence:** security-headers.mjs 16/16 now asserts the controls over real HTTP: X-Content-Type-Options nosniff, X-Frame-Options DENY, Referrer-Policy same-origin, X-Permitted-Cross-Domain-Policies none, Permissions-Policy with all four device APIs disabled, and a CSP carrying all seven directives including frame-ancestors none and a script-src without unsafe-inline. Session cookie proven HttpOnly, SameSite=lax, Path=/, and Secure when SESSION_SECURE_COOKIE=true. That suite also found and fixed a real bug: SecurityHeaders guarded Cache-Control with `if (! has(...))`, which was always false because Symfony sets a default, so the intended no-store never shipped — pages were no-cache (revalidate) but still storeable. config/session.php now defaults Secure to true when APP_ENV=production and the variable is absent, verified by booting the real config both ways (local => false, production => true). Remaining gap: HTTPS termination itself cannot be exercised in this sandbox, so the transport half of §10-5 stays unproven here; every control that depends on it (TRUSTED_PROXIES, Secure cookie) is implemented and asserted.

**Remaining gap:** Every code-side control is implemented and asserted: security-headers.mjs 16/16 covers the six headers, the seven CSP directives, and HttpOnly/SameSite/Path/Secure on the session cookie; bcrypt, CSRF, login throttling, policies and route permissions have their own suites.

**Remedy:** HTTPS termination is deployment-side and cannot be exercised in this sandbox, so the transport half of §10-5 stays unproven here. On the real host, assert the redirect to HTTPS and that Secure is set with the variable unset in production (config/session.php now defaults it on).

### REQ-10.06 — امنیت فایل‌های آپلودی و IDOR

| | |
|---|---|
| **Status** | `NOT_IMPLEMENTED` |
| **Priority** | P0 · weight 4 · type `` |
| **Source** | §۱۰ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> کنترل MIME/پسوند/حجم، نام‌گذاری امن، Storage خصوصی، جلوگیری از دسترسی مستقیم، جلوگیری از IDOR در همه مسیرها.

**Where it stands:** no uploads

**Evidence:** —

**Remaining gap:** No upload subsystem exists, so there is no MIME/extension/size gate, no private storage and no IDOR surface to test.

**Remedy:** Build it as one slice: validated upload (MIME + extension + size allowlist), opaque random filenames, storage outside public/, a permission-checked download controller, and a check that guessing another tenant's file id returns 403 rather than 404-with-content. This unblocks REQ-06.07 and the file half of REQ-12.09.

### REQ-11.05 — حالت‌های Loading/Empty/Success/Error و اعتبارسنجی

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P1 · weight 3 · type `` |
| **Source** | §۱۱ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> پیام خطای قابل فهم، تأیید عملیات حساس، فرم‌های کوتاه/مرحله‌ای، ناوبری واضح.

**Where it stands:** empty/error states exist

**Evidence:** loading/success states partial

**Remaining gap:** Empty and error states render; server-side validation returns Persian messages; sensitive actions ask for confirmation.

**Remedy:** Add explicit loading and success affordances and assert them: a check that a destructive form carries a confirm, that a failed submit re-renders with the field error, and that an empty list shows the empty state rather than a blank table.

### REQ-13.02 — بازبینی و تأیید هاست مقصد

| | |
|---|---|
| **Status** | `BLOCKED` |
| **Priority** | P1 · weight 3 · type `` |
| **Source** | §۱۳ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> پیش از تثبیت Laravel/نسخه PHP، بررسی واقعی هاست (PHP، Extension، DB، Composer، Artisan، Cron، Memory، Document Root، SSL، Queue، Storage) — در این محیط دسترسی هاست وجود ندارد.

**Where it stands:** 14/21 host checks unknown

**Evidence:** —

**Remaining gap:** SMS sending requires an external gateway that is unreachable from this sandbox.

**Remedy:** Pick a gateway, put its credentials in .env, and add a check that a failed send is logged and retried rather than silently dropped. Until then the code path cannot be exercised at all.

### REQ-15.01 — تنظیمات مدیریتی سامانه

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P1 · weight 4 · type `` |
| **Source** | §۱۵ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> هشدار قرارداد، فاصله یادآوری‌ها، انواع قرارداد/پروژه، پکیج سئو، دسترسی‌های الزامی، روش حقوق، مبالغ پروژه، قالب‌های PDF، نقش‌ها و مجوزها، تنظیمات عمومی.

**Where it stands:** Six of the seven §15 configuration areas now have a screen, and each is proven by an effect somewhere other than the screen that made the change: contract types (/settings/contract-types), project types (/settings/project-types), SEO packages (/settings/seo-packages), mandatory accesses (/settings/access-requirements), payroll rules (/settings/payroll-rules) and roles with their permission matrix (/settings/roles). The tables already existed and most were already read by real code; what was missing was any way to change them, so each list was frozen at whatever the seeder wrote. No new configuration structure was created for any of them. "Project amounts" is not a separate area — §15 puts it in the same bullet as payroll calculation («نحوه محاسبه حقوق و مبالغ مرتبط با تعداد پروژه‌ها»), and those amounts already exist as payroll_items rows carrying a project_id plus payslips.projects_total. The one open §15 bullet is the PDF template: contract templates are editable, but the project, access and payslip PDFs still have fixed layouts.

**Evidence:** tools/checks/admin-config.mjs 24/24. Contract types: a type created here is offered by the contract form, a duplicate code is a field error, retiring it removes it from the form. Project types: a new type is selectable AND a project is really created with it (the validator and the model-level HasDomainConstraints guard both read project_types now, so they cannot disagree); retiring a type stops new projects of that kind; the code of a type projects use cannot change. Mandatory accesses: adding a blocking requirement for DESIGN immediately blocks activating a design project — proven on a type other than seo, because all three seeded requirements are for seo and a hard-coded list would have passed for the wrong reason; removing it lifts the block; "blocks" without "required" is refused since blockingFor() needs both. Payroll: a new period is stamped with the approved rule, an unapproved rule is never used even when it is the only one left (storePeriod previously filtered on is_active alone), a method outside PayrollRule::METHODS is refused so no formula can be introduced against OD-16, and approving needs payroll.approve rather than settings.manage. Roles: a granted permission really reaches a user and a missing one really returns 403; a user cannot hand out a permission they do not hold — removing that guard lets an editor grant themselves payroll.approve and the suite drops to 23/24; a role people are attached to cannot be deleted; the screen is closed to anyone without users.manage, which no seeded role holds. route-smoke covers all 30 new routes (152 routes, 0 skipped). FinalizationGate::message() no longer says «پروژه سئو», because which types are gated is data.

**Remaining gap:** Five configuration screens exist and are proven to change behaviour (admin-config 24/24): users/roles, contracts, financial, SEO and tax.

**Remedy:** §15 lists more knobs than those five screens expose. Enumerate the remaining ones from the catalog and add a screen each, with a check per knob that changing it moves the behaviour it controls.

### REQ-15.03 — Installer امن

| | |
|---|---|
| **Status** | `NOT_IMPLEMENTED` |
| **Priority** | P1 · weight 4 · type `` |
| **Source** | §۱۵ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> بررسی اتصال DB، Migration، تنظیمات اولیه، ساخت مدیر اصلی با رمز امن، ثبت نسخه، غیرفعال‌سازی پس از نصب.

**Where it stands:** no installer

**Evidence:** —

**Remaining gap:** No web installer exists; setup is `composer install` + `migrate --seed` + `.env`.

**Remedy:** Build an installer that runs only when APP_KEY is unset, refuses to run twice, creates the first admin with a forced password change, and is not routable afterwards. Add a check that a second visit returns 403.

### REQ-15.04 — مهاجرت و نسخه‌بندی

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P1 · weight 3 · type `` |
| **Source** | §۱۵ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> شماره نسخه، Migration قابل ردیابی، ارتقای بدون حذف داده، تست ارتقا، Changelog.

**Where it stands:** 14 migrations, reversible

**Evidence:** no versioning policy doc

**Remaining gap:** Sixteen migration files are present and migration-safety proves up/down/re-up on a disposable database.

**Remedy:** The shipped database/database.sqlite has an empty migrations table, so it cannot report its own version. Ship an empty database plus migrations instead of a populated file, and add a check that `migrate:status` reports every file as ran.

### REQ-15.05 — تنظیمات محیطی (بدون Hardcode)

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P2 · weight 3 · type `` |
| **Source** | §۱۵ |
| **Contractual importance** | high value, not blocking |

**What the document asks:**

> تمام تنظیمات محیط/دیتابیس/کلیدها از .env؛ .env خارج از وب و Git؛ فایل نمونه بدون مقدار محرمانه.

**Where it stands:** .env driven

**Evidence:** no hardcode audit

**Remaining gap:** No hard-coded secrets: keys come from .env, and .env.example documents each one. VIRA_BACKUP_KEY, VIRA_CREDENTIAL_KEY, APP_KEY, SESSION_SECURE_COOKIE and TRUSTED_PROXIES are all env-driven.

**Remedy:** Add a check that fails if a secret-looking literal appears in app/ or config/, and verify the repository contains no real values (a secret scan is in the acceptance evidence).

### REQ-15.06 — بسته مستندات فنی و کاربری

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P2 · weight 4 · type `` |
| **Source** | §۱۵ |
| **Contractual importance** | high value, not blocking |

**What the document asks:**

> Architecture، ERD، Schema، Installation، Deployment، Env، Backup/Restore، Security، User/Admin Guide، Test Report، RTM، Changelog، Known Issues، Migration.

**Where it stands:** docs/ extensive

**Evidence:** user manual missing

**Remaining gap:** Developer documentation is extensive (deployment, backup architecture, schema, security report, traceability).

**Remedy:** There is no *end-user* manual. Write one per module — customer, contract, project, payroll, reports, settings — with screenshots from the UI audit, and add a docs-integrity check that each module has a page.

### REQ-09.06 — خروجی Excel تکمیلی

| | |
|---|---|
| **Status** | `NOT_IMPLEMENTED` |
| **Priority** | P2 · weight 3 · type `` |
| **Source** | §— |
| **Contractual importance** | high value, not blocking |

**What the document asks:**

> گزارش‌های منتخب در XLSX واقعی: اعداد مالی Numeric، شناسه‌ها Text، حفظ صفر ابتدایی، عنوان فارسی، رعایت دسترسی و Audit؛ نه جایگزین خروجی‌های الزامی.

**Where it stands:** no Excel export

**Evidence:** —

**Remaining gap:** No Excel export exists.

**Remedy:** Install an Excel writer (maatwebsite/excel or phpoffice/phpspreadsheet) and add the same export endpoints that already produce CSV/PDF, with a check that the response Content-Type and extension are correct. Marked §9-6 "تکمیلی" in the catalog, so it is P2.

### REQ-12.05 — استقلال یادآوری فاکتور و گزارش + چند دوره

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 5 · type `` |
| **Source** | §۵-۲/۱۲ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> انجام فاکتور یادآوری گزارش را نبندد و برعکس؛ هر پروژه چند نوبت فاکتور/گزارش با رکورد مستقل در دوره‌های مختلف.

**Where it stands:** ReminderSchedule unique_key design

**Evidence:** not wired

**Remaining gap:** Invoice and report reminders are computed from their own dates, independently of the contract date.

**Remedy:** Prove multi-period independence with a check: two invoices on one contract with different issue dates must produce two distinct reminders, and changing one date must move only that reminder.

### REQ-12.06 — تغییر تاریخ: بازمحاسبه بدون تکرار، حفظ سوابق

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P0 · weight 5 · type `` |
| **Source** | §۵-۲/۱۲ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> موعد قبلی مبنای یادآوری نباشد؛ یادآوری جدید بر اساس تاریخ جدید؛ بدون اعلان تکراری؛ حفظ سوابق تغییر و وضعیت انجام قبلی.

**Where it stands:** reschedule logic designed

**Evidence:** not wired

**Remaining gap:** Dates are stored and re-derived rather than cached, so a date change recalculates the state.

**Remedy:** Add a check that changing a contract end date moves the expiring bucket without creating a duplicate reminder, and that the previous state is still visible in the audit log.

### REQ-12.07 — زمان‌بندی واقعی، Idempotent و قابل مشاهده

| | |
|---|---|
| **Status** | `BLOCKED` |
| **Priority** | P0 · weight 5 · type `` |
| **Source** | §۵-۲/۱۲ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> Cron/Scheduler مستقل از بازدید کاربر؛ مقاوم در برابر اجرای چندباره؛ شناسه یکتا و پردازش Idempotent؛ ثبت وضعیت Job و خطاهای Scheduler.

**Where it stands:** H-15 host cron unavailable

**Evidence:** no scheduler wired

**Remaining gap:** The scheduler entry and the backup command exist and are idempotent, but no cron daemon is present in this environment (`command -v cron crond crontab` returns nothing).

**Remedy:** On a host with cron: install the schedule entry, run two cycles, and assert the second run neither duplicates nor prunes the first unexpectedly. Record the crontab line in the deployment guide as the evidence.

### REQ-12.08 — تعریف روز تقویمی و Timezone

| | |
|---|---|
| **Status** | `BLOCKED` |
| **Priority** | P0 · weight 4 · type `` |
| **Source** | §۵-۲/۱۲ |
| **Contractual importance** | delivery-blocking |

**What the document asks:**

> محاسبه دو روز قبل بر مبنای روز تقویمی (نه ۴۸ ساعت شناور)؛ Timezone مشخص و قابل تنظیم؛ مرز روز و ساعت اجرا نیازمند تصمیم کارفرما (ابهام ثبت‌شده).

**Where it stands:** OD-07 timezone decision pending

**Evidence:** —

**Remaining gap:** config/app.php:86 sets `timezone => UTC` and the Jalali calendar suite passes, so dates are consistent — but the *business* day boundary for an Iranian client was never confirmed with them.

**Remedy:** Ask the client whether the working day ends at midnight Tehran time (UTC+3:30). If yes, set APP_TIMEZONE and make the reminder buckets use it; add a check for a record dated 23:30 Tehran that must fall in tomorrow's bucket.

### REQ-12.09 — ثبت مشخصات و فایل فاکتور پس از ارسال

| | |
|---|---|
| **Status** | `PARTIAL` |
| **Priority** | P1 · weight 4 · type `` |
| **Source** | §۵-۲ |
| **Contractual importance** | required for version 1 |

**What the document asks:**

> پس از ارسال فاکتور، مدیر بتواند شماره فاکتور، مبلغ، تاریخ صدور و فایل فاکتور را ثبت کند.

**Where it stands:** پس از ارسال فاکتور، شماره، مبلغ و تاریخ صدور ثبت می‌شود؛ فایل فاکتور نه.

**Evidence:** The traceability summary previously read "invoice PDF pending mPDF slice", which misstated the requirement: §12-9 asks for the invoice number, amount, issue date AND the invoice file to be recorded after sending. It is not a PDF-generation requirement — §9-7 names four PDFs (contract, project dossier, access information, payslip) and an invoice is not among them. Three of the four are implemented: POST /work-items/{id}/complete requires invoice_number and invoice_amount for an invoice item, validates the amount through Money::parse rather than silently storing 0, and stores invoice_issue_date. The fourth is not: work_items.document_attachment_id is fillable but nothing in app/ ever writes it, because recording a file needs the upload subsystem (REQ-10.06), which is not built. Marked PARTIAL rather than NOT_IMPLEMENTED because the previous status understated what works, and rather than VERIFIED because the file half genuinely does not.

**Remaining gap:** Three of the four recorded fields work: POST /work-items/{id}/complete requires invoice_number and invoice_amount, parses the amount through Money::parse, and stores invoice_issue_date.

**Remedy:** The fourth — the invoice *file* — needs REQ-10.06. WorkItem::$fillable already carries document_attachment_id; nothing writes it. Wire it when uploads land and extend the completion check to assert the attachment resolves.

---

## 2. VERIFIED requirements

Each of these has automated evidence in `tools/checks/` or `tests/` and can be
re-run with one command. Evidence strings are reproduced verbatim from
`tools/traceability-status.json`.

| Requirement | Title | §  | Priority | Evidence |
|---|---|---|---|---|
| `REQ-01.01` | استقلال کامل نرم‌افزار از وردپرس | ۱ | P0 | — |
| `REQ-01.02` | دیتابیس مستقل و ساب‌دامین مستقل | ۱ | P0 | — |
| `REQ-02.02` | کارتابل تفکیک‌شده | ۲ | P1 | tools/checks/work-item-flow.mjs; tools/checks/run-all.mjs |
| `REQ-02.03` | ناوبری مستقیم از اعلان به رکورد | ۲ | P1 | tools/checks/phase4-integration.mjs P4-G3: every alert on the dashboard is a link to the record it warns about, not a count — کارتابل tasks → /work-it… |
| `REQ-02.04` | هشدار انقضای قرارداد با آستانه قابل تنظیم | ۲ | P1 | — |
| `REQ-02.05` | داشبورد خلوت و کاربردی | ۲ | P1 | — |
| `REQ-03.01` | پرونده مشتری حقیقی | ۳ | P1 | — |
| `REQ-03.02` | پرونده مشتری حقوقی | ۳ | P1 | — |
| `REQ-03.03` | فرم پویا بر اساس نوع شخص | ۳ | P1 | — |
| `REQ-03.04` | چرخه عمر مشتری | ۳ | P1 | — |
| `REQ-03.06` | جستجوی مشتری بر اساس نام/تماس/شناسه/دامنه | ۳ | P1 | — |
| `REQ-03.07` | پرونده مرکزی مشتری 360° | ۳ | P1 | project rows were unlinked until this round; fixed in 049088d |
| `REQ-04.01` | مدل داده قرارداد | ۴ | P1 | — |
| `REQ-04.02` | وضعیت‌های قرارداد | ۴ | P1 | — |
| `REQ-04.03` | چند قرارداد برای یک مشتری + اتصال به پرونده | ۴ | P1 | — |
| `REQ-04.04` | بانک قالب قرارداد | ۴ | P1 | — |
| `REQ-04.05` | موتور Template قرارداد | ۴ | P1 | — |
| `REQ-04.06` | ویرایش متن قرارداد پیش از نهایی‌سازی | ۴ | P1 | — |
| `REQ-04.07` | تثبیت نسخه نهایی قرارداد | ۴ | P0 | — |
| `REQ-04.08` | تاریخچه و نسخه‌های قرارداد | ۴ | P1 | — |
| `REQ-04.09` | جستجو و فیلتر قرارداد | ۴ | P1 | — |
| `REQ-04.10` | ثبت تمدید قرارداد | ۴ | P1 | — |
| `REQ-06.01` | مدیریت دسترسی‌های پروژه | ۶ | P1 | — |
| `REQ-06.02` | فهرست دسترسی‌های اجباری سئو | ۶ | P0 | list is data-driven per §15; the three seeded kinds need client confirmation |
| `REQ-06.03` | رمزنگاری رمزها (AES-256-GCM) | ۶ | P0 | — |
| `REQ-06.04` | Audit مشاهده و دریافت خروجی محرمانه | ۶ | P0 | the missing audit view was built in the security stage |
| `REQ-06.05` | PDF محرمانه دسترسی‌ها | ۶ | P0 | residual is REQ-06.07 (warning band on a stored file), tracked separately |
| `REQ-06.06` | جداسازی اطلاعات عمومی و محرمانه پروژه | ۶ | P1 | — |
| `REQ-07.01` | پرونده پرسنل | ۷ | P1 | — |
| `REQ-07.02` | قواعد قابل تنظیم حقوق (بدون فرمول خودساخته) | ۷ | P0 | — |
| `REQ-07.03` | فیش حقوقی ماهانه (PDF فارسی RTL) | ۷ | P1 | — |
| `REQ-07.04` | تاریخچه و تثبیت فیش‌ها | ۷ | P0 | — |
| `REQ-07.05` | دسترسی مستقل اطلاعات حقوقی | ۷ | P0 | — |
| `REQ-08.01` | مدل یادداشت | ۸ | P1 | — |
| `REQ-08.02` | چرخه عمر یادداشت | ۸ | P1 | — |
| `REQ-09.01` | جستجوی سراسری | ۹ | P1 | — |
| `REQ-09.02` | فیلترهای قرارداد و پروژه | ۹ | P1 | — |
| `REQ-09.03` | گزارش فاکتور و گزارش | ۹ | P1 | — |
| `REQ-09.04` | گزارش قراردادها و پرسنل | ۹ | P1 | tools/checks/work-item-report.mjs 13/13. The report classifies every scheduled action (work_items — the invoices and reports §9-4 names) into six mutu… |
| `REQ-09.05` | خروجی PDF الزامی | ۹ | P1 | — |
| `REQ-09.07` | گزارش تعداد پروژه‌های فعال هر کارشناس | ۹ | P1 | — |
| `REQ-10.01` | نسخه اول: فقط مدیر اصلی | ۱۰ | P1 | — |
| `REQ-10.02` | نقش‌های نمونه آینده (فعال‌سازی با تأیید کارفرما) | ۱۰ | P2 | The four seeded roles were data from the start but there was no way to change them — the only writer was the seeder. /settings/roles now lists every r… |
| `REQ-10.03` | مجوزهای مستقل به تفکیک حوزه | ۱۰ | P0 | — |
| `REQ-10.04` | Audit Log رویدادهای مهم | ۱۰ | P0 | — |
| `REQ-10.07` | سیاست رمز و مدیریت نشست | ۱۰ | P1 | — |
| `REQ-11.01` | UI فارسی RTL اختصاصی | ۱۱ | P1 | — |
| `REQ-11.02` | Design System مشترک | ۱۱ | P0 | — |
| `REQ-11.03` | فونت فارسی پایدار و قانونی | ۱۱ | P1 | security: resources/css/tokens.css:13-32 declares three @font-face rules for Vazirmatn (400/500/700) all pointing at local ../fonts/*.woff2 — Vazirmat… |
| `REQ-11.04` | تاریخ شمسی ورود/نمایش + ذخیره استاندارد | ۱۱ | P0 | — |
| `REQ-13.01` | مرحله‌بندی و گیت تأیید | ۱۳ | P1 | — |
| `REQ-14.01` | تست واقعی، بدون Pass صوری | ۱۴ | P0 | tools/checks/phase4-integration.mjs 25/25 walks the nine integration chains end to end over HTTP with the resulting database state asserted at each li… |
| `REQ-14.02` | Requirements Traceability Matrix | ۱۴ | P0 | — |
| `REQ-15.02` | Backup و Restore تست‌شده | ۱۵ | P0 | scheduling is REQ-12.07 (BLOCKED, no cron on host) — code is not the gap |
| `REQ-05.01` | اتصال پروژه به مشتری و قرارداد + فرم نوع‌محور | ۵-۱/۵-۳ | P1 | — |
| `REQ-05.02` | فیلدهای پروژه سئو | ۵-۱/۵-۳ | P1 | tools/checks/seo-website.mjs 14/14: bare domain accepted (Laravel's own `url` rule would reject it), URL with port/path/query/fragment stored as typed… |
| `REQ-05.03` | مدیریت ساختارمند کلمات کلیدی | ۵-۱/۵-۳ | P1 | — |
| `REQ-05.04` | فیلدهای پروژه طراحی سایت | ۵-۱/۵-۳ | P1 | — |
| `REQ-05.05` | تخصیص مسئول/کارشناس به پروژه | ۵-۱/۵-۳ | P1 | — |
| `REQ-12.01` | یادآوری فاکتور دو روز تقویمی قبل | ۵-۲/۱۲ | P0 | — |
| `REQ-12.02` | یادآوری روز سررسید فاکتور و عقب‌افتادگی | ۵-۲/۱۲ | P0 | — |
| `REQ-12.03` | ثبت انجام فاکتور | ۵-۲/۱۲ | P1 | — |
| `REQ-12.04` | یادآوری گزارش سئو (۲ روز قبل + روز موعد + عقب‌افتادگی) | ۵-۲/۱۲ | P0 | — |

---

## 3. Out of scope: the §16 set

**The binding client document has no section 16.** Its last section is
«۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» (line 287), followed by
«جمع‌بندی» (line 297). Verified by direct grep of
`docs/requirements/source/Vira_CRM_Requirements_FA.txt`.

These 12 requirements come from a separate file-workspace /
online-office specification. They are tracked so that nothing is silently
dropped, but **they must not be counted as delivery defects**, and no new
requirement has been added to scope without a source.

| Requirement | Title | Status | Note |
|---|---|---|---|
| `REQ-16.01` | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.02` | File Explorer با تمام قابلیت‌های ناوبری و عملیات | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.03` | مجوزهای Granular با وراثت و Deny/Allow صریح | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.04` | نسخه‌بندی غیرمخرب با هش و Restore به‌صورت نسخهٔ جدید | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.05` | قفل‌گذاری واقعی و سرور-محور بدون Lost Update | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.06` | WebDAV روی VPS مستقل با CRM به‌عنوان تنها مرجع مجوز | `BLOCKED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.07` | Storage جدا از CRM با Backend قابل تعویض | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.08` | آپلود تکه‌ای/قابل ادامه با تأیید هش و بدون فایل یتیم | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.09` | اتصال فایل به موجودیت‌های CRM بدون دور زدن مجوز | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.10` | مدل تهدید و کنترل‌های امنیتی فایل | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.11` | طبقه‌بندی و Retention با Recycle Bin | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |
| `REQ-16.12` | Online Office و ویرایش هم‌زمان (F3) | `NOT_IMPLEMENTED` | the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نها |

**Open decision for the client:** are these twelve in this contract or not?
The requirements document does not say, so it has to be asked rather than
assumed. Until then they are listed here and excluded from the acceptance
percentage.
