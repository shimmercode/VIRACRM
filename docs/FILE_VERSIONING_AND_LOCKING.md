# FILE_VERSIONING_AND_LOCKING — نسخه‌بندی و قفل‌گذاری

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> **قاعدهٔ حاکم:** هیچ نسخه‌ای بی‌ردپا حذف نمی‌شود. هیچ نوشتنی بدون بررسی نسخهٔ مبنا انجام نمی‌شود. قفل در **جریان Backend و Storage** اعمال می‌شود، نه در UI.

---

## ۱. مسئله

سه خرابی واقعی که باید غیرممکن شود:

| # | خرابی | نتیجهٔ فاجعه‌بار |
|---|-------|------------------|
| F1 | دو نفر هم‌زمان ویرایش کنند و دومی، اولی را پاک کند | **Lost Update** — داده بی‌صدا از بین می‌رود |
| F2 | نسخهٔ قبلی بازنویسی شود | تاریخچه دروغ می‌گوید |
| F3 | قفل فقط در UI باشد (دکمه غیرفعال) | درخواست مستقیم HTTP قفل را دور می‌زند |

هر سه در طراحی زیر **ساختاراً** بسته می‌شوند، نه با «مراقبت توسعه‌دهنده».

---

## ۲. مدل نسخه

### ۲.۱. قواعد تغییرناپذیر

| قاعده | اجرا در کجا |
|-------|-------------|
| هر تغییر محتوا = **نسخهٔ جدید** | `VersionService::create()` — تنها راه نوشتن |
| `version_number` هرگز بازاستفاده نمی‌شود | `UNIQUE(node_id, version_number)` |
| نسخهٔ تاریخی هرگز UPDATE نمی‌شود | فقط `retained_until`/`purged_at` توسط Job |
| Restore = نسخهٔ جدید | `source='restore'`, `restored_from_version=N` |
| فقط یک نسخه `is_current=1` | ایندکس + بررسی تراکنشی |
| هش هر نسخه ذخیره می‌شود | `file_versions.sha256` |

### ۲.۲. چرخهٔ نسخه

```
create v1 ──▶ v1 current
                │
       new upload│
                ▼
             v2 current,  v1 superseded
                │
   restore v1 ──┤
                ▼
             v3 current (source=restore, restored_from=1),  v2 superseded
                │
   retention ───┤ (max_versions=2) → v1 eligible
                ▼
             v1 purged_at set   ← ردیف می‌ماند، بایت حذف می‌شود (یا blob ref--)
```

### ۲.۳. کد مرجع (طرح، نه اجرا)

```php
final class VersionService
{
    public function create(FileNode $node, UploadedBlob $blob, int $actorId, string $source, ?string $note): FileVersion
    {
        return DB::transaction(function () use ($node, $blob, $actorId, $source, $note) {
            // قفل ردیف: تنها راه جلوگیری از دو نسخهٔ هم‌شماره
            $locked = FileNode::whereKey($node->id)->lockForUpdate()->firstOrFail();

            $next = (int) FileVersion::where('node_id', $locked->id)->max('version_number') + 1;

            FileVersion::where('node_id', $locked->id)->where('is_current', true)
                ->update(['is_current' => false]);

            $version = FileVersion::create([
                'node_id' => $locked->id,
                'version_number' => $next,
                'blob_id' => $blob->id,
                'size_bytes' => $blob->size_bytes,
                'sha256' => $blob->sha256,
                'mime' => $blob->mime,
                'source' => $source,
                'change_note' => $note,
                'created_by' => $actorId,
                'is_current' => true,
            ]);

            $locked->forceFill([
                'current_version_id' => $version->id,
                'size_bytes' => $blob->size_bytes,
                'last_modified_by' => $actorId,
            ])->save();

            return $version;
        });
    }
}
```

**نکتهٔ کلیدی:** `lockForUpdate()` روی **node** انجام می‌شود، نه روی نسخه — چون node نقطهٔ هم‌رسانی است.

### ۲.۴. Dedup (بدون از دست دادن نسخه)

