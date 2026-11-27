# Composer Lock Acceptance

**Date:** 2026-11-22
**Repository state:** `10ca096` (branch `arena/3b43de84-viracrm`)
**Check:** `node tools/checks/composer-lock-acceptance.mjs`
**Verdict:** **BLOCKED** — no `composer` binary and no reachable package repository.

Phase 3 asked for a legitimate `composer.lock` produced from real dependencies
against Packagist or an approved mirror, never fabricated metadata, then
validated, clean-installed, booted and regression-tested.

Three of those four steps cannot run here. This document records exactly which,
why, and what a host with egress must do. It does **not** contain a lock file,
because a lock file is the one artefact in this task that cannot be produced by
careful work in the wrong environment — only by a resolver reading a real index.

## 1. The artefact is absent, and that is the correct state

```
$ python3 -c "import os; print(os.path.exists('composer.lock'))"
False
```

`composer.lock` is not in the tree at `10ca096`. `composer.json` declares:

| Requirement | Constraint |
|---|---|
| `php` | `^8.2` |
| `laravel/framework` | `12.40.0` (pinned) |
| `mpdf/mpdf` | `^8.2` |
| `fakerphp/faker` | `^1.23` |
| `laravel/pail` | `^1.2.2` |
| `laravel/pint` | `^1.13` |
| `laravel/sail` | `^1.41` |
| `mockery/mockery` | `^1.6` |
| `nunomaduro/collision` | `^8.6` |
| `phpunit/phpunit` | `^11.5.3` |

`laravel/framework` is pinned to an exact version for the production requirement;
the rest are caret ranges, which is what makes the lock file load-bearing: without
it, `composer install` on the deployment host resolves those ranges afresh and
can install a different tree than the one that was tested.

## 2. Why it could not be produced — probe results

### 2.1 No Composer binary

```
$ command -v composer
composer: NOT INSTALLED
```

Installing it normally means fetching `https://getcomposer.org/installer`, which
is unreachable (§2.2). There is also no PEAR/PHP phar route that does not start
with that URL.

### 2.2 No reachable package repository

Every endpoint a Composer install could use was probed. The pattern matters:
**TCP connects, TLS is terminated by the egress boundary.** That is a network
policy, not a Composer misconfiguration and not a DNS failure — retrying,
changing mirrors, or disabling TLS verification would not help, and disabling
TLS verification would be the wrong thing to do regardless.

| Endpoint | Result |
|---|---|
| `https://repo.packagist.org/packages.json` | `SSL_ERROR_SYSCALL` immediately after Client hello (connected to 143.244.50.86:443) |
| `https://packagist.org/packages.json` | no response (HTTP 000) |
| `https://getcomposer.org/installer` | no response (HTTP 000) |
| `https://mirrors.aliyun.com/composer/packages.json` | no response (HTTP 000) |
| `https://packagist.phpcomposer.com/packages.json` | no response (HTTP 000) |
| `https://repo.huaweicloud.com/repository/php/packages.json` | no response (HTTP 000) |
| `https://api.github.com/repos/laravel/framework` | **200** — allowed, but it is a source API, not a package index |
| `https://registry.npmjs.org/` | **200** — allowed, and irrelevant to PHP dependency resolution |

The full TLS trace, abbreviated:

```
* Connected to repo.packagist.org (143.244.50.86) port 443 (#0)
* TLSv1.3 (OUT), TLS handshake, Client hello (1):
* OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to repo.packagist.org:443
* Closing connection 0
```

`api.github.com` being reachable does not help. One could in principle construct
a lock file by walking GitHub releases for every transitive dependency, but that
is not what a lock file is: it would have to encode Composer's `content-hash`,
its platform resolution, and a per-package dist URL that Composer itself would
have chosen. Hand-assembling it is exactly the fabrication the task forbids.

### 2.3 The existing `vendor/` is not Composer output

This is the detail most likely to mislead. `vendor/` exists and is 131 MB, which
looks like a solved dependency tree. It is not usable as a source of lock
metadata:

```
vendor/
  autoload.php
  brick--math/              ← flattened: "brick/math" with the slash as "double dash"
  carbonphp--carbon-doctrine-types/
  laravel--framework/
  ...
```

- There is **no** `vendor/composer/` directory at all — no `installed.json`, no
  `installed.php`, no `platform_check.php`.
- The directory names encode `vendor/package` with `--` in place of `/`
  (`laravel--framework`, not `laravel/framework`). Real Composer produces nested
  directories.
- `vendor/` is listed in `.gitignore` (`.gitignore:2`), so this tree is not part
  of the repository and was assembled in the sandbox to make the application
  bootable under php-wasm.

A directory of code cannot be back-converted into a lock file: a lock file needs,
per package, `dist.url`, `dist.type`, `source.reference` (the commit) and a
`content-hash` over `composer.json`. A flattened copy of extracted files carries
none of these. Reading version strings out of each package's `composer.json` would
recover only the version, and would still leave every provenance field to be
invented.

## 3. What was deliberately NOT done

**No `composer.lock` was written.** Producing one from `composer.json` alone would
mean inventing:

- 20–40 transitive package versions (only the ten direct requirements are stated);
- a `dist.url` per package of the form `https://api.github.com/repos/…/zipball/<sha>`;
- a `source.reference` commit per package;
- the `content-hash`, which is Composer's own hash over a normalised
  `composer.json` and cannot be computed without its algorithm.

A fabricated file of this kind is worse than an absent one in three ways. It
passes a glance by looking like the right shape. It fails `composer install` on
the deployment host, at which point the operator cannot tell whether the file is
subtly wrong or the host is misconfigured. And it converts "we cannot currently
prove the dependency tree is reproducible" — a true, actionable statement — into
an artefact that asserts reproducibility that was never established. The task
forbids fabricated lock metadata explicitly; this is the reasoning behind that
constraint.

## 4. To close this item

Run the following on a host with outbound access to `repo.packagist.org` (or an
approved internal mirror), from the repository root:

```bash
# 1. Sanity-check composer.json before resolving anything.
composer validate --strict

# 2. Resolve and write the lock file WITHOUT installing, so the lock can be
#    reviewed on its own terms. --with-all-dependencies includes dev packages.
composer update --no-install --with-all-dependencies

# 3. Confirm the lock actually installs a tree, from scratch, reproducibly.
rm -rf vendor
composer install --no-dev --prefer-dist --no-progress

# 4. Confirm the application boots on the installed tree.
php artisan about

# 5. Re-run the validator against the new lock file.
node tools/checks/composer-lock-acceptance.mjs
```

Step 5 then checks that every direct requirement appears in the lock at a
satisfying version, and that every package carries a `dist.url` or
`source.reference`. It reports `content-hash` as UNVERIFIED rather than
pretending to recompute it — recomputing it requires Composer, and the point of
running step 2 is that Composer produced it.

**Responsible party:** whoever provisions the build or deployment host, i.e. the
infrastructure owner. This is not a code change; nothing in the application
needs to change for the item to close.

**When it closes:** commit `composer.lock`, then re-run the roster. Until then the
`composer-integrity` check will continue to report the absence.

## 5. Effect on the readiness decision

This is one of the gates that keeps the verdict at **NOT PRODUCTION READY**. It is
a *reproducibility* gate, not a functional one: the application runs today on the
sandbox's hand-assembled `vendor/`, and every behavioural suite passes against
it. What is missing is the guarantee that a deployment installs the same tree.

A deployment performed without a lock file is not merely unproven — it is
non-deterministic: the same `composer.json` can install different resolved
versions on different days, so a passing regression on one host says less about
the next. That is why this is reported as BLOCKED rather than "accepted with a
caveat".

## 6. Re-running

```
node tools/checks/composer-lock-acceptance.mjs    # BLOCKED → exit 0; FAIL → exit 1
```

Exit 0 means either "a valid lock file is present and complete" or "BLOCKED and
documented here". It is deliberately not registered as a *failing* roster entry,
because an absence that is correctly explained is not a regression; it is a
recorded open gate.
