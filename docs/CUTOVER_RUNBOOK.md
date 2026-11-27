# Cutover Runbook

**Target:** Vira CRM on a single Linux host with MySQL or MariaDB
**Date:** 2026-11-22
**Repository state:** `d421c39`, branch `arena/3b43de84-viracrm`
**Audience:** the operator performing the deployment, and whoever is on call for
it afterwards.

> **This runbook is not a claim that the system is ready to cut over.** Three
> gates remain open and are collected in §10. Everything below is the procedure;
> the verdict on whether to execute it is a separate decision and it is
> currently **NOT PRODUCTION READY**.

Every command is written to be pasted as-is except for the values in `⟨angle
brackets⟩`, which are host-specific. No secret appears in this document, and none
should ever be added to it.

---

## 0. Before you start

| Item | Why it matters |
|---|---|
| A Linux host with PHP 8.2+ and the extensions in §1 | The application will not boot without them |
| MySQL 8.0+ **or** MariaDB 10.6+ reachable from the host | `mysql` is the only supported production driver |
| The MySQL/MariaDB **client tools** (`mysqldump`, `mysql`) installed | A missing `mysqldump` makes `vira:backup` refuse; see §7 |
| The generated `composer.lock` (§2) | Without it the installed tree is not reproducible |
| A place to keep two keys offline | Losing either is unrecoverable |

Read `docs/DEPLOYMENT_GUIDE.md` first if you have not deployed this stack before;
this runbook assumes its conventions and does not repeat the PHP-FPM or nginx
setup.

---

## 1. Prerequisites on the host

```bash
php -v                       # must be 8.2 or newer; 8.3 is what is tested
php -m | sort                # check the list below
```

Required extensions — the application checks these and will fail at boot rather
than at the first request that needs one:

```
pdo_mysql  mbstring  openssl  zip  gd  curl  bcmath  fileinfo  json  tokenizer
```

Two runtime capabilities matter for backups specifically:

```bash
php -r 'var_dump(function_exists("proc_open"));'   # must be bool(true)
command -v mysqldump && mysqldump --version
```

`proc_open` must be **enabled** and `mysqldump` must be **on PATH** (or
`MYSQLDUMP_PATH` set). If either is missing, `vira:backup` will exit 1 with an
explicit refusal — it will not write a file it cannot verify. This is deliberate
and is covered in §7.2.

---

## 2. Code and dependencies

```bash
git clone ⟨repository-url⟩ /var/www/viracrm
cd /var/www/viracrm
git checkout ⟨release-sha⟩        # a specific commit, not a branch
```

### 2.1 Install from the lock file

```bash
composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
```

**If `composer.lock` is absent, stop here.** As of `d421c39` it is absent, because
this repository was developed in an environment with no reachable package
repository (see `docs/COMPOSER_LOCK_ACCEPTANCE.md`). Installing without a lock
file resolves the caret ranges in `composer.json` afresh, so the tree you get is
not the tree that was regression-tested. Generate the lock on a host with egress:

```bash
composer validate --strict
composer update --no-install --with-all-dependencies
```

Then commit it and re-run the install above. This is gate §10-1.

### 2.2 Front-end assets

Assets are built ahead of time and committed under `public/build`. If your
release includes asset changes:

```bash
npm ci && npm run build
```

There is no Node runtime requirement on the production host; the build output is
static.

---

## 3. Database provisioning

### 3.1 Create the database and a dedicated user

Run as a MySQL administrator. The application user must not be `root`:

```sql
CREATE DATABASE vira CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'vira'@'127.0.0.1' IDENTIFIED BY '⟨strong-random-password⟩';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX,
      REFERENCES, LOCK TABLES, CREATE TEMPORARY TABLES, EXECUTE
   ON vira.* TO 'vira'@'127.0.0.1';

FLUSH PRIVILEGES;
```

`mysqldump` with `--single-transaction` needs no `LOCK TABLES` privilege, but
`--routines` and `--triggers` are in the backup command, so the user needs
`SELECT` on `mysql.proc` (MariaDB) or the equivalent. If your backup fails with a
privilege error, that is the grant to check — not the command.

