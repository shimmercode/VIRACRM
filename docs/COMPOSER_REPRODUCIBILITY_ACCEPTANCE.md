# Composer Reproducibility Acceptance

**Gate 1 of the final infrastructure gate.**
**Date:** 2026-11-22 · **Status:** **BLOCKED**

No `composer.lock` exists and none was fabricated. This document records every
route attempted, the exact failure at each, and the commands that close the gate
on a host with network access.

---

## 1. Decision

**BLOCKED — external infrastructure blocker.** There is no `composer` binary and
no reachable PHP package registry. This is not a code defect and nothing in the
application needs to change.

Consequence, stated precisely: `composer install` without a lock file resolves
the caret ranges in `composer.json` afresh on every install. Two hosts installing
on different days can get different trees, so a passing regression on one host
says less about the next. The dependency tree is **unproven reproducible**, not
merely unverified.

---

## 2. Environment

| Item | Value |
|---|---|
| `command -v composer` | not found |
| PHP | 8.3.36 (php-wasm) |
| PHP package registry reachable | **no** |
| `vendor/` present | yes, 131 MB, **not** Composer output (§5) |
| `composer.lock` present | **no** |

---

## 3. Every route attempted

### 3.1 Registries

| Endpoint | Result |
|---|---|
| `https://repo.packagist.org/packages.json` | `SSL_ERROR_SYSCALL` after Client hello |
| `https://packagist.org/packages.json` | HTTP 000 |
| `https://getcomposer.org/versions` | HTTP 000 |
| `https://getcomposer.org/installer` | HTTP 000 |
| `https://mirrors.aliyun.com/composer/packages.json` | HTTP 000 |
| `https://packagist.phpcomposer.com/packages.json` | HTTP 000 |
| `https://repo.huaweicloud.com/repository/php/packages.json` | HTTP 000 |
| `https://api.github.com/...` | **200** — allowed, but a source API, not an index |
| `https://registry.npmjs.org/` | **200** — allowed, irrelevant to PHP |

Exact error:

```
* Connected to repo.packagist.org (169.150.249.165) port 443 (#0)
* TLSv1.3 (OUT), TLS handshake, Client hello (1):
* OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to repo.packagist.org:443
curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL
```

TCP connects and TLS is terminated at the egress boundary. Not DNS, not routing,
not a Composer configuration problem.

### 3.2 Composer from source — attempted, not viable

`github.com` is reachable, so the real thing was cloned:

```bash
git clone --depth 1 --branch 2.11.3 https://github.com/composer/composer.git /tmp/composer-src
# succeeded — src/, bin/compile present
```

It cannot be run. `bin/compile` builds the phar from `vendor/`, and `vendor/` is
produced by Composer resolving against Packagist. The route is circular.

### 3.3 Official phar — found, then blocked

`api.github.com` lists the genuine asset:

```
tag: 2.11.3
 asset: composer.phar        3,820,169 bytes
 asset: composer.phar.asc    589 bytes
```

Downloading fails at the redirect:

```
302 -> https://release-assets.githubusercontent.com/github-production-release-asset/...
curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to
            release-assets.githubusercontent.com:443
```

`release-assets.githubusercontent.com` is not allowlisted, and **every** GitHub
release asset is served from it. This closes the release-asset route generally,
not only for Composer.

### 3.4 npm — a name collision, not a route

`npm view composer version` returns `4.1.0`. That is a JavaScript library that
happens to share the name. Installing it would not produce PHP Composer, and
claiming otherwise would be a fabricated gate.

---

## 4. Why no lock file was written

A `composer.lock` is not a list of names. Per package it carries:

- `version`
- `dist.url` and `dist.type`
- `source.reference` — the commit
- a top-level `content-hash` computed by Composer over a normalised
  `composer.json`

`composer.json` declares **ten** direct requirements. The transitive tree is not
stated anywhere in the repository. Producing a lock file from it would require
inventing:

- 20–40 transitive versions,
- a dist URL per package,
- a source commit per package,
- the `content-hash` — which is Composer's own algorithm and cannot be computed
  without it.

A file of that shape passes a glance, fails `composer validate` and
`composer install` immediately, and — the part that matters — replaces a true
statement ("reproducibility is unproven") with a false one ("reproducibility was
established").

---

## 5. Why `vendor/` cannot be converted

`vendor/` exists and is 131 MB, which looks like a solved tree. It is not
Composer output:

```
vendor/
  autoload.php
  brick--math/                ← "brick/math" flattened, slash → double dash
  carbonphp--carbon-doctrine-types/
  laravel--framework/
```

- **No `vendor/composer/` directory at all** — no `installed.json`, no
  `installed.php`, no `platform_check.php`.
- Directory names flatten `vendor/package` with `--`; real Composer nests.
- `vendor/` is in `.gitignore` (`.gitignore:2`) — not part of the repository,
  assembled in this sandbox to make the app bootable under php-wasm.

Reading each package's own `composer.json` would recover only versions, leaving
every provenance field to be invented. A directory of extracted files is not a
resolution.

---

## 6. Closing the gate

On a host with egress to `repo.packagist.org` (or an approved mirror), from the
repository root:

```bash
# 1. Sanity-check the manifest before resolving anything.
composer validate --strict

# 2. Resolve and WRITE the lock file without installing, so the lock can be
#    reviewed on its own terms.
composer update --no-install --with-all-dependencies

# 3. Prove it installs a tree, from scratch.
rm -rf vendor
composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
composer validate --no-check-publish

# 4. Prove the application boots on that tree.
php artisan about

# 5. Re-run the validator against the new lock.
node tools/checks/composer-lock-acceptance.mjs
```

Step 5 then checks that every direct requirement appears at a satisfying
version, that every package carries a `dist.url` or `source.reference`, and that
no package lacks a source commit. It reports `content-hash` as **UNVERIFIED**
rather than pretending to recompute it — the point of step 2 is that Composer
produced it.

Then commit `composer.lock` and re-run the full regression (Gate 6), since the
installed tree will have changed.

---

## 7. What the validator does today

`tools/checks/composer-lock-acceptance.mjs` is in the roster as a **required**
suite and exits:

- **0** when a valid, complete lock file is present;
- **0** when the file is absent and the absence is documented (this state);
- **1** when a lock file exists but is unparseable, is missing a direct
  dependency, or has a package without provenance.

Marking it required was itself a correction: it was first registered
`required: false`, and `runner-contract` failed the run at 10/11 with
`1 missing required:true`. The roster forbids optional suites because a check
that is quietly optional is indistinguishable from one that was never meant to
run.

---

## 8. Residual risk until closed

| Risk | Effect |
|---|---|
| Non-deterministic installs | The tree on the deployment host may differ from the tested one |
| No lock integrity check | A compromised or mistyped version resolves silently |
| Dev dependencies unbounded | `phpunit`, `pint`, `sail` are caret ranges; a CI run tomorrow may differ from today's |

**Owner:** infrastructure. **Blocking:** yes — one of the three external
blockers keeping the verdict at NOT PRODUCTION READY.
