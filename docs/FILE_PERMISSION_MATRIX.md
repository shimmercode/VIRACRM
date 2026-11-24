# FILE_PERMISSION_MATRIX — مدل مجوز Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> این سند «نیت» نیست؛ **قاعدهٔ حل تعارض** است. هر جا ابهام بماند، در پیاده‌سازی به دو رفتار مختلف تبدیل می‌شود و همان‌جا نشت داده رخ می‌دهد.

---

## ۰. یافتهٔ ساختاری مهم (پیش از هر طراحی)

بررسی مخزن نشان می‌دهد مدل هویت فعلی CRM این‌هاست:

| موجودیت | وجود دارد؟ | محل |
|---------|:---:|-----|
| `users` | ✅ | `2026_01_01_000100_create_users_and_access_tables.php` |
| `roles` (کد + عنوان) | ✅ | همان |
| `permissions` (`area.action`) | ✅ | همان |
| `role_permissions` | ✅ | همان |
| `user_roles` | ✅ | همان |
| **گروه (group)** | ❌ **ندارد** | — |
| **دپارتمان (department)** | ❌ **ندارد** | — |
| `employees` با `user_id` و `position` | ✅ | `2026_01_01_000600` |
| `project_assignments` (پروژه ↔ کارمند) | ✅ | `2026_01_01_000400` |

**نتیجهٔ اجتناب‌ناپذیر:** الزام FR-PERM-003 (Grant به گروه و دپارتمان) **بدون افزودن دو مفهوم جدید** قابل پیاده‌سازی نیست. این یک تغییر مدل سازمانی است، نه یک جزئیات فنی — و باید تصمیم کارفرما باشد:

- **OD-104** — منبع حقیقت دپارتمان کجاست؟
- **OD-105** — گروه با Role چه تفاوتی دارد و چه کسی گروه می‌سازد؟

تا تصمیم، طراحی زیر **هر دو حالت** را پشتیبانی می‌کند: اگر دپارتمان از `employees.position` استخراج شود یا جدول مستقل داشته باشد، لایهٔ Grant تغییر نمی‌کند (فقط Adapter عوض می‌شود).

---

## ۱. مجموعهٔ قابلیت‌ها (۱۸ قابلیت)

نگهداری در جدول `file_permission_defs` — **نه Enum در کد** (FR-PERM-002).

| # | کد | عنوان فارسی | معنا | حساس؟ |
|---|----|-------------|------|:-----:|
| 1 | `view` | مشاهده | دیدن نام/متادیتا در فهرست | — |
| 2 | `download` | دانلود | دریافت بایت‌ها | ✅ |
| 3 | `upload` | آپلود | افزودن فایل جدید | ✅ |
| 4 | `create_folder` | ساخت پوشه | MKCOL | — |
| 5 | `edit` | ویرایش | تولید نسخهٔ جدید روی فایل موجود | ✅ |
| 6 | `rename` | تغییر نام | تغییر نام فایل/پوشه | — |
| 7 | `move` | جابه‌جایی | تغییر والد | ✅ |
| 8 | `copy` | کپی | تکثیر | ✅ |
| 9 | `delete` | حذف | انتقال به Trash | ✅ |
| 10 | `restore` | بازیابی | بازگردانی از Trash | — |
| 11 | `share` | اشتراک | دادن مجوز به دیگری | ✅ |
| 12 | `manage_permissions` | مدیریت مجوز | تغییر Grant/Deny و Break | 🔴 |
| 13 | `lock` | قفل | گرفتن قفل نوشتن | — |
| 14 | `unlock` | آزادسازی | آزادکردن قفل دیگران (ادمین) | 🔴 |
| 15 | `view_versions` | مشاهدهٔ نسخه‌ها | تاریخچه | — |
| 16 | `restore_version` | بازیابی نسخه | Restore | ✅ |
| 17 | `view_audit` | مشاهدهٔ Audit | Activity فایل | ✅ |
| 18 | `manage_retention` | مدیریت نگهداری | Hard delete / purge | 🔴 |

### ۱.۱. قابلیت‌های وابسته (dependency)

بعضی قابلیت‌ها بدون قابلیت پایه بی‌معنا یا خطرناک‌اند. Resolver این‌ها را **اجباری** می‌کند:

