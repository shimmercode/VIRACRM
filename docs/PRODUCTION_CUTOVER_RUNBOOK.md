# Production Cutover Runbook

**Date:** 2026-11-22 · **Branch:** `arena/3b43de84-viracrm`
**Status:** Do **not** execute. Three of six gates are BLOCKED.

> This runbook is the gated cutover procedure. It is distinct from
> `docs/CUTOVER_RUNBOOK.md`, which is the operator's step-by-step for the day and
> is written to be executable. **This** document is the gate sequence: what must
> be true, and proven, before any of those steps may be run in production.

---

## 0. Gate status

| Gate | Status | Must be closed before |
|---|---|---|
| 1 — Composer reproducibility | **BLOCKED** | any install |
| 2 — MySQL backup/restore | **BLOCKED** | relying on backups |
| 3 — Scheduled backup | **BLOCKED** | relying on backups |
| 4 — TLS and proxy | **PARTIAL** | exposing the app |
| 5 — Requirement closure | **PASS** | — |
| 6 — Final regression | **PASS** | — |

**No stage below may be executed while its prerequisite gate is BLOCKED.** The
stages are ordered so that each has a verifiable precondition, not so that they
can be skipped.

---

## 1. Stage 0 — Preconditions

Nothing in this runbook starts until these are true.

```bash
# The final SHA, and a clean tree at it.
git rev-parse HEAD
git status --porcelain          # must be empty

# Gate 5 and 6 artefacts present.
ls docs/FINAL_INFRASTRUCTURE_GATE_REPORT.md docs/FINAL_REQUIREMENT_CLOSURE.md

# The regression passed at this SHA, unpiped, evidenced.
node tools/checks/run-all.mjs > /tmp/regression.log 2>&1; echo "RUNNER_EXIT=$?" | tee /tmp/regression.exit
```

**Stop if `RUNNER_EXIT` is not 0**, or if the tree is dirty at the SHA you plan to
deploy. A deployment is a *specific commit*, never "the branch".

---

## 2. Stage 1 — Build host: reproducible install (Gate 1)

Prerequisite: a host with egress to `repo.packagist.org` or an approved mirror.

```bash
composer validate --strict
composer update --no-install --with-all-dependencies    # writes composer.lock
rm -rf vendor
composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
php artisan about
node tools/checks/composer-lock-acceptance.mjs         # must exit 0 with a lock present
```

Commit `composer.lock`, then re-run Stage 0's regression **against the tree
installed from that lock** — the installed dependency tree has changed, so the
previous green run no longer describes it.

**Gate 1 closes** when a committed `composer.lock` installs a tree that boots and
passes the full regression.

---

## 3. Stage 2 — Database host: MySQL accepted (Gate 2)

Prerequisite: MySQL 8.0+/MariaDB 10.6+, client tools, `proc_open` enabled.

```bash
mysql --version && mysqldump --version
php -r 'var_dump(function_exists("proc_open"));'       # must be true AND work

# The acceptance procedure, exactly:
#   docs/MYSQL_PRODUCTION_BACKUP_ACCEPTANCE.md §5
```

The single non-negotiable assertion: **a backup must be shown to contain actual
records and to restore into a separate empty schema with matching per-table row
counts and zero orphan rows.** A container whose name looks right is not
evidence — that is precisely the defect this stack was rebuilt to eliminate.

**Gate 2 closes** when §5 of that document passes end to end.

---

## 4. Stage 3 — Schedule accepted (Gate 3)

Prerequisite: cron installed and running.

```bash
echo '15 2 * * * cd /var/www/viracrm && php artisan vira:backup >> /var/log/vira-backup.log 2>&1' | crontab -
systemctl status cron
# WAIT for the daemon. Two cycles.
#   docs/SCHEDULED_BACKUP_ACCEPTANCE.md §5
```

Running the command by hand proves the command. Only the daemon firing proves the
**schedule**, and «منظم» (REQ-10.09) is about regularity.

