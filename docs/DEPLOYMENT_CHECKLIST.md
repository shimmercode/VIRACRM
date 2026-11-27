# Vira CRM — Deployment Handover Checklist

> A standalone checklist for whoever installs this. It is deliberately separate
> from `docs/DEPLOYMENT_GUIDE.md`, which explains *how*; this one is the list of
> things that must be true, each with a command that proves it.
>
> **No real key, password or secret appears in this file or anywhere in the
> repository.** Where a value is needed it is written as `<...>` and must come
> from the operator. `.env` is git-ignored; `.env.example` ships with every
> secret-bearing variable **empty**.
>
> Tick order matters: items 1–5 must pass before 6, because a broken autoloader
> makes every later command fail in a confusing way.

---

## 1. PHP version

| Requirement | Value |
|---|---|
| Declared in `composer.json` | `php ^8.2` |
| Developed against | PHP 8.3.36 |

```bash
php -r 'echo PHP_VERSION, "\n";'      # must be 8.2 or 8.3
```

PHP 8.4 is untested. Laravel 12.40 supports it, but this codebase has never run
on it, so do not assume.

## 2. PHP extensions

Verified present on the development runtime; all are required.

| Extension | Needed by |
|---|---|
| `pdo`, `pdo_mysql`, `pdo_sqlite` | database |
| `openssl` | `BackupCipher` (AES-256-GCM), `APP_KEY`, credential encryption. `app/Services/Backup/BackupCipher.php` calls `extension_loaded('openssl')` and `vira:backup` refuses to run without it |
| `mbstring` | Persian text, `mPDF` |
| `gd` | `mPDF` image handling |
| `zip` | backup containers |
| `json`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl`, `bcmath` | Laravel core |

```bash
php -m | grep -Ei '^(pdo|pdo_mysql|pdo_sqlite|openssl|mbstring|gd|zip|json|tokenizer|xml|ctype|fileinfo|curl|bcmath)$' | sort
```

`intl` is **not** required and is absent from the development runtime. Do not
add a dependency on it.

## 3. Composer

| Item | Command | Expected |
|---|---|---|
| Composer present | `composer --version` | 2.x |
| Lockfile valid | `composer validate --no-check-publish` | `is valid` |
| Dependencies match lock | `composer install --no-dev --optimize-autoloader` | no "lock file is not up to date" |
| Clean-room install | install into an empty directory, then `php artisan --version` | version prints |

> **Status in this handover: NOT DONE.** `composer.lock` could not be generated
> in the delivery environment because `repo.packagist.org` was unreachable
> (`ECONNRESET`). Run all four commands above on a host with network access and
> commit the resulting `composer.lock`. Until it exists, "reproducible install"
> is unproven.

## 4. MySQL / MariaDB

| Item | Command |
|---|---|
| Server reachable | `mysqladmin --host=<host> --user=<user> --password ping` |
| Database exists, empty | `mysql -e "show tables" <dbname>` → no rows |
| Migrations from empty | `php artisan migrate --force` |
| Seed | `php artisan db:seed --force` |
| Migration version reported | `php artisan migrate:status` → every file `Ran` |
| Compatibility harness | see below |

**Do not ship `database/database.sqlite`.** It is a development artefact, it is
git-ignored, and running the test suite rewrites a row in it. Ship an empty
database plus the 16 migration files instead.

The compatibility harness exists but has **never been executed** — there is no
MySQL server in the delivery environment:

```bash
DB_CONNECTION=mysql DB_DATABASE=_vira_mysql_probe_1 DB_HOST=<host> \
  DB_USERNAME=<user> DB_PASSWORD=<pass> \
  php tools/checks/mysql-compatibility.php
