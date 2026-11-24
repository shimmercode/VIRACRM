# WEBDAV_CRM_AUTHORIZATION_BRIDGE — پل مجوز بین WebDAV و CRM

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 (قرارداد) · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> این سند **قرارداد** بین دو سیستم است. اگر این قرارداد مبهم باشد، WebDAV مجبور می‌شود حدس بزند — و حدس در مجوز یعنی نشت داده.
> **قاعدهٔ حاکم:** CRM تنها منبع مجوز است. سرویس WebDAV هیچ جدول مجوزی ندارد.

---

## ۱. اصل طراحی

| # | اصل | پیامد |
|---|-----|-------|
| A1 | **یک Resolver** | همان `FileAuthorization::decide()` که UI استفاده می‌کند، API هم استفاده می‌کند |
| A2 | **Fail-Closed** | خطا = رد. هیچ `catch → true` مجاز نیست |
| A3 | **کمترین امتیاز** | هر Method فقط مجوز لازم خودش را می‌پرسد |
| A4 | **بدون اعتماد به کلاینت** | مسیر و Method از کلاینت می‌آید، پس سرور هر دو را نرمال می‌کند |
| A5 | **قابل حسابرسی** | هر تصمیم (allow و deny) نوشته می‌شود |
| A6 | **بدون Secret در Log** | Authorization header، رمز، توکن هرگز لاگ نمی‌شوند |

---

## ۲. احراز هویت — دو حالت

### ۲.۱. Basic (حالت اصلی F1)

```
Authorization: Basic base64(username:password)
```

| جنبه | تصمیم | دلیل |
|------|-------|------|
| الزام TLS | **اجباری** | رمز base64 است، نه رمزنگاری |
| هش رمز | ارسال **هش** به CRM، نه رمز | حتی در پل، رمز خام نرود |
| الگوریتم | `HMAC-SHA256(password, bridge_pepper)` | تا هش قابل حملهٔ واژه‌نامه‌ای نباشد بدون pepper |
| نگهداری | CRM فقط `Hash::check` روی رمز اصلی | هیچ رمز معکوس‌پذیری ذخیره نمی‌شود |
| محدودیت | Windows همیشه Basic می‌فرستد | قابل تغییر نیست |

**نکتهٔ ویندوز:** `BasicAuthLevel=1` اجازهٔ Basic فقط روی SSL می‌دهد. ما هم روی سرور فقط با TLS قبول می‌کنیم — دو لایه.

### ۲.۲. App-Token (حالت پیشرفته/بازنویسی)

```
Authorization: Bearer vd_<public_id>_<secret>
```

| ویژگی | مقدار | توضیح |
|-------|-------|-------|
| پیشوند | `vd_` | قابل شناسایی در log scrubber |
| قالب | `vd_<12 hex>_<48 base64url>` | public id + secret |
| ذخیره | `secret` فقط به‌صورت `bcrypt` در CRM | — |
| عمر | ≤ ۹۰ روز، قابل کاهش | — |
| Scope | لیست node/workspace + مجوزها | **بدون scope = بدون دسترسی** |
| اتصال | `user_id` + `device_label` + `last_ip` | — |
| Revocation | فوری، `revoked_at` | — |
| Replay | هر Token **فقط** برای همان کاربر و همان Methodهای Scope | — |

**پیشنهاد:** Basic در F1 (سازگاری ویندوز)، App-Token برای مشتریان پیشرفته و ابزارهای CI. **تصمیم: OD-120.**

---

## ۳. API قرارداد

### ۳.۱. `POST /api/dav/verify`

**هدف:** اعتبارسنجی credential. **حساس‌ترین Endpoint.**

```http
POST /api/dav/verify
Content-Type: application/json
X-Vira-Signature: t=1732340000,v1=<hmac_sha256>
X-Vira-Service: webdav-vps-01

{ "username": "sara", "password_hmac": "…", "client_ip": "5.6.7.8" }
```

```json
200 { "ok": true,  "user_id": 12, "is_active": true, "perm_version": 41 }
401 { "ok": false, "reason": "invalid_credentials" }
423 { "ok": false, "reason": "account_locked", "retry_after": 300 }
```

