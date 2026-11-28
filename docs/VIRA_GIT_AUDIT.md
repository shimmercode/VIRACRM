# VIRA CRM — Git Audit

**Date:** 2026-11-22
**Branch:** `arena/3b43de84-viracrm`
**HEAD on arrival:** `1357871` (the directive reported `349b1fc` — four commits stale)
**HEAD after this round:** `48f3c9e8dcaac7b285d4b21448d6c14d1d6a2c43`

---

## 1. Why HEAD was not what the directive said

The directive said "Latest reported fix: `349b1fc`" and instructed inspecting
HEAD rather than assuming. It was right to instruct that. The actual HEAD was
`1357871`, four commits later:

| Commit | What it added |
|---|---|
| `349b1fc` | `composer-lock` marked required (runner-contract caught it) |
| `9f78165` | Regression report updated to 73 suites; the failing run recorded |
| `22a34ae` | False pass in the wrong-key restore test fixed (BDM-14) |
| `420eb67` | Stale matrix count corrected |
| `1357871` | Definitive regression result: 73/73, 1895 assertions, exit 0 |

Starting from `349b1fc` would have discarded the false-pass fix and the
1895-assertion evidence.

---

## 2. Commits this round

```
48f3c9e Fix a shared-database mutation my own TLS suite caused
9729ad5 Add REQ-10.09 to the catalog so the tracker can carry it
3d88092 Add the contractual backup requirement that was never tracked
```

| Commit | Change |
|---|---|
| `3d88092` | REQ-12.07 investigated — it is the reminder scheduler. The contractual backup clause at §10 line 221 was untracked. REQ-15.02 misattribution corrected. `tls-proxy.mjs` added (Gate 4). |
| `9729ad5` | `REQ-10.09` added to the catalog — the tracker correctly refused a status row with no requirement text. |
| `48f3c9e` | Shared-database mutation my own TLS suite caused, fixed and guarded. |

Plus the seven deliverable documents in the final commit.

---

## 3. Scope verification — what did NOT change

The gate directive forbids unrelated product work. Verified mechanically:

```
$ git diff --name-only 1357871..HEAD | grep -E '^(app|config|routes|database/migrations|resources|public)/'
(no output)
```

**No application code, config, route, migration, view, asset or theme changed.**
The diff is tests, documentation, and requirement data only:

```
 14 files changed, 2200 insertions(+), 28 deletions(-)
```

---

## 4. Tree state

```
$ git status --porcelain
(empty — clean)
```

The branch is pushed to `origin/arena/3b43de84-viracrm`.

---

## 5. A note on the ignored database

`database/database.sqlite` is **gitignored** (`database/.gitignore:1`). During
this round a test mutated it (484 → 490 rows) and it was recoverable only because
a fixture copy happened to predate the mutation and matched the reference digest
`69d8b91f7ef3ba239287c14eead1cbe54c6e41b939912a9698f880ae12d31d9e` byte for byte.

Restored, verified by digest, and the causing bug fixed with an isolation guard
(`TLS-ISOLATION`). But the underlying fragility remains and is recorded here:

**The reference database state has no version-controlled backup.** If it is lost
or mutated with no matching copy to hand, it cannot be reconstructed from git.
The fixes are either to commit it (large, binary, against the repo's convention)
or to make it reproducible from `migrate` + `db:seed` + a documented fixture seed.
The second is the correct answer and is **not done** — it is out of this
directive's scope and is reported, not fixed.
