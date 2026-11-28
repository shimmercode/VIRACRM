# TLS and Proxy Acceptance

**Gate 4 of the final infrastructure gate.**
**Date:** 2026-11-22 · **Status:** **PARTIAL** — application behaviour proven
against a real TLS terminator; `TRUSTED_PROXIES` not exercisable in this
environment.

---

## 1. Decision

| Half of the gate | Result |
|---|---|
| Application TLS/proxy **behaviour** | **PASS** — 10/10, against a real TLS terminator |
| `TRUSTED_PROXIES` behind a **real** proxy | **BLOCKED** — the harness has no reverse proxy and no server-process PHP |
| Deployment TLS (CA certificate, HSTS, redirect on a real host) | **BLOCKED** — no deployment host exists |

This is **not** deployment TLS acceptance. There is no public DNS name for this
application, no certificate issued for one, and no load balancer. Nothing here
establishes that production serves HTTPS correctly, and this document does not
claim it.

What it does establish is the half that lives in the code, which is worth having
because the four ways a proxy setup fails are invisible until it is behind one.

---

## 2. What was proven

`tools/checks/tls-proxy.mjs` — **10/10**, in the roster as a required suite.

### 2.1 Method

A real TLS terminator was built for this gate rather than simulated:

1. `openssl req -x509 -newkey rsa:2048 …` generates a certificate for this run.
   Self-signed, because no CA can issue for a hostname that does not exist.
2. Python's `ssl` module terminates TLS and forwards to the application over
   plain HTTP with `X-Forwarded-Proto: https`, `X-Forwarded-For`,
   `X-Forwarded-Port` and `X-Forwarded-Host` — what nginx `proxy_pass` does.
3. Assertions are made on what the application actually produced.

The TLS case uses `curl -k`. That skips **identity verification of a
self-signed certificate**, not the handshake, the key exchange or the encryption.
Curl reported `ssl_verify_result=18` (self-signed) with HTTP 200 — the hop is
real; only the identity is unissuable.

### 2.2 Results

| Case | Result |
|---|---|
| `TLS-CERT` | certificate generated for the run |
| `TLS-A-PLAIN-NOT-SECURE` | plain HTTP → session cookie **not** `Secure` |
| `TLS-B-TLS-SECURE-COOKIE` | TLS-terminated + `SESSION_SECURE_COOKIE=true` → **`Secure`** |
| `TLS-C-REAL-HANDSHAKE` | real handshake reaches the app, **200** |
| `TLS-C2-HEADERS-OVER-TLS` | CSP, X-Frame-Options, X-Content-Type-Options present over TLS |
| `TLS-D-HEADERS` | 5 application security headers on a real response |
| `TLS-E-NO-HSTS-FROM-APP` | the **application does not emit HSTS** |
| `TLS-F-COOKIE-FLAGS` | session cookie carries `HttpOnly`, `SameSite=lax` |
| `TLS-G-TRUSTED-PROXIES-UNEXERCISED` | recorded explicitly (§3) |
| `TLS-CLEANUP` | app and terminator stopped |

`TLS-B` and `TLS-A` together are the meaningful pair: the application honours
`SESSION_SECURE_COOKIE` rather than either always setting `Secure` or never
setting it.

---

## 3. What could not be proven, and why

**`TRUSTED_PROXIES` is never exercised in this environment.**

The harness runs no server-process PHP. `tools/dev-server.mjs` hands each request
to a php-wasm child as a JSON spec, and — in its own words — *"The PHP-WASM
runtime does not inherit the parent environment (getenv() returns false for
everything)"*. It forwards only `DB_DATABASE` and `VIRA_CREDENTIAL_KEY`.

`tools/http-render.php` then sets `$_SERVER['HTTPS'] = 'on'` itself when told the
request was TLS-terminated. So `$middleware->trustProxies(at: …)` in
`bootstrap/app.php` — the code that decides whether to believe
`X-Forwarded-For` **at all** — never runs. There is no proxy, so there is nothing
to trust or distrust.