| قاعده | جزئیات |
|-------|--------|
| پاسخ همیشه یک شکل | زمان پاسخ **constant-time**: نبود کاربر هم همان تأخیر |
| Rate limit | ۱۰ درخواست در دقیقه به ازای IP + ۵ به ازای username |
| Audit | `dav_auth_failure` با IP؛ **بدون** رمز |
| HMAC | `hash_hmac('sha256', "$ts.$body", $shared_secret)` |
| پنجرهٔ زمانی | ±۶۰ ثانیه برای جلوگیری از Replay |
| mTLS | **توصیه‌شده**: گواهی کلاینت VPS روی CRM ثبت شود |

### ۳.۲. `POST /api/dav/authorize`

**هدف:** تصمیم برای **یک** عملیات.

```http
POST /api/dav/authorize
X-Vira-Signature: …
{ "user_id": 12, "perm_version": 41,
  "method": "PUT",
  "path": "/Projects/ACME/docs/report.docx",
  "lock_token": "opaquelocktoken:…" }
```

```json
200 { "allow": true,
      "node_id": 9912,
      "node_uuid": "…",
      "caps": ["upload","edit","lock"],
      "current_version": 4,
      "quota_left_bytes": 18340211,
      "max_file_bytes": 268435456 }

403 { "allow": false, "reason": "denied_by_rule", "rule": "crm.module_denied" }
503 { "allow": false, "reason": "authorization_unavailable" }
```

**نرمال‌سازی مسیر (اجباری):**

```php
$path = rawurldecode($path);                 // %2e%2e → ..
$path = str_replace('\\', '/', $path);       // backslash
$path = preg_replace('#/+#', '/', $path);    // //
$path = trim($path, '/');
// رد فوری اگر:
//   - شامل ".." یا "." یا "\0" یا کاراکتر کنترلی
//   - بیش از ۳۲ segment
//   - طول > ۱۰۲۴
//   - کاراکتر رزرو ویندوز در نام segment پایانی (<,>,:,",|,?,*)
```

**نکتهٔ مهم:** مسیر به **`node_id`** تبدیل می‌شود و سپس `FileAuthorization::decide()` روی `node_id` اجرا می‌شود. **هرگز** با مسیر مستقیم به فایل‌سیستم دسترسی نمی‌شود.

### ۳.۳. `POST /api/dav/authorize-batch`

برای `PROPFIND` (به‌جای N درخواست):

```json
POST /api/dav/authorize-batch
{ "user_id": 12, "perm_version": 41,
  "nodes": ["uuid1","uuid2","…"] }       // حداکثر ۳۰۰

200 { "allowed": ["uuid1","uuid3"],
      "denied":  ["uuid2"],
      "truncated": false }
```

**قاعده:** اگر `truncated=true`، سرویس WebDAV باید **بقیه را deny** کند، نه اینکه استنتاج کند.

### ۳.۴. `POST /api/dav/commit`

**هدف:** ثبت نسخه پس از آپلود موفق.

```json
POST /api/dav/commit
{ "user_id": 12, "node_id": 9912,
  "sha256": "…", "size_bytes": 1234567,
  "temp_key": "tmp/af12….part",
  "expected_version": 4 }
```

```json
201 { "version_id": 5521, "version_number": 5, "etag": "\"<sha256>\"" }
409 { "reason": "version_conflict", "current_version": 6 }
422 { "reason": "hash_mismatch" }
507 { "reason": "quota_exceeded" }
403 { "reason": "denied" }            // TOCTOU: مجوز بین authorize و commit عوض شده
```

**تراکنش اجباری:** بررسی مجوز → بررسی قفل → بررسی `expected_version` → درج نسخه → به‌روزرسانی `current_version_id`. اگر هر گام شکست خورد، **هیچ** ردیفی درج نشود.

### ۳.۵. `POST /api/dav/event`

ثبت رخداد سمت سرویس (مثلاً `dav_auth_failure` بدون فراخوانی authorize).

```json
{ "user_id": null, "action": "dav_auth_failure",
  "ip": "5.6.7.8", "meta": { "reason": "invalid_credentials" } }
```

**قاعده:** `meta` هرگز نباید Authorization header، رمز، یا محتوای فایل داشته باشد. یک **scrubber** در CRM کلیدهای ممنوع را حذف می‌کند: `password`, `authorization`, `token`, `secret`, `cookie`.

---

## ۴. نگاشت Method → مجوز

