# SECURITY AND HTTPS DEPLOYMENT — ACCEPTANCE RECORD

**Project:** VIRA CRM — سیستم مدیریت ارتباط با مشتریان ویرا
**Commit under test:** `f73e61743663203a761dff1995627b80f7a4a0a8`
**Date:** 2026-11-27
**Suites:** `security-deployment.mjs` **16/16** · `security-headers.mjs` **16/16**
**Overall status:** **PASS** for the application-layer controls · **BLOCKED** for TLS termination · **FAIL** for one client-IP defect (fixed during this work — see §2)

---

## 1. Summary against the client's checklist

| # | Client requirement | Status | Where proven |
|---|---|---|---|
| 1 | HTTPS redirect and TLS configuration | **BLOCKED** | §7 — no TLS terminator here |
| 2 | Trusted proxies, explicit addresses, never wildcard | **PASS** | §2 |
| 3 | Correct client IP detection | **PASS** | §2 |
| 4 | Secure, HttpOnly, SameSite cookies | **PASS** | §3 |
| 5 | Session invalidation and expiry | **PASS** | §4 |
| 6 | `APP_KEY` and `VIRA_BACKUP_KEY` generation and storage | **PASS** | §5 |
| 7 | No secrets in Git or logs | **PASS** | §6 |
| 8 | Security headers on actual responses | **PASS** | §8 |
| 9 | `Cache-Control: no-store` on sensitive responses | **PASS** | §8 |
| 10 | Backup destination validation, traversal and symlink | **PASS** | §9 |
| 11 | No backup files reachable via the document root | **PASS** | §9 |

---

## 2. Trusted proxies and client IP — a real bug found by testing

This began as an attempt to write tests for a control that was believed to work.
It did not work, and the test harness was hiding it.

### 2.1 The defect

`tools/http-render.php` set the peer address the obvious way:

```php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
```

and then built the request with `Illuminate\Http\Request::create()`, whose sixth
argument is the server bag — passed as `[]`. `Request::create()` **does not read
`$_SERVER`**; it defaults `REMOTE_ADDR` to `127.0.0.1`. So `$request->ip()` was
the loopback address on every request the harness ever made, and any assertion
about `last_login_ip`, audit rows or login throttling would have passed on the
default value no matter what the trusted-proxy configuration did.

The file already contained the correct pattern a few lines away, for HTTPS:

```php
$request->server->set('HTTPS', 'on');
```

Applying the same treatment to the peer address, plus an opt-in `remote_addr`
field on both the renderer and the harness (defaulting to the previous
behaviour, so every existing caller is unaffected), made the control testable.

### 2.2 What the tests then showed

The control distinguishes three cases, and all three now behave correctly:

```
PASS SD-IP-UNSET    TRUSTED_PROXIES unset: a client-supplied X-Forwarded-For: 203.0.113.9
                    is IGNORED and last_login_ip is the real peer 127.0.0.1 — the safe default

PASS SD-IP-TRUSTED  TRUSTED_PROXIES=10.0.0.5 and the request really came from 10.0.0.5:
                    last_login_ip is the forwarded client 203.0.113.9, not the proxy

PASS SD-IP-SPOOF    TRUSTED_PROXIES=10.0.0.5 but the request came from 198.51.100.7:
                    the forged X-Forwarded-For is ignored and last_login_ip is 198.51.100.7
```

`SD-IP-SPOOF` is the one that justifies the "never wildcard" rule, and it is
worth stating plainly: with `TRUSTED_PROXIES=*`, the third case would record
`203.0.113.9` — an address the caller invented. Every IP-based control (throttle
counters, audit attribution) would then be forgeable. The request must arrive
*from a configured proxy* before its forwarding header is believed, and this
test proves that it must.

### 2.3 Configuration

`bootstrap/app.php:46-56` takes an explicit comma-separated list and trusts
nothing when unset:

```php
at: ($proxies = env('TRUSTED_PROXIES')) === null || $proxies === ''
    ? null
    : array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))),
headers: Request::HEADER_X_FORWARDED_FOR | ..._HOST | ..._PORT | ..._PROTO,
```

There is no path to a wildcard: the value is split and passed through
`array_filter`, so `*` would be passed as the literal string `*` to Symfony —
which does **not** interpret it as "trust everyone" for proxy addresses. A
deployment that sets `TRUSTED_PROXIES=*` therefore fails closed, which is the
correct direction.

---

## 3. Cookies

From `security-headers.mjs` (16/16) against real responses from `/login` and `/`:

- `HttpOnly` — set
- `SameSite=Lax` — set
- `Path=/` — set
- `Secure` — defaults to **true when `APP_ENV=production`** and the variable is
  absent (`config/session.php`), and was proven both ways by booting the real
  config with the `.env` line removed: `false` in local, `true` in production.

The `Secure` default is written as a fail-safe rather than `env('SESSION_SECURE_COOKIE', false)`:
forgetting the variable in production must not silently downgrade the cookie.

---

## 4. Session invalidation and expiry

```
PASS SD-SESS-LOGOUT   after POST /logout the same cookie is rejected:
                      / returns 302 → /login
PASS SD-SESS-ROWGONE  authenticated session rows: 1 before POST /logout, 0 after
PASS SD-SESS-EXPIRY   aged to 7800 s ago; fresh cookie works (200), aged cookie
                      is refused (302 → /login)
```

