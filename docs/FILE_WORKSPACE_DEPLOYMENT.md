# FILE_WORKSPACE_DEPLOYMENT — استقرار Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 (طرح) · **وضعیت:** Proposed — **هیچ استقراری انجام نشده است**

> دو زیرساخت جدا: **CRM روی cPanel** و **WebDAV روی VPS**. این سند هر دو را جدا پوشش می‌دهد.
> **هیچ ادعای Production-Ready بدون اجرای واقعی مجاز نیست** (دستور §39).

---

## بخش الف — CRM روی cPanel

### الف.۱. پیش‌نیازها

| مورد | حداقل | وضعیت واقعی |
|------|-------|-------------|
| PHP | ۸.۲ (Laravel 12) | `BLOCKED` — دسترسی به cPanel نیست |
| `upload_max_filesize` | ≥ ۳۲M | `BLOCKED` |
| `post_max_size` | ≥ ۳۶M | `BLOCKED` |
| `memory_limit` | ≥ ۲۵۶M | `BLOCKED` |
| `max_execution_time` | ≥ ۱۲۰s (آپلود بزرگ) | `BLOCKED` |
| `fileinfo`, `zip`, `gd`, `openssl`, `mbstring`, `pdo_mysql` | فعال | `BLOCKED` |
| Cron | ≥ ۱ در دقیقه | `BLOCKED` |
| SSH | مطلوب (نه الزامی) | `BLOCKED` |
| MySQL/MariaDB | ≥ 5.7 / 10.3 | `BLOCKED` |

> وضعیت‌ها از `HOST_COMPATIBILITY.md` می‌آید: ۱۴ از ۲۱ بررسی `BLOCKED`.

### الف.۲. فایل‌های تنظیم آپلود

**واقعیت بررسی‌شده:** مخزن **هیچ** `.user.ini` یا `php.ini` ندارد؛ هیچ تنظیم سقف آپلود در کد نیست (`grep` انجام شد).

**پیشنهاد:** افزودن `.user.ini` در `public/` (در cPanel با PHP-FPM معمولاً کار می‌کند، ولی **باید روی هاست واقعی آزموده شود**):

```ini
; public/.user.ini
upload_max_filesize = 64M
post_max_size = 72M
max_execution_time = 300
memory_limit = 256M
```

> **`NOT TESTED`** — روی cPanel واقعی آزموده نشده. اگر `AllowOverride`/FPM اجازه نداد، باید در `php.ini` سرور یا پنل هاست تنظیم شود (نیازمند دسترسی ادمین).

### الف.۳. ساختار دایرکتوری

```
/home/<user>/vira/
├── public/            ← document root (در subdomain)
│   └── .user.ini
├── app/
├── storage/
│   ├── app/private/   ← فایل‌های واقعی (اگر local استفاده شود)
│   ├── framework/
│   └── logs/
├── .env               ← 0600، بیرون از webroot
└── vendor/
```

| قاعده | چرا |
|-------|-----|
| `.env` بیرون از `public/` | افشا = فاجعه |
| `storage/` بیرون از `public/` | افشای مستقیم فایل |
| `storage/app/private` با `0700` | فقط کاربر هاست |
| `vendor/` بیرون از `public/` | — |

### الف.۴. متغیرهای محیطی جدید

```dotenv
# ---------- هستهٔ فایل ----------
VIRA_FILES_ENABLED=true
VIRA_FILES_MAX_FILE_MB=64
VIRA_FILES_CHUNK_MB=8
VIRA_FILES_CHUNK_THRESHOLD_MB=32
VIRA_FILES_TOKEN_TTL_MINUTES=5
VIRA_FILES_TRASH_DAYS=30
VIRA_FILES_DEFAULT_MAX_VERSIONS=10

# ---------- Storage ----------
VIRA_FILES_DISK=s3              # local | s3
VIRA_FILES_S3_KEY=
VIRA_FILES_S3_SECRET=
VIRA_FILES_S3_REGION=
VIRA_FILES_S3_BUCKET=
VIRA_FILES_S3_ENDPOINT=
VIRA_FILES_S3_PATH_STYLE=true   # برای SeaweedFS/MinIO-like

# ---------- پل WebDAV ----------
VIRA_DAV_ENABLED=true
VIRA_DAV_SHARED_SECRET=         # ۳۲ بایت تصادفی، فقط در دو .env
VIRA_DAV_ALLOWED_SERVICE_IDS=webdav-vps-01
VIRA_DAV_CACHE_TTL_SECONDS=30
VIRA_DAV_REQUIRE_MTLS=true      # توصیه‌شده

# ---------- اسکن / سهمیه ----------
VIRA_FILES_SCAN_DRIVER=none     # none | clamav | virustotal (OD-127)
VIRA_FILES_DEFAULT_USER_QUOTA_MB=1024
```