| اگر | باید داشته باشد | چرا |
|-----|------------------|-----|
| `download` | `view` | بدون دیدن، دانلود بی‌معناست |
| `edit` | `upload` + `view` | ویرایش = نسخهٔ جدید |
| `delete` | `view` | — |
| `restore` | `delete` | تقارن |
| `share` | `view` | نمی‌توان چیزی را که نمی‌بینی شریک شد |
| `manage_permissions` | `share` | خلاف این، تشدید امتیاز است |
| `restore_version` | `view_versions` | — |
| `unlock` | `lock` | — |

**اعمال:** در زمان نوشتن Grant (validation) **و** در زمان خواندن (defence in depth). رد با خطای صریح، نه حذف بی‌صدا.

---

## ۲. دامنه‌های Grant

| دامنه | کلید | مثال | منبع |
|-------|------|------|------|
| user | `user:{id}` | «سارا» | `users` |
| role | `role:{code}` | «accountant» | `roles` |
| group | `group:{id}` | «تیم حقوقی» | **جدید** (OD-105) |
| team | `team:{id}` | «تیم پروژهٔ الف» | از `project_assignments` |
| department | `dept:{id}` | «مالی» | **جدید** (OD-104) |
| project | `project:{id}` | پروژهٔ ۳ | `projects` |
| customer | `customer:{id}` | مشتری ۷ | `customers` |
| organization | `org:*` | همهٔ کارکنان | ثابت |

**قاعدهٔ مهم:** `organization` هرگز شامل کاربران غیرفعال یا خارج از سازمان نمی‌شود و **هرگز** به فایل `restricted` اعمال نمی‌شود (FR-FILE-028).

---

## ۳. ماتریس: قابلیت × سطح دسترسی

### ۳.۱. نقش‌های پایه

| نقش | ساخته می‌شود از | نکته |
|------|------------------|------|
| `file_admin` | Role موجود سیستمی | همهٔ ۱۸ قابلیت + force-unlock + retention |
| `file_manager` | Role | مدیریت پوشهٔ دپارتمان، بدون retention |
| `file_editor` | Role | ویرایش محتوا |
| `file_viewer` | Role | فقط `view` (+ `download` اگر داده شود) |
| `file_guest` | کاربر مهمان (F3) | فقط فایل‌های صریحاً شریک‌شده |

### ۳.۲. ماتریس پیش‌فرض نقش × قابلیت

| قابلیت | admin | manager | editor | viewer | guest |
|--------|:-----:|:-------:|:------:|:------:|:-----:|
| view | ✅ | ✅ | ✅ | ✅ | ✅ |
| download | ✅ | ✅ | ✅ | ⚙️ | ❌ |
| upload | ✅ | ✅ | ✅ | ❌ | ❌ |
| create_folder | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| edit | ✅ | ✅ | ✅ | ❌ | ❌ |
| rename | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| move | ✅ | ✅ | ❌ | ❌ | ❌ |
| copy | ✅ | ✅ | ✅ | ❌ | ❌ |
| delete | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| restore | ✅ | ✅ | ❌ | ❌ | ❌ |
| share | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_permissions | ✅ | ⚙️ | ❌ | ❌ | ❌ |
| lock | ✅ | ✅ | ✅ | ❌ | ❌ |
| unlock | ✅ | ✅ | ❌ | ❌ | ❌ |
| view_versions | ✅ | ✅ | ✅ | ⚙️ | ❌ |
| restore_version | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| view_audit | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_retention | ✅ | ❌ | ❌ | ❌ | ❌ |

⚙️ = قابل فعال‌سازی با تصمیم ادمین، پیش‌فرض خاموش.

### ۳.۳. ماتریس طبقه‌بندی × ناحیه

| ناحیه | public_internal | internal | confidential | restricted |
|-------|:---------------:|:--------:|:------------:|:----------:|
| My Files | ✅ | ✅ | ✅ | ✅ (مالک) |
| Shared With Me | ✅ | ✅ | ✅ | ✅ (Grant صریح) |
| Shared Workspace | ✅ | ✅ | ⚙️ | ❌ |
| Department | ✅ | ✅ | ⚙️ | ❌ |
| Project | ✅ | ✅ | ✅ (عضو پروژه) | ❌ |
| Customer | ✅ | ✅ | ⚙️ | ❌ |
| Contract | ✅ | ✅ | ⚙️ | ❌ |
| HR | ✅ | ✅ | ✅ | ✅ (مجوز HR) |
| Finance | ⚙️ | ✅ | ✅ | ❌ |
| Archive | ✅ | ✅ | ⚙️ | ❌ |
| Trash | — | — | — | — |