اگر `sha256` قبلاً در `file_blobs` هست:
- **هیچ بایت جدیدی ذخیره نمی‌شود**
- `ref_count` افزایش می‌یابد
- نسخهٔ جدید همچنان **ساخته می‌شود** (تاریخچه صادق می‌ماند)

اگر فایل کاربر دقیقاً همان محتوای قبلی را آپلود کند، سیستم یک نسخهٔ جدید با همان هش می‌سازد (تا «آپلود کردم» در تاریخچه باشد) ولی اگر `change_note` خالی و هش برابر باشد، UI هشدار می‌دهد «تغییری نیست — ادامه؟».

**تصمیم:** این هشدار در **OD-112**.

---

## ۳. سیاست نگهداری نسخه

| پارامتر | پیش‌فرض | سقف | توضیح |
|---------|---------|-----|-------|
| `max_versions` | ۱۰ | ۱۰۰ | تعداد نسخهٔ نگه‌داشته |
| `max_age_days` | ۳۶۵ | — | عمر نسخهٔ غیرجاری |
| `trash_days` | ۳۰ | — | عمر در سطل بازیافت |
| `keep_forever` | false | — | هرگز پاک نشود |
| `legal_hold` | false | — | **مطلق**: بر `dry_run_only` هم مقدم است |
| `dry_run_only` | **true** | — | تا ادمین صریحاً گزارش را تأیید نکند، هیچ چیز پاک نمی‌شود |

### ۳.۱. الگوریتم پاک‌سازی

```
for each node:
   versions = history sorted by number asc, excluding is_current
   if policy.legal_hold or policy.keep_forever: skip
   eligible = versions where
       (max_versions is not null and rank > max_versions)
       or (max_age_days is not null and age > max_age_days)
   if policy.dry_run_only:
       record report only          ← هیچ حذفی
   else:
       for v in eligible:
           if v.retained_until > now: skip
           mark purged_at; decrement blob.ref_count
           if blob.ref_count == 0 and blob.verified_in_last_backup:
               queue blob deletion (delayed, with audit)
           audit: version_purged
```

**قاعدهٔ سخت:** `dry_run_only=true` پیش‌فرض است. تا ادمین گزارش را ندیده و تأیید نکرده، **هیچ نسخه‌ای پاک نمی‌شود**.

---

## ۴. قفل‌گذاری — دو نوع در یک جدول

| نوع | منشأ | استفاده | سقف زمان |
|-----|------|---------|----------|
| `shared` | WebDAV `LOCK` با `Depth` | چند خواننده، هیچ نویسنده | ۱۰ دقیقه |
| `exclusive` | WebDAV `LOCK` یا UI «قفل برای ویرایش» | یک نویسنده، هیچ خوانندهٔ نویسنده | ۳۰ دقیقه (UI) / ۱۰ دقیقه (DAV) |

**تصمیم مهم:** هر دو در **همان جدول `file_locks`** هستند. اگر دو جدول می‌بودند، UI و WebDAV یکدیگر را نمی‌دیدند — و همان F3 رخ می‌داد.

### ۴.۱. الگوریتم گرفتن قفل (race-safe)

```php
public function acquire(int $nodeId, string $kind, int $userId, string $origin, int $ttlSeconds): FileLock
{
    return DB::transaction(function () use (...) {
        DB::table('file_locks')->where('node_id', $nodeId)
          ->whereNull('released_at')->where('expires_at', '<=', now())
          ->update(['released_at' => now(), 'release_reason' => 'expired']);

        $existing = FileLock::where('node_id', $nodeId)->whereNull('released_at')->first();

        if ($existing) {
            if ($existing->owner_user_id === $userId && $existing->lock_kind === $kind) {
                return $this->refresh($existing, $ttlSeconds);   // idempotent
            }
            throw new LockConflictException($existing);          // 423
        }

        // UNIQUE(node_id, active) اینجا مسابقه را قطعی می‌کند:
        // اگر دو تراکنش هم‌زمان برسند، یکی با DuplicateKeyException برمی‌گردد.
        try {
            return FileLock::create([...]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) throw new LockConflictException(...);
            throw $e;
        }
    }, attempts: 3);
}
```

