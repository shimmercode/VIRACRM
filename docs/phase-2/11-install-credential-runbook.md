# Install credential runbook — the superadmin password

**Status:** implemented, simulated end to end on 2026-11-22
**Covers:** how the secret is generated, delivered, stored, consumed, recovered, and what
happens when each step fails.

> **No real password appears in this document, in Git, in a screenshot, or in any command
> output recorded here.** Everything below is a mechanism and a measured property of it.

---

## 1. Answering each question directly

| Question | Answer |
|----------|--------|
| **How is the initial password generated?** | Three ways in precedence order: `SEED_ADMIN_PASSWORD_FILE` (operator-supplied file), `SEED_ADMIN_PASSWORD` (environment), or nothing set → `random_bytes(18)` base64url ≈ **144 bits**, produced by the seeder. |
| **How does the admin receive it securely?** | Path 1: the operator *writes* it, so they already hold it, and the seeder reads it from a `0600` file they control. Path 3: it is printed **once** to the terminal the operator is watching — nowhere else. |
| **Where does the file go?** | Anywhere the operator chooses; `/run/secrets/admin_pw` is the recommended location (present in RAM, not on persistent disk). The path is passed in `SEED_ADMIN_PASSWORD_FILE`. |
| **What are the file's permissions?** | `0600` (`-rw-------`), created under `umask 077`. Verified: `step 1 … mode: 0600`. |
| **When is the file deleted?** | Immediately after the seeder reads it — before the account is created. Verified: `step 3 … exists after seeding: no — deleted`. |
| **What if the install stops halfway?** | The file is inert. Nothing reads it until the seeder runs, so a stalled install leaves a file but no account. Re-running the seeder consumes it once and deletes it. |
| **Is an account created with a wrong or weak password?** | **No.** Three cases were executed: 5-character secret → refused, `admin rows created: 0`; missing file → refused, `0`; empty file → refused. The check runs **before** `User::create`. |
| **Does the first login force a change?** | **Yes**, on every path (`must_change_password = 1`). Verified at HTTP level: `T-AUTH-12 must_change_password user blocked from /customers -> 302 -> /password`. |
| **How is a lost credential recovered?** | `php artisan vira:reset-admin-password --generate` (or `--password-file`). Sets `must_change_password = 1`, resets the failed-attempt counter, and invalidates the account's sessions. |

---

## 2. The three provisioning paths

### Path 1 — file (recommended)

```bash
umask 077
head -c 24 /dev/urandom | base64 > /run/secrets/admin_pw

SEED_ADMIN_PASSWORD_FILE=/run/secrets/admin_pw \
  php artisan db:seed --class='Database\Seeders\UserSeeder' --force
```

**Why this is first:** the secret never appears on a command line (so it is not in shell
history, and not in `ps` output), never enters the process environment (so it is not in
`/proc/<pid>/environ` or inherited by child processes), and is removed from disk as soon as
it has been used.

### Path 2 — environment variable

```bash
SEED_ADMIN_PASSWORD='…' php artisan db:seed --class='Database\Seeders\UserSeeder' --force
```

Accepted for orchestrators that already inject secrets as environment variables. Weaker: any
process running as the same user can read it, and it can leak into crash dumps and container
inspection. Use only when the platform already treats that variable as a secret.

### Path 3 — generated

```bash
php artisan db:seed --class='Database\Seeders\UserSeeder' --force
```

Prints a ~24-character random password **once**, with an explicit warning that it cannot be
recovered. Appropriate for an interactive install where the operator is at the terminal.

---

## 3. What the seeder guarantees