**Gate 3 closes** when two daemon-triggered runs appear in the log with matching
container timestamps, retention has behaved, and the destination is outside
`public/`.

---

## 5. Stage 4 — Edge host: TLS and proxy accepted (Gate 4)

Prerequisite: DNS pointing at the host, a CA-issued certificate, and a proxy.

```nginx
# root must be public/ — never the repository root
root /var/www/viracrm/public;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

```bash
curl -sI https://⟨host⟩/login | grep -iE 'strict-transport|content-security|x-frame|x-content-type'
curl -s -o /dev/null -w '%{http_code}\n' https://⟨host⟩/.env      # 403 or 404
# TRUSTED_PROXIES set to the proxy address, never '*'; then log in and confirm
# the audit row records the CLIENT ip, not the proxy's.
```

**Gate 4 closes** when HSTS is served by the terminator, the app's headers survive
the hop, `.env` is unreachable, and the recorded client IP is the real client —
which is the one assertion this environment could never make
(`docs/TLS_PROXY_ACCEPTANCE.md` §3).

---

## 6. Stage 5 — Application deployment

Only after Stages 1–4. Follow `docs/CUTOVER_RUNBOOK.md`:

| § | Step |
|---|---|
| §2 | code + `composer install` from the committed lock |
| §3 | database and grants (utf8mb4, dedicated user, never root) |
| §4 | `.env`, both keys, `TRUSTED_PROXIES`, `ASSET_URL` |
| §5 | `migrate --force`, `db:seed --force`, capture the bootstrap admin password |
| §6 | TLS, document root, header check |
| §7 | first backup — and prove it restores, on a throwaway database |
| §8 | first run and the smoke list |
| §9 | rollback |

The two secrets are generated on the host and recorded offline immediately:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'   # VIRA_CREDENTIAL_KEY — never leaves the server
php artisan vira:backup-key                                # VIRA_BACKUP_KEY — travels with backups
```

They must differ. A leaked backup must not be a credential export.

---

## 7. Stage 6 — Post-cutover verification

```bash
# Smoke, from outside the host
curl -sI https://⟨host⟩/login | head -1
# .env unreachable, HSTS present, cookie Secure/HttpOnly/SameSite
# log in as admin, forced password change, sidebar renders, nothing says «به‌زودی»
# create one customer, then a backup, then verify the backup restores
```

| Check | Expected |
|---|---|
| `GET /login` | 200 over HTTPS |
| `GET /` unauthenticated | redirect to `/login` |
| `GET /.env` | 403/404 |
| Headers | HSTS (nginx) + CSP/XFO/XCTO (app) |
| Session cookie | `Secure`, `HttpOnly`, `SameSite=Lax` |
| `php artisan vira:backup` | `backup written and verified`, format `mysqldump (mysql)` |
| Restore test | into a throwaway database, matching counts |
| `/audit` | today's login present, client IP correct |
| Cron | a container with tomorrow's datestamp |

---

## 8. Rollback

```bash
cd /var/www/viracrm
git checkout ⟨previous-good-sha⟩
composer install --no-dev --prefer-dist --no-progress   # from the committed lock
php artisan config:cache && php artisan route:cache
systemctl reload php8.3-fpm
```

Take a **verified** backup before any schema rollback:
`migrate:rollback` drops columns, and the only undo is the backup.

---

## 9. Decision rule

Only declare **PRODUCTION READY** when all six gates have passed with recorded
evidence at the deployed SHA.

Otherwise the verdict is:

- **NOT PRODUCTION READY — TECHNICAL BLOCKERS** — a defect in the software;
- **NOT PRODUCTION READY — EXTERNAL INFRASTRUCTURE BLOCKERS** — the software is
  done, the host does not exist.

**Current verdict: NOT PRODUCTION READY — EXTERNAL INFRASTRUCTURE BLOCKERS.**

Completed code is not validated production capability. The application passes 74
suites and its requirement register is accurate; that says nothing about whether a
lock file exists, whether a MySQL dump contains records, or whether a cron daemon
ever fired. Those three are the blockers, and none of them is a code change.