**چرا این race-safe است؟** به‌خاطر `UNIQUE(node_id, active)` در سطح **دیتابیس**. دو تراکنش هم‌زمان نمی‌توانند دو قفل فعال برای یک node بسازند؛ دیتابیس یکی را رد می‌کند.

**`lockForUpdate` تنها کافی نیست** اگر در بعضی مسیرها فراموش شود؛ `UNIQUE` حتی اگر کد اشتباه باشد، محافظت می‌کند. **هر دو** با هم استفاده می‌شوند (دفاع لایه‌ای).

### ۴.۲. قفل کهنه (Stale Lock)

| حالت | رفتار |
|------|-------|
| `expires_at` گذشته | Job هر ۵ دقیقه آزاد می‌کند + `audit lock_expired` |
| کاربر غیرفعال شد | Job فوری آزاد می‌کند + Audit |
| Session منقضی شد | Job آزاد می‌کند (اگر `origin=app`) |
| سرور WebDAV ری‌استارت | قفل در DB می‌ماند؛ TTL آزادش می‌کند |
| ادمین force-unlock | با `reason` اجباری + Audit + اطلاع به صاحب قبلی |

### ۴.۳. نگاشت WebDAV

| WebDAV | داخلی |
|--------|-------|
| `LOCK` بدون body | `exclusive` |
| `LOCK` با `<lockscope><shared/>` | `shared` |
| `Timeout: Second-600` | `ttl=600` (سقف ۱۸۰۰) |
| `Timeout: Infinite` | `ttl=1800` (سقف امنیتی) |
| `If: (<token>)` روی PUT | بررسی `lock_token` |
| `UNLOCK` | `released_at=now()`, `release_reason='owner'` |
| `423 Locked` | `LockConflictException` |

**پشتیبانی sabre/dav:** پلاگین `Sabre\DAV\Locks\Plugin` با Backend از نوع PDO — قابل اتصال به همان MySQL یا یک SQLite محلی VPS. **تصمیم در OD-113:** (الف) همان جدول CRM از طریق Bridge، (ب) جدول قفل محلی VPS + همگام‌سازی.

**پیشنهاد:** (الف) — چون F3 (دو منبع قفل) را ساختاراً حذف می‌کند. ولی هزینهٔ آن یک درخواست شبکه‌ای در هر LOCK است.

---

## ۵. جلوگیری از Lost Update

### ۵.۱. مسیر مرورگر

| گام | مکانیزم |
|-----|---------|
| باز کردن ویرایشگر | قفل `exclusive` گرفته می‌شود (اختیاری) یا `expected_version` به کلاینت داده می‌شود |
| آپلود نسخهٔ جدید | کلاینت `base_version_id` می‌فرستد |
| سرور | اگر `base_version_id != current_version_id` → **409 Conflict** با شمارهٔ نسخهٔ فعلی |
| UI | «فایل توسط X تغییر کرده. نسخهٔ خودت را به‌عنوان نسخهٔ جدید ذخیره کنم یا رها کنم؟» |

**قاعده:** هرگز **بی‌صدا** رونویسی نمی‌شود. حتی در تعارض، انتخاب با کاربر است.

### ۵.۲. مسیر WebDAV

| گام | مکانیزم |
|-----|---------|
| `GET` | `ETag: "<sha256>"` و `Last-Modified` |
| `PUT` با `If-Match: "<etag>"` | اگر ETag فعلی متفاوت → `412 Precondition Failed` |
| `PUT` بدون `If-Match` | مجاز فقط اگر قفل در اختیار همان کاربر باشد |
| `PUT` روی فایل قفل‌شدهٔ دیگری | `423 Locked` |

> **واقعیت تلخ که باید مستند شود:** Windows WebClient همیشه `If-Match` نمی‌فرستد. در این حالت تنها محافظ، **قفل** است. این دلیل دیگری است که قفل **نباید** فقط UI باشد.

### ۵.۳. جدول سناریوها