| Guarantee | How | Verified |
|-----------|-----|----------|
| No plaintext in the database | `Hash::make()` — bcrypt cost 12 | `stored hash prefix: $2y$12$` |
| No plaintext in any log | no `Log::` call in the seeder or the reset command | grep: no logging statements |
| Minimum strength | 12 characters, refused before `create()` | 3 failure cases, `admin rows created: 0` |
| Single use | `must_change_password = true` | `must_change_password: 1` on all paths |
| File not reused | `@unlink($file)` after reading | file gone after seeding |
| Idempotent | refuses to touch an existing admin | "Superadmin already exists; left untouched." |
| Environment carrier-agnostic | reads `$_ENV`, `$_SERVER`, then `getenv()` | see §5 |

---

## 4. Recovery — the case that had no answer before

`UserSeeder` refuses to modify an existing account, which is right for seeding but left no
supported way back in after a lost credential. The only options were editing the database by
hand or deleting the account.

`app/Console/Commands/ResetAdminPassword.php`:

```bash
# Shell access to the host is required — this is deliberately not an HTTP route.
php artisan vira:reset-admin-password --generate
php artisan vira:reset-admin-password --password-file=/run/secrets/admin_pw
```

| Property | Behaviour |
|----------|-----------|
| Where the secret comes from | `--password-file` (deleted after reading) or `--generate`. **Never an argument** — arguments are visible in `ps`. |
| If neither flag is given | Refuses, with usage. It will not invent a password silently. |
| After the reset | `must_change_password = 1`, `failed_login_attempts = 0`, account sessions deleted. |
| Does it print the secret? | Only on `--generate`, once, to the terminal. The `--password-file` path never echoes it — verified: `secret appears in output: no`. |
| Does it log the secret? | No. |

**Verified end to end:** `before must_change: 0` → command → `after must_change: 1`, exit 0.

---

## 4b. Shell history, terminal logging and process arguments

The review asked for the one-shot print path to be examined on three surfaces. Measured
against the implementation, not assumed:

### Shell history

| Path | Enters shell history? | Why |
|------|----------------------|-----|
| `--generate` | **No secret.** The command line contains only the flag. | `vira:reset-admin-password --generate` has no secret in it; the value is produced inside PHP and printed to stdout. |
| `--password-file=/path` | **Path only.** | The argument is a filesystem path. The secret lives in the file. |
| Seeder with `SEED_ADMIN_PASSWORD_FILE` | **Path only.** | Same reasoning. |
| Seeder with `SEED_ADMIN_PASSWORD='…'` | **Yes — the secret is in history.** | This is the acknowledged weakness of path 2, and why it is not the recommended path. |
| `head -c 24 /dev/urandom \| base64 > /run/secrets/admin_pw` | **No secret.** | The redirect happens in the shell; the value never appears as an argument. |

**Rule for operators:** never put a secret after `=` on a command line. Use a redirection
(path 1) or `--generate`. Both are documented above as the recommended paths.

### Terminal logging

| Surface | Exposure |
|---------|----------|
| The terminal itself | On `--generate`, the secret is printed **once**, by design — the operator must be able to read it. Anyone watching the screen sees it. This is why path 1 exists for unattended installs. |
| `script` / `tee` / CI job logs | If the operator pipes output to a log, a generated secret is captured. **Mitigation:** the file path never prints the secret (`secret appears in output: no`, asserted), and `--generate` is not used in automation. |
| Application logs | **None.** Neither the seeder nor the reset command contains a `Log::` call (verified by grep). A secret cannot reach `storage/logs/`. |
| Overflow / crash dumps | A generated secret lives in a local variable only. It is never written to a file, a cache or a session. |

### Process arguments

`ps` and `/proc/<pid>/cmdline` show the full command line to any user who can run `ps`.

| Invocation | Visible in `ps` |
|------------|-----------------|
| `vira:reset-admin-password --generate` | the flag only — **no secret** |
| `vira:reset-admin-password --password-file=/run/secrets/admin_pw` | the **path** — no secret |
| `SEED_ADMIN_PASSWORD='…' php artisan …` | the **secret**, because it is an argument of `php` |