```

Run without those variables it refuses (`DB_DATABASE must start with
'_vira_mysql_probe_'`); run with them and no server it reports
`FAIL connect … MySQL server has gone away` and `Item stays BLOCKED`.
**SQLite is not accepted as evidence for MySQL.**

## 5. Environment variables

Copy `.env.example` to `.env`, then fill every `<...>`:

| Variable | Required | Notes |
|---|---|---|
| `APP_NAME` | yes | |
| `APP_ENV` | yes | `production` on the live host. Drives the Secure-cookie default (item 11) |
| `APP_DEBUG` | yes | **must be `false` in production** — item 18 |
| `APP_URL` | yes | the real public URL, with scheme |
| `APP_KEY` | yes | `php artisan key:generate`. Never commit it |
| `DB_CONNECTION` | yes | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | yes | |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | yes | `database`, `120` |
| `SESSION_SECURE_COOKIE` | production | `true`. If omitted, it now defaults to true when `APP_ENV=production` |
| `TRUSTED_PROXIES` | behind a proxy | item 10 |
| `VIRA_BACKUP_KEY` | yes | base64-encoded 32 bytes: `php artisan vira:backup-key` |
| `VIRA_CREDENTIAL_KEY` | yes | encrypts stored credentials |
| `MAIL_*` | if mail used | |

```bash
php artisan config:clear && php artisan config:cache
php artisan about          # prints the resolved configuration
```

**`APP_KEY`** — generate once, store outside the repository, and back it up.
Losing it makes every encrypted column (including stored credentials)
permanently unreadable.

```bash
php artisan key:generate --force
```

## 6. `APP_ENV` / `APP_DEBUG`

```bash
php artisan tinker --execute='echo config("app.env"), " ", var_export(config("app.debug"), true);'
```

Must print `production false`. `APP_DEBUG=true` in production renders stack
traces, file paths and query contents to any visitor — that is the §10-5
requirement "مدیریت خطا بدون Stack Trace".

## 7. HTTPS

| Item | Proof |
|---|---|
| Certificate valid | `openssl s_client -connect <host>:443 -servername <host> </dev/null 2>/dev/null \| openssl x509 -noout -dates` |
| HTTP redirects to HTTPS | `curl -sSI http://<host>/ \| head -1` → `301`/`308` with an `https` `Location` |
| No mixed content | browser console clean on an authenticated page |
| HSTS | `curl -sSI https://<host>/ \| grep -i strict-transport-security` |

**Cannot be verified in the delivery environment** — there is no TLS terminator
here. Record it as BLOCKED until run on the real host.

## 8. Trusted proxies

Only needed when the app sits behind nginx/Apache/Cloudflare. Leaving it unset
silently breaks three things: `users.last_login_ip` and audit rows record the
**proxy's** address instead of the user's; IP-based login throttling counts
every user as one address, so one abuser locks out everyone; and the forwarded
scheme is ignored, so the session cookie never gets `Secure`.

```bash
# .env — the proxy's real address or CIDR. NEVER '*'
TRUSTED_PROXIES=<proxy ip or cidr>
```

`*` trusts a client-supplied `X-Forwarded-For`, which lets an attacker spoof
their address and walk straight past IP throttling. Unset means "trust nothing",
which is the safe default.

## 9. Session cookie security

Proved by `tools/checks/security-headers.mjs` (16/16):

| Flag | Value | Why |
|---|---|---|
| `HttpOnly` | always on | script cannot read the session id |
| `SameSite` | `lax` | blocks a cross-site request carrying it |
| `Path` | `/` | |
| `Secure` | on when `SESSION_SECURE_COOKIE=true`, or by default in production | otherwise the session crosses the network in plaintext |

```bash
curl -sSI https://<host>/login | grep -i '^set-cookie'
# must contain HttpOnly, SameSite=lax, and Secure
```

## 10. Storage permissions

Writable by the web user, and **not** readable by other users:

```bash
# directories Laravel writes to
bootstrap/cache  storage/app  storage/framework/cache
storage/framework/sessions  storage/framework/views  storage/logs

chown -R <webuser>:<webgroup> bootstrap/cache storage
find storage bootstrap/cache -type d -exec chmod 750 {} \;
find storage bootstrap/cache -type f -exec chmod 640 {} \;
```

## 11. Public document root

**The web server must point at `public/`, not the repository root.** Everything
else — `.env`, `storage/`, `vendor/`, `database/` — must be unreachable.

```bash
curl -sSI https://<host>/.env            | head -1   # must NOT be 200
curl -sSI https://<host>/storage/logs/laravel.log | head -1   # must NOT be 200
```

`vira:backup` refuses any `--path` inside `public/`
(`backup-hardening` BK-03), so a backup cannot be written into the document
root by accident.

## 12. Queue and cron

```bash
# queue worker, if the queue driver is not sync
php artisan queue:work --tries=3 --timeout=90

# scheduler
* * * * * cd <app path> && php artisan schedule:run >> /dev/null 2>&1
```

```bash
php artisan schedule:list        # shows what would run
crontab -l                       # shows the entry is installed
```