| # | سناریو | نتیجه |
|---|--------|-------|
| L1 | A قفل می‌گیرد، B آپلود می‌کند | `423` |
| L2 | A قفل می‌گیرد، TTL می‌گذرد، B آپلود می‌کند | موفق + `lock_expired` در Audit |
| L3 | A و B هم‌زمان قفل می‌خواهند | یکی موفق، دیگری `423` (تضمین DB) |
| L4 | A قفل دارد، ادمین force-unlock | آزاد + Audit + پیام به A |
| L5 | A قفل دارد، A دوباره قفل می‌خواهد | `refresh` (idempotent) |
| L6 | A قفل دارد، A خارج می‌شود | Job آزاد می‌کند (اگر origin=app) |
| L7 | A نسخه ۳ را می‌گیرد، B نسخه ۴ می‌سازد، A نسخه ۵ آپلود می‌کند | `409` — A باید انتخاب کند |
| L8 | دو PUT هم‌زمان WebDAV | یکی ۲۰۱، دیگری `423` یا نسخهٔ بعدی با `If-Match` |
| L9 | قفل روی پوشه (`Depth: infinity`) | ساخت فرزند جدید رد می‌شود |
| L10 | فایل حذف‌شده قفل فعال دارد | حذف، قفل را آزاد می‌کند + Audit |

---

## ۶. جایی که قفل **اعمال** می‌شود

| مسیر | بررسی قفل | کد |
|------|-----------|-----|
| `UploadController@complete` | ✅ | `LockGuard::assertWritable()` |
| `VersionController@restore` | ✅ | همان |
| `FilesController@update` (rename/move) | ✅ | همان |
| `FilesController@destroy` | ✅ | همان |
| `DavController` PUT/DELETE/MOVE/COPY | ✅ | Bridge `authorize()` برمی‌گرداند |
| `DownloadController` | ❌ (خواندن آزاد) | — |

**قاعده:** `LockGuard` در **Middleware** یا در **Service** است، نه در Controller تکی. اگر روزی مسیر جدیدی اضافه شد و Middleware نداشت، بررسی در Service هست.

---

## ۷. تست‌های الزامی این سند

| ID | تست | نوع |
|----|-----|-----|
| T-VER-001 | دو نسخه پشت سر هم، شماره ۱ و ۲ | integration |
| T-VER-002 | Restore نسخهٔ ۱ → نسخهٔ ۳ ساخته شود، ۱ و ۲ دست‌نخورده | integration |
| T-VER-003 | دو آپلود **هم‌زمان** → شماره‌های ۲ و ۳، نه دو تا ۲ | integration (concurrency) |
| T-VER-004 | Dedup: آپلود همان هش → blob جدید ساخته نشود، نسخه ساخته شود | integration |
| T-VER-005 | `dry_run_only=true` → هیچ نسخه‌ای پاک نشود | integration |
| T-VER-006 | `legal_hold=true` → حتی با `dry_run_only=false` پاک نشود | integration |
| T-LOCK-001 | دو قفل هم‌زمان → یکی موفق | integration (concurrency) |
| T-LOCK-002 | TTL → آزادسازی خودکار | integration + time travel |
| T-LOCK-003 | Force-unlock → Audit + آزاد | integration |
| T-LOCK-004 | PUT روی قفل دیگری → 423 | integration |
| T-LOCK-005 | `If-Match` نادرست → 412 | integration |
| T-LOCK-006 | ۱۰۰ درخواست قفل هم‌زمان روی یک node → فقط ۱ قفل | stress |
| T-LOCK-007 | قفل پوشه با `Depth: infinity` → MKCOL رد شود | integration |

**همهٔ این‌ها باید در `FILE_WORKSPACE_TEST_PLAN.md` به تست واقعی HTTP با ≥۲ کاربر نگاشت شوند.**

---

## ۸. آنچه تصمیم نشده

| مورد | OD |
|------|-----|
| محل جدول قفل WebDAV | OD-113 |
| سقف `max_versions` پیش‌فرض | OD-114 |
| هشدار «محتوای یکسان» | OD-112 |
| سیاست قفل هنگام باز کردن در مرورگر | OD-115 |