**قاعدهٔ سخت:** `VIRA_DAV_SHARED_SECRET` **هرگز** در Git نیست: `.env` معامله‌شده و در `.gitignore`.

### الف.۵. استقرار

| گام | دستور/کار | ریسک |
|-----|-----------|------|
| 1 | `composer install --no-dev --optimize-autoloader` | **`composer.lock` وجود ندارد** → نسخه‌ها آزاد |
| 2 | `php artisan migrate --force` | Migration غیرمخرب؛ **اول بکاپ** |
| 3 | `php artisan storage:link` (اگر لازم) | نه برای private |
| 4 | `php artisan config:cache route:cache view:cache` | — |
| 5 | تنظیم Cron | `BLOCKED` |
| 6 | HTTPS + HSTS | — |
| 7 | `noindex` روی دامنه | — |

**جدول Queue:** `jobs`/`failed_jobs` موجود است. پیشنهاد: `QUEUE_CONNECTION=database` + cron `queue:work --stop-when-empty`.

### الف.۶. Cron

```
* * * * * cd /home/<user>/vira && php artisan schedule:run >> storage/logs/cron.log 2>&1
```

**کارهای زمان‌بندی که F1 لازم دارد:**

| Job | بازه | چه می‌کند |
|-----|------|-----------|
| `purge-expired-upload-sessions` | ۱۵ دقیقه | پاک‌سازی temp و نشست منقضی |
| `sweep-stale-locks` | ۵ دقیقه | آزادسازی قفل کهنه + Audit |
| `purge-expired-tokens` | ۱ ساعت | `file_access_tokens` منقضی |
| `recompute-folder-sizes` | شبانه | اندازهٔ پوشه‌ها |
| `detect-orphan-blobs` | هفتگی | گزارش (نه حذف) |
| `apply-retention-policy` | شبانه | dry-run پیش‌فرض |

### الف.۷. لاگ و خطا

| مورد | تنظیم |
|------|-------|
| `LOG_CHANNEL` | `daily`، نگهداری ۱۴ روز |
| `APP_DEBUG` | `false` در Production |
| لاگ آپلود | بدون محتوا، بدون نام رمز |
| دسترسی به `storage/logs` | `0700` |

---

## بخش ب — WebDAV روی VPS

### ب.۱. مشخصات پیشنهادی

| مورد | مقدار |
|------|-------|
| OS | Ubuntu Server LTS |
| vCPU / RAM | ۲ / ۲GB (حداقل) — ۴ / ۸GB (پیشنهادی) |
| Disk | ۲۰GB (حداقل) — ۴۰GB |
| دامنه | `dav.<domain>` |
| TLS | Let's Encrypt + auto-renew |
| PHP | 8.2/8.3-FPM |
| nginx | 1.24+ |

### ب.۲. نصب

```bash
# 1) پایه
apt update && apt install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring unzip
apt install -y fail2ban ufw unattended-upgrades

# 2) sabre/dav
mkdir -p /var/www/dav && cd /var/www/dav
composer require sabre/dav:^4.7

# 3) firewall
ufw default deny incoming
ufw allow 443/tcp
ufw allow from <ADMIN_IP> to any port 22 proto tcp
ufw enable
```

### ب.۳. nginx (نمونه)