| Method | مجوز لازم | بررسی قفل | یادداشت |
|--------|-----------|:---------:|---------|
| `OPTIONS` | — | — | پاسخ بدون داده |
| `PROPFIND` (Depth 0) | `view` | — | یک آیتم |
| `PROPFIND` (Depth 1) | `view` روی همه | — | batch |
| `PROPPATCH` | `edit` | ✅ | فقط `displayname` مجاز |
| `GET` | `download` | — | خواندن آزاد |
| `HEAD` | `view` | — | — |
| `PUT` (جدید) | `upload` روی پوشهٔ والد | ✅ | — |
| `PUT` (روی موجود) | `edit` | ✅ | `If-Match` توصیه |
| `MKCOL` | `create_folder` روی والد | ✅ | — |
| `MOVE` | `move` در مبدأ **و** `upload` در مقصد | ✅ هر دو | — |
| `COPY` | `copy` در مبدأ، `upload` در مقصد | ❌ | کپی از قفل آزاد است |
| `DELETE` | `delete` | ✅ | به Trash |
| `LOCK` | `lock` | — | — |
| `UNLOCK` | `unlock` | — | صاحب یا ادمین |

**قاعدهٔ MOVE:** بدون مجوز در مقصد، کاربر می‌تواند فایل را «از پوشهٔ محرمانه بیرون بکشد» — که همان نشتی است. پس هر دو طرف بررسی می‌شوند.

---

## ۵. امنیت قرارداد

| تهدید | دفاع |
|-------|------|
| **Replay** درخواست authorize | HMAC با timestamp ±۶۰s + `nonce` کش‌شده در CRM ۵ دقیقه |
| **Forgery** امضا | `shared_secret` ۳۲ بایت، فقط در `.env` دو طرف |
| **MITM** | TLS 1.2+ با گواهی معتبر + **mTLS** توصیه‌شده |
| **Credential stuffing** | rate limit + قفل حساب موجود (`locked_until`) |
| **Enumeration** کاربران | زمان پاسخ ثابت + پاسخ یکسان |
| **Path traversal** | نرمال‌سازی + تبدیل به `node_id` + `League\Flysystem` به‌عنوان لایهٔ سوم |
| **Privilege escalation** | authorize همیشه با `user_id` از **verify**، نه از body قابل‌دست‌کاری |
| **TOCTOU** | authorize دوباره در commit |
| **Log leakage** | scrubber + `Authorization` هرگز لاگ نمی‌شود |
| **DoS** از WebDAV به CRM | rate limit در سمت CRM + circuit breaker در VPS |

### ۵.۱. Circuit breaker (سمت VPS)

```
قانون: اگر ۲۰ درخواست متوالی به CRM خطا داد (در ۳۰ ثانیه):
        - باز کردن مدار
        - همهٔ درخواست‌ها 503 بدون تلاش (Fail-Closed)
        - تلاش هر ۱۵ ثانیه برای نیمه‌باز
```

**چرا لازم است؟** بدون آن، یک قطعی CRM به «سیل درخواست» تبدیل می‌شود و بازیابی را کند می‌کند.

---

## ۶. قرارداد خطا (Error Contract)

| کد | بدنه | معنا برای کلاینت | نمایش به کاربر |
|----|------|------------------|----------------|
| `401` | `{"reason":"invalid_credentials"}` | اعتبارنامه بد | پنجرهٔ ورود دوباره |
| `401` | `{"reason":"token_expired"}` | توکن منقضی | ورود دوباره |
| `403` | `{"reason":"denied"}` | مجوز ندارد | «دسترسی ندارید» |
| `404` | `{"reason":"not_found"}` | وجود ندارد **یا** نمی‌بیند | همان 403 از دید کاربر |
| `409` | `{"reason":"version_conflict","current":6}` | تعارض نسخه | «نسخهٔ جدیدتر موجود است» |
| `412` | `{"reason":"etag_mismatch"}` | `If-Match` نادرست | تلاش مجدد |
| `423` | `{"reason":"locked","owner":"سارا"}` | قفل | «قفل توسط …» |
| `503` | `{"reason":"authorization_unavailable","retry_after":30}` | قطع CRM | «سرویس موقتاً در دسترس نیست» |
| `507` | `{"reason":"quota_exceeded"}` | سهمیه | «فضا تمام شد» |