**قاعدهٔ سخت:** `restricted` **هرگز** از طریق ناحیه یا گروه قابل دسترسی نیست؛ فقط `user:` یا `role:` با مجوز صریح. اگر کسی بخواهد `restricted` را در `Department` بگذارد، سیستم اجازه نمی‌دهد و در UI توضیح می‌دهد.

### ۳.۴. ماتریس کلاسیک دپارتمان × ناحیه

| از ↓ به → | My | Shared With Me | Shared WS | Dept | Project | Customer | Contract | HR | Finance |
|-----------|:--:|:--------------:|:---------:|:----:|:-------:|:--------:|:--------:|:--:|:-------:|
| مدیریت | 👁 | 👁 | 👁/✏ | 👁/✏ | 👁 | 👁 | 👁 | ❌ | 👁 |
| مالی | 👁 | 👁 | 👁 | 👁 | 👁 | 👁 | 👁 | ❌ | ✏ |
| منابع انسانی | 👁 | 👁 | 👁 | 👁 | 👁 | ❌ | ❌ | ✏ | ❌ |
| پروژه‌ها | 👁 | 👁 | 👁/✏ | 👁 | ✏ | 👁 | 👁 | ❌ | ❌ |
| فروش | 👁 | 👁 | 👁 | 👁 | 👁 | ✏ | ✏ | ❌ | ❌ |
| پشتیبانی | 👁 | 👁 | 👁 | 👁 | 👁 | 👁 | 👁 | ❌ | ❌ |

این ماتریس **پیشنهادی** است و باید در OD-106 تأیید شود؛ اعدادش حاصل تحلیل دسترسی نیست، بلکه یک نقطهٔ شروع قابل بحث است.

---

## ۴. الگوریتم حل مجوز (normative)

> این همان چیزی است که `FileAuthorization::decide()` باید پیاده کند. ترتیب **قانونی** است.

```
decide(user, node, capability):

  # 0 · هویت
  if not user.is_active:                         → DENY  (auth.inactive)
  if user.locked_until > now:                    → DENY  (auth.locked)

  # 1 · دروازهٔ ماژول CRM  ← مهم‌ترین قاعده
  for rel in node.crm_relations:
      if not Gate.allows('view', rel.entity):    → DENY  (crm.module_denied)

  # 2 · طبقه‌بندی
  if node.classification == 'restricted':
      if not has_explicit_grant(user, node):     → DENY  (class.restricted)

  # 3 · زنجیرهٔ اجداد (root → node)
  chain = ancestors(node)           # شامل خود node
  grants = []
  for n in chain:
      for g in permissions(of: n):
          if applies_to(g, user): grants.add(g)
      if n.inherit == false and n != node:
          break                     # Break: بالاتر از این نقطه دیده نمی‌شود

  # 4 · ترکیب — Deny مقدم
  if any(g is DENY for capability in grants):    → DENY  (rule.explicit_deny)
  if any(g is ALLOW for capability in grants):   → go to 5
  else:                                          → DENY  (rule.default_deny)

  # 5 · وابستگی قابلیت
  if not dependencies_satisfied(capability, resolved): → DENY (dep.missing)

  # 6 · قفل
  if capability in WRITE_CAPS and lock_held_by_other(node, user):
                                                 → DENY  (lock.held)

  return ALLOW
```

### ۴.۱. جدول اولویت — «چه چیزی برنده می‌شود؟»

| # | قاعده | برنده | مثال |
|---|-------|-------|------|
| 1 | کاربر غیرفعال | **Deny** | هر مجوزی باطل |
| 2 | مجوز ماژول CRM | **Deny** | پروژه غیرقابل‌خواندن → فایل نامرئی |
| 3 | `restricted` بدون Grant | **Deny** | گروه کافی نیست |
| 4 | Deny صریح در هر سطح | **Deny** | حتی اگر Allow در سطح پایین‌تر باشد |
| 5 | Allow صریح | **Allow** | — |
| 6 | هیچ قاعده | **Deny** | default-deny |
| 7 | Break-inheritance | **قطع والد** | مجوزهای خودِ پوشه معتبرند |
| 8 | قفل دیگران | **Deny نوشتن** | خواندن مجاز است |

