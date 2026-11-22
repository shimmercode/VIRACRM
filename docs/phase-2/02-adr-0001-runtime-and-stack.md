# ADR-0001 — Runtime, stack and the Composer constraint

| | |
|---|---|
| Status | ✅ Accepted |
| Date | 2026-11-22 |
| Context | Phase 2-B, first executable code |
| Supersedes | Nothing (extends ADR in `docs/phase-1/01-architecture-decision-record.md`) |

---

## Context

Phase 2 requires a real, running application, not a design document. That means
the project must execute: migrations must run, Eloquent must query, validation
must reject bad input, and tests must actually be green.

The development environment imposes three hard facts that were measured, not
assumed:

| Fact | Evidence |
|---|---|
| No native PHP, no Composer binary | `which php composer` → nothing |
| **Packagist is unreachable** | `curl repo.packagist.org` → HTTP 000 |
| GitHub, npm, PyPI are reachable | HTTP 200 |

`composer install` is therefore impossible here. Any claim that we "installed
Laravel with Composer" in this environment would be false.

---

## Decision

### 1. Vendor tree is resolved from GitHub, not Packagist

`tools/resolve-vendor.py` walks `composer.json` dependency graphs, maps Composer
package names to GitHub repositories, selects tags that satisfy the declared
constraints, downloads tarballs from `codeload.github.com`, and generates a
PSR-4 autoloader. Result: **74/74 packages**.

**This is a development-only workaround.** On the client host the project is
installed with real Composer against Packagist. `composer.json` stays the
source of truth for production.

### 2. Laravel pinned to 12.40.0

The resolver initially selected `v12.73.0`, which **does not boot**:

```
Fatal error: Trait "Illuminate\Support\Traits\ReflectsClosures" not found
  in …/Illuminate/Container/Container.php on line 25
```

In 12.73.0 the trait moved to `Illuminate\Reflection\Traits\` but `Container.php`
still imports the old path. `v12.40.0` contains zero references to that trait
and boots cleanly.

**Consequence:** `composer.json` pins `"laravel/framework": "12.40.0"` rather
than `^12.0`. On the host, Composer would otherwise be free to pull a 12.x
release with the same defect. This pin is revisited at Phase 2-F.

### 3. PHP 8.2 target, PHP 8.3.36 in development

The host decision rule from Phase 1 is unchanged:
* host PHP 8.2 → Laravel 12 + `spatie/laravel-permission` 6.21.0
* host PHP ≥ 8.3 → Laravel 13 + `spatie/laravel-permission` 7.4.2

Development runs PHP **8.3.36** (WASM). Code is written to the 8.2 baseline, so
either host branch works.

### 4. Encryption on OpenSSL AES-256-GCM, not libsodium

The first implementation used `sodium_crypto_secretbox`. It failed the moment
it ran, because **this PHP build has no sodium extension**. Investigation
showed `openssl` is present (and `pdo_mysql`, `mysqli`, `gd`, `zip`, `intl` are
absent/present as noted in the evidence report).

Beyond this sandbox, `sodium` is an *optional* extension and is frequently
missing on shared hosting, while `openssl` is nearly always compiled in. The
client host is shared hosting. **Choosing the extension that is actually present
on the target beats the more elegant one that fails at runtime.**

Final design: AES-256-GCM, 96-bit random nonce per record, 32-byte key held
outside the document root, `v1:` payload prefix for future rotation.

### 5. Tests run on SQLite; MySQL is explicitly untested

No MySQL server binary exists in the environment (checked `mysqld`, `mariadbd`,
`docker`). SQLite is used for the schema tests.

The migration files contain **no SQLite-specific SQL** and are written for
portability, but this is a claim to be verified on the host, not a tested fact.
It is recorded as such in the test catalog (`caveat` field on T-CAL-07) and in
`docs/phase-2/01-runtime-evidence.md`.

---

## Consequences

**Positive**
* The product genuinely executes: kernel bootstrap, routing, Blade, Eloquent,
  Schema builder, Validator, Carbon and 238 assertions across 5 test classes.
* Real bugs were found and fixed by tests that would never have surfaced in a
  document review (see below).
* The encryption algorithm now matches what the target host can actually do.

**Negative / accepted costs**
* `vendor/` is a resolved tree, not a Composer-installed one; `composer.lock`
  is absent. It must be generated on the host at installation time.
* No PHPUnit. A `ViraTestCase` shim provides PHPUnit-compatible assertions so
  the same test bodies run under real PHPUnit on the host.
* MySQL behaviour (collation, index prefix limits, `TEXT` vs `VARCHAR` sizing)
  remains unverified until Phase 2-F.

**Neutral**
* `laravel/tinker` was dropped: it requires `psy/psysh`, which could not be
  resolved. Tinker is a developer convenience, not a runtime dependency.

---

## Bugs this setup already caught

Recorded because they justify the cost of building the harness:

1. **PHP operator precedence** — `$negative = ($a < 0) xor ($b < 0);` assigns
   `($a < 0)` and discards the rest, because `=` binds tighter than `xor`.
   Every rate-based money calculation returned **0 rial**. Caught by
   `MoneyTest::roundingHalfAwayFromZero`; fixed by parenthesising the whole
   expression.
2. **Sodium unavailable** — encryption was unrunnable until the algorithm was
   changed. A design-only review would have shipped a feature that fatals on
   first use.
3. **Empty-ciphertext edge case** — length guard used `<=` where `<=` is wrong
   for empty plaintext; found by asserting that an empty secret round-trips.
4. **Credential parser accepted malformed grouping** — `Money::parse('1,2,3')`
   returned 123. A typo would become a wrong invoice total.
5. **`SODIUM_*` in a class constant** — constants cannot reference runtime
   constants at compile time; produced a fatal the moment the class loaded.

---

## Alternatives rejected

| Alternative | Why rejected |
|---|---|
| Hand-written framework | Re-implements auth, ORM, validation and migrations; more risk, no benefit |
| php-wasm "results" as product proof | Explicitly excluded by the client's testing rule |
| Wait for Composer | Blocks all of Phase 2 on an environment limitation |
| libsodium via PECL | Cannot install extensions; and the host may lack it too |
| Store credentials with Laravel's `encrypted` cast only | Works, but hides key rotation and audit hooks; we need fingerprints and explicit failure semantics |

---

## Verification

```bash
/tmp/venv/bin/python tools/resolve-vendor.py     # 74/74 packages
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node docs/tools/php-cli.mjs tools/verify-vendor.php   # 8/8 checks PASS
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node docs/tools/php-cli.mjs tools/migrate.php --fresh  # 9/9 migrations, 46 tables
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node docs/tools/php-cli.mjs tests/run.php             # 5/5 classes, 238 assertions
```