This is asserted as a visible case (`TLS-G-…`) rather than left as a comment, so
it cannot be forgotten when the deployment host exists.

### 3.1 The four failure modes this leaves unverified

`TRUSTED_PROXIES` exists to prevent four specific outcomes. None can be observed
from here:

| Mode | Symptom |
|---|---|
| Client IP misread | `users.last_login_ip` and `audit_logs.ip` record the proxy |
| Rate limiting collapsed | every user counts as one address, so one abuser locks out all |
| `Secure` suppressed | TLS is terminated upstream, app sees `http://`, cookie ships without `Secure` |
| URL scheme wrong | generated URLs are `http://`, and a downgrade leaks the session |

The configuration reads correctly in `bootstrap/app.php` (uses `at:`, the correct
parameter name, with all four `HEADER_X_FORWARDED_*` flags), and the empty case is
handled (`null` when unset). **Reading correctly is not behaving correctly**, and
this document does not conflate them.

### 3.2 A related constraint worth recording

`intl` is absent from the PHP build. Symfony's console formatter fatals inside its
own error rendering, so any exception in a CLI probe can vanish instead of
printing. This is why several probes in this repository catch and print
themselves. It does not affect a real deployment (a normal PHP build has `intl`),
but it does mean error output from this environment should never be trusted to be
complete.

---

## 4. Closing the gate

On the deployment host, behind nginx or a load balancer.

### 4.1 TLS termination and HSTS

The application sets seven security headers and deliberately **not** HSTS. HSTS
belongs at the TLS terminator — the only place that knows the request arrived
over HTTPS. If HSTS is missing in production, the application will not supply it;
the nginx directive must be added:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Verify:

```bash
curl -sI https://crm.example.com/login \
  | grep -iE 'strict-transport|content-security|x-frame|x-content-type'
# expect four lines; the last three come from the app, HSTS from nginx
```

### 4.2 Redirect

```bash
curl -sI http://crm.example.com/login | head -3        # expect 301/308 to https
```

### 4.3 Trusted proxies and real client IP

```bash
# TRUSTED_PROXIES must be set to the proxy's actual address — never '*'
grep TRUSTED_PROXIES /var/www/viracrm/.env

# With the proxy in place, log in and check what was recorded:
php artisan tinker --execute='echo App\Models\AuditLog::where("event","login")->latest()->first()->ip;'
# expect the CLIENT address, not the proxy's
```

### 4.4 Secure cookie and other flags

```bash
curl -sI https://crm.example.com/login | grep -i '^set-cookie' 
# expect: Secure; HttpOnly; SameSite=Lax
```

### 4.5 Document root

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://crm.example.com/.env
# expect 403 or 404 — never 200
```

---

## 5. Pass criteria

| Criterion | Requirement |
|---|---|
| TLS terminates | valid CA-issued certificate, no browser warning |
| HTTP redirects | 301/308 to https |
| HSTS present | `max-age=31536000` or similar, from nginx |
| Security headers | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| Trusted proxy | set to the real proxy address, never `*` unless every hop is controlled |
| Client IP | audit and `last_login_ip` show the client, not the proxy |
| Cookie | `Secure`, `HttpOnly`, `SameSite=Lax` |
| `.env` unreachable | 403/404 |
| Rate limiting | a single address cannot lock out other users |

---

## 6. Verdict

**PARTIAL.** The application's TLS-facing behaviour is proven against a real
terminator (10/10). The proxy half needs the deployment host, and the deployment
half needs a certificate that cannot be issued for a hostname that does not
exist.

**Owner:** infrastructure (nginx/load balancer, DNS, certificate), with the
network team for proxy topology.
**Blocks production:** yes — but it is the least risky of the three external
blockers, because the application side is done and the remaining work is standard
host configuration.