### ۴.۲. چرا «Deny مقدم» و نه «آخرین برنده»؟

اگر «آخرین برنده» بود، یک Grant دقیق‌تر روی زیرپوشه می‌توانست یک Deny سازمانی را بشکند — که همان **تشدید امتیاز ناخواسته** است. با Deny مقدم، تنها راه دادن دسترسی، **حذف Deny** است که خودش `manage_permissions` می‌خواهد.

---

## ۵. وراثت و Break-Inheritance

### ۵.۱. مدل

هر پوشه یک ستون `inherit` (boolean) دارد:

| حالت | رفتار |
|------|-------|
| `inherit = 1` (پیش‌فرض) | مجوزهای زنجیرهٔ والد اعمال می‌شوند |
| `inherit = 0` | زنجیره **در همین پوشه متوقف می‌شود**؛ فقط مجوزهای همین پوشه و پایین‌تر |

### ۵.۲. مثال‌های کارشده (این‌ها باید در تست باشند)

**مثال ۱ — وراثت ساده**

```
/Projects/ACME           ALLOW role:editor → edit
  └── /docs              (inherit=1)
       └── report.docx   inherit=1
```
نتیجه: `editor` می‌تواند `report.docx` را ویرایش کند. ✅

**مثال ۲ — Break**

```
/Projects/ACME           ALLOW role:editor → edit
  └── /legal             inherit=0   ALLOW user:9 → view
       └── contract.pdf
```
نتیجه: `editor` به `contract.pdf` **دسترسی ندارد** (زنجیره قطع شد). کاربر ۹ فقط `view` دارد.

**مثال ۳ — Deny سازمانی**

```
/Finance                 DENY role:intern → download
  └── /2026              inherit=1
       └── budget.xlsx   ALLOW role:intern → download
```
نتیجه: **Deny برنده است** → `intern` دانلود نمی‌کند. برای دادن دسترسی باید Deny حذف شود.

**مثال ۴ — Grant دقیق‌تر**

```
/Projects                ALLOW role:viewer → view
  └── /ACME              ALLOW user:12 → download
```
نتیجه: کاربر ۱۲ هم `view` (از نقش) و هم `download` دارد. ✅

**مثال ۵ — خطر تشدید**

```
/Projects                ALLOW group:design → share
  └── /ACME              ALLOW group:design → manage_permissions
```
نتیجه: **رد** — `manage_permissions` نیازمند `share` است که گروه دارد؛ ولی دادن `manage_permissions` به کسی که `share` ندارد ممنوع است. اینجا دارد، پس مجاز است. **اما** خودِ `group:design` نمی‌تواند `manage_permissions` را به گروه دیگری بدهد که آن را ندارد (FR-PERM-010).

**مثال ۶ — ترتیب مهم نیست، نتیجه یکی است**

```
/sales                   ALLOW role:sales → view
  └── /leads             DENY role:sales → delete
```
هر ترتیبی از خواندن ردیف‌ها، همان نتیجه را می‌دهد چون **ترکیب مجموعه‌ای** است، نه «آخرین ردیف».

---

## ۶. بدون تشدید امتیاز (privilege escalation)

| سناریوی خطر | قاعدهٔ دفاعی | تست |
|--------------|---------------|-----|
| کاربر `share` ندارد ولی می‌خواهد شریک شود | `share` نیازمند `view`؛ Grant بدون `share` از Resolver رد می‌شود | T-PERM-010 |
| کاربر می‌خواهد `manage_permissions` بدهد | باید خودش `manage_permissions` داشته باشد | T-PERM-011 |
| Grant گروهی بزرگ‌تر از مجوز گروه | اجتماع مجوزهای نقش‌های کاربر، نه بیشتر | T-PERM-009 |
| تغییر Role کاربر به نقش پایین‌تر | کش مجوز نسخه‌بندی‌شده باطل می‌شود | T-PERM-012 |
| ساخت لینک اشتراک با دامنهٔ بزرگ‌تر | `share` مشروط به `share` روی **همان** node | T-PERM-013 |
| جابه‌جایی فایل به پوشهٔ با مجوز بالاتر | Move نیازمند `move` **در مبدأ و مقصد** | T-PERM-014 |