**Cron scheduling is BLOCKED in the delivery environment** — no `cron`, `crond`
or `crontab` binary exists there. Verify on the real host that two consecutive
cycles run and that the second neither duplicates nor unexpectedly prunes the
first.

## 13. Backup

```bash
php artisan vira:backup-key                       # once; store the value safely
php artisan vira:backup                           # → storage/app/backups
php artisan vira:backup --path=<dir>              # dir must be OUTSIDE public/
php artisan vira:backup --no-prune                # keep every container
```

Verified by `tools/checks/backup-hardening.mjs` (13/13):

| Check | Result |
|---|---|
| No `VIRA_BACKUP_KEY` | refuses, writes nothing — never an unencrypted dump |
| Malformed key | refuses (must be base64-encoded 32 bytes) |
| `--path` under `public/` | refused, including nested paths |
| Container format | `VIRABK1` + `aes-256-gcm` / `hkdf-sha256`, with salt, IV, GCM tag |
| Encrypted at rest | payload measured at **7.991 bits/byte** (random = 8), no SQLite magic, no plaintext text |
| Detached checksum | `<container>.sha256` written alongside |
| Wrong key on restore | exit 1, `restore_failed` audit row |
| One flipped bit | exit 1, audited — the GCM tag catches it before anything is written |
| Real restore | exit 0, row counts reconcile across all tables |

## 14. Restore

```bash
php artisan vira:restore --list
php artisan vira:restore <container> --verify                  # check only
php artisan vira:restore <container> --force --no-interaction  # actually write
```

`--no-interaction` is not optional in a script: the command prompts
"Proceed?" and, with stdin closed, prints "aborted; nothing written" **and
still exits 0**. Always pass it.

A restored database legitimately contains one extra `audit_logs` row
(`restore_ok`) — that is the restore recording itself, not corruption.

## 15. Logging and error handling

```bash
tail -f storage/logs/laravel.log
```

| Item | Requirement |
|---|---|
| Log writable | `storage/logs` owned by the web user |
| No stack traces to the client | `APP_DEBUG=false` (item 6) |
| Failed login / restore / backup recorded | `audit_logs` rows: `restore_failed`, `backup_failed`, `login` |
| Log rotation | configure `logrotate`; Laravel's own daily driver does not prune files |

```bash
php artisan tinker --execute='echo \App\Models\AuditLog::latest("id")->first()?->event;'
```

## 16. Initial admin setup

```bash
php artisan db:seed --force      # creates roles and permissions
php artisan vira:admin           # creates the first superadmin
```

The seeder generates a password rather than using a fixed one, marks the
account `must_change_password=1`, and can write the value to
`SEED_ADMIN_PASSWORD_FILE`, which it **deletes** afterwards. Do not put a
password in `.env`, a commit message or a ticket.

```bash
# first login must force a password change
```

Note: no seeded role holds `users.manage` or `settings.manage`. The `admin`
account passes only because `is_superadmin` short-circuits `hasPermission()`.
**Never use the superadmin as the actor when testing permissions** — it will
pass checks that a real role would fail.

## 17. Post-install verification

```bash
php artisan about
php artisan route:list | wc -l
php tests/run.php                          # 18 classes / 826 assertions
node tools/checks/run-all.mjs              # full regression
```

The regression's last section prints an **isolation verdict**: the shared
database's content fingerprint must be identical before and after the run.

---

## Sign-off

| # | Item | Status | Verified by | Date |
|---|---|---|---|---|
| 1 | PHP version | ☐ | | |
| 2 | PHP extensions | ☐ | | |
| 3 | Composer + lockfile | ☐ | | |
| 4 | MySQL migrate + seed | ☐ | | |
| 5 | Environment variables | ☐ | | |
| 6 | `APP_ENV`/`APP_DEBUG` | ☐ | | |
| 7 | HTTPS | ☐ | | |
| 8 | Trusted proxies | ☐ | | |
| 9 | Session cookie | ☐ | | |
| 10 | Storage permissions | ☐ | | |
| 11 | Document root | ☐ | | |
| 12 | Queue / cron | ☐ | | |
| 13 | Backup cycle | ☐ | | |
| 14 | Restore cycle | ☐ | | |
| 15 | Logging | ☐ | | |
| 16 | Initial admin | ☐ | | |
| 17 | Regression green | ☐ | | |

An item that cannot be verified in the current environment is recorded as
**BLOCKED** with the reason, never ticked.