`SD-SESS-ROWGONE` deliberately counts **authenticated** rows, not all rows. A
total row count is identical before and after a logout — the driver destroys the
authenticated row and the follow-up anonymous request immediately creates a new
one — so a total count would have "passed" on a logout that destroyed nothing.
Counting `where user_id is not null` is the assertion that means something.

Expiry was tested by pushing `last_activity` past the 120-minute lifetime
through `tools/checks/session-age.php`, a single-purpose helper that refuses the
shared database and only accepts paths under `.arena/` or `/tmp/`. Reading the
lifetime from config would prove the number exists; ageing a row proves the
driver acts on it.

---

## 5. Key generation and storage

```
PASS SD-KEY-32      base64 decodes to exactly 32 bytes (AES-256 needs 32)
PASS SD-KEY-RANDOM  two runs produce different keys — generated, not a constant
PASS SD-KEY-WORKS   the generated key produces a container, so generation and
                    consumption agree on the format
```

`vira:backup-key` **prints** the key and tells the operator to add it to `.env`;
it does not edit the file. A command that silently rewrites an environment file
can put a production secret in the wrong place, and that risk is not worth the
convenience.

`.env.example` ships `APP_KEY=`, `VIRA_BACKUP_KEY=` and `VIRA_CREDENTIAL_KEY=`
all **empty**, with comments stating they must never be committed or stored in
the database. No key value appears in this report, per the directive.

---

## 6. No secrets in Git or logs

```
PASS SD-LOG-CLEAN  2845054 bytes of log content contains no VIRA_BACKUP_KEY
                   value or assignment
```

`.env` is untracked. No `base64:` key is tracked. The only bcrypt-shaped strings
in the repository are the literal `$2y$12$` prefix in documentation and one
smoke-test fixture — not hashes of real passwords.

---

## 7. HTTPS and TLS termination — BLOCKED

The application cannot prove HTTPS behaviour, because nothing here terminates
TLS. `systemctl` fails to reach a bus and there is no web server.

What *is* verified is the application's half of the contract:

- `config/session.php` sets `Secure` automatically in production (proven).
- The forwarded-protocol header is honoured **only** from a configured proxy
  (`SD-IP-TRUSTED` / `SD-IP-SPOOF`), so a TLS-terminating proxy correctly
  produces `https` URLs and `Secure` cookies while a spoofed header from
  anywhere else cannot.
- The preview-frame exception is opt-in via `VIRA_PREVIEW_EMBED` and named-origin
  only; production keeps `X-Frame-Options: DENY` and `frame-ancestors 'none'`.

**Not verified:** the redirect from `http://` to `https://`, HSTS, TLS version
and cipher policy, and certificate validity. These belong to the reverse proxy.

**Status: BLOCKED. Responsible party:** the client's operations team. Required
when they provision the host:

```nginx
server {
    listen 80;
    server_name vira.example.ir;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl http2;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    ssl_protocols TLSv1.2 TLSv1.3;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-For $remote_addr;   # single, explicit hop
}
```

and in `.env`: `APP_ENV=production`, `SESSION_SECURE_COOKIE` (or rely on the
default), `TRUSTED_PROXIES=<the proxy's address>` — **never `*`**.

---

## 8. Headers and cache control on real responses

`security-headers.mjs` **16/16**, against `/login` and `/`:

| Header | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `same-origin` |
| `X-Permitted-Cross-Domain-Policies` | `none` |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=()` |
| `Content-Security-Policy` | 7 directives, `frame-ancestors 'none'`, `script-src 'self'` **without** `unsafe-inline` |
| `X-Powered-By` | absent |

`Cache-Control: must-revalidate, no-cache, no-store, private` is set on
authenticated data pages — verified on `/customers` (`SD-NOSTORE`), a page that
lists customer records and must never be held by a shared cache.

> **Historical note.** `SecurityHeaders` originally guarded the header with
> `if (! $response->headers->has('Cache-Control'))`, which was always false
> because the framework sets a default first. The directive was effectively dead.
> Removing the guard exposed it; `SH-cache` in `security-headers.mjs` is the
> check that caught it.

---

## 9. Backup destination — traversal, symlink, document root

Covered in full in `docs/BACKUP_RESTORE_ACCEPTANCE.md` §3. Summary:

| Attempt | Result |
|---|---|
| `public/backups`, `public/a/b/c`, `public` | refused, exit 1 |
| `.arena/../public/backups` | refused — `..` resolved before the check |
| symlink → `public/` | refused — `realpath()` judges the landing place |
| destination outside root | accepted |

After every attempt `public/` held only `.htaccess, build, favicon.ico,
index.php, robots.txt`.

---

## 10. Status summary

| Control | Status |
|---|---|
| Proxy trust (explicit, never wildcard) | **PASS** |
| Client IP detection incl. spoof rejection | **PASS** (after fixing a harness defect) |
| Cookie flags | **PASS** |
| Session invalidation and expiry | **PASS** |
| Key generation, storage guidance | **PASS** |
| No secrets in Git or logs | **PASS** |
| Security headers | **PASS** |
| `no-store` on sensitive responses | **PASS** |
| Backup path traversal / symlink / docroot | **PASS** |
| HTTPS redirect, TLS policy, HSTS, certificate | **BLOCKED** — proxy-side |

**Verdict:** the application-layer security controls are **accepted**. Transport
security is **unverified** and must be completed by the reverse proxy
configuration in §7 before the system is exposed publicly.
