# Bootstrap credential policy — the superadmin password

**Status:** Decided and implemented · **Register entry:** OD-01 (closed by this document)
**Implemented in:** `database/seeders/UserSeeder.php` · **Verified:** 2026-11-22

This closes the open decision on `SEED_ADMIN_PASSWORD`. It specifies how the first
superadmin credential is created, how it reaches the operator, and how it stops being
a long-lived secret.

---

## 1. The rule

> **No password for any account is stored in this repository — not in source, not in
> `.env.example`, not in documentation, not in a screenshot, not in a commit.**

That includes development fixtures. Every automated flow generates its own password at
run time inside its own database copy (see `tools/checks/lib/flow-harness.mjs`), so no
fixed credential exists anywhere in the tree.

What *is* in the repository is the **mechanism** for turning a secret chosen by the
operator into a hashed account.

---

## 2. The three provisioning paths

`UserSeeder` resolves the secret in this order. The order is deliberate: the file form
is first because it is the only one where the secret is never visible to another
process.

| # | Mechanism | Use when | Secret visible to |
|---|-----------|----------|-------------------|
| 1 | `SEED_ADMIN_PASSWORD_FILE=/run/secrets/admin_pw` | Production, or any install where a secret manager exists | Only the reading process |
| 2 | `SEED_ADMIN_PASSWORD=…` | Containers/CI where the orchestrator injects env | Every process of the container user, plus `/proc/<pid>/environ` |
| 3 | *(nothing set)* | Interactive install on a terminal the operator is watching | The operator's screen, once |

### Path 1 — file (recommended)

```bash
# The operator generates the secret and writes it to a file only they can read.
umask 077
head -c 24 /dev/urandom | base64 > /run/secrets/admin_pw

SEED_ADMIN_PASSWORD_FILE=/run/secrets/admin_pw \
  php artisan db:seed --class=Database\\Seeders\\UserSeeder --force
```

The seeder **deletes the file immediately after reading it**, so a leftover plaintext
secret cannot become a permanent credential. Verified: after seeding, the file was gone.

### Path 2 — environment variable

```bash
SEED_ADMIN_PASSWORD='…' php artisan db:seed --class=Database\\Seeders\\UserSeeder --force
```

Accepted, but it is the weaker option: environment variables are readable by any
process running as the same user and can leak into crash dumps, process listings and
container inspection output. Use it only when the orchestrator already treats the
variable as a secret.

### Path 3 — generated

```bash
php artisan db:seed --class=Database\\Seeders\\UserSeeder --force
```

Prints a ~144-bit random password once:

```
Generated password — shown ONCE, change it on first login:
    Xk3pQz8mR2vT7bNc4YhL
Copy it now; it is not stored anywhere and cannot be recovered.
```

Nothing is written to disk, and the plaintext is never persisted — only the bcrypt hash
(cost 12) reaches the database.

---

## 3. Strength floor

An operator-supplied secret shorter than **12 characters** is refused outright:

```
SEED_ADMIN_PASSWORD_FILE must be at least 12 characters;
refusing to create a weak superadmin.
```

The seeder throws before creating the account, so a rejected secret leaves the system
without an admin rather than with a weak one. Verified: no `admin` row was created.

---

## 4. It is single-use

Whichever path is used, the account is created with `must_change_password = true`.
The `password.settled` middleware then refuses every application route until the
password is changed, redirecting to `/password`. Verified end-to-end:

```
T-AUTH-12   must_change_password user blocked from /customers
            -> 302 -> http://localhost:8080/password
T-AUTH-13   /password reachable while change required -> 200
```

So even if the bootstrap secret leaked, its window is the time between seeding and the
operator's first login — not the lifetime of the installation.

### What the operator does

1. Run the seeder by one of the three paths above.
2. Log in as `admin` with the secret that path produced.
3. You are taken straight to `/password`. Set a new password.
4. The bootstrap secret now authenticates nothing.

The change also rotates the remember token and invalidates other sessions
(`PasswordController`), so a session opened with the bootstrap secret does not survive it.

---

## 5. What changed in this phase

The previous implementation resolved the secret with `getenv()` only. Under the PHP-WASM
runtime used in this sandbox, `getenv()` returns `false` even when the variable is set —
**verified directly**:

```
SEED_ADMIN_PASSWORD='Test-Value-123' php … → getenv: false
```

An operator following the old documentation would set the variable, see a successful
seed, and receive a *random* password instead of the one they chose — with no warning.
The seeder now consults `$_ENV`, then `$_SERVER`, then `getenv()`, and reports which
source it used. The `getenv()`-only path was a silent failure, not merely a limitation.

---

## 6. Development credentials

The local sandbox admin (`DevOnly-Passw0rd-2026`) is **not** a product credential. It is
created by `tools/checks/prepare.php`, which is a development-only script, and it exists
so the browser harness can log in against the local SQLite database. It is:

- git-ignored (`.arena/` and `database/*.sqlite` are excluded),
- never present in a production install,
- regenerated on demand, not stored.

Each regression flow sets its **own** password in its **own** database copy, so the
flows do not depend on it either.

---

## 7. Residual risk and what covers it

| Risk | Mitigation |
|------|-----------|
| Secret leaks via shell history | Path 1 (file) avoids the command line entirely |
| Secret visible in `ps`/`/proc` | Path 1 |
| Secret persists on disk after install | File is deleted by the seeder after reading |
| Weak operator-chosen secret | 12-character floor, hard refusal |
| Leaked secret stays valid | Forced change at first login; bootstrap secret dies there |
| Secret in a backup | Only the bcrypt hash is ever stored, so a database backup contains no plaintext |
| Secret in logs | The generated password is printed to the console once; nothing is written to the log channel |

**Not covered:** there is no password-policy complexity rule beyond length, and no
breach-corpus check (that needs network access at seeding time). Both are candidates for
a later phase; length alone is the single strongest predictor and is what is enforced now.