Character set: `utf8mb4` is required. The application stores Persian text and
emoji-adjacent characters; `utf8` (three-byte) will truncate.

### 3.2 Verify connectivity before continuing

```bash
php -r 'new PDO("mysql:host=127.0.0.1;dbname=vira", "vira", getenv("PWD")); echo "ok\n";'
```

---

## 4. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` ships with **every secret empty**. Fill in the following; leave
everything else at its template value unless §4.3 applies.

### 4.1 Required values

| Key | Value | Notes |
|---|---|---|
| `APP_NAME` | `ویرا` | Shown in the sidebar and page titles |
| `APP_ENV` | `production` | Anything else enables debug surfaces |
| `APP_DEBUG` | `false` | **Must** be false in production |
| `APP_URL` | `https://crm.example.com` | Public origin, with scheme |
| `APP_KEY` | *(generated)* | `php artisan key:generate`; never reuse across environments |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `127.0.0.1` | Use the socket path (`DB_SOCKET`) if you prefer |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `vira` | |
| `DB_USERNAME` | `vira` | |
| `DB_PASSWORD` | *(from §3.1)* | |
| `SESSION_SECURE_COOKIE` | `true` | Set it explicitly. `config/session.php` falls back to `APP_ENV === 'production'`, so it is *usually* right — but a deploy with `APP_ENV` left at `local` silently ships a cookie over plain HTTP |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | |
| `MAIL_MAILER` | `smtp` | `log` silently discards every message |

`APP_DEBUG=false` is not cosmetic: with debug on, an exception page renders
environment values and query fragments to whoever triggers the error.

### 4.2 The two encryption keys

These are separate secrets and must stay separate. Both are empty in the
template and neither falls back to anything: leaving one empty makes the feature
that needs it refuse to run rather than degrade to plaintext.

```bash
# Credential vault — encrypts stored project passwords.
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
#   -> VIRA_CREDENTIAL_KEY

# Backup containers — encrypts .vbk1 files.
php artisan vira:backup-key
#   -> VIRA_BACKUP_KEY, printed once
```

Record both in your secret store **now**, before the first backup and before any
credential is stored:

- `VIRA_CREDENTIAL_KEY` **must never leave the server.** Losing it loses every
  stored credential; leaking it is a full credential export.
- `VIRA_BACKUP_KEY` travels with the backups to off-host storage. Losing it
  loses every existing backup.

Back both up **separately from the database**. A backup that travels with its own
key is not an encrypted backup.

Do not reuse `APP_KEY` for either purpose. Rotating `APP_KEY` is a different
operation with different consequences, and sharing a value couples them.

### 4.3 Reverse proxy and asset path

If the app is behind nginx, a load balancer, or any TLS terminator:

```
TRUSTED_PROXIES=127.0.0.1
```

Use the proxy's actual address, or a comma-separated list, or `*` only if you
control every hop. **Why this is not optional:** without it the application reads
the proxy's address as the client's. Four things then go wrong at once — the
audit log and `users.last_login_ip` record the proxy instead of the person,
rate limiting counts every user as one address, and a TLS-terminated deployment
believes it is serving plain HTTP so it will not set a `Secure` session cookie.

If the install is in a sub-directory (`https://example.com/crm/`):

```
ASSET_URL=/crm
```

Getting this wrong produces a completely unstyled page and **no error message**.

### 4.4 Lock the file down

```bash
chmod 600 .env
chown www-data:www-data .env
```

### 4.5 Clear any cached configuration

```bash
php artisan config:clear
php artisan config:cache
```

Run `config:cache` only after `.env` is final: a cached config ignores later
edits to `.env`, which is a common source of "I changed it and nothing happened".

---

## 5. Schema and baseline data

```bash
php artisan migrate --force
```

`--force` is required in production; without it Artisan prompts and a cron or
deploy script will hang.

Then seed the configuration-grade baseline:

```bash
php artisan db:seed --force
```

This seeds **only** configuration: permissions, the four roles, contract types,
project dictionaries, SEO packages, settings, the chart of accounts and fiscal
periods, and the single superadmin. It creates no customers, contracts or
projects. `DemoDataSeeder` exists but is **not** called and must not be run on a
production install.

### 5.1 The superadmin password

`UserSeeder` creates `admin` and takes its secret from, in order of precedence:

1. `SEED_ADMIN_PASSWORD` in the environment, if set;
2. a value read from a file, if configured;
3. nothing — in which case a cryptographically random password is generated and
   **printed once** to the console.

In every case `must_change_password` is `true`, so the bootstrap secret is
single-use: the first login forces a password change before anything else is
reachable.

**Capture the printed password from the deploy log.** If it scrolled past:

```bash
php artisan vira:reset-admin-password --generate
```

which prints a new one-time secret. Rotate it at first login either way.

---

## 6. Web server and TLS

Terminate TLS at nginx (or your load balancer) and forward to PHP-FPM. The
application does not serve TLS itself.

Minimum for the nginx server block: `root` pointing at `public/` — **never** the
repository root, or `.env` is fetchable over HTTP — plus a redirect from `http` to
`https`.

The application sets its own security headers (`SecurityHeaders` middleware:
`X-Frame-Options: DENY`, `Content-Security-Policy`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store`). It does **not**
set `Strict-Transport-Security` — that belongs at the TLS terminator, which is the
only place that knows the request arrived over HTTPS. Add it to the nginx server
block:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Verify after the first deploy — the first three come from the application, HSTS
from nginx:

```bash
curl -sI https://crm.example.com/login \
  | grep -iE "strict-transport|content-security|x-frame|x-content-type"
```

Expect four lines. **A missing HSTS line means the nginx directive above was not
applied** — the application will not supply it.

`public/` must not expose dotfiles:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://crm.example.com/.env
# expect 403 or 404 — never 200
```

---

## 7. Backups

### 7.1 Run one, and prove it

```bash
php artisan vira:backup --path=/var/backups/viracrm
```

Success looks like this and nothing less:

```
backup written and verified
  file   : vira-backup-2026-11-22-…-….vbk1
  format : mysqldump (mysql)
  bytes  : …
  tables : …
  rows   : …
  sha256 : …
```

The command re-opens its own output and runs it through the restore gates before
publishing it. A file appearing on disk is not the success condition; a container
that would restore is.

### 7.2 If it refuses

Every refusal is explicit and exits 1 **before writing anything**:

| Message | Cause | Fix |
|---|---|---|
| `mysqldump was not found on PATH` | Client tools not installed | Install `mysql-client` / `mariadb-client`, or set `MYSQLDUMP_PATH` |
| `A MySQL backup requires a PHP build that permits process execution` | `proc_open` disabled | Enable it in `php.ini`; do not work around it |
| `cannot resolve the database manager` | Database not reachable | Fix §3.2 first |
| `VIRA_BACKUP_KEY is not set` | Key missing from `.env` | §4.2 |

A refusal is the correct behaviour and must not be "fixed" by pointing the backup
at a directory or a schema name.

### 7.3 Verify a restore — on a throwaway database, never production

```bash
mysql -e 'CREATE DATABASE vira_restore_check'
mysql vira_restore_check < ⟨dump.sql⟩
mysql vira_restore_check -e 'SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM customers;'
mysql -e 'DROP DATABASE vira_restore_check'
```

Restoring **into** the live MySQL server through `vira:restore` is not
implemented, and the command says so rather than overwriting a schema. To restore
for real, use the `mysql` client against a target database you have deliberately
chosen.

**Never test a restore against the production database.** The command refuses
when it detects a format mismatch, but the safe procedure is not to point it
there at all.

### 7.4 Schedule it

The application contains no scheduler entry for `vira:backup` — `routes/console.php`
registers only `inspire`. Scheduling is the host's responsibility:

```cron
# Run at 02:15 daily; the command prunes per VIRA_BACKUP_KEEP_* only after success.
15 2 * * * cd /var/www/viracrm && php artisan vira:backup >> /var/log/vira-backup.log 2>&1
```

This corresponds to **REQ-12.07, which is `BLOCKED`** in the traceability register
because no cron daemon exists on the development host — meaning this line has
never been executed anywhere. It is a documented procedure, not a verified one.
Confirm it actually ran before relying on it:

```bash
systemctl status cron
tail -20 /var/log/vira-backup.log
ls -lt /var/backups/viracrm | head
```

A crontab line that nothing executes is the failure mode this warning exists to
prevent.

---

## 8. First run

1. Open `https://crm.example.com` — you should be redirected to `/login`.
2. Sign in as `admin` with the bootstrap secret from §5.1.
3. You are forced to change the password. Do it.
4. Confirm the sidebar renders: «داشبورد», «مشتریان», «قراردادها», «گزارشها» — and
   that **nothing says «بهزودی»**.
5. Open «کاربران و نقشها» and confirm the roles screen lists four roles.
6. Create one customer by hand. This is a smoke test, not sample data — delete it
   afterwards if you prefer an empty install.

### 8.1 Smoke list

| Check | Expected |
|---|---|
| `GET /login` | 200 |
| `GET /` unauthenticated | redirect to `/login` |
| `GET /.env` | 403 or 404 |
| Response headers | CSP, X-Frame-Options, X-Content-Type-Options from the app; HSTS from nginx |
| Session cookie | `Secure`, `HttpOnly`, `SameSite=Lax` |
| «گزارشها» → contracts report | 200, not an empty stub |
| `php artisan vira:backup` | `backup written and verified` |
| `/audit` as superadmin | rows present, including today's login |

---

## 9. Rollback

### 9.1 Code

```bash
cd /var/www/viracrm
git checkout ⟨previous-good-sha⟩
composer install --no-dev --prefer-dist --no-progress
php artisan config:cache
php artisan route:cache
systemctl reload php8.3-fpm
```

### 9.2 Database

Migrations are additive by convention, and this release adds no destructive
change, so a code rollback is usually sufficient. If a migration must be undone:

```bash
php artisan migrate:rollback --step=1 --force
```

**Take a verified backup immediately before any rollback that touches the
schema.** `migrate:rollback` drops columns and tables; there is no undo beyond
the backup.

### 9.3 Verify the rollback

Re-run §8.1. A rollback that leaves the smoke list failing is a second incident,
not a recovery.

---

## 10. Known-open gates

These are open at `d421c39`. None is closed by this runbook, and each must be
closed by someone with the access to do it.

| # | Gate | Status | Owner | How to close |
|---|---|---|---|---|
| 1 | `composer.lock` absent | **BLOCKED** | Infrastructure | Run §2.1 on a host with egress to `repo.packagist.org`; see `docs/COMPOSER_LOCK_ACCEPTANCE.md` |
| 2 | MySQL backup success untested against a real server | **BLOCKED** | Whoever provisions the DB host | Run §7.1 on the target host with `mysqldump` installed; inspect the dump for `INSERT INTO` rows |
| 3 | Backup scheduling (`REQ-12.07`) | **BLOCKED** | Infrastructure | Install cron, add §7.4, confirm it fires |

Additionally:

- **MySQL restore into a live server is not implemented.** It is refused
  explicitly, not silently mishandled. Restoring uses the `mysql` client (§7.3).
- **The credential vault key cannot be rotated without re-encrypting every
  stored credential.** Plan a rotation procedure before storing many.

## 11. Decision

The deployment procedure above is complete and every step is executable on a
suitable host. That is not the same as the system being cleared to cut over.

Two of the three open gates are reproducibility gates rather than functional
ones — the application behaves correctly today — but both mean the deployment
cannot yet be shown to install the same tree or take a verified MySQL backup.
A cutover performed with those open is a decision taken with known gaps, not an
oversight, and it must be recorded as such.

**Current verdict: NOT PRODUCTION READY.**