That last row is the concrete reason path 1 is the recommendation. It is also why the reset
command deliberately has **no** `--password=` option: offering one would create a convenient
way to put a credential in `ps` and in history.

### `/run/secrets/admin_pw` and mode 0600

| Property | Assessment |
|----------|------------|
| Location | `/run` is a **tmpfs**: RAM-backed, cleared on reboot, not written to disk. A credential there does not survive a power cycle and is not in any backup. |
| Permissions | `0600` (`rw-------`), owner = the deploying user. Only that user (and root) can read it. Verified in the install simulation: `mode: 0600`. |
| Directory | `/run/secrets/` should itself be `0700`. It is created by the operator or by the platform's secret manager. |
| Lifetime | Deleted by the seeder immediately after it is read, so it exists only between "written" and "consumed". |
| Caveat | `tmpfs` means the file is **gone after a reboot**. If the install is interrupted by a reboot, the operator regenerates — which is the correct behaviour: a credential that persists across reboots is a credential nobody remembers creating. |

**Verified in this phase:** `file: /run/secrets/admin_pw`-equivalent path, `mode: 0600`,
`on disk but not yet consumed: yes`, then `exists after seeding: no — deleted`.

## 4c. Operational prerequisite: shell access

**Recovery requires shell access to the host.** `vira:reset-admin-password` is an artisan
command, deliberately not an HTTP route. This is a **deployment prerequisite**, not a bug:

- A host with no shell (restricted shared hosting, a container with no exec) has **no
  supported recovery path** if the superadmin credential is lost.
- The workaround on such a host would be restoring a database backup or asking the hosting
  provider to run the command — both are slower and both are out of scope here.

**This must be confirmed before deployment:** pick a host where the operator can run
`php artisan`. That is also the same access level required to install the application, so in
practice a host that could deploy it can recover it.

**No web endpoint for password reset exists, and none was added.** A reset endpoint would be
a second authentication surface to defend against enumeration, CSRF and brute force, for an
event that happens approximately never. The requirement does not ask for self-service reset.

## 5. The defect this phase fixed

The seeder resolved the secret with `getenv()` **alone**. Under the PHP-WASM runtime used for
verification, `getenv()` returns `false` even when the variable is exported:

```
SEED_ADMIN_PASSWORD='Test-Value-123' … → getenv: false
```

So an operator who set the variable received a **random** password instead, with no warning.
It now consults `$_ENV`, then `$_SERVER`, then `getenv()`, and reports which source it used.

This matters beyond the sandbox: any environment where `variables_order` excludes `E` (a
common php-fpm hardening setting) will not populate `getenv()` for variables set after start.
Reading all three carriers is the robust behaviour.

---

## 6. Residual risk

| Risk | Status |
|------|--------|
| Secret visible in `ps` | Eliminated on paths 1 and 3; path 2 uses an environment variable, not an argument |
| Secret in shell history | Eliminated on path 1 (redirect) and path 3 (no argument) |
| Secret on disk after install | File deleted by the seeder; path 3 writes nothing |
| Weak operator-chosen secret | Refused below 12 characters |
| Leaked bootstrap secret stays valid | `must_change_password` forces a change at first login |
| Lost credential | `vira:reset-admin-password`, with forced change |
| Stolen active session after a reset | Account's sessions deleted by the reset command |
| **No complexity rule beyond length** | **Open.** Length is the strongest single predictor; character-class rules and breach-corpus checks are not implemented. |
| **No rate limit on the reset command** | **Open.** It requires shell access, which is the same trust level as the database. |

---

## 7. What was deliberately not done

- **Not an HTTP route.** A password-reset web endpoint would be a second authentication
  surface to defend, and the requirement does not ask for self-service reset.
- **No email delivery.** There is no mail transport configured, and adding one to deliver a
  bootstrap credential would create a permanent dependency for a once-per-install event.
- **No default password.** Any fixed fallback would be a credential in the repository.