**قاعدهٔ عدم افشا:** `404` و `403` از دید کاربر **یکسان** نمایش داده می‌شوند تا وجود/عدم وجود فایل لو نرود. تفاوت فقط در Log سمت سرور.

---

## ۷. نمونهٔ کد (طرح)

### ۷.۱. سمت VPS

```php
final class PermissionGuard extends AbstractPlugin
{
    public function __construct(private CrmBridge $bridge) {}

    public function beforeMethod(RequestInterface $request, ResponseInterface $response): bool
    {
        $decision = $this->bridge->authorize(
            userId:   $this->currentUserId(),
            method:   $request->getMethod(),
            path:     $request->getPath(),
            lockToken: $request->getHeader('If'),
        );

        // هرگز: return $decision?->allow ?? true;   ← این باگ امنیتی است
        if (! $decision->isAllow()) {
            throw new DavException($decision->httpStatus(), $decision->reason());
        }
        return true;
    }
}
```

### ۷.۲. سمت CRM

```php
final class DavAuthorizationController
{
    public function authorize(DavAuthorizeRequest $request, FileAuthorization $authz): JsonResponse
    {
        $this->assertServiceSignature($request);       // HMAC + timestamp + nonce
        $node = $this->resolveNodeId($request->path);  // نرمال‌سازی + تبدیل به node_id

        if ($node === null) {
            $this->audit->denied('dav', $request, 'not_found');
            return response()->json(['allow' => false, 'reason' => 'denied'], 403);
        }

        $caps = $authz->resolve(user: $request->user(), node: $node);   // همان Resolver UI

        $need = DavMethodMap::capabilities($request->method);
        $allow = $caps->satisfies($need);

        $this->audit->record(
            action: $allow ? 'dav.authorize.ok' : 'dav.authorize.denied',
            node: $node, actor: $request->user(), client: 'webdav',
            meta: ['method' => $request->method, 'need' => $need],
        );

        return $allow
            ? response()->json(['allow' => true, 'node_id' => $node->id, 'caps' => $caps->toArray(), ...])
            : response()->json(['allow' => false, 'reason' => 'denied'], 403);
    }
}
```

---

## ۸. تست‌های قرارداد

| ID | تست | نوع | وضعیت |
|----|-----|-----|-------|
| T-BRD-001 | verify با رمز درست/غلط | integration | `NOT TESTED` |
| T-BRD-002 | verify با کاربر غیرفعال | integration | `NOT TESTED` |
| T-BRD-003 | verify زمان پاسخ ثابت (کاربر ناموجود vs رمز غلط) | integration | `NOT TESTED` |
| T-BRD-004 | authorize برای هر ۱۳ Method | integration | `NOT TESTED` |
| T-BRD-005 | authorize با `..` در مسیر → رد | security | `NOT TESTED` |
| T-BRD-006 | authorize با `%2e%2e%2f` → رد | security | `NOT TESTED` |
| T-BRD-007 | authorize با مسیر > ۱۰۲۴ → رد | security | `NOT TESTED` |
| T-BRD-008 | MOVE با مجوز مبدأ بدون مجوز مقصد → رد | security | `NOT TESTED` |
| T-BRD-009 | batch با ۳۰۱ node → `truncated` و deny بیشتر | integration | `NOT TESTED` |
| T-BRD-010 | Replay HMAC با timestamp قدیمی → رد | security | `NOT TESTED` |
| T-BRD-011 | Replay nonce تکراری → رد | security | `NOT TESTED` |
| T-BRD-012 | Circuit breaker پس از ۲۰ خطا | integration | `NOT TESTED` |
| T-BRD-013 | commit با `expected_version` قدیمی → 409 | integration | `NOT TESTED` |
| T-BRD-014 | commit پس از سلب مجوز (TOCTOU) → 403 | security | `NOT TESTED` |
| T-BRD-015 | scrubber: `password` در meta حذف شود | unit | `NOT TESTED` |

---

## ۹. آنچه تصمیم نشده

| مورد | OD |
|------|-----|
| Basic یا App-Token پیش‌فرض | OD-120 |
| TTL کش مجوز | OD-107 |
| mTLS اجباری یا توصیه‌شده | OD-121 |
| محل جدول قفل | OD-113 |
| توزیع `shared_secret` | OD-122 |