```nginx
server {
    listen 443 ssl http2;
    server_name dav.example.com;

    ssl_certificate     /etc/letsencrypt/live/dav.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dav.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # بدنه: بدون بافر در RAM؛ temp روی دیسک
    client_max_body_size 0;
    proxy_request_buffering off;
    client_body_temp_path /var/tmp/nginx_body;

    # محدودیت نرخ
    limit_req_zone $binary_remote_addr zone=dav:10m rate=30r/s;

    location / {
        limit_req zone=dav burst=60 nodelay;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm-dav.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/dav/public/index.php;
        fastcgi_read_timeout 300s;
    }

    location = /healthz {
        access_log off;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm-dav.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/dav/public/health.php;
    }
}
```

**نکات حساس:**

| مورد | چرا |
|------|-----|
| `proxy_request_buffering off` | جلوگیری از مصرف RAM برای فایل ۴GB |
| `client_body_temp_path` روی دیسک | همان |
| `client_max_body_size 0` | چون حجم را خود سرویس کنترل می‌کند |
| `fastcgi_read_timeout 300s` | آپلود بزرگ |
| **بدون** `access_log` Authorization | جلوگیری از نشت |

### ب.۴. PHP-FPM pool جدا

```ini
; /etc/php/8.3/fpm/pool.d/dav.conf
[dav]
user = www-dav
group = www-dav
listen = /run/php/php8.3-fpm-dav.sock
pm = dynamic
pm.max_children = 12
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
php_admin_value[open_basedir] = /var/www/dav:/var/tmp/nginx_body
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_admin_value[upload_max_filesize] = 4G
php_admin_value[post_max_size] = 4G
php_admin_value[memory_limit] = 256M
php_admin_flag[log_errors] = on
```

### ب.۵. سیستم‌د و Health

```ini
# /etc/systemd/system/dav-health.service
[Service]
Type=oneshot
ExecStart=/usr/local/bin/dav-health.sh
```

```bash
#!/usr/bin/env bash
# /usr/local/bin/dav-health.sh
set -euo pipefail
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 https://dav.example.com/healthz)
[[ "$code" == "200" ]] || { logger -t dav-health "FAIL $code"; exit 1; }
```

**Cron:** `* * * * * /usr/local/bin/dav-health.sh || echo "DAV DOWN" | mail -s ALERT root`

### ب.۶. مانیتورینگ

| Probe | آستانه |
|-------|--------|
| `/healthz` | ۲ خطای متوالی → هشدار |
| گواهی TLS | <۱۴ روز → هشدار |
| Disk | >۸۰٪ → هشدار |
| Failed auth | >۵۰/۵دقیقه → هشدار |
| CRM latency | p95 > ۵۰۰ms → هشدار |

### ب.۷. به‌روزرسانی و وصله

| نوع | سیاست |
|-----|-------|
| امنیتی OS | `unattended-upgrades` خودکار |
| nginx/PHP | پنجرهٔ ماهانه، با تست |
| sabre/dav | هر minor، با `litmus` |
| Rollback | snapshot قبل از هر تغییر |

### ب.۸. Secret Management

| Secret | کجا |
|--------|-----|
| `shared_secret` (CRM↔DAV) | `/etc/vira-dav/.env`, `0600`, مالک `www-dav` |
| S3 credentials | همان |
| TLS key | Let's Encrypt، `0600` |
| mTLS client cert | `/etc/vira-dav/certs/` |

**چرخش:** هر ۹۰ روز، یا فوری در صورت شک.

---

## بخش ج — اتصال امن بین دو سیستم

```
CRM ──HTTPS──▶ VPS authorize  (CRM سرور است، VPS کلاینت)
VPS ──HTTPS──▶ CRM /api/dav/*  (VPS کلاینت، CRM سرور)
```

| کنترل | مقدار |
|-------|-------|
| IP allowlist | `/api/dav/*` فقط از IP ثابت VPS |
| HMAC | روی body + timestamp |
| Nonce | کش ۵ دقیقه در CRM |
| mTLS | گواهی کلاینت VPS روی CRM؛ `VIRA_DAV_REQUIRE_MTLS=true` |
| Rate limit | ۶۰۰ درخواست/دقیقه از هر سرویس |
| Timeout | ۲ ثانیه (Fail-Closed) |
| Circuit breaker | در VPS |