---

## ۷. ابطال دسترسی (Revocation)

| کانال | مکانیزم | حداکثر تأخیر | قابل اثبات؟ |
|-------|---------|:-------------:|:-----------:|
| UI/API | `permission_version` افزایش → کلید کش باطل | **۰** | T-PERM-015 |
| لینک امضاشدهٔ دانلود | `file_access_tokens.revoked_at` بررسی در هر درخواست | **۰** | T-PERM-016 |
| WebDAV (Basic) | کش ≤۳۰ ثانیه + بررسی `permission_version` | **۳۰s** | T-PERM-017 |
| WebDAV (App-Token) | `revoked_at` | **۰** | T-PERM-017 |
| Session مرورگر | `is_active` در هر درخواست | **۰** | T-PERM-018 |

**قاعدهٔ سخت:** هیچ لینکی با TTL > ۵ دقیقه صادر نمی‌شود. TTL ۵ دقیقه انتخاب شده چون کلاینت‌های کند ممکن است درخواست را دیر شروع کنند، ولی بیش از این نه.

---

## ۸. حالت‌های مرزی که باید تست شوند

| # | حالت | انتظار |
|---|------|--------|
| E1 | فایل بدون هیچ Grant روی پوشهٔ بدون Grant | `403` برای همه به‌جز ادمین |
| E2 | فایل `restricted` در پوشهٔ `organization`-ALLOW | همچنان `403` |
| E3 | Deny روی پوشه، Allow روی فایل | Deny |
| E4 | Deny روی فایل، Allow روی پوشه | Deny |
| E5 | Break روی پوشهٔ میانی با Allow روی ریشه | دسترسی از ریشه قطع |
| E6 | کاربر در دو نقش، یکی Allow و یکی Deny | Deny |
| E7 | فایل متصل به پروژهٔ حذف‌شده | رابطه `orphaned` → دسترسی از مسیر پروژه قطع، از مسیر مستقیم باقی |
| E8 | Grant به گروه حذف‌شده | Grant بی‌اثر + Audit |
| E9 | کاربر مهمان خارج از سازمان | فقط Grant صریح |
| E10 | قفل توسط کاربر غیرفعال‌شده | قفل منقضی می‌شود، فایل آزاد |

---

## ۹. طراحی جدول‌ها (خلاصه — جزئیات در ERD)

```
file_permission_defs   (code PK, label_fa, is_sensitive, sort)
file_permissions       (id, node_id, principal_type, principal_id,
                        capability, effect enum(allow,deny), inherit_from_node,
                        granted_by, created_at, expires_at NULL)
                        UNIQUE(node_id, principal_type, principal_id, capability)
file_groups            (id, code, title, is_system)
file_group_members     (group_id, user_id, UNIQUE)
departments            (id, code, title)             ← جدید، OD-104
user_departments       (user_id, department_id)      ← جدید، OD-104
```

**ایندکس‌های الزامی:**

| جدول | ایندکس | چرا |
|------|--------|-----|
| `file_permissions` | `(node_id, capability)` | حل مجوز یک node |
| `file_permissions` | `(principal_type, principal_id)` | «همهٔ چیزهایی که کاربر می‌بیند» |
| `file_nodes` | `(parent_id, name)` | فهرست پوشه |
| `file_nodes` | `(workspace_kind, deleted_at)` | فهرست ناحیه |
| `file_nodes` | `path` (materialized) | زیردرخت |

---

## ۱۰. جایی که این سند حرف نهایی را نمی‌زند

| مورد | تصمیم لازم |
|------|-------------|
| منبع دپارتمان | **OD-104** |
| تفاوت گروه و Role | **OD-105** |
| ماتریس پیش‌فرض نقش‌ها | **OD-106** (پیشنهاد در §3.2) |
| سقف TTL کش WebDAV | **OD-107** (پیشنهاد: ۳۰ ثانیه) |
| سیاست اشتراک عمومی | **OD-108** (پیش‌فرض: خاموش، F3) |