**قاعدهٔ شبکه:** پورت دیتابیس CRM **هرگز** از اینترنت باز نیست. SMB **هرگز** روی اینترنت.

---

## بخش د — گام‌به‌گام F1 (ترتیب اجرا)

| # | گام | پیش‌نیاز | تخمین |
|---|-----|----------|-------|
| 1 | تصمیم OD-101…OD-130 | کارفرما | — |
| 2 | تأیید معماری | کارفرما | — |
| 3 | Migration جداول فایل (روی بکاپ) | تأیید | ۲ روز |
| 4 | مدل‌ها + `StorageContract` | ۳ | ۳ روز |
| 5 | Resolver مجوز + تست | ۴ | ۵ روز |
| 6 | آپلود تکه‌ای | ۴ | ۴ روز |
| 7 | دانلود + Range + preview امن | ۴ | ۳ روز |
| 8 | نسخه + قفل | ۵،۶ | ۴ روز |
| 9 | UI Explorer | ۶،۷ | ۸ روز |
| 10 | اتصال CRM (relations) | ۵ | ۲ روز |
| 11 | سرویس WebDAV روی VPS | ۵ | ۶ روز |
| 12 | پل مجوز | ۱۱ | ۵ روز |
| 13 | بکاپ و بازیابی | ۳ | ۴ روز |
| 14 | تست ویندوز | ۱۱،۱۲ + E3 | ۲ روز |
| 15 | تست کارایی | ۱۱ | ۲ روز |
| **جمع (تخمین، نه تعهد)** | — | — | **~۵۰ روز مهندسی** |

> **این تخمین بر اساس تجربه است، نه اندازه‌گیری.** هیچ تعهد زمانی محسوب نمی‌شود. **OD-130.**

---

## بخش ه — چک‌لیست پیش از تحویل F1

| # | مورد | وضعیت فعلی |
|---|------|-------------|
| 1 | همهٔ Migration بازگشت‌پذیر | ❌ نوشته نشده |
| 2 | بکاپ حین Migration | ❌ |
| 3 | همهٔ مسیرها `permission:` | ❌ |
| 4 | `.env` بیرون از webroot | ❌ بررسی نشده |
| 5 | `storage/` غیرقابل دسترسی | ❌ |
| 6 | HTTPS + HSTS | ❌ |
| 7 | Rate limit | ❌ |
| 8 | Audit فعال | ❌ |
| 9 | تست E2 پاس | ❌ |
| 10 | تست E3 پاس | ❌ |
| 11 | مستند راهنمای کاربر | ❌ |
| 12 | Runbook عملیاتی | ❌ |
| 13 | برنامهٔ بکاپ فعال | ❌ |
| 14 | مانیتورینگ فعال | ❌ |

**تا وقتی همهٔ ردیف‌ها ✅ نشوند، F1 «تحویل‌شده» نیست.**

---

## بخش و — ریسک‌های استقرار

| # | ریسک | اثر | کاهش |
|---|------|-----|------|
| D1 | cPanel اجازهٔ `.user.ini` ندهد | سقف آپلود ۲MB | مذاکره با هاست / استفاده از chunking با تکهٔ کوچک |
| D2 | Cron دقیقه‌ای نداشته باشد | قفل کهنه می‌ماند | Job مبتنی بر درخواست + TTL کوتاه |
| D3 | فایروال هاست IP ثابت VPS را نپذیرد | پل کار نکند | لیست IP ثابت / VPN |
| D4 | Let's Encrypt روی VPS مشکل داشته باشد | TLS خطا | گواهی تجاری |
| D5 | هم‌پوشانی IP داخلی | مسدودسازی WOPI در F3 | سند شد |
| D6 | `composer.lock` نبودن | نسخهٔ ناسازگار در آینده | افزودن lock پیش از F1 |
